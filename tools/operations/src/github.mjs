import { createPrivateKey, sign } from 'node:crypto';
import { constants, openSync, fstatSync, readFileSync, closeSync } from 'node:fs';
import { requireThat, digest } from './contracts.mjs';
import { createManagementEvidenceTransport } from './github-management-evidence.mjs';

const hash = value => typeof value === 'string' && /^[a-f0-9]{40}$/.test(value);
const pathValid = value => typeof value === 'string' && /^[A-Za-z0-9_][A-Za-z0-9_./-]{0,511}$/.test(value) && !value.includes('..') && !value.includes('//') && !value.endsWith('/');
const permissions = Object.freeze({ read: { contents: 'read', pull_requests: 'read' }, source: { contents: 'write', pull_requests: 'write' }, qa: { contents: 'read', actions: 'write' }, qaRead: { contents: 'read', actions: 'read' } });
export function readGithubKey(path) {
  const fd = openSync(path, constants.O_RDONLY | constants.O_NOFOLLOW);
  try {
    const stat = fstatSync(fd);
    requireThat(stat.isFile() && stat.nlink === 1 && (stat.mode & 0o077) === 0 && stat.size <= 8192
      && [0, process.getuid()].includes(stat.uid), 'GITHUB_KEY', 'Use an owner-only central GitHub App key.');
    const key = createPrivateKey(readFileSync(fd));
    requireThat(key.asymmetricKeyType === 'rsa' && key.asymmetricKeyDetails.modulusLength >= 2048, 'GITHUB_KEY', 'A GitHub App RSA signing key is required.');
    return key;
  } finally { closeSync(fd); }
}

// The HTTP surface is private to reviewed adapters. No API path, repository,
// permission scope or credential can be selected by a ChatGPT tool argument.
export class GithubApp {
  constructor({ appId, installationId, privateKey, repositories, fetchImpl = fetch, now = () => Date.now() }) {
    requireThat(/^[0-9]+$/.test(String(appId)) && Number.isSafeInteger(installationId) && installationId > 0
      && privateKey?.asymmetricKeyType === 'rsa', 'GITHUB_CONFIG', 'Central GitHub App configuration is incomplete.');
    requireThat(repositories && Object.entries(repositories).length > 0 && Object.entries(repositories).every(([name, id]) =>
      /^mrnwebdesigns\/[A-Za-z0-9_-]+$/.test(name) && Number.isSafeInteger(id) && id > 0), 'GITHUB_CONFIG', 'Pin exact MRN repository identities.');
    Object.assign(this, { appId, installationId, privateKey, repositories, fetchImpl, now });
  }
  async request(path, token, method = 'GET', body, status = 200) {
    requireThat(/^\/(?:app\/installations\/\d+\/access_tokens|repos\/mrnwebdesigns\/[A-Za-z0-9_-]+\/[^\\#]+)$/.test(path)
      && !path.includes('..') && !path.includes('//'), 'GITHUB_ROUTE', 'Unqualified GitHub route.');
    let response;
    try {
      response = await this.fetchImpl(`https://api.github.com${path}`, { method, redirect: 'error', signal: AbortSignal.timeout(30000),
        headers: { Accept: 'application/vnd.github+json', Authorization: `Bearer ${token}`, 'X-GitHub-Api-Version': '2026-03-10', 'Content-Type': 'application/json' },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }) });
    } catch { requireThat(false, method === 'GET' ? 'GITHUB_UNAVAILABLE' : 'GITHUB_OUTCOME_UNKNOWN', 'The central GitHub request did not return a verified response. Do not retry a dispatched mutation.'); }
    requireThat(response.status === status, 'GITHUB_REJECTED', 'GitHub rejected the request or a required source/permission gate. No fallback was attempted.');
    requireThat(response.headers.get('content-type')?.includes('application/json'), 'GITHUB_SCHEMA', 'Expected a GitHub JSON response.');
    const chunks = []; let size = 0;
    for await (const chunk of response.body) {
      size += chunk.length; requireThat(size <= 2 * 1024 * 1024, 'GITHUB_SIZE', 'GitHub response exceeds the bounded adapter size.'); chunks.push(chunk);
    }
    try { return JSON.parse(Buffer.concat(chunks).toString('utf8')); } catch { requireThat(false, 'GITHUB_SCHEMA', 'Invalid GitHub JSON response.'); }
  }
  async #grant(repository, purpose, authorize, request = this.request.bind(this)) {
    requireThat(Object.hasOwn(this.repositories, repository) && Object.hasOwn(permissions, purpose), 'GITHUB_SCOPE', 'This source or workflow repository is not configured.');
    await authorize();
    const at = Math.floor(this.now() / 1000);
    const prefix = `${Buffer.from(JSON.stringify({ alg: 'RS256', typ: 'JWT' })).toString('base64url')}.${Buffer.from(JSON.stringify({ iat: at - 60, exp: at + 540, iss: String(this.appId) })).toString('base64url')}`;
    const jwt = `${prefix}.${sign('RSA-SHA256', Buffer.from(prefix), this.privateKey).toString('base64url')}`;
    const grant = await request(`/app/installations/${this.installationId}/access_tokens`, jwt, 'POST',
      { repository_ids: [this.repositories[repository]], permissions: permissions[purpose] }, 201);
    requireThat(typeof grant.token === 'string' && grant.token.length > 10 && Date.parse(grant.expires_at) > this.now() + 30000
      && Date.parse(grant.expires_at) <= this.now() + 3660000
      && Array.isArray(grant.repositories) && grant.repositories.length === 1 && grant.repositories[0].id === this.repositories[repository]
      && grant.repositories[0].full_name === repository, 'GITHUB_SCOPE', 'The GitHub installation grant does not match the exact configured repository.');
    requireThat(grant.permissions && Object.entries(permissions[purpose]).every(([key, value]) => grant.permissions[key] === value)
      && Object.keys(grant.permissions).every(key => Object.hasOwn(permissions[purpose], key) || (key === 'metadata' && grant.permissions.metadata === 'read')),
    'GITHUB_SCOPE', 'GitHub returned a broader or incomplete permission grant.');
    return grant;
  }
  // Private management acquisition uses the same exact-repository App grant.
  // This does not configure credentials, dispatch a run or register a tool.
  async managementEvidence(repository, authorize, { expiresAt, monotonic } = {}) {
    return createManagementEvidenceTransport({ repository, authorize, expiresAt, monotonic,
      fetchImpl: this.fetchImpl, now: this.now,
      acquireGrant: request => this.#grant(repository, 'qaRead', authorize, request) });
  }
  async scoped(repository, purpose, authorize) {
    const grant = await this.#grant(repository, purpose, authorize);
    const api = async (path, method, body, status) => {
      await authorize(); requireThat(Date.parse(grant.expires_at) > this.now(), 'GITHUB_EXPIRED', 'The central installation grant expired.');
      return this.request(`/repos/${repository}${path}`, grant.token, method, body, status);
    };
    if (purpose === 'qaRead') api.artifact = async id => {
      await authorize(); requireThat(Number.isSafeInteger(id) && id > 0 && Date.parse(grant.expires_at) > this.now(), 'QA_ARTIFACT', 'An exact artifact and current installation grant are required.');
      let redirect; let response;
      try {
        redirect = await this.fetchImpl(`https://api.github.com/repos/${repository}/actions/artifacts/${id}/zip`, {
          headers: { Authorization: `Bearer ${grant.token}`, Accept: 'application/vnd.github+json', 'X-GitHub-Api-Version': '2026-03-10' }, redirect: 'manual', signal: AbortSignal.timeout(30000) });
      } catch { requireThat(false, 'QA_ARTIFACT', 'The protected artifact download is unavailable.'); }
      requireThat(redirect.status === 302, 'QA_ARTIFACT', 'GitHub did not return the expected protected artifact redirect.');
      const url = new URL(redirect.headers.get('location'));
      requireThat(url.protocol === 'https:' && !url.username && !url.password && !url.port && !url.hash
        && /^(?:[a-z0-9-]+\.blob\.core\.windows\.net|[a-z0-9-]+\.actions\.githubusercontent\.com)$/.test(url.hostname), 'QA_ARTIFACT_HOST', 'Unrecognized GitHub artifact storage origin.');
      await authorize();
      try { response = await this.fetchImpl(url, { redirect: 'error', signal: AbortSignal.timeout(30000) }); }
      catch { requireThat(false, 'QA_ARTIFACT', 'The protected artifact download failed.'); }
      requireThat(response.status === 200, 'QA_ARTIFACT', 'The protected artifact is unavailable.');
      const chunks = []; let size = 0;
      for await (const chunk of response.body) { size += chunk.length; requireThat(size <= 65536, 'QA_ARTIFACT_SIZE', 'The protected receipt archive exceeds its bound.'); chunks.push(chunk); }
      await authorize(); return Buffer.concat(chunks);
    };
    return api;
  }
}

export async function branchHead(api, branch) {
  requireThat(typeof branch === 'string' && /^[A-Za-z0-9][A-Za-z0-9_/-]{0,127}$/.test(branch) && !branch.includes('//') && !branch.endsWith('/'), 'SOURCE_BRANCH', 'Use the pinned source branch.');
  const result = await api(`/git/ref/heads/${branch}`);
  requireThat(result.ref === `refs/heads/${branch}` && result.object?.type === 'commit' && hash(result.object.sha), 'SOURCE_IDENTITY', 'Source branch identity is invalid.');
  return result.object.sha;
}

export async function sourceFile(api, commit, path) {
  requireThat(hash(commit) && pathValid(path), 'SOURCE_PATH', 'Use an immutable source commit and exact repository path.');
  const object = await api(`/git/commits/${commit}`);
  requireThat(object.sha === commit && hash(object.tree?.sha), 'SOURCE_IDENTITY', 'Source commit identity does not match.');
  let tree = object.tree.sha; const parts = path.split('/');
  for (let i = 0; i < parts.length; i++) {
    const result = await api(`/git/trees/${tree}`);
    requireThat(result.sha === tree && result.truncated === false && Array.isArray(result.tree), 'SOURCE_TREE', 'A complete exact source tree is required.');
    const found = result.tree.filter(entry => entry.path === parts[i]);
    if (!found.length && i === parts.length - 1) return { sha256: 'absent', content: '', blob: null, tree: object.tree.sha, mode: '100644' };
    requireThat(found.length === 1 && hash(found[0].sha), 'SOURCE_TREE', 'The exact source path is missing or ambiguous.');
    const entry = found[0];
    if (i < parts.length - 1) {
      requireThat(entry.type === 'tree' && entry.mode === '040000', 'SOURCE_PATH', 'Symlinks and submodules cannot be edited.'); tree = entry.sha;
    } else {
      requireThat(entry.type === 'blob' && ['100644', '100755'].includes(entry.mode), 'SOURCE_PATH', 'Only regular source files can be edited.');
      const blob = await api(`/git/blobs/${entry.sha}`);
      requireThat(blob.sha === entry.sha && blob.encoding === 'base64' && typeof blob.content === 'string' && blob.content.length < 45000, 'SOURCE_BLOB', 'Source file is unavailable or too large.');
      const encoded = blob.content.replaceAll('\n', ''); const bytes = Buffer.from(encoded, 'base64');
      requireThat(bytes.length <= 32768 && blob.size === bytes.length && bytes.toString('base64') === encoded && Buffer.from(bytes.toString('utf8')).equals(bytes), 'SOURCE_BLOB', 'Source bytes must be bounded canonical UTF-8.');
      return { sha256: digest(bytes), content: bytes.toString('utf8'), blob: entry.sha, tree: object.tree.sha, mode: entry.mode };
    }
  }
}

export class CodeSource {
  constructor(github) { this.github = github; }
  async propose({ repository, branch, path, expectedSha256, content, operationId, reason, actor, requiredBase, reviewProof }, authorize, checkpoint) {
    requireThat(/^[a-f0-9-]{36}$/.test(operationId) && content.isWellFormed() && Buffer.byteLength(content) <= 32768
      && typeof reason === 'string' && reason.length <= 512 && actor?.identityProvider === 'google-oauth2', 'SOURCE_INPUT', 'An exact bounded change and verified actor are required.');
    const api = await this.github.scoped(repository, 'source', authorize);
    const base = await branchHead(api, branch); const old = await sourceFile(api, base, path);
    requireThat(hash(requiredBase) && base === requiredBase, 'SOURCE_AUTOMATION_UNQUALIFIED', 'Source automation must be qualified at the exact current base commit.');
    requireThat(old.sha256 === expectedSha256, 'SOURCE_DRIFT', 'Live bytes and merged source differ; reconcile them before preparing a PR.');
    const bytes = Buffer.from(content); const newSha256 = digest(bytes);
    requireThat(newSha256 !== expectedSha256, 'NO_CHANGE', 'The proposed code is unchanged.');
    // This internal proof is returned only by the protected receipt verifier.
    // It is never accepted in an MCP tool's arguments.
    const review = reviewProof?.claims;
    requireThat(reviewProof?.status === 'qualified' && ['source_review', 'rollback_source_review'].includes(review?.kind)
      && review.expected_sha256 === expectedSha256 && review.new_sha256 === newSha256 && review.expires_at * 1000 > Date.now()
      && review.source?.mode === 'git' && review.source.repository === `https://github.com/${repository}` && review.source.branch === branch
      && review.source.commit === base && review.source.repository_path === path, 'SOURCE_QA_REQUIRED', 'A fresh protected source-review receipt must cover these exact bytes and merged baseline before publishing a PR.');
    const ref = `codex/mrn-ops/${operationId}`;
    // Persist intent before the first mutation. Lost responses never cause retries.
    await checkpoint({ status: 'source_dispatching', repository, branch, path, base, ref, expectedSha256, newSha256 });
    const blob = await api('/git/blobs', 'POST', { content: bytes.toString('base64'), encoding: 'base64' }, 201);
    requireThat(hash(blob.sha), 'SOURCE_BLOB', 'GitHub did not return a source blob.');
    const tree = await api('/git/trees', 'POST', { base_tree: old.tree, tree: [{ path, mode: old.mode, type: 'blob', sha: blob.sha }] }, 201);
    requireThat(hash(tree.sha), 'SOURCE_TREE', 'GitHub did not return a source tree.');
    const commit = await api('/git/commits', 'POST', { message: `MRN ${operationId}: ${reason}\n\nIndividual attribution is retained in the private MRN operation audit.`, tree: tree.sha, parents: [base] }, 201);
    requireThat(hash(commit.sha), 'SOURCE_COMMIT', 'GitHub did not return a source commit.');
    await api('/git/refs', 'POST', { ref: `refs/heads/${ref}`, sha: commit.sha }, 201);
    await checkpoint({ status: 'source_branch_created', repository, branch, path, base, ref, head: commit.sha, expectedSha256, newSha256 });
    const pr = await api('/pulls', 'POST', { title: `MRN code change: ${path}`, head: ref, base: branch,
      body: `${reason}\n\nOperation: ${operationId}\nIndividual attribution: private MRN operation audit\nExact file: ${path}\nBefore SHA-256: ${expectedSha256}\nAfter SHA-256: ${newSha256}\n\nThis PR does not deploy. Merged-source QA, exact live-change approval and verified backup remain required.` }, 201);
    requireThat(Number.isSafeInteger(pr.number) && pr.number > 0 && pr.head?.sha === commit.sha && pr.base?.ref === branch
      && pr.html_url === `https://github.com/${repository}/pull/${pr.number}`, 'SOURCE_PR', 'The created PR does not match the prepared source.');
    return { repository, branch, path, base, ref, head: commit.sha, number: pr.number, url: pr.html_url, expectedSha256, newSha256 };
  }
  async merged(proposal, authorize) {
    const api = await this.github.scoped(proposal.repository, 'read', authorize);
    const pr = await api(`/pulls/${proposal.number}`);
    requireThat(pr.number === proposal.number && pr.head?.repo?.full_name === proposal.repository && pr.base?.repo?.full_name === proposal.repository
      && pr.head.sha === proposal.head && pr.base.ref === proposal.branch, 'SOURCE_CHANGED', 'The reviewed PR source changed.');
    requireThat(pr.merged === true && hash(pr.merge_commit_sha), 'SOURCE_NOT_MERGED', 'The exact source PR must pass repository review and merge before live-change QA.');
    const head = await branchHead(api, proposal.branch);
    requireThat(head === pr.merge_commit_sha, 'SOURCE_MOVED', 'Merged source advanced; prepare a fresh exact source qualification.');
    const file = await sourceFile(api, head, proposal.path);
    requireThat(file.sha256 === proposal.newSha256, 'SOURCE_CHANGED', 'Merged source differs from the reviewed candidate bytes.');
    return { mode: 'git', repository: `https://github.com/${proposal.repository}`, branch: proposal.branch, commit: head };
  }
  async merge(proposal, authorize, checkpoint) {
    const api = await this.github.scoped(proposal.repository, 'source', authorize);
    const pr = await api(`/pulls/${proposal.number}`);
    requireThat(pr.number === proposal.number && pr.head?.sha === proposal.head && pr.head?.ref === proposal.ref
      && pr.head?.repo?.full_name === proposal.repository && pr.base?.repo?.full_name === proposal.repository
      && pr.base?.ref === proposal.branch && pr.state === 'open' && pr.draft === false, 'SOURCE_CHANGED', 'The PR changed or cannot be merged under the reviewed source approval.');
    requireThat(await branchHead(api, proposal.branch) === proposal.base, 'SOURCE_MOVED', 'The base branch changed; refresh the source proposal before merging.');
    const candidate = await sourceFile(api, proposal.head, proposal.path);
    requireThat(candidate.sha256 === proposal.newSha256, 'SOURCE_CHANGED', 'The PR candidate differs from its approved bytes.');
    const files = await api(`/pulls/${proposal.number}/files?per_page=100`);
    requireThat(Array.isArray(files) && files.length === 1 && files[0].filename === proposal.path
      && ['added', 'modified'].includes(files[0].status), 'SOURCE_SCOPE', 'The PR contains changes outside its exact file.');
    await checkpoint({ status: 'source_merging', number: proposal.number, head: proposal.head });
    // Native branch protection remains authoritative. The service App must not
    // be configured as a ruleset bypass actor; there is no force/admin route.
    const result = await api(`/pulls/${proposal.number}/merge`, 'PUT', { sha: proposal.head, merge_method: 'merge' });
    requireThat(result.merged === true && hash(result.sha), 'SOURCE_MERGE_BLOCKED', 'GitHub did not verify the reviewed merge. Do not retry a lost response.');
    return this.merged(proposal, authorize);
  }
}
