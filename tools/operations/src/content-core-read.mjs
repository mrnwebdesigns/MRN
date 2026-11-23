import { readFileSync } from 'node:fs';
import { requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { compileManagementSchema } from './content-management-schema.mjs';
import { contentPaths } from './content-contract.mjs';
import { toolNames } from './mainwp.mjs';

// An opt-in read contract; existing ACF snapshots and write authority are unchanged.
export const coreReadContract = JSON.parse(readFileSync(new URL('../contracts/content-core-read-v1.json', import.meta.url)));
requireThat(coreReadContract.adapter === 'wordpress-core-v1'
  && coreReadContract.limits.maximum_result_bytes === 196608, 'CONTENT_CONTRACT', 'Unsupported core-only read contract.');
const contract = coreReadContract.abilities['read-content-core-v1'];
export const coreReadSchemas = { input: compileManagementSchema(contract.input), output: compileManagementSchema(contract.output) };

export async function readCoreContent(session, site, { kind, recordId }) {
  requireThat(['pages', 'posts'].includes(kind), 'CONTENT_INPUT', 'Choose pages or posts.');
  const postType = kind === 'pages' ? 'page' : 'post';
  const args = coreReadSchemas.input.parse({ site_id: site.id, site_url: site.url, post_type: postType, post_id: recordId });
  const result = coreReadSchemas.output.parse(await session.call(toolNames.contentCore, args));
  const snapshot = result.snapshot;
  requireThat(Buffer.byteLength(delegationCanonical(result)) <= coreReadContract.limits.maximum_result_bytes,
    'CONTENT_SIZE', 'The complete core read exceeds the existing response bound.');
  requireThat(snapshot.site_id === site.id && snapshot.site_url === site.url && snapshot.post_type === postType
    && snapshot.post_id === recordId && result.snapshot_sha256 === delegationDigest(snapshot),
  'CONTENT_CORE_MISMATCH', 'The core read does not match the exact requested site, record or complete snapshot.');
  const basePath = new URL(site.url).pathname.replace(/\/$/, '') + '/';
  const queryKey = postType === 'page' ? 'page_id' : 'p';
  for (const path of result.affected_paths) {
    if (path.includes('?')) requireThat(path === `${basePath}?${queryKey}=${recordId}`,
      'CONTENT_PUBLIC_PATH', 'Only the exact same-site WordPress record reference is accepted.');
    else contentPaths([path], site.url);
  }
  return { site, kind, recordId, view: 'core', coreComplete: true, fields: snapshot.core,
    native: { postType: snapshot.post_type, modifiedGmt: snapshot.modified_gmt },
    acf: { status: 'not_requested' }, snapshotScope: snapshot.scope, snapshotSha256: result.snapshot_sha256,
    affectedPaths: result.affected_paths, observedAt: new Date().toISOString(),
    capabilities: { readableFields: Object.keys(snapshot.core), acfIncluded: false, acfQualified: false,
      menuQualified: false, mediaQualified: false },
    contentOwnership: 'Only native WordPress core properties were requested. ACF and builder content were not inspected. Empty post_content does not imply an empty visible page. This core-only snapshot does not qualify an edit.' };
}
