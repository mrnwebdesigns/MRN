# Content-list pagination URLs

The parent renderer and SEO integration share a server-side pagination contract.
The existing `mrn_list_page_<post>_<row>` parameter remains stable. No client
content, saved SEO value, keyword, native archive policy or redirect is changed.

## Request policy

- Only a paginated list actually belonging to the current published public
  singular page owns a cursor. Foreign page/row parameters have no effect.
- Page one (including zero), malformed values, negative numbers, fractions,
  exponent notation and array values use page one and the clean hub canonical.
  Decimal leading zeros normalize to an integer. Positive decimal overflow is
  out of range, without executing an oversized SQL offset.
- Each populated page after page one has a self-canonical containing only its
  effective owned cursors, sorted by key. Fragments, tracking and unrelated
  parameters are excluded. Existing navigation/filter query arguments remain
  available to visitors; they do not become canonical identity.
- A positive cursor beyond its list's final populated page returns 404. An empty
  list still has its ordinary page-one hub; requesting page two returns 404.
- Independent list rows retain independent cursors. Legacy placements sharing
  the same post/row key continue to share that cursor; it must be in range for
  every associated list. No parameter or anchor migration is introduced.
- Explicitly disabled sources do not acquire paginated public destinations;
  embedded/programmatic data and stored settings remain available. Enabled
  sources, including publicly available menu-hidden types, retain their queries.
- Native archive pagination is independent. No mapping, redirect or noindex
  policy is inferred from a similarly named list or matching page number.

## SEO and rendering

Use the existing renderer's ordering, taxonomy filters, manual selection,
page size and offset rules. Validate against that query's total, and reuse
request-local query results rather than running a separate SEO approximation.
Normal pages without requested list cursors do not need an additional list query.

Use WordPress and supported provider filters, preserving a single canonical
emitter and a matching OG URL. An explicit different custom canonical remains
an editorial override. Structured-data page identities must use the same URL
contract; site/organization identities and breadcrumb ancestor URLs remain
stable. Do not manufacture schema for a paginated list.

## Qualification and adoption

Exercise page one, page two, final populated/empty/out-of-range pages, malformed
and overflowing values, campaign parameters, two independent lists, reused
placements, disabled/enabled sources and operation without SEOPress. Compare
server-rendered item IDs and canonical/OG/schema URLs without JavaScript; retain
one title, description, H1 and canonical and unchanged shared chrome/content.

This is shared-source work. Source review/acceptance, clean merged-source Fleet
publication and installed site adoption are separate gates. Trilliant's child
critical CSS recipe pins the parent stylesheet bytes, including its version
header: requalify the paired child recipe before any approved parent adoption.
Use a fresh native backup and exclusive writer window for later Live adoption.

## Regression evidence, October 19, 2026

The disposable native WordPress/ACF/SEOPress fixture reproduced the original
page-two hub canonical before overlaying the candidate parent. All 28 cases
then passed, including enabled Team/Job, a disabled source, disabled single
routes, re-enabled Team, independent and nested reusable lists, native archive
preservation, an explicit editorial canonical and absence of SEOPress. Page-two
item IDs and order matched the baseline exactly. Hashes of all posts, postmeta,
terms, term taxonomies, relationships and the three SEO option groups matched.

MRN QA standard mode passed security, compatibility, browser, REST index,
axe/keyboard accessibility, timing and Core Web Vitals on the isolated homepage
and paginated URL. Its changed-file PHPStan scope lacked the existing hero
helper definition; a supplemental analysis including that defining module
passed without errors. No REST/AJAX/admin-post handler was introduced. Remote
inventory, Fleet promotion, deployment readiness and release signoff belong to
their later gates and were outside this source fixture.

Run the native matrix with `python3 stack/scripts/tests/list-pagination-runtime.py`
and the required `--config`, `--bootstrap`, `--evidence` paths; add `--qa` to run
MRN QA before the private fixture is destroyed. Evidence for this qualification
is retained outside the repository in the October 19 pagination/analysis release
artifact directory. This source result does not establish installed adoption.

For rollout, qualify and publish clean merged source, regenerate the paired
Trilliant child critical-CSS recipe, then obtain the separately scoped site
deployment approval and fresh native backup. Install through the normal signed
Stack workflow; independently verify canonical, OG, schema, item ordering,
invalid-page status, existing archives, forms and shared chrome on Live. Use the
normal coordinated rollback with a fresh backup if needed. No database
migration is required: reverting the parent restores the previous URL policy
and retains every content and SEO record.
