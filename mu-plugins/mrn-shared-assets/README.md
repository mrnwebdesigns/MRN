# MRN Shared Assets

WordPress plugin release unit for `mrn-shared-assets`.

## Admin layout builder

Call `mrn_shared_assets_enqueue_admin_layout_builder()` on an admin screen to load the shared layout-builder contract. `window.MRNAdminLayoutBuilder` provides grid normalization, accessible tab activation, keyboard tab navigation, and connected sortable lanes. Consumers own their fields, labels, and persistence while reusing the interaction and styling primitives.

## QA Engine

Run plugin-scoped QA with full static analysis:

```bash
MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-shared-assets
```

Runtime browser, accessibility, API, and performance checks should be run separately against an explicit target site when this plugin change affects rendered output or live WordPress behavior.
