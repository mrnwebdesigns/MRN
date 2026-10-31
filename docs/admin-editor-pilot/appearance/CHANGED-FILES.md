# Changed files and components

Only the existing `mrn-admin-ui-css` release unit changes behavior. Editor Lockdown, Dashboard Support, Config Helper, Classic Editor, ACF, AME configuration, content storage and native publishing implementations are unchanged.

| File | Purpose |
| --- | --- |
| `mu-plugins/mrn-admin-ui-css/mrn-admin-ui-css.php` | Load the appearance extension; local candidate version 3.3.0. |
| `mu-plugins/mrn-admin-ui-css/appearance.php` | Per-user preference, early HTML attribute, native toolbar form, protected AJAX/admin-post persistence, existing local emergency boundary. |
| `mu-plugins/mrn-admin-ui-css/appearance.js` | Live switching/save rollback, status announcements, keyboard disclosure, narrow toolbar/menu accessibility repairs. |
| `mu-plugins/mrn-admin-ui-css/mrn-admin-tokens.css` | Central semantic palettes, System query and explicit native-light palette. |
| `mu-plugins/mrn-admin-ui-css/mrn-admin-shell.css` | Shell/sidebar states, shared owned controls/surfaces, notifications, MRN pickers, publishing panels and intentional native islands. |
| `mu-plugins/mrn-admin-ui-css/modern-admin.php` | Extend the existing explicit allowlist to native account/plugin/taxonomy/tools screens. |
| `mu-plugins/mrn-admin-ui-css/mrn-modern-admin.css` | Replace fixed light colors with semantic tokens in the existing bounded admin layout. |
| `mu-plugins/mrn-admin-ui-css/mrn-editor-workspace.css` | Apply the palette and state colors to the approved workspace without changing its structure or field tree. |
| `mu-plugins/mrn-admin-ui-css/README.md`, `stack.lock` | Document/version the local component candidate. |
| `mu-plugins/mrn-admin-ui-css/tests/runtime/admin-appearance.cjs` | Authenticated theme/persistence/System/keyboard/responsive/axe coverage. |
| `mu-plugins/mrn-admin-ui-css/tests/runtime/appearance-integrity.cjs` | Native save/reload, media, validation, truncated-save and preference API checks. |
| `mu-plugins/mrn-admin-ui-css/tests/runtime/appearance-fallback.cjs` | No-JavaScript/System, WebKit and emergency-disable checks. |
| `mu-plugins/mrn-admin-ui-css/tests/runtime/appearance-performance.cjs` | Startup CPU/layout/style and retained-heap measurements with initialized-editor guard. |
| `mu-plugins/mrn-admin-ui-css/tests/runtime/appearance-overlays.cjs` | Both-mode MRN picker/publishing-panel captures and axe checks. |
| `mu-plugins/mrn-admin-ui-css/tests/runtime/appearance-captures.cjs` | Full-page final captures, complete toolbar/navigation scans and preference restoration. |
| `mu-plugins/mrn-admin-ui-css/tests/runtime/modern-admin-browser.cjs` | Existing regression harness now expects the themed shell with native app content. |
| `mu-plugins/mrn-admin-ui-css/tests/runtime/appearance-core-screens.cjs`, `appearance-keyboard.cjs` | Targeted core-screen and native keyboard/focus verification. |
| `.gitignore` | Ignore private/generated runtime diagnostics under `outputs/admin-appearance/`. |
| `docs/admin-editor-pilot/appearance/` | Review report, prioritized backlog, this change list and reviewed evidence. |

The complete evidence file/checksum inventory is [evidence/manifest.json](evidence/manifest.json). Auth cookies, credentials, private restore files, raw HTML and database dumps are not included. Nonce-bearing diagnostic selector attributes are redacted when evidence is copied.

Local fixture installation copied eight runtime PHP/JS/CSS files from this release unit. [Installed source proof](evidence/installed-source.json) records exact matching SHA-256 values. No shared environment was updated; no theme/plugin application-owned source was edited.
