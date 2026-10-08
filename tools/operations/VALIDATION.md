# Operations validation

## Interrupted-operation recovery — 2026-10-08

Branch: `codex/operations-recovery-20261008`.
Base: merged MRN `main` at `e43d6a2` (the original PR #147 is merged).

- 66 operations tests passed on local Node v26.0.0. Includes authenticated HTTP
  recovery and an actual stdio MCP child process with a lost rollback response;
  worker interruption/restart, two SQLite connections racing recovery, stale or
  withdrawn quiescence evidence, permission revocation, changed target, renewed
  writer enrollment, missing capability, wrong Dashboard, stale runtime, mixed
  trees, missing QA, stale HTML/assets and late-worker fencing.
- Recovery fixtures proved zero additional backup/update/rollback calls. Applied
  code and prior code produce separate `reconciled` outcomes; neither claims the
  original backup or data state was verified. Normal rollback after a reconciled
  update still passes new backup/approval/verification gates.
- Direct ESLint, whitespace validation and all five example configuration
  schemas passed. Configuration without the optional recovery-evidence path
  remains accepted, with recovery unavailable until enrolled.
- Existing Fleet updater: 13/13 tests passed. Existing standard-plugin Python
  package-builder: 17/17 passed. The historical retained-lock failure below no
  longer reproduces on this base.
- Locked production dependency audit: zero reported vulnerabilities.
- Live `mainwp://status`: connected to `wpcontrol.mrndev.io`, 84 abilities.
  This continuation made no managed-site inventory, sync, backup, update or
  deployment calls; service recovery was exercised only with controlled fixtures.
- MRN staged task acceptance passed. The matching final staged-tree proof is
  required again by the commit hook. The Operations CI workflow separately
  checks the protocol suite on Node 22; hosted acceptance remains independent.

This increment changes a Node service, its configuration, tests and operating
documentation. No PHP, WordPress markup, browser UI or frontend assets changed.
PHP/API scanners and public-site browser, axe accessibility, performance, parity
and deployment-readiness rows are inapplicable here and were not pointed at an
unrelated WordPress runtime. Direct JS lint and local authenticated HTTP/stdio
integration tests cover the changed backend; MRN QA's WordPress scanners do not
classify `.mjs` as WordPress/PHP source.

Hosting, real team IdP/OAuth acceptance, site/member/service-credential enrollment,
independent-writer exclusion, state backup/restore and real controlled-environment
recovery acceptance remain outstanding. Operator attestations bind and expire;
the service does not itself kill workers or verify the referenced reports.
Writes remain disabled by default. No service or website was deployed.

## Historical first version — 2026-09-30

Date: 2026-09-30. Branch: `codex/mrn-operations-tool`.
Base: MRN merged `main` at `2c486af`.

- 30 operations tests passed: service workflow, individual signed JWTs,
  authenticated HTTP MCP, actual stdio child MCP, role/site denial, revocation,
  exact-target resolution, stale inventory, partial Stack adoption, capability
  absence, safe mode, idempotency, persistent shared locks, backup failures,
  lost mutation responses, failed verification, exact artifact QA and stale assets.
- Existing Fleet updater regression: 13 tests passed.
- Broader existing Python package-builder suite: 16/17 passed. The retained-lock
  inventory assertion omits `2026.09.25-approved-overlay-fleet.json` from its
  expected set. The same failure was reproduced from a clean `git archive` of
  base `2c486af`; none of those source, test or manifest files changed here.
  This is inherited promotion/lock-inventory debt, not a passing release check.
- Direct ESLint for service/fixture JavaScript passed. MRN QA's current `.mjs`
  classification does not run its WordPress/PHP code scanners on these modules.
- Locked dependency install/audit: no reported vulnerabilities at validation time.
- Real internal MainWP MCP client: `connected: true`, expected
  `wpcontrol.mrndev.io`, 84 capabilities discovered. Used the existing approved
  MCP pin and approved local connection privately, with safe mode enabled.
  This proves local operator-host integration only. No live site discovery,
  targeted sync, backup, update, form submission or deployment was performed.
- MRN QA: staged task acceptance gate passed before this branch's commit.
  Its exact staged-tree proof is retained in the repository's Git directory.
  The first run flagged an environment-variable reference as a potential secret;
  the example now uses an explicit reference object, without disabling the scan.

Runtime test boundary: service authentication and MCP behavior ran locally.
Controlled fixtures simulated WordPress/Fleet mutation and package-builder output;
existing Fleet tests separately cover the inherited updater contracts. No managed
WordPress environment was modified to demonstrate the tool.

WordPress PHP lint/API scans, public-site browser smoke, axe accessibility, Core
Web Vitals, deployment parity and site-owner readiness do not apply to the changed
Node MCP/backend source. They were not run against an unrelated WordPress site.
The optional QA runner's argument/isolation/no-form-write contract was tested;
actual website QA is required when enrolling an operation's target artifact.

Hosting remains unverified: actual IdP login and OAuth client compatibility,
service credential, host/TLS/network, repository/artifact mirrors, registered
sites/members, consistent database backup/restore, external writer coordination
and a controlled real deployment/recovery acceptance run. Real writes remain off.
