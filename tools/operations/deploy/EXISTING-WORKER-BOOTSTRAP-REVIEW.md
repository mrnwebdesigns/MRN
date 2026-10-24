# Existing-worker original delivery: review proposal

Status: disabled source candidate with a pure consistency verifier; no delivery,
retention implementation or enabled route. This proposal does not qualify a
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

## Integration blocker

The independent runner verifier needs the original binding, evidence context,
dispatch provenance and observation before it verifies an identity-signed worker
readback. The gateway durably retains these records. No reviewed existing-v2
bootstrap currently delivers them to the protected remote supervisor.

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
