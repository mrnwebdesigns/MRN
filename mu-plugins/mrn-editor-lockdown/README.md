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

## QA Engine

Run plugin-scoped QA with full static analysis:

```bash
MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-editor-lockdown
```

Runtime browser, accessibility, API, and performance checks should be run separately against an explicit target site when this plugin change affects rendered output or live WordPress behavior.
