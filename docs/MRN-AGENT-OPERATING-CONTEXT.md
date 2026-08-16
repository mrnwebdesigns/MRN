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
- For infrastructure/provider access, Production Hub lookup is preferred where supported.
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
- Use alias- and host-aware routing from durable local SSH configuration.
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

### CloudPanel / `mrndev.io`
- Treat `mrndev.io` as review/development/staging infrastructure unless explicitly marked production.
- Keep public `*.mrndev.io` hostnames in the production networking posture; do not hard-code SSH assumptions for these hosts.
- Resolve ssh access details dynamically via current inventory/config, then validate reachability before privileged actions.

### MainWP
- Use as the governed shared deployment and stack-management path for applicable shared components.
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

- Backup-before-write:
  - Required precondition: verified database-only backup before non-dry-run remote writes to shared/dev/staging/production targets.
- If required backup cannot be verified: stop and report blocker; do not write.
- Deployment scoping:
  - Deploy only approved site-owned paths unless explicitly requested otherwise.
  - Preserve existing live/site behavior and compatibility unless user explicitly requests a migration or behavior change.
- Production confirmation boundaries:
  - Confirm target is production and explicit before executing production writes.

## 8) Staleness model

| Policy/Convention | Authoritative source | Last validated in this pass | Validation method |
| --- | --- | --- | --- |
| Global Codex configuration and MCP/tool wiring | `/Users/khofmeyer/.codex/config.toml` | 2026-08-16 | file timestamp + key inspection |
| Global instructions and MRN-specific operating rules | `/Users/khofmeyer/.codex/AGENTS.md` | 2026-07-08 | file timestamp + direct inspection |
| Repo-level MRN safety and conventions | `/Users/khofmeyer/Development/MRN/AGENTS.md` | 2026-08-13 | file timestamp + direct inspection |
| Backup/deploy contract | `/Users/khofmeyer/Development/MRN/stack/BACKUP_POLICY.md` | 2026-08-13 | file timestamp + direct inspection |
| TheHub runtime conventions and docs | `/Users/khofmeyer/Development/MRN-local-hub/README.md` and `~/.mrn-local-hub/settings.json` | 2026-07-20 | file timestamp + config read |
| Production Hub MCP contract + 1Password retrieval methods | `/Users/khofmeyer/LocalSites_production-hub/mcp-server/server.mjs` and `/Users/khofmeyer/.codex/config.toml` | 2026-08-16 | file timestamp + key inspection |
| SSH/Tailscale host conventions | `~/.ssh/config` | 2026-08-06 | file timestamp + direct inspection |

Dynamic state policy:
- Treat host routes, token presence, and live access as dynamic. Validate against current runtime/tool state during task execution before assuming connectivity or scope.

## 9) Portability for future CLAUDE.md

- This file is vendor-neutral and repo-native.
- A future `CLAUDE.md` should reference this file as the global source, adding only Claude-specific behavior notes.
- Keep this file as the canonical MRN operating context; project and tool-specific behavior should remain where it is authored.
