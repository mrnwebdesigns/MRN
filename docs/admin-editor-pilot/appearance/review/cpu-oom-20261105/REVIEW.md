# CPU and Local Hub OOM follow-up — 5 November 2026

**The reproduced Lima socket leak is repaired. Whole-editor CPU remains a release
blocker.** A measured editor notice optimization is installed on the isolated
review host, commit `b8a6624a1322`. No release, promotion, production/Fleet rollout,
WAF change, native save-handler change or broader infrastructure redesign.

## Local Hub: repair and bounded proof

Before maintenance, Lima host and guest were both **2.1.2**. The same static-file
probe reproduced the previous defect: eight VM-loopback requests retained no
sockets; eight host-forwarded and eight HTTPS-proxy requests retained sixteen.
The guest agent ended at **38,893 descriptors**, including 38,883 sockets.

Updated only the Homebrew Lima package to **2.2.1**, preserving its 2.1.2 keg, and
restarted only `mrn-openlitespeed`. Both host and running guest report 2.2.1.
The guest's configured 4 CPUs, 6 GiB RAM and 2 GiB swap are unchanged. No PHP
version, memory limit, proxy configuration or site content was intentionally
changed. The separate `mrn-unified-qa` VM retained host-agent PID 4287; it was not
restarted or upgraded in-guest. The stopped `lima` instance stayed stopped.

This version contains the upstream [connection teardown fix](https://github.com/lima-vm/lima/pull/5216),
released in the [2.2 line](https://github.com/lima-vm/lima/releases/tag/v2.2.0).
The [2.2.1 release](https://github.com/lima-vm/lima/releases/tag/v2.2.1) is the
Homebrew version installed here.

A consistent APFS clone of the stopped VM disk, configuration and VZ identity is
retained, owner-only, at
`/Users/khofmeyer/.mrn-local-hub/maintenance/20261105-lima-2.2.1`.
The four clone sizes were checked against their stopped originals. This is a
rollback copy, not a completed restore drill. It contains private local data and
is not committed. The previous Lima keg remains available for rollback.

| Probe | Before | After |
| --- | --- | --- |
| VM-loopback static connections | 8; no retained sockets | 80; no retained sockets |
| Host-forwarded static connections | 8; +8 retained sockets | 80; back to 15 total descriptors |
| HTTPS-proxy static connections | 8; +8 retained sockets | 80; back to 15 total descriptors |
| Further proxy traffic/idle soak | — | 500 connections over 342 seconds; final 15 descriptors |
| Guest-agent RSS | 472.4 MiB before restart | 38.3–80.5 MiB in soak; 59.3 MiB at soak end |
| Memory pressure / swap consumption | No current pressure; earlier OOM documented | Zero pressure averages and no swap consumed throughout |

After authenticated WordPress traffic the agent had 16 descriptors and 100.7 MiB
RSS, with about 4.1 GiB available VM memory. Active browser/background connections
can temporarily raise descriptor counts; closed test batches return to baseline.
The guest agent retained the same PID, reported zero restarts, and the current
boot's kernel log had no OOM kill. These are bounded observations, not a claim
that all future OOMs or all Hub faults are impossible.

[Before probe](oom-before.json), [after probe](oom-after.json),
[traffic/idle soak](oom-soak.json), [post-workload snapshot](oom-final.json),
[VM scope](vm-scope.json), [final health](local-final-health.txt).

### Separate Local Hub issue remains

The Hub initially listed a stale job whose process no longer existed. During
later testing a new Hub `limactl` job appeared and OpenLiteSpeed restarted;
`admin-editor-pilot` still reports `provision-error` with a changing update time.
That job was not stopped and the manifest was not manually rewritten.

The first local browser run passed Dashboard and the 19-section editor, then
failed its authenticated-page assertion on the stress fixture. It did not retain
the failed response, so an HTTP status/cause cannot be asserted retrospectively.
Read-only HTTP retries returned a complete authenticated 32-section document.
A fresh browser retry then passed the 32-section editor, 19-section editor and
Dashboard; both editors had four initialized TinyMCE instances. Wall times
remained variable (12.6s, 15.4s and 64.4s respectively). An apparent “critical
error” string was confirmed to be SEOPress translation data, not a PHP fatal.

[Initial partial run](local-workload.json), [successful retry](local-workload-retry.json).
The known socket-accumulation/OOM cause is resolved; general Hub provisioning and
latency health is **not** declared resolved. Keep review work on the mrndev host.

## Editor: narrow CPU improvement, broader qualification still open

The CPU profile identified `collectNotices()` scanning the entire editor DOM with
`:scope > ...` queries. The 32-section fixture contains over 53,000 nodes. The
collector now filters only the direct children that can actually be notices.
It keeps the original notice elements, controls, event handlers, visibility
checks, alert summary and dismissal behavior. No observer, dependency, field
lifecycle, configuration or save behavior was added or replaced.

Balanced instrumented ABBA samples on the 32-section fixture recorded five
collector calls per startup:

| | Before | Candidate |
| --- | --- | --- |
| Notice collection total | 45.7 / 46.7 ms | 32.3 / 21.7 ms |
| Median | 46.2 ms | 27.0 ms |

This approximately 42% reduction applies to **notice collection only**, not total
editor CPU. [Instrumented evidence](notice-check.json).

The unprofiled ABBA comparison covered 0/19/32 builder sections, both themes, two
samples per phase/theme/fixture (24 navigations). Both phases used identical
request interception/cache policy and fresh contexts. Theme changes were
browser-only; account preferences were not saved. All native control counts,
ACF field counts and per-fixture form hashes matched; no browser script errors.
Retained startup heap was roughly 17.7–17.9, 23.2–24.6 and 26.5–26.6 MiB.

**Total startup CPU did not establish non-regression.** Several candidate samples
were slower, and those results remain in the evidence. Sampling independently
attributed a 1,782ms outlier to Cloudflare's challenge script; other challenge
samples were 33–56ms. ACF/native initialization remains the largest consistent
cost. Do not subtract sampled attribution from unprofiled timings or dismiss all
variation as Cloudflare. No security policy was weakened to improve the numbers.

The startup series preserves native delayed WYSIWYG behavior (one initialized
editor at that point); it does not replace the original fully initialized
historical baseline qualification. The original pre-appearance baseline has
changed substantially since October. These are matched tests of this small
patch, **not** proof that the entire modern workspace has passed its original
CPU gate. [All samples](qualified.json), [medians/ranges](cpu-summary.json),
[editor attribution](profile.json), [settings attribution](profile-settings.json).

A separate installed stress test explicitly exercised ACF's native delayed-editor
mousedown initialization for all 59 WYSIWYG fields, yielding 60 TinyMCE editors
including Classic Editor. After 8/16/24/32 section/settings navigation cycles,
retained heap was **93.29 / 93.57 / 93.88 / 93.68 MiB**. No continuing linear
retention was observed in this bounded test. This deliberately heavier state is
not the normal one-editor startup and must not be compared directly with its
26.5 MiB heap. Authored values remained identical. [Installed check](installed-check.json).

## QA, installation and limits

- Required MRN staged snapshot gate passed; JavaScript syntax and diff checks
  passed. [Source QA](source-qa.txt). PHP/API static rows were inapplicable to the
  JavaScript-only change; source-gate runtime rows are intentionally skipped and
  covered by the named-site checks here. No release/parity/fleet QA was run.
- Late success notices are collected and open Page alerts; hidden notices and
  nested form notices remain in their native locations. Dismiss handlers remain
  attached. Installed keyboard dismissal passes in Light and Dark. Scoped axe
  WCAG A/AA checks report zero Page alerts violations in both themes.
- Installed asset SHA-256 matches the committed file. The deploy helper verified
  exact host/review marker, site-owner write access and previous checksum,
  created a fresh labeled DB-only Updraft backup, verified its remote object,
  then atomically replaced one file. [Install receipt](install-receipt.json).
  Private remote rollback: `review-private/before-cpu-notice-b8a6624a1322`.
- Public `/wp-json/` returned HTTP 200 and valid REST index JSON. Native 19/32
  section hashes, draft status, save guard and manual backup schedules remain
  unchanged. [Native readback](content-readback.json). No page content/settings
  save or publish was submitted; this is not new backend save-latency evidence.
- The owner's Codex tab was not navigated, reloaded or used for testing. Private
  cookies, VM disk/config, raw HTTP bodies and private logs are excluded from Git.

Only changed product source:
`mu-plugins/mrn-editor-lockdown/assets/section-navigator.js`.
The Lima package/guest update is local runtime maintenance, not a Local Hub app
source/release change. No other repository was edited or committed.

## Remaining priorities

1. **P1 CPU qualification:** obtain repeated comparable fully initialized samples
   and attribute ACF/native field-init cost before further lifecycle changes.
   Existing noisy results are not acceptance. Do not turn off WAF/challenges or
   discard native controls to make a benchmark pass.
2. **P1 native save latency:** the previous slow 19/32-section save measurements
   remain open; this notice-only change does not address their server-side cost.
3. **P1 Local Hub provisioning/latency:** identify the recurring provisioning job
   and `provision-error` state separately from the repaired Lima socket leak.
   Do not restart other VMs, kill unknown jobs or change unrelated sites.

Release and Fleet gates remain closed. For reproduction, scripts in this folder
require private temporary `auth.json` / `local-auth.json`; never commit them.
`qualify.cjs` and `notice-check.cjs` also require `section-navigator-before.js`
exported from `d80d542a:mu-plugins/mrn-editor-lockdown/assets/section-navigator.js`.
Use serial runs and retain failures/outliers. The local workload intentionally
blocks cross-origin browser requests on the clone; no remote security rule is
changed.
