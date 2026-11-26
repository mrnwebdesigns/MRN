import { createPublicKey, verify } from 'node:crypto';
import { TextDecoder } from 'node:util';
import { z } from 'zod';
import { digest, requireThat, sha } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { managementContract, managementTypes, validateManagementEvidence } from './content-management-contract.mjs';

const signerSchema = z.object({ keyId: z.string().regex(/^[A-Za-z0-9_-]{1,64}$/),
  publicKeyBase64: z.string().length(44), engineCommit: z.string().regex(/^[a-f0-9]{40}$/) }).strict();
const runSchema = z.object({ id: z.number().int().positive().safe(), requestId: z.string().regex(/^[a-f0-9]{32}$/),
  reportSha256: sha }).strict();

// Signature/context verification only. The service must obtain expected scope,
// signer and run/report identity from its independent protected provenance and
// admitted profile checks, never from tool arguments. This module has no signer,
// dispatch, profile admission or write registration. Frozen content-v1 receipts
// and qualification captures are not management evidence.
export function verifyManagementReceipt({ envelope, evidence, expected, signer, run, now = Date.now() }) {
  requireThat(Number.isSafeInteger(now) && now > 0, 'QA_CLOCK', 'A valid current receipt-verification time is required.');
  signer = signerSchema.parse(signer); run = runSchema.parse(run);
  const { review } = validateManagementEvidence(evidence, { ...expected, now });
  envelope = managementTypes.qa_envelope.parse(envelope);
  requireThat(Buffer.byteLength(delegationCanonical(envelope)) <= 16384, 'QA_SIZE', 'Management receipt exceeds its wire limit.');
  const key = Buffer.from(signer.publicKeyBase64, 'base64'), bytes = Buffer.from(envelope.payload_base64, 'base64');
  const signature = Buffer.from(envelope.signature_base64, 'base64');
  requireThat(key.length === 32 && key.toString('base64') === signer.publicKeyBase64 && envelope.key_id === signer.keyId,
    'QA_KEY', 'The management receipt uses a different protected signer.');
  requireThat(bytes.toString('base64') === envelope.payload_base64 && signature.length === 64
    && signature.toString('base64') === envelope.signature_base64
    && verify(null, Buffer.concat([Buffer.from(managementContract.qa_purpose), bytes]),
      createPublicKey({ key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'), key]), type: 'spki', format: 'der' }), signature),
  'QA_SIGNATURE', 'Management requires its distinct protected QA purpose. Other content or code receipts cannot substitute.');
  const claims = managementTypes.qa_claims.parse(JSON.parse(new TextDecoder('utf-8', { fatal: true }).decode(bytes)));
  requireThat(bytes.equals(Buffer.from(delegationCanonical(claims))), 'QA_CANONICAL', 'Management claims must use strict canonical JSON.');
  const at = Math.floor(now / 1000);
  requireThat(claims.issued_at <= at + 30 && claims.issued_at >= at - 900 && claims.expires_at > at
    && claims.expires_at > claims.issued_at && claims.expires_at <= claims.issued_at + 900
    && claims.expires_at <= review.expires_at, 'QA_EXPIRED', 'Management QA is expired or outlives the original preview.');
  requireThat(claims.receipt_id === digest(`${run.id}:1:${run.requestId}`).slice(0, 32)
    && claims.engine_commit === signer.engineCommit && claims.qa_report_sha256 === run.reportSha256,
  'QA_PROVENANCE', 'Management QA belongs to another protected execution, engine or report.');
  for (const field of ['site_id', 'site_url', 'before_sha256', 'candidate_sha256', 'rollback_operation_id'])
    requireThat(claims[field] === review[field], 'QA_CONTEXT', 'Management QA belongs to a different site, operation or snapshot.');
  requireThat(claims.plan_digest === review.digest && claims.runtime_url === review.profile.runtime_url
    && claims.profile_sha256 === review.profile.sha256 && claims.runtime_context_sha256 === review.profile.runtime_context_sha256
    && claims.migration_artifact_sha256 === review.migration_artifact.sha256
    && delegationDigest(claims.target) === delegationDigest(review.target)
    && delegationDigest(claims.affected_paths) === delegationDigest(review.affected_paths),
  'QA_CONTEXT', 'Management QA does not prove the exact target, profile, render paths and migration.');
  return { envelope, claims };
}
