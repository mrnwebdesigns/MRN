# Config Helper

## Summary

- Name: `Config Helper`
- Slug: `mrn-config-helper`
- Type:
  - standard plugin
- Current version: `0.1.37`
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
  - `Breadcrumbs`
  - `Display Modes`
  - `Admin`
- The `Admin` tab is visible only to users with the `administrator` role.
- Current settings areas include:
  - Site Identity
  - SendGrid / Fluent SMTP
  - WPForms Notifications
  - Google Tag Manager
  - External APIs
  - Social Media
  - Breadcrumbs
  - Display Modes
  - Dashboard Controls
  - Back-end content type visibility
- Builder layout visibility controls are not currently rendered in the Site Configurations UI (helper/storage support remains in code).
- The Breadcrumbs tab currently includes secondary tabs:
  - `Advanced Breadcrumbs`
  - `Behavior`
  - `Styling`
  - `Output`
  - `Instructions`
- Classic Editor support:
  - Adds a `Breadcrumb Trail` metabox on supported post types for per-entry manual overrides.
- Display Modes currently includes:
  - list-first management UI with search, filters, sort, and row editing
  - entity-aware mode creation for supported renderable items
  - field selection and ordering for mode output
  - a public saved-mode registry used by `Content Lists`
- SendGrid / Fluent SMTP currently includes:
  - site SendGrid sending-key storage/sync for Fluent SMTP
  - host-managed SendGrid management-key status
  - site-specific SendGrid key creation
  - SendGrid domain-auth creation and validation
  - stored DNS records for the current authenticated domain
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

- Exposes front-end-consumable site configuration and includes a stack-owned breadcrumb runtime.
- Breadcrumb runtime output is semantic by default (`nav` + ordered list + `aria-current` on active item).
- Current front-end-facing helper:
  - `mrn_config_helper_get_social_links()`
  - `mrn_config_helper_get_uptime_robot_settings()`
  - `mrn_config_helper_get_hidden_admin_cpts()`
  - `mrn_config_helper_get_hidden_builder_layouts()`
  - `mrn_config_helper_get_display_modes()`
  - `mrn_config_helper_get_content_list_display_modes()`
  - `mrn_config_helper_get_breadcrumb_settings()`
  - `mrn_config_helper_get_breadcrumb_manual_override( $post_id )`
- Breadcrumb runtime helper surface:
  - `mrn_render_breadcrumbs( $args = [] )`
  - `mrn_get_breadcrumb_items( $args = [] )`
  - shortcode: `[mrn_breadcrumbs]` (+ `view` attribute)
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
  - `name`
  - `alt_text`
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

## Breadcrumbs: Child Theme + Mode Reference

- Yes, breadcrumbs can be added from a child theme.
- Child-theme integration pattern (template-level):
  - Check function availability before calling:
    - `if ( function_exists( 'mrn_render_breadcrumbs' ) ) { ... }`
  - Render in a child theme template:
    - `mrn_render_breadcrumbs( array( 'placement' => 'singular_header' ) );`
  - Use placement keys already supported by stack settings:
    - `singular_header`
    - `archive_header`
    - `search_header`
    - `error_header`
    - `home_header`
- Data-only integration pattern:
  - Call `mrn_get_breadcrumb_items()` and map your own markup for custom child-theme views if needed.
  - Preserve accessibility semantics if overriding markup:
    - `nav` landmark
    - ordered/list structure
    - `aria-current="page"` for active crumb

### Mode Behavior (same meaning as UI Instructions tab)

- `Dynamic only`
  - Stack builds breadcrumb trail from current request context + selected view type.
  - Per-page custom chips are saved but ignored for front-end rendering.
- `Dynamic with per-page manual override`
  - Dynamic remains the default.
  - On singular entries only, if `Use manual breadcrumb override` is checked and valid chips exist, chips take precedence.
- Fallback behavior
  - If override mode is on but override is disabled/empty/invalid for that entry, stack falls back to dynamic output.
- Precedence
  - Global mode controls whether overrides are allowed.
  - Per-entry checkbox + saved chips control whether that one entry uses a custom trail.

### Why Custom Breadcrumbs May Not Show

- `Trail Mode` is still set to `Dynamic only`.
- Per-entry `Use manual breadcrumb override` is not checked.
- No valid chips were saved in the entry metabox.
- Current context or placement is disabled in Breadcrumb `Behavior` settings.
- Template area does not call breadcrumb render helper for that request type.

## Developer Hooks / Extension Points

- Public class helper:
  - `MRN_Config_Helper::get_social_links()`
  - `MRN_Config_Helper::get_uptime_robot_settings()`
  - `MRN_Config_Helper::get_hidden_admin_cpts()`
  - `MRN_Config_Helper::get_hidden_builder_layouts()`
  - `MRN_Config_Helper::get_display_modes()`
  - `MRN_Config_Helper::get_breadcrumb_settings()`
  - `MRN_Config_Helper::get_breadcrumb_manual_override( $post_id )`
- Public wrapper helper:
  - `mrn_config_helper_get_social_links()`
  - `mrn_config_helper_get_uptime_robot_settings()`
  - `mrn_config_helper_get_hidden_admin_cpts()`
  - `mrn_config_helper_get_hidden_builder_layouts()`
  - `mrn_config_helper_get_display_modes()`
  - `mrn_config_helper_get_breadcrumb_settings()`
  - `mrn_config_helper_get_breadcrumb_manual_override( $post_id )`
  - `mrn_config_helper_get_content_list_display_modes()`
- Breadcrumb runtime filters:
  - `mrn_breadcrumb_items`
  - `mrn_breadcrumbs_markup`
- Breadcrumb metabox extensibility:
  - `mrn_config_helper_breadcrumb_meta_post_types`
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
  - site SendGrid API key
  - SendGrid domain/auth metadata
  - GTM container ID
  - UptimeRobot API key fallback
  - dashboard lock roles
  - disabled admin CPT/reusable-library post types
  - disabled builder layouts
  - social links
  - breadcrumbs settings payload
- Breadcrumb per-entry override storage:
  - post meta key: `_mrn_breadcrumbs_manual_path`
  - includes:
    - enabled flag
    - legacy manual items
    - chip-based manual items
- SendGrid management key resolution order:
  - constant `MRN_SENDGRID_MANAGEMENT_API_KEY`
  - environment variable `MRN_SENDGRID_MANAGEMENT_API_KEY`
  - environment variable `SENDGRID_MANAGEMENT_API_KEY`
- Stack bootstrap is one supported way to inject the SendGrid management key into site `wp-config.php` from the stack secret file:
  - local secret path: `/Users/khofmeyer/Development/MRN/stack/secrets/sendgrid-management-api-key.txt`
  - server secret path: `/home/mrndev-stack-manager/stack/secrets/sendgrid-management-api-key.txt`
  - target constant: `MRN_SENDGRID_MANAGEMENT_API_KEY`
- For non-stack production sites, the same key should be provided by the host/deploy environment through a constant or environment variable instead of the WordPress database.
- UptimeRobot key resolution order:
  - constant `MRN_UPTIME_ROBOT_API_KEY`
  - environment variable `MRN_UPTIME_ROBOT_API_KEY`
  - environment variable `UPTIME_ROBOT_API_KEY`
  - database fallback in `mrn_helper_settings`
- Future API keys should follow the same external-first, DB-fallback pattern instead of introducing one-off storage logic.
- Stack bootstrap now seeds the Config Helper option from a stack secret payload:
  - importer mapping: `option_json|secret:mrn-config-helper-settings.json|mrn_helper_settings`
  - server secret path: `/home/mrndev-stack-manager/stack/secrets/mrn-config-helper-settings.json`
  - keep the secret payload out of repo source and thread memory

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
- Breadcrumb custom chips are only rendered when global mode allows manual overrides.
