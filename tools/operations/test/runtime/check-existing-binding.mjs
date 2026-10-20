// Separate native v2 fixture: real TLS/MCP/provider SQL, synthetic Google and
// Directory. No content execution, QA admission, production or team acceptance.
import assert from 'node:assert/strict';
import { createPrivateKey, randomUUID } from 'node:crypto';
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { createLocalJWKSet, exportJWK, generateKeyPair, SignJWT } from 'jose';
import { createAuthenticator, originalAuthorizationAnchor } from '../../src/auth.mjs';
import { digest } from '../../src/contracts.mjs';
import { IdentitySigner, delegationCanonical, delegationDigest } from '../../src/delegation.mjs';
import { DelegatedMainwpClient } from '../../src/delegated-mainwp.mjs';
import { validateManagementRead, validateManagementEvidence } from '../../src/content-management-contract.mjs';
import { connectMainwp, MainwpSession, payload, toolNames } from '../../src/mainwp.mjs';
import { OriginalManagementAuthorizationLedger } from '../../src/original-management-authorization.mjs';
import { createOriginalAuthorizationTransport, parseOriginalBindingProjection } from '../../src/original-authorization-transport.mjs';
import { Store } from '../../src/store.mjs';
import { accessRegistry } from '../fixtures/access-registry.mjs';
import { existingBindingProfile, existingBindingProfileFiles, parseExistingBindingContext } from './existing-binding-context.mjs';
import { bindingFixtureDiagnostic } from './existing-binding-diagnostics.mjs';

process.umask(0o077);
const clients = new Set(), transports = new Set();
const recentCalls = [];
let store, deadline, reportPath;
const report = { schema: 2, result: 'INCOMPLETE', scope: 'disposable-native-existing-binding-v2-only', passed: [],
  productionCredentials: false, productionActivation: false, liveGoogleVerified: false, protectedWorkerVerified: false,
  renderedQaVerified: false, consumerBindingIssued: false, contentWriteVerified: false, codeWriteVerified: false,
  remoteBackupVerified: false, chatgptAcceptanceVerified: false, afterTransportExpiryVerified: false };
const save = () => { if (reportPath) writeFileSync(reportPath, JSON.stringify(report, null, 2) + '\n', { mode: 0o600 }); };
const pass = label => { report.passed.push(label); save(); process.stdout.write(`PASS ${label}\n`); };
let phase = 'fixture-validation';
try {
  assert.equal(process.env.GITHUB_ACTIONS, 'true');
  assert.equal(process.env.MRN_DISPOSABLE_EXISTING_BINDING_TEST, '1');
  const [directory, certificate, runtimeCli, contextFile] = process.argv.slice(2);
  assert.equal(process.argv.length, 6); assert.equal(resolve(directory), directory);
  assert.equal(certificate, join(directory, 'tls.crt'));
  assert.equal(runtimeCli, join(directory, 'existing-binding-v2/wp-fixture-cli'));
  assert.equal(contextFile, join(directory, 'existing-binding-v2/context.json'));
  assert.ok(readFileSync(runtimeCli).length > 0); // Provider owns the barrier; this hook does not run owner controls.
  reportPath = process.env.MRN_GATEWAY_EXISTING_BINDING_REPORT;
  assert.ok(typeof reportPath === 'string' && resolve(reportPath) === reportPath);
  const context = parseExistingBindingContext(readFileSync(contextFile));
  const started = performance.now();
  deadline = setTimeout(() => {
    report.result = 'FAIL'; report.failure = { phase, recentCalls, deadlineExceeded: true }; save();
    process.stderr.write('FAIL disposable v2 binding deadline; private output withheld\n');
    process.exit(1);
  }, context.limits.hook_seconds * 1000);
  const lock = JSON.parse(readFileSync(new URL('./sources.json', import.meta.url)));
  const providerRoot = resolve(process.env.MRN_GATEWAY_PROVIDER_SOURCE), transportRoot = resolve(process.env.MRN_GATEWAY_MCP_SOURCE);
  for (const [name, root] of [['provider', providerRoot], ['transport', transportRoot]])
    assert.equal(execFileSync('git', ['-C', root, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock[name].commit);
  assert.equal(context.pins.provider_source_sha, lock.provider.commit);
  // Verify the full installed package and each immutable Git blob independently
  // before trusting any profile or source pin exported by the provider fixture.
  const packageRoot = join(directory, 'provider-source/mrn-mainwp-operations-api');
  const manifestBytes = readFileSync(join(packageRoot, 'release-source.json')); assert.ok(manifestBytes.length <= 262144);
  const manifest = JSON.parse(manifestBytes);
  assert.deepEqual(Object.keys(manifest).sort(), ['files', 'schema', 'source_commit', 'version']);
  assert.equal(manifest.schema, 1); assert.equal(manifest.source_commit, lock.provider.commit);
  assert.equal(manifestBytes.toString(), delegationCanonical(manifest) + '\n');
  assert.equal(context.pins.installed_manifest_sha256, digest(manifestBytes));
  assert.equal(context.pins.source_binding_sha256, delegationDigest(manifest));
  assert.ok(Object.keys(manifest.files).length > 0 && Object.keys(manifest.files).length <= 1000);
  let sourceBytes = 0;
  for (const [path, hash] of Object.entries(manifest.files)) {
    assert.match(path, /^[A-Za-z0-9_.-]+(?:\/[A-Za-z0-9_.-]+)*$/);
    assert.ok(!path.split('/').some(part => part === '.' || part === '..')); assert.notEqual(path, 'release-source.json');
    assert.match(hash, /^[a-f0-9]{64}$/);
    const bytes = readFileSync(join(packageRoot, path)); sourceBytes += bytes.length;
    assert.ok(bytes.length <= 16777216 && sourceBytes <= 33554432); assert.equal(digest(bytes), hash);
    assert.equal(digest(execFileSync('git', ['-C', providerRoot, 'show', `${lock.provider.commit}:${path}`], { maxBuffer: 16777216 })), hash);
  }
  for (const file of existingBindingProfileFiles) {
    if (file !== 'release-source.json') assert.ok(Object.hasOwn(manifest.files, file));
    assert.equal(context.profile_source[file], digest(readFileSync(join(packageRoot, file))));
  }
  report.source = lock; report.pins = context.pins;
  pass('Separate synthetic v2 descriptor and installed immutable source independently verified');

  const work = join(directory, 'actual-gateway-existing-binding-v2'); mkdirSync(work, { mode: 0o700 });
  const home = join(work, 'home'); mkdirSync(home, { mode: 0o700 });
  for (const file of ['fixture-network.mjs', 'existing-binding-network.mjs']) copyFileSync(new URL(file, import.meta.url), join(work, file));
  copyFileSync(certificate, join(work, 'tls.crt'));
  writeFileSync(join(work, 'gateway-network.json'), JSON.stringify({
    address: readFileSync(join(directory, 'existing-binding-v2/tls-ip'), 'utf8').trim(), certificate: join(work, 'tls.crt'),
  }));
  const credentials = JSON.parse(readFileSync(join(directory, 'credentials.json')));
  const config = { command: process.execPath, args: ['--import', join(work, 'existing-binding-network.mjs'), join(transportRoot, 'dist/index.js')], cwd: home,
    envRefs: Object.fromEntries(['MAINWP_URL', 'MAINWP_USER', 'MAINWP_APP_PASSWORD', 'MAINWP_SAFE_MODE', 'HOME'].map(k => [k, { env: `FIXTURE_${k}` }])) };
  const environment = { PATH: process.env.PATH, FIXTURE_MAINWP_URL: 'https://wpcontrol.mrndev.io', FIXTURE_MAINWP_USER: credentials.username,
    FIXTURE_MAINWP_APP_PASSWORD: credentials.password, FIXTURE_MAINWP_SAFE_MODE: 'false', FIXTURE_HOME: home };
  const signer = new IdentitySigner({ keyId: 'disposable-identity-only', connectionId: 'con_RuntimeFixture',
    privateKey: createPrivateKey({ key: readFileSync(join(directory, 'identity.der')), type: 'pkcs8', format: 'der' }) });
  const { registry } = accessRegistry(context.site.site_url);
  const rawClient = async () => {
    const raw = await connectMainwp(config, environment); clients.add(raw);
    const observe = row => { recentCalls.push(row); if (recentCalls.length > 8) recentCalls.shift(); };
    return { readResource: (...args) => raw.readResource(...args), listTools: (...args) => raw.listTools(...args),
      close: () => raw.close(), callTool: async (...args) => {
        const request = args[0], confirmed = request?.arguments?.user_confirmed === true;
        try {
          const result = await raw.callTool(...args);
          observe(bindingFixtureDiagnostic(request?.name, result, confirmed)); return result;
        } catch (error) {
          observe(bindingFixtureDiagnostic(request?.name, error, confirmed, true)); throw error;
        }
      } };
  };
  const verifyStaff = async actor => {
    const client = new DelegatedMainwpClient(await rawClient(), actor, signer);
    try { const access = await client.verifyStaff(); assert.equal(access.policy_sha256, context.pins.delegation_policy_sha256); }
    finally { await client.close(); }
  };
  const pins = { provider_source_sha: context.pins.provider_source_sha, authorization_policy_sha256: context.pins.authorization_policy_sha256,
    delegation_policy_sha256: context.pins.delegation_policy_sha256 };
  const openLedger = () => {
    store = new Store(join(work, 'original-binding.sqlite'));
    return new OriginalManagementAuthorizationLedger(store, { signer, registry, policy: () => pins, verifyStaff });
  };
  let ledger = openLedger();
  const audience = 'https://operations.mrnwebdesigns.com/mcp', issuer = 'https://oauth.example.invalid/';
  const keys = await generateKeyPair('RS256');
  const keySet = createLocalJWKSet({ keys: [{ ...await exportJWK(keys.publicKey), alg: 'RS256', kid: 'disposable-existing-binding' }] });
  const authenticate = createAuthenticator({ issuer, audience, verifiedEmailClaims: true,
    googleWorkspace: { connectionId: 'con_RuntimeFixture' } }, keySet, verifyStaff);
  const login = async () => {
    const bearer = await new SignJWT({ scope: 'mrn:operations mrn:operations:write', [`${audience}/email`]: context.actor.verifiedEmail,
      [`${audience}/email_verified`]: true, [`${audience}/identity_provider`]: 'google-oauth2', [`${audience}/google_sub`]: '222222',
      [`${audience}/google_connection`]: 'con_RuntimeFixture', [`${audience}/hosted_domain`]: 'mrnwebdesigns.com' })
      .setProtectedHeader({ alg: 'RS256', kid: 'disposable-existing-binding' }).setIssuer(issuer).setAudience(audience)
      .setSubject(context.actor.subject).setIssuedAt().setJti(randomUUID())
      .setExpirationTime(context.actor.expiresAt / 1000).sign(keys.privateKey);
    const actor = await authenticate(`Bearer ${bearer}`);
    assert.equal(actor.googleSubject, context.actor.googleSubject); assert.equal(actor.expiresAt, context.actor.expiresAt);
    return actor;
  };
  phase = 'fresh-target';
  const actor = await login(), originalAnchor = originalAuthorizationAnchor(actor);
  const client = new DelegatedMainwpClient(await rawClient(), actor, signer);
  const target = registry.resolve(actor, context.site.site_url, undefined, 'content');
  const session = new MainwpSession(client, registry, store, actor, target, { action: 'content' });
  const site = await session.fresh(); assert.equal(site.id, context.site.site_id); assert.equal(site.url, context.site.site_url);
  const recordArgs = { ...context.site, post_type: context.target.post_type, post_id: context.target.post_id };
  const read = validateManagementRead(await session.call(toolNames.contentRecord, recordArgs), { site, target: context.target });
  assert.equal(read.snapshot.core.post_status, 'publish');
  const profile = existingBindingProfile(read, context);
  const request = { ...recordArgs, expected_snapshot_sha256: read.snapshot_sha256,
    changes: { post_excerpt: `Disposable original binding candidate ${randomUUID()}` }, idempotency_key: randomUUID(),
    reason: 'Isolated original authorization bind and recovery; no Child execution' };
  assert.notEqual(request.changes.post_excerpt, read.snapshot.core.post_excerpt);

  // Fixture-only interruption after the actual provider returns a valid success,
  // before the production adapter brands it or any local receipt is retained.
  // Recovery must use a fresh signed read; neither transport nor ledger retries.
  let interceptedBindingHash, bindDispatches = 0;
  const openTransport = async interrupt => {
    const raw = await rawClient();
    const adapter = { readResource: (...args) => raw.readResource(...args), listTools: (...args) => raw.listTools(...args),
      close: () => raw.close(), callTool: async (...args) => {
        if (args[0].name !== 'mrn_mainwp__bind_content_edit_authorization_v2') return raw.callTool(...args);
        bindDispatches++; assert.equal(bindDispatches, 1);
        const result = await raw.callTool(...args);
        if (interrupt) {
          interceptedBindingHash = delegationDigest(parseOriginalBindingProjection(payload(result)));
          throw new Error('DISPOSABLE_POST_SUCCESS_BIND_INTERRUPTION');
        }
        return result;
      } };
    const runtime = await createOriginalAuthorizationTransport({ enabled: true, connect: async () => adapter,
      policy: () => ({ provider_source_sha: pins.provider_source_sha, authorization_policy_sha256: pins.authorization_policy_sha256 }) });
    transports.add(runtime); return runtime;
  };
  let transport = await openTransport(true);
  const ready = async () => {
    const result = await transport.status(); assert.equal(result.binding_readiness, 'ready');
    assert.equal(result.coherence_epoch_sha256, context.pins.coherence_epoch_sha256);
    assert.equal(result.production_qualified, false); assert.equal(result.consumer_binding_issued, false); return result;
  };
  phase = 'original-readiness';
  const previewReady = await ready();
  phase = 'original-preview-dispatch';
  const { originalRequestId, review } = await client.previewOriginalManagementEdit(request,
    { ledger, attemptId: randomUUID(), readiness: previewReady });
  phase = 'original-migration-read';
  const migration = await client.readManagementMigration({ ...context.site, operation_id: review.operation_id });
  phase = 'original-candidate-validation';
  const evidence = validateManagementEvidence({ read, review, migration }, { site, target: context.target, request, profile });
  phase = 'original-review-retention';
  const reviewed = await ledger.recordReview(originalRequestId, actor, request, evidence, profile);
  assert.deepEqual(reviewed.mapping.authorization, originalAnchor);
  assert.equal(reviewed.mapping.readiness.coherenceEpochSha256, context.pins.coherence_epoch_sha256);
  pass('Actual signed preview and complete candidate reconstruction retain the original verified actor and epoch');

  phase = 'interrupted-bind';
  const bindingReady = await ready(), bindHandle = await ledger.prepareBind(originalRequestId, actor, bindingReady);
  const bindInput = await ledger.claimControlDispatch(bindHandle, actor);
  await assert.rejects(() => transport.bind(bindInput, bindingReady), { message: 'DISPOSABLE_POST_SUCCESS_BIND_INTERRUPTION' });
  assert.match(interceptedBindingHash, /^[a-f0-9]{64}$/); assert.equal(bindDispatches, 1);
  assert.equal(store.db.prepare('SELECT count(*) n FROM original_management_bindings').get().n, 0);
  assert.equal(store.db.prepare('SELECT state FROM original_management_controls WHERE id=?').get(bindHandle.controlId).state, 'dispatched');
  await assert.rejects(() => ledger.prepareBind(originalRequestId, actor, bindingReady));
  await client.close(); await transport.close(); store.close(); store = undefined;
  pass('One actual provider bind succeeds before a simulated lost response; no local receipt or retry is created');

  phase = 'durable-recovery';
  ledger = openLedger(); const nextActor = await login();
  assert.notEqual(originalAuthorizationAnchor(nextActor).authorizationSha256, originalAnchor.authorizationSha256);
  assert.deepEqual((await ledger.readCurrent(originalRequestId, nextActor)).mapping.authorization, originalAnchor);
  transport = await openTransport(false);
  await assert.rejects(async () => ledger.prepareBind(originalRequestId, nextActor, await ready()));
  const readReady = await ready(), readHandle = await ledger.prepareRead(originalRequestId, nextActor, readReady);
  const readInput = await ledger.claimControlDispatch(readHandle, nextActor);
  const projection = await transport.read(readInput, readReady);
  assert.equal(delegationDigest(projection), interceptedBindingHash);
  assert.equal(projection.original_delegation_request_id, originalRequestId);
  assert.equal(projection.original_resource_authorization_sha256, originalAnchor.authorizationSha256);
  assert.equal(projection.original_resource_authorization_expires_at, originalAnchor.expiresAt / 1000);
  const binding = await ledger.recordProviderBinding(readHandle.controlId, nextActor, projection);
  assert.deepEqual(await ledger.readProviderBinding(originalRequestId, nextActor), binding);
  assert.equal(binding.coherenceEpochSha256, context.pins.coherence_epoch_sha256);
  store.close(); store = undefined; ledger = openLedger();
  assert.deepEqual(await ledger.readProviderBinding(originalRequestId, nextActor), binding);
  assert.equal(bindDispatches, 1);
  assert.equal(store.db.prepare('SELECT count(*) n FROM original_management_authorizations').get().n, 1);
  assert.equal(store.db.prepare("SELECT count(*) n FROM original_management_controls WHERE kind='bind'").get().n, 1);
  assert.equal(store.db.prepare("SELECT count(*) n FROM original_management_controls WHERE kind='read' AND state='observed'").get().n, 1);
  report.binding = { original_request_id: originalRequestId, operation_id: review.operation_id, reviewed_digest: review.digest,
    original_authorization_sha256: originalAnchor.authorizationSha256, original_expires_at: originalAnchor.expiresAt / 1000,
    projection_sha256: interceptedBindingHash, receipt_sha256: binding.digest, coherence_epoch_sha256: binding.coherenceEpochSha256,
    bind_dispatches: bindDispatches, read_dispatches: 1 };
  pass('SQLite reopen and a fresh authenticated login recover the exact binding by signed read without extending the original grant');

  phase = 'unchanged-child';
  const freshClient = new DelegatedMainwpClient(await rawClient(), nextActor, signer);
  const freshSession = new MainwpSession(freshClient, registry, store, nextActor,
    registry.resolve(nextActor, context.site.site_url, undefined, 'content'), { action: 'content' });
  const freshSite = await freshSession.fresh(); assert.equal(freshSite.id, site.id); assert.equal(freshSite.url, site.url);
  const after = validateManagementRead(await freshSession.call(toolNames.contentRecord, recordArgs), { site: freshSite, target: context.target });
  assert.equal(after.snapshot_sha256, read.snapshot_sha256); assert.deepEqual(after.snapshot, read.snapshot);
  const mappings = store.db.prepare('SELECT body FROM original_management_authorizations').all().map(row => JSON.parse(row.body));
  assert.ok(mappings.every(m => m.actor.googleSubject === '222222' && m.actor.verifiedEmail === 'bob@mrnwebdesigns.com'));
  const audit = store.db.prepare('SELECT subject,event,outcome,identity FROM audit WHERE operation=? ORDER BY seq').all(originalRequestId);
  assert.deepEqual(audit.map(row => [row.event, row.outcome]), [
    ['original-authorization-retained', 'prepared'], ['original-preview-dispatch', 'claimed'],
    ['original-preview-retained', 'reviewed'], ['original-authorization-bind', 'prepared'],
    ['original-authorization-bind', 'dispatched'], ['original-authorization-read', 'prepared'],
    ['original-authorization-read', 'dispatched'], ['original-provider-binding', 'observed'],
  ]);
  for (const row of audit) {
    assert.equal(row.subject, 'google-oauth2|222222');
    assert.deepEqual(JSON.parse(row.identity), { googleSubject: '222222', email: 'bob@mrnwebdesigns.com', provider: 'google-oauth2' });
  }
  const network = readFileSync(join(work, 'gateway-network.jsonl'), 'utf8').trim().split('\n').map(line => JSON.parse(line));
  const controls = network.filter(row => /\/(?:bind|read)-content-edit-authorization-v2\/run$/.test(row.path));
  assert.equal(controls.length, 2); assert.ok(controls.every(row => row.method === 'POST' && row.status === 200));
  report.child = { site: context.site, target: context.target, before_sha256: read.snapshot_sha256, after_sha256: after.snapshot_sha256 };
  report.network = { signed_control_posts: controls.length, network_log_sha256: digest(readFileSync(join(work, 'gateway-network.jsonl'))) };
  pass('Fresh exact-site sync and full Child readback prove the preview and binding did not edit content');
  report.result = 'PASS'; report.elapsedMs = Math.ceil(performance.now() - started); save();
} catch (error) {
  report.result = 'FAIL'; report.failure = { phase, recentCalls,
    code: typeof error?.code === 'string' && /^[A-Z0-9_]{1,80}$/.test(error.code) ? error.code : 'DISPOSABLE_BINDING_CHECK_FAILED' };
  save(); process.stderr.write(`FAIL disposable v2 binding at ${phase}; private output withheld\n`); process.exitCode = 1;
} finally {
  await Promise.allSettled([...transports].map(runtime => runtime.close()));
  await Promise.allSettled([...clients].map(client => client.close()));
  store?.close();
  clearTimeout(deadline);
}
