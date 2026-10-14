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
