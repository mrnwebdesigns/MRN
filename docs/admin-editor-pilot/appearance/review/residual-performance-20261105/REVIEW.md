# Residual save and whole-editor CPU investigation — 5 November 2026

**Whole-editor CPU and residual save latency remain release blockers.** This pass
removes verified redundant database work on the isolated review site. It does not
install another JavaScript optimization or establish stable end-to-end speed.
Release, publication, production and Fleet gates remain closed.

## Changes applied to the review site

Exact target: `admin-editor-pilot.mrndev.io`, development environment, site owner
`mrndev-admin-editor`. Drafts 2523 / 2524 contain 19 / 32 sections. The original
client pages, the owner's Codex tab, AME, native forms and publishing remain intact.
No product PHP/JavaScript/CSS changed in this pass. The previously installed
revision-cache implementation is unchanged, SHA-256
`d15f5703f14adec7bc0a628122b23f50a12fdc019eb1023163cca3e3007442f3`.

1. **A reversible, nonunique metadata index** on `(post_id, meta_key(191))` makes
   native exact-key lookups cheaper. Original indexes remain. MySQL 8 online DDL
   uses `LOCK=NONE` and bounded session lock waits; unsupported operations fail
   rather than falling back to blocking table copies. Apply, idempotence, rollback
   and reapply were exercised. The index remains installed only on this review DB.
   EXPLAIN changed from the separate `meta_key` index (about 297 rows) to the new
   index (2 rows before duplicate repair). The 100-lookup probes changed from
   109.5 / 109.5 ms to 36.2 / 32.0 ms. This is query evidence, not whole-save proof.
2. **Identical duplicate builder metadata was repaired on the two owned drafts.**
   WordPress skips unchanged metadata updates only when a key has one value.
   Duplicates therefore forced thousands of unchanged writes. The repair removed
   2,586 / 2,766 redundant rows through native per-ID deletion, retained the oldest
   value and verified every logical metadata value before/after. It touched only
   MRN-referenced builder keys with byte-identical values. Conflicts are a refusal.
   Reapplying made zero changes; subsequent native saves did not recreate them.
   The provenance of these fixture duplicates is not established. No general
   cleanup, production migration or automatic repair has been introduced.

Both scripts require the exact host/environment/site owner and a fresh verified
native Updraft receipt matching the remote S3 backup. Every mutation job had a
labeled remote DB-only backup. The duplicate repair also wrote a private 0600
recovery snapshot before deletion; its values are not included in this repository.
Index rollback was tested. Duplicate-row rollback is prepared but **not exercised**;
it restores equivalent duplicate values through native APIs, not their old meta IDs.
The verified DB backup remains the exact-state recovery option.

Source commits: `659fae8b` (index helper), `a464c5a3` (fixture repair).
Helpers: `scripts/performance/admin-editor-pilot-postmeta-index.php` and
`scripts/performance/admin-editor-pilot-duplicate-meta.php`. Neither is autoloaded.

## Native save results

All trials use native Save Draft, native ACF/revisions, the same owned fixtures and
reload. The temporary profiler records aggregate SQL categories/timing, never field
values or SQL text. Query counts begin after authenticated `admin_init`. Inclusive
callback times overlap and must not be summed.

| Trial | 19-section POST / SQL / queries | 32-section POST / SQL / queries |
| --- | --- | --- |
| Start of this pass | 12.26s / 8.08s / 10,373 | 15.31s / 10.29s / 14,803 |
| Index only | 8.27s / 4.16s / 10,373 | 15.54s / 8.74s / 14,803 |
| Index + duplicate repair | 6.87s / 2.56s / 5,756 | 7.97s / 4.00s / 9,870 |

Metadata UPDATE counts fell from 2,340 → 42 and 2,520 → 64. Query counts fell
44.5% / 33.3%. Native revision copying still writes all 2,796 / 4,822 ACF metadata
keys; it remains a substantial cost. We have not bypassed revision fidelity or
native hooks to improve a benchmark.

| Observed user wait | 19 sections | 32 sections |
| --- | --- | --- |
| Profiled baseline save + reload | 22.12s | 24.62s |
| After both repairs, profiled save + reload | 16.47s | 23.63s |
| Final unprofiled POST response start | 19.02s | 8.15s |
| Final unprofiled save + reload | 32.72s | 24.04s |

The slower final 19-section trial is retained. These sequential observations are
not a statistically matched latency qualification. Index rollback/reapply stress
controls were also mixed: POST response starts 15.66s / 17.49s. The query reduction
is verified; a consistent elapsed-time improvement is **not**. Profiled PHP peaks
changed from 196.2 / 116.0 MiB to 202.3 / 136.0 MiB. Revision accumulation and
request-cache workload are not controlled across these sequential samples; this
does not prove a memory improvement or clear concurrency qualification.

The later server snapshot showed 4 CPUs, load 1.53, about 3.9 GiB available memory
and no swap-in/out in the two current one-second samples, despite about 1.9 GiB
of occupied swap. It neither proves swap caused the slow save nor rules out
earlier contention. No service, worker or server setting was changed.

[SQL summary](save-sql-summary.json), [baseline](save-before.json),
[index-only](save-after.json), [rollback](save-rollback.json),
[reapply](save-reapply.json), [after duplicate repair](save-dedup.json),
[final unprofiled](save-final.json), [server snapshot](server-health.txt).

## Whole-editor CPU remains open

The withdrawn row-flex candidate remains withdrawn. Profiles point to native ACF
conditional/visibility processing, TinyMCE initialization and some MRN navigation
work. A Cloudflare challenge script was verified through network events; no WAF
rule changed and no security-script cost was subtracted from total CPU.

An interaction profile also exposed Playwright locator-query overhead. The final
current-state probe reuses element handles and avoids forced garbage collection
inside timed startup, idle or navigation phases. Garbage collection runs only
afterward to measure retained heap. Earlier forced-GC observations are kept
separately and are not compared with the corrected series as an improvement.

| Fixture | Fully initialized CPU, Light / Dark | Retained heap, Light / Dark |
| --- | --- | --- |
| Empty | 5.62s / 1.08s | 19.0 / 18.9 MiB |
| 19 sections, 41 TinyMCE instances | 6.69s / 4.92s | 70.9 / 69.7 MiB |
| 32 sections, 60 TinyMCE instances | 14.69s / 10.66s | 94.0 / 93.5 MiB |

These are one serial Light/Dark pair per fixture, not a theme comparison or a
historical baseline. All delayed native rich-text editors were initialized, making
this broader than normal lazy startup. Form hashes/control counts remained intact
and no script errors occurred. Six navigation cycles used 1.12–1.85s CPU on the
complex fixtures; 1.5s idle intervals used 2–48ms. The slow/outlier observations
remain included. A missing `securityScripts` entry in the page resource list does
not prove the cross-frame challenge was absent.

[Corrected current-state summary](cpu-current-summary.json),
[complete measurements](cpu-lifecycle.json), [reproduction](cpu-lifecycle.cjs),
[earlier observations and limitations](cpu-observations.json),
[19-section attribution](cpu-2523.json), [32-section attribution](cpu-2524.json),
[interaction attribution](cpu-interaction-2524.json).

## Integrity, QA and cleanup

- The final native ACF, builder content and WordPress content hashes match the
  pre-change fixtures; 19 / 32 sections remain drafts.
- Native revisions match all 2,796 / 4,822 ACF keys. A disposable native restore
  test passes nested repeater, Unicode/quotes and core `post_content` checks.
- The 400-key integration fixture passes native callbacks, cache/value equality,
  duplicate/scalar semantics, nested writes and persistent-cache fallback.
  Query counts remain 1,230 native / 824 cached / 1,230 fallback. Disposable
  fixtures are removed.
- The native truncated-submit guard rejects the missing completion marker with
  HTTP 400. Subsequent readback confirms content integrity.
- Native REST index: HTTP 200, 12 namespaces, 368 routes.
- The temporary save profiler was removed under its backup gate. The isolated QA
  authentication session was revoked; private cookie artifacts were removed.
  The owner's Codex browser session was not modified.
- Exact-staged MRN source gates passed for both operational helpers: PHP syntax,
  security/secrets, applicable WPCS/compatibility and diff checks. PHPStan's
  missing `wp-cli-stubs` warning remains advisory. Static gate boilerplate saying
  “release success” does not qualify this pilot for release.
- Broad axe/visual, API-mutation, frontend, release/parity and Fleet tests were
  intentionally not rerun: no UI/API/product-source change or promotion occurred.
  Targeted native runtime/integrity and performance evidence is separate above.

[Repair and backup](duplicates-apply.txt), [idempotence](duplicates-idempotence.txt),
[native integrity](native-integrity.txt), [save guard](save-guard.json),
[final readback](final-runtime.txt), [REST](api-health.json),
[index source QA](qa-source.txt), [repair source QA](qa-duplicate-source.txt),
[session cleanup](session-cleanup.txt).

## Remaining priorities

1. **P1 whole-editor CPU:** obtain a comparable fully initialized baseline and
   balanced repeated measurements before accepting another JavaScript candidate.
   Preserve native ACF lifecycle and keep the withdrawn patch out of the runtime.
2. **P1 residual save latency:** profile remaining native revision insertion and
   editor reload separately, including PHP worker/DB wait timing in matched runs.
   Confirm the duplicate-free fixtures stay clean; do not generalize their repair
   to other sites without exact-key evidence and authorization.
3. **P1 separate Hub installed-app qualification:** the prior source restart/
   provisioning repair is not installed-app proof. No Hub work was performed here.

The task branch and new commits are local/unpushed. Only the isolated review DB
was changed by these helpers; this is neither a published release nor Fleet-ready
source. The [previous pass](../blockers-20261105/REVIEW.md) retains the original
save-cache and separate Hub evidence.
