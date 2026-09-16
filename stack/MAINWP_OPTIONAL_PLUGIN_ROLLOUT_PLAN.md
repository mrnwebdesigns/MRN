# MainWP Optional Plugin Rollout Plan

## Purpose

This is the Fleet contract for an independently released, non-platform MRN
plugin. It is separate from the schema-2 platform package and supports both
catalog-only plugins and plugins that remain standard bootstrap defaults. An
entry in the component catalog or release registry is availability, not
installation or update authorization.

The initial contract is one exact site per plan and upgrade-only. The plan
builder refuses to create a plan when the plugin is absent. A new installation
requires a separately owner-authorized seeding plan and must never be inferred
from catalog membership, site tags, or a successful package preflight.

Cookie Consent and GTM Injector are a paired optional integration, but they
still receive separate one-plugin plans, preflights, backup receipts, applies,
readbacks, and rollback artifacts. Update GTM Injector first: version 1.0.14
fails closed when an active older Cookie Consent release cannot expose an
explicit state. Then update Cookie Consent. This order prevents an immediate-GTM
window between the two plugin updates. Release records may be added only after
their source commits merge to `origin/main`. The registered `1.1.43` and
`1.0.14` releases meet that source gate; do not attempt the rollout until the
Dashboard runs controller `0.9.3` and exposes both plugin main files.

MRN Database Retention is maintenance-only and catalog-only. Defender support
inside that plugin is conditional legacy compatibility; Defender is not a Stack
requirement. The ongoing mail-provider transition does not change the
provider-agnostic FluentSMTP retention contract.

## Release Inputs

- `manifests/component-catalog.json` provides classification and canonical
  source ownership.
- `manifests/optional-plugin-releases.json` binds the approved version to its
  merged source commit, main plugin file, artifact size, and SHA-256.
- `scripts/build-mainwp-optional-plugin-plan.py` validates one fresh inventory
  record and produces a plan matching
  `manifests/optional-plugin-update-plan.schema.json`.
- The release ZIP is retained at the exact registry path in the local release
  workspace. Catalog-only entries are not added to `manifests/plugins.txt`;
  standard-bootstrap entries keep their existing manifest membership.

## Inventory Input

Resolve the exact URL through MainWP, run a fresh sync scoped only to that site,
then save a sanitized input document shaped like this:

```json
{
  "schema_version": 1,
  "site": {
    "site_id": 123,
    "site_url": "https://example.com",
    "inventory_synced_at": "2026-09-14T12:00:00Z",
    "plugin": {
      "slug": "mrn-database-retention",
      "installed": true,
      "active": true,
      "version": "1.1.0"
    },
    "backup_readiness": {
      "ready": true,
      "provider": "UpdraftPlus",
      "remote_destination_configured": true,
      "wp_cli_available": true,
      "backup_command_available": true
    },
    "rollback_readiness": {
      "ready": true,
      "version": "1.1.0",
      "package_path": "/approved/releases/mrn-database-retention-1.1.0.zip",
      "package_sha256": "<64 lowercase hex characters>"
    }
  }
}
```

The inventory contains no credentials or customer data. `inventory_synced_at`
must be no more than 15 minutes old by default. Backup readiness proves only
that the site can create the required backup; it is not a backup receipt.
Rollback readiness requires the exact current-version package to be available
and checksum-valid before the update is authorized.

## Build A One-Site Plan

```bash
python3 stack/scripts/build-mainwp-optional-plugin-plan.py \
  --plugin-slug mrn-database-retention \
  --inventory /absolute/path/to/sanitized-inventory.json \
  --plan-id <unique-plan-id> \
  --output /absolute/path/to/plan.json
```

The builder reads the registered artifact path by default. It verifies the
component remains non-platform and catalog-only, the registered source commit
is clean merged `origin/main`, the ZIP checksum/size/root/main file/version, the
exact site URL and fresh inventory, installed/active/current/target versions,
backup readiness, and rollback artifact. The output explicitly reports whether
the operation is `upgrade-only`; it never emits a new-install plan.

## Released MainWP Controller Contract

The generic `mrn-mainwp/install-plugin-package-v1` ability validates packages,
confirmation, sites, and backup receipts, but it is not sufficient for optional
updates because it:

- can install the plugin when it is absent;
- does not return installed, active, current-version, or target-version state;
- requires a backup receipt even for its dry run;
- does not bind execution to the inventory version/state reviewed in preflight;
- does not require or report a checksum-verified plugin rollback artifact.

`mrn-mainwp-operations-api` `0.9.3` provides the dedicated
`mrn-mainwp/preflight-optional-plugin-update-v1`,
`mrn-mainwp/update-optional-plugin-v1`, and
`mrn-mainwp/rollback-optional-plugin-v1` abilities. Preflight uses POST transport
because it validates two ZIP payloads, but it is non-destructive and idempotent.
The abilities accept the exact site, main-file slug, versions, filenames,
SHA-256 values, and package bytes derived from the generated plan. The smallest
safe contract is:

1. Default `allow_new_install` to `false` and reject a missing target plugin.
2. Resolve one exact site and fetch live plugin inventory through MainWP Child's
   signed connection before returning preflight.
3. Return installed/active state, current and target versions, package SHA-256,
   backup readiness, rollback readiness, and `operation=upgrade-only`.
4. Sign or hash the reviewed preflight state. At apply time, re-read installed
   state/version and reject any change from that state before touching files.
5. Require `confirm=true` and a fresh successful remote database-only Updraft
   receipt for the exact site. The controller-generated precondition hash binds
   the reviewed plan and live state separately.
6. Preserve the current activation state. Do not activate an inactive plugin as
   a side effect of upgrading it.
7. Require the checksum-verified previous-version package in preflight, update,
   and rollback calls. The generated plan and approved artifact directory are
   the durable rollback evidence; the Dashboard stores no new persistent copy.
8. Delete temporary Dashboard upload material after success or failure.

Do not use the generic package installer for optional-plugin updates. Before a
site operation, `0.9.3` must be separately deployed to the named MainWP
Dashboard and the three abilities must be visible through the configured
`mainwp` MCP allowlist. Missing controller deployment or ability visibility is
a deployment blocker, not permission to use a browser, SSH, or direct mutation.
The merged `0.9.3` source allowlists Database Retention, Cookie Consent, GTM
Injector, Background Video Pop-Out Disabler, Announcements, Font Awesome Profile
Manager, SEO Helper, and reCAPTCHA Enterprise Manager. The connected Dashboard
must report that version and expose the exact requested main-file choice before
any site rollout.

## Ability Payload Mapping

Map the generated plan to the controller without changing any identity field:

```json
{
  "operation": "update",
  "site_id": 123,
  "site_url": "<plan.site.site_url>",
  "plugin_slug": "<plan.plugin.package.main_file>",
  "target": {
    "version": "<plan.plugin.target_version>",
    "filename": "<plan.plugin.package.filename>",
    "package_sha256": "<plan.plugin.package.sha256>",
    "package_base64": "<base64 of plan.plugin.package.path>"
  },
  "rollback": {
    "version": "<plan.preflight.rollback_readiness.version>",
    "filename": "<plan.preflight.rollback_readiness.package_filename>",
    "package_sha256": "<plan.preflight.rollback_readiness.package_sha256>",
    "package_base64": "<base64 of plan.preflight.rollback_readiness.package_path>"
  }
}
```

The numeric `site_id` example above represents `plan.site.site_id`; every other
placeholder names its exact plan source. Use that shape for preflight. For
update or rollback, omit `operation` and add
`precondition_hash`, `confirm=true`, and the exact site's `backup_receipt`.
For rollback preflight, change only `operation` to `rollback`. Encode package
bytes at invocation time; do not persist duplicate base64 payloads in the plan,
logs, or repository.

## Per-Site Deployment Sequence

1. Confirm owner authorization for one exact site URL and the named plugin.
2. Verify MainWP is connected to `wpcontrol.mrndev.io`; resolve the exact site
   from its URL and run a fresh targeted sync.
3. Produce the sanitized inventory and build the one-site plan. Stop if it says
   absent, stale, downgrade, not backup-ready, or not rollback-ready.
4. Call the dedicated optional-plugin preflight ability with `operation=update`
   plus both plan-locked packages. Compare its exact site URL, installed/active
   state, current/target versions, package SHA, readiness, and precondition hash
   with the local plan.
5. Create a labeled database-only Updraft backup on that site, send it to the
   configured remote destination, and poll to a verified successful receipt.
6. Reconfirm the exact plan, then call the dedicated update ability with both
   plan-locked packages, `confirm=true`, the precondition hash, and that receipt.
7. Require the exact target version, preserved activation state, package
   checksum, and consumed receipt in the result. Retain the plan and both ZIPs,
   then fresh-sync and read inventory again.
8. Run `wp mrn-db-retention policy`, `status`, and `cleanup --dry-run` only when
   those explicit post-update checks are authorized. Flush relevant caches only
   after a site write and verify public/admin health as applicable.

Each site receives its own plan, preflight, backup receipt, apply, readback, and
verification. Never reuse a receipt or pass an empty/multi-site target list for
an individually authorized rollout.

## Rollback Sequence

1. Stop further rollout and identify the exact site, update plan, previous
   version, previous package path, and previous package SHA.
2. Obtain owner authorization for rollback and fresh-sync the exact site.
3. Call the dedicated preflight ability with `operation=rollback` and both
   packages. Require the exact post-update version/state and retain its new
   rollback precondition hash.
4. Create and verify a new labeled database-only remote Updraft backup receipt.
5. Call the dedicated rollback ability with both packages, `confirm=true`, the
   rollback precondition hash, and the new receipt.
6. Require restoration of the exact prior version and activation state, then
   fresh-sync and verify inventory plus public/admin health.
7. Retain evidence until the owner closes the rollout. Use database restoration
   only through a separately approved recovery decision.
