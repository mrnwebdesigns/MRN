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
