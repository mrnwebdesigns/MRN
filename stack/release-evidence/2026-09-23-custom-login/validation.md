# Custom-login password-reset redirect correction

Status: Public Security Hardening 0.4.2 merged through PR #87 at `e76b140acf80b91c1dc61a0a4c0316c8cc9ded2c`. Fleet candidate `2026.09.23-custom-login-fleet` is being packaged separately. Not deployed. No production password-reset request or account mutation was made.

## Root cause and correction

The shared authoritative source is `mu-plugins/mrn-public-security-hardening/mrn-public-security-hardening.php` in MRN. The custom route requires core `wp-login.php`, whose successful lost-password and registration handlers send relative `wp-login.php?checkemail=...` redirects. Core `wp_validate_redirect()` expands these relative to the current custom route before `wp_redirect` runs. Existing URL-generation filters never see that destination, leaving a nested path that returns 404.

The new scoped `wp_redirect` filter recognizes the raw core relative target, its validated custom-path form, and the installation's normal local core path. It produces the absolute custom URL from the existing siteurl-aware helper while retaining the encoded query and fragment. It leaves external URLs, unrelated destinations, cancelled redirects, requests outside the custom route and competing login plugins alone. Default endpoint blocking and its existing compatibility exceptions are unchanged. Component header, runtime constant, wrapper version, component baseline and catalog entries are 0.4.2; wrapper behavior is unchanged. A standard readme.txt records the matching Stable tag required by release QA.

## Validation

- Existing full PHP regression suite: PASS, including the new redirect query/fragment, scope, authority and conflict cases.
- Real WordPress HTTP regression suite: 69 PASS assertions across root/default-slug, `/blog/team-access/`, and `/cms/staff-signin/` with split home/siteurl. Uses disposable accounts/database and intercepts mail before transport. The core source reports WordPress 7.1; PHP 8.5.6 and MariaDB 10.11.14.
- Baseline source 0.4.1: 15 PASS assertions reproduce successful reset mail generation followed by the nested redirect and HTTP 404 in all three configurations.
- Covers login, authenticated `/wp-admin/`, nonce-protected logout, lost-password POST, confirmation HTTP 200, reset email URL, reset-cookie/key handling, `rp`, `resetpass`, enabled registration, administrative reauthentication, explicit redirect_to and bare default endpoint HTTP 404.
- Full component MRN QA static run: PASS. PHP lint, security/WPCS, PHP compatibility, PHPStan, Semgrep, secrets, debug artifacts, API surface and diff checks passed. Runtime rows in this static run were skipped by the component configuration and covered separately below.
- Full runtime component MRN QA: PASS, including API `/wp-json/` HTTP 200, browser smoke, accessibility (axe, semantics and keyboard interaction), page timing and measured Core Web Vitals. INP was not measured. The fixture homepage redirects to the actual login screen so the runtime checks cover the changed component.
- Release QA previously identified a missing main landmark in the core login output. The shared custom route now uses WordPress's HTML processor to add `role="main"` to the existing login container, preserving existing landmarks/roles, markup structure, styling and form data. Eight real-processor preservation cases run in each installation layout, plus HTTP checks on login and confirmation responses. No core file or accessibility rule was changed.
- Readme Stable tag, header, runtime version, wrapper, baseline and catalog agree at 0.4.2. Clean-commit release-mode MRN QA passed at `12ccf831f00c556a66f048e435697ed3b54de054` with strict smoke and full component analysis. See `release-qa.md`. Stack candidate reconciliation remains the separate packaging gate.
- Full Stack promotion baseline audit at clean merged ba91208 passed before this feature.

## Production and deployment blockers

- MainWP MCP connection reverified: `connected=true`, Dashboard `wpcontrol.mrndev.io`, 84 abilities. Gloves Online now resolves at its exact live URL as connected site 117. Targeted sync transport timed out, but readback confirmed a fresh `last_sync` of `2026-09-23T12:32:39+00:00`; the timed-out request was not retried.
- Read-only qualification confirms supported `template=mrn-base-stack`, `stylesheet=mrn-base-stack-child`, child preservation and schema-2 eligibility. Release preflight is blocked on Deployment Agent `0.2.2` (requires `0.2.3`). Managed UptimeRobot/reCAPTCHA readiness is also false and must be verified after the agent prerequisite is resolved. The runtime reports baseline `2026.09.14-media-bulk-platform-required` with Config Helper and Universal Sticky Bar drift, no missing required components, no legacy collisions and no incomplete rollouts. This is current target qualification, not deployment acceptance.
- Production browser readback confirmed the nested URL displays the site's 404 page, while both canonical and legacy confirmation URLs display the WordPress email-confirmation page. Initial urllib probes were rejected with HTTP 403. Follow-up curl checks using a normal browser User-Agent verified the nested URL is HTTP 404, canonical and legacy confirmation URLs are HTTP 200, and bare `/wp-login.php` remains HTTP 404. These are pre-deployment checks only.
- A controlled production test mailbox/account is still needed. No real administrator or customer was reset. No live reset email, reset key or Postmark message was generated or inspected for this change.
- Backup receipt/checksum: NONE. No deployment was attempted; create and verify the required labeled, remote Updraft database backup immediately before the first approved remote mutation after target readiness is established. Prior unrelated backup receipts are not reusable.
- Deployment status: NOT DEPLOYED. The correction is being promoted as `2026.09.23-custom-login-fleet`; exact lock/package checksums will be recorded in the generated build receipt. No deployed package or production success is claimed.

## Fleet impact

A fresh targeted sync and MainWP runtime report on `trilliant.mrndev.io` at 2026-09-23T12:00:53Z confirmed loaded Public Security Hardening 0.4.1, matching the immutable `2026.09.16-layout-classes-fleet` baseline. Its configured custom login is active. Public GET checks returned HTTP 200 and the correct confirmation page for both its canonical and nested confirmation URLs, so its host tolerates the malformed redirect; the observed 404 consequence is hosting-dependent. The shared correction still ensures a canonical URL. The report for freshly synced Doster and Brown was unavailable, so its live version is unverified.

All Stack sites using the affected custom-login handler share this code defect, regardless of hostname or slug. Active competing login plugins disable MRN routing and are outside this path. Local snapshots additionally contain this component on Gloves, Platform, Trilliant, Doster and Brown, and Freedom House, but snapshots are not current live fleet evidence and older versions require handler inspection. No fleet sites were changed.

## Remaining work

The component accessibility and MainWP lookup blockers are resolved. Complete the immutable candidate reconciliation and deterministic build. Before a Gloves apply, resolve the agent prerequisite and managed-credential readiness through approved, backup-gated tooling; run exact-plan preflight; provide a controlled test mailbox; take the fresh remote backup; deploy through approved Stack tooling and read back installed hashes. Finally submit the controlled reset, verify canonical HTTP 200, validate the secret-free reset-link origin/path and confirm the corresponding Postmark success record. No real administrator or customer account is authorized for this test.

Full source/package contract tests: 27 release-tool tests passed, initially with one optional cross-repository test skipped for absent environment arguments. The builder suite was then rerun with both actual controller and child-agent validator roots: all 5 passed, including that integration. All 8 promotion tests passed. No deployment tooling or Config Helper behavior changed. The component QA engine correctly skips remote fleet parity, unrelated theme/licensing gates and absent npm/Composer/PHPUnit suites; these skips do not waive per-site preflight or runtime acceptance. INP was not measured. The six required standalone repositories were fetched and verified clean, at merged main, with no unmerged branches and no baseline drift.


The separate `codex/updraft-retention-policy` task worktree was left unchanged. No other repository was modified or committed by this task.
