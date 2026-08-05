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

## USB Settings Toolbar Contract

- `mrn_sticky_toolbar_render_css()` owns the toolbar layout CSS.
- `mrn_sticky_toolbar_render()` owns the rendered toolbar markup.
- For a settings/form tab, the USB primary button replaces the form's normal bottom `submit_button()`.
- When USB is available, do not render a duplicate bottom save button for the same form.
- Pass the active form id through `form_id`, and use `save_label` for that form's submit action.
- Pass `show_save => false` for tabs-only screens or tabs that do not have a form submit action.
- Do not attach unrelated utility actions to a settings form toolbar unless they are intentionally scoped to that tab.
- Keep one-off utility actions in the page body unless USB has first-class support for that action type.
- Plugin-local fallback UI should still render normal tabs and submit buttons when the USB API is unavailable.
