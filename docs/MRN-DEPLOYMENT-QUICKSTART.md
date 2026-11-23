# MRN deployment front door

This is the starting point for every site deployment task. Supported enrollment
providers are WP Engine, SiteGround and Kinsta. First identify the site's actual
source and approved transport. A shared tooling release does not enroll a site,
rotate its credentials, enable Live or authorize a deployment.

## Choose the release

| Request | Route | Required evidence |
| --- | --- | --- |
| Dev or staging | Explicit enrolled non-production target | Exact source QA, verified backup, immutable activation, cache refresh and deployed QA |
| Live release | Promote the qualified exact artifact | Same artifact digest, explicit Live target, Live backup and deployed QA |
| Emergency Live hotfix | Manual Live / hotfix, exact current main SHA and short reason | Reviewed artifact and target, fresh verified backup, atomic activation, focused public/REST proof, retained rollback |
| Site without Git | Reviewed file list imported into a private release snapshot | Snapshot file hashes and real source commit, QA on that snapshot, the same artifact and target gates |

Dev and staging are separate configured targets, never inferred from a domain
substring. Live is always explicit. A hotfix goes straight to Live and does not
require a Dev deployment. It waives pre-production acceptance and full release QA,
records that exception, and retains the target, artifact, backup, public proof
and rollback gates. Never silently treat a regular release as a hotfix.

## Ordinary Git is enough

A PR is not a deployment prerequisite. Commit and push reviewed work through the
site's approved branch, then create and push one unique release tag using Git or
a Git GUI. The enrolled shared workflow runs QA Engine even when the developer
does not have it locally. Main pushes may qualify without deploying. Automatic
Dev requires that site's separately armed setting.

```sh
git tag deploy-dev-20261123-01
git push origin deploy-dev-20261123-01
```

For the qualified current main artifact, use `deploy-live-<unique-id>` or
`deploy-both-<unique-id>`. Both completes Dev before Live. Existing tag enrollment
must be installed before these tags trigger anything. Staging and emergency
hotfix currently use the site's Deploy site form. Select **staging / deploy** or
**live / hotfix**, respectively. The hotfix form requires the exact main SHA and
reason. Commit messages never execute deployment commands.

## One controller command for an enrolled v1 adapter

A protected runner fixes the adapter, target, provider credentials, private
journal and persistent signing key. A GitHub-hosted job may request a run, but
must not hold the only journal or receipt-signing key.

```sh
mrn-site-release status --adapter /protected/site/staging.py
mrn-site-release deploy --adapter /protected/site/staging.py --request request.json --archive release.tar
```

`deploy` runs only the remaining authorized phases. Failed or throttled proof
stops before migration. Repeating the command resumes proof without another
backup, upload or activation. Completed migration replay writes nothing. Explicit
recovery and rollback remain separate commands, and another owner's lock is
never cleared. Production additionally requires `--confirm-target` with its exact
origin. Use `status` for the single actionable next phase and receipt state.

For a non-Git site, the runner can version a reviewed package without changing
its source directory or requiring a client Git repository.

```sh
mrn-site-release snapshot --directory /reviewed/site-package --files reviewed-files.json --output /private/releases/site-snapshot
```

The explicit JSON file list names every included relative source file, including
tracked content migration declarations, scripts and media. The resulting clean
snapshot has a deterministic real Git commit and checksummed inventory. Run QA
Engine against that snapshot, build code from that SHA with `build_release.py`,
and use v1 `pack` against the same snapshot. This is source ingestion, not a
backup, deployment or permission to include the whole WordPress installation.
Secrets, dumps, symlinks and unsafe paths are rejected. Existing non-Git sites
still require a reviewed adapter and artifact/asset-loader qualification before
activation. Never replace their established transport just because this helper
exists.

## Enrollment checklist

Keep each target's provider identity, source route, exact paths, backup method,
cache-layer inventory, QA plan, artifact contract and rollback evidence in its
protected adapter configuration. Dev/staging and Live qualify independently.
Mark it deployable only after a harmless test artifact, backup, all relevant
cache purges, device proof and rollback have succeeded. Before qualification,
status must explain what is missing and the next command. Shared fixture tests
are not evidence that a customer environment is enrolled.

| Provider | Required protected access and cache capability |
| --- | --- |
| WP Engine | Existing site-owner SSH and pinned host key, verified remote Updraft backup, API username/password secrets and install UUID variable, matching API install/domain/environment, page plus CDN API purges |
| SiteGround | Existing site-owner SSH and pinned host key, verified remote Updraft backup, qualified Speed Optimizer exact-URL Dynamic Cache API, file cache disabled, separate qualified purge for any active CDN |
| Kinsta | API token, exact site/environment IDs, pinned connection identity, approved native transaction backup, origin and exact-URL edge invalidation, immutable versioned CDN assets |

WP Engine's API uses Basic authentication with an API username and password.
Store `DEPLOY_WPENGINE_API_USERNAME` and `DEPLOY_WPENGINE_API_PASSWORD` as environment
secrets, and `DEPLOY_WPENGINE_INSTALL_ID` as an environment variable. Do not paste
credentials into a chat, artifact or migration. A read-only API install lookup
must match the configured SSH install, exact primary domain and production vs
non-production environment before backup or upload. The shared adapter requests
`page` and `cdn` purges without an object-cache flush. HTTP 202 only records
acceptance. QA Engine must prove the release afterwards. Missing API access is a
preflight blocker, not a reason to fall back to Varnish-only success.

SiteGround CDN is a separate layer from Speed Optimizer Dynamic Cache. A CDN
that cannot be purged and qualified is an enrollment blocker. No shared
SiteGround CDN automation is claimed by this change. Third-party CDNs on any
provider need their own qualified purge route. Do not claim all-layer readiness
from an origin-only purge. Legacy provider code remains for backward
compatibility; no new enrollment is offered outside the three providers above.

QA Engine owns desktop/tablet/mobile release and asset proof. Deployment calls
it after provider cache invalidation. These are distinct emulated device profiles
and first/warm public responses, not a guarantee about every geographic edge or
physical device. Site-owned QA defines representative URLs, interactions, visual
expectations, accessibility and performance requirements. Deployment owns the
gates and receipts, not a second browser test engine.

Every content/media change uses `scripts/content/migrations.json`, idempotent
WordPress-aware scripts and declared immutable media. Missing, unsafe, unreferenced
or oversized files fail packaging/preflight. Site owners keep the content intent,
ACF/attachment lookups, environment allowlist and affected paths. Shared code owns
media limits, checksums, backup gating, replay protection and scoped refresh.

## Global update

Shared MRN release tooling now gives QA Engine ownership of device/asset proof,
adds WP Engine API delivery-cache invalidation, provides one-command resumable v1
execution, and accepts reviewed non-Git file snapshots. Existing Git tag and
explicit Live hotfix routes remain available. All site tasks start with this
checklist and the existing [deployment standard](MRN-SITE-DEPLOYMENT-STANDARD.md).
Adoption remains per environment, with credentials, target-specific qualification
and a verified rollback. No client site was changed by the shared tooling work.

Provider references:
[WP Engine purge API](https://developers.wpengine.com/docs/managed-hosting-platform/api/reference/operations/purgecache/),
[WP Engine API access](https://wpengine.com/support/enabling-wp-engine-api/),
[SiteGround CDN layers](https://www.siteground.com/academy/cdn-cache).
