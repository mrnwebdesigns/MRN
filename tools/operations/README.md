# MRN Website Operations MCP

Working first version of the shared MRN operations service. This is an HTTP MCP
backend for conversational clients, not a WordPress plugin or a new MainWP
implementation. It is **not yet hosted or team-ready**. Real writes default off.

## Team experience and acceptance

The team-facing product must be: **add the MRN plugin in ChatGPT,
sign in with an individual MRN account, and ask for website work in ordinary
language**. Team members must not need Codex, a terminal, a local repository,
manual MCP configuration, API keys or a personal background service. Existing
website access follows their account permissions; they do not register sites
again. Findings, progress, necessary approvals and verified results stay in chat.

Service hosting, credentials, source connections and operational administration
are MRN operator responsibilities. The operator setup below is not team
onboarding. ChatGPT is the selected platform. The portable plugin manifest and
package builder are in `chatgpt/`; per-tool OAuth declarations and reconnect
challenges are exposed by the service. The hosted endpoint, real MRN identity
provider, workspace publishing and ChatGPT acceptance remain outstanding. See
[ChatGPT delivery and setup](CHATGPT.md).

Acceptance requires an ordinary team member, using only ChatGPT, to
add the integration, sign in, discover permitted sites, request an inspection and
receive evidence and coverage in the same conversation. Wrong-account access,
revoked access and denied actions must be enforced by the hosted service. Before
claiming change workflows usable, repeat the authorized repair and verification
flow in a controlled environment through that same chat experience. Fixture
tests or developer configuration alone do not satisfy this acceptance.

Existing MainWP websites appear automatically for authorized team members. There
is no second website enrollment or required Operations website list. Existing
Local Hub records add explicit remote/local relationships and deployment facts;
missing facts remain unknown and are requested only when an action needs them.

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
| Discovery | Paginated MainWP basic directory, exact-URL grants, optional Local Hub record enrichment; no duplicate website list | Authenticated HTTP/stdio tests start from an empty registry. Live basic-directory response schema verified; directory identity is separate from fresh runtime inventory |
| Website knowledge | Exact-URL joins, explicit local/remote links, source/freshness, repository/provider facts, unknown/conflicting metadata | Reads a narrow allowlist from existing `.mrn-site.json` files; no credentials, SSH targets, notes or commands imported. Production Hub provider API and site deployment record adapters remain future work |
| Inspection | Runtime inventory, installed-plugin catalog comparison, qualification cases, capability coverage, public timing/basic HTML checks, history | Controlled fixtures; MainWP security/themes/updates/change queries are inventory only, not a comprehensive interpretation or security audit |
| Performance | Bounded public GET and prior sample comparison | Does not claim daily regression or root cause from isolated measurements |
| Repair and code rollback | One installed standard-plugin update through existing Fleet; exact retained rollback through MainWP | Controlled end-to-end backup/confirmation/runtime/public verification; no real site mutation |
| QA | Required trusted source/runtime qualification for execution; optional MRN QA runtime command | Adapter command contract tested; no client runtime QA invoked by this task |
| Assets | Qualified immutable same-origin URLs must appear in public HTML and served bytes must match checksums | Stale HTML, stale CSS/JS and bad REST responses tested. Qualify representative pages separately with existing asset/browser tooling |
| Locks/retries | SQLite transactions, one canonical URL lock across operation kinds, durable idempotency, no repeated mutations after uncertain outcomes | Concurrent stores and database reopen tested; external writers are not yet enrolled |
| Interrupted-operation recovery | `reconcile_operation` checks exact runtime and public assets under operator-enrolled quiescence evidence, then atomically records the outcome and releases the lock | Controlled lost-response, restart, concurrent recovery, late-worker and stale-evidence tests; no automatic retry, force unlock or retrospective backup claim |
| Forms | Explicit unrun coverage, recorded procedure references | Rendering/validation/submission/delivery/CRM/payment workflows remain unimplemented; no real form was submitted |
| Other repairs | Explanatory routing boundary | Source authoring, content/media migrations, provider repairs and arbitrary configuration changes are not implemented |
| Site releases / full Stack | Ownership preserved by rejecting unsupported routes | No GitHub dispatch, child-theme deployment, full Stack install or native Kinsta Fleet adapter in this version |
| Fleet assessment | 1–25 explicit accessible environments, per-site results | Assessment never authorizes updates; empty/all-site selection rejected |

REST API v2 is not used in v1: the implemented workflow is already exposed by
MainWP MCP. Missing capability, authentication and identity failures are reported;
there is no silent REST/SSH credential fallback. A future REST adapter must use
the existing approved MRN client and Production Hub's `MAINWP_REST_API_KEY`
contract, not the MCP application password.

A missing tool is an integration limitation, not proof that MainWP lacks the
product capability. Before admitting another route, assess both primary routes
and the relevant official extension's installed, active and usable state on the
intended Dashboard. UI-only extension workflows require their own qualified
adapter. They never bypass authentication, safe mode, authorization, backup or
verification gates. Recovery currently needs only the existing MCP read surface;
it does not introduce a REST, extension, UI or SSH fallback.

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

## Operator setup for the hosted service

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
4. Put reviewed service, permissions, qualifications and recovery configuration in
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
6. Enable `discovery` and point `localHubRoots` at read-only mounted copies of the
   existing `MRN-sites` parent directories (or use `[]` for MainWP only). The
   hosted service needs explicit source access; it does not inherit laptop files
   or chat connectors. MainWP names/URLs are discovered automatically. Local Hub
   manifests supply saved relationships and deployment facts. `registryPath` is
   optional and reserved for reviewed missing-fact corrections or operation
   prerequisites; `websites.example.json` is intentionally empty. Never copy the
   MainWP inventory into it. See [discovery and knowledge](DISCOVERY.md).
7. Assign immutable IdP subjects explicit grants. Actions are independent:
   `read`, `test`, `repair`, `deploy_development`, `release_production`. A normal
   repairer needs `read` + `repair`; production execution additionally needs
   `release_production`. `portfolio` can grant read/test access to MainWP and/or
   Local Hub sources, including future discovered sites, without listing each
   site. Repair/release grants remain explicit by exact URL or legacy website ID.
   A restricted URL grant uses exact-site discovery and never fetches the full
   directory. No default administrator or implicit role escalation exists.
   Removing a grant takes effect before the next downstream call.
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

Discovery alone needs no release artifacts, source checkouts, QA qualification,
writer-coordination record or backup route. Those prerequisites apply when the
requested inspection/test/change actually uses them. Unknown environment and
backup facts do not prevent read-only inspection; they cannot authorize a write.

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
“What happened to that interrupted repair?”
  get_operation -> operator quiescence evidence -> reconcile_operation
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
`uncertain`; the lock remains. A crash can leave `running` or `preparing` records.
Restart never resumes or retries those automatically. For `running`/`uncertain`
Fleet operations, [the recovery runbook](RECOVERY.md) describes operator-owned
quiescence evidence and the `reconcile_operation` workflow. It requires current
release permission, exact source/artifact QA, fresh targeted runtime evidence
before and after public/REST/asset checks, and an atomic unchanged-record/lock
check. An active worker, stale proof, changed target or unknown code keeps the
lock. A late worker cannot perform another downstream call or finish the old
operation after reconciliation. Infrastructure-level worker termination and
downstream-idle verification remain operator responsibilities.

The terminal `reconciled` state reports `intended_code_verified` or
`prior_code_verified` separately from the original execution error. It never
claims the earlier backup completed, attributes the installed code to the failed
request, or assesses database/media recovery. Repeated reconciliation returns the
saved result; repeated execution cannot replay the original mutation. A verified
reconciled update can enter normal code-rollback planning with fresh gates.
Unknown/mixed code, absent artifacts, changed baseline or unrelated drift require
operator investigation; there is no force-unlock tool. `preparing` records never
held a write lock: inspect again and prepare with a new request key.

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
