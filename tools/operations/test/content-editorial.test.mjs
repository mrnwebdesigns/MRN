import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js';
import { createMcp } from '../src/mcp.mjs';
import { ContentWorkflow } from '../src/content-workflow.mjs';
import { ProtectedContentQa } from '../src/content-qa.mjs';
import { managementCandidate, managementCandidateHash } from '../src/content-management-contract.mjs';
import { editorialFixtureUrl } from '../src/content-editorial.mjs';
import { delegationDigest } from '../src/delegation.mjs';
import { toolNames } from '../src/mainwp.mjs';
import { response } from './fixtures/mainwp.mjs';
import { setup } from './helpers.mjs';

const person = { subject: 'google-oauth2|123', googleSubject: '123', verifiedEmail: 'fixture@mrnwebdesigns.com',
  identityProvider: 'google-oauth2', hostedDomain: 'mrnwebdesigns.com', googleConnection: 'con_MRNGoogle',
  expiresAt: Date.now() + 600000, scopes: ['mrn:operations', 'mrn:operations:write'] };
const vectors = JSON.parse(readFileSync(new URL('./fixtures/content-management-v1.json', import.meta.url))).cases;
const base = { website: 'example', environment: 'development', postType: 'page', reason: 'Editorial contract fixture' };
const draft = { ...base, requestKey: 'new-draft', title: 'My draft', slug: 'my-draft' };
const tools = { create: 'prepare_content_draft', edit: 'prepare_content_core_edit', status: 'prepare_content_status' };

// This is an in-process MCP and MainWP transport fixture. It never produces a
// QA or recovery receipt and deliberately has no working write dispatch.
async function fixture(t, { actor = person, url = editorialFixtureUrl } = {}) {
  const f = setup({ approvedTeam: true });
  f.policy.members = [{ subject: actor.subject, enabled: true }];
  f.state.url = f.registryData.websites[0].environments[0].url = url;
  f.state.contentManagement = true;
  f.service.contentWorkflow = new ContentWorkflow(f.service, { qa: new ProtectedContentQa({}) });
  let active = true;
  f.service.verifyStaff = async verified => {
    assert.equal(verified, actor);
    if (!active) throw new Error('Test membership revoked.');
  };
  const snapshot = structuredClone(vectors.find(x => x.name === 'existing').read.snapshot);
  const empty = structuredClone(vectors.find(x => x.name === 'creation').read.snapshot);
  for (const value of [snapshot, empty]) Object.assign(value, { site_id: 1, site_url: url });
  snapshot.core.post_status = 'draft';
  const paths = s => s.post_id === 0 ? [] : [s.core.post_status === 'publish' ? '/sample-page/' : '/?page_id=' + s.post_id];
  const read = s => ({ success: true, snapshot: s, snapshot_sha256: delegationDigest(s), affected_paths: paths(s) });
  const received = [], migrations = new Map();
  const state = { alter: null, setActive: value => { active = value; } };
  const call = f.client.callTool.bind(f.client);
  f.client.callTool = async request => {
    const { name, arguments: args } = request;
    if ([toolNames.editExecute, toolNames.editApprove, toolNames.backup].includes(name)) assert.fail('Blocked preview dispatched a write or backup');
    if (name === toolNames.contentRecord) {
      assert.equal(args.site_url, url); assert.equal(args.site_id, 1);
      assert.ok([0, snapshot.post_id].includes(args.post_id));
      return response(read(args.post_id ? snapshot : empty));
    }
    if (name === toolNames.contentRecords) {
      const items = args.statuses?.includes(snapshot.core.post_status) && snapshot.core.post_title.includes(args.search)
        ? [{ post_id: snapshot.post_id, post_type: snapshot.post_type, post_title: snapshot.core.post_title,
          post_status: snapshot.core.post_status, post_name: snapshot.core.post_name, modified_gmt: '2026-10-23 12:00:00' }]
        : [];
      return response({ success: true, site_id: 1, site_url: url, items, total: items.length, page: args.page,
        per_page: args.per_page, total_pages: items.length });
    }
    if (name === toolNames.editMigration) return response(migrations.get(args.operation_id));
    if (name !== toolNames.editPreview) return call(request);
    received.push(args);
    const { idempotency_key: _key, expected_snapshot_sha256: before_sha256, reason, ...input } = args;
    const before = args.post_id ? snapshot : empty, intent = { ...input, before_sha256 };
    const operation_id = randomUUID(), candidate = managementCandidate(before, intent);
    const candidate_sha256 = managementCandidateHash(candidate);
    const migration = { schema: 1, adapter: 'content-management-v1', operation_id, reason, request: intent,
      candidate_snapshot: candidate, candidate_sha256, rollback_snapshot: before };
    const profile = { sha256: 'a'.repeat(64), runtime_context_sha256: 'a'.repeat(64), runtime_url: 'https://mrn-qa.internal:9443' };
    const review = { operation_id, site_id: args.site_id, site_url: url, target: { kind: 'wordpress-content', post_type: args.post_type, post_id: args.post_id },
      before_snapshot: before, before_sha256, candidate_snapshot: candidate, candidate_sha256,
      affected_paths: [...new Set([...paths(before), ...paths(candidate)])], rollback_operation_id: '', profile,
      expires_at: Math.floor(Date.now() / 1000) + 600 };
    state.alter?.(review, migration);
    review.migration_artifact = { ref: `mrn-content://management/${operation_id}`, sha256: delegationDigest(migration) };
    review.digest = delegationDigest(review);
    migrations.set(operation_id, migration);
    return response(review);
  };
  const server = createMcp(f.service, actor), client = new Client({ name: 'editorial-test', version: '1.0.0' });
  t.after(async () => { await client.close(); await server.close(); f.close(); });
  const [serverWire, clientWire] = InMemoryTransport.createLinkedPair();
  await server.connect(serverWire); await client.connect(clientWire);
  return { ...f, snapshot, state: f.state, control: state, received, client, call: async (name, args) => {
    const result = await client.callTool({ name, arguments: args });
    return { isError: Boolean(result.isError), ...JSON.parse(result.content[0].text) };
  } };
}
const targetArgs = f => ({ ...base, recordId: f.snapshot.post_id, expectedSnapshotSha256: delegationDigest(f.snapshot) });

test('new draft MCP preview reads the empty record, delegates only draft changes and attributes the requester', async t => {
  const f = await fixture(t);
  const op = await f.call(tools.create, draft);
  assert.equal(op.requestedBy, person.subject);
  assert.equal(op.status, 'blocked');
  assert.equal(op.error.code, 'EDITORIAL_QA_UNQUALIFIED');
  assert.equal(op.preview.visibility, 'draft-only'); assert.equal(op.preview.before, null);
  assert.equal(op.preview.after.post_status, 'draft'); assert.equal(op.preview.after.post_name, draft.slug);
  assert.equal(f.received[0].post_id, 0);
  assert.deepEqual(f.received[0].changes, { post_title: draft.title, post_name: draft.slug, post_status: 'draft' });
  assert.equal((await f.call(tools.create, draft)).id, op.id);
  assert.equal((await f.call(tools.create, { ...draft, title: 'Different draft' })).error.code, 'REQUEST_CONFLICT');
  assert.equal(f.received.length, 1);
  const audit = f.store.db.prepare('SELECT subject, identity, outcome FROM audit WHERE operation = ? AND event = ? ORDER BY seq')
    .all(op.id, 'content:editorial-preview');
  assert.deepEqual(audit.map(a => a.outcome), ['attempted', 'preview-only']);
  for (const row of audit) {
    assert.equal(row.subject, person.subject); assert.equal(JSON.parse(row.identity).googleSubject, person.googleSubject);
  }
  assert.equal(f.state.backups, 0);
});

test('publishing and unpublishing are separate fresh previews; an unqualified plan cannot be approved or executed', async t => {
  const f = await fixture(t);
  const location = { website: base.website, environment: base.environment, kind: 'pages' };
  const found = await f.call('search_website_content', { ...location, statuses: ['draft'], search: f.snapshot.core.post_title });
  assert.equal(found.items[0].id, f.snapshot.post_id); assert.equal(found.items[0].status, 'draft');
  const firstRead = await f.call('read_website_content', { ...location, recordId: found.items[0].id });
  assert.deepEqual(firstRead.acf.values, f.snapshot.acf_values);
  const edit = await f.call(tools.edit, { ...targetArgs(f), fields: { body: 'Draft replacement' }, requestKey: 'edit-draft' });
  assert.equal(edit.preview.visibility, 'draft-only');
  assert.equal(edit.preview.after.post_status, 'draft');
  assert.equal(edit.preview.after.post_title, f.snapshot.core.post_title);
  const publish = await f.call(tools.status, { ...targetArgs(f), expectedSnapshotSha256: firstRead.snapshotSha256,
    status: 'publish', requestKey: 'publish-draft' });
  assert.equal(publish.preview.visibility, 'publish'); assert.equal(publish.preview.before.post_status, 'draft');
  assert.equal(publish.preview.after.post_status, 'publish'); assert.equal(publish.planDigest, undefined);
  assert.deepEqual(f.received[1].changes, { post_status: 'publish' });
  assert.equal(f.snapshot.core.post_status, 'draft');
  assert.equal((await f.call('approve_operation', { operationId: publish.id, planDigest: 'a'.repeat(64) })).error.code, 'EDITORIAL_QA_UNQUALIFIED');
  assert.equal((await f.call('execute_operation', { operationId: publish.id })).error.code, 'EDITORIAL_QA_UNQUALIFIED');
  const unchanged = await f.call('read_website_content', { ...location, recordId: found.items[0].id });
  assert.equal(unchanged.snapshotSha256, firstRead.snapshotSha256);
  assert.deepEqual(unchanged.fields, firstRead.fields);
  assert.deepEqual(unchanged.acf, firstRead.acf);
  const stored = f.store.get(publish.id).plan.provider;
  assert.deepEqual(stored.candidate_snapshot.acf_values, f.snapshot.acf_values);
  assert.deepEqual(stored.candidate_snapshot.acf_definitions, f.snapshot.acf_definitions);
  assert.equal(stored.candidate_snapshot.unrelated_meta_sha256, f.snapshot.unrelated_meta_sha256);
  f.snapshot.core.post_status = 'publish'; // A separate fixture state, not an operation.
  const returned = await f.call(tools.status, { ...targetArgs(f), status: 'draft', requestKey: 'return-to-draft' });
  assert.equal(returned.preview.visibility, 'unpublish'); assert.equal(returned.preview.after.post_status, 'draft');
  assert.notEqual(returned.id, publish.id);
  assert.equal(f.state.backups, 0);
});

test('stale, out of scope, unapproved and schema-invalid requests do not dispatch a provider preview', async t => {
  const f = await fixture(t);
  assert.equal((await f.call(tools.status, { ...targetArgs(f), expectedSnapshotSha256: 'f'.repeat(64), status: 'publish', requestKey: 'stale' })).error.code, 'CONTENT_PRECONDITION');
  for (const [name, input] of [[tools.create, { ...draft, status: 'publish' }], [tools.create, { ...draft, actor: person.subject }],
    [tools.edit, { ...targetArgs(f), fields: { title: 'Title', post_status: 'publish' }, requestKey: 'mixed' }]]) {
    const refused = await f.client.callTool({ name, arguments: input });
    assert.equal(refused.isError, true);
    assert.match(refused.content[0].text, /invalid arguments|unrecognized key/i);
  }
  f.control.setActive(false);
  assert.equal((await f.call(tools.create, draft)).isError, true);
  f.control.setActive(true);
  f.policy.members[0].enabled = false;
  assert.equal((await f.call(tools.create, draft)).error.code, 'FORBIDDEN');
  assert.equal(f.received.length, 0);
  const other = await fixture(t, { url: 'https://deployment-test.mrndev.io' });
  assert.equal((await other.call(tools.create, draft)).error.code, 'EDITORIAL_FIXTURE_ONLY');
  assert.equal(other.received.length, 0);
  const reader = await fixture(t, { actor: { ...person, scopes: ['mrn:operations'] } });
  assert.equal((await reader.call(tools.create, draft)).error.code, 'INSUFFICIENT_SCOPE');
  assert.equal(reader.received.length, 0);
});

test('even a rehashed provider preview fails if it changes ACF or refers to another private record', async t => {
  const f = await fixture(t);
  f.control.alter = (p, m) => {
    p.candidate_snapshot.acf_values = { field_unrelated: 'Unexpected' };
    p.candidate_sha256 = m.candidate_sha256 = managementCandidateHash(p.candidate_snapshot);
  };
  let op = await f.call(tools.status, { ...targetArgs(f), status: 'publish', requestKey: 'foreign-acf' });
  assert.equal(op.error.code, 'CONTENT_MANAGEMENT_MISMATCH'); assert.equal(op.preview, undefined);
  f.control.alter = p => { p.affected_paths[0] = '/?page_id=' + (f.snapshot.post_id + 1); };
  op = await f.call(tools.status, { ...targetArgs(f), status: 'publish', requestKey: 'foreign-record' });
  assert.equal(op.error.code, 'CONTENT_PUBLIC_PATH'); assert.equal(op.preview, undefined);
});
