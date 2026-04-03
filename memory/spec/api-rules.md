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
- Front-end singular-sidebar collapse is deferred.
- Classic-editor sidebar collapse lives in:
  `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-editor-lockdown/mrn-editor-lockdown.php`

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
