# Operations validation

## Unified Google delegation and guarded code integration — 2026-10-14

This is an unshipped integration candidate. The existing workspace plugin and live service are unchanged. Active MRN Google staff use automatic domain/customer membership; there is no named-user approval or enrollment step.

The new `provider-transport` CI job uses immutable provider/MCP commits in
`test/runtime/sources.json`, the provider's disposable WordPress/MainWP HTTPS
harness and the gateway's actual JWT, delegation, HTTP MCP and SQLite audit
code. Its synthetic OAuth/Directory checks cover concurrent staff reads,
outsider and revoked/suspended denials, read-only scope enforcement, persistent
nonce replay denial and raw service-route denial. The MCP subprocess trusts only
the generated fixture CA and routes the Dashboard hostname only to the fixture's
private address; TLS verification remains enabled. It never loads local MainWP
configuration or live credentials. Only a bounded pass report is retained, not
credentials, signed envelopes, requests or private runtime files. At source
`2929578`, [the first CI attempt](https://github.com/mrnwebdesigns/MRN/actions/runs/38591395905/job/115947138549)
stopped before provider checkout: the existing MRN CI Standalone Repos App
received HTTP 404 for that repository's installation lookup. No runtime check
ran in that attempt. The controlled-contracts and Code gate jobs passed at that
exact source. On 2026-10-14 the owner explicitly approved adding the provider to
the existing read-only CI App. Saved readback verified 29 repository selections,
preserving all prior 28, and unchanged read-only code/metadata permissions. The
[rerun at 57d61e4](https://github.com/mrnwebdesigns/MRN/actions/runs/38592055915/job/115951238897)
passed source-token creation, both pinned checkouts and builds. Its old provider
pin passed 34 HTTP, 23 content and 13 parity checks; the job then failed because
that pin has no gateway hook and the mandatory gateway report was absent. No
alternative credential was used. That integration checkpoint pinned provider
`27ac5bc1371db01db7c0d5cf5c418ef776e23e1e`, including the CI-only hook. Its
[complete CI](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38593325241)
passed 34 native HTTP, 26 content, 13 parity, 12 profile-context and 25 controller
lifecycle checks. The controller suite uses real WordPress/MySQL and signed
individual delegation but a synthetic MainWP child seam, QA signer and backup
receipt; it does not prove real child transport, protected-runner QA or remote
backup creation. The fix compares retained selector objects canonically, with a
reordered-key regression, without weakening exact target or snapshot checks.
Gateway source `a6eccc20bb3149cc6557f33e8a7041a28272776d` then passed all ten
actual gateway/MCP/WordPress transport cases in [run 38593686429](https://github.com/mrnwebdesigns/MRN/actions/runs/38593686429/job/115955067868),
alongside controlled contracts and the [Code gate](https://github.com/mrnwebdesigns/MRN/actions/runs/38593686491).
The retained bounded report pins provider `27ac5bc` and MCP `3249bde`, and explicitly
records `productionCredentials: false`, `liveGoogleVerified: false`,
`chatgptAcceptanceVerified: false`, `managedSites: 0`, `contentWriteVerified: false`
and `codeWriteVerified: false`. Its ten checks cover the identities, denials,
nonce/route enforcement and private audit described above. The empty synthetic
portfolio does not prove real-site discovery, content/code writes or ChatGPT
acceptance. Documentation checkpoint `a0f8521` also passed all Operations CI checks and the Code gate at the unchanged runtime source.

Gateway source `a3b55ea888ff0a4c92834bf74ea9fd5655aca850` adds the actual
MainWP Child lifecycle through HTTP MCP and pins provider
`e615e988cade54f2ceb3493e561094341c91c053`. The provider's own
[CI run 38608084539](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38608084539)
passes native content edit, exact rollback and hook/source refusal checks.
The combined [gateway run 38608469036](https://github.com/mrnwebdesigns/MRN/actions/runs/38608469036)
passes controlled contracts and the ten authorization transport cases, then
discovers the one real disposable Child. It fails before any gateway content
mutation: delegated `get-site-v1` rejects the exact URL because the provider's
target guard only accepts numeric IDs. The gateway retains URL resolution,
one-site sync and fresh exact readback; substituting a cached numeric ID is not
an accepted fix. The [Code gate](https://github.com/mrnwebdesigns/MRN/actions/runs/38608469081)
passes at this same source. Native gateway content acceptance remains failed.

Provider fix `aeb0f2cfe1d7496e338845ff0c810e206a45a1b0` passes
[all four CI jobs](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38611997072),
including real exact-URL resolution, one-site numeric sync and fresh exact URL,
ID, connection and timestamp readback. URL reads bind one current authorized
record and recheck the returned identity; malformed/alias/ambiguous/disconnected
targets and response drift are denied. Sync and mutations stay numeric-only.
Gateway `b7851e1215b4a661eab32602e7de0171e095c99a` pins that fix and passes
controlled contracts and the [Code gate](https://github.com/mrnwebdesigns/MRN/actions/runs/38613140903).
Its [combined run 38613140657](https://github.com/mrnwebdesigns/MRN/actions/runs/38613140657)
passes the ten authorization cases, native provider checks and initial exact URL
resolution, but fails the same-URL readback after HTTP sync. The sync route
returns HTTP 200; the following read returns `mrn_delegation_target`. No gateway
content mutation ran. HTTP-originated child sync remains unqualified even though
the provider's CLI-context native sequence passes.

The gateway now checks the canonical sync body as well as subsequent freshness:
no queue, one synchronized record matching the resolved ID/normalized URL, zero
errors and consistent counts. A failed or malformed sync returns fixed
`SYNC_UNVERIFIED` without reflecting provider messages. Two new tests reject
failed/queued/malformed/wrong-site/multiple-site results even with a fresh
timestamp, and retain valid normalized URL plus fresh-readback behavior. All 234
controlled tests and direct lint pass. A transport timeout still permits only
the existing one exact freshness readback, never a sync retry or fallback.

Provider `47de8802681450bd07f0b3610b02919531135601` passes
[all four jobs](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38616458943),
including the actual delegated HTTP fresh-sync sequence. Metadata-only probes
confirmed root-owned signing files blocked the web user. The disposable fixture
now assigns only its generated MainWP data to that user and creates private keys
with a private process mask, preserving 0700 directories and 0600 keys. No vendor
code, TLS verification, credential or production setting changed.
Gateway `9039d17` pins that fix. Its [combined run](https://github.com/mrnwebdesigns/MRN/actions/runs/38616849742)
passes discovery/fresh sync, native inspection and exact policy, unchanged
preparation and denial checks, then marks the approved execution uncertain.
The gateway expected a null error code where the provider uses an empty string
on success; it also incorrectly required rollback availability on reverse plans.
The fix admits only null/empty success codes and requires the exact forward versus
reverse rollback flag. Every state/site/target/hash/migration/readback gate remains.
The fixture now mirrors native output; failed or inconsistent outcomes retain
their locks and never retry. All 237 controlled tests pass.

Gateway `7105c1582e6f8bfde7f6ec726d9cf17dfe0d7c5b` then passed
[complete Operations CI](https://github.com/mrnwebdesigns/MRN/actions/runs/38617704685)
and the [Code gate](https://github.com/mrnwebdesigns/MRN/actions/runs/38617704678).
All ten identity/authorization transport cases and all six native content groups
pass: exact discovery/sync/snapshot/policy, unchanged preparation, approval and
revocation refusals, actual approved Child page-title edit with public HTML and provider
status, separately approved exact rollback, and verified-individual audit.
Artifact `11919998889` was downloaded and checked; archive digest is
`c601a99d7b1a8f8fbbe4173f8035ce8f871f80bb22b663a4ddb85d6e6c407f54`.
Its native report digest is
`db9421da5b2279c4cc6ea8e1366f30f3f4b4c46ef4b771a5331eea8cd6bd247e`;
the authorization report digest is
`051b8f0f25b0a0ce110e7ce398bdaa0b095fc7eee0c6c6c9b9cbc9c7bf81537a`.
The immutable sources are provider `47de880` and canonical MCP `3249bde`.
This closes the demonstrated disposable gateway content transport lifecycle,
not real-site rendering qualification, code editing or team acceptance.

Consumer preparation extracts the existing closed content-schema compiler and
adds bounded, acyclic local definition references plus semantic unique-item
checks. External/unknown/cyclic references, unknown schema vocabulary, open
objects, duplicate records, malformed Unicode and unsafe integers are rejected.
All 242 controlled tests and direct lint pass with the unchanged frozen v1/v2
wire. A read-only probe compiles the provider's 30 staged definitions and accepts
its six structural vectors; it is not an immutable contract acceptance or a
signature/lifecycle test. No new provider schema is copied into the gateway, no
qualification endpoint is enabled, and static profile trust is unchanged. The
cross-consumer target binding still needs explicit signed claims, purpose,
audiences, revision and expiry semantics before consumer wiring.

The corrected provider contract checkpoint `402991371ce0d9f258af60319c85b1848a7fa40a`
passes [all four CI jobs](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38621727078).
The gateway retains exact qualification, recipe and target-binding schemas plus
independent canonical structural vectors with source-blob SHA-256 checksums.
All 41 definitions compile and all eight vectors match; all 246 gateway tests
and direct lint pass. The existing v1 bytes remain identical. The existing v2
copy differs only in descriptive metadata; its schema, ability, role, wrapper,
input and output match the checksum-pinned upstream document exactly.

The initial qualification status requires `qualification_binding: null` and
provider-only acceptance. Queue metadata, protected worker context, expiry and
current revision/profile/binding hashes are explicit; replaced or revoked
historical jobs cannot imply current acceptance. The separate binding contract
has its own purpose, all consumer audiences, actual published revision and
bounded expiry. New-target consumer trust requires a separately versioned output
and accepted issuance/verification. These files do not enable queue dispatch,
registrar runtime, signed-profile trust, new site eligibility or staff access.
The integration fixture now pins `4029913`; its gateway rerun remains a separate
check from the provider's passing contract/runtime CI.

The newer provider foundation `792226abd7b3ed28532b661aca385f0d917a81c3` passes
[all four CI jobs](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38638976550).
Its disposable harness installs the committed runtime ZIP, including the nine
required contracts and generated source manifest. Recorded results are 25 native
content lifecycle checks, 44 actual WordPress/InnoDB catalog-storage checks with
two independent publishers and one winner, 253 contract checks, and 17 package
and extracted-source tests. Stale negative-cache overwrites, stale lock owners,
successor deletion, target revision races and capacity overflow are rejected.
Source readback rejects missing, extra, altered or linked package inputs and a
forged source claim against an independent expected commit. Original frozen
contracts remain byte-identical. The gateway now pins this exact source and
retains both additive contracts plus the full capture vector; 43 definitions and
nine canonical vectors validate. The new owner-only wire test rejects caller
identity, paths, URLs, private keys and activation fields; it does not prove
owner authorization or worker timing at runtime. No new provider control/job
ability, target binding or production consumer trust is enabled. The combined
gateway test against this packaged checkpoint remains a separate required gate.

The internal recipe-catalog verifier adds 11 controlled tests covering distinct
root/registrar/identity/QA keys, purpose-bound Ed25519 signatures, exact canonical
JSON/base64, duplicate fields, payload bounds, approved digest/epoch, date
boundaries, all three audiences, unique sorted recipe IDs and eligibility,
independent source/runtime/assertion pins, and reserved-origin/field restrictions.
These use the accepted provider's structural catalog vector and ephemeral local
signing keys. They prove verifier behavior only. No application workflow imports
the module, no target binding is accepted, and static target inventories remain
authoritative. Catalog installation and worker preflight/handoff have reviewed
additive source contracts but no gateway runtime or live activation evidence.

The passing six-group lifecycle harness requires exact snapshot and public HTML
verification after the approved edit and separate rollback, with individual
audit attribution and approval/revocation refusals. Only the two backup calls
are substituted with explicitly synthetic provider-consumable receipts; QA
uses a synthetic signer while exercising the real receipt verifier. OAuth and
Directory are synthetic too. Private credentials, receipts and fixture files
are never uploaded. Both retained reports explicitly state that protected QA, remote backup
creation, live Google, code editing and ChatGPT acceptance remain unverified.

- Verified canonical MainWP status on wpcontrol.mrndev.io: connected, 107 provider abilities, 52 exposed by a fresh configured connection at 15:06 UTC (previously 48). The abilities resource is policy-filtered too; the unexposed 55 are not enumerated or qualified. Last verified live provider 0.13.4 retains the company-only code policy and QA scope. Native REST post/page reads succeeded using the separately authorized Production Hub bearer lookup; no site write was attempted.
- Added per-request Google identity delegation, strict cross-language signed claims, POST-only metadata checks, active-membership authorization, audit identity migration, expanded portfolio/site reads, exact native content reads, code inspection, central GitHub source handling and protected QA/code lifecycle adapters.
- Full Node 24 suite passed 258 checks. Tests include signature/identity forgery, outsider and revoked denial, request isolation, unsafe GET metadata rejection, live-state drift, backup/approval gates, exact snapshot edit/rollback, pre-PR QA versus merged-source QA and live approvals, rejection before source publication on failed preliminary QA or disabled writes, lost response lock retention, bounded receipt extraction and token narrowing. Site-directory reads require portfolio access; ignored-update reads reject empty, different-site or expanded selectors. Read-only OAuth consent cannot authorize the eight change-lifecycle tools. These are controlled integration fixtures, not ordinary-member ChatGPT acceptance.
- Configuration preflight and startup use the same QA-policy loader before opening SQLite state. Fourteen CLI/loader regressions cover legacy read-only, code-only, content-only and combined configurations; missing, malformed and interchanged policy files; central QA/source repository pins; bounded non-secret output; and rejection before state creation. Snapshot workflows require no source repository. This is static configuration validation, not credential, hosted-runtime or live Google acceptance.
- Direct ESLint passed. The staged MRN task QA gate is required for the final index. A broader MRN QA invocation resolved the parent monorepo and reported static Pass, with PHPStan missing-stub advisory; it skipped service ESLint/npm rows because no root package exists. Its report is not a substitute for direct Node tests/lint or live provider/runtime checks.
- There is no new browser UI in the gateway. Its hosted WordPress/API, browser, accessibility and performance acceptance remains unverified; the separate disposable provider/runner evidence below does not establish live gateway acceptance. Central service credentials, runner/App provisioning, automatic access policy, workspace tool refresh and deployment are pending concrete approval gates.
- Typed content gateway tests cover exact before/candidate/migration/descriptor hashes, the PHP public signing vector, separate content QA signatures, normal and rollback paths, revoked membership during backup, concurrent approval, stale public content and lost-response lock retention. Protected runner source `6551079` passed [disposable runtime CI](https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/38581916586): actual HTTP dependency parity before/after QA, page/title/post/rollback, mandatory API/browser/accessibility/performance checks, and invalid-candidate/cross-record-parity denial. The later native gateway proof above qualifies disposable Child transport only; real-site profiles remain blocked. No production content profile is implied.
- Runner helper update `0fd0ff7` pins provider `27ac5bc` and SHA-256 `66110c269fe347648885fd12bce178e4ec73bc476dfdae80a4166064e15389ba`. Its 41 tests and staged PHP lint/WPCS/compatibility/PHPStan/secrets checks passed. [Run 38593836989, attempt 2](https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/38593836989/attempts/2) passed contracts and both disposable isolation jobs, including required report uploads. The owner-approved $5/month Actions budget, with cutoff and alerts preserved, resolved the initial artifact-quota failure. Downloaded artifacts `11910930605` and `11909304607` bind the same source and contain four code and four content QA reports plus two independent HTTP parity manifests. All eight reports pass mandatory API runtime, browser, accessibility and performance checks; code edit/rollback and failed-candidate denial pass, and content/title/post/rollback plus unsafe-HTML, unnormalized-title, stale-render, wrong-parity and cross-record-baseline rejection pass. The page/post parity digests remain distinct (`38dcf1b6…` / `6b4a0b45…`). Inapplicable static rows and remote Fleet inventory/license/rollout checks remain explicitly skipped within these isolated fixture profiles; they are not production release coverage. No evidence, retention setting or report gate was changed. Live provider transport and real-site rendering qualification remain separate gates.
- Native REST content reads repeat live staff authorization after both identity and content responses, and again at the service return boundary. Controlled revocation during identity resolution, record retrieval, search and service completion discards the result without retrying. Fixed allowlisted MainWP error mappings preserve content/profile/QA/backup/lock/uncertainty and Directory blockers through the pinned MCP transport, without reflecting upstream messages or credentials. Safe Mode remains a hard stop; uncertain outcomes never claim that no mutation occurred.
- The existing read-only `get_operation` tool can explicitly refresh retained code/content provider metadata. Controlled tests include actual HTTP tool dispatch, exact plan/site/target/hash checks, revocation before/after dispatch, bounded output and the unchanged local lock/status even when the provider reports completion or owner-side resolution. The read does not inspect current child state, retry a mutation, reconcile an operation or authorize rollback. Real hosted-provider status acceptance remains open.
- The gateway's `content-policy-v2.json` matches the provider proposal's input/output/role/wrapper exactly. Capability-negotiated target-aware lookup passes tests for two distinct records/fields on one site, a closed required tuple, exact signed read delegation, legacy-only-when-absent behavior, no downgrade after denial/malformed/null qualification, wrong-profile rejection before preparation/QA and the unchanged v1 edit/rollback lifecycle. No generic v2 ability or profile-registration write is admitted. Provider `27ac5bc` additionally passed v2 exact-field selection and v1 ambiguity denial in its disposable controller suite. Service-owned catalog registration, actual child transport, real-site qualification and hosted acceptance remain open.
- Native content edits, typed ACF/menu/media edits, ordinary staff ChatGPT edit/rollback acceptance and live outsider/revoked-account denial remain unverified. Do not call the product complete.

## PR #256 source integration and hosted readback — 2026-10-13

- Refreshed `main` at `0d5040f09b814994a5c4199fcb6cc670d5051c33` and updated
  the existing PR branch with GitHub's normal merge update. The original
  implementation commit `a41d1ba7ce2374fed10f110e7682eacaf4f2b49d` remains in
  history. Main introduced no competing Operations change; the PR still changes
  only `tools/operations`. Existing worktrees and unrelated changes were preserved.
- All 138 Operations tests and direct ESLint passed on local Node 26.0.0;
  the locked production dependency audit reported zero vulnerabilities.
  Changed-file MRN QA passed. The staged commit gate also requires its matching
  immutable-index proof. GitHub's `controlled-contracts` and required `Code gate` must pass for
  the final PR head before merge. No local Stack/plugin qualification or Fleet
  release is part of this task. WordPress PHP/API-runtime, frontend browser,
  axe and performance checks are inapplicable to this backend reporting change;
  authenticated HTTP/stdio and saved-result/history contracts cover its runtime.
- Read-only inspection of the existing `mrn-proposals-admin` connection
  confirmed host `MRN-Apps`, active/enabled `mrn-operations`, deployed revision
  `a41d1ba7ce2374fed10f110e7682eacaf4f2b49d` and `writesEnabled: false`.
  All 55 tracked Operations files matched that revision byte-for-byte. Loopback
  health with the expected Host header, public health and OAuth resource metadata
  returned 200; anonymous MCP returned 401.
- The persisted acceptance inspection
  `2a9926b3-725a-4e7d-be57-bec326a2b738` still contains nine named AmishMike
  plugin rows with installed/available versions and complete inventory coverage,
  with zero core/theme/translation/unknown updates. This reads the October 12
  inspection; it is not a fresh client-site inventory. No new site inspection,
  sync, backup, update or settings write was performed.
- The retained deployment backup remains present at
  `/var/backups/mrn-operations/a41d1ba-20261012T161135Z`; its database SHA-256
  still matches `408bfd874b353b881a7af2b88e7f2b30145f063fb0876eb83aec4703b3d96aed`.
  The October 12 isolated-restore evidence remains historical qualification;
  this task did not perform another restore or replace live state. Scheduled
  off-host backups remain an outstanding operational item.
- Source integration does not redeploy the hosted service. Any subsequent
  hosted deployment requires exact-target authorization and newly verified
  backup/recovery gates. Client-site writes are outside this task's scope.

## Named update inventory — 2026-10-12

- A staff member successfully used the installed plugin, as reported by the
  owner, but asking for pending plugins returned only an update count. The
  MainWP response contained the names and versions; Operations discarded them.
  Inspection now retains a bounded allowlist of type, name, slug and
  installed/available version pairs, with separate type totals and explicit
  incomplete/truncated coverage. No package URL, credential or raw plugin payload
  is retained. Saved inspections/history preserve the same sanitized detail.
- Live read-only discovery verified `mainwp://status` against the intended
  Dashboard with 95 abilities. Exact resolution found
  `https://www.amishmike.com/`; one-site synchronization/readback completed at
  `2026-10-12T15:47:18Z`. MainWP returned nine plugin updates and zero core, theme
  or translation updates, including installed and available versions for each.
  No WordPress update, backup, installation or settings change was performed.
- All 138 Operations tests, 13 current Fleet contracts, direct ESLint and diff
  checks pass locally. New coverage includes the nine-plugin scenario with
  public REST 403 and missing Stack agent, mixed/empty/malformed inventories,
  missing fields, inconsistent totals, output limits, wrong-site rows/envelopes,
  revocation, saved evidence and actual HTTP tool output with secret exclusion.
- This is backend-only. WordPress PHP/API-runtime, frontend axe/performance and
  Stack parity suites are inapplicable; no unrelated site is a QA target. The
  applicable staged MRN task gate is required before commit. This source change
  does not enable website-changing tools or alter access policy.
- Hosted deployment and real ChatGPT verification are separate acceptance
  checks, recorded on the follow-up PR. Initial MRN Apps SSH preflight was
  rejected after the 1Password agent failed to sign; no alternate credential or
  access route was attempted and no live service changed during that attempt.

## Inspection evidence correction and login renewal — 2026-10-08

- The initial pilot's REST verdict was invalid: the probe attempted to parse
  homepage HTML as REST JSON, and inspection never requested `/wp-json/`.
  That historical verdict is not evidence of a broken website API. New
  inspections request the exact site's REST root separately and retain its URL,
  time, HTTP status and bounded JSON-structure result. HTML and asset evidence
  no longer includes a REST verdict. Fleet verification explicitly requests the
  REST check and still rejects unsuccessful HTTP or malformed responses.
- A missing Stack runtime report can now use the existing read-only MainWP
  qualification capability. Authentication/permission failures never take this
  path. Exact site ID and URL are verified; only allowlisted classification,
  blockers, agent version and availability are retained. No site update or
  automatic installation is performed. On MRN's own website, live qualification
  identified agent `0.1.7`, `deployment_agent_upgrade_required` and
  `site_theme_shape_unavailable`; this is qualification evidence, not proof of
  a healthy or deployable Stack.
- All 126 Operations tests and direct ESLint pass locally, including realistic
  HTML/REST separation, subdirectory sites, invalid/denied/unreachable REST
  responses, expired/revoked identity, wrong-site qualification, secret exclusion
  and retained Fleet smoke gates. Hosted acceptance for the qualified candidate
  is recorded on PR #236.
- The original ChatGPT connection reopened the stored inspection after the
  one-hour access-token lifetime. Auth0 logs at `2026-10-08T19:12:29Z` show
  successful refresh-token exchanges for the configured ChatGPT client. No
  reconnect or new account connection was needed for this check.
- A teammate's unrelated Google GA4 MCP report was mistakenly investigated here.
  No source, service configuration, DNS, Auth0 setting or permission changed
  during that investigation. The extra owner test connection was disconnected
  and the disposable test chat archived. The original connection, accepted
  pilot and audit records were retained.
- WordPress PHP, frontend axe/performance and Stack parity suites remain
  inapplicable to this backend patch. No managed-site runtime was changed.

## Hosted ChatGPT staff pilot — 2026-10-08

- Exact candidate `b070501ef0026430b4dab2df60f8d23b506e66cc` installed on the
  verified MRN Apps droplet. All 100 Operations tests passed on Node 24.15.0;
  both candidate GitHub checks passed. Systemd is active and enabled.
- New exact-host DNS, TLS and Nginx vhost are active. Public health/metadata pass;
  anonymous MCP receives 401. Hosted MainWP status confirms the intended
  dashboard and 84 abilities. The exact-certificate renewal hook passes shell
  syntax and a live Nginx validation/reload.
- Separate Auth0 API and verified-email Login Action are active. Existing Login
  Actions, callbacks and other API grants were preserved. The public ChatGPT
  client has this API's single user scope; machine access remains denied.
- The workspace plugin is published and enabled with four tools: discovery,
  inspection, saved results and history. Future tools require review. No shared
  account connection, public Sites access or website mutation tool is enabled.
- Real ChatGPT OAuth consent, signed-in account and authenticated tool discovery
  succeeded. The plugin is selectable through chat's mention menu. Settings
  confirms the connected account even though the directory still displays Install.
- [Live chat acceptance](https://chatgpt.com/c/6ac7dd09-3954-83ea-8828-7242ab82f805)
  discovered 107 websites, then resolved and freshly synced only
  `https://mrnwebdesigns.com`. The stored inspection is bound to the signed-in
  user's immutable subject. Public sample: HTTP 200, approximately 115 ms TTFB,
  three forms detected. No form submission or website mutation was performed.
- The initial model call guessed `production` for an `unknown` environment and
  failed closed. Retrying with the discovered URL and no invented environment
  succeeded. The follow-up changes tool/schema guidance to require the discovered
  environment and makes clear this error does not require site enrollment.
  Target authorization and exact-match behavior are unchanged. All 29 focused
  ChatGPT/discovery tests and direct ESLint pass for that follow-up. MRN staged
  task acceptance passed all applicable rows.
- Inspection reported limits: Stack inspection failed through MainWP,
  REST health was incorrectly inferred from HTML (corrected above), and accessibility, full security, forms delivery,
  backups and Core Web Vitals remained unverified. This verifies the tool flow,
  not a clean health assessment or release qualification for the website.
- Consistent SQLite backup and isolated restored-copy integrity/row comparisons
  passed both before use and with one inspection and 61 audit records. No live
  database was replaced. Scheduled off-host backup coverage is not established.
- Still unverified live: a non-admin staff member's first
  install, outsider sign-in and explicit revocation. Controlled identity tests
  cover wrong domain, false verification, machine identity and revocation.
- The source follow-up changes tool descriptions and deployment evidence only;
  no WordPress PHP, frontend or Stack runtime is modified. WordPress API-runtime,
  axe, frontend performance and parity suites are inapplicable to this patch.
  No unrelated website is a QA target. This pilot is not a full Stack promotion.

## MRN Apps and verified staff access — 2026-10-08

- Owner selected DigitalOcean MRN-Apps and all verified `@mrnwebdesigns.com`
  staff. Live read-only host inspection confirmed droplet `571721080`, the
  existing private SSH route, Node 24.15.0, Nginx and available loopback port 8812.
  Auth0's API list and public OIDC metadata confirmed the existing MRN tenant.
- 100 Operations tests passed. New coverage verifies resource-specific signed
  email claims, strict boolean verification, exact domain matching, foreign and
  malformed identities rejected before downstream calls, explicit revocation
  and restriction precedence, machine-token denial, duplicate policy rejection,
  domain write-grant rejection and isolation of the new Auth0 Action from other
  API audiences. Direct ESLint, both deployment JSON schemas and diff checks pass.
- The separate Nginx vhost, systemd-compatible service configuration, staff read
  policy and Auth0 Action are prepared. No dependency was added. The host's
  Nginx configuration has not been changed or reloaded; its new vhost needs live
  syntax and TLS verification during activation.
- MRN staged task acceptance passed (100% of applicable checks).
  WordPress runtime/API, public browser, accessibility, frontend performance and
  Stack parity checks are inapplicable to these backend and deployment files.
  Live identity, ChatGPT connection/refresh, SQLite restore and workspace
  publication checks remain outstanding. No live service or client site changed.

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
