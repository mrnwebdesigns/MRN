# Shared Website Operations service policy

The MRN Website Operations service lives in `tools/operations`. It coordinates
existing workflows through MCP; it does not own a competing MainWP implementation
or replace Fleet, site deployments, content migrations, QA Engine or provider
backup policy. The first version is a review candidate, not an activated service.

The requesting team member must authenticate individually. Server policy grants
access by website, environment and operation. A downstream service credential
never expands that person's authority. Every downstream call rechecks permissions
and preserves the requester in the audit record. Identity, authentication or
permission failures stop that route; never silently change credentials or use SSH.

Website enrollment records intended knowledge, sources and freshness separately
from observed runtime evidence. Credentials remain references to approved services.
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

Use [the service onboarding and capability matrix](../tools/operations/README.md)
for implemented adapters, operational acceptance, pending hosting/identity choices
and known gaps. No service, tool, chat prompt or automation may weaken the existing
MainWP, QA, backup, content ownership or release gates.
