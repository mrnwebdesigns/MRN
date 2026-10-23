import { createPublicKey } from 'node:crypto';
import { jwtVerify } from 'jose';
import { z } from 'zod';
import { digest, OpsError, requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { OriginalManagementAuthorizationLedger } from './original-management-authorization.mjs';
import { ManagementEvidenceSigner } from './management-evidence-callback.mjs';
import { createManagementWorkerKeySet } from './management-worker-context.mjs';
import { existingWorkerContractSha256, existingWorkerPurpose, existingWorkerUrl, parseExistingWorkerRecord } from './existing-management-worker-contract.mjs';

export const existingManagementWorkerPath = new URL(existingWorkerUrl).pathname;
const need = value => requireThat(value, 'MANAGEMENT_EXISTING_WORKER_CONTEXT', 'The original protected worker context is unavailable, changed or expired.');
const trustType = z.object({ repositoryOwnerId: z.string().regex(/^[1-9][0-9]{0,19}$/),
  subject: z.string().min(1).max(1024), environment: z.string().min(1).max(255),
  runnerEnvironment: z.literal('github-hosted') }).strict();
const parseTrust = value => { const result = trustType.safeParse(value); need(result.success); return result.data; };
const signerDigest = signer => delegationDigest({ keyId: signer.keyId, connectionId: signer.connectionId,
  publicKey: digest(createPublicKey(signer.privateKey).export({ format: 'der', type: 'spki' })) });

// Fixed v2 source-only handler. Startup does not construct it. Neither the
// caller nor OIDC supplies a Google actor, key, original binding or source.
export class ExistingManagementWorkerContext {
  constructor({ ledger, trust, keys = createManagementWorkerKeySet(), now = () => Date.now(), monotonic = () => performance.now() }) {
    need(ledger instanceof OriginalManagementAuthorizationLedger && typeof trust === 'function');
    parseTrust(trust()); Object.assign(this, { ledger, trust, keys, now, monotonic });
  }
  async read(authorization, input, { deadlineAt = Infinity } = {}) {
    const pointer = parseExistingWorkerRecord('pointer', input), trust = parseTrust(this.trust());
    need(Buffer.byteLength(delegationCanonical(pointer)) <= 512);
    const started = this.monotonic(), at = this.now();
    need(deadlineAt === Infinity || (Number.isSafeInteger(deadlineAt) && deadlineAt > at));
    let expired = false, originalDeadline = deadlineAt;
    const within = () => need(!expired && this.monotonic() >= started && this.monotonic() - started < 5000
      && this.now() >= at && this.now() - at < 5000 && this.now() < originalDeadline);
    const bounded = async operation => {
      within(); let timer;
      try { return await Promise.race([Promise.resolve().then(operation), new Promise((_, reject) => {
        timer = setTimeout(() => { expired = true; reject(new OpsError('MANAGEMENT_EXISTING_WORKER_CONTEXT', 'The protected worker read expired.')); },
          Math.max(1, Math.min(5000 - (this.monotonic() - started), 5000 - (this.now() - at), originalDeadline - this.now())));
      })]); } finally { clearTimeout(timer); }
    };
    let payload;
    try {
      need(typeof authorization === 'string' && authorization.length <= 12000
        && /^Bearer [A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/.test(authorization));
      ({ payload } = await bounded(() => jwtVerify(authorization.slice(7), this.keys, {
        issuer: 'https://token.actions.githubusercontent.com', audience: existingWorkerUrl,
        algorithms: ['RS256'], requiredClaims: ['sub', 'exp', 'iat', 'nbf', 'jti'], maxTokenAge: '5m',
        clockTolerance: 0, currentDate: new Date(this.now()),
      })));
    } catch { throw new OpsError('MANAGEMENT_WORKER_AUTH', 'The protected workflow identity is invalid or unavailable.'); }
    within();
    const initial = this.ledger.originalWorkerDispatch(pointer.dispatch_id), retained = initial.value.retained;
    const { provenance: p, observation: o } = retained;
    need(pointer.context_sha256 === delegationDigest(retained.context));
    originalDeadline = Math.min(deadlineAt, o.original_expires_at, o.requested_at + 300000);
    const keyDigest = signerDigest(this.ledger.signer);
    const identityMatches = () => {
      within(); const second = Math.floor(this.now() / 1000);
      need(payload.aud === existingWorkerUrl && payload.sub === trust.subject && payload.repository === p.repository
        && payload.repository_id === p.repository_id && payload.repository_owner === 'mrnwebdesigns'
        && payload.repository_owner_id === trust.repositoryOwnerId && payload.repository_visibility === 'private'
        && payload.run_id === p.run_id && payload.run_attempt === '1' && payload.actor_id === o.app_actor_id
        && payload.workflow_ref === `${p.repository}/${p.workflow_path}@${p.ref}`
        && payload.workflow_sha === p.runner_source_sha && payload.sha === p.runner_source_sha
        && payload.ref === p.ref && payload.ref_type === 'branch' && payload.event_name === 'workflow_dispatch'
        && payload.environment === trust.environment && payload.runner_environment === trust.runnerEnvironment
        && payload.job_workflow_ref === undefined && payload.job_workflow_sha === undefined
        && [undefined, ''].includes(payload.head_ref) && [undefined, ''].includes(payload.base_ref)
        && Number.isSafeInteger(payload.iat) && Number.isSafeInteger(payload.nbf) && Number.isSafeInteger(payload.exp)
        && payload.iat <= second && payload.nbf <= second && payload.exp > second && payload.exp > payload.iat
        && payload.exp - payload.iat <= 300 && payload.iat >= Math.floor(o.requested_at / 1000)
        && typeof payload.jti === 'string' && payload.jti.length > 0 && payload.jti.length <= 200);
    };
    identityMatches();
    await bounded(() => this.ledger.verifyStaff(initial.current.original.mapping.actor));
    identityMatches();
    return this.ledger.withOriginalWorkerDispatch(pointer.dispatch_id, read => {
      const current = read();
      need(current.historyDigest === initial.historyDigest && current.current.revision === initial.current.revision
        && current.current.original.digest === initial.current.original.digest
        && delegationDigest(parseTrust(this.trust())) === delegationDigest(trust)
        && signerDigest(this.ledger.signer) === keyDigest);
      identityMatches();
      const signer = new ManagementEvidenceSigner({ ...this.ledger.signer, now: this.now });
      const claims = parseExistingWorkerRecord('claims', { ...signer.base(Math.min(payload.exp, Math.floor(originalDeadline / 1000))),
        schema: 2, audience: `https://github.com/${p.repository}/actions/runs/${p.run_id}`,
        action: 'read-original-existing-management-dispatch', dispatch_id: pointer.dispatch_id,
        context_sha256: pointer.context_sha256, worker_contract_sha256: existingWorkerContractSha256,
        context: current.value.retained.context, recorded_dispatch: current.value.retained.provenance,
        dispatch_record: current.value.retained.observation });
      need(Buffer.byteLength(delegationCanonical(claims)) <= 48000);
      const response = parseExistingWorkerRecord('output', { signed_context: signer.envelope(claims, existingWorkerPurpose) });
      need(Buffer.byteLength(delegationCanonical(response)) <= 65536);
      const m = current.current.original.mapping;
      this.ledger.store.audit(m.actor, m.site, m.requestId, 'original-worker-context-read', 'clear');
      const final = read();
      need(final.historyDigest === initial.historyDigest && final.current.revision === initial.current.revision
        && delegationDigest(parseTrust(this.trust())) === delegationDigest(trust) && signerDigest(this.ledger.signer) === keyDigest);
      identityMatches(); return response;
    });
  }
}
