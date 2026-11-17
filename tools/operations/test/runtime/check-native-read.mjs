import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const website = 'https://child.fixture.example.invalid';
export async function checkNativeRead({ privateDirectory, runtimeCli, token, call, toolResult, pass, network, store, report }) {
  const input = { website };
  const native = JSON.parse(readFileSync(join(privateDirectory, 'native-site.json'), 'utf8'));
  const seed = JSON.parse(readFileSync(join(privateDirectory, 'read-seed.json'), 'utf8'));
  assert.equal(native.site_url, website);
  assert.equal(readFileSync(join(privateDirectory, 'provider-commit'), 'utf8').trim(), 'b1a9c1537d3de208307c6028ebcdde3b7d65a67a');
  const alice = await token('111111'), bob = await token('222222');
  const execute = (bearer, name, arguments_) => call(bearer, 'tools/call', { name, arguments: arguments_ });
  const read = async (bearer, name, args) => toolResult(await execute(bearer, name, args));
  const directory = await call(alice);
  assert.equal(directory.status, 200);
  assert.deepEqual(directory.body.result.tools.map(t => t.name).sort(), [
    'get_operation', 'inspect_website', 'inspect_website_code', 'list_websites', 'read_website_content',
    'read_website_details', 'search_website_content', 'website_history'
  ]);
  const sites = await read(alice, 'list_websites', {});
  assert.equal(sites.websites.length, 1);
  for (const [bearer, kind, recordId, title] of [
    [alice, 'pages', seed.post_id, 'Original lifecycle title'],
    [bob, 'posts', seed.article_id, 'Native read post']
  ]) {
    const list = await read(bearer, 'search_website_content', { ...input, kind, search: title, statuses: ['publish'] });
    assert.equal(list.site.id, native.site_id);
    assert.deepEqual(list.items.map(row => [row.id, row.title]), [[recordId, title]]);
    const result = await read(bearer, 'read_website_content', { ...input, kind, recordId });
    assert.equal(result.recordId, recordId);
    assert.equal(result.fields.post_title, title);
    assert.equal(result.fields.post_status, 'publish');
    if (kind === 'pages') {
      assert.equal(seed.acf_version, '6.8.7');
      const { definitions, values } = result.acf;
      const rows = values.field_mrn_native_rows;
      assert.equal(rows.length, 2);
      assert.equal(rows[0].acf_fc_layout, 'mrn_native_row');
      assert.equal(rows[0].field_mrn_native_group.field_mrn_native_copy, 'Original native ACF copy é雪');
      assert.equal(rows[0].field_mrn_native_group.field_mrn_native_keep, 'Unchanged nested sibling');
      assert.equal(rows[1].acf_fc_layout, 'mrn_native_row');
      assert.equal(rows[1].acf_fc_layout_disabled, true);
      assert.equal(rows[1].acf_fc_layout_custom_label, 'Hidden native row');
      assert.match(JSON.stringify(definitions), /flexible_content/);
      assert.equal(values.field_mrn_native_other, 'Unchanged outside field');
    }
  }
  pass('Exact-site native page and post discovery and records; licensed ACF Flexible Content layout, rows and metadata');
  for (const section of ['plugins', 'themes', 'updates', 'security', 'stack_runtime', 'brand_palette']) {
    const value = await read(alice, 'read_website_details', { ...input, section });
    assert.equal(value.site.id, native.site_id);
    if (section === 'stack_runtime') assert.match(JSON.stringify(value), /mrn-site-colors/);
    if (section === 'brand_palette') assert.match(JSON.stringify(value), /#AABBCC/);
  }
  pass('Cached site inventory and signed Stack and Site Styles reads');
  const outsider = await token('999999');
  assert.equal((await call(outsider)).status, 401);
  assert.equal((await call(await token('111111', { 'https://operations.mrnwebdesigns.com/mcp/identity_provider': 'auth0' }))).status, 401);
  const before = network().filter(row => row.ability === 'mrn-mainwp/read-content-record-v1').length;
  assert.ok((await execute(alice, 'read_website_content', { website: 'https://wrong.fixture.example.invalid', kind: 'pages', recordId: seed.post_id })).body.result?.isError);
  assert.equal(network().filter(row => row.ability === 'mrn-mainwp/read-content-record-v1').length, before);
  pass('Outside Directory, non-Google and cross-site attempts refused');
  const receipts = network().filter(row => row.path.endsWith('/read-delegated-operation-v1/run') && row.status === 200);
  const identity = new Map([['google-oauth2|111111', 'alice@mrnwebdesigns.com'], ['google-oauth2|222222', 'bob@mrnwebdesigns.com']]);
  const audit = store.db.prepare('SELECT subject,identity FROM audit').all();
  for (const subject of identity.keys()) {
    const receipt = receipts.find(row => row.subject === subject && row.ability === 'mrn-mainwp/read-content-record-v1');
    assert.match(receipt?.requestId, /^[a-f0-9-]{36}$/);
    const provider = JSON.parse(execFileSync(runtimeCli, ['option', 'get', 'mrn_delegation_audit_' + receipt.requestId, '--format=json'], { encoding: 'utf8', timeout: 15000 }));
    assert.equal(provider.actor.oauth_subject, subject);
    assert.equal(provider.actor.google_sub, subject.split('|')[1]);
    assert.equal(provider.actor.email, identity.get(subject));
    assert.equal(provider.ability, receipt.ability);
    assert.equal(provider.state, 'returned');
    assert.ok(audit.some(row => row.subject === subject && JSON.parse(row.identity).providerReadIds?.includes(receipt.requestId)));
  }
  pass('Independent provider receipts match gateway audit records for both Google fixture identities');
  assert.ok(network().every(row => row.method === 'GET' || /\/(get-delegation-challenge|get-team-access|read-delegated-operation)-v1\/run$/.test(row.path)));
  const extension = existsSync(join(privateDirectory, 'checksum-extension-verified'));
  if (!extension) {
    const denied = await execute(alice, 'inspect_website_code', { ...input, path: seed.css_path });
    assert.ok(denied.body.result?.isError, 'Exact-file read must fail closed without the active Dashboard extension');
    assert.ok(network().some(row => row.ability === 'mrn-mainwp/inspect-code-file-v1' && row.errorCode === 'mrn_code_extension'));
    report.checksum = 'BLOCKED: reviewed Code Snippets Extension 5.1.1 archive required';
    throw new Error('NATIVE_READ_CHECKSUM_EXTENSION_REQUIRED');
  }
  for (const [path, hash] of [[seed.css_path, seed.css_sha256], [seed.code_path, seed.code_sha256]]) {
    const result = await read(alice, 'inspect_website_code', { ...input, path });
    assert.equal(result.sha256, hash);
    assert.equal(result.content, null);
    assert.equal(result.contentRequested, false);
  }
  pass('Exact checksum reads return no source');
}
