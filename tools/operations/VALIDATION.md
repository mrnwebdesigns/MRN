# Operations validation

## ChatGPT plugin delivery — 2026-10-08

ChatGPT is the selected team surface: install the workspace plugin, sign in, and
work in chat. This increment supplies its remote-only portable package and the
service-side connection metadata; it does not provision or publish the service.

- 96 Operations tests passed, including raw HTTP verification of top-level and
  mirrored OAuth declarations for all 11 tools, operation-effect annotations,
  expired-identity reconnect challenges, denied identity hints, audience/resource
  binding, deterministic ZIP contents, no-overwrite behavior and invalid endpoint
  rejection. Existing signed-token HTTP, stdio, discovery, repair and recovery
  regressions remain passing. Direct ESLint and whitespace checks passed.
- MRN staged task acceptance passed; the final staged tree needs its matching
  proof before commit. Direct JS tests cover the protocol; the package tests
  execute the Python builder. No new dependency was introduced.
- A preview ZIP was built using the proposed example hostname, with SHA-256
  `cdc54e31074c50de0de9dfb3ad18c89fafb91ba2de06b8d9c67025b6d6d42adc`.
  It contains only `plugin.json` and `mcp.json`; it is not a live connection.
- Live ChatGPT installation, OAuth consent/token refresh, identity-provider
  configuration and workspace publishing remain unverified. No site or service
  was changed remotely. WordPress API-runtime, browser accessibility, frontend
  performance, parity and deployment checks are inapplicable to these backend
  and packaging changes; actual ChatGPT acceptance is still required.

## Automatic discovery and MRN knowledge — 2026-10-08

Continuation on `codex/operations-recovery-20261008`, after `fdfeb72`; included in
the same draft PR #236. Existing MainWP websites no longer require a duplicate
Operations registry entry. Hosted configuration enables discovery and the optional
website override example is empty.

- 90 service tests passed, including discovery/inspection from an empty registry,
  authenticated HTTP and real stdio MCP, scoped URL grants without bulk access,
  revocation between pages, incomplete/duplicate inventory, changing site IDs,
  additions/removals, exact-URL knowledge joins, name/environment conflicts,
  unknown write prerequisites, Local Hub-only routes, credential exclusion,
  symlink boundaries, partial overrides, unrelated-site isolation and all prior
  repair/recovery regressions.
- Direct ESLint, all five example schemas and whitespace checks passed. Production
  dependency audit reported no vulnerabilities. Existing Fleet tests: 13/13.
- The configured live MainWP MCP reported the expected Dashboard and 84 abilities.
  Its basic directory returned 107 identities across two pages. This was directory
  metadata only: no individual-site runtime call, sync, backup or write. The live
  shape matches the adapter's tested protocol contract; hosted identity acceptance
  and the hosted service credential remain unconfigured.
- A read-only diagnostic using the new importer processed 27 existing Local Hub
  manifests into 50 environment URLs, with no source issues and no credential
  fields imported. No manifest was changed. These are saved intended facts, not
  proof of those environments' current runtime health.
- MRN staged task acceptance passed; the commit hook requires a matching proof
  for the final staged tree, and the PR Code gate checks the review snapshot.
  Direct JS lint and local
  protocol tests cover `.mjs`, which MRN QA's WordPress scanners do not classify.

PHP/WordPress API-runtime, public browser, axe accessibility, frontend performance,
parity and deployment-readiness rows are inapplicable to this Node backend change
and were not run against unrelated sites. No service was deployed. Production Hub
API enrichment, deployment-record imports and the other workflow adapters remain
documented gaps; automatic directory visibility grants no repair/release rights.

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
