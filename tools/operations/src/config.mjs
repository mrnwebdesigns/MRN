import { z } from 'zod';
import { isAbsolute } from 'node:path';
import { readJson, Registry, requireThat } from './contracts.mjs';

const absolute = z.string().refine(isAbsolute, 'Use an absolute service path.');
const secureUrl = z.string().url().refine(s => { const u = new URL(s); return u.protocol === 'https:' && !u.username && !u.password && !u.search && !u.hash; });
export const configSchema = z.object({
  version: z.literal(1), publicUrl: secureUrl.refine(s => new URL(s).pathname === '/mcp'),
  listen: z.object({ host: z.literal('127.0.0.1'), port: z.number().int().min(1024).max(65535) }).strict(),
  auth: z.object({ issuer: secureUrl, audience: z.string().min(1), jwksUrl: secureUrl }).strict(),
  allowedOrigins: z.array(secureUrl).default([]),
  registryPath: absolute, policyPath: absolute, qualificationsPath: absolute, stateDir: absolute, repositoryRoot: absolute,
  recoveryEvidencePath: absolute.optional(),
  artifactRoots: z.array(absolute).min(1), writesEnabled: z.boolean().default(false),
  sourceRepositories: z.record(z.string(), absolute),
  mainwp: z.object({ command: absolute, args: z.array(z.string()).min(1), cwd: absolute,
    envRefs: z.record(z.string(), z.object({ env: z.string() }).strict()) }).strict(),
  qa: z.object({ executable: absolute }).strict().optional(),
}).strict();
export function loadConfig(file) {
  const config = configSchema.parse(readJson(file));
  requireThat(new URL(config.auth.jwksUrl).origin === new URL(config.auth.issuer).origin, 'AUTH_CONFIG', 'Pin JWKS to the configured issuer origin.');
  const registry = new Registry(() => readJson(config.registryPath), () => readJson(config.policyPath));
  registry.data();
  return { config, registry };
}
