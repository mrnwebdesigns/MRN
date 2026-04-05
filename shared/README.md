# Shared Source

This folder is only for code that is intentionally shared across multiple plugins.

Current shared source:
- `mrn-sticky-settings-toolbar.php`
- `mrn-universal-sticky-bar-assets.php`

Rule:
- Edit the shared source here first.
- Consume `mrn-sticky-settings-toolbar.php` directly from `wp-content/shared` when possible.
- Plugin-local `includes/mrn-sticky-settings-toolbar.php` files should be thin loaders only, not forked copies.
- New code should call the unique `mrn_sticky_toolbar_*` API instead of the legacy `mrn_render_admin_top_bar*` wrapper names.
