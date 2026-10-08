import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { z } from 'zod';
import { normalizeSiteUrl } from '../../../stack/scripts/mrn-fleet-update.mjs';

export const digest = value => createHash('sha256').update(typeof value === 'string' || Buffer.isBuffer(value) ? value : canonical(value)).digest('hex');
export function canonical(value) {
  if (Array.isArray(value)) return `[${value.map(canonical).join(',')}]`;
  if (value && typeof value === 'object') return `{${Object.keys(value).sort().map(k => `${JSON.stringify(k)}:${canonical(value[k])}`).join(',')}}`;
  return JSON.stringify(value);
}
export class OpsError extends Error {
  constructor(code, message) { super(message); this.code = code; }
}
export function requireThat(condition, code, message) {
  if (!condition) throw new OpsError(code, message);
}
export const safeError = error => error instanceof OpsError
  ? { code: error.code, message: error.message }
  : { code: 'INTEGRATION_FAILED', message: 'The integration did not complete. Inspect private service diagnostics; no automatic credential or access fallback was attempted.' };
export const readJson = file => JSON.parse(readFileSync(file, 'utf8'));
export const sha = z.string().regex(/^[a-f0-9]{64}$/);
export const commit = z.string().regex(/^[a-f0-9]{40}$/);
const identifier = z.string().regex(/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,119}$/);
const fact = z.object({
  key: identifier, value: z.unknown(), source: z.string().min(1),
  observedAt: z.string().datetime(), expiresAt: z.string().datetime(),
  kind: z.enum(['intended', 'observed']),
}).strict();
export const httpsUrl = z.string().refine(v => { try { return normalizeSiteUrl(v) === v; } catch { return false; } }, 'Use one normalized HTTPS URL without credentials, query, fragment or trailing slash.');
export const environmentNames = ['local', 'development', 'staging', 'production', 'unknown'];
export const registrySchema = z.object({
  version: z.literal(1),
  websites: z.array(z.object({
    id: identifier, name: z.string().min(1).max(160), aliases: z.array(z.string().min(1).max(200)).default([]),
    facts: z.array(fact).default([]),
    environments: z.array(z.object({
      name: z.enum(environmentNames), url: httpsUrl,
      management: z.enum(['mainwp', 'dedicated']),
      // An enrollment record is an operator assertion, never something the chat can write.
      coordination: z.object({ exclusiveWriter: z.literal('mrn-operations'), evidenceRef: z.string().min(1), validUntil: z.string().datetime() }).strict().optional(),
      backup: z.enum(['updraft', 'kinsta', 'local', 'unknown']),
      sources: z.array(z.enum(['mainwp', 'local-hub'])).default([]),
      knowledgeIssues: z.array(z.string()).default([]),
      accessRefs: z.array(z.string().min(1)).default([]),
      facts: z.array(fact).default([]),
      qaProject: z.string().optional(),
    }).strict()).min(1),
  }).strict()),
}).strict();
export const actions = ['read', 'test', 'repair', 'deploy_development', 'release_production'];
export const policySchema = z.object({ version: z.literal(1), members: z.array(z.object({
  subject: z.string().min(1), enabled: z.boolean(),
  // Directory-wide read/test access is explicit and never grants repair/release.
  portfolio: z.object({ sources: z.array(z.enum(['mainwp', 'local-hub'])).min(1), actions: z.array(z.enum(['read', 'test'])).min(1) }).strict().optional(),
  grants: z.array(z.object({ website: z.union([identifier, httpsUrl]), environments: z.array(z.enum(environmentNames)).min(1), actions: z.array(z.enum(actions)).min(1) }).strict()).default([]),
}).strict()) }).strict();

// Observations can refresh without changing an approved target. Configuration,
// identity, management, backup and coordination still participate in its binding.
const targetConfiguration = target => {
  const { facts: _facts, sources: _sources, knowledgeIssues = [], ...configuration } = target;
  return knowledgeIssues.length ? { ...configuration, knowledgeIssues } : configuration;
};

export class Registry {
  constructor(loadRegistry, loadPolicy) { this.loadRegistry = loadRegistry; this.loadPolicy = loadPolicy; this.discovered = new Map(); }
  data(actor) {
    const r = registrySchema.parse(this.loadRegistry());
    if (actor && this.discovered.has(actor.subject)) {
      const snapshot = this.discovered.get(actor.subject);
      requireThat(snapshot.expiresAt > Date.now() && snapshot.policyDigest === digest(this.member(actor)), 'DISCOVERY_STALE', 'Website discovery must be refreshed for the current permissions.');
      const explicitUrls = new Set(r.websites.flatMap(w => w.environments.map(e => e.url)));
      const explicit = [...r.websites];
      for (const website of snapshot.websites) {
        const environments = website.environments.filter(e => !explicitUrls.has(e.url));
        const sameIdentity = explicit.find(w => w.id === website.id);
        requireThat(!sameIdentity || sameIdentity.environments.some(e => website.environments.some(observed => observed.url === e.url)),
          'REGISTRY_INVALID', 'A website override identity must match an observed exact URL.');
        // Existing overrides keep ownership and write configuration. Enrich their
        // exact URLs without treating duplicate registration as a prerequisite.
        for (const known of explicit) for (const env of known.environments) {
          const observed = website.environments.find(e => e.url === env.url);
          if (observed) {
            env.facts.push(...observed.facts); known.aliases = [...new Set([...known.aliases, ...website.aliases])];
            env.sources = [...new Set([...env.sources, ...observed.sources])].sort();
            env.knowledgeIssues = [...new Set([...env.knowledgeIssues, ...observed.knowledgeIssues])].sort();
            if (observed.name !== 'unknown' && env.name !== observed.name) env.knowledgeIssues.push('Environment override conflicts with existing MRN records.');
          }
        }
        if (environments.length) {
          if (sameIdentity) sameIdentity.environments.push(...environments);
          else r.websites.push({ ...website, environments });
        }
      }
    }
    const ids = new Set(); const urls = new Set();
    for (const w of r.websites) {
      requireThat(!ids.has(w.id), 'REGISTRY_INVALID', 'Duplicate website identity.'); ids.add(w.id);
      for (const e of w.environments) {
        requireThat(!urls.has(e.url), 'REGISTRY_INVALID', 'Duplicate environment URL.');
        urls.add(e.url);
      }
    }
    return r;
  }
  member(actor) {
    requireThat(actor?.subject && Number.isFinite(actor.expiresAt) && actor.expiresAt > Date.now(), 'AUTH_REQUIRED', 'Individual authentication is missing or expired.');
    const matches = policySchema.parse(this.loadPolicy()).members.filter(m => m.subject === actor.subject && m.enabled);
    requireThat(matches.length === 1, 'FORBIDDEN', 'Your account does not have access to MRN Operations.');
    return matches[0];
  }
  authorize(actor, target, action) {
    const member = this.member(actor);
    const direct = member.grants.some(g => [target.websiteId, target.url].includes(g.website) && g.environments.includes(target.environment) && g.actions.includes(action));
    const portfolio = member.portfolio?.actions.includes(action) && target.sources?.some(s => member.portfolio.sources.includes(s));
    requireThat(direct || portfolio, 'FORBIDDEN', 'Your account does not have this operation permission for this website and environment.');
    if (!['read', 'test'].includes(action)) requireThat(target.environment !== 'unknown' && !target.knowledgeIssues?.length, 'KNOWLEDGE_REQUIRED', 'Resolve the environment and conflicting website knowledge before preparing a change.');
  }
  resolve(actor, query, environment, action = 'read') {
    requireThat(typeof query === 'string' && query.trim().length > 0, 'TARGET_REQUIRED', 'Name one website or its exact URL.');
    const q = query.trim().toLowerCase();
    const candidates = [];
    for (const w of this.data(actor).websites) for (const e of w.environments) {
      const labels = [w.id, w.name, ...w.aliases, e.url, new URL(e.url).host].map(s => s.toLowerCase());
      if ((environment && e.name !== environment) || !labels.includes(q.replace(/\/$/, ''))) continue;
      const t = { ...e, websiteId: w.id, name: w.name, environment: e.name };
      try { this.authorize(actor, t, action); } catch (err) { if (err.code === 'FORBIDDEN') continue; throw err; }
      candidates.push(t);
    }
    requireThat(candidates.length > 0, 'TARGET_UNAVAILABLE', 'No accessible exact website/environment matches. Use list_websites or provide its exact URL.');
    requireThat(candidates.length === 1, 'TARGET_AMBIGUOUS', 'More than one accessible environment matches. Specify an environment or its exact URL.');
    return candidates[0];
  }
  current(actor, saved, action) {
    this.authorize(actor, saved, action);
    const current = this.resolve(actor, saved.url, saved.environment, action);
    requireThat(current.websiteId === saved.websiteId && digest(targetConfiguration(current)) === digest(targetConfiguration(saved)), 'TARGET_CHANGED', 'Website configuration changed. Prepare a fresh plan.');
    return current;
  }
  recoveryCurrent(actor, saved, action) {
    this.authorize(actor, saved, action);
    const current = this.resolve(actor, saved.url, saved.environment, action);
    // A recovery may outlive its writer-exclusion enrollment. Renewing only
    // that evidence must not strand its lock or rewrite the approved plan.
    const { coordination: _savedCoordination, ...before } = saved;
    const { coordination: _currentCoordination, ...after } = current;
    requireThat(digest(targetConfiguration(before)) === digest(targetConfiguration(after)), 'TARGET_CHANGED', 'Only writer-coordination enrollment may be renewed during recovery; other website configuration changed.');
    return current;
  }
  list(actor) {
    const result = [];
    for (const w of this.data(actor).websites) for (const e of w.environments) {
      const target = { ...e, websiteId: w.id, environment: e.name };
      try { this.authorize(actor, target, 'read'); } catch (err) { if (err.code === 'FORBIDDEN') continue; throw err; }
      result.push({ websiteId: w.id, name: w.name, environment: e.name, url: e.url, management: e.management,
        knowledgeIssues: e.knowledgeIssues, missingKnowledge: [e.name === 'unknown' && 'environment', e.backup === 'unknown' && 'backup route'].filter(Boolean),
        facts: [...w.facts, ...e.facts].map(f => ({ ...f, stale: Date.parse(f.expiresAt) <= Date.now() })) });
    }
    return result;
  }
}
export const writeAction = target => {
  requireThat(target.environment !== 'unknown', 'KNOWLEDGE_REQUIRED', 'Confirm the environment from authoritative MRN records before authorizing a release.');
  return target.environment === 'production' ? 'release_production' : 'deploy_development';
};
export const targetKey = target => target.url; // Aliases and caller labels never create distinct locks.
