// Disposable CI only: real gateway/Child content transport and HTML readback.
// Google, protected QA execution and remote backup creation are NOT exercised.
import assert from 'node:assert/strict';
import { createPrivateKey, createPublicKey, sign } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import https from 'node:https';
import { digest, requireWriteScope } from '../../src/contracts.mjs';
import { delegationCanonical } from '../../src/delegation.mjs';
import { ProtectedContentQa, contentQaPolicySchema } from '../../src/content-qa.mjs';
import { ContentWorkflow } from '../../src/content-workflow.mjs';
import { responseEvidence } from '../../src/probe.mjs';
import { toolNames } from '../../src/mainwp.mjs';

const origin = 'https://child.fixture.example.invalid';
export function nativeFixture({ privateDirectory, certificate, runtimeCli }) {
  assert.equal(process.env.GITHUB_ACTIONS, 'true');
  const site = JSON.parse(readFileSync(join(privateDirectory, 'native-site.json'), 'utf8'));
  const provider = JSON.parse(readFileSync(join(privateDirectory, 'native-content-policy.json'), 'utf8'));
  assert.equal(site.site_url, origin);
  assert.ok(Number.isSafeInteger(site.site_id) && site.site_id > 0);
  assert.equal(provider.profiles.length, 1);
  const profile = provider.profiles[0];
  assert.equal(profile.site_id, site.site_id); assert.equal(profile.site_url, origin);
  assert.equal(profile.parity_sha256, site.parity_sha256);
  assert.equal(site.selector, 'h1.wp-block-post-title');
  assert.equal(profile.selector, site.selector); assert.equal(profile.qa_path, site.qa_path);
  assert.deepEqual(profile.affected_paths, ['/native-child-fixture/']);
  assert.equal(profile.target.kind, 'post'); assert.equal(profile.target.post_type, 'page');
  assert.equal(profile.target.field, 'post_title');
  const key = createPrivateKey({ key: readFileSync(join(privateDirectory, 'native-content-qa.der')), type: 'pkcs8', format: 'der' });
  assert.equal(key.asymmetricKeyType, 'ed25519');
  const publicKeyBase64 = createPublicKey(key).export({ type: 'spki', format: 'der' }).subarray(-32).toString('base64');
  assert.deepEqual(provider.qa_keys, [{ id: 'disposable-content', public_key_base64: publicKeyBase64 }]);
  const policy = contentQaPolicySchema.parse({ version: 1, repository: 'mrnwebdesigns/mrn-code-qa-runner',
    workflowCommit: 'd'.repeat(40), actor: 'fixture[bot]', keyId: 'disposable-content', publicKeyBase64, engineCommit: 'e'.repeat(40),
    profiles: [{ siteUrl: origin, profile: profile.descriptor, targets: [{ target: profile.target, selector: profile.selector }] }] });
  const qa = new ProtectedContentQa(null, policy), stats = { qa: 0, backups: 0, publicReads: 0 };
  // Exercise request reconstruction and signature validation with a synthetic
  // signer. This does not replace the separately tested protected runner.
  qa.dispatch = async (request, authorize) => {
    await authorize(); qa.qualifyRequest(request); stats.qa++;
    return { runId: stats.qa, url: `https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/${stats.qa}` };
  };
  qa.receipt = async (ticket, request, authorize) => {
    await authorize();
    const issued = Math.floor(Date.now() / 1000);
    const claims = { schema: 1, issuer: 'mrn-qa-engine', audience: 'https://wpcontrol.mrndev.io', kind: 'content', result: 'Pass',
      mode: 'release', smoke_strict: true, issued_at: issued, expires_at: issued + 600,
      receipt_id: digest(`${ticket.runId}:1:${request.request_id}`).slice(0, 32), site_id: request.site_id, site_url: request.site_url,
      target: request.target, before_sha256: request.before_sha256, candidate_sha256: request.candidate_sha256,
      plan_digest: request.plan_digest, rollback_operation_id: request.rollback_operation_id, qa_report_sha256: 'f'.repeat(64),
      engine_commit: policy.engineCommit, runtime_url: request.runtime_url, runtime_context_sha256: request.profile.runtime_context_sha256,
      profile_sha256: request.profile.sha256, affected_paths: request.affected_paths, migration_artifact_sha256: request.migration_artifact.sha256 };
    const bytes = Buffer.from(delegationCanonical(claims));
    const envelope = { key_id: policy.keyId, payload_base64: bytes.toString('base64'),
      signature_base64: sign(null, Buffer.concat([Buffer.from('mrn-content-qa-v1:'), bytes]), key).toString('base64') };
    return { status: 'qualified', ...qa.verifyReceipt(envelope, request, ticket.runId) };
  };
  const backups = new Map();
  const wrapClient = client => {
    const original = client.callTool.bind(client);
    client.callTool = async (request, schema, options) => {
      if (![toolNames.backup, toolNames.backupStatus].includes(request.name)) return original(request, schema, options);
      assert.equal(process.env.GITHUB_ACTIONS, 'true');
      assert.equal(request.arguments.site_id, site.site_id); requireWriteScope(client.actor); await client.verifyStaff();
      let result;
      if (request.name === toolNames.backup) {
        assert.deepEqual(Object.keys(request.arguments), ['site_id']);
        // Only the disposable WP CLI may create this synthetic receipt. It uses
        // the real provider transient/consumption format, with no Updraft claim.
        const php = `if(getenv('MRN_DISPOSABLE_API_FIXTURE')!=='1'||!defined('WP_CLI')||!WP_CLI)throw new RuntimeException('Fixture only');`
          + `$s=json_decode(file_get_contents('/fixture-private/native-site.json'),true);`
          + `if($s['site_url']!=='${origin}'||$s['site_id']!==${site.site_id})throw new RuntimeException('Fixture target');`
          + `$n=bin2hex(random_bytes(16));$r=bin2hex(random_bytes(24));`
          + `set_transient(MRN_MainWP_Operations_API::receipt_key($s['site_id'],$n),array('receipt_hash'=>hash('sha256',$r)),300);`
          + `echo wp_json_encode(array('site_id'=>$s['site_id'],'nonce'=>$n,'receipt'=>$r));`;
        let receipt;
        try { receipt = JSON.parse(execFileSync(runtimeCli, ['eval', php], { encoding: 'utf8', timeout: 30000, stdio: ['ignore', 'pipe', 'ignore'] })); }
        catch { throw new Error('Disposable backup receipt could not be created; private output withheld'); }
        assert.ok(receipt.site_id === site.site_id && /^[a-f0-9]{32}$/.test(receipt.nonce) && /^[a-f0-9]{48}$/.test(receipt.receipt), 'Invalid disposable backup receipt; value withheld');
        backups.set(receipt.nonce, { ...receipt, subject: client.actor.subject }); stats.backups++;
        result = { site_id: site.site_id, nonce: receipt.nonce, status: 'started' };
      } else {
        assert.deepEqual(Object.keys(request.arguments).sort(), ['nonce', 'site_id']);
        const receipt = backups.get(request.arguments.nonce); assert.equal(receipt?.subject, client.actor.subject);
        result = { site_id: site.site_id, nonce: receipt.nonce, receipt: receipt.receipt, status: 'complete' };
      }
      return { content: [{ type: 'text', text: JSON.stringify(result) }] };
    };
    return client;
  };
  const address = readFileSync(join(privateDirectory, 'tls-ip'), 'utf8');
  assert.match(address, /^(?:10\.|172\.(?:1[6-9]|2[0-9]|3[01])\.|192\.168\.)[0-9.]+$/);
  const publicProbe = async (url, authorize, options = {}) => {
    await authorize(); const target = new URL(url);
    assert.equal(target.origin, origin); assert.equal(target.search, ''); assert.equal(target.hash, '');
    assert.ok(['/native-child-fixture/', '/wp-json/'].includes(target.pathname));
    const evidence = await new Promise((resolve, reject) => {
      const request = https.get(target, { ca: readFileSync(certificate), rejectUnauthorized: true,
        lookup: (_hostname, settings, done) => settings.all ? done(null, [{ address, family: 4 }]) : done(null, address, 4),
        headers: { 'Accept-Encoding': 'identity' } }, response => {
        const chunks = []; let bytes = 0;
        response.on('data', chunk => { bytes += chunk.length; if (bytes > 2 * 1024 * 1024) request.destroy(new Error('Fixture response limit')); else chunks.push(chunk); });
        response.on('error', reject);
        response.on('end', () => { try { resolve(responseEvidence(url, response.statusCode, Buffer.concat(chunks), {}, options)); } catch (error) { reject(error); } });
      });
      request.setTimeout(20000, () => request.destroy(new Error('Fixture readback timeout'))); request.on('error', reject);
    });
    await authorize(); stats.publicReads++; return evidence;
  };
  return { site, profile, stats, wrapClient, publicProbe,
    registry: { version: 1, websites: [{ id: 'native-content-fixture', name: 'Disposable native Child', environments: [{
      name: 'development', url: origin, management: 'mainwp', backup: 'updraft', sources: ['mainwp'],
      coordination: { exclusiveWriter: 'mrn-operations', evidenceRef: 'disposable-isolated-network', validUntil: new Date(Date.now() + 3600000).toISOString() },
    }] }] },
    configure: service => { service.contentWorkflow = new ContentWorkflow(service, { qa, delay: async () => {} }); } };
}

export async function checkNativeContent({ fixture, token, call, toolResult, pass, service, network, store, report, fixtureOption }) {
  const alice = await token('111111', { scope: 'mrn:operations mrn:operations:write' });
  const bob = await token('222222', { scope: 'mrn:operations mrn:operations:write' });
  const tools = (bearer, name, args = {}) => call(bearer, 'tools/call', { name, arguments: args,
    _meta: { email: 'forged@example.invalid', subject: 'auth0|forged' } });
  const invoke = async (name, args) => toolResult(await tools(alice, name, args));
  const settle = async id => { await service.jobs.get(id); return invoke('get_operation', { operationId: id }); };
  const expectError = (result, code) => { assert.equal(result.status, 200); assert.equal(result.body.result.isError, true);
    assert.equal(JSON.parse(result.body.result.content[0].text).error.code, code); };
  const directory = await invoke('list_websites');
  assert.ok(directory.sources.some(s => s.source === 'mainwp' && s.sites === 1 && s.status === 'discovered'));
  assert.equal(directory.websites.length, 1);
  const input = { website: origin, environment: 'development', target: fixture.profile.target };
  const before = await invoke('inspect_content_record', input);
  assert.equal(before.site.id, fixture.site.site_id); assert.equal(before.snapshot.post_title, 'Original lifecycle title');
  assert.deepEqual(before.policy.profile, fixture.profile.descriptor);
  pass('Exact native Child discovery, sync, semantic snapshot and bound target policy cross HTTP and MCP');
  const proposal = await invoke('prepare_content_edit', { ...input, expectedSnapshotSha256: before.snapshot_sha256,
    newValue: 'Gateway native title', reason: 'Disposable gateway lifecycle', requestKey: 'native-content-edit' });
  const prepared = await settle(proposal.id);
  assert.equal(prepared.status, 'prepared', JSON.stringify(prepared.error));
  assert.equal(prepared.preview.before, before.snapshot.post_title); assert.equal(prepared.preview.after, 'Gateway native title');
  assert.equal((await invoke('inspect_content_record', input)).snapshot_sha256, before.snapshot_sha256);
  assert.equal(fixture.stats.backups, 0);
  pass('Preparation preserves the actual Child and returns the exact signed-QA-fixture preview');
  expectError(await tools(alice, 'execute_operation', { operationId: prepared.id }), 'APPROVAL_REQUIRED');
  expectError(await tools(bob, 'execute_operation', { operationId: prepared.id }), 'CONTENT_OPERATOR');
  expectError(await tools(alice, 'approve_operation', { operationId: prepared.id, planDigest: '0'.repeat(64) }), 'PLAN_CHANGED');
  fixtureOption('111111');
  assert.equal((await tools(alice, 'execute_operation', { operationId: prepared.id })).status, 401);
  fixtureOption(''); assert.equal(fixture.stats.backups, 0);
  pass('Missing approval, changed digest, another operator and suspended identity cannot start native execution');
  const execute = async op => {
    const approved = await invoke('approve_operation', { operationId: op.id, planDigest: op.planDigest }); assert.equal(approved.status, 'approved');
    await invoke('execute_operation', { operationId: op.id }); const completed = await settle(op.id);
    assert.equal(completed.status, 'verified', JSON.stringify(completed.error));
    assert.equal(completed.result.contentSnapshotVerified, true); assert.equal(completed.result.publicHtmlContentVerified, true);
    assert.equal(completed.result.liveBrowserVerified, false);
    return completed;
  };
  const completed = await execute(prepared);
  assert.equal((await invoke('execute_operation', { operationId: completed.id })).status, 'verified');
  assert.equal(fixture.stats.backups, 1);
  const changed = await invoke('inspect_content_record', input); assert.equal(changed.snapshot.post_title, prepared.preview.after);
  const status = await invoke('get_operation', { operationId: completed.id, refreshProvider: true });
  assert.equal(status.providerStatus.state, 'completed');
  pass('Approved delegated mutation matches the native Child snapshot, actual public HTML and retained provider status');
  const rollback = await invoke('prepare_rollback', { operationId: completed.id, requestKey: 'native-content-rollback' });
  const reverse = await settle(rollback.id); assert.equal(reverse.status, 'prepared', JSON.stringify(reverse.error));
  assert.equal(reverse.preview.after, before.snapshot.post_title); await execute(reverse);
  const restored = await invoke('inspect_content_record', input);
  assert.deepEqual(restored.snapshot, before.snapshot); assert.equal(restored.snapshot_sha256, before.snapshot_sha256);
  assert.equal(fixture.stats.qa, 2); assert.equal(fixture.stats.backups, 2); assert.equal(fixture.stats.publicReads, 4);
  pass('Separate rollback approval, fresh synthetic QA and backup receipts restore the exact native record and HTML');
  const contentCalls = network().filter(row => /mrn-mainwp\/(?:prepare|approve|execute)-content-/.test(row.ability || ''));
  assert.ok(contentCalls.length >= 6); assert.ok(contentCalls.every(row => row.subject === 'google-oauth2|111111' && row.status === 200));
  assert.equal(contentCalls.filter(row => row.ability === 'mrn-mainwp/execute-content-change-v1').length, 2);
  const audit = store.db.prepare('SELECT subject,identity FROM audit WHERE event=? AND outcome=?').all('content:prepare', 'prepared');
  assert.equal(audit.length, 2); assert.ok(audit.every(row => row.subject === 'google-oauth2|111111' && JSON.parse(row.identity).googleSubject === '111111'));
  pass('Actual backend content calls and gateway audit retain the verified individual, never caller identity hints');
  Object.assign(report, { contentWriteVerified: true, scope: 'disposable-native-child-only', liveGoogleVerified: false,
    protectedQaVerified: false, productionQaVerified: false, remoteBackupVerified: false,
    syntheticInputs: ['OAuth', 'Google Directory', 'QA signer and reports', 'remote backup creation'],
    contentRollbackVerified: true, publicHtmlVerified: true, nativeChecks: 6 });
}
