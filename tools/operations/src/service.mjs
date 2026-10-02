import { randomUUID } from 'node:crypto';
import { digest, requireThat, safeError, writeAction } from './contracts.mjs';
import { MainwpSession } from './mainwp.mjs';
import { inspectTarget } from './inspect.mjs';

export class Operations {
  constructor({ registry, store, connect, fleet, publicProbe, writesEnabled = false, qa = null }) {
    Object.assign(this, { registry, store, connect, fleet, publicProbe, writesEnabled, qa }); this.jobs = new Map();
  }
  async session(actor, target, action, operation = '', onMutation) {
    this.registry.current(actor, target, action);
    requireThat(target.management === 'mainwp', 'ROUTE_UNAVAILABLE', 'This environment uses a documented dedicated route; MainWP is not required or used.');
    const client = await this.connect();
    return new MainwpSession(client, this.registry, this.store, actor, target, { action, operation, onMutation });
  }
  view(record) {
    if (record.type === 'inspection') return { ...record, target: this.targetView(record.target) };
    return { id: record.id, type: record.type, target: this.targetView(record.target), status: record.status,
      requestedBy: record.requestedBy, approvedBy: record.approvedBy, executedBy: record.executedBy,
      createdAt: record.createdAt, updatedAt: record.updatedAt, expiresAt: record.expiresAt,
      planDigest: record.planDigest, findingId: record.findingId, error: record.error, result: record.result,
      plan: record.plan && { kind: record.plan.kind, description: record.plan.description, sourceCommit: record.plan.sourceCommit,
        artifactSha256: record.plan.artifactSha256, recovery: record.plan.recovery, reverses: record.plan.reverses },
      next: record.status === 'uncertain' ? 'Reconcile downstream state and prove no job is still running. This website remains locked; do not retry.' : undefined };
  }
  targetView(t) { return { websiteId: t.websiteId, name: t.name, environment: t.environment, url: t.url }; }
  get(actor, id) { const record = this.store.get(id); this.registry.authorize(actor, record.target, 'read'); return this.view(record); }
  list(actor) { return this.registry.list(actor); }
  history(actor, query, environment) {
    const target = this.registry.resolve(actor, query, environment);
    return this.store.history(target).map(r => this.view(r));
  }
  async inspect(actor, { website, environment }) {
    const target = this.registry.resolve(actor, website, environment); let session;
    try {
      if (target.management === 'mainwp') session = await this.session(actor, target, 'read');
      const result = await inspectTarget({ target, session, catalog: this.fleet.catalog(), publicProbe: this.publicProbe,
        authorize: () => this.registry.current(actor, target, 'read'), previous: this.store.history(target, 'inspection')[0] });
      this.registry.current(actor, target, 'read');
      result.findings = result.findings.map(f => ({ ...f, id: randomUUID() }));
      const record = this.store.create('inspection', actor, target, result);
      this.store.audit(actor, target, record.id, 'inspect', 'recorded');
      return this.view(record);
    } finally { await session?.close(); }
  }
  async prepare(actor, { inspectionId, findingId, requestKey }) {
    const inspection = this.store.get(inspectionId); this.registry.current(actor, inspection.target, 'repair');
    requireThat(inspection.type === 'inspection', 'FINDING_REQUIRED', 'Choose a recorded inspection finding.');
    const finding = inspection.findings.find(f => f.id === findingId);
    requireThat(finding?.repairable && finding.code === 'STACK_PLUGIN_BEHIND', 'REPAIR_UNAVAILABLE', 'This finding needs a source, content, configuration or provider workflow that is not yet qualified in this service. No website change was made.');
    const { record: op, created } = this.store.request(actor, requestKey, { inspectionId, findingId },
      () => this.store.create('operation', actor, inspection.target, { status: 'preparing', findingId, inspectionId }));
    if (!created) return this.view(op);
    let session;
    try {
      // A recorded finding is a reference, not permanent authority to modify.
      const fresh = await this.inspect(actor, { website: op.target.url, environment: op.target.environment });
      const stillPresent = fresh.findings.find(f => f.code === finding.code && f.component === finding.component && digest(f.condition) === digest(finding.condition));
      requireThat(stillPresent, 'FINDING_CHANGED', 'The original finding changed or resolved. Review the new inspection before preparing a repair.');
      session = await this.session(actor, op.target, 'repair', op.id);
      op.plan = await this.fleet.plan(op.target, stillPresent, session, op.id);
      op.planDigest = digest({ target: op.target, plan: op.plan }); op.status = 'prepared'; op.expiresAt = Date.now() + 15 * 60000;
      op.revalidatedInspection = fresh.id;
      this.store.audit(actor, op.target, op.id, 'prepare', 'prepared'); this.store.save(op);
    } catch (error) { op.status = 'blocked'; op.error = safeError(error); this.store.save(op); }
    finally { await session?.close(); }
    return this.view(op);
  }
  approve(actor, { operationId, planDigest }) {
    const op = this.store.get(operationId); this.registry.current(actor, op.target, writeAction(op.target));
    return this.view(this.store.approve(operationId, actor, planDigest));
  }
  async execute(actor, { operationId }) {
    const op = this.store.get(operationId); this.registry.current(actor, op.target, writeAction(op.target));
    requireThat(this.writesEnabled, 'WRITES_DISABLED', 'Hosted write execution is disabled until enrollment and operating acceptance are complete.');
    requireThat(op.target.coordination?.exclusiveWriter === 'mrn-operations' && Date.parse(op.target.coordination.validUntil) > Date.now(), 'COORDINATION_REQUIRED', 'Enroll all writers in the same site lock or disable competing deployment routes before enabling this website.');
    requireThat(op.planDigest === digest({ target: op.target, plan: op.plan }), 'PLAN_CHANGED', 'The stored plan does not match its approval.');
    const { record, claimed } = this.store.claim(op.id, actor);
    if (claimed) {
      const job = this.run(actor, record).finally(() => this.jobs.delete(op.id));
      this.jobs.set(op.id, job);
    }
    return this.view(record); // Execution survives an HTTP disconnect.
  }
  async run(actor, op) {
    let session; let mutationAttempted = false;
    try {
      this.registry.current(actor, op.target, writeAction(op.target));
      session = await this.session(actor, op.target, writeAction(op.target), op.id, () => { mutationAttempted = true; });
      const result = await this.fleet.execute(op, session);
      this.store.finish(op.id, 'verified', { result }, actor);
    } catch (error) {
      this.store.finish(op.id, mutationAttempted ? 'uncertain' : 'failed', { error: safeError(error) }, actor);
    } finally { await session?.close().catch(() => {}); }
  }
  async prepareRollback(actor, { operationId, requestKey }) {
    const previous = this.store.get(operationId); this.registry.current(actor, previous.target, 'repair');
    requireThat(previous.status === 'verified' && previous.plan?.direction === 'update', 'ROLLBACK_UNAVAILABLE', 'Choose a verified update with retained code recovery evidence.');
    const { record: op, created } = this.store.request(actor, requestKey, { rollbackOf: operationId }, () => this.store.create('operation', actor, previous.target, { status: 'preparing' }));
    if (!created) return this.view(op);
    let session;
    try {
      session = await this.session(actor, op.target, 'repair', op.id);
      op.plan = await this.fleet.rollbackPlan(previous, session);
      op.planDigest = digest({ target: op.target, plan: op.plan }); op.status = 'prepared'; op.expiresAt = Date.now() + 15 * 60000;
    } catch (error) { op.status = 'blocked'; op.error = safeError(error); }
    finally { await session?.close(); }
    this.store.save(op); return this.view(op);
  }
  async assessFleet(actor, { targets }) {
    requireThat(Array.isArray(targets) && targets.length > 0 && targets.length <= 25, 'TARGET_REQUIRED', 'Select between one and 25 explicit websites; an empty selection never means all.');
    // Authorize the full selection before the first downstream call.
    const resolved = targets.map(t => this.registry.resolve(actor, t.website, t.environment));
    requireThat(new Set(resolved.map(t => t.url)).size === resolved.length, 'TARGET_DUPLICATE', 'Select each environment once.');
    const results = [];
    for (const t of resolved) {
      try { results.push(await this.inspect(actor, { website: t.url, environment: t.environment })); }
      catch (error) { results.push({ target: this.targetView(t), status: 'blocked', error: safeError(error) }); }
    }
    return { results, explanation: 'Assessment only. Inventory and parity never authorize installing or updating components.' };
  }
  async test(actor, { website, environment }) {
    const target = this.registry.resolve(actor, website, environment, 'test');
    requireThat(this.qa && target.qaProject, 'QA_ROUTE_UNAVAILABLE', 'No approved QA runtime is configured for this environment. Form submission and delivery need an approved test procedure and remain unrun.');
    return this.qa.run(actor, target);
  }
}
