# Deploy a website

This guide applies after a site's workflow and destination have been qualified.
Dev and Live are separate websites, with separate URLs, credentials and backups.

| What you want | What to do |
| --- | --- |
| Update Dev with reviewed work | Merge or push the change to `main`. Dev deploys automatically after source QA passes. |
| Test a feature on Dev before merging | Push your feature branch. In GitHub, open **Actions → Deploy site → Run workflow**. Keep the workflow on `main`, choose **dev**, enter your branch in **source_branch**, and choose **preflight**. After it passes, repeat with **deploy**. |
| Update Live only | Run the same workflow from `main`; choose **live**, **source_branch=main**, and **preflight**, then **deploy**. |
| Update Dev, then Live | Run it manually with **both**, **source_branch=main**, and **preflight**, then **deploy**. The same built artifact goes to Dev first. Live starts only if Dev succeeds. |

A feature-branch push or pull request does not deploy. A push to `main` never
deploys Live. If a site is previewing a separate Phase 2 branch, its maintainer
can protect that preview with `dev_main_enabled: false`; use manual feature-branch
Dev deployment until the approved launch.

The workflow checks the exact code, builds versioned CSS/JS, verifies the correct
backup for that server, switches releases atomically and checks the public pages,
asset checksums, browser rendering and REST API. Old assets and rollback releases
are retained. It deploys child-theme code, not database content or uploads.

Look for the **Deploy site** run in Actions. Open its source QA, deployment,
browser and runtime reports. A failed source QA run cannot deploy. Dev's broader
quality findings remain visible testing feedback; they are not Live approval.
Live runtime acceptance is blocking. If a run says it was superseded, use the
newest run; do not rerun an old workflow to replace a newer release.

Coordinate use of shared Dev before replacing another developer's preview.
**Both** is sequential: if Live fails, inspect both receipts before retrying.
Do not copy files manually, flush all caches or restore a database to roll back
a code change. Ask the deployment owner to use the retained rollback receipt.

Older sites need an explicit workflow migration. Installing a newer template
does not update workflows already pinned in site repositories. Maintainers:
follow [migration and setup](MRN-SITE-DEPLOYMENT-STANDARD.md#automatic-dev-adoption).
