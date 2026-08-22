# MRN Agent Operating Context

## Canonical Purpose
This document is the vendor-neutral, version-controlled baseline for MRN-wide agent behavior.

Use this for cross-project guidance before applying project assumptions.

## Scope and resolution order
When entering an MRN project, apply information in this order:

1. Global MRN operating context (this document).
2. `AGENTS.md` in the current project.
3. Project `README.md` and project documentation.
4. Project-specific memory/handoff/changelog artifacts.
5. For Local Hub sites, `<site_root>/.mrn-site.json`.
6. Current local configuration/tool state (for example Codex config, SSH config, MCP config).
7. Conversation memory as supplemental context only.

Current durable configuration and command-line evidence should override stale remembered values.

## 1) MRN LOCAL HUB / THEHUB

- Name: MRN Local Hub (informal: TheHub).
- Canonical repository: `/Users/khofmeyer/Development/MRN-local-hub`.
- Local site roots: `/Users/khofmeyer/Development/MRN-sites/{slug}`.
- Canonical per-site manifest: `<site_root>/.mrn-site.json`.
- Runtime instance name: `mrn-openlitespeed`.
- Friendly URL: `https://thehub.localhost`.
- CLI/wrapper: `mrn local-hub`.

Intent:
- Run and manage local WP sites in a local runtime.
- Provide pull/QA/deploy support for site repos under `MRN-sites`.
- Host metadata and runtime configuration outside site repos.

Non-goal:
- Do not confuse Local Hub with Production Hub.

### Pulled client site layout

- A pulled client site lives at `/Users/khofmeyer/Development/MRN-sites/{site-slug}/`.
- `public/` inside that folder is the WordPress document root. Site metadata, backups, dumps, and logs live beside `public/`, not inside it.
- Do client-site work in the local runtime under `public/`, served by the site's `.localhost` URL.
- Develop site-specific child themes and per-site plugins in their real runtime locations under `public/wp-content/themes/`, `plugins/`, or `mu-plugins/`.
- Keep the shared MRN stack independent from client sites. The base theme and the child-theme template belong to the stack, not to a site repository.
- Do not git-track shared MRN plugins inside a client-site repository. Distribute shared plugins and base-theme updates through MainWP unless the owner explicitly asks for site-local debugging.
- Child themes are site-specific. Do not propagate child-theme changes to other sites unless the owner explicitly requests a cross-site migration.

## 2) PRODUCTION HUB

- Name: Production Hub.
- Role: infrastructure/provider/access discovery and operational coordination system.
- Integration in Codex: configured as a global MCP server named `production-hub`.
- Canonical behavior: resolve authoritative provider infrastructure and runbook data, then perform controlled 1Password-backed credential discovery where supported.
- Scope: separate from runtime/LocalHub site operations.

Use Production Hub when environment-level facts are needed instead of static assumptions.

Locations:
- Repository: `/Users/khofmeyer/LocalSites_production-hub` (symlink to `/Users/khofmeyer/Local Sites/_production-hub`).
- MCP server: `<repo>/mcp-server/server.mjs`, run with `node` and `PRODUCTION_HUB_DIR` set to the repository path. Both Codex and Claude Code use this same implementation; do not create a second one.
- Provider and key aliases: `<repo>/manifests/credential-model.json`.
- 1Password vault/item mapping: `<repo>/manifests/onepassword-map.json` (for example Cloudflare maps to the `Production Hub - Cloudflare` item).
- Useful scripts: `<repo>/scripts/onepassword_sync.py`, `<repo>/scripts/bootstrap-1password-production-hub.sh`, and `/Users/khofmeyer/Development/mrn-runcloud-stack/scripts/phase-1/sync-cloudflare-from-production-hub.sh`.

Available operations include hub overview, provider templates, import reports, missing-key summaries, and secure provider value lookup. Credential-retrieval rules are in section 3; do not restate them here.

If `op` is not authenticated in the MCP server environment, have the caller sign in there or supply `OP_SERVICE_ACCOUNT_TOKEN`. Do not rotate secrets and do not modify the encrypted vault format unless the owner explicitly requests it.

## 3) 1Password policy

- 1Password is an authoritative credential source.
- MRN SSH is normally authenticated through the MRN/business 1Password account -> 1Password SSH Agent -> SSH client -> target host.
- Private SSH keys are normally stored and managed in the MRN/business 1Password account and exposed to SSH through the 1Password SSH Agent, not loose private-key files in project repositories.
- If an expected SSH identity is not available through the MRN/business account and SSH Agent, stop and report the missing credential rather than falling back to `thehofmeyers` or creating replacement key files.
- For all MRN business, client, development, infrastructure, hosting, deployment, DNS, provider, API, SSH, RunCloud, DigitalOcean, Cloudflare, MainWP, Production Hub, WordPress, and related operations, use only the MRN/business 1Password account.
- The personal 1Password account `thehofmeyers` is explicitly out of scope for MRN operations and must never be searched or used to satisfy an MRN credential need.
- If multiple 1Password accounts are available, explicitly target the MRN/business account before credential discovery.
- Never fall back to `thehofmeyers`. If an MRN credential is missing from the MRN/business account, stop and report the missing credential as a configuration issue.
- Production Hub and any 1Password-backed MRN credential-resolution tooling must respect this same account boundary.
- Never copy, migrate, synchronize, import, or expose credentials from `thehofmeyers` in order to complete an MRN operation.
- For infrastructure/provider access, Production Hub lookup is preferred where supported.
- When MRN tooling invokes `op`, it must set `OP_ACCOUNT=mrnwebdesigns.1password.com` explicitly, or use an MRN launcher that exports that value, so `op` cannot implicitly select the personal account.
- Production Hub continues to use the MRN account mapping it already owns.
- The SSH Agent is a separate authentication path and follows the same MRN account-boundary policy, but it is not controlled by `OP_ACCOUNT`.
- Never write passwords, API keys, tokens, private keys, recovery codes, or secret values into:
  - This document
  - `AGENTS.md`
  - project docs
  - or agent memory
- Document retrieval methods only:
  - Use `get_provider_values_secure` on `production-hub` with `auth_method` set to `1password` and `reveal_values: false`.
  - Retrieve only aliases/keys/placeholders and request values through the approved secret path.
- Routine reporting and summaries must not reveal secret values.

## 4) Infrastructure conventions

### SSH
- When SSH is required, use alias- and host-aware routing from durable local SSH configuration and check `~/.ssh/config` aliases before inventing a new connection command manually.
- Treat TheHub / `.mrn-site.json` as connection-metadata discovery/failsafe sources for hostname, user, port, provider, environment identity, and deployment method when authoritative details are unclear.
- Prefer documented private/Tailscale SSH paths where configured; do not reopen public SSH merely because a public-IP attempt fails.
- SSH is an escalation / break-glass path, not the default WordPress management path.
- Prefer provider/site-specific hostnames and aliases over hard-coded legacy values.
- Prefer dynamic resolution (Production Hub, Local Hub manifests, and local SSH config) for target-specific routing.

MRN SSH identities are normally served by the 1Password SSH agent rather than by
key files on disk:

- macOS agent socket: `/Users/khofmeyer/Library/Group Containers/2BUA8C4S2C.com.1password/t/agent.sock`. Use it when the shell's default `SSH_AUTH_SOCK` exposes no identities.
- List identities with `SSH_AUTH_SOCK="<socket>" ssh-add -l` before concluding a key is missing.
- The first use of an identity, or any use needing an approval or fingerprint prompt, requires an interactive/TTY session. Do not use `BatchMode=yes` for it — a non-interactive signing test can fail before the 1Password prompt is ever shown.
- Account-boundary rules for these credentials are in section 3.

### Tailscale
- Use project/host-specific tailscale aliases where defined in local SSH config and associated inventory.
- Treat tailscale endpoints as environment endpoints, not production credentials.

### RunCloud
- Source for server/provisioning workflows is the MRN RunCloud stack repository and scripts.
- RunCloud changes should follow that repo’s command and gating model.

### DigitalOcean
- Treat as RunCloud-supported infrastructure dependency where stack manifests/scripts require it.
- Resolve account/project-specific live state dynamically before applying host assumptions.

### Cloudflare

- MRN agents may legitimately have access to multiple Cloudflare accounts. Every
  visible account is part of infrastructure MRN manages; broad visibility is
  intentional and is not a misconfiguration.
- This is deliberately different from the 1Password boundary in section 3. Do not
  apply the 1Password single-account rule to Cloudflare.
- Before any Cloudflare operation, identify the correct account and zone for the
  specific client/site being worked on. Resolve them from current state —
  Production Hub, site manifests, or a live account/zone lookup — not from memory
  or from a previous task in the same session.
- Verify the resolved zone belongs to the intended account before acting.
- Never mutate an account or zone merely because it is visible. Visibility is not
  authorization, and a near-miss on account selection is a cross-client incident.
- Treat DNS, proxy status, WAF, and certificate changes as production mutations
  subject to the applicable confirmation requirements in section 7.
- Read-only lookups across accounts are acceptable for discovery and verification.
- A Cloudflare-proxied hostname resolves to edge addresses in public DNS. Resolve
  an origin from the zone record, not from `dig`.

### CloudPanel / `mrndev.io`
- Treat `mrndev.io` as review/development/staging infrastructure unless explicitly marked production.
- Keep public `*.mrndev.io` hostnames in the production networking posture; do not hard-code SSH assumptions for these hosts.
- Resolve ssh access details dynamically via current inventory/config, then validate reachability before privileged actions.

SSH access convention for CloudPanel `mrndev.io` sites:

- These sites share the CloudPanel server at origin `167.99.54.77`.
- Public site hostnames such as `{site}.mrndev.io` stay Cloudflare-proxied. Cloudflare does not proxy SSH on port 22, so SSH will not work through a proxied hostname.
- For each site that needs SSH access, create a Cloudflare **DNS-only** A record `ssh.{site}.mrndev.io` pointing at the origin. For `quantumbloom.mrndev.io` the record name is `ssh.quantumbloom`, target `167.99.54.77`, proxy off.
- Prefer `ssh.{site}.mrndev.io` over `{site}-ssh.mrndev.io`. The latter can fall through a proxied wildcard and resolve to Cloudflare edge addresses.
- Verify with `dig +short @1.1.1.1 ssh.{site}.mrndev.io A` and `nc -vz ssh.{site}.mrndev.io 22` before handing the host to anyone.
- Connect as `ssh <cloudpanel-ssh-user>@ssh.{site}.mrndev.io`. Confirm the CloudPanel SSH user exists first; users are per-site, for example `freedomhouse-stack`.

### SiteGround

- SiteGround SSH identities live in the 1Password SSH agent; see the agent notes under SSH above.
- The shared public-key stub is `/Users/khofmeyer/.ssh/siteground.pub` and should match the 1Password `Siteground` identity.
- SiteGround SSH uses a non-default port. Verification shape:
  `SSH_AUTH_SOCK="<1password-agent-socket>" ssh -tt -p 18765 -i /Users/khofmeyer/.ssh/siteground.pub -o IdentitiesOnly=yes -o PreferredAuthentications=publickey -o PasswordAuthentication=no <user>@<host>`
- Use `-tt` rather than `BatchMode=yes` for first use, so the 1Password approval prompt can appear.
- Host and user values are per-site and change between customers. Resolve them from Production Hub or the site's own records rather than reusing a remembered pair.

### MainWP
- MainWP is MRN's preferred and authoritative operational interface for WordPress site management whenever the required operation can safely be performed through MainWP or approved MRN tooling built around MainWP.
- Use MainWP for as much as reasonably and safely possible, including supported updates, backups, update inventory/discovery, site status/readiness checks, maintenance orchestration, post-update verification, and supported MRN shared plugin/theme/stack component deployments.
- Do not default to SSH simply because SSH access exists.
- MainWP-first does not override backup-before-write, QA, environment-separation, or production-authorization requirements.
- Respect repo-level deployment scope and MRN deployment policies.

### Shared deployment tooling
- Use shared tooling and scripts defined in canonical MRN stack/repo workflows and deployment contracts.
- Apply per-project/site-specific edits only to approved scopes.

### Site deployment standard

- GitHub Actions is the preferred deployment path for site-owned code wherever a site repository and deploy SSH exist. Examples: Morgan Development deploys by Actions plus rsync to SiteGround; Freedom House updates server-side deploy repositories by Actions.
- Deploy only site-owned surfaces — a child or active theme, a site-owned standalone theme, or a site-specific plugin.
- Never deploy WordPress core, uploads/media, Local Hub metadata, logs, dumps, backups, cache output, shared MRN plugins, or vendor/pro plugin runtime copies unless the owner asks for that exact target.
- The backup gate in section 7 applies to shared development/review, staging,
  and production WordPress runtimes. LOCAL runtimes follow the Local
  Development Exception in `stack/BACKUP_POLICY.md` and do not require a
  remote backup before code writes.
- For covered WordPress runtimes, the concrete mechanism is a remote UpdraftPlus database-only backup, preferably by WP-CLI:
  `wp updraftplus backup --include-files= --send-to-cloud=true --label="<pre-deploy label>"`.
- If WP-CLI, UpdraftPlus, or that backup command is unavailable for a covered
  runtime, stop and report the blocker. Do not deploy without the database
  backup.
- Flush WordPress cache and transients after deploying, when WP-CLI is available.
- Non-WordPress MRN services have their own documented release procedures and backup mechanisms; follow the runbook in the service's own repository.

## 5) MRN WordPress Stack model

Use this model to avoid conflating hosting architecture with stack release and component versions.

### 1) PLATFORM GENERATION

- LEGACY STACK = CloudPanel
- FUTURE STACK = RunCloud

Platform generation describes the hosting/runtime/deployment architecture and migration status.

Never infer platform generation from a stack release label or an individual plugin/component version.

### 2) STACK RELEASE / BASELINE SNAPSHOT

- Source of truth: `stack/STACK_VERSION.md`
- Current verified release: read the value from `stack/STACK_VERSION.md`. This document deliberately does not duplicate it, because a copied release label drifts silently.
- In stack language, this is a stack release/baseline snapshot label, not a platform-generation name.
- A release label may reflect a significant change included in that baseline and is not itself a statement about platform architecture.
- For rollout decisions, treat component catalog records as the durable source of plugin-model reality.

### 3) INDIVIDUAL STACK COMPONENTS

Components within the stack are independently identified/versioned where applicable.

- Example:
  - MRN Universal Sticky Bar
    - Slug: `mrn-universal-sticky-bar`
    - Current version in current baseline: read it from `stack/manifests/component-catalog.json` (illustrative example only; versions are never duplicated here).
    - Classification: platform-required standard plugin
    - Its inclusion/version is part of the stack baseline, but it is not a stack generation.

### Discovery rule (before changes)

When working on an MRN-managed WordPress site, always distinguish:

1. Which PLATFORM GENERATION is it on? (CloudPanel or RunCloud)
2. Which STACK RELEASE/BASELINE is it aligned with? (compare against `stack/STACK_VERSION.md` and applicable manifests)
3. Which COMPONENT VERSIONS are installed? (verify against the applicable stack baseline/catalog)

Do not treat these three concepts as interchangeable.

The Future/RunCloud stack is still under active development. Do not assume a stack release label means a completed Future/RunCloud architecture.

Before stack-level deployment or migration:

- determine platform generation
- determine applicable stack baseline
- verify component compatibility
- follow backup/preflight/QA requirements

## 6) Environment terminology

- LOCAL
  - Runtime and tools managed in Local Hub/TheHub (`thehub.localhost`) and local site runtimes.
- DEVELOPMENT / REVIEW
  - Includes client-review environments such as `mrndev.io`.
  - Not production, even when externally reachable.
- PRODUCTION
  - Live customer environments.
  - Must be explicitly identified before any write/deploy and follow all deployment safety gates.

Never treat LOCAL, `mrndev.io` review, and production environments as interchangeable.

## 7) Deployment safety (global standards)

- Backup-before-mutation:
  - For MRN-managed shared development/review, staging, and production WordPress sites, no mutation that can affect the site's WordPress runtime, database, code, files, configuration, plugins, themes, MU plugins, WordPress core, or customer-visible state should begin until the applicable MRN backup gate has successfully passed.
  - LOCAL runtimes are explicitly exempt from the remote-backup gate. They use Git for code rollback and may use an explicitly requested, temporary local database snapshot for destructive data work.
  - For covered runtimes, this applies regardless of execution path: QA Engine, MainWP / MRN MainWP tooling, approved deployment tooling, provider tooling, or SSH.
  - For covered runtimes, using SSH or another lower-level access path does not bypass the backup requirement.
  - Read-only inspection does not require creating a new backup merely to inspect the system.
  - Before an actual mutation:
    1. Determine the applicable backup policy for that operation.
    2. Use the highest-level supported MRN workflow to create and verify the backup.
    3. Do not merely start a backup and assume success.
    4. Verify successful completion according to the applicable policy before the first mutation.
  - Preserve the existing backup-scope distinctions in `stack/BACKUP_POLICY.md`:
    - General shared-runtime deployment writes: follow `stack/BACKUP_POLICY.md` and the documented verified database-only remote Updraft backup immediately before the write. LOCAL writes follow the local exception in that policy.
    - QA Engine protected update / campaign workflows: follow the stricter QA Engine evidence requirements, including fresh provider-complete backup and applicable restore-readiness / restore-drill evidence.
    - Dry-run / read-only exceptions remain read-only.
  - Do not weaken a stricter workflow merely because the global minimum is less strict.
  - If the required backup cannot be created or verified for a covered runtime, stop and do not perform the mutation.
  - Do not use SSH, direct database access, file manipulation, or provider tooling as a workaround for a failed backup gate on a covered runtime.
- Deployment scoping:
  - Deploy only approved site-owned paths unless explicitly requested otherwise.
  - Preserve existing live/site behavior and compatibility unless user explicitly requests a migration or behavior change.
- Production confirmation boundaries:
  - Confirm target is production and explicit before executing production writes.
- Operations hierarchy relationship:
  - QA Engine first when supported -> MainWP / MRN tooling -> approved lower-level tooling -> SSH last resort.
  - The backup gate applies before the mutation regardless of which execution layer is used.

## 8) Staleness model

| Policy/Convention | Authoritative source | Last validated in this pass | Validation method |
| --- | --- | --- | --- |
| Global Codex configuration and MCP/tool wiring | `/Users/khofmeyer/.codex/config.toml` | 2026-08-16 | file timestamp + key inspection |
| Codex global instructions and MRN-specific operating rules | `/Users/khofmeyer/.codex/AGENTS.md` | 2026-07-08 | file timestamp + direct inspection |
| Claude Code global bootstrap into shared MRN policy | `/Users/khofmeyer/.claude/CLAUDE.md` | 2026-08-17 | file created + direct inspection |
| Repo-level MRN safety and conventions | `/Users/khofmeyer/Development/MRN/AGENTS.md` | 2026-08-13 | file timestamp + direct inspection |
| Backup/deploy contract | `/Users/khofmeyer/Development/MRN/stack/BACKUP_POLICY.md` | 2026-08-13 | file timestamp + direct inspection |
| TheHub runtime conventions and docs | `/Users/khofmeyer/Development/MRN-local-hub/README.md` and `~/.mrn-local-hub/settings.json` | 2026-07-20 | file timestamp + config read |
| Production Hub MCP contract + 1Password retrieval methods | `/Users/khofmeyer/LocalSites_production-hub/mcp-server/server.mjs` and `/Users/khofmeyer/.codex/config.toml` | 2026-08-16 | file timestamp + key inspection |
| SSH/Tailscale host conventions | `~/.ssh/config` | 2026-08-06 | file timestamp + direct inspection |

Dynamic state policy:
- Treat host routes, token presence, and live access as dynamic. Validate against current runtime/tool state during task execution before assuming connectivity or scope.

## 9) MRN QA routing

Use the repo-level QA instructions in `AGENTS.md` as the detailed QA rule set; this section establishes the global routing distinction.

### A. MRN QA execution

- When the owner asks to `QA this`, `run QA`, `QA the plugin`, `QA the theme`, `QA this code`, `run MRN QA`, or gives an equivalent instruction to perform MRN code QA, use the MRN QA Engine / established MRN QA workflow.
- Do not substitute an ad-hoc collection of local checks and call that `MRN QA`.
- The agent may run the smallest relevant local checks during development, but a request specifically for MRN QA should use the established QA Engine/workflow when available.
- Follow the applicable project/repository QA instructions and scopes.
- The QA Engine may cover, where applicable, PHP lint, WordPress coding/security checks, PHPStan/static analysis, WordPress API/security surface review, capability/nonce/sanitization/escaping checks, REST/admin-ajax/admin-post coverage, accessibility, performance, release readiness, and other configured MRN QA suites.
- Do not assume every QA suite applies to every project.
- Use the smallest applicable MRN QA scope.
- Preferred command: `mrn-qa run`. If it is not on `PATH`, use `/Users/khofmeyer/Development/MRN-qa-engine/bin/mrn-qa run`.
- Pass the repository actually being worked on as `--project-root`. Do not pass the stack root `/Users/khofmeyer/Development/MRN` for client-site QA unless the owner is explicitly asking for stack or plugin-stack QA. For a pulled site, that means the site folder, for example `--project-root /Users/khofmeyer/Development/MRN-sites/{slug}`, letting the runtime target auto-detect the site's `.localhost` URL.
- Use auto gates by default, and report which static, API, accessibility, performance, and runtime checks ran or were skipped.
- WordPress API QA is required coverage where applicable: REST routes, admin-ajax, admin-post, permission callbacks, nonces, capabilities and auth, sanitization, escaping, and `/wp-json/` runtime health.
- Accessibility QA is required coverage where applicable: axe-core WCAG A/AA scans when a runtime is available, semantic markup, headings, labels and control names, image alt text, keyboard and focus risk, visible text and link names, and a WCAG 2.1 AA baseline wherever MRN controls the output.
- After changing an MRN plugin, theme, MU-plugin, Stack runtime component, or QA Engine component, run the smallest relevant MRN QA suite required by the existing repository/project policy before declaring the work complete.
- Do not interpret `tests passed` or `git diff is clean` as equivalent to MRN QA when MRN QA is required.
- Do not deploy merely because QA passes; deployment authorization and backup gates remain separate requirements.

### B. WordPress operations orchestration

- For supported WordPress site maintenance/update operations, use this routing order when the verified workflow exists and is available: QA Engine first -> MainWP / MRN MainWP tooling -> approved lower-level tooling -> SSH last resort.
- This is an operational-routing rule, not merely a code-QA rule.
- Running QA itself is normally read-only and does not require creating a site backup.
- A backup is required when the workflow is about to perform a covered shared-runtime mutation. LOCAL runtime mutations are not covered by the remote-backup gate.
- Code QA does not itself trigger an Updraft backup.
- Covered site mutation requires the applicable backup gate to pass first; LOCAL mutation follows the local exception in `stack/BACKUP_POLICY.md`.
- Protected update / campaign workflows use the stricter QA Engine backup + restore-evidence requirements.

## 10) MRN commit gate

- For MRN code repositories, a commit must not be created until the smallest applicable MRN QA suite has been run and passed for the change being committed.
- This applies, where applicable, to WordPress plugins, themes, MU plugins, MRN Stack runtime/components, QA Engine code, shared MRN tooling, deployment/runtime code, and other production-affecting MRN code repositories.
- Normal order: code change -> smallest applicable local/development validation -> MRN QA -> fix failures if any -> rerun MRN QA -> commit only after QA passes.
- Do not commit first and plan to QA afterward unless the owner explicitly authorizes an emergency/checkpoint exception.
- A clean `git diff`, syntax check, unit test, or ad-hoc local validation is not automatically equivalent to MRN QA when the repository requires MRN QA.
- Use the smallest relevant QA scope rather than automatically running the largest possible suite.
- If MRN QA cannot run because of infrastructure/tooling failure, stop before committing and report the blocker unless the owner explicitly authorizes a checkpoint/exception.

### Checkpoint / recovery exception

- A preservation/recovery checkpoint commit may be allowed without full release QA only when the owner explicitly approves it as a recovery/checkpoint commit.
- The purpose must be preserving otherwise at-risk work.
- The commit must be clearly not being treated as release-ready.
- The missing QA/validation must be explicitly documented.
- This exception must not silently become the normal development workflow.

### Relationship to deployment

- QA passing authorizes neither deployment nor production mutation.
- The sequence remains: QA pass -> commit may proceed -> deployment remains separately gated by owner/production authorization, applicable backup policy, environment safety, MainWP/QA Engine routing, and deployment-specific validation.

## 11) Vendor bootstrap portability

- This file is vendor-neutral and repo-native. It is the canonical MRN operating context for every agent.
- Each agent has a small vendor-specific bootstrap that points here and duplicates nothing:
  - Codex: `/Users/khofmeyer/.codex/AGENTS.md`
  - Claude Code: `/Users/khofmeyer/.claude/CLAUDE.md`
- A vendor bootstrap may contain only detection rules, read order, fail-closed behavior, and vendor-specific mechanics. Policy belongs here or in the applicable `AGENTS.md`.
- Claude Code does not discover `AGENTS.md` automatically; its bootstrap must state the discovery step explicitly. Codex discovers `AGENTS.md` natively.
- Keep project and tool-specific behavior where it is authored.
