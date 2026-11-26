// Explicit disposable-only sidecar, separate from the frozen actor/profile
// descriptor. No URL, identity, signer or executable path can be selected by a
// chat caller, provider response or production configuration.
import { join, resolve } from 'node:path';
import { z } from 'zod';

export function parseExistingBindingAccess(bytes, directory) {
  if (!Buffer.isBuffer(bytes) || bytes.length > 4096 || typeof directory !== 'string' || directory !== resolve(directory))
    throw new Error('Invalid disposable existing access sidecar');
  const root = join(directory, 'existing-binding-v2');
  const schema = z.object({ schema: z.literal(1), mode: z.literal('disposable-existing-management-access-v2'),
    production_credentials: z.literal(false), production_activation: z.literal(false),
    registrar_key_id: z.literal('fixture-registrar'), registrar_signer: z.literal(join(root, 'registrar.der')),
    callback_path: z.literal('/internal/mainwp/management-existing-access-v2'),
    callback_socket: z.literal(join(root, 'callback-socket/callback.sock')), route_switch: z.literal(join(root, 'switch-callback')) }).strict();
  try { return schema.parse(JSON.parse(bytes.toString('utf8'))); }
  catch { throw new Error('Invalid disposable existing access sidecar'); }
}
