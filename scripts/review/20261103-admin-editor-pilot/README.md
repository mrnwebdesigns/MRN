# Admin editor review migration — 3 November 2026

Owner authorized a dedicated protected mrndev.io review copy on 3 November.
This is a continuation of the existing editor/admin pilot, not a release or Fleet
activation. Source remains on `codex/admin-editor-pilot-20261029`.

Exact destination: `https://admin-editor-pilot.mrndev.io`, CloudPanel site owner
`mrndev-admin-editor`, web root
`/home/mrndev-admin-editor/htdocs/admin-editor-pilot.mrndev.io`, new database/user
`mrneditorpilot`. Source: the authorized `admin-editor-pilot.localhost` snapshot.
The original imported Trilliant source and the local review copy are preserved.

## Bounded workflow

1. Confirm the exact host is absent in CloudPanel, source fixture hashes, server
   capacity, the MRN Web Designs Cloudflare zone, and canonical backup identity.
2. Provision a new empty PHP site/database using `provision.py`. It refuses an
   existing target. `--database-only` resumes only a partially provisioned empty
   target with the expected owner; it refuses an existing database or WordPress
   configuration. CloudPanel database identifiers use letters/digits and the
   generated 32-character password fits its 40-character limit.
3. Require HTTP Basic Auth before copying any WordPress files. Review access is
   stored in MRN-business / Production Hub / MRN Admin Editor Pilot - Review access.
   Do not place secrets, dumps, auth cookies or licensed plugin copies in Git.
4. Bootstrap an empty WordPress database with the matching core and backup plugin,
   disabled cron/mail/indexing, then configure the approved isolated backup prefix
   `mrn-webdesigns-backups/sites/admin-editor-pilot-mrndev-io`.
5. Before restoring the source database or installing the pilot code, create and
   verify a labeled database-only remote Updraft backup of the destination. Never
   overwrite an existing target, use another site's S3 prefix, or substitute a
   local dump for this required gate.
6. Restore through a tracked, exact-target migration; preserve destination DB
   configuration, normalize URLs with WordPress-aware tooling, and retain all
   native ACF content. Transfer uploads as independent files, never as the local
   symlink to Trilliant. Exclude ad hoc login scripts, logs, caches and backups.
7. Use the committed pilot components and the existing emergency boolean. Its
   hostname boundary now permits only Local Hub plus this exact review hostname.
8. Qualify native save/reload, truncated-save protection, both appearances,
   new-page section creation, Screen controls and account appearance actions.
   Capture before/after screenshots and matched runtime performance evidence.

## Dependencies to preserve

- Source WordPress 7.0.4, ACF Pro 6.8.9 and PHP 8.4; confirm again after import.
- Approved 19- and 32-section draft fixtures and their ACF hashes.
- The Local Hub save-integrity helper outside its web root is byte-identical to
  `stack/themes/mrn-base-stack/inc/builder/editor-save-integrity.php` at the source
  commit. Preserve that helper when the cloned theme does not load it itself.
- AME owns navigation/branding; Classic Editor, ACF, native form storage,
  publishing and existing MRN components retain their ownership.

## Current state

The isolated clone is installed, HTTPS validates, unauthenticated access returns
HTTP 401, and native 19/32-section content hashes match the source after URL
normalization. Admin UI 3.3.3 / Editor Lockdown 1.0.42 are installed. Labeled
remote Updraft database backups precede clone activation, certificate work, QA
and the account-node compatibility correction. Both-theme captures and scoped
accessibility checks pass. See
[the review record](../../../docs/admin-editor-pilot/appearance/review/REFINEMENTS-ROUND2.md).

The owner explicitly approved the exact save exception on 3 November. The guarded
`apply-save-challenge.py` matched live rule version 8, saved a private provider
configuration rollback, patched only that rule, and verified version 9 plus
unchanged other custom rules/order and managed rules. See
[the readback receipt](../../../docs/admin-editor-pilot/appearance/review/refinements-round2/waf-applied-receipt.json).
`proposed-save-challenge.json` remains the original approved before/after artifact;
its preparation status is historical. The apply script deliberately refuses
changed or already-applied live state. Native 19/32-section saves, explicit reloads, full stored hashes and truncated-save
HTTP 400 rejection pass. Save responses took 41.6s / 120.5s in one serial run and
remain a performance blocker. The zero-row test exposed the outline choosing
ACF top insert-before instead of bottom append. Commit `bc91c90f` corrects only
that selector; `update-native-append.py` installed its three files after fresh
backup `63f4d228cb37`, retained rollback and verified installed hashes.
The complete installed-picker 0→1→0 workflow now passes, including both native
saves and explicit reloads. The final empty fixture is restored to zero rows;
complex fixture content/ACF hashes remain unchanged.

The migration's Basic Auth directives are directly in this site's Nginx vhost,
independent of CloudPanel's per-site Basic Auth switch. The realm is
`MRN editor review`. The anonymous/fake-cookie boundary tests pass.
Codex's embedded browser rejected Basic Auth; live authentication remains
protected. Native Chrome navigation exposes the ordinary sign-in prompt. The
static paired gallery is available for Codex annotations while live access is
resolved. No release/Fleet action is part of this migration.

The canonical shell wrapper cannot traverse `/home` with the new site-owner account.
`backup.php` uses its existing Updraft API fallback and the same history/checksum/
upload-log verification, restricted to this exact host and isolated S3 prefix.
The migration additionally verifies the remote object through AWS CLI. No shared
permissions or deployment-tool source are changed.
