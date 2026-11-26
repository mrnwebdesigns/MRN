// CI-only real gateway callback/MCP/provider/Child integration. The owner fixture
// retains all registrar authority; no production credentials or Google session.
import assert from 'node:assert/strict';
import { createHash, createPrivateKey, randomUUID } from 'node:crypto';
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { execFile, execFileSync } from 'node:child_process';
import { promisify } from 'node:util';
import { createLocalJWKSet, exportJWK, generateKeyPair, SignJWT } from 'jose';
import { createAuthenticator } from '../../src/auth.mjs';
import { IdentitySigner, delegationCanonical, delegationDigest } from '../../src/delegation.mjs';
import { DelegatedMainwpClient } from '../../src/delegated-mainwp.mjs';
import { contentSchemas, contentSnapshot } from '../../src/content-contract.mjs';
import { accessVetoPath } from '../../src/content-access.mjs';
import { createContentAccessRuntime } from '../../src/content-access-runtime.mjs';
import { accessRegistry } from '../fixtures/access-registry.mjs';
import { requesterDigest } from '../../src/content-access-store.mjs';
import { compileContentSchema } from '../../src/content-schema.mjs';
import { createHttpServer } from '../../src/http.mjs';
import { connectMainwp, payload } from '../../src/mainwp.mjs';
import { Store } from '../../src/store.mjs';

process.umask(0o077);
assert.equal(process.env.GITHUB_ACTIONS, 'true');
const [directory, certificate, runtimeCli, contextFile] = process.argv.slice(2);
assert.equal(process.argv.length, 6);
assert.equal(certificate, join(directory, 'tls.crt'));
assert.equal(runtimeCli, join(directory, 'wp-fixture-cli'));
assert.equal(contextFile, join(directory, 'access-integration/context.json'));
const context = JSON.parse(readFileSync(contextFile));
assert.equal(context.schema, 1); assert.equal(context.mode, 'disposable-access-integration');
assert.equal(context.production_credentials, false); assert.equal(context.production_activation, false);
assert.equal(context.actor.googleSubject, '222222'); assert.equal(context.actor.verifiedEmail, 'bob@mrnwebdesigns.com');
assert.equal(context.actor.googleConnection, 'con_RuntimeFixture');
assert.equal(context.site.site_url, 'https://child.fixture.example.invalid');
assert.equal(context.control.callback_path, accessVetoPath);
assert.equal(context.control.callback_socket, join(directory, 'access-socket/callback.sock'));
assert.equal(context.control.worker, join(directory, 'access-integration/worker'));
assert.equal(context.control.route_switch, join(directory, 'access-integration/switch-callback'));
assert.equal(context.control.acquire_measurements, join(directory, 'node-acquisition/acquire-measurements'));
assert.equal(context.control.acquisition_request_count, join(directory, 'node-acquisition/acquisition-request-count'));
assert.deepEqual(context.limits, { hook_seconds: 150, owner_wait_seconds: 170, read_wait_seconds: 15, child_delay_seconds: 6 });
const transportRoot = resolve(process.env.MRN_GATEWAY_MCP_SOURCE);
const providerRoot = resolve(process.env.MRN_GATEWAY_PROVIDER_SOURCE);
const runnerRoot = resolve(process.env.MRN_GATEWAY_RUNNER_SOURCE);
const lock = JSON.parse(readFileSync(new URL('./sources.json', import.meta.url)));
const recoveryContract = JSON.parse(readFileSync(new URL('../../contracts/content-capture-read-v1.json', import.meta.url)));
const recoverySchema = compileContentSchema(recoveryContract.ability.output, recoveryContract.definitions);
const measurementContract = JSON.parse(readFileSync(new URL('../fixtures/measurement-readback-candidate/schema.json', import.meta.url)));
const measurementSchema = compileContentSchema(measurementContract.ability.output, measurementContract.definitions);
const recordSchema = compileContentSchema({ $ref: '#/definitions/measurement_record' }, measurementContract.definitions);
const transcriptSchema = compileContentSchema({ $ref: '#/definitions/acquisition_transcript' }, measurementContract.definitions);
const bytesDigest = bytes => createHash('sha256').update(bytes).digest('hex');
assert.equal(execFileSync('git', ['-C', transportRoot, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock.transport.commit);
assert.equal(execFileSync('git', ['-C', providerRoot, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock.provider.commit);
assert.equal(execFileSync('git', ['-C', runnerRoot, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock.runner.commit);
const expectedSource = { provider_source_sha: lock.provider.commit,
  ...Object.fromEntries([['capture_template_sha256', 'child/content-capture.php'],
    ['mutation_template_sha256', 'child/content-posts.php'], ['parity_helper_sha256', 'child/content-parity.php']]
    .map(([field, path]) => [field, bytesDigest(execFileSync('git', ['-C', providerRoot, 'show', `${lock.provider.commit}:${path}`]))])) };
const work = join(directory, 'actual-gateway-access'); mkdirSync(work, { mode: 0o700 });
const home = join(work, 'home'); mkdirSync(home, { mode: 0o700 });
const preload = join(work, 'fixture-network.mjs');
copyFileSync(new URL('./fixture-network.mjs', import.meta.url), preload); copyFileSync(certificate, join(work, 'tls.crt'));
writeFileSync(join(work, 'gateway-network.json'), JSON.stringify({ address: readFileSync(join(directory, 'tls-ip'), 'utf8'), certificate: join(work, 'tls.crt') }));
const credentials = JSON.parse(readFileSync(join(directory, 'credentials.json')));
const config = { command: process.execPath, args: ['--import', preload, join(transportRoot, 'dist/index.js')], cwd: home,
  envRefs: Object.fromEntries(['MAINWP_URL', 'MAINWP_USER', 'MAINWP_APP_PASSWORD', 'MAINWP_SAFE_MODE', 'HOME'].map(k => [k, { env: `FIXTURE_${k}` }])) };
const environment = { PATH: process.env.PATH, FIXTURE_MAINWP_URL: 'https://wpcontrol.mrndev.io', FIXTURE_MAINWP_USER: credentials.username,
  FIXTURE_MAINWP_APP_PASSWORD: credentials.password, FIXTURE_MAINWP_SAFE_MODE: 'false', FIXTURE_HOME: home };
const identityKey = createPrivateKey({ key: readFileSync(join(directory, 'identity.der')), type: 'pkcs8', format: 'der' });
const identity = { keyId: 'disposable-identity-only', privateKey: identityKey };
const signer = new IdentitySigner({ ...identity, connectionId: 'con_RuntimeFixture' });
const store = new Store(join(work, 'access.sqlite'));
const { registry, registryData, policy } = accessRegistry(context.site.site_url);
const clients = [], processes = new Set();
const run = async (file, args, timeout = 90000, maxBuffer = 131072) => {
  // Buffer privately; never print subprocess credentials/claims on failure.
  const promise = promisify(execFile)(file, args, { encoding: 'utf8', timeout, maxBuffer });
  processes.add(promise.child);
  try { return (await promise).stdout; }
  catch { throw new Error('Disposable access worker failed; private output withheld.'); }
  finally { processes.delete(promise.child); }
};
const worker = async (mode, id) => {
  assert.ok(['context', 'capture', 'status', 'arm-delay', 'wait-read'].includes(mode));
  assert.match(id, /^[a-f0-9-]{36}$/);
  return JSON.parse(await run(context.control.worker, [mode, id]));
};
const recover = async (id, expectedHash) => {
  assert.match(id, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
  assert.match(expectedHash, /^[a-f0-9]{64}$/);
  // The private fixture signs one new read; it never retries capture or exports
  // its key/assertion. Only this full-artifact response gets the larger bound.
  return JSON.parse(await run(context.control.worker, ['read-capture', id, expectedHash], 90000, 4500000));
};
const measurements = async (id, expectedHash) => {
  assert.match(id, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
  assert.match(expectedHash, /^[a-f0-9]{64}$/);
  // The existing private fixture owns signing and exact HTTP transport. Its
  // small closed wrapper is the only addition to the response byte ceiling.
  const raw = await run(context.control.worker, ['read-measurements', id, expectedHash], 90000, 100000);
  assert.ok(Buffer.byteLength(raw) <= measurementContract.limits.read_response_utf8_bytes + 64);
  return JSON.parse(raw);
};
const acquisitionChecks = [];
const acquisitionRequestCount = async () => {
  const value = JSON.parse(await run(context.control.acquisition_request_count, [], 10000, 1024));
  assert.deepEqual(Object.keys(value).sort(), ['requests', 'schema']); assert.equal(value.schema, 1);
  assert.ok(Number.isSafeInteger(value.requests) && value.requests >= 0 && value.requests <= 4096);
  return value.requests;
};
const acquireMeasurements = async (id, expectedHash, expectedState = 'clear') => {
  assert.match(id, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
  assert.match(expectedHash, /^[a-f0-9]{64}$/);
  const previous = new Set(store.db.prepare('SELECT id FROM content_access_queries WHERE job=?').all(id).map(row => row.id));
  const beforeRequests = await acquisitionRequestCount();
  // The fixed fixture issuer keeps both signing keys private. A separate
  // pinned Node container receives only its one-use signed handoff, public
  // bootstrap/source inputs and central test credentials. Its real TLS route
  // uses internal DNS; no gateway network interception is imported there.
  const raw = await run(context.control.acquire_measurements, [id, expectedHash], 90000, 98304);
  assert.equal(await acquisitionRequestCount() - beforeRequests, 3);
  assert.ok(Buffer.byteLength(raw) <= 98304);
  const value = JSON.parse(raw), clear = expectedState === 'clear';
  assert.deepEqual(Object.keys(value).sort(), clear
    ? ['capture_artifact_sha256', 'ok', 'output', 'qualification_job_id']
    : ['error_code', 'ok', 'qualification_job_id']);
  assert.equal(value.qualification_job_id, id); assert.equal(value.ok, clear);
  if (clear) assert.equal(value.capture_artifact_sha256, expectedHash);
  else assert.equal(value.error_code, 'fixture_node_acquisition_refused');
  const queries = store.db.prepare('SELECT * FROM content_access_queries WHERE job=?').all(id)
    .filter(row => !previous.has(row.id));
  assert.equal(queries.length, clear ? 2 : 1);
  assert.ok(queries.every(row => row.state === expectedState && row.revision === (expectedState === 'pending-revocation' ? 2 : 1)));
  acquisitionChecks.push({ state: expectedState, callbacks: queries.length, http_requests: 3, returned_measurements: Object.hasOwn(value, 'output') });
  return value.output;
};
const raw = async () => { const c = await connectMainwp(config, environment); clients.push(c); return c; };
const report = { schema: 1, source: lock, scope: 'disposable-native-access-only', passed: [], productionCredentials: false,
  liveGoogleVerified: false, protectedWorkerVerified: false, chatgptAcceptanceVerified: false, codeWriteVerified: false,
  contentWriteVerified: false, remoteBackupVerified: false };
const pass = label => { report.passed.push(label); process.stdout.write(`PASS ${label}\n`); };
let server, accessRuntime, routed = false;
try {
  accessRuntime = await createContentAccessRuntime({ enabled: true, store, registry, identity,
    connectionId: 'con_RuntimeFixture', connect: () => connectMainwp(config, environment) });
  const { ledger, veto } = accessRuntime;
  const audience = 'https://operations.mrnwebdesigns.com/mcp', issuer = 'https://oauth.example.invalid/';
  const keys = await generateKeyPair('RS256');
  const keySet = createLocalJWKSet({ keys: [{ ...await exportJWK(keys.publicKey), alg: 'RS256', kid: 'disposable-access' }] });
  const authConfig = { issuer, audience, verifiedEmailClaims: true, googleWorkspace: { connectionId: 'con_RuntimeFixture' } };
  const authenticate = createAuthenticator(authConfig, keySet, async actor => {
    const client = new DelegatedMainwpClient(await raw(), actor, signer);
    try { await client.verifyStaff(); } finally { await client.close(); }
  });
  const bearer = await new SignJWT({ scope: 'mrn:operations mrn:operations:write', [`${audience}/email`]: context.actor.verifiedEmail,
    [`${audience}/email_verified`]: true, [`${audience}/identity_provider`]: 'google-oauth2', [`${audience}/google_sub`]: '222222',
    [`${audience}/google_connection`]: 'con_RuntimeFixture', [`${audience}/hosted_domain`]: 'mrnwebdesigns.com' })
    .setProtectedHeader({ alg: 'RS256', kid: 'disposable-access' }).setIssuer(issuer).setAudience(audience)
    .setSubject('google-oauth2|222222').setIssuedAt().setExpirationTime(Math.floor(context.actor.expiresAt / 1000)).sign(keys.privateKey);
  const actor = await authenticate(`Bearer ${bearer}`);
  assert.equal(actor.googleSubject, '222222'); assert.equal(actor.verifiedEmail, 'bob@mrnwebdesigns.com');
  const client = new DelegatedMainwpClient(await raw(), actor, signer);
  server = createHttpServer({ publicUrl: audience, issuer, accessVeto: veto });
  await new Promise((resolve, reject) => { server.once('error', reject); server.listen(context.control.callback_socket, resolve); });
  await run(context.control.route_switch, ['node'], 10000); routed = true;
  const inspectArgs = { ...context.site, target: context.target };
  const inspected = contentSchemas['inspect-content-v1'].output.parse(payload(await client.callTool({ name: 'mrn_mainwp__inspect_content_v1', arguments: inspectArgs })));
  const snapshot = contentSnapshot(inspected.snapshot);
  assert.equal(snapshot.site_url, context.site.site_url); assert.equal(snapshot.site_id, context.site.site_id);
  assert.deepEqual(snapshot.target, context.target); assert.equal(inspected.snapshot_sha256, delegationDigest(snapshot));
  // The readiness adapter is deliberately fixture-owned. This proves the queue
  // and callback transport, not production protected worker/recipe admission.
  const admit = async (original, request) => {
    assert.equal(original.subject, actor.subject); assert.equal(request.site_id, context.site.site_id);
    assert.equal(request.site_url, context.site.site_url); assert.deepEqual(request.target, context.target);
    assert.equal(request.expected_snapshot_sha256, inspected.snapshot_sha256); return context.expected;
  };
  const queue = () => client.queueQualification({ ...inspectArgs, expected_snapshot_sha256: inspected.snapshot_sha256,
    reason: 'Actual disposable gateway access verification', idempotency_key: randomUUID() }, { ledger, admit });
  const first = await queue();
  const original = JSON.parse(store.db.prepare('SELECT body FROM content_access_jobs WHERE id=?').get(first.qualification_job_id).body);
  assert.equal(original.requester, requesterDigest(actor)); assert.equal(original.actor.subject, actor.subject);
  const ctx = await worker('context', first.qualification_job_id);
  assert.equal(ctx.ok, true); assert.equal(ctx.output.job_context.requester_binding_sha256, requesterDigest(actor));
  assert.equal(ctx.output.job_context_sha256, delegationDigest(ctx.output.job_context));
  pass('Actual delegated queue and protected context bind the verified original Google requester');
  const captured = await worker('capture', first.qualification_job_id);
  if (captured.ok !== true) {
    // Closed failure-only diagnostics: no actor, signed input, nonce, body or
    // artifact. A refusal must remain a failure, never a retry/alternate route.
    const queries = store.db.prepare('SELECT phase,state FROM content_access_audit WHERE job=? AND event=?')
      .all(first.qualification_job_id, 'veto-answered');
    process.stderr.write(`${JSON.stringify({ access_capture_failure: {
      provider_error_code: /^mrn_[a-z0-9_]{1,64}$/.test(captured.error_code) ? captured.error_code : 'unclassified',
      http_status: Number.isInteger(captured.http_status) && captured.http_status >= 0 && captured.http_status <= 599 ? captured.http_status : null,
      callback_answers: queries.length, clear_answers: queries.filter(row => row.state === 'clear').length,
      refused_answers: queries.filter(row => ['denied', 'pending-revocation'].includes(row.state)).length,
    } })}\n`);
  }
  assert.equal(captured.ok, true); assert.equal(captured.state, 'running'); assert.match(captured.capture_artifact_sha256, /^[a-f0-9]{64}$/);
  const saved = await worker('status', first.qualification_job_id);
  assert.equal(saved.ok, true); assert.equal(saved.capture_present, true); assert.equal(saved.capture_authenticated, true); assert.equal(saved.retained_state_matches, true);
  assert.equal(saved.capture_artifact_sha256, captured.capture_artifact_sha256);
  const answered = store.db.prepare('SELECT * FROM content_access_queries WHERE job=?').all(first.qualification_job_id);
  assert.ok(answered.length >= 3); assert.ok(answered.every(row => row.state === 'clear' && row.revision === 1));
  pass('Native capture passes real HTTPS callback and independently authenticated MCP query readback');
  const running = await worker('context', first.qualification_job_id);
  assert.equal(running.ok, true); assert.equal(running.output.job_context.state, 'running');
  assert.equal(running.output.job_context_sha256, delegationDigest(running.output.job_context));
  assert.equal(running.output.job_context.capture_artifact_sha256, captured.capture_artifact_sha256);
  const recovered = await recover(first.qualification_job_id, captured.capture_artifact_sha256);
  assert.deepEqual(Object.keys(recovered).sort(), ['ok', 'output']); assert.equal(recovered.ok, true);
  const recoveredOutput = recoverySchema.parse(recovered.output), artifact = recoveredOutput.capture_artifact;
  assert.equal(recoveredOutput.qualification_job_id, first.qualification_job_id);
  assert.equal(recoveredOutput.capture_artifact_sha256, captured.capture_artifact_sha256);
  assert.equal(delegationDigest(artifact), captured.capture_artifact_sha256);
  assert.ok(Buffer.byteLength(delegationCanonical(artifact)) <= 4400000);
  for (const field of ['schema', 'qualification_job_id', 'authorization_policy_sha256', 'catalog_epoch',
    'requester_binding_sha256', 'site_id', 'site_url', 'target', 'expected_snapshot_sha256',
    'expected_target_revision', 'recipe_catalog_sha256']) assert.deepEqual(artifact[field], running.output.job_context[field]);
  assert.equal(delegationDigest(artifact.snapshot), inspected.snapshot_sha256);
  assert.equal(artifact.recipe_sha256, context.expected.recipe_sha256);
  assert.deepEqual(await worker('status', first.qualification_job_id), saved);
  const recoveredQueries = store.db.prepare('SELECT * FROM content_access_queries WHERE job=?').all(first.qualification_job_id)
    .filter(row => !answered.some(previous => previous.id === row.id));
  assert.equal(recoveredQueries.length, 2);
  assert.ok(recoveredQueries.every(row => row.state === 'clear' && row.revision === 1));
  const readAudit = store.db.prepare("SELECT * FROM content_access_audit WHERE job=? AND phase='read-capture' AND event='veto-answered'")
    .all(first.qualification_job_id);
  assert.equal(readAudit.length, 2); assert.ok(readAudit.every(row => row.state === 'clear' && row.revision === 1));
  pass('Retained private capture recovery returns the original artifact through two actual Node access vetoes');
  const beforeMeasurement = store.db.prepare('SELECT id FROM content_access_queries WHERE job=?').all(first.qualification_job_id);
  const measured = await measurements(first.qualification_job_id, captured.capture_artifact_sha256);
  assert.deepEqual(Object.keys(measured).sort(), ['ok', 'output']); assert.equal(measured.ok, true);
  const measuredOutput = measurementSchema.parse(measured.output);
  assert.ok(Buffer.byteLength(delegationCanonical(measuredOutput)) <= measurementContract.limits.read_response_utf8_bytes);
  assert.ok(Buffer.byteLength(measuredOutput.measurement_record_json) <= measurementContract.limits.measurement_record_utf8_bytes);
  const record = recordSchema.parse(JSON.parse(measuredOutput.measurement_record_json));
  assert.equal(delegationCanonical(record), measuredOutput.measurement_record_json);
  assert.equal(delegationDigest(record), measuredOutput.measurement_record_sha256);
  assert.equal(delegationDigest(record.binding), measuredOutput.binding_sha256);
  assert.equal(record.binding_sha256, measuredOutput.binding_sha256);
  assert.equal(measuredOutput.qualification_job_id, first.qualification_job_id);
  assert.equal(measuredOutput.capture_artifact_sha256, captured.capture_artifact_sha256);
  for (const field of ['qualification_job_id', 'authorization_policy_sha256', 'catalog_epoch',
    'requester_binding_sha256', 'site_id', 'site_url', 'target', 'expected_snapshot_sha256',
    'expected_target_revision', 'recipe_catalog_sha256', 'recipe_id', 'recipe_sha256'])
    assert.deepEqual(record.binding[field], artifact[field], field);
  assert.equal(record.binding.capture_artifact_sha256, captured.capture_artifact_sha256);
  assert.equal(record.binding.job_expires_at, running.output.job_context.expires_at);
  assert.equal(record.binding.measurement_contract_sha256, bytesDigest(readFileSync(new URL('../fixtures/measurement-readback-candidate/schema.json', import.meta.url))));
  assert.deepEqual(record.binding.expected_source, expectedSource);
  for (const [field, value] of Object.entries(expectedSource)) assert.equal(record.source_integrity[field], value);
  assert.equal(record.capture_integrity.artifact_bytes, Buffer.byteLength(delegationCanonical(artifact)));
  assert.equal(record.capture_integrity.snapshot_sha256, inspected.snapshot_sha256);
  assert.equal(record.capture_integrity.capture_artifact_sha256, captured.capture_artifact_sha256);
  assert.equal(record.capture_integrity.native_output_truncations, 0);
  assert.equal(record.chunks.length, record.capture_integrity.chunks_received);
  assert.equal(record.chunks.length, record.decoder.accepted_chunks);
  assert.equal(record.chunks.length, record.decoder.total_chunks);
  assert.deepEqual(record.scope_ownership, { ownership: 'wordpress-post', post_status: snapshot.post_status,
    site_id: snapshot.site_id, site_url: snapshot.site_url, target: snapshot.target });
  const measuredQueries = store.db.prepare('SELECT * FROM content_access_queries WHERE job=?').all(first.qualification_job_id)
    .filter(row => !beforeMeasurement.some(previous => previous.id === row.id));
  assert.equal(measuredQueries.length, 2);
  assert.ok(measuredQueries.every(row => row.state === 'clear' && row.revision === 1));
  assert.deepEqual(await worker('status', first.qualification_job_id), saved);
  pass('Private measurement readback binds original capture and pinned source through two actual Node vetoes');
  const verifyAcquired = value => {
    assert.deepEqual(Object.keys(value).sort(), ['binding_sha256', 'group_sha256', 'groups', 'measurement_record_sha256', 'schema', 'state', 'transcript']);
    assert.equal(value.schema, 1); assert.equal(value.state, 'measured');
    assert.equal(value.binding_sha256, measuredOutput.binding_sha256);
    assert.equal(value.measurement_record_sha256, measuredOutput.measurement_record_sha256);
    const transcript = transcriptSchema.parse(value.transcript);
    assert.deepEqual(transcript.binding, record.binding);
    assert.equal(transcript.binding_sha256, measuredOutput.binding_sha256);
    assert.equal(transcript.measurement_record_sha256, measuredOutput.measurement_record_sha256);
    assert.ok(Buffer.byteLength(delegationCanonical(transcript)) <= measurementContract.limits.acquisition_transcript_utf8_bytes);
    const roles = ['anonymous_probe', 'wrong_purpose_probe', 'authorized_read'];
    const observations = roles.map(role => transcript.observations[role]);
    for (const [index, observation] of observations.entries()) {
      assert.equal(observation.sequence, index + 1);
      assert.equal(observation.correlation_id, transcript.correlation_id);
      assert.equal(observation.payload_sha256, observations[0].payload_sha256);
      assert.ok(observation.started_at_ms <= observation.completed_at_ms);
      assert.ok(observation.completed_at_ms < record.binding.job_expires_at * 1000);
      assert.ok(observation.completed_at_ms - observation.started_at_ms <= measurementContract.limits.http_timeout_ms);
      if (index) assert.ok(observation.started_at_ms > observations[index - 1].completed_at_ms);
    }
    assert.equal(observations[0].request_body_sha256, observations[2].request_body_sha256);
    assert.notEqual(observations[1].request_body_sha256, observations[2].request_body_sha256);
    const groups = ['capture_integrity', 'scope_ownership', 'source_integrity', 'wordpress_api'];
    assert.deepEqual(Object.keys(value.groups).sort(), groups);
    assert.deepEqual(Object.keys(value.group_sha256).sort(), groups);
    for (const name of groups.slice(0, 3)) assert.deepEqual(value.groups[name], record[name]);
    assert.deepEqual(value.groups.wordpress_api, { inspected_http_status: 200,
      response_sha256: observations[2].response_body_sha256, anonymous_status: '401',
      wrong_purpose_code: 'mrn_job_signature', denial_measurement_sha256: delegationDigest(transcript) });
    for (const name of groups) assert.equal(value.group_sha256[name], delegationDigest(value.groups[name]));
    return value;
  };
  const acquired = verifyAcquired(await acquireMeasurements(first.qualification_job_id, captured.capture_artifact_sha256));
  assert.deepEqual(await worker('status', first.qualification_job_id), saved);
  pass('Pinned Node runner measures real anonymous, wrong-purpose and authorized HTTPS outcomes through two actual gateway vetoes');
  // Exercise real authenticated provider readback while local membership stays
  // enabled. No synthetic callback decision and no changes to provider policy.
  for (const change of ['permission', 'environment']) {
    if (change === 'permission') policy.emailDomains[0].portfolio.actions = ['read'];
    else registryData.websites[0].environments[0].name = 'staging';
    assert.equal(registry.member(actor).enabled, true);
    const withheld = await recover(first.qualification_job_id, captured.capture_artifact_sha256);
    assert.equal(withheld.ok, false); assert.equal(withheld.error_code, 'mrn_capture_read_access');
    assert.equal(Object.hasOwn(withheld, 'output'), false);
    assert.equal(await acquireMeasurements(first.qualification_job_id, captured.capture_artifact_sha256, 'denied'), undefined);
    assert.deepEqual(await worker('status', first.qualification_job_id), saved);
    // A removed content grant blocks every new job. An authorized environment
    // reconfiguration blocks this old map, but may admit a separately fresh job.
    if (change === 'permission') await assert.rejects(queue);
    policy.emailDomains[0].portfolio.actions = ['read', 'content'];
    registryData.websites[0].environments[0].name = 'production';
  }
  assert.equal(store.db.prepare("SELECT count(*) AS n FROM content_access_audit WHERE job=? AND phase='read-capture' AND state='denied'")
    .get(first.qualification_job_id).n, 4);
  assert.deepEqual((await recover(first.qualification_job_id, captured.capture_artifact_sha256)).output, recoveredOutput);
  const reacquired = verifyAcquired(await acquireMeasurements(first.qualification_job_id, captured.capture_artifact_sha256));
  for (const name of ['capture_integrity', 'scope_ownership', 'source_integrity']) assert.deepEqual(reacquired.groups[name], acquired.groups[name]);
  assert.notEqual(reacquired.transcript.correlation_id, acquired.transcript.correlation_id);
  assert.equal(reacquired.transcript.observations.authorized_read.response_body_sha256, acquired.transcript.observations.authorized_read.response_body_sha256);
  assert.deepEqual(await worker('status', first.qualification_job_id), saved);
  pass('Current local content permission and the original environment independently gate retained capture and real Node acquisition');
  const second = await queue();
  assert.equal((await worker('arm-delay', second.qualification_job_id)).armed, true);
  const pendingCapture = worker('capture', second.qualification_job_id).then(value => ({ value }), error => ({ error }));
  // Capture is asynchronous: the Node callback and exact read marker remain live.
  const activeRead = await worker('wait-read', second.qualification_job_id);
  assert.equal(activeRead.ok, true); assert.equal(activeRead.in_flight, true);
  assert.deepEqual(ledger.recordPendingRevocation(second.qualification_job_id), { state: 'pending-revocation', revision: 2, committed_denial: false });
  const pendingResult = await pendingCapture;
  if (pendingResult.error) throw pendingResult.error;
  const refused = pendingResult.value; assert.equal(refused.ok, false);
  const stopped = await worker('status', second.qualification_job_id);
  assert.equal(stopped.ok, true); assert.equal(stopped.capture_present, false); assert.equal(stopped.capture_artifact_sha256, null);
  assert.equal(stopped.state, 'failed'); assert.equal(stopped.capture_authenticated, false); assert.equal(stopped.retained_state_matches, true);
  assert.ok(store.db.prepare('SELECT * FROM content_access_queries WHERE job=?').all(second.qualification_job_id).some(row => row.state === 'pending-revocation' && row.revision === 2));
  pass('Pending gateway revocation during a real delayed Child read prevents complete capture retention');
  const deniedRecovery = await recover(first.qualification_job_id, captured.capture_artifact_sha256);
  assert.equal(deniedRecovery.ok, false); assert.equal(deniedRecovery.qualification_job_id, first.qualification_job_id);
  assert.equal(Object.hasOwn(deniedRecovery, 'output'), false);
  assert.equal(deniedRecovery.error_code, 'mrn_capture_read_access');
  assert.deepEqual(await worker('status', first.qualification_job_id), saved);
  assert.ok(store.db.prepare("SELECT * FROM content_access_audit WHERE job=? AND phase='read-capture' AND event='veto-answered'")
    .all(first.qualification_job_id).some(row => row.state === 'pending-revocation' && row.revision === 2));
  pass('Pending original-requester revocation also withholds a previously completed capture without changing it');
  assert.equal(await acquireMeasurements(first.qualification_job_id, captured.capture_artifact_sha256, 'pending-revocation'), undefined);
  assert.deepEqual(await worker('status', first.qualification_job_id), saved);
  pass('Pending original-requester revocation refuses real Node acquisition without altering the capture');
  await assert.rejects(queue, { code: 'ACCESS_DENIED' });
  const attempts = store.db.prepare('SELECT * FROM content_access_audit WHERE job=?').all(second.qualification_job_id);
  assert.ok(attempts.some(row => row.event === 'revocation-pending'));
  assert.ok(attempts.some(row => row.event === 'veto-answered' && row.state === 'pending-revocation'));
  const network = readFileSync(join(work, 'gateway-network.jsonl'), 'utf8').trim().split('\n').map(line => JSON.parse(line));
  const queued = network.filter(row => row.path.endsWith('/queue-delegated-qualification-v1/run'));
  assert.equal(queued.length, 2); assert.ok(queued.every(row => row.method === 'POST' && row.status === 200 && row.subject === actor.subject));
  const lookups = network.filter(row => row.path.endsWith('/read-access-veto-query-v1/run'));
  assert.equal(lookups.length, store.db.prepare('SELECT count(*) AS n FROM content_access_queries').get().n);
  assert.ok(lookups.every(row => row.method === 'POST' && row.status === 200 && !row.subject));
  pass('Durable original-requester ledger denies subsequent queueing and retains attributable revocation evidence');
  await accessRuntime.close(); await accessRuntime.close();
  await assert.rejects(() => veto.respond({ query_id: randomUUID(), provider_query_nonce: 'a'.repeat(64) }), { code: 'ACCESS_UNAVAILABLE' });
  pass('Service lifecycle validates a dedicated control connection and refuses callbacks after shutdown');
  report.result = 'PASS'; report.captureVerified = true; report.pendingDuringReadVerified = true;
  report.privateLifecycleVerified = true;
  report.measurementReadbackVerified = true; report.measurementSitePermissionVerified = true; report.pendingMeasurementsDenied = true;
  report.nodeAcquisitionVerified = true; report.nodeAcquisitionDenialsVerified = true;
  assert.deepEqual(acquisitionChecks.map(row => row.state), ['clear', 'denied', 'denied', 'clear', 'pending-revocation']);
  report.nodeAcquisition = acquired; report.nodeAcquisitionChecks = acquisitionChecks;
  report.currentSitePermissionVerified = true; report.retainedCaptureRecoveryVerified = true; report.pendingRecoveryDenied = true;
  writeFileSync(process.env.MRN_GATEWAY_ACCESS_REPORT, `${JSON.stringify(report, null, 2)}\n`, { mode: 0o600 });
} finally {
  for (const child of processes) child?.kill('SIGTERM');
  let cleanupError;
  try { if (routed) await run(context.control.route_switch, ['synthetic'], 10000); } catch (error) { cleanupError = error; }
  if (server?.listening) { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
  try { await accessRuntime?.close(); } catch (error) { cleanupError ||= error; }
  await Promise.allSettled(clients.map(client => client.close()));
  store.close();
  if (cleanupError) throw cleanupError;
}
