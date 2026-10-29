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

## Local editor pilot

On an eligible, explicitly enabled Editor Lockdown pilot screen, load
`mrn-editor-workspace.css` instead of the global foundations/cleanup rules.
The pilot scopes neutral light surfaces, row summaries, section navigation,
visible focus and wrapping publishing controls to its editor. Native notices,
fields, tabs, conditional logic, pickers and media controls keep their markup.
Only the two identified GTranslate review/upgrade promotions are hidden there;
its conflict and debug warnings remain visible.

The small surface/ink/muted/accent token set is local to
`body.mrn-editor-experience`. The measured `--mrn-editor-offset` keeps navigation
and jump targets below the existing sticky toolbar as its controls wrap.
Disabling the single `MRN_EDITOR_EXPERIENCE_ENABLED` flag restores the ordinary
loader path on reload. Broad admin styling remains outside this pilot.
