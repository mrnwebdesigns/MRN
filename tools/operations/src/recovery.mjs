import { z } from 'zod';
import { digest, requireThat, sha } from './contracts.mjs';

const evidence = z.object({ reportRef: z.string().min(1), reportSha256: sha }).strict();
// Operator-owned enrollment, never MCP input. A readback cannot prove that a
// timed-out worker, backup, or independent deployment has stopped writing.
export const recoverySchema = z.object({ version: z.literal(1), records: z.array(z.object({
  operationId: z.string().uuid(), operationDigest: sha,
  siteUrl: z.string().url(), environment: z.enum(['local', 'development', 'staging', 'production']),
  verifiedBy: z.string().min(1), verifiedAt: z.string().datetime(), validUntil: z.string().datetime(),
  workersStopped: evidence, downstreamIdle: evidence, independentWritersExcluded: evidence,
}).strict()) }).strict();

export class RecoveryEvidence {
  constructor(load) { this.load = load; }
  find(op) {
    const records = recoverySchema.parse(this.load()).records.filter(r => r.operationId === op.id);
    requireThat(records.length === 1, 'RECOVERY_EVIDENCE_REQUIRED', 'An operator must record unique evidence that the original workers, downstream jobs and competing writers have stopped.');
    const record = records[0];
    requireThat(record.operationDigest === digest(op) && record.siteUrl === op.target.url && record.environment === op.target.environment,
      'RECOVERY_EVIDENCE_MISMATCH', 'Quiescence evidence must bind the current operation snapshot and exact environment.');
    const at = Date.parse(record.verifiedAt); const until = Date.parse(record.validUntil);
    requireThat(at >= Date.parse(op.updatedAt) && at <= Date.now() && until > Date.now() && until <= at + 15 * 60000,
      'RECOVERY_EVIDENCE_EXPIRED', 'Quiescence must be checked after the current operation snapshot, with an expiry no more than 15 minutes later.');
    return record;
  }
}
