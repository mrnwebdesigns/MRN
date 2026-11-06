import { z } from 'zod';
import { requireThat } from './contracts.mjs';
import { managementSchemas, validateManagementRead } from './content-management-contract.mjs';
import { toolNames } from './mainwp.mjs';

const kinds = z.enum(['posts', 'pages']);
export async function readManagedContent(session, site, { kind, recordId, search = '', maximum = 20, page = 1, statuses }) {
  kinds.parse(kind);
  const post_type = kind === 'pages' ? 'page' : 'post';
  const base = { site_id: site.id, site_url: site.url, post_type };
  if (recordId !== undefined) {
    z.number().int().positive().safe().parse(recordId);
    const args = managementSchemas['read-content-record-v1'].input.parse({ ...base, post_id: recordId });
    const result = validateManagementRead(await session.call(toolNames.contentRecord, args),
      { site, target: { kind: 'wordpress-content', post_type, post_id: recordId } });
    return { site, kind, recordId, fields: result.snapshot.core,
      acf: { definitions: result.snapshot.acf_definitions, values: result.snapshot.acf_values },
      snapshotSha256: result.snapshot_sha256, affectedPaths: result.affected_paths, observedAt: new Date().toISOString(),
      capabilities: { readableFields: Object.keys(result.snapshot.core), acfReadable: true,
        acfQualified: false, menuQualified: false, mediaQualified: false },
      contentOwnership: 'Registered ACF fields retain their field keys, layout metadata and row order. Reading them does not qualify a content change; preview and operation-specific QA are still required.' };
  }
  z.number().int().min(1).max(50).parse(maximum);
  const args = managementSchemas['list-content-records-v1'].input.parse({ ...base, page, per_page: maximum, search,
    ...(statuses === undefined ? {} : { statuses }) });
  const result = managementSchemas['list-content-records-v1'].output.parse(await session.call(toolNames.contentRecords, args));
  requireThat(result.site_id === site.id && result.site_url === site.url && result.page === page && result.per_page === maximum
    && result.items.length <= maximum && result.total >= result.items.length && result.total_pages === Math.ceil(result.total / maximum)
    && new Set(result.items.map(item => item.post_id)).size === result.items.length
    && result.items.every(item => item.post_type === post_type && (!statuses || statuses.includes(item.post_status))),
  'CONTENT_MANAGEMENT_MISMATCH', 'The content directory returned a different site, type, status or pagination scope.');
  return { site, kind, items: result.items.map(item => ({ id: item.post_id, title: item.post_title, status: item.post_status,
    type: item.post_type, slug: item.post_name, modifiedGmt: item.modified_gmt })),
  page, limit: maximum, total: result.total, totalPages: result.total_pages,
  complete: page * maximum >= result.total, observedAt: new Date().toISOString() };
}
