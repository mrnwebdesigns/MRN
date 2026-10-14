# Team configuration review

Read-only observations from 2026-10-14. This is evidence for a later concrete
deployment/access review; none of the settings below were changed.

## Existing identity infrastructure

- Google Admin identifies MRN Web Designs as Workspace customer `C03u6obsi`.
- Existing Auth0 tenant: `dev-bhbm2z074birs7aj.us.auth0.com`.
- Google social connection: `con_WZZEaStiiXGzMjDD`, strategy/name `google-oauth2`.
- The connection is enabled for the existing native OAuth application
  `R0GQ8FV2XEOD3T8Ym1bkLF4pl9OUEprl`, currently named MRN Client Review MCP OAuth.
- That application's callback allowlist contains the existing Operations
  ChatGPT callback `https://chatgpt.com/connector/oauth/MbAPpAv-mkG3`.
- The existing Operations API is `6ac7d75a0699732d6ed78674`, with exact audience
  `https://operations.mrnwebdesigns.com/mcp`, RS256 and 3600-second token lifetime.
  User-delegated access is per-app; machine and anonymous access are disabled.
  Offline access is enabled, RBAC is off, and consent skipping is unchecked.
- The shared OAuth application currently has the API's one permission,
  `mrn:operations`, described as "Read MRN website information allowed by your
  staff account". The candidate retains that scope for reads and requires the
  additional `mrn:operations:write` for preparation, approvals, source merge,
  execution and recovery. Existing read-only tokens cannot gain write authority
  from staff roles, tool arguments or metadata. The new scope and grant are
  **not configured live**.
- Both Google and Username-Password-Authentication are enabled on this shared
  application. Operations must enforce its Google-only resource-specific
  Action and verified gateway/provider claims without globally disabling
  authentication used by another MRN app.
- Auth0 explicitly reports that the Google connection uses development keys.
  Replacing these with an MRN-owned production Google OAuth client needs a
  reviewed credential/configuration change and regression checks for the other
  apps sharing this connection. It does not require another team plugin.

The Google customer and Auth0 connection IDs are public policy identifiers,
not credentials. No client secret, token or private key was retrieved or saved.

## Observed service state

The MRN Apps Operations service was active at source
`a41d1ba7ce2374fed10f110e7682eacaf4f2b49d`. Its configuration still has writes
disabled and no new Google Workspace delegation, code workflow or content
configuration. The existing workspace plugin still exposes four hosted tools.
The proposed source has 21 tools; this is not workspace publication evidence.

## Required deployment review

The CI source-access blocker was resolved on 2026-10-14 with the owner's
explicit approval. The existing **MRN CI Standalone Repos** installation
`155567409` now includes `mrnwebdesigns/mrn-mainwp-operations-api`: all 28 prior
repository selections were preserved, with 29 selections verified after saving.
Its existing read-only code and metadata permissions are unchanged. The
[rerun at source 57d61e4](https://github.com/mrnwebdesigns/MRN/actions/runs/38592055915/job/115951238897)
successfully obtained the repository-scoped read token and checked out both
pinned sources. The old provider pin passed its own runtime checks but did not
invoke the new gateway hook; the required gateway report was absent and the job
correctly failed. Provider checkpoint `27ac5bc` subsequently passed its disposable
runtime CI and supplies that hook. Gateway source `a6eccc2` then passed all ten
actual MCP/WordPress transport checks in [run 38593686429](https://github.com/mrnwebdesigns/MRN/actions/runs/38593686429/job/115955067868).
Those checks use synthetic OAuth/Directory identities and an empty managed-site
directory; live sign-in and site edits remain unverified.
This CI grant supplies no write, PR, deployment or production
source-editing authority, and is unrelated to individual staff access or site
enrollment. No personal token was substituted.

The runner's subsequent CI run `38593836989` passed its actual code/content test
steps but failed both required report uploads at the account artifact quota.
Authenticated read-only billing inspection found 2 GB used / 2 GB included and
an existing account-wide Actions budget of $0 with usage blocked. No budget,
retention policy or evidence was changed. Restoring artifact uploads requires a
separately approved billing change or an explicitly reviewed retention action;
then rerun the failed jobs and verify retained reports. Do not make report upload
optional, delete qualification evidence or treat the failed workflow as green.
GitHub documents that storage accounting can take 6–12 hours to update and that
artifact deletion does not remove already-accrued usage in the billing cycle:
[Actions billing](https://docs.github.com/en/billing/concepts/product-billing/github-actions).

Before an activation request, finish the content/provider/runtime qualification
and present exact source commits, qualified artifacts and rollback artifacts.
Then review the resource-specific Auth0 Action, production Google client,
customer/connection pins, distinct backend service credential fingerprint,
identity signer, Directory availability, central GitHub Apps and protected QA
actor/policy. Compare the service fingerprint with the owner/global MCP and
independent workflows before restricting it; never pin a reused credential.

The access review must include adding `mrn:operations:write` ("Prepare, approve
and execute guarded MRN website changes") to this existing API and only its
Operations user-delegated grant for the existing OAuth client. Update the same
workspace plugin's tool metadata and Google OAuth scope request to include both
Operations scopes. Do not grant machine access or change another API's grants.
Verify that an existing read-only token still reads but receives an
`insufficient_scope` reconnect challenge for all eight change-lifecycle tools;
newly consented tokens must still pass Google membership, policy, QA, approval
and backup gates. This is OAuth consent for the existing plugin, not individual
staff approval or a second connector.

All active verified MRN staff receive domain/customer-based portfolio roles.
There is no named-member allowlist or enrollment approval. Staff revocation,
suspension and outsider denial must be tested, along with ordinary ChatGPT
content editing, code editing and exact rollback. Source tests do not establish
that live acceptance. See `../TEAM-ACCEPTANCE.md` for the evidence record.
