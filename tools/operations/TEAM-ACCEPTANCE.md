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

## Latest disposable evidence, 2026-10-19

Gateway `ed5dacb` with provider `d44b800`, MCP `3249bde`, legacy runner `d9f2eba`
and management worker `8465b25` passes all 496 hosted tests,
[combined CI 39283333559](https://github.com/mrnwebdesigns/MRN/actions/runs/39283333559)
and [MRN QA 39283333346](https://github.com/mrnwebdesigns/MRN/actions/runs/39283333346).
All six original reports and completed job logs were independently verified.
Artifact `12203650764` is 8836 bytes with SHA-256
`5a71e9894cf17502ba405cdc94bf7ced39e28c6fd6aa8e00f3e1102d0901daf2`.
Exact tested Operations runtime/workflow blobs match committed source.

This includes the disposable content edit, public readback and exact rollback,
ten delegated identity checks, seven actual source groups, twelve worker context
groups, source-owner cleanup and retention of the later uncertain capture lock.
The fixture uses synthetic OAuth, Directory, QA and backup authorities.
Consolidated provider `18bf0d4` now independently passes all four jobs in
[provider CI 39287071024](https://github.com/mrnwebdesigns/mrn-mainwp-operations-api/actions/runs/39287071024),
including both native report-retention cases with 13 checks each. Its tested
merge tree equals source and preserves current-main field/backup work. All four
gateway provenance records now select it, and the seventh report-custody gate
is enabled for a new combined run. That gateway result, hosted cache provenance,
protected rendered QA and report admission remain open.
Every ordinary-member and live-denial row above remains unverified.

## Earlier disposable checkpoints

Gateway `f13ec5d` with provider `b18592d`, MCP `3249bde` and runner `d9f2eba`
passes [combined CI 38906299558](https://github.com/mrnwebdesigns/MRN/actions/runs/38906299558)
and [MRN QA 38906299501](https://github.com/mrnwebdesigns/MRN/actions/runs/38906299501).
All 349 controlled tests pass. The retained original artifact `12048782275`
matches SHA256 `2c2c29c22b315d86efb8d5c1ed509340d3107401f297750cbe29da27c9b1b3de`;
the actual CI checkout's complete Operations subtree, workflow and imported
Fleet helpers match the committed source.

Its ten identity, eight native content, eleven private-access and six transport
groups include a structured page read and exact-site status-filtered search with
read-only OAuth scope, plus the existing approved field edit, independent
readback and exact rollback. Controlled fixtures separately cover registered
ACF values and field definitions. The separate management receipt checks
reject old purposes, mismatched scope/report/run, stale evidence and unrequested
sibling edits. That verifier is not a registered write workflow. Full management
qualification/admission and live deployment remain open. The fixture's OAuth,
Directory, QA signer and backup authority are synthetic; every ordinary-member
and live-denial row above remains unverified.

The disposable integration at gateway `a6eccc2` with provider `27ac5bc` and MCP
`3249bde` passed ten actual HTTP/MCP/WordPress transport checks, including
identity switching, concurrent reads, outsider/non-Google/missing-Directory
denial, existing-token suspension/revocation, read-only scope, nonce replay and
raw-service-route denial, and individual SQLite audit attribution.
[Run 38593686429](https://github.com/mrnwebdesigns/MRN/actions/runs/38593686429/job/115955067868)
uses synthetic OAuth/Directory identities, zero managed sites and no production
credentials. It improves backend integration evidence; every live acceptance row
above remains open.

The later native-child gateway run at `a3b55ea` with provider `e615e98`
([38608469036](https://github.com/mrnwebdesigns/MRN/actions/runs/38608469036))
discovers the actual disposable site but fails on exact-URL authorization before
content mutation. It does not pass content acceptance. Its planned edit/rollback
checks use synthetic OAuth/Directory, QA signatures and backup creation; even
after they pass, every live row above will still require its own evidence.

At gateway `b7851e1` with provider `aeb0f2c`,
[run 38613140657](https://github.com/mrnwebdesigns/MRN/actions/runs/38613140657)
passes the first URL read and dispatches the HTTP sync, then fails the fresh
same-URL readback. No gateway content mutation ran. The provider's passing
CLI-context native sequence does not close this HTTP integration failure.

The later gateway `7105c15` with provider `47de880` passes
[run 38617704685](https://github.com/mrnwebdesigns/MRN/actions/runs/38617704685).
All ten authorization checks and all six native Child groups pass, including
approved page-title editing, exact snapshot/public HTML/provider-status readback,
separately approved rollback to the original state and individual audit.
Artifact `11919998889` was downloaded and verified. This closes the disposable
native transport failure. Its synthetic OAuth/Directory, QA and backup inputs
remain explicit; every live acceptance row above remains open.

Protected runner `0fd0ff7` separately passed disposable code/content QA and both
required report uploads in [run 38593836989, attempt 2](https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/38593836989/attempts/2).
The eight downloaded reports and two HTTP parity manifests verify fixture
evidence retention after the approved Actions budget change. They do not prove
ordinary-member login, real-site content/code changes or live denial behavior.

## Evidence and signoff

For each actual run retain timestamp, immutable release/config references, operation IDs, exact site/record/path, approved preview digest, QA report/receipt IDs, backup verification, resulting checksums/readback and rollback result. Keep the individual Google identity in restricted audit storage; user-visible reports can reference the operation ID. Never record tokens, cookies, private keys or backend passwords.

A failed or unrun row remains open. Record source acceptance, CI/static qualification, deployment, installed readback, workspace publication and ordinary-member acceptance separately. The product is not complete while content editing, guarded code editing or real team acceptance remains unverified.
