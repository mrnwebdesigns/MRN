# Native login appearance — 6 November 2026

The existing MRN Light/Dark/System appearance now covers native sign-in, recovery,
reset, validation notices and signed-out screens. Installed on the isolated Local
Hub fixture and `https://admin-editor-pilot.mrndev.io/site-login/` for visual review.
Source commit: `deb63185`, component version `3.3.6`. Release/Fleet gates remain closed.

The login uses the existing semantic tokens, a responsive card, readable labels,
44px controls and visible keyboard focus. AME retains the logo, destination and
support copy. Native WordPress forms, authentication, password controls and redirects
remain intact. No plugin, framework, endpoint, font or JavaScript was added.

The account appearance remains per-user. A host-only Secure/HttpOnly/SameSite=Lax
cookie remembers only its Light/Dark/System enum for this browser after sign-out;
it contains no identity or authentication data. A new browser defaults to System.
The palette is rendered in the initial HTML; System follows the OS through CSS.
The existing pilot/environment boundary and emergency disable also cover login.

| View | Before | Light | Dark |
| --- | --- | --- | --- |
| Review desktop | [Original](before-review.png) | [Login](review-light-login.png) | [Login](review-dark-login.png) |
| 320px mobile | — | [Login](review-light-mobile.png) | [Login](review-dark-mobile.png) |
| Password recovery | — | [Recovery](review-light-recovery.png) | [Recovery](review-dark-recovery.png) |
| Local Hub | [Original](before-local.png) | [Login](local-light-login.png) | [Login](local-dark-login.png) |

**Verification:** [Local matrix](local-results.json), [review matrix](review-results.json)
and [account checks](account-results.json) passed. Both runtimes cover Light, Dark
and System under both OS schemes, first-paint preference, live System changes,
password visibility, keyboard order, Remember Me, 320px overflow, JavaScript-disabled
fallback and an actual invalid-credentials response. Axe WCAG A/AA/2.1AA reported
zero violations in those login/recovery/error scans.

A disposable subscriber verified native password sign-in, requested redirect,
account appearance changes without navigation, cookie synchronization, invalid-nonce
rejection, sign-out and inability to access admin using only the appearance cookie.
A valid reset form passed a Light-mode axe scan; no reset email or password change
was submitted. A later repeat could no longer reach the reset form after the account
login cycle, so it does not establish Dark reset-form coverage. Reset submission,
MFA and third-party authentication integrations are outside this narrow pass.
The account and private credentials were deleted through WordPress after a fresh
verified backup; no existing editor credentials or preferences changed.

**Performance:** the two styles total 10,862 decoded bytes, approximately 2.6KB gzip.
[Serial ABBA observation](performance.json) measured themed CPU at 51.8–56.7ms,
native at 62.1–156.5ms, with no long tasks. Native's first cold navigation was slower;
these four runs do not prove a general speedup. Heap ranged 8.68–9.50MiB themed
and 8.68–8.69MiB native, without forced GC. Login CLS was 0.00181 themed versus
0.00065 native; recovery CLS was zero. This is bounded login evidence, not editor
CPU, retained-memory or save-latency qualification.

**Source QA:** the exact-staged MRN QA Engine commit gate passed PHP lint, security
sniffs, WordPress practices, compatibility, Semgrep, secrets, diff checks and
Composer audit. PHPStan's advisory run reported its missing wp-cli stubs file.
[Full source log](source-qa.txt). Browser/a11y/performance and API runtime rows are
skipped by that static commit-gate policy and covered separately above; the review
REST index returned HTTP 200. No new API handler exists. Full theme/security suites,
parity, license/rollout contracts and Fleet promotion were intentionally not run for
this scoped change; the engine's generic “Release QA” wording is not release approval.

**Installation:** the review-site prewrite DB backup was verified remotely before
atomic installation of only the two new files and the component loader. Source and
installed SHA-256 values match; the original loader remains in the site's private
rollback directory. [Deployment](deploy-receipt.txt), [account QA backup](account-backup.txt),
[cleanup](cleanup-receipt.txt), [installed verification](runtime-verification.json).
Local Hub retained its existing 3.3.1 runtime and received only the additive login
module/require; unrelated admin assets were not replaced. Its original loader is
retained privately. No production deployment, release publication or Git push.

Changed source: `mu-plugins/mrn-admin-ui-css/{mrn-admin-ui-css.php,login.php,login.css,tests/login-boundary.php}`.
No editor JavaScript or save behavior changed. Whole-editor CPU, residual native
save latency and separate Hub provisioning/restart qualification remain open as
recorded in the [previous performance report](../residual-performance-20261105/REVIEW.md).
Next step: human visual review of login density, branding and both palettes.
