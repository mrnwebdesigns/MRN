# Existing-worker original delivery: review proposal

Status: disabled source candidate with a pure verifier, atomic gateway journal
and one-use delivery composition. Production startup does not enable its route;
supervisor retention remains unimplemented. This does not qualify a
worker, create credentials, change access or authorize deployment.
It leaves all six existing-v2 contracts and the worker readback wire unchanged.

The additive `content-management-existing-bootstrap-v2-proposal.json` and
`existing-worker-bootstrap-contract.mjs` check the two registrar signatures
against independent public keys, complete policy pins and current run metadata.
The bootstrap policy also pins the complete owner/environment run policy; a
configuration change cannot silently substitute those expectations. The
[normative semantics](EXISTING-WORKER-BOOTSTRAP-SEMANTICS.md) define exact digest
preimages, lifetime units and the still-required durable lifecycle. Nine focused
test groups exercise consistency and denials. All results explicitly leave
retention, delivery consumption, hosted OIDC, current Google access and admission
unproved. No service startup or private endpoint constructs this verifier.

The private original ledger can now opt into an `ExistingWorkerBootstrapJournal`.
Only a genuine new original snapshots its complete bootstrap configuration and
seeds the journal in the original transaction. Worker preparation atomically
retains the exact registrar-signed commitment; verified dispatch observation
atomically adds a separate registrar-signed observation tied to the final worker
journal. SQLite failure rolls back both journals. Old originals cannot gain this
state through a restart or configuration addition.

Six focused SQLite/crypto groups cover reopen, failed seed/prepare/observation
writes, missing or rewound history, valid signatures for another original,
coherent policy changes, key substitution, original expiry and revocation.
The private getter performs retained-state inspection only. The separate delivery
handler implements consumption before release with controlled signed OIDC and
fresh staff checks. Actual hosted OIDC, live Google access and supervisor
durability remain required.

## One-use delivery source, 2026-10-28

`ExistingWorkerBootstrapDelivery` uses the existing worker authentication logic
with a distinct fixed bootstrap audience. Only the opaque dispatch/context pointer
is accepted. It repeats current original staff verification and checks the
original queue, revocation, source/policy/key fingerprints, exact hosted-run
claims and deadlines inside the final SQLite transaction. The bootstrap's
independently pinned owner/environment policy must also match the handler trust.

A delivery record and separate digest/state anchors on the original attempt are
seeded atomically with each new eligible original. Constructors never backfill
old or missing records. Consumption, response digest and per-person audit commit
together before release. Concurrent readers have one winner; a lost response or
restart cannot refill delivery. Missing, partial, changed or rewound sidecars
fail closed. This does not claim resistance to rewinding the entire authoritative
database and every anchor together. No provider request or provider counter is
used by metadata delivery.

The fixed HTTP composition remains absent by default and unselected by service
startup. It accepts canonical JSON up to 512 bytes, returns at most 64 KiB with
`no-store`, rejects browser origins and shares the existing worker capacity
limits. Its five-second budget includes body receipt and final consumption;
original and worker lifetimes are unchanged. Source tests exercise actual local
HTTP, RSA/Ed25519 signatures, two SQLite connections, lost responses, failed
writes/audits, timeout, revocation, trust drift, and distinct-audience denial.
These controlled identities are not actual hosted OIDC or Google acceptance.

The next required step is protected supervisor retention and independently
configured trust, followed by actual hosted bootstrap and source admission.
No deployment, access change, content/code write, backup or team-acceptance gate
is satisfied by this source checkpoint.

## Integration blocker

The independent runner verifier needs the original binding, evidence context,
dispatch provenance and observation before it verifies an identity-signed worker
readback. The gateway durably retains these records. Its disabled delivery handler now
serves them once to an exact authenticated run in local HTTP tests. No protected
remote supervisor yet receives and durably retains them in an actual hosted run.

An opaque workflow pointer is a lookup key, not the original. Copying records
out of the identity readback and passing them back as the independent expected
records would make verification circular. A relabeled legacy bootstrap also
does not establish this authority. The provider source APIs require a signed
original source context and cannot repair this missing worker prerequisite.

## Proposed authority path

1. A genuine new Google-authorized original snapshots an explicitly configured
   bootstrap contract/key-policy digest and creates an empty durable eligibility
   marker in the same transaction as the original. Existing originals cannot be
   backfilled. Configuration remains disabled until its separate approval.
2. After capture and measurements pass, but before dispatch to GitHub, the
   gateway creates a canonical commitment to the complete original worker
   context, opaque pointer, reviewed workflow/source policy, App actor and
   unchanged original expiry. It signs with an independently configured
   registrar role under a new bootstrap-only signature purpose. It retains the
   exact bytes and digest atomically with the one-use dispatch preparation.
   No identity, root or QA key fallback is allowed.
3. The existing dispatcher verifies the actual protected GitHub run. Only then
   can a separate registrar-signed observation bind the retained commitment to
   that exact run, provenance and observation. Its source is the append-only
   original worker journal; request arguments cannot supply those records.
   A lost or uncertain dispatch remains unusable, with no repair or redispatch.
4. A private, fixed-origin bootstrap delivery uses exact hosted GitHub OIDC and
   the original Google membership/access checks. It returns only the retained
   commitment and observation envelopes for that run. It cannot choose a site,
   source, principal, signing key, expiry or replacement original. No content,
   candidate code, token or private key is included. Production startup must
   leave the route absent without the reviewed service configuration.
5. The protected supervisor starts with reviewed public registrar trust,
   workflow/source policy and independently established current GitHub run
   identity. It checks both signature domains, every commitment/hash relation,
   the original opaque pointer, exact source/run/App provenance and expiry.
   It retains the verified original once in private supervisor-owned storage
   before making the separate identity-signed worker-context request. Candidate
   containers cannot read or replace that retained state or its trust config.
6. The existing worker record verifier then compares the fresh identity readback
   against this independently verified, already-retained original. Bootstrap
   consistency never implies current staff access, source qualification, QA
   admission, approval, a backup or write authority. All later gates remain.

The registrar commitment supplies a separate authority purpose and retained
pre-dispatch record; it is not a second team login or connector. The ordinary
member still adds one workspace plugin and signs in with Google.

## Required implementation and activation evidence

- Confirm that the existing registrar role may sign this specific original
  commitment, where signing occurs, and how its reviewed public trust reaches
  the protected supervisor. Do not repurpose a provider, root or identity key
  merely because it is accessible. New credential provisioning, if needed,
  requires a concrete separate approval.
- Prove the exact authoritative GitHub fields the supervisor obtains from
  its protected environment and independently verified hosted identity. No
  workflow input or response field can supply its expected run or source.
- Metadata delivery uses its own one-use marker, seeded atomically with a
  genuinely new original. It neither charges nor expands provider worker64 or
  source48; it is not another allowance for provider actions. Any actual provider
  context call still uses its existing signed action and normal64/512 budgets.
  A headroom observation is not a charge. Define consumption before release and
  lost-response behavior without refill, lazy initialization, repair or backfill.
- Qualify the bounded canonical schemas, maximum bytes, signature purposes and
  deadlines against actual context sizes and timing. Reuse the
  existing five-second read, 300-second worker and 900-second original limits;
  delivery never extends them. No endpoint or timeout change is implied here.
- Prove at-rest integrity and atomicity across prepare, dispatch observation,
  delivery and supervisor retention, including partial database/history loss.
  The API's own returned bytes must never become their only expected digest.

## Required evidence before activation

Use actual gateway SQLite history and protected supervisor storage, ephemeral
separate registrar/identity keys, real local TLS and a disposable workflow.
Prove the positive chain and deny old originals, absent/rewound history, changed
policy/key/source, alternate signature roles, caller identity, wrong run/attempt,
expired/replayed delivery, out-of-budget access, revocation before and during
delivery, uncertain dispatch and supervisor-file substitution. Retain original
reports and exact source pins. Do not derive expected records from the response
under test or suppress failed gates.

Local proofs remain distinct from actual hosted OIDC, approved protected
environment configuration and ordinary-member ChatGPT acceptance. The company
pilot stays mrnwebdesigns.com; this design authorizes no live site operation.
