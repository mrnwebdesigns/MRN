import { requireThat } from './contracts.mjs';
import { AccessSigner, AccessQueryReader, ContentAccessVeto } from './content-access.mjs';
import { ContentAccessLedger } from './content-access-store.mjs';
import { ManagementAccessSigner, ManagementAccessQueryReader, ManagementAccessVeto } from './content-management-access.mjs';
import { ManagementAccessLedger } from './content-management-access-store.mjs';
import { ExistingManagementAccessSigner, ExistingManagementAccessQueryReader, ExistingManagementAccessVeto,
  ExistingManagementAccessLedger } from './existing-management-access.mjs';

// Service-owned lifecycle only. This neither admits/dispatches qualification
// jobs nor exposes a workspace tool. Production activation is an explicit gate.
export function createContentAccessRuntime(options = {}) {
  return createRuntime(options, AccessSigner, AccessQueryReader, ContentAccessVeto, ContentAccessLedger);
}

// Source-only opt-in for the separate management path. main.mjs does not
// register it; native callback qualification and production approval are pending.
export function createManagementAccessRuntime(options = {}) {
  return createRuntime(options, ManagementAccessSigner, ManagementAccessQueryReader, ManagementAccessVeto, ManagementAccessLedger);
}

// This route uses the already observed original queue. It cannot initialize a
// legacy job map from an incoming callback or add itself to production startup.
export function createExistingManagementAccessRuntime(options = {}) {
  requireThat(typeof (options.enabled ?? false) === 'boolean', 'ACCESS_CONFIG', 'Explicit private control configuration is required.');
  if (!options.enabled) return Promise.resolve(null);
  const ledger = new ExistingManagementAccessLedger(options.ledger);
  return createRuntime(options, ExistingManagementAccessSigner, ExistingManagementAccessQueryReader,
    ExistingManagementAccessVeto, null, ledger);
}

async function createRuntime({ enabled = false, store, registry, connectionId, identity, connect }, Signer, Reader, Veto, Ledger, existingLedger) {
  requireThat(typeof enabled === 'boolean', 'ACCESS_CONFIG', 'Explicit private control configuration is required.');
  if (!enabled) return null;
  const signer = new Signer(identity || {});
  const client = await connect(); // Independent of a site operation waiting on its callback.
  const reader = new Reader(client);
  let closing = false, closePromise;
  const close = () => {
    if (!closePromise) {
      closing = true; reader.ready = false;
      closePromise = Promise.resolve().then(() => client.close());
    }
    return closePromise;
  };
  try {
    await reader.initialize(); // Correct Dashboard and dedicated POST ability before listening.
    const ledger = existingLedger ?? new Ledger(store, { connectionId, registry });
    const handler = new Veto({ signer, reader, ledger });
    const veto = { async respond(input) {
      requireThat(!closing, 'ACCESS_UNAVAILABLE', 'Private access control is closed.');
      const result = await handler.respond(input);
      requireThat(!closing, 'ACCESS_UNAVAILABLE', 'Private access control closed before return.');
      return result;
    } };
    return { ledger, veto, close };
  } catch (error) {
    await close().catch(() => {});
    throw error;
  }
}
