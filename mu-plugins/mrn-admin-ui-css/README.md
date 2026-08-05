# MRN Admin UI CSS

WordPress plugin release unit for `mrn-admin-ui-css`.

## QA Engine

Run plugin-scoped QA with full static analysis:

```bash
MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-admin-ui-css
```

Runtime browser, accessibility, API, and performance checks should be run separately against an explicit target site when this plugin change affects rendered output or live WordPress behavior.

## Admin UI contract

The semantic component and action rules live in [`ADMIN_UI_CONTRACT.md`](ADMIN_UI_CONTRACT.md). Stack consumers can feature-detect `mrn_admin_ui_contract_version()` and read native class/verb mappings through `mrn_admin_ui_contract_get()`. Consumers must still render functional WordPress-native markup when this MU plugin is absent.
