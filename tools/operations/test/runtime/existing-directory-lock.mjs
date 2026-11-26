// Diagnostic only: token-cache contention cannot grant membership or authority.
import { z } from 'zod';
const report = z.object({ schema: z.literal(1), scope: z.literal('disposable-directory-token-lock'),
  forced_committed_expiry: z.literal(true), oauth_refresh_in_work: z.literal(true), cache_write_in_work: z.boolean(),
  independent_expired_cache: z.literal(true), independent_lock_refused: z.boolean(), probe_limit_seconds: z.literal(1),
  probe_elapsed_ms: z.number().int().min(0).max(10000), production_changes: z.literal(false), membership_granted_by_probe: z.literal(false) }).strict();
export function parseExistingDirectoryLock(bytes) {
  try {
    if (!Buffer.isBuffer(bytes) || !bytes.length || bytes.length > 2048) throw new Error();
    return report.parse(JSON.parse(bytes.toString('utf8')));
  } catch { throw new Error('Invalid disposable Directory lock probe'); }
}
