import { randomUUID } from 'node:crypto';
import { digest, requireThat, safeError } from './contracts.mjs';
import { codeInspection, codePath } from './code.mjs';
import { toolNames } from './mainwp.mjs';

const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
export class CodeWorkflow {
  constructor(service, { source, qa, delay = pause }) { Object.assign(this, { service, source, qa, delay }); }
  authorize(actor, target) {
    return async () => {
      this.service.registry.current(actor, target, 'code');
      requireThat(this.service.verifyStaff, 'AUTH_REQUIRED', 'Live Google Workspace authorization is required for code changes.');
      await this.service.verifyStaff(actor);
    };
  }
  profile(target, path) {
    const profiles = this.qa.policy.profiles.filter(p => p.siteUrl === target.url + '/' && p.paths.includes(path));
    requireThat(profiles.length === 1, 'QA_PROFILE_UNQUALIFIED', 'This exact site/file has no qualified code workflow. Services must qualify it before a team member can edit it.');
    requireThat(target.backup === 'updraft', 'BACKUP_UNQUALIFIED', 'A qualified remote database-backup route is required.');
    return profiles[0];
  }
  async prepare(actor, input, previous = null) {
    const s = this.service;
    await s.discovery?.refresh(actor);
    const target = s.registry.resolve(actor, input.website, input.environment, 'code');
    const authorize = this.authorize(actor, target); await authorize();
    codePath.parse(input.path); const profile = this.profile(target, input.path);
    requireThat(typeof input.content === 'string' && input.content.isWellFormed() && Buffer.byteLength(input.content) <= 32768
      && typeof input.reason === 'string' && input.reason.length > 0 && input.reason.length <= 512, 'CODE_INPUT', 'Provide a bounded UTF-8 candidate and reason.');
    const { record: op, created } = s.store.request(actor, input.requestKey, { code: input, rollbackOf: previous?.id || null },
      () => s.store.create('operation', actor, target, { status: 'preparing', workflow: 'code' }));
    if (!created) return s.view(op);
    let session;
    try {
      session = await s.session(actor, target, 'code', op.id);
      const before = await codeInspection(session, input.path);
      requireThat(!before.contentWithheld && typeof before.content === 'string' && before.sha256 === input.expectedSha256,
        'CODE_PRECONDITION', 'The exact live file changed or its content is protected. Inspect again before preparing.');
      requireThat(before.storage.backupReady && before.storage.atomicReady, 'CODE_STORAGE', 'Protected snapshot storage and atomic replacement must be qualified by MRN services.');
      requireThat(digest(input.content) !== before.sha256, 'NO_CHANGE', 'The proposed code is unchanged.');
      op.plan = { kind: 'code', path: input.path, beforeSha256: before.sha256, newSha256: digest(input.content), beforeContent: before.content, content: input.content,
        description: input.reason, profileDigest: digest(profile), site: before.site, rollbackOf: previous?.plan.provider.operation_id || '',
        source: { mode: profile.source.mode }, recovery: { kind: 'exact-file', beforeSha256: before.sha256, database: 'Verified remote database backup is required before execution.' } };
      if (profile.source.mode === 'git') {
        requireThat(this.source, 'SOURCE_UNAVAILABLE', 'The central source service is not configured.');
        requireThat(profile.sourceAutomation?.deploymentDisabled === true && profile.sourceAutomation.expiresAt > Date.now()
          && /^[a-f0-9]{40}$/.test(profile.sourceAutomation.baseCommit), 'SOURCE_AUTOMATION_UNQUALIFIED',
        'Source automation must be verified inactive for this workflow before creating or merging a candidate. Existing automatic deployments need a separately qualified shared-lock and backup adapter.');
        const roots = Object.keys(profile.source.paths).filter(root => input.path.startsWith(root));
        requireThat(roots.length === 1, 'SOURCE_PATH', 'The source mapping is missing or ambiguous.');
        const path = profile.source.paths[roots[0]] + input.path.slice(roots[0].length);
        op.plan.repositoryPath = path;
        op.plan.source = { mode: 'git', repository: profile.source.repository, branch: profile.source.branch, commit: profile.sourceAutomation.baseCommit };
        s.store.save(op); this.startQualification(actor, op, true);
      } else {
        s.store.save(op); this.startQualification(actor, op);
      }
    } catch (error) {
      op.status = op.sourceProgress ? 'source_uncertain' : 'blocked'; op.error = safeError(error); s.store.save(op);
    } finally { await session?.close(); }
    return s.view(s.store.get(op.id));
  }
  async approveSource(actor, { operationId, planDigest }) {
    const s = this.service; const op = s.store.get(operationId); const authorize = this.authorize(actor, op.target); await authorize();
    requireThat(op.workflow === 'code' && op.status === 'source_review' && op.requestedBy === actor.subject && op.expiresAt > Date.now(), 'SOURCE_APPROVAL', 'Only the original active operator can approve this current source proposal.');
    requireThat(op.planDigest === planDigest && planDigest === digest({ target: op.target, plan: op.plan }), 'PLAN_CHANGED', 'Review the exact source proposal before approving.');
    requireThat(s.writesEnabled, 'WRITES_DISABLED', 'Source and website writes remain disabled until the reviewed service rollout is enabled.');
    const profile = this.profile(op.target, op.plan.path);
    requireThat(digest(profile) === op.plan.profileDigest && profile.sourceAutomation?.deploymentDisabled === true
      && profile.sourceAutomation.expiresAt > Date.now(), 'SOURCE_AUTOMATION_UNQUALIFIED', 'Source automation qualification changed or expired.');
    // Persist state before awaiting GitHub so concurrent approvals cannot merge twice.
    s.store.transaction(() => {
      const current = s.store.get(op.id);
      requireThat(current.status === 'source_review' && current.planDigest === planDigest, 'OPERATION_ACTIVE', 'Source approval is already claimed or the plan changed.');
      s.store.audit(actor, op.target, op.id, 'code:source-approve', 'approved');
      op.status = 'source_merging'; s.store.save(op);
    });
    try {
      op.plan.source = await this.source.merge(op.plan.proposal, authorize, state => { op.sourceProgress = state; s.store.save(op); });
      op.sourceApprovedBy = actor.subject; op.status = 'source_merged'; s.store.save(op);
      this.startQualification(actor, op);
    } catch (error) { op.status = 'source_uncertain'; op.error = safeError(error); s.store.save(op); }
    return s.view(s.store.get(op.id));
  }
  startQualification(actor, op, sourceReview = false) {
    requireThat(!this.service.jobs.has(op.id), 'OPERATION_ACTIVE', 'Qualification is already running.');
    op.status = sourceReview ? 'source_qualifying' : 'qualifying'; this.service.store.save(op);
    const job = this.qualify(actor, op, sourceReview).finally(() => this.service.jobs.delete(op.id));
    this.service.jobs.set(op.id, job);
  }
  async qualify(actor, op, sourceReview = false) {
    const s = this.service; const authorize = this.authorize(actor, op.target); let session;
    try {
      await authorize(); const profile = this.profile(op.target, op.plan.path);
      requireThat(digest(profile) === op.plan.profileDigest, 'QA_PROFILE_CHANGED', 'The qualified runtime policy changed. Prepare again.');
      if (op.plan.proposal) op.plan.source = await this.source.merged(op.plan.proposal, authorize);
      session = await s.session(actor, op.target, 'code', op.id);
      const current = await codeInspection(session, op.plan.path);
      requireThat(current.sha256 === op.plan.beforeSha256 && current.site.id === op.plan.site.id, 'CODE_PRECONDITION', 'Live state changed before QA.');
      const request = { schema: 1, request_id: randomUUID().replaceAll('-', ''), site_id: current.site.id, site_url: current.site.url + '/',
        kind: sourceReview ? (op.plan.rollbackOf ? 'rollback_source_review' : 'source_review') : (op.plan.rollbackOf ? 'rollback' : 'file'),
        path: op.plan.path, repository_path: op.plan.repositoryPath || op.plan.path,
        expected_sha256: op.plan.beforeSha256, new_sha256: op.plan.newSha256, rollback_operation_id: op.plan.rollbackOf,
        runtime_url: profile.runtimeUrl, source: op.plan.source, payload_base64: Buffer.from(op.plan.content).toString('base64') };
      const prefix = sourceReview ? 'sourceQa' : 'qa';
      op[`${prefix}Request`] = request; s.store.save(op);
      op[`${prefix}Ticket`] = await this.qa.dispatch(request, authorize, state => { op[`${prefix}Progress`] = state; s.store.save(op); }); s.store.save(op);
      const deadline = Date.now() + 20 * 60000; let proof;
      while (Date.now() < deadline) {
        proof = await this.qa.receipt(op[`${prefix}Ticket`], request, authorize);
        if (proof.status === 'qualified') break;
        await this.delay(10000);
      }
      requireThat(proof?.status === 'qualified', 'QA_PENDING', 'QA did not produce a fresh signed receipt within the qualification window. No live change was attempted.');
      const after = await codeInspection(session, op.plan.path);
      requireThat(after.sha256 === op.plan.beforeSha256 && after.site.id === op.plan.site.id, 'CODE_PRECONDITION', 'Live state changed while QA was running.');
      op[`${prefix}Proof`] = proof;
      if (sourceReview) {
        await authorize();
        requireThat(s.writesEnabled, 'WRITES_DISABLED', 'Source writes remain disabled until the reviewed service rollout is enabled.');
        requireThat(profile.sourceAutomation.expiresAt > Date.now(), 'SOURCE_AUTOMATION_UNQUALIFIED', 'Source automation qualification expired during QA.');
        op.plan.proposal = await this.source.propose({ repository: profile.source.repository.slice('https://github.com/'.length), branch: profile.source.branch,
          path: request.repository_path, expectedSha256: op.plan.beforeSha256, content: op.plan.content, operationId: op.id, reason: op.plan.description, actor,
          requiredBase: profile.sourceAutomation.baseCommit, reviewProof: proof }, authorize,
        state => { op.sourceProgress = state; s.store.save(op); });
        op.status = 'source_review'; op.planDigest = digest({ target: op.target, plan: op.plan });
        op.expiresAt = Math.min(profile.sourceAutomation.expiresAt, proof.claims.expires_at * 1000);
        s.store.audit(actor, op.target, op.id, 'code:source-prepare', 'prepared'); s.store.save(op); return;
      }
      const input = { site_id: current.site.id, site_url: current.site.url + '/', kind: request.kind, path: op.plan.path,
        expected_sha256: op.plan.beforeSha256, reason: op.plan.description, qa_evidence_sha256: proof.claims.qa_report_sha256,
        qa_receipt_json: JSON.stringify(proof.envelope), ...(op.plan.rollbackOf ? { rollback_operation_id: op.plan.rollbackOf }
          : { content_base64: request.payload_base64, new_sha256: op.plan.newSha256 }) };
      const plan = await session.call(toolNames.codePrepare, input);
      requireThat(/^[a-f0-9-]{36}$/.test(plan.operation_id) && /^[a-f0-9]{64}$/.test(plan.digest) && plan.site_id === current.site.id
        && plan.site_url === current.site.url + '/' && plan.path === op.plan.path && plan.before_sha256 === op.plan.beforeSha256
        && plan.kind === request.kind && plan.qa_evidence_sha256 === proof.claims.qa_report_sha256 && plan.prepared_by === actor.verifiedEmail
        && plan.qa?.new_sha256 === op.plan.newSha256 && (request.kind === 'rollback' || plan.new_sha256 === op.plan.newSha256),
      'CODE_PLAN_MISMATCH', 'The provider prepared a different operation.');
      op.plan.provider = plan; op.planDigest = digest({ target: op.target, plan: op.plan }); op.status = 'prepared';
      op.expiresAt = Math.min(plan.expires_at, proof.claims.expires_at) * 1000;
      requireThat(op.expiresAt > Date.now(), 'PLAN_EXPIRED', 'The provider plan expired before review.');
      s.store.audit(actor, op.target, op.id, 'code:prepare', 'prepared'); s.store.save(op);
    } catch (error) { op.status = sourceReview && op.sourceProgress ? 'source_uncertain' : 'blocked'; op.error = safeError(error); s.store.save(op); }
    finally { await session?.close().catch(() => {}); }
  }
  bind(session, op) { session.codeOperation = { id: op.plan.provider.operation_id, digest: op.plan.provider.digest }; }
  async approve(actor, op, planDigest) {
    const s = this.service; await this.authorize(actor, op.target)();
    requireThat(op.planDigest === planDigest && planDigest === digest({ target: op.target, plan: op.plan })
      && ['prepared', 'approved'].includes(op.status) && op.expiresAt > Date.now(), 'PLAN_CHANGED', 'A current exact prepared code plan is required.');
    const claimed = s.store.transaction(() => {
      const current = s.store.get(op.id);
      requireThat(current.planDigest === planDigest, 'PLAN_CHANGED', 'The current plan changed before approval.');
      if (current.status === 'approved') return false;
      requireThat(current.status === 'prepared', 'OPERATION_ACTIVE', 'Another approval is pending or needs reconciliation.');
      current.status = 'approving'; s.store.audit(actor, op.target, op.id, 'code:approve', 'attempted'); s.store.save(current); return true;
    });
    if (!claimed) return s.view(s.store.get(op.id));
    let session;
    try {
      session = await s.session(actor, op.target, 'code', op.id); await session.fresh(); this.bind(session, op);
      const result = await session.call(toolNames.codeApprove, { operation_id: op.plan.provider.operation_id, reviewed_digest: op.plan.provider.digest });
      requireThat(result.operation_id === op.plan.provider.operation_id && result.digest === op.plan.provider.digest
        && result.approved_by === actor.verifiedEmail, 'CODE_APPROVAL', 'The provider did not record this person’s exact approval.');
      const current = s.store.get(op.id); requireThat(current.status === 'approving', 'OPERATION_ACTIVE', 'Approval state changed during dispatch.');
      current.status = 'prepared'; s.store.save(current);
      return s.view(s.store.approve(op.id, actor, planDigest));
    } catch (error) {
      const current = s.store.get(op.id); current.status = 'approval_uncertain'; current.error = safeError(error); s.store.save(current); throw error;
    } finally { await session?.close(); }
  }
  async execute(actor, op, session) {
    const authorize = this.authorize(actor, op.target); await authorize();
    requireThat(op.requestedBy === actor.subject, 'CODE_OPERATOR', 'The original verified operator must execute the prepared code plan.');
    const profile = this.profile(op.target, op.plan.path); requireThat(digest(profile) === op.plan.profileDigest, 'QA_PROFILE_CHANGED', 'The qualified policy changed.');
    if (op.plan.proposal) requireThat(digest(await this.source.merged(op.plan.proposal, authorize)) === digest(op.plan.source), 'SOURCE_CHANGED', 'Merged source changed after approval.');
    const before = await codeInspection(session, op.plan.path); requireThat(before.sha256 === op.plan.beforeSha256, 'CODE_PRECONDITION', 'The file changed after approval.');
    this.bind(session, op);
    const started = await session.call(toolNames.backup, { site_id: before.site.id });
    requireThat(started.site_id === before.site.id && /^[a-f0-9]+$/i.test(started.nonce), 'BACKUP_INVALID', 'The remote database backup did not return an exact-site nonce.');
    const deadline = Date.now() + 10 * 60000; let receipt;
    while (Date.now() < deadline) {
      const result = await session.call(toolNames.backupStatus, { site_id: before.site.id, nonce: started.nonce });
      requireThat(result.status !== 'failed', 'BACKUP_FAILED', 'The required remote database backup failed.');
      if (result.status === 'complete' && typeof result.receipt === 'string' && result.receipt) { receipt = { site_id: before.site.id, nonce: started.nonce, receipt: result.receipt }; break; }
      await this.delay(5000);
    }
    requireThat(receipt, 'BACKUP_UNVERIFIED', 'The required remote database backup was not verified.');
    await authorize();
    const result = await session.call(toolNames.codeExecute, { operation_id: op.plan.provider.operation_id, reviewed_digest: op.plan.provider.digest, confirm: true, backup_receipt: receipt });
    requireThat(result.operation_id === op.plan.provider.operation_id && result.state === 'completed' && result.data?.success === true
      && result.data.sha256 === op.plan.newSha256, 'CODE_OUTCOME_UNVERIFIED', 'The provider did not verify the exact code outcome. Keep the lock and inspect before retrying.');
    const after = await codeInspection(session, op.plan.path); requireThat(after.sha256 === op.plan.newSha256, 'CODE_READBACK', 'Fresh readback did not match the approved code.');
    const publicResult = await this.service.publicProbe(op.target.url, () => session.authorize());
    const restResult = await this.service.publicProbe(op.target.url + '/wp-json/', () => session.authorize(), { checkRest: true });
    requireThat(publicResult.status === 200 && restResult.status === 200 && restResult.restHealthy === true, 'PUBLIC_VERIFICATION', 'Post-change website/API health was not verified.');
    return { providerOperationId: result.operation_id, beforeSha256: op.plan.beforeSha256, sha256: after.sha256, backupVerified: true,
      qaReportSha256: op.qaProof.claims.qa_report_sha256, source: op.plan.source, publicVerified: true, rollbackAvailable: true };
  }
  async rollback(actor, { operationId, requestKey }) {
    const previous = this.service.store.get(operationId);
    requireThat(previous.workflow === 'code' && previous.status === 'verified' && !previous.plan.rollbackOf, 'ROLLBACK_UNAVAILABLE', 'Choose a verified code edit with its retained original snapshot.');
    return this.prepare(actor, { website: previous.target.url, environment: previous.target.environment, path: previous.plan.path,
      content: previous.plan.beforeContent, expectedSha256: previous.plan.newSha256, reason: `Restore the exact file snapshot from ${operationId}.`, requestKey }, previous);
  }
}
