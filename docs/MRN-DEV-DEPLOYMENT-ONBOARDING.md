# CloudPanel Dev deployment onboarding

Status: shared implementation and corrected bootstrap candidate
`2026.10.06-dev-enrollment-r2` are merged. The CloudPanel controller, source QA,
credentials and checksum-verified bootstrap scripts are installed. The isolated
pilot exists; full deployment qualification and push-only acceptance are still
pending. General new-site enrollment remains disabled until that proof passes.

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

## Copy-ready instructions for remote developers

```text
1. Accept the repository invitation if one arrives, then clone the supplied
   repository using your usual Git app. No GitHub CLI is needed.
2. Before starting work, Pull the latest main branch.
3. Make your child-theme code changes. Commit them with a short description.
4. Push main. This requests Dev deployment automatically.
5. Wait for the Deploy site result, then open the supplied Dev URL and check
   your change. The earlier MRN source push result alone is not deployment proof.
6. If QA or deployment fails, keep the failed run link and report it. Do not
   upload files directly to the server or repeatedly push the same change.

CSS and JavaScript cache busting is automatic. Edit the source files; the build
creates synchronized minified files and content-hashed asset URLs. You do not
need to change version numbers or clear every cache.

This new-site setup deploys child-theme code to Dev only. Live and Both become
available after a separate Live setup and qualification. A normal push never
publishes to Live. Database content, media, plugins and the shared parent theme
have separate deployment processes.
```

A developer can also use their Git app to create and push a fresh
`deploy-dev-YYYYMMDD-1` tag on a committed same-repository branch when deliberately
requesting Dev testing without pushing that work to `main`. Increment the final
number for another request; never move or reuse a deployment tag. The shared
controller resolves and checks the exact tagged commit before deployment.

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
2. Install Python 3.11+ with virtual-environment support, Git, Node 22, PHP, WP-CLI, the pinned MRN QA Engine and
   its documented source-analysis dependencies. The enrollment process invokes
   the installed QA Engine before publishing the initial child source. GitHub
   repeats source QA for the exact committed source before any deployment.
   On Ubuntu, check the package-manager plan before installing `python3-venv`:
   it may also upgrade the system Python packages. An isolated environment
   created with `--without-pip`, then bootstrapped from a pinned, checksum-verified
   PyPI pip wheel, avoids unrelated system upgrades. Keep source QA's Semgrep
   installation outside user-site packages because QA intentionally changes
   `HOME`. Make the isolated Node, Semgrep and PHPCS binaries available in the
   QA launcher's `PATH`; an interactive administrator's shell is not the cron
   environment.
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
6. Use a dedicated MRN-business 1Password vault for enrollment. Put only the
   approved enrollment GitHub token, the separate QA read token and generated
   new-site SSH identities there. Grant a dedicated service identity
   `read_items,write_items` on that vault only; 1Password service-account access
   is vault-scoped, not item-scoped. Do not install MRN's broader automation
   identity on the CloudPanel host. Keep its recovery token in the operator's
   Production Hub vault, outside the service's own scope.
   Copy the credential-reference example to
   `/etc/mrn/dev-enrollment-credentials.json` (root-owned, `0600`) with the actual
   vault UUID, item references and verified service account UUID. The broker
   checks the business account URL and service UUID before any secret read and
   rejects references outside the configured vault.
   Deliver the service token through private stdin to `systemd-creds encrypt
   --name=mrn-dev-enrollment - /etc/mrn/dev-enrollment-service.cred`, never in
   command arguments, logs or source. Keep that encrypted file root-owned and
   `0600`. The broker decrypts it inside its own process; WordPress, QA and the
   controller do not inherit the token. Back up the recovery token in 1Password;
   host-bound encryption is not a portable recovery copy. The isolated `op`
   binary must be on the credential command's PATH.
   A previously approved broker using `OP_SERVICE_ACCOUNT_TOKEN` and existing
   `op://Production Hub/` identity references remains supported; it must not be
   silently changed to new-site provisioning.
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
8. For new sites, enable `new_site_identity.mode: create-for-new-site` and
   configure the exact CloudPanel SSH host. After the controller validates the
   fresh site and private enrollment record, the broker creates one Ed25519
   identity in the dedicated vault, bound to domain, site owner and host.
   A retry reuses that exact identity. A failed lookup, duplicate title or
   mismatched binding stops enrollment and never generates a replacement.
   Existing identity-reference configurations retain their existing keys.
   After a verified database backup, enrollment adds only the public key while
   preserving existing authorized keys and creates private rollback storage.
   The private key is used in a `0600` temporary file for public-key derivation
   and in GitHub Dev-secret encryption, then the temporary file is removed.
   It is not saved in source, enrollment state or logs.
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

## Host installation status on 2026-10-06

Authenticated administration of `mrndev` succeeded using the existing MRN
business credential for `kyle`; no access rules or SSH identities were changed.
The following are installed outside WordPress roots:

- Clean detached MRN checkout at
  `b3eaf2b15c17566fc9f703e1074ab8cc027c363f` under
  `/opt/mrn-site-deploy/MRN`.
- Isolated Python environment with PyNaCl 1.6.0.
- Node 22.23.3 verified against the official distribution checksum.
- 1Password CLI 2.39.0 verified against the vendor's signing key.
- Root-private enrollment directories and a `0600` configuration with the
  verified tooling revision, SSH address, isolated QA launcher and approved
  `mrn-developer-collab` collaborator. General configuration remains disabled;
  a separate enabled configuration is restricted to the isolated pilot.
- SSH host keys captured through authenticated administrator access and stored
  in the root-private known-hosts file.

The QA Engine is installed at immutable commit
`c6ba43995e4bb37fb10feed35a71fca706f09dc5`, with isolated Semgrep 1.179.0 and
PHPCS/WPCS dependencies. The controller's real `source_qa` operation passed
against a generated child-theme fixture and thin workflows on the host. This
checks the installed source-QA path; it is not a WordPress runtime or GitHub
deployment qualification. The corrected bootstrap scripts have been published
and checked against their committed bytes; the existing root scanner cadence
is unchanged. The new isolated WordPress pilot has been provisioned at
`https://deployment-test.mrndev.io` under owner `deployment-test-stack`. HTTPS,
WordPress core checksums and a remotely verified Updraft database backup have
passed. Existing client sites have not been enrolled or modified.

The approved `MRN CloudPanel Dev Enrollment` GitHub token was created and
stored in the existing business `Production Hub - GitHub` item. It expires on
January 4, 2027. Existing QA credentials and their permissions were preserved.
A dedicated `MRN Dev Enrollment` vault now holds the enrollment API credentials;
its service identity was verified to see exactly one vault. The recovery token
is stored in Production Hub. The broader MRN AI Automation identity is not
installed on this host.

Rotate the enrollment GitHub token before its expiration, update both the
canonical Production Hub field and its dedicated enrollment copy, and verify
new-site API access. Rotate the dedicated service credential before its 90-day
expiration: save its replacement in the operator vault, encrypt it on the host,
update the configured service UUID and verify the broker. Do not rotate site
SSH keys or change existing GitHub deployment identities during this operation.

Pilot acceptance must still prove the first qualified GitHub deployment,
rollback exercise, exact deployed SHA, CSS/JS asset checksums, responsive browser
rendering, runtime QA, and the subsequent ordinary `main` push. A public HTTP
success or a verified backup alone is not deployment readiness. Record the run
URLs and final state here once qualification completes.

Bootstrap recovery uncovered and corrected two shared defects: protected JSON
settings now reach the site-owned WP process through stdin, and a package path
is resolved to its installed plugin slug before activation. Their regression
tests and shared contract/QA checks pass. The merged release lock's candidate
check and GitHub promotion reconciliation also pass.

Host prerequisites include valid PEM formatting for the existing reCAPTCHA
Enterprise private key. A secret export containing literal `\n` characters is
not a PEM file: verify it with OpenSSL and normalize the storage format while
preserving the original and the same key identity. Do not rotate credentials or
skip the required credential gate to work around this configuration error.

## GitHub API references

The controller uses GitHub's documented [user repository creation API](https://docs.github.com/en/rest/repos/repos#create-a-repository-for-the-authenticated-user),
[organization repository creation API](https://docs.github.com/en/rest/repos/repos#create-an-organization-repository),
[collaborator access and invitations](https://docs.github.com/en/rest/collaborators/collaborators),
[encrypted Actions secrets](https://docs.github.com/en/rest/actions/secrets),
and [workflow run evidence](https://docs.github.com/en/rest/actions/workflow-runs).
