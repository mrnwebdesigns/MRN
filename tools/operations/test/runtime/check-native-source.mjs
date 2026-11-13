// Optional disposable integration: actual provider source capture, private MCP,
// gateway encryption and independent reconstruction. Google/OIDC/cache authority
// are explicit fixtures; this neither runs candidate code nor admits a write.
import assert from 'node:assert/strict';
import https from 'node:https';
import { once } from 'node:events';
import { mock } from 'node:test';
import { createPrivateKey, createPublicKey, generateKeyPairSync, randomBytes, randomUUID } from 'node:crypto';
import { copyFileSync, existsSync, lstatSync, mkdirSync, readFileSync, realpathSync, writeFileSync } from 'node:fs';
import { basename, join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { execFile, execFileSync } from 'node:child_process';
import { promisify } from 'node:util';
import { createLocalJWKSet, exportJWK, SignJWT } from 'jose';
import { createAuthenticator } from '../../src/auth.mjs';
import { digest } from '../../src/contracts.mjs';
import { IdentitySigner, delegationCanonical, delegationDigest } from '../../src/delegation.mjs';
import { DelegatedMainwpClient } from '../../src/delegated-mainwp.mjs';
import { createManagementAccessRuntime } from '../../src/content-access-runtime.mjs';
import { validateManagementRead, validateManagementEvidence } from '../../src/content-management-contract.mjs';
import { validateManagementCapture, validateManagementMeasurements } from '../../src/content-management-capture.mjs';
import { createHttpServer } from '../../src/http.mjs';
import { connectMainwp, MainwpSession, toolNames } from '../../src/mainwp.mjs';
import { ManagementEvidenceLedger } from '../../src/management-evidence-store.mjs';
import { ManagementEvidenceSigner } from '../../src/management-evidence-callback.mjs';
import { ManagementSourceCatalogRegistry } from '../../src/management-source-catalog.mjs';
import { createManagementSourceProvider, ManagementSourceContextReader } from '../../src/management-source-provider.mjs';
import { ManagementWorkerContext, managementWorkerAudience } from '../../src/management-worker-context.mjs';
import { ManagementSourceBroker, ManagementSourceReader } from '../../src/management-source-broker.mjs';
import { ManagementSourceContext } from '../../src/management-source-context.mjs';
import { Store } from '../../src/store.mjs';
import { accessRegistry } from '../fixtures/access-registry.mjs';
import { createNativeReportCustody } from './native-report-custody.mjs';

process.umask(0o077);
assert.equal(process.env.GITHUB_ACTIONS, 'true');
assert.equal(process.env.MRN_DISPOSABLE_SOURCE_TEST, '1');
assert.ok(['0', '1', undefined].includes(process.env.MRN_DISPOSABLE_REPORT_CUSTODY_TEST));
const reportCustodyEnabled = process.env.MRN_DISPOSABLE_REPORT_CUSTODY_TEST === '1';
const [directory, certificate, runtimeCli] = process.argv.slice(2);
assert.equal(process.argv.length, 5); assert.equal(resolve(directory), directory);
assert.equal(certificate, join(directory, 'tls.crt')); assert.equal(runtimeCli, join(directory, 'wp-fixture-cli'));
const sourceDir = join(directory, 'source-integration');
const closed = (value, keys) => assert.deepEqual(Object.keys(value).sort(), [...keys].sort());
const privateFile = (path, limit) => {
  const stat = lstatSync(path);
  assert.ok(stat.isFile() && !stat.isSymbolicLink() && stat.nlink === 1 && (stat.mode & 0o077) === 0
    && stat.uid === process.getuid() && stat.size > 0 && stat.size <= limit && realpathSync(path) === path);
  return stat;
};
const json = (name, limit = 32768) => { const path = join(sourceDir, name); privateFile(path, limit); return JSON.parse(readFileSync(path)); };
const descriptor = json('descriptor.json');
closed(descriptor, ['schema', 'fixture_only', 'production_activation', 'dashboard_origin', 'site', 'target', 'actor',
  'pins', 'profile_source', 'profile_runtime_url', 'control', 'limits', 'protocols', 'files']);
assert.equal(descriptor.schema, 1); assert.equal(descriptor.fixture_only, true); assert.equal(descriptor.production_activation, false);
assert.equal(descriptor.dashboard_origin, 'https://wpcontrol.mrndev.io');
assert.deepEqual(descriptor.files, { backend_credentials: 'credentials.json', identity_signer: 'identity.der', registrar_signer: 'registrar.der',
  owner_policy: 'owner-policy.json', registries: 'registries.json', signed_source_catalog: 'signed-source-catalog.json',
  source_catalog: 'source-catalog.json', source_cache_index: 'source-cache/index.json' });
assert.deepEqual(descriptor.control, { native_worker: 'native-worker', callback_switch: 'switch-callback', callback_socket: 'callback.sock' });
assert.deepEqual(descriptor.limits, { hook_seconds: 180, owner_wait_seconds: 200, read_wait_seconds: 15 });
closed(descriptor.site, ['site_id', 'site_url']); assert.equal(descriptor.site.site_url, 'https://child.fixture.example.invalid');
assert.ok(Number.isSafeInteger(descriptor.site.site_id) && descriptor.site.site_id > 0);
closed(descriptor.target, ['post_type', 'post_id']); assert.equal(descriptor.target.post_type, 'page');
assert.ok(Number.isSafeInteger(descriptor.target.post_id) && descriptor.target.post_id > 0);
closed(descriptor.actor, ['subject', 'googleSubject', 'verifiedEmail', 'identityProvider', 'hostedDomain', 'googleConnection', 'expiresAt', 'scopes']);
assert.deepEqual({ ...descriptor.actor, expiresAt: 0 }, { subject: 'google-oauth2|222222', googleSubject: '222222',
  verifiedEmail: 'bob@mrnwebdesigns.com', identityProvider: 'google-oauth2', hostedDomain: 'mrnwebdesigns.com',
  googleConnection: 'con_RuntimeFixture', expiresAt: 0, scopes: ['mrn:operations', 'mrn:operations:write'] });
assert.ok(descriptor.actor.expiresAt > Date.now() && descriptor.actor.expiresAt <= Date.now() + 180000);
assert.equal(descriptor.profile_runtime_url, 'https://mrn-qa.internal:9443');

const lock = JSON.parse(readFileSync(new URL('./sources.json', import.meta.url)));
const providerRoot = resolve(process.env.MRN_GATEWAY_PROVIDER_SOURCE), transportRoot = resolve(process.env.MRN_GATEWAY_MCP_SOURCE);
const runner = resolve(process.env.MRN_GATEWAY_MANAGEMENT_RUNNER_SOURCE);
for (const [name, root] of [['provider', providerRoot], ['transport', transportRoot], ['managementWorker', runner]])
  assert.equal(execFileSync('git', ['-C', root, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock[name].commit);
const protocolFiles = { management_qualification_sha256: 'content-management-qualification-v1.json',
  source_transfer_sha256: 'content-management-source-transfer-v1.json', source_context_sha256: 'content-management-source-context-v1.json',
  source_api_registration_sha256: 'content-management-source-api-v1.json' };
closed(descriptor.protocols, Object.keys(protocolFiles));
for (const [name, file] of Object.entries(protocolFiles)) {
  const bytes = execFileSync('git', ['-C', providerRoot, 'show', `${lock.provider.commit}:contracts/${file}`]);
  assert.equal(descriptor.protocols[name], digest(bytes));
  if (name !== 'source_api_registration_sha256') assert.deepEqual(bytes, readFileSync(new URL(`../../contracts/${file}`, import.meta.url)));
}
const packageRoot = join(directory, 'provider-source/mrn-mainwp-operations-api');
const manifestBytes = readFileSync(join(packageRoot, 'release-source.json')), manifest = JSON.parse(manifestBytes);
closed(manifest, ['schema', 'source_commit', 'version', 'files']); assert.equal(manifest.schema, 1);
assert.equal(manifest.source_commit, lock.provider.commit); assert.equal(manifestBytes.toString(), delegationCanonical(manifest) + '\n');
assert.equal(descriptor.pins.provider_source_sha, lock.provider.commit);
assert.equal(descriptor.pins.installed_manifest_sha256, digest(manifestBytes));
assert.equal(descriptor.pins.source_binding_sha256, delegationDigest(manifest));
closed(descriptor.pins, ['authorization_policy_sha256', 'provider_source_sha', 'installed_manifest_sha256', 'source_binding_sha256', 'job_intent_ttl_seconds']);
assert.ok(Object.keys(manifest.files).length > 0 && Object.keys(manifest.files).length <= 1000);
let sourceBytes = 0;
for (const [path, hash] of Object.entries(manifest.files)) {
  assert.match(path, /^[A-Za-z0-9_.-]+(?:\/[A-Za-z0-9_.-]+)*$/); assert.ok(!path.split('/').some(p => p === '.' || p === '..'));
  const bytes = readFileSync(join(packageRoot, path)); sourceBytes += bytes.length;
  assert.ok(bytes.length <= 16777216 && sourceBytes <= 33554432); assert.equal(digest(bytes), hash);
  assert.equal(digest(execFileSync('git', ['-C', providerRoot, 'show', `${lock.provider.commit}:${path}`], { maxBuffer: 16777216 })), hash);
}
const profileFiles = ['child/content-acf.php', 'child/content-fields.php', 'child/content-records.php',
  'includes/class-mrn-mainwp-content-management.php', 'includes/class-mrn-mainwp-content-source.php', 'release-source.json'];
closed(descriptor.profile_source, profileFiles);
for (const path of profileFiles) assert.equal(descriptor.profile_source[path], digest(readFileSync(join(packageRoot, path))));

const keys = json('registries.json'), catalog = json('source-catalog.json', 98304), signedCatalog = json('signed-source-catalog.json', 196608);
const owner = json('owner-policy.json', 131072), caches = json('source-cache/index.json', 131072);
assert.ok(Array.isArray(caches) && caches.length > 0 && caches.length <= 128);
closed(owner, ['schema', 'enabled', 'policy_epoch', 'evidence_policy', 'performance_budget', 'app_actor_id', 'source_recipe_catalog_sha256']);
assert.equal(owner.schema, 1); assert.equal(owner.enabled, false); assert.equal(owner.evidence_policy.enabled, false);
assert.match(owner.app_actor_id, /^[1-9][0-9]{0,29}$/);
assert.equal(owner.source_recipe_catalog_sha256, delegationDigest(catalog));
assert.equal(owner.policy_epoch, owner.evidence_policy.policy_epoch);
assert.equal(delegationDigest(owner.performance_budget), owner.evidence_policy.performance_budget_sha256);
const roleKey = (role, file) => {
  const path = join(sourceDir, file); privateFile(path, 512);
  const privateKey = createPrivateKey({ key: readFileSync(path), type: 'pkcs8', format: 'der' });
  const publicKey = createPublicKey(privateKey), raw = publicKey.export({ type: 'spki', format: 'der' }).subarray(-32).toString('base64');
  assert.equal(privateKey.asymmetricKeyType, 'ed25519');
  const selected = keys[role].filter(key => key.public_key_base64 === raw); assert.equal(selected.length, 1);
  return { keyId: selected[0].id, privateKey, publicKey };
};
const identity = roleKey('identity', 'identity.der'), registrar = roleKey('registrar', 'registrar.der');
const work = join(directory, 'actual-gateway-source'); mkdirSync(work, { mode: 0o700 });
const home = join(work, 'home'); mkdirSync(home, { mode: 0o700 });
const preload = join(work, 'fixture-network.mjs'); copyFileSync(new URL('./fixture-network.mjs', import.meta.url), preload);
copyFileSync(certificate, join(work, 'tls.crt'));
writeFileSync(join(work, 'gateway-network.json'), JSON.stringify({ address: readFileSync(join(directory, 'tls-ip'), 'utf8'), certificate: join(work, 'tls.crt') }));
const credentials = json('credentials.json');
const config = { command: process.execPath, args: ['--import', preload, join(transportRoot, 'dist/index.js')], cwd: home,
  envRefs: Object.fromEntries(['MAINWP_URL', 'MAINWP_USER', 'MAINWP_APP_PASSWORD', 'MAINWP_SAFE_MODE', 'HOME']
    .map(key => [key, { env: `FIXTURE_${key}` }])) };
// Native source capture has its own bounded transport. Keep the independent
// warmed access callback and ordinary delegated connections at their defaults.
const sourceConfig = { ...config, envRefs: { ...config.envRefs, MAINWP_REQUEST_TIMEOUT: { env: 'FIXTURE_MAINWP_REQUEST_TIMEOUT' } } };
const environment = { PATH: process.env.PATH, FIXTURE_MAINWP_URL: descriptor.dashboard_origin,
  FIXTURE_MAINWP_USER: credentials.username, FIXTURE_MAINWP_APP_PASSWORD: credentials.password,
  FIXTURE_MAINWP_SAFE_MODE: 'false', FIXTURE_HOME: home };
const signer = new IdentitySigner({ ...identity, connectionId: 'con_RuntimeFixture' });
const { registry } = accessRegistry(descriptor.site.site_url), store = new Store(join(work, 'source.sqlite'));
const clients = new Set(), processes = new Set(), cleanups = [];
const rawClient = async () => { const c = await connectMainwp(config, environment); clients.add(c); return c; };
const sourceClient = async () => {
  const client = await connectMainwp(sourceConfig, { ...environment, FIXTURE_MAINWP_REQUEST_TIMEOUT: '120000' });
  clients.add(client); return client;
};
const run = async (file, args, timeout, maxBuffer = 5243008, input) => {
  const pending = promisify(execFile)(file, args, { encoding: 'utf8', timeout, maxBuffer }); processes.add(pending.child);
  if (input !== undefined) pending.child.stdin.end(input);
  try { return (await pending).stdout; }
  catch { throw new Error('Disposable source fixture control failed; private output withheld.'); }
  finally { processes.delete(pending.child); }
};
const nativeReport = async (mode, id, input) => {
  assert.ok(['report-dispatch', 'report-packet'].includes(mode));
  assert.match(id, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
  const bytes = mode === 'report-dispatch' ? delegationCanonical(input) : undefined;
  if (bytes !== undefined) assert.ok(Buffer.byteLength(bytes) <= 32768); else assert.equal(input, undefined);
  const raw = await run(join(sourceDir, 'native-worker'), [mode, id], 25000, 1048576, bytes);
  const packet = JSON.parse(raw); assert.ok(raw.trimEnd() === delegationCanonical(packet)); return packet;
};
const native = async (mode, id, hash) => {
  const recovery = ['read-capture', 'read-measurements'].includes(mode);
  assert.ok(['capture', 'read-capture', 'read-measurements', 'status'].includes(mode));
  assert.match(id, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
  if (recovery) assert.match(hash, /^[a-f0-9]{64}$/); else assert.equal(hash, undefined);
  return JSON.parse(await run(join(sourceDir, 'native-worker'), [mode, id, ...(recovery ? [hash] : [])], mode === 'capture' ? 130000 : 25000));
};
const report = { schema: 1, result: 'INCOMPLETE', source: lock, scope: 'disposable-native-source-integration', passed: [],
  productionCredentials: false, liveGoogleVerified: false, hostedOidcVerified: false, hostedCacheProvenanceVerified: false,
  protectedRenderedQaVerified: false, consumerBindingIssued: false, contentWriteVerified: false,
  remoteBackupVerified: false, chatgptAcceptanceVerified: false };
const reportPath = process.env.MRN_GATEWAY_SOURCE_REPORT; assert.ok(typeof reportPath === 'string' && resolve(reportPath) === reportPath);
const save = () => writeFileSync(reportPath, JSON.stringify(report, null, 2) + '\n', { mode: 0o600 });
const pass = name => { report.passed.push(name); save(); process.stdout.write(`PASS ${name}\n`); };
// Fixed, aggregate fixture diagnostics only. Never retain callback bodies,
// provider messages, signed inputs, headers, original identities or credentials.
const callbackObservation = { requests: 0, ok: 0, refused: 0, closed: 0, vetoErrors: {} };
const callbackCodes = new Set(['ACCESS_INVALID', 'ACCESS_EXPIRED', 'ACCESS_BINDING', 'ACCESS_MAPPING',
  'ACCESS_REPLAY', 'ACCESS_TRANSPORT', 'ACCESS_UNAVAILABLE']);
save();
let access, source, reportCustody, callbackServer, tlsServer, networkHook, routed = false, recipient, assembly, reconstructed;
const sockets = new Set();
try {
  access = await createManagementAccessRuntime({ enabled: true, store, registry, identity,
    connectionId: 'con_RuntimeFixture', connect: rawClient });
  const verifyStaff = async actor => {
    registry.member(actor); const client = new DelegatedMainwpClient(await rawClient(), actor, signer);
    try { await client.verifyStaff(); } finally { await client.close(); }
  };
  const audience = 'https://operations.mrnwebdesigns.com/mcp', issuer = 'https://oauth.example.invalid/';
  const oauth = generateKeyPairSync('rsa', { modulusLength: 2048 });
  const oauthKeys = createLocalJWKSet({ keys: [{ ...await exportJWK(oauth.publicKey), alg: 'RS256', kid: 'fixture-source-oauth' }] });
  const authenticate = createAuthenticator({ issuer, audience, verifiedEmailClaims: true,
    googleWorkspace: { connectionId: 'con_RuntimeFixture' } }, oauthKeys, verifyStaff);
  const bearer = await new SignJWT({ scope: 'mrn:operations mrn:operations:write', [`${audience}/email`]: descriptor.actor.verifiedEmail,
    [`${audience}/email_verified`]: true, [`${audience}/identity_provider`]: 'google-oauth2', [`${audience}/google_sub`]: '222222',
    [`${audience}/google_connection`]: 'con_RuntimeFixture', [`${audience}/hosted_domain`]: 'mrnwebdesigns.com' })
    .setProtectedHeader({ alg: 'RS256', kid: 'fixture-source-oauth' }).setIssuer(issuer).setAudience(audience)
    .setSubject(descriptor.actor.subject).setIssuedAt().setExpirationTime(Math.floor(descriptor.actor.expiresAt / 1000)).sign(oauth.privateKey);
  const actor = await authenticate(`Bearer ${bearer}`); assert.equal(actor.expiresAt, descriptor.actor.expiresAt);
  const client = new DelegatedMainwpClient(await rawClient(), actor, signer);
  callbackServer = createHttpServer({ publicUrl: audience, issuer, managementAccessVeto: { async respond(input) {
    try { return await access.veto.respond(input); }
    catch (error) {
      const code = callbackCodes.has(error?.code) ? error.code : 'OTHER';
      callbackObservation.vetoErrors[code] = (callbackObservation.vetoErrors[code] || 0) + 1;
      throw error;
    }
  } }, ...(reportCustodyEnabled ? { managementEvidence: { respond(input) {
    assert.ok(reportCustody, 'Disposable report callback is not prepared'); return reportCustody.respond(input);
  } } } : {}) });
  callbackServer.on('request', (req, res) => {
    if (req.method !== 'POST' || req.url !== '/internal/mainwp/management-access-veto') return;
    callbackObservation.requests++;
    res.once('finish', () => { callbackObservation[res.statusCode === 200 ? 'ok' : 'refused']++; });
    res.once('close', () => { if (!res.writableFinished) callbackObservation.closed++; });
  });
  await new Promise((yes, no) => { callbackServer.once('error', no); callbackServer.listen(join(sourceDir, 'callback.sock'), yes); });
  await run(join(sourceDir, 'switch-callback'), ['node'], 10000, 4096); routed = true;
  const target = registry.resolve(actor, descriptor.site.site_url, undefined, 'content');
  const session = new MainwpSession(client, registry, store, actor, target, { action: 'content' });
  const site = await session.fresh(); assert.equal(site.id, descriptor.site.site_id); assert.equal(site.url, descriptor.site.site_url);
  const contentTarget = { kind: 'wordpress-content', ...descriptor.target }, args = { ...descriptor.site, ...descriptor.target };
  const before = validateManagementRead(await session.call(toolNames.contentRecord, args), { site, target: contentTarget });
  const profileHash = delegationDigest({ adapter: 'content-management-v1', runtime: before.snapshot.runtime,
    acf_definitions: before.snapshot.acf_definitions, source: descriptor.profile_source });
  const profile = { sha256: profileHash, runtime_context_sha256: profileHash, runtime_url: descriptor.profile_runtime_url };
  const request = { ...args, expected_snapshot_sha256: before.snapshot_sha256, changes: { post_excerpt: 'Native source qualification candidate only' },
    idempotency_key: randomUUID(), reason: 'Disposable full native source reconstruction' };
  const review = await client.previewManagementEdit(request);
  const migration = await client.readManagementMigration({ ...descriptor.site, operation_id: review.operation_id });
  const evidence = validateManagementEvidence({ read: before, review, migration }, { site, target: contentTarget, request, profile });
  const status = await client.verifyStaff(), expected = { evidence, request, profile, pins: descriptor.pins, delegationPolicySha256: status.policy_sha256 };
  const queueArgs = { ...descriptor.site, operation_id: review.operation_id, reviewed_digest: review.digest };
  const queuedContext = await client.queueManagementQualification(queueArgs, { ledger: access.ledger, admit: async (original, input) => {
    assert.equal(original.googleSubject, actor.googleSubject); assert.deepEqual(input, queueArgs); return expected;
  } });
  const job = queuedContext.binding.qualification_job_id, completeExpected = { ...expected, queuedContext };
  const captured = await native('capture', job);
  if (captured.ok !== true) {
    const state = await native('status', job);
    assert.equal(state.ok, true);
    assert.ok(['queued', 'capturing', 'captured', 'failed', 'uncertain'].includes(state.state));
    for (const key of ['capture_present', 'capture_authenticated', 'retained_state_matches']) assert.equal(typeof state[key], 'boolean');
    const states = { clear: 0, denied: 0, 'pending-revocation': 0, other: 0 };
    for (const row of store.db.prepare('SELECT state,count(*) AS n FROM management_access_queries WHERE job=? GROUP BY state').all(job)) {
      assert.ok(Number.isSafeInteger(row.n) && row.n >= 0 && row.n <= 512);
      states[Object.hasOwn(states, row.state) ? row.state : 'other'] += row.n;
    }
    report.failure = { phase: 'initial-management-capture',
      workerError: captured.error_code === 'fixture_management_worker_refused' ? captured.error_code : 'REDACTED',
      nativeState: state.state, capturePresent: state.capture_present,
      captureAuthenticated: state.capture_authenticated, retainedStateMatches: state.retained_state_matches,
      callback: callbackObservation, queryStates: states };
    if (captured.diagnostic) {
      const value = captured.diagnostic, safe = {};
      for (const [field, choices] of Object.entries({
        mode: ['capture'],
        response_error_code: ['mrn_management_qualification', 'mrn_site_locked', 'mrn_delegation_required', 'mrn_content_access',
          'rest_ability_cannot_execute', 'rest_ability_invalid_input', 'rest_invalid_param', 'rest_no_route'],
        management_stage: ['unavailable', 'claim', 'preflight', 'sync', 'native-program', 'native-response', 'native-child',
          'dataset-schema', 'dataset-plugins', 'dataset-runtime', 'dataset-paths', 'dataset-binding', 'dataset-snapshot',
          'dataset-identity', 'current-access', 'slice', 'dataset', 'readback', 'artifact', 'retention', 'post-commit'],
        transport_error: ['none', 'http_request_failed', 'http_request_not_executed'],
        refusal: ['unavailable', 'key', 'http-status', 'schema', 'deadline', 'content-type', 'content-encoding', 'binding', 'artifact-hash', 'recovery-hash'],
      })) safe[field] = choices.includes(value[field]) ? value[field] : 'redacted';
      for (const [field, maximum] of [['http_status', 599], ['elapsed_ms', 125001], ['response_bytes', 5242881]])
        safe[field] = Number.isSafeInteger(value[field]) && value[field] >= 0 ? Math.min(maximum, value[field]) : 0;
      for (const field of ['body_decoded', 'schema_valid', 'content_type_valid', 'content_encoding_valid']) safe[field] = value[field] === true;
      report.failure.workerDiagnostic = safe;
    }
    save(); process.stdout.write(JSON.stringify({ source_capture_failure: report.failure }) + '\n');
  }
  assert.equal(captured.ok, true);
  const capture = validateManagementCapture(captured.output, completeExpected), artifactHash = capture.capture_artifact_sha256;
  const recovered = await native('read-capture', job, artifactHash); assert.equal(recovered.ok, true);
  assert.equal(delegationDigest(validateManagementCapture(recovered.output, completeExpected)), delegationDigest(capture));
  const measured = await native('read-measurements', job, artifactHash); assert.equal(measured.ok, true);
  const measurements = validateManagementMeasurements(measured.output, capture, completeExpected);
  const ledger = new ManagementEvidenceLedger(access.ledger, { policy: () => owner.evidence_policy, appActorId: owner.app_actor_id });
  ledger.recordCapture(job, capture, measurements, completeExpected);
  pass('fresh-original-Google-intent-and-actual-native-capture-readback');

  const catalogRegistry = { schema: 1, keys, catalogs: [{ sha256: delegationDigest(catalog), envelope: signedCatalog }] };
  const catalogPin = delegationDigest(catalogRegistry);
  const catalogs = new ManagementSourceCatalogRegistry({ ledger, current: () => catalogRegistry, expectedSha256: () => catalogPin });
  source = await createManagementSourceProvider({ enabled: true, ledger, catalogs, registrar, verifyStaff, connect: sourceClient });
  const mapped = await source.prepare(job);
  assert.equal(delegationDigest(mapped.context.management), delegationDigest(ledger.captured(job).context));
  assert.ok(mapped.slot.native_files > 0 && mapped.slot.native_bytes > 0);
  await assert.rejects(() => source.prepare(job));
  pass('canonical-MCP-bootstrap-and-native-source-capture-retain-one-original-slot');

  const prepared = ledger.prepareDispatch(job), dispatch = ledger.recordDispatch(job, prepared.dispatch_id, '30');
  const original = ledger.dispatch(job), oidc = generateKeyPairSync('rsa', { modulusLength: 2048 });
  const trust = { repositoryOwnerId: '98', environment: 'MRN QA', runnerEnvironment: 'github-hosted',
    subject: `repo:${dispatch.repository}:environment:MRN QA` };
  const oidcKeys = createLocalJWKSet({ keys: [{ ...await exportJWK(oidc.publicKey), alg: 'RS256', kid: 'fixture-source-oidc' }] });
  const token = async () => {
    const at = Math.floor(Date.now() / 1000);
    return new SignJWT({ iss: 'https://token.actions.githubusercontent.com', aud: managementWorkerAudience, sub: trust.subject,
      iat: at, nbf: at, exp: Math.min(at + 60, Math.floor(actor.expiresAt / 1000)), jti: randomUUID(),
      repository: dispatch.repository, repository_id: dispatch.repository_id, repository_owner: 'mrnwebdesigns', repository_owner_id: '98',
      repository_visibility: 'private', run_id: dispatch.run_id, run_attempt: '1', actor_id: owner.app_actor_id, ref: dispatch.ref, ref_type: 'branch',
      workflow_ref: `${dispatch.repository}/${dispatch.workflow_path}@${dispatch.ref}`, workflow_sha: dispatch.runner_source_sha,
      sha: dispatch.runner_source_sha, event_name: 'workflow_dispatch', environment: trust.environment, runner_environment: 'github-hosted' })
      .setProtectedHeader({ alg: 'RS256', kid: 'fixture-source-oidc' }).sign(oidc.privateKey);
  };
  const worker = new ManagementWorkerContext({ ledger, identity, trust: () => trust, keys: oidcKeys, verifyStaff });
  const batchReader = new ManagementSourceReader(await rawClient()); await batchReader.initialize();
  const batchObservation = { backendReads: 0, backendCompleted: 0, backendError: null, backendElapsedMs: 0, httpStatus: null };
  const batchCodes = new Set(['ACCESS_UNAVAILABLE', 'DOWNSTREAM_TIMEOUT', 'MAINWP_ACCESS', 'MAINWP_FAILED',
    'MANAGEMENT_SOURCE_TRANSFER', 'MANAGEMENT_SOURCE_BROKER']);
  const broker = new ManagementSourceBroker({ worker, identity, registrar, reader: { async read(input) {
    batchObservation.backendReads++; const started = performance.now();
    try { const value = await batchReader.read(input); batchObservation.backendCompleted++; return value; }
    catch (error) { batchObservation.backendError = error?.code === -32001 ? 'MCP_TIMEOUT'
      : batchCodes.has(error?.code) ? error.code : 'REDACTED'; throw error; }
    finally { batchObservation.backendElapsedMs = Math.min(20001, Math.ceil(performance.now() - started)); }
  } }, resolveSource: value => source.capture.resolve(value) });
  const sourceContext = new ManagementSourceContext({ worker, source: source.capture });
  const gateway = createHttpServer({ publicUrl: audience, managementWorker: worker, managementSource: broker, managementSourceContext: sourceContext });
  gateway.on('request', (req, res) => {
    if (req.method === 'POST' && req.url === '/internal/mainwp/management-source-batch')
      res.once('finish', () => { batchObservation.httpStatus = res.statusCode; });
  });
  const tlsKey = join(work, 'broker.key'), tlsCert = join(work, 'broker.crt');
  execFileSync('openssl', ['req', '-x509', '-nodes', '-newkey', 'rsa:2048', '-keyout', tlsKey, '-out', tlsCert, '-days', '1',
    '-subj', '/CN=operations.mrnwebdesigns.com', '-addext', 'subjectAltName=DNS:operations.mrnwebdesigns.com'], { stdio: 'ignore' });
  tlsServer = https.createServer({ key: readFileSync(tlsKey), cert: readFileSync(tlsCert) }, (req, res) => gateway.emit('request', req, res));
  tlsServer.on('connection', socket => { sockets.add(socket); socket.once('close', () => sockets.delete(socket)); });
  tlsServer.listen(0, '127.0.0.1'); await once(tlsServer, 'listening');
  const { acquireManagementWorkerContext } = await import(pathToFileURL(join(runner, 'src/management-worker-context.mjs')));
  const { ManagementSourceRecipient } = await import(pathToFileURL(join(runner, 'src/management-source-recipient.mjs')));
  const { acquireManagementSourceContext, bindManagementSourceRecipient, readManagementSourceBatch,
    managementSourceContextUrl, managementSourceRecipientUrl, managementSourceBatchUrl }
    = await import(pathToFileURL(join(runner, 'src/management-source-http.mjs')));
  const { ManagementSourceAssembly } = await import(pathToFileURL(join(runner, 'src/management-source-assembly.mjs')));
  const { extractManagementCacheArchive } = await import(pathToFileURL(join(runner, 'src/management-cache-archive.mjs')));
  const realRequest = https.request;
  networkHook = mock.method(https, 'request', (url, options, handler) => {
    assert.ok([managementWorkerAudience, managementSourceContextUrl, managementSourceRecipientUrl, managementSourceBatchUrl].includes(url));
    assert.equal(options.rejectUnauthorized, true);
    return realRequest(url, { ...options, hostname: '127.0.0.1', port: tlsServer.address().port,
      ca: readFileSync(tlsCert), headers: { ...options.headers, Host: 'operations.mrnwebdesigns.com' } }, handler);
  });
  const pointer = { dispatch_id: dispatch.dispatch_id, context_sha256: delegationDigest(original.context) };
  const expectedDispatch = { ...Object.fromEntries(['repository_id', 'workflow_id', 'workflow_path', 'workflow_sha256', 'run_id',
    'runner_source_sha', 'engine_commit', 'engine_configuration_sha256', 'runtime_sha256'].map(key => [key, dispatch[key]])),
  app_actor_id: owner.app_actor_id, evidence_policy_sha256: original.context.evidence_policy_sha256 };
  const recorded = await acquireManagementWorkerContext({ pointer, expected: expectedDispatch,
    gatewayKeyId: identity.keyId, gatewayPublicKey: identity.publicKey, oidcToken: await token() });
  const metadata = await acquireManagementSourceContext({ original: recorded, gatewayKeyId: identity.keyId,
    gatewayPublicKey: identity.publicKey, oidcToken: await token() });
  assert.equal(delegationDigest(metadata.context), delegationDigest(mapped.context));
  recipient = new ManagementSourceRecipient({ original: recorded, sourceContext: metadata.context, slot: metadata.slot,
    gatewayKeyId: identity.keyId, gatewayPublicKey: identity.publicKey });
  await bindManagementSourceRecipient({ recipient, oidcToken: await token() });
  pass('independent-worker-reads-signed-original-metadata-and-binds-recipient-over-TLS');

  const parity = capture.capture_artifact.dataset.parity_manifest, b = mapped.context.management.binding;
  const recipe = { schema: 1, job_uuid: job, site_id: b.site_id, site_url: b.site_url,
    binding_sha256: mapped.context.management.management_context_sha256, capture_sha256: artifactHash,
    parity_sha256: mapped.context.management.parity_sha256, expires_at: b.expires_at,
    roots: parity.roots.map(({ path, kind }) => ({ path, kind })), native_reads: catalog.native_reads };
  assert.equal(delegationDigest(recipe), mapped.slot.recipe_sha256);
  assembly = new ManagementSourceAssembly({ context: metadata.context, parityManifest: parity, recipe, catalog, slot: metadata.slot });
  for (let batch = 0; batch * 16 < mapped.slot.total_frames; batch++) {
    let opened;
    try { opened = await readManagementSourceBatch({ recipient, batchIndex: batch, oidcToken: await token() }); }
    catch (error) {
      const errors = new Set(['SOURCE_HTTP_RESPONSE', 'SOURCE_HTTP_TIMEOUT', 'SOURCE_HTTP_TRANSPORT', 'SOURCE_HTTP_EXPIRED', 'SOURCE_HTTP_SIZE']);
      report.failure = { phase: 'source-batch', batchIndex: batch, workerError: errors.has(error?.message) ? error.message : 'REDACTED',
        sourceBatch: batchObservation, callback: callbackObservation };
      save(); process.stdout.write(JSON.stringify({ source_batch_failure: report.failure }) + '\n'); throw error;
    }
    assert.equal(opened.state, 'partial-source-batch-verified'); assembly.accept(opened);
  }
  pass('actual-provider-native-frames-cross-encrypted-broker-and-independent-decryption');
  const extracted = new Map(), ids = new Set();
  for (const entry of caches) {
    closed(entry, ['artifact_id', 'archive_path', 'archive_sha256', 'archive_bytes', 'root_kind', 'relative_inventory_sha256']);
    assert.match(entry.archive_path, /^source-cache\/cache-[0-9]{4}\.zip$/); assert.ok(!ids.has(entry.artifact_id)); ids.add(entry.artifact_id);
    const matches = catalog.artifacts.filter(a => a.artifact_id === entry.artifact_id); assert.equal(matches.length, 1);
    const artifact = matches[0];
    for (const field of ['archive_sha256', 'root_kind', 'relative_inventory_sha256']) assert.equal(entry[field], artifact[field]);
    assert.ok(Number.isSafeInteger(entry.archive_bytes) && entry.archive_bytes > 0 && entry.archive_bytes <= artifact.maximum_archive_bytes);
    const archivePath = join(sourceDir, entry.archive_path); assert.equal(privateFile(archivePath, artifact.maximum_archive_bytes).size, entry.archive_bytes);
    const roots = [...parity.roots, { kind: 'core-php', path: '', files: parity.core_php }];
    const inventories = roots.filter(root => root.files.length && root.kind === artifact.root_kind).map(root =>
      Object.fromEntries(root.files.map(file => [root.kind === 'file' ? basename(file.path) : root.kind === 'core-php'
        ? file.path : file.path.slice(root.path.length), file.sha256]).sort(([a], [b]) => a < b ? -1 : a > b ? 1 : 0)))
      .filter(inventory => delegationDigest(inventory) === artifact.relative_inventory_sha256);
    assert.equal(inventories.length, 1);
    const value = extractManagementCacheArchive({ archivePath, artifact, inventory: inventories[0], expiresAt: Math.min(actor.expiresAt, b.expires_at * 1000) });
    cleanups.push(value.cleanup); extracted.set(artifact.artifact_id, { directory: value.directory, archive_sha256: value.archive_sha256 });
  }
  assert.deepEqual([...ids].sort(), catalog.artifacts.map(a => a.artifact_id).sort());
  reconstructed = assembly.finish(extracted);
  assert.equal(reconstructed.result.source_bytes_verified, true); assert.equal(reconstructed.result.provenance_verified, false);
  assert.equal(reconstructed.result.admitted, false); await verifyStaff(actor); ledger.captured(job);
  report.sourceClosure = { manifest_sha256: reconstructed.result.manifest_sha256,
    parity_sha256: mapped.context.management.parity_sha256, transfer_manifest_sha256: mapped.slot.transfer_manifest_sha256,
    cache_artifacts: ids.size, native_files: mapped.slot.native_files, native_bytes: mapped.slot.native_bytes,
    source_frames: mapped.slot.total_frames };
  pass('complete-real-cache-and-native-source-reconstruction-independently-reopened');

  if (reportCustodyEnabled) {
    reportCustody = await createNativeReportCustody({ source: lock, reportPath: process.env.MRN_GATEWAY_REPORT_CUSTODY_REPORT,
      original, identity, registrar, keys, ledger, verifyStaff, connectQuery: rawClient, connectControl: sourceClient,
      networkLogPath: join(work, 'gateway-network.jsonl'),
      nativeDispatch: (id, envelope) => nativeReport('report-dispatch', id, envelope),
      nativePacket: id => nativeReport('report-packet', id) });
    await reportCustody.run();
  }

  const denialReader = new ManagementSourceContextReader(await rawClient()); await denialReader.initialize();
  const issued = Math.floor(Date.now() / 1000), requestSigner = new ManagementEvidenceSigner(registrar);
  const denial = requestSigner.envelope({ schema: 1, issuer: 'mrn-content-qualification', audience: descriptor.dashboard_origin,
    issued_at: issued, expires_at: Math.min(issued + 20, b.expires_at), request_id: randomUUID(), nonce: randomBytes(32).toString('hex'),
    action: 'read-management-source-context', qualification_job_id: job,
    management_context_sha256: mapped.context.management.management_context_sha256 }, 'mrn-content-management-source-context-v1:');
  const pendingQueries = () => store.db.prepare("SELECT count(*) AS n FROM management_access_queries WHERE job=? AND state='pending-revocation'").get(job).n;
  const beforeDenial = pendingQueries();
  assert.deepEqual(access.ledger.recordPendingRevocation(job), { state: 'pending-revocation', revision: 2, committed_denial: false });
  await assert.rejects(() => denialReader.read(denial));
  assert.ok(pendingQueries() > beforeDenial, 'Provider refusal must observe the actual original pending-revocation callback');
  // A second metadata read is unused and would otherwise be allowed. Reusing a
  // consumed batch would prove replay denial, not current access revocation.
  await assert.rejects(async () => acquireManagementSourceContext({ original: recorded, gatewayKeyId: identity.keyId,
    gatewayPublicKey: identity.publicKey, oidcToken: await token() }));
  pass('original-pending-revocation-denies-provider-bootstrap-and-protected-source');
  if (reportCustodyEnabled) {
    const beforeReportDenial = pendingQueries();
    await reportCustody.revoked();
    assert.ok(pendingQueries() > beforeReportDenial, 'Unused report read must observe the original pending-revocation callback');
  }
  const after = validateManagementRead(await session.call(toolNames.contentRecord, args), { site, target: contentTarget });
  assert.equal(after.snapshot_sha256, before.snapshot_sha256);
  pass('original-child-content-unchanged-without-candidate-execution');
  const network = readFileSync(join(work, 'gateway-network.jsonl'), 'utf8').trim().split('\n').filter(Boolean).map(line => JSON.parse(line));
  for (const route of ['read-content-edit-qualification-source-context-v1', 'capture-content-edit-qualification-source-v1', 'read-content-edit-qualification-source-v1']) {
    const calls = network.filter(row => row.path?.endsWith(`/mrn-mainwp/${route}/run`));
    assert.ok(calls.some(row => row.method === 'POST' && row.status === 200));
    assert.ok(calls.every(row => row.method === 'POST'));
  }
  for (const [route, statuses] of reportCustodyEnabled ? [
    ['retain-content-edit-qualification-report-v1', [200, 500]],
    ['read-content-edit-qualification-report-v1', [200, 500]],
    ['read-management-evidence-query-v1', [200]],
  ] : []) {
    const calls = network.filter(row => row.path?.endsWith(`/mrn-mainwp/${route}/run`));
    assert.deepEqual(calls.map(row => row.status), statuses);
    assert.ok(calls.every(row => row.method === 'POST'));
  }
  report.providerSourceBootstrapVerified = true; report.nativeSourceCaptureVerified = true;
  report.sourceBrokerTlsVerified = true; report.fullSourceBytesVerified = true; report.originalRevocationVerified = true;
  assert.equal(report.passed.length, 7);
} finally {
  try { if (routed) await run(join(sourceDir, 'switch-callback'), ['disabled'], 10000, 4096); }
  finally {
    const cleanupErrors = [];
    for (const clean of [() => networkHook?.mock.restore(), () => recipient?.destroy(), () => reconstructed?.cleanup(),
      () => assembly?.destroy(), ...cleanups]) {
      try { clean(); } catch (error) { cleanupErrors.push(error); }
    }
    for (const socket of sockets) socket.destroy(); for (const child of processes) child.kill('SIGTERM');
    const closed = await Promise.allSettled([reportCustody?.close(), source?.close(), access?.close(), ...[...clients].map(client => client.close()),
      ...(callbackServer ? [new Promise(done => callbackServer.close(done))] : []),
      ...(tlsServer ? [new Promise(done => tlsServer.close(done))] : [])]);
    cleanupErrors.push(...closed.filter(value => value.status === 'rejected').map(value => value.reason));
    store.close();
    if (reconstructed) assert.equal(existsSync(reconstructed.directory), false);
    assert.equal(cleanupErrors.length, 0, 'Every disposable connection and private source root must close');
  }
}
// A failed cleanup is a failed integration, even if all behavior checks passed.
report.result = 'PASS'; save();
reportCustody?.complete();
