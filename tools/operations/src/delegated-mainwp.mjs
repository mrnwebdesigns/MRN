import { z } from 'zod';
import { requireThat, requireWriteScope, sha } from './contracts.mjs';
import { payload, validateStatus } from './mainwp.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';

export const delegationTools = Object.freeze({ challenge: 'mrn_mainwp__get_delegation_challenge_v1',
  access: 'mrn_mainwp__get_team_access_v1', read: 'mrn_mainwp__read_delegated_operation_v1',
  sync: 'mrn_mainwp__sync_delegated_site_v1', run: 'mrn_mainwp__run_delegated_operation_v1' });
const readAbilities = new Set([
  'list-sites', 'get-site', 'get-sites-basic', 'get-site-plugins', 'get-site-themes', 'get-site-updates',
  'get-site-security', 'get-site-changes', 'get-abandoned-plugins', 'get-abandoned-themes', 'get-site-client', 'get-site-costs',
  'count-sites', 'list-clients', 'count-clients', 'get-client', 'get-client-sites', 'count-client-sites', 'get-client-costs',
  'list-tags', 'get-tag', 'get-tag-sites', 'get-tag-clients', 'list-ignored-updates', 'list-updates', 'get-batch-job-status',
].map(name => `mainwp/${name}-v1`).concat([
  'get-stack-runtime-report', 'qualify-stack-site', 'get-stack-deployment-agent-status', 'get-google-fonts-status',
  'inspect-backup-retention-policy', 'inspect-code-file', 'get-code-operation',
  'get-content-policy', 'inspect-content', 'get-content-operation', 'get-content-migration',
].map(name => `mrn-mainwp/${name}-v1`)));
readAbilities.add('mrn-mainwp/get-content-policy-v2');
const writeAbilities = new Set(['start-database-backup', 'get-database-backup-status', 'qualify-code-storage',
  'prepare-code-change', 'approve-code-change', 'execute-code-change', 'preflight-stack-plugin-update',
  'update-stack-plugin', 'rollback-stack-plugin', 'prepare-content-change', 'approve-content-change', 'execute-content-change', 'prepare-content-rollback'].map(name => `mrn-mainwp/${name}-v1`));
const actorSchema = z.object({ oauth_subject: z.string(), google_sub: z.string(), email: z.string() }).strict();
const resultSchema = z.object({ schema: z.literal(1), request_id: z.string().uuid(), ability: z.string(),
  actor: actorSchema, result: z.unknown(), policy_sha256: sha }).strict();
const accessSchema = actorSchema.extend({ schema: z.literal(1), roles: z.array(z.enum(['reader', 'preparer', 'approver', 'executor'])),
  portfolio_access: z.boolean(), expires_at: z.number().int(), policy_sha256: sha }).strict();
const identityMatches = (result, actor) => result.oauth_subject === actor.subject && result.google_sub === actor.googleSubject && result.email === actor.verifiedEmail;
export function abilityName(tool) {
  if (tool === 'mrn_mainwp__get_content_policy_v2') return 'mrn-mainwp/get-content-policy-v2';
  requireThat(/^(?:mrn_mainwp__)?[a-z0-9]+(?:_[a-z0-9]+)*_v1$/.test(tool) && !Object.values(delegationTools).includes(tool),
    'CAPABILITY_DENIED', 'Only a native qualified workflow may be delegated.');
  const namespace = tool.startsWith('mrn_mainwp__') ? 'mrn-mainwp' : 'mainwp';
  return `${namespace}/${tool.replace(/^mrn_mainwp__/, '').replaceAll('_', '-')}`;
}

// Construct one instance per verified actor. Nothing is written into the shared
// MCP client's session, environment or Google lease; every dispatch is signed.
export class DelegatedMainwpClient {
  constructor(client, actor, signer) { Object.assign(this, { client, actor, signer }); }
  async ready() {
    validateStatus(await this.client.readResource({ uri: 'mainwp://status' }));
    const names = new Set(); const metadata = new Map(); const cursors = new Set(); let cursor;
    do {
      const page = await this.client.listTools(cursor ? { cursor } : {});
      requireThat(Array.isArray(page.tools) && names.size + page.tools.length <= 1000, 'MAINWP_SCHEMA', 'Invalid capability directory.');
      for (const t of page.tools) { names.add(t.name); metadata.set(t.name, t.annotations); }
      cursor = page.nextCursor;
      requireThat(!cursor || !cursors.has(cursor), 'MAINWP_SCHEMA', 'Repeated capability page.');
      if (cursor) cursors.add(cursor);
    } while (cursor);
    requireThat(Object.values(delegationTools).every(n => names.has(n)), 'DELEGATION_UNAVAILABLE', 'The MainWP provider or service policy has not enabled verified team delegation.');
    requireThat(Object.values(delegationTools).every(n => metadata.get(n)?.readOnlyHint === false
      && metadata.get(n)?.destructiveHint === (n === delegationTools.run)), 'DELEGATION_TRANSPORT',
    'Delegation requires POST-only metadata and the explicit write-confirmation boundary. Assertions must never enter GET access logs.');
    this.transportVerified = true;
  }
  async envelope(ability, args) {
    if (!this.transportVerified) await this.ready();
    const challenge = payload(await this.client.callTool({ name: delegationTools.challenge,
      arguments: { ability, arguments_sha256: delegationDigest(args) } }, undefined, { timeout: 30000 }));
    const envelope = this.signer.sign(this.actor, ability, args, challenge);
    return { envelope, claims: JSON.parse(Buffer.from(envelope.payload_base64, 'base64').toString('utf8')) };
  }
  async verifyStaff() {
    await this.ready();
    const { envelope, claims } = await this.envelope('mrn-mainwp/get-team-access-v1', {});
    const result = accessSchema.parse(payload(await this.client.callTool({ name: delegationTools.access, arguments: { delegation: envelope } }, undefined, { timeout: 30000 })));
    requireThat(identityMatches(result, this.actor) && result.policy_sha256 === claims.policy_sha256
      && result.expires_at > Date.now() / 1000 && result.roles.includes('reader') && result.portfolio_access,
    'AUTH_REQUIRED', 'Active MRN staff membership and portfolio permission could not be verified.');
    return result;
  }
  async callTool({ name, arguments: args = {} }, _schema, options) {
    const ability = abilityName(name);
    requireThat(readAbilities.has(ability) || writeAbilities.has(ability) || ability === 'mainwp/sync-sites-v1',
      'CAPABILITY_DENIED', 'No qualified delegated adapter exists for this operation.');
    if (writeAbilities.has(ability)) requireWriteScope(this.actor);
    if (ability === 'mainwp/sync-sites-v1') requireThat(Object.keys(args).length === 1
      && Array.isArray(args.site_ids) && args.site_ids.length === 1 && Number.isSafeInteger(args.site_ids[0]) && args.site_ids[0] > 0,
    'TARGET_MISMATCH', 'Delegated sync requires one exact resolved site.');
    const arguments_json = delegationCanonical(args);
    requireThat(Buffer.byteLength(arguments_json) <= 98304, 'DELEGATION_INPUT', 'Delegated request exceeds the supported bound.');
    const { envelope, claims } = await this.envelope(ability, args);
    // No retry and no interactive-lease/raw-service fallback on any failure.
    const wrapper = readAbilities.has(ability) ? delegationTools.read : ability === 'mainwp/sync-sites-v1' ? delegationTools.sync : delegationTools.run;
    const input = { ability, arguments_json, delegation: envelope, ...(wrapper === delegationTools.run ? { confirm: true } : {}) };
    let response = await this.client.callTool({ name: wrapper, arguments: input }, undefined, options);
    if (wrapper === delegationTools.run) {
      const preview = payload(response);
      requireThat(preview.status === 'CONFIRMATION_REQUIRED' && typeof preview.confirmation_token === 'string' && preview.confirmation_token.length > 0,
        'CONFIRMATION_GATE', 'The mandatory MainWP confirmation gate is unavailable or safe mode is enabled.');
      requireThat(claims.expires_at > Date.now() / 1000, 'DELEGATION_EXPIRED', 'The signed request expired before confirmed dispatch; prepare again.');
      response = await this.client.callTool({ name: wrapper, arguments: { ...input, user_confirmed: true, confirmation_token: preview.confirmation_token } }, undefined, options);
    }
    const result = resultSchema.parse(payload(response));
    requireThat(result.ability === ability && result.request_id === claims.request_id
      && result.policy_sha256 === claims.policy_sha256 && identityMatches(result.actor, this.actor),
    'DELEGATION_MISMATCH', 'MainWP returned a result for a different request or verified person.');
    return { content: [{ type: 'text', text: JSON.stringify(result.result) }] };
  }
  async listTools(params) { return this.client.listTools(params); }
  async readResource(params) {
    requireThat(params.uri === 'mainwp://status', 'CAPABILITY_DENIED', 'Only non-secret connection status is available outside delegated actions.');
    return this.client.readResource(params);
  }
  async close() { await this.client.close(); }
}
