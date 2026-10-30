# Stack mail, media and security source QA — 2026-10-30

## Scope and state

- Branch: `codex/stack-mail-media-security-20261030`.
- Worktree: `/Users/khofmeyer/.codex/worktrees/stack-mail-media-security/MRN`.
- Source preparation with owner authorization to commit, push and open a draft PR; no existing-site, MainWP setting, Cloudflare, provider or deployment write.
- Wordfence Free is the owner-selected baseline, with Premium supported per site. The owner selected `security@mrnwebdesigns.com` for alerts; both versioned presets now include it. No remote setting or test-send authorization is implied.
- Full merged-source qualification, final immutable platform release lock and publication are pending. This is not a Fleet-ready or installed release.

## Passing checks

- MRN QA Engine, standard mode, explicitly scoped to the task PHP/helper, shell, manifest, build input and two presets: PHP lint, PHPCS security, WordPress best practices, PHP 7.4–8.3 compatibility, Semgrep, secrets, debug-artifact and whitespace checks pass. Composer audit passes. PHPStan is advisory: its nine unknown-symbol findings arise from the third-party `wfConfig` class not being in its configured analysis universe; actual vendor calls were exercised below.
- 27 bootstrap contract/regression tests pass, including the real install and activate-remaining functions leaving FluentSMTP inactive.
- Shell syntax and Cloudflare policy-profile QA pass.
- Official plugin ZIP hashes, byte sizes, main-file paths, new manifest counts (36 Stack / 35 plain) and bootstrap distribution inclusion checks pass.
- Disposable native WordPress 7.1.2 / private MySQL fixture: Converter for Media and Wordfence activate, FluentSMTP remains inactive, both presets pass actual vendor validation/save/readback, repeated application changes no settings, and invalid profile/wrong-host writes are rejected. Scheduled scans remain enabled and WordPress Application Passwords remain available. A fixture value enabling deletion on deactivation was reset to false, and preview mode was confirmed not to save settings.

The fixture blocks WordPress outbound HTTP and mail, disables optional Wordfence NTP, and disables DNS record probing only in the test PHP process to avoid vendor loopback-DNS delays. It does not establish production DNS, proxy, license, scan-service or email readiness. Temporary configuration/database and disposable credentials were removed after the check.

## Advisory and deferred checks

- The first QA run lacked task-worktree dependencies; installing the committed Composer lock resolved those tooling blockers. No tracked dependency files changed.
- The standard engine's broader parity/rollout rows reported existing Config Helper version differences and a missing sticky-toolbar standalone source in this checkout. These are outside this task and remain promotion considerations; they were not silently fixed or included.
- API surface/runtime, browser smoke, axe accessibility, performance and CWV rows were automatically skipped because no API route or rendered frontend was changed and no release runtime was selected. These skips are not evidence that the new plugins' runtime UI, public proxy behavior or media delivery are release qualified.
- Converter codec/rewrite support, actual public image bytes/cache/fallback/quality and bulk optimization remain host-specific qualification.
- Wordfence free-license/terms, completed malware scan, visitor-IP verification, WAF optimization/learning review, administrator 2FA and alert delivery remain site launch checks.
- MainWP connection is healthy on `wpcontrol.mrndev.io`, with 153 abilities. The tool catalog has no Wordfence settings surface. MainWP's documented supported workflow uses its Dashboard Wordfence extension and per-site overrides. After the owner completed login in another tab, fresh authenticated inspection confirmed MainWP Dashboard 6.4 and Wordfence Integration 5.2.1 enabled, with management and network-option screens working. Its add-on license is marked unactivated. The global options have no alert recipient and enable deletion of Wordfence data on deactivation; these are explicit enrollment-readiness issues. Both prepared presets retain data on deactivation. No Dashboard settings were saved. Saving network defaults can broadcast changes to existing children; do not perform that as an implicit source operation.

## Evidence

The engine report and isolated runtime result are retained at `/private/tmp/mrn-stack-mail-media-security/mrn-qa.md` and `/private/tmp/mrn-stack-mail-media-security/runtime4/result.json`. These temporary diagnostic files are not release artifacts. The versioned presets, helper, plugin input hashes and migration runbook are the durable source handoff.

## Alert-recipient follow-up

The owner-approved `security@mrnwebdesigns.com` address passes actual Wordfence vendor validation and saved-value readback for both profiles in the isolated runtime. Idempotency and preview-without-save checks still pass. Outbound mail was blocked, so this proves configuration only; no delivery is claimed. Follow-up result: `/private/tmp/mrn-stack-mail-media-security/runtime5/result.json`.

## Bootstrap guide packaging follow-up

The merged bootstrap notes link to `MAIL-MEDIA-SECURITY.md`. Include that tracked
guide in the deterministic bootstrap bundle so its mail, media, licensing and
MainWP instructions are available alongside the shipped presets and helper.
This follow-up changes package membership only; runtime behavior is unchanged.
Validation: Python compilation, package-member file/readability check and
`git diff --check` passed. The staged task snapshot also passes the MRN QA commit gate.
