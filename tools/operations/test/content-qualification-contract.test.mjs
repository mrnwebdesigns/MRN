import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { compileContentSchema } from '../src/content-schema.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';

const root = new URL('../', import.meta.url);
const read = path => readFileSync(new URL(path, root));
const names = ['content-qualification-v1.json', 'content-recipe-catalog-v1.json', 'content-target-binding-v1.json', 'content-recipe-install-v1.json', 'content-qualification-handoff-v1.json'];
const contracts = Object.fromEntries(names.map(name => [name, JSON.parse(read(`contracts/${name}`))]));
const fixture = JSON.parse(read('test/fixtures/content-qualification-contract.json'));
const schema = (contract, definition) => compileContentSchema({ $ref: `#/definitions/${definition}` }, contracts[contract].definitions);

test('qualification contracts and independent vectors are pinned to one provider checkpoint', () => {
  const source = JSON.parse(read('contracts/content-qualification-sources.json'));
  assert.equal(source.schema, 1); assert.equal(source.repository, 'mrnwebdesigns/mrn-mainwp-operations-api');
  assert.equal(source.commit, 'c5d8f36bf0ace4ca27c99b7e4f6469b16513ded4');
  assert.deepEqual(source.files.map(f => f.local), [...names.map(n => `contracts/${n}`),
    'test/fixtures/content-qualification-contract.json', 'test/fixtures/content-policy-v2-provider.json',
    'contracts/content-capture-read-v1.json', 'contracts/content-qualification-report-v1.json',
    'test/fixtures/content-qualification-additive-v1.json', 'contracts/content-current-access-v1.json']);
  for (const file of source.files) assert.equal(createHash('sha256').update(read(file.local)).digest('hex'), file.sha256);
  const dependencies = contracts[names[0]].frozen_dependencies;
  assert.equal(createHash('sha256').update(read('contracts/content-v1.json')).digest('hex'), dependencies['content-v1.json']);
  const upstream = read('test/fixtures/content-policy-v2-provider.json');
  assert.equal(createHash('sha256').update(upstream).digest('hex'), dependencies['content-policy-v2-proposal.json']);
  // The existing gateway copy has different descriptive metadata. Verify the
  // actual frozen wire fields against the checksum-pinned upstream document.
  const provider = JSON.parse(upstream), gateway = JSON.parse(read('contracts/content-policy-v2.json'));
  for (const key of ['schema', 'ability', 'role', 'wrapper', 'input', 'output']) assert.deepEqual(gateway[key], provider[key]);
});

test('all provider qualification definitions and canonical structural vectors validate in the gateway', () => {
  let count = 0;
  for (const [name, contract] of Object.entries(contracts)) for (const definition of Object.keys(contract.definitions || {})) {
    schema(name, definition); count++;
  }
  assert.equal(count, 43); assert.equal(fixture.cases.length, 9);
  for (const row of fixture.cases) {
    assert.deepEqual(schema(row.contract, row.definition).parse(row.value), row.value);
    assert.equal(delegationCanonical(row.value), row.canonical); assert.equal(delegationDigest(row.value), row.sha256);
  }
});

test('the initial job contract cannot represent consumer trust or widen content and origin eligibility', () => {
  const q = contracts[names[0]], job = fixture.cases.find(row => row.definition === 'job_output').value;
  assert.deepEqual(q.definitions.job_output.properties.qualification_binding, { type: 'null' });
  assert.equal(schema(names[0], 'job_output').safeParse({ ...job, qualification_binding: {
    key_id: 'fixture', payload_base64: 'e30=', signature_base64: 'A'.repeat(86) + '==' } }).success, false);
  for (const changed of [{ state: 'qualified' }, { acceptance_scope: 'all-consumers' }, { site_url: 'https://mrnwebdesigns.com' },
    { target: { ...job.target, field: 'post_custom' } }, { actor: 'caller-selected' }]) {
    assert.equal(schema(names[0], 'job_output').safeParse({ ...job, ...changed }).success, false);
  }
  assert.equal(q.abilities['request-content-qualification-v1'].wrapper, 'queue-delegated-qualification-v1');
  assert.deepEqual(q.abilities['queue-delegated-qualification-v1'].annotations, { readonly: false, destructive: false, idempotent: true });
  for (const name of ['capture-content-qualification-v1', 'register-content-profile-v1', 'get-content-qualification-job-context-v1']) {
    assert.equal(q.abilities[name].workspace_exposed, false); assert.equal(q.abilities[name].delegated_staff_allowed, false);
  }
});

test('the separate target binding requires an actual revision and all consumers without becoming a publication request', () => {
  const q = contracts[names[0]], binding = contracts[names[2]];
  assert.equal(binding.purpose_prefix, 'mrn-content-target-binding-v1:');
  assert.ok(!Object.values(q.signature_prefixes).includes(binding.purpose_prefix));
  const row = fixture.cases.find(item => item.contract === names[2]), parsed = schema(names[2], 'claims');
  assert.ok(row); assert.deepEqual(parsed.parse(row.value), row.value);
  for (const changed of [{ target_revision: 0 }, { expected_target_revision: 0 },
    { audiences: ['mrn-mainwp-provider'] }, { audiences: Array(3).fill('mrn-operations-gateway') },
    { action: 'publish' }, { qualification_snapshot_sha256: 'bad' }]) {
    assert.equal(parsed.safeParse({ ...row.value, ...changed }).success, false);
  }
  // Structural compatibility is not a signature, current-state or trust test.
  // No production consumer loads these contracts or accepts bindings yet.
});

test('additive catalog installation stays owner-only and handoff does not assert worker readiness', () => {
  const install = contracts[names[3]], handoff = contracts[names[4]], q = contracts[names[0]];
  assert.deepEqual(Object.keys(install.abilities), ['install-content-recipe-catalog-v1']);
  const ability = install.abilities['install-content-recipe-catalog-v1'];
  assert.equal(ability.role, 'owner with fresh MRN verification'); assert.equal(ability.wrapper, null);
  assert.equal(ability.workspace_exposed, false); assert.equal(ability.delegated_staff_allowed, false);
  const input = compileContentSchema(ability.input, install.definitions), output = compileContentSchema(ability.output, install.definitions);
  const value = { confirm: true, expected_policy_sha256: 'a'.repeat(64),
    catalog: { key_id: 'fixture', payload_base64: 'e30=', signature_base64: 'A'.repeat(86) + '==' } };
  assert.deepEqual(input.parse(value), value);
  for (const changed of [{ confirm: false }, { expected_policy_sha256: '' }, { actor: 'caller' },
    { path: '/caller/catalog' }, { url: 'https://caller.example.invalid' }, { private_key: 'caller-key' }, { enabled: true }])
    assert.equal(input.safeParse({ ...value, ...changed }).success, false);
  const installed = { installed: true, recipe_catalog_sha256: 'a'.repeat(64), authorization_policy_sha256: 'b'.repeat(64) };
  assert.deepEqual(output.parse(installed), installed);
  assert.equal(output.safeParse({ ...installed, qualification_binding: value.catalog }).success, false);
  const qHash = createHash('sha256').update(read(`contracts/${names[0]}`)).digest('hex');
  assert.equal(install.frozen_dependencies[names[0]], qHash);
  assert.equal(handoff.frozen_qualification_contract_sha256, qHash);
  assert.equal(handoff.dispatch_owner, 'mrn-operations-gateway'); assert.equal(handoff.provider_readiness_claim, false);
  assert.equal(handoff.handoff_deadline_seconds, 300);
  assert.deepEqual(q.definitions.job_output.properties.qualification_binding, { type: 'null' });
  // This checks the closed wire and frozen dependency, not owner authorization,
  // dispatch readiness or the provider's deadline enforcement at runtime.
});
