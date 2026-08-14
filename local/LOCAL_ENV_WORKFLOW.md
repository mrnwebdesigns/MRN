# Local Environment Workflow

This workflow lets Local behave like an environment endpoint for MRN sites, with explicit pull/deploy steps over SSH.

Script:

- `/Users/khofmeyer/Development/MRN/local/scripts/local-env-workflow.sh`
- wrapper: `/Users/khofmeyer/Development/MRN/scripts/mrn`

## Shortcut Command

Use the shorthand commands:

- `mrn pull-site`
- `mrn deploy-site`
- `mrn nightly-pull`
- `mrn local-hub`
- `mrn install-completion zsh`

Fast positional form:

- `mrn pull-site <site-hostname>`
- `mrn deploy-site <site-hostname>`

When local path is omitted, MRN resolves it from the mapping file/inferred Local path first, then tries Local app API auto-create/reuse for pull operations.

Optional one-time install into your PATH:

```bash
ln -sf /Users/khofmeyer/Development/MRN/scripts/mrn /opt/homebrew/bin/mrn
```

## MRN Local Hub

Use the standalone browser UI when you want a Local-app-free control panel for site manifests, SSH pulls, selected-path push dry runs, and MRN QA.

Hub source now lives outside this stack repo:

```text
/Users/khofmeyer/Development/MRN-local-hub
```

```bash
mrn local-hub
```

Then open:

```text
http://127.0.0.1:5678
```

The wrapper defaults to `../MRN-local-hub/server.js`. Override the checkout path when needed:

```bash
MRN_LOCAL_HUB_HOME=/path/to/MRN-local-hub mrn local-hub
MRN_LOCAL_HUB_SERVER=/path/to/MRN-local-hub/server.js mrn local-hub
```

The hub stores site manifests under:

```text
/Users/khofmeyer/Development/MRN-sites/<site-slug>/.mrn-site.json
```

Runtime parity target:

- true local, no Docker Desktop
- OpenLiteSpeed-oriented site manifests
- local VM/native OpenLiteSpeed adapter hooks
- SSH/rsync/WP-CLI operations from the Mac

After Lima is installed:

1. Use `Generate Bootstrap`.
2. Use `Bootstrap Runtime`.
3. Use `Check Services`.
4. Use `Open HTTP` or `Open Admin` to confirm OpenLiteSpeed is reachable.

SSH import flow:

1. Use Provider Discovery first when the site is on WP Engine or SiteGround.
2. Choose MRN Dev, RunCloud, SiteGround, WP Engine, or Generic SSH.
3. Add the site URL or short slug, plus an SSH alias from `~/.ssh/config` when possible.
4. Use `Preview SSH Config` to confirm the alias resolves locally without opening a remote connection.
5. Add a live URL and remote WordPress root if known.
6. Leave the remote root blank when you want provider-aware inspection to try common roots.
7. Run `Test SSH`.
8. Run `Inspect WordPress`.
9. Create the manifest.
10. Run `Provision Site` to create the local OpenLiteSpeed vhost and MariaDB database/user.
11. Run Pull `Preflight`, then `Dry Run`, then the guarded file/database pulls.

Local site URLs use:

```text
https://<site-slug>.localhost
```

That keeps the runtime truly local and avoids macOS hosts-file edits. The friendly HTTPS helper binds ports 80/443 and proxies to OpenLiteSpeed on `127.0.0.1:8088`; Firefox may need the Runtime > HTTPS trust action because it uses its own certificate store. The hub patches the local `wp-config.php` to `127.0.0.1:3307` after provisioning or file pulls, with the first live config copied into the site's `backups/` directory.

The `SSH Profiles` rail reads local aliases from `~/.ssh/config`, including simple included files. Click an alias there to fill the SSH Import target, then use `Preview SSH Config` or `Test SSH` when you want to resolve or connect.

Provider Discovery:

- WP Engine can list installs through the WP Engine API. Enter credentials for that one call, or start the Hub with `WPENGINE_API_USER_ID` and `WPENGINE_API_PASSWORD`.
- SiteGround entries are saved locally from Site Tools SSH details because the practical SiteGround path is SSH/SFTP per site, not a public account-site listing API.
- Saved SiteGround entries live at `/Users/khofmeyer/Development/MRN-sites/.mrn-provider-sites.json`.
- Click `Use` on a discovered provider site to fill SSH Import, then inspect WordPress and create the manifest.

For `*.mrndev.io` sites, choose `MRN Dev`, enter the live URL or short slug in `Site URL or slug`, then use `Resolve MRN Dev`. The Hub discovers the site owner/root via the `mrndev` SSH alias and fills the import form with `site-user@mrndev-site-owner` plus `/home/<site-user>/htdocs/<site>.mrndev.io`, so the standard pull/push buttons work against MRN Dev too.

More detail:

- `/Users/khofmeyer/Development/MRN/local/hub/README.md`

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

- `/Users/khofmeyer/Development/MRN/local/configs/local-site-map.mrndev.io.txt`

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
    - `/Users/khofmeyer/Development/MRN/local/scripts/list-mrndev-hostnames.sh`

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
/Users/khofmeyer/Development/MRN/local/scripts/local-env-workflow.sh pull \
  --site-hostname <site-hostname> \
  --local-site-path "/Users/khofmeyer/Local Sites/<local-site>/app/public"
```

What it does:

1. Resolves `SITE_USER` / `SITE_ROOT` via `resolve-live-site-owner.sh`.
2. Verifies direct site-owner SSH access.
3. Resolves or auto-creates/reuses a Local site path for the hostname (when needed).
4. Auto-syncs runtime code surfaces (`themes`, `plugins`, `mu-plugins`) for Local API-resolved sites.
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
/Users/khofmeyer/Development/MRN/local/scripts/local-env-workflow.sh deploy \
  --site-hostname <site-hostname> \
  --local-site-path "/Users/khofmeyer/Local Sites/<local-site>/app/public"
```

Deploy behavior:

1. Runs canonical preflight (`preflight-live-site-deploy.sh`) by default:
   - site-owner resolution
   - direct SSH verify
   - read-only Updraft readiness checks and a verified database-only remote backup
2. Prompts for scope:
   - `site` = deploy local DB/uploads (site-specific content/config updates)
   - `stack` = deploy canonical stack code (theme/plugins/mu/shared) from this repo
3. Requires explicit `DEPLOY` confirmation before writes.

Helpful flags:

- `--deploy-scope site|stack` to skip scope prompt.
- `--yes` to skip confirmation prompt.
- `--dry-run` for a read-only preview without a backup. Non-dry-run deploys cannot bypass the backup gate.
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
