# SEOPress analysis of Stack builder content

The Stack renders saved ACF content outside `post_content`. SEOPress 10.3's
private word-count method reads only `post_content`, even when its DOM analysis
has correctly obtained the public headings. Copying ACF into that field would
change content ownership and is not the integration contract.

## Checks and extraction

The supported `seopress_content_analysis_builder_driven` filter identifies saved
Stack builder, Hero, After Content, Job sections and Team biography data. For
builder content below SEOPress's native readable-content threshold, its own
four content-quality checks explicitly say they were skipped and require
published-page review. They do not report a fabricated rendered word count.
SEOPress represents this skipped state with its native `good` impact value and
an explanatory description; that value is not evidence that the content passed
a quality review. Its normal policy for hybrid posts with a sufficiently long
native body remains unchanged: those checks still measure that native body.

The `seopress_get_data_dom_filter_content` hook provides descriptors before
extraction. Native body extractors are delegated to a separate document that
contains public `main` content and omits navigation, footer, sidebar, admin
chrome, scripts/styles and explicitly hidden variants. The original document
remains available to the native head and JSON-LD extractors. Unknown/replaced
descriptors, unanchored inputs, headless fragments without a main container and
taxonomy analysis retain their provider behavior. No vendor
files, templates, public copy, keywords or stored SEO settings are rewritten.

## Incoming links and audit consistency

Retain native incoming sources and add public saved-ACF references after
verifying actual rendered `a[href]` elements with the existing Stack renderers.
The candidate lookup searches saved URL paths and follows explicit reusable
block placements to their hosts, with cycle/depth bounds. Unused fields and
plain mentions cannot create links. Self, draft, password-protected, disabled,
Content Only and disabled Team profile sources are excluded. Shared reusable
data can contribute through an enabled public host.

This extends the provider's native lookup; it is not a complete crawler of
arbitrary custom templates or links generated solely by a dynamic query.
Custom public templates can supply their rendered ACF body through
`mrn_base_stack_seopress_builder_markup`. No site-specific raw-field scanning
or persistent link index is introduced.

The fixture separately reproduced Relevanssi blocking the literal native URL
lookup in audit context with `WHERE 1=2`, while the REST route found the native
source. The adapter uses the provider's supported selective filters only for
that native lookup and removes them in `finally`. Ordinary search behavior is
preserved. See the [Relevanssi selective-filter contract](https://www.relevanssi.com/user-manual/filter-hooks/relevanssi_prevent_default_request/).

Both direct analysis and the native audit/rescan pipeline use the same adapters.
SEOPress's own issue reconciliation clears resolved content-depth/incoming
warnings and retains real remaining issues; the Stack does not erase the issue
table, ignore warnings, lower thresholds, invent keywords or auto-run an audit.
Previously saved results remain dated until an authorized analysis refresh.

## Qualification and deployment

Run `python3 stack/scripts/tests/seopress-analysis-runtime.py` with `--config`,
`--bootstrap`, `--evidence` and optional `--qa`. Its private, disposable
WordPress/ACF/SEOPress/Relevanssi runtime exercises native, ACF, mixed, enabled
and disabled Team/Job, explicit and relative incoming links, reusable hosts,
self/draft/unused sources, direct/audit agreement, stale issue cleanup, real
image warnings, unset keywords and absence of SEOPress. It compares native
content/meta/term/SEO option hashes and public main markup, and checks that
ordinary search and its registered filters are restored. The audit test uses
the actual native rescan handler with a fixture-only HTTP response containing
the captured public HTML; it does not crawl or mutate a client site.

Source review, accepted merged-source Fleet qualification/publication and site
adoption are distinct gates. This branch follows the pagination source change.
Trilliant adoption requires a paired child critical-CSS recipe qualified for
the exact parent stylesheet, separate deployment authorization, an exclusive
writer window, a fresh verified native backup, and installed/public proof.
Analysis refreshes change the native analysis/issue tables and need their own
authorized scope. No Live analysis, backup, cache action or deployment is part
of this source fixture.

Rollback uses the normal signed Stack workflow and a fresh backup. No content
migration or data deletion is required. Reverting the adapter restores vendor
analysis behavior; a separately authorized analysis rerun can regenerate its
derived results. Preserve later site content, settings, redirects and forms.

## October 19 qualification result

The native matrix passed with clean PHP diagnostics. It reproduced the original
zero-word and ACF-only incoming-link gaps and verified direct/native audit
agreement, scoped issue reconciliation, enabled/disabled/re-enabled Team/Job,
public markup/data preservation, headless-fragment compatibility, restoration
of Relevanssi filters/search behavior and operation without SEOPress. The
combined parent also passed all 28 pagination cases from the preceding change.

MRN QA standard mode passed static security/compatibility, browser, REST index,
axe/keyboard accessibility, timing and Core Web Vitals on the private homepage
and ACF source page. The target page deliberately contains a genuine missing-alt
fixture to prove that real issues survive; it is not an accessibility signoff
target. The changed-file PHPStan row lacked existing helper definitions; the
supplemental scope containing those definitions passed without errors. Final
headless guards were verified by the native matrix and staged static gate.

Remote inventory/parity, merged-source release/Fleet checks, site-owner write
readiness and production analysis/adoption were outside this source gate. The
retained evidence is in the October 19 pagination/analysis release artifact
directory. No new overall Live SEO score or installed adoption is claimed.
