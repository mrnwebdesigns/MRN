import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { readManagedContent } from '../src/content-management-read.mjs';
import { coreReadContract } from '../src/content-core-read.mjs';
import { delegationDigest } from '../src/delegation.mjs';
import { toolNames } from '../src/mainwp.mjs';
import { actor, setup } from './helpers.mjs';
import { response } from './fixtures/mainwp.mjs';
import { OpsError } from '../src/contracts.mjs';

const site = { id: 7, url: 'https://core.fixture.example.invalid' };
function wire(type = 'page', target = site) {
  const snapshot = { scope: 'wordpress-core-v1', site_id: target.id, site_url: target.url, post_id: 10498,
    post_type: type, core: { post_title: 'Privacy fixture é雪', post_content: '<p>Complete native content.</p>',
      post_excerpt: 'Exact excerpt', post_name: 'privacy', post_status: 'publish' }, modified_gmt: '2026-11-23 00:00:00',
    post_record_sha256: 'a'.repeat(64), runtime: { wordpress: '7.1.5', php_minor: '8.3', stylesheet: 'fixture',
      template: 'fixture', plugins_sha256: 'b'.repeat(64) } };
  return { success: true, snapshot, snapshot_sha256: delegationDigest(snapshot), affected_paths: ['/privacy/'] };
}

test('explicit core page/post reads return all requested native properties without a combined read or invented ACF result', async () => {
  for (const [kind, type] of [['pages', 'page'], ['posts', 'post']]) {
    const calls = [], source = wire(type);
    const result = await readManagedContent({ async call(name, args) { calls.push({ name, args }); return source; } }, site,
      { kind, recordId: 10498, view: 'core' });
    assert.deepEqual(calls, [{ name: toolNames.contentCore, args: { site_id: 7, site_url: site.url, post_type: type, post_id: 10498 } }]);
    assert.deepEqual(result.fields, source.snapshot.core);
    assert.equal(result.coreComplete, true); assert.equal(result.view, 'core'); assert.equal(result.recordId, 10498);
    assert.equal(result.native.postType, type); assert.equal(result.native.modifiedGmt, source.snapshot.modified_gmt);
    assert.deepEqual(result.acf, { status: 'not_requested' }); assert.equal(result.capabilities.acfIncluded, false);
    assert.equal(result.snapshotScope, 'wordpress-core-v1'); assert.equal(result.snapshotSha256, delegationDigest(source.snapshot));
  }
});

test('core read refuses wrong site, record, scope, hash, response extras, unsafe URLs and oversized data', async () => {
  for (const mutate of [
    v => { v.snapshot.site_id++; }, v => { v.snapshot.site_url = 'https://wrong.fixture.example.invalid'; },
    v => { v.snapshot.post_id++; }, v => { v.snapshot.post_type = 'post'; },
    v => { v.snapshot.scope = 'content-management-v1'; }, v => { v.snapshot_sha256 = 'f'.repeat(64); },
    v => { v.snapshot.acf_values = {}; }, v => { v.affected_paths = ['//outside.example.invalid/']; },
    v => { v.affected_paths = ['/?page_id=20']; },
    v => { v.snapshot.core.post_content = 'x'.repeat(196608); v.snapshot_sha256 = delegationDigest(v.snapshot); },
  ]) {
    const value = wire(); mutate(value);
    await assert.rejects(() => readManagedContent({ async call() { return value; } }, site,
      { kind: 'pages', recordId: 10498, view: 'core' }));
  }
  let calls = 0;
  for (const input of [{ kind: 'users', recordId: 10498, view: 'core' }, { kind: 'pages', recordId: 0, view: 'core' },
    { kind: 'pages', view: 'core' }, { kind: 'pages', recordId: 10498, view: 'unknown' }]) {
    await assert.rejects(() => readManagedContent({ async call() { calls++; } }, site, input));
  }
  assert.equal(calls, 0);
});

test('core path uses the same authorized staff session and fails closed without its capability or on revocation', async t => {
  const f = setup(); t.after(f.close); f.state.coreContent = true;
  const person = { ...actor, identityProvider: 'google-oauth2' };
  let calls = 0, restCalls = 0, revoked = false;
  f.service.verifyStaff = async () => { if (revoked) throw new OpsError('STAFF_ACCESS_DENIED', 'Fixture revoked.'); };
  f.service.content = { async read() { restCalls++; }, async search() { restCalls++; } };
  const connect = f.service.connect;
  f.service.connect = async (...args) => {
    const client = await connect(...args), original = client.callTool.bind(client);
    client.callTool = async request => {
      if (request.name !== toolNames.contentCore) return original(request);
      calls++; return response(wire('page', { id: f.state.id, url: f.state.url }));
    };
    return client;
  };
  const input = { website: 'example', kind: 'pages', recordId: 10498, view: 'core' };
  assert.equal((await f.service.readContent(person, input)).fields.post_title, 'Privacy fixture é雪');
  const rows = f.store.db.prepare("SELECT subject,outcome FROM audit WHERE event='content:read'").all();
  assert.deepEqual(rows.map(row => row.outcome), ['attempted', 'returned']); assert.ok(rows.every(row => row.subject === person.subject));
  revoked = true;
  await assert.rejects(() => f.service.readContent(person, input), { code: 'STAFF_ACCESS_DENIED' });
  assert.equal(calls, 1);
  revoked = false; f.state.coreContent = false;
  await assert.rejects(() => f.service.readContent(person, input), { code: 'CAPABILITY_MISSING' });
  await assert.rejects(() => f.service.readContent({ ...person, identityProvider: 'auth0' }, { ...input, website: 'unknown' }));
  assert.equal(calls, 1); assert.equal(restCalls, 0);
});

test('new read contract is separate from the byte-frozen management/write contract', () => {
  assert.equal(coreReadContract.adapter, 'wordpress-core-v1');
  assert.deepEqual(Object.keys(coreReadContract.abilities), ['read-content-core-v1']);
  assert.equal(coreReadContract.abilities['read-content-core-v1'].role, 'reader');
  const old = JSON.parse(readFileSync(new URL('../contracts/content-management-v1.json', import.meta.url)));
  assert.equal(old.abilities['read-content-core-v1'], undefined);
  assert.equal(coreReadContract.limits.maximum_result_bytes, 196608);
  assert.equal(coreReadContract.limits.transport_deadline_seconds, 120);
});
