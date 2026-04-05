# Editor Enhancements

## Summary

- Name: `Editor Enhancements`
- Slug: `mrn-editor-tools`
- Type: standard plugin
- Current version: `1.8.17`
- Source path:
  - `/Users/khofmeyer/Development/MRN/plugins/mrn-editor-tools`

## Purpose

This plugin enhances the Classic Editor and ACF WYSIWYG experience.

It exists to give editors a more powerful, stack-standard authoring experience through:

- configurable wrap buttons
- snippets
- text color/style formats
- icon helpers
- editor modal tools

It is now a consumer of the shared Font Awesome runtime asset, not the long-term owner of that asset bundle.

## Admin Surface Area

The plugin adds:

- `Settings -> Editor Enhancements`

The settings model currently groups data into:

- `classes`
- `buttons`
- `snippets`
- `text_colors`

It also includes export/import behavior and section-based import filtering.

## Editor Integrations

Key integration points include:

- TinyMCE plugin registration
- primary toolbar button extension
- secondary toolbar extension
- teeny editor support
- ACF WYSIWYG toolbar support
- Classic Editor runtime settings injection
- ACF WYSIWYG runtime asset loading

Relevant code surfaces include:

- `mce_external_plugins`
- `mce_buttons`
- `teeny_mce_buttons`
- `acf/fields/wysiwyg/toolbars`
- `tiny_mce_before_init`

## ACF Behavior

This plugin is expected to work inside ACF WYSIWYG fields, not only on the main classic editor.

Durable behavior from memory:

- TinyMCE runtime assets/settings should attach broadly in classic-editor admin contexts
- ACF TinyMCE/WYSIWYG fields should receive the same enhancement behavior where appropriate
- the plugin should not be treated as MU-managed in the stack; it is a normal packaged plugin

## Front-End / Theming Behavior

This is primarily an editor/admin plugin, but it does have some front-end implications:

- it can sync generated class CSS into `editor-style.css`
- it enables importcss support
- it registers shortcodes:
  - `mrn_snippet`
  - `mrn_wrap`
- it enables shortcode processing through content/excerpt-related filters

It is not the stack’s design-token source of truth, but it does influence editor styling and reusable text-format behavior.

For icon previews and icon metadata, it now prefers the shared MU plugin:

- `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-shared-assets`

and only falls back to its local bundled copy if the shared runtime layer is unavailable.

## Developer Hooks / Extension Points

This plugin currently leans more on WordPress/TinyMCE hook integration than on custom public MRN hooks.

Important reusable functions include:

- `mrn_editor_tools_default_settings()`
- `mrn_editor_tools_get_settings()`
- `mrn_editor_tools_sanitize_settings()`
- `mrn_editor_tools_get_text_color_style_formats()`
- `mrn_editor_tools_enqueue_editor_runtime_assets()`
- `mrn_editor_tools_enqueue_fontawesome()`

## Data / Storage

Primary storage:

- option: `mrn_editor_tools_settings`

Secondary behavior:

- generated editor-style sync into the active theme’s `editor-style.css`
- temporary notices via transients for failed editor-style writes

## Rollout / Packaging Notes

- This is a standard packaged plugin, not a stack MU plugin.
- It should install through the normal plugin manifest/package path in stack rollout.
- Durable baseline in memory: `1.8.17`

## Special Rules

- It should enhance classic editor contexts broadly, not only one specific screen.
- ACF WYSIWYG support is a first-class requirement.
- The plugin should stay focused on editor capability, not become a general front-end theming system.
- Shared runtime assets like Font Awesome should live in the shared MU asset layer when they are used outside the editor plugin itself.

## Risks / Gotchas

- TinyMCE integrations can conflict with other editor plugins if scoping is sloppy.
- Editor-style syncing writes into the active theme, so theme-writability assumptions matter.
- Over-customizing TinyMCE labels/controls can create confusion if not kept consistent with stack conventions.
