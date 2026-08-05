# Architecture

## Inputs

- profile defaults from `profiles/*.env`
- optional project overrides from `<project>/.mrn-qa.env`
- CLI flags

## Core pipeline

1. Discovery: detect repo root and nested repos.
2. Release state: branch/SHA/tag/dirty per in-scope repo.
3. Stop conditions: block on ambiguous release state or missing required discovery files.
4. Execute checks: code quality, security, runtime, smoke (policy-driven).
5. Report: emit strict markdown sections + tool table + overall result.

## Smoke execution model

- Primary: engine-owned Playwright runner (`tools/run-playwright-smoke.sh`).
- Optional fallback: stack Playwright runner when provider is `stack` or `auto`.
- Provider controlled by `MRN_QA_PLAYWRIGHT_PROVIDER`.

## Extensibility

Engine checks are implemented in shell functions under `lib/checks_wordpress.sh` and can be split into additional profiles in the future.
