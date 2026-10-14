import { requireThat, digest } from './contracts.mjs';
import { toolNames, validateStatus, withoutSecrets, payload } from './mainwp.mjs';
import { updateEvidence } from './updates.mjs';

export const detailSections = Object.freeze({
  plugins: toolNames.plugins, themes: toolNames.themes, updates: toolNames.updates, security: toolNames.security,
  changes: toolNames.changes, abandoned_plugins: toolNames.abandonedPlugins, abandoned_themes: toolNames.abandonedThemes,
  client: toolNames.siteClient, costs: toolNames.siteCosts, google_fonts: toolNames.fonts,
  backup_policy: toolNames.backupPolicy, deployment_agent: toolNames.deploymentAgent,
  stack_runtime: toolNames.runtime, stack_qualification: toolNames.qualify,
});
export const portfolioSections = Object.freeze({ clients: 'list_clients_v1', client: 'get_client_v1', client_sites: 'get_client_sites_v1',
  client_costs: 'get_client_costs_v1', client_site_count: 'count_client_sites_v1', client_count: 'count_clients_v1',
  tags: 'list_tags_v1', tag: 'get_tag_v1', tag_sites: 'get_tag_sites_v1', tag_clients: 'get_tag_clients_v1', site_count: 'count_sites_v1' });
const mapped = new Map([
  ['get_sites_basic_v1', 'list_websites'], ['get_site_v1', 'inspect_website'], ['sync_sites_v1', 'inspect_website'],
  ...Object.values(detailSections).map(name => [name, 'read_website_details']),
  ...['preflight', 'backup', 'backupStatus', 'update', 'rollback'].map(key => [toolNames[key], 'approved_operation']),
  [toolNames.codeInspect, 'inspect_website_code'],
  [toolNames.codePrepare, 'prepare_code_edit'], [toolNames.codeApprove, 'approve_operation'],
  [toolNames.codeExecute, 'execute_operation'],
  ...Object.values(portfolioSections).map(name => [name, 'read_portfolio_details']),
]);

export async function capabilityInventory(client, authorize) {
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
  return { observedAt: new Date().toISOString(), dashboardHost: status.dashboardHost,
    providerDiscoveredAbilities: status.abilitiesCount, backendExposedTools: all.length,
    coverage: 'The backend permission policy filters its tool directory. Hidden abilities are not callable or inferred from the provider count. A listed workflow still requires its operation-specific qualification.',
    capabilities: all.map(t => ({ capability: t.name, schemaSha256: digest(t.inputSchema),
      workflow: mapped.get(t.name) || null, integration: mapped.has(t.name) ? 'connected_workflow' : 'integration_required',
      backendReadOnly: t.annotations?.readOnlyHint === true,
      ...(!mapped.has(t.name) ? { reason: 'No qualified hosted workflow exposes this backend capability yet.' } : {}) })),
    content: { posts: 'native_rest_route', pages: 'native_rest_route', acf: 'qualification_required', menus: 'qualification_required', media: 'qualification_required' },
  };
}

export async function portfolioDetails(client, authorize, { section, recordId, page = 1, search = '' }) {
  requireThat(Object.hasOwn(portfolioSections, section), 'CAPABILITY_DENIED', 'Choose a supported portfolio directory section.');
  requireThat(Number.isSafeInteger(page) && page >= 1 && page <= 100 && typeof search === 'string' && search.length <= 200,
    'MAINWP_SCHEMA', 'Use bounded directory filters.');
  const name = portfolioSections[section];
  const needsRecord = ['client', 'client_sites', 'client_costs', 'client_site_count', 'tag', 'tag_sites', 'tag_clients'].includes(section);
  requireThat(!needsRecord || Number.isSafeInteger(recordId) && recordId > 0, 'TARGET_REQUIRED', 'Choose one exact client or tag ID from the directory.');
  requireThat(needsRecord || recordId === undefined, 'TARGET_MISMATCH', 'This directory section does not accept a record ID.');
  const args = {};
  if (needsRecord) args[section.startsWith('client') ? 'client_id_or_email' : 'tag_id'] = recordId;
  if (['clients', 'tags', 'tag_sites', 'tag_clients'].includes(section)) Object.assign(args, { page, per_page: 100 });
  if (['clients', 'tags'].includes(section) && search) args.search = search;
  const inventory = await capabilityInventory(client, authorize);
  requireThat(inventory.capabilities.some(c => c.capability === name), 'CAPABILITY_MISSING', 'The approved backend does not expose this directory capability.');
  await authorize();
  const response = await client.callTool({ name, arguments: args }, undefined, { timeout: 60000 });
  await authorize();
  // Parsed by the same sanitized MainWP boundary as exact-site workflows.
  return { section, recordId, page, observedAt: new Date().toISOString(), data: withoutSecrets(payload(response)),
    scope: 'Portfolio directory information. Site inventory and health require an exact-site fresh inspection.' };
}

export async function websiteDetails(session, section) {
  requireThat(Object.hasOwn(detailSections, section), 'CAPABILITY_DENIED', 'Choose one supported website detail section.');
  const site = await session.fresh();
  const tool = detailSections[section];
  const provider = tool.startsWith('mrn_mainwp__');
  const args = provider ? { site_id: site.id } : { site_id_or_domain: site.id };
  if (section === 'google_fonts') args.check_frontend = true;
  const raw = await session.call(tool, args);
  const data = section === 'updates' ? updateEvidence(raw, site.id, site.url) : withoutSecrets(raw);
  return { site, section, observedAt: new Date().toISOString(), data };
}
