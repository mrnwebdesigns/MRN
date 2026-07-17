# Production-readiness implementation plan

This plan is designed to preserve the current stack's observed behavior. Each phase is independently releasable, uses a narrow change surface, and has explicit stop conditions. Do not combine phases in one release.

## Operating rules

- Begin every phase from a clean branch and record the source commit, package version, and intended deployment targets.
- Change one release unit at a time. Do not mix shared-stack, theme, and client-site changes.
- Preserve stable CSS classes, data attributes, hooks, ACF field keys, CPT slugs, and existing editor workflows unless the phase explicitly migrates them.
- Treat passing static checks as necessary but not sufficient: run the smallest relevant runtime regression suite before promotion.
- Stop and roll back the phase if a representative public page, admin workflow, REST index, or browser console regression appears.

## Phase 1 — completed: media-bulk request hardening

**Scope:** `plugins/mrn-media-bulk-tools` only.

**Implemented:** Normalize title, alt, caption, and redirect request values before they reach the existing processing path. Existing nonce, `upload_files`, `edit_post`, template token, redirect, and transient behavior were retained.

**Verification:** Plugin-scoped full MRN QA passed: PHP lint, PHPCS security sniffs, WordPress best practices, PHPStan, and diff check.

**Runtime follow-up:** When the Local/staging runtime is healthy, manually exercise one title, alt, caption, and all-fields bulk update; confirm tokens, multiline captions, cancellation, invalid redirect fallback, and an unauthorized attachment all retain expected outcomes.

## Phase 2 — repair the QA runtime, with no application-code change

**Scope:** Local site configuration/infrastructure only; no theme or plugin code.

1. Confirm the Local database service and WordPress DB credentials.
2. Confirm `home` and `siteurl` match the Local domain selected by the TLS certificate.
3. Regenerate/trust the Local certificate if its SAN does not include the selected hostname.
4. Verify public homepage, sample page, `wp-admin`, and `/wp-json/` return expected responses.
5. Re-run release QA against the same URLs.

**Acceptance criteria:** WP-CLI no longer reports a DB connection failure; curl/Chromium do not report certificate mismatch; REST, Playwright, axe, and timing gates reach application code.

**Stop condition:** Any database repair requiring a destructive import, credential rotation, or site configuration change outside the Local environment requires separate approval and a backup.

## Phase 3 — release integrity and rollout parity

**Scope:** packaging/release automation and metadata, not runtime features.

1. Identify the source commit for the intended theme and helper releases.
2. Rebuild packages only from that commit; record source SHA and checksums.
3. Make CI reject mismatch among source header, package manifest, git tag, and release manifest.
4. Update the rollout matrix deliberately; do not force-update older sites.
5. Pilot on one noncritical target, validate, then promote in measured batches with rollback artifacts.

**Acceptance criteria:** source theme/package versions agree; helper-package versions are explicit for every deployment target; no parity failure is unexplained.

## Phase 4 — make quality gates trustworthy

**Scope:** theme development tooling and CI only. No browser asset behavior change.

1. Define whether CSS source is compiled SCSS or committed CSS; fix the lint script to target the real source.
2. Configure lint for the existing supported browser syntax, then reduce violations incrementally by file rather than mass-formatting the repository.
3. Run PHPCS serially with 2 GB memory in CI.
4. Repair PHPStan WordPress/ACF stubs and establish a reviewed baseline; require zero new findings.
5. Add a minimal automated test harness for pure helpers and a Playwright smoke fixture for the media-bulk admin workflow.

**Acceptance criteria:** scripts run on real source, give actionable results, and do not rely on unreviewed blanket ignores.

## Phase 5 — toolchain modernization

**Scope:** theme build environment in a dedicated branch.

1. Replace Node Sass with Dart Sass without changing generated CSS intent.
2. Upgrade `@wordpress/scripts` in controlled major-version increments.
3. Regenerate the lockfile, inspect all production bundles, and resolve high/critical advisories.
4. Run visual regression, keyboard, and browser smoke on representative templates before merge.

**Acceptance criteria:** supported Node/tool versions, no critical/high applicable dev-tool advisories, CSS/JS lint passes, and no visual/runtime regression.

## Phase 6 — performance isolation

**Scope:** one front-end capability at a time.

1. Instrument pages to detect motion, slider, tabs, and gallery independently.
2. Register assets centrally but enqueue only the capabilities present in page data.
3. Measure transfer, LCP, INP, CLS, and TBT before/after on mobile and desktop.
4. Split the base stylesheet only after coverage and visual regression tests exist.

**Acceptance criteria:** no template loses its required assets; the tested page type reduces transfer/main-thread cost; Lighthouse budgets are measured rather than assumed.

## Phase 7 — architecture migration

**Scope:** content-model ownership, performed one post type at a time.

1. Move one CPT/taxonomy registration set from theme to functionality plugin while preserving slugs, capabilities, REST settings, rewrite rules, and admin labels.
2. Test all archives, singles, feeds, REST routes, navigation links, and editor workflows on staging.
3. Release with a rollback plugin/theme compatibility window.

**Acceptance criteria:** switching themes does not remove content types or make their records inaccessible; presentation remains theme-owned.

## Phase 8 — fleet rollout

Use a canary site first, create the required database-only backup before deployment, deploy only site-owned or explicitly shared target paths, flush caches, and rerun runtime QA. Expand only after a documented observation window succeeds.
