# Team configuration review

Configuration evidence from 2026-10-14. Identity and production settings were
inspected read-only for a later deployment/access review. The separately approved
CI repository grant and Actions budget change are recorded below.

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
- Readback of the connection's Applications tab shows five enabled applications:
  Default App, MRN Client Review MCP (Test Application), MRN Client Review MCP
  OAuth, MRN Google Reporting MCP (Test Application), and MRN Website Operations
  (Test Application). Basic and extended profile are selected; additional Google
  scopes and offline Google access are not selected. Preserve those settings
  and application selections unless a separate change is reviewed.
- After administrator reauthentication, Google Cloud project
  `mrn-infrastructure-access` (`535170757354`) reports **Internal** audience.
  Its existing `MRN MainWP Code Access` client is
  `535170757354-1dbvdmkck66u1lkkdd25jq4143gllbln.apps.googleusercontent.com`.
  It allows the JavaScript origin `https://wpcontrol.mrndev.io` and has no
  authorized redirect URI. The other existing client is MRN Cloudflare Access.
  The existing Directory reader is
  `mrn-mainwp-directory@mrn-infrastructure-access.iam.gserviceaccount.com`.
  No client, audience, secret, callback or service-account setting was changed.

### Google production-client proposal, not activated

Prepare a dedicated service-owned Google OAuth web client for the existing
Auth0 connection, preserving the MainWP pilot and Cloudflare clients. This is
backend setup for the same team plugin. Its proposed name is `MRN Auth0 Staff
Sign-in`, origin `https://dev-bhbm2z074birs7aj.us.auth0.com`, and sole redirect
`https://dev-bhbm2z074birs7aj.us.auth0.com/login/callback`, following
[Auth0's Google connection contract](https://auth0.com/docs/authenticate/identity-providers/social-identity-providers/google).
The ChatGPT connector callback remains on the existing Auth0 OAuth application;
it is not this Google-to-Auth0 callback. Keep profile/email scopes only.

An Internal Google client would constrain **all five connected applications**
to the Workspace. Operations requires that restriction, but existing allowed
audiences for the other applications still need verification before choosing
this shared connection's production client. Do not change the project's
audience, reuse the pilot secret, or silently narrow another application's
users. The release review must name that impact and its approved resolution.
Any new client credential must remain in the approved central secret store and
Auth0; no staff credential collection or client secret in ChatGPT is needed.

The Google customer and Auth0 connection IDs are public policy identifiers,
not credentials. No client secret, token or private key was retrieved or saved.

The candidate's `check:config` and startup now share code/content QA-policy
loading. Missing, malformed or interchanged policies and missing central
repository identity pins fail before SQLite state is opened. A passing preflight
reports profile counts only; it does not verify credentials, Google membership,
App permissions, hosted health or production rendering qualification.

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

The runner's CI run `38593836989` initially passed its actual code/content test
steps but failed both required report uploads at the account artifact quota.
Authenticated billing inspection found 2 GB used / 2 GB included and the existing
account-wide Actions budget at $0 with usage blocked. On 2026-10-14 the owner
explicitly approved increasing only that budget to **$5/month**, preserving
**Stop usage when budget limit is reached** and **Receive budget threshold
alerts**. The existing budget `3c5a3885-2781-462e-b1fd-ad24f95ea9ee` was saved,
then independently reopened to verify the amount and both checked controls.
The budget list showed $0.00 / $5.00; the four other product budgets stayed at $0.
This is an account-wide Actions spending limit, not production authorization.
No artifact, retention setting or required report gate was changed.

Only the failed jobs were rerun at the same immutable runner source `0fd0ff7`.
Both isolation jobs passed, including their required report uploads. The code
bundle `11910930605` and content bundle `11909304607` bind that same source and
were downloaded and checked: eight QA reports passed their API runtime, browser,
accessibility and performance rows, and two distinct HTTP parity manifests were
retained. [Attempt 2](https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/38593836989/attempts/2)
is fully green. This resolves CI evidence retention; it does not qualify native
MainWP child transport, production rendering dependencies or team acceptance.

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
