# QA Toolkit

This repo now includes a small local QA toolkit for the stack theme and the local stack site.

## Recommended Local Tools

- `php`
- `node`
- `composer`
- `wp`
- `rg`
- `curl`
- optional: `gitleaks`
- optional: `shellcheck`
- optional: `lighthouse`
- `playwright` for browser smoke QA

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

### Security QA

Run the dedicated security-focused QA pass:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-security.sh
```

What it checks:

- risky-pattern scan
- focused WordPress security PHPCS sniffs
- lightweight secret-pattern scan in the theme source
- runtime dependency audit for npm packages
- runtime dependency audit for Composer packages when `composer` is installed

To also audit local dev-tooling dependencies, use:

```bash
MRN_QA_INCLUDE_DEV_AUDIT=1 \
/Users/khofmeyer/Development/MRN/stack/scripts/qa-security.sh
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

### Rollout Contract QA

Verify the stack rollout contract for `default-configs.mrndev.io`:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-rollout-contract.sh
```

What it checks:

- local `mrn-base-stack` version matches the packaged theme zip version
- `/home/mrndev-stack-manager/stack/shared` exists on the server with required shared runtime files
- `default-configs.mrndev.io/wp-content/shared` exists with required shared runtime files
- the live site resolves an active stylesheet slug successfully
- the live active theme version matches local stack source
- rollout-owned `Case Study` files exist on the active live theme when present locally
- WordPress registers the `case_study` post type on the live site when that feature is present locally

### Browser Smoke QA

Run browser-based smoke checks against the Local stack site:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-playwright-local-stack-site.sh
```

What it checks:

- home page loads in Chromium
- sample page loads in Chromium
- browser console/runtime errors are surfaced
- failed network requests are surfaced
- WordPress editor smoke coverage using a local-only QA admin account
- Site Configurations admin smoke coverage when `mrn-config-helper` is active
- leaked CSS/style text detection for targeted admin pages
- sticky-toolbar layout sanity checks for targeted admin pages

By default, the script will create or refresh a local-only `codex_qa_admin` administrator on the Local stack site for the editor smoke test. If you want to override that user, you can still provide credentials explicitly:

```bash
MRN_WP_ADMIN_USER=admin \
MRN_WP_ADMIN_PASS='your-password' \
/Users/khofmeyer/Development/MRN/stack/scripts/qa-playwright-local-stack-site.sh
```

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

For browser QA in the theme folder, also install Node dependencies and the Chromium browser:

```bash
cd /Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack
npm install
npx playwright install chromium
```

## Notes

- PHPCS now runs locally under the current PHP version, but the existing theme still has standards findings to clean up separately.
- Use this toolkit as the default baseline before packaging or release work.
