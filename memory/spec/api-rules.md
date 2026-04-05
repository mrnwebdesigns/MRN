# API Rules

## Enumerated Layout Values
- Shared section-width values:
  - `Content`
  - `Wide`
  - `Full Width`
- These values are the standard contract for body-section width behavior.
- Hero is not part of this width enum.

## Content Lists Contract
- `Content Lists` display modes are managed in `Site Configurations -> Display Modes`.
- Current client flow:
  - create a display mode
  - choose entity type
  - choose entity item or subtype
  - choose which fields render for that entity
- Builder `Content Lists` should only show post-type display modes whose subtype matches the selected content type.
- Builder must allow fallback to `Use Row Settings`.
- Reusable content-list blocks should map back to the native `content_lists` page layout when converted to page-specific content.
- Reusable content-list rendering must receive host page context so term filtering and pagination behave the same inside page-builder placement.
- Key theme helpers:
  - `mrn_base_stack_get_content_list_display_mode_choice_map()`
  - `mrn_base_stack_render_content_list_item()`

## Singular Sidebar Contract
- Theme helper:
  - `mrn_base_stack_get_singular_sidebar_settings()`
- Theme-owned field group name:
  - `Sidebar`
- Current sidebar fields:
  - `sidebar_layout`
  - `page_sidebar_rows`
- Editor label should read `Sidebar Position`.
- Sidebar field flow belongs after `After Content`, not in the side metabox column.
- Empty sidebar builder rows must not count as rendered sidebar markup; the singular sidebar shell should only activate when at least one sidebar row outputs real front-end markup.
- Front-end singular-sidebar collapse is deferred.
- Classic-editor sidebar collapse lives in:
  `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-editor-lockdown/mrn-editor-lockdown.php`

## Editorial Standard Set Contract
- Theme-owned editorial CPTs should use a shared top-of-form `Standard Set` pattern before CPT-specific body fields.
- The standard-set fields are:
  - `Label`
  - `Heading`
  - `Subheading`
- These fields should be plain text inputs, not WYSIWYG editors.
- Their instructions should read:
  `Limited inline HTML allowed: span, strong, em, br.`
- Avoid tabbed ACF grouping for this standard set unless a specific CPT has an established exception.
- `Heading` is the primary editorial display title for the entry body.
- WordPress post title may remain the fallback display title when `Heading` is empty.
- `Label` and `Subheading` are optional supporting fields for shell/header presentation.

## Editorial CPT Pattern
- Theme-owned editorial CPTs currently live in `mrn_base_stack_get_editorial_cpts()` and are expected to follow one shared structural pattern unless a deliberate exception is documented.
- Registration pattern:
  - register on `init`
  - use `mrn_base_stack_is_admin_cpt_visible()` for admin visibility
  - set `show_in_rest => true`
  - set `publicly_queryable => true`
  - set `show_in_nav_menus => true`
  - use an explicit rewrite slug with `with_front => false`
- Editor/admin pattern:
  - register ACF field groups on `acf/init`
  - use `position => acf_after_title`
  - use `label_placement => top`
  - use `instruction_placement => label`
  - hide the native WordPress content editor when the CPT is theme-rendered instead of `the_content()`
  - opt the CPT into the shared singular shell, hero, after-content, sidebar, and sticky-bar helpers through the theme-owned editorial CPT registry instead of one-off hooks
- Header/body field pattern:
  - place the `Standard Set` first
  - follow with CPT-specific body fields
  - avoid tabs for the standard set; use tabs only when a CPT-specific editing flow clearly benefits from them
  - allow CPT-specific classes for front-end targeting, but keep them inside the shared shell contract
- Singular front-end pattern:
  - use the shared singular wrapper classes:
    - `mrn-singular-shell`
    - `mrn-singular-shell__main`
    - `mrn-singular-shell__sidebar`
    - `entry-header`
    - `entry-content`
  - keep CPT-specific content aligned to the same shell width as the header unless a narrower or wider treatment is intentional
  - render hero, sidebar, and after-content through shared theme helpers when the CPT participates in the builder-style singular shell
- Archive/listing pattern:
  - use the matching `template-parts/content-{post_type}.php` template
  - show the entry heading with a permalink
  - show optional supporting media or summary content when available
  - use either the native excerpt field or a helper-derived plain-text excerpt when the CPT does not store a native excerpt
- Data/helper pattern:
  - provide a dedicated `mrn_base_stack_get_{cpt}_data()` helper when the CPT has custom structured fields
  - provide a helper-derived excerpt when archive cards need a stable summary and the CPT does not expose the core excerpt UI
- Current alignment note:
  - `Blog`, `Gallery`, `Testimonial`, and `Case Study` are the current theme-owned editorial reference pattern
  - CPT-specific field payloads can still differ, but they should share the same shell, standard-set, and helper-driven structure

## Theme Options And Business Information
- Theme header/footer options helper:
  - `mrn_base_stack_get_theme_header_footer_options()`
- Business information helpers:
  - `mrn_base_stack_get_business_information()`
  - `mrn_base_stack_get_business_logo( $context )`
  - `mrn_base_stack_get_business_schema_data()`
  - `mrn_base_stack_get_business_address_lines()`
  - `mrn_base_stack_get_business_hours_display_rows()`
  - `mrn_base_stack_get_footer_copyright_text()`
  - `mrn_base_stack_render_social_links()`
- Business information payload should include:
  - `phone_uri`
  - `text_phone_uri`
  - `address`
  - `business_hours`
  - `holiday_hours`
- Canonical business-information fields include:
  - `business_profile`
  - `years_in_business`
  - `logo`
  - `logo_inverted`
  - `logo_footer`
  - `logo_footer_inverted`
  - `phone`
  - `text_phone`
  - `address_line_1`
  - `address_line_2`
  - `address_city`
  - `address_state`
  - `address_postal_code`
  - `address_country`
  - weekday `open` and `close` pairs for Monday through Friday
  - `holiday_hours` repeater with `name`, `date`, `status`, `open`, `close`, `note`

## Header Contract
- Native WordPress or theme-owned sources:
  - custom logo
  - primary menu location `menu-1`
  - utility menu location `menu-2`
- Theme-owned header toggles:
  - `header_show_utility_menu`
  - `header_show_search`
  - `header_show_business_phone`
  - `header_show_business_profile`
- Header logo priority:
  - business information logo
  - WordPress custom logo
  - site title
- Header search hook:
  - `mrn_base_stack_header_search`

## Footer Contract
- Native WordPress or theme-owned sources:
  - footer menu location `menu-3`
  - legal menu location `menu-4`
- Theme-owned footer toggles:
  - `footer_show_footer_menu`
  - `footer_show_legal_menu`
  - `footer_show_business_profile`
  - `footer_show_business_phone`
  - `footer_show_text_phone`
  - `footer_show_address`
  - `footer_show_business_hours`
  - `footer_show_social_links`
- Theme-owned footer text fields:
  - `footer_copyright_text`
  - `footer_legal_text`

## SEO Helper Rules
- `mrn-seo-helper` owns sidebar placement for its SEO title/meta description ACF group.
- The field group must register on WordPress `init` after CPT registration, not early on `acf/init`.
- As of the current durable contract, the field group uses:
  - `position => side`
  - `menu_order => 0`
- Locked classic-editor sidebars must preserve metabox `acf-group_69a1c0f3a1b01`.

## Deployment And Manifest Rules
- Theme rollout manifest must reference the packaged stack theme zip path instead of a bare slug.
- Live refresh verification should confirm theme helper availability and public `200` responses for core pages after rollout.
