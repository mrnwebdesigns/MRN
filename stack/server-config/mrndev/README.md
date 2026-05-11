# MRN Dev Server Config

This folder tracks operational files used by the `mrndev` server.

Runbook:
- `RUNBOOK.md` is a quick copy/paste command playbook for incident recovery, SSH/fail2ban fixes, monitoring operations, and config sync.

Manifest:
- `manifest.txt` maps local repo files to remote server paths.
- Format: `local_path|remote_path|access_mode|file_mode`

Access modes:
- `rw`: pull + push allowed by sync script.
- `ro`: pull-only snapshot (tracked in Git, not pushed by default).

Current intent:
- `stack/scripts/stack-load-alerts-user.sh` is the editable source for user-level load alerts.
- Root-managed files (`/etc/cron.d/*`, `/usr/local/sbin/*`) are tracked as snapshots so changes are reviewable.
- `root-export/stack-config-export.sh` is the root-side exporter that mirrors privileged files into `/home/mrndev-stack-manager/stack/server-config-export/` for pull tracking.

Root install (one-time on server):
1. Copy `root-export/stack-config-export.sh` to `/usr/local/sbin/stack-config-export.sh` (mode `750`, owner `root:root`).
2. Copy `etc/cron.d/stack-config-export` to `/etc/cron.d/stack-config-export` (mode `644`, owner `root:root`).
