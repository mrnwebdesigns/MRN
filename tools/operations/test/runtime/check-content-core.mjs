// Extends the existing signed native read lane; never authenticates to production.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { delegationDigest } from '../../src/delegation.mjs';

export async function checkContentCore({ privateDirectory, runtimeCli, token, call, toolResult, pass, network, store, report }) {
  const website = 'https://child.fixture.example.invalid';
  const native = JSON.parse(readFileSync(join(privateDirectory, 'native-site.json'), 'utf8'));
  const seed = JSON.parse(readFileSync(join(privateDirectory, 'core-seed.json'), 'utf8'));
  const ordinary = JSON.parse(readFileSync(join(privateDirectory, 'read-seed.json'), 'utf8'));
  assert.equal(native.site_url, website);
  assert.equal(seed.record_id, 10498);
  assert.equal(seed.production_content_used, false);
  assert.deepEqual(seed.unsupported_shape, { type: 'tab', runtime_prefixed_key: true, globally_resolvable: false });
  const alice = await token('111111'), bob = await token('222222');
  const execute = (bearer, args) => call(bearer, 'tools/call', { name: 'read_website_content', arguments: args });
  const input = { website, kind: 'pages', recordId: seed.record_id };
  const transcript = [];
  for (const args of [input, { ...input, view: 'core_and_acf' }]) {
    const response = await execute(alice, args);
    assert.equal(response.status, 200);
    assert.equal(response.body.result?.isError, true, 'Legacy combined inspection must still reject unsupported ACF');
    const receipt = network().filter(row => row.ability === 'mrn-mainwp/read-content-record-v1').at(-1);
    assert.equal(receipt.errorCode, 'mrn_content_fields');
    transcript.push({ tool: 'read_website_content', arguments: args, result: 'REFUSED', providerError: receipt.errorCode });
  }
  for (const [bearer, kind, recordId, title] of [
    [alice, 'pages', seed.record_id, seed.core.post_title],
    [alice, 'pages', ordinary.post_id, 'Original lifecycle title'],
    [bob, 'posts', ordinary.article_id, 'Native read post'],
  ]) {
    const args = { website, kind, recordId, view: 'core' }, began = performance.now();
    const value = toolResult(await execute(bearer, args));
    assert.equal(value.site.id, native.site_id);
    assert.equal(value.recordId, recordId);
    assert.equal(value.view, 'core');
    assert.equal(value.coreComplete, true);
    assert.equal(value.snapshotScope, 'wordpress-core-v1');
    assert.equal(value.native.postType, kind === 'pages' ? 'page' : 'post');
    assert.deepEqual(value.acf, { status: 'not_requested' });
    assert.equal(value.fields.post_title, title);
    assert.deepEqual(Object.keys(value.fields).sort(), ['post_content', 'post_excerpt', 'post_name', 'post_status', 'post_title']);
    if (recordId === seed.record_id) {
      assert.deepEqual(value.fields, seed.core);
      assert.equal(value.native.modifiedGmt, seed.modified_gmt);
    }
    const subject = bearer === alice ? 'google-oauth2|111111' : 'google-oauth2|222222';
    const receipt = network().filter(row => row.ability === 'mrn-mainwp/read-content-core-v1' && row.subject === subject).at(-1);
    assert.equal(receipt.status, 200);
    const provider = JSON.parse(execFileSync(runtimeCli, ['option', 'get', `mrn_delegation_audit_${receipt.requestId}`, '--format=json'], { encoding: 'utf8', timeout: 15000 }));
    assert.equal(provider.actor.oauth_subject, subject);
    assert.equal(provider.actor.google_sub, subject.split('|')[1]);
    assert.equal(provider.ability, 'mrn-mainwp/read-content-core-v1');
    assert.equal(provider.state, 'returned');
    assert.deepEqual(provider.targets, { [native.site_id]: website });
    assert.equal(provider.arguments_sha256, delegationDigest({ site_id: native.site_id, site_url: website, post_type: value.native.postType, post_id: recordId }));
    assert.ok(store.db.prepare('SELECT subject,identity FROM audit').all().some(row => row.subject === subject && JSON.parse(row.identity).providerReadIds?.includes(receipt.requestId)));
    transcript.push({ tool: 'read_website_content', arguments: args, result: value, elapsedMs: performance.now() - began,
      responseBytes: Buffer.byteLength(JSON.stringify(value)), independentActorAndArgumentsMatched: true });
  }
  const countCore = () => network().filter(row => row.ability === 'mrn-mainwp/read-content-core-v1').length;
  const before = countCore();
  assert.equal((await execute(alice, { ...input, view: 'core', website: 'https://wrong.fixture.example.invalid' })).body.result?.isError, true);
  assert.equal(countCore(), before, 'Wrong-site core read must not dispatch');
  for (const bearer of [await token('999999'), await token('111111', { 'https://operations.mrnwebdesigns.com/mcp/identity_provider': 'auth0' })]) {
    assert.equal((await execute(bearer, { ...input, view: 'core' })).status, 401);
    assert.equal(countCore(), before, 'Unauthorized core read must not dispatch');
  }
  const wrongType = await execute(alice, { ...input, view: 'core', kind: 'posts' });
  assert.equal(wrongType.body.result?.isError, true);
  assert.equal(network().filter(row => row.ability === 'mrn-mainwp/read-content-core-v1').at(-1).errorCode, 'mrn_content_target');
  report.coreOnly = { status: 'PASS', authentication: 'SYNTHETIC_FIXTURE_JWT_NOT_LIVE_GOOGLE',
    legacyCombinedStillRefuses: true, independentActorAndArgumentsMatched: true,
    wrongSiteAndUnauthorizedRejectedBeforeCoreDispatch: true, wrongRecordTypeRejectedByChild: true, transcript };
  pass('Page 10498 unsupported native ACF clone remains refused; explicit core page/post reads, signed target and independent actor receipts pass');
}
