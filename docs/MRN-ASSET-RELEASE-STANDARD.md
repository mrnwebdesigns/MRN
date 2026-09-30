# CSS and JavaScript release standard

This is the required deployment contract for MRN-owned CSS/JS in the shared
Stack, its components, and site child themes, including partial-Stack sites.
Shared components retain the Stack release-lock process; child themes retain
their site release process. Each owner packages its own assets and manifest.
A child-theme deployment does not replace the parent or shared plugins.

**Adoption status:** the child-theme candidate now includes a backup-gated
CloudPanel Dev adapter, immutable private code, a request-pinned loader, atomic
release selection, retained public assets, public checksum verification, and
rollback. First adoption remains an explicit host qualification operation;
`DEPLOY_READY=0` is required until that target passes. Live activation remains
disabled in code. Existing Stack and other host adapters must independently
demonstrate this contract before asset promotion.

## Automatic versions and synchronized builds

- Build assets once from the reviewed immutable source using a locked toolchain.
  Generate content hashes from the **final emitted bytes**, after minification
  and dependency-URL rewriting. Prefer filenames such as
  `site.<content-hash>.min.css` and `header.<content-hash>.min.js`.
- Generate a release manifest mapping logical handles to immutable paths,
  full SHA-256 checksums, sizes, dependencies, component/source commit, and build
  identity. If filenames abbreviate hashes, detect collisions during packaging.
  Unchanged output keeps its URL; changed output always gets a different URL.
  **Never serve different bytes at an already released asset URL**, including
  after rollback. Do not overwrite or recycle old versioned objects.
- Prefer hashed filenames or immutable release directories. A content-derived
  query version is acceptable only when every cache keys on it **and** the
  origin maps each version to its preserved bytes. Changing `?ver=` while
  overwriting one mutable file does not satisfy this immutability contract.
  Manual theme/plugin version bumps, WordPress's default version, `filemtime()`,
  request timestamps, and random query strings are not the release mechanism.
- Build source and minified CSS/JS together. Package the source, generated
  variants, dependency graph, and manifest from that same build; verify a clean
  rebuild reproduces the outputs. Fail on a missing/stale `.min` variant or
  manifest checksum mismatch. Never hand-edit only one variant or rebuild a
  different artifact for Live after qualifying Dev.
- Enqueue through the owner's generated manifest. Production/minified and
  development/debug variants each resolve to their own versioned output. Keep
  WordPress's theme metadata `style.css` separate from the fingerprinted frontend
  stylesheet. Include module imports, dynamic chunks, CSS imports/URLs, preload
  hints, and optimizer-generated references in the dependency graph. A changed
  dependency must update every emitted reference that needs its new URL.
- Resolve the manifest once per request/release, not by hashing files on every
  page request. Scope any manifest lookup cache to the immutable release ID.
  Missing entries or missing files block release; there is no silent fallback
  to an unversioned asset.

WordPress exposes version parameters on
[`wp_enqueue_style()`](https://developer.wordpress.org/reference/functions/wp_enqueue_style/)
and [`wp_enqueue_script()`](https://developer.wordpress.org/reference/functions/wp_enqueue_script/).
Those parameters do not build a manifest or preserve previous file contents.
Long-lived `immutable` caching belongs on immutable asset URLs; HTML needs a
separate freshness policy. See [MDN's caching guidance](https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/Caching).

## Back up, stage, and activate atomically

1. Resolve the exact environment, live parent/child identity, current release,
   drift, owned paths, and affected HTML URLs/cache tags. Complete source and
   applicable runtime QA. Record the parent/child compatibility pair; a shared
   asset release must qualify its consumers without overwriting child code.
2. Before the first remote runtime write, including staging new assets, create
   and verify the fresh labeled database backup required by
   [MRN backup policy](../stack/BACKUP_POLICY.md). Use the approved provider
   exception where applicable. Queued/pending or local-only backup output is
   insufficient. Preserve and checksum the current code, manifest, release
   pointer, and recovery receipt in verified private rollback storage.
3. Stage the exact artifact in a separate release location. Verify all hashes
   and publish its immutable assets to every serving origin before new HTML
   can reference them. Retain the old assets. Never rsync with deletion over
   the active asset directory or expose a partially uploaded release.
4. Atomically activate code and manifest as one consistent release, using a
   verified same-filesystem pointer/directory swap or an equivalent qualified
   provider primitive. Resolve the release path once per request so concurrent
   requests see a complete old or complete new dependency set. Qualify any
   changed-PHP OPcache handling without flushing unrelated application caches.
   `git checkout` and `rsync --delay-updates` alone are not release transactions.
   If the host cannot provide the required activation behavior, stop promotion
   and implement/qualify its adapter first.
5. Refresh affected HTML only, then run the public verification below. Record
   verification as pending until all required checks pass; switch back to the
   previous complete release if activation or verification fails.

Atomicity is per target and dependency boundary. Dev followed by Live is still
two deployments. Shared components that must change together need coordinated
activation or proven compatibility throughout the transition.

## Refresh only affected HTML

Derive the invalidation set from changed manifest handles and their consumers,
including locale/device/cache-key variants, shared layouts, and reusable
components. A globally loaded asset can legitimately affect all public HTML on
one site; that does not justify invalidating other cache classes or sites.
Record the exact URL/tag set before the write.

After activation, invalidate or regenerate only those HTML entries at the
origin page cache and applicable edge HTML cache. Use supported provider/plugin
APIs, then warm representative anonymous pages at a bounded rate. Preserve
cart/account/checkout cache exclusions and visitor-specific state. Refresh only
the release-owned manifest lookup if needed; leave unrelated data untouched.

Do not routinely run `wp cache flush`, `wp transient delete --all`, Redis
FLUSHDB/FLUSHALL, CDN "purge everything", or static-asset cache purges for a
CSS/JS release. Preserve unrelated page, object, transient, media, and asset
caches. If an adapter only supports a global purge, report scoped invalidation
as blocked instead of silently widening the operation. A hard browser refresh
or a request with an ad hoc cache-busting query is not the acceptance test.

Keep previous and newly published asset URLs available for cached HTML,
in-flight requests, supported open tabs/service workers, and rollback. Set
retention from the longest applicable HTML/client freshness and stale-serving
window plus the recovery policy. Garbage-collect only when references and the
rollback window have expired; if that lifetime cannot be bounded, retain the
objects. Never reuse a retired URL for different content.

## Verify the public release, not just the filesystem

For each affected route/template class and cache variant:

1. Fetch the normal public URL anonymously, without a bypass query. Check the
   first refreshed response and a subsequent warm-cache response. Parse HTML
   stylesheet/script/preload references and exercise browser-loaded modules or
   delayed assets; every expected release-owned URL must match the manifest.
   Fresh affected HTML must no longer reference superseded assets. Separately
   prove retained old HTML still has working immutable dependencies.
2. Fetch those **exact referenced URLs** through the public serving path.
   Require successful responses, correct CSS/JS content types, and full SHA-256
   equality with the manifest/artifact. Hash decoded response bytes, not gzip
   or Brotli transport bytes. An ETag, timestamp, HTTP 200, CDN HIT, or matching
   origin file alone is not checksum proof. Treat redirects, HTML challenge
   bodies, stale content, and hash mismatches as failures.
3. Compare origin and public-edge responses where available; include warm and
   returning-browser behavior. Record cache headers/status, timestamps, URLs,
   and tested edge location. A single edge observation does not prove all POPs.
   Disable unqualified CSS/JS rewriting for these assets, or require the
   optimizer to supply a separately versioned artifact and checksum manifest;
   unexplained transformed bytes cannot pass by ignoring their mismatch.
4. Verify the changed styling/behavior and relevant desktop/mobile,
   accessibility, and performance contracts. Include an unaffected route/cache
   sentinel and the invalidation log to prove the refresh stayed scoped.

Retain release/source IDs, artifact and manifest digests, backup receipt,
previous release/rollback pointer, affected HTML set, invalidation receipt,
observed asset URLs and checksums, and browser/runtime results with deployment
evidence. Origin-only success is not public release success.

## Rollback

Before a rollback write, pass the applicable fresh verified backup gate again.
Verify the saved artifact/archive checksum and restore the previous compatible
code **and manifest** atomically. Keep both releases' assets available. Refresh
only HTML affected by the switch and repeat the public URL/checksum and behavior
checks. Do not restore a commerce database merely to undo CSS/JS; preserve orders,
customers, and other new data. A failed verification remains a failed release
until recovery is verified and recorded.

## Regression case: Gloves stale CSS, September 30, 2026

The recorded investigation found matching CSS on Git, Dev, and production disk,
but the normal production `style.min.css?ver=1.0.470` URL repeatedly served an
older Cloudflare HIT. The affected rules included blog-card image containment
and hover behavior. This establishes stale public bytes, not lost source edits.

| Recorded response | Bytes | SHA-256 |
| --- | ---: | --- |
| Old public `?ver=1.0.470`, Cloudflare HIT | 327254 | `4faac19a3629ef15d6ab979a3d85108bfda3ebecad7ff27949ae0329a71ce180` |
| Current origin/Git and public `?ver=1.0.471` | 327279 | `d214e5437fb662d83281937368b961ea1bd20217e5014620c67478f8628ff64d` |

The old response advertised `max-age=31536000, immutable`. The incident repair
used a verified remote DB backup, the published `1.0.471` version change, and
refresh of 318 affected cached HTML files. Its receipt records seven public
page checks, matching new CSS checksums, and no Redis, Cloudflare-wide, or static
asset purge. The final new CSS response was a Cloudflare HIT with the correct
checksum. This manual version repair is historical evidence, not the automatic
build contract. The original observation was from ATL, not a fleet-wide POP audit.

Evidence inventory: Gloves operational reports
`reports/css-history-audit-2026-09-30/{findings,cache-verification}.json` and
`reports/asset-version-fix-2026-09-30/{completion,edge-final-verification}.json`.
These are retained in the Gloves site workspace; do not copy private backup
data into the shared repository.

Every asset deployment adapter must exercise this regression on an isolated
test runtime before adoption; it is not a request to replay stale CSS on Live:

| Case | Required result |
| --- | --- |
| Warm old HTML and old CSS, change a real source rule, rebuild without a manual version bump | A new output hash/URL is generated; unchanged outputs keep their URLs |
| Change source but leave the minified output or manifest stale | Build/release gate fails before activation |
| Origin is current but the public response has the old Gloves checksum | Public verification fails even for HTTP 200 / CDN HIT |
| Reuse an old URL for changed bytes, including only changing a query on a mutable file | Immutability gate fails; old URL must retain its old bytes |
| Stage incompletely or interrupt activation under concurrent requests | No new HTML references missing assets; requests see a complete release; old release stays usable |
| Activate with warm old HTML, then refresh the affected entries | Normal public HTML references the new manifest URLs, and their decoded checksums match |
| Observe an unrelated HTML/object-cache sentinel | It survives; invalidation records contain only affected HTML keys/tags |
| Roll back with warm caches after a failed verification | Previous code/manifest and public hashes are restored; both asset generations remain available; commerce data is unchanged |

Store the results with the adapter's qualification. This document defines the
regression gate; the current candidate's unit tests do not yet execute it.
