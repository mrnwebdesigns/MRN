import { createRemoteJWKSet, customFetch, jwtVerify } from 'jose';
import { z } from 'zod';
import { requireThat, OpsError } from './contracts.mjs';
import { delegationDigest, delegationIssuer } from './delegation.mjs';
import { managementEvidenceTypes } from './management-evidence-contract.mjs';
import { ManagementEvidenceSigner } from './management-evidence-callback.mjs';

export const managementWorkerPath = '/internal/mainwp/management-worker-context';
export const managementWorkerAudience = delegationIssuer + managementWorkerPath;
export const managementWorkerPurpose = 'mrn-content-management-worker-context-v1:';
const oidcIssuer = 'https://token.actions.githubusercontent.com';
const jwksUrl = `${oidcIssuer}/.well-known/jwks`;
const check = (value, message) => requireThat(value, 'MANAGEMENT_WORKER_CONTEXT', message);
const pointerSchema = z.object({ dispatch_id: managementEvidenceTypes.uuid, context_sha256: managementEvidenceTypes.hash }).strict();
const trustSchema = z.object({ repositoryOwnerId: managementEvidenceTypes.id,
  subject: z.string().min(1).max(1024), environment: z.string().min(1).max(255),
  runnerEnvironment: z.literal('github-hosted') }).strict();
const parse = (schema, value) => { const result = schema.safeParse(value); check(result.success, 'Invalid protected worker context contract.'); return result.data; };

// Fixed public trust-key endpoint. No token/key URL can be selected by a JWT or
// request. Redirects, oversized bodies and long key fetches are refused.
export function createManagementWorkerKeySet(fetchImpl = fetch) {
  return createRemoteJWKSet(new URL(jwksUrl), { timeoutDuration: 2000, cooldownDuration: 30000, cacheMaxAge: 300000,
    [customFetch]: async url => {
      check(String(url) === jwksUrl, 'Unexpected GitHub signing-key endpoint.');
      const response = await fetchImpl(jwksUrl, { method: 'GET', redirect: 'error', signal: AbortSignal.timeout(2000),
        headers: { Accept: 'application/json', 'Accept-Encoding': 'identity' } });
      check(response.status === 200 && response.headers.get('content-type')?.includes('application/json')
        && ['', 'identity'].includes(response.headers.get('content-encoding') || ''), 'GitHub signing keys are unavailable.');
      const chunks = []; let size = 0;
      for await (const chunk of response.body) { size += chunk.length; check(size <= 65536, 'GitHub signing keys exceed their bound.'); chunks.push(chunk); }
      check(size > 0, 'GitHub signing keys are empty.');
      return new Response(Buffer.concat(chunks), { status: 200, headers: { 'Content-Type': 'application/json' } });
    },
  });
}

// Additive source-only reader. Production startup supplies no trust policy or
// endpoint. Exact subject/environment/owner pins need independent owner review;
// neither the incoming token nor its claims may define that trust configuration.
export class ManagementWorkerContext {
  constructor({ ledger, verifyStaff, identity, trust, keys = createManagementWorkerKeySet(),
    now = () => Date.now(), monotonic = () => performance.now() }) {
    check(typeof trust === 'function' && typeof verifyStaff === 'function', 'Current service trust and original-staff verification are required.');
    parse(trustSchema, trust());
    Object.assign(this, { ledger, verifyStaff, trust, keys, now, monotonic });
    this.signer = new ManagementEvidenceSigner({ ...identity, now });
  }
  async read(authorization, input) {
    const pointer = parse(pointerSchema, input), trust = parse(trustSchema, this.trust());
    const started = this.monotonic(), startedAt = this.now();
    const within = () => check(this.monotonic() >= started && this.monotonic() - started < 5000
      && this.now() >= startedAt && this.now() - startedAt < 5000, 'The protected context read expired.');
    let payload;
    try {
      check(typeof authorization === 'string' && /^Bearer [A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/.test(authorization)
        && authorization.length <= 12000, 'A protected GitHub workflow identity is required.');
      ({ payload } = await jwtVerify(authorization.slice(7), this.keys, { issuer: oidcIssuer, audience: managementWorkerAudience,
        algorithms: ['RS256'], requiredClaims: ['sub', 'exp', 'iat', 'nbf', 'jti'], maxTokenAge: '5m', clockTolerance: 0,
        currentDate: new Date(this.now()) }));
    } catch { throw new OpsError('MANAGEMENT_WORKER_AUTH', 'The protected workflow identity is invalid or unavailable.'); }
    within();
    const original = this.ledger.originalDispatch(pointer.dispatch_id), { provenance: p, observation: o } = original;
    check(pointer.context_sha256 === delegationDigest(original.context), 'The worker pointer does not bind the original context.');
    const expectedRef = `${p.repository}/${p.workflow_path}@${p.ref}`;
    const identityMatches = () => {
      const at = Math.floor(this.now() / 1000);
      check(payload.aud === managementWorkerAudience && payload.sub === trust.subject && payload.repository === p.repository
        && payload.repository_id === p.repository_id && payload.repository_owner === 'mrnwebdesigns'
        && payload.repository_owner_id === trust.repositoryOwnerId && payload.repository_visibility === 'private'
        && payload.run_id === p.run_id && payload.run_attempt === '1' && payload.actor_id === o.app_actor_id
        && payload.workflow_ref === expectedRef && payload.workflow_sha === p.runner_source_sha && payload.sha === p.runner_source_sha
        && payload.ref === p.ref && payload.ref_type === 'branch' && payload.event_name === 'workflow_dispatch'
        && payload.environment === trust.environment && payload.runner_environment === trust.runnerEnvironment
        && payload.job_workflow_ref === undefined && payload.job_workflow_sha === undefined
        && [undefined, ''].includes(payload.head_ref) && [undefined, ''].includes(payload.base_ref)
        && Number.isSafeInteger(payload.exp) && Number.isSafeInteger(payload.iat) && Number.isSafeInteger(payload.nbf)
        && payload.iat <= at && payload.nbf <= at && payload.exp > at && payload.exp > payload.iat && payload.exp - payload.iat <= 300
        && payload.iat >= Math.floor(o.requested_at / 1000)
        && typeof payload.jti === 'string' && payload.jti.length >= 1 && payload.jti.length <= 200,
      'The protected workflow differs from the original run, pinned source, environment or lifetime.');
    };
    identityMatches();
    // Read only; never store the OIDC token or use it as a staff identity. The
    // original Google actor is recovered solely from the immutable job map.
    let timer;
    try {
      await Promise.race([Promise.resolve().then(() => this.verifyStaff(original.mapping.actor)), new Promise((_, reject) => {
        timer = setTimeout(() => {
          try { check(false, 'Original staff verification exceeded the context-read deadline.'); } catch (error) { reject(error); }
        }, Math.max(1, Math.min(5000 - (this.monotonic() - started), o.original_expires_at - this.now())));
      })]);
    } finally { clearTimeout(timer); }
    within(); identityMatches();
    return this.ledger.store.transaction(() => {
      const current = this.ledger.originalDispatch(pointer.dispatch_id);
      check(delegationDigest(parse(trustSchema, this.trust())) === delegationDigest(trust)
        && delegationDigest(current.observation) === delegationDigest(o) && delegationDigest(current.context) === pointer.context_sha256,
      'The original dispatch or current trust changed during readback.');
      within(); identityMatches();
      const claims = { ...this.signer.base(Math.min(payload.exp, Math.floor(o.original_expires_at / 1000))),
        audience: `https://github.com/${p.repository}/actions/runs/${p.run_id}`,
        action: 'read-original-management-dispatch', dispatch_id: pointer.dispatch_id, context_sha256: pointer.context_sha256,
        context: current.context, recorded_dispatch: current.provenance, dispatch_record: current.observation };
      check(claims.issuer === delegationIssuer && Buffer.byteLength(JSON.stringify(claims)) <= 48000,
        'The original context exceeds its bounded format.');
      const response = { signed_context: this.signer.envelope(claims, managementWorkerPurpose) };
      this.ledger.access.audit(current.context.binding.qualification_job_id, 'worker-context-read', '', 'clear', current.revision);
      this.ledger.originalDispatch(pointer.dispatch_id); within(); identityMatches();
      return response;
    });
  }
}
