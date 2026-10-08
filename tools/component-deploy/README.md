# Shared component release foundation

**Source tooling with an explicit parent-only CloudPanel Dev qualification
adapter and a manual Nexcess Live qualification candidate.** Standard-plugin
adoption remains a candidate. No automatic site adoption or native Dashboard
parent installer is enabled. Live acceptance requires its separate exact-target
qualification; see the [candidate gates](../../docs/releases/2026.10.08-parent-live-qualification.md).
This directory does not enter the default Stack package. The ZIP format is deliberately not a
WordPress plugin/theme installation ZIP. The normal MainWP installer must not
receive it. Build receipts report `runtime_qualified: false`; the authorized
operator transaction produces separate target-specific runtime evidence.

This implements the source/package and early component-selection portions of
[D19](../../docs/MRN-ASSET-RELEASE-STANDARD.md). It does not complete the shared
component deployment contract. It does not automatically enroll existing sites,
change installed plugin state, or replace retired capability archives. The
explicit Dev qualification and the reviewed Nexcess Live candidate are the
available operator routes; neither authorizes a site write by itself.

## Implemented and tested

- Export one exact Git commit and source directory. Honor `export-ignore`;
  preserve runtime `scripts/` and `vendor/` directories. Exclude explicit
  development/test files. Refuse aliases and unsupported archive entries.
- Reuse the locked esbuild compiler. Regenerate stale minified siblings, retain
  relative static dependency layout, and hash the final emitted bytes. A whole
  component static graph is one immutable generation: unchanged graphs keep
  their URL; a dependency change versions the graph together. This does not
  provide per-file URL reuse across changed generations.
- Parse CSS dependencies and literal ES imports, exports, dynamic imports and
  `new URL(..., import.meta.url)` references. Refuse missing local dependencies,
  computed imports, CommonJS loaders and workers requiring a specific adapter.
  External references are recorded, not fetched or qualified. Arbitrary DOM
  asset construction, inline PHP/JS URLs, preload hints, JSON/language bundles,
  optimizers and service workers require consumer-level qualification; the
  parser is not a complete browser dependency audit.
- Emit deterministic ZIPs with source identity, full file/checksum inventory,
  synchronized code/static bytes and an asset manifest. Independently validate
  the archive against a separately trusted checksum, including duplicate keys,
  traversal, symlinks, mismatched generations and unlisted payloads.
- A candidate early MU template verifies private control code and pins the
  complete selection before normal plugins load. Explicit public entrypoint
  stubs can then use `MRN_Component_Release_Runtime::entrypoint(__FILE__)` while
  retaining WordPress activation and hook identities. Inactive plugins stay
  inactive. Core updates, uploaded overwrite ZIPs, uninstall callbacks and
  deletion of selected plugins are refused before changing managed files.
- A SHA-bound, immutable theme discovery view contains exactly two explicit
  directory links: the selected private parent and the physical, preserved
  child. Native `WP_Theme` discovers the same parent as template functions;
  metadata, file inventories, block patterns and classic template lookup stay
  pinned. Parent public assets use `mrn-assets/<generation>/mrn-base-stack`,
  matching WordPress's native root/slug URI composition. Earlier parent-only
  packaging candidates must be rebuilt for this layout; plugin paths do not
  change. Private view paths never enter the persistent theme-roots cache.
- The discovery fixture qualifies one ordinary single-site parent/child pair.
  Core theme switches, removal and overwrite are guarded. Child files and
  their normal public URL remain unchanged. Missing, substituted or aliased
  views fail closed before hooks are installed.
- Requests retain their selected physical code and asset manifest even after
  the fixture changes the pointer. New requests observe the new selection or
  rollback. No old code or assets are deleted by these tools.
- Disposable HTTP/Chromium tests verify public asset SHA-256/MIME equality,
  literal/dynamic module imports, changed computed CSS, actual browser cache
  hits, fresh and returning visitors, retained old HTML, and rollback with
  OPcache enabled and timestamp revalidation disabled. The test server's
  explicit cache policy is a fixture, not a production hosting adapter.

The runtime has no REST/AJAX/admin-post endpoint, database mutation or automatic
enrollment. It cannot authorize a deployment. Do not manually install the MU
template or entrypoint stubs. The operator adapter below is an explicit
backup-gated transaction, not a normal WordPress installer.

## Parent-only CloudPanel Dev adapter

`parent_deploy.py` accepts one canonical `https://<site>.mrndev.io` target,
fresh exact-site MainWP resolution/sync evidence and an explicitly selected
site-owner SSH transport. The current Dashboard has no parent-only installer
ability; this is a disclosed operator route, not a replacement MainWP server.
It refuses Live, cross-Dashboard or stale inventory, changed preflight trees,
unverified/unlabeled/local-only backups, aliases and unknown transaction outcomes.

Use the existing canonical labeled Updraft pre-deploy helper when the MainWP
start ability cannot provide the required label. MainWP must verify completion;
the host independently verifies that exact nonce, remote upload, DB checksum,
label and freshness before even transferring private control tools.

The public parent and child remain byte-identical. Code, synchronized minified
assets and manifests are staged privately. Assets publish under a retained
immutable generation before an atomic pointer selects the parent. A stable early
MU bootstrap installs native parent discovery; a physical child stays unchanged.
For an already adopted child, the exact existing loader is verified and its
current private functions/assets are pinned before either theme loads. Native
child metadata retains the stable public identity, matching that child loader's
existing behavior. A later child release is picked up by the next request.

Parent transactions share the existing child deployment lock, use exact
compare-and-switch checks and durable intent/receipt records. First adoption
requires an explicitly authorized Dev qualification with public rollback and
reactivation. Verification failure returns to the original untouched parent.
Interrupted/unknown outcomes block automatic retry until the actual pointer,
bootstrap, code, assets and preserved trees are inspected. No release or old
asset is deleted. Shell-only recovery bypasses selection for backup commands;
HTTP parameters cannot enable that recovery path.

The old full Stack lock remains unchanged. Its signed runtime report shows the
actual private parent version/tree/source/generation and intentional parent
variance. Ordinary parent overwrite, theme switching and legacy full Stack
apply/rollback are blocked while this selection is active. Before a later full
Stack rollout, use the same fresh-backup-gated adapter with `disable: true` to
return to the original public parent, then follow the normal Stack workflow.
This adapter does not add a Dashboard-approved overlay ledger record.

A read-only preflight uses:

```sh
python3 tools/component-deploy/parent_deploy.py /private/one-site-plan.json \
  --receipt /private/preflight.json
```

Review the returned preserved parent/child/pointer and other-Stack inventories,
then bind those exact values, the source/artifact checksums and the fresh backup
nonce into the confirmed plan. First adoption sets `qualify_dev: true` and
`exercise_rollback: true`. Confirmed execution uses the same command with
`--confirm`. Every target must separately qualify its provider and actual
parent/child consumers; passing the disposable fixture is not site adoption.

Disposable coverage includes package/CAS/writer conflicts, preserved trees,
immutable-byte drift, private-path aliases, backup failure before writes,
WordPress parent discovery with an independently released child, existing child
updates, actual warm browser cache, retained old HTML and OPcache rollback.

## Remaining shared-component adoption work

1. **Consumer qualification:** the ordinary physical-child discovery contract
   is tested; multisite, additional theme roots, child release loaders,
   optimizers, recovery mode and real fleet parent/child consumers are not
   qualified by that fixture. The adoption adapter must validate the exact
   child mode and compatibility pair before creating a view. The parent Dev adapter tests an independently adopted single-site child;
   every actual pair still requires the explicit target qualification above. A core method explicitly given an old physical
   theme root will still read that root; consumers bypassing normal WordPress
   discovery need an adapter or must be refused.
2. **Plugin inventory and lifecycle:** `all_plugins` updates the admin list's
   displayed version, but raw `get_plugins()` still reads stable stub headers.
   Signed MainWP preflight/readback must agree with the selected artifact.
   Controlled unadoption/data retirement, translation/JSON assets, direct PHP
   endpoints, preloads and component-specific native build steps remain
   unqualified. Core uninstall/delete is blocked, not implemented as a data
   migration. A native plugin updater is not a release transport.
3. **Guarded transport and adoption:** implement the existing signed MainWP
   agent/Dashboard contract, verified fresh DB backup, exact target/source/
   checksum binding, private recovery inventory, exclusive writer lock,
   interruption-safe adoption, compare-and-switch activation, unknown-outcome
   recovery and atomic rollback. Stage/verify every immutable public asset
   before changing the pointer. Keep all coupled components compatible or
   activate them together. The fixture's direct file writes are test setup,
   not a production transaction implementation.
4. **Public serving and QA:** extend the passing disposable serving/browser
   checks to exact provider adapters, scoped HTML refresh and failure recovery,
   and run accessibility/performance against actual component consumers.
   Passing the test page does not qualify fleet layouts. Parent-only CloudPanel
   Dev qualification requires explicit owner authorization through the adapter
   above. Standard-plugin adoption, other providers, and Live remain outside
   that authorization and unqualified.
5. **Promotion:** integrate exact artifacts into Stack lock/preflight and
   optional-plugin plans, verify clean source, hosted package parity and the
   required release/runtime checks. The current retirement candidate remains
   preserved; this source tooling does not promote or replace it.

## Build and verify source artifacts

Run in a dedicated checkout with the locked dependencies installed:

```sh
npm ci --ignore-scripts --prefix tools/site-deploy
npm ci --ignore-scripts --prefix tools/component-deploy
python3 tools/component-deploy/build.py \
  --repo /absolute/component/repository --commit <40-character-source-SHA> \
  --slug mrn-example --kind standard-plugin --entrypoint mrn-example.php \
  --output /private/artifacts/mrn-example.zip
python3 tools/component-deploy/verify.py /private/artifacts/mrn-example.zip \
  --sha256 <separately-trusted-build-SHA256> --commit <40-character-source-SHA> \
  --slug mrn-example --kind standard-plugin --entrypoint mrn-example.php
```

Parent packaging uses `--kind parent-theme --slug mrn-base-stack --entrypoint
functions.php --source stack/themes/mrn-base-stack` against the MRN repository.
Artifact success never authorizes parent adoption on a site.

## Tests and evidence

```sh
python3 -m unittest discover -s tools/component-deploy/tests -v
```

Supply `MRN_COMPONENT_WP_ARCHIVE` and `MRN_COMPONENT_SQLITE_ARCHIVE` for the real
WordPress test. Its pinned inputs are WordPress 7.1.2 and SQLite Integration
3.0.2; SHA-256 digests are checked in the test before extraction. CI downloads
only those public development dependencies. The test creates a fresh temporary
WordPress installation, blocks outgoing WordPress HTTP/mail and cron, uses a
new SQLite file, and removes only that temporary tree when done. It never uses
an existing database, site directory or listener. Set `MRN_COMPONENT_BROWSER=1`
to also start a temporary PHP server bound only to a freshly selected loopback
port and exercise Chromium. Install the pinned browser with
`tools/component-deploy/node_modules/.bin/playwright install chromium` first.
CI runs this browser test; an omitted environment switch is an explicit skip.

For MRN QA against that same temporary server, set `MRN_COMPONENT_QA_ENGINE`
to the engine executable and `MRN_COMPONENT_QA_OUTPUT` to an external report
path. The fixture then runs source/API/browser/axe/performance/CWV checks with
an explicit temporary site path and URL before stopping its own server. Run
this serially because the engine uses shared report filenames. These reports
are fixture acceptance, not full Stack promotion or public-provider evidence.

WordPress behavior references: [plugin identity mapping](https://developer.wordpress.org/reference/functions/plugin_basename/),
[active plugin loading](https://developer.wordpress.org/reference/functions/wp_get_active_and_valid_plugins/),
[theme roots](https://developer.wordpress.org/reference/functions/get_theme_root/).
The source parser is [Acorn](https://github.com/acornjs/acorn), pinned to 8.15.0.
