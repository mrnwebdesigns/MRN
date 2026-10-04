# MRN Stack Simplification Plan

- Started: 2026-09-24
- Status: planning with local consent qualification; implementation and site migration have not started in this task.
- Planning branch: `codex/stack-simplification-plan-20260924`
- Worktree: `/Users/khofmeyer/Development/MRN-task-worktrees/stack-simplification-plan-20260924`
- Source baseline: `8c7f60119b40820a3a4305175fbac6496c742f84`

## Objective and preservation requirement

Simplify the default Stack around SEOPress and Post SMTP, preserving the useful
MRN integrations and all existing custom work for possible future reuse.

Removing a component from the default Stack must not destroy its repository,
history, release artifacts, documentation, tests, or a site's saved content.
Retained capabilities should remain discoverable through the component catalog,
with their actual distribution and support/readiness recorded. `catalog-only`
means available for deliberate selection, not automatically installed or safe to
reactivate without compatibility checks.

The current authorization is to prepare this plan and test the existing SEOPress
consent configuration on `https://platform.localhost`. This document does not
authorize code changes, plugin installation/removal, database migrations,
credential or DNS changes, commits/pushes/merges, or deployment. Execute later
phases only within the owner's approved scope. No other task's work is to be
reverted, merged, discarded, or repackaged by this planning task.

## Decisions from the discussion

- Replace **FluentSMTP** with **Post SMTP** in the default Stack.
- Remove **SendGrid** as a default Stack dependency and provisioning assumption.
  Post SMTP still needs a sending provider. **Postmark is the proposed provider**,
  following the prior per-site delivery approach; the provider choice remains
  to be explicitly settled before implementation.
- Move SEO title/description editing into **SEOPress**. Remove the duplicate
  **MRN SEO Helper** interface and retire its synchronization only after data
  reconciliation and a dependency audit. Full removal is the preferred outcome
  if no remaining consumer requires its public functions.
- Remove **MRN ACF Character Count**, **MRN AI Assist**, and **MRN Editor
  Enhancements** from the default selection. The interpretation of "Enhanced
  Editor" is `mrn-editor-tools`; confirm that exact identity before execution.
  Classic Editor, Advanced Editor Tools (`tinymce-advanced`), and ACF themselves
  are outside this removal list.
- Replace **MRN Cookie Consent** and **MRN GTM Injector** on suitable sites with
  one verified consent implementation and one tracking loader. SEOPress is the
  candidate; equivalence has not been proven. Retain the MRN implementations as
  reusable options and keep them active on existing sites until a replacement
  passes the consent checks below.
- Use **SEOPress PRO breadcrumbs** with a narrow MRN mega-menu integration.
  The owner reports no current use of the full-trail editor and confirms mega
  menus are used. Using a mega menu does not by itself establish that every
  site's breadcrumbs should follow that menu; record the intended trail per
  migration target.
- Keep the mega-menu feature. Retire the old breadcrumb renderer and redundant
  settings only after its required behavior is supplied through SEOPress.
- Preserve all existing custom components for reuse. Retirement from defaults,
  migration of existing sites, and eventual data cleanup are separate steps.

## Current baseline and concurrent work

The planning worktree was created from `8c7f601`, which includes PR #120 and
`72bd56f` ("Make SendGrid provisioning opt-in"). That work merged while this
plan was being prepared. SendGrid Provisioning is already `catalog-only`, is
absent from the bootstrap plugin list, and automatic provisioning is opt-in.
This is merged source evidence, not evidence of deployment to any site.

Reuse that change. Inspect its release/lock follow-up before preparing a new
mail change; do not repeat or undo it. A SendGrid optional-lock worktree and an
Updraft retention worktree were active during planning. Their state can change;
re-read Git/worktree ownership before implementation. The canonical checkout
remains a coordination surface; implementation uses dedicated task worktrees.

Cookie Consent and GTM Injector were already catalog-only at this baseline.
FluentSMTP, SEO Helper, ACF Character Count, Editor Enhancements, and AI Assist
remain in `manifests/plugins.txt`. Editing that file changes new-site inputs;
it does not remove or migrate components installed on existing sites.

### Source preservation references

These nine canonical plugin checkouts were clean on `main` during the read-only
planning inspection. Each displayed no divergence from its locally cached
upstream reference. No remote fetch, full ref/stash audit, or archive verification
was performed. Recheck before mutation; this table is a recovery index, not a
claim that complete backups or deployable packages have been verified.

Paths below are relative to `/Users/khofmeyer/Development/MRN-plugins/`.

| Component / repository directory | Observed source commit |
| --- | --- |
| `mrn-sendgrid-provisioning` | `7465010230c23c1d901ba382b88be36e93dc0022` |
| `mrn-seo-helper` | `056a2071781af613e73beed288e2e183af4647d7` |
| `mrn-acf-character-count` | `0e9c4bb9a0a81d882b7f3b8e787d8bbd524b1a33` |
| `mrn-cookie-consent` | `e3e1737f1db325f3076025c341af55a1cd7318bc` |
| `mrn-gtm-injector` | `1c8908013fa0b35581409c13502f9a98894a96a0` |
| `mrn-editor-tools` | `8e3b6cdbe2d47039faafa6c814b810d731f7dbb5` |
| `mrn-ai-assist` | `95df9e85ee7d154a7e477fec5be4f41ad8dea361` |
| `mrn-mega-menu` | `de68c999edade4a4f8c9481c8d2f280d045db6f3` |
| `mrn-config-helper` | `12fa0498d67e6e7a649645bdeddd4846558228db` |

The Stack baseline commit also preserves the base-theme and MU-plugin source,
including Schema Bridge. Vendor plugin versions/packages, runtime settings,
and ignored or untracked work require the separate preservation inventory below.
Source HEAD, catalog version, locked release, and live version are distinct facts.

## Phase 0 — Preserve and map dependencies

This is the first implementation prerequisite, before retiring any source or
changing installation defaults.

- Inventory relevant repositories, branches, worktrees, stashes, dirty/untracked
  source, ignored release artifacts, and local-only work. Establish ownership;
  resolve relevant unfinished work without modifying another task's checkout.
- Record a named pre-cleanup source reference and verify recoverability. Preserve
  all refs with verified Git bundles in the approved recovery location; verify a
  temporary restore can resolve the recorded commits. Git bundles do not include
  dirty/untracked files: preserve reviewed non-secret source separately when
  necessary. Do not place credentials or runtime database dumps in Git.
- Preserve immutable prior release locks, versioned ZIPs, source commits,
  checksums, build instructions, tests, and configuration documentation. Verify
  ZIP integrity and package/version identity; an unversioned moving ZIP is not
  the preservation reference. Record secure backup locations without secrets.
- Prepare a capability register describing what each retained component does,
  how to reintroduce it, known dependencies, its support status, and the QA needed
  before reuse. Keep original repositories and historical release records.
- Map public function callers, filters, settings UI, bootstrap writes, scheduled
  jobs, deployment requirements, documentation, and QA contracts for each removal.
  Audit third-party options enforced by Config Helper so removed plugins are not
  silently reconfigured or assumed present.
- Identify actual site usage and intended replacement behavior through read-only
  inventory. The owner's statement about the full-trail editor is the planning
  assumption; check target metadata before retiring its runtime support.

Exit evidence: recoverable source/artifact inventory, ownership of outstanding
Git work, dependency map, and an exact change list for the first implementation.
No preservation archive has been created by this planning pass.

## Phase 1 — Mail defaults and existing-site transition

Build on the already merged SendGrid opt-in change. Replace FluentSMTP with Post
SMTP in new-site inputs and update bootstrap, configuration, sender identity,
diagnostics, retention, documentation, and readiness checks that assume FluentSMTP.
Preserve the development/review behavior that prevents unintended real mail.

Confirm the sending provider and required Post SMTP capabilities/license before
implementation. If Postmark is selected, use a dedicated per-site delivery
identity/server/token through the existing approved secret configuration route.
Keep SMTP-plugin choice separate from provider account/DNS administration.

For existing sites, migrate individually: preserve sender/reply-to behavior and
old configuration securely; configure and verify the replacement; ensure one
active mail handler; then deactivate the old handler through the approved workflow.
Keep old provider accounts, API keys, DNS records, logs, and plugin data until
their dependencies and rollback needs are resolved. Deleting them is a later,
explicitly scoped operation.

Exit evidence: new-site bootstrap works, development mail remains controlled,
approved test messages cover applicable forms/password resets/orders, local mail
logs agree with provider acceptance, and rollback is documented. Provider
acceptance must not be described as inbox placement.

## Phase 2 — SEOPress breadcrumbs with mega-menu hierarchy

Implement a small integration using the documented
[`seopress_pro_breadcrumbs_crumbs` filter](https://www.seopress.org/support/hooks/filter-crumbs-from-breadcrumbs/).
Proposed owner: MRN Mega Menu, conditional on SEOPress PRO availability. Confirm
the integration location against the dependency map before editing.

- Reuse the existing menu-path resolution where suitable. Read labels and URLs
  from WordPress menus and MRN mega-menu assignments, using WordPress permalink
  APIs. Do not hardcode site content or environment URLs.
- Supply the intended intermediate menu trail only on applicable requests with
  an unambiguous match. Define the preferred menu/path when a page appears more
  than once. Fall back to SEOPress's normal trail when no suitable path exists.
- Treat mega-menu layout headings separately from actual navigable ancestors.
  Do not invent destinations. Verify the schema treatment of any non-link item,
  current-page behavior, Home handling, sequential positions, and pagination.
- Let SEOPress own rendering and breadcrumb structured data. Preserve child-theme
  placement and styling through supported output/class hooks or a minimal
  compatibility wrapper while existing callers migrate. Avoid duplicate output.
- Replace existing template/shortcode callers before disabling their implementation.
  Remove the old renderer and unused settings after consumer and metadata checks;
  preserve the original source and saved overrides for potential reuse.

Exit evidence: ordinary and mega-menu trails, duplicate-menu cases, unmatched
pages, non-link headings, custom post types, relevant WooCommerce views, mobile
layout, keyboard/ARIA behavior, and a single coherent BreadcrumbList all pass.
Verify against the exact clean SEOPress release selected for implementation;
earlier inspection of a local vendor runtime copy is not package qualification.

## Phase 3 — Native SEO editing and AI Assist retirement

Inventory SEO Helper's provider selection, field registration, required-field
behavior, post-type eligibility, synchronization, bulk tools, template setup,
public APIs, and consumers. Include Config Helper, Schema Bridge, Editor Lockdown,
AI Assist, bootstrap, theme code, and site-owned integrations.

Move editing to SEOPress's native interface. Reconcile title, description, and
keyword values before stopping synchronization: preserve intentional overrides,
report conflicts instead of blindly overwriting, and validate emitted metadata
against the pre-migration baseline. Use replayable, idempotent migration scripts
with dry-run output, precise post-type scope, and rollback records.

Retire AI Assist's entry points and stop/drain its jobs through supported behavior
before removing its SEO Helper dependency. Preserve approved content, alt text,
metadata, settings, and review/change history. SEOPress AI is not assumed to
replace the entire approval/queue/budget workflow; do not enable automatic content
changes or remove the separate AI Guardrails integration under this scope.

Remove SEO Helper fully if every remaining consumer has a supported alternative.
If a shared function is still needed, document its caller and minimal destination
before retaining any compatibility code. Existing SmartCrawl sites require an
explicit migration or exception; do not remove their provider support globally.

Exit evidence: native fields are visible to intended roles; save/reload and bulk
migration preserve values; page/post/product/CPT output is correct; no missing
function calls, duplicate fields, or competing metadata writers remain. Schema
Bridge and breadcrumb output remain correct independently of SEO Helper.

## Phase 4 — Editor and character-count simplification

Remove MRN Editor Enhancements and MRN ACF Character Count from defaults after
confirming the plugin identity and their actual use. Inventory custom wrap
buttons, snippets, editor styles, TinyMCE/ACF toolbar configuration, and all fields
configured for counters. Remove stale controls and dependencies coherently.

Preserve existing content HTML/classes, saved snippets/configuration, and any
theme-owned styling still used by that content. Do not rewrite client content
or assume every character counter was attached to an SEO field.

Exit evidence: Classic Editor and ACF WYSIWYG still save correctly, intended roles
can edit existing content, no dead toolbar buttons remain, and representative
existing CTA/content markup renders correctly on desktop/tablet/mobile.

## Phase 5 — Consent and tracking consolidation

### Local qualification checkpoint — 2026-09-24

The owner selected `https://platform.localhost` for testing its existing SEOPress
consent configuration. Read-only inventory confirmed SEOPress 10.1 and PRO 10.1.1
active, with MRN Cookie Consent and GTM Injector inactive. The free plugin passed
WordPress.org checksum verification. No site configuration or plugin code was
changed during testing.

Fresh visits and initial rejection blocked tracking at desktop, tablet, and mobile
sizes. Desktop/mobile acceptance produced one observed GA4 page view per tested
page. Withdrawal changed Google consent to denied, but later navigation/reload
still emitted requests from the loaded tag and retained tracking cookies. Accept
also granted advertising consent and enabled DoubleClick requests while the banner
described analytics. An announcement popup initially covered the consent controls.

**Replacement qualification remains incomplete.** No GTM container was configured
or tested. Keep the existing MRN options available until these gaps are resolved.
The full findings, screenshots, network evidence, and MRN QA results are in the
[Platform consent qualification report](review-evidence/2026-09-24-platform-consent/README.md).
MRN QA overall failed on unrelated unnamed links on `/about/`; consent-scoped
checks and their limitations are reported separately.

### Remaining implementation and acceptance work

SEOPress is the proposed replacement, not yet a proven equivalent. Inventory all
tracking sources, including Site Kit, direct scripts, tag containers, pixels,
embeds, and snippets outside SEOPress. Decide each site's required consent scope
and whether it needs GTM at all. SEOPress's banner does not establish control over
scripts emitted independently by other plugins.

Configure one consent manager and one loader for each tracker. Move existing
settings through a reviewed migration and prevent old/new loaders from running
together. Keep MRN Cookie Consent/GTM Injector available for sites where the
replacement does not meet the required behavior.

Exit evidence: clean-browser network checks show no nonessential GTM/GA/Ads/
DoubleClick requests before opt-in; Reject stays quiet through navigation/reload;
Accept loads once without duplicate events; stored choices, any required separate
categories, and withdrawal work; relevant cookies/transports are handled correctly.
Check noscript output, caches, keyboard access, accessible names, and responsive
banner/preferences layout. Consent Mode `denied` alone does not satisfy the
no-request requirement. If equivalence fails, keep the existing integration and
record the gap rather than weakening the requirement to complete a removal.

## Phase 6 — Distribution, QA, and per-site rollout

Each independent change may progress through acceptance and promotion when ready;
there is no requirement to wait for every phase to finish before a narrow release.

Update the applicable default manifest, component catalog, human catalog,
bootstrap/configuration tooling, hosting-platform contract, dependency checks,
and release documentation together. Reusable removed components should remain
catalog-only where appropriate; do not erase their historical locks or release
artifacts. Classification and catalog presence do not authorize installation.

Existing optional-plugin and selective Stack-plugin planners are upgrade-oriented.
Do not assume they can install Post SMTP, switch mailers, deactivate components,
or migrate data. Establish the exact supported migration workflow before using
it; any needed tooling extension is an explicitly scoped implementation change.

Use one task branch/worktree per implementation and the smallest relevant MRN QA
suite for each changed component. Include API, accessibility, performance, browser,
and integration coverage where applicable. Record skipped checks and blockers.
Task acceptance, merged source, release candidate, and verified deployment are
separate states under the concurrent development policy.

For deliberate Stack promotion, reconcile clean merged source, run promotion
audit, produce immutable release locks/artifacts, and run candidate/release QA.
Before each approved remote runtime write, verify the required labeled remote
database-only Updraft backup and a rollback package/configuration record. Resolve
each exact MainWP target and fresh-sync/read back before relying on its inventory;
use the canonical access routes. Verify the site's active child/parent theme
before changing theme output. Local preview approval does not authorize a remote
write. Do not use a manifest removal as a substitute for a site migration.

Roll out to an explicitly chosen canary, verify behavior and runtime versions,
then proceed in approved cohorts. Preserve old plugin data until a separate
retention/deletion decision. Reactivation may require compatible dependencies and
settings restoration; retaining a ZIP alone does not prove rollback.

## Open decisions and next step

1. Confirm Postmark as the delivery provider behind Post SMTP and required plugin
   capabilities/license.
2. Confirm "Enhanced Editor" means `mrn-editor-tools` only.
3. During Phase 0, identify which sites require menu-derived breadcrumbs and
   which need consent features beyond SEOPress's verified coverage.
4. Platform is selected for existing-configuration consent qualification. Select
   the first implementation target and authorize its exact phase. The recommended
   starting point is preservation/dependency inventory,
   then the remaining mail-default work built on PR #120.

The initial planning pass created this document and its README entry. The local
consent test added the linked evidence and MRN QA report. Document checks cover
whitespace, links, and recorded source references. No executable project code,
release metadata, settings, or deployed component was changed. Release and
recoverability signoff remain outstanding; the local runtime test is limited to
the reported consent scenario.

## References

- [Concurrent development and promotion policy](../docs/MRN-CONCURRENT-DEVELOPMENT-POLICY.md)
- [Agent operating context](../docs/MRN-AGENT-OPERATING-CONTEXT.md)
- [Component governance](PLUGIN_GOVERNANCE.md)
- [Component catalog](manifests/component-catalog.json)
- [Backup policy](BACKUP_POLICY.md)
- [Optional-plugin rollout](MAINWP_OPTIONAL_PLUGIN_ROLLOUT_PLAN.md)
- [Selective Stack-plugin rollout](MAINWP_STACK_PLUGIN_ROLLOUT_PLAN.md)
- [SEOPress breadcrumb filter](https://www.seopress.org/support/hooks/filter-crumbs-from-breadcrumbs/)
- [SEOPress breadcrumb configuration](https://www.seopress.org/support/guides/customize-breadcrumbs/)
- [SEOPress consent configuration](https://www.seopress.org/support/guides/how-to-request-users-consent-for-analytics-tracking-with-seopress/)
- [SEOPress GTM integration](https://www.seopress.org/support/guides/google-tag-manager-wordpress-seopress/)
- [Google basic and advanced consent behavior](https://developers.google.com/tag-platform/security/concepts/consent-mode)
- [Post SMTP provider support](https://wordpress.org/plugins/post-smtp/)
