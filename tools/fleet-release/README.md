# Accepted source distribution

`coordinator.py` is the source release worker. It polls accepted default refs in
MRN and the catalog's participating WordPress repositories, creates isolated
mirrors, reconciles versions and immutable release records, runs native local
qualification with all default packages and no WooCommerce, builds twice,
opens a promotion PR, waits for its normal Code gate, qualifies clean merged
source, publishes private/public distributions, and verifies their checksums.
It never calls MainWP, site installers, site deployment, or provider APIs.

`launcher.py` fetches the current accepted controller into its own clean mirror.
`install.py` installs an owner-local LaunchAgent at five minute intervals. It
runs while the owner is logged in and this Mac is awake. Its GitHub CLI account
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

CAPTCHA's default stays at 0.1.4 until genuine provider/controller/protection
cutover evidence exists. Retired capabilities remain excluded. Optional
components stay optional: their presence in a source inventory does not imply
installation. Native qualification uses synthetic content, blocks HTTP/mail,
and makes no claim about provider delivery, CAPTCHA assessment, client-site
adoption or a new deployment adapter's genuine provider recovery qualification.
Required diagnostics, native editor, API, whole-page AA, timing and CWV failures
block source publication. Site-specific contrast/content/provider failures and
holds remain rollout blockers in their own evidence.

Run the offline publication/recovery tests with:

```bash
python3 -m unittest discover -s tools/fleet-release/tests -v
node --test tools/fleet-release/tests/published-source.test.mjs
```
