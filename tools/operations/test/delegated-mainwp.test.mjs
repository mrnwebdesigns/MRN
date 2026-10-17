import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, randomBytes, randomUUID, verify } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { IdentitySigner, delegationDigest, delegationPrefix } from '../src/delegation.mjs';
import { DelegatedMainwpClient, delegationTools, qualificationQueueTool } from '../src/delegated-mainwp.mjs';
import { Store } from '../src/store.mjs';
import { accessRegistry } from './fixtures/access-registry.mjs';
import { ContentAccessLedger } from '../src/content-access-store.mjs';
import { ManagementAccessLedger } from '../src/content-management-access-store.mjs';
import { managementAccessFixture } from './fixtures/management-access.mjs';

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
      assert.ok([delegationTools.access, delegationTools.run, delegationTools.read, delegationTools.sync, qualificationQueueTool].includes(name), 'Never call a raw privileged backend ability');
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

test('structured content reads use signed per-person read wrappers without granting write abilities', async () => {
  const f = fixture(), person = { ...f.actor('123'), scopes: ['mrn:operations'] };
  const client = new DelegatedMainwpClient(f.client, person, f.signer);
  for (const name of ['mrn_mainwp__list_content_records_v1', 'mrn_mainwp__read_content_record_v1'])
    await client.callTool({ name, arguments: { site_id: 73, site_url: 'https://fixture.example.invalid', post_type: 'page' } });
  assert.ok(f.state.dispatched.every(row => row.person.oauth_subject === person.subject));
  assert.deepEqual(f.state.dispatched.map(row => row.ability), ['mrn-mainwp/list-content-records-v1', 'mrn-mainwp/read-content-record-v1']);
  assert.ok(!f.state.calls.includes(delegationTools.run));
  await assert.rejects(() => client.callTool({ name: 'mrn_mainwp__execute_content_edit_v1', arguments: {} }), { code: 'CAPABILITY_DENIED' });
});

function queueFixture(t) {
  const f = fixture(), store = new Store(':memory:'); t.after(() => store.close());
  const vectors = JSON.parse(readFileSync(new URL('./fixtures/content-qualification-contract.json', import.meta.url)));
  const request = vectors.cases.find(c => c.definition === 'request_input').value;
  const { registry, registryData, policy } = accessRegistry(request.site_url);
  const ledger = new ContentAccessLedger(store, { connectionId: 'con_MRNGoogle', registry });
  const job = { ...vectors.cases.find(c => c.definition === 'job_output').value, expires_at: Math.floor(Date.now() / 1000) + 300,
    recipe_catalog_sha256: 'c'.repeat(64), recipe_sha256: null };
  const expected = { authorization_policy_sha256: job.authorization_policy_sha256, recipe_catalog_sha256: job.recipe_catalog_sha256, recipe_sha256: 'd'.repeat(64) };
  f.state.corrupt = result => { result.result = job; };
  const list = f.client.listTools;
  f.client.listTools = async () => ({ tools: [...(await list()).tools, { name: qualificationQueueTool, annotations: { readOnlyHint: false, destructiveHint: false } }] });
  return { ...f, store, ledger, registry, registryData, policy, request, job, expected, queue: f.delegated('123') };
}

function managementQueueFixture(t) {
  const f = fixture(), store = new Store(':memory:'); t.after(() => store.close());
  const actor = f.actor('123'), values = managementAccessFixture(actor, Date.now());
  values.context.binding.authorization_policy_sha256 = 'c'.repeat(64);
  values.context.binding_sha256 = delegationDigest(values.context.binding);
  values.expected.pins.authorization_policy_sha256 = 'c'.repeat(64);
  values.expected.delegationPolicySha256 = 'b'.repeat(64);
  const { registry, registryData, policy } = accessRegistry(values.request.site_url);
  const ledger = new ManagementAccessLedger(store, { connectionId: 'con_MRNGoogle', registry });
  f.state.corrupt = result => { result.result = values.context; };
  return { ...f, ...values, store, registry, registryData, policy, ledger, queue: new DelegatedMainwpClient(f.client, actor, f.signer) };
}

test('private management preview and migration use the same human delegation without enabling execution', async t => {
  const f = managementQueueFixture(t);
  f.state.corrupt = result => { result.result = result.ability === 'mrn-mainwp/preview-content-edit-v1'
    ? f.expected.evidence.review : f.expected.evidence.migration; };
  assert.deepEqual(await f.queue.previewManagementEdit(f.expected.request), f.expected.evidence.review);
  const migrationInput = { site_id: f.request.site_id, site_url: f.request.site_url, operation_id: f.request.operation_id };
  assert.deepEqual(await f.queue.readManagementMigration(migrationInput), f.expected.evidence.migration);
  assert.deepEqual(f.state.dispatched.map(row => row.ability), ['mrn-mainwp/preview-content-edit-v1', 'mrn-mainwp/get-content-edit-migration-v1']);
  assert.ok(f.state.dispatched.every(row => row.person.google_sub === '123'));
  for (const name of ['preview_content_edit', 'approve_content_edit', 'execute_content_edit'])
    await assert.rejects(() => f.queue.callTool({ name: `mrn_mainwp__${name}_v1`, arguments: {} }), { code: 'CAPABILITY_DENIED' });
  const readOnly = new DelegatedMainwpClient(f.client, { ...f.queue.actor, scopes: ['mrn:operations'] }, f.signer);
  await assert.rejects(() => readOnly.previewManagementEdit(f.expected.request), { code: 'INSUFFICIENT_SCOPE' });
  await assert.rejects(() => f.queue.previewManagementEdit({ ...f.expected.request, post_id: 0 }), { code: 'CAPABILITY_UNAVAILABLE' });
});

test('private management preview refuses changed target or migration identity without retry', async t => {
  const f = managementQueueFixture(t);
  f.state.corrupt = result => { result.result = { ...f.expected.evidence.review, site_id: f.request.site_id + 1 }; };
  await assert.rejects(() => f.queue.previewManagementEdit(f.expected.request), { code: 'CONTENT_MANAGEMENT_MISMATCH' });
  f.state.corrupt = result => { result.result = { ...f.expected.evidence.migration, operation_id: randomUUID() }; };
  await assert.rejects(() => f.queue.readManagementMigration({ site_id: f.request.site_id, site_url: f.request.site_url, operation_id: f.request.operation_id }), { code: 'CONTENT_MANAGEMENT_MISMATCH' });
  assert.equal(f.state.dispatched.length, 2);
});

test('management queue uses the existing confirmed run with original identity and immutable admitted context', async t => {
  const f = managementQueueFixture(t);
  const result = await f.queue.queueManagementQualification(f.request, { ledger: f.ledger, admit: async () => f.expected });
  assert.deepEqual(result, f.context);
  assert.deepEqual(f.state.calls, [delegationTools.challenge, delegationTools.run, delegationTools.run]);
  assert.equal(f.state.dispatched.length, 1);
  assert.equal(f.state.dispatched[0].ability, 'mrn-mainwp/request-content-edit-qualification-v1');
  assert.equal(f.state.dispatched[0].person.google_sub, '123');
  const mapping = JSON.parse(f.store.db.prepare('SELECT body FROM management_access_jobs').get().body);
  assert.equal(mapping.context.binding_sha256, f.context.binding_sha256);
  assert.equal(mapping.pins.authorization_policy_sha256, 'c'.repeat(64));
  assert.notEqual(mapping.pins.authorization_policy_sha256, f.expected.delegationPolicySha256);
  assert.equal(mapping.actor.subject, f.queue.actor.subject);
  await assert.rejects(() => f.queue.callTool({ name: 'mrn_mainwp__request_content_edit_qualification_v1', arguments: f.request }), { code: 'CAPABILITY_DENIED' });
});

test('management qualification refuses unavailable worker, caller identity and changed policy before queue dispatch', async t => {
  const f = managementQueueFixture(t);
  await assert.rejects(() => f.queue.queueManagementQualification(f.request), { code: 'QUALIFICATION_WORKER_UNAVAILABLE' });
  await assert.rejects(() => f.queue.queueManagementQualification({ ...f.request, email: 'staff@mrnwebdesigns.com' }, { ledger: f.ledger, admit: async () => f.expected }));
  await assert.rejects(() => f.queue.queueManagementQualification(f.request, { ledger: f.ledger, admit: async () => ({}) }), { code: 'ACCESS_MAPPING' });
  assert.equal(f.state.calls.length, 0);
  for (const delegationPolicySha256 of [undefined, null, '', 'invalid']) {
    await assert.rejects(() => f.queue.queueManagementQualification(f.request,
      { ledger: f.ledger, admit: async () => ({ ...f.expected, delegationPolicySha256 }) }), { code: 'ACCESS_MAPPING' });
  }
  assert.equal(f.state.calls.length, 0);
  for (const delegationPolicySha256 of ['e'.repeat(64), f.expected.pins.authorization_policy_sha256]) {
    await assert.rejects(() => f.queue.queueManagementQualification(f.request,
      { ledger: f.ledger, admit: async () => ({ ...f.expected, delegationPolicySha256 }) }), { code: 'DELEGATION_MISMATCH' });
  }
  assert.deepEqual(f.state.calls, [delegationTools.challenge, delegationTools.challenge]);
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_access_jobs').get().n, 0);
});

test('valid team delegation cannot substitute for independent content authorization admission', async t => {
  const f = managementQueueFixture(t), expected = structuredClone(f.expected);
  expected.pins.authorization_policy_sha256 = expected.delegationPolicySha256;
  await assert.rejects(() => f.queue.queueManagementQualification(f.request,
    { ledger: f.ledger, admit: async () => expected }), { code: 'ACCESS_MAPPING' });
  assert.equal(f.state.dispatched.length, 1);
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_access_jobs').get().n, 0);
});

test('management queue cannot retain an altered actor, source, preview or environment from an uncertain result', async t => {
  for (const failure of ['actor', 'source', 'preview', 'permission', 'environment', 'uncertain', 'storage']) {
    const f = managementQueueFixture(t);
    f.state.corrupt = result => {
      result.result = structuredClone(f.context);
      if (failure === 'actor') result.actor.google_sub = '999';
      if (failure === 'source') result.result.binding.provider_source_sha = 'e'.repeat(40);
      if (failure === 'preview') result.result.binding.candidate_sha256 = 'e'.repeat(64);
      result.result.binding_sha256 = delegationDigest(result.result.binding);
      if (failure === 'permission') f.policy.emailDomains[0].portfolio.actions = ['read'];
      if (failure === 'environment') f.registryData.websites[0].environments[0].name = 'staging';
      if (failure === 'uncertain') throw new Error('response lost');
      if (failure === 'storage') f.store.db.exec("CREATE TRIGGER reject_management_map BEFORE INSERT ON management_access_jobs BEGIN SELECT RAISE(ABORT,'fixture storage failure'); END");
    };
    await assert.rejects(() => f.queue.queueManagementQualification(f.request, { ledger: f.ledger, admit: async () => f.expected }));
    assert.equal(f.state.dispatched.length, 1); // No automatic retry or fallback.
    assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_access_jobs').get().n, 0);
  }
});

test('private qualification queue binds the verified requester only after service worker admission and exact response', async t => {
  const f = queueFixture(t); let admission = 0;
  const output = await f.queue.queueQualification(f.request, { ledger: f.ledger, admit: async (actor, request) => {
    admission++; assert.equal(actor.googleSubject, '123'); assert.deepEqual(request, f.request); return f.expected;
  } });
  assert.deepEqual(output, f.job); assert.equal(admission, 1);
  assert.deepEqual(f.state.calls, [delegationTools.challenge, qualificationQueueTool]);
  const mapping = JSON.parse(f.store.db.prepare('SELECT body FROM content_access_jobs').get().body);
  assert.equal(mapping.actor.subject, 'google-oauth2|123'); assert.equal(mapping.actor.verifiedEmail, 'staff123@mrnwebdesigns.com');
  assert.equal(f.store.db.prepare('SELECT event FROM content_access_audit').get().event, 'queue-mapped');
  await assert.rejects(() => f.queue.callTool({ name: qualificationQueueTool, arguments: f.request }), { code: 'CAPABILITY_DENIED' });
});

test('missing worker, changed actor, revoked requester or bad pins leave no usable qualification map', async t => {
  const f = queueFixture(t);
  await assert.rejects(() => f.queue.queueQualification(f.request), { code: 'QUALIFICATION_WORKER_UNAVAILABLE' });
  await assert.rejects(() => f.queue.queueQualification(f.request, { ledger: f.ledger, admit: async () => { throw new Error('worker unavailable'); } }));
  assert.equal(f.state.calls.length, 0);
  f.state.corrupt = result => { result.result = f.job; result.actor.email = 'other@mrnwebdesigns.com'; };
  await assert.rejects(() => f.queue.queueQualification(f.request, { ledger: f.ledger, admit: async () => f.expected }), { code: 'DELEGATION_MISMATCH' });
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM content_access_jobs').get().n, 0);
  f.state.corrupt = result => { result.result = f.job; };
  await assert.rejects(() => f.queue.queueQualification(f.request, { ledger: f.ledger, admit: async () => ({ ...f.expected, recipe_catalog_sha256: 'e'.repeat(64) }) }), { code: 'ACCESS_MAPPING' });
  f.state.revoked.add('123');
  await assert.rejects(() => f.queue.queueQualification(f.request, { ledger: f.ledger, admit: async () => f.expected }));
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM content_access_jobs').get().n, 0);
});

test('local content permission and the same environment are rechecked across asynchronous queue admission', async t => {
  for (const phase of ['before', 'admission', 'response']) {
    const f = queueFixture(t);
    const deny = () => { f.policy.emailDomains[0].portfolio.actions = ['read']; };
    if (phase === 'before') deny();
    if (phase === 'response') f.state.corrupt = result => { result.result = f.job; deny(); };
    await assert.rejects(() => f.queue.queueQualification(f.request, { ledger: f.ledger, admit: async () => {
      if (phase === 'admission') deny(); return f.expected;
    } }));
    if (phase !== 'response') assert.equal(f.state.calls.length, 0);
    assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM content_access_jobs').get().n, 0);
  }
  const f = queueFixture(t);
  await assert.rejects(() => f.queue.queueQualification(f.request, { ledger: f.ledger, admit: async () => {
    f.registryData.websites[0].environments[0].name = 'staging'; return f.expected;
  } }));
  assert.equal(f.state.calls.length, 0);
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM content_access_jobs').get().n, 0);
});

test('a fresh queue may bind a newly configured authorized environment', async t => {
  const f = queueFixture(t); f.registryData.websites[0].environments[0].name = 'staging';
  assert.deepEqual(await f.queue.queueQualification(f.request, { ledger: f.ledger, admit: async () => f.expected }), f.job);
  const mapping = JSON.parse(f.store.db.prepare('SELECT body FROM content_access_jobs').get().body);
  assert.equal(mapping.site.environment, 'staging'); assert.equal(mapping.site.url, f.request.site_url);
});

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
