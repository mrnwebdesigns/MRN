# Stack Baseline - MRN Recovery Agent

## Baseline Snapshot
- Date pinned: 2026-08-20
- Plugin source path: `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-recovery-agent`
- Current plugin version: `0.1.0`
- Intended integration target: mrn-shared-mu-plugin-loader
- Current release model: shared MU plugin release unit

## Why This File Exists
This plugin follows MRN QA Engine discovery standards so it can be checked independently from unrelated stack or site work.

## Status
Phase 1 of a two-tier design (see `AGENTS.md`). Not yet installed on any
live site — recovery-key provisioning, QA Engine-side wiring review, and
the MRN QA Engine Web App repo's own required validation suites must all
pass first.

## Update Process
1. Update plugin release metadata and version headers.
2. Run `MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-recovery-agent`.
3. Run a separate runtime QA pass against the target site when validating live HTTP, admin, accessibility, or performance behavior.
4. Update `stack.lock` when baseline metadata changes.
