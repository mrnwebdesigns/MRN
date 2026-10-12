import { readdirSync, lstatSync, readFileSync, realpathSync } from 'node:fs';
import { join, relative } from 'node:path';
import { normalizeSiteUrl } from '../../../stack/scripts/mrn-fleet-update.mjs';
import { digest, requireThat } from './contracts.mjs';

export const safeLabel = value => typeof value === 'string' ? value.replace(/[\u0000-\u001f\u007f]/g, '').trim().slice(0, 160) : '';
const url = value => { try { return normalizeSiteUrl(value); } catch { return null; } };
const environment = value => ({ dev: 'development', development: 'development', staging: 'staging', live: 'production', production: 'production' })[value] || 'unknown';

// Read only the established direct-child Local Hub manifest. Never walk public/,
// follow symlinks, import credentials, infer SSH access, or execute source data.
export function readLocalHubRecords(roots, authorize) {
  const records = []; const issues = [];
  for (const root of roots) {
    authorize();
    let entries;
    try { entries = readdirSync(root, { withFileTypes: true }); }
    catch { requireThat(false, 'KNOWLEDGE_SOURCE_UNAVAILABLE', 'A configured Local Hub records root is unavailable.'); }
    requireThat(entries.length <= 2000, 'KNOWLEDGE_LIMIT', 'The Local Hub records root exceeds its discovery bound.');
    const actualRoot = realpathSync(root);
    for (const entry of entries.sort((a, b) => a.name.localeCompare(b.name))) {
      if (!entry.isDirectory() || !/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,119}$/.test(entry.name)) continue;
      const file = join(root, entry.name, '.mrn-site.json');
      let stat;
      try { stat = lstatSync(file); } catch (error) { if (error.code === 'ENOENT') continue; throw error; }
      authorize();
      const resolved = realpathSync(file);
      requireThat(stat.isFile() && !stat.isSymbolicLink() && !relative(actualRoot, resolved).startsWith('..') && stat.size <= 1024 * 1024,
        'KNOWLEDGE_SOURCE_INVALID', 'A Local Hub manifest is outside the approved root or exceeds its file bound.');
      let raw;
      try { raw = JSON.parse(readFileSync(resolved, 'utf8')); }
      catch { issues.push({ source: `local-hub:${entry.name}`, code: 'INVALID_MANIFEST' }); continue; }
      if (!raw || typeof raw !== 'object' || Array.isArray(raw)) { issues.push({ source: `local-hub:${entry.name}`, code: 'INVALID_MANIFEST' }); continue; }
      const remoteUrl = url(raw.liveUrl); const localUrl = url(raw.localUrl);
      if (!remoteUrl && !localUrl) { issues.push({ source: `local-hub:${entry.name}`, code: 'NO_SUPPORTED_URL' }); continue; }
      const updated = Date.parse(raw.updatedAt);
      const at = Number.isFinite(updated) && updated <= Date.now() ? updated : stat.mtimeMs;
      records.push({ slug: entry.name, title: safeLabel(raw.title) || entry.name, remoteUrl, localUrl,
        environment: environment(raw.deploymentEnvironment), source: `local-hub:${entry.name}/.mrn-site.json`,
        observedAt: new Date(at).toISOString(), expiresAt: new Date(at + 86400000).toISOString(),
        provider: /^[a-z][a-z0-9-]{0,49}$/.test(raw.provider || '') ? raw.provider : null,
        repository: /^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(raw.deploymentGithubRepo || '') ? raw.deploymentGithubRepo : null,
        branch: /^[A-Za-z0-9_./-]{1,120}$/.test(raw.deploymentGithubBranch || '') ? raw.deploymentGithubBranch : null,
        workflow: /^[A-Za-z0-9_./-]{1,120}\.ya?ml$/.test(raw.deploymentGithubWorkflow || '') ? raw.deploymentGithubWorkflow : null,
        method: ['ssh', 'github-actions', 'github', 'manual'].includes(raw.deploymentMethod) ? raw.deploymentMethod : null });
      requireThat(records.length <= 2000, 'KNOWLEDGE_LIMIT', 'Too many Local Hub manifests.');
    }
  }
  return { records, issues };
}

export function buildKnowledge(mainwpSites, localRecords, observedAt = new Date().toISOString()) {
  const groups = new Map(); const locations = new Map();
  const group = (key, name) => {
    if (!groups.has(key)) groups.set(key, { id: `site-${digest(key).slice(0, 24)}`, name, aliases: [], facts: [], environments: [] });
    return groups.get(key);
  };
  const add = (website, siteUrl, name, source) => {
    if (locations.has(siteUrl) && locations.get(siteUrl).website !== website) {
      for (const env of website.environments) env.knowledgeIssues.push('Conflicting Local Hub environment relationship.');
      locations.get(siteUrl).env.knowledgeIssues.push('Conflicting Local Hub environment relationship.');
      return null;
    }
    let env = locations.get(siteUrl)?.env;
    if (!env) {
      env = { name, url: siteUrl, management: source === 'mainwp' ? 'mainwp' : 'dedicated', backup: name === 'local' ? 'local' : 'unknown',
        sources: [], facts: [], accessRefs: [], knowledgeIssues: [] };
      website.environments.push(env); locations.set(siteUrl, { website, env });
    }
    if (!env.sources.includes(source)) env.sources.push(source);
    return env;
  };
  for (const site of mainwpSites) {
    const w = group(site.url, site.name); const env = add(w, site.url, 'unknown', 'mainwp');
    env.facts.push({ key: 'directory.identity', value: { id: site.id, url: site.url }, source: site.source || 'mainwp:get_sites_basic_v1',
      observedAt, expiresAt: new Date(Date.parse(observedAt) + 300000).toISOString(), kind: 'observed' });
  }
  const remoteEnvironments = new Map();
  for (const record of localRecords) {
    const key = record.remoteUrl || record.localUrl; const w = group(key, record.title);
    w.aliases.push(record.slug, record.title);
    const fact = (key, value) => ({ key, value, source: record.source, observedAt: record.observedAt, expiresAt: record.expiresAt, kind: 'intended' });
    if (record.remoteUrl) {
      const env = add(w, record.remoteUrl, 'unknown', 'local-hub');
      if (env) {
        const names = remoteEnvironments.get(record.remoteUrl) || new Set();
        if (record.environment !== 'unknown') names.add(record.environment);
        remoteEnvironments.set(record.remoteUrl, names);
        for (const field of ['provider', 'repository', 'branch', 'workflow', 'method']) if (record[field]) env.facts.push(fact(`deployment.${field}`, record[field]));
        if (record.environment !== 'unknown') env.facts.push(fact('environment', record.environment));
      }
    }
    if (record.localUrl) {
      const env = add(w, record.localUrl, 'local', 'local-hub');
      if (env) {
        // localUrl is a field assertion, not permission to probe private networks.
        env.facts.push(fact('environment.relationship', { remoteUrl: record.remoteUrl, localUrl: record.localUrl }));
        if (record.localUrl === record.remoteUrl || env.management === 'mainwp') env.knowledgeIssues.push('Conflicting local and remote environment identity.');
      }
    }
  }
  for (const [siteUrl, names] of remoteEnvironments) {
    const env = locations.get(siteUrl).env;
    env.name = names.size === 1 ? [...names][0] : 'unknown';
    if (names.size > 1) env.knowledgeIssues.push('Conflicting environment classifications in existing MRN records.');
  }
  const websites = [...groups.values()];
  for (const w of websites) {
    w.aliases = [...new Set(w.aliases)].sort();
    // More than one environment of the same kind is ambiguous. Retain all URLs
    // and let exact URL selection disambiguate; do not guess from similar names.
    for (const env of w.environments) { env.sources.sort(); env.knowledgeIssues = [...new Set(env.knowledgeIssues)].sort(); }
  }
  return websites;
}
