import { digest, requireThat } from './contracts.mjs';

// Separate private read surface. These bounds do not widen the old 64 KiB
// receipt download, public tools or the 2.5-second MainWP access-veto handler.
export const managementEvidenceLimits = Object.freeze({ acquisitionMs: 60000, requestMs: 30000,
  grantBytes: 32768, metadataBytes: 131072, qualificationBytes: 262144, acquisitionBytes: 196608 });
const idValid = value => typeof value === 'string' && /^[1-9][0-9]{0,19}$/.test(value);
const refuse = (condition, code, message) => requireThat(condition, `MANAGEMENT_EVIDENCE_${code}`, message);

// authorize resolves the original immutable human request. The caller supplies
// service-recorded identifiers, never a report-selected run or download URL.
export async function createManagementEvidenceTransport({ repository, acquireGrant, authorize, expiresAt,
  fetchImpl = fetch, now = () => Date.now(), monotonic = () => performance.now() }) {
  refuse(/^mrnwebdesigns\/[A-Za-z0-9_-]+$/.test(repository) && typeof authorize === 'function'
    && Number.isSafeInteger(expiresAt) && expiresAt > now(), 'CONFIG', 'An exact configured repository and unexpired original intent are required.');
  const started = monotonic(), startedAt = now(), used = new Set(), completed = new Set(), artifactIds = new Set();
  let failed = false, grant, active = false, selectedRun;
  const remaining = () => {
    const elapsed = monotonic() - started;
    const left = Math.min(managementEvidenceLimits.acquisitionMs - elapsed, expiresAt - now());
    refuse(!failed && elapsed >= 0 && now() >= startedAt && left > 0,
      'EXPIRED', 'The original evidence acquisition expired or was already refused.');
    return left;
  };
  const current = async () => {
    remaining(); await authorize(); remaining();
    if (grant) refuse(Date.parse(grant.expires_at) > now(), 'EXPIRED', 'The central installation grant expired.');
  };
  // One monotonic budget includes token acquisition, every network read and
  // authorization check. A partial/lost response poisons this acquisition.
  const once = async (key, perform) => {
    refuse(!active && !used.has(key), 'REPLAY', 'Evidence acquisition does not retry or overlap consumed reads.');
    used.add(key); active = true;
    const controller = new globalThis.AbortController(); let timer;
    try {
      const milliseconds = Math.max(1, Math.ceil(Math.min(remaining(), managementEvidenceLimits.requestMs)));
      const timeout = new Promise((_, reject) => {
        timer = setTimeout(() => {
          controller.abort();
          try { refuse(false, 'EXPIRED', 'The protected evidence request exceeded its original deadline.'); } catch (error) { reject(error); }
        }, milliseconds);
      });
      const operation = (async () => {
        await current();
        const result = await perform(controller.signal);
        await current(); return result;
      })();
      const result = await Promise.race([operation, timeout]); completed.add(key); return result;
    } catch (error) { failed = true; controller.abort(); throw error; }
    finally { clearTimeout(timer); active = false; }
  };
  const bytes = async (response, limit, signal) => {
    refuse(response.body && !signal.aborted, 'BODY', 'A complete evidence response is required.');
    refuse(['', 'identity'].includes((response.headers.get('content-encoding') ?? '').toLowerCase()),
      'BODY', 'Evidence must use the requested identity encoding.');
    const length = response.headers.get('content-length');
    refuse(length === null || (/^(0|[1-9][0-9]*)$/.test(length) && Number(length) <= limit), 'SIZE', 'Evidence exceeds its fixed byte limit.');
    const reader = response.body.getReader(), chunks = []; let size = 0, complete = false;
    try {
      while (true) {
        remaining(); refuse(!signal.aborted, 'EXPIRED', 'The evidence read expired.');
        const part = await reader.read();
        if (part.done) { complete = true; break; }
        size += part.value.length;
        refuse(size <= limit, 'SIZE', 'Evidence exceeds its fixed byte limit.'); chunks.push(Buffer.from(part.value));
      }
      refuse(size > 0 && (length === null || size === Number(length)), 'BODY', 'The evidence body is empty or incomplete.');
      return Buffer.concat(chunks);
    } finally { if (!complete) void reader.cancel().catch(() => {}); reader.releaseLock(); }
  };
  const headers = token => ({ Accept: 'application/vnd.github+json', Authorization: `Bearer ${token}`,
    'X-GitHub-Api-Version': '2026-03-10', 'Content-Type': 'application/json', 'Accept-Encoding': 'identity' });
  const json = async (path, token, signal, limit, method = 'GET', body, status = 200) => {
    let response;
    try { response = await fetchImpl(`https://api.github.com${path}`, { method, redirect: 'error', signal,
      headers: headers(token), ...(body === undefined ? {} : { body: JSON.stringify(body) }) }); }
    catch { refuse(false, 'UNAVAILABLE', 'The protected GitHub evidence request failed. No retry was attempted.'); }
    refuse(response.status === status && response.headers.get('content-type')?.includes('application/json'),
      'RESPONSE', 'GitHub did not return the required evidence response.');
    const raw = await bytes(response, limit, signal);
    refuse(Buffer.from(raw.toString('utf8')).equals(raw), 'JSON', 'Evidence metadata must be valid UTF-8.');
    let value; try { value = JSON.parse(raw.toString('utf8')); } catch { refuse(false, 'JSON', 'Evidence metadata is invalid.'); }
    return { value, sha256: digest(raw) };
  };
  grant = await once('grant', signal => acquireGrant(async (path, token, method, body, status) => {
    refuse(/^\/app\/installations\/[1-9][0-9]*\/access_tokens$/.test(path) && method === 'POST' && status === 201,
      'ROUTE', 'Only the central installation grant may be requested.');
    return (await json(path, token, signal, managementEvidenceLimits.grantBytes, method, body, status)).value;
  }));
  // The shared App verifies exact repository, grant expiry and least privilege.
  // No arbitrary route, request method, hostname or URL escapes this facade.
  const metadata = (kind, runId) => {
    refuse(idValid(runId) && (!selectedRun || selectedRun === runId), 'ID', 'Use the same service-recorded run identifier.');
    selectedRun = runId;
    const suffix = kind === 'run' ? '' : '/artifacts?per_page=100';
    return once(kind, signal => json(`/repos/${repository}/actions/runs/${runId}${suffix}`, grant.token,
      signal, managementEvidenceLimits.metadataBytes));
  };
  return Object.freeze({
    readRun: runId => metadata('run', runId),
    readArtifacts: runId => metadata('artifacts', runId),
    downloadArtifact: (artifactId, role) => {
      refuse(idValid(artifactId) && ['qualification', 'acquisition'].includes(role) && !artifactIds.has(artifactId),
        'ID', 'Use two distinct service-verified artifact identifiers and fixed roles.');
      artifactIds.add(artifactId);
      return once(`artifact:${role}`, async signal => {
        let redirect;
        try { redirect = await fetchImpl(`https://api.github.com/repos/${repository}/actions/artifacts/${artifactId}/zip`,
          { headers: headers(grant.token), redirect: 'manual', signal }); }
        catch { refuse(false, 'UNAVAILABLE', 'The protected artifact redirect is unavailable.'); }
        refuse(redirect.status === 302, 'RESPONSE', 'GitHub did not return the expected artifact redirect.');
        const location = redirect.headers.get('location');
        refuse(typeof location === 'string' && location.length <= 8192, 'HOST', 'The artifact redirect is invalid.');
        let url; try { url = new URL(location); } catch { refuse(false, 'HOST', 'The artifact redirect is invalid.'); }
        refuse(url.protocol === 'https:' && !url.username && !url.password && !url.port && !url.hash
          && /^(?:[a-z0-9-]+\.blob\.core\.windows\.net|[a-z0-9-]+\.actions\.githubusercontent\.com)$/.test(url.hostname),
        'HOST', 'The artifact redirect is outside GitHub storage.');
        await current(); refuse(!signal.aborted, 'EXPIRED', 'The original evidence request expired.');
        let response;
        // Installation authorization never follows the storage redirect.
        try { response = await fetchImpl(url, { redirect: 'error', signal, headers: { 'Accept-Encoding': 'identity' } }); }
        catch { refuse(false, 'UNAVAILABLE', 'The protected artifact download failed.'); }
        refuse(response.status === 200, 'RESPONSE', 'The protected artifact is unavailable.');
        const raw = await bytes(response, managementEvidenceLimits[`${role}Bytes`], signal);
        return { bytes: raw, sha256: digest(raw), downloadedAt: now() };
      });
    },
    finish: () => once('finish', async () => {
      refuse(['run', 'artifacts', 'artifact:qualification', 'artifact:acquisition'].every(key => completed.has(key)),
        'INCOMPLETE', 'Both original artifacts and their run metadata are required.');
      return { startedAt, completedAt: now() };
    }),
  });
}
