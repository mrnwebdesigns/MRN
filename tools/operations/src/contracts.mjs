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
const httpsUrl = z.string().refine(v => { try { return normalizeSiteUrl(v) === v; } catch { return false; } }, 'Use one normalized HTTPS URL without credentials, query, fragment or trailing slash.');
export const registrySchema = z.object({
  version: z.literal(1),
  websites: z.array(z.object({
    id: identifier, name: z.string().min(1).max(160), aliases: z.array(z.string().min(1).max(200)).default([]),
    facts: z.array(fact).default([]),
    environments: z.array(z.object({
      name: z.enum(['local', 'development', 'staging', 'production']), url: httpsUrl,
      management: z.enum(['mainwp', 'dedicated']),
      // An enrollment record is an operator assertion, never something the chat can write.
      coordination: z.object({ exclusiveWriter: z.literal('mrn-operations'), evidenceRef: z.string().min(1), validUntil: z.string().datetime() }).strict().optional(),
      backup: z.enum(['updraft', 'kinsta', 'local']),
      accessRefs: z.array(z.string().min(1)).default([]),
      facts: z.array(fact).default([]),
      qaProject: z.string().optional(),
    }).strict()).min(1),
  }).strict()),
}).strict();
export const actions = ['read', 'test', 'repair', 'deploy_development', 'release_production'];
export const policySchema = z.object({ version: z.literal(1), members: z.array(z.object({
  subject: z.string().min(1), enabled: z.boolean(),
  grants: z.array(z.object({ website: identifier, environments: z.array(z.enum(['local', 'development', 'staging', 'production'])).min(1), actions: z.array(z.enum(actions)).min(1) }).strict()),
}).strict()) }).strict();

export class Registry {
  constructor(loadRegistry, loadPolicy) { this.loadRegistry = loadRegistry; this.loadPolicy = loadPolicy; }
  data() {
    const r = registrySchema.parse(this.loadRegistry());
    const ids = new Set(); const urls = new Set();
    for (const w of r.websites) {
      requireThat(!ids.has(w.id), 'REGISTRY_INVALID', 'Duplicate website identity.'); ids.add(w.id);
      const names = new Set();
      for (const e of w.environments) {
        requireThat(!names.has(e.name) && !urls.has(e.url), 'REGISTRY_INVALID', 'Duplicate environment or URL.');
        names.add(e.name); urls.add(e.url);
      }
    }
    return r;
  }
  authorize(actor, target, action) {
    requireThat(actor?.subject && Number.isFinite(actor.expiresAt) && actor.expiresAt > Date.now(), 'AUTH_REQUIRED', 'Individual authentication is missing or expired.');
    const matches = policySchema.parse(this.loadPolicy()).members.filter(m => m.subject === actor.subject && m.enabled);
    requireThat(matches.length === 1 && matches[0].grants.some(g => g.website === target.websiteId && g.environments.includes(target.environment) && g.actions.includes(action)), 'FORBIDDEN', 'Your account does not have this operation permission for this website and environment.');
  }
  resolve(actor, query, environment, action = 'read') {
    requireThat(typeof query === 'string' && query.trim().length > 0, 'TARGET_REQUIRED', 'Name one website or its exact URL.');
    const q = query.trim().toLowerCase();
    const candidates = [];
    for (const w of this.data().websites) for (const e of w.environments) {
      const t = { ...e, websiteId: w.id, name: w.name, environment: e.name };
      try { this.authorize(actor, t, action); } catch (err) { if (err.code === 'FORBIDDEN') continue; throw err; }
      const labels = [w.id, w.name, ...w.aliases, e.url, new URL(e.url).host].map(s => s.toLowerCase());
      if ((!environment || e.name === environment) && labels.includes(q.replace(/\/$/, ''))) candidates.push(t);
    }
    requireThat(candidates.length > 0, 'TARGET_UNAVAILABLE', 'No accessible exact website/environment matches. Use list_websites or provide its exact URL.');
    requireThat(candidates.length === 1, 'TARGET_AMBIGUOUS', 'More than one accessible environment matches. Specify development, staging, local, or production.');
    return candidates[0];
  }
  current(actor, saved, action) {
    this.authorize(actor, saved, action);
    const current = this.resolve(actor, saved.url, saved.environment, action);
    requireThat(current.websiteId === saved.websiteId && digest(current) === digest(saved), 'TARGET_CHANGED', 'Website configuration changed. Prepare a fresh plan.');
    return current;
  }
  list(actor) {
    const result = [];
    for (const w of this.data().websites) for (const e of w.environments) {
      const target = { websiteId: w.id, environment: e.name };
      try { this.authorize(actor, target, 'read'); } catch (err) { if (err.code === 'FORBIDDEN') continue; throw err; }
      result.push({ websiteId: w.id, name: w.name, environment: e.name, url: e.url, management: e.management,
        facts: [...w.facts, ...e.facts].map(f => ({ ...f, stale: Date.parse(f.expiresAt) <= Date.now() })) });
    }
    return result;
  }
}
export const writeAction = target => target.environment === 'production' ? 'release_production' : 'deploy_development';
export const targetKey = target => target.url; // Aliases and caller labels never create distinct locks.
