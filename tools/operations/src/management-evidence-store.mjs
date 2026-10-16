import { randomUUID } from 'node:crypto';
import { requireThat, digest } from './contracts.mjs';
import { delegationDigest } from './delegation.mjs';
import { ManagementAccessLedger } from './content-management-access-store.mjs';
import { validateManagementMeasurements } from './content-management-capture.mjs';
import { managementEvidenceTypes as types } from './management-evidence-contract.mjs';

const check = (value, message) => requireThat(value, 'MANAGEMENT_EVIDENCE_STATE', message);
const parse = (schema, value) => {
  const result = schema.safeParse(value); check(result.success, 'Invalid private management evidence state.'); return result.data;
};
const same = (a, b) => delegationDigest(a) === delegationDigest(b);
const sourceFields = ['repository', 'repository_id', 'ref', 'workflow_id', 'workflow_path', 'workflow_sha256',
  'runner_source_sha', 'engine_commit', 'engine_configuration_sha256', 'runtime_sha256'];

// Source-only lifecycle: no dispatcher, callback, public tool or production
// policy is registered. Only service code holding the original access ledger
// may record independently read capture and protected-dispatch observations.
export class ManagementEvidenceLedger {
  constructor(access, { policy, appActorId, now = () => Date.now() }) {
    check(access instanceof ManagementAccessLedger && typeof policy === 'function', 'Original management authority and service policy are required.');
    parse(types.id, appActorId);
    Object.assign(this, { access, store: access.store, policy, appActorId, now });
    this.currentPolicy();
    this.store.db.exec(`CREATE TABLE IF NOT EXISTS management_evidence_jobs(
      job TEXT PRIMARY KEY, body TEXT NOT NULL, digest TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS management_evidence_dispatches(
      job TEXT PRIMARY KEY, id TEXT NOT NULL UNIQUE, body TEXT NOT NULL, digest TEXT NOT NULL, run TEXT UNIQUE);
      CREATE TABLE IF NOT EXISTS management_evidence_acquisitions(
      job TEXT PRIMARY KEY, query TEXT NOT NULL UNIQUE, digest TEXT NOT NULL, revision INTEGER NOT NULL,
      started INTEGER NOT NULL, state TEXT NOT NULL);`);
  }
  currentPolicy() {
    const policy = parse(types.policy, this.policy());
    check(/^mrnwebdesigns\/[A-Za-z0-9_-]+$/.test(policy.repository), 'The central policy must select the exact MRN repository.');
    return policy;
  }
  decode(row) {
    check(row && typeof row.body === 'string' && digest(row.body) === row.digest, 'The immutable evidence record is missing or corrupt.');
    try { return JSON.parse(row.body); } catch { check(false, 'The immutable evidence record is corrupt.'); }
  }
  captured(jobId) {
    const { mapping, revision } = this.access.current(jobId);
    const context = parse(types.context, this.decode(this.store.db.prepare('SELECT body,digest FROM management_evidence_jobs WHERE job=?').get(jobId)));
    const policy = this.currentPolicy();
    check(same(context.binding, mapping.context.binding) && context.management_context_sha256 === mapping.context.binding_sha256
      && context.evidence_policy_epoch === policy.policy_epoch && context.evidence_policy_sha256 === delegationDigest(policy),
    'Captured evidence no longer matches the original actor, job, source or current policy.');
    return { mapping, revision, context, policy };
  }
  recordCapture(jobId, capture, measurements, expected) {
    // Validate full private bytes before keeping only their small original-bound
    // digests. No content, token, signature or archive is stored in this ledger.
    const validatedAt = this.now();
    const measured = validateManagementMeasurements(measurements, capture, { ...expected, now: validatedAt });
    return this.store.transaction(() => {
      const { mapping } = this.access.current(jobId), policy = this.currentPolicy();
      check(same(expected.queuedContext, mapping.context) && measured.qualification_job_id === jobId
        && this.now() >= validatedAt && this.now() - validatedAt < 5000,
      'Capture must come from the fresh original service-owned queue read.');
      const context = parse(types.context, { binding: mapping.context.binding,
        management_context_sha256: mapping.context.binding_sha256,
        capture_artifact_sha256: capture.capture_artifact_sha256,
        measurement_record_sha256: measured.measurement_record_sha256,
        parity_sha256: measured.measurements.parity_sha256,
        evidence_policy_epoch: policy.policy_epoch, evidence_policy_sha256: delegationDigest(policy) });
      const old = this.store.db.prepare('SELECT body,digest FROM management_evidence_jobs WHERE job=?').get(jobId);
      if (old) check(same(this.decode(old), context), 'The original capture cannot be replaced.');
      else {
        const body = JSON.stringify(context);
        this.store.db.prepare('INSERT INTO management_evidence_jobs VALUES(?,?,?)').run(jobId, body, digest(body));
        this.access.audit(jobId, 'evidence-capture-mapped', '', 'clear', this.access.current(jobId).revision);
      }
      return context;
    });
  }
  prepareDispatch(jobId) {
    return this.store.transaction(() => {
      const { context, policy, revision, mapping } = this.captured(jobId);
      check(!this.store.db.prepare('SELECT job FROM management_evidence_dispatches WHERE job=?').get(jobId),
        'An original dispatch may be prepared only once; uncertain dispatches cannot be retried.');
      const dispatchId = randomUUID();
      const request = { schema: 1, dispatch_id: dispatchId, context_sha256: delegationDigest(context) };
      const prepared = { request, context_sha256: delegationDigest(context), evidence_policy_sha256: delegationDigest(policy),
        requested_at: this.now(), original_expires_at: Math.min(mapping.actor.expiresAt, context.binding.expires_at * 1000),
        app_actor_id: this.appActorId };
      const body = JSON.stringify(prepared);
      this.store.db.prepare('INSERT INTO management_evidence_dispatches VALUES(?,?,?,?,NULL)').run(jobId, dispatchId, body, digest(body));
      this.access.audit(jobId, 'evidence-dispatch-prepared', '', 'clear', revision);
      return request;
    });
  }
  recordDispatch(jobId, dispatchId, runId) {
    parse(types.uuid, dispatchId); parse(types.id, runId);
    return this.store.transaction(() => {
      const original = this.captured(jobId), { context, policy, revision } = original;
      const row = this.store.db.prepare('SELECT * FROM management_evidence_dispatches WHERE job=?').get(jobId);
      const prepared = this.decode(row);
      check(row.id === dispatchId && row.run === null && prepared.context_sha256 === delegationDigest(context)
        && prepared.evidence_policy_sha256 === delegationDigest(policy) && prepared.requested_at <= this.now()
        && prepared.original_expires_at > this.now() && prepared.app_actor_id === this.appActorId,
      'Only the independently observed original protected dispatch can be recorded once.');
      const observation = { ...prepared, run_id: runId, observed_at: this.now() };
      const provenance = parse(types.provenance, { ...Object.fromEntries(sourceFields.map(key => [key, policy[key]])),
        dispatch_id: dispatchId, dispatch_request_sha256: delegationDigest(prepared.request),
        dispatch_record_sha256: delegationDigest(observation), run_id: runId, run_attempt: 1 });
      const body = JSON.stringify({ observation, provenance });
      this.store.db.prepare('UPDATE management_evidence_dispatches SET body=?,digest=?,run=? WHERE job=? AND run IS NULL')
        .run(body, digest(body), runId, jobId);
      this.access.audit(jobId, 'evidence-dispatch-recorded', '', 'clear', revision);
      return provenance;
    });
  }
  dispatch(jobId) {
    const original = this.captured(jobId), { context, policy, mapping } = original;
    const row = this.store.db.prepare('SELECT * FROM management_evidence_dispatches WHERE job=?').get(jobId);
    const { observation: o, provenance: input } = this.decode(row), provenance = parse(types.provenance, input);
    check(o && row.id === provenance.dispatch_id && row.run === provenance.run_id && o.run_id === row.run
      && o.context_sha256 === delegationDigest(context) && o.evidence_policy_sha256 === delegationDigest(policy)
      && o.app_actor_id === this.appActorId && o.request?.dispatch_id === row.id && o.request.context_sha256 === o.context_sha256
      && provenance.dispatch_record_sha256 === delegationDigest(o) && provenance.dispatch_request_sha256 === delegationDigest(o.request)
      && sourceFields.every(key => provenance[key] === policy[key])
      && Number.isSafeInteger(o.requested_at) && Number.isSafeInteger(o.observed_at) && o.requested_at <= o.observed_at
      && o.observed_at <= this.now() && o.original_expires_at === Math.min(mapping.actor.expiresAt, context.binding.expires_at * 1000)
      && o.original_expires_at > this.now(), 'The immutable dispatch does not bind the original requester, source and lifetime.');
    return { ...original, provenance, observation: o, record: { repository: provenance.repository, repositoryId: provenance.repository_id,
      workflowId: provenance.workflow_id, runId: provenance.run_id, appActorId: this.appActorId,
      workflowPath: provenance.workflow_path, runnerCommit: provenance.runner_source_sha,
      requestedAt: o.requested_at, originalExpiresAt: o.original_expires_at } };
  }
  originalDispatch(dispatchId) {
    parse(types.uuid, dispatchId);
    const row = this.store.db.prepare('SELECT job FROM management_evidence_dispatches WHERE id=?').get(dispatchId);
    check(row, 'The original protected dispatch is unavailable.');
    return this.dispatch(row.job);
  }
  begin(input) {
    const query = parse(types.query, input);
    return this.store.transaction(() => {
      const job = query.context.binding.qualification_job_id, original = this.dispatch(job), now = this.now();
      check(same(query.context, original.context) && query.created_at <= Math.floor(now / 1000)
        && query.expires_at > Math.floor(now / 1000) && query.expires_at > query.created_at
        && query.expires_at - query.created_at <= 90 && query.expires_at <= original.context.binding.expires_at,
      'The authenticated evidence query differs from the immutable original job or expired.');
      check(!this.store.db.prepare('SELECT job FROM management_evidence_acquisitions WHERE job=? OR query=?').get(job, query.query_id),
        'Evidence acquisition is one-use, including failed or interrupted attempts.');
      const queryDigest = delegationDigest(query);
      this.store.db.prepare('INSERT INTO management_evidence_acquisitions VALUES(?,?,?,?,?,?)')
        .run(job, query.query_id, queryDigest, original.revision, now, 'acquiring');
      this.access.audit(job, 'evidence-acquisition-started', '', 'clear', original.revision);
      return { job, query, queryDigest, revision: original.revision, startedAt: now };
    });
  }
  current(lease) {
    const original = this.dispatch(lease.job);
    const row = this.store.db.prepare('SELECT * FROM management_evidence_acquisitions WHERE job=?').get(lease.job);
    check(row?.state === 'acquiring' && row.query === lease.query.query_id && row.digest === delegationDigest(lease.query)
      && row.digest === lease.queryDigest && row.revision === lease.revision && row.revision === original.revision
      && row.started === lease.startedAt && same(lease.query.context, original.context)
      && this.now() >= row.started && this.now() - row.started < 60000
      && lease.query.expires_at > Math.floor(this.now() / 1000), 'Evidence acquisition expired, changed, was consumed or lost original access.');
    return original;
  }
  finish(lease, makeResponse) {
    return this.store.transaction(() => {
      const original = this.current(lease), result = makeResponse(original);
      check(result && !result.then, 'Signing must complete inside the short local transaction.');
      this.current(lease);
      this.access.audit(lease.job, 'evidence-acquisition-observed', '', 'clear', original.revision);
      this.current(lease);
      this.store.db.prepare('UPDATE management_evidence_acquisitions SET state=? WHERE job=?').run('observed', lease.job);
      return result;
    });
  }
}
