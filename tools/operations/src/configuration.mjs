import { load } from 'cheerio';
import { z } from 'zod';
import { requireThat } from './contracts.mjs';

const token = /^--site-color-[a-z0-9_-]{1,96}$/;
const slug = /^[a-z0-9_-]{1,96}$/;
const hex = /^#(?:[A-F0-9]{3}|[A-F0-9]{6})$/i;
const palette = z.strictObject({ site_id: z.number().int().positive().safe(), site_url: z.string(), schema_version: z.literal(1),
  component_version: z.literal('0.1.39'), source: z.literal('site_styles_normalized_saved_palette'),
  colors: z.array(z.strictObject({ slug: z.string().regex(slug), hex: z.string().regex(hex), css_property: z.string().regex(token) })).max(128) });

// Only the declaration emitted by Site Styles is observed, never the cascade.
export function emittedPaletteDeclaration(html, property) {
  requireThat(token.test(property), 'PALETTE_SCHEMA', 'Use one supported CSS token.');
  const styles = load(html)('style#mrn-site-colors-vars');
  if (styles.length !== 1) return null;
  const body = /^\s*:root\s*\{([^{}]*)\}\s*$/u.exec(styles.text())?.[1];
  if (body === undefined) return null;
  const matches = body.split(';').map(declaration => declaration.split(':').map(field => field.trim()))
    .filter(fields => fields.length === 2 && fields[0] === property);
  return matches.length === 1 && hex.test(matches[0][1]) ? matches[0][1].toUpperCase() : null;
}

export async function paletteEvidence(data, site, observe) {
  const parsed = palette.safeParse(data);
  requireThat(parsed.success, 'PALETTE_SCHEMA', 'The provider did not return the bounded Site Styles contract.');
  const value = parsed.data;
  requireThat(value.site_id === site.id && value.site_url === site.url, 'TARGET_MISMATCH', 'The palette belongs to another site.');
  requireThat(value.colors.every(row => row.css_property === `--site-color-${row.slug}`)
    && new Set(value.colors.map(row => row.slug)).size === value.colors.length, 'PALETTE_SCHEMA', 'The palette token identities are ambiguous.');
  const sample = value.colors[0];
  const observed = sample ? await observe?.(sample.css_property) : null;
  const declaredHex = observed?.status === 200 && typeof observed.paletteDeclaration === 'string' && hex.test(observed.paletteDeclaration)
    ? observed.paletteDeclaration.toUpperCase() : null;
  return { componentVersion: value.component_version, source: value.source,
    colors: value.colors.map(row => ({ slug: row.slug, storedHex: row.hex.toUpperCase(), cssProperty: row.css_property })),
    frontend: sample ? { cssProperty: sample.css_property, declaredHex,
      status: declaredHex === null ? 'unavailable' : declaredHex === sample.hex.toUpperCase() ? 'matches_saved' : 'mismatch',
      computedHex: null, limitation: 'Only the declared Site Styles value is observed; additional CSS can override it.' } : null };
}
