1) Changes Summary By Repo
- In-scope repos detected: 1
- Repo: /Users/khofmeyer/Development/MRN-plugins/mrn-tokens
  - branch: main
  - commit: 8362722812a0212466c87dd4bf41daa375603762
  - tag: none
  - working tree: clean
- Changed files: none detected

2) Release Readiness Summary
- Mode: release
- Scope: site-only
- Approved ref mode: approved-clean-working-tree
- Playwright provider: engine
- Project root: /Users/khofmeyer/Development/MRN-plugins/mrn-tokens
- Project: mrn-tokens
- Project kind: plugin
- Runtime target source: cli
- Site path: /Users/khofmeyer/Development/MRN-sites/platform/public
- Site URL: https://platform.localhost
- Change policy: wordpress=1, frontend=0, admin=1, api=1, security=1, docs_only=0
- Auto gates: smoke=always, accessibility=always, performance=always, api=always, phpcbf=auto, secrets=auto, debug_artifacts=auto, php_compat=auto, readme_version=auto, cwv=auto

3) Tool Execution Report
| Tool | Ran/Skipped | Pass/Fail | Repo/Path | Notes |
| --- | --- | --- | --- | --- |
| PHP lint | Ran | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | 5 files linted |
| PHPCS | Ran | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | 5 PHP target(s) passed security sniffs (memory_limit=2G, parallel=1) |
| WordPress best practices | Ran | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | 5 PHP target(s) passed WordPress standard (memory_limit=2G, parallel=1, chunk_size=40) |
| PHPCBF | Ran | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | no auto-fixable WordPress coding-standard violations detected |
| PHP compatibility | Ran | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | 5 PHP target(s) compatible with testVersion=7.4-8.3 |
| PHPStan | Ran | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | static analysis passed |
| Semgrep | Skipped | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | not applicable: semgrep config not found |
| Secrets scan | Ran | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | 7 file(s) scanned for hardcoded secrets |
| Debug artifact scan | Ran | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | 5 file(s) scanned for debug artifacts |
| WordPress API surface audit | Ran | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | 2 API surface file(s) checked |
| WordPress API runtime smoke | Ran | Pass | https://platform.localhost/wp-json/ | REST index returned HTTP 200 |
| git diff --check | Ran | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | no whitespace errors |
| Readme/version consistency | Ran | Fail | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | release mode requires a readme.txt with a Stable tag; none found at readme.txt |
| audit-config-helper-parity.sh | Skipped | Pass | /Users/khofmeyer/Development/MRN/stack/scripts/audit-config-helper-parity.sh | not applicable: standalone project without stack scope |
| qa-theme.sh | Skipped | Pass | /Users/khofmeyer/Development/MRN/stack/scripts/qa-theme.sh | not applicable: standalone project; static theme checks run in engine rows |
| qa-security.sh | Skipped | Pass | /Users/khofmeyer/Development/MRN/stack/scripts/qa-security.sh | not applicable: standalone project; security covered by PHPCS/Semgrep/API rows |
| qa-playwright-local-stack-site.sh | Ran | Pass | /Users/khofmeyer/Development/MRN-qa-engine/tools/run-playwright-smoke.sh | engine, scope=public |
| Accessibility smoke | Ran | Fail | /Users/khofmeyer/Development/MRN-qa-engine/tools/run-accessibility-smoke.sh |      attachment #2: video (video/webm) ──────────────────────────────────────────────────────────────     test-results/accessibility-accessibility-smoke-about--chromium/video.webm     ────────────────────────────────────────────────────────────────────────────────────────────────      Error Context: test-results/accessibility-accessibility-smoke-about--chromium/error-context.md    1 failed     [chromium] › tests/playwright/accessibility.spec.mjs:452:3 › accessibility smoke: /about/ ──────   1 passed (19.1s)  |
| qa-page-speed.sh | Ran | Fail | /Users/khofmeyer/Development/MRN/stack/scripts/qa-page-speed.sh | https://platform.localhost/ code=503 ttfb=0.149374 total=0.149440 https://platform.localhost/about/ code=503 ttfb=0.056799 total=0.056877 |
| Core Web Vitals | Ran | Fail | /Users/khofmeyer/Development/MRN-qa-engine/tools/run-web-vitals-smoke.sh |      attachment #2: video (video/webm) ──────────────────────────────────────────────────────────────     test-results/web-vitals-core-web-vitals-about--chromium/video.webm     ────────────────────────────────────────────────────────────────────────────────────────────────      Error Context: test-results/web-vitals-core-web-vitals-about--chromium/error-context.md    2 failed     [chromium] › tests/playwright/web-vitals.spec.mjs:86:3 › core web vitals: / ────────────────────     [chromium] › tests/playwright/web-vitals.spec.mjs:86:3 › core web vitals: /about/ ──────────────  |
| qa-license-coverage.sh | Skipped | Pass | /Users/khofmeyer/Development/MRN/stack/scripts/qa-license-coverage.sh | not applicable: skipped by scope |
| qa-rollout-contract.sh | Skipped | Pass | /Users/khofmeyer/Development/MRN/stack/scripts/qa-rollout-contract.sh | not applicable: skipped by scope |
| qa-local-stack-site.sh | Skipped | Pass | /Users/khofmeyer/Development/MRN/stack/scripts/qa-local-stack-site.sh | not applicable: scope does not require |
| ESLint | Skipped | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | not applicable: root package.json missing |
| Stylelint | Skipped | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | not applicable: root package.json missing |
| Composer audit | Skipped | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | not applicable: composer.lock missing |
| npm audit | Skipped | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | not applicable: root package-lock.json missing |
| PHPUnit | Skipped | Pass | /Users/khofmeyer/Development/MRN-plugins/mrn-tokens | not applicable: phpunit or config missing |
| **OVERALL** | Ran | Fail/Blocked | all in-scope repos | Failing or warning checks present |

**Release QA Result: NOT 100%**

4) Verification Steps
- Verified repo release state for each in-scope repository.
- Classified changes into WordPress, frontend, admin, API, security, and docs-only policy buckets.
- Executed standardized QA toolchain rows with pass/fail evidence.
- Applied policy-aware WordPress/API/accessibility/performance/runtime checks.

5) Missing Release Items
- none

6) Rollout Risks
- Deploying out of order can desync site and stack expectations; verify scope before release.

7) Security Concerns
- Review nonce, capability, sanitization, and escaping in any failing PHPCS/Semgrep/API rows.

8) Accessibility Concerns
- Accessibility smoke runs automatically for frontend/release runtime targets; complete manual WCAG review for material UI changes.

9) Performance Concerns
- Performance timing runs automatically for frontend/release runtime targets; complete deeper Lighthouse review for material rendering changes.

10) Classification:
- release blockers
- none
- should-fix-before-release
- Resolve failed checks listed in Tool Execution Report before release sign-off.
- follow-up items
- Add project-specific regression cases as coverage grows.

11) Cross-Repo Coordination Risks
- Site-only scope selected: verify no hidden dependency on unreleased stack changes.
