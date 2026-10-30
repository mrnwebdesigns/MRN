// Disposable CI only: real gateway/Child content transport and HTML readback.
// Google, protected QA execution and remote backup creation are NOT exercised.
import assert from 'node:assert/strict';
import { createPrivateKey, createPublicKey, sign } from 'node:crypto';
import { lstatSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { isAbsolute, join, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { Worker } from 'node:worker_threads';
import { pathToFileURL } from 'node:url';
import https from 'node:https';
import { digest, OpsError, requireWriteScope } from '../../src/contracts.mjs';
import { delegationCanonical } from '../../src/delegation.mjs';
import { ProtectedContentQa, contentQaPolicySchema } from '../../src/content-qa.mjs';
import { ContentWorkflow } from '../../src/content-workflow.mjs';
import { CodeWorkflow } from '../../src/code-workflow.mjs';
import { ProtectedCodeQa, codePolicySchema } from '../../src/code-qa.mjs';
import { responseEvidence } from '../../src/probe.mjs';
import { payload, toolNames } from '../../src/mainwp.mjs';

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
  const qa = new ProtectedContentQa(null, policy), stats = { qa: 0, backups: 0, publicReads: 0, executions: [] };
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
      if (![toolNames.backup, toolNames.backupStatus].includes(request.name)) {
        const response = await original(request, schema, options);
        if (request.name === toolNames.contentExecute && stats.executions.length < 2) {
          const result = payload(response);
          // Bounded fixture diagnostics only: never log records, hashes,
          // receipts, identities or arbitrary upstream messages.
          stats.executions.push({
            state: ['completed', 'failed', 'uncertain'].includes(result.state) ? result.state : 'other',
            errorCode: [null, '', 'mrn_content_uncertain', 'mrn_content_profile', 'mrn_content_precondition'].includes(result.error_code) ? result.error_code : 'other',
            changed: result.changed === true, rollbackAvailable: result.rollback_available === true,
            candidateHashMatches: typeof result.current_sha256 === 'string' && result.current_sha256 === result.candidate_sha256,
          });
        }
        return response;
      }
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
  const readOnly = await token('111111');
  const structured = toolResult(await tools(readOnly, 'read_website_content', { website: origin, environment: 'development', kind: 'pages',
    recordId: fixture.profile.target.post_id }));
  assert.equal(structured.site.id, fixture.site.site_id); assert.equal(structured.fields.post_title, 'Original lifecycle title');
  assert.equal(structured.capabilities.acfReadable, true); assert.equal(structured.capabilities.acfQualified, false);
  assert.ok(Array.isArray(structured.acf.definitions)); assert.match(structured.snapshotSha256, /^[a-f0-9]{64}$/);
  pass('Existing gateway read tool obtains the complete typed record through individual Google delegation and native MainWP');
  const listed = toolResult(await tools(readOnly, 'search_website_content', { website: origin, environment: 'development', kind: 'pages',
    search: 'Original lifecycle title', maximum: 1, page: 1, statuses: ['publish'] }));
  assert.equal(listed.site.id, fixture.site.site_id); assert.equal(listed.items.length, 1);
  assert.equal(listed.items[0].id, fixture.profile.target.post_id); assert.equal(listed.items[0].status, 'publish');
  const managementReads = network().filter(row => ['mrn-mainwp/read-content-record-v1', 'mrn-mainwp/list-content-records-v1'].includes(row.ability));
  assert.equal(managementReads.length, 2);
  assert.ok(managementReads.every(row => row.subject === 'google-oauth2|111111' && row.status === 200));
  pass('Existing gateway search tool preserves exact native site, status and pagination with no separate REST credential');
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
    if (completed.status !== 'verified') process.stderr.write(JSON.stringify({ nativeExecution: fixture.stats.executions }) + '\n');
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
    contentRollbackVerified: true, publicHtmlVerified: true, structuredManagementReadsVerified: true,
    managementReadOnlyScopeVerified: true, managementWritesVerified: false, nativeChecks: 8 });
}

// Additional release workflow gate. Unlike the inherited protocol fixture, this
// invokes the actual keyless runner/QA Engine before a fixture signer may issue.
// Reviewed forward/inverse policy inputs are never reconstructed from candidates.
export function validateNativeWriteInputs(site, input, runnerLock) {
  assert.ok(input.schema === 1 && site.site_url === origin && input.site_url === origin && input.site_id === site.site_id
    && Number.isSafeInteger(input.post_id) && input.post_id > 0 && Array.isArray(input.acf_path) && input.acf_path.length > 0
    && input.acf_selector === '.mrn-native-acf-copy' && /^wp-content\/themes\/[A-Za-z0-9_-]+\/mrn-native-code-fixture\.css$/.test(input.css_path),
  'NATIVE_WRITE_FIXTURE_INVALID: require the owned native field and active-theme CSS fixture');
  const runtime = input.management_profile_context.runtime;
  assert.ok(/^[A-Za-z0-9_-]+$/.test(runtime.stylesheet)
    && input.css_path === `wp-content/themes/${runtime.stylesheet}/mrn-native-code-fixture.css`, 'NATIVE_CSS_ACTIVE_THEME_REQUIRED');
  assert.ok(runtime.wordpress === runnerLock.wordpress_version && runtime.php_minor === runnerLock.php_minor,
    'NATIVE_QA_RUNTIME_MISMATCH: independently qualify matching WordPress/PHP fixture images; never relabel parity');
  return runtime;
}
export async function nativeWriteFixture({ privateDirectory, certificate, runtimeCli }) {
  assert.equal(process.env.GITHUB_ACTIONS, 'true', 'Native write fixtures require the existing disposable CI harness');
  assert.ok(typeof process.env.MRN_GATEWAY_RUNNER_SOURCE === 'string' && isAbsolute(process.env.MRN_GATEWAY_RUNNER_SOURCE),
    'NATIVE_QA_RUNNER_INPUT_REQUIRED: provide the existing pinned runner checkout');
  const runnerRoot = resolve(process.env.MRN_GATEWAY_RUNNER_SOURCE);
  const source = JSON.parse(readFileSync(new URL('./sources.json', import.meta.url)));
  assert.equal(execFileSync('git', ['-C', runnerRoot, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), source.runner.commit,
    'NATIVE_QA_RUNNER_PIN: the reviewed runner checkout must match the source lock');
  assert.equal(execFileSync('git', ['-C', runnerRoot, 'status', '--porcelain', '--untracked-files=no'], { encoding: 'utf8' }).trim(), '',
    'NATIVE_QA_RUNNER_DIRTY: qualify committed runner source');
  const site = JSON.parse(readFileSync(join(privateDirectory, 'native-site.json')));
  const input = JSON.parse(readFileSync(join(privateDirectory, 'native-write-fixture.json')));
  const runnerLock = JSON.parse(readFileSync(join(runnerRoot, 'runtime/lock.json')));
  const runtime = validateNativeWriteInputs(site, input, runnerLock);
  assert.equal(process.platform, 'linux', 'NATIVE_QA_LINUX_REQUIRED: use the existing keyless Linux isolator');
  try { execFileSync('docker', ['info'], { stdio: 'ignore', timeout: 10000 }); }
  catch { throw new Error('NATIVE_QA_DOCKER_REQUIRED: no local or production runtime fallback'); }
  // Optional FILE inputs refer only to explicit existing operator-owned fixture
  // policies. A random fixture directory cannot be pre-populated by guessing.
  // IDs, native parity and forward/candidate baselines still need independent
  // qualification after the fixture exists; this driver generates no policy.
  const privatePolicy = (name, expected, explicitFile) => {
    assert.match(expected || '', /^[a-f0-9]{64}$/, 'NATIVE_QA_POLICY_HASH_REQUIRED: reviewed private fixture policies need explicit checksums');
    assert.ok(explicitFile === undefined || isAbsolute(explicitFile), 'NATIVE_QA_POLICY_PATH: use an explicit absolute existing private file');
    const file = explicitFile === undefined ? join(privateDirectory, name) : resolve(explicitFile), stat = lstatSync(file);
    assert.ok(stat.isFile() && !stat.isSymbolicLink() && stat.nlink === 1 && stat.size <= 262144
      && stat.uid === process.getuid() && (stat.mode & 0o077) === 0, 'NATIVE_QA_POLICY_CUSTODY: use an owner-only regular fixture policy');
    const bytes = readFileSync(file); assert.ok(digest(bytes) === expected, 'NATIVE_QA_POLICY_CHANGED');
    return { file, bytes, value: JSON.parse(bytes) };
  };
  const forward = privatePolicy('native-content-forward-policy.json', process.env.MRN_NATIVE_CONTENT_FORWARD_POLICY_SHA256, process.env.MRN_NATIVE_CONTENT_FORWARD_POLICY_FILE);
  const inverse = privatePolicy('native-content-inverse-policy.json', process.env.MRN_NATIVE_CONTENT_INVERSE_POLICY_SHA256, process.env.MRN_NATIVE_CONTENT_INVERSE_POLICY_FILE);
  const codeOwner = privatePolicy('native-code-policy.json', process.env.MRN_NATIVE_CODE_POLICY_SHA256, process.env.MRN_NATIVE_CODE_POLICY_FILE);
  const target = { kind: 'wordpress-content', post_type: 'page', post_id: input.post_id };
  const actor = 'native-fixture[bot]';
  const enrolled = owner => {
    assert.ok(owner.schema === 1 && owner.enabled === true && owner.repository === 'mrnwebdesigns/mrn-code-qa-runner'
      && owner.actors?.includes(actor), 'NATIVE_QA_POLICY_UNQUALIFIED');
    const found = owner.profiles.filter(p => p.site_id === site.site_id && p.site_url === origin && digest(p.target) === digest(target));
    assert.equal(found.length, 1, 'NATIVE_ACF_PROFILE_REQUIRED: qualify one exact owner-reviewed field renderer');
    const p = found[0];
    assert.ok(p.adapter === 'content-management-v1' && digest(p.acf_path) === digest(input.acf_path)
      && p.selector === input.acf_selector && digest(p.provider_source) === digest(input.management_profile_context.source)
      && p.wordpress_version === runtime.wordpress && p.php_minor === runtime.php_minor,
    'NATIVE_ACF_PROFILE_MISMATCH');
    assert.ok(p.descriptor.sha256 === digest(input.management_profile_context)
      && p.descriptor.runtime_context_sha256 === p.descriptor.sha256, 'NATIVE_ACF_DESCRIPTOR_MISMATCH');
    return p;
  };
  const profile = enrolled(forward.value), inverseProfile = enrolled(inverse.value);
  assert.ok(digest(profile.descriptor) === digest(inverseProfile.descriptor)
    && profile.runtime_profile_sha256 !== inverseProfile.runtime_profile_sha256 && profile.parity_sha256 !== inverseProfile.parity_sha256,
  'NATIVE_ACF_INVERSE_PROFILE_REQUIRED: inverse QA needs an independently qualified current-value baseline');
  assert.ok(codeOwner.value.schema === 1 && codeOwner.value.enabled === true && codeOwner.value.actors?.includes(actor), 'NATIVE_CSS_POLICY_UNQUALIFIED');
  const codeSites = codeOwner.value.sites.filter(p => p.site_id === site.site_id && p.site_url === origin + '/');
  assert.equal(codeSites.length, 1, 'NATIVE_CSS_PROFILE_REQUIRED');
  const codeSite = codeSites[0];
  assert.ok(codeSite.source.mode === 'unversioned' && codeSite.runtime_url === 'https://mrn-qa.internal:9443'
    && codeSite.wordpress_version === runtime.wordpress && codeSite.php_minor === runtime.php_minor
    && codeSite.roots.some(root => input.css_path.startsWith(root)) && typeof codeSite.rollback_profile === 'string'
    && codeSite.rollback_profile_sha256 !== codeSite.profile_sha256, 'NATIVE_CSS_INVERSE_PROFILE_REQUIRED');
  const providerPolicy = JSON.parse(readFileSync(join(privateDirectory, 'native-content-policy.json')));
  assert.equal(providerPolicy.qa_keys.length, 1, 'NATIVE_FIXTURE_SIGNER_REQUIRED');
  const keyId = providerPolicy.qa_keys[0].id, publicKeyBase64 = providerPolicy.qa_keys[0].public_key_base64;
  const contentPolicy = contentQaPolicySchema.parse({ version: 1, repository: forward.value.repository, workflowCommit: source.runner.commit,
    actor, keyId, publicKeyBase64, engineCommit: runnerLock.engine_commit,
    profiles: [{ siteUrl: origin, profile: profile.descriptor, targets: [{ target, selector: input.acf_selector,
      acfPath: input.acf_path, providerSource: input.management_profile_context.source }] }] });
  const codePolicy = codePolicySchema.parse({ version: 1, repository: codeOwner.value.repository, workflowCommit: source.runner.commit,
    actor, keyId, publicKeyBase64, engineCommit: runnerLock.engine_commit,
    profiles: [{ siteUrl: origin + '/', paths: [input.css_path], runtimeUrl: codeSite.runtime_url, source: { mode: 'unversioned' } }] });
  const stats = { qa: 0, contentQa: 0, codeQa: 0, backups: 0, publicReads: 0 };
  let configuredService, previousCodePolicy;
  const cli = php => {
    try { return execFileSync(runtimeCli, ['eval', php], { encoding: 'utf8', timeout: 30000, stdio: ['ignore', 'pipe', 'ignore'] }); }
    catch { throw new Error('NATIVE_FIXTURE_STATE_UNAVAILABLE: private CLI output withheld'); }
  };
  const guard = `if(getenv('MRN_DISPOSABLE_API_FIXTURE')!=='1'||!defined('WP_CLI')||!WP_CLI||home_url()!=='https://wpcontrol.mrndev.io')throw new RuntimeException('Disposable Dashboard only');`;
  const qaWorker = async (kind, request, policy) => {
    const requestB64 = Buffer.from(JSON.stringify(request)).toString('base64');
    const output = join(privateDirectory, `native-engine-${request.request_id}`);
    const env = Object.fromEntries(['PATH', 'HOME', 'LANG', 'TMPDIR'].filter(key => process.env[key]).map(key => [key, process.env[key]]));
    // Worker memory/environment never receives backend credentials or signer
    // keys. Its result comes only from the existing actual isolation function.
    await new Promise((done, reject) => {
      let passed = false;
      const worker = new Worker(`const {parentPort,workerData:d}=require('node:worker_threads');(async()=>{const fs=require('node:fs'),url=require('node:url'),crypto=require('node:crypto');const bytes=fs.readFileSync(d.policy);if(crypto.createHash('sha256').update(bytes).digest('hex')!==d.policySha)throw Error('NATIVE_QA_POLICY_CHANGED');const policy=JSON.parse(bytes);const m=await import(url.pathToFileURL(d.root+'/src/'+(d.kind==='content'?'content-isolate.mjs':'isolate.mjs')).href);const options={root:d.root,requestB64:d.request,policy,output:d.output,fixture:true,api:{async source(){return {mode:'unversioned'};}}};const result=await (d.kind==='content'?m.contentQA(options):m.isolatedQA(options));if(result.result!=='Pass')throw Error('QA_ENGINE_FAILED');parentPort.postMessage({result:'Pass'});})().catch(e=>{parentPort.postMessage({result:'Blocked',code:/^[A-Z0-9_]+$/.test(e.message)?e.message:'NATIVE_QA_FAILED'});process.exitCode=1;});`,
        { eval: true, workerData: { root: runnerRoot, kind, request: requestB64, policy: policy.file, policySha: digest(policy.bytes), output }, env, stdout: true, stderr: true });
      worker.stdout.resume(); worker.stderr.resume();
      worker.on('message', value => { passed = value?.result === 'Pass'; });
      worker.on('error', () => reject(new Error('NATIVE_QA_WORKER_FAILED: no fixture receipt was issued')));
      worker.on('exit', code => code === 0 && passed ? done() : reject(new OpsError('NATIVE_QA_ENGINE_FAILED', 'Actual isolated QA did not pass; no fixture receipt was issued.')));
    });
    assert.ok(readFileSync(policy.file).equals(policy.bytes), 'NATIVE_QA_POLICY_CHANGED');
    return { requestB64, output };
  };
  const contexts = new Map();
  const fixtureApi = (context, owner, kind) => ({ async source() { return { mode: 'unversioned' }; }, async get(path) {
    const base = `/repos/${owner.repository}`, run = Number(context.run_id), workflow = `.github/workflows/${kind === 'content' ? 'content-qa.yml' : 'code-qa.yml'}`;
    if (path === `${base}/actions/runs/${run}`) return { repository: { id: owner.repository_id, full_name: owner.repository }, head_repository: { id: owner.repository_id },
      event: 'workflow_dispatch', head_branch: 'main', head_sha: context.sha, run_attempt: 1, path: workflow, workflow_id: 1,
      actor: { login: actor }, triggering_actor: { login: actor }, status: 'completed' };
    if (path === `${base}/actions/workflows/1`) return { path: workflow, state: 'active' };
    if (path === `${base}/git/ref/heads/main`) return { object: { sha: context.sha } };
    if (path === `${base}/actions/runs/${run}/attempts/1/jobs?per_page=100`) return { jobs: [{ name: 'qualification', status: 'completed', conclusion: 'success', steps: [{ name: 'Run isolated QA Engine', conclusion: 'success' }] }] };
    if (path === `${base}/actions/runs/${run}/artifacts?per_page=100`) return { artifacts: [{ id: run, name: `qualification-${run}-1`, expired: false, size_in_bytes: 1, workflow_run: { id: run, head_sha: context.sha } }] };
    throw new Error('NATIVE_FIXTURE_PROVENANCE_PATH');
  } });
  const actualQa = (instance, kind) => {
    instance.dispatch = async (request, authorize) => {
      await authorize(); instance.qualifyRequest(request);
      const selected = kind === 'content' ? (request.review.rollback_operation_id ? inverse : forward) : codeOwner;
      assert.ok(readFileSync(selected.file).equals(selected.bytes), 'NATIVE_QA_POLICY_CHANGED');
      const result = await qaWorker(kind, request, selected); await authorize();
      const runId = ++stats.qa; stats[kind === 'content' ? 'contentQa' : 'codeQa']++;
      contexts.set(request.request_id, { ...result, selected, runId });
      return { runId, url: `https://github.com/${instance.policy.repository}/actions/runs/${runId}` };
    };
    instance.receipt = async (ticket, request, authorize) => {
      await authorize(); const run = contexts.get(request.request_id);
      assert.ok(run && run.runId === ticket.runId, 'NATIVE_ACTUAL_QA_REQUIRED');
      // Match the existing workflow's selected artifact members. Supervisor
      // diagnostics stay in the original output; the strict issuer still sees
      // exactly its three bounded, independently copied data files.
      const selectedNames = kind === 'content' ? ['qa.md', 'qualification.json', 'request.json'] : ['qa.md', 'qualification.json', 'candidate.bin'];
      if (!run.receiptDirectory) {
        run.receiptDirectory = join(privateDirectory, `native-receipt-${request.request_id}`);
        mkdirSync(run.receiptDirectory, { mode: 0o700 });
        for (const name of selectedNames) {
          const original = join(run.output, name), stat = lstatSync(original);
          const maximum = name === 'qa.md' ? 1048576 : name === 'qualification.json' ? 16384 : kind === 'content' ? 48000 : 32768;
          assert.ok(stat.isFile() && !stat.isSymbolicLink() && stat.nlink === 1 && stat.uid === process.getuid() && stat.size <= maximum,
            'NATIVE_QA_ARTIFACT_CUSTODY');
          const bytes = readFileSync(original), staged = join(run.receiptDirectory, name);
          writeFileSync(staged, bytes, { flag: 'wx', mode: 0o600 });
          assert.ok(readFileSync(staged).equals(bytes) && readFileSync(original).equals(bytes), 'NATIVE_QA_ARTIFACT_CHANGED');
        }
      }
      const module = await import(pathToFileURL(join(runnerRoot, 'src', kind === 'content' ? 'content-sign.mjs' : 'sign.mjs')));
      const context = { run_id: String(ticket.runId), run_attempt: '1', repository: instance.policy.repository, ref: 'refs/heads/main', sha: source.runner.commit };
      // Only a disposable signer is opened, after actual QA and artifact checks.
      const keyPem = createPrivateKey({ key: readFileSync(join(privateDirectory, 'native-content-qa.der')), format: 'der', type: 'pkcs8' }).export({ format: 'pem', type: 'pkcs8' });
      const options = { root: runnerRoot, requestB64: run.requestB64, policy: run.selected.value, lock: runnerLock, context,
        directory: run.receiptDirectory, api: fixtureApi(context, run.selected.value, kind), keyPem, keyId };
      const envelope = await (kind === 'content' ? module.issueContent(options) : module.issue(options));
      await authorize();
      if (kind === 'content') {
        const reportSha256 = digest(readFileSync(join(run.receiptDirectory, 'qa.md')));
        const verified = instance.verifyReceipt(envelope, request, ticket.runId, reportSha256);
        assert.equal(verified.claims.qa_report_sha256, reportSha256, 'NATIVE_QA_ARTIFACT_MISMATCH');
        return { status: 'qualified', ...verified, reportSha256 };
      }
      return { status: 'qualified', ...instance.verifyReceipt(envelope, request, ticket.runId) };
    };
    return instance;
  };
  const contentQa = actualQa(new ProtectedContentQa(null, contentPolicy), 'content');
  const codeQa = actualQa(new ProtectedCodeQa(null, codePolicy), 'code');
  const backups = new Map();
  const wrapClient = client => {
    const original = client.callTool.bind(client);
    client.callTool = async (request, schema, options) => {
      if (![toolNames.backup, toolNames.backupStatus].includes(request.name)) return original(request, schema, options);
      await client.verifyStaff(); requireWriteScope(client.actor); assert.equal(request.arguments.site_id, site.site_id);
      let value;
      if (request.name === toolNames.backup) {
        const running = configuredService.store.history({ url: origin }, 'operation').filter(op => op.status === 'running' && op.requestedBy === client.actor.subject);
        assert.equal(running.length, 1, 'NATIVE_RECOVERY_OPERATION_UNAVAILABLE');
        const p = running[0].plan.provider;
        assert.ok([origin, origin + '/'].includes(p.site_url), 'NATIVE_RECOVERY_EXACT_SITE_REQUIRED');
        // Existing provider-owned fixture custody only. This driver does not
        // manufacture remote/readability/restoration assertions or an issuer.
        const php = guard + `$id='${p.operation_id}';$path='/fixture-private/native-write-recovery-'.$id.'.json';`
          + `$stat=lstat($path);if(!is_file($path)||is_link($path)||!is_array($stat)||$stat['nlink']!==1||$stat['size']>4096||(fileperms($path)&0077)!==0)throw new RuntimeException('Qualified recovery fixture required');`
          + `$v=json_decode(file_get_contents($path),true);if(($v['operation_id']??null)!==$id||($v['digest']??null)!=='${p.digest}'||($v['site_id']??null)!==${site.site_id})throw new RuntimeException('Recovery binding');`
          + `$s=get_transient(MRN_MainWP_Operations_API::receipt_key(${site.site_id},$v['nonce']));$r=$s['write_recovery']??array();`
          + `if(($r['operation_id']??null)!==$id||($r['operation_sha256']??null)!=='${p.digest}'||($r['site_url']??null)!=='${p.site_url}'||($r['expires_at']??0)<=time()||!hash_equals($s['receipt_hash']??'',hash('sha256',$v['receipt']??'')))throw new RuntimeException('Recovery unavailable');`
          + `echo wp_json_encode(array_intersect_key($v,array_flip(array('site_id','nonce','receipt'))));`;
        let receipt;
        try { receipt = JSON.parse(cli(php)); }
        catch { throw new OpsError('NATIVE_QUALIFIED_RECOVERY_REQUIRED', 'An operation-bound existing provider recovery receipt is absent or unqualified. No replacement proof was manufactured.'); }
        assert.ok(receipt.site_id === site.site_id && /^[a-f0-9]{1,64}$/.test(receipt.nonce) && typeof receipt.receipt === 'string' && receipt.receipt.length >= 32,
          'NATIVE_RECOVERY_RECEIPT_INVALID');
        backups.set(receipt.nonce, { ...receipt, subject: client.actor.subject }); stats.backups++;
        value = { site_id: site.site_id, nonce: receipt.nonce, status: 'started' };
      } else {
        const receipt = backups.get(request.arguments.nonce); assert.equal(receipt?.subject, client.actor.subject);
        value = { site_id: site.site_id, nonce: receipt.nonce, receipt: receipt.receipt, status: 'complete' };
      }
      return { content: [{ type: 'text', text: JSON.stringify(value) }] };
    };
    return client;
  };
  const address = readFileSync(join(privateDirectory, 'tls-ip'), 'utf8'); assert.match(address, /^(?:10\.|172\.(?:1[6-9]|2[0-9]|3[01])\.|192\.168\.)[0-9.]+$/);
  const publicProbe = async (url, authorize, options = {}) => {
    await authorize(); const parsed = new URL(url); assert.equal(parsed.origin, origin);
    assert.ok(!parsed.search && !parsed.hash && ['/', '/native-child-fixture/', '/wp-json/'].includes(parsed.pathname));
    const result = await new Promise((done, reject) => {
      const request = https.get(parsed, { ca: readFileSync(certificate), rejectUnauthorized: true,
        lookup: (_hostname, settings, callback) => settings.all ? callback(null, [{ address, family: 4 }]) : callback(null, address, 4), headers: { 'Accept-Encoding': 'identity' } }, response => {
        const chunks = []; let bytes = 0;
        response.on('data', chunk => { bytes += chunk.length; if (bytes > 2097152) request.destroy(new Error('Native response limit')); else chunks.push(chunk); });
        response.on('error', reject); response.on('end', () => { try { done(responseEvidence(url, response.statusCode, Buffer.concat(chunks), {}, options)); } catch (error) { reject(error); } });
      });
      request.setTimeout(20000, () => request.destroy(new Error('Native readback timeout'))); request.on('error', reject);
    });
    await authorize(); stats.publicReads++; return result;
  };
  return { site, input, target, profile, stats, publicProbe, wrapClient,
    registry: { version: 1, websites: [{ id: 'native-write-fixture', name: 'Owned native write fixture', environments: [{ name: 'development', url: origin,
      management: 'mainwp', backup: 'updraft', sources: ['mainwp'], coordination: { exclusiveWriter: 'mrn-operations', evidenceRef: 'owned-disposable-network', validUntil: new Date(Date.now() + 3600000).toISOString() } }] }] },
    configure(service) {
      configuredService = service;
      const setup = guard + `$fixture=json_decode(file_get_contents('/fixture-private/native-write-fixture.json'),true);`
        + `if(($fixture['site_url']??null)!=='${origin}'||($fixture['site_id']??null)!==${site.site_id})throw new RuntimeException('Fixture target');`
        + `$old=get_option('mrn_mainwp_code_policy');echo base64_encode(wp_json_encode($old));$p=MRN_MainWP_Code_Identity::policy();$p['enabled']=true;$p['setup_complete']=true;$p['independent_approval']=false;`
        + `$p['sites']=array(array('site_id'=>${site.site_id},'site_url'=>'${origin}/','roots'=>array('wp-content/themes/${runtime.stylesheet}/'),'source'=>array('mode'=>'unversioned'),'qa_runtime_url'=>'https://mrn-qa.internal:9443'));`
        + `$p['qa_keys']=array(array('id'=>'${keyId}','public_key_base64'=>'${publicKeyBase64}'));if(MRN_MainWP_Code_Evidence::policy($p)!==true)throw new RuntimeException('Fixture code policy');`
        + `$p['signature']=hash_hmac('sha256','mrn-code-policy:'.wp_json_encode($p),MRN_MainWP_Code_Identity::key('authentication'));update_option('mrn_mainwp_code_policy',$p,false);`;
      previousCodePolicy = cli(setup).trim(); assert.match(previousCodePolicy, /^[A-Za-z0-9+/]+={0,2}$/, 'NATIVE_POLICY_CUSTODY');
      service.contentWorkflow = new ContentWorkflow(service, { qa: contentQa, delay: async () => {} });
      service.codeWorkflow = new CodeWorkflow(service, { source: null, qa: codeQa, delay: async () => {} });
    },
    async close() { if (previousCodePolicy) cli(guard + `$p=json_decode(base64_decode('${previousCodePolicy}',true),true);update_option('mrn_mainwp_code_policy',$p,false);`); },
  };
}

export async function checkNativeWriteWorkflows({ fixture, token, call, toolResult, pass, service, network, store, report, fixtureOption }) {
  const alice = await token('111111', { scope: 'mrn:operations mrn:operations:write' });
  const bob = await token('222222', { scope: 'mrn:operations mrn:operations:write' });
  const readOnly = await token('111111');
  const tools = (bearer, name, args) => call(bearer, 'tools/call', { name, arguments: args,
    _meta: { subject: 'auth0|forged', email: 'outsider@example.invalid' } });
  const invoke = async (name, args = {}) => toolResult(await tools(alice, name, args));
  const settle = async id => { await service.jobs.get(id); return invoke('get_operation', { operationId: id }); };
  const rejected = (response, code) => {
    assert.equal(response.status, 200); assert.equal(response.body.result.isError, true);
    assert.equal(JSON.parse(response.body.result.content[0].text).error.code, code);
  };
  const aliceSites = await invoke('list_websites'), bobSites = toolResult(await tools(bob, 'list_websites', {}));
  assert.deepEqual(aliceSites.websites, bobSites.websites); assert.equal(aliceSites.websites.length, 1);
  pass('Two approved active members obtain equal native portfolio access with separate Google identities');
  const contentInput = { website: origin, environment: 'development', target: fixture.target };
  const before = await invoke('inspect_content_record', contentInput);
  const leaf = snapshot => fixture.input.acf_path.reduce((value, part) => value[part], snapshot.acf_values);
  assert.equal(leaf(before.snapshot), fixture.input.acf_original);
  const contentArgs = { ...contentInput, expectedSnapshotSha256: before.snapshot_sha256,
    acfPatch: { path: fixture.input.acf_path, value: fixture.input.acf_candidate }, reason: 'Owned actual QA scalar workflow', requestKey: 'native-acf-forward' };
  rejected(await tools(readOnly, 'prepare_content_edit', contentArgs), 'INSUFFICIENT_SCOPE');
  assert.equal(fixture.stats.qa, 0); assert.equal(fixture.stats.backups, 0);
  const prepared = await settle((await invoke('prepare_content_edit', contentArgs)).id);
  assert.equal(prepared.status, 'prepared', JSON.stringify(prepared.error));
  assert.equal(prepared.preview.before, fixture.input.acf_original); assert.equal(prepared.preview.after, fixture.input.acf_candidate);
  assert.equal((await invoke('inspect_content_record', contentInput)).snapshot_sha256, before.snapshot_sha256);
  assert.equal(fixture.stats.contentQa, 1); assert.equal(fixture.stats.backups, 0);
  pass('Native ACF preparation preserves the complete record and uses actual isolated QA plus its purpose-separated fixture receipt');
  const execute = async (op, kind) => {
    rejected(await tools(alice, 'execute_operation', { operationId: op.id }), 'APPROVAL_REQUIRED');
    rejected(await tools(bob, 'execute_operation', { operationId: op.id }), kind === 'content' ? 'CONTENT_OPERATOR' : 'CODE_OPERATOR');
    rejected(await tools(alice, 'approve_operation', { operationId: op.id, planDigest: '0'.repeat(64) }), 'PLAN_CHANGED');
    fixtureOption('111111'); assert.equal((await tools(alice, 'execute_operation', { operationId: op.id })).status, 401); fixtureOption('');
    const approved = await invoke('approve_operation', { operationId: op.id, planDigest: op.planDigest }); assert.equal(approved.status, 'approved');
    await invoke('execute_operation', { operationId: op.id }); const completed = await settle(op.id);
    assert.equal(completed.status, 'verified', JSON.stringify(completed.error));
    assert.equal(completed.requestedBy, 'google-oauth2|111111'); assert.equal(completed.approvedBy, completed.requestedBy); assert.equal(completed.executedBy, completed.requestedBy);
    assert.equal((await invoke('execute_operation', { operationId: op.id })).status, 'verified');
    return completed;
  };
  const complete = await execute(prepared, 'content');
  assert.equal(complete.result.contentSnapshotVerified, true); assert.equal(complete.result.unrelatedMetadataVerified, true);
  assert.equal(complete.result.publicHtmlContentVerified, true); assert.equal(complete.result.liveBrowserVerified, false);
  const installed = await invoke('inspect_content_record', contentInput);
  const expected = structuredClone(before.snapshot.acf_values); let cursor = expected;
  for (const part of fixture.input.acf_path.slice(0, -1)) cursor = cursor[part]; cursor[fixture.input.acf_path.at(-1)] = fixture.input.acf_candidate;
  assert.deepEqual(installed.snapshot.acf_values, expected); assert.deepEqual(installed.snapshot.core, before.snapshot.core);
  assert.equal(installed.snapshot.unrelated_meta_sha256, before.snapshot.unrelated_meta_sha256);
  const contentStatus = await invoke('get_operation', { operationId: complete.id, refreshProvider: true }); assert.equal(contentStatus.providerStatus.state, 'completed');
  pass('Confirmed ACF edit crosses real gateway/MCP/MainWP, enforces provider-owned recovery and independently verifies full data and rendered value');
  const inverse = await settle((await invoke('prepare_rollback', { operationId: complete.id, requestKey: 'native-acf-inverse' })).id);
  assert.equal(inverse.status, 'prepared', JSON.stringify(inverse.error)); assert.equal(inverse.preview.after, fixture.input.acf_original);
  const inverseDone = await execute(inverse, 'content'); assert.equal(inverseDone.result.rollbackAvailable, false);
  const restored = await invoke('inspect_content_record', contentInput);
  assert.deepEqual(restored.snapshot.acf_values, before.snapshot.acf_values); assert.deepEqual(restored.snapshot.core, before.snapshot.core);
  assert.deepEqual(restored.snapshot.acf_definitions, before.snapshot.acf_definitions); assert.equal(restored.snapshot.unrelated_meta_sha256, before.snapshot.unrelated_meta_sha256);
  assert.equal(fixture.stats.contentQa, 2);
  pass('Guarded ACF inverse uses separately qualified current-value QA and preserves unrelated/disabled field data');
  const cssInput = { website: origin, environment: 'development', path: fixture.input.css_path };
  const cssBefore = await invoke('inspect_website_code', cssInput);
  assert.equal(cssBefore.sha256, fixture.input.css_sha256); assert.equal(typeof cssBefore.content, 'string');
  const childCli = join(process.argv[2], 'wp-child-fixture-cli');
  const metadata = () => {
    let value;
    const php = `if(getenv('MRN_DISPOSABLE_API_FIXTURE')!=='1'||!defined('WP_CLI')||!WP_CLI||untrailingslashit(home_url())!=='${origin}')throw new RuntimeException('Owned Child only');`
      + `$i=json_decode(file_get_contents('/fixture-private/child-info.json'),true);$p=ABSPATH.$i['css_path'];clearstatcache(true,$p);$s=lstat($p);if(is_link($p)||!is_array($s)||$s['nlink']!==1)throw new RuntimeException('Exact CSS inode');`
      + `echo wp_json_encode(array('sha256'=>hash_file('sha256',$p),'metadata'=>array('mode'=>$s['mode']&0777,'uid'=>$s['uid'],'gid'=>$s['gid'])));`;
    try { value = JSON.parse(execFileSync(childCli, ['eval', php], { encoding: 'utf8', timeout: 30000, stdio: ['ignore', 'pipe', 'ignore'] })); }
    catch { throw new Error('NATIVE_CSS_METADATA_UNAVAILABLE: private Child CLI output withheld'); }
    assert.deepEqual(value.metadata, fixture.input.css_metadata); return value;
  };
  assert.equal(metadata().sha256, cssBefore.sha256);
  const candidate = '/* Owned actual QA legacy CSS. */\n.mrn-native-acf-copy { color: rgb(36, 36, 36); }\n';
  const cssArgs = { ...cssInput, expectedSha256: cssBefore.sha256, content: candidate, reason: 'Owned actual QA legacy CSS workflow', requestKey: 'native-css-forward' };
  rejected(await tools(readOnly, 'prepare_code_edit', cssArgs), 'INSUFFICIENT_SCOPE');
  const cssPlan = await settle((await invoke('prepare_code_edit', cssArgs)).id); assert.equal(cssPlan.status, 'prepared', JSON.stringify(cssPlan.error));
  assert.equal((await invoke('inspect_website_code', cssInput)).sha256, cssBefore.sha256); assert.equal(fixture.stats.codeQa, 1);
  const cssDone = await execute(cssPlan, 'code');
  assert.equal(cssDone.result.sha256, digest(candidate)); assert.equal(cssDone.result.publicVerified, true);
  const cssInstalled = await invoke('inspect_website_code', cssInput); assert.equal(cssInstalled.content, candidate); assert.equal(metadata().sha256, digest(candidate));
  pass('Legacy CSS uses the same approval/execution lifecycle, actual QA, server-owned recovery and independent byte/inode metadata readback');
  const cssInverse = await settle((await invoke('prepare_rollback', { operationId: cssDone.id, requestKey: 'native-css-inverse' })).id);
  assert.equal(cssInverse.status, 'prepared', JSON.stringify(cssInverse.error)); await execute(cssInverse, 'code');
  const cssRestored = await invoke('inspect_website_code', cssInput); assert.equal(cssRestored.content, cssBefore.content); assert.equal(cssRestored.sha256, cssBefore.sha256);
  assert.equal(metadata().sha256, cssBefore.sha256); assert.equal(fixture.stats.codeQa, 2); assert.equal(fixture.stats.qa, 4); assert.equal(fixture.stats.backups, 4);
  pass('A separately QA-qualified approved CSS inverse restores exact bytes, mode, owner and group without overwriting unrelated state');
  const writes = network().filter(row => ['mrn-mainwp/execute-content-edit-v1', 'mrn-mainwp/execute-code-change-v1'].includes(row.ability));
  assert.equal(writes.length, 4); assert.ok(writes.every(row => row.subject === 'google-oauth2|111111' && row.status === 200));
  const audit = store.db.prepare('SELECT subject,identity FROM audit WHERE event=? AND outcome=?').all('execute', 'verified');
  assert.equal(audit.length, 4); assert.ok(audit.every(row => row.subject === 'google-oauth2|111111' && JSON.parse(row.identity).googleSubject === '111111'));
  Object.assign(report, { scope: 'owned-disposable-native-write-workflows', contentWriteVerified: true, managementWritesVerified: true, codeWriteVerified: true,
    contentRollbackVerified: true, codeRollbackVerified: true, cssMetadataVerified: true, qaEngineVerified: true, actualQaRuns: 4,
    operationRecoveryGateVerified: true, protectedQaVerified: false, remoteBackupVerified: false, liveGoogleVerified: false,
    syntheticInputs: ['OAuth and Google Directory', 'GitHub provenance and ephemeral QA signer'], productionCredentials: false });
}

// Reproducible offline input checks; never asserts CI or opens a runtime.
if (process.argv[1] && pathToFileURL(resolve(process.argv[1])).href === import.meta.url && process.argv[2] === '--check-write-preflight') {
  const site = { site_id: 1, site_url: origin }, lock = { wordpress_version: '6.9.4', php_minor: '8.3' };
  const input = { schema: 1, site_id: 1, site_url: origin, post_id: 7, acf_path: ['field_owned', 'field_copy'],
    acf_selector: '.mrn-native-acf-copy', css_path: 'wp-content/themes/owned/mrn-native-code-fixture.css',
    management_profile_context: { runtime: { wordpress: '6.9.4', php_minor: '8.3', stylesheet: 'owned' } } };
  assert.equal(validateNativeWriteInputs(site, input, lock).stylesheet, 'owned');
  for (const change of [{ site_url: 'https://client.example' }, { site_id: 2 }, { acf_path: [] },
    { css_path: 'wp-content/themes/other/mrn-native-code-fixture.css' }, { css_path: 'wp-content/themes/owned/../secret.css' }]) {
    assert.throws(() => validateNativeWriteInputs(site, { ...input, ...change }, lock));
  }
  assert.throws(() => validateNativeWriteInputs(site, input, { wordpress_version: '7.1.3', php_minor: '8.2' }), /NATIVE_QA_RUNTIME_MISMATCH/);
  if (process.env.GITHUB_ACTIONS !== 'true') await assert.rejects(() => nativeWriteFixture({ privateDirectory: '/not-opened',
    certificate: '/not-opened', runtimeCli: '/not-opened' }), /existing disposable CI harness/);
  process.stdout.write('PASS native write preflight: owned inputs, target/path denials and actual runtime mismatch; no runtime, signer or credentials opened.\n');
}
