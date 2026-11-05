# Editor CPU, native save and Hub provisioning — 5 November 2026

**Native save latency is substantially reduced on the review host. Whole-editor CPU and remaining save latency still block release. Hub restart/provisioning repairs pass source-runtime tests; the installed Hub app is still 0.2.3 and does not contain these source fixes. Release, publication and Fleet gates remain closed.**

## Installed review-site save improvement

Exact host: `admin-editor-pilot.mrndev.io`. Parent theme `mrn-base-stack`, child
`mrn-base-stack-child`, WordPress 7.0.4, ACF 6.8.9, PHP 8.4.27. Only
`wp-content/themes/mrn-base-stack/inc/builder/performance.php` changed remotely.
Commits: `bc1d5c62` and scalar compatibility correction `cdd0d781`.

A fixture-only temporary profiler located most of the native POST cost in ACF's
revision metadata copy. Every new key invalidated the revision's entire native
metadata cache. The following update reloaded an ever-growing result set.

The fix keeps the request-local revision cache warm only during a new revision
of supported MRN singular post types. Every native metadata write, sanitization
step and hook still runs. No external object cache is modified; persistent cache
installations retain native behavior. Temporary callbacks are removed when the
revision finishes. Failed/missing cache snapshots fall back to WordPress.

| Measurement | 19 sections: before / after | 32 sections: before / after |
| --- | --- | --- |
| Profiled native POST | 46.54s / 14.70s | 94.58s / 14.50s |
| Profiled revision step | 32.21s / 5.51s | 85.66s / 5.72s |
| Revision queries | 8,397 / 5,602 | 14,475 / 9,654 |
| Full click-save-reload, matching profiled trials | 54.46s / 23.91s | 104.98s / 22.63s |
| Final installed code, unprofiled click-save-reload | 21.05s | 32.90s |
| Final installed code, POST response start | 11.68s | 20.45s |

The last rows are confirmation runs, not a statistically matched before/after
series. An earlier unprofiled run measured 20.95s / 24.57s save-and-reload.
Variability and the remaining 21–33 second user wait are retained in the record;
**native save performance is improved, not cleared for release**. The remaining
profiled ACF flexible/repeater updates take about 8 seconds including recursive
calls; do not sum overlapping callback times. Server peak memory in the paired
profiles rose about 8 MiB (19 sections) and 6 MiB (32 sections), below the 512 MiB
site limit. This does not establish broad memory or concurrency qualification.

[Profile summary](save-profile-summary.json), [baseline 19](save-baseline.json),
[baseline 32](save-baseline-32.json), [profiled candidate](save-candidate.json),
[final native saves](save-final.json).

### Integrity evidence

- Native saved content and ACF hashes are unchanged for the 19/32-section drafts.
- Latest native revisions match all **2,796 / 4,822** ACF metadata keys, including
  value/reference pairs. Both remain drafts; no page was published.
- A disposable owned draft passes native `wp_restore_post_revision`, nested
  repeater, Unicode/quotes and WordPress `post_content` restoration, then is deleted.
- A 400-ACF-key native integration fixture covers duplicate values, nested hook
  writes, updates/deletes, callback-time reads, SQL scalar types, cache-vs-database
  equality, temporary hook cleanup and the persistent-object-cache fallback.
  Final query counts: native 1,230; optimized 824; fallback 1,230.
- The strengthened callback-time scalar test exposed a type mismatch in the first
  candidate. Correction `cdd0d781` preserves SQL string/NULL semantics; the same
  test now passes. No stored values were changed by that mismatch.
- The final native incomplete submission is rejected with **HTTP 400** by the
  existing save-integrity guard. No save protection was bypassed.
- Each runtime install/test mutation passed the labeled remote database-only
  Updraft backup gate. Exact-file checksums and private rollback copies exist.
- The temporary fixture-only profiler is removed. No diagnostic plugin remains.

[Native integration](native-integration.json), [save guard](save-guard.json),
[final installed readback](final-installed.txt). Reproduction scripts are included;
`revision-cache-test-evidence.php` and `revision-restore.php` intentionally write
and delete owned test drafts and require the backup gate first.

## Whole-editor CPU: candidate withdrawn, blocker open

Eight balanced ABBA browser navigations used fresh contexts and identical request
interception/cache policy: the 19-section Light fixture and 32-section Dark fixture,
two samples per variant. Every native delayed WYSIWYG was then initialized:
**41 / 60 TinyMCE editors**. All runs preserved ACF control counts and form hashes;
no browser script errors. The owner's Codex tab was untouched.

A candidate replaced repeated row-flex descendant scans with direct-child traversal
and reused the existing row index. Its whole-editor results did not establish
non-regression, so **it was withdrawn and never installed**:

| Fully initialized CPU | Before samples | Candidate samples |
| --- | --- | --- |
| 19 sections | 5.37s, 2.79s | 4.91s, 2.98s |
| 32 sections | 5.25s, 7.09s | 8.03s, 6.73s |

Retained heap was about 68.6–69.9 MiB / 92.4–93.0 MiB. The profile still shows
native ACF conditional logic and editor initialization as major costs. A sampled
Cloudflare script outlier also remains in the data; no security rule was changed,
no outlier discarded, and sampled attribution is not subtracted from total CPU.
The original pre-appearance whole-editor baseline remains a separate unmet gate.

[All CPU samples](qualified.json), [CPU summary](cpu-summary.json),
[stack attribution](cpu-stacks.json), [reproduction](qualify.cjs).
The withdrawn source is retained as `row-flex-rejected.js` only as evidence.

## Separate Local Hub repair and bounded proof

Dedicated Hub worktree:
`/Users/khofmeyer/Development/MRN/.worktrees/hub-pilot-performance-20261105`,
branch `codex/pilot-provision-performance-20261105`, source commit `8d0ce21`.
The canonical Hub checkout and other task worktrees were left unchanged.

The controlled installed-app provisioning call reproduced a restart hang. Its
MCP connector timed out at 300 seconds, but the underlying `limactl` process
continued beyond 11 minutes. The guest restart script had exited. OpenLiteSpeed
PID 12985 descriptor 3 still pointed to pipe 74220, also held by sshd PID 1871.
The daemon's duplicate of command stdout prevented SSH completion.

The shared restart helper now captures control-script output through a temporary
regular file, prints it, and unlinks it. It preserves exit status and the existing
PHP security gate/containment. The daemon cannot retain the SSH output pipe.
Only the verified stale provisioning SSH child was terminated; no unknown job,
other VM or remote review service was stopped.

Two additional scoped repairs:

1. Provision/start reads `home` and `siteurl`; when both exactly match the local
   URL, it skips five otherwise unconditional full-database replacement scans.
   Imports and explicit normalization retain the complete workflow. Mismatched
   or unreadable siteurl falls back to that workflow.
2. Manifest reads retain their persisted `updatedAt`. **Correction to the earlier
   report:** the apparent constantly changing timestamp came from read-time
   defaults. Disk state had been unchanged since 2 November; that timestamp did
   not prove recurring provisioning or another thread modifying the site.

Forty-five targeted tests and syntax checks pass, including restart descriptor
inheritance, exit status, unsafe-PHP refusal, normalization fallbacks and timestamp
stability. Exact staged MRN QA passed. An initial 1.2-second simulated-daemon test
was timing-sensitive under browser load; its timeout was increased to 5 seconds
with a 15-second inherited-pipe sentinel. The complete targeted suite then passed.

The committed production functions were exercised directly against only
`admin-editor-pilot` without starting a second Hub, replacing the packaged app,
or changing its published endpoint. Full provisioning completed in **16.08s and
11.50s**, status `provisioned`, no stderr. The database fast path took 1.19/1.23s.
The installed app remains 0.2.3; it will still use the old helper until a separately
qualified local app update. **Source-runtime proof is not installed-app proof.**

After these runs: guest-agent 16 descriptors, 110.1 MiB RSS, 4,985 MiB available
VM memory, zero swap use, zero memory-pressure averages and no OOM kill in this
boot. The retained descriptor now points to an unlinked regular file. The pilot
static endpoint returns HTTP 200. All configured web handlers pass the PHP security
gate (8.4.27 / 8.2.35). The older vendor CLI binary is distinct from the patched
web LSAPI handler; no security gate was relaxed.

[Hub runtime checks](hub-runtime.json), [health snapshot](hub-health.json),
[45 tests](hub-tests.txt), [syntax check](hub-check.txt).
The previously repaired Lima socket leak remains bounded by the earlier soak;
this pass does not claim all future OOMs or all Hub operations are qualified.

## QA scope, Git state and next priorities

MRN exact-staged gates passed for the save change, scalar correction and Hub code:
syntax/lint, applicable WPCS/compatibility, security/secrets and diff checks.
The PHPStan missing `wp-cli-stubs` warning remains advisory in snapshot QA.
The commit gate intentionally does not prove authenticated runtime, accessibility,
whole-editor performance or release qualification; the targeted evidence above is
separate. No visual behavior changed, so broad screenshot/axe sweeps were not
repeated. No frontend, Fleet parity, release lock, artifact publication or fleet
activation was run. No plugin/framework or new user configuration was introduced.

1. **P1 whole-editor CPU:** retain the release blocker. Isolate native field-init
   and conditional-logic costs against comparable fully initialized historical
   fixtures before changing lifecycle behavior. Do not adopt the rejected patch.
2. **P1 residual save latency:** retain the release blocker. Expand matched native
   timings and profile remaining ACF update/query costs; preserve revision fidelity.
3. **P1 Hub installed-app qualification:** source repairs pass, but app rebuild/
   installation and repeated UI/MCP starts are still outstanding. Keep this
   separate from the repaired Lima socket leak and the remote review-site results.

The MRN pilot commits and dedicated Hub branch are local/unpushed. The Hub branch
is clean after its commit. Review-site installation is limited to the one PHP
file; it does not imply source publication, promotion or release readiness.
