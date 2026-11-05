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
- Eligible Posts, Pages and CPT lists render a working native Actions disclosure
  through the existing post/page row-action filters. Its initial geometry matches
  the enhanced control, avoiding an inline-links-to-button change during loading.
  The shared enhancer adopts the original nodes and preserves an already-open menu
  and its focus. Existing owner disclosures are left alone.
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
- Without the enabled appearance pilot, original native actions remain unchanged.
  Without JavaScript or native Popover support, Posts/Pages/CPT and Media retain
  working native disclosures; other eligible tables retain their original inline
  actions. Consumers must not hide them themselves.

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
The [first-paint qualification](../../docs/admin-editor-pilot/appearance/review/actions-first-paint-20261104/REVIEW.md)
also covers delayed initialization, early interaction and native fallbacks.
This documents the existing native-markup contract; it adds no runtime dependency
or contract-version requirement. The candidate remains review-only until the
separate Stack release gates are satisfied.

### Reusable pagination component

Use WordPress's native `.tablenav-pages` output for pagination on supported
MRN lists and the Media Library list. The shared appearance shell owns its
alignment and Light/Dark states; do not copy the styles into consumers or
replace the controls with a JavaScript renderer.

- Keep `.displaying-num`, `.pagination-links`, `.paging-input`, `.current-page`,
  native accessible labels and disabled `.tablenav-pages-navspan` elements.
  WordPress owns URLs, query/filter preservation, page bounds and form submission.
- Count, arrows, page input and total share one horizontal centerline. Controls
  are 36px tall on desktop and 44px on narrow screens. The toolbar keeps its
  native placement; the group centers on narrow screens and wraps as needed.
- Top and bottom pagination use the same rules, including the read-only bottom
  page count. Preserve core `.one-page`/`.no-pages` behavior and hidden controls.
- Media's unboxed count text follows its native light canvas; controls keep their
  own theme surfaces. Do not apply dark text tokens over a vendor-owned canvas.
- Styling is available at first paint with no extra request, script or observer.
  Only the existing audited list-screen scope and native Media screen opt in;
  vendor-owned tables are not a reason to broaden this selector.

## Icons

Native list drag indicators sit left of the selection checkbox on the same
horizontal centerline. The shared shell reserves this gutter before initialization
when Post Types Order is enqueued on a supported list, or HappyFiles is enqueued
on Media. Their original handles, glyphs and drag/save behavior remain authoritative.
Header/footer checkboxes align with the row checkboxes; do not add per-list copies.

- Visible text is the default for actions.
- A Dashicon may precede visible text with `.mrn-admin-action-with-icon`; mark the icon `aria-hidden="true"`.
- Icon-only controls are reserved for spatially constrained, repeated utilities such as reorder arrows. They require an accessible name and consistent icon usage.
- Card and nested-item removal uses the WordPress `dashicons-trash` icon in `.button-link-delete.mrn-admin-card-remove`. The button must have a specific `aria-label` and matching `title`; the icon is `aria-hidden="true"`.
- Record-level `Move to Trash` and `Delete permanently` actions remain visible text.

## Navigation and feedback

- Use `.nav-tab-wrapper`, `.nav-tab`, and `.nav-tab-active` for page-level fallback navigation.
- `mrn-admin-shell.css` owns the shared tab presentation on approved MRN screens:
  ACF field tabs, native `.nav-tab` navigation, `.mrn-settings-tabs`,
  `.mrn-workspace-modes`, and Classic Editor Visual/Text controls. All use 44px
  minimum targets, 14px labels, semantic theme colors, a single active underline,
  a neutral hover surface and an inset keyboard focus ring. Do not add per-screen
  pill backgrounds, competing borders, typography or control padding overrides.
- Keep each tab family's native markup and behavior. Navigation links stay links;
  ACF owns conditional visibility and field order; Classic Editor owns mode
  switching. Vertical ACF tabs retain their layout. Save buttons are actions, not
  tabs. Application-owned interfaces remain outside this component's scope.
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


### Stable startup on settings screens

The themed shared settings bar uses in-flow sticky positioning, with no measured
spacer contributing layout height. Keep original form/save ownership. Config
Helper's existing nested wrapper retains its content gutters through scoped CSS.
Known forms do not enqueue list Actions assets; unknown MRN table consumers do.
ACF settings panels may wait for the native `ready` action, retaining measurement
geometry and an eight-second fail-open path. Do not manually trigger ACF lifecycle
actions, hide the whole admin, or add a second initialization system. No-JavaScript
content remains visible. Render supplemental help with its native description
before paint whenever a server-side render hook is available.
