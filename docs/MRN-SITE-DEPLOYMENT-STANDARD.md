# Site Git, deployment, and server transitions

Status: implementation candidate; site activation requires the onboarding evidence below.
Use the current helper for preflight only until it satisfies the
[CSS/JS release contract](MRN-ASSET-RELEASE-STANDARD.md), including atomic
activation, scoped HTML refresh, and public manifest/checksum verification.

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

The first implementation intentionally has no push-triggered deployment.
PR/push QA continues independently. Manual selection is the production intent
gate; configure additional GitHub environment reviewers where the account plan
supports them. Never assume a private repository has reviewer protection just
because its environment exists.

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
is filed in the MRN Policies folder. Trilliant is the first pilot; do not change
Gloves deployment configuration while its separate work continues.

The builder, asset URL adapter, and public HTTP checksum verifier are candidate
components. The CloudPanel Dev controller can activate only a previously
adopted, explicitly enabled child theme. Live writes remain blocked in code.
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
back with a new verified backup. Full MRN runtime QA is retained as feedback for
Dev testing; unresolved findings prevent release approval, even when the Dev
testing deployment itself succeeds. Live remains disabled. Cross-run reuse of
a Dev-qualified artifact and provider-specific Live activation are still pending.

Update site wrappers from the complete reviewed template, not only the tooling
SHA. Trilliant's legacy uploader must remain disabled after first adoption,
because its rsync overwrite would destroy the stable loader. Preserve its
current Phase 2 branches and do not publish current main to Live.

Artifact transfer follows GitHub's documented
[reusable workflow outputs](https://docs.github.com/en/actions/how-tos/reuse-automations/reuse-workflows#using-outputs-from-a-reusable-workflow)
and [download by artifact ID](https://github.com/actions/download-artifact/tree/v4#download-artifacts-by-id).
