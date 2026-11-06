import test from 'node:test';
import assert from 'node:assert/strict';
import { parseExistingDirectoryLock } from './runtime/existing-directory-lock.mjs';
const value = () => ({ schema: 1, scope: 'disposable-directory-token-lock', forced_committed_expiry: true,
  oauth_refresh_in_work: true, cache_write_in_work: true, independent_expired_cache: true, independent_lock_refused: true,
  probe_limit_seconds: 1, probe_elapsed_ms: 1002, production_changes: false, membership_granted_by_probe: false });
const parse = v => parseExistingDirectoryLock(Buffer.from(JSON.stringify(v)));
test('bounded lock and non-lock observations confer no authority', () => {
  const v = value();assert.deepEqual(parse(v), v);
  v.cache_write_in_work = false;v.independent_lock_refused = false;v.probe_elapsed_ms = 1;
  assert.deepEqual(parse(v), v);
});
test('private fields, invented authority, wrong types and unbounded timing are refused', () => {
  for (const change of [v => { v.secret = 'PRIVATE'; }, v => { v.membership_granted_by_probe = true; },
    v => { v.production_changes = true; }, v => { v.oauth_refresh_in_work = false; }, v => { v.forced_committed_expiry = false; },
    v => { v.probe_limit_seconds = 2; }, v => { v.probe_elapsed_ms = true; }, v => { v.probe_elapsed_ms = 10001; },
    v => { delete v.independent_lock_refused; }]) {
    const v = value();change(v);assert.throws(() => parse(v), /^Error: Invalid disposable Directory lock probe$/);
  }
  for (const v of [null, 'PRIVATE', Buffer.alloc(0), Buffer.alloc(2049), Buffer.from('PRIVATE')])
    assert.throws(() => parseExistingDirectoryLock(v), /^Error: Invalid disposable Directory lock probe$/);
});
