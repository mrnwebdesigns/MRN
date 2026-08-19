# MRN Mega Menu

A focused WordPress menu enhancement layer with optional, first-class WooCommerce content.

## Current feature set

- Native WordPress menus that can be standard or mega-enabled
- Automatic mega panels for top-level parents using their own children
- Configurable top-level parent clicks, with a menu-wide default and stable menu-item-ID overrides for link or toggle behavior
- Optional reusable Mega Layouts with one independently editable mega menu per top-level parent and one-to-six visual columns inside each mega menu
- Custom link groups using the native WordPress link search and picker
- Native WordPress menu blocks using the assigned item's children, an entire saved menu, or one selected parent branch
- WooCommerce product category blocks with a hierarchy-aware picker, linked/plain/hidden names, selectable semantic name tags, and full or short descriptions
- Featured, on-sale, latest, and searchable manually selected product blocks
- Promotional image, message, and call-to-action blocks
- Bounded promotional media that stays inside its assigned menu column regardless of the source image dimensions
- Published Reusable Block Library content that remains centrally editable
- Content-width and full-width panels, or a simple single-column dropdown that opens a narrow list anchored under the parent item instead of a full mega panel, with a per-column choice of which columns feed that dropdown
- Full-width layouts retain viewport width when themes add content-driven menu sizing rules
- Mouse, touch, and keyboard controls with Escape and outside-click closing
- Responsive columns and reduced-motion support
- Drag-and-drop content blocks between columns and sortable custom link rows, with keyboard move controls retained
- Graceful behavior when WooCommerce is inactive
- Universal Sticky Bar actions in the classic Mega Menu editor when the stack plugin is available

## Usage

1. Build the menu normally in **Appearance → Menus**.
2. Open **Appearance → Mega Menus → Menus** and enable mega behavior for that WordPress menu.
3. Use the automatic parent-children layout, or open **Reusable Layouts** in the same workspace to build richer content and assign it to the menu.
4. Select that same WordPress menu in the stack header configuration or any normal menu location.

The workspace uses the MRN Universal Sticky Bar for **Menus** and **Reusable Layouts** navigation when that shared contract is available. A standard WordPress tab bar is used when it is not, so the plugin remains standalone-safe.

Each top-level parent with children becomes a mega trigger. By default, click, Enter, or Space toggles its panel without navigating, preserving the original plugin behavior. Administrators can allow the original WordPress parent URL globally or override one assigned parent by its menu-item ID. In link mode, desktop hover and Arrow Down open the panel, click or Enter follows the URL, touch uses a first tap to open and a second tap to follow, and Space toggles without navigating. Escape closes the panel and returns focus to the parent. The underlying object remains a normal WordPress menu.

Template files use the normal WordPress API; no special renderer is required:

```php
wp_nav_menu( array( 'menu' => 79 ) );
```

If menu 79 is mega-enabled it renders as mega; otherwise it renders as a standard menu.

For large menus, use **Build from assigned menu** to create one editor tab for every top-level parent branch. Each tab represents that parent item's complete mega menu and contains its own one-to-six-column layout. Blocks can be reordered or moved between columns without affecting another parent's mega menu. Each native menu block can display the parent with its children, only its children, or only the parent.

## Compatibility

- WordPress 6.0+
- PHP 8.1+
- WooCommerce is optional and required only for product/category content.

Theme CSS can be layered over the `mrn-mega-menu` classes. The plugin deliberately avoids replacing the theme's primary navigation or WordPress's menu-management model.

## MRN stack integration

The plugin detects the stack through public PHP contracts rather than filesystem paths, hostnames, or theme names. When connected it can use each available feature independently:

- Site Styles color variables and shell-width variables
- The stack admin/data-only post-type contract for optional Mega Layout records
- MRN Tokens in human-readable menu copy using `{token:token_name}`
- Capability detection for shared assets and base-theme business information
- Direct rendering of published Reusable Block Library content inside menu panels, without page-row wrappers or duplicate anchors
- The stack-owned admin layout builder for tabs, sortable columns, shared grid math, and layout styling
- Native Appearance → Menus item management with per-item leading and navigation-arrow icons
- Incremental “Add mega menu” controls plus per-layout parent-label, parent-click, trigger-icon, trigger-arrow, and child-arrow overrides
- One page-level Save settings action for all menu behavior assignments
- Universal Sticky Bar integration with Mega Menu-specific save and admin-link behavior

The editor displays **MRN Stack connected** or **Standalone mode**. When the shared layout builder is unavailable, the plugin retains its local WordPress/jQuery UI fallback so missing stack features never prevent the editor or menu from rendering.

Admin actions follow the MRN Admin UI Contract when it is available and retain the same WordPress-native labels and classes in standalone mode. Recoverable layout deletion is always labeled **Move to Trash**; repeated card-removal controls use the built-in WordPress trash icon with accessible names and tooltips.

Integration filters:

- `mrn_mega_menu_stack_detected`
- `mrn_mega_menu_stack_capabilities`
- `mrn_mega_menu_resolve_text`
- `mrn_mega_menu_use_sticky_toolbar`

## Changelog

### 0.16.17

- Removed the orphaned spatial-grid editor (unenqueued JS, unused sanitize/render branches) left over from before the panel/column builder.
- Added a per-mega-menu "Menu style" setting: Full mega menu (unchanged) or a Simple dropdown that opens a narrow, single-anchor flyout instead of the full-width panel.
- Added a per-column "Show in dropdown" toggle so a Simple dropdown can pull content from any chosen subset of that mega menu's layout columns.

### 0.16.16

- Replaced bootstrap-global asset and path constants with a discoverable plugin runtime API.
- Normalized WordPress nav-menu parent properties without relying on undeclared `WP_Post` members.
- Made optional sticky-toolbar and Reusable Block Library calls fail closed through callable integration boundaries.
- Corrected global WordPress term annotations and eliminated all Mega Menu PHPStan findings.
- Enabled strict PHPStan enforcement in the plugin QA profile so advisory errors fail future QA runs.

### 0.16.15

- Added accessible copy and paste controls to layout columns in the mega-menu editor.
- Copied columns can be pasted into columns belonging to other top-level menu items while preserving all supported block settings and content.
- Pasting into a populated column requires confirmation and continues through the existing server-side layout sanitization on save.

### 0.16.14

- Added the WooCommerce product-category `short_description` term field as a category-block description source.
- Added independent controls for linked, plain, or hidden category names and an allowlisted `span`, `p`, or `h2`–`h6` name tag.
- Preserved existing category-block output by mapping legacy links-only, links-with-descriptions, and descriptions-only settings to the new controls.

### 0.16.13

- Added a backward-compatible menu-wide parent click setting and per-top-level-item overrides stored by WordPress menu-item ID.
- Preserved linked anchors and their original URLs, exposed resolved behavior through `data-mrn-parent-click`, and added fine-pointer, touch, and keyboard interaction rules.
- Kept unusable parent destinations such as empty URLs and `#` in accessible toggle-only mode.
