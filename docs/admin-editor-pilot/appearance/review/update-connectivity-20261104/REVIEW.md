# Review-site update connectivity — 4 November 2026

Applied source commit `2e9d2683` only to `admin-editor-pilot.mrndev.io`.
The native Updates page now loads without the reported PHP warnings and displays
fresh WordPress/plugin update metadata. No core, plugin or theme update was installed.
Source remains on the dedicated pilot branch; no release, Fleet activation or
production change occurred.

## Cause and correction

The clone bootstrap set `WP_HTTP_BLOCK_EXTERNAL=true` with only four S3 backup
hosts in `WP_ACCESSIBLE_HOSTS`. Read-only runtime probes reproduced
`http_request_not_executed` for WordPress, AME and UpdraftPlus. These failed before
TLS negotiation: the generic WordPress secure-connection message was misleading.
[WordPress documents this allowlist behavior](https://developer.wordpress.org/reference/classes/wp_http/block_request/).

The development environment also resolved `WP_DEBUG=true`,
`WP_DEBUG_DISPLAY=true`, `WP_DEBUG_LOG=false`. Warning output preceded native
response headers, causing the secondary headers-already-sent message.

Only `wp-config.php` changed:

- Added `api.wordpress.org`, `adminmenueditor.com`, `updraftplus.com` to the
  existing backup-host allowlist. No wildcard or blanket unblock.
- Set `WP_DEBUG_DISPLAY=false` and `WP_DEBUG_LOG` to an owner-only file outside
  the web root. Debugging stays enabled; warning output no longer enters HTML.
- Retained the external-request block, mail block, disabled cron, noindex,
  authentication, WAF and all product components.

## Verification

| Check | Result |
| --- | --- |
| Exact owner, hostname and original configuration | Pass; dry-run matched expected bootstrap constants |
| Remote DB backup | Updraft `9dc817f10ba6`, labeled `pre-admin-pilot-update-connectivity-20261104`, verified success/upload/hash/history |
| Independent S3 verification | Exact isolated object exists, 6,297,174 bytes, matching backup receipt; modified 2026-11-04 13:41:44 UTC |
| Config rollback | Original retained privately; before/after hashes recorded in `install-receipt.json` |
| HTTPS metadata | WordPress 200 / 81 ms; AME 200 / 322 ms; Updraft 200 / 132 ms, certificate validation enabled |
| Metadata content | WordPress and AME version metadata present. Minimal unauthenticated Updraft probe returned JSON without a version; the native updater successfully displayed Updraft 2.26.14.0 as available |
| Isolation | Unrelated test hostname still blocked, S3 allowed, cron disabled, indexing 0, mail blocked |
| Private log | Mode 0600; zero occurrences of the four reported request/metadata/header errors after probes and native reload. Other diagnostic log content is not included |
| Native browser | Existing signed-in Codex tab refreshed; no reported warning text, fresh check time, 12 plugin updates and one core update shown |
| Installed versions | Browser still shows WP 7.0.4, AME 2.34, Updraft 2.26.7.0; no upgrader action invoked |
| Source checks | PHP lint; candidate transform; idempotent rerun; wrong-host, isolation, allowlist, debug and duplicate-definition drift refusal |
| MRN QA | Staged task gate passed, proof tree `2e4b43f11c1ce76428fb396dc1763ba8dc3a5cdc`; PHPStan advisory: missing wp-cli-stubs in temporary snapshot |

MRN QA runtime API, axe, performance, parity and release checks were intentionally
not run by the static commit gate. This patch adds no API handler, UI code or
client payload; targeted live HTTP and native-page verification above cover its
runtime behavior. Full editor saves and both-theme visual tests were not repeated
for a configuration-only repair. These results do not qualify the existing editor
CPU/memory, slow-save, Local Hub OOM or release gates.

The native automatic-update-overdue notice remains: cron is deliberately disabled
on this isolated review copy. This repair does not enable scheduled jobs or alter
automatic-update preferences. The existing Cloudflare list-form POST issue is
also unchanged.

## Evidence

- [Installation and backup receipt](install-receipt.json)
- [Runtime metadata/isolation checks](runtime-after.json)
- [Refreshed Codex Updates page](updates-after.png)
- [Guarded repair](../../../../../scripts/review/20261103-admin-editor-pilot/repair-update-connectivity.py)
- [Read-only verification](../../../../../scripts/review/20261103-admin-editor-pilot/verify-update-connectivity.php)

Rollback requires the same backup gate and restoring the saved configuration
from the private path recorded in the receipt. It would restore the former
metadata block and visible warnings; do not run it as routine cleanup.
