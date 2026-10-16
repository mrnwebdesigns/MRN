import { requireThat, digest } from './contracts.mjs';
import { toolNames, validateStatus, withoutSecrets, payload } from './mainwp.mjs';
import { updateEvidence } from './updates.mjs';

export const detailSections = Object.freeze({
  plugins: toolNames.plugins, themes: toolNames.themes, updates: toolNames.updates, ignored_updates: toolNames.ignoredUpdates, security: toolNames.security,
  changes: toolNames.changes, abandoned_plugins: toolNames.abandonedPlugins, abandoned_themes: toolNames.abandonedThemes,
  client: toolNames.siteClient, costs: toolNames.siteCosts, google_fonts: toolNames.fonts,
  backup_policy: toolNames.backupPolicy, deployment_agent: toolNames.deploymentAgent,
  stack_runtime: toolNames.runtime, stack_qualification: toolNames.qualify,
});
export const portfolioSections = Object.freeze({ sites: 'list_sites_v1', clients: 'list_clients_v1', client: 'get_client_v1', client_sites: 'get_client_sites_v1',
  client_costs: 'get_client_costs_v1', client_site_count: 'count_client_sites_v1', client_count: 'count_clients_v1',
  tags: 'list_tags_v1', tag: 'get_tag_v1', tag_sites: 'get_tag_sites_v1', tag_clients: 'get_tag_clients_v1', site_count: 'count_sites_v1',
  updates: 'list_updates_v1', batch_job: 'get_batch_job_status_v1' });
const mapped = new Map([
  ['get_sites_basic_v1', 'list_websites'], ['get_site_v1', 'inspect_website'], ['sync_sites_v1', 'inspect_website'],
  ...Object.values(detailSections).map(name => [name, 'read_website_details']),
  ...['preflight', 'backup', 'backupStatus', 'update', 'rollback'].map(key => [toolNames[key], 'approved_operation']),
  [toolNames.codeInspect, 'inspect_website_code'],
  [toolNames.codePrepare, 'prepare_code_edit'], [toolNames.codeApprove, 'approve_operation'],
  [toolNames.codeExecute, 'execute_operation'],
  [toolNames.codeStatus, 'get_operation (refreshProvider)'], [toolNames.contentStatus, 'get_operation (refreshProvider)'],
  [toolNames.contentPolicy, 'inspect_content_record'], [toolNames.contentInspect, 'inspect_content_record'],
  [toolNames.contentTargetPolicy, 'inspect_content_record'],
  [toolNames.contentRecords, 'search_website_content'], [toolNames.contentRecord, 'read_website_content'],
  [toolNames.contentPrepare, 'prepare_content_edit'], [toolNames.contentMigration, 'prepare_content_edit'],
  [toolNames.contentApprove, 'approve_operation'], [toolNames.contentExecute, 'execute_operation'], [toolNames.contentRollback, 'prepare_rollback'],
  ...Object.values(portfolioSections).map(name => [name, 'read_portfolio_details']),
]);

export async function capabilityInventory(client, authorize, { nativeContentConfigured = false } = {}) {
  await authorize();
  const status = validateStatus(await client.readResource({ uri: 'mainwp://status' }));
  const all = []; const names = new Set(); const cursors = new Set(); let cursor;
  do {
    await authorize(); const page = await client.listTools(cursor ? { cursor } : {});
    requireThat(Array.isArray(page.tools) && all.length + page.tools.length <= 1000, 'MAINWP_SCHEMA', 'Invalid capability directory.');
    for (const tool of page.tools) {
      requireThat(typeof tool.name === 'string' && /^[a-z0-9_]{1,160}$/.test(tool.name) && !names.has(tool.name), 'MAINWP_SCHEMA', 'Duplicate or invalid capability identity.');
      names.add(tool.name); all.push(tool);
    }
    cursor = page.nextCursor;
    requireThat(!cursor || !cursors.has(cursor), 'MAINWP_SCHEMA', 'Repeated capability directory page.');
    if (cursor) cursors.add(cursor);
  } while (cursor);
  await authorize();
  const contentRoutes = {
    record: names.has(toolNames.contentRecord) ? 'structured_mainwp' : nativeContentConfigured ? 'native_rest_route' : 'unavailable',
    search: names.has(toolNames.contentRecords) ? 'structured_mainwp' : nativeContentConfigured ? 'native_rest_route' : 'unavailable',
  };
  const contentAvailability = contentRoutes.record === contentRoutes.search ? contentRoutes.record
    : Object.values(contentRoutes).includes('unavailable') ? 'partially_available' : 'mixed_mainwp_routes';
  return { observedAt: new Date().toISOString(), dashboardHost: status.dashboardHost,
    providerDiscoveredAbilities: status.abilitiesCount, backendExposedTools: all.length,
    coverage: 'The backend permission policy filters its tool directory. Hidden abilities are not callable or inferred from the provider count. A listed workflow still requires its operation-specific qualification.',
    capabilities: all.map(t => ({ capability: t.name, schemaSha256: digest(t.inputSchema),
      workflow: mapped.get(t.name) || null, integration: mapped.has(t.name) ? 'connected_workflow' : 'integration_required',
      backendReadOnly: t.annotations?.readOnlyHint === true,
      ...(!mapped.has(t.name) ? { reason: 'No qualified hosted workflow exposes this backend capability yet.' } : {}) })),
    content: { posts: contentAvailability, pages: contentAvailability, readRoutes: contentRoutes,
      scope: 'Routes reflect backend discovery and central reader configuration. They do not prove current site authorization, endpoint health or write qualification. A structured-reader denial never falls back to REST.',
      acf: names.has(toolNames.contentRecord) ? 'structured_read_available_editing_requires_qualification' : 'qualification_required',
      menus: 'qualification_required', media: 'qualification_required' },
  };
}

export async function portfolioDetails(client, authorize, { section, recordId, page = 1, search = '', types, batchJobId }) {
  requireThat(Object.hasOwn(portfolioSections, section), 'CAPABILITY_DENIED', 'Choose a supported portfolio directory section.');
  requireThat(Number.isSafeInteger(page) && page >= 1 && page <= 100 && typeof search === 'string' && search.length <= 200,
    'MAINWP_SCHEMA', 'Use bounded directory filters.');
  const name = portfolioSections[section];
  const needsRecord = ['client', 'client_sites', 'client_costs', 'client_site_count', 'tag', 'tag_sites', 'tag_clients'].includes(section);
  requireThat(!needsRecord || Number.isSafeInteger(recordId) && recordId > 0, 'TARGET_REQUIRED', 'Choose one exact client or tag ID from the directory.');
  requireThat(needsRecord || recordId === undefined, 'TARGET_MISMATCH', 'This directory section does not accept a record ID.');
  requireThat(section === 'batch_job' ? typeof batchJobId === 'string' && /^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/.test(batchJobId) && page === 1 && !search
    : batchJobId === undefined, 'TARGET_MISMATCH', 'Use one bounded existing batch job ID only with the batch_job section.');
  requireThat(types === undefined || section === 'updates' && Array.isArray(types) && types.length > 0 && types.length <= 4
    && new Set(types).size === types.length && types.every(type => ['core', 'plugins', 'themes', 'translations'].includes(type)),
  'MAINWP_SCHEMA', 'Update-type filters apply only to the updates section.');
  requireThat(section !== 'updates' || !search, 'MAINWP_SCHEMA', 'Aggregate updates do not accept a text search.');
  const args = {};
  if (needsRecord) args[section.startsWith('client') ? 'client_id_or_email' : 'tag_id'] = recordId;
  if (['sites', 'clients', 'tags', 'tag_sites', 'tag_clients', 'updates'].includes(section)) Object.assign(args, { page, per_page: 100 });
  if (['sites', 'clients', 'tags'].includes(section) && search) args.search = search;
  if (section === 'updates' && types) args.types = types;
  if (section === 'batch_job') args.job_id = batchJobId;
  const inventory = await capabilityInventory(client, authorize);
  requireThat(inventory.capabilities.some(c => c.capability === name), 'CAPABILITY_MISSING', 'The approved backend does not expose this directory capability.');
  await authorize();
  const response = await client.callTool({ name, arguments: args }, undefined, { timeout: 60000 });
  await authorize();
  // Parsed by the same sanitized MainWP boundary as exact-site workflows.
  return { section, recordId, page, ...(batchJobId ? { batchJobId } : {}), observedAt: new Date().toISOString(), data: withoutSecrets(payload(response)),
    scope: section === 'updates' ? 'Cached Dashboard update inventory across the portfolio; no child sync was performed. Inspect the exact site freshly before an action.'
      : section === 'batch_job' ? 'Status of an existing Dashboard batch job. This read does not start, resume or retry work.'
        : 'Portfolio directory information. Site inventory and health require an exact-site fresh inspection.' };
}

export async function websiteDetails(session, section) {
  requireThat(Object.hasOwn(detailSections, section), 'CAPABILITY_DENIED', 'Choose one supported website detail section.');
  const site = await session.fresh();
  const tool = detailSections[section];
  const provider = tool.startsWith('mrn_mainwp__');
  const args = section === 'ignored_updates' ? { site_ids_or_domains: [site.id] }
    : provider ? { site_id: site.id } : { site_id_or_domain: site.id };
  if (section === 'google_fonts') args.check_frontend = true;
  const raw = await session.call(tool, args);
  const data = section === 'updates' ? updateEvidence(raw, site.id, site.url) : withoutSecrets(raw);
  return { site, section, observedAt: new Date().toISOString(), data };
}
