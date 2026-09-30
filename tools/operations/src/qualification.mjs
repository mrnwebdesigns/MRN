import { z } from 'zod';
import { sha, commit, requireThat } from './contracts.mjs';

// Written through reviewed MRN QA/release enrollment, never accepted from MCP
// arguments. Each record attests one immutable artifact on one environment.
export const qualificationSchema = z.object({ version: z.literal(1), records: z.array(z.object({
  siteUrl: z.string().url(), environment: z.enum(['local', 'development', 'staging', 'production']),
  sourceCommit: commit, artifactSha256: sha, verifiedBy: z.string().min(1),
  verifiedAt: z.string().datetime(), validUntil: z.string().datetime(),
  sourceQa: z.object({ status: z.literal('passed'), reportRef: z.string().min(1), reportSha256: sha }).strict(),
  runtimeQa: z.object({ status: z.literal('passed'), reportRef: z.string().min(1), reportSha256: sha }).strict(),
  // No frontend payload is a reviewed fact, not a default inferred from no files.
  frontend: z.enum(['none', 'immutable-assets']),
  assets: z.array(z.object({ url: z.string().url(), sha256: sha, generationSha256: sha.optional() }).strict()),
}).strict()) }).strict();

export class Qualifications {
  constructor(load) { this.load = load; }
  find(target, plan) {
    const records = qualificationSchema.parse(this.load()).records.filter(r => r.siteUrl === target.url && r.environment === target.environment && r.sourceCommit === plan.sourceCommit && r.artifactSha256 === plan.artifactSha256);
    requireThat(records.length === 1, 'QA_REQUIRED', 'The exact site, source commit and artifact need a unique MRN source/runtime QA qualification record.');
    const record = records[0];
    requireThat(Date.parse(record.verifiedAt) <= Date.now() && Date.parse(record.validUntil) > Date.now(), 'QA_EXPIRED', 'The exact artifact QA qualification is stale.');
    requireThat(record.frontend === 'none' ? record.assets.length === 0 : record.assets.length > 0, 'ASSET_QUALIFICATION', 'Record immutable asset expectations or explicitly attest no frontend payload.');
    for (const asset of record.assets) {
      const url = new URL(asset.url);
      requireThat(url.origin === new URL(target.url).origin && !url.search && !url.hash && !url.username && !url.password && url.pathname.includes((asset.generationSha256 || asset.sha256).slice(0, 12)), 'ASSET_QUALIFICATION', 'Expected assets must use same-origin content-hashed URLs without query-versioning.');
    }
    return record;
  }
}
