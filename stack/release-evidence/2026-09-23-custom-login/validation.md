# Custom-login password-reset redirect correction

Status: source correction prepared as Public Security Hardening 0.4.2. Not deployed. No production password-reset request or account mutation was made.

## Root cause and correction

The shared authoritative source is `mu-plugins/mrn-public-security-hardening/mrn-public-security-hardening.php` in MRN. The custom route requires core `wp-login.php`, whose successful lost-password and registration handlers send relative `wp-login.php?checkemail=...` redirects. Core `wp_validate_redirect()` expands these relative to the current custom route before `wp_redirect` runs. Existing URL-generation filters never see that destination, leaving a nested path that returns 404.

The new scoped `wp_redirect` filter recognizes the raw core relative target, its validated custom-path form, and the installation's normal local core path. It produces the absolute custom URL from the existing siteurl-aware helper while retaining the encoded query and fragment. It leaves external URLs, unrelated destinations, cancelled redirects, requests outside the custom route and competing login plugins alone. Default endpoint blocking and its existing compatibility exceptions are unchanged. Component header, runtime constant, wrapper version and catalog entries are 0.4.2; wrapper behavior is unchanged.

## Validation

- Existing full PHP regression suite: PASS, including the new redirect query/fragment, scope, authority and conflict cases.
- Real WordPress HTTP regression suite: 60 PASS assertions across root/default-slug, `/blog/team-access/`, and `/cms/staff-signin/` with split home/siteurl. Uses disposable accounts/database and intercepts mail before transport. The core source reports WordPress 7.1; PHP 8.5.6 and MariaDB 10.11.14.
- Baseline source 0.4.1: 15 PASS assertions reproduce successful reset mail generation followed by the nested redirect and HTTP 404 in all three configurations.
- Covers login, authenticated `/wp-admin/`, nonce-protected logout, lost-password POST, confirmation HTTP 200, reset email URL, reset-cookie/key handling, `rp`, `resetpass`, enabled registration, administrative reauthentication, explicit redirect_to and bare default endpoint HTTP 404.
- Full component MRN QA static run: PASS. PHP lint, security/WPCS, PHP compatibility, PHPStan, Semgrep, secrets, debug artifacts, API surface and diff checks passed. Runtime rows in this static run were skipped by the component configuration and covered separately below.
- Runtime MRN QA: API `/wp-json/` HTTP 200, browser smoke, page timing and measured Core Web Vitals passed. Accessibility FAILED because the unchanged core login screen has no main landmark. Axe WCAG A/AA found no violations on the login screen; the additional MRN semantic assertion failed before keyboard/dynamic checks could complete. INP was not measured. The report is a failed runtime gate, not release signoff.
- An earlier broad runtime probe also found invalid nested list markup in the disposable default theme's homepage navigation. The dedicated login fixture now routes its homepage probe to the actual login screen; no production theme or core markup was changed and no accessibility rules were disabled.
- Full Stack promotion baseline audit at clean merged ba91208 passed before this feature. No new immutable release lock was generated while required runtime QA is failing.

## Production and deployment blockers

- MainWP MCP status verified `connected=true`, Dashboard `wpcontrol.mrndev.io`, 84 abilities. Exact lookup of `https://gloves-online.com/` returned `mainwp_site_not_found`; a current 105-site inventory contains neither Gloves Online nor its formerly recorded temporary-host entry. No remembered site ID was used for a write.
- The approved alternate REST API credential lookup through Production Hub timed out awaiting the MRN 1Password connection. No replacement credential or direct production file edit was attempted.
- Production browser readback confirmed the nested URL displays the site's 404 page, while both canonical and legacy confirmation URLs display the WordPress email-confirmation page. Initial urllib probes were rejected with HTTP 403. Follow-up curl checks using a normal browser User-Agent verified the nested URL is HTTP 404, canonical and legacy confirmation URLs are HTTP 200, and bare `/wp-login.php` remains HTTP 404. These are pre-deployment checks only.
- A controlled production test mailbox/account is still needed. No real administrator or customer was reset. No live reset email, reset key or Postmark message was generated or inspected for this change.
- Backup receipt/checksum: NONE. No deployment was attempted; create and verify the required labeled, remote Updraft database backup immediately before the first approved remote mutation after readiness is restored. Prior unrelated backup receipts are not reusable.
- Deployment status: NOT DEPLOYED. No Stack release identifier, immutable release-lock checksum, deployed package or production success claim exists for this correction yet.

## Fleet impact

A fresh targeted sync and MainWP runtime report on `trilliant.mrndev.io` at 2026-09-23T12:00:53Z confirmed loaded Public Security Hardening 0.4.1, matching the immutable `2026.09.16-layout-classes-fleet` baseline. It needs the correction when this custom-login handler is active. The report for freshly synced Doster and Brown was unavailable, so its live version is unverified.

All Stack sites using the affected custom-login handler share this code defect, regardless of hostname or slug. Active competing login plugins disable MRN routing and are outside this path. Local snapshots additionally contain this component on Gloves, Platform, Trilliant, Doster and Brown, and Freedom House, but snapshots are not current live fleet evidence and older versions require handler inspection. No fleet sites were changed.

## Remaining work

Resolve or explicitly disposition the core-login semantic accessibility blocker through the proper QA/release workflow. Restore an exact, verified Gloves Online deployment route and provide the controlled test mailbox. Then merge accepted source, promote from clean current merged main, reconcile versions/notes/immutable lock, build deterministic artifacts and record checksums, qualify the target, take the fresh remote backup, deploy through approved Stack tooling and read back installed hashes. Finally submit the controlled reset, verify canonical HTTP 200, validate the secret-free reset-link origin/path and confirm the corresponding Postmark success record.

The separate `codex/updraft-retention-policy` task worktree was left unchanged. No other repository was modified or committed by this task.
