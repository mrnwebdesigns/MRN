import { randomUUID } from 'node:crypto';
import { digest, requireThat, safeError } from './contracts.mjs';
import { delegationDigest } from './delegation.mjs';
import { contentTargetSchema, contentCall, contentSnapshot, contentSame, contentPaths, validateContentPlan, validateContentMigration } from './content-contract.mjs';
import { contentQaInput } from './content-qa.mjs';
import { toolNames } from './mainwp.mjs';
import { htmlContentText } from './probe.mjs';

const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
export async function inspectContentTarget(session, input) {
  const target = contentTargetSchema.parse(input), site = await session.fresh();
  const args = { site_id: site.id, site_url: site.url };
  const policy = await contentCall(session, 'get-content-policy-v1', args);
  const inspected = await contentCall(session, 'inspect-content-v1', { ...args, target });
  const snapshot = contentSnapshot(inspected.snapshot);
  requireThat(policy.site_id === site.id && policy.site_url === site.url && snapshot.site_id === site.id && snapshot.site_url === site.url
    && contentSame(snapshot.target, target) && inspected.snapshot_sha256 === delegationDigest(snapshot)
    && inspected.stable_selector.post_type === target.post_type, 'CONTENT_TARGET_MISMATCH', 'MainWP returned a different content record or invalid snapshot.');
  contentPaths(inspected.affected_paths, site.url);
  return { site, policy, inspected };
}
export class ContentWorkflow {
  constructor(service, { qa, delay = pause }) { Object.assign(this, { service, qa, delay }); }
  authorize(actor, target) {
    return async () => {
      this.service.registry.current(actor, target, 'content');
      requireThat(actor.identityProvider === 'google-oauth2' && this.service.verifyStaff, 'AUTH_REQUIRED', 'Content changes require live verified Google Workspace membership.');
      await this.service.verifyStaff(actor);
    };
  }
  qualifyPolicy(evidence, target) {
    requireThat(evidence.policy.enabled && evidence.policy.profile && evidence.policy.supported_target_kinds.includes(target.kind)
      && evidence.inspected.ownership === 'wordpress-post', 'CONTENT_OWNERSHIP', 'Content editing is disabled or its rendering ownership is unqualified.');
    requireThat(evidence.policy.qa_keys.some(k => k.id === this.qa.policy.keyId && k.public_key_base64 === this.qa.policy.publicKeyBase64), 'QA_KEY', 'The provider has not admitted this protected content signer.');
    return this.qa.profile(evidence.site.url, target, evidence.policy.profile);
  }
  bind(session, plan) { session.contentOperation = { id: plan.operation_id, digest: plan.digest }; }
  async prepare(actor, input, previous = null) {
    const s = this.service; await s.discovery?.refresh(actor);
    const target = s.registry.resolve(actor, input.website, input.environment, 'content'), authorize = this.authorize(actor, target); await authorize();
    contentTargetSchema.parse(input.target);
    requireThat(target.backup === 'updraft', 'BACKUP_UNQUALIFIED', 'Content changes need a qualified remote database-backup route.');
    requireThat(typeof input.newValue === 'string' && input.newValue.isWellFormed() && !input.newValue.includes('\0') && Buffer.byteLength(input.newValue) <= 8192
      && typeof input.reason === 'string' && input.reason.length > 0 && input.reason.length <= 1000, 'CONTENT_INPUT', 'Provide a bounded UTF-8 content value and reason.');
    const { record: op, created } = s.store.request(actor, input.requestKey, { content: input, rollbackOf: previous?.id || null },
      () => s.store.create('operation', actor, target, { workflow: 'content', status: 'preparing' }));
    if (!created) return s.view(op);
    let session;
    try {
      session = await s.session(actor, target, 'content', op.id);
      const evidence = await inspectContentTarget(session, input.target); this.qualifyPolicy(evidence, input.target);
      requireThat(evidence.inspected.snapshot_sha256 === input.expectedSnapshotSha256, 'CONTENT_PRECONDITION', 'The content changed. Inspect the exact record again before preparing.');
      requireThat(evidence.inspected.snapshot[input.target.field] !== input.newValue, 'NO_CHANGE', 'The requested value is already present.');
      let plan;
      if (previous) {
        this.bind(session, previous.plan.provider);
        plan = await contentCall(session, 'prepare-content-rollback-v1', { operation_id: previous.plan.provider.operation_id,
          expected_snapshot_sha256: input.expectedSnapshotSha256, reason: input.reason });
      } else plan = await contentCall(session, 'prepare-content-change-v1', { site_id: evidence.site.id, site_url: evidence.site.url,
        target: input.target, expected_snapshot_sha256: input.expectedSnapshotSha256, new_value: input.newValue, reason: input.reason });
      validateContentPlan(plan, { ...evidence, inspected: evidence.inspected, target: input.target, profile: evidence.policy.profile, rollbackOf: previous?.plan.provider.operation_id || '' });
      if (previous) requireThat(contentSame(plan.candidate_snapshot, previous.plan.provider.before_snapshot), 'CONTENT_ROLLBACK', 'Rollback did not retain the exact original content snapshot.');
      this.bind(session, plan);
      const migration = validateContentMigration(plan, await contentCall(session, 'get-content-migration-v1', { operation_id: plan.operation_id }));
      op.plan = { kind: 'content', description: input.reason, provider: plan, policySha256: evidence.policy.policy_sha256,
        migration, requestedValueNormalized: plan.candidate_snapshot[input.target.field] !== input.newValue };
      op.qaRequest = this.qa.qualifyRequest(contentQaInput(plan, randomUUID().replaceAll('-', '')));
      op.status = 'qualifying'; s.store.save(op);
      const job = this.qualify(actor, op).finally(() => s.jobs.delete(op.id)); s.jobs.set(op.id, job);
    } catch (error) { op.status = 'blocked'; op.error = safeError(error); s.store.save(op); }
    finally { await session?.close(); }
    return s.view(s.store.get(op.id));
  }
  async fresh(session, op) {
    const p = op.plan.provider, evidence = await inspectContentTarget(session, p.target); this.qualifyPolicy(evidence, p.target);
    requireThat(evidence.policy.policy_sha256 === op.plan.policySha256 && contentSame(evidence.policy.profile, p.profile)
      && contentSame(evidence.inspected.snapshot, p.before_snapshot) && contentSame(evidence.inspected.affected_paths, p.affected_paths)
      && contentSame(evidence.inspected.stable_selector, p.stable_selector), 'CONTENT_PRECONDITION', 'Content, ownership or qualification changed after preparation.');
    return evidence;
  }
  proof(op) {
    this.qa.qualifyRequest(op.qaRequest);
    requireThat(op.plan.provider.expires_at * 1000 > Date.now(), 'PLAN_EXPIRED', 'The prepared content proposal expired.');
    return this.qa.verifyReceipt(op.qaProof.envelope, op.qaRequest, op.qaTicket.runId);
  }
  async qualify(actor, op) {
    const s = this.service, authorize = this.authorize(actor, op.target); let session;
    try {
      await authorize();
      op.qaTicket = await this.qa.dispatch(op.qaRequest, authorize, state => { op.qaProgress = state; s.store.save(op); }); s.store.save(op);
      const deadline = Math.min(op.plan.provider.expires_at * 1000, Date.now() + 15 * 60000); let proof;
      while (Date.now() < deadline) {
        proof = await this.qa.receipt(op.qaTicket, op.qaRequest, authorize);
        if (proof.status === 'qualified') break;
        await this.delay(10000);
      }
      requireThat(proof?.status === 'qualified', 'QA_PENDING', 'No fresh protected content receipt was produced before the plan expired.');
      op.qaProof = proof; this.proof(op); await authorize();
      session = await s.session(actor, op.target, 'content', op.id); await this.fresh(session, op);
      op.planDigest = digest({ target: op.target, plan: op.plan }); op.expiresAt = Math.min(op.plan.provider.expires_at, proof.claims.expires_at) * 1000;
      op.status = 'prepared'; s.store.audit(actor, op.target, op.id, 'content:prepare', 'prepared'); s.store.save(op);
    } catch (error) { op.status = 'blocked'; op.error = safeError(error); s.store.save(op); }
    finally { await session?.close().catch(() => {}); }
  }
  async approve(actor, op, planDigest) {
    const s = this.service; await this.authorize(actor, op.target)(); this.proof(op);
    requireThat(op.planDigest === planDigest && planDigest === digest({ target: op.target, plan: op.plan }) && op.expiresAt > Date.now(), 'PLAN_CHANGED', 'Review the exact current content preview.');
    const claimed = s.store.transaction(() => {
      const current = s.store.get(op.id);
      requireThat(current.planDigest === planDigest, 'PLAN_CHANGED', 'Content approval changed.');
      if (current.status === 'approved') return false;
      requireThat(current.status === 'prepared', 'OPERATION_ACTIVE', 'Content approval is already pending or unavailable.');
      current.status = 'approving'; s.store.audit(actor, op.target, op.id, 'content:approve', 'attempted'); s.store.save(current); return true;
    });
    if (!claimed) return s.view(s.store.get(op.id));
    let session;
    try {
      session = await s.session(actor, op.target, 'content', op.id); await this.fresh(session, op); this.bind(session, op.plan.provider);
      const result = await contentCall(session, 'approve-content-change-v1', { operation_id: op.plan.provider.operation_id, reviewed_digest: op.plan.provider.digest,
        qa_receipt_json: JSON.stringify(op.qaProof.envelope), qa_evidence_sha256: op.qaProof.claims.qa_report_sha256 });
      requireThat(result.operation_id === op.plan.provider.operation_id && result.digest === op.plan.provider.digest && result.approved_by === actor.verifiedEmail
        && result.expires_at * 1000 > Date.now() && result.expires_at <= op.plan.provider.expires_at, 'CONTENT_APPROVAL', 'The provider did not verify this person’s exact approval.');
      const current = s.store.get(op.id); requireThat(current.status === 'approving', 'OPERATION_ACTIVE', 'Approval state changed during dispatch.');
      current.expiresAt = Math.min(current.expiresAt, result.expires_at * 1000); current.status = 'prepared'; s.store.save(current);
      return s.view(s.store.approve(op.id, actor, planDigest));
    } catch (error) { const current = s.store.get(op.id); current.status = 'approval_uncertain'; current.error = safeError(error); s.store.save(current); throw error; }
    finally { await session?.close(); }
  }
  async execute(actor, op, session) {
    const s = this.service, authorize = this.authorize(actor, op.target); await authorize();
    requireThat(op.requestedBy === actor.subject, 'CONTENT_OPERATOR', 'The original active operator must execute the content change.');
    this.proof(op); const before = await this.fresh(session, op), p = op.plan.provider; this.bind(session, p);
    const started = await session.call(toolNames.backup, { site_id: before.site.id });
    requireThat(started.site_id === before.site.id && /^[a-f0-9]+$/i.test(started.nonce), 'BACKUP_INVALID', 'The remote backup did not identify this exact site.');
    const deadline = Math.min(op.expiresAt, Date.now() + 10 * 60000); let receipt;
    while (Date.now() < deadline) {
      const result = await session.call(toolNames.backupStatus, { site_id: before.site.id, nonce: started.nonce });
      requireThat(result.status !== 'failed', 'BACKUP_FAILED', 'The required remote database backup failed.');
      if (result.status === 'complete' && typeof result.receipt === 'string' && result.receipt) { receipt = { site_id: before.site.id, nonce: started.nonce, receipt: result.receipt }; break; }
      await this.delay(5000);
    }
    requireThat(receipt, 'BACKUP_UNVERIFIED', 'The remote database backup was not verified before expiry.');
    await authorize(); this.proof(op); await this.fresh(session, op);
    const result = await contentCall(session, 'execute-content-change-v1', { operation_id: p.operation_id, reviewed_digest: p.digest, confirm: true, backup_receipt: receipt });
    requireThat(result.operation_id === p.operation_id && result.state === 'completed' && result.error_code === null && typeof result.changed === 'boolean'
      && result.site_id === p.site_id && result.site_url === p.site_url && contentSame(result.target, p.target)
      && result.before_sha256 === p.before_sha256 && result.candidate_sha256 === p.candidate_sha256 && result.current_sha256 === p.candidate_sha256
      && result.rollback_available && contentSame(result.migration_artifact, p.migration_artifact) && contentSame(result.affected_paths, p.affected_paths),
    'CONTENT_OUTCOME_UNVERIFIED', 'Content execution did not verify the exact outcome. Retain the lock and do not retry.');
    const after = await inspectContentTarget(session, p.target); this.qualifyPolicy(after, p.target);
    requireThat(contentSame(after.inspected.snapshot, p.candidate_snapshot) && contentSame(after.inspected.affected_paths, p.affected_paths), 'CONTENT_READBACK', 'Fresh content readback differs from the approved result.');
    const render = this.qa.profile(p.site_url, p.target, p.profile), expectedText = htmlContentText(p.candidate_snapshot[p.target.field]);
    for (const path of p.affected_paths) {
      await authorize();
      const publicResult = await s.publicProbe(new URL(path, p.site_url).href, () => session.authorize(), { contentAssertions: [{ selector: render.selector, expectedText }] });
      requireThat(publicResult.status === 200 && publicResult.contentAssertions?.length === 1 && publicResult.contentAssertions[0].passed === true,
        'CONTENT_RENDER_UNVERIFIED', 'The public page did not show the exact approved content in its qualified location.');
    }
    const rest = await s.publicProbe(p.site_url + '/wp-json/', () => session.authorize(), { checkRest: true });
    requireThat(rest.status === 200 && rest.restHealthy === true, 'PUBLIC_VERIFICATION', 'Post-change WordPress API health is unverified.');
    return { providerOperationId: p.operation_id, snapshotSha256: p.candidate_sha256, backupVerified: true, contentSnapshotVerified: true,
      publicHtmlContentVerified: true, publicVerificationMethod: 'exact-selector-html-text', liveBrowserVerified: false,
      qaReportSha256: op.qaProof.claims.qa_report_sha256, migration: p.migration_artifact, rollbackAvailable: true };
  }
  async rollback(actor, { operationId, requestKey }) {
    const previous = this.service.store.get(operationId), p = previous.plan?.provider;
    requireThat(previous.workflow === 'content' && previous.status === 'verified' && !p.rollback_operation_id, 'ROLLBACK_UNAVAILABLE', 'Choose a verified content edit with its retained original snapshot.');
    return this.prepare(actor, { website: previous.target.url, environment: previous.target.environment, target: p.target,
      expectedSnapshotSha256: p.candidate_sha256, newValue: p.before_snapshot[p.target.field], reason: `Restore exact content from ${operationId}.`, requestKey }, previous);
  }
}
