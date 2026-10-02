# Deploy a website

Dev and Live have different URLs. Code goes to the chosen environment; database
content, orders, forms and uploads are not copied by this workflow.

## Everyday Dev updates

Commit your work and push the site's configured Dev branch:

```bash
git push origin main
```

Source QA must pass. The system then builds versioned CSS/JS, verifies a backup,
deploys atomically and checks public pages, asset checksums, browser rendering
and REST health. If QA fails, nothing is deployed. Look for **MRN source push**
followed by **Deploy site**. Old assets and rollback releases are retained.

For a Phase 2 site, push the named phase branch instead of main. The site's
`DEPLOYMENT.md` lists its branch and URLs. Other feature branches and pull
requests do not deploy automatically. A normal push never publishes Live.

## Choose Dev, Live or Both from the command line

Install GitHub CLI and sign in once with your authorized team GitHub account:
`gh auth login`. Run these commands from the site's Git repository.

First check the destination without writing to it:

```bash
gh workflow run site-deploy.yml --ref main -f target=live -f mode=preflight -f source_branch=main
```

After preflight succeeds, request the release:

```bash
gh workflow run site-deploy.yml --ref main -f target=live -f mode=deploy -f source_branch=main
```

- `target=dev`: Dev only. `source_branch` can be a same-repository feature branch.
- `target=live`: Live only, from reviewed main.
- `target=both`: Dev first, then Live, using one built artifact. Main only.

Keep `--ref main`: it selects the trusted workflow, not the source to deploy.
To see results without the GitHub website, use `gh run list --workflow site-deploy.yml`
and `gh run view RUN_ID`. `gh run watch RUN_ID --exit-status` waits for completion.
A dispatch acknowledgment means queued, not deployed.

Live fixes must be in main. If a separate Phase 2 branch is active, incorporate
those fixes there too. Both and Dev-from-main are blocked while that preview is
protected. Unqualified destinations remain blocked until the deployment owner
completes their backup, activation and rollback setup.

A Git push-only SSH key cannot run CLI release requests; an authorized account
with repository write/workflow access is required. No shared admin token is needed.

Coordinate shared Dev previews with the team. Do not upload files manually,
flush all caches or restore a database to undo a code release. Use the retained
rollback receipt. Live verification is blocking; broader Dev QA findings remain
visible testing feedback and are not production approval.

Maintainers: install both thin workflow files and arm only future pushes using
[the migration instructions](MRN-SITE-DEPLOYMENT-STANDARD.md#automatic-dev-adoption).
Updating the shared template does not update already-pinned site workflows.
