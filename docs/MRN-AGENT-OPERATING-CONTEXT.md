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

## 2) PRODUCTION HUB

- Name: Production Hub.
- Role: infrastructure/provider/access discovery and operational coordination system.
- Integration in Codex: configured as a global MCP server named `production-hub`.
- Canonical behavior: resolve authoritative provider infrastructure and runbook data, then perform controlled 1Password-backed credential discovery where supported.
- Scope: separate from runtime/LocalHub site operations.

Use Production Hub when environment-level facts are needed instead of static assumptions.

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

### MainWP
- MainWP is MRN's preferred and authoritative operational interface for WordPress site management whenever the required operation can safely be performed through MainWP or approved MRN tooling built around MainWP.
- Use MainWP for as much as reasonably and safely possible, including supported updates, backups, update inventory/discovery, site status/readiness checks, maintenance orchestration, post-update verification, and supported MRN shared plugin/theme/stack component deployments.
- Do not default to SSH simply because SSH access exists.
- MainWP-first does not override backup-before-write, QA, environment-separation, or production-authorization requirements.
- Respect repo-level deployment scope and MRN deployment policies.

### Shared deployment tooling
- Use shared tooling and scripts defined in canonical MRN stack/repo workflows and deployment contracts.
- Apply per-project/site-specific edits only to approved scopes.

## 5) MRN WordPress Stack model

Use this model to avoid conflating hosting architecture with stack release and component versions.

### 1) PLATFORM GENERATION

- LEGACY STACK = CloudPanel
- FUTURE STACK = RunCloud

Platform generation describes the hosting/runtime/deployment architecture and migration status.

Never infer platform generation from a stack release label or an individual plugin/component version.

### 2) STACK RELEASE / BASELINE SNAPSHOT

- Source of truth: `stack/STACK_VERSION.md`
- Current verified release: `2026.08.14-sticky-bar-platform-requirement`
- In stack language, this is a stack release/baseline snapshot label, not a platform-generation name.
- A release label may reflect a significant change included in that baseline and is not itself a statement about platform architecture.
- For rollout decisions, treat component catalog records as the durable source of plugin-model reality.

### 3) INDIVIDUAL STACK COMPONENTS

Components within the stack are independently identified/versioned where applicable.

- Example:
  - MRN Universal Sticky Bar
    - Slug: `mrn-universal-sticky-bar`
    - Current version in current baseline: `1.1.8`
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
  - For MRN-managed WordPress sites, no mutation that can affect the site's WordPress runtime, database, code, files, configuration, plugins, themes, MU plugins, WordPress core, or customer-visible state should begin until the applicable MRN backup gate has successfully passed.
  - This applies regardless of execution path: QA Engine, MainWP / MRN MainWP tooling, approved deployment tooling, provider tooling, or SSH.
  - Using SSH or another lower-level access path does not bypass the backup requirement.
  - Read-only inspection does not require creating a new backup merely to inspect the system.
  - Before an actual mutation:
    1. Determine the applicable backup policy for that operation.
    2. Use the highest-level supported MRN workflow to create and verify the backup.
    3. Do not merely start a backup and assume success.
    4. Verify successful completion according to the applicable policy before the first mutation.
  - Preserve the existing backup-scope distinctions in `stack/BACKUP_POLICY.md`:
    - General Stack / deployment writes: follow `stack/BACKUP_POLICY.md` and the documented verified database-only remote Updraft backup immediately before the write.
    - QA Engine protected update / campaign workflows: follow the stricter QA Engine evidence requirements, including fresh provider-complete backup and applicable restore-readiness / restore-drill evidence.
    - Dry-run / read-only exceptions remain read-only.
  - Do not weaken a stricter workflow merely because the global minimum is less strict.
  - If the required backup cannot be created or verified, stop and do not perform the mutation.
  - Do not use SSH, direct database access, file manipulation, or provider tooling as a workaround for a failed backup gate.
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
- After changing an MRN plugin, theme, MU-plugin, Stack runtime component, or QA Engine component, run the smallest relevant MRN QA suite required by the existing repository/project policy before declaring the work complete.
- Do not interpret `tests passed` or `git diff is clean` as equivalent to MRN QA when MRN QA is required.
- Do not deploy merely because QA passes; deployment authorization and backup gates remain separate requirements.

### B. WordPress operations orchestration

- For supported WordPress site maintenance/update operations, use this routing order when the verified workflow exists and is available: QA Engine first -> MainWP / MRN MainWP tooling -> approved lower-level tooling -> SSH last resort.
- This is an operational-routing rule, not merely a code-QA rule.
- Running QA itself is normally read-only and does not require creating a site backup.
- A backup is required when the workflow is about to perform a covered site/runtime mutation.
- Code QA does not itself trigger an Updraft backup.
- Site mutation requires the applicable backup gate to pass first.
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
