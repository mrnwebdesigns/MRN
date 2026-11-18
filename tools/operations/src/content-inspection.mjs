import { createHash, randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { requireThat } from './contracts.mjs';
import { compileManagementSchema } from './content-management-schema.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { contentPaths } from './content-contract.mjs';
import { managementContract } from './content-management-contract.mjs';

export const inspectionContract = JSON.parse(readFileSync(new URL('../contracts/content-inspection-v1.json', import.meta.url)));
const spec = inspectionContract.abilities['read-content-inspection-v1'];
export const inspectionInput = compileManagementSchema(spec.input);
export const inspectionOutput = compileManagementSchema(spec.output);
const insist = condition => requireThat(condition, 'CONTENT_INSPECTION_MISMATCH', 'The bounded inspection does not match the exact record, continuation or complete definition sequence.');
const definitions = structuredClone(managementContract.$defs);
definitions.acf_field.properties.key.pattern = spec.output.$defs.field_metadata.properties.key.pattern;
definitions.acf_values.oneOf[1].propertyNames.pattern = spec.output.$defs.acf_values.oneOf[1].propertyNames.pattern;
const fullSnapshot = compileManagementSchema({ $ref: '#/$defs/snapshot', $defs: definitions });
const uuid = /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/;
const hex = /^[a-f0-9]{64}$/;
const cursorKeys = ['contract', 'site_id', 'site_url', 'post_type', 'post_id', 'inspection_id', 'maximum', 'actor_sha256', 'connection', 'policy', 'snapshot_sha256', 'offset', 'expires_at'].sort();

// Parsing is scope validation only. The provider authenticates the cursor with
// its existing signing root. A cursor never replaces fresh Google delegation.
export function inspectionCursor(cursor, args) {
  requireThat(typeof cursor === 'string' && cursor.length <= 4096 && /^[A-Za-z0-9_-]+\.[a-f0-9]{64}$/.test(cursor),
    'CONTENT_INSPECTION_CURSOR', 'Use the exact continuation from this inspection.');
  let state;
  try {
    const body = cursor.split('.')[0], bytes = Buffer.from(body, 'base64url');
    requireThat(bytes.toString('base64url') === body, 'CONTENT_INSPECTION_CURSOR', 'Noncanonical inspection continuation.');
    state = JSON.parse(bytes.toString('utf8'));
    insist(delegationCanonical(state) === bytes.toString('utf8'));
  } catch { requireThat(false, 'CONTENT_INSPECTION_CURSOR', 'Malformed inspection continuation.'); }
  requireThat(state && typeof state === 'object' && !Array.isArray(state)
    && JSON.stringify(Object.keys(state).sort()) === JSON.stringify(cursorKeys)
    && state.contract === 'content-inspection-v1' && uuid.test(state.inspection_id)
    && ['actor_sha256', 'connection', 'policy', 'snapshot_sha256'].every(key => typeof state[key] === 'string' && hex.test(state[key]))
    && Number.isSafeInteger(state.offset) && state.offset > 0 && state.offset <= 10000
    && Number.isSafeInteger(state.expires_at) && state.expires_at > Date.now() / 1000 && state.expires_at <= Date.now() / 1000 + 300,
  'CONTENT_INSPECTION_CURSOR', 'Expired or invalid inspection continuation.');
  for (const key of ['site_id', 'site_url', 'post_type', 'post_id', 'inspection_id', 'maximum'])
    requireThat(state[key] === args[key], 'CONTENT_INSPECTION_CURSOR', 'Continuation belongs to a different site, record or inspection.');
  return state;
}

function comparePath(a, b) {
  for (let i = 0; i < Math.min(a.length, b.length); i++) {
    if (a[i] === b[i]) continue;
    return typeof a[i] === 'number' && typeof b[i] === 'number' ? a[i] - b[i]
      : a[i] === 'sub_fields' && b[i] === 'layouts' ? -1 : a[i] === 'layouts' && b[i] === 'sub_fields' ? 1 : NaN;
  }
  return a.length - b.length;
}
export function validateInspectionPage(value, args) {
  const result = inspectionOutput.parse(value);
  insist(Buffer.byteLength(delegationCanonical(result)) <= 196608);
  insist(result.snapshot_sha256 === createHash('sha256').update('mrn-content-inspection-v1:'
    + delegationCanonical(result.record) + ':' + result.root_count + ':' + result.total_definitions + ':' + result.definitions_sha256 + ':' + result.source_schema_sha256).digest('hex'));
  if (result.offset === 0 && result.complete) {
    const hash = createHash('sha256');
    for (const entry of result.entries) hash.update(delegationCanonical(entry) + '\n');
    insist(hash.digest('hex') === result.definitions_sha256);
  }
  for (const key of ['site_id', 'site_url', 'post_type', 'post_id']) insist(result.record[key] === args[key]);
  insist(result.inspection_id === args.inspection_id && result.maximum === args.maximum
    && result.entries.length <= args.maximum && result.offset + result.entries.length <= result.total_definitions
    && result.root_count <= result.total_definitions && result.complete === (result.offset + result.entries.length === result.total_definitions)
    && (result.entries.length > 0 || result.total_definitions === 0 && result.offset === 0)
    && (result.next_cursor === null) === result.complete && result.expires_at > Date.now() / 1000
    && result.expires_at <= Date.now() / 1000 + 300);
  if (args.cursor) {
    const prior = inspectionCursor(args.cursor, args);
    insist(result.offset === prior.offset && result.snapshot_sha256 === prior.snapshot_sha256 && result.expires_at === prior.expires_at);
  } else insist(result.offset === 0);
  if (result.next_cursor) {
    const next = inspectionCursor(result.next_cursor, args);
    insist(next.offset === result.offset + result.entries.length && next.snapshot_sha256 === result.snapshot_sha256
      && next.expires_at === result.expires_at);
  }
  let previous;
  for (const [index, node] of result.entries.entries()) {
    insist(node.ordinal === result.offset + index && node.path.length % 2 === 1 && node.path[0] < result.root_count);
    for (let n = 0; n < node.path.length; n++) insist(n % 2 === 0 ? Number.isSafeInteger(node.path[n])
      : ['sub_fields', 'layouts'].includes(node.path[n]));
    insist((node.kind === 'layout') === (node.path.at(-2) === 'layouts')
      && (node.kind === 'layout' ? node.layouts === null && node.sub_fields !== null && !Object.hasOwn(node.definition, 'type')
        : typeof node.definition.type === 'string' && node.definition.type !== 'password'));
    if (previous) insist(comparePath(previous, node.path) < 0);
    previous = node.path;
  }
  const basePath = new URL(args.site_url).pathname.replace(/\/$/, '') + '/';
  for (const path of result.affected_paths) {
    if (path.includes('?')) insist(path === `${basePath}?${args.post_type === 'page' ? 'page_id' : 'p'}=${args.post_id}`);
    else contentPaths([path], args.site_url);
  }
  return result;
}

// This assembles only a single complete bounded page. It never fetches or
// combines continuation pages, and refuses gaps, duplicates and wrong parents.
export function reconstructDefinitions(page) {
  insist(page.offset === 0 && page.complete && page.entries.length === page.total_definitions);
  const roots = new Array(page.root_count), parents = new Map();
  for (const node of page.entries) {
    const value = structuredClone(node.definition);
    for (const key of ['sub_fields', 'layouts']) if (node[key] !== null) value[key] = new Array(node[key]);
    let container = roots, position = node.path[0];
    if (node.path.length > 1) {
      const parent = parents.get(JSON.stringify(node.path.slice(0, -2)));
      insist(parent && Array.isArray(parent[node.path.at(-2)]));
      container = parent[node.path.at(-2)]; position = node.path.at(-1);
    }
    insist(position < container.length && !Object.hasOwn(container, position));
    container[position] = value; parents.set(JSON.stringify(node.path), value);
  }
  const pending = [roots];
  while (pending.length) {
    const items = pending.pop();
    insist(Object.keys(items).length === items.length);
    for (const item of items) for (const key of ['sub_fields', 'layouts']) if (item[key]) pending.push(item[key]);
  }
  return roots;
}

export async function readContentInspection(session, site, { kind, recordId, inspectionId, cursor, definitionLimit = 50 }) {
  requireThat(!cursor || inspectionId, 'CONTENT_INSPECTION_CURSOR', 'A continuation needs its original inspectionId.');
  const args = inspectionInput.parse({ site_id: site.id, site_url: site.url, post_type: kind === 'pages' ? 'page' : 'post',
    post_id: recordId, inspection_id: inspectionId ?? randomUUID(), maximum: definitionLimit, ...(cursor === undefined ? {} : { cursor }) });
  if (cursor) inspectionCursor(cursor, args);
  const page = validateInspectionPage(await session.call('mrn_mainwp__read_content_inspection_v1', args), args);
  const allDefinitions = page.offset === 0 && page.complete;
  let snapshot;
  if (allDefinitions) {
    const candidate = { ...page.record, acf_definitions: reconstructDefinitions(page) };
    const parsed = fullSnapshot.safeParse(candidate);
    if (parsed.success && Buffer.byteLength(delegationCanonical(parsed.data)) <= 196608) snapshot = parsed.data;
  }
  const result = { site, kind, recordId, fields: page.record.core,
    acf: { definitions: snapshot?.acf_definitions ?? null, values: page.record.acf_values,
      ...(snapshot ? {} : { definitionEntries: page.entries, definitionFormat: 'ordered-path-entries-v1', definitionsComplete: allDefinitions }) },
    snapshotSha256: snapshot ? delegationDigest(snapshot) : page.snapshot_sha256, affectedPaths: page.affected_paths,
    inspection: { contract: 'content-inspection-v1', inspectionId: page.inspection_id, snapshotSha256: page.snapshot_sha256,
      definitionsSha256: page.definitions_sha256, sourceSchemaSha256: page.source_schema_sha256, offset: page.offset, returned: page.entries.length, totalDefinitions: page.total_definitions,
      rootCount: page.root_count, endOfDefinitions: page.complete, complete: allDefinitions, nextCursor: page.next_cursor,
      definitionLimit: page.maximum, expiresAt: page.expires_at },
    observedAt: new Date().toISOString(), capabilities: { readableFields: Object.keys(page.record.core), acfReadable: true,
      acfQualified: false, menuQualified: false, mediaQualified: false },
    contentOwnership: 'Read-only inspection, never change authorization. Values include saved nested and disabled rows. Definition pages retain exact native identities and source order; continue with the same inspectionId, definitionLimit and nextCursor. Do not present an incomplete definition sequence as complete or combine pages with different snapshot hashes.' };
  insist(Buffer.byteLength(delegationCanonical(result)) <= 196608);
  return result;
}
