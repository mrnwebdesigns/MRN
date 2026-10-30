#!/usr/bin/env bash
set -euo pipefail
gateway_test_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Frozen contract origin and current compatible runtime have distinct pins.
# Verify the actual checkout and complete upstream byte closure before any
# gateway call; metadata declarations alone cannot establish compatibility.
node --input-type=module - "$gateway_test_root" <<'JS'
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync, lstatSync } from 'node:fs';
import { resolve, isAbsolute } from 'node:path';
import { execFileSync } from 'node:child_process';
const gateway = resolve(process.argv[2], '../..'), provider = process.env.MRN_GATEWAY_PROVIDER_SOURCE;
assert.ok(typeof provider === 'string' && isAbsolute(provider), 'The exact pinned provider checkout is required');
const frozen = JSON.parse(readFileSync(resolve(gateway, 'contracts/content-qualification-sources.json')));
const runtime = JSON.parse(readFileSync(resolve(gateway, 'test/runtime/sources.json')));
assert.deepEqual(runtime.provider, { repository: frozen.compatible_runtime.repository, commit: frozen.compatible_runtime.commit });
assert.equal(frozen.repository, frozen.compatible_runtime.repository);
assert.equal(execFileSync('git', ['-C', provider, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), runtime.provider.commit);
const canonical = value => Array.isArray(value) ? '[' + value.map(canonical).join(',') + ']' : value && typeof value === 'object'
  ? '{' + Object.keys(value).sort().map(k => JSON.stringify(k) + ':' + canonical(value[k])).join(',') + '}' : JSON.stringify(value);
const sha = bytes => createHash('sha256').update(bytes).digest('hex');
assert.equal(frozen.compatible_runtime.files_sha256, sha(canonical(frozen.files)));
assert.equal(frozen.files.length, 11);
for (const file of frozen.files) {
  assert.match(file.source, /^(?:contracts|tests\/fixtures)\/[a-z0-9][a-z0-9.-]*\.json$/);
  assert.match(file.local, /^(?:contracts|test\/fixtures)\/[a-z0-9][a-z0-9.-]*\.json$/);
  assert.ok(!file.source.includes('..') && !file.local.includes('..'));
  const upstream = resolve(provider, file.source), copy = resolve(gateway, file.local);
  for (const path of [upstream, copy]) { const info = lstatSync(path); assert.ok(info.isFile() && !info.isSymbolicLink()); }
  const bytes = readFileSync(upstream); assert.equal(sha(bytes), file.sha256); assert.ok(bytes.equals(readFileSync(copy)));
}
console.log('PASS pinned provider and complete frozen contract byte closure');
JS
node "$gateway_test_root/check-provider.mjs" "$@"
