import { randomUUID } from 'node:crypto';
import { digest, requireThat, safeError } from './contracts.mjs';
import { delegationDigest } from './delegation.mjs';
import { contentTargetSchema, contentCall, contentSnapshot, contentSame, contentPaths, validateContentPlan, validateContentMigration } from './content-contract.mjs';
import { contentQaInput, managementQaInput } from './content-qa.mjs';
import { acfPatchSchema, acfScalar, managedTargetSchema } from './content.mjs';
import { managementCall, validateManagementRead, validateManagementEvidence, validateManagementOutcome, managementCandidateHash } from './content-management-contract.mjs';
import { toolNames } from './mainwp.mjs';
import { htmlContentText } from './probe.mjs';

const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
export async function inspectContentTarget(session, input) {
  if (input.kind === 'wordpress-content') {
    const target = managedTargetSchema.parse(input), site = await session.fresh();
    const read = validateManagementRead(await managementCall(session, 'read-content-record-v1', { site_id: site.id, site_url: site.url,
      post_type: target.post_type, post_id: target.post_id }), { site, target });
    return { site, management: true, read };
  }
  const target = contentTargetSchema.parse(input), site = await session.fresh();
  const args = { site_id: site.id, site_url: site.url };
  // Capability absence alone permits the legacy unambiguous lookup. A failed,
  // denied or unqualified v2 response must never downgrade to a site-only call.
  const policy = await session.supports(toolNames.contentTargetPolicy)
    ? await contentCall(session, 'get-content-policy-v2', { ...args, target })
    : await contentCall(session, 'get-content-policy-v1', args);
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
    if (input.target?.kind === 'wordpress-content') return this.prepareManagement(actor, input, previous);
    const s = this.service; await s.discovery?.refresh(actor);
    const target = s.registry.resolve(actor, input.website, input.environment, 'content'), authorize = this.authorize(actor, target); await authorize();
    contentTargetSchema.parse(input.target);
    requireThat(target.backup === 'updraft', 'BACKUP_UNQUALIFIED', 'Content changes need a qualified remote database-backup route.');
    requireThat(input.acfPatch === undefined && typeof input.newValue === 'string' && input.newValue.isWellFormed() && !input.newValue.includes('\0') && Buffer.byteLength(input.newValue) <= 8192
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
    if (op.plan.adapter === 'content-management-v1') {
      const evidence = await inspectContentTarget(session, op.plan.provider.target);
      const qualified = this.qa.managementProfile(evidence.site.url, op.plan.provider.target, evidence.read);
      requireThat(contentSame(qualified.profile, op.plan.provider.profile) && contentSame(evidence.read, op.plan.read),
        'CONTENT_PRECONDITION', 'The complete ACF snapshot, definitions, unrelated metadata, runtime or rendering paths changed after preparation.');
      return evidence;
    }
    const p = op.plan.provider, evidence = await inspectContentTarget(session, p.target); this.qualifyPolicy(evidence, p.target);
    requireThat(evidence.policy.policy_sha256 === op.plan.policySha256 && contentSame(evidence.policy.profile, p.profile)
      && contentSame(evidence.inspected.snapshot, p.before_snapshot) && contentSame(evidence.inspected.affected_paths, p.affected_paths)
      && contentSame(evidence.inspected.stable_selector, p.stable_selector), 'CONTENT_PRECONDITION', 'Content, ownership or qualification changed after preparation.');
    return evidence;
  }
  proof(op) {
    this.qa.qualifyRequest(op.qaRequest);
    requireThat(op.plan.provider.expires_at * 1000 > Date.now(), 'PLAN_EXPIRED', 'The prepared content proposal expired.');
    return this.qa.verifyReceipt(op.qaProof.envelope, op.qaRequest, op.qaTicket.runId, op.qaProof.reportSha256);
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
      const managed = op.plan.adapter === 'content-management-v1';
      const result = await (managed ? managementCall : contentCall)(session, managed ? 'approve-content-edit-v1' : 'approve-content-change-v1', {
        ...(managed ? { site_id: op.plan.provider.site_id, site_url: op.plan.provider.site_url, confirm: true } : {}),
        operation_id: op.plan.provider.operation_id, reviewed_digest: op.plan.provider.digest,
        qa_receipt_json: JSON.stringify(op.qaProof.envelope), qa_evidence_sha256: op.qaProof.claims.qa_report_sha256 });
      requireThat(result.operation_id === op.plan.provider.operation_id && result.digest === op.plan.provider.digest
        && (managed ? result.state === 'approved' : result.approved_by === actor.verifiedEmail && result.expires_at * 1000 > Date.now()
          && result.expires_at <= op.plan.provider.expires_at), 'CONTENT_APPROVAL', 'The provider did not verify this person’s exact approval.');
      const current = s.store.get(op.id); requireThat(current.status === 'approving', 'OPERATION_ACTIVE', 'Approval state changed during dispatch.');
      current.expiresAt = Math.min(current.expiresAt, (managed ? op.plan.provider.expires_at : result.expires_at) * 1000); current.status = 'prepared'; s.store.save(current);
      return s.view(s.store.approve(op.id, actor, planDigest));
    } catch (error) { const current = s.store.get(op.id); current.status = 'approval_uncertain'; current.error = safeError(error); s.store.save(current); throw error; }
    finally { await session?.close(); }
  }
  async execute(actor, op, session) {
    const s = this.service, authorize = this.authorize(actor, op.target); await authorize();
    requireThat(op.requestedBy === actor.subject, 'CONTENT_OPERATOR', 'The original active operator must execute the content change.');
    this.proof(op); const before = await this.fresh(session, op), p = op.plan.provider; this.bind(session, p);
    const receipt = await s.changeBackup(session, before.site, op.expiresAt, this.delay);
    await authorize(); this.proof(op); await this.fresh(session, op);
    if (op.plan.adapter === 'content-management-v1') return this.executeManagement(actor, op, session, receipt, authorize);
    const result = await contentCall(session, 'execute-content-change-v1', { operation_id: p.operation_id, reviewed_digest: p.digest, confirm: true, backup_receipt: receipt });
    requireThat(result.operation_id === p.operation_id && result.state === 'completed' && [null, ''].includes(result.error_code) && typeof result.changed === 'boolean'
      && result.site_id === p.site_id && result.site_url === p.site_url && contentSame(result.target, p.target)
      && result.before_sha256 === p.before_sha256 && result.candidate_sha256 === p.candidate_sha256 && result.current_sha256 === p.candidate_sha256
      && result.rollback_available === (p.rollback_operation_id === '') && contentSame(result.migration_artifact, p.migration_artifact) && contentSame(result.affected_paths, p.affected_paths),
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
      qaReportSha256: op.qaProof.claims.qa_report_sha256, migration: p.migration_artifact, rollbackAvailable: result.rollback_available };
  }
  async rollback(actor, { operationId, requestKey }) {
    const previous = this.service.store.get(operationId), p = previous.plan?.provider;
    requireThat(previous.workflow === 'content' && previous.status === 'verified' && !p.rollback_operation_id, 'ROLLBACK_UNAVAILABLE', 'Choose a verified content edit with its retained original snapshot.');
    if (previous.plan.adapter === 'content-management-v1') return this.prepare(actor, {
      website: previous.target.url, environment: previous.target.environment, target: p.target,
      expectedSnapshotSha256: previous.result.snapshotSha256,
      acfPatch: { path: previous.plan.acfPath, value: acfScalar(p.before_snapshot, previous.plan.acfPath) },
      reason: `Restore exact ACF scalar from ${operationId}.`, requestKey }, previous);
    return this.prepare(actor, { website: previous.target.url, environment: previous.target.environment, target: p.target,
      expectedSnapshotSha256: p.candidate_sha256, newValue: p.before_snapshot[p.target.field], reason: `Restore exact content from ${operationId}.`, requestKey }, previous);
  }

  async prepareManagement(actor, input, previous) {
    const s = this.service; await s.discovery?.refresh(actor);
    const target = s.registry.resolve(actor, input.website, input.environment, 'content'), authorize = this.authorize(actor, target); await authorize();
    const recordTarget = managedTargetSchema.parse(input.target), patch = acfPatchSchema.parse(input.acfPatch);
    requireThat(input.newValue === undefined && typeof input.reason === 'string' && input.reason.length > 0 && input.reason.length <= 500
      && patch.value.isWellFormed() && !patch.value.includes('\0') && Buffer.byteLength(patch.value) <= 8192,
    'CONTENT_INPUT', 'Provide one existing ACF scalar patch and a bounded reason.');
    requireThat(target.backup === 'updraft', 'BACKUP_UNQUALIFIED', 'ACF changes require qualified remote database recovery.');
    const { record: op, created } = s.store.request(actor, input.requestKey, { content: input, rollbackOf: previous?.id || null },
      () => s.store.create('operation', actor, target, { workflow: 'content', status: 'preparing' }));
    if (!created) return s.view(op);
    let session;
    try {
      session = await s.session(actor, target, 'content', op.id);
      const evidence = await inspectContentTarget(session, recordTarget), qualified = this.qa.managementProfile(evidence.site.url, recordTarget, evidence.read);
      requireThat(contentSame(patch.path, qualified.acfPath), 'CONTENT_ACF_PATH', 'This scalar path has no qualified rendering assertion.');
      requireThat(evidence.read.snapshot_sha256 === input.expectedSnapshotSha256, 'CONTENT_PRECONDITION', 'The complete ACF record changed. Read it again before preparing.');
      requireThat(acfScalar(evidence.read.snapshot, patch.path) !== patch.value, 'NO_CHANGE', 'The requested ACF scalar is already present.');
      const preview = { site_id: evidence.site.id, site_url: evidence.site.url, post_type: recordTarget.post_type, post_id: recordTarget.post_id,
        expected_snapshot_sha256: input.expectedSnapshotSha256, idempotency_key: op.id, reason: input.reason, acf_patches: [patch] };
      let plan;
      if (previous) {
        this.bind(session, previous.plan.provider);
        plan = await managementCall(session, 'preview-content-edit-rollback-v1', { site_id: evidence.site.id, site_url: evidence.site.url,
          operation_id: previous.plan.provider.operation_id, expected_snapshot_sha256: input.expectedSnapshotSha256,
          idempotency_key: op.id, reason: input.reason });
      } else plan = await managementCall(session, 'preview-content-edit-v1', preview);
      this.bind(session, plan);
      const migration = await managementCall(session, 'get-content-edit-migration-v1', { site_id: evidence.site.id, site_url: evidence.site.url, operation_id: plan.operation_id });
      validateManagementEvidence({ review: plan, migration, read: evidence.read }, { site: evidence.site, target: recordTarget,
        request: preview, profile: qualified.profile, rollbackOf: previous?.plan.provider.operation_id || '' });
      if (previous) requireThat(managementCandidateHash(plan.candidate_snapshot) === managementCandidateHash(previous.plan.provider.before_snapshot),
        'CONTENT_ROLLBACK', 'The inverse did not preserve the exact original core and ACF values, definitions and runtime.');
      op.plan = { kind: 'content', adapter: 'content-management-v1', description: input.reason, provider: plan, migration,
        read: evidence.read, preview, acfPath: patch.path, requestedValueNormalized: false };
      op.qaRequest = this.qa.qualifyRequest(managementQaInput({ review: plan, migration, read: evidence.read }, preview, randomUUID().replaceAll('-', '')));
      op.status = 'qualifying'; s.store.save(op);
      const job = this.qualify(actor, op).finally(() => s.jobs.delete(op.id)); s.jobs.set(op.id, job);
    } catch (error) { op.status = 'blocked'; op.error = safeError(error); s.store.save(op); }
    finally { await session?.close(); }
    return s.view(s.store.get(op.id));
  }

  async executeManagement(actor, op, session, receipt, authorize) {
    const p = op.plan.provider;
    let state;
    try { state = await managementCall(session, 'execute-content-edit-v1', { site_id: p.site_id, site_url: p.site_url,
      operation_id: p.operation_id, reviewed_digest: p.digest, confirm: true, backup_receipt: receipt }); }
    catch (error) { await this.service.confirmChangeRefusal(actor, op, session, error, () => this.fresh(session, op)); throw error; }
    const after = await inspectContentTarget(session, p.target);
    const outcome = validateManagementOutcome(state, after.read, p);
    const qualified = this.qa.managementProfile(p.site_url, p.target, after.read);
    requireThat(contentSame(qualified.profile, p.profile) && contentSame(after.read.affected_paths, p.affected_paths)
      && outcome.read.snapshot.unrelated_meta_sha256 === p.before_snapshot.unrelated_meta_sha256,
    'CONTENT_READBACK', 'Independent ACF readback changed the rendering profile, unrelated metadata or affected paths.');
    const expectedText = htmlContentText(acfScalar(p.candidate_snapshot, op.plan.acfPath));
    for (const path of p.affected_paths) {
      await authorize();
      const result = await this.service.publicProbe(new URL(path, p.site_url).href, () => session.authorize(),
        { contentAssertions: [{ selector: qualified.selector, expectedText }] });
      requireThat(result.status === 200 && result.contentAssertions?.length === 1 && result.contentAssertions[0].passed === true,
        'CONTENT_RENDER_UNVERIFIED', 'The exact ACF scalar was not independently verified in its qualified public location.');
    }
    const rest = await this.service.publicProbe(p.site_url + '/wp-json/', () => session.authorize(), { checkRest: true });
    requireThat(rest.status === 200 && rest.restHealthy === true, 'PUBLIC_VERIFICATION', 'Post-change WordPress API health is unverified.');
    return { providerOperationId: p.operation_id, snapshotSha256: after.read.snapshot_sha256, candidateSha256: p.candidate_sha256,
      backupVerified: true, contentSnapshotVerified: true, unrelatedMetadataVerified: true, publicHtmlContentVerified: true,
      publicVerificationMethod: 'exact-selector-html-text', liveBrowserVerified: false, qaReportSha256: op.qaProof.claims.qa_report_sha256,
      migration: p.migration_artifact, rollbackAvailable: state.rollback_available };
  }
}
