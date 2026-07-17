# Stack Baseline - MRN Schema Bridge

## Baseline Snapshot
- Date pinned: 2026-07-17
- Plugin source path: `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-schema-bridge`
- Intended integration target: MRN shared MU plugin loader and normal MRN brochure/client sites
- Current release model: shared MU plugin release unit
- Current plugin version: `0.4.1`

## Why This File Exists
This directory follows MRN QA Engine discovery standards so the schema plugin can be checked independently from unrelated stack work.

## Update Process
1. Update plugin release metadata and version headers.
2. Run `MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-schema-bridge`.
3. Run a separate stack QA pass when changing `stack/mu-plugins/mrn-loader.php`.
4. Run explicit runtime structured-data smoke against the target site when validating emitted JSON-LD.
5. Update `stack.lock` when the baseline metadata changes.
