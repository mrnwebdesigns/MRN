# Candidate private measurement readback

This is source design and synthetic schema/vector coverage only. It registers no
ability, changes no runtime package, enrolls no credential or person, and performs
no measurement acquisition, real signing, admission, publication or deployment.
All seventeen frozen v1/v2 dependency files retain their accepted bytes. Native
content scope remains published WordPress posts/pages and the existing three
fields; this does not qualify ACF, menus or media.

The proposed POST ability is
`mrn-mainwp/read-content-qualification-measurements-v1`. Its registrar purpose is
`mrn-content-qualification-measurements-read-v1:`. It is private to the pinned
backend connection, rejects delegated staff scope, and never enters a team MCP
allowlist. No second probe endpoint is proposed. The closed candidate contract is
`contracts/content-qualification-measurement-read-v1-proposal.json`; its ordered
guards and semantic requirements are normative parts of the proposal. JSON Schema
validity alone does not satisfy the cross-record, lifetime or provenance checks.

Every assertion and observation binds the original job, requester digest, complete
capture, snapshot, target, target revision, recipe and catalog, authorization
digest, evidence-policy epoch/digest, original expiry and independently expected
source/helper pins. Current-job rebinding and lease renewal are forbidden.
Existing private context/capture/recovery outputs remain unchanged. The protected
runner must independently possess the reviewed evidence/source pins; the returned
record cannot establish its own expected authority. Old captures without an
atomically committed observation record remain unavailable. There is no backfill
or repair path that reconstructs a successful packet count from stored size.

Capture instrumentation would measure each actual accepted native packet before
decoding: exact output UTF-8 bytes/hash and an explicit boolean truncation flag.
Successful records require every flag to be false. Consecutive packet indices,
decoded byte totals, dataset/source hashes, declared totals, an independent
accepted counter and the decoder's count must agree. The native dataset and final
capture artifact have different bytes; the synthetic vectors deliberately cross
different 32 KiB boundaries to prevent conflating their counts. The immutable
encrypted record, full capture, job outcome and audit must commit and read back
together. Uncertainty withholds the record and retains the existing conservative
operation behavior.

The record has three measured group preimages: capture integrity, actual native
snapshot ownership/status, and installed-source integrity. Expected source comes
from the original reviewed recipe/job. Measured source must pass fresh
`Content_Source::verify(expected_commit)`. Its installed-manifest digest covers
the exact canonical `release-source.json` bytes including the existing final LF;
it is neither the full profile digest nor a re-encoded manifest without that LF.
The response contains exact canonical record JSON and its digest, without a
second object copy or new expiry. Caps are 65,536 record bytes, 98,304 response
bytes and the existing 135 packets / 4,400,000 dataset/artifact byte bounds.

Before any durable assertion or budget claim, the future handler must verify the
pinned authenticated backend, closed bounded input and exact registrar purpose.
Anonymous or wrong-purpose probes consume no nonce, request ID or read slot. A
valid signed read then rechecks original authority inside the existing ordered
work guard and reserves a globally one-use nonce/request plus one of eight
durable measurement-read slots. These slots are separate from the existing eight
capture-recovery slots, which remain unchanged. A later access denial, expiry or
failure consumes its reservation; no retry or reset is hidden. A fresh existing
`read-capture` veto guards each side of the bounded read, with no work locks held
across callback HTTP. The final original-authority/source/expiry check must still
hold immediately before return. Reading measurements never dispatches a Child
request or recaptures content.

The fourth group, WordPress API behavior, belongs to the protected acquisition
runner. It performs exactly three TLS-verified requests to the fixed Dashboard
origin and proposed route: anonymous first, authenticated wrong-purpose second,
then the valid read. Anonymous and valid requests use identical body bytes. The
wrong-purpose request uses the identical canonical payload and a valid registrar
signature under the existing capture-read purpose. It must fail before the valid
assertion can be consumed. The new route's proposed application pairs are
`401 / rest_ability_cannot_execute` and `403 / mrn_job_signature`; the second is a
normative requirement for this additive route. Existing endpoints are untouched
and can currently return a default 500 for a status-less signature error. These
vectors do not claim the future route has been measured.

The runner emits canonical UTF-8 request entity bytes and hashes them excluding
headers. It hashes exact uncompressed response entity bytes after transfer
framing removal and before UTF-8 decoding, JSON parsing or normalization. It sends
`Accept-Encoding: identity`, follows no redirects, sends no cookies, and uses no
credential fallback. Each transport has a 20-second timeout and must finish
before the original assertion/job expiry. Denials have a 4,096-byte ceiling;
the transcript ceiling is 16,384 bytes. The runner must parse the actual
closed WordPress error JSON (`code`, bounded `message`, and `data.status`) and
match the allowlisted code plus `data.status` to HTTP. Additional keys are refused.
Empty bodies, invalid UTF-8, ambiguous/duplicate JSON keys, unexpected encodings,
WAF HTML/JSON, unrelated codes, redirects and transport failures are unavailable.
A 403 alone is never a pass. TLS still trusts the configured origin/edge: this is
observed application-shaped behavior, not cryptographic proof of server internals.

Only the bounded transcript survives acquisition: original binding/correlation,
sequence/times, purpose/authentication mode, byte counts/digests, verified origin,
route/method, status, content type/encoding, and parsed allowlisted error metadata.
No token, cookie, signature, envelope, content, email, raw body or error message
may enter that transcript or logs. Raw synthetic examples live only in the test
vectors. The authorized response digest differs from the canonical inner-record
digest; the vectors test that distinction and that even an added LF changes the
raw response digest while leaving its decoded JSON value unchanged.

Projection fills only the four existing v2 groups. The API group's
`denial_measurement_sha256` hashes the exact canonical acquisition transcript.
The original transcript preimage must be retained as a protected acquisition
artifact; trusted retrieval/admission of that artifact remains a later review
gate. The frozen two-member qualification archive and external mutation receipt /
three-file interface are unchanged. Missing original provenance cannot become an
inferred pass. The runner's other four runtime groups, independent QA signing,
real artifact authority and final admission are outside this contract checkpoint.
