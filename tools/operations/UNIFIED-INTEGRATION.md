# Unified MRN MainWP integration

## Required team experience

One existing MRN Website Operations ChatGPT plugin at https://operations.mrnwebdesigns.com/mcp. Active MRN Google Workspace members get automatic portfolio access; there is no individual approval or enrollment step. Necessary approvals apply to concrete site changes. No teammate WordPress account, Application Password, API key, terminal, Codex, SSH or site token.

## Verified baseline, 2026-10-14

- Existing workspace plugin exposes four hosted tools. This source now registers 19 workflow tools; those new tools are not published or deployed.
- Canonical MainWP MCP is connected to wpcontrol.mrndev.io. Provider reports 107 discovered abilities; its current tool policy exposes 48. Hidden capabilities remain filtered; this inventory does not bypass that policy.
- Live code provider is 0.13.4. Code policy and signed QA are qualified only for the existing company pilot, not the managed portfolio.
- MainWP REST key was found through the Production Hub mainwp provider and authenticated successfully against the expected Dashboard. Fresh company-site sync and exact REST identity readback succeeded. The installed REST route index exposes native post/page CRUD; exact-site page search/detail reads succeeded. No content write was attempted.
- Generic post metadata does not establish qualified ACF, menu or media editing. The current native detail response omits title/modified time and may have empty post_content for builder pages. Typed inspect/precondition adapters are required.

## Identity and transport

Auth0's resource-specific Action accepts only the authenticating Google connection and a primary numeric Google identity with a verified exact MRN email domain. The gateway additionally pins the Auth0 connection ID, verifies issuer/audience/signature/expiry/scope, and asks the Dashboard for current staff authorization on every HTTP request. Plain email claims, linked non-Google primary accounts and caller metadata are not identity. Directory/customer/suspension/revocation checks repeat at backend dispatch.

Each call uses an independent Ed25519 assertion, separate from the protected QA key. The nonce binds service connection, inner ability, strict canonical argument digest and policy. Maximum lifetime is 60 seconds, bounded further by the caller token and challenge. Shared PHP/Node fixtures are in test/fixtures/delegation-v1.json; that public test key must never be enrolled.

The four-field challenge is nonce/expires_at/authenticated_connection_sha256/policy_sha256. All delegation endpoints use POST-only metadata to keep signed identities out of GET URLs and access logs. Read and single-site sync wrappers are non-destructive; only the mutating wrapper is destructive and requires confirmation. Gateway readiness rejects mismatched metadata before signing an assertion. A write retains the backend two-phase confirmation with the exact same envelope; no retry after a dispatched response is lost. The pinned service connection cannot fall back to an interactive Google lease or raw privileged child dispatch.

The source config keeps legacy deployment behavior until a reviewed migration enables googleWorkspace and delegation together. Failure or absence of the new provider denies authentication; there is no silent fallback. Identity keys are owner-only, non-symlink Ed25519 files. Audit events retain the validated individual subject, Google subject and email without tokens or argument payloads.

## Capability mapping

Observed 2026-10-14T10:57:16.039Z. Mapping is against this source checkout; connected_workflow does not mean deployed, write-qualified or workspace-enabled.

| Backend tool | Hosted workflow | Backend effect |
| --- | --- | --- |
| list_sites_v1 | Not yet integrated | Read |
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
| list_updates_v1 | Not yet integrated | Read |
| list_ignored_updates_v1 | Not yet integrated | Read |
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
| get_batch_job_status_v1 | Not yet integrated | Read |
| mrn_mainwp__start_database_backup_v1 | approved_operation | Guarded operation |
| mrn_mainwp__get_database_backup_status_v1 | approved_operation | Guarded operation |
| mrn_mainwp__get_google_fonts_status_v1 | read_website_details | Read |
| mrn_mainwp__get_stack_runtime_report_v1 | read_website_details | Read |
| mrn_mainwp__inspect_backup_retention_policy_v1 | read_website_details | Read |
| mrn_mainwp__apply_backup_retention_policy_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__get_stack_deployment_agent_status_v1 | read_website_details | Read |
| mrn_mainwp__qualify_stack_site_v1 | read_website_details | Read |
| mrn_mainwp__preflight_mu_release_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__preview_incomplete_rollout_reconciliation_v1 | Not yet integrated | Read |
| mrn_mainwp__install_mu_release_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__rollback_mu_release_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__configure_code_policy_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__get_code_access_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__inspect_code_file_v1 | inspect_website_code | Read |
| mrn_mainwp__qualify_code_storage_v1 | Not yet integrated | Guarded operation |
| mrn_mainwp__prepare_code_change_v1 | prepare_code_edit | Guarded operation |
| mrn_mainwp__approve_code_change_v1 | approve_operation | Guarded operation |
| mrn_mainwp__execute_code_change_v1 | execute_operation | Guarded operation |
| mrn_mainwp__get_code_operation_v1 | Not yet integrated | Read |
| mrn_mainwp__resolve_code_operation_v1 | Not yet integrated | Guarded operation |

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
