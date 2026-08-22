# Stack MU Plugins

This directory holds the stack-facing MU plugin wrapper layer that should be
deployed to the stack server under `/home/mrndev-stack-manager/stack/mu-plugins/`.

Canonical MU plugin source lives in `/Users/khofmeyer/Development/MRN/mu-plugins`.
The `mrn-loader.php` file owns the explicit subfolder load order.

## Runtime Report

Loader `1.5.0` exposes a read-only stack report for parity verification:

- WP-CLI: `wp mrn stack-report`
- REST: `GET /wp-json/mrn/v1/stack-report`
- PHP: `mrn_loader_get_runtime_report()`

The REST route requires `manage_options` by default. Change that capability only
through `mrn_loader_runtime_report_capability`; never make the route public. The
report contains wp-content-relative paths, versions, deterministic tree hashes,
active theme identifiers, missing/drifted required components, and legacy flat
MU collisions. It does not expose absolute server paths or credentials.

When `mrn-stack-release.lock.json` is deployed beside the loader, runtime hashes
and versions are compared to that release. Without a lock, the report remains
useful for loader/component presence but cannot claim release parity.

Current entries:
- `mrn-loader`
- `mrn-admin-data-post-types`
- `mrn-active-style-guide`
- `mrn-admin-ui-css`
- `mrn-dashboard-support`
- `mrn-disable-comments`
- `mrn-editor-lockdown`
- `mrn-environment-runtime`
- `mrn-public-security-hardening`
- `mrn-schema-bridge`
- `mrn-shared-assets`
- `mrn-site-colors`
- `mrn-updraft-local-retention`

Naming/version audit:
- `/Users/khofmeyer/Development/MRN/stack/MRN_PLUGIN_AUDIT.md`
