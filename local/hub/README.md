# MRN Local Hub

MRN Local Hub is a lightweight local control panel for pulling WordPress sites into a local filesystem workspace, running QA, and pushing selected paths back over SSH.

It is intentionally not Docker Desktop and not an Electron app. The UI is a small local Node server, and site operations shell out to standard tools: `ssh`, `rsync`, `wp`, and `mrn-qa`.

## Start

```bash
mrn local-hub
```

Then open:

```text
http://127.0.0.1:5678
```

Optional environment variables:

```bash
MRN_LOCAL_HUB_PORT=5679 mrn local-hub
MRN_LOCAL_SITES_ROOT=/Users/khofmeyer/Development/MRN-sites mrn local-hub
WPENGINE_API_USER_ID=... WPENGINE_API_PASSWORD=... mrn local-hub
```

## Site Layout

New sites are created under:

```text
/Users/khofmeyer/Development/MRN-sites/<site-slug>/
  public/
  dumps/
  backups/
  logs/
  .mrn-site.json
```

The manifest keeps the local URL, live URL, SSH target, remote path, PHP version, database name, and QA project root.

New manifests default to a true-local friendly HTTPS URL:

```text
https://<site-slug>.localhost
```

The `.localhost` suffix resolves locally without editing `/etc/hosts`. The friendly HTTPS helper binds macOS ports 80/443, proxies to OpenLiteSpeed on `127.0.0.1:8088`, and uses mkcert for local SSL. The hub also creates per-site MariaDB credentials instead of using the root database user.

## Current Runtime Contract

The hub is built for a true-local RunCloud/OpenLiteSpeed workflow, but the first layer is the control plane:

- site manifests
- SSH import with connection test and WordPress inspection
- Provider Discovery for WP Engine installs and a local SiteGround SSH registry
- provider presets for MRN Dev, RunCloud, SiteGround, WP Engine, and generic SSH hosts
- pull preflight with local tool, remote WordPress, and command previews
- guarded SSH/rsync pull
- database export/import/search-replace with WP-CLI
- selected-path push with dry-run first
- MRN QA launcher
- local tool doctor

The runtime adapter is explicit in each manifest:

```json
{
  "runtime": "local-vm-openlitespeed",
  "webserver": "openlitespeed"
}
```

That lets us wire in a local OpenLiteSpeed VM/bootstrap layer next without changing the site management UI.

## Runtime Adapter

The Runtime rail uses a Lima/OpenLiteSpeed adapter plan.

Current behavior:

- detects `brew` and `limactl`
- reports whether the `mrn-openlitespeed` Lima instance appears to exist
- generates a non-destructive bootstrap script at:

```text
/Users/khofmeyer/Development/MRN/.tmp/local-hub/runtime/bootstrap-mrn-openlitespeed.sh
```

The generated script only prints the plan by default. It writes the Lima config and starts the VM only when run with `--execute`.

Planned host ports:

- `http://127.0.0.1:8088` -> OpenLiteSpeed HTTP
- `https://*.localhost` -> friendly HTTPS helper -> OpenLiteSpeed HTTP
- `http://*.localhost` -> friendly HTTP redirect -> HTTPS
- `https://127.0.0.1:7080` -> OpenLiteSpeed admin
- `127.0.0.1:3307` -> MariaDB

Runtime actions:

- `Generate Bootstrap` writes the Lima/OpenLiteSpeed bootstrap script.
- `Bootstrap Runtime` runs the script with `--execute` and starts the Lima instance.
- `Install HTTPS Helper` installs the macOS launchd helper for no-port local HTTPS.
- `Trust Firefox` imports the mkcert local CA into Firefox profiles when Firefox does not trust the macOS System keychain.
- `Repair Install` re-runs the OpenLiteSpeed/LSPHP package install inside an existing instance.
- `Check Services` runs an in-VM status check for OpenLiteSpeed, MariaDB, Redis, PHP, and the `/srv/mrn-sites` mount.
- `Open HTTP` opens the OpenLiteSpeed default HTTP listener.
- `Open Admin` opens the OpenLiteSpeed admin listener.

## Per-Site Provisioning

Use `Provision Site` after creating a manifest and before pulling the database.

It performs the local runtime work for that site:

- creates a MariaDB database and per-site database user inside the Lima runtime
- maps `<site-slug>.localhost` to the site's public directory in OpenLiteSpeed through the friendly HTTPS helper
- rewrites the OpenLiteSpeed listener without touching macOS `/etc/hosts`
- updates `wp-config.php` to use the local database endpoint when the file already exists
- saves the original live `wp-config.php` under the site's `backups/` directory before the first local patch

Recommended first import flow:

1. Use Provider Discovery when the site lives in WP Engine or SiteGround.
2. Load the provider site into SSH Import, or enter the site URL and SSH details manually.
3. Create the manifest from SSH inspection.
4. Run `Provision Site`.
5. Run Pull `Preflight`.
6. Run file `Dry Run`.
7. Run `Pull Files`.
8. Run `Pull DB`.

## Provider Discovery

Provider Discovery prepares provider sites for the normal SSH Import workflow.

WP Engine supports account-level listing through the WP Engine API. Enter the API user ID and password in the UI for a single list call, or start the Hub with:

```bash
WPENGINE_API_USER_ID=... WPENGINE_API_PASSWORD=... mrn local-hub
```

The Hub does not write WP Engine credentials to disk. Listed installs are kept in browser memory until refresh/reload, then each result can fill SSH Import with the `environment@environment.ssh.wpengine.net` target and `sites/<environment>` root.

SiteGround does not have a matching public account-sites API in this workflow. Save SiteGround entries from Site Tools SSH details instead. Those entries are stored locally at:

```text
/Users/khofmeyer/Development/MRN-sites/.mrn-provider-sites.json
```

Each saved SiteGround entry can fill SSH Import with the chosen alias/target, optional port, live URL, and optional root such as `public_html`.

## SSH Providers

The SSH Import panel supports provider presets:

- `MRN Dev`: resolves `*.mrndev.io` sites through the `mrndev` discovery alias, then uses `site-user@mrndev-site-owner` and `/home/<site-user>/htdocs/<site>.mrndev.io` for pull/push.
- `RunCloud`: starts from `/home/runcloud/webapps/<app>/public`.
- `SiteGround`: tries `public_html` and common domain-based SiteGround roots.
- `WP Engine`: tries `sites/<environment>` based on the SSH Gateway username.
- `Generic SSH`: tries common roots such as `public_html`, `www`, `htdocs`, and `public`.

You can still enter an explicit remote WordPress root. When the path is blank, the inspector tries the provider candidates first, then does a bounded search for `wp-config.php`.

For MRN Dev, enter the live URL (`https://example.mrndev.io`) or short slug (`example`) in `Site URL or slug`, then click `Resolve MRN Dev`. The resolver is read-only: it discovers the site owner/root through the `mrndev` SSH alias and fills the SSH Import form with the site-owner SSH target used for actual file/database operations.

## SSH Key Strategy

Prefer dedicated SSH keys managed by the macOS/1Password SSH agent, with stable aliases in `~/.ssh/config`.

Example:

```sshconfig
Host client-wpengine
  HostName environment.ssh.wpengine.net
  User environment
  IdentitiesOnly yes

Host client-siteground
  HostName siteground-host.example.com
  User siteground-user
  Port 18765
  IdentitiesOnly yes
```

Use the alias (`client-wpengine`, `client-siteground`) in the Hub's SSH field. The `Preview SSH Config` button runs `ssh -G` locally, so it can show the resolved host, user, port, agent, and identity files without opening a remote SSH connection.

The `SSH Profiles` rail reads local `Host` aliases from `~/.ssh/config` and simple included config files. Selecting an alias fills the SSH Import target and keeps the port field empty so OpenSSH can use the alias's own `Port`, `User`, `IdentityFile`, agent, and proxy settings.

## Safety Defaults

- Pushes are selected-path only.
- Push writes require typing `PUSH` in the in-app confirmation dialog.
- File pulls require typing `PULL` in the in-app confirmation dialog.
- Database pulls require typing `DB` in the in-app confirmation dialog.
- SSH config previews do not connect to the remote host.
- Site provisioning writes only the local Lima/OpenLiteSpeed runtime and the local site manifest.
- Pull preflight checks SSH, local tools, remote `wp-config.php`, WP-CLI, and generated command previews before a pull.
- File pulls and pushes use `rsync --itemize-changes`.
- Push dry runs are first-class operations.
- DB pulls save a timestamped dump under `dumps/` before importing locally.
- Database search-replace skips `guid`.

## Useful Actions

From the UI:

- test SSH connection
- inspect a remote WordPress root
- create a local manifest from SSH inspection
- create/edit site manifest
- open local frontend/admin
- preflight pull readiness
- dry-run file pull
- pull files
- pull database
- dry-run selected path push
- push selected path
- run MRN QA
- run doctor

From the terminal:

```bash
node /Users/khofmeyer/Development/MRN/local/hub/server.js --doctor
```
