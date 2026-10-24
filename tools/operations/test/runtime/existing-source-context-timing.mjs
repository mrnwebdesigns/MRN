// Disposable timing evidence only; no source, access or admission proof.
import { z } from 'zod';

const phases = ['authenticate', 'authenticated-current', 'original-guard', 'guard-current', 'claim',
  'precommit-current', 'first-history', 'post-first-history-current', 'reauthenticate', 'post-reauth-current',
  'output-current', 'access-veto', 'post-veto-current', 'final-history', 'release'];
const microseconds = z.number().int().min(0).max(120000000);
const attemptType = z.object({ schema: z.literal(2), outcome: z.enum(['returned', 'refused']),
  claim_committed: z.boolean(), elapsed_us: microseconds,
  phases: z.array(z.object({ phase: z.enum([...phases, 'finished']), elapsed_us: microseconds }).strict()).min(2).max(16),
}).strict().refine(attempt => {
  let previous = 0;
  return attempt.phases.every((row, index) => {
    const valid = row.phase === (index === attempt.phases.length - 1 ? 'finished' : phases[index])
      && row.elapsed_us >= previous && row.elapsed_us <= attempt.elapsed_us;
    previous = row.elapsed_us; return valid;
  }) && previous === attempt.elapsed_us
    && (attempt.outcome !== 'returned' || (attempt.phases.length === 16 && attempt.claim_committed));
});
const reportType = z.object({ schema: z.literal(2), scope: z.literal('disposable-existing-source-context-phase-timing'),
  available: z.boolean(), attempts: z.array(attemptType).max(8),
  current_access_proven: z.literal(false), production_changes: z.literal(false),
}).strict().refine(report => report.available || report.attempts.length === 0);

export function parseExistingSourceContextTiming(bytes) {
  try {
    if (!Buffer.isBuffer(bytes) || bytes.length === 0 || bytes.length > 32768) throw new Error();
    return reportType.parse(JSON.parse(bytes.toString('utf8')));
  } catch { throw new Error('Invalid disposable source-context timing report'); }
}
