# October 4 Stack reconciliation evidence

The source is merged in MRN PR 177 and the component PRs linked in the decision record. Reports preserve their actual scope and date. The final Stack source/runtime report passes strict static analysis, API, browser, accessibility, performance and CWV.

`full-release-qa.md` is the broader release-mode run against `mrn-stack-qa-20261004.localhost`. Its generated overall success label does **not** override advisory failures: seven development installations differ from the hosted Config Helper baseline, and the legacy rollout script cannot resolve the standalone sticky-toolbar source from the task worktree. The hosted source and exact candidate need independent rollout reconciliation. No production rollout or fleet signoff is established by that report. PHPCBF was intentionally disabled; root ESLint/Stylelint, npm audit and PHPUnit were inapplicable because their root configurations are absent. API, browser, accessibility, performance, PHP/static/security and Composer checks ran.

`first-candidate-runtime.json` records a valid installed lock with no missing components, drift or legacy collisions on the isolated fixture. `first-candidate-mainwp-preflight.json` records read-only Gloves transport/storage qualification, not deployment. These receipts belong to the first candidate; r2 receipts are retained separately in the external artifact directory after sealing the final source and lock.

`editor-browser.json` verifies an authenticated Classic Editor save, native SEOPress, absence of both screenshot controls and the obsolete breadcrumb box. `editor-migration-qa.json` verifies conflict refusal, metadata preservation, repeat application and rollback. `development-policy.json` verifies stored synthetic tracking settings remain available while frontend tracking is absent and noindex is present.

CAPTCHA reports use mocked Google responses. Its 0.2.0 receipt is a deterministic source-bound candidate; production assessment access, genuine remote qualification and the optional-plugin asset adapter remain unqualified. The bootstrap package selection remains 0.1.4.

Hosted packages are checksum-verified in the Stack manager's `.incoming/2026.10.04-stack-simplification/packages` directory only. No claim of active hosted bootstrap parity or site deployment follows from staging them. See the main reconciliation document for the remaining shared asset adapter and operations-service boundaries.
