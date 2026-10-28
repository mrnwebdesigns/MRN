import { createPublicKey } from 'node:crypto';
import { jwtVerify } from 'jose';
import { z } from 'zod';
import { digest, OpsError, requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { existingWorkerUrl, parseExistingWorkerRecord } from './existing-management-worker-contract.mjs';
import { existingBootstrapUrl } from './existing-worker-bootstrap-contract.mjs';

const need = value => requireThat(value, 'MANAGEMENT_EXISTING_WORKER_CONTEXT', 'The original protected worker context is unavailable, changed or expired.');
const trustType = z.object({ repositoryOwnerId: z.string().regex(/^[1-9][0-9]{0,19}$/),
  subject: z.string().min(1).max(1024), environment: z.string().min(1).max(255),
  runnerEnvironment: z.literal('github-hosted') }).strict();
export const parseExistingWorkerTrust = value => { const result = trustType.safeParse(value); need(result.success); return result.data; };
const signerDigest = signer => delegationDigest({ keyId: signer.keyId, connectionId: signer.connectionId,
  publicKey: digest(createPublicKey(signer.privateKey).export({ format: 'der', type: 'spki' })) });

// Shared fixed-audience authorization for private worker metadata reads. The
// returned verifier must run synchronously in the final ledger transaction.
export async function authorizeExistingWorker({ ledger, trust: trustSource, keys, now, monotonic }, authorization, input,
  { deadlineAt = Infinity } = {}, audience = existingWorkerUrl) {
  need([existingWorkerUrl, existingBootstrapUrl].includes(audience));
  const pointer = parseExistingWorkerRecord('pointer', input), trust = parseExistingWorkerTrust(trustSource());
  need(Buffer.byteLength(delegationCanonical(pointer)) <= 512);
  const started = monotonic(), at = now();
  need(deadlineAt === Infinity || (Number.isSafeInteger(deadlineAt) && deadlineAt > at));
  let expired = false, originalDeadline = deadlineAt;
  const within = () => need(!expired && monotonic() >= started && monotonic() - started < 5000
    && now() >= at && now() - at < 5000 && now() < originalDeadline);
  const bounded = async operation => {
    within(); let timer;
    try { return await Promise.race([Promise.resolve().then(operation), new Promise((_, reject) => {
      timer = setTimeout(() => { expired = true; reject(new OpsError('MANAGEMENT_EXISTING_WORKER_CONTEXT', 'The protected worker read expired.')); },
        Math.max(1, Math.min(5000 - (monotonic() - started), 5000 - (now() - at), originalDeadline - now())));
    })]); } finally { clearTimeout(timer); }
  };
  let payload;
  try {
    need(typeof authorization === 'string' && authorization.length <= 12000
      && /^Bearer [A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/.test(authorization));
    ({ payload } = await bounded(() => jwtVerify(authorization.slice(7), keys, {
      issuer: 'https://token.actions.githubusercontent.com', audience,
      algorithms: ['RS256'], requiredClaims: ['sub', 'exp', 'iat', 'nbf', 'jti'], maxTokenAge: '5m',
      clockTolerance: 0, currentDate: new Date(now()),
    })));
  } catch { throw new OpsError('MANAGEMENT_WORKER_AUTH', 'The protected workflow identity is invalid or unavailable.'); }
  within();
  const initial = ledger.originalWorkerDispatch(pointer.dispatch_id), retained = initial.value.retained;
  const { provenance: p, observation: o } = retained;
  need(pointer.context_sha256 === delegationDigest(retained.context));
  originalDeadline = Math.min(deadlineAt, o.original_expires_at, o.requested_at + 300000);
  const keyDigest = signerDigest(ledger.signer);
  const identityMatches = () => {
    within(); const second = Math.floor(now() / 1000);
    need(payload.aud === audience && payload.sub === trust.subject && payload.repository === p.repository
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
  await bounded(() => ledger.verifyStaff(initial.current.original.mapping.actor));
  identityMatches();
  return { pointer, retained, payload, originalDeadline, assertActive: identityMatches, verifyCurrent: current => {
    need(current.historyDigest === initial.historyDigest && current.current.revision === initial.current.revision
      && current.current.original.digest === initial.current.original.digest
      && delegationDigest(parseExistingWorkerTrust(trustSource())) === delegationDigest(trust)
      && signerDigest(ledger.signer) === keyDigest);
    identityMatches();
  }, trust };
}
