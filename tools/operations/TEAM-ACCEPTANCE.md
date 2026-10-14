# Unified plugin acceptance record

Status: **not accepted**. This is a repeatable test record, not a staff onboarding form. Every active MRN Google Workspace member receives portfolio access automatically. No tester nomination, individual approval or personal backend credential is required.

## Release boundary

Use only the existing MRN Website Operations workspace plugin and `https://operations.mrnwebdesigns.com/mcp`. Record the reviewed Operations commit, provider version/artifact checksum, QA runner workflow commit, configuration digest and workspace tool-list digest before testing. All remain unverified for the unified release. Existing four-tool read-only acceptance does not cover this candidate.

Production deployment, service access configuration and each concrete site change require their existing approvals. A passing test never broadens the company code pilot or a qualified content adapter to another site. Preserve WPControl field releases and unrelated apps.

## Staff experience

An ordinary active member uses their own ChatGPT workspace session and their own Google sign-in. The member may be any active staff member; no per-person access grant is created for this test. Owner-only credentials and controlled fixture identities do not prove the ordinary-member experience.

| Step | Chat experience | Evidence required | Current result |
| --- | --- | --- | --- |
| Install | Add the one MRN Website Operations plugin from the MRN workspace | Existing plugin ID, visible published tool inventory, no second connector | Unverified for unified release |
| Sign in | Use the member's active MRN Google account | Verified individual identity in private service and provider audit; no Dashboard login or local setup | Unverified |
| Discover | “Show the websites I can manage and the available workflows.” | Portfolio directory, exact URL resolution, reported qualification limits | Unverified |
| Read | “Inspect the approved test website and show the current page content.” | Fresh single-site sync and exact record snapshot | Unverified |
| Preview content | Request a specific approved change on that record | Before/after preview, fresh precondition, typed adapter and retained migration record | Unverified |
| Apply content | Approve that exact preview in chat | Verified remote DB backup, per-person approval/execution audit, exact record readback and affected public-page verification | Unverified |
| Preview code | Request the qualified company-pilot code change | Exact file snapshot, source ownership, applicable isolated QA, source PR and merged-source proof or qualified snapshot evidence | Unverified |
| Apply code | Approve the exact source proposal where applicable, then the final live plan | Distinct approvals, remote DB backup, file hash and public/API verification | Unverified |
| Roll back code | “Restore the original file from that operation.” Review and approve the new exact plan | Original snapshot checksum restored, repeated source/QA/backup gates and verified public/API state | Unverified |

All results and necessary decisions must appear in chat. Any request for a WordPress account, Application Password, API key, terminal, Codex, SSH, repository creation or personal token is an acceptance failure.

## Denial and interruption checks

| Scenario | Required result | Current result |
| --- | --- | --- |
| Outside-domain Google account | Denied before portfolio/site data is returned | Controlled fixtures pass; live unverified |
| Non-Google login or linked non-Google primary identity | Denied, without accepting a caller-supplied email | Controlled fixtures pass; live unverified |
| Suspended, archived or removed Workspace member | Existing session denied by current Directory read before dispatch | Controlled fixtures pass; live unverified |
| Explicitly revoked access | Existing valid token is insufficient; revocation persists through automatic membership checks | Controlled fixtures pass; live unverified |
| Revoked during an operation | Next protected step stops; no further write is dispatched | Controlled fixtures pass; live unverified |
| Existing read-only OAuth token | Reads still work; preparing, approving, executing or recovering a change requires the additional guarded-write consent | Controlled JWT/HTTP/delegation fixtures pass; live unverified |
| Stale content or file snapshot | Fresh preparation required; no silent overwrite | Controlled fixtures pass for code and content; live unverified |
| Failed QA, missing backup or missing exact approval | No site mutation; failure is explained in chat | Controlled fixtures pass for code and content; live unverified |
| Lost mutation response | Uncertain outcome and retained lock; no automatic retry | Controlled fixtures pass for code and content; live unverified |

Use already-authorized test identities or separately reviewed administrative test changes. Do not suspend a real staff member, weaken a policy, forge a production identity or grant an outsider access to manufacture test evidence. A local negative test is not a live denial result.

The disposable integration at gateway `a6eccc2` with provider `27ac5bc` and MCP
`3249bde` passed ten actual HTTP/MCP/WordPress transport checks, including
identity switching, concurrent reads, outsider/non-Google/missing-Directory
denial, existing-token suspension/revocation, read-only scope, nonce replay and
raw-service-route denial, and individual SQLite audit attribution.
[Run 38593686429](https://github.com/mrnwebdesigns/MRN/actions/runs/38593686429/job/115955067868)
uses synthetic OAuth/Directory identities, zero managed sites and no production
credentials. It improves backend integration evidence; every live acceptance row
above remains open.

## Evidence and signoff

For each actual run retain timestamp, immutable release/config references, operation IDs, exact site/record/path, approved preview digest, QA report/receipt IDs, backup verification, resulting checksums/readback and rollback result. Keep the individual Google identity in restricted audit storage; user-visible reports can reference the operation ID. Never record tokens, cookies, private keys or backend passwords.

A failed or unrun row remains open. Record source acceptance, CI/static qualification, deployment, installed readback, workspace publication and ordinary-member acceptance separately. The product is not complete while content editing, guarded code editing or real team acceptance remains unverified.
