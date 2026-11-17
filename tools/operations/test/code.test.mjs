import test from 'node:test';
import assert from 'node:assert/strict';
import { actor, setup } from './helpers.mjs';
import { digest } from '../src/contracts.mjs';
import { toolNames } from '../src/mainwp.mjs';
import { response } from './fixtures/mainwp.mjs';

test('code inspection preserves exact target and verifies bytes before returning UTF-8 content', async t => {
  const f = setup(); t.after(f.close);
  const bytes = Buffer.from('<?php // reviewed fixture\n');
  const state = { success: true, exists: true, sha256: digest(bytes), bytes: bytes.length, content_base64: bytes.toString('base64'), code_storage: { backup_ready: true, atomic_ready: true } };
  const call = f.client.callTool.bind(f.client); const path = 'wp-content/themes/example-child/functions.php';
  f.client.callTool = async request => {
    if (request.name !== toolNames.codeInspect) return call(request);
    assert.deepEqual(request.arguments, { site_id: 1, site_url: f.state.url + '/', path, include_content: true });
    return response(state);
  };
  const person = { ...actor, identityProvider: 'google-oauth2' };
  f.service.verifyStaff = async () => {};
  const result = await f.service.inspectCode(person, { website: 'example', path });
  assert.equal(result.content, bytes.toString()); assert.equal(result.sha256, digest(bytes));
  assert.deepEqual(f.state.calls, [toolNames.site, toolNames.sync, toolNames.site]);
  assert.equal(result.storage.backupReady, true);
  state.sha256 = 'a'.repeat(64);
  await assert.rejects(() => f.service.inspectCode(person, { website: 'example', path }), { code: 'CODE_PRECONDITION' });
  state.content_withheld = true; delete state.content_base64;
  const withheld = await f.service.inspectCode(person, { website: 'example', path });
  assert.equal(withheld.content, null); assert.equal(withheld.contentWithheld, true);
  await assert.rejects(() => f.service.inspectCode(person, { website: 'example', path: 'wp-content/themes/example-child/../secret.php' }));
});

test('code inspection stops before dispatch without current Google membership and discards results after revocation', async t => {
  const f = setup(); t.after(f.close);
  const path = 'wp-content/themes/example-child/script.js';
  const person = { ...actor, identityProvider: 'google-oauth2', scopes: ['mrn:operations'] };
  await assert.rejects(() => f.service.inspectCode(person, { website: 'example', path }), { code: 'AUTH_REQUIRED' });
  assert.deepEqual(f.state.calls, []);
  let current = true, reads = 0;
  f.service.verifyStaff = async user => {
    assert.equal(user.subject, person.subject);
    if (!current) throw Object.assign(new Error('Fixture staff access revoked.'), { code: 'STAFF_ACCESS_DENIED' });
  };
  const original = f.client.callTool.bind(f.client);
  f.client.callTool = async request => {
    if (request.name !== toolNames.codeInspect) return original(request);
    reads++; current = false;
    return response({ success: true, exists: false, sha256: 'absent', bytes: 0 });
  };
  const args = { website: 'example', path, includeContent: false };
  await assert.rejects(() => f.service.inspectCode(person, args), { code: 'STAFF_ACCESS_DENIED' });
  const syncCalls = f.state.calls.length;
  await assert.rejects(() => f.service.inspectCode(person, args), { code: 'STAFF_ACCESS_DENIED' });
  assert.equal(f.state.calls.length, syncCalls); assert.equal(reads, 1);
  const audit = f.store.db.prepare('SELECT subject,outcome FROM audit WHERE event=?').all('code:read');
  assert.deepEqual(audit.map(row => row.outcome), ['attempted', 'failed', 'attempted', 'failed']);
  assert.ok(audit.every(row => row.subject === person.subject));
});
