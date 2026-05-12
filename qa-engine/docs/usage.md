# Usage

## Initialize in a project

```bash
mrn-qa init
```

This writes `.mrn-qa.env` into the current directory.

## Run QA

```bash
mrn-qa run
```

## Set up engine Playwright

```bash
bash /Users/khofmeyer/Development/MRN-qa-engine/tools/setup-playwright.sh
```

## Useful options

```bash
mrn-qa run --project-root /abs/path/to/repo
mrn-qa run --site-path "/Users/.../Local Sites/site/app/public"
mrn-qa run --site-url http://site.local
mrn-qa run --mode release
mrn-qa run --output-file reports/qa-report.md
mrn-qa run --playwright-provider engine
```

## Strict release mode example

```bash
MRN_QA_RUN_SMOKE=always MRN_SMOKE_STRICT=1 MRN_QA_FAIL_ON_THEME_ADVISORY=1 mrn-qa run --mode release
```

## Playwright env knobs

- `MRN_QA_PLAYWRIGHT_PROVIDER=engine|stack|auto`
- `MRN_QA_PLAYWRIGHT_IGNORE_REGEX=<comma-separated regex patterns>`
- `MRN_QA_ADMIN_USER=<wp admin username>` (optional for full smoke scope)
- `MRN_QA_ADMIN_PASS=<wp admin password>` (optional for full smoke scope)
