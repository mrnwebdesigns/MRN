import { createHash } from 'node:crypto';
import { closeSync, constants, fstatSync, fsyncSync, mkdtempSync, openSync, realpathSync, renameSync, rmSync, writeSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { digest, requireThat } from './contracts.mjs';
import { sourceCacheProducerSchema } from './management-source-cache.mjs';

const repository = 'mrnwebdesigns/mrn-code-qa-runner';
let activeCacheReads = 0;
const check = value => requireThat(value, 'MANAGEMENT_SOURCE_CACHE_ACQUISITION', 'The original approved cache archive could not be verified.');
const id = value => typeof value === 'string' && /^[1-9][0-9]{0,19}$/.test(value);
const integerId = value => Number.isSafeInteger(value) && value > 0 ? String(value) : value;
const time = value => {
  check(typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.000)?Z$/.test(value));
  const at = Date.parse(value); check(Number.isSafeInteger(at)
    && [new Date(at).toISOString(), new Date(at).toISOString().replace('.000Z', 'Z')].includes(value));
  return at / 1000;
};

// Separate streaming path: existing 64-KiB receipt and evidence archive limits
// stay unchanged. This never extracts or executes the downloaded ZIP. The
// supervisor must verify its complete root inventory before using any bytes.
export async function acquireManagementSourceCache({ selection, acquireGrant, authorize, expiresAt, fetchImpl = fetch,
  now = () => Date.now(), monotonic = () => performance.now() }) {
  const artifact = selection?.catalog_artifact;
  const producer = sourceCacheProducerSchema.safeParse(selection?.producer); check(producer.success);
  check(selection && id(selection.repository_id) && id(selection.github_artifact_id) && id(selection.run_id)
    && /^[a-f0-9]{40}$/.test(selection.source_sha) && artifact?.archive_format === 'zip'
    && /^[a-f0-9]{64}$/.test(artifact.archive_sha256) && Number.isSafeInteger(artifact.maximum_archive_bytes)
    && artifact.maximum_archive_bytes > 0 && artifact.maximum_archive_bytes <= 536870912
    && /^[a-f0-9]{64}$/.test(selection.github_artifact_sha256)
    && Number.isSafeInteger(selection.github_artifact_bytes) && selection.github_artifact_bytes >= 22 && selection.github_artifact_bytes <= 536870912
    && Number.isSafeInteger(selection.source_archive_bytes) && selection.source_archive_bytes >= 22
    && selection.source_archive_bytes <= artifact.maximum_archive_bytes
    && Number.isSafeInteger(selection.created_at) && Number.isSafeInteger(selection.expires_at)
    && selection.created_at > 0 && selection.created_at * 1000 <= now() && selection.expires_at > selection.created_at
    && Number.isSafeInteger(expiresAt) && expiresAt > now() && typeof authorize === 'function');
  const startedAt = now(), started = monotonic(), controller = new globalThis.AbortController();
  let directory, fd, succeeded = false, grant, timer;
  const current = async () => {
    const elapsed = monotonic() - started;
    check(!controller.signal.aborted && elapsed >= 0 && elapsed < 60000 && now() >= startedAt
      && now() < expiresAt && now() < selection.expires_at * 1000);
    await authorize();
    check(!controller.signal.aborted && monotonic() - started < 60000 && now() < expiresAt
      && (!grant || Date.parse(grant.expires_at) > now()));
  };
  const headers = token => ({ Authorization: `Bearer ${token}`, Accept: 'application/vnd.github+json',
    'X-GitHub-Api-Version': '2026-03-10', 'Accept-Encoding': 'identity', 'Content-Type': 'application/json' });
  const entity = async (response, limit, consume) => {
    check(response.body && ['', 'identity'].includes(response.headers.get('content-encoding') || ''));
    const length = response.headers.get('content-length');
    check(length === null || (/^[1-9][0-9]*$/.test(length) && Number(length) <= limit));
    const reader = response.body.getReader(); let size = 0, complete = false;
    try {
      while (true) {
        check(!controller.signal.aborted && now() >= startedAt && now() < expiresAt && monotonic() - started >= 0 && monotonic() - started < 60000);
        const part = await reader.read(); if (part.done) { complete = true; break; }
        size += part.value.length; check(size <= limit); consume(Buffer.from(part.value));
      }
      check(size > 0 && (length === null || size === Number(length))); return size;
    } finally { if (!complete) void reader.cancel().catch(() => {}); reader.releaseLock(); }
  };
  const json = async (path, token, method = 'GET', body, status = 200) => {
    await current();
    const response = await fetchImpl(`https://api.github.com${path}`, { method, redirect: 'error', signal: controller.signal,
      headers: headers(token), ...(body === undefined ? {} : { body: JSON.stringify(body) }) });
    check(response.status === status && response.headers.get('content-type')?.includes('application/json'));
    const chunks = []; await entity(response, method === 'POST' ? 32768 : 131072, chunk => chunks.push(chunk));
    const raw = Buffer.concat(chunks); check(Buffer.from(raw.toString('utf8')).equals(raw));
    const value = JSON.parse(raw.toString('utf8')); await current(); return { value, sha256: digest(raw) };
  };
  const observeProducer = async token => {
    const observed = await json(`/repos/${repository}/actions/runs/${selection.run_id}`, token), run = observed.value;
    check(integerId(run.id) === selection.run_id && integerId(run.repository?.id) === selection.repository_id
      && run.repository.full_name === repository && run.repository.private === true
      && integerId(run.head_repository?.id) === selection.repository_id && run.head_repository.full_name === repository
      && integerId(run.workflow_id) === producer.data.workflow_id && run.path === producer.data.workflow_path
      && run.head_sha === selection.source_sha && run.head_branch === 'main' && run.event === 'workflow_dispatch'
      && run.run_attempt === 1 && run.status === 'completed' && run.conclusion === 'success'
      && integerId(run.actor?.id) === producer.data.app_actor_id && integerId(run.triggering_actor?.id) === producer.data.app_actor_id);
    const created = time(run.created_at), completed = time(run.updated_at);
    check(created > 0 && created <= selection.created_at && selection.created_at <= completed && completed * 1000 <= now());
    return observed;
  };
  check(activeCacheReads < 2); activeCacheReads++;
  try {
    const operation = async () => {
      let granted = false;
      grant = await acquireGrant(async (path, token, method, body, status) => {
        check(!granted && /^\/app\/installations\/[1-9][0-9]*\/access_tokens$/.test(path) && method === 'POST' && status === 201);
        granted = true; return (await json(path, token, method, body, status)).value;
      });
      const producerRun = await observeProducer(grant.token);
      const metadata = await json(`/repos/${repository}/actions/artifacts/${selection.github_artifact_id}`, grant.token), item = metadata.value;
      check(integerId(item.id) === selection.github_artifact_id && item.expired === false
        && item.digest === `sha256:${selection.github_artifact_sha256}` && item.size_in_bytes === selection.github_artifact_bytes
        && integerId(item.workflow_run?.id) === selection.run_id && integerId(item.workflow_run?.repository_id) === selection.repository_id
        && item.workflow_run.head_sha === selection.source_sha && time(item.created_at) === selection.created_at
        && time(item.expires_at) === selection.expires_at);
      await current();
      const response = await fetchImpl(`https://api.github.com/repos/${repository}/actions/artifacts/${selection.github_artifact_id}/zip`,
        { redirect: 'manual', signal: controller.signal, headers: headers(grant.token) });
      check(response.status === 302); const location = response.headers.get('location');
      check(typeof location === 'string' && location.length <= 8192); const url = new URL(location);
      check(url.protocol === 'https:' && !url.username && !url.password && !url.port && !url.hash
        && /^(?:[a-z0-9-]+\.blob\.core\.windows\.net|[a-z0-9-]+\.actions\.githubusercontent\.com)$/.test(url.hostname));
      await current();
      const archive = await fetchImpl(url, { redirect: 'error', signal: controller.signal, headers: { 'Accept-Encoding': 'identity' } });
      check(archive.status === 200); await current();
      directory = mkdtempSync(join(realpathSync(tmpdir()), 'mrn-source-cache-'));
      const partial = join(directory, 'archive.partial'), path = join(directory, 'archive.zip');
      fd = openSync(partial, constants.O_WRONLY | constants.O_CREAT | constants.O_EXCL | constants.O_NOFOLLOW, 0o600);
      const hash = createHash('sha256');
      const size = await entity(archive, selection.github_artifact_bytes, chunk => {
        hash.update(chunk);
        for (let offset = 0; offset < chunk.length;) {
          check(!controller.signal.aborted && fd !== undefined);
          const n = writeSync(fd, chunk, offset, Math.min(65536, chunk.length - offset)); check(n > 0); offset += n;
        }
      });
      const stat = fstatSync(fd); check(stat.isFile() && stat.nlink === 1 && (stat.mode & 0o077) === 0 && stat.uid === process.getuid()
        && stat.size === size && size === item.size_in_bytes && hash.digest('hex') === selection.github_artifact_sha256);
      // A rerun or changed producer observation during transfer invalidates the
      // download. This is still inside the original sixty-second/read allowance.
      check((await observeProducer(grant.token)).sha256 === producerRun.sha256);
      fsyncSync(fd); closeSync(fd); fd = undefined;
      await current(); renameSync(partial, path); await current(); succeeded = true;
      const removeAt = Math.min(expiresAt, selection.expires_at * 1000);
      const expiryTimer = setTimeout(() => rmSync(directory, { recursive: true, force: true }), Math.max(1, removeAt - now()));
      expiryTimer.unref();
      // GitHub wraps uploaded files in its own ZIP. The catalog hash describes
      // the inner source.zip; only the independent envelope verifier may open it.
      return { archivePath: path, archiveSha256: selection.github_artifact_sha256, bytes: size,
        sourceArchiveSha256: artifact.archive_sha256, sourceArchiveBytes: selection.source_archive_bytes,
        metadataSha256: metadata.sha256, producerRunSha256: producerRun.sha256, producerRunVerified: true,
        inventoryVerified: false, sourceBytesVerified: false, admitted: false,
        expiresAt: removeAt, cleanup: () => { clearTimeout(expiryTimer); rmSync(directory, { recursive: true, force: true }); } };
    };
    const timeout = new Promise((_, reject) => {
      timer = setTimeout(() => { controller.abort(); reject(new Error('MANAGEMENT_SOURCE_CACHE_TIMEOUT')); },
        Math.max(1, Math.min(60000, expiresAt - now(), selection.expires_at * 1000 - now())));
    });
    return await Promise.race([operation(), timeout]);
  } catch { controller.abort(); check(false); }
  finally { activeCacheReads--; clearTimeout(timer); if (fd !== undefined) { closeSync(fd); fd = undefined; } if (directory && !succeeded) rmSync(directory, { recursive: true, force: true }); }
}
