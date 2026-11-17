import { z } from 'zod';
import { requireThat, digest } from './contracts.mjs';
import { toolNames } from './mainwp.mjs';

export const codePath = z.string().max(512).regex(/^wp-content\/(?:themes|plugins|mu-plugins)\/[A-Za-z0-9_][A-Za-z0-9_./-]+$/)
  .refine(path => !path.includes('..') && !path.includes('//') && !path.endsWith('/'), 'Select one exact permitted code file.');

export async function codeInspection(session, path, { includeContent = true } = {}) {
  codePath.parse(path);
  z.boolean().parse(includeContent);
  // Site binding is verified by the Dashboard; the signed file inspection
  // itself is a current Child read. The checksum path never refreshes inventory.
  const site = includeContent ? await session.fresh() : await session.resolveSite();
  const result = await session.call(toolNames.codeInspect, { site_id: site.id, site_url: site.url + '/', path, include_content: includeContent });
  const data = result.data || result;
  requireThat(data.success === true && typeof data.exists === 'boolean'
    && Number.isSafeInteger(data.bytes) && data.bytes >= 0 && data.bytes <= 32768
    && (data.exists ? typeof data.sha256 === 'string' && /^[a-f0-9]{64}$/.test(data.sha256) : data.sha256 === 'absent' && data.bytes === 0),
    'CODE_SCHEMA', 'The code provider did not return a qualified exact-file snapshot.');
  let content = null;
  if (includeContent && data.exists && data.content_withheld !== true) {
    requireThat(typeof data.content_base64 === 'string' && data.content_base64.length <= 43692, 'CODE_SCHEMA', 'Code content is unavailable or exceeds the provider limit.');
    const bytes = Buffer.from(data.content_base64, 'base64');
    requireThat(bytes.toString('base64') === data.content_base64 && bytes.length <= 32768 && data.bytes === bytes.length && digest(bytes) === data.sha256,
      'CODE_PRECONDITION', 'Returned file bytes do not match the provider checksum.');
    content = bytes.toString('utf8');
    requireThat(Buffer.from(content).equals(bytes), 'CODE_ENCODING', 'Only UTF-8 code files can be edited in chat.');
  }
  return { site, path, exists: data.exists, bytes: data.bytes, sha256: data.sha256, content, contentRequested: includeContent,
    contentWithheld: includeContent && data.content_withheld === true, observedAt: new Date().toISOString(),
    storage: { backupReady: data.code_storage?.backup_ready === true, atomicReady: data.code_storage?.atomic_ready === true },
    scope: 'Inspection does not qualify a code change. Source review, protected QA, exact approval, verified backup and rollback remain required.' };
}
