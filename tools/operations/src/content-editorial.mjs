import { z } from 'zod';
import { requireThat, sha } from './contracts.mjs';

// First admission is restricted to the existing disposable native fixture.
// Never derive this scope from a caller's environment label or a DNS suffix.
export const editorialFixtureUrl = 'https://child.fixture.example.invalid';
export const editorialPostType = z.enum(['post', 'page']);
const boundedText = max => z.string().max(max)
  .refine(text => text.isWellFormed() && !text.includes('\0') && Buffer.byteLength(text) <= max);
const title = boundedText(500).refine(text => text.trim().length > 0);
const slug = z.string().regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/).max(200);
const fields = { title: title.optional(), body: boundedText(8192).optional(), excerpt: boundedText(4000).optional() };
export const draftFields = { postType: editorialPostType, ...fields, title, slug };
const existing = { postType: editorialPostType, recordId: z.number().int().positive().safe(), expectedSnapshotSha256: sha };
export const coreEditFields = { ...existing, fields: z.object(fields).strict().refine(v => Object.keys(v).length > 0) };
export const statusFields = { ...existing, status: z.enum(['publish', 'draft']) };
const actions = { create: draftFields, edit: coreEditFields, status: statusFields };

export function editorialIntent(action, raw) {
  requireThat(Object.hasOwn(actions, action), 'CONTENT_INPUT', 'Unknown editorial action.');
  const input = z.object(actions[action]).strict().parse(raw);
  const target = { kind: 'wordpress-content', post_type: input.postType, post_id: input.recordId ?? 0 };
  if (action === 'status') return { target, changes: { post_status: input.status }, action };
  const f = action === 'create' ? input : input.fields;
  const changes = {
    ...(f.title !== undefined ? { post_title: f.title } : {}),
    ...(f.body !== undefined ? { post_content: f.body } : {}),
    ...(f.excerpt !== undefined ? { post_excerpt: f.excerpt } : {}),
    ...(action === 'create' ? { post_name: input.slug, post_status: 'draft' } : {}),
  };
  return { target, changes, action };
}

export function validateEditorialScope(siteUrl) {
  requireThat(siteUrl === editorialFixtureUrl, 'EDITORIAL_FIXTURE_ONLY',
    'Editorial previews are limited to the exact disposable native fixture.');
}

export function validateDraftPreview(args) {
  validateEditorialScope(args.site_url);
  requireThat(args.post_id === 0 && args.acf_patches === undefined && args.changes?.post_status === 'draft',
    'CAPABILITY_UNAVAILABLE', 'New-record preview only supports a draft with bounded core fields on the native fixture.');
  z.object({ post_status: z.literal('draft'), post_title: title, post_name: slug,
    post_content: fields.body, post_excerpt: fields.excerpt }).strict().parse(args.changes);
}

export function editorialVisibility(intent, core) {
  if (intent.action === 'create') return 'draft-only';
  requireThat(['draft', 'publish'].includes(core.post_status), 'EDITORIAL_STATUS',
    'This workflow only supports drafts and published posts or pages.');
  if (intent.action === 'status') {
    requireThat(core.post_status !== intent.changes.post_status, 'NO_CHANGE', 'The record already has the requested status.');
    return intent.changes.post_status === 'publish' ? 'publish' : 'unpublish';
  }
  requireThat(Object.entries(intent.changes).some(([key, value]) => core[key] !== value),
    'NO_CHANGE', 'The requested fields already have these values.');
  return core.post_status === 'draft' ? 'draft-only' : 'public-edit';
}
