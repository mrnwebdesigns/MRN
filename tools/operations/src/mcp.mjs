import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';
import { toJsonSchemaCompat } from '@modelcontextprotocol/sdk/server/zod-json-schema-compat.js';
import { z } from 'zod';
import { safeError, sha, environmentNames } from './contracts.mjs';
import { securitySchemes, oauthChallenge } from './auth.mjs';

const target = { website: z.string().min(1).max(200).describe('Website name, alias or exact URL from list_websites.'),
  environment: z.enum(environmentNames).optional().describe('Copy the environment returned by list_websites. If unknown, omit this argument or use unknown. Never infer production from a public URL.') };
const requestKey = z.string().min(1).max(120).describe('Stable unique key for this logical request. Reuse only when retrying the same request.');
const id = z.string().uuid();
export const guide = `MRN Website Operations lets the MRN team manage its WordPress websites through ChatGPT. Team members add this plugin, sign in, and chat. Resolve their permitted websites automatically; do not ask for terminal commands, Codex, API keys, MCP configuration, or duplicate website enrollment. Report evidence, limits and verified outcomes in ordinary language. MRN services enforce permissions and change safeguards; this plugin cannot bypass them.
Use list_websites to discover the accessible MainWP directory and existing MRN environment records. Websites do not need to be added again. Discovery returns websites plus source coverage and unresolved issues.
Resolve accessible exact environments; ask only if ambiguous or a requested action actually requires missing knowledge. Unknown environment or backup information does not prevent inspection, but cannot authorize a change. Directory discovery does not prove current site health; inspection performs exact-site synchronization.
Copy the discovered URL and environment exactly. When the directory reports unknown environment, omit the optional environment argument or pass unknown; never substitute production merely because a URL is public. A mismatched guessed environment is not evidence that a website needs enrollment.
Use inspect_website for ordinary requests such as why is the site slow, what needs attention, or what changed.
Explain confirmed findings using evidence and coverage; never treat missing checks as passed or one timing sample as yesterday's baseline.
For fix that issue, use its inspection and finding IDs with prepare_repair. Present the concrete plan.
approve_operation records an authorized person's explicit approval of the exact plan. Use existing explicit approval when it covers that plan; do not repeatedly ask.
execute_operation starts durable work; follow get_operation until verified, failed or uncertain. Attempted is not verified.
For interrupted or uncertain work, an operator must first stop the original workers and downstream jobs and enroll short-lived exact-operation quiescence evidence. reconcile_operation then reads runtime/public state and releases the lock only on a qualified match. Never invent quiescence evidence, clear a lock, or retry the original write. A reconciled code state does not prove the original execution, backup, database or media outcome.
No arbitrary downstream tools, commands, credentials, caller identity, paths, or site IDs are accepted from chat.
Content is authored in WordPress/ACF and repaired with tracked idempotent migrations. Site-owned child themes, Fleet shared components, content and providers keep separate workflows.
Only installed standard-plugin Fleet repairs and their code rollback are qualified for execution in this version. Other repairs need their documented workflow; report the precise gap.
Form submission/delivery is not implemented: do not submit real forms or imply delivery. test_website runs the configured read-only MRN QA runtime only.
Role permissions, fresh state, backups, immutable source/artifact bindings and shared locks are enforced by services, independent of these instructions.`;

export function createMcp(service, actor, { publicUrl } = {}) {
  const server = new McpServer({ name: 'mrn-website-operations', title: 'MRN Website Operations', version: '0.1.0' }, { instructions: guide });
  const descriptors = [];
  const register = (name, description, schema, readOnly, fn) => {
    const annotations = { readOnlyHint: readOnly,
      destructiveHint: ['execute_operation', 'reconcile_operation'].includes(name),
      idempotentHint: readOnly || ['prepare_repair', 'prepare_rollback', 'approve_operation', 'execute_operation', 'reconcile_operation'].includes(name),
      openWorldHint: !['list_websites', 'get_operation', 'website_history', 'approve_operation'].includes(name) };
    const title = name.replaceAll('_', ' ').replace(/^./, c => c.toUpperCase());
    const _meta = { securitySchemes, 'openai/toolInvocation/invoking': 'Working on your MRN request', 'openai/toolInvocation/invoked': 'MRN result received' };
    descriptors.push({ name, title, description, inputSchema: toJsonSchemaCompat(z.object(schema), { strictUnions: true, pipeStrategy: 'input' }), annotations, securitySchemes, _meta });
    server.registerTool(name, { title, description, inputSchema: schema, annotations, _meta }, async args => {
      try { const result = await fn(args); return { content: [{ type: 'text', text: JSON.stringify(result) }] }; }
      catch (error) {
        const safe = safeError(error);
        return { isError: true, content: [{ type: 'text', text: JSON.stringify({ error: safe }) }],
          ...(safe.code === 'AUTH_REQUIRED' && publicUrl ? { _meta: { 'mcp/www_authenticate': [oauthChallenge(publicUrl)] } } : {}) };
      }
    });
  };
  register('list_websites', 'Discover accessible websites from MainWP and existing MRN records. Returns websites, source coverage and unresolved knowledge; no duplicate site setup or bulk sync.', {}, true, () => service.list(actor));
  register('inspect_website', 'Use when asked what needs attention or why a site is slow. Fresh-sync one site, measure public behavior and save an inspection with evidenced findings and coverage limits.', target, false, args => service.inspect(actor, args));
  register('test_website', 'Run the configured read-only MRN QA Engine and save its report for the exact environment. Does not submit forms, charge payments or prove delivery.', target, false, args => service.test(actor, args));
  register('prepare_repair', 'Use when asked to fix a recorded finding. Revalidate it and save a concrete repair plan through the established MRN workflow. Does not deploy.', { inspectionId: id, findingId: id, requestKey }, false, args => service.prepare(actor, args));
  register('approve_operation', 'Record explicit existing authorization for the exact prepared plan. Requires the team member’s release permission for this environment.', { operationId: id, planDigest: sha }, false, args => service.approve(actor, args));
  register('execute_operation', 'Execute an approved plan under the shared website lock, backup and verification gates. Returns immediately with an operation ID; poll get_operation.', { operationId: id }, false, args => service.execute(actor, args));
  register('get_operation', 'Read a recorded inspection or operation, including attempted, blocked, uncertain and verified outcomes.', { operationId: id }, true, args => service.get(actor, args.operationId));
  register('reconcile_operation', 'Reconcile interrupted Fleet work using operator-enrolled quiescence evidence and fresh runtime/public verification. Does not change WordPress. Releases the local lock only after a qualified exact-code match; requires release permission and cannot force-unlock.', { operationId: id }, false, args => service.reconcile(actor, args));
  register('website_history', 'Read previous inspections and changes for the exact selected environment.', target, true, args => service.history(actor, args.website, args.environment));
  register('prepare_rollback', 'Save a code rollback plan for a verified or reconciled Fleet update using its exact retained artifacts. Does not restore database or media.', { operationId: id, requestKey }, false, args => service.prepareRollback(actor, args));
  register('assess_fleet', 'Assess one to 25 explicitly selected environments and save their inspections; partial Stack installations remain qualification cases. No update is authorized by this assessment.', { targets: z.array(z.object(target).strict()).min(1).max(25) }, false, args => service.assessFleet(actor, args));
  // SDK 1.31's registerTool drops top-level securitySchemes. Use its public
  // request-handler and schema-conversion APIs to retain ChatGPT's declaration;
  // SDK validation and handlers still execute every tool. No private SDK state.
  server.server.setRequestHandler(ListToolsRequestSchema, () => ({ tools: descriptors }));
  server.registerResource('workflow-guide', 'mrn-operations://guide', { mimeType: 'text/plain' }, async uri => ({ contents: [{ uri: uri.href, text: guide }] }));
  return server;
}
