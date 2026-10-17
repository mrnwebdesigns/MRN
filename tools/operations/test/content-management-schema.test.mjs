import test from 'node:test';
import assert from 'node:assert/strict';
import { compileManagementSchema, managementWireLimits } from '../src/content-management-schema.mjs';
import { compileContentSchema } from '../src/content-schema.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';

const ref = name => ({ $ref: `#/$defs/${name}` });
const object = properties => ({ type: 'object', properties, required: Object.keys(properties), additionalProperties: false });
const list = items => ({ type: 'array', items, maxItems: 1000 });
const map = additionalProperties => ({ type: 'object', additionalProperties, maxProperties: 1000,
  propertyNames: { type: 'string', maxLength: 191, pattern: '^(?!__proto__$|constructor$|prototype$)[ -~]+$' } });
const raw = { oneOf: [{ type: 'string' }, { type: 'integer' }, { type: 'boolean' }, { type: 'null' },
  list(ref('raw')), map(ref('raw'))] };
const compileRaw = () => compileManagementSchema({ ...ref('raw'), $defs: { raw } });
const nested = (depth, value = 'leaf') => depth ? { a: nested(depth - 1, value) } : value;

test('management opts into local recursive typed maps without widening the legacy compiler', () => {
  const schema = compileRaw();
  const value = { field_rows: [{ field_group: { field_body: 'Copy', field_price: '1.5' } }], field_empty: [] };
  const parsed = schema.parse(value);
  assert.deepEqual(parsed, value); assert.notEqual(parsed, value);
  assert.equal(delegationCanonical(parsed), delegationCanonical(value));
  assert.throws(() => compileContentSchema(ref('raw'), { raw }), { code: 'CONTENT_CONTRACT' });
  assert.throws(() => compileContentSchema(map({ type: 'string' })), { code: 'CONTENT_CONTRACT' });
});

test('closed management controls reject extra fields and preserve exact optional/default semantics', () => {
  const schema = compileManagementSchema({ ...object({ confirm: { type: 'boolean', default: false },
    page: { type: 'integer', minimum: 1, default: 1 } }), required: ['confirm'] });
  assert.deepEqual(schema.parse({ confirm: false }), { confirm: false });
  for (const value of [{}, { confirm: 'false' }, { confirm: true, email: 'caller@example.invalid' }, { confirm: false, page: '1' }])
    assert.equal(schema.safeParse(value).success, false);
  assert.throws(() => compileManagementSchema({ type: 'boolean', default: 'false' }), { code: 'CONTENT_MANAGEMENT_CONTRACT' });
  assert.throws(() => compileManagementSchema({ ...ref('raw'), $defs: { raw }, default: 1.5 }), { code: 'CONTENT_MANAGEMENT_CONTRACT' });
});

test('numeric object keys and empty lists/maps retain different canonical identities', () => {
  const schema = compileRaw();
  for (const value of [{}, [], { 0: 'first', 2: 'third' }, ['first', 'third']])
    assert.deepEqual(schema.parse(value), value);
  assert.notEqual(delegationDigest(schema.parse({})), delegationDigest(schema.parse([])));
  assert.notEqual(delegationDigest(schema.parse({ 0: 'first' })), delegationDigest(schema.parse(['first'])));
  const choices = compileManagementSchema(list(object({ key: { type: 'string' }, label: { type: 'string' } })));
  assert.deepEqual(choices.parse([{ key: '日本語', label: 'Japanese' }]), [{ key: '日本語', label: 'Japanese' }]);
  assert.equal(schema.safeParse({ 日本語: 'not a supported object key' }).success, false);
});

test('floats, unsafe integers, invalid Unicode and unsafe object keys never reach canonical signing', () => {
  const schema = compileRaw();
  for (const value of [1.5, -0, NaN, Infinity, Number.MAX_SAFE_INTEGER + 1, 1n, undefined, '\ud800', '\0',
    { a: undefined }, JSON.parse('{"__proto__":{"admin":true}}'), { constructor: 'x' }, { prototype: 'x' }, { '': 'x' }])
    assert.equal(schema.safeParse(value).success, false);
  for (const value of [Number.MAX_SAFE_INTEGER, -Number.MAX_SAFE_INTEGER, '1.5', '0.000000000001'])
    assert.equal(schema.parse(value), value);
});

test('serialization hooks, accessors, hidden values and exotic containers are refused without running code', () => {
  const schema = compileRaw(); let calls = 0;
  const accessor = Object.defineProperty({}, 'a', { enumerable: true, get() { calls++; return 'x'; } });
  const hidden = Object.defineProperty({}, 'a', { value: 'hidden' });
  const symbol = { [Symbol('a')]: 'hidden' };
  const hook = { toJSON() { calls++; return 'replacement'; } };
  const array = ['x']; array.extra = 'hidden';
  const inherited = Object.create({ a: 'inherited' });
  class SpecialArray extends Array {}
  const cyclic = {}; cyclic.a = cyclic;
  for (const value of [accessor, hidden, symbol, hook, array, inherited, new SpecialArray('x'), new Date(), new Map(),
    Array(1), cyclic, Object.assign(['x'], { 2: 'sparse' })]) assert.equal(schema.safeParse(value).success, false);
  assert.equal(calls, 0);
  assert.deepEqual(schema.parse(Object.assign(Object.create(null), { a: 'ok' })), { a: 'ok' });
});

test('complete-response depth, collection size and node bounds apply before recursive branch validation', () => {
  const schema = compileRaw();
  assert.deepEqual(schema.parse(nested(32)), nested(32));
  assert.equal(schema.safeParse(nested(33)).success, false);
  assert.equal(schema.safeParse(Array(1000).fill(null)).success, true);
  assert.equal(schema.safeParse(Array(1001).fill(null)).success, false);
  assert.equal(schema.safeParse(Object.fromEntries(Array.from({ length: 1000 }, (_, i) => ['a' + i, null]))).success, true);
  assert.equal(schema.safeParse(Object.fromEntries(Array.from({ length: 1001 }, (_, i) => ['a' + i, null]))).success, false);
  assert.equal(schema.safeParse(Array.from({ length: 19 }, () => Array(1000).fill(null))).success, true);
  assert.equal(schema.safeParse(Array.from({ length: 20 }, () => Array(1000).fill(null))).success, false);
});

test('wire byte bounds count canonical UTF-8 and escaping rather than JavaScript character counts', () => {
  const schema = compileRaw(), max = managementWireLimits.bytes;
  assert.equal(schema.safeParse('a'.repeat(max - 2)).success, true);
  assert.equal(schema.safeParse('a'.repeat(max - 1)).success, false);
  assert.equal(schema.safeParse('é'.repeat((max - 2) / 2)).success, true);
  assert.equal(schema.safeParse('é'.repeat(max / 2)).success, false);
  assert.equal(schema.safeParse('\n'.repeat((max - 2) / 2)).success, true);
  assert.equal(schema.safeParse('\n'.repeat(max / 2)).success, false);
});

test('ACF definitions permit a root and twelve field traversals while sibling counts stay independent', () => {
  const acf_field = { ...object({ key: { type: 'string' }, sub_field: ref('acf_field') }), required: ['key'] };
  const schema = compileManagementSchema({ ...list(ref('acf_field')), $defs: { acf_field } });
  const field = depth => depth ? { key: 'field_group', sub_field: field(depth - 1) } : { key: 'field_text' };
  assert.equal(schema.safeParse([field(12), field(12)]).success, true);
  assert.equal(schema.safeParse([field(13)]).success, false);
});

test('remote, missing and unguarded recursive references never compile', () => {
  for (const value of [{ $ref: 'https://example.invalid/schema' }, ref('missing'),
    { $ref: '#/definitions/raw', $defs: { raw } }, { ...ref('raw'), type: 'string', $defs: { raw } },
    { ...ref('a'), $defs: { a: ref('a') } }, { ...ref('a'), $defs: { a: ref('b'), b: ref('a') } },
    { ...ref('a'), $defs: { a: { oneOf: [ref('a'), { type: 'null' }] } } },
    { ...object({ a: ref('a') }), $defs: { a: { anyOf: [ref('a'), { type: 'null' }] } } }])
    assert.throws(() => compileManagementSchema(value), { code: 'CONTENT_MANAGEMENT_CONTRACT' });
});

test('unimplemented constraints, open maps and unbounded or ambiguous key policies fail at compile time', () => {
  for (const value of [{ ...object({}), additionalProperties: true }, { type: 'object', additionalProperties: false },
    { ...map({ type: 'string' }), propertyNames: undefined }, { ...map({ type: 'string' }), maxProperties: 1001 },
    { ...map({ type: 'string' }), required: ['missing'] }, { ...map({ type: 'string' }), patternProperties: {} },
    { type: 'array', items: { type: 'string' }, maxItems: 1001 }, { type: 'string', minItems: 1 },
    { type: 'number' }, { type: 'string', enum: [{}] }, { type: 'boolean', description: '\ud800' },
    { type: 'string', minLength: '1' }, { type: 'string', minLength: -1 }, { type: 'integer', minimum: 1.5 },
    { type: 'integer', minimum: 10, maximum: 1 }, { type: 'string', minLength: 10, maxLength: 1 },
    { type: 'string', $defs: JSON.parse('{"constructor":{"type":"string"}}') }])
    assert.throws(() => compileManagementSchema(value));
});

test('exclusive alternatives and typed map keys are enforced at every nested value', () => {
  const schema = compileManagementSchema({ ...map({ oneOf: [{ type: 'integer', minimum: 0, maximum: 10 },
    { type: 'integer', minimum: 5, maximum: 15 }] }), propertyNames: { type: 'string', maxLength: 191, pattern: '^field_' } });
  assert.deepEqual(schema.parse({ field_a: 0, field_b: 15 }), { field_a: 0, field_b: 15 });
  for (const value of [{ field_a: 7 }, { field_a: '0' }, { unqualified: 0 }]) assert.equal(schema.safeParse(value).success, false);
  const unique = compileManagementSchema({ ...list(ref('raw')), uniqueItems: true, $defs: { raw } });
  assert.equal(unique.safeParse([{ a: 1, b: 2 }, { b: 2, a: 1 }]).success, false);
  assert.equal(unique.safeParse([{}, []]).success, true);
});

test('branch expansion has a total work bound even for acyclic overlapping reference graphs', () => {
  const defs = { level0: { type: 'integer' } };
  for (let i = 1; i < 20; i++) defs['level' + i] = { oneOf: [ref('level' + (i - 1)), ref('level' + (i - 1))] };
  assert.equal(compileManagementSchema({ ...ref('level19'), $defs: defs }).safeParse('mismatch').success, false);
});

test('scalar-or-checkbox values apply only explicit type-specific bounds without permitting floats or coercion', () => {
  const wire = { type: ['string', 'integer', 'boolean', 'array', 'null'], maxLength: 5, maxItems: 2,
    items: { type: 'string', maxLength: 3 } };
  const schema = compileManagementSchema(wire);
  for (const value of ['1.5', 1, false, null, [], ['one', 'two']]) assert.deepEqual(schema.parse(value), value);
  for (const value of [1.5, {}, 'longer', ['long'], ['one', 'two', 'tri'], [1]]) assert.equal(schema.safeParse(value).success, false);
  for (const bad of [{ ...wire, ignored: true }, { ...wire, type: ['string', 'number'] }, { ...wire, type: ['string', 'string'] },
    { ...wire, type: ['string', 'boolean'] }, { ...wire, items: { type: 'number' } }])
    assert.throws(() => compileManagementSchema(bad), { code: 'CONTENT_MANAGEMENT_CONTRACT' });
});
