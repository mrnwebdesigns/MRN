# MRN Website Operations MCP

The shared MRN HTTP MCP backend is hosted at `https://operations.mrnwebdesigns.com/mcp` and published as one ChatGPT workspace plugin. The deployed version supports read-only inspection. This source contains the unshipped unified integration; content/code/team acceptance is not complete, and writes remain disabled live. See [current integration and open gates](UNIFIED-INTEGRATION.md).

## Team experience and acceptance

The team-facing product must be: **add the MRN plugin in ChatGPT,
sign in with an active MRN Google Workspace account, and ask for website work in ordinary
language**. Team members must not need Codex, a terminal, a local repository,
manual MCP configuration, API keys or a personal background service. Existing
website access follows their account permissions; they do not register sites
again. Findings, progress, necessary approvals and verified results stay in chat.

Service hosting, credentials, source connections and operational administration
are MRN operator responsibilities. The operator setup below is not team
onboarding. ChatGPT is the selected platform. The portable plugin manifest and
package builder are in `chatgpt/`; per-tool OAuth declarations and reconnect
challenges are exposed by the service. The hosted endpoint, existing identity provider and workspace plugin are already in use. The new Google-only delegation and change workflows require their own deployment and acceptance. See
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

The service reuses the following MRN contracts. No client site, component catalog or release lock was changed.

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
| Individual HTTP authentication | Issuer/JWKS/audience/expiry/algorithm/scope checks; no shared-user bypass | Existing Auth0 login is live; new Google-only connection binding, active Directory checks and signed delegation are fixture-tested but unshipped |
| Site/operation authorization | Automatic active-MRN Google portfolio access, explicit revocation overrides and separate qualified operation roles, checked before every downstream call | Revocation, denied roles, inaccessible sites and cross-site requests tested |
| Discovery | Paginated MainWP basic directory, exact-URL grants, optional Local Hub record enrichment; no duplicate website list | Authenticated HTTP/stdio tests start from an empty registry. Live basic-directory response schema verified; directory identity is separate from fresh runtime inventory |
| Website knowledge | Exact-URL joins, explicit local/remote links, source/freshness, repository/provider facts, unknown/conflicting metadata | Reads a narrow allowlist from existing `.mrn-site.json` files; no credentials, SSH targets, notes or commands imported. Production Hub provider API and site deployment record adapters remain future work |
| Inspection | Runtime inventory, installed-plugin catalog comparison, qualification cases, named pending updates with installed/available versions and separate type totals, public checks and history | Update names, versions and types are allowlisted; raw package URLs and credentials are excluded. Unknown, inconsistent or truncated details remain explicit. Availability is not compatibility or authorization; security/themes/change queries remain limited inventory |
| Performance | Bounded public GET and prior sample comparison | Does not claim daily regression or root cause from isolated measurements |
| Repair and code rollback | One installed standard-plugin update through existing Fleet; exact retained rollback through MainWP | Controlled end-to-end backup/confirmation/runtime/public verification; no real site mutation |
| QA | Required trusted source/runtime qualification for execution; optional MRN QA runtime command | Adapter command contract tested; no client runtime QA invoked by this task |
| Assets | Qualified immutable same-origin URLs must appear in public HTML and served bytes must match checksums | Stale HTML, stale CSS/JS and bad REST responses tested. Qualify representative pages separately with existing asset/browser tooling |
| Locks/retries | SQLite transactions, one canonical URL lock across operation kinds, durable idempotency, no repeated mutations after uncertain outcomes | Concurrent stores and database reopen tested; external writers are not yet enrolled |
| Interrupted-operation recovery | `reconcile_operation` checks exact runtime and public assets under operator-enrolled quiescence evidence, then atomically records the outcome and releases the lock | Controlled lost-response, restart, concurrent recovery, late-worker and stale-evidence tests; no automatic retry, force unlock or retrospective backup claim |
| Forms | Explicit unrun coverage, recorded procedure references | Rendering/validation/submission/delivery/CRM/payment workflows remain unimplemented; no real form was submitted |
| Content | Native posts/pages reads plus typed published-record preview, protected QA binding, approval, backup, exact public HTML/data readback and rollback in source | Protected runner is implemented but runtime/live-profile qualification, typed ACF/menu/media and real ChatGPT acceptance remain open |
| Guarded code | Exact preview, central source PR/merged commit, protected QA, approval, backup, apply/readback and exact rollback | Controlled snapshot and Git lifecycle tests pass; central App provisioning, isolated provider/runtime and live ChatGPT acceptance remain open |
| Other repairs | Explicit integration boundaries | Arbitrary configuration/provider changes remain outside qualified adapters |
| Site releases / full Stack | Ownership preserved by rejecting unsupported routes | Guarded code uses protected GitHub QA dispatch; full site/Stack installation and native Kinsta Fleet remain separate unsupported adapters |
| Fleet assessment | 1–25 explicit accessible environments, per-site results | Assessment never authorizes updates; empty/all-site selection rejected |

The native post/page reader uses the official MainWP REST v2 routes with the centrally protected `MAINWP_REST_API_KEY` supplied through Production Hub. This is an explicit read adapter, not an authentication fallback. Mutating code uses the canonical MCP provider with request-bound Google delegation. Missing capabilities or failed authentication stop the workflow; no REST/SSH or credential fallback is attempted.

A missing tool is an integration limitation, not proof that MainWP lacks the
product capability. Before admitting another route, assess both primary routes
and the relevant official extension's installed, active and usable state on the
intended Dashboard. UI-only extension workflows require their own qualified
adapter. They never bypass authentication, safe mode, authorization, backup or
verification gates. Recovery currently needs only the existing MCP read surface;
it does not introduce a REST, extension, UI or SSH fallback.

## Run and test

Requires Node 22.13+ (built-in SQLite), Python 3, Git and the MRN repository layout.
The selected MRN Apps host has Node 24; SQLite may emit an experimental warning.

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

CI also runs `test/runtime/check-capture-transport.mjs` through the unmodified,
pinned MainWP MCP subprocess and SDK over authenticated loopback HTTPS. It
verifies an exact 4,400,000-byte synthetic capture artifact, refusal of responses
over the existing 10 MiB HTTP limit, incomplete JSON, header/body timeouts and
zero retries. A response delayed beyond the default 30-second upstream timeout
passes with an explicit bounded 150-second upstream deadline. The SDK call
deadline alone does not configure that HTTP timeout.

This is transport evidence only: its inventory is synthetic and does not prove
provider capture, parity eligibility, a protected qualification worker, or team
acceptance. The protected content QA runner exists; automatic qualification
dispatch and its authenticated capture connection remain unfinished. No
production setting or consumer authority is enabled by this test.

## Operator setup for the hosted service

1. Reuse the existing DigitalOcean MRN Apps service at
   `https://operations.mrnwebdesigns.com/mcp` and its Auth0 issuer, JWKS and audience.
   OAuth uses PKCE and `mrn:operations` for reads; the unified candidate requires
   additional `mrn:operations:write` consent for guarded changes. The new scope's
   live grant and workspace metadata update remain a concrete access-review gate;
   see `deploy/TEAM-CONFIGURATION-REVIEW.md`.
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
7. Use the automatic active-MRN Google domain policy in `config/permissions.team.example.json`; do not enroll or approve individual staff. Enable `auth.googleWorkspace` and request delegation together, with the verified connection/customer/service-key policy. Explicit subject overrides are reserved for revocation or narrower exceptions. Legacy Fleet grants remain separate until its portfolio write adapter is qualified. The earlier read-only deployment uses an `emailDomains` policy with `auth.verifiedEmailClaims: true`. Only
   signed `${audience}/email` and boolean `${audience}/email_verified` claims
   qualify, after full signature/issuer/audience validation. Match the exact
   domain, never a suffix or subdomain. Explicit subject entries override domain
   access, including `enabled: false` revocation. Domain policies can grant guarded `content` and `code` roles only with verified Google identity; legacy Fleet repair/release grants remain separate. See [MRN Apps staff policy](deploy/MRN-APPS.md).
   Actions are independent:
   `read`, `test`, `content`, `code`, `repair`, `deploy_development`, `release_production`. A normal
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
    backups. Updraft Fleet and qualified provider code operations have separate adapters. Kinsta
    native backups are implemented in existing site tooling but are not connected
    to this Fleet adapter: it refuses that route. Local content/code workflows
    will use the documented Local Hub exception once implemented.

The MRN Apps host, Auth0 identity provider, centralized MainWP credentials and staff read policy are deployed. The unified source candidate has not been deployed; its Google-only delegation, source service, content/code workflows and real ChatGPT acceptance remain delivery work. Artifact provisioning and writer
coordination apply later when qualifying executable changes. Passing code tests
does not establish any of these live acceptance steps.

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

The private content authorization callback is disabled by default. An explicitly reviewed `contentAccess: true` service setting requires the existing Operations origin, Google delegation and content workflow configuration. Startup validates a separate MainWP control connection before listening and closes it on setup failure or shutdown. This setting supplies only the callback lifecycle; it does not provision provider authority, admit qualification jobs, activate a protected worker or establish team acceptance. Activation remains part of the concrete deployment review.

The private source broker is prepared source only. `ManagementSourceBroker`
reuses `ManagementWorkerContext` to verify the exact original protected GitHub
OIDC run and recheck the original Google staff member before and after source
reads. It binds one ephemeral supervisor public key per recorded dispatch in
SQLite, retains spent nonces and failed attempts across restarts, and signs
observations of encrypted actual bytes. Worker input cannot choose an email,
site, catalog, backend route, repository, run or source path. The fixed backend
read uses `mrn_mainwp__read_content_edit_qualification_source_v1`, with a separate
registrar key and the provider's frozen `mrn-content-management-source-read-v1:`
purpose. Existing identity signing and backend credentials remain service-owned.

The provider source contract is byte-pinned at commit
`c49b88be6242a7012587df5d93cba3a89c587841`, SHA-256
`36320f6d2d5dac5523ecc68b0fa463c2fa1246cbf75f81e3209c055aa196f3e9`.
The independent supervisor decoder verifies the signed transcript and decrypts
X25519/HKDF-SHA256/AES-256-GCM batches. HKDF info is the fixed
`mrn-content-management-source-broker-aead-v1:` prefix plus the SHA-256 of
canonical header bytes, the recipient UUID is its salt, and full canonical
header bytes are authenticated as GCM associated data. Separate recipient and
batch signature purposes prevent reuse of ordinary context signatures. Batches
retain the provider's 16-frame/32-KiB geometry and 1-MiB plaintext bound; encrypted
responses have their own 2-MiB ceiling, without widening existing endpoints.

Optional source HTTP handlers have separate 5-second recipient and 20-second
batch deadlines, two concurrent slots and 96 requests/minute. Production startup
provides no source broker, so both routes remain absent there. The required
`resolveSource` service adapter must obtain an authenticated retained original
capture and its independently verified current pre-queue owner catalog pin.
That adapter, provider capture/controller activation, durable capture custody,
approved opaque central cache registry/population and full source assembly are
still integration blockers. There is no caller-controlled installation method
or fallback credential path. Unit and cross-repository codec fixtures do not
prove live source capture, Google/OIDC acceptance, rendered content QA or team
readiness. No content write or code policy is activated by these modules.

`ManagementSourceCacheRegistry` adds a closed, source-only owner registry for
opaque catalog artifact IDs. It defaults to missing-source denial, and accepts
only the fixed `mrnwebdesigns/mrn-code-qa-runner` central repository with pinned
numeric repository/artifact/run identity, source commit, archive hash and expiry.
Registry/catalog changes, duplicates, unknown IDs and stale entries fail before
credential acquisition. It reuses the existing exact-repository GitHub App grant
and requests only its existing `qaRead` permission profile; it cannot grant App
permissions or fall back to a personal token.

The separate cache downloader verifies original artifact metadata and streams
actual bytes to an exclusive private temporary file in at most 60 seconds under
the original request expiry. It enforces the catalog archive bound (at most
512 MiB), verifies the actual archive hash, removes partial files on failure, schedules removal at original expiry and
never forwards authorization to GitHub's storage redirect. This does not widen
receipt/evidence limits or extract/execute any ZIP. Complete inventory and source
verification flags remain false until the protected supervisor independently
validates all archive paths and bytes against the complete approved root.

No registry is populated or activated. A read-only attempt to inspect App
installations through the existing CLI returned 403 because that token cannot
list installations; this neither proves nor grants the App's artifact-read
permission. Live App access, owner-signed catalog population, safe supervisor
extraction and encrypted cache delivery remain unverified integration work.
