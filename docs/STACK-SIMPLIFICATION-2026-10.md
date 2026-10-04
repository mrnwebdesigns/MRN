# Stack simplification: accepted decisions and implementation

Owner approved implementation, commits, pushes, Git reconciliation and release qualification on October 4, 2026. Fleet readiness does not mean every existing site has been migrated. Site execution still uses an exact target, guarded plan, backup and verification.

## Added editor cleanup

- Remove the **SEO Helper Content Types** settings section, including its post-type toggles, by retiring SEO Helper after metadata reconciliation.
- Remove the **SEO & Schema** post editor box: Schema mode, Page intent and Schema description override. Preserve historical values and published schema; keep Business Information and Schema Bridge integration.
- These requirements come from the two owner screenshots dated October 1 supplied in this task. They are included in implementation and runtime acceptance, not deferred UI polish.

## Decisions

| ID | Accepted direction |
| --- | --- |
| D01 | Replace SmartCrawl Pro with SEOPress Free + PRO as the Stack SEO platform. |
| D02 | Strip SEO Helper down to indispensable integrations; remove it entirely if none remain. |
| D03 | Remove MRN ACF Character Count from the default Stack. |
| D04 | Remove MRN AI Assist from the default Stack. |
| D05 | Remove MRN Editor Enhancements from the default Stack. |
| D06 | Use SEOPress for native GA4 and GTM insertion; retire MRN GTM Injector. |
| D07 | Replace MRN Cookie Consent with the SEOPress consent banner. |
| D08 | Replace the custom breadcrumb engine with SEOPress PRO breadcrumbs, retaining needed MRN context. |
| D09 | Preserve Business Information and Schema Bridge while changing SEO providers. |
| D10 | Use SEOPress ecommerce tracking, with only the compatibility work demonstrated necessary. |
| D11 | Replace FluentSMTP with Post SMTP in the default Stack. |
| D12 | Remove SendGrid as a default Stack dependency and provisioning assumption. |
| D13 | Preserve custom components for reuse while removing them from the standard installation. |
| D14 | Let selected non-administrator roles view WordPress comments and WooCommerce reviews. |
| D15 | Add per-site comment/review notification recipients to MRN Comment Management. |
| D16 | Extend MRN reCAPTCHA Enterprise Manager to blog comments and WooCommerce reviews. |
| D17 | Stop shared Stack assets and expensive discovery work from running where they are unused. |
| D18 | Standardize Git-based site deployment with QA and explicit Dev/Live destinations. |
| D19 | Make CSS/JS URLs immutable and deployment/cache behavior predictable. |
| D20 | Use the shared Updraft retention, isolation and pre-write backup policy. |
| D21 | Keep content-only CPTs useful: category/tag filtering, downloads, links and tokens must work. |
| D22 | Build one conversational MRN operations tool around WordPress, the Stack and MainWP. |

## Execution order

1. Preserve pre-existing source, uncommitted planning evidence and unique refs before Git cleanup.
2. Retire duplicate editor surfaces and reconcile installation/bootstrap defaults.
3. Provide guarded, repeatable existing-site migrations with metadata and settings preservation.
4. Resolve remaining source branches and qualification dependencies; merge only reviewed, tested work.
5. Reconcile release metadata, exact committed sources, immutable packages and hosted bootstrap inputs.
6. Run component, new-site, existing-site, runtime and release QA; generate the release lock from clean merged main.
7. Verify supported Fleet preflight and rollout/rollback contracts against the exact candidate and named environments.

## Current execution status

Source reconciliation is merged. The immutable candidate is being qualified; it is not a deployed fleet release.

- Schema Bridge 0.7.0 removes the requested panel/save handler and retains historical output values. Full component and named local runtime MRN QA passed, including strict source analysis after isolating standalone fixture stubs.
- Config Helper 0.1.70 hides legacy breadcrumb fields on migrated sites while retaining saved values. Native provider regression tests and component/runtime MRN QA passed; strict source analysis passed. The per-post Breadcrumb Trail box and its save handler are also disabled when SEOPress is selected, preserving historical metadata.
- New-site defaults now omit the four retired editors, install Post SMTP, pin SEOPress Free/PRO 10.3, and configure native breadcrumbs. Development keeps native SEO editing active while suppressing indexing, tracking and disabled external cron.
- The editor-retirement migration passed read-only planning, conflict refusal, metadata/schema preservation, no-op repeat, guarded rollback and repeat-rollback checks on `mrn-stack-qa-20261004.localhost`.
- Browser verification on that isolated fixture confirms the old Schema panel is absent and native SEOPress remains in the Classic Editor/ACF workflow.
- The operations service's 30 tests and lint passed. Its separate hosting/team-identity/exclusive-writer enrollment requirements remain explicit.
- The reCAPTCHA comments/reviews branch is merged with the later WPForms loader, preserving both asset graphs. Neither opt-in feature is enabled remotely by this reconciliation.

The canonical Stack release and its optional integrations have different qualification boundaries. The default CAPTCHA package remains the registered 0.1.4 until the 0.2.0 deployment adapter and genuine-token qualification pass. Its merged 0.2.0 source and deterministic artifact are preserved as a candidate, not silently promoted.

The full release-mode QA run passed source, security, API, browser, accessibility, performance and CWV checks on the isolated Local Hub fixture. Its advisory parity and rollout rows are not release acceptance: seven older development copies differ from the hosted package, and the feature worktree cannot resolve the standalone sticky-toolbar source at the legacy relative path. The report's generated “100% SUCCESS” label must not be read as fleet readiness. See `stack/release-evidence/2026-10-04-simplification/full-release-qa.md` and its README.

The first locked candidate was assembled twice with identical package bytes, installed on the local fixture and read back with no missing components, drift or legacy collisions. Fresh MainWP inventory for `https://gloves-online.com/` then passed the exact 29-component package preflight, preserving its active child theme. This was read-only: production was not deployed or migrated. Revision r2 corrects the Updraft fixture tree's version and seals source before the separate lock commit; it must receive its own package/readback/preflight receipts.

Existing site deployment receipts are historical evidence, not proof of this candidate. Hosted package parity, actual named-target deployment/verification and the shared asset adapter below remain required before release promotion.

## Preservation

A verified pre-change Git bundle and exact copies of the September planning files are retained outside source in `MRN-release-artifacts/2026-10-04-stack-simplification`. Original source repositories and historical release locks remain available. No client database or secret values are included in this document.

## Remaining release boundaries

- **Shared asset deployment:** the parent still serves mutable `style.css` and script paths with version/mtime query strings. The MainWP optional-plugin installer overwrites directories. Neither satisfies the accepted immutable asset/atomic request contract merely because child-theme deployment now does. Shared parent/plugin asset promotion requires its own adapter implementation and named runtime qualification. This is unresolved engineering work, not a missing owner approval of Stack simplification.
- **CAPTCHA 0.2.0:** source is merged and its deterministic candidate is built, with 121 integration, 24 browser and 10 asset/loader assertions passing locally. Real Google assessment permission and synthetic remote staging submissions require the narrow approvals in the plugin's readiness plan. Existing protection stays active until cutover is qualified.
- **Operations service:** merged and tested source still needs the real team identity/hosting/enrollment and exclusive writer contract. A locally passing service is not a deployed team tool.
- **Existing sites:** per-site metadata, tracking/consent, mail delivery, backups and rollback qualification remain required. A full Stack code package does not run the editor-retirement migration or convert SmartCrawl automatically.

The source and package work must not be called 100% fleet ready while these required boundaries remain open.
