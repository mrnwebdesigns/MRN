# MRN WordPress Bootstrap Stack

This folder is a reusable bootstrap stack for new CloudPanel WordPress sites.

## Environment and installation ownership

The future hosting platform owns site and environment selection. Its canonical vocabulary is the `stack` or `plain` site profile combined with the `dev`, `staging`, or `production` environment. This repository owns WordPress component source, release metadata, package inputs, and compatibility contracts; it does not define a competing environment-profile system.

`manifests/plugins.txt` is currently the Stack bundle input consumed by the hosting platform. Optional feature selection belongs in the hosting platform and should resolve components from the MRN catalog. Development-only, maintenance-only, and optional components must not become production defaults merely because MRN maintains them.

`scripts/site-bootstrap.sh` honors `--site-profile` and `MRN_SITE_PROFILE` (`stack` or `plain`) and supports profile-scoped manifest entries that end in `|stack` or `|plain` so optional shared plugins can stay out of the plain-profile bootstrap.

## What this gives you

- A plugin manifest (`manifests/plugins.txt`) for install/activate.
- A stack release snapshot (`STACK_VERSION.md`) and stack-wide notes (`CHANGELOG.md`).
- A release versioning strategy (`RELEASE_VERSIONING_STRATEGY.md`) for consistent component version bumps and packaging.
- A builder/dev conventions guide (`BUILDER_CONVENTIONS.md`) for theme layouts, reusable blocks, and shared content rules.
- A curated developer handoff guide (`DEV_HANDOFF.md`) for backend/frontend team onboarding and Google Docs export.
- A reusable feature prompt template (`FEATURE_PROMPT_TEMPLATE.md`) for scoping new stack work with shared accessibility, performance, and rollout requirements.
- A front-end implementation guide (`FRONTEND_IMPLEMENTATION_GUIDE.md`) for Motion `inView`, Site Styles token usage, and Business Information template patterns.
- A theme strategy guide (`THEME_ROADMAP.md`) for the stack theme’s role, ownership boundaries, and development roadmap.
- A theme execution checklist (`THEME_TASKLIST.md`) for turning the roadmap into backend/frontend work.
- A site update process guide (`SITE_UPDATE_PROCESS.md`) for safe plugin/theme rollouts, cloned active-theme deploys, and later front-end handoff expectations.
- A stack workflow/ops guide (`STACK_OPERATIONS.md`) for local symlink workflow, server ownership, and sync/deploy rules.
- A Local environment pull/deploy guide (`../local/LOCAL_ENV_WORKFLOW.md`) for using Local like a site environment endpoint.
- A canonical rollout checklist (`ROLLOUT_CHECKLIST.md`) for pre-flight QA, deploy-path decisions, post-deploy verification, and live parity checks.
- A schema and AI discovery baseline (`SCHEMA_DISCOVERY_BASELINE.md`) for active SEO provider ownership, CPT mappings, editor controls, crawler policy, and launch checks.
- An authoritative machine-readable component inventory (`manifests/component-catalog.json`), human catalog (`PLUGIN_CATALOG.md`), governance rules (`PLUGIN_GOVERNANCE.md`), historical plugin audit (`MRN_PLUGIN_AUDIT.md`), and plugin doc template (`PLUGIN_DOC_TEMPLATE.md`). Catalog inclusion does not imply default installation.
- First deep-dive plugin docs live in `plugin-docs/`.
- A per-site bootstrap script (`scripts/site-bootstrap.sh`).
- A CloudPanel cron scanner (`scripts/bootstrap-new-sites.sh`) that bootstraps only once per site.
- A canonical direct site-owner SSH public key file (`configs/site-owner-authorized-key.pub`) that bootstrap installs into each new site owner's `authorized_keys`, after removing group/other home-directory write access required by OpenSSH StrictModes.
- A canonical stack feature-deploy helper (`scripts/deploy-feature-stack-and-default-configs.sh`) that mirrors stack theme and MU changes to both the stack server and `default-configs.mrndev.io`.
- A live-site preflight helper (`scripts/preflight-live-site-deploy.sh`) that resolves the site owner, verifies direct site-owner SSH, detects malformed Updraft placeholder settings, and requires a verified database-only remote backup before every real deployment.
- A canonical backup policy (`BACKUP_POLICY.md`) with per-site S3 prefixes, staggered daily schedules, four-set retention, development-backup hygiene, and a backup gate for all non-dry-run shared-runtime writes.
- A canonical Cloudflare security policy (`CLOUDFLARE_SECURITY_POLICY.md`) with validated, plan-aware development and production intent profiles under `configs/cloudflare/`.
- A Local environment workflow helper (`../local/scripts/local-env-workflow.sh`) that pulls into Local and deploys with an explicit site-vs-stack scope prompt.
- A repo shortcut command (`../scripts/mrn`) for `mrn pull-site` and `mrn deploy-site`.
- A nightly Local sync helper (`../local/scripts/nightly-pull-mrndev-sites.sh`) for discovered `*.mrndev.io` sites.
- A release build helper (`scripts/build-release-zips.sh`) that rebuilds ignored plugin, MU plugin, and stack theme zip artifacts into `../releases/`.
- A rollout-contract QA script (`scripts/qa-rollout-contract.sh`) that verifies packaged theme parity, shared runtime presence, live active theme version parity, and rollout-owned CPT registration on `default-configs.mrndev.io`.
- A Cloudflare policy QA script (`scripts/qa-cloudflare-security-policy.sh`) that validates both declarative profiles without contacting or changing Cloudflare.
- A checklist for non-portable plugin configs (`configs/plugin-config-checklist.md`).
- Export/import payload storage (`configs/exports/`) and importer mapping manifest (`manifests/importers.txt`).

## Suggested flow in CloudPanel

1. Create a new WordPress site in CloudPanel.
2. A cron job runs `scripts/bootstrap-new-sites.sh` every 1-5 minutes.
3. The script finds unbootstrapped sites and runs `scripts/site-bootstrap.sh`.
4. Bootstrap clears host-provided standard plugins, installs the stack manifest, syncs MU plugins and `wp-content/shared`, activates the stack theme clone, and authorizes the canonical MRN site-owner public key for direct site-owner SSH.
5. A marker file is created so the same site is not bootstrapped again.

## First setup

1. Edit `manifests/plugins.txt` with your plugin slugs or zip URLs, and add `|stack` or `|plain` when an entry should be profile-scoped.
2. Review defaults inside `scripts/site-bootstrap.sh` (timezone, permalink, admin email).
   - Optional stack-managed reCAPTCHA Enterprise secret files consumed by bootstrap:
   - `secrets/recaptcha-enterprise-project-id.txt`
   - `secrets/recaptcha-enterprise-service-account-email.txt`
   - `secrets/recaptcha-enterprise-private-key.pem`
   - `secrets/recaptcha-enterprise-allowed-domains.txt` (optional)
   - `secrets/recaptcha-enterprise-default-integration-type.txt` (optional)
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
