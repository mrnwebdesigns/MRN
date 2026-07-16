# mrn-base-stack Child Theme

This child theme is the rollout scaffold for site-specific front-end overrides while keeping `mrn-base-stack` upgradeable on demand.

## Rollout Rename

Before activating on a site handoff:

1. Rename the theme directory from `mrn-base-stack-child` to the site child slug (for example, `acme-site-child`).
2. Update `Theme Name` in `style.css` so it ends with `(Child)` (for example, `Acme Site (Child)`).
3. Keep `Template` in `style.css` pointed at the deployed parent directory slug.

## Override Surface

- Add CSS overrides in `style.css` using existing parent variables/classes where possible.
- Add template overrides by mirroring parent template paths (for example `template-parts/content.php`).
- Add new template files not present in the parent as new entities/features are introduced.
- For custom ACF flexible-content row wrappers, use `mrn_base_stack_get_row_spacing_attr_html_for_current_row()` and keep `mrn-content-builder__row` on the wrapper. The parent helper hydrates missing row-spacing selector fields from raw flexible-content meta so disabled/skipped ACF rows do not shift spacing onto the wrong visible row.
- Keep direct `mrn_base_stack_get_row_spacing_contract( $row )` calls for code paths that already pass complete row data.

The parent theme remains the source for shared builder/runtime behavior. Keep child changes focused on site-specific visual and template needs.

## Stack Contracts

The child loads the complete parent stylesheet before its overrides and does not replace parent rendering. Consequently, layout widths, shell gutters, row-spacing attributes, display styles, motion behavior, breadcrumbs, accessibility markup, and performance-oriented conditional assets continue to use the parent contracts.

- Override documented `--mrn-*` custom properties and stable `.mrn-*` classes; do not copy parent CSS into the child.
- Keep `mrn-content-builder__row` on custom flexible-content wrappers.
- Use `mrn_base_stack_get_row_spacing_attr_html_for_current_row()` when a custom wrapper receives incomplete ACF row data. This includes disabled/skipped-row index resolution.
- Preserve parent semantic structure, accessible names, focus behavior, and reduced-motion handling in template overrides.
- Enqueue site assets with the child stylesheet as a dependency and avoid duplicate libraries already supplied by the stack.
- Keep shared fields, rendering helpers, API endpoints, and save behavior in the parent stack or shared plugins.

See `CONTRACTS.md` for the production handoff checklist.
