# Freedom House release acceptance

The default reusable workflow still requires hosted browser and strict runtime
QA. The explicit `local_qa_acceptance` input supports only a manual Live dispatch
from `mrnwebdesigns/freedomhouse-site` main, for the approved October 14 repair.
It does not alter deployment ordering, secrets, backups, immutable artifacts,
public checksum verification, or rollback.

Before site access, the workflow verifies the committed local report checksum,
required passing release rows, freshness, approved operator and runtime versions.
It compares the qualified commit with the selected commit across the entire child
theme and all QA inputs. A squash or workflow pin may differ; qualified code and
QA configuration may not.

After activation, fresh Live REST health and ordinary Chrome readback are required.
The operator records all four deployment routes at desktop, tablet and mobile,
applied immutable stylesheet URLs, screenshots, overflow/errors, Services contrast,
native search results and the admin form/notices. A separate main-only
`freedomhouse-live-readback.yml` dispatch retains that JSON as a GitHub artifact.
The deploy workflow accepts only a successful same-source run by the approved
operator, bound to its own deployment run and artifact checksum. Evidence expires
after 30 minutes. Missing or failed evidence rolls back within 20 minutes.

The retained result names this path `local-qualified-with-live-readback` and
continues to report hosted runtime QA as skipped/Cloudflare-blocked. It never
reports those hosted tests as passing. No security challenge exception is created.
