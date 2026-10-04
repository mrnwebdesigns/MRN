import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { safeError, sha } from './contracts.mjs';

const target = { website: z.string().min(1).max(200).describe('Website name, alias or exact URL from list_websites.'),
  environment: z.enum(['local', 'development', 'staging', 'production']).optional() };
const requestKey = z.string().min(1).max(120).describe('Stable unique key for this logical request. Reuse only when retrying the same request.');
const id = z.string().uuid();
export const guide = `MRN Website Operations is the shared conversational interface for the MRN WordPress Stack.
Use list_websites to resolve accessible exact environments; ask only if ambiguous.
Use inspect_website for ordinary requests such as why is the site slow, what needs attention, or what changed.
Explain confirmed findings using evidence and coverage; never treat missing checks as passed or one timing sample as yesterday's baseline.
For fix that issue, use its inspection and finding IDs with prepare_repair. Present the concrete plan.
approve_operation records an authorized person's explicit approval of the exact plan. Use existing explicit approval when it covers that plan; do not repeatedly ask.
execute_operation starts durable work; follow get_operation until verified, failed or uncertain. Attempted is not verified.
No arbitrary downstream tools, commands, credentials, caller identity, paths, or site IDs are accepted from chat.
Content is authored in WordPress/ACF and repaired with tracked idempotent migrations. Site-owned child themes, Fleet shared components, content and providers keep separate workflows.
Only installed standard-plugin Fleet repairs and their code rollback are qualified for execution in this version. Other repairs need their documented workflow; report the precise gap.
Form submission/delivery is not implemented: do not submit real forms or imply delivery. test_website runs the configured read-only MRN QA runtime only.
Role permissions, fresh state, backups, immutable source/artifact bindings and shared locks are enforced by services, independent of these instructions.`;

export function createMcp(service, actor) {
  const server = new McpServer({ name: 'mrn-website-operations', version: '0.1.0' }, { instructions: guide });
  const register = (name, description, schema, readOnly, fn) => server.registerTool(name, { description, inputSchema: schema,
    annotations: { readOnlyHint: readOnly, destructiveHint: !readOnly, idempotentHint: readOnly, openWorldHint: true } }, async args => {
    try { const result = await fn(args); return { content: [{ type: 'text', text: JSON.stringify(result) }] }; }
    catch (error) { return { isError: true, content: [{ type: 'text', text: JSON.stringify({ error: safeError(error) }) }] }; }
  });
  register('list_websites', 'List websites and environments this team member can inspect; registry facts include source and freshness.', {}, true, () => service.list(actor));
  register('inspect_website', 'Inspect one website, fresh-sync MainWP, measure public behavior and record evidenced findings with explicit coverage limits.', target, true, args => service.inspect(actor, args));
  register('test_website', 'Run the configured read-only MRN QA Engine against the exact environment. Does not submit forms, charge payments or prove delivery.', target, true, args => service.test(actor, args));
  register('prepare_repair', 'Revalidate one recorded finding and prepare a concrete repair through the established MRN workflow. Does not deploy.', { inspectionId: id, findingId: id, requestKey }, true, args => service.prepare(actor, args));
  register('approve_operation', 'Record explicit existing authorization for the exact prepared plan. Requires the team member’s release permission for this environment.', { operationId: id, planDigest: sha }, false, args => service.approve(actor, args));
  register('execute_operation', 'Execute an approved plan under the shared website lock, backup and verification gates. Returns immediately with an operation ID; poll get_operation.', { operationId: id }, false, args => service.execute(actor, args));
  register('get_operation', 'Read a recorded inspection or operation, including attempted, blocked, uncertain and verified outcomes.', { operationId: id }, true, args => service.get(actor, args.operationId));
  register('website_history', 'Read previous inspections and changes for the exact selected environment.', target, true, args => service.history(actor, args.website, args.environment));
  register('prepare_rollback', 'Prepare code rollback of a verified Fleet update using its exact retained artifacts. Does not restore database or media.', { operationId: id, requestKey }, true, args => service.prepareRollback(actor, args));
  register('assess_fleet', 'Assess one to 25 explicitly selected environments; partial Stack installations remain qualification cases. No update is authorized by this assessment.', { targets: z.array(z.object(target).strict()).min(1).max(25) }, true, args => service.assessFleet(actor, args));
  server.registerResource('workflow-guide', 'mrn-operations://guide', { mimeType: 'text/plain' }, async uri => ({ contents: [{ uri: uri.href, text: guide }] }));
  return server;
}
