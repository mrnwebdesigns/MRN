import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { actor, setup } from './helpers.mjs';
import { response } from './fixtures/mainwp.mjs';
import { OpsError } from '../src/contracts.mjs';
import { delegationDigest } from '../src/delegation.mjs';
import { toolNames } from '../src/mainwp.mjs';
import { readManagedContent } from '../src/content-management-read.mjs';

const vectors = JSON.parse(readFileSync(new URL('./fixtures/content-management-v1.json', import.meta.url)));
const person = { ...actor, identityProvider: 'google-oauth2' };
function fixture(t) {
  const f = setup(); t.after(f.close); f.state.contentManagement = true;
  const wire = structuredClone(vectors.cases[0].read);
  Object.assign(wire.snapshot, { site_id: f.state.id, site_url: f.state.url });
  wire.snapshot_sha256 = delegationDigest(wire.snapshot);
  const state = { rest: 0, calls: [], after: null, fail: null, wire };
  f.service.verifyStaff = async () => { if (state.revoked) throw new OpsError('STAFF_ACCESS_DENIED', 'Fixture revoked.'); };
  f.service.content = { async read() { state.rest++; return { legacy: true }; }, async search() { state.rest++; return { legacy: true }; } };
  const connect = f.service.connect;
  f.service.connect = async (...args) => {
    const client = await connect(...args), call = client.callTool.bind(client);
    client.callTool = async request => {
      if (![toolNames.contentRecord, toolNames.contentRecords].includes(request.name)) return call(request);
      state.calls.push(structuredClone(request));
      if (state.fail) throw state.fail;
      const args = request.arguments;
      let result = state.wire;
      if (request.name === toolNames.contentRecords) result = { site_id: args.site_id, site_url: args.site_url, success: true,
        page: args.page, per_page: args.per_page, total: 3, total_pages: Math.ceil(3 / args.per_page), items: [{ post_id: 7,
          post_type: args.post_type, post_title: 'Privacy', post_name: 'privacy', post_status: 'draft', modified_gmt: '2026-10-16 00:00:00' }] };
      state.after?.(result); return response(result);
    };
    return client;
  };
  return { ...f, state, backend: f.state, input: { website: 'example', kind: 'pages', recordId: 7 } };
}

test('existing content tool returns exact structured ACF data through the current staff session without REST credentials', async t => {
  const f = fixture(t); f.service.content = null;
  const read = await f.service.readContent(person, f.input);
  assert.deepEqual(read.fields, f.state.wire.snapshot.core);
  assert.deepEqual(read.acf, { definitions: f.state.wire.snapshot.acf_definitions, values: f.state.wire.snapshot.acf_values });
  assert.equal(read.snapshotSha256, f.state.wire.snapshot_sha256);
  assert.equal(read.capabilities.acfReadable, true); assert.equal(read.capabilities.acfQualified, false);
  assert.deepEqual(f.backend.calls, ['get_site_v1', 'sync_sites_v1', 'get_site_v1']);
  assert.deepEqual(f.state.calls[0], { name: toolNames.contentRecord, arguments: { site_id: f.backend.id,
    site_url: f.backend.url, post_type: 'page', post_id: 7 } });
  const audits = f.store.db.prepare('SELECT subject,outcome FROM audit WHERE event=?').all('content:read');
  assert.deepEqual(audits.map(row => row.outcome), ['attempted', 'returned']);
  assert.ok(audits.every(row => row.subject === person.subject));
});

test('existing search tool preserves explicit page, status, type and result limits', async t => {
  const f = fixture(t);
  const result = await f.service.readContent(person, { website: 'example', kind: 'pages', search: 'Privacy', maximum: 1, page: 2, statuses: ['draft'] });
  assert.equal(result.page, 2); assert.equal(result.totalPages, 3); assert.equal(result.complete, false);
  assert.equal(result.items[0].id, 7); assert.equal(result.items[0].status, 'draft');
  assert.deepEqual(f.state.calls[0].arguments, { site_id: f.backend.id, site_url: f.backend.url, post_type: 'page',
    page: 2, per_page: 1, search: 'Privacy', statuses: ['draft'] });
  assert.equal(f.state.rest, 0);
});

test('draft record queries remain exact read data without REST fallback or write qualification', async t => {
  const f = fixture(t); f.service.content = null;
  f.state.wire.snapshot.core.post_status = 'draft';
  f.state.wire.snapshot_sha256 = delegationDigest(f.state.wire.snapshot);
  f.state.wire.affected_paths = ['/?page_id=7'];
  const result = await f.service.readContent(person, f.input);
  assert.equal(result.fields.post_status, 'draft');
  assert.deepEqual(result.affectedPaths, ['/?page_id=7']);
  assert.equal(result.capabilities.acfQualified, false);
  assert.equal(f.state.rest, 0);
  for (const path of ['/?page_id=8', '/?p=7', '/?page_id=07', '/?page_id=7&preview=true',
    '/?page_id=7#other', '//other.test/?page_id=7', '/wp-admin/?page_id=7', '/?page_id=%37']) {
    f.state.wire.affected_paths = [path];
    await assert.rejects(() => f.service.readContent(person, f.input));
  }
  assert.equal(f.state.rest, 0);
});

test('provider denial or invalid structured data never downgrades to native REST', async t => {
  const f = fixture(t); f.state.fail = new OpsError('MAINWP_ACCESS', 'Denied.');
  await assert.rejects(() => f.service.readContent(person, f.input), { code: 'MAINWP_ACCESS' });
  f.state.fail = null; f.state.wire.snapshot.site_id++;
  await assert.rejects(() => f.service.readContent(person, f.input), { code: 'CONTENT_MANAGEMENT_MISMATCH' });
  assert.equal(f.state.rest, 0); assert.equal(f.state.calls.length, 2);
});

test('structured reads require Google and withhold results if current staff access is revoked during a read', async t => {
  const f = fixture(t);
  await assert.rejects(() => f.service.readContent(actor, f.input), { code: 'AUTH_REQUIRED' });
  assert.equal(f.state.calls.length, 0);
  f.state.after = () => { f.state.revoked = true; };
  await assert.rejects(() => f.service.readContent(person, f.input), { code: 'STAFF_ACCESS_DENIED' });
  assert.equal(f.state.rest, 0);
  assert.equal(f.store.db.prepare('SELECT COUNT(*) AS n FROM audit WHERE event=? AND outcome=?').get('content:read', 'returned').n, 0);
});

test('absent management capability preserves existing REST reads but never drops unsupported filters', async t => {
  const f = fixture(t); f.backend.contentManagement = false;
  assert.deepEqual(await f.service.readContent(actor, f.input), { legacy: true });
  for (const extra of [{ page: 2 }, { statuses: ['draft'] }])
    await assert.rejects(() => f.service.readContent(actor, { website: 'example', kind: 'pages', ...extra }), { code: 'CAPABILITY_MISSING' });
  assert.equal(f.state.rest, 1); assert.equal(f.state.calls.length, 0);
});

test('structured directory rejects changed scope, duplicate records and inconsistent pagination', async () => {
  const site = { id: 73, url: 'https://fixture.example.invalid' };
  const list = { site_id: site.id, site_url: site.url, success: true, page: 1, per_page: 1, total: 1, total_pages: 1,
    items: [{ post_id: 7, post_type: 'page', post_title: 'Title', post_name: 'title', post_status: 'draft', modified_gmt: '' }] };
  const input = { kind: 'pages', maximum: 1, statuses: ['draft'] };
  for (const mutate of [v => { v.site_id++; }, v => { v.page++; }, v => { v.total_pages++; },
    v => { v.items[0].post_type = 'post'; }, v => { v.items[0].post_status = 'publish'; }, v => { v.items.push(v.items[0]); }]) {
    const value = structuredClone(list); mutate(value);
    await assert.rejects(() => readManagedContent({ async call() { return value; } }, site, input), { code: 'CONTENT_MANAGEMENT_MISMATCH' });
  }
  let calls = 0;
  for (const invalid of [{ ...input, kind: 'users' }, { ...input, maximum: 51 }, { ...input, recordId: 0 }, { ...input, statuses: ['draft', 'draft'] }])
    await assert.rejects(() => readManagedContent({ async call() { calls++; return list; } }, site, invalid));
  assert.equal(calls, 0);
});
