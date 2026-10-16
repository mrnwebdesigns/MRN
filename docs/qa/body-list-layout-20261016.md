# Native Body Text list layout — source qualification

Parent candidate 1.5.22 extends the existing Body Text layout with optional list
columns, mobile columns, reading flow, markers, alignment and column gap. The
controls apply to top-level lists in its editable WYSIWYG. Unset controls retain
the prior renderer output. Root and nested instances have unique ACF keys and
conditional fields bound to their own opt-in selector. No new layout or asset is
introduced; row margin/padding continues to use the Stack spacing fields.

Live CSS and the retained child template show that consultation lists flow down
columns, correcting the earlier audit's row-major assumption. The site mapper
authors two columns on both desktop/mobile, a 30px column gap, hidden markers and
end alignment switching to start at 767px. Across-row flow is separately available
using Grid. The list retains its semantic markup; empty CSS markers preserve
WebKit's list accessibility semantics. Nested list markup remains intact.

Qualification used a private CLI-only source bootstrap on the exact guarded
migration clone. It did not replace managed runtime files or alter authored
records. The installed parent remains 1.5.20 at this checkpoint.

- Native runtime: **21 assertions**, including root/nested ACF write/readback,
  conditional key references, actual nested rendering, malformed settings,
  unchanged default output and fixture removal.
- Site converter 20261016.7: **22 groups / 34 fixtures** against candidate source;
  **22 groups / 33 fixtures** against installed 1.5.20. The baseline refuses
  unavailable native fields. The candidate retains repeated/inline list text,
  query links, down-column order, both CTA styles, heading reveal, native zero
  spacing, verified repeat/no-op and exact rollback. Disabled legacy link
  interaction remains a review rather than silently losing a URL.
- Actual browser: 1280px down-column and row-major positions; 390px preserves
  authored columns and changes alignment without horizontal overflow. Native
  list roles remain in the WebKit accessibility tree. Keyboard Tab reaches a
  named link with a visible 2px outline. No motion or JavaScript is added.
- MRN QA Engine targeted WCAG 2 A/AA and 2.1 AA scan passes with no ignored rules
  or regions. Local component CWV: LCP **104ms**, CLS **0**, INP **8ms**, all good.
  These are source-component results, not full-site signoff.
- API surface review adds no REST/AJAX/admin-post handlers or permissions.
  Existing clone `/wp-json/` is healthy: 390 routes, 13 namespaces. The source
  renderer only emits whitelisted classes, bounded columns and a validated CSS
  length through escaped attributes. PHP lint and diff checks pass.
- All **63 authored source hashes** match the retained installed snapshot. Every
  owned fixture and converter journal was removed. No authored page converted.

Content-free evidence is in the adjacent directory. Private proof images and
source snapshot remain outside Git under the migration clone. User-facing source
preview: https://mrnwebdesigns-stack-migration.localhost/stack-body-list-preview-20261016/

Classic Editor Save Draft/reload and real-page visual acceptance are still open
for this new composition. Source acceptance, automatic Fleet publication, clone
adoption and content migration are separate gates. Full remote plugin parity and
site-owner deploy checks are intentionally outside this local source task; they
belong to promotion/rollout. No production deployment is included.
