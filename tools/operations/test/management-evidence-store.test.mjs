import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { Store } from '../src/store.mjs';
import { delegationDigest } from '../src/delegation.mjs';
import { ManagementAccessLedger } from '../src/content-management-access-store.mjs';
import { ManagementEvidenceLedger } from '../src/management-evidence-store.mjs';
import { managementEvidenceFixture as fixture } from './fixtures/management-evidence.mjs';

test('evidence ledger binds independently validated capture and separately recorded dispatch to the original human job', t => {
  const f = fixture(t), request = f.prepare();
  assert.deepEqual(Object.keys(request), ['schema', 'dispatch_id', 'context_sha256']);
  assert.equal(request.context_sha256, delegationDigest(f.ledger.captured(f.job).context));
  assert.throws(() => f.ledger.dispatch(f.job));
  const provenance = f.ledger.recordDispatch(f.job, request.dispatch_id, '30'), original = f.ledger.dispatch(f.job);
  assert.equal(provenance.dispatch_request_sha256, delegationDigest(request));
  assert.equal(provenance.run_id, '30'); assert.equal(provenance.repository, f.policy.repository);
  assert.equal(original.mapping.actor.googleSubject, f.actor.googleSubject);
  assert.equal(original.record.originalExpiresAt, f.context.binding.expires_at * 1000);
  const query = f.query(), lease = f.ledger.begin(query);
  assert.equal(f.ledger.current(lease).provenance.dispatch_id, request.dispatch_id);
  const result = f.ledger.finish(lease, () => ({ observation: 'not-an-admission' }));
  assert.equal(result.observation, 'not-an-admission');
  assert.equal(f.store.db.prepare('SELECT state FROM management_evidence_acquisitions').get().state, 'observed');
  for (const table of ['management_evidence_jobs', 'management_evidence_dispatches']) {
    const body = f.store.db.prepare(`SELECT body FROM ${table}`).get().body;
    for (const privateValue of ['never-store-this-token', 'post_content', 'signature_base64', 'zip_base64']) assert.equal(body.includes(privateValue), false);
  }
  assert.throws(() => f.ledger.current(lease));
  assert.throws(() => f.ledger.begin(query));
  assert.throws(() => f.ledger.begin(f.query()));
});

test('capture binding rejects altered bytes, measurement digests and replaced original jobs before dispatch', t => {
  for (const mutate of [f => { f.capture.capture_artifact.dataset.read.snapshot.post_title = 'changed'; },
    f => { f.measurements.measurement_record_sha256 = 'f'.repeat(64); },
    f => { f.context.binding.requester_binding_sha256 = 'f'.repeat(64); },
    f => { f.context.binding.qualification_job_id = randomUUID(); },
    f => { f.policy.repository = 'outsider/fork'; }, f => { f.policy.enabled = true; }]) {
    const f = fixture(t); mutate(f); assert.throws(f.recordCapture);
    assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_evidence_jobs').get().n, 0);
  }
  const f = fixture(t); f.recordCapture(); assert.deepEqual(f.recordCapture(), f.ledger.captured(f.job).context);
  f.policy.policy_epoch++; assert.throws(f.recordCapture);
});

test('original dispatch preparation and run observation are one-use, exact and immutable', t => {
  const f = fixture(t), request = f.prepare();
  assert.throws(f.prepare); assert.throws(() => f.ledger.recordDispatch(f.job, randomUUID(), '30'));
  assert.throws(() => f.ledger.recordDispatch(f.job, request.dispatch_id, '0'));
  f.ledger.recordDispatch(f.job, request.dispatch_id, '30');
  assert.throws(() => f.ledger.recordDispatch(f.job, request.dispatch_id, '31'));
  const p = f.ledger.dispatch(f.job).provenance; p.run_id = '999';
  assert.equal(f.ledger.dispatch(f.job).provenance.run_id, '30');
  f.store.db.exec("UPDATE management_evidence_dispatches SET body='{}'");
  assert.throws(() => f.ledger.dispatch(f.job));
});

test('provider query cannot select actor, capture, policy, source, run or an extended intent', t => {
  for (const mutate of [q => { q.run_id = '999'; }, q => { q.email = 'another@mrnwebdesigns.com'; },
    q => { q.context.capture_artifact_sha256 = 'f'.repeat(64); }, q => { q.context.measurement_record_sha256 = 'f'.repeat(64); },
    q => { q.context.parity_sha256 = 'f'.repeat(64); }, q => { q.context.evidence_policy_epoch++; },
    q => { q.context.binding.requester_binding_sha256 = 'f'.repeat(64); },
    q => { q.expires_at++; }, q => { q.created_at++; }, q => { q.expires_at = q.created_at; }]) {
    const f = fixture(t); f.dispatch(); const query = f.query(); mutate(query);
    assert.throws(() => f.ledger.begin(query));
    assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_evidence_acquisitions').get().n, 0);
  }
});

test('revocation, policy changes, original expiry and clock rollback deny acquisition and final signing', t => {
  for (const mutate of [f => f.access.recordPendingRevocation(f.job),
    f => { f.policy.policy_epoch++; }, f => { f.policy.workflow_sha256 = 'f'.repeat(64); },
    f => { f.state.now += 60000; }, f => { f.state.now--; },
    f => { f.state.now = f.actor.expiresAt; },
    f => { f.store.db.exec("UPDATE content_access_principals SET state='denied',revision=revision+1"); }]) {
    const f = fixture(t); f.dispatch(); const lease = f.ledger.begin(f.query()); mutate(f);
    assert.throws(() => f.ledger.current(lease));
    let signed = false; assert.throws(() => f.ledger.finish(lease, () => { signed = true; return {}; })); assert.equal(signed, false);
  }
  const f = fixture(t); f.dispatch(); const lease = f.ledger.begin(f.query());
  assert.throws(() => f.ledger.finish(lease, () => { f.state.now += 60000; return {}; }));
  assert.equal(f.store.db.prepare('SELECT state FROM management_evidence_acquisitions').get().state, 'acquiring');
});

test('interrupted acquisition remains consumed after restart and fresh login cannot renew its original authority', t => {
  const dir = mkdtempSync(join(tmpdir(), 'mrn-management-evidence-')); t.after(() => rmSync(dir, { recursive: true, force: true }));
  const path = join(dir, 'ledger.sqlite'), f = fixture(t, path); f.dispatch(); const query = f.query(); f.ledger.begin(query);
  f.access.recordPendingRevocation(f.job); f.store.close(); f.state.closed = true;
  const reopened = new Store(path); t.after(() => reopened.close());
  const access = new ManagementAccessLedger(reopened, { connectionId: 'con_fixture', registry: f.registry, now: () => f.state.now });
  const ledger = new ManagementEvidenceLedger(access, f.options);
  assert.throws(() => ledger.begin(query));
  assert.throws(() => access.assertQueueAllowed({ ...f.actor, expiresAt: f.actor.expiresAt + 3600000 }, f.request));
  assert.equal(reopened.db.prepare('SELECT count(*) AS n FROM management_evidence_acquisitions').get().n, 1);
});

test('storage failure rolls back local state and never supplies an evidence answer', t => {
  const f = fixture(t); f.dispatch();
  f.store.db.exec("CREATE TRIGGER reject_evidence BEFORE INSERT ON management_evidence_acquisitions BEGIN SELECT RAISE(ABORT,'fixture storage failure'); END");
  assert.throws(() => f.ledger.begin(f.query()));
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_evidence_acquisitions').get().n, 0);
});
