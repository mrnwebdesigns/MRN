# MRN WordPress Stack Production QA Audit

**Audit date:** 2026-07-17  
**Target:** `/Users/khofmeyer/Development/MRN` at `b636c7828a9dca092aa483284efd943e063a28b2`  
**Scope:** shared plugins, MU plugins, `mrn-base-stack` theme, release contracts, and configured Local runtime  
**Method:** MRN QA Engine release mode with complete PHP scope, strict smoke, theme-owned checks, dependency audit, and manual architecture/SEO/accessibility/performance review.

## Executive summary

**Grade: D**  
**Production readiness: 42%**  
**Release decision: do not release.**

The stack has substantial strengths: it is modular, uses WordPress responsive-image APIs, conditionally loads some layout assets, has semantic landmarks/skip links/reduced-motion support, supplies organization schema, and passes PHP syntax, Semgrep, Composer runtime audit, root dependency audit, and diff-whitespace checks. The media bulk workflow also has real capability checks, WordPress nonce verification, per-attachment authorization, and redirect validation.

It is not ready to be an agency baseline for 100+ sites. Three conditions dominate risk: the local runtime cannot be trusted or tested (certificate host mismatch and database failure), a release package is stale relative to source, and the front-end/tooling quality gates are failing or ineffective. A real input-sanitization defect remains in the bulk-media plugin. The theme also contains accumulated architectural debt: very large monolithic files and stylesheet, universally loaded interaction libraries on runtime pages, CPTs owned by the theme, an obsolete JS build stack, and missing working CI-level lint/test gates.

### Risk assessment

| Area | Risk | Basis |
| --- | --- | --- |
| Release integrity | Critical | Source theme is `1.2.83`; packaged theme is `1.2.4`. Five deployed helper packages are `0.1.39`–`0.1.40` while release package is `0.1.43`. |
| Security | High | Bulk-media request template/redirect inputs are read without sanitization at the point of use; dev toolchain has 3 critical advisories. |
| Runtime quality | High | `/wp-json/`, smoke, axe, and timing checks could not run because the Local TLS/database configuration is broken. |
| Performance | High | No valid field/lab timing result; 198 KB base CSS and unconditional runtime loading of 132 KB Motion + 30 KB Splide plus slider/tabs code on affected pages. |
| Accessibility | Medium–High | Strong static foundations exist, but axe WCAG A/AA and keyboard/browser verification did not execute; several custom controls need runtime verification. |
| Maintainability | High | Root PHPStan reports 169 advisory findings; theme PHPStan 83; theme JS lint reports 2,807 errors; theme tooling/metadata still identify it as `_s`. |

## Priority remediation plan

### Critical

1. **Restore a testable Local WordPress runtime and correct its URL/certificate mapping.**

   - **Evidence:** WP-CLI reports `Error establishing a database connection`; curl and Chromium receive `ERR_CERT_COMMON_NAME_INVALID` for `http://mrn-plugins-stack.local`; REST, Playwright, axe and page-speed therefore failed before exercising application code.
   - **Why it matters:** Functional, WCAG, Core Web Vitals, browser compatibility, sitemap/robots, and actual REST health are unverified. A 95+ PageSpeed claim is currently unsupported.
   - **Recommendation:** Start/repair the Local site database, set WordPress `home`/`siteurl` and the QA target to the certificate’s actual hostname/protocol, regenerate/trust the Local certificate, then require `/wp-json/`, public smoke, axe, and timing gates in CI.
   - **Effort / impact:** 0.5–1 day; unblocks all evidence-based release QA.

2. **Eliminate source/package/deployed-version drift before any rollout.**

   - **Evidence:** `qa-rollout-contract.sh` reports local theme `1.2.83` versus packaged `1.2.4`. The parity audit reports five helper-package/version readiness failures, including Freedom House and platform sites.
   - **Why it matters:** A release may deploy unknown code; package/source discrepancies make rollback, incident response, and support across 100 sites unreliable.
   - **Recommendation:** Make packaging a CI artifact built only from the tagged source commit; fail CI if style header/package manifest/git tag disagree. Maintain an explicit rollout matrix with target version, checksum, deployment status, and rollback artifact.
   - **Effort / impact:** 1–2 days; prevents fleet inconsistency and broken rollbacks.

### High

3. **Sanitize and validate media-bulk input at the boundary.**

   - **Evidence:** `plugins/mrn-media-bulk-tools/includes/class-mrn-media-bulk-tools.php:164–166` reads title/alt/caption templates from `$_REQUEST` using only unslash/trim; line 335 reads the redirect similarly. PHPCS reports four errors. Capability checks (`upload_files` and `edit_post`), nonce verification (`check_admin_referer('bulk-media')`), and redirect validation exist, so this is not an unauthenticated exploit.
   - **Why it matters:** Privileged users can persist user-controlled strings into attachment title, caption, and alt fields. Boundary sanitization must be explicit, consistent, and auditable.
   - **Recommendation:** Read POST-only fields after `wp_unslash`; apply `sanitize_text_field` to title/alt templates, `sanitize_textarea_field` (if multiline captions are intended) to captions, and `wp_validate_redirect`/`esc_url_raw` to the redirect. Replace broad `$_REQUEST` use with the expected method-specific superglobal. Add tests for malformed templates, external redirects, and unauthorized attachment IDs.
   - **Effort / impact:** 0.5 day; resolves actual security coding-standard failures.

4. **Make linting and static analysis a reliable release gate.**

   - **Evidence:** Theme `npm run lint:js` has **2,807 errors**; `lint:scss` targets a nonexistent `sass/**/*.scss`; theme PHPCS exhausts 128 MB and uses parallel workers; theme PHPStan shows 83 findings. Root PHPStan has 169 advisory errors, many caused by incomplete WordPress stubs declaring hook/core functions with incorrect arity.
   - **Why it matters:** The existing gates give either noisy false failures or no useful coverage, which trains developers to ignore them.
   - **Recommendation:** Modernize the theme package, define the source-of-truth CSS path, and either migrate the JS incrementally to the configured WordPress standard or configure the standard intentionally for browser-compatible IIFE code. Run PHPCS serially with 2 GB in CI. Replace/repair PHPStan stubs with WordPress/ACF stubs and a small, reviewed baseline; promote only validated findings to blocking.
   - **Effort / impact:** 3–5 days; establishes maintainable enforcement.

5. **Replace the obsolete Node build toolchain.**

   - **Evidence:** `node-sass@7`, `@wordpress/scripts@19`, and an `_s` starter metadata package produce **79** audit advisories (3 critical, 30 high). The production runtime audit is clear because these are development dependencies, but they execute in developer/CI environments.
   - **Why it matters:** Vulnerable build tools expose CI/developer systems and make supported Node upgrades difficult.
   - **Recommendation:** Move from `node-sass` to Dart Sass, update `@wordpress/scripts` to a currently supported major version, regenerate the lockfile, and use Dependabot/Renovate plus `npm audit --omit=dev` and a scheduled dev-tool audit. Do not use `npm audit fix --force` blindly; perform the migration with visual regression tests.
   - **Effort / impact:** 2–4 days; removes critical toolchain exposure and restores maintainable builds.

### Medium

6. **Split front-end runtime assets by feature, not by “builder runtime.”**

   - **Evidence:** `functions.php:942–974` loads Motion (~132 KB), Splide (~30 KB), `front-end-slider.js`, and `front-end-tabs.js` whenever shared builder runtime is enabled, even if a page uses only one feature. The base stylesheet is 198 KB before optional styles.
   - **Why it matters:** This is a material mobile JS/CSS tax and threatens INP/LCP/TBT and 95+ PSI targets.
   - **Recommendation:** Scan the rendered ACF/layout data for motion, slider, and tabs independently; register all assets but enqueue only the feature used. Build/minify component CSS and split the base stylesheet into tokens/base/header-footer/components. Set explicit script strategies (`defer` where compatible) and add a per-template asset budget.
   - **Estimated impact:** typically 30–170 KB less JS on basic pages; measurable TBT/INP improvement. Exact PSI gain requires the repaired runtime.
   - **Effort / impact:** 2–4 days; high Core Web Vitals value.

7. **Move content-model ownership out of the theme.**

   - **Evidence:** case studies, testimonials, galleries, and team/locations CPT registrations are in theme files (`inc/case-study.php`, `inc/testimonial.php`, `inc/gallery.php`, `inc/content-post-types.php`).
   - **Why it matters:** Changing themes should not make client content types, REST routes, archives, or rewrite rules disappear. This is especially risky across a 100-site fleet.
   - **Recommendation:** Put CPTs, taxonomies, migration/versioning, and REST contracts in a site-owned or shared functionality plugin. Keep templates/presentation in the theme. Add activation/deactivation rewrite-flush behavior only where WordPress permits it and preserve existing slugs.
   - **Effort / impact:** 2–3 days plus migration testing; major architectural resilience.

8. **Reduce oversized files and establish component boundaries.**

   - **Evidence:** `style.css` is 198 KB; `inc/builder/helpers.php` is 388 KB; `functions.php` is 81 KB; several MU plugins are 88–258 KB single files. Theme PHPStan also finds a real documented-return mismatch in `inc/builder/helpers.php:5941`.
   - **Why it matters:** Large mixed-responsibility files slow review, increase merge conflicts, and make per-feature testing/reuse difficult.
   - **Recommendation:** Introduce namespaces/prefix-safe modules by domain: assets, media, schema/SEO, builder contracts, each layout renderer, and admin. Keep public helper contracts stable behind compatibility wrappers. Use feature tests before extraction.
   - **Effort / impact:** 1–2 sprints; high maintainability/scalability value.

9. **Treat PHPStan findings as a remediation program, not a pass-with-warning.**

   - **Evidence:** root scan reports 169 advisory findings; the majority are stub-signature noise, while several WP_Post property and return-type findings require classification.
   - **Recommendation:** First fix the bootstrap/stubs so `add_action`, `current_user_can`, `get_post_meta`, ACF calls and WP_Post are accurately typed. Then triage the remaining result set by genuine type risk and enforce new-code cleanliness.
   - **Effort / impact:** 1–3 days; reduces false confidence.

10. **Review focus behavior after runtime is restored.**

   - **Evidence:** skip link, landmarks, labelled navs, accessible search labels, tabs ARIA, and reduced-motion rules are present. However, several components explicitly set `outline: none` (notably icon chooser/search); axe and keyboard runs were blocked.
   - **Recommendation:** Test keyboard order, focus visibility, focus trap/return for mobile navigation and modal/lightbox, tab keyboard model, and error announcement in Chrome/Firefox/Safari. Retain an equal-or-better visible replacement whenever removing outlines.
   - **WCAG:** 2.1.1 Keyboard, 2.4.3 Focus Order, 2.4.7 Focus Visible, 2.4.11 Focus Not Obscured (Minimum), 4.1.2 Name/Role/Value.
   - **Effort / impact:** 1–2 days; verifies WCAG 2.2 AA rather than relying on static intent.

### Low / quality improvements

11. **Remove stale `_s` branding and document supported contracts.** Theme `package.json` describes the project as `underscores` and a starter theme. Rename metadata, document supported WordPress/PHP/Node versions, asset budgets, plugin dependencies, public hooks, release process, and rollback/runbook.

12. **Add QA fixtures and integration coverage.** PHPUnit is skipped because no config exists. Add factory/fixture tests for media bulk actions, schema output, CPT migration, asset decisions, REST permissions, and a public sample page for each builder feature.

13. **Improve SEO observability instead of duplicating plugin responsibilities.** The theme correctly supports `title-tag`, semantic main/header/footer/nav, breadcrumbs, responsive images, and organization JSON-LD. Canonical, meta descriptions, Open Graph/Twitter cards, XML sitemaps, redirects, robots policy and duplicate-content rules must be verified in the repaired live runtime and assigned to one authoritative SEO layer (core/SEO plugin), not duplicated by the theme.

## Security findings

| Severity | Finding | Status |
| --- | --- | --- |
| High | Unsanitized privileged media-bulk template and redirect inputs | Confirmed coding-standard defect; nonce/capability limits exploitability. |
| High | Obsolete development dependency tree: 3 critical and 30 high advisories | Confirmed developer/CI supply-chain risk; not loaded in browser production runtime. |
| Medium | `$_SERVER` values in dashboard support and SearchWP editor performance are not normalized/sanitized according to WPCS | Confirmed standards finding; inspect intended use and normalize at read boundary. |
| Medium | Two compact-link output findings are escaped by surrounding `wp_kses_post` in the comparable hero implementation; template contexts should be made explicit | Likely false-positive/defense-in-depth action, not proven XSS. |
| Informational | Shared-assets AJAX finding | False positive: no handler is registered in the flagged file. The actual provider must still be separately audited. |
| Pass | Semgrep, Composer runtime audit, root Composer audit, syntax checks | No blocking result from these tools. |

## Accessibility findings

Static strengths: skip link, `main` landmarks, labelled navigation, labelled search forms, semantic buttons, decorative background images marked hidden, responsive image helper alt support, and reduced-motion coverage.

Runtime accessibility status: **not assessed**. axe-core could not load either target due to certificate mismatch, so there is no compliant WCAG A/AA result. The first rerun must include homepage, builder pages with hero/slider/tabs/gallery, search, 404, forms, and authenticated admin controls. Run both automated axe and manual keyboard/screen-reader checks.

## SEO findings

Static strengths: `title-tag`, feed links, HTML5 support, semantic template hierarchy, schema.org Organization/LocalBusiness data, breadcrumb integration, and native attachment image rendering with `srcset`/dimensions.

Unverified / needs ownership: canonical URLs, meta descriptions, OG/Twitter output, XML sitemap, robots.txt, archive pagination, redirects, indexability, and rich-result validity. These are runtime/content/configuration concerns and could not be inspected because WordPress is unavailable. Schema should be validated per site in Rich Results Test after the runtime repair; only emit organization data when it matches the active site’s legal identity.

## Performance opportunities

| Opportunity | Approximate benefit | Verification |
| --- | --- | --- |
| Feature-gate Motion/Splide/tabs/slider assets | 30–170 KB less JS depending on page; lower parse/eval and TBT | WebPageTest/Lighthouse after runtime repair |
| Componentize the 198 KB base CSS and deliver critical/base CSS by template | Lower render-blocking CSS and unused CSS | Coverage and PSI/Lighthouse |
| Preserve current hero eager/high-priority treatment; do not globally lazy-load LCP media | Protects LCP | LCP element audit |
| Convert build chain to modern bundling/minification and remove unused legacy vendor code | Smaller transfer and fewer requests | Bundle analyzer/coverage |
| Add resource budgets and RUM (LCP/INP/CLS) to the stack baseline | Prevents cross-site regressions | CI + field data |

No numerical PageSpeed or Core Web Vitals score is reported because the configured runtime did not serve a valid test page.

## Test evidence and limitations

| Check | Result |
| --- | --- |
| `MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root … --mode release --smoke-strict 1` | Failed/blocked: PHPCS/WPCS, API runtime, smoke, axe, timing, local site health; PHP lint/Semgrep/Composer audit/diff passed. |
| API surface audit | Flagged shared-assets file, manually classified as false positive. |
| Theme risk/security scan | Passed focused theme security scan; secret scan found none. |
| Theme full PHPCS | Tooling-capacity blocker at 128 MB, not a code conclusion. Use serial 2 GB scan. |
| Theme PHPStan | 83 findings; many stem from inaccurate shared stubs, one confirmed return-doc mismatch. |
| Theme JS lint | Failed: 2,807 errors. |
| Theme style lint | Failed: configured SCSS glob has no matching source directory. |
| Theme dependency audit | Runtime deps clean; dev audit has 79 advisories (3 critical / 30 high). |

## Acceptance criteria before production

1. Local/staging runtime has valid TLS, working DB, HTTP 200 public pages, and a valid `/wp-json/` index.
2. Package, source, tag, and rollout matrix versions agree; target-site parity is deliberately approved.
3. Media-bulk sanitization is fixed and tested; all PHP security sniffs pass.
4. PHPStan baseline/stubs are corrected; new/changed code has no untriaged findings.
5. JavaScript/style lint run on real source and pass; obsolete dependencies are migrated and audited.
6. Public smoke and axe scan pass on representative templates; manual keyboard/mobile-navigation/lightbox checks pass.
7. Lighthouse/PageSpeed budgets are measured on a representative mobile and desktop page; regressions fail CI.
8. CPT/taxonomy ownership has a documented migration plan out of the theme, or the exception is explicitly approved.
