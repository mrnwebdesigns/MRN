# Existing-worker bootstrap normative semantics candidate

Disabled source candidate, 2026-10-24. Extends the reviewed draft with an
explicit run_policy_sha256 pin for complete protected owner/environment policy;
not a deployed configuration, frozen provider contract, or activation approval.

## Trusted inputs and exact request

The network request is exactly the existing worker pointer (dispatch_id and
context_sha256); it cannot supply a person, site, run, policy, key, expiry,
protocol, URL or replacement original. The fixed bootstrap origin/path is the
one in the draft wire, with TLS verification, no redirects/proxies/retries.

Before interpreting a response, the protected supervisor must already hold:
- the reviewed bootstrap contract bytes and digest;
- the complete bootstrap policy and complete worker policy, loaded from
  supervisor-owned configuration with independently provisioned expected hashes;
- the registrar and identity Ed25519 public keys and expected key IDs;
- independently authenticated current GitHub run provenance, including run ID,
  attempt, repository/name/owner IDs, App actor, workflow ID/path/source, ref,
  environment and runner environment. Workflow request inputs and returned
  records cannot supply these expected values.

The minimal observed_run object is only a projection of this full protected
provenance. It is not itself an authentication API. The verifier must match
its run ID/attempt/repository ID/source/App actor and independently verify the
configured workflow/ref/owner/environment before use. Missing provenance is a
refusal; a caller email or signed bootstrap response cannot replace it.

## Exact encoding, hashes and units

All JSON objects are closed and canonical UTF-8 using the existing delegation
canonical encoder. Reject duplicate keys, noncanonical bytes, malformed UTF-8,
unknown fields and noncanonical base64, even when a signature verifies.

SHA-256 values are lowercase hexadecimal. A contract hash is the SHA-256 of
its immutable file bytes. A policy hash is the SHA-256 of canonical JSON bytes
of the entire corresponding closed policy object, not a selected subset.
run_policy_sha256 pins the closed repository_owner_id/environment/runner_environment
configuration used to compare independently authenticated current run provenance.
Public-key fingerprints hash the public Ed25519 SPKI DER encoding; reject
private keys, other algorithms, absent trust, changed key IDs or fingerprints,
and equal registrar/identity key material. No root, QA or identity fallback
may sign or verify either bootstrap signature domain.

commitment_sha256 hashes the exact canonical signed-commitment ENVELOPE bytes,
including its key ID and signature, not merely the decoded commitment claims.
worker_history_sha256 is the existing final three-row original worker-journal
digest: canonical array of SELECT projection original, ordinal, body, digest,
dispatch, run ordered by ordinal. Each body is the exact retained canonical
string, and each row's digest is SHA-256 of that body's UTF-8 bytes. Compute
this after the observed worker event and before appending the separate
bootstrap observation. Do not insert bootstrap observations into worker history.

Binding created_at/expires_at and job_intent_ttl_seconds use seconds. The
prepared requested_at, observed observed_at, original_expires_at and local
receipt clocks use integer milliseconds. Every timestamp must be a positive
safe integer. The original binding lifetime is <=900 seconds; worker lifetime
ends at min(binding.expires_at*1000, requested_at+300000). The complete bootstrap
read, including identity/staff checks, receipt, validation and retention, takes
<5000 wall-clock AND monotonic milliseconds, with no backward clock movement.
Neither signatures nor fresh login/readback extend those limits.

## Cross-record relations

Both envelopes use only their distinct bootstrap purpose prefixes. The
commitment uses the fixed service issuer and bootstrap audience; the observation
uses the exact GitHub run URL from independently authenticated expected run.
Both bind the independently selected bootstrap contract and policy hashes.

The commitment context and observation.retained.context must be identical.
context.management_context_sha256 must hash its complete binding. The binding
must satisfy the unchanged existing-v2 protocol and six-contract map, exact
HTTPS site/target constraints and original lifetime. The context evidence
policy epoch and digest must match the complete configured worker policy.
The binding provider source and provenance runner source are distinct anchors;
never equate or replace them. The pointer hashes that complete context. The commitment request is the exact
existing dispatch request: pointer, schema2 and unchanged worker wire hash.

The retained observation request must equal the commitment request; its
context/evidence-policy hashes, requested_at, original_expires_at and App actor
must equal the corresponding commitment values. The retained provenance must
match every workflow/source/engine/runtime field in the independently configured
worker policy, the exact current run/attempt, and the commitment dispatch ID.
Its dispatch_request_sha256 and dispatch_record_sha256 hash the exact complete
request and retained observation. The retained observation run ID must match
both provenance and the independently authenticated run. observed_at is >=
requested_at and <= receipt time; requested_at is >= binding creation and both
original/worker deadlines must remain valid through final retention/readback.

The worker-history digest is a registrar attestation to checked server state;
a supervisor lacking the actual journal must not label it independent SQL proof.

## Durable state transitions and release

A genuinely new original snapshots the complete bootstrap configuration/key
policy digest and seeds an unused bootstrap marker in the SAME transaction as
the original. No configuration addition, new login, restart, missing row or
older original may initialize or backfill it.

The existing one-use worker prepare transaction atomically retains the exact
signed bootstrap commitment, its digest and original/worker-journal anchors
BEFORE GitHub dispatch. It also advances the separately original-anchored
bootstrap progress. Failed/uncertain/lost dispatch is terminal for this original.

Only the dispatcher that has independently verified the actual protected run
may atomically append its worker observation and the separate signed bootstrap
observation anchored to the resulting worker journal and saved commitment.
Caller request data is not an observation. Signer/key/policy/source changes,
missing or inconsistent history, or partial progress make the original unusable.

Delivery verifies exact hosted OIDC and current original staff/policy/source/
revocation/expiry. After all asynchronous reads complete, a final transaction
revalidates the original progress, journal relations, key/policy fingerprints,
OIDC claims and deadlines, and atomically compare-and-swaps unused delivery to
consumed BEFORE response release. No provider operation is performed or charged
by metadata delivery itself; actual provider requests retain their existing
64 worker/512 access/48 source counters and original gates. Failure after consume
never refills the marker, repairs history, redispatches or creates a replacement.

## Supervisor persistence and limits of proof

The supervisor validates both envelopes against independent trusted inputs and
current run, then publishes a private owner-only retained original exactly once
before the separate identity-signed worker readback. Publication must be atomic,
no-replace, bounded, with no symlink/hardlink/path substitution, and durable before
network continuation. The retained bytes, original binding, commitment/response
hashes, receipt bounds and trust-policy digests are immutable. Candidate containers
cannot read/write these files or the supervisor trust configuration.

A restart may validate and reuse that exact original only within its unchanged
original/worker lifetime and independent current run. It cannot redeliver,
overwrite, repair missing/changed state or obtain another original. A lost
bootstrap response has no retained original and stays terminal. Substitution
or duplicate publication is refused before identity readback.

Negative tests must cover missing/partial/rewound sidecars against progress
anchored in the original, plus complete protocol/signature/identity/source/run/
expiry/clock/revocation/key/policy and filesystem substitutions. These checks do
NOT prove resistance to rollback of the complete authoritative database and all
anchors together. Such a claim requires an independent nonrewindable anchor.

Any successful local result retains false flags for actual hosted OIDC, live
Google access, source provenance/admission, QA acceptance, backups and write
permission unless each is separately exercised and proved. Production startup
and credential/access configuration remain disabled pending concrete approval.
