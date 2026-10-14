import { z } from 'zod';
import { isAbsolute } from 'node:path';
import { readJson, Registry, requireThat } from './contracts.mjs';

const absolute = z.string().refine(isAbsolute, 'Use an absolute service path.');
const secureUrl = z.string().url().refine(s => { const u = new URL(s); return u.protocol === 'https:' && !u.username && !u.password && !u.search && !u.hash; });
export const configSchema = z.object({
  version: z.literal(1), publicUrl: secureUrl.refine(s => new URL(s).pathname === '/mcp'),
  listen: z.object({ host: z.literal('127.0.0.1'), port: z.number().int().min(1024).max(65535) }).strict(),
  auth: z.object({ issuer: secureUrl, audience: z.string().min(1), jwksUrl: secureUrl, verifiedEmailClaims: z.boolean().default(false),
    googleWorkspace: z.object({ connectionId: z.string().regex(/^con_[A-Za-z0-9]+$/) }).strict().optional(),
  }).strict(),
  allowedOrigins: z.array(secureUrl).default([]),
  registryPath: absolute.optional(), policyPath: absolute, qualificationsPath: absolute, stateDir: absolute, repositoryRoot: absolute,
  discovery: z.object({ localHubRoots: z.array(absolute).max(10).default([]) }).strict().optional(),
  recoveryEvidencePath: absolute.optional(),
  artifactRoots: z.array(absolute).min(1), writesEnabled: z.boolean().default(false),
  sourceRepositories: z.record(z.string(), absolute),
  delegation: z.object({ keyId: z.string().regex(/^[A-Za-z0-9_-]{1,64}$/), privateKeyPath: absolute }).strict().optional(),
  content: z.object({ restKeyEnv: z.string().regex(/^[A-Z][A-Z0-9_]*$/) }).strict().optional(),
  github: z.object({ appId: z.string().regex(/^[0-9]+$/), installationId: z.number().int().positive().safe(), privateKeyPath: absolute,
    repositories: z.record(z.string().regex(/^mrnwebdesigns\/[A-Za-z0-9_-]+$/), z.number().int().positive().safe()) }).strict().optional(),
  code: z.object({ policyPath: absolute }).strict().optional(),
  mainwp: z.object({ command: absolute, args: z.array(z.string()).min(1), cwd: absolute,
    envRefs: z.record(z.string(), z.object({ env: z.string() }).strict()) }).strict(),
  qa: z.object({ executable: absolute }).strict().optional(),
}).strict().refine(config => config.auth.audience === config.publicUrl, { path: ['auth', 'audience'], message: 'The access-token audience must equal the published MCP resource URL.' })
  .refine(config => Boolean(config.delegation) === Boolean(config.auth.googleWorkspace) && (!config.auth.googleWorkspace || config.auth.verifiedEmailClaims),
    { path: ['delegation'], message: 'Google-only membership and request delegation must be enabled together with verified resource-bound claims.' })
  .refine(config => !config.content || Boolean(config.auth.googleWorkspace),
    { path: ['content'], message: 'Native content access requires Google-only live staff authorization.' })
  .refine(config => Boolean(config.code) === Boolean(config.github) && (!config.code || Boolean(config.auth.googleWorkspace)),
    { path: ['code'], message: 'Code workflows require Google delegation and the central GitHub App configuration together.' });
export function loadConfig(file) {
  const config = configSchema.parse(readJson(file));
  requireThat(new URL(config.auth.jwksUrl).origin === new URL(config.auth.issuer).origin, 'AUTH_CONFIG', 'Pin JWKS to the configured issuer origin.');
  const registry = new Registry(() => config.registryPath ? readJson(config.registryPath) : { version: 1, websites: [] }, () => readJson(config.policyPath));
  registry.data();
  return { config, registry };
}
