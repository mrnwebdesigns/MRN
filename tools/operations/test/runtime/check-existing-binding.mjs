// Separate native v2 fixture: real TLS/MCP/provider SQL, synthetic Google and
// Directory. No content execution, QA admission, production or team acceptance.
import assert from 'node:assert/strict';
import { createPrivateKey, randomUUID } from 'node:crypto';
import { copyFileSync, lstatSync, mkdirSync, readFileSync, realpathSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { execFile, execFileSync } from 'node:child_process';
import { promisify } from 'node:util';
import { createLocalJWKSet, exportJWK, generateKeyPair, SignJWT } from 'jose';
import { createAuthenticator, originalAuthorizationAnchor } from '../../src/auth.mjs';
import { digest } from '../../src/contracts.mjs';
import { IdentitySigner, delegationCanonical, delegationDigest } from '../../src/delegation.mjs';
import { DelegatedMainwpClient, delegationTools } from '../../src/delegated-mainwp.mjs';
import { validateManagementRead, validateManagementEvidence } from '../../src/content-management-contract.mjs';
import { connectMainwp, MainwpSession, payload, toolNames } from '../../src/mainwp.mjs';
import { OriginalManagementAuthorizationLedger, originalQualificationAbility } from '../../src/original-management-authorization.mjs';
import { createOriginalAuthorizationTransport, parseOriginalBindingProjection } from '../../src/original-authorization-transport.mjs';
import { Store } from '../../src/store.mjs';
import { accessRegistry } from '../fixtures/access-registry.mjs';
import { existingBindingFixtureLimits, existingBindingProfile, existingBindingProfileFiles,
  parseExistingBindingContext } from './existing-binding-context.mjs';
import { bindingFixtureDiagnostic, bindingFixtureNetworkDiagnostic } from './existing-binding-diagnostics.mjs';
import { parseExistingBindingAccess } from './existing-binding-access.mjs';
import { createExistingManagementAccessRuntime } from '../../src/content-access-runtime.mjs';
import { createExistingManagementContextRuntime, existingManagementContextTool } from '../../src/existing-management-context.mjs';
import { createExistingManagementCaptureRuntime, existingManagementCaptureTools,
  validateExistingManagementCapture } from '../../src/existing-management-capture.mjs';
import { ExistingManagementMeasurements, ExistingManagementMeasurementsReader,
  existingManagementMeasurementsTool } from '../../src/existing-management-measurements.mjs';
import { createHttpServer } from '../../src/http.mjs';

process.umask(0o077);
const clients = new Set(), transports = new Set();
const recentCalls = [];
let store, captureStore, deadline, hardDeadline, reportPath, sidecar, callbackServer, accessRuntime, routingTouched = false, closingAccess, started;
let checksComplete = false;
let networkPath, captureStarted;
const report = { schema: 2, result: 'INCOMPLETE', scope: 'disposable-native-existing-queue-access-v2-only', passed: [],
  productionCredentials: false, productionActivation: false, liveGoogleVerified: false, protectedWorkerVerified: false,
  renderedQaVerified: false, consumerBindingIssued: false, contentWriteVerified: false, codeWriteVerified: false,
  remoteBackupVerified: false, chatgptAcceptanceVerified: false, afterTransportExpiryVerified: false,
  fixtureLimits: existingBindingFixtureLimits, aggregateDeadlineExceeded: false };
const save = () => {
  if (report.captureCall?.kind === 'pending') report.captureCall.elapsed_ms = Math.ceil(performance.now() - captureStarted);
  if (reportPath) writeFileSync(reportPath, JSON.stringify(report, null, 2) + '\n', { mode: 0o600 });
};
const networkDiagnostic = () => {
  try {
    if (!networkPath) return bindingFixtureNetworkDiagnostic();
    return bindingFixtureNetworkDiagnostic(privateFile(networkPath, 1048576).toString('utf8'));
  } catch { return bindingFixtureNetworkDiagnostic(); }
};
const pass = label => { report.passed.push(label); save(); process.stdout.write(`PASS ${label}\n`); };
const switchCallback = async mode => {
  assert.ok(sidecar && ['node', 'disabled'].includes(mode));
  try { await promisify(execFile)(sidecar.route_switch, [mode], { timeout: 5000, maxBuffer: 4096 }); }
  catch { throw new Error('Disposable callback routing failed; private output withheld'); }
};
const closeAccess = () => {
  if (!closingAccess) closingAccess = (async () => {
    let failed = false;
    if (routingTouched) {
      try { await switchCallback('disabled'); report.callbackRouteDisabled = true; }
      catch { failed = true; }
    }
    if (callbackServer?.listening) {
      callbackServer.closeAllConnections();
      await new Promise(resolveClose => callbackServer.close(resolveClose));
    }
    await accessRuntime?.close();
    if (failed) throw new Error('Disposable callback route cleanup failed');
  })();
  return closingAccess;
};
const privateFile = (path, maximum) => {
  const info = lstatSync(path);
  assert.ok(info.isFile() && !info.isSymbolicLink() && (info.mode & 0o777) === 0o600 && info.size <= maximum);
  return readFileSync(path);
};
let phase = 'fixture-validation';
try {
  assert.equal(process.env.GITHUB_ACTIONS, 'true');
  assert.equal(process.env.MRN_DISPOSABLE_EXISTING_BINDING_TEST, '1');
  const [directory, certificate, runtimeCli, contextFile] = process.argv.slice(2);
  assert.equal(process.argv.length, 6); assert.equal(resolve(directory), directory);
  assert.equal(realpathSync(directory), directory);
  assert.equal(certificate, join(directory, 'tls.crt'));
  assert.equal(runtimeCli, join(directory, 'existing-binding-v2/wp-fixture-cli'));
  assert.equal(contextFile, join(directory, 'existing-binding-v2/context.json'));
  assert.ok(readFileSync(runtimeCli).length > 0); // Provider owns the barrier; this hook does not run owner controls.
  reportPath = process.env.MRN_GATEWAY_EXISTING_BINDING_REPORT;
  assert.ok(typeof reportPath === 'string' && resolve(reportPath) === reportPath);
  const context = parseExistingBindingContext(readFileSync(contextFile));
  sidecar = parseExistingBindingAccess(privateFile(join(directory, 'existing-binding-v2/access.json'), 4096), directory);
  const routeInfo = lstatSync(sidecar.route_switch);
  assert.ok(routeInfo.isFile() && !routeInfo.isSymbolicLink() && (routeInfo.mode & 0o777) === 0o700);
  const registrarBytes = privateFile(sidecar.registrar_signer, 48);
  assert.equal(registrarBytes.length, 48); assert.equal(registrarBytes.subarray(0, 16).toString('hex'), '302e020100300506032b657004220420');
  const registrar = { keyId: sidecar.registrar_key_id, privateKey: createPrivateKey({ key: registrarBytes, type: 'pkcs8', format: 'der' }) };
  registrarBytes.fill(0);
  started = performance.now();
  // Reserve cleanup inside the separately reviewed aggregate test allocation.
  // Per-operation and already-issued original authority limits remain fixed.
  // The hard deadline still exits even if a subprocess or close becomes stuck.
  deadline = setTimeout(async () => {
    report.aggregateDeadlineExceeded = true;
    report.result = 'FAIL'; report.failure = { phase, recentCalls, network: networkDiagnostic(), deadlineExceeded: true }; save();
    process.stderr.write('FAIL disposable v2 binding deadline; private output withheld\n');
    try { await closeAccess(); }
    finally { report.elapsedMs = Math.ceil(performance.now() - started); save(); process.exit(1); }
  }, (context.limits.hook_seconds - 10) * 1000);
  hardDeadline = setTimeout(() => process.exit(1), context.limits.hook_seconds * 1000);
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
  networkPath = join(work, 'gateway-network.jsonl');
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
  const queueCalls = [];
  const networkRows = () => readFileSync(join(work, 'gateway-network.jsonl'), 'utf8').trim().split('\n').map(line => JSON.parse(line));
  const queuePostCount = () => networkRows().filter(row => row.ability === originalQualificationAbility).length;
  const rawClient = async (captureConnection = false) => {
    assert.equal(typeof captureConnection, 'boolean');
    // The dedicated private capture connection must carry the existing 120s
    // contract. Its SDK/worker/original-intent limits remain independently
    // enforced; ordinary reads keep their existing transport configuration.
    const selected = captureConnection ? { ...config, envRefs: { ...config.envRefs,
      MAINWP_REQUEST_TIMEOUT: { env: 'FIXTURE_CAPTURE_TIMEOUT' }, MAINWP_RETRY_ENABLED: { env: 'FIXTURE_CAPTURE_RETRY' } } } : config;
    const raw = await connectMainwp(selected, captureConnection ? { ...environment,
      FIXTURE_CAPTURE_TIMEOUT: '120000', FIXTURE_CAPTURE_RETRY: 'false' } : environment); clients.add(raw);
    const observe = row => { recentCalls.push(row); if (recentCalls.length > 8) recentCalls.shift(); };
    return { readResource: (...args) => raw.readResource(...args), listTools: (...args) => raw.listTools(...args),
      close: () => raw.close(), callTool: async (...args) => {
        const request = args[0], confirmed = request?.arguments?.user_confirmed === true;
        const queueInput = request?.name === delegationTools.run && request?.arguments?.ability === originalQualificationAbility;
        let queueCall;
        if (queueInput) {
          const { ability, arguments_json, delegation } = request.arguments;
          queueCall = { confirmed, inputSha256: delegationDigest({ ability, arguments_json, delegation }), before: queuePostCount() };
          queueCalls.push(queueCall);
        }
        try {
          const result = await raw.callTool(...args);
          if (queueCall) {
            const { ability, arguments_json, delegation } = request.arguments;
            assert.equal(delegationDigest({ ability, arguments_json, delegation }), queueCall.inputSha256);
            queueCall.after = queuePostCount();
            if (!confirmed) assert.equal(payload(result).status, 'CONFIRMATION_REQUIRED');
          }
          observe(bindingFixtureDiagnostic(request?.name, result, confirmed)); return result;
        } catch (error) {
          observe(bindingFixtureDiagnostic(request?.name, error, confirmed, true)); throw error;
        }
      } };
  };
  // Reuse only the subprocess connection. Each invocation constructs a new
  // actor-bound adapter, rechecks transport metadata, obtains a new challenge
  // and performs a fresh signed staff read. No access result is cached. This
  // connection is separate from the capture and callback readers and is closed
  // once by the fixture lifecycle, including after a failed check.
  const staffConnection = await rawClient();
  report.staffChecks = { connections: 1, dispatched: 0, completed: 0 };
  const verifyStaff = async actor => {
    const client = new DelegatedMainwpClient(staffConnection, actor, signer);
    report.staffChecks.dispatched++;
    const access = await client.verifyStaff(); assert.equal(access.policy_sha256, context.pins.delegation_policy_sha256);
    report.staffChecks.completed++;
  };
  const pins = { provider_source_sha: context.pins.provider_source_sha, authorization_policy_sha256: context.pins.authorization_policy_sha256,
    delegation_policy_sha256: context.pins.delegation_policy_sha256 };
  const queueReadiness = async (actor, input) => {
    assert.equal(actor.googleSubject, context.actor.googleSubject);
    assert.equal(input.site_id, context.site.site_id); assert.equal(input.site_url, context.site.site_url);
    const current = await ready();
    // Explicit disposable catalog expectation from the pinned catalog-owner
    // fixture, not a production protected-worker/provenance claim.
    return { coherenceEpochSha256: current.coherence_epoch_sha256,
      pins: { provider_source_sha: pins.provider_source_sha, authorization_policy_sha256: pins.authorization_policy_sha256,
        installed_manifest_sha256: context.pins.installed_manifest_sha256, source_binding_sha256: context.pins.source_binding_sha256,
        job_intent_ttl_seconds: 600 } };
  };
  const openLedger = () => {
    store = new Store(join(work, 'original-binding.sqlite'));
    return new OriginalManagementAuthorizationLedger(store, { signer, registry, policy: () => pins, verifyStaff, queueReadiness });
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
  assert.equal(reviewed.review.schema, 2);
  assert.deepEqual(reviewed.review.qualification, {
    requestSha256: delegationDigest(migration.request), profileSha256: profile.sha256,
    runtimeContextSha256: profile.runtime_context_sha256,
    acfSchemaSha256: delegationDigest(read.snapshot.acf_definitions),
    beforePathsSha256: delegationDigest(read.affected_paths),
    managementContractSha256: digest(readFileSync(new URL('../../contracts/content-management-v1.json', import.meta.url))),
  });
  const reviewAnchorHash = digest(JSON.stringify(reviewed.review));
  assert.equal(store.db.prepare('SELECT review_digest FROM original_management_attempts WHERE request=?').get(originalRequestId).review_digest,
    reviewAnchorHash);
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
  const recoveredOriginal = await ledger.readCurrent(originalRequestId, nextActor);
  assert.deepEqual(recoveredOriginal.mapping.authorization, originalAnchor);
  assert.deepEqual(recoveredOriginal.review, reviewed.review);
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
  assert.deepEqual((await ledger.readCurrent(originalRequestId, nextActor)).review, reviewed.review);
  const retainedReview = store.db.prepare('SELECT body,digest FROM original_management_reviews WHERE request=?').get(originalRequestId);
  assert.equal(digest(retainedReview.body), reviewAnchorHash); assert.equal(retainedReview.digest, reviewAnchorHash);
  assert.equal(store.db.prepare('SELECT review_digest FROM original_management_attempts WHERE request=?').get(originalRequestId).review_digest,
    reviewAnchorHash);
  assert.equal(bindDispatches, 1);
  assert.equal(store.db.prepare('SELECT count(*) n FROM original_management_authorizations').get().n, 1);
  assert.equal(store.db.prepare("SELECT count(*) n FROM original_management_controls WHERE kind='bind'").get().n, 1);
  assert.equal(store.db.prepare("SELECT count(*) n FROM original_management_controls WHERE kind='read' AND state='observed'").get().n, 1);
  report.binding = { original_request_id: originalRequestId, operation_id: review.operation_id, reviewed_digest: review.digest,
    original_authorization_sha256: originalAnchor.authorizationSha256, original_expires_at: originalAnchor.expiresAt / 1000,
    projection_sha256: interceptedBindingHash, receipt_sha256: binding.digest, coherence_epoch_sha256: binding.coherenceEpochSha256,
    review_anchor_sha256: reviewAnchorHash, bind_dispatches: bindDispatches, read_dispatches: 1 };
  pass('SQLite reopen and a fresh authenticated login recover the exact binding by signed read without extending the original grant');

  phase = 'original-qualification-queue';
  const queueClient = new DelegatedMainwpClient(await rawClient(), nextActor, signer);
  const queued = await queueClient.queueOriginalManagementQualification({ ...context.site, operation_id: review.operation_id,
    reviewed_digest: review.digest }, { ledger, originalRequestId });
  const jobId = queued.binding.qualification_job_id;
  assert.equal(queued.binding.expires_at, Math.min(review.expires_at, originalAnchor.expiresAt / 1000, queued.binding.created_at + 600));
  assert.deepEqual(ledger.originalQueuedJob(jobId).queue.context, queued);
  await assert.rejects(() => queueClient.queueOriginalManagementQualification({ ...context.site, operation_id: review.operation_id,
    reviewed_digest: review.digest }, { ledger, originalRequestId }));
  assert.equal(store.db.prepare('SELECT count(*) n FROM original_management_queues').get().n, 1);
  assert.equal(store.db.prepare("SELECT count(*) n FROM original_management_queue_history WHERE state='dispatched'").get().n, 1);
  // The canonical MCP transport issues the first token locally: this wrapper
  // has no dry_run. Two identical MCP inputs must produce exactly one POST.
  const confirmation = { phases: queueCalls.map(c => c.confirmed), same_input: queueCalls.length === 2
    && queueCalls[0].inputSha256 === queueCalls[1].inputSha256,
  http_before: queueCalls.map(c => c.before), http_after: queueCalls.map(c => c.after) };
  report.queueConfirmation = confirmation; save();
  assert.deepEqual(confirmation, { phases: [false, true], same_input: true, http_before: [0, 0], http_after: [0, 1] });
  report.qualification = { job_id: jobId, context_sha256: queued.binding_sha256,
    original_expires_at: originalAnchor.expiresAt / 1000, job_expires_at: queued.binding.expires_at, dispatches: 1 };
  pass('Actual signed qualification queues the retained original once with unchanged grant and intent ceilings');

  phase = 'private-current-access-setup';
  accessRuntime = await createExistingManagementAccessRuntime({ enabled: true, ledger,
    identity: { keyId: signer.keyId, privateKey: signer.privateKey }, connect: rawClient });
  callbackServer = createHttpServer({ publicUrl: audience, existingManagementAccessVeto: accessRuntime.veto });
  await new Promise((resolveListen, reject) => { callbackServer.once('error', reject); callbackServer.listen(sidecar.callback_socket, resolveListen); });
  routingTouched = true; await switchCallback('node');
  let contextCalls = 0, revokeDuringContext = false;
  const contextClient = await rawClient();
  const contextRuntime = await createExistingManagementContextRuntime({ enabled: true, ledger, registrar, connect: async () => ({
    readResource: (...args) => contextClient.readResource(...args), listTools: (...args) => contextClient.listTools(...args),
    close: () => contextClient.close(), callTool: async (...args) => {
      assert.equal(args[0].name, existingManagementContextTool); contextCalls++;
      if (revokeDuringContext) ledger.recordQueuePendingRevocation(jobId);
      return contextClient.callTool(...args);
    },
  }) });
  transports.add(contextRuntime);
  phase = 'private-context-and-callback';
  assert.deepEqual(await contextRuntime.context.read(jobId), queued);
  const consumed = store.db.prepare('SELECT body FROM original_management_access_queries WHERE job=?').all(jobId).map(r => JSON.parse(r.body));
  assert.equal(consumed.length, 1); assert.equal(consumed[0].state, 'clear'); assert.equal(consumed[0].phase, 'read-capture');
  assert.equal(contextCalls, 1);
  pass('Actual registrar context request triggers the provider TLS callback and separate signed identity readback');

  phase = 'private-capture-interruption';
  const captureExpected = { evidence, request, profile };
  let captureDispatches = 0, recoveryReads = 0, interceptedCapture;
  const openCapture = async interrupt => {
    captureStore = new Store(join(work, 'original-binding.sqlite'));
    const captureLedger = new OriginalManagementAuthorizationLedger(captureStore,
      { signer, registry, policy: () => pins, verifyStaff, queueReadiness });
    const raw = await rawClient(true);
    const runtime = await createExistingManagementCaptureRuntime({ enabled: true, ledger: captureLedger, registrar,
      connect: async () => ({ readResource: (...args) => raw.readResource(...args), listTools: (...args) => raw.listTools(...args),
        close: () => raw.close(), callTool: async (...args) => {
          const name = args[0].name;
          assert.ok(Object.values(existingManagementCaptureTools).includes(name));
          if (name === existingManagementCaptureTools.capture) captureDispatches++; else recoveryReads++;
          const callStarted = performance.now(); captureStarted = callStarted;
          report.captureCall = { ...bindingFixtureDiagnostic(name, {}), kind: 'pending', elapsed_ms: 0,
            transport_timeout_ms: 120000, automatic_retries: false }; save();
          const record = (value, transportError = false) => {
            report.captureCall = { ...bindingFixtureDiagnostic(name, value, false, transportError),
              elapsed_ms: Math.ceil(performance.now() - callStarted), transport_timeout_ms: 120000, automatic_retries: false };
            save();
          };
          let result;
          try { result = await raw.callTool(...args); }
          catch (error) { record(error, true); throw error; }
          record(result);
          if (interrupt && name === existingManagementCaptureTools.capture) {
            const captured = validateExistingManagementCapture(payload(result), await captureLedger.currentQueuedJob(jobId), captureExpected);
            interceptedCapture = { artifact: captured.capture_artifact_sha256, measurements: delegationDigest(captured.measurements) };
            throw new Error('DISPOSABLE_POST_SUCCESS_CAPTURE_INTERRUPTION');
          }
          return result;
        } }) });
    transports.add(runtime); return { runtime, captureLedger };
  };
  const interrupted = await openCapture(true);
  await assert.rejects(() => interrupted.runtime.capture.capture(jobId, captureExpected), { message: 'DISPOSABLE_POST_SUCCESS_CAPTURE_INTERRUPTION' });
  assert.equal(captureDispatches, 1); assert.match(interceptedCapture.artifact, /^[a-f0-9]{64}$/);
  assert.deepEqual(await interrupted.captureLedger.readCapture(jobId), { state: 'uncertain', recoveryReads: 0, receipt: null });
  await interrupted.runtime.close(); captureStore.close(); captureStore = undefined;
  const resumed = await openCapture(false);
  await assert.rejects(() => resumed.runtime.capture.capture(jobId, captureExpected)); assert.equal(captureDispatches, 1);
  const recoveredCapture = await resumed.runtime.capture.recover(jobId, captureExpected);
  assert.equal(recoveredCapture.capture_artifact_sha256, interceptedCapture.artifact);
  assert.equal(delegationDigest(recoveredCapture.measurements), interceptedCapture.measurements);
  assert.deepEqual(recoveredCapture.capture_artifact.dataset.read, read);
  assert.equal((await resumed.captureLedger.readCapture(jobId)).state, 'captured');
  assert.equal(recoveryReads, 1);
  report.capture = { dispatches: captureDispatches, recovery_reads: recoveryReads, uncertain_before_recovery: true,
    artifact_sha256: interceptedCapture.artifact, measurements_sha256: interceptedCapture.measurements,
    binding_sha256: recoveredCapture.capture_artifact.binding_sha256, native_calls: recoveredCapture.measurements.native_calls,
    original_expires_at: originalAnchor.expiresAt / 1000, job_expires_at: queued.binding.expires_at };
  pass('One native capture survives a lost response and SQLite reopen with identical bounded read recovery and no recapture');

  phase = 'private-measurement-read';
  let measurementReads = 0;
  // Reuse only the registrar transport; each read signs fresh claims and the
  // provider repeats its original-requester callback and shared recovery budget.
  const measurementReader = new ExistingManagementMeasurementsReader({
    readResource: (...args) => contextClient.readResource(...args), listTools: (...args) => contextClient.listTools(...args),
    callTool: (...args) => {
      assert.equal(args[0].name, existingManagementMeasurementsTool); measurementReads++;
      return contextClient.callTool(...args);
    },
  });
  await measurementReader.initialize();
  const measurementRuntime = new ExistingManagementMeasurements({ ledger: resumed.captureLedger, reader: measurementReader, registrar });
  const measured = await measurementRuntime.read(jobId);
  assert.deepEqual(measured.measurements, recoveredCapture.measurements);
  assert.equal(measured.measurement_record_sha256, interceptedCapture.measurements);
  assert.equal(measured.binding_sha256, queued.binding_sha256);
  assert.equal(measurementReads, 1); assert.equal(captureDispatches, 1); assert.equal(recoveryReads, 1);
  report.measurements = { reads: measurementReads, measurement_record_sha256: measured.measurement_record_sha256,
    matches_retained_capture: true, original_authorization_unchanged: true, admitted: false };
  pass('Native measurement read matches the independently retained capture receipt under the original authority');

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
  assert.deepEqual(audit.filter(row => !['original-queue-access', 'original-qualification-capture'].includes(row.event)).map(row => [row.event, row.outcome]), [
    ['original-authorization-retained', 'prepared'], ['original-preview-dispatch', 'claimed'],
    ['original-preview-retained', 'reviewed'], ['original-authorization-bind', 'prepared'],
    ['original-authorization-bind', 'dispatched'], ['original-authorization-read', 'prepared'],
    ['original-authorization-read', 'dispatched'], ['original-provider-binding', 'observed'],
    ['original-qualification-queue', 'prepared'], ['original-qualification-queue', 'dispatched'], ['original-qualification-queue', 'observed'],
  ]);
  assert.deepEqual(audit.filter(row => row.event === 'original-qualification-capture').map(row => row.outcome),
    ['capture:dispatched', 'read-capture:dispatched', 'read-capture:observed']);
  for (const row of audit) {
    assert.equal(row.subject, 'google-oauth2|222222');
    assert.deepEqual(JSON.parse(row.identity), { googleSubject: '222222', email: 'bob@mrnwebdesigns.com', provider: 'google-oauth2' });
  }
  report.child = { site: context.site, target: context.target, before_sha256: read.snapshot_sha256, after_sha256: after.snapshot_sha256 };
  pass('Fresh exact-site sync and full Child readback prove the preview, binding and qualification did not edit content');

  phase = 'revocation-during-context';
  revokeDuringContext = true;
  await assert.rejects(() => contextRuntime.context.read(jobId));
  assert.equal(contextCalls, 2);
  const accessRows = store.db.prepare('SELECT body FROM original_management_access_queries WHERE job=? ORDER BY rowid').all(jobId).map(r => JSON.parse(r.body));
  assert.deepEqual(accessRows.at(-1) && [accessRows.at(-1).state, accessRows.at(-1).phase], ['pending-revocation', 'read-capture']);
  assert.ok(accessRows.slice(0, -1).every(r => r.state === 'clear' && ['capture', 'read-capture'].includes(r.phase)));
  assert.ok(accessRows.filter(r => r.phase === 'capture').length >= recoveredCapture.measurements.native_calls + 2);
  assert.ok(accessRows.every(r => r.actor.subject === context.actor.subject && r.actor.verifiedEmail === context.actor.verifiedEmail));
  assert.equal(store.db.prepare('SELECT access_count FROM original_management_attempts WHERE request=?').get(originalRequestId).access_count, accessRows.length);
  await assert.rejects(() => contextRuntime.context.read(jobId)); assert.equal(contextCalls, 2);
  await assert.rejects(() => resumed.runtime.capture.recover(jobId, captureExpected)); assert.equal(recoveryReads, 1);
  await assert.rejects(() => measurementRuntime.read(jobId)); assert.equal(measurementReads, 1);
  report.currentAccess = { context_requests: contextCalls, signed_callbacks: accessRows.length, clear: accessRows.length - 1, pending_revocation: 1,
    repeated_revoked_read_dispatched: false };
  pass('Revocation during actual provider context lookup returns a signed denial and blocks later local dispatch');
  phase = 'network-verification';
  const network = networkRows();
  const controls = network.filter(row => /\/(?:bind|read)-content-edit-authorization-v2\/run$/.test(row.path));
  const accessPosts = network.filter(row => /\/read-management-access-veto-query-existing-v2\/run$/.test(row.path));
  const contextPosts = network.filter(row => /\/get-content-edit-qualification-context-existing-v2\/run$/.test(row.path));
  const queuePosts = network.filter(row => row.ability === originalQualificationAbility);
  const capturePosts = network.filter(row => /\/capture-content-edit-qualification-existing-v2\/run$/.test(row.path));
  const recoveryPosts = network.filter(row => /\/read-content-edit-qualification-capture-existing-v2\/run$/.test(row.path));
  const measurementPosts = network.filter(row => /\/read-content-edit-qualification-measurements-existing-v2\/run$/.test(row.path));
  const staffPosts = network.filter(row => /\/get-team-access-v1\/run$/.test(row.path));
  assert.equal(report.staffChecks.dispatched, report.staffChecks.completed);
  assert.equal(staffPosts.length, report.staffChecks.dispatched);
  assert.ok(staffPosts.length > 0 && staffPosts.every(row => row.method === 'POST' && row.status === 200));
  report.staffChecks.verified_posts = staffPosts.length;
  report.network = { signed_control_posts: controls.length, context_posts: contextPosts.length, access_readback_posts: accessPosts.length,
    queue_posts: queuePosts.length, capture_posts: capturePosts.length, recovery_posts: recoveryPosts.length, measurement_posts: measurementPosts.length,
    network_log_sha256: digest(readFileSync(join(work, 'gateway-network.jsonl'))) }; save();
  assert.equal(controls.length, 2); assert.ok(controls.every(row => row.method === 'POST' && row.status === 200));
  assert.equal(accessPosts.length, accessRows.length); assert.ok(accessPosts.every(row => row.method === 'POST' && row.status === 200));
  assert.equal(contextPosts.length, 2); assert.ok(contextPosts.every(row => row.method === 'POST'));
  assert.equal(queuePosts.length, 1); assert.ok(queuePosts.every(row => row.method === 'POST' && row.status === 200));
  for (const posts of [capturePosts, recoveryPosts, measurementPosts]) {
    assert.equal(posts.length, 1); assert.ok(posts.every(row => row.method === 'POST' && row.status === 200));
  }
  checksComplete = true;
} catch (error) {
  report.result = 'FAIL'; report.failure = { phase, recentCalls, network: networkDiagnostic(),
    code: typeof error?.code === 'string' && /^[A-Z0-9_]{1,80}$/.test(error.code) ? error.code : 'DISPOSABLE_BINDING_CHECK_FAILED' };
  save(); process.stderr.write(`FAIL disposable v2 binding at ${phase}; private output withheld\n`); process.exitCode = 1;
} finally {
  try { await closeAccess(); }
  catch {
    report.result = 'FAIL'; report.failure = { phase: 'callback-route-cleanup', code: 'DISPOSABLE_CALLBACK_CLEANUP_FAILED' };
    process.exitCode = 1;
  }
  await Promise.allSettled([...transports].map(runtime => runtime.close()));
  await Promise.allSettled([...clients].map(client => client.close()));
  captureStore?.close(); store?.close();
  if (checksComplete && report.result !== 'FAIL') report.result = 'PASS';
  if (started !== undefined) report.elapsedMs = Math.ceil(performance.now() - started);
  save(); clearTimeout(deadline); clearTimeout(hardDeadline);
}
