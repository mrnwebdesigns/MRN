import test from 'node:test';
import assert from 'node:assert/strict';
import { NativeContentReader, contentPreview } from '../src/content.mjs';
import { setup, actor } from './helpers.mjs';

const site = { id: 85, url: 'https://example.test', syncedAt: new Date().toISOString() };
function fixture() {
  const calls = []; const state = { status: 200, site: { id: '85', url: 'https://example.test/' },
    list: { 'https://example.test/': [{ id: 12, website_id: 85, post_type: 'page', title: 'About', status: 'publish', authorEmail: 'private@staff.test' }] },
    record: { id: 12, website_id: 85, post_type: 'page', post_content: '', post_excerpt: '', post_status: 'publish', post_name: 'about', post_password: 'private-sentinel', upload_dir: '/private/path', post_custom: { privateToken: 'never-return' } } };
  const reader = new NativeContentReader({ token: () => 'fixture-rest-token', request: async (url, options) => {
    calls.push({ url, options });
    const path = new URL(url).pathname;
    const data = path.endsWith('/sites/85') ? state.site : path.endsWith('/pages/85/12') ? state.record : state.list;
    return new Response(JSON.stringify({ success: 1, data }), { status: state.status, headers: { 'content-type': 'application/json; charset=UTF-8' } });
  } });
  return { reader, state, calls };
}

test('native content search is exact-site, bounded, read-only and separates the REST credential', async () => {
  const f = fixture(); const result = await f.reader.search(site, 'pages', 'About');
  assert.deepEqual(result.items, [{ id: 12, title: 'About', status: 'publish', type: 'page' }]);
  assert.equal(result.complete, true);
  assert.equal(new URL(f.calls[1].url).searchParams.get('websites'), '85');
  for (const call of f.calls) {
    assert.equal(new URL(call.url).origin, 'https://wpcontrol.mrndev.io');
    assert.equal(call.options.method, 'GET'); assert.equal(call.options.redirect, 'error');
    assert.equal(call.options.headers.Authorization, 'Bearer fixture-rest-token');
    assert.ok(!call.url.includes('fixture-rest-token'));
  }
  f.state.list['https://other.test'] = [];
  await assert.rejects(() => f.reader.search(site, 'pages'), { code: 'TARGET_MISMATCH' });
});

test('content snapshots exclude passwords and metadata and identify unqualified builder ownership', async () => {
  const f = fixture(); const snapshot = await f.reader.read(site, 'pages', 12);
  assert.match(snapshot.contentOwnership, /ACF or a builder/);
  assert.equal(snapshot.capabilities.acfQualified, false);
  assert.ok(!/private-sentinel|privateToken|never-return|upload_dir/.test(JSON.stringify(snapshot)));
  const preview = contentPreview(snapshot, { post_excerpt: 'Approved description' });
  assert.deepEqual(preview.diff, [{ field: 'post_excerpt', before: '', after: 'Approved description' }]);
  assert.equal(preview.expectedSha256, snapshot.snapshotSha256);
  assert.throws(() => contentPreview(snapshot, { post_title: 'Title the provider did not return' }), { code: 'CONTENT_FIELD_UNAVAILABLE' });
  assert.throws(() => contentPreview(snapshot, { post_custom: { key: 'arbitrary' } }));
  f.state.record.website_id = 86;
  await assert.rejects(() => f.reader.read(site, 'pages', 12), { code: 'TARGET_MISMATCH' });
});

test('REST failures and wrong site identity have no retry or credential fallback', async () => {
  const f = fixture(); f.state.status = 403;
  await assert.rejects(() => f.reader.read(site, 'pages', 12), { code: 'MAINWP_ACCESS' });
  assert.equal(f.calls.length, 1);
  f.state.status = 200; f.state.site.url = 'https://other.test/';
  await assert.rejects(() => f.reader.read(site, 'pages', 12), { code: 'TARGET_MISMATCH' });
  assert.equal(f.calls.length, 2);
});

test('service content read re-resolves and syncs exact site and rechecks permissions before returning', async t => {
  const f = setup(); t.after(f.close);
  let seen;
  f.service.content = { async read(target, kind, recordId) { seen = { target, kind, recordId }; return { fields: { post_content: 'Current content' } }; } };
  const input = { website: 'example', kind: 'pages', recordId: 12 };
  assert.equal((await f.service.readContent(actor, input)).fields.post_content, 'Current content');
  assert.equal(seen.target.id, 1); assert.equal(seen.target.url, f.state.url);
  assert.deepEqual(f.state.calls, ['get_site_v1', 'sync_sites_v1', 'get_site_v1']);
  f.service.content.read = async () => { f.policy.members[0].enabled = false; return {}; };
  await assert.rejects(() => f.service.readContent(actor, input), { code: 'FORBIDDEN' });
});
