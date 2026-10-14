import { z } from 'zod';
import { digest, OpsError, requireThat } from './contracts.mjs';
import { normalizeSiteUrl } from '../../../stack/scripts/mrn-fleet-update.mjs';

export const contentKinds = ['posts', 'pages'];
const kindSchema = z.enum(contentKinds);
const positiveId = z.number().int().positive().safe();
const patchSchema = z.object({
  post_title: z.string().min(1).max(500).optional(), post_content: z.string().max(32768).optional(),
  post_excerpt: z.string().max(4000).optional(), post_name: z.string().regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/).max(200).optional(),
  post_status: z.enum(['draft', 'pending', 'private', 'publish']).optional(),
}).strict().refine(patch => Object.keys(patch).length > 0, 'Specify a content change.');
export const contentPatchShape = patchSchema;
const allowedFields = ['post_title', 'post_content', 'post_excerpt', 'post_name', 'post_status'];

// Native REST remains a separate credential type from the MCP Application
// Password. It is resolved inside the service; it never enters a tool argument.
export class NativeContentReader {
  constructor({ token, request = fetch }) { Object.assign(this, { token, request }); }
  async get(path, authorize = async () => {}) {
    await authorize();
    requireThat(/^\/(?:sites\/[1-9][0-9]*|(?:posts|pages)(?:\/[1-9][0-9]*\/[1-9][0-9]*)?(?:\?[^#]*)?)$/.test(path), 'CONTENT_ROUTE', 'Only qualified MainWP content reads are supported.');
    const key = this.token();
    requireThat(typeof key === 'string' && key.length > 0, 'MAINWP_CREDENTIAL_MISSING', 'The central MainWP REST key is unavailable.');
    try {
      const response = await this.request(`https://wpcontrol.mrndev.io/wp-json/mainwp/v2${path}`, {
        method: 'GET', headers: { Authorization: `Bearer ${key}`, Accept: 'application/json' }, redirect: 'error', signal: AbortSignal.timeout(45000),
      });
      requireThat(response.ok, [401, 403].includes(response.status) ? 'MAINWP_ACCESS' : 'CONTENT_UNAVAILABLE', 'MainWP rejected the native content request. No alternative credential or route was used.');
      requireThat(/^application\/json\b/i.test(response.headers.get('content-type') || ''), 'MAINWP_SCHEMA', 'MainWP content must return JSON.');
      const chunks = []; let bytes = 0;
      for await (const chunk of response.body) {
        bytes += chunk.length;
        requireThat(bytes <= 2 * 1024 * 1024, 'CONTENT_LIMIT', 'MainWP content exceeded the bounded response size.'); chunks.push(chunk);
      }
      const body = JSON.parse(Buffer.concat(chunks).toString('utf8'));
      requireThat(body && typeof body === 'object' && (!Object.hasOwn(body, 'success') || [1, true].includes(body.success)), 'CONTENT_UNAVAILABLE', 'MainWP did not confirm the content read.');
      return body.data;
    } catch (error) {
      if (error instanceof OpsError) throw error;
      throw new OpsError('CONTENT_UNAVAILABLE', 'MainWP content read failed. No retry or alternate access was attempted.');
    }
  }
  async identity(site, authorize) {
    positiveId.parse(site.id);
    const data = await this.get(`/sites/${site.id}`, authorize);
    requireThat(data && Number(data.id) === site.id && normalizeSiteUrl(data.url) === site.url, 'TARGET_MISMATCH', 'The native content route resolved a different website.');
  }
  async search(site, kind, search = '', maximum = 20, authorize) {
    kindSchema.parse(kind); z.string().max(200).parse(search); z.number().int().min(1).max(50).parse(maximum);
    await this.identity(site, authorize);
    const query = new URLSearchParams({ websites: String(site.id), maximum: String(maximum), search, search_on: 'title' });
    const data = await this.get(`/${kind}?${query}`, authorize);
    requireThat(data && typeof data === 'object' && !Array.isArray(data), 'MAINWP_SCHEMA', 'Invalid MainWP content search response.');
    const items = [];
    for (const [url, rows] of Object.entries(data)) {
      requireThat(normalizeSiteUrl(url) === site.url && Array.isArray(rows), 'TARGET_MISMATCH', 'Content search included another website.');
      for (const row of rows) {
        requireThat(Number(row.website_id) === site.id && row.post_type === (kind === 'pages' ? 'page' : 'post'), 'TARGET_MISMATCH', 'Content search returned a different record type or site.');
        const id = positiveId.parse(Number(row.id));
        items.push({ id, title: typeof row.title === 'string' ? row.title.slice(0, 500) : null, status: row.status, type: row.post_type });
      }
    }
    requireThat(items.length <= maximum && new Set(items.map(i => i.id)).size === items.length, 'MAINWP_SCHEMA', 'Content search returned too many or duplicate records.');
    return { site, kind, items, limit: maximum, complete: items.length < maximum, observedAt: new Date().toISOString() };
  }
  async read(site, kind, recordId, authorize) {
    kindSchema.parse(kind); positiveId.parse(recordId); await this.identity(site, authorize);
    const data = await this.get(`/${kind}/${site.id}/${recordId}`, authorize);
    requireThat(data && Number(data.id) === recordId && Number(data.website_id) === site.id
      && data.post_type === (kind === 'pages' ? 'page' : 'post'), 'TARGET_MISMATCH', 'MainWP returned a different content record.');
    const fields = {};
    for (const name of allowedFields) if (Object.hasOwn(data, name)) {
      requireThat(typeof data[name] === 'string', 'MAINWP_SCHEMA', 'Invalid content field value.'); fields[name] = data[name];
    }
    // Do not expose password, upload paths, arbitrary metadata or credentials.
    const custom = data.post_custom;
    const customFieldsPresent = custom && typeof custom === 'object' && Object.keys(custom).length > 0;
    return { site, kind, recordId, fields, snapshotSha256: digest(data), observedAt: new Date().toISOString(),
      capabilities: { readableFields: Object.keys(fields), acfQualified: false, menuQualified: false, mediaQualified: false },
      ...(customFieldsPresent ? { contentOwnership: 'Custom fields are present. Empty post_content may mean the page is authored in ACF or a builder; use a qualified field adapter before editing that content.' } : {}) };
  }
}

export function contentPreview(snapshot, changes) {
  const patch = patchSchema.parse(changes);
  const diff = [];
  for (const [field, after] of Object.entries(patch)) {
    requireThat(Object.hasOwn(snapshot.fields, field), 'CONTENT_FIELD_UNAVAILABLE', 'MainWP did not return the existing value for this field. Its exact change cannot be previewed safely.');
    const before = snapshot.fields[field];
    if (before !== after) diff.push({ field, before, after });
  }
  requireThat(diff.length > 0, 'CONTENT_UNCHANGED', 'The requested content is already present.');
  return { kind: 'content', description: `Update ${snapshot.kind === 'pages' ? 'page' : 'post'} ${snapshot.recordId} on ${snapshot.site.url}`,
    site: snapshot.site, contentKind: snapshot.kind, recordId: snapshot.recordId, expectedSha256: snapshot.snapshotSha256,
    changes: patch, diff, readAt: snapshot.observedAt };
}
