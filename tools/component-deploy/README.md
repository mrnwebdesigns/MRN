# Shared component release foundation

**Candidate tooling; no site deployment or adoption is enabled.** This directory
does not enter the default Stack package. The ZIP format is deliberately not a
WordPress plugin/theme installation ZIP. The normal MainWP installer must not
receive it. Every receipt reports `runtime_qualified: false`.

This implements the source/package and early plugin-selection portions of
[D19](../../docs/MRN-ASSET-RELEASE-STANDARD.md). It does not complete the shared
component deployment contract. Existing sites, installed plugin state, saved
data and retired capability archives are untouched.

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
  inactive. Normal overwrite updates of selected plugins are refused.
- Requests retain their selected physical code and asset manifest even after
  the fixture changes the pointer. New requests observe the new selection or
  rollback. No old code or assets are deleted by these tools.

The runtime has no REST/AJAX/admin-post endpoint, installer, transport, database
mutation, automatic enrollment or runtime discovery. It cannot authorize a
deployment. Do not manually install the MU template or entrypoint stubs.

## Required before any adoption

1. **Parent theme discovery:** runtime selection explicitly rejects parent
   themes. `template_directory` alone is insufficient: `WP_Theme` can discover
   the public parent directly and bypass it. The `theme_root` filter receives
   no theme slug and cannot safely redirect only the parent. A qualified
   parent/child discovery design must cover templates, metadata, block and
   classic consumers without rewriting child-theme content or paths.
2. **Plugin inventory and lifecycle:** `all_plugins` updates the admin list's
   displayed version, but raw `get_plugins()` still reads stable stub headers.
   Signed MainWP preflight/readback must agree with the selected artifact.
   Safe uninstall, deletion, translation/JSON assets, direct PHP endpoints,
   preloads, bulk updates and component-specific native build steps remain
   unqualified. A native plugin updater is not a release transport.
3. **Guarded transport and adoption:** implement the existing signed MainWP
   agent/Dashboard contract, verified fresh DB backup, exact target/source/
   checksum binding, private recovery inventory, exclusive writer lock,
   interruption-safe adoption, compare-and-switch activation, unknown-outcome
   recovery and atomic rollback. Stage/verify every immutable public asset
   before changing the pointer. Keep all coupled components compatible or
   activate them together. The fixture's direct file writes are test setup,
   not a production transaction implementation.
4. **Public serving and QA:** prove scoped HTML refresh, MIME/checksum equality
   through the serving path, fresh/warm/returning browser behavior, retained
   old HTML/assets, OPcache handling, accessibility/performance and failure
   recovery on disposable runtime/provider fixtures. Real provider/site
   qualification remains separately scoped and is forbidden by the current
   no-existing-site-touch instruction.
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
Artifact success never implies that parent activation is supported.

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
an existing database, site directory or listener. WordPress was exercised
through PHP bootstrap; this is not browser/public-edge QA.

WordPress behavior references: [plugin identity mapping](https://developer.wordpress.org/reference/functions/plugin_basename/),
[active plugin loading](https://developer.wordpress.org/reference/functions/wp_get_active_and_valid_plugins/),
[theme roots](https://developer.wordpress.org/reference/functions/get_theme_root/).
The source parser is [Acorn](https://github.com/acornjs/acorn), pinned to 8.15.0.
