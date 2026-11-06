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
  const result = await f.service.inspectCode(person, { website: 'example', path });
  assert.equal(result.content, bytes.toString()); assert.equal(result.sha256, digest(bytes));
  assert.equal(result.storage.backupReady, true);
  state.sha256 = 'a'.repeat(64);
  await assert.rejects(() => f.service.inspectCode(person, { website: 'example', path }), { code: 'CODE_PRECONDITION' });
  state.content_withheld = true; delete state.content_base64;
  const withheld = await f.service.inspectCode(person, { website: 'example', path });
  assert.equal(withheld.content, null); assert.equal(withheld.contentWithheld, true);
  await assert.rejects(() => f.service.inspectCode(person, { website: 'example', path: 'wp-content/themes/example-child/../secret.php' }));
});
