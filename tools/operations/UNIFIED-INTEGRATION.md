# Unified MRN MainWP integration

## Required team experience

One existing MRN Website Operations ChatGPT plugin at https://operations.mrnwebdesigns.com/mcp. Active MRN Google Workspace members get automatic portfolio access; there is no individual approval or enrollment step. Necessary approvals apply to concrete site changes. No teammate WordPress account, Application Password, API key, terminal, Codex, SSH or site token.

## Current structured-content integration, 2026-10-16

The existing `read_website_content` and `search_website_content` tools now select
the structured MainWP reader when that capability is exposed. They retain the
same 21-tool plugin, individual signed Google identity, fresh exact-site sync
and current-membership checks. Reads include registered ACF field keys, row
values and layout metadata; searches support explicit pages and post statuses.
No separate staff credential is needed. A denial or malformed result never
falls back to another route. The existing REST reader remains available only
when the structured capability is absent; unsupported filters fail explicitly.

Capability discovery reports record-read and search routes separately. A
missing structured capability with no configured central REST reader is
unavailable; partial discovery does not imply a complete post/page workflow.
The inventory describes discovered/configured routes, not current site
authorization, endpoint health or write qualification, and is withheld if
access changes during discovery.

Runtime test sources now select provider
`b18592de3a2c3e8900f538bb753c446c3cba9f51`. All four provider jobs in
[CI 38903564637](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38903564637)
pass, including 65 native WordPress contract checks and 22 MainWP/Child content
lifecycle checks. The actual CI checkout has the identical complete source
tree. Eleven existing contract blobs, three measurement blobs and all seventeen
frozen dependencies are unchanged. The separately imported management contract
and vectors remain exact `9e0511f` blobs. That earlier source failed native
approval because WordPress cannot resolve recursive schema references; b18592d
adds complete provider validation plus a native-compatible closed projection.
The original failed run is retained.

Gateway `f13ec5d7918500fbb913641c65c3d0d20eee902e` passes all 349 Node tests,
ESLint and staged MRN QA, plus
[combined CI 38906299558](https://github.com/mrnwebdesigns/MRN/actions/runs/38906299558)
and [MRN QA 38906299501](https://github.com/mrnwebdesigns/MRN/actions/runs/38906299501).
The combined harness passes ten identity, eight native content, eleven current
access and six transport groups. The native groups include two structured
read/search checks with a read-only OAuth scope, alongside the existing content
edit and rollback regression. Original artifact `12048782275` has archive SHA256
`2c2c29c22b315d86efb8d5c1ed509340d3107401f297750cbe29da27c9b1b3de`.
Actual CI merge `8719a3ccc0bb102f85f5967c5107d11ea18cd87a` has identical
Operations, workflow and imported Fleet helper blobs. Measurement group and
transcript hashes independently recompute. Synthetic Google, QA and backup
inputs are not live team acceptance. Local company-clone ACF PRO proof is
separate from hosted ACF-free proof. Static MRN QA skips PHP and browser/API
runtime rows for this JavaScript-only change; disposable native CI supplies the
applicable transport regression, without protected management-write admission.

The distinct management write abilities and protected signer are not registered
in the gateway. A structured read does not qualify a write. Full render/hook
parity, the protected management producer/admission, concrete deployment gates
and actual ChatGPT team acceptance remain open. Production and staff access are
unchanged; this is not a team-complete or deployment-ready checkpoint.

The management receipt verifier now checks the distinct
`mrn-content-management-qa-v1:` signature against the independently reconstructed
preview and migration. It binds the protected run/request, exact report and QA
engine, site/record, full baseline, semantic candidate, profile/runtime, affected
paths and rollback identity. Its lifetime cannot exceed the original preview.
It rejects older content/code purposes, duplicate/noncanonical claims, other
signers and even validly signed, rehashed unrequested sibling edits. New drafts
retain target ID zero until a separate installed read returns WordPress's ID.
This is signature/context validation only: the verifier is not registered with
a workflow, and caller-provided run/profile information cannot establish trust.

The existing single-field capture/measurement contracts cannot represent these
full records or ACF leaf edits. A separate provider management qualification
bridge is being implemented; it must retain original requester/current-access
checks, exact source/schema/runtime bindings, protected rendered/hook parity
and all required measurement groups. Neither native API regression nor local
ACF proof substitutes for protected profile admission.

The private management capture compiler has a separate fixed 5 MiB response,
depth-48, 200,000-node, 16,384-item and 1,000,000-step budget for complete parity
inventories. It reuses the checked schema vocabulary and rejects the same
serialization hooks, ambiguous values and unguarded recursion. Public management
reads retain their 524,288-byte, depth-32, 20,000-node and 1,000-item limits;
callers and schema annotations cannot override either budget. The private
compiler remains unregistered. A future pinned capture consumer must separately
validate snapshot, dataset, parity and artifact bounds, original source/access
bindings and every hash; fitting the outer envelope establishes no authority.

Private management callbacks use the fixed
`/internal/mainwp/management-access-veto` path on the same Operations service.
The old single-field `/internal/mainwp/access-veto` path remains separate. An
opaque query pointer cannot choose a signature domain or trigger a fallback
readback. Both paths share the existing ingress limits. The management handler
is absent from startup until its separately pinned query reader and original
requester ledger are integrated and reviewed; routing tests alone establish no
authenticated access or production activation.

The source-only capture consumer imports the exact additive contract and
synthetic vectors from provider `216d0f11397e1e73e5baa3398b4b1d01153ecbbf`.
It independently compares capture and measurement reads with the original
queued context and reconstructed preview/migration. It preserves original
affected paths and plugin activation order, checks runtime/schema/source
bindings and every digest/count, and rejects expired or renewed intent. Dataset,
parity and artifact limits remain smaller than the outer response allowance.
This consumer is unregistered; matching hashes do not establish authenticated
acquisition, full rendered/hook parity, profile admission or permission to write.
Native provider qualification remains a separate required gate. The earlier
synthetic vector with a job lifetime beyond its review expiry was rejected and
corrected upstream; no lifetime rule was relaxed to accept it.

Compiler checkpoint `a214256cdbdbd1c6fad3264605cfc700bdb2759e` passes 355 local
tests, ESLint and staged MRN QA, plus
[Operations CI 38909515349](https://github.com/mrnwebdesigns/MRN/actions/runs/38909515349)
and [MRN QA 38909515525](https://github.com/mrnwebdesigns/MRN/actions/runs/38909515525).
Original artifact `12049628380` matches archive SHA256
`4424af87364287151eb41595227909844b4abcd2628cfbd7751790928cbe31d3`;
group and transcript hashes independently recompute. CI merge
`55e0e580538bb1b31dfa3bb1f54cfe9b4f4735bd` has identical Operations, workflow
and imported Fleet helper blobs; unrelated merged MRN files differ. The
provider runtime pin remains b18592d. Production and ChatGPT acceptance are
not established by this evidence.

## Earlier acceptance checkpoint, 2026-10-15

The expanded plugin is **not team-ready**. Source and disposable qualification are ahead of the hosted workspace plugin. No production deployment, policy enablement or access change is authorized by these results.

- Gateway `5b3b5a35911b3623c4091eecca45f1a5684d6945` passes [combined CI 38806118603](https://github.com/mrnwebdesigns/MRN/actions/runs/38806118603) and [MRN QA 38806118553](https://github.com/mrnwebdesigns/MRN/actions/runs/38806118553): 308 Node tests and ten identity, six native content, ten actual callback and six transport groups. Original artifact `12006846700` has ZIP SHA-256 `9c37c0ff539cc08171b72cfdf04b4011e02e7d7130793a76abb66227bbbc9c5e`. The actual merge checkout's Operations subtree, workflow and imported Fleet modules match this source. The run pins provider b83265a and verifies private measurement readback, current permission/environment and pending-revocation denial through actual Node callbacks. OAuth/Directory, QA and backup authorities remain synthetic.
- Provider `b83265affd9e2c555f1beb8820acece3d5ffcd82` passes all four [CI 38804969514](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38804969514) jobs. The actual merge checkout `c02581455429e3dab3921257e8f31118f81fa3fb` has the identical source tree `7c4e1f64e6b78edf231a4eb73f94ddb68179b1e1`. It adds a private original-job measurement reader and fixed disposable adapter, with 138 native checks. All eleven existing copied gateway contracts/vectors and the three reviewed measurement blobs remain byte-identical. Report admission and production activation remain absent.

Runner follow-up `c2c00e29cd0ecf69638a16c5b87ab9223f823c0c` assembles four measured v2 groups against the full candidate profile, exact mutation report and three complete HTTP dependency inventories. It preserves the distinction between full-profile, profile-context and runtime-context hashes, and the output cannot satisfy the required eight-group schema. All 69 Node tests, applicable local MRN QA and all three [CI 38772144951](https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/38772144951) jobs pass. Eight downloaded reports pass required API, browser, accessibility, performance and Core Web Vitals checks; the exact-profile/group hashes independently recompute, and rollback sabotage is refused. Original content/code archive hashes are `5c537901bdde32e861b12732fd95e047a08f7d59643c9b845861f0e0dc04cb98` and `eb68e47fcdf721ac747816f25265b36fc354ea1d1f481ba30b89c17bdcaff84c`. The preceding [run 38770940388](https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/38770940388) passed the actual four-group assembly and first two content cases, then failed during title-case startup with TLS alert 80; it does not qualify the full suite. Source review found CA-file existence was the sole proxy-ready signal. The follow-up requires a fresh authenticated fixed-host handshake before QA and disables cross-instance HTTP connection/session reuse. Certificate/hostname/trust errors fail immediately, and failed QA observations are never retried. The original TLS server-side cause remains unproven; the failed run and its original archives are retained privately.

The provider retains observed packet sizes, hashes, chunk counts, truncations, installed-source and ownership evidence, bound to the original job/capture/requester/policy/revision. Its separate private read requires current access and a purpose-specific signature; it cannot backfill or renew old captures. The gateway's ten callback groups pass the combined run above. Provider logs cannot substitute for authenticated worker acquisition or independent report admission.

Runner `d9f2eba47efd27bcc3e5ee04c3c7eeaf0b3d11a1` adds private measurement validation and fixed-origin HTTPS acquisition. It verifies independently expected original binding, capture, recipe and package source, then performs anonymous, wrong-purpose and authorized requests with mandatory TLS, bounded raw JSON and a single original monotonic expiry budget. It returns four redacted groups and no authority. All 86 Node tests, applicable MRN QA and all three [CI 38809626510](https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/38809626510) jobs pass. The actual merge checkout `a5d0479a4440eda2aa36bc01216ea3f40ef12d4c` has an identical source tree. Eight downloaded reports pass required runtime checks; profile, distinct contexts and four runtime-group hashes independently recompute. Original content/code archive hashes are `6e7afc7e2347a43bf5f4a4ac718e65e95799218c74bbd49093feb549e81a1fb6` and `d973dae06efc153d63d747e1e6cd08058e0580adf55d88c8fd86a0d72e80430e`.

The acquisition client has actual local TLS coverage with synthetic application responses. The new combined harness pins runner `d9f2eba` and provider `289ee77ccbedbfb40f6e0b6c0d56a5a465142606`. Its fixed disposable controller keeps keys private and calls the real Node client over verified HTTPS. Eleven access groups require five acquisitions: successful reads, permission and environment denials, restored access, and pending-revocation denial. Each acquisition must produce exactly three actual HTTP requests and fresh gateway callbacks. Four redacted groups are compared with an independent original measurement record; all transcript/group digests are recomputed. The existing time, read and expiry limits remain unchanged. This assembled harness still requires its own combined CI result.

Gateway `6ab3208` passed MRN QA but [combined run 38811214018](https://github.com/mrnwebdesigns/MRN/actions/runs/38811214018) stopped before runtime because the existing CI App could not read the requested repository set. Read-only installation inspection confirmed the provider is among 29 selected repositories and the runner is absent. The owner approved adding only the runner; GitHub confirmed the saved read-only installation with all 29 prior selections preserved, for 30 total. No alternate credential was used. Separately, provider `eedd0b6` stopped at fixture Nginx startup because the exact route exceeded its default map hash bucket. Provider `289ee77` changes only that fixture limit to 128; the original failure is retained and the new native result is pending. All eleven copied contracts/vectors, three measurement candidate blobs and seventeen frozen dependencies remain byte-identical. Protected dispatch, original-job capture-time provenance and independent report admission remain unfinished.

Still required: protected worker dispatch/transport, all eight measured profile-evidence groups with independent signed-run/artifact admission, current-access revocation propagation (optional callback startup is implemented but disabled), service-owned publication/consumer trust, qualified ACF/menu/media workflows and the remaining capability mapping. Production identity/write-scope/key/policy setup and deployment need concrete approval. The existing workspace plugin then needs its published tool refresh and real ChatGPT acceptance: Google sign-in, site discovery, approved content edit/readback, qualified code edit/exact rollback, and outsider/revoked-account denial. Company code policy remains limited to the existing pilot. Staff enrollment or individual access approval is not part of this sequence.

The historical qualification trail below retains earlier failures and corrections; an older pending status does not supersede the current checkpoint. The separate local replay/worker failures described below remain unresolved and are not waived by CI success.

## Historical baseline, 2026-10-14

- Existing workspace plugin exposes four hosted tools. This source now registers 21 workflow tools; those new tools are not published or deployed.
- Canonical MainWP MCP is connected to wpcontrol.mrndev.io. The fresh configured connection at 15:06 UTC reports 107 discovered abilities and exposes 52 tools (the earlier connection exposed 48). Its abilities resource is also policy-filtered; the other 55 are counted, not enumerated or qualified here. Hidden capabilities remain filtered; this inventory does not bypass that policy.
- Live code provider is 0.13.4. Code policy and signed QA are qualified only for the existing company pilot, not the managed portfolio.
- Provider identity candidate PR #28 passed disposable WordPress/MainWP HTTPS runtime enforcement at source `603c816`: individual principal switching, replay/suspension denial and raw service-route bypass denial. This uses synthetic Directory responses and does not prove live Google OAuth or hosted ingress acceptance.
- Provider content candidate PR #29 at `c4eb01ff7a52e76b7a19b94a33b24b5ccdf53afa` passed [full CI run 38578290602](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38578290602): 34 native HTTP checks, 23 WordPress/MySQL content checks and 13 filesystem/SQL parity measurements. This supersedes the earlier failed measurer checkpoint. The reusable helper is accepted for those demonstrated CLI-context semantics; the runner's independent HTTP proof is recorded below. Provider controller wiring and real-site qualification remain separate gates. These disposable checks used no production credentials.
- The newer provider checkpoint `27ac5bc1371db01db7c0d5cf5c418ef776e23e1e` passed [CI run 38593325241](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38593325241): 34 native HTTP, 26 content, 13 parity, 12 profile-context and 25 controller lifecycle checks. It implements exact-target v2 lookup, checks parity under database locks and compares retained selector objects canonically. Its controller edit/rollback/replay/lost-response tests use real WordPress/MySQL and signed individual delegation, with synthetic child transport, QA signatures and backup receipts. It does not qualify live child transport, protected QA or remote backup creation. Gateway source `a6eccc2` pins this checkpoint and its CI hook and passed all ten actual gateway/MCP/WordPress transport cases in [run 38593686429](https://github.com/mrnwebdesigns/MRN/actions/runs/38593686429/job/115955067868). The report explicitly excludes production credentials, live Google sign-in, real site discovery, content/code writes and ChatGPT acceptance. The protected runner has updated the helper and passed its complete disposable rerun with both evidence bundles retained, as recorded below.
- MainWP REST key was found through the Production Hub mainwp provider and authenticated successfully against the expected Dashboard. Fresh company-site sync and exact REST identity readback succeeded. The installed REST route index exposes native post/page CRUD; exact-site page search/detail reads succeeded. No content write was attempted.
- Initial native gateway checkpoint `a3b55ea` pins provider `e615e98`, whose own disposable native MainWP Child edit/rollback and refusal checks pass. The combined [gateway run 38608469036](https://github.com/mrnwebdesigns/MRN/actions/runs/38608469036) passes the ten authorization cases and one-site discovery but fails at exact-URL `get-site-v1`, before content mutation. The provider target guard required a numeric ID where canonical URL resolution was needed. Provider fix `aeb0f2c` passes [complete CI 38611997072](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38611997072), including the native exact URL → one-site numeric sync → fresh exact readback sequence and negative target checks. Gateway b7851e1 pins that immutable fix. Its combined run 38613140657 passes initial exact URL resolution and HTTP sync dispatch but fails the same-URL fresh readback before content mutation; the HTTP sync path was unresolved at that checkpoint. The harness uses synthetic OAuth/Directory, QA signatures and backup creation; it cannot establish live Google, remote backup or ChatGPT acceptance.
- Gateway `7105c15` with provider `47de880` and canonical MCP `3249bde` passes [Operations CI 38617704685](https://github.com/mrnwebdesigns/MRN/actions/runs/38617704685) and [Code gate 38617704678](https://github.com/mrnwebdesigns/MRN/actions/runs/38617704678). Fixture-only ownership and private file creation fix the HTTP sync blocker without changing vendor code or production. The gateway now accepts the documented empty/null success error code and verifies forward versus reverse rollback eligibility. All ten authorization cases and six native content groups pass: exact fresh discovery, unchanged preview, approval/revocation refusal, actual page-title edit/public HTML/provider-status readback, separately approved exact rollback and individual audit. The retained artifact `11919998889` has archive SHA-256 `c601a99d7b1a8f8fbbe4173f8035ce8f871f80bb22b663a4ddb85d6e6c407f54`. This is disposable Child transport evidence with synthetic OAuth/Directory, QA and backups; live Google, remote backup, code and ChatGPT acceptance remain unverified.
- Read-only Dashboard inspection confirmed Code Snippets 5.1.1 and File Uploader 5.0.2 enabled and licensed. On 2026-10-15 the normal authenticated MainWP account download succeeded. The official 5.1.1 ZIP is retained privately (SHA-256 `d34b63dc7f9c5e32ebcff33b30930f1cfb238ccf8b625937dd0d72288bbcf93a`); product/version, checksum, entry paths/types, CRC and size bounds passed the archive validator. No user download is needed. No live reinstallation or licensed-source publication occurred. The separate disposable native code lifecycle result is recorded below; the archive alone does not establish it.
- Generic post metadata does not establish qualified ACF, menu or media editing. The current native detail response omits title/modified time and may have empty post_content for builder pages. Typed inspect/precondition adapters are required.

## Identity and transport

Read-only refresh after the restart on 2026-10-16: `mainwp://status` reports
`connected: true`, `dashboardHost: wpcontrol.mrndev.io` and 115 abilities across
two catalog pages. This session exposes 67 MainWP tools, including seven
knowledge tools without a qualified team workflow. The desktop abilities
resource still truncates its JSON, so that response cannot establish complete
schemas. The separately configured transport upgrade
does not prove that this existing connection or the hosted plugin has restarted.
The existing hosted `/healthz` responds successfully; that is only service
health, not authentication, editing or team-acceptance proof.

Auth0's resource-specific Action accepts only the authenticating Google connection and a primary numeric Google identity with a verified exact MRN email domain. The gateway additionally pins the Auth0 connection ID, verifies issuer/audience/signature/expiry/scope, and asks the Dashboard for current staff authorization on every HTTP request. Plain email claims, linked non-Google primary accounts and caller metadata are not identity. Directory/customer/suspension/revocation checks repeat at backend dispatch.

The existing OAuth permission is explicitly read-only. Candidate source requires the additional `mrn:operations:write` scope for all eight change-lifecycle tools, registry write authorization and delegated write challenges. It derives immutable scopes only from the verified JWT; roles and caller metadata cannot upgrade existing consent. Source `fa7672c` passed 197 controlled tests and both exact-head CI checks. The new Auth0 permission/grant and workspace metadata have not been activated; [the configuration review](deploy/TEAM-CONFIGURATION-REVIEW.md) records that separate gate.

Each call uses an independent Ed25519 assertion, separate from the protected QA key. The nonce binds service connection, inner ability, strict canonical argument digest and policy. Maximum lifetime is 60 seconds, bounded further by the caller token and challenge. Shared PHP/Node fixtures are in test/fixtures/delegation-v1.json; that public test key must never be enrolled.

The four-field challenge is nonce/expires_at/authenticated_connection_sha256/policy_sha256. All delegation endpoints use POST-only metadata to keep signed identities out of GET URLs and access logs. Read and single-site sync wrappers are non-destructive; only the mutating wrapper is destructive and requires confirmation. Gateway readiness rejects mismatched metadata before signing an assertion. A write retains the backend two-phase confirmation with the exact same envelope; no retry after a dispatched response is lost. The pinned service connection cannot fall back to an interactive Google lease or raw privileged child dispatch.

The source config keeps legacy deployment behavior until a reviewed migration enables googleWorkspace and delegation together. Failure or absence of the new provider denies authentication; there is no silent fallback. Identity keys are owner-only, non-symlink Ed25519 files. Audit events retain the validated individual subject, Google subject and email without tokens or argument payloads.

Native content reads repeat live staff authorization after each REST response and at the service return boundary. Revocation during a pending read withholds its result. Known MainWP content/QA/backup/lock/Directory blockers retain safe, fixed chat explanations; raw upstream messages are never reflected. A sync response must confirm one exact synchronized ID/URL with zero errors; HTTP 200 alone is insufficient. Safe Mode and uncertain outcomes remain hard stops with no alternate-route retry. The full gateway suite passes 246 controlled tests; this is source evidence, not live revoked-account acceptance.

## Capability mapping

Observed 2026-10-14T15:06:50.092Z through the configured MCP, using the existing SDK/client because the desktop resource response truncated the JSON. Only status, tool discovery and the abilities resource were read; no child call or configuration write occurred. Mapping is against this source checkout; connected_workflow does not mean deployed, write-qualified or workspace-enabled.

| Backend tool | Hosted workflow | Backend effect |
| --- | --- | --- |
| list_sites_v1 | read_portfolio_details | Read |
| get_site_v1 | inspect_website | Read |
| sync_sites_v1 | inspect_website | Guarded operation |
| get_site_plugins_v1 | read_website_details | Read |
| get_site_themes_v1 | read_website_details | Read |
| get_abandoned_plugins_v1 | read_website_details | Read |
| get_abandoned_themes_v1 | read_website_details | Read |
| get_site_security_v1 | read_website_details | Read |
| get_site_changes_v1 | read_website_details | Read |
| get_site_client_v1 | read_website_details | Read |
| get_site_costs_v1 | read_website_details | Read |
| count_sites_v1 | read_portfolio_details | Read |
| get_sites_basic_v1 | list_websites | Read |
| list_updates_v1 | read_portfolio_details (cached updates) | Read |
| list_ignored_updates_v1 | read_website_details | Read |
| get_site_updates_v1 | read_website_details | Read |
| list_clients_v1 | read_portfolio_details | Read |
| count_clients_v1 | read_portfolio_details | Read |
| get_client_v1 | read_portfolio_details | Read |
| get_client_sites_v1 | read_portfolio_details | Read |
| count_client_sites_v1 | read_portfolio_details | Read |
| get_client_costs_v1 | read_portfolio_details | Read |
| list_tags_v1 | read_portfolio_details | Read |
| get_tag_v1 | read_portfolio_details | Read |
| get_tag_sites_v1 | read_portfolio_details | Read |
| get_tag_clients_v1 | read_portfolio_details | Read |
| get_batch_job_status_v1 | read_portfolio_details (existing batch_job) | Read |
| mrn_mainwp__start_database_backup_v1 | approved_operation | Guarded operation |
| mrn_mainwp__get_database_backup_status_v1 | approved_operation | Guarded operation |
| mrn_mainwp__install_plugin_package_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__get_google_fonts_status_v1 | read_website_details | Read |
| mrn_mainwp__get_stack_runtime_report_v1 | read_website_details | Read |
| mrn_mainwp__inspect_backup_retention_policy_v1 | read_website_details | Read |
| mrn_mainwp__apply_backup_retention_policy_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__get_stack_deployment_agent_status_v1 | read_website_details | Read |
| mrn_mainwp__qualify_stack_site_v1 | read_website_details | Read |
| mrn_mainwp__preflight_mu_release_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__preflight_stack_release_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__preview_incomplete_rollout_reconciliation_v1 | Not yet integrated | Read |
| mrn_mainwp__install_mu_release_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__install_stack_release_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__rollback_mu_release_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__rollback_stack_release_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__configure_code_policy_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__get_code_access_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__inspect_code_file_v1 | inspect_website_code | Read |
| mrn_mainwp__qualify_code_storage_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__prepare_code_change_v1 | prepare_code_edit | Guarded operation |
| mrn_mainwp__approve_code_change_v1 | approve_operation | Guarded operation |
| mrn_mainwp__execute_code_change_v1 | execute_operation | Guarded operation |
| mrn_mainwp__get_code_operation_v1 | get_operation (refreshProvider) | Read |
| mrn_mainwp__resolve_code_operation_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__get_backup_policy_v1 | Not yet integrated | Read |
| mrn_mainwp__get_backup_roster_v1 | Not yet integrated | Read |
| mrn_mainwp__preview_backup_policy_v1 | Not yet integrated | Read-only plan |
| mrn_mainwp__apply_backup_policy_v1 | Not yet integrated | Exact plan, verified backup, two-step confirmation |
| mrn_mainwp__preview_backup_decommission_v1 | Not yet integrated | Read-only plan |
| mrn_mainwp__decommission_backup_policy_v1 | Not yet integrated | Exact plan, verified backup, two-step confirmation |
| mrn_mainwp__preview_backup_cleanup_v1 | Not yet integrated | Read-only component manifest |
| mrn_mainwp__execute_backup_cleanup_v1 | Not yet integrated | Exact unchanged manifest, two-step confirmation, absence verification |

## Typed content workflow in source

The development integration now pins provider `e6b0903d74aa1f068ecf0e482037f7bb552c715e`, adding private retained-capture recovery and a fixed disposable helper to the authority isolation and native code fixture. Its exact-pin combined gateway CI remains a separate gate; the earlier f25be6b integration result is retained below. The preceding provider `0b73857c2ae3a3a46a3698aa17d9de8fea214030` came from isolated [provider PR #32](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/pull/32), stacked on PR #31 and PR #29. Its four provider CI jobs pass in [run 38737864986](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38737864986), including 110 requester/capture checks and the separate authorized-start and denied-start COMMIT-fault cases. The full private local suite also passes with the genuine extension. Exact-pin combined gateway CI is a separate gate. At the preceding `09dac5f4f6f599e5a497f86f92a2b2ca02aeaf1d` checkpoint, [Provider CI 38725372387](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38725372387) passes all four jobs at that exact commit. It includes 105 requester/capture checks, 34 owner checks, 16 callback checks, 25 native content lifecycle checks, 43 capture chunk checks and 266 admission-contract checks. Real HTTPS and independent InnoDB tests verify private query readback while work locks remain held, separate answer/consumption states, no second consumption and pending revocation during a native read exceeding five seconds. Google and gateway authorities remain synthetic in that provider suite. These results qualify the demonstrated disposable behavior only.

Recovery and report-retention contracts are copied byte-for-byte with checksums in `contracts/content-qualification-sources.json`. Their linked golden vectors agree on the retained snapshot, requester, recipe and artifact; twelve substitution vectors differ. Recovery is a bounded private read with a separate signature purpose. Report retention requires independent QA authority and all eight evidence groups; binary archive bounds are separate from UTF-8 manifest bounds. The gateway checks wire compatibility only. Private recovery, evidence retrieval, closed measurement/provenance admission, current-access propagation veto, publication and consumer trust still require implementation and qualification. No new chat tool or runtime admission is activated by these copies.

An earlier [provider run at dbf4d3d](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38658114376) failed in a concurrent capture worker. The old fixture discarded its stderr, so the cause is unknown. Later diagnostic and three-round runs passed; they do not establish a root cause or a fix. Redacted worker diagnostics are now retained on failure. This unresolved issue remains a release/activation concern, and any recurrence blocks the current gate. Source-development acceptance does not waive it or qualify live Google, remote backups, code edits or ChatGPT team acceptance.

The [combined run 38726887627](https://github.com/mrnwebdesigns/MRN/actions/runs/38726887627) failed before the actual Node callback hook: a concurrent capture loser returned `mrn_site_locked` with SQL 1213 during its insert-only site claim. Artifact `11969480260` was downloaded and its ZIP SHA-256 verified as `43edd74c58fca58648caf52a0838f8c9c1be1210e0dde87c2a28f37dcef43b38`. The exact lock graph was not retained. PR #31 adds a zero-wait database/table/site claim guard and a raw existence read, preserving durable ownership, failure retention and strict worker diagnostics. The local two-connection MySQL test passes 22 assertions and full provider MRN QA passes. Native MariaDB and an actual Node combined run subsequently passed as recorded below; unexplained intermittent refusals still prevent a release-readiness claim. This concrete failure does not establish the cause of the earlier diagnostic-free failure.

Provider lock fix `c5d8f36` passed all four jobs in [CI 38730691382](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38730691382), including 22 real MariaDB SQL checks and 105 requester/capture checks. However, [combined run 38730976094](https://github.com/mrnwebdesigns/MRN/actions/runs/38730976094) then failed a different gate: an injected start-COMMIT rejection left a retained site lock. The failure occurred before the actual Node hook. The old fault could intercept either an authorized start (whose proven rollback releases exclusion) or a denied start (whose uncertain commit intentionally retains exclusion). Commit `364341c` separates both cases and requires exact phase, state, claim, zero native dispatch and lock outcomes; it does not accept either result interchangeably. Provider `fa22862` adds bounded failure-only query/status/timing records and synthetic callback-stage diagnostics without actors, tokens, envelopes or captured content. Its complete run passes. Separate earlier runs failed a clean concurrent winner assertion or a slow-read trace assertion without sufficient timing evidence; passing reruns do not establish their cause.

Gateway `5f6b8b8`, provider `c68afe2` and transport `3249bde` passed [combined run 38732205662](https://github.com/mrnwebdesigns/MRN/actions/runs/38732205662): 281 Node tests, ten identity groups, four actual Node callback groups, six native content edit/rollback groups and six bounded-transport scenarios. The callback verified original requester binding, authorized capture, pending revocation during a six-second Child read with no retained second artifact, and denial of a later queue. Artifact `11970293601` was downloaded and verified (ZIP SHA-256 `a3952fda40e5a1feca0453e38b46bfffae5809323b5f529e19af572e1cc619db`). Google/Directory, QA and backup inputs were synthetic. Live Google, protected QA, remote backup, native code and ChatGPT acceptance remain unverified.

Gateway `1d60562` with provider `c68afe2` also passed [combined run 38736815673](https://github.com/mrnwebdesigns/MRN/actions/runs/38736815673). The contract source pin was `fa22862`, but the actual runtime checkout remained `c68afe2`; this run does not qualify gateway transport against `fa22862`. Downloaded artifact `11974317884` verifies against ZIP SHA-256 `d5c248ffb26bc300f1e2a08b45e802f6ee9fdcf33ba2fcb46d3e853bc86cca8c`; all four reports pass with the same explicit live-Google/QA/backup/ChatGPT limits.

Provider `0b73857` passes the full local disposable suite with the privately supplied genuine Code Snippets 5.1.1 archive. Its 23 code lifecycle assertions use actual Dashboard HTTPS abilities, signed individual delegation, native MainWP/Child transport, public/direct-origin TLS storage qualification, unchanged preview, approval, exact file write/readback, individual audit and separately approved rollback. The original and restored SHA-256 are `ef49003b3b3b8883a75789da2f7ec488e58ce60ab0f3bc2c58fdcb9cfbba7914`; candidate SHA-256 is `0037a0ad624dbfec302fdc7d730fe025e107808abfcff709d493e34b61357af6`. Synthetic Google/QA/backup authorities remain explicit, so this is native-provider proof rather than protected-runner or ChatGPT acceptance. The standard CI does not receive or publish the licensed ZIP.

The earlier full local run and provider CI `38736869706` at `d7d8b32` passed code/preceding checks but failed the concurrent-capture assertion: one `mrn_site_locked`, one `mrn_access_veto`, and no callback HTTP record from either worker. This narrows the refusal to pre-callback provider checks without identifying its cause. `af81898` adds bounded private error-code/source-location diagnostics in test fixtures only. Its CI and local/CI runs passed at that checkpoint. A later diagnostic reproduction identified the counter-insertion refusal described below; earlier failures without diagnostic records are not individually attributed by inference.

The gateway now implements a private current-access callback at the fixed `/internal/mainwp/access-veto` path, installed only when an explicit handler is supplied to the HTTP server. The production entrypoint can install it only with the explicit `contentAccess: true` service setting; the default remains disabled and no deployed setting has been changed. Startup reuses the existing Google identity authority, validates a dedicated MainWP connection and its canonical POST capability before listening, and creates the private ledger only after that validation. The connection closes on initialization failure or service shutdown; shutdown cannot return an in-flight clearance. The callback accepts only an opaque query ID/nonce, retrieves the exact registered query using a separate warmed MainWP control session and the existing identity signing authority, and checks its canonical digest, original requester, exact site/target, policy and lifetime. Its signed answer is purpose-separated, valid for at most five seconds and never an execution permit. Pending revocation, missing/corrupt mapping or ledger, mismatches, replay and expiry fail closed. Callback capacity, bodies and per-job queries are bounded.

A dedicated private qualification queue method requires service-owned protected-worker admission before obtaining the provider queue response. It retains the original verified Google identity and exact queue result in an immutable SQLite mapping; callback data cannot create or replace a mapping. A fresh login cannot extend an existing mapped lifetime. Pending revocation and used query IDs survive restarts, and access audit rows resolve to the original individual through that map. No OAuth token is retained. The local pending state is not a claim of committed provider revocation. Owner authorization/propagation/readback, the real protected-worker readiness adapter, dispatch, report admission and consumer publication still need integration. The queue method is not exposed as a workspace tool.

Gateway tests cover those control paths, including the HTTP route, exact signatures, storage interruption, restart, monotonic revisions, local denial and an immutable delegated queue. Combined testing now invokes the optional provider fixture hook with the actual Node callback over the existing TLS origin and an isolated Unix socket. Its fixed worker retains the synthetic registrar inside PHP. The hook authenticates Bob, queues and maps an exact request through MCP, captures it, then queues a second and records pending revocation during a real six-second Child read. It requires exact absence of a complete second artifact, closed subsequent queueing, attributable audit and authenticated POST readback traces. This new combined gate must pass before its behavior is accepted; the existing provider synthetic-gateway suite does not substitute for it. Nothing in this checkpoint activates a live policy, key, access change or deployment.

The frozen `contracts/content-v1.json` matches the provider contract. The gateway supports inspection and preparation of one existing published post/page title, excerpt or content field. It verifies full semantic snapshots, one-field changes, the canonical plan descriptor and an immutable replayable migration retained centrally. There is no caller PHP, arbitrary metadata, create/delete/status change or per-site repository requirement.

The v1 provider policy lookup selects by site only and returns one exact-record profile, or null when the catalog is ambiguous. The gateway implements the provider's `get-content-policy-v2` contract, with the exact closed site/record/field tuple and unchanged v1 output. It prefers v2 when advertised; only absence permits the legacy lookup. Denial, malformed output, missing qualification or a failed v2 response never falls back. Only this exact v2 ability is added to the signed read allowlist; no registrar or policy-write tool is exposed. Controlled tests prove two different targets on one site, wrong-profile denial and the unchanged edit/rollback lifecycle. Provider `27ac5bc` passed exact-field selection, missing-target denial and v1 ambiguity checks in its disposable controller suite. A protected service-owned registrar and real-site profile qualification remain pending; actual disposable Child transport passes the combined lifecycle above, but this source adapter does not establish multiple-record production support. Qualification remains an MRN service responsibility, never a staff page/site enrollment or individual access-approval step.

The proposed provider catalog separates stable access-policy identity from immutable per-target qualification, so adding an unrelated record need not cancel an existing plan. Target replacement/revocation must invalidate that target's plans; access-policy changes still invalidate identity grants. Protected service-owned profile registration and independently qualified ACF scalar/reference, existing menu-item label/link and existing attachment title/caption/alt adapters remain implementation work. No generic metadata write, staff registration, real-site profile or production access is activated by the gateway contract.

Provider contract checkpoint `4029913` passes [complete CI](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38621727078). Exact qualification, recipe and separate target-binding schemas and canonical vectors are retained in the gateway with source checksums; 41 definitions and eight vectors validate. This is contract compatibility only. The first job output has a null-only binding and provider-only acceptance; it cannot activate consumer trust. Protected worker context, job expiry and current target revision/profile/binding hashes make setup and stale-target handling explicit. The separate binding names actual published revision, purpose, consumer audiences and expiry. Provider catalog/job runtime and the separately versioned binding/consumer transition remain unimplemented; no new queue/control route is exposed in the workspace.

The next accepted provider foundation is `792226abd7b3ed28532b661aca385f0d917a81c3`, with [all four CI jobs passing](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38638976550). It separates stable authorization from exact target revisions, retains encrypted immutable history and revocation tombstones, and verifies per-target compare-and-swap under the shared site lock. Native tests found and fixed a stale WordPress absence-cache race: initialization and one-use claims now use insert-only SQL; code cleanup and resolution cannot delete another operation's lock. The packaged provider, including nine explicit runtime contracts and its generated source manifest, is installed in the disposable harness. Evidence includes 25 native content lifecycle checks, 44 WordPress/InnoDB storage checks with two independent publishers and exactly one winner, 253 structural/canonical contract checks, and 17 package/extracted-source tests. The gateway now pins this provider and retains the two additive contracts and full capture vector: 43 definitions and nine vectors validate. This implements private storage and package provenance only; owner installation, job queue/capture/publication abilities, protected worker dispatch and the separately versioned consumer-binding transition remain pending. The combined gateway run is a separate acceptance gate.

The internal `content-recipes.mjs` verifier now validates the approved recipe authority separately from registrar, identity and QA keys. It requires the exact canonical signed payload, current service-owned digest and epoch, valid dates, all three consumer audiences, sorted unique recipe identifiers and eligibility, and every independently configured source/runtime/assertion hash. Recipe matching remains restricted to the accepted disposable post/page scope. Eleven focused signature and rejection tests pass. No application workflow loads this verifier yet; it does not issue or accept target bindings, replace static target inventories, dispatch a worker, or enable production eligibility.

Two additive provider contracts were reviewed at source: `content-recipe-install-v1.json` SHA-256 `9d1cd1afaf58b748d9981a88df85735d22d9ef6b8b08b5e0f8bcc05eb8408216` and `content-qualification-handoff-v1.json` SHA-256 `400149d7164dc67b89d58a443f1eeabeba4b5241018ab544426566c1c5be826f`. Catalog installation requires a fresh Google-authorized owner, `manage_options`, a disabled policy and an exact policy-digest compare-and-swap; it retains a matching independently signed catalog centrally and cannot activate it. The gateway must verify protected dispatch and source pins before creating a provider job. Without them it returns `blocked/worker_unavailable` and creates no job. Queued means pending intent; only authenticated capture can start work, before the earlier of job expiry or five minutes after creation. Unclaimed jobs become blocked, and late capture cannot revive them. An indeterminate dispatch permits read-only reconciliation without redispatch. These source decisions require implementation and runtime tests; they add no staff setup, public worker endpoint or gateway-held registrar private key. Live provisioning remains subject to concrete approval.

The protected content receipt has its own signature prefix and binds the exact record, before/candidate hashes, plan, migration, qualified profile/runtime context and affected public paths. Code or preliminary source receipts cannot authorize content. Approval and execution require live Google membership; original-operator execution repeats current policy and baseline checks after the verified remote DB backup. Fresh full snapshot and exact public HTML text at a trusted selector must match after execution. A stale public page or uncertain response retains the lock; Fleet recovery cannot release it. Rollback requires new QA, approval and backup.

The gateway has controlled and actual disposable Child transport coverage only. The separate runner draft PR #4 implements `content-qa.yml`, a fixed typed WordPress replay adapter, before/after visible-text browser checks and a separate content signer. [Disposable runtime CI at 6551079](https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/38581916586) passed page-content edit/rollback, normalized title, post excerpt, mandatory API/browser/accessibility/performance QA and unsafe-HTML/unnormalized-title/stale-render rejection. Cross-service receipt verification also passes. Production content policy remains disabled with no profiles or service actors; the runner additionally rejects all real sites until independent production-profile parity is qualified. A passing synthetic run cannot prove current production rendering dependencies match a pinned profile. Typed ACF, menu and media adapters remain required follow-up work; this phase does not make them available.

Runner source `65510797ffb0f0627c31c6cb5b38c7e038aa4b10` pins the accepted provider helper (`b87136783fbe97ce87ca093b772caf397285a6022ff6f5d1bc800362c05ebe1e`) and independently measures HTTP dependencies before and after QA. The same expected `parity_sha256` participates in both runtime/profile contexts and signer evidence. Only owner-policy option names reach the fixed measurer; no temporary WordPress plugin or inventory exclusion is introduced. Its explicit synthetic bootstrap produces only unsigned measurement data, never signable qualification artifacts. All 41 local tests, staged static QA and exact-head contracts/code-isolation/content-isolation CI passed. Wrong digests and substituting another record's baseline were rejected. The retained fixture manifests differ only in target metadata: the page has `_wp_page_template`, while the post has `_encloseme`. Each record needs its own complete qualified dependency evidence.

Runner source `0fd0ff76083b4350fd86488e282723e73b821c43` pins provider `27ac5bc` and helper SHA-256 `66110c269fe347648885fd12bce178e4ec73bc476dfdae80a4166064e15389ba`. All 41 local tests and staged MRN QA passed. [Run 38593836989, attempt 2](https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/38593836989/attempts/2) passed contracts, code isolation and content isolation, including both required evidence uploads. The first attempt failed only at report upload because the account artifact quota was exhausted. After the owner approved and saved the existing Actions budget at $5/month with cutoff and alerts preserved, only the failed jobs were rerun at the same source. Both bundles were downloaded and checked: eight passing QA reports with mandatory API/browser/accessibility/performance rows, plus distinct page/post HTTP parity manifests. Runtime checks also rejected unsafe HTML, unnormalized title, stale rendering, wrong parity and a cross-record baseline. Code artifact `11910930605` has ZIP SHA-256 `7f3cd8dd96a241478a5438ca4536d455afb42a74fedfb0b7d15b6343abfe9754`; content artifact `11909304607` has ZIP SHA-256 `1af30db4462f7d75d7a626ce461a72f6ffea4f0494b2e5ca4e664e9da2d1d068`. No required gate, retention setting or stored evidence was changed. This qualifies the demonstrated disposable runtime only; real-site profile parity and team acceptance remain unverified.

Portfolio updates and existing batch-job status are bounded reads through the same signed identity transport. Updates are explicitly labeled cached Dashboard inventory; they do not trigger bulk child sync or replace exact-site inspection. Batch reads never start/retry a job. Provider-side adapters passed the disposable native HTTP checks noted above, including native batch denial propagation. The provider source remains a draft; its controller lifecycle passed with a synthetic child connection, and the later combined gateway run qualifies actual disposable Child transport. Real-site transport and rendering remain unqualified. Real hosted execution remains unverified, and these source mappings are not deployed capabilities.

Live gateway readback reports `publicHtmlContentVerified` with method `exact-selector-html-text` and explicitly reports `liveBrowserVerified: false`. It does not label an HTTP/HTML comparison as live browser visibility. The protected disposable runtime and final team acceptance have their own browser evidence.

`get_operation` accepts optional `refreshProvider: true` for an existing retained content/code plan. It resolves the exact current MainWP directory identity and reads `get-content-operation-v1` or `get-code-operation-v1` under the verified Google actor. It checks the operation, site, target/file and stored plan hashes, withholds results after access revocation, and returns only bounded status metadata. This is a Dashboard audit read, not a child sync or current-live-state check. Provider completion or owner-side resolution never upgrades local uncertainty, retries execution, releases its lock or authorizes rollback. An ordinary local-only operation read remains available when provider status is unavailable. Controlled HTTP-tool and actual disposable Child lifecycle/status tests pass; live provider status remains unverified.

## Guarded code workflow in source

`prepare_code_edit` accepts one exact inspected file checksum, bounded UTF-8 candidate and reason. Qualified snapshot profiles proceed to protected QA. Git profiles first run isolated preliminary QA against the exact original merged/live baseline, then create a single-file PR only after a fresh signed source-review receipt; `approve_code_source` requires the exact source preview and normal GitHub merge gates. After merge, a second QA run must verify the candidate in merged source. Preliminary receipt kinds cannot authorize a live edit. Source approval is separate from the later QA-qualified live-change approval. Individual attribution stays in the private operation audit; repository text uses the operation ID. `prepare_rollback` retains original bytes and repeats source, QA, backup and approval gates.

The central GitHub App adapter requests a short-lived token for one pinned repository and the minimum operation permissions. No staff token is accepted. Runner dispatch pins the workflow commit, runtime profile, actor and request ID. Receipt handling checks exact run/artifact provenance, bounded in-memory ZIP extraction, Ed25519 signature, TTL, engine commit, site, path, source and before/after hashes. No candidate code runs in the hosted Operations service.

Source changes remain blocked unless trusted current evidence shows source-triggered deployments are inactive at that exact base commit. Sites with existing automatic deployments need a qualified adapter coordinating those jobs with the same site lock and backup gate; this implementation does not disable automation. The central App must not be a branch-rules bypass actor. The existing read-only CI App is not silently broadened or reused.

`execute_operation` requires current Google authorization, a valid exact approval, shared-writer coordination, a newly verified remote DB backup and the provider's code lock. It verifies exact file readback plus public/API health. Unknown results retain the local lock and never retry; Fleet reconciliation is barred from code locks. Protected QA and provider policy remain company-pilot-only. Creating new files, deleting files and reconciling uncertain code outcomes in chat are not yet qualified.

No central App has been provisioned for this workflow, no runner service actor has been admitted, no production policy has changed, and these new workflows have not been deployed or tested by a staff member in ChatGPT.

## Release and acceptance gates still open

1. Finish and review the provider identity release, common content/code locking and typed content workflows.
2. Provision and qualify central App/runner dispatch, exercise the implemented source/code lifecycle against an isolated provider runtime, and qualify any additional snapshot profiles. Existing pilot qualification must not be generalized.
3. Review exact deployment artifacts, service identity key, Google connection/customer pin, service connection restriction, automatic domain policy and rollback. Production/access configuration stays unchanged until that approval.
4. Update the existing workspace plugin's tools, without creating a second plugin.
5. Verify an ordinary active staff session in ChatGPT, exact-site discovery, approved content edit/readback and qualified code edit/exact rollback; verify outsider and revoked/suspended denial. No named tester or per-person access approval is required. These tests are acceptance evidence, not onboarding gates.

Do not call the product complete while any required content/code/ChatGPT acceptance remains unverified.


### Access policy and query-ledger isolation

On 2026-10-15 the full private native suite at `0b73857` reproduced the clean concurrent refusal. Its inner diagnostics locate `mrn_access_storage` at the first private counter insert, before callback dispatch. A reduced real WordPress/MariaDB 11.4.13 test classified a SQL lock-wait timeout and captured an `X RECORD` wait on the control table's PRIMARY supremum record: work connection 6198 blocked control connection 6310. Reducing the work guard to `START TRANSACTION` plus `lock_policy()` alone also reproduced the timeout. Simple standalone point lookup and several fresh fixture layouts passed; those passes did not prove isolation. No callback delay or Google outage is inferred from this failure.

Provider `8a110737ea0ff9081b05be85f77ee57359b89599` moves only the policy row to the separately validated `mrn_content_access_authority` table. Query/counter/replay/answer rows stay in `mrn_content_access`. The work transaction still locks policy authority; principal/target/site locks, transaction isolation, five-second vetoes, single-use receipts and conservative refusal gates remain. There is no automatic retry or pre-created counter workaround. Owner-only storage preparation validates both schemas; the changed schema digest invalidates prior authority, and an old query-table policy cannot activate the new authority. Production setup needs its separate reviewed approval and is not run on plugin activation.

The exact fix passed staged MRN QA and the full local disposable runtime: 96 fresh counter registrations under the work guard, independently committed query records, a competing policy UPDATE that times out and succeeds only after work rollback, unchanged `REPEATABLE-READ`, 212 requester/job checks, 35 owner checks, and the existing 25 native content edit/rollback checks. Provider CI is tracked in [run 38751931045](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38751931045). Merge `f25be6b` combines this source with the genuine extension fixture. All 11 frozen contract files remain byte-identical. Protected worker qualification, wider content coverage, production activation and real ChatGPT acceptance remain unverified.

Both provider CI runs passed: `8a11073` in run `38751931045` and combined `f25be6b` in run `38752453679`. The private local `f25be6b` run passed all 23 genuine native code assertions and the 96-counter isolation regression, then failed the first replay-positive access vector with `mrn_access_replay` after an HTTP 200 callback. This separate freshness/replay refusal remains under diagnosis; no passing rerun waives it.

A subsequent exploratory local run passed that replay vector but later produced a concurrent worker with exit zero, blank stderr and stdout that decoded to null. The old helper discarded that malformed output. Diagnostic-only provider `80134cf` now retains malformed worker output privately and records the first replay vector's total elapsed time, current revision and authority checks on failure; its CI `38754572554` passes. Host load above 180 was observed after the local failures, but timing/resource causation is not proven. This task's disposable QA VM was stopped; shared runtimes and applications were not changed.

### Runtime pin verification correction

Downloading and inspecting the reports from gateway `c296bf9` run `38753607100` revealed that `test/runtime/sources.json` still selected provider `c68afe2`. Only `contracts/content-qualification-sources.json` had advanced to `f25be6b`. The same runtime pin remained in runs `38736815673` and `38738762957`, as their checkout logs confirm. Earlier statements calling those runs exact `fa22862`, `0b73857` or `f25be6b` integration proof are withdrawn. Provider standalone/local evidence above is unaffected.

Run `38753607100` genuinely passed the four disposable report groups against `c68afe2`. Artifact `11983725207` is retained with verified ZIP SHA-256 `2c7cc42c250d2699f25545b3c29f0c563eeecee270b8bd5156d1be4596d0476d`; all provider report headers name that actual runtime source. The runtime pin now matches `f25be6b`, and the contract test asserts equality of both repository and commit before combined CI may proceed. That new assertion first reproduced the stale-pin failure and then passed with the corrected pin.

Corrected gateway `2ff77ebd60e8d3fa701d8217ea470e0ca8663bcb` passes combined run `38756584075` and MRN QA `38756584017`. Downloaded artifact `11983618625` verifies against ZIP SHA-256 `279db1fe4893ad3f5d8a1003be69720aa8839e085956fc07d5d34f25bf8e7de9`. Its three provider report headers explicitly name `f25be6b`, and all four report groups pass: ten identity, six native content edit/rollback, four actual Node access-callback and six bounded-transport groups. This proves disposable gateway transport against the intended provider; it does not establish live Google, protected worker, remote backups or ChatGPT acceptance, and does not close the separate local failures.

Runner `7607c7d98111cb7d8938034cbc43616fc943f980` adds distinct validation of private retained-capture data: authenticated running context, fresh purpose-specific claims and identical original/context/response artifact hashes, with unchanged source/recipe/snapshot/parity/expiry restrictions. All 56 Node tests and applicable MRN QA pass; static API/browser/accessibility/performance checks are not applicable to this unwired data-only module. All three protected runner CI `38756107305` jobs pass. The retained code/content archives (`11983847502`, `11983019292`) match SHA-256 `8095322fec59868e945c22d8504ac41673a99ac2b4d819b10fa7ec77af20fb7b` and `bedd55cf0e6cf41164d92c91812977b4e2a51a43e497619dea7e4cf372b7a163`; all eight reports pass required WordPress API runtime, browser, accessibility, page-speed and Core Web Vitals rows. Provider recovery PR #33 is draft and unqualified while its actual endpoint tests run. Neither change activates transport, production policy or team access.


### Private retained-capture recovery integration

Provider `a003753959577cf792e4f72c989210ed35c4ab5e` passes all four jobs in CI `38759798309`, including 58 native recovery checks and the existing 212 requester/job checks. Recovery verifies the purpose-signed registrar request, original requester/authority/recipe/revision, exact encrypted artifact and two fresh access vetoes. Eight one-use reads are allowed; the final-slot concurrency test has exactly one winner. Revocation, wrong bindings, uncertain state, corrupt storage, oversized ciphertext and replay fail closed; no child traffic or consumer binding is produced. Earlier failures exposed an omitted central REST route and excessive allocation for oversized ciphertext. The exact private POST is now admitted to its existing signature checks, and SQL rejects oversized records before transferring bytes to PHP. Neither the full byte limit nor the 128 MiB test limit was relaxed.

Follow-up `e6b0903d74aa1f068ecf0e482037f7bb552c715e` adds only a fixed disposable `read-capture <job UUID> <previously observed artifact SHA>` mode with strict PHP/Python argument guards, unchanged context shape, a bounded response and no retries. Frozen contracts remain byte-identical. Provider CI `38760789666` and combined gateway CI `38761348591` pass. The combined test verifies the closed recovered response against authenticated running context and original digest, exactly two actual Node vetoes, unchanged retained state, and denial after the original requester enters pending revocation. It cross-checks report provenance against both actual Git checkouts. These tests do not manufacture read claims or recipes for the protected runner and do not claim worker orchestration.


The private queue now resolves and retains the exact service-owned site/environment before asynchronous worker admission. It rechecks current content permission and configuration before delegated queue dispatch and again before retaining the response. Each callback uses the original mapped Google identity, original site configuration and current local registry policy inside the short ledger transaction; the former injectable membership-only check is removed. An active member with a removed content grant, a changed environment or missing/expired discovery is denied. Discovery observations may refresh, but cannot silently change the queued configuration. Maps lacking this original site binding are rejected rather than reconstructed from callback input. No network request is made inside that transaction; the provider remains responsible for independently checking current Google Directory state.

All 287 gateway tests and ESLint pass locally for this change, including permission changes across asynchronous admission, exact direct grants, changed source permissions/configuration, expired or missing discovery, restart and legacy-map refusal. The disposable transport gate now additionally removes local content permission and changes the original environment while membership remains enabled, requires retained-capture readback denial, then verifies unchanged original capture after restoration. That new seven-group actual-callback gate requires its own CI result. This source change does not install the production callback, configure a protected worker, activate access or establish team acceptance.


The first combined attempt at `57626a0`, run `38776664982`, passed identity, provider capture/recovery and the first three actual callback groups, then failed a new test assertion that a fresh qualification queue must refuse an environment changed before admission. The existing map must refuse that change; a separately fresh job may bind the newly authorized environment. The corrected fixture asserts queue denial only for removed content permission, retains both old-map recovery denials and adds a controlled fresh-environment admission test. The failed run remains evidence and does not qualify the complete transport suite.

### Private callback lifecycle integration

The entrypoint now uses a shared service lifecycle factory for optional private current-access callbacks. Configuration requires the existing Operations origin, Google delegation and a valid content workflow; preflight reports only configuration, never deployment or acceptance. No new credential, connector, workspace tool or staff enrollment is added. The actual disposable callback fixture uses the same factory and requires closed callbacks to refuse after shutdown. This source step does not supply protected-worker admission, report evidence, owner activation, deployment or ordinary-member ChatGPT acceptance.

### Qualified disabled-authority provider source

The first disabled-authority checkpoint selected provider `041150dc5db08e2f61787962baa7fc68e630b097` in both the runtime and copied-contract manifests. All eleven copied contracts/vectors are byte-identical to the previous e6b0903 checkpoint. All four provider [CI jobs in run 38780634932](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38780634932) pass. The native run verifies 263 private-reader checks (64 released), 212 requester checks, 58 retained-recovery checks, 179 disabled-authority checks and two 96-counter isolation groups under the unchanged 128 MiB limit. Memory remains 67,633,152 bytes through the original authority races and 76,021,760 after recovery. Both earlier out-of-memory runs remain retained.

This fixes retained private database readers and adds disabled evidence-authority storage; it does not activate policy or report admission. The provider workflow has no success artifact upload: evidence is its original completed CI log and source-bound installed-package checks, with a separately reproduced package. A legacy fixture warning reads a deliberately malformed synthetic audit without a job ID; the unchanged predicate still requires exactly one audit matching the real original job/requester/key/capture hash. It is documented, not suppressed or treated as a runtime waiver. This new provider pin requires separate combined gateway qualification; no deployed provider or access setting changes.

### Combined authority fixture handoff

Gateway dd2afe8 with provider041150d failed [combined run 38782044990](https://github.com/mrnwebdesigns/MRN/actions/runs/38782044990) after the provider suites and actual Node queue/context passed: the first actual capture was refused. Original artifact11995219410 (SHA256 `cc8722a67b2a54fb2aef9bf5fe226b155b5217fbeb32de9df56c0b99a6b7ccf4`) contains only identity and transport reports. This does not qualify provider adoption. Gateway852f66f adds bounded failure-only error/status and callback counts; diagnostic CI38783481809 still tests unchanged041150d.

The candidate provider035d401f6320a541567e754d240f7ca7a09c4413 changes only two disposable fixture files. It runs the actual Node callback under prepared epoch4 before synthetic Bob revision100 is recorded, asserts no prior requester revision and no remaining site lock, and separately requires actual signed revision1 refusal after100 while100 remains usable. It resets no history and changes no runtime contract, authority or limit. Both source manifests now select this candidate for combined testing; provider and combined gates must pass independently before qualification. The initial refusal remains under diagnostic confirmation. Last qualified combination is gateway2919b61/provider e6b0903.

The unchanged-order diagnostic run38783481809 at gateway852f66f records provider `mrn_access_veto`, HTTP500, one clear actual callback and zero refused callback answers; no complete capture report is produced. Candidate035d401 then failed its new direct-check assertion in provider run38783689448 because it expected the outer capture error instead of `Access::check`'s exact `mrn_access_replay`. Candidate9cf154e9bea679eae36dc8de762ec938b6f3abad corrects that one fixture expectation, retains both revision100 predicates, and emits a closed passing summary only after they hold. Current integration manifests select9cf for independent qualification. No runtime authorization predicate, history, limit or production state is changed; these failed runs remain evidence.

Both reordered combined runs38784024503 (gatewaye66cde7/provider035d401) and38784861698 (gatewayc380c12/provider9cf154e) pass all eight actual Node callback groups, then refuse the subsequent synthetic capture. The delayed Child fixture writes its separate synthetic mirror to pending-revocation/revision100 during Node's slow-read test. Provider candidateb429137850ddd78b611dfbb115921a669bb133c9 restores only the exact pre-barrier mirror file, asserts byte-identical durable requester history across that restoration and retains the no-site-lock assertion. The actual Node pending ledger is untouched. This changes only a fixture; source manifests select it for new standalone and combined qualification. Earlier failed runs and partial artifacts remain retained; no automatic retry, history reset or production change is included.

### Earlier qualified integration and proposed measurement format

Gateway `cdb482c0ec1552fbef1cc936277a3a7563d81477` with provider
`b429137850ddd78b611dfbb115921a669bb133c9` passes combined
[CI 38786103167](https://github.com/mrnwebdesigns/MRN/actions/runs/38786103167)
and [MRN QA 38786103075](https://github.com/mrnwebdesigns/MRN/actions/runs/38786103075).
Original artifact `11998137725` has ZIP SHA-256
`f677d9655697031cfd7286e1eececb502f300c7745c61e882990a61bddabd750`.
All four downloaded source-bound reports pass: ten identity, six native content,
eight actual Node access/lifecycle and six bounded transport groups. The original
log verifies prepared epoch 4, exact synthetic mirror restoration with unchanged
provider history, rejection of signed revision 1 after revision 100, and 185
combined authority checks. The earlier failed runs above remain retained.

The proposed private measurement contract has separate source provenance under
`test/fixtures/measurement-readback-candidate`. Its schema, vectors and design are
exact Git blobs from provider `11956fda5d5029ef7416b3f2368996e708253387`.
At that checkpoint the qualified runtime and existing contract-source manifests
both remained at `b429137` and the proposed endpoint was unregistered. The new
private runtime reader is recorded below; no service code loads these test files.
The gateway's six compatibility tests cover the real schema
compiler, canonical bytes, all 26 structural versus semantic negative vectors,
Ed25519 purpose separation, distinct packet/artifact/record/HTTP byte digests,
private scope and original assertion/transport time boundaries.

Review of preceding provider `4422781` reproduced a fixture verifier accepting
observations before assertion issuance or after assertion expiry while the job
remained valid. The corrected verifier and six new negative vectors reject both
counterexamples, exact expiry, job expiry and requests exceeding 20 seconds.
Inclusive issuance, one millisecond before expiry and exactly 20 seconds remain
valid. All nine provider Python test groups pass. The schema and design hashes
are unchanged; the corrected vector hash is
`81af70d88420b1856b8d062c4c5ba4e62876db35b5a4fa3ba610c8d617c185c1`.
Exact provider qualification is tracked in
[CI 38788996097](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38788996097).
Synthetic schema/signature vectors do not prove actual measurement acquisition.

Protected worker acquisition, independent evidence admission/signing, broader
content qualification, production deployment and ordinary-member ChatGPT
acceptance remain open. No Google access, key, policy, connector or production
runtime is changed by this checkpoint.

### Private measurement reader integration

Provider b83265a adds only the canonical private measurement route, never a
workspace tool or delegated staff capability. The runtime keeps separate
eight-use measurement and capture-recovery budgets, original requester/job
bindings, two fresh access vetoes outside its work transaction, current source
verification and closed bounded records. Native tests prove exact anonymous
401 and wrong-purpose 403 denials without claims, concurrent final-slot
exclusion and no child request during readback.

Independent review of the initial implementation found row locks fetched full
stored ciphertext before size validation. The correction locks option names
only and bounds record, budget and capture payload reads in SQL, including
post-commit readback. Three 8 MiB corruption cases exercise actual query shapes
under the original 128 MiB memory ceiling. Failed runs 38794600768 and
38795164062 remain retained: their GET probe incorrectly supplied a string body
to WordPress Requests. Corrected source bb1a8b0 passes run 38804071980; b83265a
adds the fixed private worker adapter and passes run 38804969514 with 138 checks.

The combined gateway test independently hashes source helpers from the pinned
Git blobs and verifies the exact original capture, record and binding. It
requires two actual Node clearances, then denies readback after local content
permission removal, environment mismatch and pending original-requester
revocation, retaining the unchanged capture. This remains disposable evidence;
protected acquisition, report signing/admission, remote backup and actual
ChatGPT acceptance are separate open gates.
