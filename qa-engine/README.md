# MRN QA Engine

Portable, repo-agnostic QA engine for MRN projects.

## What this is

`mrn-qa` is a shared QA runner that can be invoked from any repo when a thread requests formal QA. It standardizes:

- release state validation
- site-only scope detection (with optional stack linkage)
- WordPress security checks
- static analysis/tool execution
- strict markdown report output

## Design goals

- Single command interface across projects and repos.
- Works regardless of repo path.
- Fails safely when release state is ambiguous.
- Uses local project scripts when available, with engine fallbacks.
- Transportable as a standalone repo.

## Quick start

1. Install a local command shim:

```bash
bash tools/install.sh
```

2. Install engine Playwright once:

```bash
bash tools/setup-playwright.sh
```

3. In any project repo:

```bash
mrn-qa init
mrn-qa run
```

4. Optional strict release gate:

```bash
MRN_QA_RUN_SMOKE=always MRN_SMOKE_STRICT=1 mrn-qa run --mode release
```

## Command summary

- `mrn-qa init` - write `.mrn-qa.env` template in current repo
- `mrn-qa doctor` - show available QA toolchain binaries/scripts and engine Playwright status
- `mrn-qa run` - execute QA and output canonical report

## Repo layout

- `bin/mrn-qa` - primary CLI
- `lib/` - shared functions (discovery, reporting, checks)
- `profiles/wordpress-site.env` - default WordPress profile settings
- `tools/install.sh` - symlink installer for `mrn-qa`
- `tools/setup-playwright.sh` - install Playwright deps + Chromium for engine smoke checks

## Playwright provider

Smoke checks are now engine-owned by default.

- `MRN_QA_PLAYWRIGHT_PROVIDER=engine` uses engine Playwright runner.
- `MRN_QA_PLAYWRIGHT_PROVIDER=stack` uses stack runner.
- `MRN_QA_PLAYWRIGHT_PROVIDER=auto` tries engine first, then stack.

## Transport

This repo is self-contained and can be cloned anywhere. The CLI resolves absolute paths at runtime and supports env overrides per machine.
