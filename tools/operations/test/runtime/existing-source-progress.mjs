// Closed disposable trace only. Completion is never authorization or success.
import { z } from 'zod';
const claim = 'authenticate preflight exclusion original-guard locked-history counter prepare write-veto claim-write commit first-readback release-veto final-readback'.split(' ');
const retention = 'preflight commit-veto frame-write commit first-readback release-veto final-readback'.split(' ');
const sequence = [['capture', 'controller'], ['capture', 'reservation'], ...claim.map(p => ['claim', p]),
  ...['native', 'frames', 'retention'].map(p => ['capture', p]), ...retention.map(p => ['retention', p]), ['capture', 'projection']];
const row = z.object({ ordinal: z.number().int().min(1).max(64), scope: z.enum(['capture', 'claim', 'retention']),
  phase: z.enum([...new Set([...sequence.map(p => p[1]), 'finished'])]), elapsed_us: z.number().int().min(0).max(300000000) }).strict();
const report = z.object({ schema: z.literal(1), scope: z.literal('disposable-existing-source-capture-progress'),
  state: z.enum(['available', 'unavailable', 'malformed']), phases: z.array(row).max(64), handler_finished: z.boolean(),
  current_access_proven: z.literal(false), production_changes: z.literal(false) }).strict().refine(value => {
  if (value.state !== 'available') return value.phases.length === 0 && !value.handler_finished;
  if (!value.phases.length) return false;
  let previous = 0, finished = false;
  const valid = value.phases.every((p, i) => {
    if (finished || p.ordinal !== i + 1 || p.elapsed_us < previous) return false;
    previous = p.elapsed_us;
    finished = p.scope === 'capture' && p.phase === 'finished';
    return finished ? i > 0 : p.scope === sequence[i]?.[0] && p.phase === sequence[i]?.[1];
  });
  return valid && finished === value.handler_finished;
});
export function parseExistingSourceProgress(bytes) {
  try {
    if (!Buffer.isBuffer(bytes) || bytes.length === 0 || bytes.length > 16384) throw new Error();
    return report.parse(JSON.parse(bytes.toString('utf8')));
  } catch { throw new Error('Invalid disposable source-capture progress'); }
}
