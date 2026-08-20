# AGENTS.md - MRN Recovery Agent

## Purpose
Detects and self-heals fatal-PHP-error/WSOD failures caused by a QA
Engine-triggered plugin or theme update, on managed child sites that have no
SSH or host-level restore API (SiteGround is the primary target — see
`docs/AUTOMATED-UPDATE-QA.md` in the QA Engine Web App repo for the wider
context). Runs entirely inside plain PHP/filesystem access via this site's
own WordPress bootstrap; never depends on SSH, WP-CLI, or a hosting
provider's API.

## Scope (read before extending)
This is Phase 1 of a two-tier design. This MU-plugin is Tier 1: it loads
inside WordPress's own bootstrap and can survive most single-plugin fatal
errors because mu-plugins load unconditionally before every regular plugin,
including the one that just broke. It cannot survive every failure class —
see "What this cannot fix" below. A bootstrap-independent Tier 0 endpoint
(for the deeper case where even mu-plugin loading fails) is explicitly out
of scope for this plugin and not yet built.

## Rules
- Keep the shutdown tripwire (`mrn_recovery_agent_shutdown_tripwire()`)
  scoped to fatals tied to an active pending-update marker only. Do not
  widen it to act on every plugin fatal on the site without an explicit,
  reviewed decision — the narrower scope was chosen deliberately to match
  the QA Engine-triggered-update problem this exists for, not general-purpose
  site monitoring.
- Every mutating REST action (`disable_plugin`, `enable_plugin`,
  `switch_theme`, `clear_cache`, `restore_snapshot`) must stay behind the
  fixed allowlist in `mrn_recovery_agent_route_action()`. Never add an
  action that accepts an arbitrary file path, command, or code string —
  this plugin must never become a general-purpose remote code/file
  execution surface.
- `disable_plugin`/`enable_plugin` must keep bypassing WordPress's normal
  `deactivate_plugin()`/`activate_plugin()` hook chains (direct
  `active_plugins` option writes only) — those hook chains could themselves
  be part of what is fataling.
- Every `/action` mutation, including `restore_snapshot`, must keep
  re-running the internal health check and refusing (HTTP 409) when the
  site currently reports healthy. Only the separate `/snapshot` *capture*
  route is exempt (capturing is passive). This gate is the primary defense
  against this plugin becoming a standing backdoor — it should only ever be
  useful exactly when the site is already broken.
- `MRN_RECOVERY_KEY` must never be logged, echoed, or written anywhere
  other than the `wp-config.php` constant it is read from. The permission
  callback must keep failing closed (denying all requests) when that
  constant is undefined.
- Treat `mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-recovery-agent` as the canonical plugin readiness signal.

## What this cannot fix
Web server/PHP-FPM down entirely, DNS failure, disk full, filesystem
permissions blocking PHP writes, a PHP-level crash severe enough that no
code (including this plugin's own shutdown handler) gets to run, or a bad
database migration from the update itself (detectable via `/status`, but
not fixable by this plugin — needs a real backup restore). None of these
are caused by a plugin/theme update in the ordinary case; this plugin is
scoped to the failure modes an update actually can cause.

## Open, unresolved (do not implement without sign-off)
- How `MRN_RECOVERY_KEY` gets provisioned into `wp-config.php` and rotated.
  This plugin's auth deliberately fails closed until that mechanism exists;
  do not add a fallback/bootstrap credential path to this file to work
  around that gap.

## Safety
- Never auto-deploy from this plugin directory.
- Never commit a real `MRN_RECOVERY_KEY` value anywhere in this repo.
- Coordinate any change to the fixed action allowlist with the QA Engine
  Web App repo's `protected_updates.py`/`mainwp_updates.py`, which is the
  only intended caller of the `/action` and `/snapshot` routes.
