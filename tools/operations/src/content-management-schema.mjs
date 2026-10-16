import { z } from 'zod';
import { requireThat } from './contracts.mjs';
import { delegationCanonical } from './delegation.mjs';
import { compileContentSchema } from './content-schema.mjs';

// Opt-in vocabulary for the checked-in management contract. Existing content
// schemas keep their acyclic, closed-object compiler and are not widened.
export const managementWireLimits = Object.freeze({ depth: 32, nodes: 20000,
  mapEntries: 1000, arrayItems: 1000, bytes: 524288, validationSteps: 250000 });
const unsafe = new Set(['__proto__', 'prototype', 'constructor']);
const keyAllowed = key => /^[\x20-\x7e]+$/.test(key) && !unsafe.has(key);
const record = value => value && typeof value === 'object' && !Array.isArray(value)
  && [Object.prototype, null].includes(Object.getPrototypeOf(value));

// Check before serialization or recursive validation. No getters, toJSON,
// coercion, sparse lists or hidden properties may influence signed bytes.
function boundedWire(value) {
  const seen = new Set();
  let nodes = 0, bytes = 0;
  const count = size => (bytes += size) <= managementWireLimits.bytes;
  const walk = (item, depth) => {
    if (depth > managementWireLimits.depth || ++nodes > managementWireLimits.nodes) return false;
    if (item === null || typeof item === 'boolean') return count(item === null ? 4 : item ? 4 : 5);
    if (typeof item === 'number') return Number.isSafeInteger(item) && !Object.is(item, -0) && count(String(item).length);
    if (typeof item === 'string') return item.length <= managementWireLimits.bytes && item.isWellFormed()
      && !item.includes('\0') && count(Buffer.byteLength(JSON.stringify(item)));
    if (!item || typeof item !== 'object' || seen.has(item) || Object.getOwnPropertySymbols(item).length) return false;
    const list = Array.isArray(item);
    if (list ? Object.getPrototypeOf(item) !== Array.prototype : !record(item)) return false;
    const descriptors = Object.getOwnPropertyDescriptors(item);
    const keys = Object.keys(descriptors).filter(key => !list || key !== 'length');
    if (list ? item.length > managementWireLimits.arrayItems || keys.length !== item.length
      : keys.length > managementWireLimits.mapEntries) return false;
    if (!count(2 + Math.max(0, keys.length - 1))) return false;
    seen.add(item);
    try {
      for (let index = 0; index < keys.length; index++) {
        const key = keys[index], descriptor = descriptors[key];
        if (!descriptor.enumerable || !Object.hasOwn(descriptor, 'value')
          || (list ? key !== String(index) : !keyAllowed(key))) return false;
        if (!list && !count(Buffer.byteLength(JSON.stringify(key)) + 1)) return false;
        if (!walk(descriptor.value, depth + 1)) return false;
      }
      return true;
    } finally { seen.delete(item); }
  };
  return walk(value, 0);
}

export function compileManagementSchema(wire) {
  const fail = (condition, message) => requireThat(condition, 'CONTENT_MANAGEMENT_CONTRACT', message);
  fail(record(wire), 'Management schema must be an object.');
  const { $defs: definitions = {}, ...root } = wire;
  fail(record(definitions) && Object.keys(definitions).length <= 256
    && Object.keys(definitions).every(key => /^[A-Za-z0-9_]{1,128}$/.test(key) && !unsafe.has(key)), 'Invalid management definitions.');
  const refs = new Map(), defaults = [], activeSchema = new Set();
  let nodes = 0;
  const refName = value => typeof value === 'string' && /^#\/\$defs\/([A-Za-z0-9_]{1,128})$/.exec(value)?.[1];
  const bounded = predicate => (value, state) => {
    if (++state.steps > managementWireLimits.validationSteps) return false;
    if (++state.depth > 128) { state.depth--; return false; }
    try { return predicate(value, state); } finally { state.depth--; }
  };
  const validate = (predicate, value) => boundedWire(value) && predicate(value, { steps: 0, depth: 0, fields: 0 });
  const compile = (source, depth = 0) => {
    fail(record(source) && depth <= 64 && ++nodes <= 8192 && !activeSchema.has(source), 'Invalid or excessive management schema.');
    activeSchema.add(source);
    try {
      const { description, default: defaultValue, ...s } = source;
      if (Object.hasOwn(source, 'description')) fail(typeof description === 'string' && description.length <= 4096
        && description.isWellFormed() && !description.includes('\0'), 'Invalid management description.');
      const finish = predicate => {
        const result = bounded(predicate);
        if (Object.hasOwn(source, 'default')) defaults.push([result, defaultValue]);
        return result;
      };
      const child = schema => compile(schema, depth + 1);
      const only = allowed => fail(Object.keys(s).every(key => allowed.includes(key)), 'Unsupported management schema constraint.');
      if (Object.hasOwn(s, '$ref')) {
        const name = refName(s.$ref);
        only(['$ref']);
        fail(name && Object.hasOwn(definitions, name), 'Management references must resolve in local $defs.');
        if (!refs.has(name)) {
          const slot = {}; refs.set(name, slot);
          slot.check = child(definitions[name]);
        }
        return finish((value, state) => {
          if (name !== 'acf_field') return refs.get(name).check(value, state);
          // Provider field depth starts at zero: the root plus 12 traversals.
          if (++state.fields > 13) { state.fields--; return false; }
          try { return refs.get(name).check(value, state); } finally { state.fields--; }
        });
      }
      if (Object.hasOwn(s, 'oneOf') || Object.hasOwn(s, 'anyOf')) {
        const kind = Object.hasOwn(s, 'oneOf') ? 'oneOf' : 'anyOf';
        only([kind]);
        fail(Array.isArray(s[kind]) && s[kind].length >= 2 && s[kind].length <= 32, 'Invalid management alternatives.');
        const choices = s[kind].map(child);
        return finish((value, state) => {
          let matches = 0;
          for (const choice of choices) {
            if (choice(value, state)) matches++;
            if (state.steps > managementWireLimits.validationSteps) return false;
            if (kind === 'anyOf' && matches) return true;
            if (matches > 1) return false;
          }
          return matches === 1;
        });
      }
      if (Array.isArray(s.type)) {
        // JSON Schema applies string/list constraints only to those types.
        // Support the provider's scalar-or-checkbox-value wire explicitly;
        // unknown keywords still fail rather than being silently discarded.
        const keysByType = { string: ['minLength', 'maxLength', 'pattern', 'format'],
          integer: ['minimum', 'maximum'], boolean: [], null: [], array: ['items', 'minItems', 'maxItems', 'uniqueItems'] };
        fail(s.type.length >= 2 && s.type.length <= 5 && new Set(s.type).size === s.type.length
          && s.type.every(type => typeof type === 'string' && Object.hasOwn(keysByType, type)), 'Invalid management type alternatives.');
        only(['type', ...s.type.flatMap(type => keysByType[type])]);
        const choices = s.type.map(type => child({ type, ...Object.fromEntries(Object.entries(s)
          .filter(([key]) => keysByType[type].includes(key))) }));
        return finish((value, state) => choices.some(choice => choice(value, state)));
      }
      if (s.type === 'object') {
        only(['type', 'properties', 'required', 'additionalProperties', 'propertyNames', 'minProperties', 'maxProperties']);
        const properties = s.properties ?? {}, required = s.required ?? [];
        fail(record(properties) && Object.keys(properties).every(keyAllowed) && Array.isArray(required)
          && new Set(required).size === required.length && required.every(key => typeof key === 'string' && Object.hasOwn(properties, key)),
        'Invalid management object properties.');
        const fields = new Map(Object.entries(properties).map(([key, schema]) => [key, child(schema)]));
        const typedMap = record(s.additionalProperties);
        fail(s.additionalProperties === false || typedMap, 'Management objects require closed properties or a typed map.');
        if (!typedMap) fail(record(s.properties) && Array.isArray(s.required), 'Closed management objects need explicit properties and required fields.');
        // Maps must explicitly constrain every key and their entry count. Open
        // objects never become an implicit escape hatch for control records.
        if (typedMap) fail(s.propertyNames?.type === 'string' && Number.isSafeInteger(s.propertyNames.maxLength)
          && s.propertyNames.maxLength >= 1 && s.propertyNames.maxLength <= 191 && typeof s.propertyNames.pattern === 'string'
          && Number.isSafeInteger(s.maxProperties), 'Management maps require bounded names and entries.');
        const names = s.propertyNames === undefined ? null : child(s.propertyNames);
        const extra = typedMap ? child(s.additionalProperties) : null;
        const min = s.minProperties ?? 0, max = s.maxProperties ?? managementWireLimits.mapEntries;
        fail(Number.isSafeInteger(min) && min >= 0 && Number.isSafeInteger(max) && max >= min
          && max <= managementWireLimits.mapEntries, 'Invalid management map bounds.');
        return finish((value, state) => {
          if (!record(value)) return false;
          const keys = Object.keys(value);
          return keys.length >= min && keys.length <= max && required.every(key => Object.hasOwn(value, key))
            && keys.every(key => (!names || names(key, state)) && (fields.get(key) ?? extra)?.(value[key], state));
        });
      }
      if (s.type === 'array') {
        only(['type', 'items', 'minItems', 'maxItems', 'uniqueItems']);
        const min = s.minItems ?? 0, max = s.maxItems ?? managementWireLimits.arrayItems;
        fail(Number.isSafeInteger(min) && min >= 0 && Number.isSafeInteger(max) && max >= min
          && max <= managementWireLimits.arrayItems && (s.uniqueItems === undefined || typeof s.uniqueItems === 'boolean'),
        'Invalid management list bounds.');
        const item = child(s.items);
        return finish((value, state) => Array.isArray(value) && value.length >= min && value.length <= max
          && value.every(entry => item(entry, state))
          && (!s.uniqueItems || new Set(value.map(entry => delegationCanonical(entry))).size === value.length));
      }
      // The existing compiler remains the authority for primitive validation.
      fail(['string', 'integer', 'boolean', 'null'].includes(s.type), 'Unsupported management value type.');
      only(['type', 'enum', ...(s.type === 'string' ? ['minLength', 'maxLength', 'pattern', 'format']
        : s.type === 'integer' ? ['minimum', 'maximum'] : [])]);
      for (const key of ['minLength', 'maxLength', 'minimum', 'maximum']) if (s[key] !== undefined)
        fail(Number.isSafeInteger(s[key]) && (!key.endsWith('Length') || s[key] >= 0), 'Invalid management primitive bound.');
      fail(s.minLength === undefined || s.maxLength === undefined || s.minLength <= s.maxLength, 'Invalid management string bounds.');
      fail(s.minimum === undefined || s.maximum === undefined || s.minimum <= s.maximum, 'Invalid management integer bounds.');
      if (s.pattern !== undefined) fail(typeof s.pattern === 'string' && s.pattern.length <= 1024, 'Invalid management pattern.');
      if (s.enum !== undefined) fail(Array.isArray(s.enum) && s.enum.length > 0 && s.enum.length <= 1000
        && s.enum.every(value => value === null || ['string', 'number', 'boolean'].includes(typeof value)), 'Invalid management enum.');
      const primitive = compileContentSchema(s);
      return finish(value => primitive.safeParse(value).success);
    } finally { activeSchema.delete(source); }
  };
  const predicate = compile(root);
  // A reference loop must consume an object property or array item. Ref/union
  // loops at the same value cannot be made safe by a response depth limit.
  const checked = new Set();
  const unguarded = (schema, trail = new Set()) => {
    const name = refName(schema.$ref);
    if (name && !checked.has(name)) {
      fail(!trail.has(name), 'Management reference recursion must consume a value.');
      unguarded(definitions[name], new Set([...trail, name]));
      checked.add(name);
    }
    for (const choice of schema.oneOf ?? schema.anyOf ?? []) unguarded(choice, trail);
  };
  unguarded(root);
  for (const name of refs.keys()) if (!checked.has(name)) {
    unguarded(definitions[name], new Set([name])); checked.add(name);
  }
  for (const [check, value] of defaults) fail(validate(check, value), 'Invalid management default.');
  return z.unknown().transform((value, context) => {
    if (validate(predicate, value)) return JSON.parse(delegationCanonical(value));
    context.addIssue({ code: z.ZodIssueCode.custom, message: 'Invalid or excessive management content.' });
    return z.NEVER;
  });
}
