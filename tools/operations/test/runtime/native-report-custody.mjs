// Disposable transport/custody proof only. The packet contains synthetic
// rendered, WordPress HTTP and GitHub observations, never production QA.
import assert from 'node:assert/strict';
import { createPublicKey, randomBytes, randomUUID, verify } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { digest } from '../../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../../src/delegation.mjs';
import { ManagementEvidenceCallback, ManagementEvidenceQueryReader, ManagementEvidenceSigner } from '../../src/management-evidence-callback.mjs';
import { managementEvidenceTypes as evidenceTypes, managementEvidenceWire as evidenceWire,
  managementReportTypes as reportTypes, managementReportWire as reportWire } from '../../src/management-evidence-contract.mjs';
import { createManagementReportTransport } from '../../src/management-report-transport.mjs';
import { reportFailureDiagnostics } from './report-failure-diagnostics.mjs';

const need = condition => assert.ok(condition, 'Disposable report custody precondition failed; private values withheld');
const same = (a, b) => need(delegationDigest(a) === delegationDigest(b));
const parse = (schema, value) => { const result = schema.safeParse(value); need(result.success); return result.data; };
const fields = (value, names) => same(Object.keys(value).sort(), [...names].sort());
const stamp = seconds => new Date(seconds * 1000).toISOString();

// Return a closed in-memory GitHub observation fixture. The actual callback's
// downloader still validates run identity, inventory, bytes, expiry and access.
// No metadata supplied here is represented as hosted GitHub provenance.
function githubFixture(packet, original, archives, observations) {
  return { async managementEvidence(repository, authorize, options) {
    need(repository === original.record.repository && options.expiresAt === original.record.originalExpiresAt);
    const startedAt = Date.now(), used = new Set();
    const once = async (name, perform) => {
      need(!used.has(name)); used.add(name); await authorize();
      const result = perform(); await authorize(); observations.push(name); return result;
    };
    const run = packet.observed_run;
    const runValue = { id: run.run_id,
      repository: { id: run.repository_id, full_name: repository }, head_repository: { id: run.repository_id, full_name: repository },
      workflow_id: run.workflow_id, path: original.record.workflowPath, head_sha: run.head_sha, head_branch: run.head_branch,
      event: run.event, run_attempt: run.run_attempt, status: run.status, conclusion: run.conclusion,
      actor: { id: run.actor_id }, triggering_actor: { id: run.triggering_actor_id },
      created_at: stamp(run.created_at), updated_at: stamp(run.updated_at) };
    const artifacts = Object.entries(packet.artifacts).map(([_role, item]) => ({ id: item.artifact_id, name: item.name,
      size_in_bytes: item.bytes, digest: item.reported_digest, expired: item.expired,
      created_at: stamp(item.created_at), expires_at: stamp(item.expires_at),
      workflow_run: { id: item.workflow_run.run_id, repository_id: item.workflow_run.repository_id,
        head_sha: item.workflow_run.head_sha, head_branch: 'main' } }));
    return {
      readRun: id => once('run', () => {
        need(id === original.record.runId); return { value: runValue, sha256: delegationDigest(runValue) };
      }),
      readArtifacts: id => once('inventory', () => {
        need(id === original.record.runId); const value = { total_count: 2, artifacts };
        return { value, sha256: delegationDigest(value) };
      }),
      downloadArtifact: (id, role) => once(role, () => {
        need(['qualification', 'acquisition'].includes(role) && id === packet.artifacts[role].artifact_id);
        return { bytes: archives[role], sha256: digest(archives[role]), downloadedAt: Date.now() };
      }),
      finish: () => once('finish', () => {
        same([...used].sort(), ['run', 'inventory', 'qualification', 'acquisition', 'finish'].sort());
        return { startedAt, completedAt: Date.now() };
      }),
    };
  } };
}

export async function createNativeReportCustody({ source, reportPath, original, identity, registrar, keys, ledger,
  verifyStaff, connectQuery, connectControl, nativeDispatch, nativePacket, networkLogPath }) {
  need(process.env.GITHUB_ACTIONS === 'true' && process.env.MRN_DISPOSABLE_SOURCE_TEST === '1'
    && process.env.MRN_DISPOSABLE_REPORT_CUSTODY_TEST === '1');
  need(typeof reportPath === 'string' && resolve(reportPath) === reportPath);
  need(typeof networkLogPath === 'string' && resolve(networkLogPath) === networkLogPath);
  const report = { schema: 1, result: 'INCOMPLETE', source, scope: 'disposable-native-report-custody', passed: [],
    syntheticObservations: ['rendered_runtime', 'wordpress_http_transcript', 'github_run_and_artifacts'],
    productionCredentials: false, liveGoogleVerified: false, hostedProvenanceVerified: false,
    protectedRenderedQaVerified: false, consumerBindingIssued: false, contentWriteVerified: false,
    remoteBackupVerified: false, chatgptAcceptanceVerified: false };
  const save = () => writeFileSync(reportPath, JSON.stringify(report, null, 2) + '\n', { mode: 0o600 });
  const pass = name => { report.passed.push(name); save(); process.stdout.write(`PASS ${name}\n`); };
  const phase = name => { report.phase = name; save(); };
  const failure = (stage, error) => {
    const allowed = new Set(['MANAGEMENT_EVIDENCE_CALLBACK', 'MANAGEMENT_EVIDENCE_BINDING', 'MANAGEMENT_EVIDENCE_STATE',
      'MANAGEMENT_REPORT_TRANSPORT', 'ACCESS_INVALID', 'ACCESS_EXPIRED', 'ACCESS_BINDING', 'ACCESS_MAPPING',
      'ACCESS_REPLAY', 'ACCESS_TRANSPORT', 'ACCESS_UNAVAILABLE', 'MAINWP_ACCESS', 'MAINWP_FAILED', 'DOWNSTREAM_TIMEOUT']);
    // Fixed stages, known error codes and aggregate progress only. Exception
    // messages, signatures, pointers and original source values stay private.
    report.failures ??= [];
    if (report.failures.length < 4) report.failures.push({ stage,
      code: error?.code === -32001 ? 'MCP_TIMEOUT' : allowed.has(error?.code) ? error.code : 'REDACTED' });
    save();
  };
  save();
  const job = original.context.binding.qualification_job_id;
  const signedClaims = (envelope, role, purpose, schema) => {
    parse(evidenceTypes.envelope, envelope);
    const bytes = Buffer.from(envelope.payload_base64, 'base64');
    need(bytes.toString('base64') === envelope.payload_base64);
    const value = JSON.parse(bytes); need(bytes.toString() === delegationCanonical(value));
    const selected = keys[role].filter(key => key.id === envelope.key_id); need(selected.length === 1);
    const publicKey = createPublicKey({ key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'),
      Buffer.from(selected[0].public_key_base64, 'base64')]), type: 'spki', format: 'der' });
    need(verify(null, Buffer.concat([Buffer.from(purpose), bytes]), publicKey, Buffer.from(envelope.signature_base64, 'base64')));
    return parse(schema, value);
  };
  const identitySigner = new ManagementEvidenceSigner(identity), registrarSigner = new ManagementEvidenceSigner(registrar);
  const issued = Math.floor(Date.now() / 1000);
  const handoff = identitySigner.envelope({ schema: 1, fixture_only: true, action: 'bind-management-report-dispatch',
    context: original.context, provenance: original.provenance, requested_at_ms: original.record.requestedAt,
    issued_at: issued, expires_at: Math.min(issued + 120, original.context.binding.expires_at),
    request_id: randomUUID(), nonce: randomBytes(32).toString('hex') }, 'mrn-disposable-management-report-dispatch-v1:');
  phase('original-dispatch-handoff');
  const dispatch = await nativeDispatch(job, handoff);
  fields(dispatch, ['ok', 'qualification_job_id', 'dispatch_sha256']);
  need(dispatch.ok === true && dispatch.qualification_job_id === job && dispatch.dispatch_sha256 === delegationDigest(original.provenance));
  const packet = await nativePacket(job);
  fields(packet, ['schema', 'fixture_only', 'synthetic_observations', 'production_activation', 'consumer_binding_issued',
    'context', 'provenance', 'retain_input', 'read_input', 'observed_run', 'artifacts', 'qualification_zip_base64', 'acquisition_zip_base64']);
  need(packet.schema === 1 && packet.fixture_only === true && packet.production_activation === false && packet.consumer_binding_issued === false);
  same(packet.synthetic_observations, report.syntheticObservations); same(packet.context, original.context); same(packet.provenance, original.provenance);
  parse(reportTypes.retain_input, packet.retain_input); parse(reportTypes.read_input, packet.read_input);
  const qa = signedClaims(packet.retain_input.signed_qa_report, 'qa', reportWire.purposes.qa_report, reportTypes.qa_report_claims);
  const retain = signedClaims(packet.retain_input.signed_request, 'registrar', reportWire.purposes.retain, reportTypes.registrar_claims);
  const read = signedClaims(packet.read_input.signed_request, 'registrar', reportWire.purposes.read, reportTypes.read_claims);
  same(qa.context, original.context); same(qa.run_provenance, original.provenance);
  for (const claims of [retain, read]) { same(claims.context, original.context); need(claims.qualification_report_sha256 === delegationDigest(qa)); }
  parse(evidenceTypes.run_observation, packet.observed_run);
  fields(packet.artifacts, ['qualification', 'acquisition']);
  const archives = {};
  for (const [role, limit] of [['qualification', 262144], ['acquisition', 196608]]) {
    const encoded = packet[`${role}_zip_base64`]; need(typeof encoded === 'string' && encoded.length <= Math.ceil(limit / 3) * 4);
    const bytes = Buffer.from(encoded, 'base64');
    need(bytes.length > 0 && bytes.length <= limit && bytes.toString('base64') === encoded);
    const item = packet.artifacts[role];
    parse(evidenceTypes.artifact_observation, { ...item, download_completed_at: Math.floor(Date.now() / 1000) });
    need(item.bytes === bytes.length && item.sha256 === digest(bytes)); archives[role] = bytes;
  }
  pass('signed-native-packet-preserves-original-dispatch-and-separate-QA-authority');
  const queryClient = await connectQuery();
  let transport;
  const close = async () => { await transport?.close(); await queryClient.close(); };
  try {
    const reader = new ManagementEvidenceQueryReader(queryClient); await reader.initialize();
    transport = await createManagementReportTransport({ enabled: true, callbackClient: queryClient, connect: connectControl });
    const observations = [], counts = { requests: 0, completed: 0, queryReads: 0 };
    report.callback = counts; report.syntheticObservationStages = observations; save();
    let acquisition;
    const handler = new ManagementEvidenceCallback({ signer: identitySigner, readbackSigner: registrarSigner, ledger, verifyStaff,
      github: githubFixture(packet, original, archives, observations), reader: { async read(envelope) {
        signedClaims(envelope, 'registrar', evidenceWire.purposes.query_readback, evidenceTypes.query_readback_claims);
        counts.queryReads++; return reader.read(envelope);
      } } });
    return {
      close,
      async respond(input) {
        counts.requests++;
        try {
          const result = await handler.respond(input);
          acquisition = signedClaims(result.signed_acquisition, 'identity', evidenceWire.purposes.acquisition, evidenceTypes.acquisition_claims);
          same(acquisition.recorded_dispatch, original.provenance);
          for (const role of ['qualification', 'acquisition']) need(result[`${role}_zip_base64`] === packet[`${role}_zip_base64`]);
          counts.completed++; save(); return result;
        } catch (error) { failure('evidence-callback', error); throw error; }
      },
      async run() {
        phase('actual-MCP-retain-and-TLS-evidence-callback');
        const retentionStarted = performance.now();
        let summary;
        try { summary = await transport.retain(packet.retain_input); }
        catch (error) {
          // Relative timings diagnose expiry without retaining the signed body,
          // original identifiers, private context or arbitrary remote messages.
          const now = Date.now();
          report.failureTiming = { retentionElapsedMs: Math.round(performance.now() - retentionStarted),
            originalRemainingMs: original.context.binding.expires_at * 1000 - now,
            acquisitionAgeMs: acquisition ? now - acquisition.issued_at * 1000 : null,
            acquisitionRemainingMs: acquisition ? acquisition.expires_at * 1000 - now : null };
          try { report.providerResponses = reportFailureDiagnostics(readFileSync(networkLogPath, 'utf8')); }
          catch { report.providerResponses = { available: false, responses: [] }; }
          failure('retain-response', error); throw error;
        }
        same(counts, { requests: 1, completed: 1, queryReads: 1 });
        same(observations, ['run', 'inventory', 'qualification', 'acquisition', 'finish']);
        need(summary.qualification_job_id === job && summary.management_context_sha256 === original.context.management_context_sha256
          && summary.qualification_report_sha256 === delegationDigest(qa)
          && summary.qualification_archive_sha256 === digest(archives.qualification)
          && summary.acquisition_archive_sha256 === digest(archives.acquisition)
          && summary.acquisition_observation_sha256 === delegationDigest(acquisition)
          && summary.evidence_policy_epoch === original.context.evidence_policy_epoch
          && summary.evidence_policy_sha256 === original.context.evidence_policy_sha256
          && summary.retained_at >= original.context.binding.created_at
          && summary.retained_at < original.context.binding.expires_at);
        need(summary.consumer_binding_issued === false && summary.production_qualified === false && summary.state === 'retained');
        report.retained = summary;
        pass('actual-MCP-retention-completes-through-Node-TLS-callback-and-distinct-role-keys');
        phase('fresh-retained-read-and-replay-denial');
        same(await transport.read(packet.read_input), summary);
        pass('fresh-MCP-readback-matches-original-report-and-both-archive-hashes');
        await assert.rejects(() => transport.retain(packet.retain_input));
        same(counts, { requests: 1, completed: 1, queryReads: 1 });
        pass('consumed-acquisition-cannot-retry-or-mint-another-callback');
      },
      async revoked() {
        phase('fresh-report-read-after-original-revocation');
        const at = Math.floor(Date.now() / 1000);
        const fresh = { ...read, issued_at: at, expires_at: Math.min(at + 20, read.expires_at),
          request_id: randomUUID(), nonce: randomBytes(32).toString('hex') };
        need(fresh.expires_at > at); parse(reportTypes.read_claims, fresh);
        await assert.rejects(() => transport.read({ signed_request: registrarSigner.envelope(fresh, reportWire.purposes.read) }));
        pass('fresh-original-revocation-denies-unused-report-read');
      },
      complete() { need(report.passed.length === 5 && !report.failures); delete report.phase; report.result = 'PASS'; save(); },
    };
  } catch (error) { await close(); throw error; }
}
