import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHash, createPublicKey, verify } from 'node:crypto';
import { compileContentSchema } from '../src/content-schema.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';

// Candidate wire compatibility only. These files are not consumed by the
// service, and neither schema validity nor a synthetic signature grants trust.
const read = path => readFileSync(new URL(path, import.meta.url));
const candidate = path => read(`./fixtures/measurement-readback-candidate/${path}`);
const source = JSON.parse(candidate('source.json'));
const contract = JSON.parse(candidate('schema.json'));
const fixture = JSON.parse(candidate('vectors.json'));
const chain = fixture.coherent_chain;
const hash = bytes => createHash('sha256').update(bytes).digest('hex');
const schema = name => compileContentSchema({ $ref: `#/definitions/${name}` }, contract.definitions);

test('measurement candidate has separate source provenance and preserves the qualified runtime pin', () => {
  assert.equal(source.repository, 'mrnwebdesigns/mrn-mainwp-operations-api');
  assert.match(source.commit, /^[a-f0-9]{40}$/);
  assert.equal(source.source_only, true);
  assert.notEqual(source.commit, source.runtime_provider_commit);
  assert.equal(JSON.parse(read('./runtime/sources.json')).provider.commit, source.runtime_provider_commit);
  assert.equal(JSON.parse(read('../contracts/content-qualification-sources.json')).commit, source.runtime_provider_commit);
  assert.deepEqual(source.files.map(row => row.local), ['schema.json', 'vectors.json', 'design.md']);
  for (const row of source.files) assert.equal(hash(candidate(row.local)), row.sha256);
  assert.equal(hash(candidate('schema.json')), fixture.contract_sha256);
  assert.equal(hash(candidate('design.md')), contract.design_sha256);
  assert.deepEqual(contract.frozen_dependencies, fixture.frozen_contract_sha256);
  assert.equal(Object.keys(contract.frozen_dependencies).length, 17);
  const existing = JSON.parse(read('../contracts/content-qualification-sources.json'));
  for (const row of existing.files) {
    const name = row.source.replace(/^contracts\//, '');
    if (Object.hasOwn(contract.frozen_dependencies, name))
      assert.equal(contract.frozen_dependencies[name], row.sha256, name);
  }
});

test('every candidate definition compiles with the gateway and closed vectors keep exact canonical bytes', () => {
  for (const name of Object.keys(contract.definitions)) schema(name);
  for (const row of fixture.cases) {
    const parser = schema(row.definition);
    assert.deepEqual(parser.parse(row.value), row.value);
    assert.equal(delegationCanonical(row.value), row.canonical);
    assert.equal(delegationDigest(row.value), row.sha256);
    assert.equal(parser.safeParse({ ...row.value, caller_success: true }).success, false);
    for (const key of Object.keys(row.value)) {
      const missing = structuredClone(row.value); delete missing[key];
      assert.equal(parser.safeParse(missing).success, false, `${row.definition}.${key}`);
    }
  }
  for (const row of fixture.negative_vectors) {
    // Cross-binding refusals belong to the future semantic consumer; explicitly
    // retain that distinction instead of claiming the schema enforces them.
    assert.equal(schema(row.definition).safeParse(row.value).success,
      row.expected_rejection !== 'schema', row.name);
  }
});

test('candidate synthetic Ed25519 assertions verify only under their exact registrar purpose', () => {
  const publicKey = createPublicKey({ format: 'der', type: 'spki', key: Buffer.concat([
    Buffer.from('302a300506032b6570032100', 'hex'), Buffer.from(chain.registrar_public_key_base64, 'base64'),
  ]) });
  const claims = delegationCanonical(chain.claims);
  const purposes = [contract.purpose_prefix, contract.wrong_purpose_probe_prefix];
  for (const [index, name] of ['signed_request', 'wrong_purpose_signed_request'].entries()) {
    const envelope = schema('signed_envelope').parse(chain[name]);
    assert.equal(Buffer.from(envelope.payload_base64, 'base64').toString('utf8'), claims);
    const signature = Buffer.from(envelope.signature_base64, 'base64');
    assert.equal(verify(null, Buffer.from(purposes[index] + claims), publicKey, signature), true);
    assert.equal(verify(null, Buffer.from(purposes[1 - index] + claims), publicKey, signature), false);
    assert.equal(verify(null, Buffer.from(purposes[index] + claims + '\n'), publicKey, signature), false);
  }
});

test('candidate packet and HTTP digests retain their distinct byte preimages', () => {
  const { measurement_record: record, output, acquisition_transcript: transcript } = chain;
  assert.equal(output.measurement_record_json, delegationCanonical(record));
  assert.equal(output.measurement_record_sha256, hash(output.measurement_record_json));
  assert.equal(output.binding_sha256, delegationDigest(chain.binding));
  assert.equal(transcript.measurement_record_sha256, output.measurement_record_sha256);
  assert.equal(chain.binding.measurement_contract_sha256, fixture.contract_sha256);
  const pieces = chain.synthetic_native_outputs.map((reply, index) => {
    const observed = record.chunks[index];
    assert.equal(reply.output_truncated, false); assert.equal(observed.output_truncated, false);
    assert.equal(observed.native_output_sha256, hash(reply.output));
    assert.equal(observed.native_output_utf8_bytes, Buffer.byteLength(reply.output));
    const packet = JSON.parse(reply.output), bytes = Buffer.from(packet.chunk_base64, 'base64');
    assert.equal(packet.chunk_index, index); assert.equal(observed.chunk_index, index);
    assert.equal(bytes.length, observed.decoded_bytes);
    return bytes;
  });
  assert.equal(hash(Buffer.concat(pieces)), record.decoder.dataset_sha256);
  assert.equal(Buffer.concat(pieces).length, record.decoder.total_bytes);
  assert.equal(pieces.length, record.decoder.accepted_chunks);
  assert.equal(pieces.length, record.capture_integrity.chunks_received);
  assert.notEqual(pieces.length, Math.ceil(Buffer.byteLength(delegationCanonical(chain.original_capture)) / 32768));
  assert.equal(record.source_integrity.installed_manifest_sha256, hash(chain.synthetic_installed_manifest_json));
  assert.ok(chain.synthetic_installed_manifest_json.endsWith('\n'));
  assert.notEqual(hash(chain.synthetic_installed_manifest_json.trimEnd()), record.source_integrity.installed_manifest_sha256);
  for (const [role, name] of [['anonymous_probe', 'anonymous'], ['wrong_purpose_probe', 'wrong_purpose'], ['authorized_read', 'authorized']]) {
    const observed = transcript.observations[role], raw = chain.synthetic_http_entity_examples[name];
    const envelope = role === 'wrong_purpose_probe' ? chain.wrong_purpose_signed_request : chain.signed_request;
    const request = delegationCanonical({ input: { signed_request: envelope } });
    assert.equal(observed.request_body_sha256, hash(request));
    assert.equal(observed.request_body_utf8_bytes, Buffer.byteLength(request));
    assert.equal(observed.response_body_sha256, hash(raw));
    assert.equal(observed.response_body_utf8_bytes, Buffer.byteLength(raw));
  }
  const raw = chain.synthetic_http_entity_examples.authorized;
  assert.notEqual(hash(raw), output.measurement_record_sha256);
  assert.notEqual(hash(raw), hash(raw + '\n'));
  assert.deepEqual(JSON.parse(raw), JSON.parse(raw + '\n'));
  assert.equal(chain.v2_projection.wordpress_api.denial_measurement_sha256, delegationDigest(transcript));
});

test('candidate stays private and cannot widen eligible targets or represent activation', () => {
  for (const key of ['production_enabled', 'binding_issuance', 'consumer_binding_enabled']) assert.equal(contract[key], false);
  for (const key of ['registered', 'workspace_exposed', 'delegated_staff_allowed']) assert.equal(contract.ability[key], false);
  assert.equal(contract.http_method, 'POST');
  assert.equal(contract.limits.measurement_read_reservations_per_job, 8);
  assert.match(contract.semantic_requirements.read_budget, /separate from/);
  for (const changes of [{ actor_email: 'caller@mrnwebdesigns.com' }, { site_url: 'https://mrnwebdesigns.com' },
    { target: { ...chain.binding.target, field: 'acf' } }, { target: { ...chain.binding.target, kind: 'menu' } }])
    assert.equal(schema('binding').safeParse({ ...chain.binding, ...changes }).success, false);
  const serialized = delegationCanonical(chain.acquisition_transcript);
  for (const key of ['authorization', 'cookie', 'signature_base64', 'payload_base64', 'message', 'body', 'email', 'token'])
    assert.equal(serialized.includes(`"${key}":`), false);
});

test('cross-language lifetime vectors refuse observations outside the original assertion or transport deadline', () => {
  const issued = chain.claims.issued_at * 1000;
  const expires = Math.min(chain.claims.expires_at, chain.binding.job_expires_at) * 1000;
  assert.ok(chain.claims.expires_at < chain.binding.job_expires_at);
  const withinOriginalWindow = transcript => {
    let previous = 0;
    for (const role of ['anonymous_probe', 'wrong_purpose_probe', 'authorized_read']) {
      const item = transcript.observations[role];
      if (item.started_at_ms < issued || item.started_at_ms <= previous
        || item.completed_at_ms < item.started_at_ms || item.completed_at_ms >= expires
        || item.completed_at_ms - item.started_at_ms > contract.limits.http_timeout_ms) return false;
      previous = item.completed_at_ms;
    }
    return true;
  };
  assert.equal(withinOriginalWindow(chain.acquisition_transcript), true);
  for (const name of ['before-assertion-issuance', 'at-assertion-expiry', 'after-assertion-expiry',
    'at-job-expiry', 'all-after-assertion-expiry', 'request-over-twenty-seconds']) {
    const row = fixture.negative_vectors.find(item => item.name === name);
    assert.ok(row, name); assert.equal(row.expected_rejection, 'cross-binding');
    assert.equal(schema('acquisition_transcript').safeParse(row.value).success, true);
    assert.equal(withinOriginalWindow(row.value), false, name);
  }
  for (const mode of ['issuance', 'before-expiry', 'exact-timeout']) {
    const value = structuredClone(chain.acquisition_transcript);
    if (mode === 'issuance') {
      const offset = issued - value.observations.anonymous_probe.started_at_ms;
      for (const item of Object.values(value.observations)) {
        item.started_at_ms += offset; item.completed_at_ms += offset;
      }
    } else {
      const item = value.observations.authorized_read;
      if (mode === 'before-expiry') {
        item.started_at_ms = expires - 501; item.completed_at_ms = expires - 1;
      } else item.completed_at_ms = item.started_at_ms + contract.limits.http_timeout_ms;
    }
    assert.equal(withinOriginalWindow(value), true, mode);
  }
});
