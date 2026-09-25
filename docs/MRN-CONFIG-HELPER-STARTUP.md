# Config Helper startup dependency correction

## Scope and source

This task changes only the shared Config Helper plugin. The authoritative plugin
source is `mrnwebdesigns/mrn-config-helper`, based on `13d3138` (0.1.65), after the initial 0.1.64 reproduction.
The Stack qualification source is `mrnwebdesigns/MRN` at `7033089` (content-filters Fleet candidate).
The production investigation is
`MRN-sites/gloves/reports/admin-performance-2026-09-25/RESULTS.md`.
Gloves exposed the dependency; the implementation contains no site/domain tests.

Task worktrees are `config-helper-startup-20260925` and
`stack-startup-20260925`, under `MRN-task-worktrees`, on corresponding `codex/`
branches. The preceding Content Only editor-label release is preserved; this release adds no further label changes.

## Dependency and callers

Previously every private `get_settings()` read merged defaults, then normalized
builder settings. After `acf/init`, normalization called
`get_builder_layout_choices()`, which reads the Stack builder target definitions
(or ACF flexible fields as fallback) and applies the layout-choice filter.
This was repeated on each settings read; the recursion guard prevented recursion,
not repeated work across callers.

The registration filter in Admin Data Post Types evaluates its configuration;
that invokes theme and Reusable Block Library visibility predicates. Those call
`get_hidden_admin_cpts()`, making a visibility decision load unrelated builder
fields. ACF initializes before later post-type registrations on these runtimes,
so the existing early-initialization guard does not prevent the later scans.

| Consumer | Context | Candidate behavior |
| --- | --- | --- |
| Theme and Reusable Block Library CPT visibility; Admin Data Post Types registration filter | Frontend, admin, REST, AJAX, cron, CLI bootstrap | Stored/default settings only; no layout discovery |
| Theme-editor lock, comments policy, dashboard widget roles, SMTP notice | Relevant admin/security hooks and API policy callers | Stored/default settings only |
| Sender details, notification exclusions, external API settings | Mail/forms/admin operations and any programmatic callers | Stored/default settings only |
| Breadcrumbs, social links, display modes/styles | Frontend rendering, ACF field construction, editor controls | Stored/default settings only |
| Site Configurations screen and settings sanitizer | Admin GET and capability/nonce-protected save | Explicit builder normalization, preserving old defaults and migration rules |
| Global/hidden/per-post-type builder allow-lists | Real editor, field filtering, rendering and programmatic consumers | Explicit builder normalization and current layout discovery |
| Layout availability and picker metadata | Builder consumers | Existing explicit layout discovery remains; unrelated allow-list normalization removed |

The public methods and wrappers retain their names and arguments. Only private
reader responsibilities change: `get_stored_settings()` validates the option
shape; `get_settings()` merges defaults; `get_builder_layout_settings()` retains
the former normalization path. There is no caching and no database migration on
read. No ACF registration, capability, nonce, save hook, frontend markup or asset
registration is disabled or changed.

## Compatibility contract

- Missing, non-array and partial settings keep the same defaults.
- Configured hidden content types remain sanitized and deduplicated.
- Presence of `allowed_builder_layouts` is checked before defaults are merged;
  explicit empty allow-lists stay empty.
- Legacy disabled lists are converted in memory only for builder consumers.
- Early/during-ACF normalization retains stored lists without catalog discovery.
- Missing ACF/catalogs preserve sanitized stored lists; later calls see fields
  registered afterward. Same-request option/filter changes remain visible.
- Reentrant builder reads retain the guard, including reset after exceptions.
- Settings screen/save consumers still receive normalized builder values.

## Qualification and measurement method

`tests/settings-startup-test.php` in Config Helper provides 123 assertions per
context (frontend/admin/REST/AJAX/cron/CLI). The 0.1.64 baseline fails the cheap
post-ACF settings read assertion with 14 discovery calls; the candidate passes.
Existing breadcrumb, WooCommerce breadcrumb and release-contract tests also run.
`tests/runtime/settings-startup.php` supplies local WordPress/ACF integration and
settings/field-save checks.

Two disposable local sites use separate table prefixes in the Local Hub
MariaDB database, separate WordPress core copies, and copied plugin/theme files.
Both run WordPress 7.1.2, ACF Pro, Classic Editor, Config Helper, Reusable Block
Library, Tokens, the Stack parent/child themes, Admin Data Post Types, Site Styles and the shared runtime. The WooCommerce fixture also contains a product and 25 synthetic HPOS
orders. It is not the Gloves production database and does not represent its
order count, plugin inventory or server load.

Diagnostic runs use a temporary local MU probe to count layout discovery, retain
caller names, and time named callbacks. Callback timings are inclusive and must
not be summed where nested. The probe is absent for wall-clock HTTP benchmarks.
For those, baseline/candidate/candidate/baseline blocks each make five uncached
requests per route after warming each route (10 observations per version).
Both variants use the same database, auth cookies, routes, PHP options, payloads,
and local machine. Each block restarts its dedicated PHP server, then warms it;
OPcache is enabled. Query strings and no-cache headers prevent page-cache reuse.

The web server is PHP 8.5.6 `cli-server` on loopback, not production PHP-FPM.
These are real HTTP document/endpoint TTFB measurements, not full browser page
load or Customers REST waterfalls. AJAX's no-action rejection and cron's empty
response measure startup only. Separate CLI observations are labeled as such.
The initial symlink/OPcache samples failed loaded-source verification and were
discarded, not included in results. Final diagnostic samples record the loaded
source hash and whether the separated reader exists.

## Measured results

The final matched 0.1.65-to-0.1.66 comparison, complete route table, diagnostic
counts, CLI observations and compatibility evidence are recorded in
`reports/config-helper-startup-2026-09-25/RESULTS.md`. Local Orders and Customers
TTFB improved, while several other route medians were slower; no fleet-wide or
production performance claim follows from these fixtures.

## Release gates and rollback

The target standalone version is 0.1.66. Source acceptance is separate from
release registration and remote deployment. At investigation time:

- The initial catalog/lock named 0.1.64; the completed concurrent release moved
  the source baseline to 0.1.65 in `2026.09.25-content-filters-fleet`.
- Fresh MainWP runtime reports for Gloves production and Trilliant development
  attest Config Helper 0.1.64, tree hash
  `0cd8b1aa6a30a3bb2543f2ac13fddeae54176dfe7ecf39a3fd9a666f50b0b39c`,
  and Deployment Agent 0.2.5, with the canonical parent/child theme shape.
- Both targeted sync calls timed out, but subsequent readbacks show advanced
  sync timestamps. This is not a reason to retry a deployment operation.
- The initially blocking Content Only label work was merged as plugin PR #7
  and released at 0.1.65 during this task. This correction was rebased onto it
  and versioned 0.1.66; no exclusion exception was needed.
- The concurrent content-filter candidate locks parent theme 1.5.0 and Config
  Helper 0.1.65. This task uses that completed source baseline. A selective
  startup canary changes only Config Helper; it does not deploy the parent theme.

After source acceptance and Git-hygiene resolution, the release owner should:

1. Merge the exact reviewed Config Helper source; reconcile the final version.
2. Register its deterministic ZIP/tree in `stack-plugin-releases.json`, retaining
   the exact 0.1.64 rollback package. Update the component catalog in an isolated
   release task and run the relevant registry/planner tests.
3. Run clean-main promotion audit. Reconcile all outstanding deployable source
   before generating a cumulative lock, commit that lock separately, then run
   candidate audit and deterministic build verification. A selective plugin plan
   binds the site's existing signed baseline and changes only Config Helper; it
   does not claim a new full-Stack release identity.
4. Select exact approved standard-WordPress and WooCommerce canary URLs. Freshly
   resolve/sync them through MainWP, read the runtime report, construct and
   preflight exact upgrade-only plans. Preserve the active plugin and child theme.
5. Immediately before each write, create and verify a labeled database-only
   Updraft backup at the configured remote destination. Preserve its receipt.
6. Apply through the established selective Stack-plugin Fleet workflow. Verify
   installed version, tree hash, active state and expected overlay in the signed
   runtime inventory, then test frontend, REST, editor fields, allow-lists, settings
   save/reload, and WooCommerce screens. Benchmark matched uncached HTTP requests
   and authenticated browser waterfalls on each canary independently.
7. On a regression or verification failure, stop the cohort. Take a fresh backup,
   use the exact reviewed 0.1.64 rollback plan, and verify restored hash/state and
   consumer behavior. A successful transport alone is not rollback proof.
8. Expand only through approved canary/cohort gates, with per-site receipts and
   runtime readback. Do not extrapolate a canary speed gain to all Stack sites.

## Separate opportunities

Character Count asset scoping remains a separate task/release. Further ACF field
construction cost, Reusable Block Library field transformations, WooCommerce
Customers payload size, worker queues and production tracing are also separate.
No changes for those opportunities are included here.
