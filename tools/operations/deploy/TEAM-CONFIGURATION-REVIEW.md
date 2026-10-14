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

The separate disposable integration test found a CI source-access blocker on
2026-10-14: the existing **MRN CI Standalone Repos** installation
`155567409` returned HTTP 404 when requesting a read-only token for
`mrnwebdesigns/mrn-mainwp-operations-api`. The workflow requests only repository
contents read access and does not request write/PR/deployment authority. The
installation was identified in the signed-in MRN GitHub account, but GitHub
requires passkey confirmation before its selected repositories and current
permissions can be inspected. No access change has been made. After inspection,
present the exact single-repository addition for approval and preserve every
existing selection. Do not broaden this App into the separate production
source-editing App or substitute a personal token. This is MRN service setup;
it is unrelated to individual staff access or site enrollment.

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
