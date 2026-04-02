# MRN Editor Lockdown (MU)

## Summary

- Name: `MRN Editor Lockdown (MU)`
- Slug: `mrn-editor-lockdown`
- Type: MU plugin
- Current version: `1.0.2`
- Source path:
  - `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-editor-lockdown`

## Purpose

This plugin enforces the MRN classic editor metabox layout across supported screens.

It exists to keep the editor shell predictable for:

- posts
- pages
- reusable block library screens

## Ownership Boundary

This plugin owns:

- screen layout locking
- metabox ordering
- closed-metabox defaults
- light admin CSS/JS to reinforce the locked behavior
- classic editor sidebar collapse affordance for the right metabox column

This plugin does not own:

- the page/post content builder
- ACF field group structure
- front-end theme/sidebar behavior
- broad admin CSS cleanup

That separation is intentional and important.

## Supported Screens

It currently supports:

- `post`
- `page`
- reusable block CPTs discovered from the reusable block library

The reusable block support is intentionally dynamic through:

- `mrn_rbl_get_post_types()`

## Core Behavior

Key functions:

- `mrn_editor_lockdown_get_layouts()`
- `mrn_editor_lockdown_get_reusable_layout()`
- `mrn_editor_lockdown_get_layout_for_post_type()`
- `mrn_editor_lockdown_get_supported_post_types()`
- `mrn_editor_lockdown_apply_layout()`

On supported screens it writes/filters user-option values for:

- `screen_layout_{post_type}`
- `meta-box-order_{post_type}`
- `closedpostboxes_{post_type}`

## Runtime Enforcement

The plugin enforces layout through:

- `current_screen`
- `get_user_option_screen_layout_*`
- `get_user_option_meta-box-order_*`
- `get_user_option_closedpostboxes_*`

It also prints limited admin CSS and JS to reinforce the locked metabox experience, including the classic-editor right-sidebar collapse control.

## Front-End / Theming Behavior

- none

This is an editor-shell enforcement plugin only.

## Developer Hooks / Extension Points

This plugin is mostly internal-function based and currently does not expose a major public hook API.

Its main integration behavior is compatibility with:

- WordPress screen/metabox option filters
- reusable block post type discovery via the reusable block library

## Special Rules

Durable rules from memory:

- treat this plugin as the owner of classic-editor metabox shell behavior
- keep front-end singular sidebar behavior in the theme layer
- if a problem is about the actual ACF builder layout, this plugin is probably not the right place to fix it

## Rollout / Packaging Notes

- This is a stack MU plugin.
- It rolls out through MU source sync, not through the normal plugin manifest.

## Risks / Gotchas

- If this plugin starts taking ownership of builder structure instead of metabox shell behavior, responsibilities will blur quickly.
- Because it writes/filters user option data, changes here can affect editor UX across multiple screen types at once.
