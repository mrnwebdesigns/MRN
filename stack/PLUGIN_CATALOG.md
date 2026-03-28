# Plugin Catalog

This is the starting inventory for the current MRN plugin and MU-plugin set.

Use this file to answer:

- what each plugin does
- whether it exposes developer-facing hooks
- whether it prints front-end styles, tokens, or other theming behavior
- where detailed docs should live next

This is an inventory and summary, not yet the full deep-dive documentation for every plugin.

## Standard Plugins

### `mrn-acf-character-count`

- Name: `ACF Character Count`
- Version: `1.1.4`
- Purpose:
  - adds live character counts under selected ACF fields in admin
- Admin/UI:
  - settings page in wp-admin
  - ACF input screen integration
- Front-end / theming:
  - none expected
- Developer-facing hooks:
  - none identified in the main file
- Notes:
  - utility plugin for editor feedback

### `mrn-comment-management`

- Name: `Comment Management`
- Version: `1.1.5`
- Purpose:
  - admin auditing and safe bulk deletion of comments
- Admin/UI:
  - Tools page
- Front-end / theming:
  - none expected
- Developer-facing hooks:
  - no custom hooks identified in the main file
- Notes:
  - operational/admin utility, not a presentation plugin

### `mrn-config-helper`

- Name: `Config Helper`
- Version: `0.1.18`
- Purpose:
  - centralized site configuration and admin workflow helpers
- Admin/UI:
  - settings page
  - media-library validation helpers
  - editor/admin behavior helpers
  - WPForms notification locking behavior
- Front-end / theming:
  - not primarily a front-end/theming plugin
- Developer-facing hooks:
  - no custom MRN hooks identified in the main file
  - integrates heavily with WPForms and WordPress hooks
- Notes:
  - cross-cutting admin utility plugin

### `mrn-cookie-consent`

- Name: `Cookie Consent`
- Version: `1.1.19`
- Purpose:
  - Silktide consent integration with Consent Mode v2 and GTM-friendly runtime behavior
- Admin/UI:
  - settings page
  - setup/runtime notices
- Front-end / theming:
  - prints consent default script in `wp_head`
  - enqueues consent assets on the front end
- Developer-facing hooks:
  - `mrn_silktide_consent_css_url`
  - `mrn_silktide_consent_js_url`
- Notes:
  - front-end behavior plugin with real runtime impact

### `mrn-editor-tools`

- Name: `Editor Enhancements`
- Version: `1.8.13`
- Purpose:
  - classic editor and ACF WYSIWYG enhancements
  - snippets, wrap buttons, style helpers, icon tools
- Admin/UI:
  - settings/admin page
  - TinyMCE integration
  - ACF WYSIWYG toolbar integration
- Front-end / theming:
  - editor-style support and shortcode handling
  - not a primary front-end design-token plugin
- Developer-facing hooks:
  - no obvious custom MRN hooks in the main file
  - extends TinyMCE through WordPress/ACF hook points
- Deep doc:
  - `/Users/khofmeyer/Development/MRN/stack/plugin-docs/mrn-editor-enhancements.md`
- Notes:
  - core editor-experience plugin in this stack

### `mrn-gtm-injector`

- Name: `GTM Injector`
- Version: `1.0.7`
- Purpose:
  - manages GTM container ID and injects GTM markup in recommended locations
- Admin/UI:
  - settings page
- Front-end / theming:
  - outputs GTM scripts in `wp_head` and `wp_body_open`
- Developer-facing hooks:
  - `mrn_gtm_output_enabled`
- Notes:
  - front-end runtime integration plugin

### `mrn-license-vault`

- Name: `License Vault`
- Version: `0.2.4`
- Purpose:
  - scans, stores, populates, strips, imports, and exports plugin license data
- Admin/UI:
  - admin page with multiple management actions
- Front-end / theming:
  - none expected
- Developer-facing hooks:
  - no custom public hook inventory captured yet
- Notes:
  - operational/admin utility plugin

### `mrn-seo-helper`

- Name: `SEO Helper`
- Version: `0.2.6`
- Purpose:
  - registers baseline SEO ACF fields for posts and pages
  - syncs SEO field content into SmartCrawl-compatible storage
- Admin/UI:
  - ACF field registration
  - tools/admin notices
- Front-end / theming:
  - indirectly affects rendered SEO/meta behavior through synced data
- Developer-facing hooks:
  - no custom public hook inventory captured yet
- Notes:
  - content-model helper plugin, not a design/theming plugin

### `mrn-unified-exporter`

- Name: `Unified Exporter`
- Version: `1.2.4`
- Purpose:
  - exports/imports Editor Enhancements and AME-related config in one workflow
- Admin/UI:
  - admin page
  - export/import/analyze actions
- Front-end / theming:
  - none expected
- Developer-facing hooks:
  - no custom public hook inventory captured yet
- Notes:
  - stack utility and migration/helper plugin

### `mrn-universal-sticky-bar`

- Name: `MRN Universal Sticky Bar`
- Version: `1.0.8`
- Purpose:
  - adds a sticky action bar to classic editor screens
- Admin/UI:
  - classic post/page/reusable block editor UX
- Front-end / theming:
  - none expected
- Developer-facing hooks:
  - `mrn_universal_sticky_bar_post_types`
- Notes:
  - editor-experience plugin with direct admin UX impact

## MU Plugins

### `mrn-active-style-guide`

- Name: `Style Guide (MU)`
- Version: `0.1.2`
- Purpose:
  - logged-in-only front-end style guide panel and full reference page
- Admin/UI:
  - admin menu + admin bar entry
- Front-end / theming:
  - yes, explicitly front-end oriented
  - uses the live active theme header/footer context
- Developer-facing hooks:
  - no custom public hook inventory captured yet
- Notes:
  - reference tool for reviewing front-end styling in live context

### `mrn-admin-ui-css`

- Name: `Admin UI CSS (MU)`
- Version: `3.1.10`
- Purpose:
  - unified wp-admin CSS loader
- Admin/UI:
  - broad admin styling cleanup
- Front-end / theming:
  - admin only
- Developer-facing hooks:
  - no custom public hook inventory captured yet
- Notes:
  - should be documented carefully because admin CSS can become broad-impact

### `mrn-dashboard-support`

- Name: `Dashboard Support (MU)`
- Version: `1.0.3`
- Purpose:
  - fixed support widget on the dashboard
- Admin/UI:
  - dashboard widget
- Front-end / theming:
  - none
- Developer-facing hooks:
  - no custom public hook inventory captured yet
- Notes:
  - stack/admin support utility

### `mrn-disable-comments`

- Name: `Disable Comments (MU)`
- Version: `1.2.3`
- Purpose:
  - globally disables comments across UI, REST, XML-RPC, feeds, and defaults
- Admin/UI:
  - broad admin cleanup around comments
- Front-end / theming:
  - affects front-end comment behavior by disabling it globally
- Developer-facing hooks:
  - no custom MRN hooks identified in the main file
- Notes:
  - behavioral platform MU plugin

### `mrn-duplicate-enhance`

- Name: `Post Duplicator Admin Bar Enhance`
- Version: `1.1.1`
- Purpose:
  - adds front-end admin bar access to duplication behavior
- Admin/UI:
  - front-end admin bar entry and editor auto-open behavior
- Front-end / theming:
  - minor front-end admin-bar behavior for logged-in users
- Developer-facing hooks:
  - no custom public hook inventory captured yet
- Notes:
  - workflow convenience MU plugin

### `mrn-editor-lockdown`

- Name: `MRN Editor Lockdown (MU)`
- Version: `1.0.0`
- Purpose:
  - enforces metabox ordering/layout on classic post, page, and reusable block screens
- Admin/UI:
  - strong admin/editor layout control
- Front-end / theming:
  - none
- Developer-facing hooks:
  - no custom public hook inventory captured yet
- Deep doc:
  - `/Users/khofmeyer/Development/MRN/stack/plugin-docs/mrn-editor-lockdown.md`
- Notes:
  - intended to control metabox shell/layout, not to own the content builder itself

### `mrn-editor-ui-css`

- Name: `Admin UI CSS (MU Legacy)`
- Version: `1.0.8`
- Purpose:
  - backwards-compatible legacy admin CSS loader
- Admin/UI:
  - admin only
- Front-end / theming:
  - none
- Developer-facing hooks:
  - no custom public hook inventory captured yet
- Notes:
  - stands down automatically when unified admin UI CSS is present

### `mrn-reusable-block-library`

- Name: `Reusable Block Library (MU)`
- Version: `0.1.3`
- Purpose:
  - typed reusable content block system
- Admin/UI:
  - reusable block post types
  - editor/admin UX for reusable block types
  - custom admin menu behavior
- Front-end / theming:
  - yes
  - owns reusable block render templates, with theme override support
- Developer-facing hooks:
  - `mrn_rbl_post_type_definitions`
- Deep doc:
  - `/Users/khofmeyer/Development/MRN/stack/plugin-docs/mrn-reusable-block-library.md`
- Notes:
  - central shared-content platform plugin in this stack

### `mrn-site-colors`

- Name: `Site Styles (MU)`
- Version: `0.1.2`
- Purpose:
  - shared design token registry for site colors and graphic elements
- Admin/UI:
  - `Settings -> Site Styles`
- Front-end / theming:
  - yes, explicitly a front-end token/theming source
  - prints CSS variables on front-end, admin, and login
- Developer-facing hooks / APIs:
  - helper functions such as:
    - `mrn_site_colors_get_css_var()`
    - `mrn_site_styles_get_graphic_element_choices()`
    - `mrn_site_styles_get_bottom_accent_contract()`
- Deep doc:
  - `/Users/khofmeyer/Development/MRN/stack/plugin-docs/mrn-site-styles.md`
- Notes:
  - this is the current source of truth for shared color tokens and accent element definitions

### `mrn-svg-support`

- Name: `Enable SVG Support (MU)`
- Version: `1.0`
- Purpose:
  - enables SVG uploads and improves SVG display in media views
- Admin/UI:
  - media library behavior
- Front-end / theming:
  - none expected
- Developer-facing hooks:
  - no custom public hook inventory captured yet
- Notes:
  - small platform utility MU plugin

## Next Documentation Pass

For each plugin, the next deeper pass should capture:

1. feature summary
2. admin screens/settings owned
3. front-end output or theming behavior
4. custom hooks/filters/actions exposed for developers
5. template override paths, if any
6. data/options stored
7. rollout/package notes
