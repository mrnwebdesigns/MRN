import { createPublicKey, verify } from 'node:crypto';
import { z } from 'zod';
import { digest, requireThat, sha } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { ProtectedCodeQa } from './code-qa.mjs';
import { contentEnvelopeSchema, contentProfileSchema, contentSnapshotSchema, contentSnapshot, contentTargetSchema, contentSame, contentPaths, contentSchemas, contentPlanDescriptor } from './content-contract.mjs';

const commit = z.string().regex(/^[a-f0-9]{40}$/);
const uuid = z.string().regex(/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
const planOutput = contentSchemas['prepare-content-change-v1'].output.shape;
export const contentQaPolicySchema = z.object({ version: z.literal(1), repository: z.literal('mrnwebdesigns/mrn-code-qa-runner'), workflowCommit: commit,
  actor: z.string().regex(/^[A-Za-z0-9-]+\[bot\]$/), keyId: z.string().regex(/^[A-Za-z0-9_-]{1,64}$/), publicKeyBase64: z.string().length(44), engineCommit: commit,
  profiles: z.array(z.object({ siteUrl: z.string().url(), profile: contentProfileSchema,
    targets: z.array(z.object({ target: contentTargetSchema, selector: z.string().min(1).max(256) }).strict()).min(1) }).strict()).min(1),
}).strict();
export const contentQaRequest = z.object({ schema: z.literal(1), request_id: z.string().regex(/^[a-f0-9]{32}$/), kind: z.literal('content'),
  operation_id: uuid, site_id: z.number().int().positive().safe(), site_url: z.string().url(), target: contentTargetSchema,
  before_snapshot: contentSnapshotSchema, new_value: z.string().max(8192), before_sha256: sha, candidate_sha256: sha, plan_digest: sha,
  rollback_operation_id: z.union([uuid, z.literal('')]), profile: contentProfileSchema, runtime_url: z.string().url(),
  affected_paths: planOutput.affected_paths, stable_selector: planOutput.stable_selector, migration_artifact: planOutput.migration_artifact,
}).strict();
export function contentQaInput(plan, requestId) {
  return contentQaRequest.parse({ schema: 1, request_id: requestId, kind: 'content', operation_id: plan.operation_id,
    site_id: plan.site_id, site_url: plan.site_url, target: plan.target, before_snapshot: plan.before_snapshot,
    new_value: plan.candidate_snapshot[plan.target.field], before_sha256: plan.before_sha256, candidate_sha256: plan.candidate_sha256,
    plan_digest: plan.digest, rollback_operation_id: plan.rollback_operation_id, profile: plan.profile,
    runtime_url: plan.profile.runtime_url, affected_paths: plan.affected_paths, stable_selector: plan.stable_selector, migration_artifact: plan.migration_artifact });
}
export function contentCandidate(request) {
  const before = contentSnapshot(request.before_snapshot), after = contentSnapshot({ ...before, [request.target.field]: request.new_value });
  requireThat(before.site_id === request.site_id && before.site_url === request.site_url && contentSame(before.target, request.target)
    && delegationDigest(before) === request.before_sha256 && delegationDigest(after) === request.candidate_sha256
    && request.before_sha256 !== request.candidate_sha256, 'CONTENT_QA_SNAPSHOT', 'Content QA must receive the exact before and candidate values.');
  const bundle = { schema: 1, migration_id: request.operation_id, site_url: request.site_url, target: request.target, stable_selector: request.stable_selector,
    expected_snapshot_sha256: request.before_sha256, candidate_snapshot: after, candidate_sha256: request.candidate_sha256,
    affected_paths: request.affected_paths, rollback_snapshot_sha256: request.before_sha256, adapter_version: '1' };
  requireThat(request.migration_artifact.ref === `mrn-content://migrations/${request.operation_id}` && delegationDigest(bundle) === request.migration_artifact.sha256,
    'CONTENT_QA_MIGRATION', 'Content QA must reconstruct the exact immutable migration.');
  requireThat(delegationDigest(contentPlanDescriptor(request)) === request.plan_digest, 'CONTENT_QA_PLAN', 'Content QA must bind the exact canonical descriptor.');
  return after;
}
const claimsSchema = z.object({ schema: z.literal(1), issuer: z.literal('mrn-qa-engine'), audience: z.literal('https://wpcontrol.mrndev.io'),
  kind: z.literal('content'), result: z.literal('Pass'), mode: z.literal('release'), smoke_strict: z.literal(true),
  issued_at: z.number().int().safe(), expires_at: z.number().int().safe(), receipt_id: z.string().regex(/^[a-f0-9]{32}$/),
  site_id: z.number().int().positive().safe(), site_url: z.string(), target: contentTargetSchema,
  before_sha256: sha, candidate_sha256: sha, plan_digest: sha, rollback_operation_id: z.union([uuid, z.literal('')]), qa_report_sha256: sha,
  engine_commit: commit, runtime_url: z.string(), runtime_context_sha256: sha, profile_sha256: sha,
  affected_paths: planOutput.affected_paths, migration_artifact_sha256: sha,
}).strict();
export function verifyContentReceipt(envelope, request, policy, runId, now = Date.now()) {
  envelope = contentEnvelopeSchema.parse(envelope); request = contentQaRequest.parse(request); contentCandidate(request);
  requireThat(Buffer.byteLength(JSON.stringify(envelope)) <= 16384, 'QA_SIZE', 'Content receipt exceeds its wire limit.');
  const bytes = Buffer.from(envelope.payload_base64, 'base64'), signature = Buffer.from(envelope.signature_base64, 'base64'), key = Buffer.from(policy.publicKeyBase64, 'base64');
  requireThat(key.length === 32 && key.toString('base64') === policy.publicKeyBase64 && envelope.key_id === policy.keyId, 'QA_KEY', 'The content receipt uses a different protected signer.');
  requireThat(bytes.toString('base64') === envelope.payload_base64 && signature.length === 64 && signature.toString('base64') === envelope.signature_base64
    && verify(null, Buffer.concat([Buffer.from('mrn-content-qa-v1:'), bytes]), createPublicKey({ key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'), key]), type: 'spki', format: 'der' }), signature),
  'QA_SIGNATURE', 'Content requires an independently signed content receipt; code and source-review receipts cannot substitute.');
  const claims = claimsSchema.parse(JSON.parse(bytes.toString('utf8')));
  requireThat(bytes.equals(Buffer.from(delegationCanonical(claims))), 'QA_CANONICAL', 'Content QA claims must use strict canonical JSON.');
  const at = Math.floor(now / 1000);
  requireThat(claims.issued_at <= at + 30 && claims.issued_at >= at - 900 && claims.expires_at > at && claims.expires_at <= claims.issued_at + 900, 'QA_EXPIRED', 'Content qualification expired.');
  requireThat(claims.receipt_id === digest(`${runId}:1:${request.request_id}`).slice(0, 32) && claims.engine_commit === policy.engineCommit, 'QA_PROVENANCE', 'Content QA belongs to a different protected execution.');
  for (const field of ['site_id', 'site_url', 'before_sha256', 'candidate_sha256', 'plan_digest', 'rollback_operation_id', 'runtime_url'])
    requireThat(claims[field] === request[field], 'QA_CONTEXT', 'Content QA belongs to a different operation or snapshot.');
  requireThat(contentSame(claims.target, request.target) && contentSame(claims.affected_paths, request.affected_paths)
    && claims.profile_sha256 === request.profile.sha256 && claims.runtime_context_sha256 === request.profile.runtime_context_sha256
    && claims.migration_artifact_sha256 === request.migration_artifact.sha256, 'QA_CONTEXT', 'Content QA does not prove the exact profile, render paths and migration.');
  return { envelope, claims };
}
export class ProtectedContentQa extends ProtectedCodeQa {
  workflowFile() { return 'content-qa.yml'; }
  runName(id) { return `MRN Content QA ${id}`; }
  verifyReceipt(envelope, request, runId) { return verifyContentReceipt(envelope, request, this.policy, runId); }
  profile(siteUrl, target, descriptor) {
    const matches = this.policy.profiles.filter(p => p.siteUrl === siteUrl && contentSame(p.profile, descriptor));
    requireThat(matches.length === 1 && descriptor.ownership === 'wordpress-post' && descriptor.covered_fields.includes(target.field)
      && descriptor.requires_render_assertions === true, 'CONTENT_PROFILE_UNQUALIFIED', 'MRN services must qualify this exact content ownership and runtime profile.');
    const targets = matches[0].targets.filter(t => contentSame(t.target, target));
    requireThat(targets.length === 1, 'CONTENT_TARGET_UNQUALIFIED', 'This record/field has no qualified rendering assertion.');
    return targets[0];
  }
  qualifyRequest(input) {
    const request = contentQaRequest.parse(input); contentCandidate(request);
    requireThat(request.runtime_url === request.profile.runtime_url && /^https:\/\//.test(request.runtime_url), 'QA_CONTEXT', 'The runtime must match the trusted content profile.');
    this.profile(request.site_url, request.target, request.profile); contentPaths(request.affected_paths, request.site_url);
    requireThat(Buffer.byteLength(JSON.stringify(request)) <= 48000, 'QA_SIZE', 'The exact content request exceeds the protected dispatch size. No content was truncated.');
    return request;
  }
}
