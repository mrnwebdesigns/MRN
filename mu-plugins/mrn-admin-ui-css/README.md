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

The **Actions dropdown** is a reusable shared component, not a per-screen feature.
Eligible native/MRN tables receive it through their existing `.wp-list-table` and
`.row-actions` markup. Reuse the [row-actions contract](ADMIN_UI_CONTRACT.md#reusable-row-actions-component)
instead of copying the renderer, styles or interaction handlers. The existing
screen allowlist and native fallback remain authoritative.

The **Site menu** is shared by the Media and ACF editor workspaces. Its delegated
controller lives in `appearance.js`, and its drawer/disclosure styles live in
`mrn-admin-shell.css`. Both render `.mrn-site-menu` inside `.mrn-workspace-header`;
the editor only supplies its publishing-toolbar offset. Do not add a second
controller or hover-driven submenu rules in a consumer. The controller prepares
disclosure buttons on first use, retains the original AME links/groups, and never
writes menu preferences. Submenus open explicitly; hover, native focus/current
classes and vendor styles cannot expand them. Escape returns focus, Tab or
ArrowDown enters the drawer, and the anchor keeps navigation available if the
appearance script fails. This component is scoped to the two existing workspace
body classes; ordinary WordPress/plugin navigation remains native.

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

W6 Local Hub candidate (3.2.8) adds shared visual tokens and an explicit native/MRN screen allowlist. Dashboard, native content lists, core settings and named MRN pages share the approved workspace palette. AME retains menu configuration and branding; app-owned screens receive none of the new assets. No settings, content, endpoints or publishing handlers are replaced. The existing strict Local Hub switch disables both workstreams. See `docs/admin-editor-pilot/w5-w6/README.md` for evidence and limitations.

Local appearance candidate (3.3.0) extends that same component with semantic Light/Dark tokens, a themed AME/WordPress shell, and a per-user Light/Dark/System preference. The server emits the preference before CSS; System uses a media query rather than a late initialization script. Existing MRN pickers, settings, notifications and the approved editor workspace consume the palette. Application-owned screens and native media/TinyMCE remain deliberate native light surfaces. No AME configuration or branding data is changed. The original Local Hub emergency switch still disables the candidate. See `docs/admin-editor-pilot/appearance/README.md` for local evidence and outstanding qualification issues; this candidate is not published or Fleet qualified.
