# AGENTS.md - MRN Environment Runtime

## Purpose
This directory contains the shared, lightweight environment and deployment-policy runtime.

## Rules
- Keep frontend bootstrap free of database reads, remote requests, and asset loading.
- Read only declared constants and WordPress environment APIs for runtime policy.
- Keep admin diagnostics read-only; infrastructure owns deployment and plugin reconciliation.
- Do not add provider credentials, client domains, or deployment secrets.
- Treat `MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-environment-runtime` as the canonical plugin readiness signal.

## Safety
- Never auto-deploy from this plugin directory.
- Coordinate stack loader and infrastructure policy changes separately.
- Require a named runtime target for live admin, API, accessibility, or performance validation.
