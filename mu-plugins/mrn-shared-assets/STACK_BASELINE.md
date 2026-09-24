# Stack Baseline - MRN Shared Assets

## Baseline Snapshot
- Date pinned: 2026-09-24
- Plugin source path: `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-shared-assets`
- Current plugin version: `0.2.1`
- Intended integration target: mrn-shared-mu-plugin-loader
- Current release model: shared MU plugin release unit

## Why This File Exists
This plugin follows MRN QA Engine discovery standards so it can be checked independently from unrelated stack or site work.

Release `0.2.1` adds shared, consumer-aware ACF admin asset detection so Stack
integrations can avoid loading editor payload on screens that cannot use it.

## Update Process
1. Update plugin release metadata and version headers.
2. Run `MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-shared-assets`.
3. Run a separate runtime QA pass against the target site when validating live HTTP, admin, accessibility, or performance behavior.
4. Update `stack.lock` when baseline metadata changes.
