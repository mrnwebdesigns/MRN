# Unified MRN MainWP integration

## Required team experience

One existing MRN Website Operations ChatGPT plugin at https://operations.mrnwebdesigns.com/mcp. Active MRN Google Workspace members get automatic portfolio access; there is no individual approval or enrollment step. Necessary approvals apply to concrete site changes. No teammate WordPress account, Application Password, API key, terminal, Codex, SSH or site token.

## Verified baseline, 2026-10-14

- Existing workspace plugin exposes four hosted tools. This source now registers 21 workflow tools; those new tools are not published or deployed.
- Canonical MainWP MCP is connected to wpcontrol.mrndev.io. The fresh configured connection at 15:06 UTC reports 107 discovered abilities and exposes 52 tools (the earlier connection exposed 48). Its abilities resource is also policy-filtered; the other 55 are counted, not enumerated or qualified here. Hidden capabilities remain filtered; this inventory does not bypass that policy.
- Live code provider is 0.13.4. Code policy and signed QA are qualified only for the existing company pilot, not the managed portfolio.
- Provider identity candidate PR #28 passed disposable WordPress/MainWP HTTPS runtime enforcement at source `603c816`: individual principal switching, replay/suspension denial and raw service-route bypass denial. This uses synthetic Directory responses and does not prove live Google OAuth or hosted ingress acceptance.
- Provider content candidate PR #29 at `c4eb01ff7a52e76b7a19b94a33b24b5ccdf53afa` passed [full CI run 38578290602](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38578290602): 34 native HTTP checks, 23 WordPress/MySQL content checks and 13 filesystem/SQL parity measurements. This supersedes the earlier failed measurer checkpoint. The reusable helper is accepted for those demonstrated CLI-context semantics; the runner's independent HTTP proof is recorded below. Provider controller wiring and real-site qualification remain separate gates. These disposable checks used no production credentials.
- The newer provider checkpoint `27ac5bc1371db01db7c0d5cf5c418ef776e23e1e` passed [CI run 38593325241](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/38593325241): 34 native HTTP, 26 content, 13 parity, 12 profile-context and 25 controller lifecycle checks. It implements exact-target v2 lookup, checks parity under database locks and compares retained selector objects canonically. Its controller edit/rollback/replay/lost-response tests use real WordPress/MySQL and signed individual delegation, with synthetic child transport, QA signatures and backup receipts. It does not qualify live child transport, protected QA or remote backup creation. Gateway source `a6eccc2` pins this checkpoint and its CI hook and passed all ten actual gateway/MCP/WordPress transport cases in [run 38593686429](https://github.com/mrnwebdesigns/MRN/actions/runs/38593686429/job/115955067868). The report explicitly excludes production credentials, live Google sign-in, real site discovery, content/code writes and ChatGPT acceptance. The protected runner has updated the helper and passed its complete disposable rerun with both evidence bundles retained, as recorded below.
- MainWP REST key was found through the Production Hub mainwp provider and authenticated successfully against the expected Dashboard. Fresh company-site sync and exact REST identity readback succeeded. The installed REST route index exposes native post/page CRUD; exact-site page search/detail reads succeeded. No content write was attempted.
- Current gateway checkpoint `a3b55ea` pins provider `e615e98`, whose own disposable native MainWP Child edit/rollback and refusal checks pass. The combined [gateway run 38608469036](https://github.com/mrnwebdesigns/MRN/actions/runs/38608469036) passes the ten authorization cases and one-site discovery but fails at exact-URL `get-site-v1`, before content mutation. The provider target guard currently requires a numeric ID at a point where canonical URL resolution is required. Gateway native content acceptance remains failed until that integration is corrected and the complete lifecycle passes. Its harness explicitly uses synthetic OAuth/Directory, QA signatures and backup creation; it cannot establish live Google, remote backup or ChatGPT acceptance.
- Read-only authenticated Dashboard Add-ons inspection confirms Code Snippets 5.1.1 and File Uploader 5.0.2 enabled and licensed on Dashboard 6.3. The public v2 namespace index has 195 routes with no Dashboard extension export/download route, and the configured MCP has no such export tool. The official MainWP account download requires sign-in before its licensed Code Snippets artifact can be supplied to isolated code qualification. No license, plugin, snippet or host setting was changed, and no SSH retrieval was used. An installed extension is not evidence that its code lifecycle has passed in the disposable harness.
- Generic post metadata does not establish qualified ACF, menu or media editing. The current native detail response omits title/modified time and may have empty post_content for builder pages. Typed inspect/precondition adapters are required.

## Identity and transport

Auth0's resource-specific Action accepts only the authenticating Google connection and a primary numeric Google identity with a verified exact MRN email domain. The gateway additionally pins the Auth0 connection ID, verifies issuer/audience/signature/expiry/scope, and asks the Dashboard for current staff authorization on every HTTP request. Plain email claims, linked non-Google primary accounts and caller metadata are not identity. Directory/customer/suspension/revocation checks repeat at backend dispatch.

The existing OAuth permission is explicitly read-only. Candidate source requires the additional `mrn:operations:write` scope for all eight change-lifecycle tools, registry write authorization and delegated write challenges. It derives immutable scopes only from the verified JWT; roles and caller metadata cannot upgrade existing consent. Source `fa7672c` passed 197 controlled tests and both exact-head CI checks. The new Auth0 permission/grant and workspace metadata have not been activated; [the configuration review](deploy/TEAM-CONFIGURATION-REVIEW.md) records that separate gate.

Each call uses an independent Ed25519 assertion, separate from the protected QA key. The nonce binds service connection, inner ability, strict canonical argument digest and policy. Maximum lifetime is 60 seconds, bounded further by the caller token and challenge. Shared PHP/Node fixtures are in test/fixtures/delegation-v1.json; that public test key must never be enrolled.

The four-field challenge is nonce/expires_at/authenticated_connection_sha256/policy_sha256. All delegation endpoints use POST-only metadata to keep signed identities out of GET URLs and access logs. Read and single-site sync wrappers are non-destructive; only the mutating wrapper is destructive and requires confirmation. Gateway readiness rejects mismatched metadata before signing an assertion. A write retains the backend two-phase confirmation with the exact same envelope; no retry after a dispatched response is lost. The pinned service connection cannot fall back to an interactive Google lease or raw privileged child dispatch.

The source config keeps legacy deployment behavior until a reviewed migration enables googleWorkspace and delegation together. Failure or absence of the new provider denies authentication; there is no silent fallback. Identity keys are owner-only, non-symlink Ed25519 files. Audit events retain the validated individual subject, Google subject and email without tokens or argument payloads.

Native content reads repeat live staff authorization after each REST response and at the service return boundary. Revocation during a pending read withholds its result. Known MainWP content/QA/backup/lock/Directory blockers retain safe, fixed chat explanations; raw upstream messages are never reflected. Safe Mode and uncertain outcomes remain hard stops with no alternate-route retry. The full gateway suite passes 232 controlled tests; this is source evidence, not live revoked-account acceptance.

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

## Typed content workflow in source

The frozen `contracts/content-v1.json` matches the provider contract. The gateway supports inspection and preparation of one existing published post/page title, excerpt or content field. It verifies full semantic snapshots, one-field changes, the canonical plan descriptor and an immutable replayable migration retained centrally. There is no caller PHP, arbitrary metadata, create/delete/status change or per-site repository requirement.

The v1 provider policy lookup selects by site only and returns one exact-record profile, or null when the catalog is ambiguous. The gateway implements the provider's `get-content-policy-v2` contract, with the exact closed site/record/field tuple and unchanged v1 output. It prefers v2 when advertised; only absence permits the legacy lookup. Denial, malformed output, missing qualification or a failed v2 response never falls back. Only this exact v2 ability is added to the signed read allowlist; no registrar or policy-write tool is exposed. Controlled tests prove two different targets on one site, wrong-profile denial and the unchanged edit/rollback lifecycle. Provider `27ac5bc` passed exact-field selection, missing-target denial and v1 ambiguity checks in its disposable controller suite. A protected service-owned registrar, actual child transport and real-site profile qualification remain pending; this source adapter does not establish multiple-record production support. Qualification remains an MRN service responsibility, never a staff page/site enrollment or individual access-approval step.

The proposed provider catalog separates stable access-policy identity from immutable per-target qualification, so adding an unrelated record need not cancel an existing plan. Target replacement/revocation must invalidate that target's plans; access-policy changes still invalidate identity grants. Protected service-owned profile registration and independently qualified ACF scalar/reference, existing menu-item label/link and existing attachment title/caption/alt adapters remain implementation work. No generic metadata write, staff registration, real-site profile or production access is activated by the gateway contract.

The protected content receipt has its own signature prefix and binds the exact record, before/candidate hashes, plan, migration, qualified profile/runtime context and affected public paths. Code or preliminary source receipts cannot authorize content. Approval and execution require live Google membership; original-operator execution repeats current policy and baseline checks after the verified remote DB backup. Fresh full snapshot and exact public HTML text at a trusted selector must match after execution. A stale public page or uncertain response retains the lock; Fleet recovery cannot release it. Rollback requires new QA, approval and backup.

The gateway has controlled fixture coverage only. The separate runner draft PR #4 implements `content-qa.yml`, a fixed typed WordPress replay adapter, before/after visible-text browser checks and a separate content signer. [Disposable runtime CI at 6551079](https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/38581916586) passed page-content edit/rollback, normalized title, post excerpt, mandatory API/browser/accessibility/performance QA and unsafe-HTML/unnormalized-title/stale-render rejection. Cross-service receipt verification also passes. Production content policy remains disabled with no profiles or service actors; the runner additionally rejects all real sites until independent production-profile parity is qualified. A passing synthetic run cannot prove current production rendering dependencies match a pinned profile. Typed ACF, menu and media adapters remain required follow-up work; this phase does not make them available.

Runner source `65510797ffb0f0627c31c6cb5b38c7e038aa4b10` pins the accepted provider helper (`b87136783fbe97ce87ca093b772caf397285a6022ff6f5d1bc800362c05ebe1e`) and independently measures HTTP dependencies before and after QA. The same expected `parity_sha256` participates in both runtime/profile contexts and signer evidence. Only owner-policy option names reach the fixed measurer; no temporary WordPress plugin or inventory exclusion is introduced. Its explicit synthetic bootstrap produces only unsigned measurement data, never signable qualification artifacts. All 41 local tests, staged static QA and exact-head contracts/code-isolation/content-isolation CI passed. Wrong digests and substituting another record's baseline were rejected. The retained fixture manifests differ only in target metadata: the page has `_wp_page_template`, while the post has `_encloseme`. Each record needs its own complete qualified dependency evidence.

Runner source `0fd0ff76083b4350fd86488e282723e73b821c43` pins provider `27ac5bc` and helper SHA-256 `66110c269fe347648885fd12bce178e4ec73bc476dfdae80a4166064e15389ba`. All 41 local tests and staged MRN QA passed. [Run 38593836989, attempt 2](https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/38593836989/attempts/2) passed contracts, code isolation and content isolation, including both required evidence uploads. The first attempt failed only at report upload because the account artifact quota was exhausted. After the owner approved and saved the existing Actions budget at $5/month with cutoff and alerts preserved, only the failed jobs were rerun at the same source. Both bundles were downloaded and checked: eight passing QA reports with mandatory API/browser/accessibility/performance rows, plus distinct page/post HTTP parity manifests. Runtime checks also rejected unsafe HTML, unnormalized title, stale rendering, wrong parity and a cross-record baseline. Code artifact `11910930605` has ZIP SHA-256 `7f3cd8dd96a241478a5438ca4536d455afb42a74fedfb0b7d15b6343abfe9754`; content artifact `11909304607` has ZIP SHA-256 `1af30db4462f7d75d7a626ce461a72f6ffea4f0494b2e5ca4e664e9da2d1d068`. No required gate, retention setting or stored evidence was changed. This qualifies the demonstrated disposable runtime only; real-site profile parity and team acceptance remain unverified.

Portfolio updates and existing batch-job status are bounded reads through the same signed identity transport. Updates are explicitly labeled cached Dashboard inventory; they do not trigger bulk child sync or replace exact-site inspection. Batch reads never start/retry a job. Provider-side adapters passed the disposable native HTTP checks noted above, including native batch denial propagation. The provider source remains a draft; its controller lifecycle passed with a synthetic child connection, while actual child transport remains unqualified. Real hosted execution remains unverified, and these source mappings are not deployed capabilities.

Live gateway readback reports `publicHtmlContentVerified` with method `exact-selector-html-text` and explicitly reports `liveBrowserVerified: false`. It does not label an HTTP/HTML comparison as live browser visibility. The protected disposable runtime and final team acceptance have their own browser evidence.

`get_operation` accepts optional `refreshProvider: true` for an existing retained content/code plan. It resolves the exact current MainWP directory identity and reads `get-content-operation-v1` or `get-code-operation-v1` under the verified Google actor. It checks the operation, site, target/file and stored plan hashes, withholds results after access revocation, and returns only bounded status metadata. This is a Dashboard audit read, not a child sync or current-live-state check. Provider completion or owner-side resolution never upgrades local uncertainty, retries execution, releases its lock or authorizes rollback. An ordinary local-only operation read remains available when provider status is unavailable. Controlled HTTP-tool and lifecycle tests pass; live end-to-end provider status remains unverified.

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
