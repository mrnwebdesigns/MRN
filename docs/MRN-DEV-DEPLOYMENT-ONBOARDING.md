# CloudPanel Dev deployment onboarding

Status: implementation candidate. Installation, MRN service credentials and a
real new-site acceptance run are required before enabling this on a host. This
document does not claim that the CloudPanel server has been updated.

## What the owner and developers do

1. Create the new WordPress site in CloudPanel as usual.
2. MRN's bootstrap sets up WordPress, then queues Dev deployment enrollment.
3. The existing bootstrap scanner advances enrollment on subsequent passes.
4. Hand the repository to developers only when enrollment reports `ready`.
5. Developers clone with their Git app, pull before editing, commit and push
   `main`. QA, versioned assets, verified backup, atomic deployment and runtime
   checks run through the shared deployment system. GitHub CLI is unnecessary.
   If GitHub sends a repository invitation, developers accept it once using
   their existing GitHub account before cloning.

The final record supplies the Dev URL, private GitHub repository, deployed SHA,
qualification run, ordinary-push run and recovery evidence. `DEPLOYMENT.md` in
that repository contains the short developer instructions. Default repository
name: `mrnwebdesigns/<dev-subdomain>-site`.

Live/Both remain disabled. Changing `WP_ENVIRONMENT_TYPE` does not enable Live,
change a deployment URL, migrate data or publish code. A later Live feature can
record a request for operator qualification when the environment changes; it
must verify the production identity, approved source and authorization separately.

## Ownership and boundaries

- `stack/scripts/site-bootstrap.sh` queues only a newly bootstrapped site after
  its required license gate. Existing bootstrap markers do not enroll old sites.
- `stack/scripts/bootstrap-dev-enrollment.sh` is a thin adapter with a private,
  operator-owned durable queue. It creates no GitHub workflows or deployment
  logic itself. Missing host configuration leaves existing bootstrap behavior
  unchanged and explicitly reports that enrollment is not configured.
- `tools/site-deploy/enroll_dev.py` is the central state machine. Run from a
  clean, immutable MRN checkout outside all WordPress roots. It reuses the
  existing workflows, source QA, asset builder, verified backup, atomic
  controller and rollback implementation.
- Operator configuration, queue and state are root-owned. Site-owned PHP,
  environment options and website files cannot provide GitHub credentials,
  override the MRN tooling revision, or choose another repository owner.
- `mrnwebdesigns` is currently a GitHub user account. Configure
  `github_owner: mrnwebdesigns`, `github_owner_type: User` and explicit
  `collaborators`. User-owned repositories use `/user/repos`; enrollment first
  verifies that the credential belongs to that exact owner. Organization mode
  remains supported with separately configured `team_slugs`, but is never
  inferred from a repository owner's name or a legacy `GITHUB_ORG` setting.
- Dev is restricted to an exact `https://<name>.mrndev.io` identity, CloudPanel
  `/home/<owner>/htdocs/<domain>` path, `development` environment and child theme.
  Nonstandard layouts need an explicit adapter; they are not inferred.
- New enrollment may create its own private repository or resume one bearing
  its exact enrollment identity. It refuses an unrelated existing repository.
  Existing sites/repositories use the separate deployment adoption runbook.
- This captures only the child theme. It does not copy plugins, the parent
  theme, uploads, database, credentials, hidden files or deployment output.
  The only generated theme change opts into the existing immutable asset adapter.
- The canonical GitHub credential is operator-side. Deployment credentials are
  stored as encrypted Dev environment secrets; the QA read credential is a
  repository secret. No account-wide GitHub credential is placed in WordPress.

## One-time host installation

Perform installation through the authorized infrastructure process. Do not
replace a site's currently installed workflow or rotate an existing identity.

1. Install a clean checkout of the reviewed MRN commit under
   `/opt/mrn-site-deploy/MRN`; pin `tooling_ref` to its complete 40-character SHA.
   The workflow and tooling must both exist at that same published revision.
2. Install Python 3.11+, Git, Node 22, PHP, WP-CLI, the pinned MRN QA Engine and
   its documented source-analysis dependencies. The enrollment process invokes
   the installed QA Engine before publishing the initial child source. GitHub
   repeats source QA for the exact committed source before any deployment.
3. Create the isolated Python environment:

   ```bash
   python3 -m venv /opt/mrn-site-deploy/venv
   /opt/mrn-site-deploy/venv/bin/pip install \
     -r /opt/mrn-site-deploy/MRN/tools/site-deploy/requirements-enrollment.txt
   ```

4. Copy `tools/site-deploy/enrollment.example.json` to
   `/etc/mrn/dev-enrollment.json`, owned by root, mode `0600`. Fill in the
   verified SSH hostname/port, pinned tooling revision, installed QA path and
   preapproved collaborator logins. `mrn-developer-collab` is the existing
   developer collaborator on the inspected Doster repository; verify the
   intended new-site access before adding it to this configuration. Use
   `team_slugs` only for a verified Organization owner. Keep `enabled: false` until these
   prerequisites and the first new-site target have been reviewed.
5. Place trusted provider/operator-verified SSH host keys in
   `/etc/mrn/dev-enrollment.known_hosts` (root-owned). Do not substitute a fresh
   unauthenticated `ssh-keyscan` result. The configured origin must support
   GitHub runner access under the correct site-owner identity.
6. Configure a noninteractive MRN-business 1Password service identity using the
   approved host secret delivery mechanism. It must access only the required
   items in the `Production Hub` vault. Copy the credential-reference example to
   `/etc/mrn/dev-enrollment-credentials.json` (root-owned, `0600`) and use actual
   approved item references and the verified service account UUID. The broker checks
   `op whoami` against that UUID and the business account URL before reading any
   secret. It requires noninteractive service authentication, clears conflicting
   Connect variables, and fixes the account to
   `mrnwebdesigns.1password.com` and never falls back to a personal account.
   A different approved central broker may be configured as an argument array;
   its private stdout contract is the same JSON object.
7. Supply an MRN owner-authorized credential authorized to create private
   repositories and manage their contents/workflows, Dev environment settings,
   encrypted secrets, Actions runs/artifacts, variables and approved developer access.
   The current User owner needs an owner-authenticated credential supported by
   `/user/repos`; an installation token is not a substitute for that identity.
   For an Organization owner, use its approved automation identity and the
   organization repository endpoint. Never reuse a developer's local CLI token
   as the unattended service credential or use an Actions job token.
   It must also read the pinned QA Engine source through the separate QA
   credential. Permissions must cover newly created repositories.
8. The SSH reference resolves the approved deployment identity for each new
   site owner. Missing identity blocks enrollment; no replacement key is
   generated. After a verified database backup, enrollment adds its public key
   while preserving existing authorized keys and creates private rollback
   storage. The private key is used only in a `0600` temporary file for public
   key derivation and in GitHub secret encryption, then the temporary file is
   removed. It is not saved in source, state or logs.
9. Publish the reviewed `site-bootstrap.sh`, `bootstrap-new-sites.sh` and
   `bootstrap-dev-enrollment.sh` together through the existing checksum-verified
   bootstrap-contract publication process. This does not install the central
   controller or provide its credentials: those are separate steps above.
10. Enable configuration for an approved new-site pilot. Create/bootstrap that
    site and monitor `/var/lib/mrn-site-enrollment/<binding>/state.json` plus
    GitHub run links. Verify a real qualification and ordinary-push receipt
    before enabling enrollment as the normal new-site handoff process.

The bootstrap adapter defaults to `/opt/mrn-site-deploy/venv/bin/python3` and
`/opt/mrn-site-deploy/MRN/tools/site-deploy/enroll_dev.py`. Operator overrides are
`MRN_DEV_ENROLLMENT_CONFIG`, `MRN_DEV_ENROLLMENT_RUNNER`,
`MRN_DEV_ENROLLMENT_PYTHON`, and `MRN_DEV_ENROLLMENT_QUEUE`. These are service/cron
settings, never WordPress options or site-supplied command strings. The standard
scanner remains on its existing cadence; no extra website cron is installed.

## Stages and evidence

The durable queue records new-site intent before depending on controller or
credential availability. Each scanner pass advances at most one enrollment
stage. Depending on the scanner cadence and QA duration, enrollment takes
multiple passes; WordPress can be bootstrapped while deployment is still pending.

1. Capture a bounded child snapshot; run source MRN QA and preserve source bytes.
2. Create/resume the exact enrollment-owned private repository; publish the
   source and two thin workflows, pinned to the reviewed shared revision.
3. Refuse changed source/site baseline; take and verify the shared Updraft
   database backup; prepare the existing deployment identity/private storage;
   configure the Dev environment with `DEPLOY_READY=0`.
4. Wait until the installation's source signal is registered and complete.
   Dispatch one qualification run and durably record the dispatch intent first.
5. Qualification permits only manual Dev/main, the exact environment-authorized
   source SHA and successful QA for that SHA. It adopts the exact existing tree,
   builds once, verifies backups, activates, exercises rollback and reactivates.
   Browser/REST/accessibility/performance acceptance is strict for this first
   qualification, even though routine Dev runtime findings remain advisory.
6. Verify GitHub job outcomes and downloaded artifact receipts. A green signal,
   successful upload, advisory runtime failure or wrong-site receipt is not ready.
   The separate runtime-result receipt records the actual QA outcome even when
   GitHub's advisory step is allowed to continue.
7. Clear the one-commit qualification authorization, enable Dev readiness and
   set automatic/release cutoffs later than the known installation signal.
8. Publish a docs-only ordinary `main` commit using the same child-theme bytes.
   Verify that push actually triggers the shared pipeline; bind its exact SHA,
   source signal sequence, backups and browser/asset evidence.
9. Grant the configured collaborators (or Organization teams) push access,
   verify accepted access, and report `ready`. A pending invitation leaves
   `access_pending: true` at `grant-access`; scanner retries poll it without
   repeatedly sending invitations. No Live environment or Live readiness is created.

`qualification.json`, `automatic-push.json`, `source-*.qa.md`, the original source
snapshot and the current stage stay in private operator storage. GitHub retains
its usual detailed source/build/deployment/runtime artifacts. Secret values are
never included in the enrollment state. Website/QA subprocesses do not inherit service tokens.

## Failures and safe retries

A missing credential, wrong host, incomplete backup or failed QA leaves deployment
pending. The next scanner pass can retry safe setup stages. It never reruns the
WordPress bootstrap simply because enrollment is unfinished. Unknown GitHub
publish outcomes are reconciled against the exact recorded commit before retry.
An uncertain dispatch is looked up by its recorded correlation ID and is never
blindly repeated. A failed qualification, changed source, previously adopted
target or ambiguous run requires operator inspection of retained receipts.

Do not reset/delete state, move tags, clear the deployment pointer, or set
`DEPLOY_READY=1` to bypass a failure. After first adoption, a failed qualification
must be handled through the existing recovery workflow; there is no automatic
re-adoption of a changed runtime. Correct the underlying issue and deliberately
reconcile/requalify the exact target before resuming. A pilot failure after
arming Dev is not `ready`; pause automatic requests through the existing cutoff
control while diagnosing. No developer access is granted until proof passes.

Rerunning `--enqueue` does not replace an existing state. `--resume` alone never
creates a new enrollment. Existing sites are not silently enrolled when this
feature is installed. Neither template updates nor a new shared commit upgrades
existing site consumers; they retain their pinned revisions until explicitly
migrated using `MRN-SITE-DEPLOYMENT-STANDARD.md`.

The original uninstalled candidate used an `organization` field and required
teams. Replace that field with `github_owner` and `github_owner_type`, and
configure `collaborators` for MRN's current User owner. Old configuration is
rejected explicitly; no existing host configuration is rewritten automatically.

## Host readiness checked on 2026-10-06

Read-only inspection confirmed that CloudPanel is reachable through the
configured MRN SSH identities. Python 3.12, Git, PHP, Composer and WP-CLI are
available. The enrollment controller, QA installation and `/etc/mrn` configuration
are absent; Node and 1Password CLI were not found in the manager's PATH.
The configured `kyle` administrator has general sudo rights that require
authentication. The operations and Stack-manager identities have limited
passwordless commands; those permissions must not be repurposed to install
sudo rules or other access bypasses.

Production Hub's approved MRN-business lookup found `MRN_QA_ENGINE_TOKEN` but
reported `GITHUB_TOKEN` missing from `Production Hub - GitHub`. Provide the
approved owner credential through that secure account, plus the unattended
1Password service identity and target deployment identity, before enrollment.
Do not paste credential values into the chat or repository. No host files,
repositories or client runtimes were changed during this inspection.

An existing `nethues-sandbox.mrndev.io` install is already bootstrapped and is
not automatically selected as this task's pilot. A fresh approved Dev target
is still required for the bootstrap acceptance test.

## GitHub API references

The controller uses GitHub's documented [user repository creation API](https://docs.github.com/en/rest/repos/repos#create-a-repository-for-the-authenticated-user),
[organization repository creation API](https://docs.github.com/en/rest/repos/repos#create-an-organization-repository),
[collaborator access and invitations](https://docs.github.com/en/rest/collaborators/collaborators),
[encrypted Actions secrets](https://docs.github.com/en/rest/actions/secrets),
and [workflow run evidence](https://docs.github.com/en/rest/actions/workflow-runs).
