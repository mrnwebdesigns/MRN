import { createPublicKey, verify } from 'node:crypto';
import { z } from 'zod';
import { digest, requireThat, sha } from './contracts.mjs';
import { codePath } from './code.mjs';
import { branchHead } from './github.mjs';
import { receiptFromZip } from './receipt-zip.mjs';

const commit = z.string().regex(/^[a-f0-9]{40}$/);
const snapshot = z.union([sha, z.literal('absent')]);
const source = z.discriminatedUnion('mode', [z.object({ mode: z.literal('unversioned') }).strict(), z.object({ mode: z.literal('git'),
  repository: z.string().regex(/^https:\/\/github\.com\/mrnwebdesigns\/[A-Za-z0-9_-]+$/), branch: z.string().min(1).max(128), commit }).strict()]);
export const codePolicySchema = z.object({ version: z.literal(1), repository: z.literal('mrnwebdesigns/mrn-code-qa-runner'), workflowCommit: commit,
  actor: z.string().regex(/^[A-Za-z0-9-]+\[bot\]$/), keyId: z.string().regex(/^[A-Za-z0-9_-]{1,64}$/), publicKeyBase64: z.string().length(44), engineCommit: commit,
  profiles: z.array(z.object({ siteUrl: z.string().url().refine(v => /^https:\/\/[^/?#@]+\/$/.test(v)), paths: z.array(codePath).min(1), runtimeUrl: z.string().url().refine(v => /^https:\/\//.test(v)),
    source: z.union([z.object({ mode: z.literal('unversioned') }).strict(), z.object({ mode: z.literal('git'), repository: z.string().regex(/^https:\/\/github\.com\/mrnwebdesigns\/[A-Za-z0-9_-]+$/),
      branch: z.string().regex(/^[A-Za-z0-9][A-Za-z0-9_/-]{0,127}$/), paths: z.record(z.string(), z.string()) }).strict()]),
    sourceAutomation: z.object({ deploymentDisabled: z.literal(true), baseCommit: commit, expiresAt: z.number().int().positive(), evidenceRef: z.string().min(1) }).strict().optional(),
  }).strict()).min(1),
}).strict();
export const codeQaRequest = z.object({ schema: z.literal(1), request_id: z.string().regex(/^[a-f0-9]{32}$/), site_id: z.number().int().positive().safe(),
  site_url: z.string().url(), kind: z.enum(['file', 'rollback']), path: codePath, repository_path: z.string().min(1).max(512),
  expected_sha256: snapshot, new_sha256: snapshot, rollback_operation_id: z.string(), runtime_url: z.string().url(), source,
  payload_base64: z.string().max(43692) }).strict();
const claimsSchema = z.object({ schema: z.literal(1), issuer: z.literal('mrn-qa-engine'), audience: z.literal('https://wpcontrol.mrndev.io'),
  result: z.literal('Pass'), mode: z.literal('release'), smoke_strict: z.literal(true), issued_at: z.number().int(), expires_at: z.number().int(),
  receipt_id: z.string().regex(/^[a-f0-9]{32}$/), site_id: z.number().int().positive(), site_url: z.string(), kind: z.enum(['file', 'rollback']),
  path: z.string(), expected_sha256: snapshot, new_sha256: snapshot, rollback_operation_id: z.string(), qa_report_sha256: sha, engine_commit: commit,
  runtime_url: z.string(), runtime_sha256: snapshot, source: z.union([z.object({ mode: z.literal('unversioned') }).strict(),
    z.object({ mode: z.literal('git'), repository: z.string(), branch: z.string(), commit, repository_path: z.string(), clean: z.literal(true), remote_verified: z.literal(true) }).strict()]) }).strict();
const receiptSchema = z.object({ key_id: z.string().regex(/^[A-Za-z0-9_-]{1,64}$/), payload_base64: z.string().max(12288), signature_base64: z.string().max(88) }).strict();

export function verifyCodeReceipt(envelope, request, policy, runId, now = Date.now()) {
  envelope = receiptSchema.parse(envelope); request = codeQaRequest.parse(request);
  const keyBytes = Buffer.from(policy.publicKeyBase64, 'base64');
  requireThat(keyBytes.length === 32 && keyBytes.toString('base64') === policy.publicKeyBase64 && envelope.key_id === policy.keyId, 'QA_KEY', 'The QA receipt does not use the configured protected signer.');
  const payload = Buffer.from(envelope.payload_base64, 'base64'); const signature = Buffer.from(envelope.signature_base64, 'base64');
  requireThat(payload.toString('base64') === envelope.payload_base64 && signature.length === 64 && signature.toString('base64') === envelope.signature_base64
    && verify(null, Buffer.concat([Buffer.from('mrn-code-qa-v1:'), payload]), createPublicKey({ key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'), keyBytes]), type: 'spki', format: 'der' }), signature),
  'QA_SIGNATURE', 'Protected QA verification failed. Caller assertions cannot replace signed evidence.');
  const claims = claimsSchema.parse(JSON.parse(payload.toString('utf8'))); const at = Math.floor(now / 1000);
  requireThat(claims.issued_at <= at + 30 && claims.issued_at >= at - 900 && claims.expires_at > at && claims.expires_at <= claims.issued_at + 900,
    'QA_EXPIRED', 'The exact QA receipt expired. Qualify the current candidate again.');
  requireThat(claims.receipt_id === digest(`${runId}:1:${request.request_id}`).slice(0, 32) && claims.engine_commit === policy.engineCommit,
    'QA_PROVENANCE', 'The receipt does not match the pinned runner execution.');
  for (const field of ['site_id', 'site_url', 'kind', 'path', 'expected_sha256', 'new_sha256', 'rollback_operation_id', 'runtime_url'])
    requireThat(claims[field] === request[field], 'QA_CONTEXT', 'The QA receipt belongs to different site, bytes or operation.');
  const expectedSource = request.source.mode === 'git' ? { ...request.source, repository_path: request.repository_path, clean: true, remote_verified: true } : { mode: 'unversioned' };
  requireThat(digest(claims.source) === digest(expectedSource) && claims.runtime_sha256 === request.new_sha256, 'QA_SOURCE', 'The QA receipt does not prove the exact merged source or snapshot.');
  return { envelope, claims };
}

export class ProtectedCodeQa {
  constructor(github, policy) { this.github = github; this.policy = policy; }
  qualifyRequest(input) {
    const request = codeQaRequest.parse(input); const bytes = Buffer.from(request.payload_base64, 'base64');
    requireThat(bytes.toString('base64') === request.payload_base64 && bytes.length <= 32768
      && (request.new_sha256 === 'absent' ? request.kind === 'rollback' && bytes.length === 0 : digest(bytes) === request.new_sha256), 'QA_PAYLOAD', 'QA must receive the exact bounded candidate bytes.');
    requireThat(request.kind === 'rollback' ? /^[a-f0-9-]{36}$/.test(request.rollback_operation_id) : request.rollback_operation_id === '', 'QA_ROLLBACK', 'Rollback qualification requires the exact completed provider operation.');
    const profiles = this.policy.profiles.filter(p => p.siteUrl === request.site_url && p.paths.includes(request.path));
    requireThat(profiles.length === 1, 'QA_PROFILE_UNQUALIFIED', 'No qualified protected runtime profile covers this site and file. Company pilot QA cannot be applied to other sites.');
    const profile = profiles[0];
    requireThat(profile.runtimeUrl === request.runtime_url && profile.source.mode === request.source.mode, 'QA_PROFILE_MISMATCH', 'The trusted source and runtime profile cannot be changed by the caller.');
    if (profile.source.mode === 'git') {
      const roots = Object.keys(profile.source.paths).filter(root => request.path.startsWith(root));
      requireThat(roots.length === 1 && profile.source.repository === request.source.repository && profile.source.branch === request.source.branch
        && profile.source.paths[roots[0]] + request.path.slice(roots[0].length) === request.repository_path, 'QA_SOURCE', 'The candidate does not match its configured repository mapping.');
    } else requireThat(request.repository_path === request.path, 'QA_SOURCE', 'Snapshot qualification must preserve the exact live path.');
    return request;
  }
  async dispatch(input, authorize, checkpoint) {
    const request = this.qualifyRequest(input); const api = await this.github.scoped(this.policy.repository, 'qa', authorize);
    const head = await branchHead(api, 'main');
    requireThat(head === this.policy.workflowCommit, 'QA_RUNNER_CHANGED', 'The protected workflow changed and needs service qualification.');
    const workflow = await api('/actions/workflows/code-qa.yml');
    requireThat(workflow.path === '.github/workflows/code-qa.yml' && workflow.state === 'active' && Number.isSafeInteger(workflow.id), 'QA_WORKFLOW', 'The expected protected workflow is unavailable.');
    const encoded = Buffer.from(JSON.stringify(request)); requireThat(encoded.length <= 48000, 'QA_SIZE', 'The qualification request is too large.');
    await checkpoint({ status: 'qa_dispatching', requestId: request.request_id, workflowCommit: head, workflowId: workflow.id });
    const run = await api('/actions/workflows/code-qa.yml/dispatches', 'POST', { ref: 'main', inputs: { request: encoded.toString('base64'), request_id: request.request_id } });
    requireThat(Number.isSafeInteger(run.workflow_run_id) && run.workflow_run_id > 0
      && run.html_url === `https://github.com/${this.policy.repository}/actions/runs/${run.workflow_run_id}`, 'QA_DISPATCH_UNKNOWN', 'QA dispatch did not return a verifiable run identity. Do not redispatch.');
    return { runId: run.workflow_run_id, url: run.html_url, requestId: request.request_id, workflowCommit: head, workflowId: workflow.id };
  }
  async status(ticket, authorize) {
    const api = await this.github.scoped(this.policy.repository, 'qaRead', authorize);
    const run = await api(`/actions/runs/${ticket.runId}`);
    requireThat(run.id === ticket.runId && run.run_attempt === 1 && run.repository?.id === this.github.repositories[this.policy.repository]
      && run.repository?.full_name === this.policy.repository && run.head_repository?.id === run.repository.id
      && run.event === 'workflow_dispatch' && run.head_branch === 'main' && run.head_sha === ticket.workflowCommit
      && run.workflow_id === ticket.workflowId && run.path === '.github/workflows/code-qa.yml'
      && run.display_title === `MRN QA ${ticket.requestId}` && run.actor?.login === this.policy.actor && run.triggering_actor?.login === this.policy.actor,
    'QA_PROVENANCE', 'QA status belongs to another run, actor, workflow or request.');
    if (run.status !== 'completed') return { status: 'running', url: ticket.url };
    requireThat(run.conclusion === 'success', 'QA_FAILED', 'The protected QA workflow did not pass. No live change is permitted.');
    const artifacts = await api(`/actions/runs/${ticket.runId}/artifacts?per_page=100`);
    requireThat(Number.isSafeInteger(artifacts.total_count) && artifacts.total_count <= 100 && Array.isArray(artifacts.artifacts), 'QA_ARTIFACT', 'QA artifacts could not be enumerated completely.');
    const found = artifacts.artifacts.filter(a => a.name === `receipt-${ticket.runId}-1`);
    requireThat(found.length === 1 && !found[0].expired && Number.isSafeInteger(found[0].id) && found[0].size_in_bytes <= 65536
      && found[0].workflow_run?.id === ticket.runId && found[0].workflow_run?.head_sha === ticket.workflowCommit, 'QA_ARTIFACT', 'The protected receipt artifact is missing, expired or mismatched.');
    return { status: 'receipt_available', artifactId: found[0].id, url: ticket.url };
  }
  async receipt(ticket, request, authorize) {
    this.qualifyRequest(request);
    const result = await this.status(ticket, authorize);
    if (result.status !== 'receipt_available') return result;
    const api = await this.github.scoped(this.policy.repository, 'qaRead', authorize);
    const envelope = receiptFromZip(await api.artifact(result.artifactId));
    const verified = verifyCodeReceipt(envelope, request, this.policy, ticket.runId);
    return { status: 'qualified', ...verified, url: ticket.url };
  }
}
