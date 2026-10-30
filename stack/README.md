# MRN WordPress Bootstrap Stack

This folder is a reusable bootstrap stack for new CloudPanel WordPress sites.

## Source release and site rollout

Accepted Stack/plugin default-branch pushes must continue through automatic
qualification, immutable packaging, and verified publication with no separate
routine action from the owner. This makes the release **Fleet ready**. Site
backup, deployment, and installed verification remain separately initiated and
authorized. See the [source readiness contract](../docs/MRN-FLEET-READINESS-AUTOMATION.md)
for the required workflow and the current audit-only implementation boundary.

## Environment and installation ownership

The future hosting platform owns site and environment selection. Its canonical vocabulary is the `stack` or `plain` site profile combined with the `dev`, `staging`, or `production` environment. This repository owns WordPress component source, release metadata, package inputs, and compatibility contracts; it does not define a competing environment-profile system.

`manifests/plugins.txt` is currently the Stack bundle input consumed by the hosting platform. Optional feature selection belongs in the hosting platform and should resolve components from the MRN catalog. Development-only, maintenance-only, and optional components must not become production defaults merely because MRN maintains them.

`scripts/site-bootstrap.sh` honors `--site-profile` and `MRN_SITE_PROFILE` (`stack` or `plain`) and supports profile-scoped manifest entries that end in `|stack` or `|plain` so optional shared plugins can stay out of the plain-profile bootstrap.

Independently released non-platform plugins use `manifests/optional-plugin-releases.json` and `scripts/build-mainwp-optional-plugin-plan.py`, whether they are catalog-only or remain standard bootstrap defaults. The plan builder is deliberately one-site and upgrade-only: it rejects an absent plugin, stale inventory, an unverified package, or missing backup/rollback readiness. MainWP Operations API `0.9.8` provides the matching exact-site preflight, update, and rollback abilities without adding absent plugins. See [`MAINWP_OPTIONAL_PLUGIN_ROLLOUT_PLAN.md`](./MAINWP_OPTIONAL_PLUGIN_ROLLOUT_PLAN.md).

Platform-required standard plugins can also move one at a time without
replacing the full Stack. The selective contract keeps the immutable release as
the site's baseline and records one exact component overlay with independently
verified target and rollback trees. Use
`manifests/stack-plugin-releases.json`,
`scripts/build-mainwp-stack-plugin-plan.py`, and MainWP Operations API `0.9.8`.
MU components, shared runtime, and themes remain full-release targets. See
[`MAINWP_STACK_PLUGIN_ROLLOUT_PLAN.md`](./MAINWP_STACK_PLUGIN_ROLLOUT_PLAN.md).

The normal operator entry point is now the repository-level command:

```bash
mrn fleet update \
  --site https://example.com \
  --component mrn-config-helper
```

This first pass cannot update the site. It exact-resolves and freshly syncs one
MainWP child, validates the signed baseline plus registered forward/rollback
artifacts, runs controller preflight, and prints the exact precondition hash.
After that scope has been reviewed and explicitly authorized, repeat the
printed command with `--execute`, `--approve <precondition-sha256>`, and
`--confirm-site <exact-url>`. Execution re-runs every check, fails while MCP
safe mode is on, creates and verifies the required remote database backup,
uses MainWP's two-step confirmation, and performs exact runtime plus public
HTTP readback. MainWP Operations API `0.9.8` then records the exact component
overlay and reports `current_with_approved_overlays`; unknown changes still
remain hard drift. The command never disables safe mode or installs an absent
plugin.

Cookie Consent and GTM Injector are optional integrations and are not part of
the universal bootstrap manifest. Their checksum-locked release records bind
merged standalone source, while rollout still requires the Dashboard to run
controller `0.9.8` and expose both main-file slugs. For paired updates, install
GTM Injector first so an older active Cookie Consent integration is treated as
ambiguous and fails closed throughout the transition.

## What this gives you

### Header Utility Message scheduling

The parent theme's **Header Utility Message** settings retain the **Active**
master switch and add an optional **Schedule** switch and searchable **Schedule
Time Zone**. The Announcements-style controls include optional **Starts/Ends**,
**Days of the week**, **US national holidays**, and **Custom days** (one-time
YYYY-MM-DD or annual MM-DD dates). Optional **Daily On Time/Off Time** add
recurring hours, including overnight windows. These rules can be combined;
day rules run inside the outer date window. No selected day rules means every
day, while selected weekdays, holidays and custom days match with OR logic.
Holidays include their observed weekdays. Business Information triggers and
message-token substitution remain Announcements features.

Scheduling is off by default, preserving manual messages. A blank start has
no start limit and a blank end has no expiry. The start is inclusive and the
end is exclusive. Both daily times must be set together or left blank for the
whole day. An off time earlier than the on time continues into the following
day; that spillover belongs to the selected starting day. Equal daily times
mean 24 hours from the on time.

Choose a city time zone to follow its daylight saving rules, or use the site's
WordPress time zone. Enter dates in the selected zone, independently of the
browser or server zone. Invalid dates, skipped spring-forward times and an end
at or before the start are rejected through ACF validation. If a time occurs
twice during the autumn clock change, the first occurrence is used for an
explicit date boundary. Recurring hours follow the local wall clock, including
both occurrences of a repeated hour.

Scheduled message markup remains in cached HTML, initially hidden. The inline
controller obtains UTC from a same-origin, public, read-only POST endpoint at
`/wp-json/mrn/v1/header-utility-message-clock`. Browser/CDN no-store headers and
a random echoed request token prevent cached clock replies from being trusted.
The response binds the message to the current saved settings. Refresh clients
send their current fingerprint and receive sanitized replacement markup and
schedule rules only when settings have changed. Disabled/invalid messages return
an empty slot without their saved content. The older clock-only contract remains
available for cached controllers from earlier releases.

The server clock anchors `performance.now()` for exact date boundaries and
recurring rules in the selected zone. Visitor wall time and browser time zone do
not decide visibility. The controller excludes server processing time when
estimating network transit, resynchronizes once per minute while visible and
whenever the tab regains focus, and discards samples after two minutes. Failed,
slow or unverified responses hide the message and retry after 15 seconds.
Scheduled messages stay hidden without JavaScript or before the first verified
reply. Manual messages retain server-rendered output without JavaScript. With
JavaScript, every header message slot checks once per minute while visible,
including an empty/inactive slot, so an open page can discover a newly enabled
message. Returning to a tab checks immediately. Unchanged markup is left intact;
updates replace only the message, preserving forms and the surrounding page.
Icon stylesheets are loaded on demand when a changed message needs them.
No WordPress cron job is involved.

Some browsers pause their monotonic counter during OS sleep. Device wall time
is used only to detect a discontinuity and request fresh server time; it can
never reveal a message. Hidden/restored tabs must revalidate before appearing.
Timing accuracy is bounded by the site's UTC clock, network transit and browser
timer scheduling; sleeping/offline browsers cannot promise a simultaneous visual
change. Use a time-synchronized host clock. The selected city zone still owns
DST conversion, independently of the server's configured local zone.

Saving content, dates or Active reaches visible open pages on their next check
(normally within a minute). A saved schedule already loaded in a page still
switches at its exact boundaries between checks. Deploying this feature requires
normal HTML cache invalidation once so pages receive the new controller and empty
slot; pages opened before deployment need a navigation/reload to receive it.
Normal cache purges after settings edits keep the initial HTML and no-JavaScript
manual output current, but updated controllers recover fresh settings without a
page reload or purge. Child themes overriding the parent's header must preserve
the message slot, its refresh attributes and adjacent controller even when off.

Focused checks:

```bash
php stack/themes/mrn-base-stack/tests/php/header-utility-message-schedule.php
cd stack/themes/mrn-base-stack
npx playwright test header-utility-message-schedule.spec.js --workers=2
```

### Stack components

- A plugin manifest (`manifests/plugins.txt`) for install/activate.
- A stack release snapshot (`STACK_VERSION.md`) and stack-wide notes (`CHANGELOG.md`).
- A release versioning strategy (`RELEASE_VERSIONING_STRATEGY.md`) for consistent component version bumps and packaging.
- A builder/dev conventions guide (`BUILDER_CONVENTIONS.md`) for theme layouts, reusable blocks, and shared content rules.
- A curated developer handoff guide (`DEV_HANDOFF.md`) for backend/frontend team onboarding and Google Docs export.
- A reusable feature prompt template (`FEATURE_PROMPT_TEMPLATE.md`) for scoping new stack work with shared accessibility, performance, and rollout requirements.
- A front-end implementation guide (`FRONTEND_IMPLEMENTATION_GUIDE.md`) for Motion `inView`, Site Styles token usage, and Business Information template patterns.
- A theme strategy guide (`THEME_ROADMAP.md`) for the stack theme’s role, ownership boundaries, and development roadmap.
- A theme execution checklist (`THEME_TASKLIST.md`) for turning the roadmap into backend/frontend work.
- A site update process guide (`SITE_UPDATE_PROCESS.md`) for safe plugin/theme rollouts, cloned active-theme deploys, and later front-end handoff expectations.
- A stack workflow/ops guide (`STACK_OPERATIONS.md`) for local symlink workflow, server ownership, and sync/deploy rules.
- A Local environment pull/deploy guide (`../local/LOCAL_ENV_WORKFLOW.md`) for using Local like a site environment endpoint.
- A canonical rollout checklist (`ROLLOUT_CHECKLIST.md`) for pre-flight QA, deploy-path decisions, post-deploy verification, and live parity checks.
- A MainWP full Stack fleet plan (`MAINWP_FLEET_ROLLOUT_PLAN.md`), selective standard-plugin plan (`MAINWP_STACK_PLUGIN_ROLLOUT_PLAN.md`), development-canary checklist (`MAINWP_FLEET_DEV_CANARY.md`), and deterministic builders for exact, backup-gated, one-site-at-a-time updates that preserve the active child theme.
- A guarded `mrn fleet update` operator workflow that turns the selective
  standard-plugin contract into a two-pass plan/approve/apply command without
  exposing MainWP credentials or bypassing safe mode.
- A schema and AI discovery baseline (`SCHEMA_DISCOVERY_BASELINE.md`) for active SEO provider ownership, CPT mappings, editor controls, crawler policy, and launch checks.
- An authoritative machine-readable component inventory (`manifests/component-catalog.json`), human catalog (`PLUGIN_CATALOG.md`), governance rules (`PLUGIN_GOVERNANCE.md`), historical plugin audit (`MRN_PLUGIN_AUDIT.md`), and plugin doc template (`PLUGIN_DOC_TEMPLATE.md`). Catalog inclusion does not imply default installation.
- First deep-dive plugin docs live in `plugin-docs/`.
- A per-site bootstrap script (`scripts/site-bootstrap.sh`).
- A CloudPanel cron scanner (`scripts/bootstrap-new-sites.sh`) that bootstraps only once per site.
- A canonical direct site-owner SSH public key file (`configs/site-owner-authorized-key.pub`) that bootstrap installs into each new site owner's `authorized_keys`, after removing group/other home-directory write access required by OpenSSH StrictModes.
- A canonical stack feature-deploy helper (`scripts/deploy-feature-stack-and-default-configs.sh`) that mirrors stack theme and MU changes to both the stack server and `default-configs.mrndev.io`.
- A live-site preflight helper (`scripts/preflight-live-site-deploy.sh`) that resolves the site owner, verifies direct site-owner SSH, detects malformed Updraft placeholder settings, and requires a verified database-only remote backup before every real deployment.
- A canonical backup policy (`BACKUP_POLICY.md`) with per-site S3 prefixes, staggered daily schedules, four-set retention, development-backup hygiene, and a backup gate for all non-dry-run shared-runtime writes.
- A canonical Cloudflare security policy (`CLOUDFLARE_SECURITY_POLICY.md`) with validated, plan-aware development and production intent profiles under `configs/cloudflare/`.
- A Local environment workflow helper (`../local/scripts/local-env-workflow.sh`) that pulls into Local and deploys with an explicit site-vs-stack scope prompt.
- A repo shortcut command (`../scripts/mrn`) for `mrn pull-site` and `mrn deploy-site`.
- A nightly Local sync helper (`../local/scripts/nightly-pull-mrndev-sites.sh`) for discovered `*.mrndev.io` sites.
- A release build helper (`scripts/build-release-zips.sh`) that rebuilds ignored plugin, MU plugin, and stack theme zip artifacts into `../releases/`.
- A rollout-contract QA script (`scripts/qa-rollout-contract.sh`) that verifies packaged theme parity, shared runtime presence, live active theme version parity, and rollout-owned CPT registration on `default-configs.mrndev.io`.
- A Cloudflare policy QA script (`scripts/qa-cloudflare-security-policy.sh`) that validates both declarative profiles without contacting or changing Cloudflare.
- A checklist for non-portable plugin configs (`configs/plugin-config-checklist.md`).
- Export/import payload storage (`configs/exports/`) and importer mapping manifest (`manifests/importers.txt`).

## Suggested flow in CloudPanel

1. Create a new WordPress site in CloudPanel.
2. A cron job runs `scripts/bootstrap-new-sites.sh` every 1-5 minutes.
3. The script finds unbootstrapped sites and runs `scripts/site-bootstrap.sh`.
4. Bootstrap clears host-provided standard plugins, installs the stack manifest, syncs MU plugins and `wp-content/shared`, activates the stack theme clone, and authorizes the canonical MRN site-owner public key for direct site-owner SSH.

For immutable platform updates to an existing site, use the assembled,
backup-gated workflow in [`VERIFIED_RELEASE_DEPLOY.md`](VERIFIED_RELEASE_DEPLOY.md).
It preserves each site's child stylesheet and verifies the live runtime against
the selected release lock before reporting success. For a qualified site using
the canonical `mrn-base-stack` parent plus either the stock or an agent-attested
site-specific child, the provider-independent MainWP path in
[`MAINWP_FLEET_ROLLOUT_PLAN.md`](MAINWP_FLEET_ROLLOUT_PLAN.md) applies the same
immutable release one named site at a time.
5. A marker file is created so the same site is not bootstrapped again.
6. On a host configured for [Dev deployment enrollment](../docs/MRN-DEV-DEPLOYMENT-ONBOARDING.md),
   a successful new-site bootstrap queues repository and Dev deployment setup.
   Later scanner passes resume this independent stage without re-bootstrapping
   WordPress. Handoff waits for `ready`, including an ordinary-push deployment
   test. Existing marked sites are not automatically enrolled; Live stays disabled.


## First setup

1. Edit `manifests/plugins.txt` with your plugin slugs or zip URLs, and add `|stack` or `|plain` when an entry should be profile-scoped.
2. Review defaults inside `scripts/site-bootstrap.sh` (timezone, permalink, admin email).
   - Optional stack-managed reCAPTCHA Enterprise secret files consumed by bootstrap:
   - `secrets/recaptcha-enterprise-project-id.txt`
   - `secrets/recaptcha-enterprise-service-account-email.txt`
   - `secrets/recaptcha-enterprise-private-key.pem`
   - `secrets/recaptcha-enterprise-allowed-domains.txt` (optional)
   - `secrets/recaptcha-enterprise-default-integration-type.txt` (optional)
3. Add optional plugin import scripts to `configs/importers/`.
4. Manage importer mappings in `manifests/importers.txt` (or through Stack Manager UI).
   - Supported by default importer script:
   - `option_json|file.json|option_name`
   - `option_text|file.txt|option_name`
   - `mrn_license_vault_json|file.json|overwrite`
   - `mrn_unified_export_zip|file.zip`
5. Add a root cron entry on the CloudPanel server:

```bash
*/5 * * * * /bin/bash /opt/mrnplugins/scripts/bootstrap-new-sites.sh --stack-root /opt/mrnplugins >> /var/log/mrnplugins-bootstrap.log 2>&1
```

Adjust `/opt/mrnplugins` to wherever you deploy this folder on the server.

## October 2026 simplification

The accepted decisions and release gates are tracked in [`docs/STACK-SIMPLIFICATION-2026-10.md`](../docs/STACK-SIMPLIFICATION-2026-10.md). Fresh installs use SEOPress Free/PRO 10.3, native breadcrumbs and FluentSMTP. Converter for Media and Wordfence are standard defaults; see [mail, media and security configuration](MAIL-MEDIA-SECURITY.md). Duplicate MRN editor plugins are catalog-only; existing sites use the [guarded migration runbook](scripts/migrations/README.md). Source changes are not a completed Fleet rollout until exact packages, backup/preflight, runtime QA and deployment verification pass.
