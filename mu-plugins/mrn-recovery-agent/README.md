# MRN Recovery Agent

Must-use WordPress plugin. Detects and self-heals a fatal PHP error caused
by a QA Engine-triggered plugin or theme update, without SSH or a hosting
provider API. Companion piece to MRN QA Engine's protected-update pipeline
(`protected_updates.py`/`mainwp_updates.py` in the QA Engine Web App repo).

## Why an mu-plugin

Must-use plugins (`wp-content/mu-plugins/`) load unconditionally before
every regular plugin, including `mainwp-child`. A fatal PHP error thrown
while loading a regular plugin halts the whole request, so anything that
depends on a regular plugin having loaded successfully — including MainWP
Child's own authenticated request handling — can't be trusted during the
exact failure this plugin exists to detect. This plugin runs before that
point and does not depend on it.

## What it does

- **Shutdown tripwire**: registers a shutdown handler at load time. If the
  request ends in a fatal error tied to a plugin QA Engine is actively
  updating (a pending-update marker set via `/mark-pending` immediately
  before the update runs), disables that one plugin so the next real
  visitor request recovers — no waiting on any external poll.
- **REST API** (`mrn-recovery/v1`, bearer-token authenticated):
  - `GET /status` — health-check battery: home page (scanned for PHP error
    signatures), `/wp-json/` REST root (differentiates a broken front end
    from broken rewrite rules), `wp-login.php` reachability, memory
    headroom, best-effort error-log tail.
  - `POST /snapshot` — captures a plugin/theme's code plus `.htaccess` and
    the `rewrite_rules` option, ahead of an update. Passive; not gated on
    current health.
  - `POST /action` — fixed allowlist: `disable_plugin`, `enable_plugin`,
    `switch_theme`, `clear_cache`, `restore_snapshot`. Refuses to mutate a
    currently-healthy site (self-enforced, HTTP 409).
  - `POST /mark-pending` — sets the pending-update marker the shutdown
    tripwire checks against.

## What it does not do

No arbitrary code execution, no general file manager, no database access
beyond the specific option writes listed above. See `AGENTS.md` for the
full list of failure modes this plugin cannot fix (web server down, disk
full, DNS failure, and similar — none of which a plugin update ordinarily
causes).

## Configuration

`MRN_RECOVERY_KEY` — required `wp-config.php` constant. The REST API
refuses every request (fails closed) until this is defined. Provisioning
and rotation of this value is **not yet implemented** — see `AGENTS.md`'s
"Open, unresolved" section before building that piece.

## Status

Phase 1 of a two-tier design. Not yet installed on any live site pending:
recovery-key provisioning mechanism, QA Engine-side wiring, and the
required MRN QA Engine Web App validation suites.
