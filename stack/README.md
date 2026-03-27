# MRN WordPress Bootstrap Stack

This folder is a reusable bootstrap stack for new CloudPanel WordPress sites.

## What this gives you

- A plugin manifest (`manifests/plugins.txt`) for install/activate.
- A per-site bootstrap script (`scripts/site-bootstrap.sh`).
- A CloudPanel cron scanner (`scripts/bootstrap-new-sites.sh`) that bootstraps only once per site.
- A checklist for non-portable plugin configs (`configs/plugin-config-checklist.md`).
- Export/import payload storage (`configs/exports/`) and importer mapping manifest (`manifests/importers.txt`).

## Suggested flow in CloudPanel

1. Create a new WordPress site in CloudPanel.
2. A cron job runs `scripts/bootstrap-new-sites.sh` every 1-5 minutes.
3. The script finds unbootstrapped sites and runs `scripts/site-bootstrap.sh`.
4. A marker file is created so the same site is not bootstrapped again.

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
