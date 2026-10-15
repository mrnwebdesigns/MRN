import { z } from 'zod';
import { requireThat } from './contracts.mjs';
import { delegationCanonical } from './delegation.mjs';

// Only the checked-in content contract vocabulary is supported. References
// resolve within the explicitly supplied definitions; nothing loads remotely.
export function compileContentSchema(schema, definitions = {}) {
  let nodes = 0;
  const fail = (condition, message) => requireThat(condition, 'CONTENT_CONTRACT', message);
  const compile = (s, depth = 0, references = new Set()) => {
    fail(s && typeof s === 'object' && !Array.isArray(s) && depth <= 64 && ++nodes <= 8192, 'Invalid or excessive content schema.');
    if (Object.hasOwn(s, '$ref')) {
      const match = typeof s.$ref === 'string' && /^#\/definitions\/([A-Za-z0-9_]+)$/.exec(s.$ref);
      fail(Object.keys(s).length === 1 && match && Object.hasOwn(definitions, match[1]) && !references.has(match[1]),
        'Content references must be local, defined and acyclic.');
      return compile(definitions[match[1]], depth + 1, new Set([...references, match[1]]));
    }
    const keys = ['type', 'properties', 'required', 'additionalProperties', 'anyOf', 'oneOf', 'enum', 'minimum', 'maximum', 'minLength', 'maxLength', 'pattern', 'items', 'minItems', 'maxItems', 'uniqueItems'];
    fail(Object.keys(s).every(k => keys.includes(k)), 'Unsupported content wire schema.');
    const child = value => compile(value, depth + 1, references);
    if (Object.hasOwn(s, 'oneOf')) {
      fail(Object.keys(s).length === 1 && Array.isArray(s.oneOf) && s.oneOf.length >= 2 && s.oneOf.length <= 32,
        'Invalid or excessive exclusive content alternatives.');
      const alternatives = s.oneOf.map(child);
      return z.unknown().transform((value, context) => {
        let matches = 0, parsed;
        for (const alternative of alternatives) {
          const result = alternative.safeParse(value);
          if (result.success) { matches++; parsed = result.data; }
          if (matches > 1) break;
        }
        if (matches === 1) return parsed;
        context.addIssue({ code: z.ZodIssueCode.custom, message: 'Content must match exactly one alternative.' });
        return z.NEVER;
      });
    }
    if (s.anyOf) {
      fail(Object.keys(s).length === 1 && Array.isArray(s.anyOf) && s.anyOf.length >= 2, 'Invalid content schema alternatives.');
      return z.union(s.anyOf.map(child));
    }
    if (Array.isArray(s.type)) return z.union(s.type.map(type => child({ ...s, type })));
    let out;
    if (s.type === 'object') {
      fail(s.additionalProperties === false && Array.isArray(s.required) && s.properties && typeof s.properties === 'object' && !Array.isArray(s.properties), 'Content objects must be closed.');
      fail(new Set(s.required).size === s.required.length && s.required.every(k => Object.hasOwn(s.properties, k))
        && Object.keys(s.properties).every(k => !['__proto__', 'prototype', 'constructor'].includes(k)), 'Invalid content object properties.');
      out = z.object(Object.fromEntries(Object.entries(s.properties).map(([k, v]) => [k, s.required.includes(k) ? child(v) : child(v).optional()]))).strict();
    } else if (s.type === 'array') {
      out = z.array(child(s.items));
      if (s.minItems !== undefined) out = out.min(s.minItems);
      if (s.maxItems !== undefined) out = out.max(s.maxItems);
      if (s.uniqueItems !== undefined) {
        fail(typeof s.uniqueItems === 'boolean', 'Content uniqueness must be explicit.');
        if (s.uniqueItems) out = out.refine(values => new Set(values.map(value => delegationCanonical(value))).size === values.length, 'Content items must be unique.');
      }
    } else if (s.type === 'string') {
      out = z.string();
      if (s.minLength !== undefined) out = out.min(s.minLength);
      if (s.maxLength !== undefined) out = out.max(s.maxLength);
      if (s.pattern) out = out.regex(new RegExp(s.pattern));
      out = out.refine(value => value.isWellFormed() && !value.includes('\0'), 'Invalid content Unicode.');
    } else if (s.type === 'integer') {
      out = z.number().int().safe();
      if (s.minimum !== undefined) out = out.min(s.minimum);
      if (s.maximum !== undefined) out = out.max(s.maximum);
    } else if (s.type === 'boolean') out = z.boolean();
    else if (s.type === 'null') out = z.null();
    else fail(false, 'Unsupported content contract type.');
    return s.enum ? out.refine(value => s.enum.includes(value), 'Unsupported content enum.') : out;
  };
  return compile(schema);
}
