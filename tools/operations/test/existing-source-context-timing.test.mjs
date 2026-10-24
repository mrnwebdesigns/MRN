import test from 'node:test';
import assert from 'node:assert/strict';
import { parseExistingSourceContextTiming } from './runtime/existing-source-context-timing.mjs';

const phaseNames = ['authenticate', 'authenticated-current', 'original-guard', 'guard-current', 'claim',
  'precommit-current', 'first-history', 'post-first-history-current', 'reauthenticate', 'post-reauth-current',
  'output-current', 'access-veto', 'post-veto-current', 'final-history', 'release', 'finished'];
const report = () => ({ schema: 2, scope: 'disposable-existing-source-context-phase-timing', available: true,
  attempts: [{ schema: 2, outcome: 'returned', claim_committed: true, elapsed_us: 15000000,
    phases: phaseNames.map((phase, index) => ({ phase, elapsed_us: index * 1000000 })) }],
  current_access_proven: false, production_changes: false });
const parse = value => parseExistingSourceContextTiming(Buffer.from(JSON.stringify(value)));

test('completed and refused provider timing traces remain diagnostic-only and preserve exact ordered intervals', () => {
  const value = report(); assert.deepEqual(parse(value), value);
  value.attempts.push({ schema: 2, outcome: 'refused', claim_committed: false, elapsed_us: 3100,
    phases: [{ phase: 'authenticate', elapsed_us: 0 }, { phase: 'finished', elapsed_us: 3100 }] });
  assert.deepEqual(parse(value), value);
  assert.deepEqual(parse({ ...value, available: false, attempts: [] }).attempts, []);
});

test('partial, unordered, substituted and oversized timing evidence cannot become a retained diagnostic', () => {
  for (const change of [r => { r.secret = 'PRIVATE'; }, r => { r.current_access_proven = true; },
    r => { r.available = false; }, r => { r.attempts = Array(9).fill(r.attempts[0]); },
    r => { r.attempts[0].outcome = 'PRIVATE'; }, r => { r.attempts[0].claim_committed = false; },
    r => { r.attempts[0].phases[1].phase = 'claim'; }, r => { r.attempts[0].phases[2].elapsed_us = 1; },
    r => { r.attempts[0].elapsed_us++; }, r => { r.attempts[0].phases[15].elapsed_us = 120000001; },
    r => { r.attempts[0].phases[0].elapsed_us = -1; }, r => { r.attempts[0].phases[0].elapsed_us = 0.1; },
    r => { r.attempts[0].phases[0].token = 'PRIVATE'; }, r => { r.attempts[0].phases.pop(); }]) {
    const value = report(); change(value); assert.throws(() => parse(value), /^Error: Invalid disposable source-context timing report$/);
  }
  for (const bytes of [undefined, '', Buffer.alloc(0), Buffer.alloc(32769), Buffer.from('PRIVATE'), Buffer.from('null')])
    assert.throws(() => parseExistingSourceContextTiming(bytes), /^Error: Invalid disposable source-context timing report$/);
});
