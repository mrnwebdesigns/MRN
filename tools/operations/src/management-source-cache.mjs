import { z } from 'zod';
import { requireThat } from './contracts.mjs';
import { delegationDigest } from './delegation.mjs';
import { compileManagementEvidenceSchema } from './content-management-schema.mjs';
import { managementSourceWire } from './management-source-transfer.mjs';

export const managementSourceRepository = 'mrnwebdesigns/mrn-code-qa-runner';
const id = z.string().regex(/^[1-9][0-9]{0,19}$/), hash = z.string().regex(/^[a-f0-9]{64}$/);
const artifact = compileManagementEvidenceSchema({ $ref: '#/$defs/cache_artifact', $defs: managementSourceWire.$defs });
const entry = z.object({ catalog_artifact: artifact, github_artifact_id: id, run_id: id,
  github_artifact_sha256: hash, github_artifact_bytes: z.number().int().safe().min(22).max(536870912),
  source_archive_bytes: z.number().int().safe().min(22).max(536870912),
  source_sha: z.string().regex(/^[a-f0-9]{40}$/), created_at: z.number().int().safe().positive(),
  expires_at: z.number().int().safe().positive() }).strict();
const registry = z.object({ schema: z.literal(1), repository: z.literal(managementSourceRepository),
  repository_id: id, entries: z.array(entry).max(128) }).strict();
const check = (value, code = 'MANAGEMENT_SOURCE_CACHE') => requireThat(value, code,
  code === 'MANAGEMENT_SOURCE_MISSING_CACHE' ? 'Required original source has no approved central cache artifact.' : 'The approved source cache identity or original access changed.');

// Trusted owner configuration only. No public tool or workflow argument can
// populate this registry, choose a repository or acquire an installation key.
// The catalog root signature and its pre-queue owner pin are prerequisites;
// matching their metadata never establishes actual source-byte coverage.
export class ManagementSourceCacheRegistry {
  constructor({ current, expectedSha256, github, now = () => Date.now() } = {}) {
    check((current === undefined && expectedSha256 === undefined) || (typeof current === 'function' && typeof expectedSha256 === 'function'));
    Object.assign(this, { current, expectedSha256, github, now });
  }
  resolve(input) {
    const parsed = artifact.safeParse(input); check(parsed.success);
    check(this.current, 'MANAGEMENT_SOURCE_MISSING_CACHE');
    const configured = registry.safeParse(this.current()), pinned = this.expectedSha256();
    check(configured.success && hash.safeParse(pinned).success && delegationDigest(configured.data) === pinned);
    const value = configured.data, ids = new Set(), githubIds = new Set();
    for (const item of value.entries) {
      check(!ids.has(item.catalog_artifact.artifact_id) && !githubIds.has(item.github_artifact_id)
        && item.expires_at > item.created_at && item.source_archive_bytes <= item.catalog_artifact.maximum_archive_bytes);
      ids.add(item.catalog_artifact.artifact_id); githubIds.add(item.github_artifact_id);
    }
    const matches = value.entries.filter(item => item.catalog_artifact.artifact_id === parsed.data.artifact_id);
    check(matches.length === 1, 'MANAGEMENT_SOURCE_MISSING_CACHE');
    const selected = matches[0];
    check(delegationDigest(selected.catalog_artifact) === delegationDigest(parsed.data)
      && selected.created_at * 1000 <= this.now() && selected.expires_at * 1000 > this.now());
    return { ...selected, repository_id: value.repository_id, registry_sha256: pinned };
  }
  async acquire(input, { authorize, expiresAt }) {
    check(typeof authorize === 'function' && Number.isSafeInteger(expiresAt) && expiresAt > this.now());
    const selected = this.resolve(input), snapshot = delegationDigest(selected);
    // Authorize always recovers the original Google actor, never caller email.
    const current = async () => {
      check(expiresAt > this.now() && snapshot === delegationDigest(this.resolve(input)));
      await authorize();
      check(expiresAt > this.now() && snapshot === delegationDigest(this.resolve(input)));
    };
    await current();
    return this.github.managementSourceCache(selected, current, { expiresAt });
  }
}
