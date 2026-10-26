# Accepted source distribution

`coordinator.py` is the source release worker. It polls accepted default refs in
MRN, the accepted QA Engine and the catalog's participating WordPress repositories, creates isolated
mirrors, reconciles versions and immutable release records, runs native local
qualification with all default packages and no WooCommerce, separately packages
and qualifies eligible non-bootstrap plugins on a native fixture (including a
pinned, isolated MainWP Dashboard with no connected sites), builds twice,
opens a promotion PR, waits for its normal Code gate, qualifies clean merged
source, publishes private/public distributions, and verifies their checksums.
The QA Engine revision participates in the source/material fingerprint and is checked against the actual qualification toolchain. Engine merges therefore trigger fresh qualification automatically. Unpublished legacy jobs without that binding are superseded through the existing receipt-safe recovery path. It never calls MainWP, site installers, site deployment, or provider APIs.

The publisher uses the promotion gate's deployment-contract classifier for its
tracked source fingerprint. This includes Stack scripts/configuration and the
shared `tools/site-deploy/release-bootstrap.php` child loader. A change to that
loader requires fresh qualification even when the parent/plugin bytes are
unchanged. Unrelated repository documents remain outside the release material.

`launcher.py` fetches the current accepted controller into its own clean mirror.
`install.py` installs an owner-local LaunchAgent at five minute intervals. It
runs with Interactive launchd scheduling so the owner's native browser timing
qualification has the same CPU and I/O policy as an explicitly requested app
task. macOS still throttles Standard jobs. The worker remains serialized; timing
thresholds, PHP execution limits and diagnostic gates are unchanged. Existing
Standard or Background definitions must be preserved and reinstalled once; the
installer refuses to overwrite a different definition. See
`docs/releases/2026.10.26-fleet-qualification-scheduling.md` for the measured comparison.
The service runs while the owner is logged in and this Mac is awake. Its GitHub CLI account
and business 1Password SSH agent must remain available; authentication failure
blocks publication without credential fallback. This is not an always-on hosted
runner. Public MRN Actions run read-only contract/Code gates; licensed packages
and publishing credentials never enter a public workflow or its artifacts.

The owner-controlled JSON configuration follows `config.example.json`. Keep it
private and use absolute paths. `publish: false` exercises all local gates and
retains a qualified rehearsal for later continuation. `publish: true` enables
source PRs/publication after those gates. Installation and activation are a
one-time operator action, not another step after each source merge.

Completed phases have a durable checksum seal. Transient pre-publication
qualification failures retain their evidence and retry in a fresh attempt after
30 minutes. Unknown remote write outcomes are reconciled by the host journal;
unknown bytes block recovery. An interrupted known publication restores its
exact previous source before any new attempt. No failed release advances the
owner-local Fleet index. A retained public release may exist before the hosted
pointer changes; only a fully verified publication is selected as latest.

Qualified plugin ZIPs are retained in the private immutable local cache before
their promotion records merge, so a superseding release can recover those exact
inputs. Caching inputs does not advance the Fleet-ready index.

The host publisher accepts only the canonical source-owner/root/depot. It
requires one-time source-lease enrollment before replacing the bootstrap bundle.
Pause legacy source consumers and verify no installer is running before that
first guarded publication; resume only once all scanner/bootstrap entry points
hold the same shared lease. The enrollment receipt must identify the exact root
and is owner controlled. The worker does not pause/resume system cron or assert
that this administrative step happened. Provider administration needs its own
exact target authorization. Publication keeps a private exact filesystem
rollback archive; this does not replace a site's Updraft pre-write backup gate.

Only the ordinary parent theme, release lock and checksums are GitHub release
assets. Fleet and vendor/bootstrap ZIPs remain in the private source depot.
`published-fleet-source.mjs` binds the later explicit Fleet command to the
qualified catalog, registry and artifact map. Site backup, deployment and
installed verification remain that command's separately authorized gates.
The optional-plan builder uses the same verified source index; optional packages
remain private and never become automatic new installs. Retired packages remain
outside the eligible source map. Historical ZIP/version records stay immutable.

Pinned WordPress.org inputs can be downloaded only from the recorded official
URL and must match the accepted checksum and size. Licensed input updates stage
their ZIP as `releases/vendor-inputs/<sha256>.zip` as part of that source task;
the worker discovers it without a separate release request or credential lookup.

CAPTCHA 0.2.3 is accepted for source promotion under the owner's October 13
decision. The retained genuine Google and operator cutover evidence binds the
exact 0.2.2 artifact; the recorded successor comparison verifies unchanged
protection code and browser assets, with version/documentation/provenance changes
and additional exact-package no-WooCommerce qualification. This acceptance does
not claim a new genuine Google trial on 0.2.3 or qualify the newer native
Dashboard migration controller. Its connected prepare/resume/restore recovery
trial remains a separate gate before using that controller. See
`docs/releases/2026.10.13-captcha-source-acceptance.json` and the accompanying
release note. The worker uses CAPTCHA's committed,
locked release builder to include both generated browser asset pairs and their
manifest. Registry comparison uses those distributable bytes and keeps the same
immutable-version checks as other plugins. Retired capabilities remain excluded. Optional
components stay optional: their presence in a source inventory does not imply
installation. Native qualification uses synthetic content and a disposable MySQL
database on a private Unix socket with TCP disabled. It starts no global database
service, retains diagnostic logs, removes its private database on exit, blocks HTTP/mail,
and makes no claim about provider delivery, CAPTCHA assessment, client-site
adoption or a new deployment adapter's genuine provider recovery qualification.
Required diagnostics, native editor, API, whole-page AA, timing and CWV failures
block source publication. Site-specific contrast/content/provider failures and
holds remain rollout blockers in their own evidence.

Each native fixture retains `php-diagnostics.json` with counts and plugin-relative
early-translation origins. `php-diagnostics.log` and `translation-trace.log` stay
in the private evidence directory after the disposable runtime is removed.
WordPress early-translation warnings block qualification even when vendor code
filters their PHP error emission. No exception or log filter qualifies an input.
Automatic WordPress/vendor update checks are explicitly disabled in the
disposable offline fixture using their normal hooks. They are external provider
operations outside this qualification; the fixture supplies no fake update
responses and leaves all runtime diagnostic reporting enabled.

Run the offline publication/recovery tests with:

```bash
python3 -m unittest discover -s tools/fleet-release/tests -v
node --test tools/fleet-release/tests/published-source.test.mjs
```

## Form Success default

Form Success is part of the new-site plugin set and stays inactive until a site
configures its own lead forms. The coordinator checks inactive defaults, native
admin accessibility, real WPForms acceptance and rejection, one-use receipt
redemption, and metadata-only events without WooCommerce or external delivery.
Executable test fixtures remain in its source repository and are excluded from
plugin archives. The exact locked Git archive can be rebuilt when it is absent
from historical vendor inputs; different bytes or an unchanged-version source
change still fail. Provider tags, consent setup and site configuration remain
separate onboarding steps.
