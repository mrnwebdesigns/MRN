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
Other admin screens retain their existing behavior. Summary priority is the
native renamed title, existing internal name, heading, label, then native title.
The index observes only native row containers and title text, and follows ACF
ready/append/remove/sort/validation hooks. It stores no content or order.

The review report, screenshots and measurements are in
[`docs/admin-editor-pilot/README.md`](../../docs/admin-editor-pilot/README.md).
Runtime scripts in `tests/runtime/section-navigator-*.cjs` target only the named
fixture and reuse the installed MRN QA Engine Playwright/axe dependencies. Run
them from the Stack worktree root after preparing that fixture; they write to
`outputs/admin-editor-pilot/`. The regression script intentionally edits and
restores copied draft content, and the publishing script creates a local test
page. Never point these scripts at a shared or remote environment.

Fixture prerequisites: retain the isolated Trilliant snapshot and the fixture
IDs recorded in the report; run `section-navigator-fixture.php` through its
Local Hub WP CLI for setup/auth, and stress preparation before test edits. Copy
`section-navigator-hooks.php` to the fixture's local-only MU test hook path and
the existing `stack/themes/mrn-base-stack/inc/builder/editor-save-integrity.php`
to the fixture root as `editor-save-integrity-baseline.php`. The hook refuses
other hostnames and is test scaffolding, not a new distributed component.
Keep auth session files private in the fixture's `logs/` directory. The paired
benchmark temporarily toggles the one flag in this fixture's `wp-config.php`
and restores the original file in `finally`; do not run other fixture tests
concurrently with that measurement.

## QA Engine

Run plugin-scoped QA with full static analysis:

```bash
MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-editor-lockdown
```

Runtime browser, accessibility, API, and performance checks should be run separately against an explicit target site when this plugin change affects rendered output or live WordPress behavior.
