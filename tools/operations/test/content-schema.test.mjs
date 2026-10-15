import test from 'node:test';
import assert from 'node:assert/strict';
import { compileContentSchema } from '../src/content-schema.mjs';

const ref = name => ({ $ref: `#/definitions/${name}` });
const object = properties => ({ type: 'object', properties, required: Object.keys(properties), additionalProperties: false });
const definitions = { hash: { type: 'string', pattern: '^[a-f0-9]{64}$' },
  target: object({ site_id: { type: 'integer', minimum: 1 }, snapshot: ref('hash') }) };

test('local content definitions preserve exact nested records and reject unknown or missing fields', () => {
  const schema = compileContentSchema(object({ target: ref('target') }), definitions);
  const value = { target: { site_id: 1, snapshot: 'a'.repeat(64) } };
  assert.deepEqual(schema.parse(value), value);
  for (const bad of [{ ...value, actor: 'caller' }, { target: { ...value.target, email: 'caller@example.invalid' } },
    { target: { site_id: 1 } }, { target: { ...value.target, site_id: 0 } }, { target: { ...value.target, snapshot: 'bad' } }]) {
    assert.equal(schema.safeParse(bad).success, false);
  }
});

test('remote, missing, recursive or ambiguous references cannot compile or load content definitions', () => {
  for (const schema of [{ $ref: 'https://example.invalid/schema' }, { $ref: '#/definitions/missing' },
    { ...ref('target'), type: 'string' }, { $ref: '#/other/target' }, { $ref: '#/definitions/constructor' }]) {
    assert.throws(() => compileContentSchema(schema, definitions), { code: 'CONTENT_CONTRACT' });
  }
  assert.throws(() => compileContentSchema(ref('a'), { a: ref('b'), b: ref('a') }), { code: 'CONTENT_CONTRACT' });
  let deep = { type: 'boolean' };
  for (let i = 0; i < 70; i++) deep = { type: 'array', items: deep };
  assert.throws(() => compileContentSchema(deep), { code: 'CONTENT_CONTRACT' });
});

test('closed content schema vocabulary rejects unimplemented constraints instead of ignoring them', () => {
  for (const schema of [{ type: 'string', format: 'uri' }, { type: 'string', oneOf: [] },
    { type: 'object', properties: {}, required: [], additionalProperties: true },
    { ...object({}), required: ['missing'] }, { type: 'array', items: { type: 'string' }, uniqueItems: 'true' }]) {
    assert.throws(() => compileContentSchema(schema), { code: 'CONTENT_CONTRACT' });
  }
});

test('unique recipe and consumer lists reject semantic duplicates including reordered object keys', () => {
  const schema = compileContentSchema({ type: 'array', items: ref('target'), uniqueItems: true, minItems: 1, maxItems: 3 }, definitions);
  const first = { site_id: 1, snapshot: 'a'.repeat(64) }, second = { site_id: 2, snapshot: 'a'.repeat(64) };
  assert.deepEqual(schema.parse([first, second]), [first, second]);
  assert.equal(schema.safeParse([first, { snapshot: first.snapshot, site_id: first.site_id }]).success, false);
  assert.equal(schema.safeParse([]).success, false);
  assert.equal(schema.safeParse([first, second, { ...second, site_id: 3 }, { ...second, site_id: 4 }]).success, false);
});

test('definition expansion retains strict Unicode, bounded integers and nullable output rules', () => {
  const schema = compileContentSchema(object({ text: { type: 'string', minLength: 1, maxLength: 4 },
    revision: { anyOf: [{ type: 'integer', minimum: 1 }, { type: 'null' }] } }));
  assert.deepEqual(schema.parse({ text: 'ok', revision: null }), { text: 'ok', revision: null });
  for (const value of [{ text: '\ud800', revision: 1 }, { text: '\0', revision: 1 }, { text: 'large', revision: 1 },
    { text: 'ok', revision: Number.MAX_SAFE_INTEGER + 1 }, { text: 'ok', revision: 1.2 }]) {
    assert.equal(schema.safeParse(value).success, false);
  }
});

test('exclusive content outcomes keep committed and uncertain states separate', () => {
  const committed = object({ state: { type: 'string', enum: ['committed'] },
    confirmed: { type: 'boolean', enum: [true] }, evidence: ref('hash') });
  const pending = object({ state: { type: 'string', enum: ['pending'] },
    confirmed: { type: 'boolean', enum: [false] }, evidence: { type: 'null' } });
  const schema = compileContentSchema({ oneOf: [committed, pending] }, definitions);
  const value = { state: 'committed', confirmed: true, evidence: 'a'.repeat(64) };
  const parsed = schema.parse(value);
  assert.deepEqual(parsed, value); assert.notEqual(parsed, value);
  assert.deepEqual(schema.parse({ state: 'pending', confirmed: false, evidence: null }),
    { state: 'pending', confirmed: false, evidence: null });
  for (const bad of [{ ...value, confirmed: false }, { ...value, state: 'pending' },
    { ...value, evidence: null }, { ...value, actor: 'caller' }, { state: 'pending', confirmed: false }, undefined])
    assert.equal(schema.safeParse(bad).success, false);
});

test('oneOf rejects overlapping branches while anyOf still accepts a valid union', () => {
  const alternatives = [{ type: 'integer', minimum: 0, maximum: 10 }, { type: 'integer', minimum: 5, maximum: 15 }];
  const exclusive = compileContentSchema({ oneOf: alternatives });
  assert.equal(exclusive.parse(0), 0); assert.equal(exclusive.parse(15), 15);
  for (const bad of [5, 7, 10, -1, 16, 5.5, '0']) assert.equal(exclusive.safeParse(bad).success, false);
  assert.equal(compileContentSchema({ anyOf: alternatives }).parse(7), 7);
  const nullable = compileContentSchema({ oneOf: [{ type: 'null' }, { type: 'boolean' }] });
  assert.equal(nullable.parse(null), null); assert.equal(nullable.parse(false), false);
});

test('exclusive alternatives retain local reference validation and bounded schema complexity', () => {
  const schema = compileContentSchema(object({ outcome: { oneOf: [ref('target'), { type: 'null' }] } }), definitions);
  assert.deepEqual(schema.parse({ outcome: { site_id: 1, snapshot: 'a'.repeat(64) } }),
    { outcome: { site_id: 1, snapshot: 'a'.repeat(64) } });
  for (const oneOf of [null, [], [{ type: 'null' }], Array(33).fill({ type: 'null' }),
    [ref('missing'), { type: 'null' }], [{ $ref: 'https://example.invalid/schema' }, { type: 'null' }]])
    assert.throws(() => compileContentSchema({ oneOf }, definitions), { code: 'CONTENT_CONTRACT' });
  assert.throws(() => compileContentSchema({ oneOf: [{ type: 'null' }, { type: 'boolean' }], type: 'boolean' }),
    { code: 'CONTENT_CONTRACT' });
  assert.throws(() => compileContentSchema(ref('loop'), { loop: { oneOf: [ref('loop'), { type: 'null' }] } }),
    { code: 'CONTENT_CONTRACT' });
});
