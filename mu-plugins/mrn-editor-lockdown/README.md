# MRN Editor Lockdown

WordPress plugin release unit for `mrn-editor-lockdown`.

When the Config Helper's administrator-only Theme File Editor lock is enabled,
the plugin denies the `edit_themes` capability for every user and removes the
Theme File Editor link from Appearance. Direct access to `theme-editor.php` is
therefore rejected by WordPress even for administrators. The lock defaults to
enabled if Config Helper is unavailable. Other theme-management capabilities
and the Plugin File Editor are not changed.

Classic Editor screens start without a loading overlay. The legacy loading
feedback filters remain available as explicit opt-ins. The command palette keeps
its native toolbar button and keyboard shortcut, but downloads its exclusive
libraries when first opened. Libraries used by other enqueued features still
load normally, with WordPress settings, translations and CSP attributes intact.
The Gutenberg pre-publish checklist is omitted on Classic Editor screens; Post
Duplicator uses its existing Classic Editor media picker there. Set the
`mrn_editor_lockdown_defer_command_palette` filter to false to restore the
unchanged optional-asset loading path.

## Local editor pilot

The W0–W4 pilot adds a searchable section outline, meaningful collapsed row
summaries, native jump/expand, return navigation and validation error discovery.
It reads existing MRN Flexible Content fields and titles; ACF remains the owner
of fields, ordering, validation and saving. Native layout operations and the
existing Config Helper picker remain in place. No fields are moved or cloned
into the navigator and its controls have no submitted names.

Enable only on the authorized `admin-editor-pilot.localhost` fixture by adding
`define( 'MRN_EDITOR_EXPERIENCE_ENABLED', true );` to its local `wp-config.php`.
The default is off, and a non-`.localhost` home URL is rejected even with a true
flag. **Emergency disable:** change that constant to `false` (or remove it) and
reload the editor. The native editor and existing save guard remain available.

Behavior belongs here; pilot presentation belongs to `mrn-admin-ui-css`.
Dashboard Support preserves contextual notices on eligible pilot editors.
The revised pilot opens a persistent page structure outline and a focused native
section. Content and Page settings are separate workspace views; Arrange sections
exposes the original full-page ACF ordering controls. A section selection is kept
only in same-tab session storage, by post ID, field key and index. No content,
order, metabox preferences or feature configuration is stored there.

Root fields and controls stay in their original form and DOM order. Inactive
sections are hidden with scoped presentation classes, never disabled or removed.
ACF owns expansion, picker, append, duplication, removal, ordering, validation,
TinyMCE and serialization. Errors reveal the native failing section, including
when Page settings was selected. The legacy sidebar restore/toggles are skipped
only on the enabled pilot screen because they conflict with the new layout.
Page alerts contains original notices and their controls; errors auto-open it.

The current review is in [`docs/admin-editor-pilot/revision/README.md`](../../docs/admin-editor-pilot/revision/README.md).
The first sprint report remains historical evidence of the earlier experience.
`tests/runtime/editor-workspace-browser.cjs` exercises the revised workflow and
native save/reload on the named fixture only. `editor-workspace-measure.cjs`
reads the prior pilot assets directly from commit `0fe8847c` for comparison. These reuse MRN QA Engine Playwright/axe dependencies;
run from the worktree root. Results go to `outputs/admin-editor-pilot/revision/`.
The tests intentionally edit and restore copied drafts. The fixture helper has a
`restore-workspace-test` recovery mode that refuses writes unless the reconstructed
content matches the recorded baseline hash. Never point these at a
shared or remote environment. The older `section-navigator-browser.cjs` and
`section-navigator-save.cjs` describe the original floating-panel workflow and
are historical harnesses, not acceptance checks for the revised layout.

Fixture prerequisites: retain the isolated Trilliant snapshot and fixture IDs
recorded in the report; run `section-navigator-fixture.php` through Local Hub WP
CLI for setup/auth. Copy `section-navigator-hooks.php` to the fixture's local-only
MU test hook and the existing source save-integrity guard to the fixture root as
`editor-save-integrity-baseline.php`. This is local test scaffolding. Keep auth
session files private in the fixture's logs. Do not run flag-toggling benchmarks
concurrently with fixture tests.

## QA Engine

Run plugin-scoped QA with full static analysis:

```bash
MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-editor-lockdown
```

Runtime browser, accessibility, API, and performance checks should be run separately against an explicit target site when this plugin change affects rendered output or live WordPress behavior.
