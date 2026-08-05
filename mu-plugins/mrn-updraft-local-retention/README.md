# MRN Updraft Backup Policy

WordPress plugin release unit for `mrn-updraft-local-retention`.

The MU plugin keeps no more than four local Updraft backup sets. It also
recreates missing Updraft file and database cron events when saved non-manual
schedule settings survive a database restore without their WP-Cron rows.

## QA Engine

Run plugin-scoped QA with full static analysis:

```bash
MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-updraft-local-retention
```

Runtime browser, accessibility, API, and performance checks should be run separately against an explicit target site when this plugin change affects rendered output or live WordPress behavior.
