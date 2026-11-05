# Optional native Hero heading entrance — local checkpoint

An opt-in Basic Hero select, `hero_heading_entrance=gentle-rise`, emits a bounded
class for a 700ms on-load fade/0.35em rise. The existing Hero stylesheet owns the
keyframes and reduced-motion override. Blank, unknown and malformed values retain
prior output. Existing enabled Row Effects suppress the preset to avoid competing
transforms. Direct Hero heading elements alone receive it; no JavaScript, new asset,
Motion dependency, global setting, spacing override or site-specific source is added.

Native runtime integration passes 181 assertions, including actual ACF persistence,
default safety, enum refusal, competing effects, component-need detection and cleanup.
All 56 standalone PHP suite runs (8.2.35/8.5.6) pass; configured full-theme PHPStan
has zero errors. Site source/refusal checks pass 20 assertions. Motion comparison
passes 537 assertions / 88 samples / 11 widths across source/native/no-script/prior
native and normal/reduced motion. Fixed-time keyframe samples match the source.
Complete saved-page comparison passes 687 assertions / 44 samples / 11 widths.
Normal Classic Editor Save preserves 1,417 configured entries and identical markup.

Fresh actual runtime QA passes REST, public browser and WCAG/keyboard/dynamic checks
with the owner's contrast-only exclusion. It **fails timing and Web Vitals**: Home
TTFB 11.293s / LCP 4,212ms; native TTFB 8.686s / LCP 6,396ms / CLS 0.133612. A later
diagnostic identifies the Hero image as LCP with 2,688.6ms response start and 3,516ms
LCP, not a resolved performance result. Static preview and repeated dynamic timing
separate response delays from visual proof; the WordPress root cause remains open.
No QA-engine edit, threshold reduction or whole-Stack/release acceptance is claimed.

Only hero-frame.php and css/layouts/hero.css are adopted on the exact local clone,
with original bytes and before/after hashes retained. Fresh private SQL is verified
against matching host/VM checksum, completion footer and all 70 tables. All 79
authored states, 65 routes, four Site Styles options and 63 source records remain
unchanged; only Thank You's candidate changes. Owned fixtures are removed. Site
converter is 20261105.31; authored conversion remains 15/63 and performance remains
held before page admission. Evidence: site migration branch,
docs/stack-migration-2026-11-05/LOCAL_CONFIRMATION_MOTION.md. No merge, release, Fleet
promotion, case-study-model change or remote deployment. Exact source gates are
recorded separately; remote parity/deploy checks stay deferred by local-only scope.
