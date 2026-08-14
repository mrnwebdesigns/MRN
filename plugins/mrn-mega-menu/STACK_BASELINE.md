# Stack Baseline - MRN Mega Menu

## Baseline Snapshot

- Date pinned: 2026-08-14
- Plugin source path: `/Users/khofmeyer/Development/MRN/plugins/mrn-mega-menu`
- Current plugin version: `0.16.16`
- Intended integration target: mrn-plugin-stack
- Current release model: in-repo standard plugin release unit

## Why This File Exists

This plugin follows MRN QA Engine discovery standards so it can be checked independently from unrelated stack or site work.

## Update Process

1. Update plugin release metadata and version headers.
2. Run `MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/plugins/mrn-mega-menu`.
3. Run a separate runtime QA pass against a named WooCommerce site when validating storefront, admin, accessibility, or performance behavior.
4. Update `stack.lock` when baseline metadata changes.
