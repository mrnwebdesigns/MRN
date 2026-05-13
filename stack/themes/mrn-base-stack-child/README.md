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

## Included Starter Template

- `templates/template-rollout-starter.php`
  - Page template available in Classic Editor.
  - Preserves parent shell and builder rendering contracts.
  - Includes a guarded breadcrumb render point via `mrn_render_breadcrumbs()` for site-level placement control.

The parent theme remains the source for shared builder/runtime behavior. Keep child changes focused on site-specific visual and template needs.
