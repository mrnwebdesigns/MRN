# Local Environment Workflow

This workflow lets Local behave like an environment endpoint for MRN sites, with explicit pull/deploy steps over SSH.

Script:

- `/Users/khofmeyer/Development/MRN/stack/scripts/local-env-workflow.sh`
- wrapper: `/Users/khofmeyer/Development/MRN/scripts/mrn`

## Shortcut Command

Use the shorthand commands:

- `mrn pull-site`
- `mrn deploy-site`
- `mrn nightly-pull`
- `mrn install-completion zsh`

Fast positional form:

- `mrn pull-site <site-hostname>`
- `mrn deploy-site <site-hostname>`

When local path is omitted, MRN resolves it from the mapping file/inferred Local path first, then tries Local app API auto-create/reuse for pull operations.

Optional one-time install into your PATH:

```bash
ln -sf /Users/khofmeyer/Development/MRN/scripts/mrn /opt/homebrew/bin/mrn
```

## Nightly Pull For All `*.mrndev.io` Sites

Use this to pull every discovered `*.mrndev.io` site.

One-off run:

```bash
mrn nightly-pull
```

Default behavior:

- pulls database for discovered sites
- skips uploads by default for nightly speed
- uses snapshot fallback when a runnable Local path is unavailable

Include uploads:

```bash
mrn nightly-pull --with-uploads
```

Preview only:

```bash
mrn nightly-pull --dry-run
```

Mapping file used by nightly pull:

- `/Users/khofmeyer/Development/MRN/stack/configs/local-site-map.mrndev.io.txt`

Map file format:

```text
hostname|/absolute/local/site/path
```

Nightly cron example (runs at 2:30 AM daily):

```bash
30 2 * * * /opt/homebrew/bin/mrn nightly-pull >> "/Users/khofmeyer/Development/MRN/.tmp/nightly-pull.log" 2>&1
```

## Tab Completion

Install zsh completion:

```bash
mrn install-completion zsh
```

Install bash completion:

```bash
mrn install-completion bash
```

Print completion script without installing:

```bash
mrn completion zsh
mrn completion bash
```

Hostname autocomplete source:

- Completion now merges:
  - mapped hostnames from `local-site-map.mrndev.io.txt`
  - live-discovered hostnames from the dev server (`mrndev`) via:
    - `/Users/khofmeyer/Development/MRN/stack/scripts/list-mrndev-hostnames.sh`

So hosts like `freedomhouse.mrndev.io` autocomplete even before a local mapping exists.

## Pull A Site Into Local

Use this when you want the latest live/dev content and media in Local for testing/QA.

Shortcut form:

```bash
mrn pull-site \
  --site-hostname <site-hostname> \
  --local-site-path "/Users/khofmeyer/Local Sites/<local-site>/app/public"
```

Shortcut positional form:

```bash
mrn pull-site <site-hostname>
```

Direct form:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/local-env-workflow.sh pull \
  --site-hostname <site-hostname> \
  --local-site-path "/Users/khofmeyer/Local Sites/<local-site>/app/public"
```

What it does:

1. Resolves `SITE_USER` / `SITE_ROOT` via `resolve-live-site-owner.sh`.
2. Verifies direct site-owner SSH access.
3. Resolves or auto-creates/reuses a Local site path for the hostname (when needed).
4. Auto-syncs runtime code surfaces (`themes`, `plugins`, `mu-plugins`) for Local API auto-created sites.
5. Pulls `wp-content/uploads` into Local.
6. Pulls the database into Local and rewrites imported URLs back to the Local home URL.

Helpful flags:

- `--skip-db` for media/files only.
- `--skip-uploads` for DB-only pull.
- `--local-home-url <url>` when local home cannot be inferred.
- `--sync-runtime` to force runtime code sync (`themes/plugins/mu-plugins`) before pull.
- `--no-sync-runtime` to skip runtime code sync.
- `--local-api-auto-create` to force-enable Local API auto-create/reuse.
- `--no-local-api-auto-create` to disable Local API auto-create/reuse.
- `--snapshot-if-missing` for artifact-only pull when no Local site is available.
- `--dry-run` for non-writing preview (DB import/export skipped).

## Deploy From Local

Use this when you are ready to push updates and want a deploy prompt that separates site-specific updates from stack updates.

Shortcut form:

```bash
mrn deploy-site \
  --site-hostname <site-hostname> \
  --local-site-path "/Users/khofmeyer/Local Sites/<local-site>/app/public"
```

Shortcut positional form:

```bash
mrn deploy-site <site-hostname>
```

Direct form:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/local-env-workflow.sh deploy \
  --site-hostname <site-hostname> \
  --local-site-path "/Users/khofmeyer/Local Sites/<local-site>/app/public"
```

Deploy behavior:

1. Runs canonical preflight (`preflight-live-site-deploy.sh`) by default:
   - site-owner resolution
   - direct SSH verify
   - Updraft normalization/backup
2. Prompts for scope:
   - `site` = deploy local DB/uploads (site-specific content/config updates)
   - `stack` = deploy canonical stack code (theme/plugins/mu/shared) from this repo
3. Requires explicit `DEPLOY` confirmation before writes.

Helpful flags:

- `--deploy-scope site|stack` to skip scope prompt.
- `--yes` to skip confirmation prompt.
- `--skip-backup` only when intentionally bypassing preflight backup.
- `--skip-db` / `--skip-uploads` to narrow site-scope deploys.
- `--delete-uploads` to mirror uploads exactly during site-scope deploy.
- `--dry-run` for rsync previews (DB write steps skipped).

## Scope Rule

- Use `site` deploy scope for site-specific content/config changes.
- Use `stack` deploy scope for shared stack runtime changes that should stay aligned with canonical source in this repo.

## Safety Notes

- One site at a time by design.
- Deploys are site-owner SSH writes, not fallback personal-user writes.
- Stack deploy keeps clone-theme identity by preserving the live active theme metadata (`Theme Name`, `Text Domain`) during sync.
