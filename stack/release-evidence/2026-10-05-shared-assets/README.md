# Shared asset source foundation — October 5

Status: **not fleet-ready; no site was contacted or changed**. This change does
not install a loader, publish hosted packages, promote CAPTCHA, change plugin
activation/settings, or replace the preserved `2026.10.05-stack-retirement-r1`
candidate. The code is outside the default Stack payload.

Evidence:

- `source-qa.md`: MRN QA Engine, changed source, strict PHPStan, PHP lint,
  WordPress/security standards, compatibility, Semgrep, secrets and diff checks
  passed. No new REST/AJAX/admin-post handlers. Runtime/API HTTP, browser,
  accessibility, performance, hosted parity and rollout rows were deliberately
  excluded from this source gate. Its generated success label is not release
  or fleet acceptance.
- `component-tests.log`: 16 tests passed, including fresh WordPress 7.1.2 with
  a disposable SQLite 3.0.2 database. Native activation identity, inactive
  plugins, unchanged parent/child selection, request pinning, subsequent
  activation and pointer rollback were exercised. Outgoing WP HTTP/mail and
  cron were blocked. No existing local or remote runtime was used.
- `child-asset-regressions.log`: all 3 existing Node asset regression tests
  passed. The complete existing Python deployment suite also passed 108 tests;
  its private log is retained with the artifact set.
- `deterministic-component-builds.json`: independent repeated builds of parent
  1.5.3 and Config Helper 0.1.71 produced identical bytes. The verifier checks
  every archive file and both code/public static inventories. Both receipts
  intentionally retain `runtime_qualified: false`.
- GitHub Actions runs component tests, pinned disposable WordPress inputs and
  the existing child deployment suite for this tooling's future changes.

Private artifacts and full logs:
`/Users/khofmeyer/Development/MRN-release-artifacts/2026-10-05-shared-assets`.
A full pre-change MRN Git bundle and the prior retirement release are retained.
Development archives and local test dependencies are not committed to source.

The [candidate runbook](../../../tools/component-deploy/README.md) identifies
the remaining work: parent discovery, native plugin inventory and lifecycle,
signed backup-gated adoption/atomic transport, public assets/scoped HTML cache
verification, provider qualification and release integration. Parent adoption
fails closed because `WP_Theme` bypasses simple directory filters. Plugin list
display follows the selected manifest, while raw native inventory still reads
stub headers; the transport must reconcile that before adoption.
