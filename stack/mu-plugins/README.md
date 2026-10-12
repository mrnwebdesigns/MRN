# Stack MU Plugins

This directory holds the stack-facing MU plugin wrapper layer that should be
deployed to the stack server under `/home/mrndev-stack-manager/stack/mu-plugins/`.

Canonical MU plugin source lives in `/Users/khofmeyer/Development/MRN/mu-plugins`.
The `mrn-loader.php` file owns the explicit subfolder load order.

Loader 1.6.2 also qualifies the retained SEOPress PRO 10.3, MainWP Dashboard
6.2.2 and WPForms Pro 1.10.2.1 initialization contracts. Before `init`, it
supplies only nonlocalized cron interval metadata; the vendor callbacks supply
their normal translated labels after `init`. It moves the original
WPForms public `objects()` bootstrap to the first `init` priority, preserving
its internal service order before other `init` and native admin callbacks. These compatibility paths are restricted to the named
versions. Vendor ZIPs and diagnostic reporting remain unchanged; unknown
versions/registrations still require normal qualification.

## Runtime Report

Loader `1.5.1` exposes a read-only stack report for parity verification:

- WP-CLI: `wp mrn stack-report`
- REST: `GET /wp-json/mrn/v1/stack-report`
- PHP: `mrn_loader_get_runtime_report()`
- MainWP Child signed transport: `mrn_stack_report_action=report` through
  `extra_execution`

The REST route requires `manage_options` by default. Change that capability only
through `mrn_loader_runtime_report_capability`; never make the route public. The
report contains wp-content-relative paths, versions, deterministic tree hashes,
active theme identifiers, missing/drifted required components, and legacy flat
MU collisions. It does not expose absolute server paths or credentials.

When `mrn-stack-release.lock.json` is deployed beside the loader, runtime hashes
and versions are compared to that release. Without a lock, the report remains
useful for loader/component presence but cannot claim release parity.

The MainWP transport is not a public endpoint. It runs only inside MainWP
Child's authenticated `mainwp_child_extra_execution` request and leaves other
extension responses untouched unless the exact report action is present.

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
