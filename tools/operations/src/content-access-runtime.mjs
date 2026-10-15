import { requireThat } from './contracts.mjs';
import { AccessSigner, AccessQueryReader, ContentAccessVeto } from './content-access.mjs';
import { ContentAccessLedger } from './content-access-store.mjs';

// Service-owned lifecycle only. This neither admits/dispatches qualification
// jobs nor exposes a workspace tool. Production activation is an explicit gate.
export async function createContentAccessRuntime({ enabled = false, store, registry, connectionId, identity, connect }) {
  requireThat(typeof enabled === 'boolean', 'ACCESS_CONFIG', 'Explicit private control configuration is required.');
  if (!enabled) return null;
  const signer = new AccessSigner(identity || {});
  const client = await connect(); // Independent of a site operation waiting on its callback.
  const reader = new AccessQueryReader(client);
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
    const ledger = new ContentAccessLedger(store, { connectionId, registry });
    const handler = new ContentAccessVeto({ signer, reader, ledger });
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
