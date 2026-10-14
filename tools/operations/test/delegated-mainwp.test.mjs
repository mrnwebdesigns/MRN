import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, randomBytes, verify } from 'node:crypto';
import { IdentitySigner, delegationDigest, delegationPrefix } from '../src/delegation.mjs';
import { DelegatedMainwpClient, delegationTools } from '../src/delegated-mainwp.mjs';

const response = value => ({ content: [{ type: 'text', text: JSON.stringify(value) }] });
function fixture() {
  const { privateKey, publicKey } = generateKeyPairSync('ed25519');
  const signer = new IdentitySigner({ keyId: 'fixture-only', privateKey, connectionId: 'con_MRNGoogle' });
  const state = { calls: [], dispatched: [], consumed: new Set(), challenges: new Map(), revoked: new Set(), corrupt: null };
  const actor = id => Object.freeze({ subject: `google-oauth2|${id}`, googleSubject: id, verifiedEmail: `staff${id}@mrnwebdesigns.com`, expiresAt: Date.now() + 300000, identityProvider: 'google-oauth2', googleConnection: 'con_MRNGoogle', hostedDomain: 'mrnwebdesigns.com', scopes: ['mrn:operations', 'mrn:operations:write'] });
  const client = {
    async readResource() { return { contents: [{ text: JSON.stringify({ connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 110 }) }] }; },
    async listTools() { return { tools: Object.values(delegationTools).map(name => ({ name, annotations: { readOnlyHint: false, destructiveHint: name === delegationTools.run } })) }; },
    async close() {},
    async callTool({ name, arguments: args }) {
      state.calls.push(name);
      if (name === delegationTools.challenge) {
        const challenge = { nonce: randomBytes(32).toString('hex'), expires_at: Math.floor(Date.now() / 1000) + 60, authenticated_connection_sha256: 'a'.repeat(64), policy_sha256: 'b'.repeat(64) };
        state.challenges.set(challenge.nonce, args); return response(challenge);
      }
      assert.ok([delegationTools.access, delegationTools.run, delegationTools.read, delegationTools.sync].includes(name), 'Never call a raw privileged backend ability');
      if (name === delegationTools.run && !args.user_confirmed) {
        assert.equal(args.confirm, true); state.preview = structuredClone(args);
        return response({ status: 'CONFIRMATION_REQUIRED', confirmation_token: 'fixture-confirmation' });
      }
      if (name === delegationTools.run) {
        assert.deepEqual(args.delegation, state.preview.delegation);
        assert.equal(args.arguments_json, state.preview.arguments_json);
        assert.equal(args.confirmation_token, 'fixture-confirmation');
      }
      const bytes = Buffer.from(args.delegation.payload_base64, 'base64');
      assert.equal(verify(null, Buffer.concat([Buffer.from(delegationPrefix), bytes]), publicKey, Buffer.from(args.delegation.signature_base64, 'base64')), true);
      const claims = JSON.parse(bytes); const challenge = state.challenges.get(claims.nonce);
      assert.equal(challenge.ability, claims.ability); assert.equal(challenge.arguments_sha256, claims.arguments_sha256);
      assert.equal(state.consumed.has(claims.nonce), false); state.consumed.add(claims.nonce);
      if (state.revoked.has(claims.google_sub)) throw new Error('Membership revoked');
      const person = { oauth_subject: claims.oauth_subject, google_sub: claims.google_sub, email: claims.email };
      if (name === delegationTools.access) return response({ ...person, schema: 1, roles: ['reader', 'preparer', 'approver', 'executor'], portfolio_access: true, expires_at: claims.expires_at, policy_sha256: claims.policy_sha256 });
      assert.equal(args.ability, claims.ability); assert.equal(delegationDigest(JSON.parse(args.arguments_json)), claims.arguments_sha256);
      state.dispatched.push({ person, ability: args.ability, args: JSON.parse(args.arguments_json) });
      const result = { schema: 1, request_id: claims.request_id, ability: args.ability, actor: person, result: { id: 85 }, policy_sha256: claims.policy_sha256 };
      state.corrupt?.(result); return response(result);
    },
  };
  return { state, actor, client, signer, delegated: id => new DelegatedMainwpClient(client, actor(id), signer) };
}

test('shared backend connection keeps parallel people isolated with no caller-supplied identity', async () => {
  const f = fixture(); const alice = f.delegated('123'), bob = f.delegated('456');
  await Promise.all([alice.verifyStaff(), bob.verifyStaff()]);
  await Promise.all([alice.callTool({ name: 'get_site_v1', arguments: { site_id_or_domain: 'https://example.test' } }), bob.callTool({ name: 'get_site_v1', arguments: { site_id_or_domain: 'https://another.test' } })]);
  assert.deepEqual(f.state.dispatched.map(d => d.person.google_sub).sort(), ['123', '456']);
  assert.equal(f.state.consumed.size, 4);
  assert.ok(f.state.calls.every(n => Object.values(delegationTools).includes(n)));
  f.state.revoked.add('123');
  await assert.rejects(() => alice.callTool({ name: 'get_site_v1', arguments: { site_id_or_domain: 'https://example.test' } }));
  assert.equal(f.state.dispatched.length, 2);
  await bob.callTool({ name: 'get_site_v1', arguments: { site_id_or_domain: 'https://another.test' } });
  assert.equal(f.state.dispatched.length, 3);
});

test('mismatched delegation responses fail and never retry or fall back', async () => {
  for (const corrupt of [r => { r.actor.google_sub = 'another'; }, r => { r.ability = 'mainwp/other-v1'; }, r => { r.policy_sha256 = 'c'.repeat(64); }, r => { r.request_id = '00000000-0000-4000-8000-000000000000'; }]) {
    const f = fixture(); f.state.corrupt = corrupt;
    await assert.rejects(() => f.delegated('123').callTool({ name: 'get_site_v1', arguments: {} }), { code: 'DELEGATION_MISMATCH' });
    assert.equal(f.state.calls.length, 2); assert.equal(f.state.dispatched.length, 1);
  }
  const f = fixture(); f.client.listTools = async () => ({ tools: [] });
  await assert.rejects(() => f.delegated('123').verifyStaff(), { code: 'DELEGATION_UNAVAILABLE' });
  assert.equal(f.state.calls.length, 0);
});

test('write confirmation reuses its exact envelope; reads and single-site sync do not request write approval', async () => {
  const f = fixture(); const client = f.delegated('123');
  await client.callTool({ name: 'mrn_mainwp__start_database_backup_v1', arguments: { site_id: 85 } });
  assert.deepEqual(f.state.calls, [delegationTools.challenge, delegationTools.run, delegationTools.run]);
  assert.equal(f.state.consumed.size, 1); assert.equal(f.state.dispatched.length, 1);
  await client.callTool({ name: 'sync_sites_v1', arguments: { site_ids: [85] } });
  assert.equal(f.state.calls.at(-1), delegationTools.sync);
  await assert.rejects(() => client.callTool({ name: 'sync_sites_v1', arguments: { site_ids: [] } }), { code: 'TARGET_MISMATCH' });
  await assert.rejects(() => client.callTool({ name: 'mrn_mainwp__configure_code_policy_v1', arguments: {} }), { code: 'CAPABILITY_DENIED' });
});

test('read-only bearer cannot obtain a delegated write challenge or start a backup', async () => {
  const f = fixture();
  const client = new DelegatedMainwpClient(f.client, { ...f.actor('123'), scopes: ['mrn:operations'] }, f.signer);
  for (const name of ['start_database_backup', 'prepare_code_change', 'approve_code_change', 'execute_code_change',
    'prepare_content_change', 'approve_content_change', 'execute_content_change', 'prepare_content_rollback', 'update_stack_plugin']) {
    await assert.rejects(() => client.callTool({ name: `mrn_mainwp__${name}_v1`, arguments: {} }), { code: 'INSUFFICIENT_SCOPE' });
  }
  assert.equal(f.state.calls.length, 0);
  await client.callTool({ name: 'get_site_v1', arguments: { site_id_or_domain: 'https://example.test' } });
  await client.callTool({ name: 'sync_sites_v1', arguments: { site_ids: [85] } });
  assert.deepEqual(f.state.dispatched.map(d => d.ability), ['mainwp/get-site-v1', 'mainwp/sync-sites-v1']);
});

test('only the exact qualified v2 policy read uses the signed read wrapper with its target bound', async () => {
  const f = fixture(); const client = new DelegatedMainwpClient(f.client, { ...f.actor('123'), scopes: ['mrn:operations'] }, f.signer);
  const args = { site_id: 85, site_url: 'https://example.test', target: { kind: 'post', post_type: 'page', post_id: 42, field: 'post_content' } };
  await client.callTool({ name: 'mrn_mainwp__get_content_policy_v2', arguments: args });
  assert.deepEqual(f.state.calls, [delegationTools.challenge, delegationTools.read]);
  assert.equal(f.state.dispatched[0].ability, 'mrn-mainwp/get-content-policy-v2'); assert.deepEqual(f.state.dispatched[0].args, args);
  for (const name of ['mrn_mainwp__get_content_policy_v3', 'mrn_mainwp__configure_content_policy_v2', 'mrn_mainwp__register_content_profile_v1']) {
    await assert.rejects(() => client.callTool({ name, arguments: args }), { code: 'CAPABILITY_DENIED' });
  }
  assert.equal(f.state.calls.length, 2);
});

test('unsafe GET metadata or missing confirmation boundary blocks before sending an identity assertion', async () => {
  for (const name of Object.values(delegationTools)) {
    const f = fixture();
    f.client.listTools = async () => ({ tools: Object.values(delegationTools).map(n => ({ name: n, annotations: { readOnlyHint: n === name, destructiveHint: n === delegationTools.run } })) });
    await assert.rejects(() => f.delegated('123').callTool({ name: 'get_site_v1', arguments: {} }), { code: 'DELEGATION_TRANSPORT' });
    assert.equal(f.state.calls.length, 0);
  }
});

test('portfolio updates and existing batch status use signed read delegation only', async () => {
  const f = fixture(), client = f.delegated('123');
  await client.callTool({ name: 'list_updates_v1', arguments: { page: 1, per_page: 100, types: ['plugins'] } });
  await client.callTool({ name: 'get_batch_job_status_v1', arguments: { job_id: 'sync_fixture-123' } });
  assert.deepEqual(f.state.calls, [delegationTools.challenge, delegationTools.read, delegationTools.challenge, delegationTools.read]);
  assert.deepEqual(f.state.dispatched.map(d => d.ability), ['mainwp/list-updates-v1', 'mainwp/get-batch-job-status-v1']);
  assert.ok(f.state.dispatched.every(d => d.person.google_sub === '123'));
});
