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
