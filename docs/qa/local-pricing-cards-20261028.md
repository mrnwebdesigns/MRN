# Local native pricing Cards — October 28

Unpublished continuation after **70d81530**, committed alongside site checkpoint
**029e0d2** after both exact staged MRN QA gates. Owner authorization permits
shared Stack work in this migration chat. Pricing qualification remains local and unpublished.
No version, lock, catalog, package, publication or managed installation changes.

Optional Cards **Pricing Plans** adds editable price/badge text, plain plan
description, highlight selection, a structured feature repeater and an independent
ACF action link. Existing Card Name is the plan heading. Thirty features fit;
editor, REST schema and write guards agree, and the existing single Card Row
contract stays unchanged. Standard Cards remain the default and ignore dormant
pricing data. Factory keys are isolated, and inactive Card Row/image/link fields
are hidden for the pricing selection.

Presentation controls reuse bounded dimensions, Site Text tokens, registered
Site Colors, native frame/width/surface and row-spacing contracts. No site copy,
price or plan is hardcoded. The pricing renderer keeps semantic articles,
headings, feature lists and visible actions, with decorative SVGs, secured new-tab
links and visible keyboard focus. It adds no JavaScript or media payload.

The four source rows are all disabled. Owned-draft comparisons qualify 12 plans,
84 features and four highlights with exact text, independent actions and desktop
geometry, while preserving disabled visibility. Mobile native row presets repair
inherited narrow side padding. No authored conversion or installed adoption occurs.

23 native, 135 saved-source and 28 existing repeater-policy assertions pass,
alongside 32 converter groups / 49 removed fixtures and a separate exact 63-record
snapshot comparison. The site retains browser evidence, previews, bounded QA
reports and source/artifact manifests under its October 28 pricing evidence.
Complete-page/editor acceptance, installed adoption and release remain separate.
Current QA results follow; contrast alone is excluded, and remote parity/deploy
readiness remain deferred locally.

Both standard local MRN QA runs complete. PHP lint, security sniffs, WPCS,
compatibility, secrets/debug scans, theme/security suites, public browser smoke
and REST HTTP 200 pass. Stack Semgrep and Composer audit pass. PHPStan remains
advisory with 41 Stack / 38 site errors; this is not clean static analysis.
API surface checking is inapplicable because no routes/handlers are added.
Remote parity, license/rollout and deploy readiness are deliberately excluded.

Both original reports remain **Fail/Blocked**: their standalone comparison lacked
a page H1. The comparison compiler now supplies a visible H1 and, when the source
section has no H2, a preview-only H2. The authored card headings and source copy
are unchanged. The bounded canonical accessibility rerun passes both homepage
and corrected pricing export (2 tests), including axe, semantic, keyboard and
dynamic checks, with contrast alone excluded. Original reports are retained
without editing their failed result.

Initial standard runs pass TTFB and CWV (homepage LCP 976/1412ms; pricing LCP
144/168ms, CLS 0.00097/0.00123, INP 40/8ms). The final canonical CWV check after
the wrapper fix keeps pricing good (LCP **944ms**, CLS **0.002418**, INP **24ms**)
but finds homepage LCP **10.972s**; homepage CLS 0.004792 / INP 40ms remain good.
That unchanged homepage variability is already documented in earlier batches.
The final CWV suite is therefore **1 passed / 1 failed**; no rerun was used to
replace that result and no performance thresholds changed. Full-site performance
sign-off remains open despite good bounded pricing measurements.
