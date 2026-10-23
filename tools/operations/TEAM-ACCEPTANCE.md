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

## Latest source evidence, 2026-10-23

Capture/recovery plus measurement is qualified at gateway
`6b484e3e365fb05e28d279d564f863a5279d5861`: combined CI `39999779123`
and MRN QA `39999779280` pass, with all eight original reports and their
immutable source pins independently verified. The complete native hook ran
186,981 ms within its fixed fixture allocation, preserved the original grant
and Child content, and completed revocation and callback cleanup.

The next source-context composition at `b6a6fc9` failed run `40004266848`
on its first valid signed lookup after capture/recovery/measurement passed.
Its provider refusal and original artifact are retained in
`UNIFIED-INTEGRATION.md`; the gate remains closed. The additive v2 owner
catalog resolver passes 647 local gateway tests but still requires genuine
pre-queue provider preparation and native qualification. Neither checkpoint
changes the live acceptance rows above.

The expanded private original-capture/recovery composition is now qualified
for its disposable native scope at gateway
`948f6d6da3d9c3af0a2a07c39a42ca584bc02807`, provider fixture
`f7ca46f54ca383d5708548652f0d6a5b3d334bf7` and transport
`16a0e1e719077deb98f8d3b9a0ca57e85b301406`.
[Combined CI 39985770653](https://github.com/mrnwebdesigns/MRN/actions/runs/39985770653)
and [MRN QA 39985770424](https://github.com/mrnwebdesigns/MRN/actions/runs/39985770424)
pass: 621 gateway tests, 13 Fleet regressions and eight original runtime reports.
Artifact `12517251055` independently matches SHA-256
`62c8c65006bcd492b01ffdc5e74605be5777e09f9b5766692740ff4aa5ce71b4`.
The 145,440 ms native hook proves exactly one capture, lost-response recovery
after SQLite reopen, identical data, 58 fresh signed staff checks, revocation
refusal, unchanged Child content and callback cleanup. Original deadlines stay
unchanged. This closes that private source gate only; every live team acceptance
row above remains unverified, and ordinary plugin/theme maintenance still needs
the Google-delegated chat integration.

Earlier candidates failed and remain recorded:
native runs `39978255035`, `39979454472` and `39983906354` failed during capture.
The second stopped after 60,068 ms despite a 120-second client limit; after the
isolated proxy correction, the third hit the 170-second cleanup deadline.
The cause is still under investigation. Gateway `a182a7e` passed 619 local tests; `a82c61b` adds bounded
HTTP failure diagnostics with six focused tests and staged MRN QA passing.
Those failed results do not override the later passing checkpoint, and none
establishes any live acceptance row.

Gateway `84aaf09d4279370e6dc28e5ec2ec87be5d5a6e6a` with provider
`a199464c725d01611164d07b77183727c98ebf5e` and MCP transport
`16a0e1e719077deb98f8d3b9a0ca57e85b301406` passes
[combined CI 39936250677](https://github.com/mrnwebdesigns/MRN/actions/runs/39936250677)
and [MRN QA 39936250701](https://github.com/mrnwebdesigns/MRN/actions/runs/39936250701).
All 609 gateway tests, 13 Fleet regressions and eight runtime reports pass.
Independent verification confirms 61 provider native checks, 29 private HTTP
route checks, exact two-call MCP confirmation with one confirmed queue POST,
actual TLS access/revocation callbacks and cleanup. Artifact `12494652971`
has SHA-256 `0596fccc9c1438009d5de76d22b918a551bd6c5c7fd5476ff09b20f2f3c42ce1`;
the five relevant tested source objects match the exact gateway composition.
The source-only status and remaining boundaries are recorded in
[UNIFIED-INTEGRATION.md](UNIFIED-INTEGRATION.md#current-content-integration-2026-10-23).

This replaces the older candidate checkpoint below. It does not establish
production deployment, real Google identity/denial or ordinary-member acceptance.
Every live acceptance row above remains unverified. No staff nomination or
individual access approval is introduced.

## Earlier candidate evidence, 2026-10-22

The earlier isolated candidate was provider
`534990b273c9ad70f4290d408b6f2190751e5e78`, which adds the five exact private
POST routes missing from the central REST guard. All twenty imported contract
and fixture files match immutable source; signature, capability and machine-only
gates remain required. Provider CI `39837935867` and the new gateway combined
run remain separate qualification gates. The previous provider capture failure
at `6fb4718` remains open until the new native result identifies and proves it.
The candidate fixes the REST test helper to send actual canonical JSON and adds
two WordPress parser regressions. Required provider native checks are now 52,
with the same 29 HTTP route checks; this remains test selection only.

Gateway `dbd4ddb` passed hosted MRN QA `39832745950` and controlled contracts,
but combined `39832746016` failed at the first context callback with
`mrn_delegation_required`, after a successful actual signed queue. Its retained
artifact and exact tested source were independently verified, including route
cleanup. No production or ordinary-member acceptance follows from these results.

Gateway `1a3c1c7` passed MRN QA `39835946602`, but combined `39835946576`
failed a final queue network-count assertion after its eight behavioral groups
passed. The corrected proof requires the canonical two MCP confirmation calls
with identical signed input, zero queue POSTs before confirmation and one after.
Actual TLS access/revocation observations are retained in artifact `12451735525`;
the failed overall run is not accepted as complete integration. Provider native
capture CI `39835510055` also failed and remains a separate gate.

The preceding native queue/context/access fixture used provider
`b81a0dbe4d4be177480c9497bad9533831ff9b08` solely as its disposable test candidate.
All four provider jobs in `39829662335` pass. Gateway `e593e98` run
`39830911903` failed two stale provenance assertions and skipped native testing;
its earlier 609-test local pass preceded the runtime pin change. The correction
independently verifies all eleven imported qualification files and preserves the
separate measurement candidate source, then aligns only the runtime references
and exact test assertion. After the correction and indirect dependency patch,
all 609 gateway tests, 13 Fleet regressions, lint and the zero-vulnerability
dependency audit pass. The new combined proof remains pending.
Required results are actual signed queue,
provider-generated TLS callback and identity readback, revocation denial,
blocked later dispatch, unchanged Child content and route cleanup. The strict
sidecar is separate from the unchanged original descriptor/profile/deadlines.
This adds no production registration, access grant or ordinary-member proof.

Gateway `47a1bde` passes [combined CI 39434418265](https://github.com/mrnwebdesigns/MRN/actions/runs/39434418265)
and [MRN QA 39434418251](https://github.com/mrnwebdesigns/MRN/actions/runs/39434418251).
The eight original artifacts and tested source blobs were independently verified,
including the complete parent preservation barrier and immutable review anchor.
Artifact `12272697476` SHA-256 is
`b14bdd9b45a505d89e778210d9e52d08127cd985f0afe838e58711add58529e2`.

The subsequent private one-time queue passes 593 local gateway tests and lint.
It covers both signed confirmation phases, actual SQLite close/reopen, a new
Google login, concurrent claims, loss/corruption, revocation, current source pins
and unchanged original expiry. These tests use controlled identities and a
provider fixture. Actual v2 queue/capture/source/rendered-QA/report integration,
retry-safe draft creation and every real ChatGPT row above remain unverified.
No production policy, tool registration or qualification flag is enabled.

The next current-access slice passes 602 local gateway tests, ESLint and a clean
dependency audit. It adds the exact private v2 callback, original-job binding,
signed protocol separation, atomic bounded query consumption, persistent
revocation and a tested public MCP input-element ceiling. Controlled tests cover
actual HTTP, SQLite reopen, replay, history loss and the full 512-query limit.
They do not prove live Google Directory state or an ordinary member's session.
The prior queue commit `d2b31fa` passed hosted MRN QA `39823890828`, but Operations
`39823890823` failed on MCP SDK dependencies and skipped native integration.
The dependency is now pinned to 1.32.1 with the required structural input bound;
the new combined hosted result is still pending. The provider source remains
`f084797` at that checkpoint; the new disposable candidate above requires its
own independent qualification.

The private context reader raises the passing gateway suite to 606 tests. It
uses the original observed queue, separate registrar authority, fixed existing-v2
route/purpose, fresh staff/readiness checks and bounded expiry; changed or late
responses cannot authorize continuation. These controlled tests exercise the
gateway callback components but do not manufacture native provider proof.
Gateway `ff0bbb4` hosted MRN QA `39827377578` passes; its controlled-contract job
passes and native integration in `39827377501` now passes. Actual v2 context
generation, callback transport and every real ChatGPT row remain open.

## Earlier disposable evidence, 2026-10-20

Gateway `0ce3f14` is the latest independently accepted combined checkpoint:
[CI 39428046110](https://github.com/mrnwebdesigns/MRN/actions/runs/39428046110)
and [MRN QA 39428045661](https://github.com/mrnwebdesigns/MRN/actions/runs/39428045661)
pass, including 574 controlled tests and 13 Fleet regressions.
All eight original reports and relevant tested source/workflow/Fleet blobs
match committed source, including actual TLS/MCP report custody, native content
edit/readback/exact rollback, original revocation denial and source reconstruction.
The new actual signed-preview/binding test recovers a lost response after SQLite
reopen and fresh same-person authentication without rebinding or extending the
original grant. Independent parent SQL/key/Child preservation also passes.
Artifact `12266709930` is 11990 bytes, SHA-256
`16ba545b15892959845ac75cff85d496b28ec105e572ef21b50ad4630e510fe1`.
The synthetic identity, remote-backup, hosted-provenance and rendered-QA limits
remain explicit. Every ordinary-member and live-denial row above is still open.

Provider `293b138` passes all four component CI jobs in
`39428711609` with exact tested source. Whole-record password-field refusal
and draft-date preservation pass 81 licensed local ACF PRO/native checks.
That local result does not establish hosted ACF PRO or MainWP transport coverage.
The newly pinned gateway `1ffa31f` passes 574 controlled tests, 13 Fleet
regressions and MRN QA. Combined `39431563142` fails at the provider parent
completion marker after all five inner v2 binding checks pass. Three partial
reports are insufficient: full parent preservation and later groups remain
unverified. A source inspection identifies non-atomic marker publication;
the actual failed marker bytes were not retained. This failure is not waived.
The current gateway pins fixture-only correction `f084797`, with atomic private
marker publication, fresh-stat validation and closed host/process diagnostics.
All eight files match review; thirty filesystem checks, provider full/staged
MRN QA and syntax checks pass. Product runtime and contracts are unchanged.
Its component CI `39434147170` and new combined run remain required, including
the original parent preservation and every later integration report.

## Earlier binding failures and their correction

Gateway `37fe496` passed 571 controlled tests but failed combined CI
`39392624734` at the parent-state fingerprint, before the clone or v2 hook.
The statistics failure's cause remains unknown. A separate review found missing
coverage for the management evidence marker, anchor and audit options.
Gateway `9e1262a` pins provider `a4ccb0c`, which adds that coverage, clone-only
negative checks and bounded failure diagnostics without changing runtime code
or bounds. Hosted MRN QA and 571 controlled tests pass. Combined CI
`39417143399` failed before cloning: 1,208 protected option rows occupied
78,870,591 bytes, above the unchanged 67,108,864-byte bound. SQL and numeric
parsing succeeded. Provider component CI `39416147753` separately failed an
earlier native sync preflight because the HTTP user could not read the
synthetic key directory.

Provider candidate `3d0e839` runs the binding fixture at the earlier coherent
parent checkpoint, prepares disabled management authority only inside the
separate clone, and retains every later large-history test. Normal native
registration creates keys as the actual HTTP user with unchanged private modes.
Its 12 committed files match the reviewed fixture/documentation bytes; product
runtime and contracts are unchanged. Gateway `402675c` passed all 571 controlled
tests and hosted MRN QA, but combined CI `39422160292` failed in the original
preview phase with `MAINWP_FAILED`. The early parent fingerprint contained
42 rows / 747,017 bytes; clone preparation and installed source verification
passed. No successful bind or complete parent-preservation barrier is claimed.
Independent source review found that the three registered v2 execution routes
were absent from the service REST guard. Provider component CI `39421841230`
separately exhausted the unchanged 128 MiB limit at `source-native-start`.
Both blockers required corrected native runs before acceptance.
The replacement provider candidate `a6611f1` narrowly admits the three exact
private v2 routes and preserves existing denials. It adds actual HTTP coverage
and isolates the same source checks in a fresh PHP process at 128 MiB, with
unchanged original authority and expiry. All eight committed files match the
reviewed source; local provider MRN QA passes. The gateway pins that candidate
and adds bounded, body-free fixture diagnostics. Provider CI `39425106965`
passes all four jobs; its tested merge has the exact source tree. The native
log contains 121 JSON groups, including all nine binding HTTP checks and the
unchanged source-control/native/storage/context groups under 128 MiB.
Gateway `1fdbe15` passes 574 controlled tests, 13 Fleet regressions and hosted
MRN QA, but combined CI `39425348769` stops at candidate validation with
`CONTENT_MANAGEMENT_MISMATCH`. Readiness, signed preview and migration retrieval
were reached; binding and the complete parent barrier remain unverified.
An independent class-composition reproduction identifies scoped preview
calling an unscoped-only registry reader, then selecting the legacy protocol.
The gateway now pins correction `affb7e9`: a boolean readiness check is limited
to the exact verified preview operation and fresh preparer authorization.
Private keys and control permissions remain unchanged. Configured unavailable
state is refused, while never-configured state retains legacy behavior.
All six committed files match review; local full/staged MRN QA pass. Component
CI `39427741941` and combined CI `39428046110` subsequently pass, closing these
isolated integration failures. They do not close production or team acceptance.
Current implementation and remaining qualification are in `UNIFIED-INTEGRATION.md`.

## Earlier disposable checkpoints

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
