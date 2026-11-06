import assert from 'node:assert/strict';
import { lstatSync, readFileSync, renameSync } from 'node:fs';

export const profileRoute = '/wp-json/wp-abilities/v1/abilities/mrn-mainwp/read-management-access-veto-query-existing-v2/run';
export function claimProfileRequest(directory, input, options, optIn) {
  assert.equal(optIn, 'github-disposable-profile-only');
  const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url);
  if (url.origin !== 'https://wpcontrol.mrndev.io' || url.pathname !== profileRoute) return options;
  assert.equal(options.method, 'POST'); assert.equal(url.search, '');
  const armed = new URL('access-profile-armed', directory), claimed = new URL('access-profile-claimed', directory);
  let info;
  try { info = lstatSync(armed); } catch (error) { if (error.code === 'ENOENT') return options; throw error; }
  assert.ok(info.isFile() && !info.isSymbolicLink() && info.size === 13 && (info.mode & 0o777) === 0o600);
  assert.equal(readFileSync(armed, 'utf8'), 'PROFILE_ONLY\n');
  // mkdir in the owner hook and a single atomic rename allow one subprocess
  // to claim the marker. All signed bytes, abort signals and credentials stay.
  try { renameSync(armed, claimed); } catch (error) { if (error.code === 'ENOENT') return options; throw error; }
  const headers = new globalThis.Headers(options.headers);
  assert.equal(headers.has('cookie'), false);
  headers.set('cookie', 'XDEBUG_TRIGGER=mrn-access-profile-only');
  return { ...options, headers };
}
