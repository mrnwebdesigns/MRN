import { requireThat } from './contracts.mjs';
import { OriginalManagementAuthorizationLedger } from './original-management-authorization.mjs';
import { createManagementWorkerKeySet } from './management-worker-context.mjs';
import { existingBootstrapUrl } from './existing-worker-bootstrap-contract.mjs';
import { authorizeExistingWorker, parseExistingWorkerTrust } from './existing-worker-identity.mjs';

export const existingBootstrapPath = new URL(existingBootstrapUrl).pathname;
const need = value => requireThat(value, 'MANAGEMENT_EXISTING_BOOTSTRAP_DELIVERY',
  'The original bootstrap delivery is unavailable, changed, expired or already consumed.');

// Private source composition only. No production startup or route selects it.
// The opaque pointer cannot choose identity, source, keys or another original.
export class ExistingWorkerBootstrapDelivery {
  constructor({ ledger, trust, keys = createManagementWorkerKeySet(), now = () => Date.now(), monotonic = () => performance.now() }) {
    need(ledger instanceof OriginalManagementAuthorizationLedger && typeof trust === 'function');
    parseExistingWorkerTrust(trust()); Object.assign(this, { ledger, trust, keys, now, monotonic });
  }
  async read(authorization, input, options) {
    const { pointer, trust, verifyCurrent, assertActive } = await authorizeExistingWorker(this, authorization, input, options, existingBootstrapUrl);
    const output = this.ledger.consumeOriginalWorkerBootstrap(pointer.dispatch_id, (current, configuration) => {
      verifyCurrent(current);
      const run = configuration.runPolicy;
      need(run.repository_owner_id === trust.repositoryOwnerId && run.environment === trust.environment
        && run.runner_environment === trust.runnerEnvironment);
    });
    // Slow durable COMMIT may outlive the request. Keep consumption terminal,
    // but never release metadata after the unchanged receipt/token deadline.
    assertActive(); return output;
  }
}
