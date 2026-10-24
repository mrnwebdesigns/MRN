import test from 'node:test';
import assert from 'node:assert/strict';
import { compileManagementSchema, compileManagementCaptureSchema, managementWireLimits,
  managementCaptureWireLimits } from '../src/content-management-schema.mjs';
import { delegationCanonical } from '../src/delegation.mjs';

const ref = name => ({ $ref: `#/$defs/${name}` });
const map = value => ({ type: 'object', additionalProperties: value, maxProperties: 1000,
  propertyNames: { type: 'string', pattern: '^[A-Za-z0-9_]+$', maxLength: 191 } });
const list = (items, maxItems = 16384) => ({ type: 'array', items, maxItems });
const raw = { anyOf: [{ type: 'string' }, { type: 'null' }, list(ref('raw')), map(ref('raw'))] };
const recursive = () => compileManagementCaptureSchema({ ...ref('raw'), $defs: { raw } });
const nested = depth => depth ? { value: nested(depth - 1) } : null;

test('private capture accommodates a full parity inventory without widening public schemas', () => {
  const wire = list({ type: 'object', properties: { path: { type: 'string', maxLength: 512 }, sha256: { type: 'string', pattern: '^[a-f0-9]{64}$' } },
    required: ['path', 'sha256'], additionalProperties: false });
  const schema = compileManagementCaptureSchema(wire);
  const rows = Array.from({ length: 8000 }, (_, i) => ({ path: `wp-content/themes/fixture-child/${i}.php`, sha256: 'a'.repeat(64) }));
  assert.deepEqual(schema.parse(rows), rows);
  assert.throws(() => compileManagementSchema(wire), { code: 'CONTENT_MANAGEMENT_CONTRACT' });
  assert.equal(managementWireLimits.arrayItems, 1000);
  assert.equal(compileManagementSchema({ type: 'string' }).safeParse('a'.repeat(524288)).success, false);
});

test('private capture counts canonical UTF-8 bytes and escaping at its independent envelope ceiling', () => {
  const schema = compileManagementCaptureSchema({ type: 'string' }), max = managementCaptureWireLimits.bytes;
  for (const value of ['a'.repeat(max - 2), 'é'.repeat((max - 2) / 2), '\n'.repeat((max - 2) / 2)])
    assert.equal(schema.safeParse(value).success, true);
  for (const value of ['a'.repeat(max - 1), 'é'.repeat(max / 2), '\n'.repeat(max / 2)])
    assert.equal(schema.safeParse(value).success, false);
});

test('private capture enforces depth, collection and node limits before recursive schema work', () => {
  const schema = recursive();
  assert.deepEqual(schema.parse(nested(48)), nested(48));
  assert.equal(schema.safeParse(nested(49)).success, false);
  assert.equal(schema.safeParse(Array(16384).fill(null)).success, true);
  assert.equal(schema.safeParse(Array(16385).fill(null)).success, false);
  const simple = compileManagementCaptureSchema(list(list({ type: 'null' })));
  assert.equal(simple.safeParse(Array.from({ length: 12 }, () => Array(16384).fill(null))).success, true);
  assert.equal(simple.safeParse(Array.from({ length: 13 }, () => Array(16384).fill(null))).success, false);
  assert.equal(schema.safeParse(Object.fromEntries(Array.from({ length: 1001 }, (_, i) => ['key' + i, null]))).success, false);
});

test('capture limits cannot be overridden by a schema or by extra public compiler arguments', () => {
  assert.equal(Object.isFrozen(managementCaptureWireLimits), true);
  assert.equal(Object.isFrozen(managementWireLimits), true);
  assert.equal(compileManagementSchema({ type: 'string' }, managementCaptureWireLimits).safeParse('a'.repeat(524288)).success, false);
  for (const wire of [{ type: 'string', maxBytes: 10000000 }, list({ type: 'null' }, 16385),
    { ...map({ type: 'null' }), maxProperties: 1001 }, { ...ref('raw'), $defs: { raw }, limits: { depth: 100 } }])
    assert.throws(() => compileManagementCaptureSchema(wire), { code: 'CONTENT_MANAGEMENT_CONTRACT' });
});

test('larger capture budgets do not permit getters, cyclic values, non-JSON data or ambiguous map identities', () => {
  const schema = recursive(); let calls = 0;
  const accessor = Object.defineProperty({}, 'value', { enumerable: true, get() { calls++; return null; } });
  const cyclic = {}; cyclic.value = cyclic;
  for (const value of [accessor, cyclic, 1.5, -0, '\ud800', '\0', new Map(), Array(1), { toJSON() { calls++; return null; } },
    JSON.parse('{"__proto__":null}'), { constructor: null }]) assert.equal(schema.safeParse(value).success, false);
  assert.equal(calls, 0);
  assert.equal(delegationCanonical(schema.parse({ 0: null })), '{"0":null}');
  assert.equal(delegationCanonical(schema.parse([])), '[]');
  assert.equal(delegationCanonical(schema.parse({})), '{}');
});

test('private capture preserves the ACF traversal ceiling and global branch-work exhaustion', () => {
  const acf_field = { type: 'object', properties: { key: { type: 'string' }, child: ref('acf_field') }, required: ['key'], additionalProperties: false };
  const schema = compileManagementCaptureSchema({ ...ref('acf_field'), $defs: { acf_field } });
  const field = depth => depth ? { key: 'field_group', child: field(depth - 1) } : { key: 'field_leaf' };
  assert.equal(schema.safeParse(field(12)).success, true);
  assert.equal(schema.safeParse(field(13)).success, false);
  const defs = { level0: { type: 'string' } };
  for (let i = 1; i < 21; i++) defs['level' + i] = { oneOf: [ref('level' + (i - 1)), ref('level' + (i - 1))] };
  assert.equal(compileManagementCaptureSchema({ ...ref('level20'), $defs: defs }).safeParse(null).success, false);
  assert.throws(() => compileManagementCaptureSchema({ ...ref('cycle'), $defs: { cycle: { anyOf: [ref('cycle'), { type: 'null' }] } } }),
    { code: 'CONTENT_MANAGEMENT_CONTRACT' });
});
