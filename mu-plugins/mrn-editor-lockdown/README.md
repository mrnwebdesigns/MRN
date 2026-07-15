# MRN Editor Lockdown

WordPress plugin release unit for `mrn-editor-lockdown`.

When the Config Helper's administrator-only Theme File Editor lock is enabled,
the plugin denies the `edit_themes` capability for every user and removes the
Theme File Editor link from Appearance. Direct access to `theme-editor.php` is
therefore rejected by WordPress even for administrators. The lock defaults to
enabled if Config Helper is unavailable. Other theme-management capabilities
and the Plugin File Editor are not changed.

## QA Engine

Run plugin-scoped QA with full static analysis:

```bash
MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-editor-lockdown
```

Runtime browser, accessibility, API, and performance checks should be run separately against an explicit target site when this plugin change affects rendered output or live WordPress behavior.
