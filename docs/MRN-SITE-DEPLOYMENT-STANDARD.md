# Site Git, deployment, and server transitions

Status: implementation candidate; site activation requires the onboarding evidence below.
Use the current helper for preflight only until it satisfies the
[CSS/JS release contract](MRN-ASSET-RELEASE-STANDARD.md), including atomic
activation, scoped HTML refresh, and public manifest/checksum verification.

## Daily workflow

Use one source repository per site, one task branch/worktree per change, and a
reviewed `main`. Run task-scoped MRN QA before committing. Merge reviewed work
before deployment; a merge is not a deployment. Do not maintain separate Dev
and Live source branches. Both environments receive an exact immutable commit.

In GitHub Actions choose **Deploy site**, select **dev**, **live**, or **both**,
and choose **preflight** (the default) or **deploy**. Run it from `main`.
`both` runs Dev first and starts Live only after Dev succeeds. Each target has
its own backup, credentials, identity checks, verification, and deployment
record. Failure in Live leaves Dev on the new commit; it is not an atomic
two-server transaction. Inspect the receipts before retrying.

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
Restrict deployment branches to `main`. Keep credentials scoped to the target
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
