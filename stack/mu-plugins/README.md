# Stack MU Plugins

This directory holds the stack-facing MU plugin wrapper layer that should be
deployed to the stack server under `/home/mrndev-stack-manager/stack/mu-plugins/`.

Canonical MU plugin source lives in `/Users/khofmeyer/Development/MRN/mu-plugins`.
The `mrn-loader.php` file owns the explicit subfolder load order.

Current entries:
- `mrn-loader`
- `mrn-admin-data-post-types`
- `mrn-active-style-guide`
- `mrn-admin-ui-css`
- `mrn-dashboard-support`
- `mrn-disable-comments`
- `mrn-editor-lockdown`
- `mrn-public-security-hardening`
- `mrn-reusable-block-library`
- `mrn-schema-bridge`
- `mrn-shared-assets`
- `mrn-site-colors`
- `mrn-updraft-local-retention`

Naming/version audit:
- `/Users/khofmeyer/Development/MRN/stack/MRN_PLUGIN_AUDIT.md`
