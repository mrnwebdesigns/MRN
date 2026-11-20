# Native contained-frame breakpoints — local migration checkpoint

Optional Cards and Body Text frames now support independent **767px row margins**,
**900px row padding**, and inner-gutter overrides at **900px / 480px**. All outer
margin/padding dimensions still come from native Site Styles presets. Cards also
adds an optional **one-column-at-900px** view while retaining the authored desktop
column count. Default/other views are unchanged; no site wording or IDs enter Stack.

The shared `frame-spacing.php` factory/contract keeps field keys scoped to each
existing layout seed. Only active contained Card/Body frames resolve these settings.
Breakpoint choices are allow-listed; gutter dimensions use the existing bounded
length/clamp validator. Unknown values cannot emit styles. CSS consumes validated
properties and selected preset values once; it does not embed migration dimensions.
The opt-in padding view moves the surface padding to its owning outer row and keeps
the selected Cards background across it. New helper dependencies must accompany
Card/Body source adoption; this is not a release/package/lock checkpoint.

The site converter maps Thank You's two card rows, text row and following article
boundary through these native fields, retaining exact source wording/HTML/links.
It keeps the three/four-card counts, removes duplicated sibling margins, and uses
existing 64/56px and 120/0px presets without adding Site Styles entries. The site
child changes only two legacy compatibility selector exclusions, letting the newly
selected native frame own its preset spacing; it adds no child spacing declarations.

Qualification: **499 browser assertions / 32 samples at 16 widths**, including both
sides of 900/782/767/480px, pass for outer bounds/preset padding/gutters, adjacent
margins, card counts, DOM wording and links. The complete candidate has no horizontal
scroll overflow. At 641–900px native cards deliberately fill the single-column track,
correcting the legacy leftover one-third item width. This is not zero difference for
that old tablet bug, nor complete internal typography/action/Hero acceptance.

The existing native ACF runtime suite passes **98 assertions** (registered fields,
unsafe-value refusal, exact storage and opt-out behavior); its owned draft is removed.
The site adds **30 installed-schema mapping/refusal checks**. All **56 PHP suite
runs** on 8.2.35/8.5.6 and configured complete-theme PHPStan pass (zero errors).
The full analysis required its normal 2GB tooling memory after a 128MB worker limit;
no diagnostic baseline or runtime threshold was weakened.

Owned body fixture 17671 survives a normal Classic Editor Save action: **888 configured
metadata entries**, 56 exact known RBL/native spacing aliases and 1,342 additional
editor defaults. The original source intro's CRLF is retained in native data, avoiding
a synthetic LF-to-CRLF mismatch on textarea submission. Native semantic readback and
byte-identical rendered body hash pass. No generic whitespace or field-reference
comparison was relaxed. The automation could not capture the final notice/screenshot;
that UI proof and complete authored-page/editor acceptance remain open.

Installed-runtime MRN QA passes security/source, REST, browser smoke and WCAG A/AA
with contrast alone excluded. The partial-symbol PHPStan scan retains 14 advisories;
configured full-theme analysis has zero errors. **Performance remains failed:** the
owned body fixture records TTFB 3.618s, LCP 12664ms (poor), CLS 0.1283 (needs
improvement), INP 48ms (good). Home CLS 0.1261 also needs improvement. This is a
source checkpoint with no complete-page or runtime-performance acceptance.

Actual QA and final cleanup/preservation receipts are recorded in
the site branch under `docs/stack-migration-2026-11-04/LOCAL_CONFIRMATION_FRAMES.md`.
Authored conversion remains 15/63. No merge, promotion, release/version/lock/tag,
Fleet publication, remote site/database write or global runtime configuration change.

Final cleanup removes both owned trial/final editor fixtures and confirms all 79
authored states, 65 routes, four Site Styles options and 63 source records unchanged.
Only Thank You's prepared candidate changes; the other 62 stay exact. The site
apply allow-list is unchanged; no additional authored page is converted.
