# Config Helper

## Summary

- Name: `Config Helper`
- Slug: `mrn-config-helper`
- Type:
  - standard plugin
- Current version: `0.1.25`
- Source path:
  - `/Users/khofmeyer/Development/MRN/plugins/mrn-config-helper`

## Purpose

- Centralizes common site configuration that would otherwise be scattered across plugins or user-specific admin settings.
- Gives admins one place to manage sender identity, notification email behavior, GTM, dashboard lock roles, and site-wide social links.
- It is primarily:
  - admin utility
  - stack configuration
  - light theme-integration support

## Admin Surface Area

- Adds:
  - `Settings -> Site Configurations`
- The settings screen is now organized into tabs:
  - `General`
  - `Integrations`
  - `Social`
  - `Admin`
- Current settings areas include:
  - Site Identity
  - Fluent SMTP
  - Google Tag Manager
  - External APIs
  - Social Media
  - Dashboard Controls
- External APIs currently includes:
  - UptimeRobot API key storage fallback
  - a nonce-protected admin connection test against UptimeRobot's `getMonitors` API
  - a shared external-API secret resolution pattern for future services
  - current-site monitor management for UptimeRobot:
    - fetch matching monitors for the current site URL
    - add a new monitor for the current site
    - remove an existing matching monitor
- Uses the WordPress media modal on its own settings page for social icon selection.
- Also supports a searchable Font Awesome chooser for social icons when the shared asset layer is available.

## Front-End / Theming Behavior

- Does not directly render front-end markup by itself.
- Exposes front-end-consumable site configuration so theme code can render it intentionally.
- Current front-end-facing helper:
  - `mrn_config_helper_get_social_links()`
  - `mrn_config_helper_get_uptime_robot_settings()`
- UptimeRobot helper return shape:
  - `api_key`
  - `source` (`constant`, `environment`, or `database`)
- External API secret pattern:
  - Config Helper now uses a shared internal registry for external API credentials
  - each service can define:
    - DB fallback option key
    - constant override names
    - environment override names
    - optional admin test action metadata
- Current social-link row shape:
  - `icon_type`
  - `icon_id`
  - `icon_url`
  - `fa_style`
  - `fa_name`
  - `fa_class`
  - `url`
- Intended usage:
  - theme header/footer or other front-end template code can call `mrn_config_helper_get_social_links()`
  - iterate the returned rows
  - if `icon_type === 'media'`, render the image icon linked to the destination URL
  - if `icon_type === 'fontawesome'`, render the stored Font Awesome class linked to the destination URL

## Developer Hooks / Extension Points

- Public class helper:
  - `MRN_Config_Helper::get_social_links()`
  - `MRN_Config_Helper::get_uptime_robot_settings()`
- Public wrapper helper:
  - `mrn_config_helper_get_social_links()`
  - `mrn_config_helper_get_uptime_robot_settings()`
- Theme/front-end recommendation:
  - prefer the wrapper helper over reaching into plugin internals directly
  - treat the plugin as a configuration source, not a markup renderer

## Data / Storage

- Main option:
  - `mrn_helper_settings`
- Also syncs into other plugin options when configured:
  - Fluent SMTP option: `fluentmail-settings`
  - GTM Injector option: `mrn_gtm_settings`
  - legacy GTM option: `mrn_gtm_container_id`
- Current `mrn_helper_settings` areas include:
  - sender name/email
  - site notification email
  - SendGrid API key
  - GTM container ID
  - UptimeRobot API key fallback
  - dashboard lock roles
  - social links
- UptimeRobot key resolution order:
  - constant `MRN_UPTIME_ROBOT_API_KEY`
  - environment variable `MRN_UPTIME_ROBOT_API_KEY`
  - environment variable `UPTIME_ROBOT_API_KEY`
  - database fallback in `mrn_helper_settings`
- Future API keys should follow the same external-first, DB-fallback pattern instead of introducing one-off storage logic.

## Dependencies / Integrations

- WordPress settings API
- WordPress media modal
- Fluent SMTP
- GTM Injector
- WPForms
- shared MRN sticky settings toolbar helper
- shared Font Awesome runtime asset layer via `mrn-shared-assets`

## Security Notes

- Settings page is `manage_options` only.
- Settings saves use WordPress settings API registration.
- Early media AJAX validators explicitly check the same nonces/capabilities as the core media handlers they run ahead of.
- External plugin option sync is gated to `manage_options` users.

## Rollout / Packaging Notes

- Standard plugin source lives in:
  - `/Users/khofmeyer/Development/MRN/plugins/mrn-config-helper`
- Package/push through the normal plugin packaging flow when releasing.

## Risks / Gotchas

- This plugin is currently a single large PHP file.
- It is safe to consume its helper data from the theme, but avoid letting theme code write back into its options directly.
- Social links are configuration only right now; front-end output/styling remains theme-owned on purpose.
- Social links can now use either media-library images or Font Awesome classes, so front-end render code should branch on `icon_type` rather than assuming an image-only contract.
