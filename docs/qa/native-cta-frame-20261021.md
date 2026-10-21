# Optional native CTA frame — parent 1.6.9

The centered CTA migration needs editable inner gutters, content width/gaps,
paragraph/action spacing and alignment. The previous native grid and child
spacing overrides cannot reproduce that frame through row presets alone.

The opt-in Stacked Content frame stores these values in native ACF fields and
keeps row spacing/motion on the outer wrapper. Existing unselected CTAs retain
both their previous markup and presentation. New frame fields have isolated
nested keys, bounded dimensions and fixed choice allowlists. Complete content,
media, heading semantics, links and existing Site Colors remain in native fields.
No new endpoint, JavaScript or asset request is introduced.

## Source acceptance

- 39 new native assertions: schema, nested keys, repeated normalization,
  ACF persistence, single spacing owner, complete links, unchanged defaults and
  malformed-input rejection. Its owned draft is removed.
- 260 other native assertions and 24 standalone PHP regressions pass.
- Staged-snapshot MRN QA commit gate passes.
- Complete SEO page source preview passes MRN QA WCAG 2.1 A/AA and keyboard
  smoke without ignored regions/rules. The page retains legacy rows except its
  one CTA, so this does not qualify the remaining layouts for conversion.
- Local preview CWV: SEO route LCP 120ms / CLS 0 / INP 24ms; the identical root
  preview measured LCP 108ms / CLS 0.1324 / INP 16ms. The latter is
  needs-improvement, below the engine's poor threshold; complete-page performance
  variance remains open and is not a production signoff.
- After correcting the disposable legacy fixture to copy its externally injected
  spacing selectors and set the current post, native CTA row height, heading
  geometry and action size/position match at 2560, 1280, 1199, 960 and 820px.
  Earlier 76px comparisons used an incomplete fixture and are superseded.
- The site-owned native CTA Side Padding preset preserves desktop 120px and
  explicitly uses 0px on phones. At measured client width 375px, body width
  improves from 121px to 313px; no document overflow. That preset is separate
  from this shared parent change and requires the site's reversible preparation.
- All 63 authored source hashes remain unchanged. CTA presentation holds remain
  until installed editor and individual page acceptance are complete.

Evidence: [native checks](cta-frame-20261021/cta-frame.json),
[existing native checks](cta-frame-20261021/native-regressions.json),
[PHP regressions](cta-frame-20261021/php-regressions.json),
[responsive geometry](cta-frame-20261021/responsive.json),
[accessibility](cta-frame-20261021/accessibility.txt),
[CWV](cta-frame-20261021/web-vitals.ndjson).

This is source acceptance only. Publication, migration-clone adoption and
installed/editor verification are separate steps. Production remains unchanged.
