import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, verify } from 'node:crypto';
import { GithubApp, CodeSource, sourceFile } from '../src/github.mjs';
import { digest } from '../src/contracts.mjs';

const repository = 'mrnwebdesigns/example-site';
const key = generateKeyPairSync('rsa', { modulusLength: 2048 });
const json = (value, status = 200) => new Response(JSON.stringify(value), { status, headers: { 'content-type': 'application/json' } });
const old = Buffer.from('<?php // old\n'); const fresh = '<?php // new\n';
const hashes = { base: 'a'.repeat(40), tree: 'b'.repeat(40), blob: 'c'.repeat(40), newBlob: 'd'.repeat(40), newTree: 'e'.repeat(40), head: 'f'.repeat(40) };
function sourceApi() {
  const calls = [];
  const api = async (path, method = 'GET', body) => {
    calls.push({ path, method, body });
    if (path === '/git/ref/heads/main') return { ref: 'refs/heads/main', object: { type: 'commit', sha: hashes.base } };
    if (path === `/git/commits/${hashes.base}`) return { sha: hashes.base, tree: { sha: hashes.tree } };
    if (path === `/git/trees/${hashes.tree}`) return { sha: hashes.tree, truncated: false, tree: [{ path: 'file.php', type: 'blob', mode: '100644', sha: hashes.blob }] };
    if (path === `/git/blobs/${hashes.blob}`) return { sha: hashes.blob, encoding: 'base64', size: old.length, content: old.toString('base64') };
    if (path === '/git/blobs') return { sha: hashes.newBlob };
    if (path === '/git/trees') return { sha: hashes.newTree };
    if (path === '/git/commits') return { sha: hashes.head };
    if (path === '/git/refs') return {};
    if (path === '/pulls') return { number: 7, head: { sha: hashes.head }, base: { ref: 'main' }, html_url: `https://github.com/${repository}/pull/7` };
    throw new Error(`Unexpected fixture route ${path}`);
  };
  return { api, calls };
}

test('central app mints one-repository short-lived grants and never forwards its signing JWT to source APIs', async () => {
  const now = Date.now(); const calls = []; let denied = false;
  const app = new GithubApp({ appId: 123, installationId: 456, privateKey: key.privateKey, repositories: { [repository]: 789 }, now: () => now,
    fetchImpl: async (url, options) => {
      calls.push({ url, options });
      if (url.endsWith('/access_tokens')) {
        const jwt = options.headers.Authorization.slice(7).split('.');
        assert.equal(verify('RSA-SHA256', Buffer.from(jwt.slice(0, 2).join('.')), key.publicKey, Buffer.from(jwt[2], 'base64url')), true);
        assert.deepEqual(JSON.parse(options.body), { repository_ids: [789], permissions: { contents: 'read', pull_requests: 'read' } });
        return json({ token: 'installation-test-only', expires_at: new Date(now + 3600000).toISOString(), repositories: [{ id: 789, full_name: repository }], permissions: { contents: 'read', pull_requests: 'read', metadata: 'read' } }, 201);
      }
      assert.equal(options.headers.Authorization, 'Bearer installation-test-only'); assert.equal(options.redirect, 'error'); return json({ ok: true });
    } });
  const authorize = async () => { if (denied) throw new Error('revoked'); };
  const api = await app.scoped(repository, 'read', authorize);
  await api('/git/ref/heads/main'); denied = true;
  await assert.rejects(() => api('/git/ref/heads/main'), /revoked/);
  assert.equal(calls.length, 2);
  await assert.rejects(() => app.scoped('mrnwebdesigns/unconfigured', 'source', authorize), { code: 'GITHUB_SCOPE' });
});

test('extra repository access, broader permissions and redirects fail closed', async () => {
  for (const variant of ['repo', 'permission', 'redirect']) {
    const app = new GithubApp({ appId: 123, installationId: 456, privateKey: key.privateKey, repositories: { [repository]: 789 },
      fetchImpl: async () => variant === 'redirect' ? new Response('', { status: 302, headers: { location: 'https://untrusted.test' } }) : json({ token: 'installation-test-only', expires_at: new Date(Date.now() + 3600000).toISOString(),
        repositories: [{ id: 789, full_name: repository }, ...(variant === 'repo' ? [{ id: 999, full_name: 'mrnwebdesigns/other' }] : [])],
        permissions: { contents: variant === 'permission' ? 'write' : 'read', pull_requests: 'read' } }, 201) });
    await assert.rejects(() => app.scoped(repository, 'read', async () => {}), { code: variant === 'redirect' ? 'GITHUB_REJECTED' : 'GITHUB_SCOPE' });
  }
});

test('source PR preserves original tree, exact live precondition and verified attribution without deploying', async () => {
  const { api, calls } = sourceApi(); const checkpoints = [];
  const source = new CodeSource({ scoped: async () => api });
  const input = { repository, branch: 'main', path: 'file.php', requiredBase: hashes.base, expectedSha256: digest(old), content: fresh,
    operationId: '12345678-1234-4234-8234-123456789abc', reason: 'Correct scoped code', actor: { identityProvider: 'google-oauth2', subject: 'google-oauth2|123', verifiedEmail: 'staff@mrnwebdesigns.com' } };
  const proposal = await source.propose(input, async () => {}, state => checkpoints.push(state));
  assert.equal(proposal.newSha256, digest(fresh)); assert.equal(proposal.number, 7);
  assert.deepEqual(calls.find(c => c.path === '/git/trees').body, { base_tree: hashes.tree, tree: [{ path: 'file.php', mode: '100644', type: 'blob', sha: hashes.newBlob }] });
  assert.match(calls.find(c => c.path === '/git/commits').body.message, /google-oauth2\|123/);
  assert.deepEqual(checkpoints.map(c => c.status), ['source_dispatching', 'source_branch_created']);
  assert.equal(calls.some(c => /merge|dispatch/.test(c.path)), false);
  const before = calls.length;
  await assert.rejects(() => source.propose({ ...input, expectedSha256: '0'.repeat(64) }, async () => {}, () => {}), { code: 'SOURCE_DRIFT' });
  assert.equal(calls.slice(before).some(c => c.method !== 'GET'), false);
});

test('source paths reject symlinks, truncated trees, changed bytes and never retry a mutation', async () => {
  for (const mode of ['120000', '160000']) {
    await assert.rejects(() => sourceFile(async path => path.startsWith('/git/commits/') ? { sha: hashes.base, tree: { sha: hashes.tree } }
      : { sha: hashes.tree, truncated: false, tree: [{ path: 'file.php', mode, type: 'blob', sha: hashes.blob }] }, hashes.base, 'file.php'), { code: 'SOURCE_PATH' });
  }
  const { api } = sourceApi(); let mutations = 0; let intent;
  const source = new CodeSource({ scoped: async () => async (path, method, ...args) => { if (method === 'POST') { mutations++; throw new Error('lost response'); } return api(path, method, ...args); } });
  await assert.rejects(() => source.propose({ repository, branch: 'main', path: 'file.php', requiredBase: hashes.base, expectedSha256: digest(old), content: fresh,
    operationId: '12345678-1234-4234-8234-123456789abc', reason: 'Test', actor: { identityProvider: 'google-oauth2' } }, async () => {}, state => { intent = state; }), /lost response/);
  assert.equal(mutations, 1); assert.equal(intent.status, 'source_dispatching');
});

test('receipt downloads strip authorization at the redirect and reject unknown artifact hosts', async () => {
  let badHost = false; let storageCalls = 0;
  const app = new GithubApp({ appId: 123, installationId: 456, privateKey: key.privateKey, repositories: { [repository]: 789 },
    fetchImpl: async (input, options) => {
      const url = String(input);
      if (url.endsWith('/access_tokens')) return json({ token: 'installation-test-only', expires_at: new Date(Date.now() + 3600000).toISOString(),
        repositories: [{ id: 789, full_name: repository }], permissions: { contents: 'read', actions: 'read' } }, 201);
      if (url.startsWith('https://api.github.com/')) {
        assert.equal(options.headers.Authorization, 'Bearer installation-test-only'); assert.equal(options.redirect, 'manual');
        return new Response('', { status: 302, headers: { location: badHost ? 'https://untrusted.invalid/receipt' : 'https://fixture.blob.core.windows.net/receipt?signature=test' } });
      }
      storageCalls++; assert.equal(options.headers, undefined); assert.equal(options.redirect, 'error'); return new Response('bounded archive');
    } });
  const api = await app.scoped(repository, 'qaRead', async () => {});
  assert.equal((await api.artifact(7)).toString(), 'bounded archive');
  badHost = true; await assert.rejects(() => api.artifact(7), { code: 'QA_ARTIFACT_HOST' }); assert.equal(storageCalls, 1);
});
