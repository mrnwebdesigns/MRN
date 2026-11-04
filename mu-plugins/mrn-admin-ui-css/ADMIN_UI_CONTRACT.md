# MRN Admin UI Contract

Contract version: `1.1.0`

MRN admin interfaces use WordPress core markup, language, classes, and interaction patterns first. The MRN theme layer may improve spacing, radii, grouping, and brand-neutral polish, but it must not create a second component vocabulary.

## Core rules

1. Use semantic HTML and WordPress-native classes before adding an MRN class.
2. An interface must remain usable if MRN Admin UI CSS and the MRN stack are absent.
3. Use text labels for actions except the standardized, repeated card-removal control defined below.
4. Use one primary action per region. Secondary actions use `.button`; tertiary actions use `.button-link`.
5. Use WordPress notices, tabs, list tables, row actions, form tables, and screen-reader utilities instead of custom equivalents.

## Standard actions

| Intent | Label | WordPress primitive | Placement |
| --- | --- | --- | --- |
| Create the main record | `Add …` | `.button.button-primary` or `.page-title-action` | Page heading/action group |
| Save the current record | `Save …` or `Update` | `.button.button-primary` | Form or sticky action bar |
| Edit an existing record | `Edit` | Plain link | `.row-actions` |
| Recoverable record deletion | `Move to Trash` | `.button-link-delete` | `.row-actions` |
| Restore a trashed record | `Restore` | Plain link | Trash `.row-actions` |
| Irreversible record deletion | `Delete permanently` | `.button-link-delete` or WordPress delete control | Trash only, with confirmation |
| Remove a card or unsaved nested item | Trash Dashicon with accessible name `Remove {item}` | `.button-link-delete.mrn-admin-card-remove` | Card header or adjacent to the nested item |
| Cancel or navigate | `Cancel`, `Back`, or destination name | Plain link or `.button` | Adjacent to the primary action |

Do not label a recoverable trash operation `Delete`. Whole-record trash actions use the visible text `Move to Trash`; repeated card-removal actions use the standardized trash icon.

## Lists and records

- Use `table.widefat`/WordPress list-table structure for record collections.
- Put `Edit`, `Move to Trash`, `Restore`, and `Delete permanently` in `.row-actions` beneath the record title.
- Do not add a standalone red delete button column when row actions are available.
- POST every state-changing action with an explicit capability check and nonce.
- Trash is preferred over permanent deletion when WordPress supports it.

### Reusable row-actions component

**Actions** is the shared row-action disclosure for supported MRN administration
tables, owned by `mrn-admin-ui-css`. It is not a Pages-specific control. Reuse the
existing native `row_actions` class mapping; do not introduce a second renderer,
copy its JavaScript/CSS into a consuming plugin, or add a new preference.

- Render a native `.wp-list-table` with its own `tbody`, record title and original
  `.row-actions` container inside the row. A `WP_List_Table` consumer should
  continue using its native `row_actions( $actions )` output. Keep capability checks,
  action URLs/nonces, confirmation and save handlers with the action's owner.
- On an eligible screen, `modern-admin.js` and shared `row-actions.css` enhance that
  markup automatically. Original actions stay in their original row and form.
  Native AJAX row replacement is enhanced once without a consumer callback.
- Use the existing native destructive wrappers (`trash`, `delete`,
  `delete_permanent`, `delete_permanently`) where applicable. The shared component
  places these actions last, behind a divider, with the semantic danger color.
- The record title supplies the accessible `Actions for {record}` name. Keep
  the native `.row-title`, `.plugin-title strong`, `.username strong` or
  `.column-name strong`/Media `.column-title strong` title markup. Do not render the generated toggle/popover
  classes yourself; they are implementation details, not consumer markup.
- Light/Dark styling, viewport placement over clipped tables, keyboard opening,
  Escape/focus return and Quick Edit replacement behavior have one owner here.
  Do not add per-screen positioning, a document-body portal, or a polling loop.
- Without the enabled appearance pilot, JavaScript or native Popover support,
  original inline actions (or Media’s native disclosure) remain usable. Consumers
  must not hide them themselves.

Eligibility is bounded by `mrn_admin_ui_modern_screen()` plus the exact Media
Library **list** screen while appearance is enabled. The Media integration adopts
only MRN Media Tools’ known `mrn_media_actions` disclosure, preserving its original
action nodes, handlers, nonces and row/form ancestry. Media Tools still owns its
actions and native fallback. Grid, Add Media, Dashboard widget tables, other
custom disclosures and vendor applications are not enhanced. Media loads only
the shared component styles, not the broader modern-admin stylesheet.

A new MRN screen must first be qualified and added to the existing screen
allowlist; do not broaden it through prefix matching or force the component onto
vendor applications.

Reuse acceptance checks: native links/nonces and form ancestry, Quick Edit where
present, keyboard/focus, Light/Dark hover and destructive states, narrow viewports,
wide/clipped tables and native fallback. See the
[verified review implementation](../../docs/admin-editor-pilot/appearance/review/row-actions-20261104/REVIEW.md).
This documents the existing native-markup contract; it adds no runtime dependency
or contract-version requirement. The candidate remains review-only until the
separate Stack release gates are satisfied.

## Icons

- Visible text is the default for actions.
- A Dashicon may precede visible text with `.mrn-admin-action-with-icon`; mark the icon `aria-hidden="true"`.
- Icon-only controls are reserved for spatially constrained, repeated utilities such as reorder arrows. They require an accessible name and consistent icon usage.
- Card and nested-item removal uses the WordPress `dashicons-trash` icon in `.button-link-delete.mrn-admin-card-remove`. The button must have a specific `aria-label` and matching `title`; the icon is `aria-hidden="true"`.
- Record-level `Move to Trash` and `Delete permanently` actions remain visible text.

## Navigation and feedback

- Use `.nav-tab-wrapper`, `.nav-tab`, and `.nav-tab-active` for page-level fallback navigation.
- The Universal Sticky Bar may enhance navigation and primary actions when available, but the native controls remain the standalone contract.
- Use `.notice.notice-success.is-dismissible`, `.notice.notice-error`, `.notice-warning`, or `.notice-info` for feedback.
- Keep the action label stable between the control, confirmation, and resulting notice.

## Theme layer

`mrn-admin-foundations.css` may style elements inside `[data-mrn-admin-ui-contract]` using additive layout helpers:

- `.mrn-admin-action-group`
- `.mrn-admin-inline-action`
- `.mrn-admin-action-with-icon`
- `.mrn-admin-card-remove`

These helpers only arrange WordPress-native components. They do not redefine core button, destructive-action, notice, row-action, or tab semantics.

## PHP feature detection

```php
if ( function_exists( 'mrn_admin_ui_contract_version' ) ) {
	$version = mrn_admin_ui_contract_version();
}

$contract = function_exists( 'mrn_admin_ui_contract_get' )
	? mrn_admin_ui_contract_get()
	: array();
```

Plugins must not require these functions to render or operate.
