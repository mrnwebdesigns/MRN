import { readFileSync } from 'node:fs';
import { digest, requireThat } from './contracts.mjs';
import { delegationDigest } from './delegation.mjs';
import { compileManagementCaptureSchema, compileManagementEvidenceSchema } from './content-management-schema.mjs';

// Explicit existing-record v2 schemas. No caller-controlled version switch,
// transport registration, signing key, provenance claim or report admission.
export const existingManagementProtocol = 'content-management-existing-v2';
export const existingManagementHashes = Object.freeze({
  qualification: '1e652a77e369a341c4fa1d179ff6c69379b84661bdcd787850708adc6c01630e',
  report: 'dd20dc082d006e4e53d02eddb5cdfc1543e3056281ef304dd750687856030114',
  evidence: 'e511e4c9bc62eb839c68b59e64996e6b0d013f61ad8961520486ad821d4de6ff',
  source_transfer: 'c99c352961aaf7f289352e6aeacd4ad914747fdff69072cf54151c4d1e2f64d5',
  source_context: '3996f36dc37181d1952b31ad32a2a231823954b4c058be1d3cb18e927a78b6c1',
  wire_registry: 'dd375ecdc7e33b8e8e8f80ed0846ab76a5f3ad038be83d349b2902f8ae8fcef6',
});
const need = value => requireThat(value, 'MANAGEMENT_EXISTING_CONTRACT', 'The existing-record protocol differs from its fixed reviewed source.');
const freeze = value => {
  if (value && typeof value === 'object' && !Object.isFrozen(value)) { Object.values(value).forEach(freeze); Object.freeze(value); }
  return value;
};
const wires = {};
for (const [name, hash] of Object.entries(existingManagementHashes)) {
  const file = name === 'wire_registry' ? 'content-management-existing-wire-v2-proposal.json'
    : `content-management-existing-${name.replaceAll('_', '-')}-v2.json`;
  const bytes = readFileSync(new URL(`../contracts/${file}`, import.meta.url));
  need(digest(bytes) === hash);
  const wire = JSON.parse(bytes);
  need(wire.qualification_protocol === existingManagementProtocol && wire.production_enabled === false
    && wire.runtime_registered === false && wire.consumer_binding_enabled === false);
  wires[name] = freeze(wire);
}
// Verify the legacy dependencies without modifying any v1 entry point.
for (const [file, hash] of Object.entries(wires.wire_registry.frozen_inputs_sha256))
  need(/^[a-z0-9-]+\.json$/.test(file) && digest(readFileSync(new URL(`../contracts/${file}`, import.meta.url))) === hash);
export const existingManagementWires = freeze(wires);
// Complete 20,000-file manifests belong to the runner's bounded byte assembly,
// not this metadata parser. Do not widen the legacy/private message budgets to
// make an unused manifest definition compile here.
export const existingManagementTypes = Object.freeze(Object.fromEntries(Object.entries(wires)
  .filter(([, wire]) => wire.$defs).map(([domain, wire]) => [domain, Object.freeze(Object.fromEntries(Object.keys(wire.$defs)
    .filter(name => !(domain === 'source_transfer' && name === 'transfer_manifest'))
    .map(name => [name, (domain === 'qualification' ? compileManagementCaptureSchema : compileManagementEvidenceSchema)
      ({ $ref: `#/$defs/${name}`, $defs: wire.$defs })])))])));

// The expected original must come from independently retained service/provider
// state. Schema equality establishes no identity, fresh access or child-home
// authority and cannot by itself authorize dispatch or renew an expired job.
export function validateExistingManagementBinding(value, retainedOriginal) {
  const binding = existingManagementTypes.qualification.binding.parse(value);
  const original = existingManagementTypes.qualification.binding.parse(retainedOriginal);
  need(delegationDigest(binding.protocol_contracts_sha256) === delegationDigest(existingManagementHashes)
    && delegationDigest(binding) === delegationDigest(original)
    && binding.expires_at > binding.created_at && binding.expires_at <= binding.created_at + binding.job_intent_ttl_seconds
    && binding.job_intent_ttl_seconds <= 900 && binding.target.post_id > 0);
  return binding;
}
