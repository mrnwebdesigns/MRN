# MRN Premium Plugin License Delivery

Premium plugin licenses and vendor account passwords are **not** part of the
Stack site bootstrap. `stack/scripts/site-bootstrap.sh` installs and activates
the premium plugins but never reads or applies a license. A missing, rejected
or expired license can no longer fail a bootstrap or block Dev enrollment.

Licenses are applied after bootstrap, one exact site at a time, through MainWP.

## Components

| Piece | Where | Role |
| --- | --- | --- |
| Coverage registry | `stack/manifests/premium-licenses.txt` | Plugin -> handler -> logical credential keys. Names only, never values. Not shipped in the bootstrap package. Checked by `stack/scripts/qa-license-coverage.sh`. |
| Canonical secrets | MRN-business 1Password item (one field per logical key) | The only place a license key or vendor password is edited. |
| Sealed store | MainWP Dashboard, MRN MainWP Operations API >= 0.13.21 | Per-handler Sodium-sealed options, key derived from the Dashboard's server salts. Written only by the host CLI `wp mrn-mainwp-licenses set <handler>` (stdin). |
| Sync tool | `tools/plugin-licenses/sync-dashboard-licenses.sh` | Operator-run. `--check` compares fingerprints read-only; `--apply` seals changed handlers on the Dashboard. |
| Abilities | `mrn-mainwp/preview-plugin-licenses-v1`, `mrn-mainwp/apply-plugin-licenses-v1` | Exact-site preview/apply. Inputs never accept credential values; outputs carry fingerprints and closed statuses only. |
| Child operation | MRN Stack Deployment Agent >= 0.3.11, `mrn_plugin_licenses` | Activates and verifies each vendor through its own API, restores the previous local license if a replacement is rejected, and never stores or returns credential values itself. |

Handlers: `acf_pro`, `ame_pro`, `happyfiles_pro`, `seopress_pro`,
`updraftplus_premium`, `wpforms`.

## Boundaries

- **Repository and bootstrap:** no secret values, ever. The registry holds
  logical key names only.
- **1Password:** MRN-business account only. The sync tool exports
  `OP_ACCOUNT=mrnwebdesigns.1password.com`. Production Hub may index the item
  for presence checks (`reveal_values: false`), and it stays the infrastructure
  credential system. License values never pass through Production Hub or MCP.
- **Dashboard:** values exist only as authenticated ciphertext. A Dashboard salt
  change makes them unreadable; re-run the sync tool to reseal.
- **Transport:** a value leaves the Dashboard only inside a signed MainWP
  `extra_execution` apply request, and only for handlers the child's current
  plan will write. Ability responses and child messages are scrubbed of every
  credential value.
- **Child sites:** each vendor plugin stores its own license as it always has.
  UpdraftPlus Premium discards the account password once the site has claimed
  its add-on, and authenticates afterwards with a per-site token.

## Fingerprints

Previews compare a 16-hex fingerprint: the first 64 bits of the SHA-256 of
`mrn-plugin-license-v1\0<handler>\0<identifying value>`. The identifying value is
the license key, or, for UpdraftPlus, only the normalized account email. A
password is never fingerprinted.

## New site

1. Bootstrap completes. Licenses are not part of that run.
2. Onboard the site to MainWP, sync it, and confirm that MRN Stack Deployment
   Agent 0.3.11 or later is active.
3. Run `preview-plugin-licenses-v1` for the exact site. Every handler
   should show `unlicensed` / `apply`.
4. `start-database-backup-v1`, then `get-database-backup-status-v1` for a
   receipt. This is the standard pre-write backup gate.
5. Run `apply-plugin-licenses-v1` with `confirm: true`, the preview's
   `plan_sha256`/`expires_at` and the receipt. Each handler returns `verified`,
   or `applied_unverified` for AME (no local activation API), or a closed
   failure with the vendor's message.

## Changing a key or a vendor password (one place)

1. Update the field in the 1Password item.
2. `tools/plugin-licenses/sync-dashboard-licenses.sh --vault <vault> --item <item> --wp-path <dashboard path> --check`
   shows which handlers differ.
3. Verify the Dashboard's own backup, then re-run with
   `--apply --dashboard-backup-verified`.
4. For each affected site, run preview then apply. A site whose stored key
   already matches shows `current` and is skipped. Use `force: true` to
   re-apply anyway, for example to reconnect UpdraftPlus Premium on a site whose
   token has lapsed.

Sites already connected to UpdraftPlus Premium keep working after a password
change, because they hold a site token rather than the password. Only sites that
connect or reconnect need the new password.

## Failure handling

- `vendor_rejected`: the vendor refused the credential. The previous local
  license options are restored, and the message explains why (for example
  UpdraftPlus "password wasn't recognised").
- `MRN_PLUGIN_LICENSES_OUTCOME_UNKNOWN`: the transport failed after dispatch.
  The per-site lock is kept. Preview the site and reconcile before retrying.
- `license_not_provisioned`: the Dashboard store has no readable value for
  that handler. Run the sync tool.
