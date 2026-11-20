# Native Articles runtime follow-up — local checkpoint

Two narrow opt-in article fixes were adopted on the guarded MRN migration clone.
No release/version/lock, CSS, ACF schema, endpoint or shared default changes.

- Explicit Articles with featured images now call core `update_post_thumbnail_cache()`
  after the post query. This bulk-primes image records instead of repeated per-card
  lookups. Other list profiles and image-disabled rows do not call it.
- Per-instance JavaScript captures auto-sized image widths before hiding cards,
  performs all layout reads before attribute writes, and restores original `sizes`
  when cards are revealed. Source/srcset/alt and script-disabled markup are intact.
  Hidden images no longer request a larger fallback source when their measured width
  disappears. There are no timers, endpoints or extra script payloads.

Paired cold request-local core-cache SQL tests (both orders) preserve byte-identical
markup: Insights **689/688 -> 14** queries for 416 cards; Thank You **17 -> 13** for
three. Persistent object caches are refused by the regression harness. This proves
query reduction, not full-page HTTP performance acceptance.

The site adds 25 network/decode/responsive-image assertions, with 45 requests and
zero failures. Existing 39 browser interactions and 52 responsive comparisons remain
passing. All 56 PHP suite runs (28 on PHP 8.2.35/8.5.6), targeted PHP standards and
configured complete-theme PHPStan pass. The engine's partial-symbol analysis retains
38 advisories; complete configured analysis has zero errors.

Installed WordPress MRN QA passes browser smoke, REST health and WCAG A/AA with
contrast alone excluded. **Runtime speed remains failed/blocked:** Home/owned-feed
TTFB 4.177/2.602s and LCP 10660/4016ms. No settings or services were changed to hide
this finding. Owned fixture 17666 is removed; fresh editor Save was not verified
because navigation timed out before any save. Authored pages remain 15/63 converted.

Complete-page source/native reviews retain all 3/5 rows and heading wording/form
controls at 1440/768/390px, but generic Hero/Card/Text framing, mobile overflow and
adjacent-row margin sums are still held. No child CSS compensation was added.
The site branch records exact evidence, backup and runtime hashes in
`docs/stack-migration-2026-11-04/LOCAL_ARTICLE_RUNTIME.md` and `article-runtime/`.
This checkpoint does not clear full-page/editor/performance or release acceptance.
