// Optional disposable integration only. Actual MCP/provider/Child and Node
// callbacks; synthetic Google, owner admission and signing authority remain
// explicitly separate from protected QA and ordinary-member acceptance.
import assert from 'node:assert/strict';
import { createPrivateKey, randomUUID } from 'node:crypto';
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { execFile, execFileSync } from 'node:child_process';
import { promisify } from 'node:util';
import { createLocalJWKSet, exportJWK, generateKeyPair, SignJWT } from 'jose';
import { createAuthenticator } from '../../src/auth.mjs';
import { digest } from '../../src/contracts.mjs';
import { IdentitySigner, delegationCanonical, delegationDigest } from '../../src/delegation.mjs';
import { DelegatedMainwpClient } from '../../src/delegated-mainwp.mjs';
import { managementAccessVetoPath } from '../../src/content-access.mjs';
import { createManagementAccessRuntime } from '../../src/content-access-runtime.mjs';
import { requesterDigest } from '../../src/access-principal.mjs';
import { validateManagementRead, validateManagementEvidence } from '../../src/content-management-contract.mjs';
import { validateManagementCapture, validateManagementMeasurements } from '../../src/content-management-capture.mjs';
import { createHttpServer } from '../../src/http.mjs';
import { connectMainwp, MainwpSession, toolNames } from '../../src/mainwp.mjs';
import { Store } from '../../src/store.mjs';
import { accessRegistry } from '../fixtures/access-registry.mjs';

process.umask(0o077);
assert.equal(process.env.GITHUB_ACTIONS, 'true');
assert.equal(process.env.MRN_DISPOSABLE_MANAGEMENT_TEST, '1');
const [directory, certificate, runtimeCli, contextFile] = process.argv.slice(2);
assert.equal(process.argv.length, 6); assert.equal(resolve(directory), directory);
assert.equal(certificate, join(directory, 'tls.crt')); assert.equal(runtimeCli, join(directory, 'wp-fixture-cli'));
assert.equal(contextFile, join(directory, 'management-integration/context.json'));
const descriptorBytes = readFileSync(contextFile); assert.ok(descriptorBytes.length <= 32768);
const context = JSON.parse(descriptorBytes);
assert.equal(context.schema, 1); assert.equal(context.mode, 'disposable-management-integration');
for (const key of ['production_credentials', 'production_activation', 'consumer_binding_issued']) assert.equal(context[key], false);
assert.deepEqual(Object.keys(context.site).sort(), ['site_id', 'site_url']);
assert.equal(context.site.site_url, 'https://child.fixture.example.invalid');
assert.ok(Number.isSafeInteger(context.site.site_id) && context.site.site_id > 0);
assert.deepEqual(Object.keys(context.target).sort(), ['kind', 'post_id', 'post_type']);
assert.equal(context.target.kind, 'wordpress-content'); assert.equal(context.target.post_type, 'page');
assert.ok(Number.isSafeInteger(context.target.post_id) && context.target.post_id > 0);
assert.equal(context.actor.subject, 'google-oauth2|222222'); assert.equal(context.actor.googleSubject, '222222');
assert.equal(context.actor.verifiedEmail, 'bob@mrnwebdesigns.com'); assert.equal(context.actor.googleConnection, 'con_RuntimeFixture');
assert.equal(context.actor.hostedDomain, 'mrnwebdesigns.com'); assert.equal(context.actor.identityProvider, 'google-oauth2');
assert.deepEqual(context.actor.scopes, ['mrn:operations', 'mrn:operations:write']);
assert.ok(context.actor.expiresAt > Date.now() && context.actor.expiresAt <= Date.now() + 180000);
assert.equal(context.profile_runtime_url, 'https://mrn-qa.internal:9443');
assert.deepEqual(context.limits, { hook_seconds: 180, owner_wait_seconds: 200, read_wait_seconds: 15, child_delay_seconds: 6 });
assert.deepEqual(context.control, { worker: join(directory, 'management-integration/worker'),
  route_switch: join(directory, 'management-integration/switch-callback'), callback_socket: join(directory, 'management-socket/callback.sock'),
  callback_path: managementAccessVetoPath });

const lock = JSON.parse(readFileSync(new URL('./sources.json', import.meta.url)));
const providerRoot = resolve(process.env.MRN_GATEWAY_PROVIDER_SOURCE), transportRoot = resolve(process.env.MRN_GATEWAY_MCP_SOURCE);
for (const [name, root] of [['provider', providerRoot], ['transport', transportRoot]])
  assert.equal(execFileSync('git', ['-C', root, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock[name].commit);
assert.equal(context.pins.provider_source_sha, lock.provider.commit);
// Independently validate the complete extracted package against immutable Git,
// not merely the descriptor's claim about its own source identity.
const packageRoot = join(directory, 'provider-source/mrn-mainwp-operations-api');
const manifestBytes = readFileSync(join(packageRoot, 'release-source.json')); assert.ok(manifestBytes.length <= 262144);
const manifest = JSON.parse(manifestBytes);
assert.deepEqual(Object.keys(manifest).sort(), ['files', 'schema', 'source_commit', 'version']);
assert.equal(manifest.schema, 1); assert.equal(manifest.source_commit, lock.provider.commit);
assert.equal(manifestBytes.toString(), delegationCanonical(manifest) + '\n');
assert.equal(context.pins.installed_manifest_sha256, digest(manifestBytes));
assert.equal(context.pins.source_binding_sha256, delegationDigest(manifest));
assert.ok(Object.keys(manifest.files).length > 0 && Object.keys(manifest.files).length <= 1000);
let totalSourceBytes = 0;
for (const [path, hash] of Object.entries(manifest.files)) {
  assert.match(path, /^[A-Za-z0-9_.-]+(?:\/[A-Za-z0-9_.-]+)*$/); assert.ok(!path.split('/').some(p => p === '.' || p === '..'));
  assert.match(hash, /^[a-f0-9]{64}$/);
  const bytes = readFileSync(join(packageRoot, path)); totalSourceBytes += bytes.length;
  assert.ok(bytes.length <= 16777216 && totalSourceBytes <= 33554432);
  assert.equal(digest(bytes), hash);
  assert.equal(digest(execFileSync('git', ['-C', providerRoot, 'show', `${lock.provider.commit}:${path}`], { maxBuffer: 16777216 })), hash);
}
const profileFiles = ['child/content-acf.php', 'child/content-fields.php', 'child/content-records.php',
  'includes/class-mrn-mainwp-content-management.php', 'includes/class-mrn-mainwp-content-source.php', 'release-source.json'];
assert.deepEqual(Object.keys(context.profile_source).sort(), profileFiles.sort());
for (const path of profileFiles) assert.equal(context.profile_source[path], digest(readFileSync(join(packageRoot, path))));

const work = join(directory, 'actual-gateway-management'); mkdirSync(work, { mode: 0o700 });
const home = join(work, 'home'); mkdirSync(home, { mode: 0o700 });
const preload = join(work, 'fixture-network.mjs'); copyFileSync(new URL('./fixture-network.mjs', import.meta.url), preload);
copyFileSync(certificate, join(work, 'tls.crt'));
writeFileSync(join(work, 'gateway-network.json'), JSON.stringify({ address: readFileSync(join(directory, 'tls-ip'), 'utf8'), certificate: join(work, 'tls.crt') }));
const credentials = JSON.parse(readFileSync(join(directory, 'credentials.json')));
const config = { command: process.execPath, args: ['--import', preload, join(transportRoot, 'dist/index.js')], cwd: home,
  envRefs: Object.fromEntries(['MAINWP_URL', 'MAINWP_USER', 'MAINWP_APP_PASSWORD', 'MAINWP_SAFE_MODE', 'HOME'].map(k => [k, { env: `FIXTURE_${k}` }])) };
const environment = { PATH: process.env.PATH, FIXTURE_MAINWP_URL: 'https://wpcontrol.mrndev.io', FIXTURE_MAINWP_USER: credentials.username,
  FIXTURE_MAINWP_APP_PASSWORD: credentials.password, FIXTURE_MAINWP_SAFE_MODE: 'false', FIXTURE_HOME: home };
const identity = { keyId: 'disposable-identity-only', privateKey: createPrivateKey({ key: readFileSync(join(directory, 'identity.der')), type: 'pkcs8', format: 'der' }) };
const signer = new IdentitySigner({ ...identity, connectionId: 'con_RuntimeFixture' });
const { registry, policy } = accessRegistry(context.site.site_url);
const store = new Store(join(work, 'management.sqlite')), clients = new Set(), processes = new Set();
const run = async (file, args, timeout, maxBuffer = 5243008) => {
  const promise = promisify(execFile)(file, args, { encoding: 'utf8', timeout, maxBuffer }); processes.add(promise.child);
  try { return (await promise).stdout; }
  catch { throw new Error('Disposable management control failed; private output withheld.'); }
  finally { processes.delete(promise.child); }
};
const worker = async (mode, id, hash) => {
  assert.ok(['context', 'capture', 'status', 'arm-delay', 'wait-read', 'read-capture', 'read-measurements'].includes(mode));
  assert.match(id, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
  const reader = ['read-capture', 'read-measurements'].includes(mode);
  if (reader) assert.match(hash, /^[a-f0-9]{64}$/); else assert.equal(hash, undefined);
  const raw = await run(context.control.worker, [mode, id, ...(reader ? [hash] : [])], mode === 'capture' ? 130000 : 25000);
  assert.ok(Buffer.byteLength(raw) <= 5243008); return JSON.parse(raw);
};
const rawClient = async () => { const c = await connectMainwp(config, environment); clients.add(c); return c; };
const report = { schema: 1, source: lock, scope: 'disposable-native-management-access-only', passed: [], productionCredentials: false,
  liveGoogleVerified: false, protectedWorkerVerified: false, renderedQaVerified: false, consumerBindingIssued: false,
  contentWriteVerified: false, remoteBackupVerified: false, chatgptAcceptanceVerified: false };
const pass = label => { report.passed.push(label); process.stdout.write(`PASS ${label}\n`); };
let server, runtime, routed = false;
try {
  runtime = await createManagementAccessRuntime({ enabled: true, store, registry, identity,
    connectionId: 'con_RuntimeFixture', connect: () => connectMainwp(config, environment) });
  const audience = 'https://operations.mrnwebdesigns.com/mcp', issuer = 'https://oauth.example.invalid/';
  const keys = await generateKeyPair('RS256');
  const keySet = createLocalJWKSet({ keys: [{ ...await exportJWK(keys.publicKey), alg: 'RS256', kid: 'disposable-management' }] });
  const authenticate = createAuthenticator({ issuer, audience, verifiedEmailClaims: true, googleWorkspace: { connectionId: 'con_RuntimeFixture' } }, keySet, async actor => {
    const client = new DelegatedMainwpClient(await rawClient(), actor, signer);
    try { await client.verifyStaff(); } finally { await client.close(); }
  });
  const bearer = await new SignJWT({ scope: 'mrn:operations mrn:operations:write', [`${audience}/email`]: context.actor.verifiedEmail,
    [`${audience}/email_verified`]: true, [`${audience}/identity_provider`]: 'google-oauth2', [`${audience}/google_sub`]: '222222',
    [`${audience}/google_connection`]: 'con_RuntimeFixture', [`${audience}/hosted_domain`]: 'mrnwebdesigns.com' })
    .setProtectedHeader({ alg: 'RS256', kid: 'disposable-management' }).setIssuer(issuer).setAudience(audience)
    .setSubject(context.actor.subject).setIssuedAt().setExpirationTime(Math.floor(context.actor.expiresAt / 1000)).sign(keys.privateKey);
  const actor = await authenticate(`Bearer ${bearer}`), client = new DelegatedMainwpClient(await rawClient(), actor, signer);
  assert.equal(actor.googleSubject, context.actor.googleSubject); assert.equal(actor.expiresAt, context.actor.expiresAt);
  server = createHttpServer({ publicUrl: audience, issuer, managementAccessVeto: runtime.veto });
  await new Promise((resolveListen, reject) => { server.once('error', reject); server.listen(context.control.callback_socket, resolveListen); });
  await run(context.control.route_switch, ['node'], 10000, 4096); routed = true;
  const target = registry.resolve(actor, context.site.site_url, undefined, 'content');
  const session = new MainwpSession(client, registry, store, actor, target, { action: 'content' });
  const site = await session.fresh(); assert.equal(site.id, context.site.site_id); assert.equal(site.url, context.site.site_url);
  const recordArgs = { ...context.site, post_type: context.target.post_type, post_id: context.target.post_id };
  const read = validateManagementRead(await session.call(toolNames.contentRecord, recordArgs), { site, target: context.target });
  assert.ok(Buffer.byteLength(delegationCanonical(read)) > 65535, 'Exercise the native stdout boundary with an actual large page');
  const profileContext = { adapter: 'content-management-v1', runtime: read.snapshot.runtime,
    acf_definitions: read.snapshot.acf_definitions, source: context.profile_source };
  const profileHash = delegationDigest(profileContext);
  const profile = { sha256: profileHash, runtime_context_sha256: profileHash, runtime_url: context.profile_runtime_url };
  const queue = async () => {
    const request = { ...recordArgs, expected_snapshot_sha256: read.snapshot_sha256,
      changes: { post_excerpt: 'Actual Node management capture candidate only' }, idempotency_key: randomUUID(),
      reason: 'Disposable Node original requester management capture' };
    const review = await client.previewManagementEdit(request);
    const migration = await client.readManagementMigration({ ...context.site, operation_id: review.operation_id });
    const evidence = validateManagementEvidence({ read, review, migration }, { site, target: context.target, request, profile });
    const args = { ...context.site, operation_id: review.operation_id, reviewed_digest: review.digest };
    const access = await client.verifyStaff();
    const expected = { evidence, request, profile, pins: context.pins, delegationPolicySha256: access.policy_sha256 };
    assert.notEqual(expected.delegationPolicySha256, expected.pins.authorization_policy_sha256);
    const queuedContext = await client.queueManagementQualification(args, { ledger: runtime.ledger, admit: async (original, input) => {
      assert.equal(original.subject, actor.subject); assert.deepEqual(input, args); return expected;
    } });
    return { ...expected, queuedContext, args };
  };
  const first = await queue(), id = first.queuedContext.binding.qualification_job_id;
  const mapped = JSON.parse(store.db.prepare('SELECT body FROM management_access_jobs WHERE id=?').get(id).body);
  assert.equal(mapped.actor.expiresAt, actor.expiresAt); assert.equal(mapped.requester, requesterDigest(actor));
  assert.equal(mapped.context.binding_sha256, first.queuedContext.binding_sha256);
  const originalContext = await worker('context', id); assert.equal(originalContext.ok, true);
  assert.deepEqual(originalContext.output, first.queuedContext);
  pass('Signed Google delegation binds fresh large-page preview, migration and original management intent');

  const captured = await worker('capture', id); assert.equal(captured.ok, true);
  const capture = validateManagementCapture(captured.output, first), artifactHash = capture.capture_artifact_sha256;
  assert.ok(capture.measurements.readback_chunks > 1 && capture.measurements.readback_bytes > 65535);
  const firstQueries = store.db.prepare('SELECT state,revision FROM management_access_queries WHERE job=?').all(id);
  assert.ok(firstQueries.length >= capture.measurements.native_calls * 2);
  assert.ok(firstQueries.every(q => q.state === 'clear' && q.revision === 1));
  report.capture = { binding_sha256: first.queuedContext.binding_sha256, artifact_sha256: artifactHash,
    measurement_record_sha256: delegationDigest(capture.measurements), measurements: capture.measurements, callbacks: firstQueries.length };
  pass('Actual management callbacks protect complete native capture and independently chunked readback');

  const recovered = await worker('read-capture', id, artifactHash); assert.equal(recovered.ok, true);
  assert.deepEqual(validateManagementCapture(recovered.output, first), capture);
  const measured = await worker('read-measurements', id, artifactHash); assert.equal(measured.ok, true);
  validateManagementMeasurements(measured.output, capture, first);
  const retained = await worker('status', id);
  assert.equal(retained.ok, true); assert.equal(retained.state, 'captured'); assert.equal(retained.retained_state_matches, true);
  assert.equal(retained.capture_artifact_sha256, artifactHash);
  pass('Retained capture and independent measurements preserve every original digest and count');

  policy.emailDomains[0].portfolio.actions = ['read'];
  assert.equal((await worker('read-capture', id, artifactHash)).ok, false);
  assert.equal((await worker('read-measurements', id, artifactHash)).ok, false);
  assert.ok(store.db.prepare("SELECT count(*) AS n FROM management_access_queries WHERE job=? AND state='denied'").get(id).n >= 2);
  policy.emailDomains[0].portfolio.actions = ['read', 'content'];
  assert.equal((await worker('read-measurements', id, artifactHash)).ok, true);
  pass('Current site permission is rechecked for retained management evidence without fallback');

  const second = await queue(), secondId = second.queuedContext.binding.qualification_job_id;
  const armed = await worker('arm-delay', secondId); assert.equal(armed.ok, true); assert.equal(armed.armed, true);
  const started = performance.now(), slow = worker('capture', secondId);
  // Attach immediately so a refused subprocess never becomes an unhandled rejection.
  const observed = slow.then(value => ({ value }), error => ({ error }));
  const pending = await worker('wait-read', secondId); assert.equal(pending.ok, true); assert.equal(pending.in_flight, true);
  assert.deepEqual(runtime.ledger.recordPendingRevocation(secondId), { state: 'pending-revocation', revision: 2, committed_denial: false });
  const outcome = await observed; if (outcome.error) throw outcome.error;
  assert.equal(outcome.value.ok, false); assert.ok(performance.now() - started >= 5000);
  const stopped = await worker('status', secondId);
  assert.equal(stopped.ok, true); assert.equal(stopped.capture_present, false); assert.equal(stopped.capture_authenticated, false);
  assert.equal(stopped.capture_artifact_sha256, null); assert.equal(stopped.retained_state_matches, true);
  assert.notEqual(stopped.state, 'captured');
  assert.ok(store.db.prepare("SELECT count(*) AS n FROM management_access_queries WHERE job=? AND state='pending-revocation' AND revision=2").get(secondId).n >= 1);
  pass('Pending revocation during the real delayed Child read prevents retaining a complete capture');

  assert.equal((await worker('read-capture', id, artifactHash)).ok, false);
  assert.equal((await worker('read-measurements', id, artifactHash)).ok, false);
  await assert.rejects(() => client.queueManagementQualification(first.args, { ledger: runtime.ledger, admit: async () => first }), { code: 'ACCESS_DENIED' });
  const after = validateManagementRead(await session.call(toolNames.contentRecord, recordArgs), { site, target: context.target });
  assert.equal(after.snapshot_sha256, read.snapshot_sha256);
  pass('Original-principal revocation blocks earlier evidence and new intent while the child record remains unchanged');

  const network = readFileSync(join(work, 'gateway-network.jsonl'), 'utf8').trim().split('\n').filter(Boolean).map(line => JSON.parse(line));
  const callbacks = network.filter(row => row.path?.endsWith('/mrn-mainwp/read-management-access-veto-query-v1/run'));
  // fixture-network independently rejects any query string before transport.
  assert.ok(callbacks.length > 0); assert.ok(callbacks.every(row => row.method === 'POST' && row.status === 200));
  assert.equal(network.some(row => row.path?.endsWith('/mrn-mainwp/read-access-veto-query-v1/run')), false);
  report.result = 'PASS'; report.managementCallbackVerified = true; report.originalIntentVerified = true;
  report.completeCaptureVerified = true; report.independentReadbackVerified = true; report.retainedMeasurementsVerified = true;
  report.currentPermissionVerified = true; report.pendingDuringReadVerified = true; report.pendingRecoveryDenied = true;
  report.childUnchangedVerified = true; report.callbackPosts = callbacks.length;
  assert.equal(report.passed.length, 6);
  writeFileSync(process.env.MRN_GATEWAY_MANAGEMENT_REPORT, JSON.stringify(report, null, 2) + '\n', { mode: 0o600 });
} finally {
  try { if (routed) await run(context.control.route_switch, ['disabled'], 10000, 4096); }
  finally {
    for (const child of processes) child.kill('SIGTERM');
    await Promise.allSettled([
      ...(server ? [new Promise(resolveClose => server.close(resolveClose))] : []),
      runtime?.close(), ...[...clients].map(client => client.close()),
    ]);
    store.close();
  }
}
