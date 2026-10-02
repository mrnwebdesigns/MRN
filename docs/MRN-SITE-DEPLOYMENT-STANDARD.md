# Site Git, deployment, and server transitions

Status: shared automatic Dev workflow available for individually qualified environments.
Use `preflight` until the target has passed the onboarding evidence below and
`DEPLOY_READY=1` has been recorded. A provider connection or an installed workflow
does not qualify a target. Deployment must satisfy the
[CSS/JS release contract](MRN-ASSET-RELEASE-STANDARD.md), including atomic
activation, scoped HTML refresh, and public manifest/checksum verification.
Keep target-specific source, backup, rollback, browser and runtime receipts in
the site repository and operational record; incomplete adapters stay disabled.

## Daily workflow

Use one source repository per site, one task branch/worktree per change, and a
reviewed `main`. Run task-scoped MRN QA before committing. Feature branches may
deploy to Dev for testing before merge. Live and Both require reviewed `main`.
After merging, qualify the exact merged artifact on Dev before promoting that
same artifact to Live. A feature preview cannot qualify a different merge commit.
Keep `main` as the approved production line. A named, temporary phase/release
branch may collect a multi-week next phase and deploy to Dev while Live remains
on the previous phase. Merge task PRs into that phase branch until its launch is
approved; incorporate production fixes into it before final integration. Do not
maintain permanently divergent Dev and Live source branches.

Installing deployment tooling does not authorize a next-phase launch. When
unreleased work has already reached `main`, preserve the complete work and
reconcile the production baseline through a reviewed Git change before enabling
Live. Inspect existing push triggers first: restoring a production baseline
must not automatically replace the phase being tested on Dev. Preserve history
and verify the later phase can still merge completely after the correction.

In GitHub Actions choose **Deploy site**, select **dev**, **live**, or **both**,
and choose **preflight** (the default) or **deploy**. Run the trusted workflow
from `main`; the separate **Source branch** input may select a same-repository
feature branch for Dev. Live and Both reject every source branch except `main`.
The source is resolved once to an immutable SHA before the target jobs run.
`both` runs Dev first and starts Live only after Dev succeeds. Each target has
its own backup, credentials, identity checks, verification, and deployment
record. Failure in Live leaves Dev on the new commit; it is not an atomic
two-server transaction. Inspect the receipts before retrying.

Coordinate the shared Dev site with its current developer before replacing a
preview. Record its owner, branch, SHA, and agreed restore baseline. Serialization
prevents overlapping writes; it does not reserve a testing window. Use separate
preview environments for simultaneous testing.

Participating repositories use `push: branches: [main]` and `workflow_dispatch`
in a thin wrapper calling the shared `site-deploy.yml`. A main push selects
`github.sha` without resolving a newer branch tip: blocking source QA of that
commit -> immutable build -> verified artifact/preflight -> provider-appropriate
backup -> atomic Dev activation -> public/browser/REST verification. Failed,
skipped or cancelled source QA cannot reach the build or deployment.
PRs and arbitrary branch pushes never deploy. Live and Both remain manual.
Manual Dev still resolves a selected same-repository `source_branch` once and
uses that exact commit throughout the run. Read-only preflight remains available.

Manual selection is the production intent gate; configure additional GitHub
environment reviewers where the account plan supports them. Never assume a
private repository has reviewer protection just because its environment exists.

See the [short developer guide](MRN-DEPLOYMENT-QUICK-START.md) for daily steps.

## Ownership

Deploy only the site's child theme in this first version. WordPress core,
uploads, database content, provider configuration, parent themes, vendor
plugins, and shared MRN components remain outside that payload. Full and
partial Stack sites use the same site-code deployment method. Shared Stack
updates retain their release-lock/MainWP/Fleet process.

Both shared Stack assets and child-theme assets follow the
[CSS/JS release standard](MRN-ASSET-RELEASE-STANDARD.md): automatic versions
from final build output, synchronized source/minified files, and immutable URLs
that are never reused for changed content. Build once and deploy that exact
artifact; do not rely on manual version bumps or cache purges for correctness.

Content/media changes use tracked idempotent `wp eval-file` migration bundles
and the existing approval/backup gates. A code deployment must never copy the
Dev database onto Live. This is especially important for Gloves orders,
customers, form entries, and other records created after launch.

## Environment configuration

Create GitHub environments named `dev` and `live` in each site repository.
Restrict the workflow execution ref to `main`; Dev feature code is selected
through the separate source input, not by running an unreviewed workflow.
Keep credentials scoped to the target
environment, with distinct deployment identities wherever hosting supports it.
Store the canonical deploy credential in the MRN business 1Password account;
provision GitHub secrets through the approved secret path without displaying or
committing secret values. Do not copy an existing opaque GitHub secret into a
new identity or use personal-account credentials.

Required environment variables:

| Variable | Meaning |
| --- | --- |
| `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_USER` | Verified site-owner SSH destination |
| `DEPLOY_ROOT` | Absolute WordPress document root on that server |
| `DEPLOY_URL` | Exact WordPress home URL, including canonical host |
| `DEPLOY_TEMPLATE` | Expected active parent template slug |
| `DEPLOY_STATE_DIR` | Existing private writable directory outside the web root for receipts and code rollback archives; WP Engine uses its protected persistent path below |
| `DEPLOY_TRANSPORT` | `rsync`, or `git` for an existing clean deployment repository whose source subtree resolves to the active child theme |
| `DEPLOY_BASELINE_TREE` | Reviewed remote tree digest from preflight for first adoption |
| `DEPLOY_READY` | Set to `1` only after qualification and owner authorization |

Required environment secrets: `DEPLOY_SSH_PRIVATE_KEY` and
`DEPLOY_SSH_KNOWN_HOSTS`. Verify host keys through a trusted provider/operator
channel; a fresh unverified `ssh-keyscan` result is not a trust decision.
The repository also needs `MRN_QA_ENGINE_TOKEN` with read access to the pinned
private QA Engine. Host values are configuration, not workflow source.

The site wrapper pins the shared workflow and tooling to the same reviewed
40-character MRN commit. Its fixed source path and stylesheet slug are reviewed
in the site PR. Do not let dispatch inputs choose arbitrary filesystem paths.

## Automatic Dev adoption

Updating this repository or its template does **not** update consumers pinned to
older commits. Migrate each approved site separately:

1. Confirm its current Git source, active Dev preview, qualified target, backup
   route and `DEPLOY_READY=1`. Reconcile server-only changes first. Check pending
   runs and retain all previous receipts, private releases and asset generations.
2. Select a reviewed shared MRN commit containing `site-deploy.yml`,
   `site-source-qa.yml` and the ordering-aware controller. Render
   `tools/site-deploy/site-deploy.yml.template` using that same 40-character SHA
   for both `uses` and `tooling_ref`, plus the site's existing source path/slug.
3. Replace the existing wrapper at its existing workflow path. Keep provider
   configuration and credential identities in their existing `dev`/`live`
   environments. Preserve any explicit secret-name mappings. The repository QA
   Engine token must be readable by the source QA job, before environment access.
   Keep PR source checks as applicable; they do not trigger deployment. Retire
   duplicate deployment triggers; never reinstate the old rsync uploader.
4. For a protected Phase 2 Dev preview, set `dev_main_enabled: false` in the
   shared workflow call. Main pushes become a no-op, manual Dev-from-main and
   Both are blocked, and manual feature-branch Dev/approved main Live remain
   available. Remove this protection only at its separately approved launch.
5. Review and test the wrapper PR. Its merge/push to main is the first automatic
   Dev deployment and requires explicit pilot/adoption authorization plus the
   verified backup gate. It does not enable Live or configure a missing target.
6. Verify that a push alone passes source QA and build, preserves the same source
   SHA/artifact checksum in the activation receipt, verifies backups, serves the
   released CSS/JS URLs and matching bytes, renders correctly, and passes REST
   health. Record broader runtime findings separately under the existing Dev policy.

Dev and Live use independent `DEPLOY_URL`, host/root, site-owner credentials,
backup adapter and readiness settings. WordPress generates URLs for the target
environment; this workflow does not copy a database or replace Dev URLs in data.

### Ordering and recovery

Target jobs serialize on `site-code-<repository>-<environment>` without cancelling
an active write. Automatic runs recheck current main before backup/transfer and
before invoking the host controller. An obsolete push cannot silently substitute
a newer commit or deploy its older artifact.

Under the private host lock, `deployment-order.json` records the trusted caller
workflow, GitHub run number/attempt, run ID and source SHA. It is written only
after verified backup, before staging or activation. An older queued run or old
rerun cannot replace a newer attempted release, even after that newer run failed
and recovered. A retry of the same run must retain its SHA/run ID and increase
the attempt. An intentional new manual dispatch may select an older feature
branch on Dev; it is a new operator request, not an old queued run.

Automatic recovery uses its original run identity and exact current pointer.
Explicit local receipt-bound rollback remains available and preserves the
ordering record. Do not delete the record to unblock a stale run. Keep the caller
workflow path stable; changing its name/path or resetting its run sequence
requires explicit reconciliation of the stored workflow identity. Never rerun
pre-migration workflows: their old pins do not contain the new ordering guards.

## Gates and evidence

1. Resolve exact site/repository/environment; preserve unrelated Git work.
   Reconcile uncommitted site code and unmerged release branches before first
   activation. A prepared workflow does not resolve those branches.
2. Run read-only preflight. Verify SSH host key, WordPress home, active child
   stylesheet and parent template, physical theme path, write permissions,
   private rollback directory, and Updraft backup API/remote configuration.
3. Compare the full deployable tree with the reviewed adoption digest or last
   successful receipt. Unexpected server changes block overwrite. Review the
   diff, preserve necessary changes in Git, and approve a new baseline only
   after reconciliation.
4. Run MRN source QA for the exact commit. Complete task-relevant browser,
   accessibility, API, performance, and integration acceptance on that site's
   runtime; static source QA and HTTP smoke do not replace that evidence.
5. Immediately before a runtime write, create and verify a fresh labeled
   database-only Updraft backup sent to the configured remote storage, under
   normal retention. Keep labels within Updraft's 40-character limit. Do not
   accept merely queued/running output. Kinsta and other provider-specific
   exceptions require their separately approved adapter; this initial adapter
   fails closed when Updraft cannot satisfy the policy.
6. Preserve the current code/manifest and rollback pointer in private storage,
   stage and verify the immutable payload, then activate it atomically under
   the asset release contract. Refresh only affected cached HTML; preserve
   unrelated page/object/transient/static-asset caches. Verify installed hashes,
   exact public home/REST responses, and the normal public pages' released asset
   URLs and decoded file checksums. A redirect, challenge, or 401/403 is not
   success. Origin-only verification does not prove a public asset release.
7. Retain a receipt containing repository/commit, environment, target identity,
   backup nonce, old/new tree and manifest digests, rollback location, affected
   HTML/invalidation evidence, and public asset URL/checksum verification.
   Report partial/failing runs; never label transport alone as verified.

GitHub serializes each site's deployments with cancellation disabled. Disable
the old deployment workflow when adopting the new one so two paths cannot
write the same destination. Preserve old workflow code in Git history.

Prefer Git metadata outside the public document root. An existing theme-root
Git checkout may remain in place when it owns only the child theme and exact
public `.git/HEAD` and `.git/config` requests return 403/404 without redirects.
The helper verifies that protection before backup or deployment; it never reads
or displays those files' contents. A whole WordPress checkout is not a valid
site-code deployment target.

WP Engine's SSH Gateway discards files outside `/sites/<environment>` when a
session ends. For that provider only, use
`/sites/<environment>/_wpeprivate/mrn-site-deploy/<target>` for persistent private
rollback storage. The helper requires the matching WP Engine SSH host/account,
the physical protected path, private filesystem permissions, and a 403/404
without redirect for an existing provider-private file before a deployment.
It never reads that file's contents. This is the provider's documented
HTTP-blocked storage, not an arbitrary exception for folders under WordPress.
See [WP Engine SSH storage](https://wpengine.com/support/ssh-gateway/) and
[protected directories](https://wpengine.com/support/wp-engines-security-environment/).

## First setup and server changes

Deployment readiness belongs **before launch**, alongside DNS/TLS, backup,
forms/mail, cron, indexability, cache, and runtime acceptance. For already-live sites, complete it as post-launch onboarding.

When a site changes servers:

1. Pause deployment dispatches; record the old connection, last deployed SHA,
   runtime tree, database/content cutover plan, and rollback window.
2. Provision the new server's WordPress runtime, provider configuration,
   database/media, backup policy, least-privilege deploy account, and private
   rollback directory through the authorized migration process. This source
   deploy does not provision or migrate a whole site.
3. Update the existing `live` environment with the new verified origin, port,
   account, root, host keys, template, and canonical URL. Set `DEPLOY_READY=0`.
   Retire the old adoption digest/receipt only after preserving its evidence.
4. Test origin/preview access before DNS changes and reconcile the migrated
   theme with the source commit. Run preflight and the verified backup gate;
   perform a controlled code deployment through the approved launch workflow.
5. Cut over DNS and verify canonical URL/TLS/redirects, REST, critical journeys,
   indexability, forms/mail, and commerce behavior. Then authorize and record
   the new deployment baseline and set `DEPLOY_READY=1`.
6. Update Local Hub's separate Live and Dev metadata, Production Hub/runbooks,
   and MainWP exact-domain connection; fresh-sync and read back. A stale local
   `liveUrl` pointing to `mrndev.io` must never decide a production destination.
7. Revoke the retired server's deploy access after the rollback window. Keep
   only the agreed backup/code recovery evidence under the retention policy.

Routine rollback deploys a reviewed previous immutable commit through the same
backup gates. Restore its matching manifest and code atomically, retain both
asset generations, refresh only affected HTML, and verify public URLs/hashes
again. If a legacy partial transfer left drift, use the recorded private code
archive under an explicit recovery operation and verify hashes. Do not restore
a live commerce database merely to undo code.

## Activation record

Keep client-specific source reconciliation, target inventory, connection
readiness, and verification receipts in the site's private repository and
operational records. The standard itself does not authorize source merges or
site writes. Activation is complete only after the configured workflow has
passed preflight and an authorized verified deployment for that environment.

References: [GitHub deployments](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/control-deployments),
[environment protection availability](https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments),
[MRN concurrent development](MRN-CONCURRENT-DEVELOPMENT-POLICY.md),
[backup policy](../stack/BACKUP_POLICY.md).

## Formal MRN policy and pilot

The [MRN Git, Environment Deployment, and Asset Release Policy](https://docs.google.com/document/d/1xAnhiuhPxNvMItxaARB_sZxi1phByUBPS4rvKDumH0Q/edit)
is filed in the MRN Policies folder. Trilliant was the first Dev pilot. Its
Live environment remains disabled; Phase 2 must not be published by deployment
infrastructure work. The next authorized v1 targets are Gloves, Freedom House,
and SWC Care Partners, preserving their launched child-theme code.

The builder, asset URL adapter, and public HTTP checksum verifier are candidate
components. The CloudPanel Dev controller can activate only a previously
adopted, explicitly enabled child theme. Live writes require an explicit qualified provider adapter and an enabled
environment readiness switch.
Kinsta preflight uses its native API to verify environment identity and backup
access; production never falls back to Updraft.

The candidate caller now requires a successful build before either target job.
The build returns its immutable GitHub artifact ID and tar SHA-256; both target
jobs download that same artifact from the same run and independently verify it
before configuring site access. The expected digest comes from the build output,
not a sidecar downloaded beside the tar. Verification covers the source identity,
complete file inventory, asset manifest, content-addressed generation, URL maps,
and synchronized theme/static bytes. Unsafe paths, links, duplicate archive/JSON
entries, missing files, and checksum mismatches stop the run without extraction.
Read-only target preflight records this verified artifact and compares its built
theme inventory with the target; it does not rebuild source. Source-only CLI
preflight remains available for initial adoption inventory and cannot deploy.

A theme can declare exact-path stylesheet alternatives in `mrn-asset-routes.json`:
`{"schema":1,"stylesheet_routes":{"/":["style-home.css","style.css"]}}`.
The builder validates CSS sources and copies the declaration into the immutable
asset manifest. Undeclared paths still require `style.css`. HTML and browser
checks require an applied stylesheet from the declared alternatives and verify
its normal public URL and exact bytes; a preload alone does not qualify.

The Dev adapter snapshots the original child theme and changes only its public
`functions.php` to a stable loader. Original public assets remain byte-for-byte
unchanged. New code is stored privately; new static assets use immutable public
generation paths. Each PHP request captures one physical code directory and
matching asset manifest. Atomic `current.json` replacement selects the next
release; rollback selects the retained prior release through the same verified
backup gate. First-adoption compatibility failures restore the original public
bootstrap. No database restoration is part of code rollback.

The current cache adapter requires fresh proof that WordPress and CloudPanel
page caching are off and canonical first/warm Cloudflare responses are DYNAMIC
or BYPASS. In that configuration HTML needs no purge. Enabled/unknown HTML
caching blocks activation until a scoped provider adapter is qualified. Object
caches, transients, unrelated HTML, and old static generations are preserved.

The GitHub target checks browser-loaded dependency checksums at three viewport
sizes and runs site layout contracts. A failed browser/layout acceptance rolls
back with a new verified backup. Dev runs API, functional browser and accessibility
checks, with performance and Core Web Vitals probes explicitly disabled. Speed
testing runs only on Live. Unresolved Dev functional findings prevent release
approval, even when the Dev testing deployment itself succeeds. Live runtime QA is blocking, with automatic
code rollback on failure. Cross-run reuse of a Dev-qualified artifact remains
future work; v1 builds once per dispatch and Both uses the same artifact.

Update site wrappers from the complete reviewed template, not only the tooling
SHA. Trilliant's legacy uploader must remain disabled after first adoption,
because its rsync overwrite would destroy the stable loader. Preserve its
current Phase 2 branches and do not publish current main to Live.

Artifact transfer follows GitHub's documented
[reusable workflow outputs](https://docs.github.com/en/actions/how-tos/reuse-automations/reuse-workflows#using-outputs-from-a-reusable-workflow)
and [download by artifact ID](https://github.com/actions/download-artifact/tree/v4#download-artifacts-by-id).

## Live host adapters (v1)

`DEPLOY_HOST_PROVIDER` selects `nexcess`, `wpengine`, or `siteground` explicitly.
The default `cloudpanel` adapter remains restricted to Dev. Unknown providers,
identity mismatches, unverified storage, missing backups, or unqualified cache
configuration stop activation. `DEPLOY_READY=1` is set only after adoption,
public asset checks, a rollback exercise, and required runtime QA pass.

Private release paths are resolved on the destination. The stable bootstrap
resolves storage relative to WordPress so SSH/FPM chroot aliases do not select
different releases. WP Engine uses its account-bound `_wpeprivate` storage and
requires a public HTTP denial probe; other adapters keep storage outside the
WordPress root. State must belong to the site owner, have mode 0700, and share
the theme filesystem. Existing public asset files are never overwritten.

Globally loaded child assets affect the site's public HTML routes. The controller
records public WordPress permalinks, archives, taxonomy URLs, explicitly verified
pages, and (on Nexcess) existing cached pagination routes before activation.
Nexcess uses Cache Enabler's exact-page operation; WP Engine uses anchored host
and path Varnish purges; SiteGround uses its dynamic-cache URL API with child-path
purging disabled. SiteGround file caching requires separate qualification and
blocks this v1 adapter. No object-cache flush, transient deletion, static-asset
purge, or cache operation against another hostname is permitted.

An edge that retains affected HTML must serve the released asset references
within the bounded public verification window. The controller retries ordinary
public requests for up to 120 seconds; it never treats cache-busting queries or a
CAPTCHA/challenge response as release proof. A failed verification restores the
previous code selection and refreshes the same HTML scope. Prior immutable asset
generations stay available for cached pages and rollback.

This file describes the implementation contract, not a completed site rollout.
Record each environment's evidence separately. Unsupported cached routes or
additional HTML cache layers must be qualified before enabling that environment.
