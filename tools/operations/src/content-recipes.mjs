import { createPublicKey, verify } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { z } from 'zod';
import { requireThat, sha } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { compileContentSchema } from './content-schema.mjs';

const recipeContract = JSON.parse(readFileSync(new URL('../contracts/content-recipe-catalog-v1.json', import.meta.url), 'utf8'));
const qualificationContract = JSON.parse(readFileSync(new URL('../contracts/content-qualification-v1.json', import.meta.url), 'utf8'));
const compile = (contract, definition) => compileContentSchema({ $ref: `#/definitions/${definition}` }, contract.definitions);
const envelopeSchema = compile(recipeContract, 'signed_envelope'), catalogSchema = compile(recipeContract, 'catalog_claims');
const recipeSchema = compile(recipeContract, 'recipe'), keySchema = compile(recipeContract, 'key');
const trustSchema = z.object({ catalog_epoch: z.number().int().positive().safe(), trusted_recipe_catalog_sha256: sha,
  recipe_root_keys: z.array(keySchema).min(1).max(10), registrar_keys: z.array(keySchema).min(1).max(10),
  identity_keys: z.array(keySchema).min(1).max(10), qa_keys: z.array(keySchema).min(1).max(10),
}).strict();
const scopeSchema = z.object({ site_id: compile(qualificationContract, 'site_id'), site_url: compile(qualificationContract, 'site_url'),
  target: compile(qualificationContract, 'target') }).strict();
const parsed = (schema, value, code, message) => {
  const result = schema.safeParse(value); requireThat(result.success, code, message); return result.data;
};
const sortedUnique = values => values.every((value, index) => index === 0 || values[index - 1] < value);

// This module is an internal verifier, not a target qualification or a new trust
// mode. Its policy and source pins must come from MRN service configuration,
// never tool arguments. Static target inventories remain authoritative until
// the separately versioned target-binding/consumer transition is accepted.
export function verifyContentRecipeCatalog(input, trustedPolicy, now = Date.now()) {
  const policy = parsed(trustSchema, trustedPolicy, 'RECIPE_TRUST', 'A complete service-owned recipe trust policy is required.');
  const keys = [...policy.recipe_root_keys, ...policy.registrar_keys, ...policy.identity_keys, ...policy.qa_keys];
  requireThat(new Set(keys.map(key => key.id)).size === keys.length && new Set(keys.map(key => key.public_key_base64)).size === keys.length,
    'RECIPE_KEY_SEPARATION', 'Recipe, registrar, identity and QA keys must have distinct identifiers and public keys.');
  for (const key of keys) {
    const bytes = Buffer.from(key.public_key_base64, 'base64');
    requireThat(bytes.length === 32 && bytes.toString('base64') === key.public_key_base64, 'RECIPE_KEY', 'Recipe trust keys must be canonical Ed25519 public keys.');
  }
  const envelope = parsed(envelopeSchema, input, 'RECIPE_ENVELOPE', 'The recipe catalog requires the closed signed envelope.');
  const signer = policy.recipe_root_keys.find(key => key.id === envelope.key_id);
  requireThat(signer, 'RECIPE_KEY', 'The catalog signer is not an approved recipe authority.');
  const bytes = Buffer.from(envelope.payload_base64, 'base64'), signature = Buffer.from(envelope.signature_base64, 'base64');
  requireThat(bytes.length <= recipeContract.max_payload_utf8_bytes && bytes.toString('base64') === envelope.payload_base64
    && signature.length === 64 && signature.toString('base64') === envelope.signature_base64,
  'RECIPE_ENCODING', 'The catalog and signature must use bounded canonical base64.');
  const publicKey = createPublicKey({ key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'), Buffer.from(signer.public_key_base64, 'base64')]),
    type: 'spki', format: 'der' });
  requireThat(verify(null, Buffer.concat([Buffer.from(recipeContract.purpose_prefix), bytes]), publicKey, signature),
    'RECIPE_SIGNATURE', 'The catalog does not have an approved recipe-purpose signature.');
  let value;
  try { value = JSON.parse(bytes.toString('utf8')); } catch { requireThat(false, 'RECIPE_SCHEMA', 'The signed catalog is not valid JSON.'); }
  const catalog = parsed(catalogSchema, value, 'RECIPE_SCHEMA', 'The signed catalog does not match the accepted recipe contract.');
  // Comparing the original bytes rejects duplicate keys, alternate Unicode or
  // number encodings, invalid UTF-8 and whitespace even when JSON.parse accepts them.
  requireThat(bytes.equals(Buffer.from(delegationCanonical(catalog))), 'RECIPE_CANONICAL', 'The signed catalog must contain exact canonical UTF-8 JSON.');
  const catalogSha256 = delegationDigest(catalog);
  requireThat(catalogSha256 === policy.trusted_recipe_catalog_sha256 && catalog.catalog_epoch === policy.catalog_epoch,
    'RECIPE_POLICY_CHANGED', 'The catalog digest or epoch is not currently approved by MRN services.');
  requireThat(Number.isSafeInteger(now) && now >= 0, 'RECIPE_CLOCK', 'A valid service clock is required.');
  const at = Math.floor(now / 1000);
  requireThat(catalog.issued_at <= at && catalog.expires_at > at && catalog.expires_at > catalog.issued_at,
    'RECIPE_EXPIRED', 'The recipe catalog is expired or not yet valid.');
  requireThat(sortedUnique(catalog.recipes.map(recipe => recipe.recipe_id)) && catalog.recipes.every(recipe =>
    sortedUnique(recipe.eligible_post_types) && sortedUnique(recipe.eligible_fields)),
  'RECIPE_ORDER', 'Recipe identifiers and eligibility lists must be unique and sorted.');
  return { catalog, catalogSha256 };
}

export function matchContentRecipe(envelope, trustedPolicy, trustedRecipe, targetScope, now = Date.now()) {
  const { catalog, catalogSha256 } = verifyContentRecipeCatalog(envelope, trustedPolicy, now);
  const expected = parsed(recipeSchema, trustedRecipe, 'RECIPE_SOURCE', 'An exact service-owned recipe and its source pins are required.');
  const scope = parsed(scopeSchema, targetScope, 'RECIPE_SCOPE', 'Only the accepted exact disposable post/page scope is supported.');
  let url;
  try { url = new URL(scope.site_url); } catch { requireThat(false, 'RECIPE_SCOPE', 'The target origin is invalid.'); }
  requireThat(url.protocol === 'https:' && url.hostname.endsWith('.example.invalid') && !url.username && !url.password && !url.port && !url.search && !url.hash,
    'RECIPE_SCOPE', 'This contract cannot qualify production origins.');
  const recipe = catalog.recipes.find(row => row.recipe_id === expected.recipe_id);
  requireThat(recipe && delegationDigest(recipe) === delegationDigest(expected), 'RECIPE_SOURCE', 'The signed recipe differs from the independently reviewed source and runtime pins.');
  requireThat(recipe.eligible_post_types.includes(scope.target.post_type) && recipe.eligible_fields.includes(scope.target.field),
    'RECIPE_INELIGIBLE', 'This recipe does not cover the exact post type and field.');
  return { recipe, recipeSha256: delegationDigest(recipe), catalogSha256, catalogEpoch: catalog.catalog_epoch, catalogRevision: catalog.catalog_revision };
}
