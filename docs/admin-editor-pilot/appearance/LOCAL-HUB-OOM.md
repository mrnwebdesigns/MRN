# Local Hub OOM investigation — 31 October 2026

**Historical investigation; superseded by the [5 November repair and qualification](review/cpu-oom-20261105/REVIEW.md).** The reproduced Lima socket leak is now repaired on host/guest 2.2.1. Separate Hub provisioning/latency and editor CPU gates remain open.

**Status as of 31 October: release blocker remains open.** A guest-agent socket leak now reproduces independently of WordPress. The evidence strongly matches an upstream Lima defect. No upgrade, restart, service/configuration change, memory tuning, release, deployment or Fleet action was performed.

The completed appearance implementation remains `c1f249f1` (`mrn-admin-ui-css` 3.3.0) on `codex/admin-editor-pilot-20261029`. All eight installed runtime files were rechecked against source and the recorded hashes. A serial authenticated refresh returned HTTP 200 for Dashboard, Pages, General settings, MRN Config Helper and the complex editor. The editor initialized four TinyMCE instances and retained its 19-section outline. These refresh checks did not submit content, settings or appearance preferences. They supplement the existing Light/Dark QA and full-page screenshot gallery; they do not replace it or clear the CPU qualification blocker.

## Historical OOM: a more precise attribution

The VM kernel recorded global OOM events on **30 October at 10:02:44–45 EDT**. It had 6 GiB configured RAM and 2 GiB swap; only 244 KiB swap remained in the first dump. The first task table records:

| Allocation | Recorded size |
| --- | ---: |
| Lima guest agent resident memory | 2,895.7 MiB |
| Lima guest agent swapped pages | 1,466.3 MiB |
| Unreclaimable kernel slab | 1,269.9 MiB |
| CLI PHP process invoking the first OOM | 170.8 MiB resident, no swap |

The kernel killed the guest agent despite its `oom_score_adj=-500`. The service restarted at 10:03:08 EDT and currently reports one restart. The PHP process was the allocation trigger; it was not the largest resident process. Web PHP workers and other services also occupied memory. Shared PHP pages must not be summed as independent private allocations.

No historical guest-agent heap profile or descriptor count was captured at the OOM, so the exact allocation history cannot be reconstructed. The evidence identifies guest-agent accumulation as a major likely contributor; it does not prove that every historical byte came from the newly reproduced leak.

## Bounded reproduction

Installed host `limactl` and guest `lima-guestagent` both report **2.1.2**. The VZ VM uses the guest-agent vsock service and forwarded local ports. Its current agent already held **48,630 descriptors**, including **48,620 sockets**, with very little active TCP traffic.

The test requested only the existing static `mrn-admin-tokens.css` file, eight times per path, serially with `Connection: close`. All 24 responses were HTTP 200. No PHP code, browser JavaScript, application submission or content save was involved. Each request had a ten-second timeout. The test would stop below 1.5 GB available memory or above 500 additional descriptors.

| Checkpoint | Agent descriptors | Socket descriptors | Delta from preceding checkpoint |
| --- | ---: | ---: | ---: |
| Baseline | 48,630 | 48,620 | — |
| Four-second idle control | 48,630 | 48,620 | 0 |
| Eight requests inside VM, loopback port 8088 | 48,630 | 48,620 | 0 |
| Eight requests from host, forwarded port 8088 | 48,638 | 48,628 | +8 |
| Eight requests through local HTTPS proxy | 48,646 | 48,636 | +8 |
| Twenty-second settle | 48,647 | 48,637 | +1 |

The final idle interval contained one additional socket, which is not attributed to the controlled requests. The 16 sockets from the two forwarded batches did not return to baseline. Agent RSS stayed near 544.6 MiB during this small test; available VM memory stayed above 4.3 GiB. This demonstrates descriptor retention, not a measured per-request heap growth rate or a new OOM. No new OOM event was found in the queried kernel history through the investigation.

## Upstream match and required follow-up

[Lima issue 5210](https://github.com/lima-vm/lima/issues/5210) describes one leaked guest-side socket per forwarded connection, distinct from the host reconnect leak fixed in 2.1.2. [Upstream fix 5216](https://github.com/lima-vm/lima/pull/5216) corrects blocked connection teardown and closes the dialed guest connection. The [tagged 2.2.0 implementation](https://github.com/lima-vm/lima/blob/v2.2.0/pkg/portfwdserver/server.go) contains that correction. The installed 2.1.2 version predates it. This version/path/reproduction match is strong evidence for the local forwarding defect.

The next infrastructure step is a coordinated Local Hub maintenance change using a supported Lima version containing this correction, with both host and guest binaries verified afterward. Treat that as separate infrastructure work because the VM serves other local projects. A restart alone would clear accumulated sockets without proving the defect fixed. Increasing RAM would not address retained connections.

Before clearing this blocker, repeat the same loopback/forwarded/proxy comparison on the repaired runtime, verify descriptors return to baseline after connections close, then run bounded sequential dashboard/editor workload and an idle/traffic soak while recording agent RSS, descriptor counts, swap and memory pressure. Inspect kernel history for further OOMs. Repeat the outstanding matched editor CPU measurements only after runtime stability is established.

## Evidence and scope

- [Parsed kernel task table and serial measurements](evidence/oom-investigation-20261031.json).
- [Installed-source and authenticated refresh](evidence/review-refresh-20261031.json).
- Historical kernel read: `sudo journalctl -k --since "2026-10-30 10:02:43" --until "2026-10-30 10:02:46" --no-pager` inside `mrn-openlitespeed` (VM local time).
- Snapshots read `/proc/meminfo`, `/proc/pressure/memory`, the current agent's `/proc/<pid>/status` and descriptor symlinks, plus native `systemctl show` memory/restart counters. No process command lines, environment variables, credentials, cookies, database dumps or client content are in the evidence.

This follow-up changes documentation/evidence only. Product code and the local runtime configuration remain unchanged. Component/API/accessibility regression results remain those already recorded in the appearance review report; no broad compatibility or release QA was begun. The documentation snapshot passes the MRN staged commit gate separately from runtime release qualification.
