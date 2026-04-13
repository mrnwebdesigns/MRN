# Stack Rollout Checklist

This is the canonical pre-flight, deploy, and parity checklist for MRN stack rollouts.

Use it when you want a site to be as close to local parity as practical, especially for:

- stack theme changes
- stack MU plugin changes
- standard plugin releases
- shared runtime updates
- `default-configs.mrndev.io` verification

## Goal

A successful rollout means:

- local QA gates are green
- the intended code and packaged artifacts are deployed
- the live site is running the expected theme and plugin versions
- shared runtime files are present
- rollout-owned features still register and render
- basic admin and front-end smoke checks pass

Perfect `100%` parity should not be assumed unless you also verify live config, options, AME payloads, media, and environment behavior.

## Before You Start

1. Confirm the repo is clean.

```bash
git -C /Users/khofmeyer/Development/MRN status --short
```

2. Confirm the exact approved release state you expect to ship.

- Record the branch and exact commit SHA you are deploying.
- If the approved release state is the current working tree, record whether local is ahead of `origin/main` before you touch live.
- Do not assume the pushed remote is the approved release state unless you have verified that explicitly.

3. Confirm the release targets you expect to ship.

Examples:

- stack theme version in `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/style.css`
- plugin package versions in `/Users/khofmeyer/Development/MRN/releases/plugins/`
- rebuild fresh package artifacts when needed with `/Users/khofmeyer/Development/MRN/stack/scripts/build-release-zips.sh all`
- stack manifests in:
  - `/Users/khofmeyer/Development/MRN/stack/manifests/themes.txt`
  - `/Users/khofmeyer/Development/MRN/stack/manifests/plugins.txt`

4. Confirm whether your change touches:

- theme only
- MU plugins
- standard plugins
- `shared/`
- AME or config exports
- bootstrap/manifests

This matters because theme/MU deploy and standard-plugin deploy are different flows.

## Pre-Flight QA

Run these from the repo root unless noted otherwise.

1. Theme QA

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-theme.sh
```

2. Security QA

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-security.sh
```

3. Local stack smoke

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-local-stack-site.sh
```

4. Browser/admin smoke

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-playwright-local-stack-site.sh
```

5. Quick local speed smoke

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-page-speed.sh \
  http://mrn-plugin-stack.local \
  / \
  /sample-page/
```

6. Rollout contract pre-check for `default-configs.mrndev.io`

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-rollout-contract.sh
```

Interpret this one carefully:

- A pre-deploy failure on packaged-theme parsing or remote shared/runtime checks is a real blocker.
- After a theme version bump, a pre-deploy failure that is only the live theme version being older than local is expected until rollout.
- Re-run the same command post-deploy and require a full pass there.

Do not roll out while any of the above is red unless you have an explicit exception and know why.

## Live Preflight

Before any live-site write, run the canonical preflight helper for the target site:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/preflight-live-site-deploy.sh \
  --site-hostname default-configs.mrndev.io
```

This helper is responsible for:

- resolving `SITE_USER` / `SITE_ROOT`
- verifying the direct `mrndev-site-owner` SSH path
- removing malformed Updraft placeholder values without inventing new settings
- starting a clean Updraft backup before the deploy continues

## Deploy Path Decision

Choose the right deploy path before touching live.

### Theme / MU / Shared

Use the canonical feature deploy helper:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/deploy-feature-stack-and-default-configs.sh
```

Use this when the rollout includes any of:

- `stack/themes/mrn-base-stack`
- `mu-plugins/`
- `stack/mu-plugins/`
- `shared/`

This helper is responsible for keeping the stack server copy and `default-configs.mrndev.io` aligned for those surfaces.
It now performs the live-site preflight and writes the `default-configs.mrndev.io` refresh through the site-owner SSH path.

### Standard Plugins

Standard plugins are not part of the feature deploy helper.

Examples:

- `mrn-dummy-content`
- `mrn-config-helper`
- `mrn-editor-tools`
- `mrn-seo-helper`

For these, use the normal plugin package/install flow and then verify the installed live version with WP-CLI.

Current stack-standard-plugin path:

1. Rebuild the local zip artifact in `/Users/khofmeyer/Development/MRN/releases/plugins/`.
2. Sync that zip to `/home/mrndev-stack-manager/stack/packages/<plugin>.zip`.
3. If the plugin is active on `default-configs.mrndev.io`, run `wp plugin install /home/mrndev-stack-manager/stack/packages/<plugin>.zip --force --activate --path=/home/default-configs-stack/htdocs/default-configs.mrndev.io`.
4. Verify the resulting live version with `wp plugin list`.

## Deploy Checklist

1. Deploy theme/MU/shared changes if applicable.

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/deploy-feature-stack-and-default-configs.sh
```

2. Deploy any changed standard plugins separately.

3. If stack manifests or packaged artifacts changed, ensure the server copy is current.

4. If config exports changed, verify the intended canonical files are present in:

- `/Users/khofmeyer/Development/MRN/stack/configs/exports/`

5. If the rollout affects bootstrap behavior, verify the relevant manifest and bootstrap files on the stack server before relying on them for new sites.
   - For direct site-owner SSH bootstrap changes, verify both `scripts/site-bootstrap.sh` and `configs/site-owner-authorized-key.pub` are current on the stack server.
   - Remember that bootstrap-only fixes do not backfill older sites automatically; existing site owners may still need a one-time `authorized_keys` repair if `ssh -l <site-user> mrndev-site-owner 'whoami && pwd'` still fails.

## Post-Deploy Verification

### Contract Checks

1. Re-run the rollout contract check.

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-rollout-contract.sh
```

2. Confirm the live active stylesheet slug explicitly.

```bash
ssh -l default-configs-stack mrndev-site-owner \
  'wp --path=/home/default-configs-stack/htdocs/default-configs.mrndev.io option get stylesheet'
```

Expected today:

- `default-configs`

Do not assume the live site is using the `mrn-base-stack` directory slug directly.

### Theme Version Checks

1. Check the live active theme version.

```bash
ssh -l default-configs-stack mrndev-site-owner \
  'wp --path=/home/default-configs-stack/htdocs/default-configs.mrndev.io theme list --fields=name,status,version'
```

2. Confirm it matches the local source version.

### Standard Plugin Version Checks

Check changed plugins directly after deploy.

```bash
ssh -l default-configs-stack mrndev-site-owner \
  'wp --path=/home/default-configs-stack/htdocs/default-configs.mrndev.io plugin list --fields=name,status,version'
```

Pay special attention to standard plugins that were part of the rollout, because they can drift even when theme/MU deploy succeeds.

### Shared Runtime Checks

Verify these exist when shared runtime is part of the rollout:

- `/home/mrndev-stack-manager/stack/shared`
- `/home/default-configs-stack/htdocs/default-configs.mrndev.io/wp-content/shared`

### Feature Registration Checks

Verify rollout-owned features still register on live. Current contract example:

- `case_study` post type registration

### Front-End / Admin Smoke

At minimum, check:

1. Home page
2. Sample page
3. One affected admin page
4. One affected editor flow if the rollout changed editor/admin behavior

For `default-configs.mrndev.io`, prefer both:

- a WP-CLI verification
- a browser/manual sanity pass

### Ownership / Mode Spot Checks

If the deploy path included live file sync plus `chmod` or other ownership-sensitive normalization:

1. Check representative changed files explicitly with `stat`, not only `find`.
2. Confirm they landed with the expected owner and readable mode.
3. If one file still shows an unexpected mode after the broad normalization pass, fix that exact file and re-verify before calling the rollout complete.

## Parity Checklist

Use this when the goal is "as close to local parity as practical."

Confirm parity across:

1. Theme version
2. MU plugin files
3. Standard plugin versions
4. `shared/` runtime files
5. AME/config export payloads when relevant
6. Registered CPTs and field groups
7. Expected active stylesheet slug
8. Key admin pages
9. Key front-end pages

If one of these differs, the site is not in full rollout parity even if the deploy itself succeeded.

## Common Drift Traps

Watch for these every time:

1. Standard plugin version drift
   Theme/MU deploy helpers do not update standard plugins.

2. Active theme slug drift
   `default-configs.mrndev.io` currently runs the `default-configs` stylesheet slug.

3. Shared runtime drift
   `wp-content/shared` must be present and current.

4. Config drift
   AME or exported option payloads can differ from local expectations even when files match.
   If a canonical AME export is revised again during the same day or thread, overwrite both the active `ame-config-container.json` and the matching dated snapshot, rerun the local import smoke plus roles follow-up verification, rebuild any stack release bundle that includes the payload, and re-sync the server copy before calling the rollout done.

5. Environment drift
   The approved release may be a local commit that has not been pushed yet. Record the exact SHA in rollout notes so live state can be traced back unambiguously.
   PHP, cron, cache, or plugin runtime behavior can differ from local even with matching code.

## Rollout Is Done When

A rollout is complete when:

1. All required QA gates are green.
2. The correct deploy path was used for each changed surface.
3. The live site reports the expected theme and plugin versions.
4. The rollout contract check passes.
5. Shared runtime presence is verified when applicable.
6. A minimal live smoke test passes.
7. Any intentional parity exceptions are documented.

## Recommended Commands Bundle

Use this as the minimum practical bundle before calling a rollout "done":

```bash
git -C /Users/khofmeyer/Development/MRN status --short
/Users/khofmeyer/Development/MRN/stack/scripts/qa-theme.sh
/Users/khofmeyer/Development/MRN/stack/scripts/qa-security.sh
/Users/khofmeyer/Development/MRN/stack/scripts/qa-local-stack-site.sh
/Users/khofmeyer/Development/MRN/stack/scripts/qa-playwright-local-stack-site.sh
/Users/khofmeyer/Development/MRN/stack/scripts/qa-rollout-contract.sh
```

Then deploy the correct surfaces, and re-run:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-rollout-contract.sh
ssh -l default-configs-stack mrndev-site-owner 'wp --path=/home/default-configs-stack/htdocs/default-configs.mrndev.io theme list --fields=name,status,version'
ssh -l default-configs-stack mrndev-site-owner 'wp --path=/home/default-configs-stack/htdocs/default-configs.mrndev.io plugin list --fields=name,status,version'
```
