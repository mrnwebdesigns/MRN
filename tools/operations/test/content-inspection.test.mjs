import test from 'node:test';
import assert from 'node:assert/strict';
import { createHash, createHmac, randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { inspectionInput, inspectionOutput, inspectionCursor, reconstructDefinitions, validateInspectionPage,
  readContentInspection } from '../src/content-inspection.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { readManagedContent } from '../src/content-management-read.mjs';
import { toolNames } from '../src/mainwp.mjs';
import { setup, actor } from './helpers.mjs';
import { response } from './fixtures/mainwp.mjs';
import { OpsError } from '../src/contracts.mjs';
import { managementSnapshot } from '../src/content-management-contract.mjs';

const vector = JSON.parse(readFileSync(new URL('./fixtures/content-management-v1.json', import.meta.url))).cases[0].read;
const site = { id: vector.snapshot.site_id, url: vector.snapshot.site_url };
const args = { site_id: site.id, site_url: site.url, post_type: vector.snapshot.post_type,
  post_id: vector.snapshot.post_id, inspection_id: randomUUID(), maximum: 50 };
function entries(fields) {
  const result = [];
  const walk = (item, path, kind) => {
    const { sub_fields, layouts, ...definition } = structuredClone(item);
    result.push({ ordinal: result.length, kind, path, definition, sub_fields: sub_fields?.length ?? null, layouts: layouts?.length ?? null });
    sub_fields?.forEach((field, i) => walk(field, [...path, 'sub_fields', i], 'field'));
    layouts?.forEach((layout, i) => walk(layout, [...path, 'layouts', i], 'layout'));
  };
  fields.forEach((field, index) => walk(field, [index], 'field'));
  return result;
}
function cursor(page, input, offset) {
  const state = { contract: 'content-inspection-v1', site_id: input.site_id, site_url: input.site_url,
    post_type: input.post_type, post_id: input.post_id, inspection_id: input.inspection_id, maximum: input.maximum,
    actor_sha256: 'a'.repeat(64), connection: 'b'.repeat(64), policy: 'c'.repeat(64),
    snapshot_sha256: page.snapshot_sha256, offset, expires_at: page.expires_at };
  const body = Buffer.from(delegationCanonical(state)).toString('base64url');
  return body + '.' + createHmac('sha256', 'synthetic-test-key').update(body).digest('hex');
}
function wire(input = args, source = vector) {
  const { acf_definitions: definitions, ...record } = structuredClone(source.snapshot);
  Object.assign(record, { site_id: input.site_id, site_url: input.site_url, post_type: input.post_type, post_id: input.post_id });
  const nodes = entries(definitions);
  return refresh({ success: true, contract: 'content-inspection-v1', record, snapshot_sha256: 'd'.repeat(64),
    definitions_sha256: 'e'.repeat(64), source_schema_sha256: 'f'.repeat(64), affected_paths: source.affected_paths, root_count: definitions.length,
    total_definitions: nodes.length, offset: 0, entries: nodes, complete: true, inspection_id: input.inspection_id,
    maximum: input.maximum, next_cursor: null, expires_at: Math.floor(Date.now() / 1000) + 290 });
}
function refresh(page) {
  const hash = createHash('sha256');
  for (const entry of page.entries) hash.update(delegationCanonical(entry) + '\n');
  page.definitions_sha256 = hash.digest('hex');
  page.snapshot_sha256 = createHash('sha256').update('mrn-content-inspection-v1:' + delegationCanonical(page.record)
    + ':' + page.root_count + ':' + page.total_definitions + ':' + page.definitions_sha256 + ':' + page.source_schema_sha256).digest('hex');
  return page;
}
const inputFor = input => ({ kind: input.post_type === 'page' ? 'pages' : 'posts', recordId: input.post_id,
  inspectionId: input.inspection_id, definitionLimit: input.maximum, ...(input.cursor ? { cursor: input.cursor } : {}) });

test('small inspection reconstructs the existing complete definitions and snapshot hash without new tools', async () => {
  const page = wire(), calls = [];
  const output = await readContentInspection({ async call(name, input) { calls.push({ name, input }); return page; } }, site, inputFor(args));
  assert.deepEqual(output.acf, { definitions: vector.snapshot.acf_definitions, values: vector.snapshot.acf_values });
  assert.deepEqual(output.fields, vector.snapshot.core);
  assert.equal(output.snapshotSha256, vector.snapshot_sha256);
  assert.equal(output.inspection.complete, true); assert.equal(output.inspection.nextCursor, null);
  assert.equal(output.capabilities.acfQualified, false);
  assert.equal(calls.length, 1); assert.equal(calls[0].name, toolNames.contentInspection);
});

test('registered clone identities, nested Flexible Content, disabled rows and labels remain exact', async () => {
  const page = wire();
  const keys = page.entries.filter(node => node.kind === 'field');
  for (const node of keys) node.definition.key = 'after_content_' + node.definition.key;
  refresh(page);
  const output = await readContentInspection({ async call() { return page; } }, site, inputFor(args));
  assert.ok(JSON.stringify(output.acf.definitions).includes('after_content_field_'));
  assert.deepEqual(output.acf.values, page.record.acf_values);
  const row = Object.values(page.record.acf_values).find(Array.isArray)?.[0];
  if (row) assert.deepEqual(Object.values(output.acf.values).find(Array.isArray)?.[0], row);
});

test('definition continuation returns explicit partial data and never auto-fetches another page', async () => {
  const page = wire();
  assert.ok(page.entries.length > 1);
  const all = structuredClone(page.entries);
  page.entries = [all[0]]; page.complete = false; page.next_cursor = cursor(page, args, 1);
  let count = 0;
  const partial = await readContentInspection({ async call() { count++; return page; } }, site, inputFor(args));
  assert.equal(count, 1); assert.equal(partial.acf.definitions, null);
  assert.deepEqual(partial.acf.definitionEntries, [all[0]]);
  assert.equal(partial.acf.definitionsComplete, false); assert.equal(partial.inspection.complete, false);
  const nextArgs = { ...args, cursor: page.next_cursor };
  const last = { ...page, offset: 1, entries: all.slice(1), complete: true, next_cursor: null };
  const final = await readContentInspection({ async call() { count++; return last; } }, site, inputFor(nextArgs));
  assert.equal(final.inspection.endOfDefinitions, true); assert.equal(final.inspection.complete, false);
  assert.equal(final.acf.definitions, null); assert.equal(count, 2);
});

test('scope, ordering, duplicates, missing nodes and inconsistent completion fail closed', () => {
  const good = wire();
  for (const change of [p => { p.record.site_id++; }, p => { p.record.post_id++; }, p => { p.inspection_id = randomUUID(); },
    p => { p.maximum++; }, p => { p.offset = 1; }, p => { p.entries[0].ordinal++; },
    p => { p.entries.push(p.entries[0]); p.total_definitions++; }, p => { p.complete = false; },
    p => { p.next_cursor = 'invalid'; }, p => { p.entries[0].path = [1000]; },
    p => { p.entries[0].kind = 'layout'; }, p => { p.entries[0].definition.extra = 'not allowed'; },
    p => { p.record.core.post_content = 'x'.repeat(196609); }]) {
    const value = structuredClone(good); change(value);
    assert.throws(() => validateInspectionPage(value, args));
  }
  const missing = structuredClone(good); missing.entries.pop(); missing.total_definitions--;
  assert.throws(() => reconstructDefinitions(missing));
});

test('continuations bind exact site, record, inspection and limits; expiry and malformed bytes reject before dispatch', async () => {
  const page = wire(); page.complete = false; page.entries = [page.entries[0]]; page.next_cursor = cursor(page, args, 1);
  const token = page.next_cursor;
  assert.equal(inspectionCursor(token, args).offset, 1);
  for (const altered of [{ ...args, site_id: args.site_id + 1 }, { ...args, site_url: 'https://wrong.example.invalid' },
    { ...args, post_id: args.post_id + 1 }, { ...args, post_type: args.post_type === 'page' ? 'post' : 'page' },
    { ...args, inspection_id: randomUUID() }, { ...args, maximum: 49 }]) assert.throws(() => inspectionCursor(token, altered));
  for (const malformed of ['', 'x', '.' + token, token + '=', 'e30.' + 'a'.repeat(64)]) assert.throws(() => inspectionCursor(malformed, args));
  page.expires_at = Math.floor(Date.now() / 1000) - 1;
  assert.throws(() => inspectionCursor(cursor(page, args, 1), args));
  let calls = 0;
  await assert.rejects(() => readContentInspection({ async call() { calls++; } }, site,
    { kind: 'pages', recordId: args.post_id, cursor: token }), { code: 'CONTENT_INSPECTION_CURSOR' });
  assert.equal(calls, 0);
});

test('changed snapshot, continuation offset, expiry or record values cannot be combined', () => {
  const page = wire(); const prior = cursor(page, args, 1);
  const next = { ...page, offset: 1, entries: page.entries.slice(1), next_cursor: null };
  validateInspectionPage(next, { ...args, cursor: prior });
  for (const change of [p => { p.snapshot_sha256 = 'f'.repeat(64); }, p => { p.expires_at--; }, p => { p.offset++; }, p => { p.record.core.post_content = 'Changed silently'; }]) {
    const bad = structuredClone(next); change(bad);
    assert.throws(() => validateInspectionPage(bad, { ...args, cursor: prior }));
  }
});

test('closed new input/output retains response limits, rejects password defaults and arbitrary control inputs', () => {
  for (const input of [{ ...args, maximum: 101 }, { ...args, maximum: 0 }, { ...args, code: 'anything' },
    { ...args, post_id: 0 }, { ...args, inspection_id: 'not-a-uuid' }, { ...args, cursor: '' }]) assert.equal(inspectionInput.safeParse(input).success, false);
  const page = wire(); page.entries[0].definition.type = 'password';
  assert.throws(() => validateInspectionPage(page, args));
  const excessive = wire(); excessive.record.acf_values = { field_large: Array(1001).fill('x') };
  assert.equal(inspectionOutput.safeParse(excessive).success, false);
  assert.throws(() => reconstructDefinitions({ ...wire(), offset: 1 }));
});

test('new capability denial never downgrades to the legacy reader or alternative credentials', async () => {
  const calls = [];
  const session = { async supports(name) { return name === toolNames.contentInspection; },
    async call(name) { calls.push(name); throw new OpsError('STAFF_ACCESS_DENIED', 'Denied.'); } };
  await assert.rejects(() => readManagedContent(session, site, inputFor(args)), { code: 'STAFF_ACCESS_DENIED' });
  assert.deepEqual(calls, [toolNames.contentInspection]);
});

test('individual Google authorization is checked before and after inspection, with no returned audit after revocation', async t => {
  const f = setup(); t.after(f.close); f.state.contentInspection = true;
  const person = { ...actor, identityProvider: 'google-oauth2' };
  let revoked = false, calls = 0;
  f.service.verifyStaff = async () => { if (revoked) throw new OpsError('STAFF_ACCESS_DENIED', 'Revoked.'); };
  const original = f.service.connect;
  f.service.connect = async (...a) => {
    const client = await original(...a), call = client.callTool.bind(client);
    client.callTool = async request => {
      if (request.name !== toolNames.contentInspection) return call(request);
      calls++; const page = wire(request.arguments); revoked = true; return response(page);
    };
    return client;
  };
  const input = { website: 'example', kind: args.post_type === 'page' ? 'pages' : 'posts', recordId: args.post_id };
  await assert.rejects(() => f.service.readContent(actor, input), { code: 'AUTH_REQUIRED' });
  assert.equal(calls, 0);
  await assert.rejects(() => f.service.readContent(person, input), { code: 'STAFF_ACCESS_DENIED' });
  assert.equal(calls, 1);
  assert.equal(f.store.db.prepare('SELECT COUNT(*) AS n FROM audit WHERE event=? AND outcome=?').get('content:read', 'returned').n, 0);
  assert.equal(f.store.db.prepare('SELECT COUNT(*) AS n FROM audit WHERE event=? AND outcome=? AND subject=?').get('content:read', 'failed', person.subject).n, 2);
});

test('separate inspection schema does not alter the frozen management contract or permit clone-key write snapshots', () => {
  const frozen = JSON.parse(readFileSync(new URL('../contracts/content-management-v1.json', import.meta.url)));
  assert.equal(createHash('sha256').update(readFileSync(new URL('../contracts/content-management-v1.json', import.meta.url))).digest('hex'),
    'e164d4bc265096493e6650c0beed42e7d8acbbd013d261a70b63513f591500de');
  const cloneWrite = structuredClone(vector.snapshot); cloneWrite.acf_definitions[0].key = 'after_content_' + cloneWrite.acf_definitions[0].key;
  assert.throws(() => managementSnapshot(cloneWrite));
  assert.equal(frozen.$defs.acf_field.properties.key.pattern, '^field_[A-Za-z0-9_-]+$');
  assert.equal(frozen.limits.snapshot_bytes, 196608);
  assert.equal(frozen.limits.raw_json_nodes, 20000);
  const before = delegationDigest(vector.snapshot);
  assert.equal(before, vector.snapshot_sha256);
});
