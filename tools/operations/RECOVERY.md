# Interrupted Fleet operation recovery

Recovery establishes current code state after a lost response or service crash.
It does not retry a backup/update, restore database/media, or prove that the
original request caused the observed state. Only the installed standard-plugin
Fleet update/rollback adapter is supported. No production acceptance is implied
by the controlled tests.

## Operator prerequisites

1. Read `get_operation` for the interrupted operation. Keep its site/environment,
   status, plan digest and `recoverySnapshotDigest`. Only `running` or `uncertain`
   operations with their original site lock are eligible. If a local worker is
   active, the service returns `OPERATION_ACTIVE`.
2. Stop new write admission and positively stop/fence the original service
   workers, including surviving subprocesses and any previous service instance.
   A timeout, process restart, elapsed time, HTTP 200 or sync timestamp is not
   evidence of quiescence. Keep independent Fleet CLI, MainWP UI, site Actions
   and other writers excluded for this environment through completion.
3. Use the approved operating route to verify no outstanding MainWP, Updraft,
   child-site or provider job can still write for this operation. Preserve
   non-secret evidence and its checksum. If that cannot be established, keep the
   lock and investigate. Never manufacture an idle assertion from absent API
   coverage. Missing MCP/REST exposure requires capability/extension assessment
   under the canonical MainWP policy before a different route can be considered.
4. Read `get_operation` again after stopping workers; use its **current** snapshot
   digest. Verify the website's writer-coordination enrollment is current.
   Recovery permits renewing only that enrollment; it cannot change site
   identity, management, backup policy or other registered target facts. The
   original approved plan remains immutable and the renewed enrollment is
   retained in the reconciliation result.
5. Retain valid source/runtime QA qualifications for both possible code states
   (the proposed release and its retained prior release), including their
   immutable asset URLs and checksums. Only the actually observed artifact's
   qualification is used. Recovery compares the original bound tree, not today's
   latest release; publishing a newer release registry alone does not invalidate
   these read-only checks. Later rollback planning still checks current retained
   artifact/source availability through the existing Fleet gates.

## Enroll evidence

`recoveryEvidencePath` is an optional absolute service configuration path. If it
is absent, or contains no matching record, recovery is blocked. The empty
`config/recovery-evidence.example.json` enables no recoveries. Protect this file
like the permissions/qualification files: operator-owned and read-only to the
service account, outside the checkout. Replace it atomically after review. MCP
does not accept proof fields, filesystem paths or caller identity as arguments.

The operator adds one record to `records` using this template. Replace every
placeholder with reviewed evidence; do not copy secrets, receipts or tokens into
references. The timestamp must follow the latest operation snapshot, must not be
in the future, and must expire within 15 minutes. A duplicate record blocks
recovery rather than selecting an arbitrary assertion.

```json
{
  "version": 1,
  "records": [{
    "operationId": "<operation UUID>",
    "operationDigest": "<current recoverySnapshotDigest>",
    "siteUrl": "<exact registered HTTPS URL>",
    "environment": "development",
    "verifiedBy": "<operator identity>",
    "verifiedAt": "<UTC ISO timestamp after quiescence checks>",
    "validUntil": "<UTC ISO timestamp at most 15 minutes later>",
    "workersStopped": {
      "reportRef": "<non-secret retained worker-fencing evidence reference>",
      "reportSha256": "<64-character evidence SHA-256>"
    },
    "downstreamIdle": {
      "reportRef": "<non-secret retained downstream-job evidence reference>",
      "reportSha256": "<64-character evidence SHA-256>"
    },
    "independentWritersExcluded": {
      "reportRef": "<non-secret retained writer-exclusion evidence reference>",
      "reportSha256": "<64-character evidence SHA-256>"
    }
  }]
}
```

These records are trusted operator attestations. The service validates their
schema, binding, uniqueness and freshness; it does not retrieve the referenced
reports or independently terminate workers. Host acceptance must validate the
operator's evidence collection and filesystem access controls.

## Reconcile and interpret the result

An authenticated member with read and environment release permission calls:

```text
reconcile_operation(operationId)
```

Real writes can remain disabled and MainWP safe mode enabled. The recovery
session admits only the existing read capabilities and one-site sync; backup,
preflight, update and rollback calls are forbidden. Before every downstream call
it rechecks authorization, unchanged operation/target, lock ownership and current
quiescence evidence. It exact-resolves the original URL and ID, syncs narrowly,
and requires a valid unchanged baseline, loaded component, exact version/tree
hash/file count, and no unknown drift or stale overlays. It checks the selected
artifact's current QA, public HTML, REST root and qualified asset bytes, then
repeats runtime readback. An atomic SQLite transaction stores the outcome and
releases only this operation's lock if the original snapshot is still current.

| Result | Meaning and next action |
| --- | --- |
| `reconciled` / `intended_code_verified` | The intended update or rollback code is currently verified. The original execution error remains visible. An updated plugin can now enter normal `prepare_rollback`; fresh approval, backup, qualification and verification remain required. |
| `reconciled` / `prior_code_verified` | The code present before that operation is currently verified. Inspect again before proposing any new repair; do not replay the original operation. |
| Error / unchanged `running` or `uncertain` | The website remains locked. Resolve the reported evidence/access/runtime issue and recheck quiescence before trying reconciliation again. No recovery mutation was attempted. |

Both reconciled outcomes report `backup: not_reverified` and
`databaseAndMedia: not_assessed`. They are not release-complete claims. Repeated
reconciliation returns the durable result without another downstream call; the
original operation is never executable again. The original error, source binding,
requester/approver/executor, quiescence operator, reconciliation requester,
timestamps and runtime/public evidence remain in history. Remove expired unused
enrollment records through normal operator maintenance, preserving audit evidence.

If the service stops during readback, its operation and lock are unchanged;
repeat quiescence verification before retrying recovery. If it stops after the
final transaction, both result and lock release are durable. Unknown/mixed code,
changed baselines, unavailable original artifacts, target changes beyond renewed
coordination, or broader Stack drift need a separately reviewed recovery plan.
There is no SQL-edit, expiry-based unlock or force-unlock fallback.
