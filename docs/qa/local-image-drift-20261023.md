# Optional Image Scroll Drift — October 23

Local continuation after `63e6ff0`; unpublished. Adds an opt-in Scroll Drift
choice and bounded speed/maximum fields to native Image, including isolated
nested fields. The existing presentation scheduler and conditional asset
discovery handle the effect without a new library. Images follow their nearest
Split center (or their own Image row), capped in both directions. Fine hover
pointer, 768px minimum width and reduced-motion checks update live; section size
changes refresh geometry. Individual translation leaves other transforms intact.

Image-only native frames with standard/drift presentation reserve the requested
attachment ratio, fixing WordPress auto-sizes' 1,500px mobile reservation. No
section spacing is added or altered. Ordinary Image markup and zoom/gallery
crop overlays retain their previous behavior. Invalid controls cannot emit
drift markup. This is reusable Stack behavior, not site-specific content.

31 native persistence/safety/compatibility assertions, the JS drift regression
(geometry/caps/resizing/live media changes/defaults), exclusive-FAQ regression
and conditional asset regression pass. The site suite passes 104 checks across
ten saved service rows / 63 question-answer pairs; converter integration passes
32 groups / 49 removed fixtures. All 63 authored source/native states remain
unchanged. Desktop browser calculations match for all five image rows; tablet
and mobile retain expected widths/ratios and no horizontal overflow.

Standard MRN QA covers final changed PHP plus local API/browser/accessibility/
performance. Configured static/security/compatibility/Semgrep checks pass;
PHPStan has advisory runtime-symbol errors. Public smoke and REST HTTP 200 pass.
Full-page qualification remains blocked by five baseline process-number contrast
findings and homepage performance. The native Website sample has good measured
CWV (LCP about 1.2s, CLS 0.0021, INP 24ms); that does not clear the whole site.
Site-specific Landing restoration also reports reCAPTCHA contrast and CLS debt.
Exact receipts/hashes are in the site task's
`docs/stack-migration-2026-10-23/image-drift/`.

No managed parent install, version bump, Fleet publication, remote write, release
manifest or RBL source change. The five former image-drift field gaps are now
encoded/qualified and remain held for full-page/editor acceptance, not marked
authored-converted or release-ready.
