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

Packaging note:

- The stack theme zip is expected to keep `style.css` at the archive root. If you wrap the archive in a parent `mrn-base-stack/` folder, this check will fail before it ever reaches the remote parity steps.

How to interpret it during a release:

- Before deploy, use it to catch bad packaging, missing shared runtime files, or broken active-stylesheet resolution.
- After a theme version bump, a pre-deploy run can still fail on live-version parity because the site is correctly still on the old release.
- Re-run it after deployment and require a full clean pass before calling the rollout complete.

What it does not check:

- Admin Menu Editor parity. The rollout contract can pass while `default-configs.mrndev.io` still has stale AME settings.

When AME parity matters, use this follow-up audit:

```bash
ssh mrndev-stack-manager@167.99.54.77 \
  'wp --path=/home/default-configs-stack/htdocs/default-configs.mrndev.io \
  admin-menu-editor export --all /tmp/default-configs-live-ame-audit.json'

scp mrndev-stack-manager@167.99.54.77:/tmp/default-configs-live-ame-audit.json \
  /Users/khofmeyer/Development/MRN/.tmp/default-configs-live-ame-audit.json
```

Then compare the live export against:

- `/Users/khofmeyer/Development/MRN/stack/configs/exports/ame-config-container.json`

If the live site is missing stack-owned AME updates, repair it by importing both canonical files:

```bash
ssh mrndev-stack-manager@167.99.54.77 \
  'wp --path=/home/default-configs-stack/htdocs/default-configs.mrndev.io \
  admin-menu-editor import /home/mrndev-stack-manager/stack/configs/exports/ame-config-container.json'
```

- Also apply `/Users/khofmeyer/Development/MRN/stack/configs/exports/ame-toolbar-editor.settings.json` because Toolbar Editor settings are not covered by the main AME container.
- Expect post-import AME re-exports to differ from the local canonical JSON in some environment-specific fields, especially callback file paths and capability-index ordering. Treat missing stack-owned menu/metabox/table-column/CPT entries as the real drift signal.

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

By default, the script will create or refresh a local-only `codex_qa_admin` administrator on the Local stack site for the editor smoke test. The script now keeps that local-only password deterministic and verifies it with a `wp_check_password()` preflight before Chromium launches so auth/bootstrap failures fail fast.

Local stack troubleshooting:

- If Chromium fails to launch from a Codex/agent run with a macOS `mach_port_rendezvous` or similar permission error, rerun `qa-playwright-local-stack-site.sh` or `npx playwright test` outside the sandbox before treating it as a site regression. That failure mode has been environment-specific rather than a broken Playwright/project setup.
- If front-end smoke reports a `404` on `mrn-plugin-stack.local`, inspect the rendered page HTML first to identify the exact missing asset before assuming a theme or deploy regression.
- The Local stack site can keep stale option data after manual content/media cleanup. One known example is a social-link `media` icon whose saved `icon_url` still points at a deleted upload.
- `mrn-base-stack` now falls back cleanly when a saved social-link media icon no longer resolves, but stale local site content or options can still surface other missing asset references.
- If you intentionally deleted local Dummy Content or uploads, refresh the Local stack site state before treating smoke failures as release blockers.

Important scope note:

- `qa-playwright-local-stack-site.sh` is smoke coverage, not a full crawl of all dummy-content output.
- When seeded-content regressions are suspected, enumerate published URLs and public archives from WordPress first, then run a broader Playwright sweep across those URLs to catch issues outside the default home/sample-page coverage.

If you want to override the default QA admin user, you can still provide credentials explicitly:

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

If you are running browser smoke from Codex and the sandboxed launch is blocked by macOS, rerun the same Playwright command outside the sandbox or approve the escalated browser run instead of changing the project first.

## Notes

- PHPCS now runs locally under the current PHP version, but the existing theme still has standards findings to clean up separately.
- Use this toolkit as the default baseline before packaging or release work.
