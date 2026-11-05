# Fixed QA egress

This host-network tool lets the private Amish Mike production workflow run its
existing browser checks through the existing MRN QA server. It does not run
repository code on that server, change the QA application, or provide CI with
server SSH credentials. No shared plugin/runtime package is changed.

## Scope

- Existing node: `mrn-qa-engine`, Tailscale `100.97.81.44`.
- Public egress: `159.65.33.118` and `2604:a880:800:14:0:2:f6f6:b000`.
- CI tag: `tag:amishmike-ci`, with only Internet web/DNS access via `tag:qa`.
- Federation: GitHub OIDC, exact private repository ID, protected main workflow,
  and `production` environment. The identity can create tagged ephemeral nodes;
  it cannot administer the policy, host or other devices.
- The subject includes the immutable owner/repository IDs issued by this
  repository. Verify the received subject before enrollment; GitHub repositories
  created after July 15, 2026 use the ID-bearing format. Keep all six custom
  claims exact; do not replace the subject with a wildcard to fix a mismatch.
- No SSH, internal subnets, production servers or public SSH destinations are
  granted. Existing administrator grants and policy comments are preserved.

The separate site workflow defaults `QA_EGRESS_ENABLED` to `0`. Its wrapper
selects this exact exit node, verifies IPv4 and IPv6 through two services each,
executes the unchanged QA commands and restores routing even after failure.
GitHub's Tailscale action logs out the ephemeral node during job cleanup. A
hard-killed runner must still disappear from device inventory after expiry.

## Prepare and qualify

Use a dedicated task worktree. Install the pinned parser dependency in an
isolated directory, not the production QA application.

```sh
python3 -m pip install --target /tmp/mrn-qa-egress-deps -r tools/qa-egress/requirements.txt
PYTHONPATH=/tmp/mrn-qa-egress-deps python3 -m unittest discover -s tools/qa-egress/tests -v
PYTHONPATH=/tmp/mrn-qa-egress-deps python3 tools/qa-egress/policy_plan.py \
  --policy /private/path/current-policy.hujson --output /private/path/plan
```

The planner refuses conflicting CI permissions and broad existing source grants.
Review and validate the resulting policy with Tailscale before saving. Refetch
and compare its ETag immediately before a write. If changed, replan from the new
version. Never replace concurrent administrator changes.

## One-time enrollment

1. Through the existing approved `mrn-qa-engine-admin` SSH alias, run the
   `exit_node.py preflight` command with sudo. It checks both public addresses,
   existing container identities/config hashes and public QA health. No writes.
2. Qualify and commit the exact host script. Run `exit_node.py apply` with sudo.
   It checkpoints only relevant network preferences and service hashes under
   root-only `/var/lib/mrn-qa-egress/`, creates only its own sysctl file, enables
   IPv4/IPv6 forwarding and advertises the exit routes. It does not restart
   containers, change host routing, enable Tailscale SSH or open inbound ports.
3. Save the reviewed policy and explicitly approve exit-node use for this node
   in Tailscale. There is no broad route auto-approver.
4. Create the identity from `federated-identity.json`. Check all custom claims
   and scope. Store its generated client ID and audience in the site's protected
   GitHub production environment as `TS_QA_CLIENT_ID` and `TS_QA_AUDIENCE`.
   There is no long-lived client secret or reusable auth key.
5. Create site-specific bot-protection exceptions for only the two verified
   public addresses. Preserve bot protection and all unrelated rules. Scope any
   Cloudflare catalog-rule exclusion to this site's GET/HEAD requests and these
   addresses; preserve the full original rule and verify readback.
6. Enable the protected QA egress variable and dispatch **runtime**, not deploy.
   Check the route receipts, strict existing QA results and ephemeral-node
   removal. Verify denied SSH/internal access using Tailscale policy tests.
   A successful route probe is not completed site QA.
7. Only after the runtime proof, continue the separately authorized site release
   with its own fresh native Kinsta backup and unchanged rollback gates.

The existing read-only policy-reader credential cannot perform enrollment.
Use the administrator UI for those one-time operations; do not broaden or
repurpose the reader credential. Follow UI action-time confirmation requirements.

## Recovery

Disable `QA_EGRESS_ENABLED` before revoking the federated identity or removing
the scoped policy grant. This restores the prior access path; it does not bypass
the required website QA. Remove only the exact owned site exceptions if retiring
the service. Preserve unrelated administrator/provider exceptions.

Run `exit_node.py rollback` as the approved host administrator. It refuses a
changed owned sysctl file or concurrent route changes, withdraws only the exit
advertisement, restores the two original forwarding values and verifies the
existing QA services. The root-only checkpoint remains for audit; it is not a
site database or application backup. Rollback may still restore routing when
an HTTP health probe fails. Investigate any reported service drift separately.

Official references: [GitHub action](https://tailscale.com/docs/integrations/github/github-action),
[federation](https://tailscale.com/docs/features/workload-identity-federation),
[exit nodes](https://tailscale.com/docs/features/exit-nodes),
[route filtering](https://tailscale.com/docs/features/access-control/grants/grants-via).
