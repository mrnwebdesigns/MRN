# Stack Baseline - MRN Post Duplicator Admin Bar Enhance

## Baseline Snapshot
- Date pinned: 2026-06-30
- Plugin source path: `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-duplicate-enhance`
- Current plugin version: `1.1.1`
- Intended integration target: mrn-shared-mu-plugin-loader
- Current release model: shared MU plugin release unit

## Why This File Exists
This plugin follows MRN QA Engine discovery standards so it can be checked independently from unrelated stack or site work.

## Update Process
1. Update plugin release metadata and version headers.
2. Run `MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-duplicate-enhance`.
3. Run a separate runtime QA pass against the target site when validating live HTTP, admin, accessibility, or performance behavior.
4. Update `stack.lock` when baseline metadata changes.
