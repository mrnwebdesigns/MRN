// Closed descriptor for the separate, disposable v2 native test. Never a
// production setting or a source-selection argument to a team tool.
import { z } from 'zod';
import { delegationDigest } from '../../src/delegation.mjs';
import { existingManagementHashes } from '../../src/management-existing-contracts.mjs';

// Initial synthetic grant and aggregate test allocation only. A full capture,
// lost-response recovery, measurement read and revocation sequence must fit
// without renewing its original grant. Individual 120s/20s limits are unchanged.
export const existingBindingFixtureLimits = Object.freeze({
  initial_grant_seconds: 300, hook_seconds: 300, owner_wait_seconds: 320,
});

export const existingBindingProfileFiles = Object.freeze([
  'child/content-acf.php', 'child/content-fields.php', 'child/content-records.php',
  'includes/class-mrn-mainwp-content-management.php', 'includes/class-mrn-mainwp-content-source.php',
  'release-source.json', 'child/content-url.php', 'child/content-existing-home.php',
  'includes/class-mrn-mainwp-management-existing-original.php',
  'includes/class-mrn-mainwp-management-original-authorization.php',
  'contracts/content-management-original-authorization-v2.json',
]);
const sha = z.string().regex(/^[a-f0-9]{64}$/);
const contextType = z.object({
  schema: z.literal(2), mode: z.literal('disposable-existing-binding-v2'),
  production_credentials: z.literal(false), production_activation: z.literal(false), consumer_binding_issued: z.literal(false),
  site: z.object({ site_id: z.number().int().positive().safe(), site_url: z.literal('https://child.fixture.example.invalid') }).strict(),
  target: z.object({ kind: z.literal('wordpress-content'), post_type: z.literal('page'), post_id: z.number().int().positive().safe() }).strict(),
  actor: z.object({ subject: z.literal('google-oauth2|222222'), googleSubject: z.literal('222222'),
    verifiedEmail: z.literal('bob@mrnwebdesigns.com'), identityProvider: z.literal('google-oauth2'),
    hostedDomain: z.literal('mrnwebdesigns.com'), googleConnection: z.literal('con_RuntimeFixture'),
    expiresAt: z.number().int().positive().safe().multipleOf(1000),
    scopes: z.tuple([z.literal('mrn:operations'), z.literal('mrn:operations:write')]),
  }).strict(),
  profile_source: z.object(Object.fromEntries(existingBindingProfileFiles.map(file => [file, sha]))).strict(),
  profile_runtime_url: z.literal('https://mrn-qa.internal:9443'),
  pins: z.object({ provider_source_sha: z.string().regex(/^[a-f0-9]{40}$/), installed_manifest_sha256: sha,
    source_binding_sha256: sha, authorization_policy_sha256: sha, delegation_policy_sha256: sha,
    coherence_epoch_sha256: sha, qualification_catalog_sha256: sha, qualification_authority_sha256: sha }).strict(),
  limits: z.object({ hook_seconds: z.literal(existingBindingFixtureLimits.hook_seconds),
    owner_wait_seconds: z.literal(existingBindingFixtureLimits.owner_wait_seconds) }).strict(),
}).strict();

export function parseExistingBindingContext(bytes, now = Date.now()) {
  if (!Buffer.isBuffer(bytes) || bytes.length > 32768) throw new Error('Invalid disposable v2 context');
  let result;
  try { result = contextType.parse(JSON.parse(bytes.toString('utf8'))); }
  catch { throw new Error('Invalid disposable v2 context'); }
  if (result.actor.expiresAt <= now || result.actor.expiresAt > now + existingBindingFixtureLimits.initial_grant_seconds * 1000)
    throw new Error('Expired or extended disposable v2 context');
  return result;
}

export function existingBindingProfile(read, context) {
  const hash = delegationDigest({ adapter: 'content-management-v1', runtime: read.snapshot.runtime,
    acf_definitions: read.snapshot.acf_definitions, source: context.profile_source,
    qualification_protocol: 'content-management-existing-v2', protocol_contracts_sha256: existingManagementHashes,
    qualification_catalog_sha256: context.pins.qualification_catalog_sha256,
    qualification_authority_sha256: context.pins.qualification_authority_sha256,
    qualification_lifetime_domain: 'original-resource-authorization-v2',
    requester_coherence_epoch_sha256: context.pins.coherence_epoch_sha256 });
  return { sha256: hash, runtime_context_sha256: hash, runtime_url: context.profile_runtime_url };
}
