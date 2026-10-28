import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import test from 'node:test';
import { claimProfileRequest, profileRoute } from './runtime/access-profile-trigger.mjs';

const enabled = 'github-disposable-profile-only', target = 'https://wpcontrol.mrndev.io' + profileRoute;
const fixture = run => {
  const dir = mkdtempSync(join(tmpdir(), 'mrn-profile-selection-'));
  try { run(pathToFileURL(dir + '/')); } finally { rmSync(dir, { recursive: true, force: true }); }
};
const arm = directory => writeFileSync(new URL('access-profile-armed', directory), 'PROFILE_ONLY\n', { mode: 0o600, flag: 'wx' });

test('diagnostic opt-in and exact route precede one-shot selection', () => fixture(directory => {
  arm(directory);
  const options = { method: 'POST', body: '{"signed":"unchanged"}', headers: { authorization: 'synthetic-only' }, signal: AbortSignal.timeout(2000) };
  assert.throws(() => claimProfileRequest(directory, target, options, undefined));
  for (const url of [target.replace('wpcontrol.mrndev.io', 'other.invalid'), target + '/extra', target.replace('read-management-access', 'read-content-access')])
    assert.equal(claimProfileRequest(directory, url, options, enabled), options);
  assert.throws(() => claimProfileRequest(directory, target + '?probe=1', options, enabled));
  assert.throws(() => claimProfileRequest(directory, target, { ...options, method: 'GET' }, enabled));
  const selected = claimProfileRequest(directory, target, options, enabled);
  assert.equal(selected.body, options.body); assert.equal(selected.signal, options.signal);
  assert.equal(selected.headers.get('authorization'), options.headers.authorization);
  assert.equal(selected.headers.get('cookie'), 'XDEBUG_TRIGGER=mrn-access-profile-only');
  assert.deepEqual([...selected.headers.keys()].sort(), ['authorization', 'cookie']);
  assert.deepEqual(options.headers, { authorization: 'synthetic-only' });
  assert.equal(claimProfileRequest(directory, target, options, enabled), options);
  assert.equal(readFileSync(new URL('access-profile-claimed', directory), 'utf8'), 'PROFILE_ONLY\n');
}));

test('diagnostic marker rejects symlinks, directories, broad permissions and other contents', () => {
  for (const kind of ['symlink', 'directory', 'mode', 'contents']) fixture(directory => {
    const marker = new URL('access-profile-armed', directory);
    if (kind === 'symlink') { writeFileSync(new URL('elsewhere', directory), 'PROFILE_ONLY\n'); symlinkSync(new URL('elsewhere', directory), marker); }
    if (kind === 'directory') mkdirSync(marker);
    if (kind === 'mode') writeFileSync(marker, 'PROFILE_ONLY\n', { mode: 0o644 });
    if (kind === 'contents') writeFileSync(marker, 'NOT_A_PROFILE', { mode: 0o600 });
    assert.throws(() => claimProfileRequest(directory, target, { method: 'POST' }, enabled));
  });
});

test('existing cookies are refused, never replaced', () => fixture(directory => {
  arm(directory);
  assert.throws(() => claimProfileRequest(directory, target, { method: 'POST', headers: { cookie: 'session=synthetic' } }, enabled));
}));

test('bounded profile parser, redaction and cleanup failure tests', () => {
  execFileSync('python3', [new URL('./runtime/test-access-profile.py', import.meta.url).pathname], { timeout: 30000, maxBuffer: 65536 });
});

test('ordinary integration workflow matches its reviewed checksum and profile stop precedes capture', () => {
  const normal = readFileSync(new URL('../../../.github/workflows/operations-ci.yml', import.meta.url));
  // Reviewed fixture-only addition retains a bounded capture-progress report.
  // Request/authority deadlines and diagnostic-profile isolation are unchanged.
  assert.equal(createHash('sha256').update(normal).digest('hex'), '737228611aed6da43e1003d1458e499640fa5afd0fe5ab30bea4e7ce610f6042');
  const source = readFileSync(new URL('./runtime/check-existing-binding.mjs', import.meta.url), 'utf8');
  assert.ok(source.indexOf('throw profileStop;') < source.indexOf("phase = 'private-capture-interruption'"));
  assert.ok(source.includes('(context.limits.hook_seconds - 10) * 1000'));
  const workflow = readFileSync(new URL('../../../.github/workflows/operations-access-profile.yml', import.meta.url), 'utf8');
  assert.ok(workflow.indexOf('build-access-profiler.sh') < workflow.indexOf('run-dashboard-api.sh'));
  assert.ok(workflow.includes("assert r['result'] == 'PROFILE_ONLY'"));
  assert.ok(workflow.includes('MRN_DISPOSABLE_ACCESS_HOOK: ${{ github.workspace }}/tools/operations/test/runtime/access-hook.sh'));
});
