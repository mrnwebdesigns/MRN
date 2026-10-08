# Shared Website Operations service policy

The MRN Website Operations service lives in `tools/operations`. It coordinates
existing workflows through MCP; it does not own a competing MainWP implementation
or replace Fleet, site deployments, content migrations, QA Engine or provider
backup policy. The first version is a review candidate, not an activated service.

The team-facing experience is to add the MRN plugin in the team's chat app, sign
in individually, and use ordinary conversation. It must not require Codex,
terminal commands, local code or manual connection/credential configuration from
team members. Hosting and integration setup belong to MRN operators. The
installable chat integration, real sign-in and chat-only acceptance are required
delivery work; a tested MCP backend alone is not the finished team product.

The requesting team member must authenticate individually. Server policy grants
access by website, environment and operation. A downstream service credential
never expands that person's authority. Every downstream call rechecks permissions
and preserves the requester in the audit record. Identity, authentication or
permission failures stop that route; never silently change credentials or use SSH.

MainWP supplies the website directory; existing managed websites must not require
duplicate enrollment in Operations. Existing MRN records enrich exact URLs and
explicit environment relationships, preserving source and freshness separately
from observed runtime evidence. Missing facts stay unknown and only block actions
that need them. Source-wide read/test permissions are explicit; repair and release
authority remain scoped. Optional website overrides supply missing facts or
reviewed operation prerequisites, never a second mandatory inventory. Credentials
remain references to approved services. See
[discovery and knowledge](../tools/operations/DISCOVERY.md).
MainWP IDs are fresh-resolved from exact URLs and narrowly synchronized before
inventory is treated as current. Empty selections are rejected, not interpreted as
all sites. Older/partial Stack sites require qualification; missing components are
not automatically installed. A dedicated development management route is valid.

A repair refers to a recorded finding and revalidates it. Execution requires a
concrete exact-target plan, applicable source/runtime QA, explicit existing
operation authorization, the provider-appropriate verified backup and subsequent
runtime/public verification. Plans bind environment, source commit and artifact.
Content remains in WordPress/ACF, versioned through idempotent migrations; assets
follow the existing immutable asset standard. Code rollback and database/media
recovery remain different operations.

All admitted mutation workflows share a durable canonical-site lock. Before a
site can enable Operations writes, enroll or disable every independent writer for
that environment, including Fleet CLI, MainWP UI and site GitHub Actions. Existing
controller-local locks are not proof of cross-workflow exclusion. The initial
service refuses writes without reviewed, current writer-coordination enrollment.
This policy does not claim existing production writers have been enrolled.

Do not automatically retry a mutation whose outcome is unknown. Keep its lock and
operation evidence until exact state and downstream quiescence are established.
Service restart does not itself authorize recovery, re-execution or unlocking.
For the installed-plugin Fleet adapter, `reconcile_operation` accepts only an
operation ID and requires operator-enrolled, current quiescence evidence bound to
the exact saved operation. It performs fresh runtime/public/asset verification
and atomically records `reconciled` before releasing that operation's lock.
Unknown code, changed state, active workers, missing QA or stale evidence retain
the lock. A reconciled code state never establishes the original backup,
execution attribution, database or media outcome. Follow the
[recovery runbook](../tools/operations/RECOVERY.md); neither the chat nor a service
restart may fabricate worker termination or downstream-idle evidence.

A missing MCP tool or REST endpoint describes integration coverage, not MainWP
product capability. Assess both primary routes and relevant official extension
readiness before qualifying a different route. Authentication, safe mode, backup,
authorization and verification gates apply to extension/UI workflows as well.

Use [the service onboarding and capability matrix](../tools/operations/README.md)
for implemented adapters, operational acceptance, pending hosting/identity choices
and known gaps. No service, tool, chat prompt or automation may weaken the existing
MainWP, QA, backup, content ownership or release gates.
