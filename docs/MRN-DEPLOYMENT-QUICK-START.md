# Deploy a website from your Git app

Use the Git app you already have. No GitHub CLI installation, terminal command,
or manual GitHub Actions run is required. You need your existing permission to
push commits and tags to the site repository.

Dev and Live have separate URLs and configuration. This releases child-theme
code; it does not copy databases, orders, forms, page content or uploaded media.

## Everyday Dev updates

Commit your work and push `main`. QA runs first. When it passes, the system builds
a versioned release, verifies a backup, deploys to Dev and checks the result.
A normal branch push never publishes Live. If QA fails, nothing is deployed.

A site with a protected Phase 2 preview may use a named phase branch instead.
Use the branch and URLs listed in that site's `DEPLOYMENT.md`. Ordinary feature
branches and pull requests do not deploy automatically.

## Choose a destination

A **tag** is a named marker on one commit. In your Git app's history, select the
commit, use **Create tag** (the label varies by app), and push that tag.
Use a new name each time:

| What you want | Tag name example | Source |
| --- | --- | --- |
| Dev only | `deploy-dev-20261004-01` | Current main, or a same-repository feature commit that includes current main |
| Live only | `deploy-live-20261004-01` | Current main |
| Dev and then Live | `deploy-both-20261004-01` | Current main |

Change the date/number for your release. Push **one release tag at a time**.
Both lightweight and annotated tags work. Do not rename, reuse, move or delete
a release tag. An old commit is not a rollback request.

1. Pull the latest work, resolve conflicts, commit and push your code.
2. For a release to Live, first get the change into `main` through the site's
   normal review process. For a feature test, merge current `main` into your
   feature branch so existing fixes stay included.
3. Select that pushed commit in history, create the appropriate tag and push it.
4. Wait for **Deploy site** to finish successfully. **MRN source push** only
   acknowledges receipt; its green check does not mean the site deployed.
5. Open the site's Dev/Live URL and check your change. The release evidence
   records the exact source SHA, backup, asset checks and runtime result.

GitHub Desktop, Sourcetree and GitKraken expose Git tags, though menu names vary.
[GitHub Desktop's tag guide](https://docs.github.com/en/desktop/managing-commits/managing-tags-in-github-desktop)
is one example. The release protocol is ordinary Git and is independent of the app.

## Keep environments consistent

Live fixes belong in `main`, which also triggers Dev. **Both** is the usual
choice when the same reviewed update should reach both environments: it builds
once and waits for Dev verification before releasing that identical artifact
to Live. A Live-only tag itself writes only Live; the preceding main push still
follows the site's normal automatic Dev policy.

When Dev contains a later phase, keep the protected phase branch and incorporate
Live fixes into it. Do not use Both to replace that preview. Doster is Dev-only
until launch; Live/Both requests are blocked.

If a release fails, read the failed **Deploy site** check or ask the deployment
owner. Source QA failures happen before a deployment. Runtime verification can
trigger rollback; use the recorded outcome rather than assuming success.
Do not upload files manually or flush all caches. Operators use the retained
release/rollback receipts for recovery.

The GitHub manual Dev/Live/Both workflow remains an optional operator fallback.
It runs from trusted `main`; manual Dev may select a same-repository
`source_branch`. The team does not need GitHub CLI to use the standard process.

Maintainers: existing sites must adopt both wrappers and the new immutable
shared revision. See [release-tag adoption](MRN-SITE-DEPLOYMENT-STANDARD.md#release-tag-adoption).
Updating a template alone does not update existing sites.
