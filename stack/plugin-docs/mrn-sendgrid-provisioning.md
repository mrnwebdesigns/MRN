# SendGrid Provisioning

## Summary

- Name: `MRN SendGrid Provisioning`
- Slug: `mrn-sendgrid-provisioning`
- Type:
  - standard plugin
- Current version: `0.1.0`
- Source path:
  - `/Users/khofmeyer/Development/MRN-plugins/mrn-sendgrid-provisioning`

## Purpose

- Provisions per-site SendGrid identity so every Stack site sends through its own SendGrid Subuser instead of the shared parent account.
- Split out of `mrn-config-helper` on 2026-08-19 (`release_group: config-helper-decomposition` in `stack/manifests/component-catalog.json`) to isolate SendGrid credentials and the parent management key from general site configuration.
- Deliberately does not activate or configure FluentSMTP itself. Bootstrap prepares the subuser, site key, and domain authentication ahead of launch; delivering the finished key into a live site's FluentSMTP connection is a separate, ops-owned go-live step outside this plugin.

## Admin Surface Area

- Adds `Settings -> SendGrid Provisioning`.
- Shows management-key detection status, the site's subuser, the site sending key, and domain authentication status/DNS records.
- Actions: create the site's `mail.send`-only API key, create domain authentication, validate DNS, and reassociate a mismatched domain-auth record to this site's subuser.

## Provisioning Model

- One high-privilege `MRN_SENDGRID_MANAGEMENT_API_KEY` (constant or environment variable, resolved the same way `mrn-config-helper` resolved it) is shared across sites and used only against SendGrid's account-level Subuser/API-key/domain-auth endpoints.
- Required scopes on that key: `api_keys.create`, `api_keys.read`, `subusers.read`, `subusers.create`, `subusers.update`, `subusers.credits.read`, `subusers.credits.update`, `subusers.credits.remaining.read`, `subusers.credits.remaining.update`, `whitelabel.read`, `whitelabel.update`, `ips.read`, `ips.assigned.read`.
- Subuser creation requires the account to resolve to exactly one assigned dedicated IP (`GET /v3/ips/assigned`); it fails closed rather than guessing when that's not true.
- Domain authentication is associated to the owning subuser (`POST /v3/whitelabel/domains/{id}/subuser`), never duplicated. An existing domain-auth record that doesn't match this site's subuser is surfaced on the settings page for an explicit reassociation action, not touched automatically.
- New sites only: this plugin does not migrate sites that already have a flat API key/domain-auth on the parent account. That migration is a separate, ops-owned project (`/Users/khofmeyer/Development/MRN Infrastructure Ops/scripts/sendgrid-subuser-*`), whose proven policy (assigned-IP handling, associate-not-duplicate, required scopes) this plugin mirrors in PHP for server-side bootstrap use.

## Bootstrap Contract

- `stack/scripts/site-bootstrap.sh`'s `provision_external_services()` calls `MRN_SendGrid_Provisioning::bootstrap_site_provisioning( home_url( '/' ) )` when this plugin is active, gated by `STACK_BOOTSTRAP_SENDGRID_AUTO_PROVISION` (default on).
- Idempotent: re-running bootstrap reuses an existing subuser/site key/domain-auth rather than recreating them.

## Data / Storage

- Main option: `mrn_sendgrid_provisioning_settings`.
- Stores: subuser username, site API key metadata (id/name/created timestamp — never the key secret's origin beyond what FluentSMTP needs), domain/subdomain/DKIM selector, domain-auth id/validity/DNS records, and any detected domain-auth policy mismatch.
- Reads (does not own) `mrn-config-helper`'s `mrn_config_helper_get_site_sender_name()`/`_email()` public wrappers to keep FluentSMTP's sender identity in sync.
- Writes into FluentSMTP's `fluentmail-settings` option only when FluentSMTP is active and a site API key exists.

## Dependencies / Integrations

- WordPress settings API.
- `mrn-config-helper` (soft): sender name/email wrappers only; guarded with `function_exists`/`class_exists` so this plugin degrades gracefully if Config Helper is inactive.
- FluentSMTP (soft): sync is skipped entirely if FluentSMTP isn't active.

## Security Notes

- Settings page and all admin-post handlers are `manage_options` only, nonce-checked.
- The parent management key is host-managed only (constant/environment); this plugin never writes it to the database.
- Subuser passwords are generated per-creation via `wp_generate_password()` and never stored; provisioning authenticates as the subuser via `On-Behalf-Of` with the parent key, not subuser credentials.

## Risks / Gotchas

- If the SendGrid account's assigned dedicated IP changes or becomes ambiguous, new subuser creation fails closed with a warning rather than guessing an IP.
- A domain-auth policy mismatch requires a human to click "Reassociate" on the settings page; bootstrap will not silently reassign an existing domain-auth record.
