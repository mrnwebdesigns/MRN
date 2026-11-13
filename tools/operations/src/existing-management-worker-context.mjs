import { requireThat } from './contracts.mjs';
import { delegationCanonical } from './delegation.mjs';
import { OriginalManagementAuthorizationLedger } from './original-management-authorization.mjs';
import { ManagementEvidenceSigner } from './management-evidence-callback.mjs';
import { createManagementWorkerKeySet } from './management-worker-context.mjs';
import { existingWorkerContractSha256, existingWorkerPurpose, existingWorkerUrl, parseExistingWorkerRecord } from './existing-management-worker-contract.mjs';
import { authorizeExistingWorker, parseExistingWorkerTrust } from './existing-worker-identity.mjs';

export const existingManagementWorkerPath = new URL(existingWorkerUrl).pathname;
const need = value => requireThat(value, 'MANAGEMENT_EXISTING_WORKER_CONTEXT', 'The original protected worker context is unavailable, changed or expired.');

// Fixed v2 source-only handler. Startup does not construct it. Neither the
// caller nor OIDC supplies a Google actor, key, original binding or source.
export class ExistingManagementWorkerContext {
  constructor({ ledger, trust, keys = createManagementWorkerKeySet(), now = () => Date.now(), monotonic = () => performance.now() }) {
    need(ledger instanceof OriginalManagementAuthorizationLedger && typeof trust === 'function');
    parseExistingWorkerTrust(trust()); Object.assign(this, { ledger, trust, keys, now, monotonic });
  }
  async read(authorization, input, options) {
    const { pointer, retained, payload, originalDeadline, verifyCurrent } = await authorizeExistingWorker(this, authorization, input, options);
    const p = retained.provenance;
    return this.ledger.withOriginalWorkerDispatch(pointer.dispatch_id, read => {
      const current = read(); verifyCurrent(current);
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
      verifyCurrent(read()); return response;
    });
  }
}
