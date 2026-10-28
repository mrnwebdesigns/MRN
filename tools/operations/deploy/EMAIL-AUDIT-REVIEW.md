# Hosted email audit source and deployment review

This is a source candidate for the existing MRN Website Operations plugin at
`https://operations.mrnwebdesigns.com/mcp`. It is not a deployed capability or
ordinary-team acceptance. No second connector, staff enrollment or personal key
is required. The existing native audit protocol is `mrn-email-address-audit-v1`;
reader readiness is determined by that protocol and returned coverage, not an
allowlist of one reader version.

## Team workflow

The same Google-authenticated plugin exposes `start_email_address_audit`,
`continue_email_address_audit`, `get_email_address_audit` and
`export_email_address_audit` when the reviewed service feature is configured.
Start requires one email address and explicit exact sites or explicit fleet
scope. Continue performs bounded serial reads and persists checkpoints; no
background task or website settings write is implied. MainWP inventory sync is
allowed through the existing signed sync wrapper.

The shared native engine retains explicit coverage, failed/unsupported sites,
stale inventory, form states and recipient/configuration categories. A match
proves stored configuration only, not an actual send, delivery or inbox result.
No match with incomplete coverage cannot establish absence.

Every backend call carries the existing Ed25519-signed, resource-bound Google
identity. The gateway rechecks the active Directory identity and all exact site
ACLs before releasing results. The provider repeats active staff and site ACL
checks after its audit read. Caller-supplied email/identity, raw REST, snippets,
alternate credentials and a shared Dashboard login are not accepted fallbacks.

Jobs and reports are private to the verified Google subject, connection and
email. They use separate owner-keyed SQLite tables, outside generic operation
history. Another active staff member cannot list/read/continue/export them.
Exports return authenticated MCP resource links with MIME type, size and SHA256.
Each `resources/read` repeats current Google membership, ownership, inventory and
site authorization. An old export is refused after its job changes. ChatGPT's
actual report presentation/download still requires live acceptance.

Storage is bounded: 10 MiB per job/report, 20 jobs and 50 reports per identity,
200 jobs and 500 reports globally, and 512 MiB aggregate payload. Duplicate
exports of the same state reuse their report. At capacity the service refuses
new evidence; an operator must review retention. This candidate does not
silently delete evidence or expose staff retention controls. The existing
private SQLite location and backup/access protections apply. Process locks
recover only when the owning PID is demonstrably dead; age does not unlock them.

## Exact source composition

`../test/runtime/email-audit-sources.json` locks the provider and MCP commits,
MCP dependency lock and built entrypoint digest for the independent audit CI job.
The native content/code qualification pins in `sources.json` are unchanged.

The provider composes unified base `f9571258ab9fc6f307fd0af8a45d392a050bc778`
with the native audit provider from deployed 0.13.13 source
`a690f666b2329699d76db7ce0725597ea77730c1`. Its maintenance implementation
remains intact; it is still a 0.14.0 candidate, not permission to replace the
installed Dashboard provider. The MCP adapter starts from accepted main
`5b563ffc8b93baad3c4d4e12eef0d0cb1d29279b`, preserving the accepted native
`e7a7626dc190b3ade5e85878805b50f2849582d3` behavior and local custody.

The gateway loads the shared engine from an operator-installed immutable MCP
package. `emailAudit.enginePath` must name its absolute built entrypoint and
`emailAudit.engineSha256` must match. Startup verifies a regular, non-symlink,
single-link file with no group/other write permission and the expected engine
contract. This entrypoint check does not hash every imported dependency: the
complete exact source/dependency package and service filesystem permissions
must also be verified when packaging and installing. Chat cannot select code,
paths, keys or hashes. Configured Codex/Claude clients are not repointed.

## Bounded continuation cancellation

The source composition includes the accepted native timeout fix `d486395`
(merged as `cece5a5`). The shared engine carries the original per-call limit and
absolute batch deadline into the hosted adapter. Its private timer covers both
the signed delegation challenge and read. Only an eligible clipped continuation
worker request cancelled with that exact local abort-reason object may become
pending; remote SDK timeout codes, injected `ETIMEDOUT`, access errors and unknown
backend errors cannot impersonate this proof. Inventory and authorization remain
outside worker deferral. No request limit is increased and no retry is added.

Actual SDK cancellation tests verify identity of the abort reason and downstream
cancellation notification. Hosted real-engine tests verify saved pages/cursor,
resume without replaying completed preparation, cancelled challenges, and revoked
membership withholding results. Late success is withheld; clock rollback cannot
manufacture a pending result. These are isolated source tests, separate from
native WordPress composition, production Google access and ChatGPT acceptance.

## Required production review and gates

Read-only service observation on 2026-10-28 found gateway `a41d1ba7`, MCP
`3249bde6`, `writesEnabled=false`, and Google-only delegation unconfigured.
Those are observed deployment facts, not instructions to alter them. Before
activation, refresh the facts and review a concrete package/configuration delta:

1. Qualify the exact provider/MCP/gateway composition in disposable native
   WordPress, including signed Google delegation, exact target/ACL denial,
   interrupted resume and export/read. The current signed-transport fixture
   uses the real engine and cryptography but a synthetic native transport.
2. Resolve the existing Google/Auth0 setup review in
   `TEAM-CONFIGURATION-REVIEW.md`, including production Google credentials and
   impact on other apps sharing the connection. Review the resource-specific
   Action, pinned connection/customer, existing live Directory lookup, delegated
   backend policy and central signer. Keep secrets inside approved services.
3. Review an exact Dashboard package, backup/restore plan and provider policy
   delta. Installation requires the Dashboard's fresh verified remote backup,
   explicit target approval and installed hash/ability readback. A source PR
   cannot authorize provider installation or new access.
4. Review an exact MRN Apps release and configuration delta for the same endpoint.
   Enable `auth.googleWorkspace`, `delegation` and `emailAudit` together; preserve
   `writesEnabled=false` and unrelated features. No write OAuth scope, GitHub
   token, content/code runner or new team plugin is needed for this read-only
   slice. Existing team domain access follows live active Google membership.
5. Through an ordinary staff account in the existing ChatGPT workspace plugin,
   sign in, discover sites, audit the company pilot, continue/resume, read results
   and obtain both export formats and coverage. Verify outsider, suspended,
   revoked and cross-user report denial. This verifies the product experience;
   it is not an approval requirement for each team member.

Source tests do not close these gates. The company pilot is
`https://mrnwebdesigns.com`; client-reader rollout is separately owned and
approved by MainWP MCP Major Upgrade. This thread does not change child readers.
For rollback, stop the candidate service, restore its reviewed prior release and
configuration, and keep private audit tables retained/inaccessible. Use the
separately verified Dashboard rollback for a provider deployment. Never weaken
identity checks or switch credentials to recover access.

## Source qualification boundaries

The source suite covers native engine regressions; real engine with signed
delegation; protocol tool discovery and protected resource reads; per-user
custody; revocation before/during read; inventory drift; ambiguous sync readback;
locking; export freshness; CSV formula escaping; storage limits; and provider
native permission/attribution regressions on PHP 8.2 and 8.4. MRN QA checks the
changed source and staged snapshots. No product frontend is changed, so browser
accessibility/performance rows do not apply to this source slice. Live WordPress
API smoke and ChatGPT presentation remain unverified.

The wider unified plugin is still incomplete: protected content-source/runtime
qualification, qualified content/code edits and rollback, service activation,
workspace availability and ordinary-team acceptance retain their own gates.

### Native error-envelope qualification, 2026-10-28

The shared-engine composition now reproduces reader absence through the actual
delegated error-envelope shape. Previously it became a generic fatal
`MAINWP_FAILED` and prevented the audit from returning explicit incomplete-site
coverage. The gateway recognizes exactly four provider inspection reasons:
reader unavailable, child transport failure, stale inventory and inspection
failure. Only the native audit inspection can turn those reasons into a failed
site row; authorization, inventory and other operations still stop on them.
The canonical MCP write-only-input error format is recognized alongside its
older message format, without copying remote prose into results.

Tests require incomplete coverage, no implicit scan retry, fresh authorization
before result release and successful explicit retry after the fixture reader
recovers. Permission, identity, contract, unknown-error and mid-read revocation
refusals remain blocking. These are source/transport fixtures, not native Child
or live team acceptance; the remaining deployment gates above are unchanged.
