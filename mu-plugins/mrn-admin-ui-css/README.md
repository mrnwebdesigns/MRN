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
`mrn-editor-workspace.css` instead of global foundations/cleanup. The revision
provides a full editing canvas with persistent page outline, a focused native
section, distinct Content/Page settings views, native Arrange sections, a compact
publishing bar and a temporary Site menu drawer. It does not modify global menu
preferences. Native fields, tabs, conditional logic and picker ownership remain.

The workspace rules apply only after its JS successfully mounts. Notice controls
remain intact in Page alerts; actionable errors auto-open. Only the two identified
GTranslate review/upgrade promotions are suppressed. The measured toolbar offset
keeps navigation below publishing actions. The single
`MRN_EDITOR_EXPERIENCE_ENABLED` flag is still the emergency disable: set false and
reload to restore the ordinary loader/editor. Broad admin styling remains outside
this local pilot. See the [revision report](../../docs/admin-editor-pilot/revision/README.md).

Local editor W5 (3.2.7) adds scoped keyboard arrangement controls to the approved workspace. The existing strict Local Hub flag remains the emergency disable mechanism.
