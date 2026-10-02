# MRN Website Operations MCP

Working first version of the shared MRN operations service. This is an HTTP MCP
backend for conversational clients, not a WordPress plugin or a new MainWP
implementation. It is **not yet hosted or team-ready**. Real writes default off.

A user can name an accessible website, inspect it, refer to a recorded finding,
prepare an installed standard-plugin repair, authorize its exact plan, execute
through Fleet, inspect verification evidence, and prepare code rollback.
The complete sequence is tested against controlled fixtures, including a real
stdio MCP child process and individually authenticated HTTP MCP clients.

## Existing capabilities and genuine gaps

The implementation was based on merged MRN source at `2c486af` and the following
existing contracts. No client site, component catalog or release lock was changed.

| Authority | Reused behavior / boundary |
| --- | --- |
| `stack/scripts/mrn-fleet-update.mjs` | Exact-site discovery/sync, installed standard-plugin selection, immutable package validation, controller preflight, safe mode, confirmation, verified Updraft backup, runtime readback |
| `stack/scripts/build-mainwp-stack-plugin-plan.py` | Clean source, exact merged commit, package/source-tree match, immutable baseline and retained rollback artifact; service relocates checkout paths via its explicit source map |
| Existing MainWP MCP pin `3249bde643f52f29b7b18db41f4c3c7bb88d2434` | Internal MCP client connects to this implementation; approved dashboard status, capability discovery, supported abilities; no replacement server |
| `stack/manifests/component-catalog.json`, `stack-plugin-releases.json`, release locks | Intended approved versions and source/artifact authority; not a mandate to install absent components |
| `tools/site-deploy`, `MRN-SITE-DEPLOYMENT-STANDARD.md` | Child-theme ownership, exact build artifacts, provider backups, retained releases, scoped HTML caches; controller currently restricts activation to qualified Dev/Updraft paths |
| `MRN-ASSET-RELEASE-STANDARD.md` | Content-hashed asset generations, synchronized minification, immutable URLs, preserved release assets and public checksum verification |
| `MRN-AGENT-OPERATING-CONTEXT.md`, concurrency policy, `AGENTS.md`, `stack/BACKUP_POLICY.md` | Authorization, backup-before-write, Local Hub exception, worktree isolation, ACF/content ownership, task acceptance versus promotion |
| MRN QA Engine | Optional registered-site read-only API, accessibility and performance runner, isolated temp directory and report digest |
| Production Hub / Local Hub | Authoritative provider/access references and local manifests. No secret values are imported into the website registry |

The gaps addressed here are shared identity/permissions, an environment registry,
evidenced findings, durable operations, exact-plan approval, request deduplication,
service-wide per-site locks, and an MCP interface over those services.

## What is implemented and what remains

| Capability | Implemented | Verification / limit |
| --- | --- | --- |
| Individual HTTP authentication | Issuer/JWKS/audience/expiry/algorithm/scope checks; no shared-user bypass | Real signed-token local HTTP tests; actual team identity provider not configured |
| Site/operation authorization | Explicit subject, site and environment grants, checked before every downstream call | Revocation, denied roles, inaccessible sites and cross-site requests tested |
| Discovery | Registry aliases and exact environment URLs; fresh MainWP site ID resolution | Real internal-client connection/status and all 84 capabilities verified; no live site inventory calls were made |
| Inspection | Runtime inventory, installed-plugin catalog comparison, qualification cases, capability coverage, public timing/basic HTML checks, history | Controlled fixtures; MainWP security/themes/updates/change queries are inventory only, not a comprehensive interpretation or security audit |
| Performance | Bounded public GET and prior sample comparison | Does not claim daily regression or root cause from isolated measurements |
| Repair and code rollback | One installed standard-plugin update through existing Fleet; exact retained rollback through MainWP | Controlled end-to-end backup/confirmation/runtime/public verification; no real site mutation |
| QA | Required trusted source/runtime qualification for execution; optional MRN QA runtime command | Adapter command contract tested; no client runtime QA invoked by this task |
| Assets | Qualified immutable same-origin URLs must appear in public HTML and served bytes must match checksums | Stale HTML, stale CSS/JS and bad REST responses tested. Qualify representative pages separately with existing asset/browser tooling |
| Locks/retries | SQLite transactions, one canonical URL lock across operation kinds, durable idempotency, no repeated mutations after uncertain outcomes | Concurrent stores and database reopen tested; external writers are not yet enrolled |
| Forms | Explicit unrun coverage, recorded procedure references | Rendering/validation/submission/delivery/CRM/payment workflows remain unimplemented; no real form was submitted |
| Other repairs | Explanatory routing boundary | Source authoring, content/media migrations, provider repairs and arbitrary configuration changes are not implemented |
| Site releases / full Stack | Ownership preserved by rejecting unsupported routes | No GitHub dispatch, child-theme deployment, full Stack install or native Kinsta Fleet adapter in this version |
| Fleet assessment | 1–25 explicit accessible environments, per-site results | Assessment never authorizes updates; empty/all-site selection rejected |

REST API v2 is not used in v1: the implemented workflow is already exposed by
MainWP MCP. Missing capability, authentication and identity failures are reported;
there is no silent REST/SSH credential fallback. A future REST adapter must use
the existing approved MRN client and Production Hub's `MAINWP_REST_API_KEY`
contract, not the MCP application password.

## Run and test

Requires Node 22.13+ (built-in SQLite), Python 3, Git and the MRN repository layout.
Use Node 22 LTS on the service host; SQLite may emit an experimental warning.

```bash
cd tools/operations
npm ci --ignore-scripts
npm test
MRN_OPERATIONS_CONFIG=/etc/mrn-operations/service.json npm run check:config
MRN_OPERATIONS_CONFIG=/etc/mrn-operations/service.json npm run check:mainwp
MRN_OPERATIONS_CONFIG=/etc/mrn-operations/service.json npm start
```

`check:mainwp` is an operator diagnostic: status/capability discovery only, no site
calls or mutations. It emits no credential values. Normal downstream calls always
run under an authenticated subject and the backend permission service.

The tests do not contact managed sites. The fixture artifacts intentionally use
simulated package bytes and an injected plan builder; the real adapter uses the
canonical Python builder. Existing Fleet and source-builder contract tests remain
the source of validation for ZIP/source/baseline construction.

## Hosted onboarding

1. Choose the service host and public hostname. The proposed
   `operations.mrnwebdesigns.com` is a placeholder, not a provisioned service.
   Select the MRN identity provider's issuer, JWKS endpoint and audience. It must
   support MCP-client OAuth authorization, PKCE and `mrn:operations` access tokens.
   This service is a protected resource, not an authorization server.
2. Install this reviewed MRN checkout and exact npm lockfile under
   `/opt/mrn-operations/MRN`. Use a dedicated `mrn-operations` OS account. Install
   the existing approved MainWP MCP pin with its own locked dependencies under
   `/opt/mainwp-mcp/<pin>`. Do not install a competing MainWP connector.
3. Provision a dedicated service credential through the MRN business approved
   secret service; no personal interactive login. Configure the five environment
   references in `service.example.json`. `OPS_MAINWP_URL` is exactly
   `https://wpcontrol.mrndev.io`; `OPS_MAINWP_SAFE_MODE` initially is `true`;
   `OPS_MAINWP_HOME` is the dedicated service home. Keep the application password
   in the service secret environment. Require user confirmation and disable
   downstream automatic retries. Never disable TLS verification. The service
   does not read Codex configuration or inherit a chat application's connectors.
4. Put reviewed copies of the four example configuration files in
   `/etc/mrn-operations`, owned by the operator and read-only to the service. Set
   paths, identity and policy explicitly. Store credential references only.
   Create the private MainWP working directory named in configuration. Systemd
   and reverse-proxy examples are in `deploy/`; neither was installed by this task.
5. Provision approved release artifacts and clean component repository mirrors.
   Map each `source.repository` name to its host checkout in `sourceRepositories`.
   Fetch its approved `origin/main` through the established MRN read-only GitHub
   credential path before qualification. The Python builder still enforces clean
   source, merged exact commits, package/tree checksums and rollback availability.
   The adapter never fetches arbitrary repositories or commits from chat inputs.
6. Enroll websites and environments through reviewed `websites.json` changes.
   Exact MainWP IDs are discovered, never stored as authority. Dedicated Dev
   environments may remain outside MainWP. Record management and backup routes,
   and facts for ownership, contacts, Stack adoption/exceptions, hosting, DNS,
   CDN/cache, monitoring, repositories/branches/release method, forms/integrations,
   test recipients/procedures, recovery and access references. Every fact requires
   source, observed/expiry timestamps and intended/observed classification.
   Runtime observations live separately in operation records. Updating a registry
   fact never changes a site's configuration or installs a component.
7. Assign immutable IdP subjects explicit grants. Actions are independent:
   `read`, `test`, `repair`, `deploy_development`, `release_production`. A normal
   repairer needs `read` + `repair`; production execution additionally needs
   `release_production`. No wildcard sites, default administrator or implicit
   role escalation exists. Removing a grant takes effect on the next call.
8. Verify real identity login, wrong-site denial, a controlled read-only site,
   artifact provisioning, storage recovery and service supervision. Keep
   `writesEnabled: false` until these pass. Add qualified source/runtime QA records
   for the exact site/environment/commit/artifact in `qualifications.json`; the
   schema is in `src/qualification.mjs`. Each record names its verifier, report
   references/checksums, freshness and frontend asset expectations. An attestation
   is a trusted release-admin record, not proof manufactured by the chat model.
9. Before enabling any writer, coordinate **all** routes for that environment.
   Existing Fleet CLI/MainWP UI and site Actions do not share this SQLite lock.
   Disable competing writers or enroll them in a reviewed shared lock adapter.
   Only then add `coordination: {exclusiveWriter: "mrn-operations", evidenceRef:
   "<reviewed-enrollment-evidence>", validUntil: "<expiry>"}`. The backend rejects
   execution without that enrollment and rejects changes to the registered target
   after approval. Service locks protect workflows admitted to this service, not
   independently running tools. This is an outstanding hosting acceptance gate.
10. Qualify a controlled environment's update and recovery with provider-correct
    backups. Updraft Fleet is the only write route currently admitted. Kinsta
    native backups are implemented in existing site tooling but are not connected
    to this Fleet adapter: it refuses that route. Local content/code workflows
    will use the documented Local Hub exception once implemented.

The identity provider, hosting destination, initial member/site grants, service
credential, repository/artifact provisioning and writer enrollment remain owner
setup decisions. Configuration and tests are ready for review; none of these
acceptance steps is implied by a passing code test.

## Conversation contract

The chat client supplies natural-language interpretation; it sees one MRN MCP
server. Deterministic services own authorization and workflow gates. There is no
embedded model with permission to execute arbitrary commands.

```text
“Inspect Example Client.”
  list_websites -> resolve exact environment -> inspect_website
“Fix that plugin finding.”
  prepare_repair(inspectionId, findingId, requestKey) -> concrete plan
“Publish this approved repair.”
  approve_operation(operationId, planDigest) using existing explicit approval
  -> execute_operation(operationId) -> get_operation(operationId)
“Undo that deployment.”
  prepare_rollback(operationId, requestKey) -> exact reviewed code plan
  -> approve_operation -> execute_operation -> get_operation
```

Plan approval is recorded separately from execution so existing authorization can
be reused without repeated chat prompts. The principal comes exclusively from the
verified bearer token, never a tool argument. The operation records requester,
approver, executor, target, source/artifact, recovery and verification. Frontend
clients cannot assert a backup receipt, bypass QA, replace a source SHA or clear
a lock. An artifact change requires a new plan. Database/media recovery is a
separate approved operation; code rollback makes no data-restoration promise.

## Durable operations and recovery

SQLite WAL/FULL transactions store operations, findings, request deduplication,
site locks and an allowlisted audit trail. State is outside the checkout, private
to the service account. Protect/backup the complete state directory using an
SQLite-consistent backup procedure and verify recovery before hosting acceptance.
The initial topology is one host and one service process. It is not a distributed
queue, replicated database or multi-region service.

Preparation and exact approvals do not mutate WordPress. Execution atomically
claims the canonical URL lock and returns an ID. Duplicate execution returns the
same record and cannot re-run the write. All operations for a site share that lock
regardless of Fleet versus future site/content/provider ownership. Provider and
child-controller checks remain additional gates, never replacements.

After a backup or mutation attempt, a missing/failed verification yields
`uncertain`; the lock remains indefinitely. A crash can leave `running` or
`preparing` records. Restart never resumes or retries those automatically. Stop
new admission, verify no downstream job is running, exact-readback site state,
inspect private receipts and perform a reviewed operator reconciliation. There is
no chat tool to force-unlock a site. An automated reconciliation/unlock workflow
is a remaining operational gap; do not label a restart as a recovered deployment.

Audit rows contain subject, operation, target, event, timestamp and outcome only.
Tool arguments, secrets, bearer/confirmation tokens and backup receipts are not
copied to the conversational record. Downstream credential-shaped metadata is
removed before Fleet persists its evidence. Raw stderr is suppressed; errors use
fixed classifications. Review trusted configuration and QA report retention as
part of enrollment; do not put secrets in facts or report references.

## Validation record

See [VALIDATION.md](VALIDATION.md) for the exact tests, live integration boundary,
MRN QA result and unrun hosting/runtime checks. The CI workflow runs controlled
service tests, existing Fleet regression and the locked dependency audit on PRs.
