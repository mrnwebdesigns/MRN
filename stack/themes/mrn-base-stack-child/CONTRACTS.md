# Stack Child-Theme Contract

This repository is a thin site-customization layer for `mrn-base-stack`. Parent updates remain authoritative for shared behavior.

## Required invariants

- `Template: mrn-base-stack` remains unchanged unless the deployed parent directory is deliberately renamed.
- The parent `mrn-base-stack-style` handle loads the parent stylesheet; the child stylesheet depends on that handle.
- Custom builder wrappers retain stable parent shell, width, layout, and `mrn-content-builder__row` classes.
- Custom ACF wrappers call `mrn_base_stack_get_row_spacing_attr_html_for_current_row()` when row arrays may omit spacing selectors. The parent resolves visible rows to validated raw `acf_fc_layout` indexes.
- Parent display-style, visibility, flex, sub-content-width, motion, and reduced-motion contracts are not reimplemented in the child.
- Template overrides preserve semantic landmarks, heading order, labels, alternative text, keyboard behavior, visible focus, and WCAG 2.1 AA expectations.
- Site assets are additive, locally versioned, and do not duplicate parent or plugin payloads.
- Shared fields, REST/AJAX handlers, save logic, and reusable rendering remain in stack-owned code.

## Release checklist

1. Rename the directory, theme name, text domain, function prefix, and child version constant for the site.
2. Keep the parent template slug and documented public hooks intact.
3. Add only site-owned styles, assets, and intentional template overrides.
4. Run PHP lint and MRN QA against the child-theme repository.
5. Run runtime API, browser, axe accessibility, and performance checks against the target site before deployment.
