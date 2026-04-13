# MRN WordPress Bootstrap Stack

This folder is a reusable bootstrap stack for new CloudPanel WordPress sites.

## What this gives you

- A plugin manifest (`manifests/plugins.txt`) for install/activate.
- A stack release snapshot (`STACK_VERSION.md`) and stack-wide notes (`CHANGELOG.md`).
- A builder/dev conventions guide (`BUILDER_CONVENTIONS.md`) for theme layouts, reusable blocks, and shared content rules.
- A curated developer handoff guide (`DEV_HANDOFF.md`) for backend/frontend team onboarding and Google Docs export.
- A front-end implementation guide (`FRONTEND_IMPLEMENTATION_GUIDE.md`) for Motion `inView`, Site Styles token usage, and Business Information template patterns.
- A theme strategy guide (`THEME_ROADMAP.md`) for the stack theme’s role, ownership boundaries, and development roadmap.
- A theme execution checklist (`THEME_TASKLIST.md`) for turning the roadmap into backend/frontend work.
- A site update process guide (`SITE_UPDATE_PROCESS.md`) for safe plugin/theme rollouts, cloned active-theme deploys, and later front-end handoff expectations.
- A stack workflow/ops guide (`STACK_OPERATIONS.md`) for local symlink workflow, server ownership, and sync/deploy rules.
- A canonical rollout checklist (`ROLLOUT_CHECKLIST.md`) for pre-flight QA, deploy-path decisions, post-deploy verification, and live parity checks.
- A plugin inventory (`PLUGIN_CATALOG.md`) and plugin doc template (`PLUGIN_DOC_TEMPLATE.md`) for documenting MRN plugins and MU plugins.
- First deep-dive plugin docs live in `plugin-docs/`.
- A per-site bootstrap script (`scripts/site-bootstrap.sh`).
- A CloudPanel cron scanner (`scripts/bootstrap-new-sites.sh`) that bootstraps only once per site.
- A canonical direct site-owner SSH public key file (`configs/site-owner-authorized-key.pub`) that bootstrap installs into each new site owner's `authorized_keys`.
- A canonical stack feature-deploy helper (`scripts/deploy-feature-stack-and-default-configs.sh`) that mirrors stack theme and MU changes to both the stack server and `default-configs.mrndev.io`.
- A live-site preflight helper (`scripts/preflight-live-site-deploy.sh`) that resolves the site owner, verifies direct site-owner SSH, normalizes malformed Updraft placeholder settings, and starts a clean pre-deploy backup.
- A release build helper (`scripts/build-release-zips.sh`) that rebuilds ignored plugin, MU plugin, and stack theme zip artifacts into `../releases/`.
- A rollout-contract QA script (`scripts/qa-rollout-contract.sh`) that verifies packaged theme parity, shared runtime presence, live active theme version parity, and rollout-owned CPT registration on `default-configs.mrndev.io`.
- A checklist for non-portable plugin configs (`configs/plugin-config-checklist.md`).
- Export/import payload storage (`configs/exports/`) and importer mapping manifest (`manifests/importers.txt`).

## Suggested flow in CloudPanel

1. Create a new WordPress site in CloudPanel.
2. A cron job runs `scripts/bootstrap-new-sites.sh` every 1-5 minutes.
3. The script finds unbootstrapped sites and runs `scripts/site-bootstrap.sh`.
4. Bootstrap clears host-provided standard plugins, installs the stack manifest, syncs MU plugins and `wp-content/shared`, activates the stack theme clone, and authorizes the canonical MRN site-owner public key for direct site-owner SSH.
5. A marker file is created so the same site is not bootstrapped again.

## First setup

1. Edit `manifests/plugins.txt` with your plugin slugs or zip URLs.
2. Review defaults inside `scripts/site-bootstrap.sh` (timezone, permalink, admin email).
3. Add optional plugin import scripts to `configs/importers/`.
4. Manage importer mappings in `manifests/importers.txt` (or through Stack Manager UI).
   - Supported by default importer script:
   - `option_json|file.json|option_name`
   - `option_text|file.txt|option_name`
   - `mrn_license_vault_json|file.json|overwrite`
   - `mrn_unified_export_zip|file.zip`
5. Add a root cron entry on the CloudPanel server:

```bash
*/5 * * * * /bin/bash /opt/mrnplugins/scripts/bootstrap-new-sites.sh --stack-root /opt/mrnplugins >> /var/log/mrnplugins-bootstrap.log 2>&1
```

Adjust `/opt/mrnplugins` to wherever you deploy this folder on the server.
