# QA Toolkit

This repo now includes a small local QA toolkit for the stack theme and the local stack site.

## Recommended Local Tools

- `php`
- `node`
- `composer`
- `wp`
- `rg`
- `curl`
- optional: `shellcheck`
- optional: `lighthouse`
- optional: `playwright`

## Repo Scripts

All QA scripts live in:

- `/Users/khofmeyer/Development/MRN/stack/scripts`

### Theme QA

Run the stack theme checks:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-theme.sh
```

What it does:

- `php -l` across theme PHP files
- `git diff --check`
- risky-pattern scan
- `node --check` for non-vendor theme JS
- `parallel-lint` when Composer tools are installed
- `phpcs` when Composer tools are installed

### Risk Scan

Run the lightweight risky-pattern scan directly:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-risk-scan.sh \
  /Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack
```

### Local Stack Site Smoke Test

Smoke-test the Local stack site:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-local-stack-site.sh
```

What it checks:

- site home URL resolves through `wp`
- active theme name/version
- builder Add Row helper is loaded
- ACF builder layouts are registered

### Lightweight Speed Checks

Use curl-based timing checks for quick regressions:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-page-speed.sh \
  https://default-configs.mrndev.io \
  / \
  /sample-page/
```

Recommended local equivalent:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-page-speed.sh \
  http://mrn-plugin-stack.local \
  / \
  /sample-page/
```

## Composer Tooling

For the stack theme, install/update Composer dev tools in:

- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack`

Example:

```bash
cd /Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack
composer install
```

That provides:

- `vendor/bin/phpcs`
- `vendor/bin/phpcbf`
- `vendor/bin/parallel-lint`

## Notes

- PHPCS now runs locally under the current PHP version, but the existing theme still has standards findings to clean up separately.
- Use this toolkit as the default baseline before packaging or release work.
