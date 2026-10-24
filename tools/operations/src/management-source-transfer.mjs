import { readFileSync } from 'node:fs';
import { createCipheriv, createPublicKey, diffieHellman, generateKeyPairSync, hkdfSync, randomBytes } from 'node:crypto';
import { digest, requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { compileManagementEvidenceSchema } from './content-management-schema.mjs';
import { existingManagementTypes, existingManagementWires, validateExistingManagementBinding } from './management-existing-contracts.mjs';

const check = value => requireThat(value, 'MANAGEMENT_SOURCE_TRANSFER', 'The private source transfer is invalid or changed.');
const bytes = readFileSync(new URL('../contracts/content-management-source-transfer-v1.json', import.meta.url));
check(digest(bytes) === '36320f6d2d5dac5523ecc68b0fa463c2fa1246cbf75f81e3209c055aa196f3e9');
export const managementSourceWire = JSON.parse(bytes);
export const sourceRecipientPurpose = 'mrn-content-management-source-recipient-v1:';
export const sourceBatchPurpose = 'mrn-content-management-source-broker-batch-v1:';
export const sourceCipherPurpose = 'mrn-content-management-source-broker-aead-v1:';
export const sourceTransferTypes = Object.fromEntries(['source_context', 'slot', 'read_output', 'read_claims', 'read_input'].map(name => [name,
  compileManagementEvidenceSchema({ $ref: `#/$defs/${name}`, $defs: managementSourceWire.$defs })]));
export function parseSource(name, value) {
  const result = sourceTransferTypes[name].safeParse(value); check(result.success); return result.data;
}
export function sourceRecipientKey(encoded) {
  check(typeof encoded === 'string' && encoded.length === 60);
  const der = Buffer.from(encoded, 'base64'); check(der.length === 44 && der.toString('base64') === encoded);
  const key = createPublicKey({ key: der, format: 'der', type: 'spki' });
  check(key.asymmetricKeyType === 'x25519' && key.export({ format: 'der', type: 'spki' }).equals(der));
  // Reject unusable low-order public keys at enrollment, before any source read.
  const probe = diffieHellman({ privateKey: generateKeyPairSync('x25519').privateKey, publicKey: key }); probe.fill(0);
  return key;
}
export function validateSourceBatch(input, context, slot, request, signedInput) {
  return validateBatch(input, context, slot, request, signedInput, parseSource, managementSourceWire);
}

// Separate private v2 byte validator. Its expected binding comes from retained
// original state, never a caller-selected version or translated v1 response.
// This verifies packet consistency only; signer/transport/current-access and
// complete runner reconstruction remain mandatory before any source admission.
export function validateExistingSourceBatch(input, context, slot, request, signedInput, retainedOriginal) {
  const types = existingManagementTypes.source_transfer;
  const parse = (name, value) => { const result = types[name].safeParse(value); check(result.success); return result.data; };
  const expected = parse('source_context', context);
  validateExistingManagementBinding(expected.management.binding, retainedOriginal);
  check(expected.management.management_context_sha256 === delegationDigest(expected.management.binding));
  return validateBatch(input, expected, parse('slot', slot), request, signedInput, parse, existingManagementWires.source_transfer);
}

function validateBatch(input, context, slot, request, signedInput, parse, wire) {
  const value = parse('read_output', input); parse('read_claims', request);
  const originalInput = parse('read_input', signedInput);
  const encoded = originalInput.signed_request.payload_base64, bytes = Buffer.from(encoded, 'base64');
  // The frozen provider vector binds the entire canonical signed input, not
  // decoded claims. The exact sent envelope and its claims must both match.
  check(bytes.length <= wire.limits.registrar_decoded_bytes && bytes.toString('base64') === encoded
    && bytes.equals(Buffer.from(delegationCanonical(request))));
  check(delegationDigest(value.context) === delegationDigest(context) && delegationDigest(value.slot) === delegationDigest(slot)
    && value.request_sha256 === delegationDigest(originalInput) && value.batch_index === request.batch_index
    && delegationDigest(request.context) === delegationDigest(context) && request.transfer_id === slot.transfer_id
    && request.transfer_manifest_sha256 === slot.transfer_manifest_sha256);
  check(slot.manifest_frames === Math.ceil(slot.manifest_bytes / 32768)
    && slot.total_frames === slot.manifest_frames + slot.native_frames
    && ((slot.native_files === 0 && slot.native_frames === 0 && slot.native_bytes === 0)
      || (slot.native_files > 0 && slot.native_frames >= slot.native_files)));
  const first = value.batch_index * 16, count = Math.min(16, slot.total_frames - first);
  check(count > 0 && value.frames.length === count);
  for (let i = 0; i < count; i++) {
    const frame = value.frames[i], index = first + i, manifest = index < slot.manifest_frames;
    const decoded = Buffer.from(frame.data_base64, 'base64');
    try {
      check(frame.index === index && frame.kind === (manifest ? 'manifest' : 'native')
        && decoded.length === frame.bytes && decoded.toString('base64') === frame.data_base64 && digest(decoded) === frame.sha256
        && (!manifest || frame.bytes === Math.min(32768, slot.manifest_bytes - index * 32768)));
    } finally { decoded.fill(0); }
  }
  check(Buffer.byteLength(delegationCanonical(value)) <= wire.limits.private_response_bytes);
  return value;
}

// Only the broker calls this with its recorded recipient and authenticated
// backend response. This is an observation of bytes, never source admission.
export function encryptSourceBatch(value, recipient, header) {
  const ephemeral = generateKeyPairSync('x25519'), publicKey = sourceRecipientKey(recipient.public_key_base64);
  const secret = diffieHellman({ privateKey: ephemeral.privateKey, publicKey });
  const aad = Buffer.from(delegationCanonical(header)), iv = randomBytes(12);
  const key = Buffer.from(hkdfSync('sha256', secret, Buffer.from(recipient.recipient_id), Buffer.concat([Buffer.from(sourceCipherPurpose), Buffer.from(digest(aad), 'hex')]), 32));
  const plaintext = Buffer.from(delegationCanonical(value));
  try {
    const cipher = createCipheriv('aes-256-gcm', key, iv, { authTagLength: 16 }); cipher.setAAD(aad);
    const ciphertext = Buffer.concat([cipher.update(plaintext), cipher.final()]);
    return { algorithm: 'X25519-HKDF-SHA256-AES-256-GCM',
      ephemeral_public_key_base64: ephemeral.publicKey.export({ format: 'der', type: 'spki' }).toString('base64'),
      iv_base64: iv.toString('base64'), tag_base64: cipher.getAuthTag().toString('base64'),
      ciphertext_base64: ciphertext.toString('base64') };
  } finally { secret.fill(0); key.fill(0); plaintext.fill(0); }
}
