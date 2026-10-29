import test from 'node:test';
import assert from 'node:assert/strict';
import { parseExistingSourceProgress } from './runtime/existing-source-progress.mjs';
const value = () => ({ schema: 1, scope: 'disposable-existing-source-capture-progress', state: 'available',
  phases: [{ ordinal: 1, scope: 'capture', phase: 'controller', elapsed_us: 0 },
    { ordinal: 2, scope: 'capture', phase: 'reservation', elapsed_us: 30 },
    { ordinal: 3, scope: 'claim', phase: 'authenticate', elapsed_us: 40 }],
  handler_finished: false, current_access_proven: false, production_changes: false });
const parse = v => parseExistingSourceProgress(Buffer.from(JSON.stringify(v)));
test('pending and early-finished capture phase reports retain diagnostics without granting authority', () => {
  const v = value(); assert.deepEqual(parse(v), v);
  v.phases.push({ ordinal: 4, scope: 'capture', phase: 'finished', elapsed_us: 500 });v.handler_finished = true;
  assert.deepEqual(parse(v), v);
  for (const state of ['unavailable', 'malformed']) assert.deepEqual(parse({ ...v, state, phases: [], handler_finished: false }).phases, []);
});
test('unknown fields, reordered phases, misleading completion and private data are refused', () => {
  for (const change of [v => { v.secret = 'PRIVATE'; }, v => { v.current_access_proven = true; },
    v => { v.handler_finished = true; }, v => { v.phases[1].phase = 'projection'; },
    v => { v.phases[2].elapsed_us = 0; }, v => { v.phases[0].ordinal = 2; },
    v => { v.phases[0].elapsed_us = true; }, v => { v.phases[0].elapsed_us = 300000001; },
    v => { v.phases[0].email = 'PRIVATE'; }, v => { v.state = 'unavailable'; },
    v => { v.phases = []; }, v => { v.phases[0].scope = 'PRIVATE'; }]) {
    const v = value();change(v);assert.throws(() => parse(v), /^Error: Invalid disposable source-capture progress$/);
  }
  for (const v of [null, 'PRIVATE', Buffer.alloc(0), Buffer.alloc(16385), Buffer.from('PRIVATE')])
    assert.throws(() => parseExistingSourceProgress(v), /^Error: Invalid disposable source-capture progress$/);
});

test('native sync, parity and file timing prefixes cannot claim handler completion or skip a phase', () => {
  const steps = [['capture', 'controller'], ['capture', 'reservation'],
    ...'authenticate preflight exclusion original-guard locked-history counter prepare write-veto claim-write commit first-readback release-veto final-readback'.split(' ').map(p => ['claim', p]),
    ['capture', 'native'], ...'preflight assignment sync parity-before files parity-after finished'.split(' ').map(p => ['native', p])];
  const v = value();
  v.phases = steps.map(([scope, phase], i) => ({ ordinal: i + 1, scope, phase, elapsed_us: i * 100 }));
  assert.deepEqual(parse(v), v);
  assert.equal(parse(v).handler_finished, false);
  assert.throws(() => parse({ ...v, handler_finished: true }));
  const skipped = structuredClone(v); skipped.phases.splice(18, 1); skipped.phases.forEach((p, i) => { p.ordinal = i + 1; });
  assert.throws(() => parse(skipped));
  const secret = structuredClone(v); secret.phases[18].phase = 'private-file-path';
  assert.throws(() => parse(secret));
  v.phases.push({ ordinal: v.phases.length + 1, scope: 'capture', phase: 'finished', elapsed_us: 3000 }); v.handler_finished = true;
  assert.deepEqual(parse(v), v);
  assert.equal(parse(v).current_access_proven, false);
});
