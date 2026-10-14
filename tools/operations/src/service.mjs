import { randomUUID } from 'node:crypto';
import { digest, requireThat, safeError, writeAction } from './contracts.mjs';
import { MainwpSession } from './mainwp.mjs';
import { inspectTarget } from './inspect.mjs';
import { capabilityInventory, websiteDetails, portfolioDetails } from './capabilities.mjs';
import { codeInspection } from './code.mjs';
import { inspectContentTarget } from './content-workflow.mjs';
import { providerOperationStatus } from './operation-status.mjs';

export class Operations {
  constructor({ registry, store, connect, fleet, publicProbe, writesEnabled = false, qa = null, recoveryEvidence = null, discovery = null, content = null, verifyStaff = null }) {
    Object.assign(this, { registry, store, connect, fleet, publicProbe, writesEnabled, qa, recoveryEvidence, discovery, content, verifyStaff }); this.jobs = new Map();
  }
  async session(actor, target, action, operation = '', onMutation, checkAdmission) {
    this.registry.current(actor, target, action);
    checkAdmission?.();
    requireThat(target.management === 'mainwp', 'ROUTE_UNAVAILABLE', 'This environment uses a documented dedicated route; MainWP is not required or used.');
    const client = await this.connect(actor);
    return new MainwpSession(client, this.registry, this.store, actor, target, { action, operation, onMutation, checkAdmission });
  }
  view(record) {
    if (record.type === 'inspection') return { ...record, target: this.targetView(record.target) };
    if (record.workflow === 'content') {
      const p = record.plan?.provider;
      return { id: record.id, type: record.type, workflow: 'content', target: this.targetView(record.target), status: record.status,
        requestedBy: record.requestedBy, approvedBy: record.approvedBy, executedBy: record.executedBy, planDigest: record.planDigest, expiresAt: record.expiresAt,
        error: record.error, result: record.result, qa: record.qaTicket && { url: record.qaTicket.url, runId: record.qaTicket.runId },
        preview: p && { target: p.target, before: p.before_snapshot[p.target.field], after: p.candidate_snapshot[p.target.field],
          beforeSha256: p.before_sha256, afterSha256: p.candidate_sha256, reason: record.plan.description, affectedPaths: p.affected_paths,
          migration: p.migration_artifact, requestedValueNormalized: record.plan.requestedValueNormalized, rollbackOf: p.rollback_operation_id || undefined },
        next: record.status === 'prepared' ? 'Review the exact QA-qualified content preview, then approve_operation and execute_operation.'
          : ['uncertain', 'approval_uncertain'].includes(record.status) ? 'The provider outcome needs qualified reconciliation. Do not retry or clear locks.' : undefined };
    }
    if (record.workflow === 'code') return { id: record.id, type: record.type, workflow: 'code', target: this.targetView(record.target), status: record.status,
      requestedBy: record.requestedBy, approvedBy: record.approvedBy, executedBy: record.executedBy, planDigest: record.planDigest, expiresAt: record.expiresAt,
      error: record.error, result: record.result, qa: record.qaTicket && { url: record.qaTicket.url, runId: record.qaTicket.runId },
      sourceQa: record.sourceQaTicket && { url: record.sourceQaTicket.url, runId: record.sourceQaTicket.runId },
      preview: record.plan && { path: record.plan.path, beforeSha256: record.plan.beforeSha256, afterSha256: record.plan.newSha256,
        before: record.plan.beforeContent, after: record.plan.content, reason: record.plan.description, source: record.plan.source,
        pullRequest: record.plan.proposal?.url, rollbackOf: record.plan.rollbackOf || undefined },
      next: record.status === 'source_review' ? 'Review the exact source PR and approve_code_source in chat. Merging source does not approve a live change.'
        : record.status === 'prepared' ? 'Review the QA-qualified exact preview, then approve_operation and execute_operation with that plan digest.'
          : ['source_uncertain', 'uncertain'].includes(record.status) ? 'The dispatched outcome needs operator reconciliation. Do not retry or clear locks.' : undefined };
    return { id: record.id, type: record.type, target: this.targetView(record.target), status: record.status,
      requestedBy: record.requestedBy, approvedBy: record.approvedBy, executedBy: record.executedBy,
      createdAt: record.createdAt, updatedAt: record.updatedAt, expiresAt: record.expiresAt,
      planDigest: record.planDigest, findingId: record.findingId, error: record.error, result: record.result,
      reconciliation: record.reconciliation,
      recoverySnapshotDigest: ['running', 'uncertain'].includes(record.status) ? digest(record) : undefined,
      plan: record.plan && { kind: record.plan.kind, description: record.plan.description, sourceCommit: record.plan.sourceCommit,
        artifactSha256: record.plan.artifactSha256, recovery: record.plan.recovery, reverses: record.plan.reverses },
      next: ['running', 'uncertain'].includes(record.status) ? 'Follow running work. If interrupted, an operator must attest quiescence before reconcile_operation can verify state and release the lock. Do not retry the write.' :
        record.status === 'reconciled' ? 'Current code was reconciled; the original execution and backup are not retrospectively verified. Any new change needs a fresh plan and its normal gates.' : undefined };
  }
  targetView(t) { return { websiteId: t.websiteId, name: t.name, environment: t.environment, url: t.url }; }
  get(actor, id) { const record = this.store.get(id); this.registry.authorize(actor, record.target, 'read'); return this.view(record); }
  async refreshOperation(actor, id) {
    const record = this.store.get(id);
    const authorize = async () => {
      this.registry.current(actor, record.target, 'read');
      requireThat(actor.identityProvider === 'google-oauth2' && this.verifyStaff, 'AUTH_REQUIRED', 'Use your active MRN Google identity to inspect provider operations.');
      await this.verifyStaff(actor);
    };
    await authorize();
    requireThat(['code', 'content'].includes(record.workflow) && record.plan?.provider, 'OPERATION_STATUS_UNAVAILABLE', 'This operation has no retained MainWP code/content plan to inspect.');
    const session = await this.session(actor, record.target, 'read', record.id);
    try {
      const providerStatus = await providerOperationStatus(session, record);
      await authorize();
      return { ...this.get(actor, id), providerStatus };
    } finally { await session.close(); }
  }
  async list(actor) {
    await this.discovery?.refresh(actor, { force: true });
    const websites = this.registry.list(actor);
    if (!this.discovery) return websites;
    const snapshot = this.registry.discovered.get(actor.subject);
    return { websites, sources: snapshot?.coverage || [], issues: snapshot?.issues || [] };
  }
  async capabilities(actor) {
    const authorize = () => {
      const member = this.registry.member(actor);
      requireThat(member.portfolio?.actions.includes('read') || member.grants.some(g => g.actions.includes('read')), 'FORBIDDEN', 'Website read access is required.');
    };
    authorize(); const client = await this.connect(actor);
    try { return await capabilityInventory(client, authorize); } finally { await client.close(); }
  }
  async portfolioDetails(actor, input) {
    const authorize = () => {
      const member = this.registry.member(actor);
      requireThat(member.portfolio?.actions.includes('read') && member.portfolio.sources.includes('mainwp'),
        'FORBIDDEN', 'Portfolio read permission is required for client and tag directories.');
    };
    authorize(); const client = await this.connect(actor); const target = { url: 'mainwp://portfolio' };
    this.store.audit(actor, target, '', 'portfolio:read', 'attempted');
    try {
      const result = await portfolioDetails(client, authorize, input);
      this.store.audit(actor, target, '', 'portfolio:read', 'returned'); return result;
    } catch (error) { this.store.audit(actor, target, '', 'portfolio:read', 'failed'); throw error; }
    finally { await client.close(); }
  }
  async details(actor, { website, environment, section }) {
    await this.discovery?.refresh(actor);
    const target = this.registry.resolve(actor, website, environment);
    const session = await this.session(actor, target, 'read');
    try { return await websiteDetails(session, section); } finally { await session.close(); }
  }
  async readContent(actor, { website, environment, kind, recordId, search, maximum }) {
    requireThat(this.content, 'CONTENT_UNAVAILABLE', 'The central MainWP content integration is not configured.');
    await this.discovery?.refresh(actor);
    const target = this.registry.resolve(actor, website, environment);
    const session = await this.session(actor, target, 'read');
    try {
      const site = await session.fresh();
      this.store.audit(actor, target, '', 'content:read', 'attempted');
      const authorize = async () => { this.registry.current(actor, target, 'read'); await this.verifyStaff?.(actor); };
      const result = recordId ? await this.content.read(site, kind, recordId, authorize) : await this.content.search(site, kind, search, maximum, authorize);
      await authorize();
      this.store.audit(actor, target, '', 'content:read', 'returned');
      return result;
    } catch (error) { this.store.audit(actor, target, '', 'content:read', 'failed'); throw error; }
    finally { await session.close(); }
  }
  async inspectCode(actor, { website, environment, path }) {
    requireThat(actor.identityProvider === 'google-oauth2', 'AUTH_REQUIRED', 'Use your MRN Google identity for code inspection.');
    await this.discovery?.refresh(actor);
    const target = this.registry.resolve(actor, website, environment);
    const session = await this.session(actor, target, 'read');
    try { return await codeInspection(session, path); } finally { await session.close(); }
  }
  async inspectContent(actor, { website, environment, target: contentTarget }) {
    requireThat(actor.identityProvider === 'google-oauth2', 'AUTH_REQUIRED', 'Use your verified MRN Google identity.');
    await this.discovery?.refresh(actor);
    const target = this.registry.resolve(actor, website, environment);
    const session = await this.session(actor, target, 'read');
    try {
      const { site, policy, inspected } = await inspectContentTarget(session, contentTarget);
      return { site, ...inspected, policy: { enabled: policy.enabled, profile: policy.profile, policySha256: policy.policy_sha256 },
        qualification: 'Ownership and a readable snapshot alone do not approve a change. Use prepare_content_edit for protected QA and the exact preview.' };
    } finally { await session.close(); }
  }
  async prepareContent(actor, input) {
    requireThat(this.contentWorkflow, 'CONTENT_WORKFLOW_UNAVAILABLE', 'The protected content workflow is not configured.');
    return this.contentWorkflow.prepare(actor, input);
  }
  async prepareCode(actor, input) {
    requireThat(this.codeWorkflow, 'CODE_WORKFLOW_UNAVAILABLE', 'The protected code workflow is not provisioned on this service.');
    return this.codeWorkflow.prepare(actor, input);
  }
  async approveCodeSource(actor, input) {
    requireThat(this.codeWorkflow, 'CODE_WORKFLOW_UNAVAILABLE', 'The central source workflow is not provisioned.');
    return this.codeWorkflow.approveSource(actor, input);
  }
  async history(actor, query, environment) {
    await this.discovery?.refresh(actor);
    const target = this.registry.resolve(actor, query, environment);
    return this.store.history(target).map(r => this.view(r));
  }
  async inspect(actor, { website, environment }) {
    await this.discovery?.refresh(actor);
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
    await this.discovery?.refresh(actor);
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
  async approve(actor, { operationId, planDigest }) {
    await this.discovery?.refresh(actor);
    const op = this.store.get(operationId);
    if (op.workflow === 'code') { requireThat(this.codeWorkflow, 'CODE_WORKFLOW_UNAVAILABLE', 'Code workflow is unavailable.'); return this.codeWorkflow.approve(actor, op, planDigest); }
    if (op.workflow === 'content') { requireThat(this.contentWorkflow, 'CONTENT_WORKFLOW_UNAVAILABLE', 'Content workflow is unavailable.'); return this.contentWorkflow.approve(actor, op, planDigest); }
    this.registry.current(actor, op.target, writeAction(op.target));
    return this.view(this.store.approve(operationId, actor, planDigest));
  }
  async execute(actor, { operationId }) {
    await this.discovery?.refresh(actor);
    const op = this.store.get(operationId); this.registry.current(actor, op.target, ['code', 'content'].includes(op.workflow) ? op.workflow : writeAction(op.target));
    if (op.workflow === 'code') requireThat(this.codeWorkflow && op.requestedBy === actor.subject, 'CODE_OPERATOR', 'The original verified operator must execute this code workflow.');
    if (op.workflow === 'content') requireThat(this.contentWorkflow && op.requestedBy === actor.subject, 'CONTENT_OPERATOR', 'The original verified operator must execute this content workflow.');
    requireThat(this.writesEnabled, 'WRITES_DISABLED', 'Hosted write execution is disabled until service qualification and operating acceptance are complete.');
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
      const action = ['code', 'content'].includes(op.workflow) ? op.workflow : writeAction(op.target);
      this.registry.current(actor, op.target, action);
      session = await this.session(actor, op.target, action, op.id, () => { mutationAttempted = true; }, () => this.store.assertRunning(op.id));
      const result = op.workflow === 'code' ? await this.codeWorkflow.execute(actor, op, session)
        : op.workflow === 'content' ? await this.contentWorkflow.execute(actor, op, session) : await this.fleet.execute(op, session);
      this.store.finish(op.id, 'verified', { result }, actor);
    } catch (error) {
      // A reconciled record belongs to the recovery path. Never overwrite it
      // with a late success/failure from the fenced original worker.
      try { this.store.finish(op.id, mutationAttempted ? 'uncertain' : 'failed', { error: safeError(error) }, actor); }
      catch (finishError) { if (!['EXECUTION_FENCED', 'LOCK_CHANGED'].includes(finishError.code)) throw finishError; }
    } finally { await session?.close().catch(() => {}); }
  }
  async reconcile(actor, { operationId }) {
    await this.discovery?.refresh(actor);
    const op = this.store.get(operationId);
    requireThat(!['code', 'content'].includes(op.workflow), 'RECOVERY_UNAVAILABLE', 'Code/content outcomes require the provider’s qualified reconciliation; Fleet recovery cannot release this lock.');
    const target = this.registry.recoveryCurrent(actor, op.target, writeAction(op.target));
    const authorize = () => this.registry.current(actor, target, writeAction(target));
    authorize();
    if (op.status === 'reconciled') return this.view(op);
    requireThat(op.type === 'operation' && ['running', 'uncertain'].includes(op.status), 'RECOVERY_UNAVAILABLE', 'Only interrupted running or uncertain operations can be reconciled.');
    requireThat(!this.jobs.has(op.id), 'OPERATION_ACTIVE', 'The original worker is still running. Wait for it to finish; do not release its lock.');
    requireThat(op.planDigest === digest({ target: op.target, plan: op.plan }), 'PLAN_CHANGED', 'The stored plan no longer matches its approved binding.');
    requireThat(this.recoveryEvidence, 'RECOVERY_EVIDENCE_REQUIRED', 'Configure operator-reviewed quiescence evidence before reconciliation.');
    const proof = this.recoveryEvidence.find(op); const snapshot = digest(op);
    const checkAdmission = () => {
      authorize();
      requireThat(target.coordination?.exclusiveWriter === 'mrn-operations' && Date.parse(target.coordination.validUntil) > Date.now(), 'COORDINATION_REQUIRED', 'Current exclusion of competing writers is required for reconciliation.');
      requireThat(digest(this.store.get(op.id)) === snapshot, 'RECOVERY_STATE_CHANGED', 'The operation changed during reconciliation.');
      this.store.assertLocked(op);
      requireThat(digest(this.recoveryEvidence.find(op)) === digest(proof), 'RECOVERY_EVIDENCE_MISMATCH', 'Quiescence evidence changed during reconciliation.');
    };
    let session;
    try {
      // Read scope forbids preflight, backup, update and rollback even when the
      // caller has release permission. Writes may remain disabled during recovery.
      session = await this.session(actor, target, 'read', op.id, undefined, checkAdmission);
      const result = await this.fleet.reconcile(op, session);
      checkAdmission();
      return this.view(this.store.reconcile(op.id, snapshot, { ...result, quiescence: proof, coordination: target.coordination }, actor));
    } catch (error) {
      this.store.audit(actor, op.target, op.id, 'reconcile', 'blocked'); throw error;
    } finally { await session?.close().catch(() => {}); }
  }
  async prepareRollback(actor, { operationId, requestKey }) {
    await this.discovery?.refresh(actor);
    const previous = this.store.get(operationId);
    if (previous.workflow === 'code') { requireThat(this.codeWorkflow, 'CODE_WORKFLOW_UNAVAILABLE', 'Code workflow is unavailable.'); return this.codeWorkflow.rollback(actor, { operationId, requestKey }); }
    if (previous.workflow === 'content') { requireThat(this.contentWorkflow, 'CONTENT_WORKFLOW_UNAVAILABLE', 'Content workflow is unavailable.'); return this.contentWorkflow.rollback(actor, { operationId, requestKey }); }
    const target = previous.status === 'reconciled' ? this.registry.recoveryCurrent(actor, previous.target, 'repair') : this.registry.current(actor, previous.target, 'repair');
    const updatePresent = previous.status === 'verified' || (previous.status === 'reconciled' && previous.reconciliation?.outcome === 'intended_code_verified');
    requireThat(updatePresent && previous.plan?.direction === 'update', 'ROLLBACK_UNAVAILABLE', 'Choose a verified or reconciled update with retained code recovery evidence.');
    const { record: op, created } = this.store.request(actor, requestKey, { rollbackOf: operationId }, () => this.store.create('operation', actor, target, { status: 'preparing' }));
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
    await this.discovery?.refresh(actor);
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
    await this.discovery?.refresh(actor);
    const target = this.registry.resolve(actor, website, environment, 'test');
    requireThat(this.qa && target.qaProject, 'QA_ROUTE_UNAVAILABLE', 'No approved QA runtime is configured for this environment. Form submission and delivery need an approved test procedure and remain unrun.');
    return this.qa.run(actor, target);
  }
}
