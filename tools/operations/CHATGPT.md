# MRN Website Operations in ChatGPT

## What the team does

Once MRN has made the plugin available in its ChatGPT workspace:

1. Install **MRN Website Operations** from the workspace's plugins.
2. Connect it and sign in with the approved individual MRN account.
3. Select the plugin in chat and ask, for example, “Inspect this website and tell
   me what needs attention.”

The plugin finds the member's accessible websites and keeps findings, required
decisions, progress and verification in the conversation. It must never direct a
team member to Codex, a terminal, API keys, configuration files or site enrollment.
An unavailable check is explained as unavailable. Unsupported repairs remain
explicit; plugin installation does not enable an unimplemented workflow.

## Delivery state

ChatGPT is the chosen host. `chatgpt/plugin.json` defines the portable package,
and `chatgpt/package.py` builds a ZIP containing that manifest and one remote
Streamable HTTP connection. It copies no service configuration or executables.
The service exposes OAuth metadata, per-tool security declarations and reconnect
challenges. A valid sign-in still needs the member's server-enforced permissions.
Platform-supplied user or organization metadata is not an authorization source.

The owner selected the existing DigitalOcean **MRN-Apps** server and approved
access for all verified `@mrnwebdesigns.com` staff on October 8, 2026. Reuse the
existing MRN Auth0 tenant with a separate Operations API and audience-bound
verified email claims. The reviewed initial policy grants MainWP reads only.
See the [MRN Apps delivery record](deploy/MRN-APPS.md) for the exact deployment.

This is a local implementation and test candidate, not an installed ChatGPT
plugin. The new endpoint and Auth0 API are not yet active.
The hostname is proposed, not provisioned. Workspace publishing and
end-to-end ChatGPT sign-in have not been performed. No private-directory listing,
public-directory approval or user entitlement is implied by the package.

## MRN operator delivery

Use a workspace-published plugin for the team. The operator creates the remote
MCP connection in ChatGPT Plugins with OAuth, tests it privately, then publishes
it to the intended workspace roles. Ordinary members install that published
plugin; they do not create MCP connections themselves. Workspace administration
and policy must permit publishing. This is the documented internal distribution
route, separate from public directory submission.
[OpenAI packaging and workspace publishing](https://developers.openai.com/plugins/build/plugins)

The connection uses the approved HTTPS `/mcp` URL and OAuth-only authentication.
OpenAI supports custom MCP tools, so this service's operations need no artificial
search/fetch wrapper. Refresh connection metadata after service tool changes.
[OpenAI custom MCP connection](https://developers.openai.com/api/docs/guides/custom-mcp-server)

Before that connection is usable, the operator must complete these concrete
settings in `service.json` and the chosen identity provider:

| Setting | Required MRN value |
| --- | --- |
| Public resource | Approved HTTPS URL ending in `/mcp`; also use it as token audience |
| Issuer and JWKS | Actual MRN authorization server; exact issuer match and published signing keys |
| OAuth | Authorization code with PKCE S256 and scope `mrn:operations` |
| Client registration | The provider's supported CIMD, DCR or predefined client route |
| Callback | Copy the exact URI shown by ChatGPT into the provider's allowlist |
| Member policy | Verified MRN email-domain read access; explicit subject overrides and write permissions |

Use an established identity provider; this service does not implement a new
password store or authorization server. An upstream Google/Microsoft login alone
does not establish the MCP OAuth contract. The provider must bind the requested
resource to the issued token's audience. Stable ChatGPT callbacks require issuer
identification support with matching metadata and authorization-response `iss`;
otherwise use ChatGPT's connection-specific callback. Token refresh and browser
consent belong to the provider and ChatGPT; the service validates every request.
[OpenAI authentication contract](https://developers.openai.com/plugins/build/auth)

All tools declare the MRN OAuth scope. Missing/invalid HTTP credentials receive
a 401 challenge. An identity that expires during a tool call receives the tool
reconnect signal. A denied site/action does not trigger a sign-in loop. Tool
annotations distinguish reads, saved inspection/planning records and actions
that may change a website or release an operation lock. These declarations guide
ChatGPT; backend controls remain authoritative.
[OpenAI tool metadata](https://developers.openai.com/plugins/reference)

## Package creation — operator only

For a reviewed portable artifact, supply the approved endpoint and a new output
path. From `tools/operations`, run:

```bash
python3 chatgpt/package.py \
  --server-url 'https://<approved-service-host>/mcp' \
  --output '/absolute/new/path/mrn-website-operations.zip'
```

The builder writes exactly `plugin.json` and `mcp.json`, reports a SHA-256 digest,
and refuses to overwrite an existing file. Its deterministic ZIP contains no
hooks, local servers, credentials or registered-app IDs. Building it does not
deploy, connect, publish or verify the endpoint. Public directory submission
would additionally need the applicable review and listing material; it is not
part of the internal workspace rollout.
[OpenAI package submission format](https://developers.openai.com/plugins/deploy/submission)

## Acceptance in the real workspace

With writes disabled, an ordinary member must install, sign in, discover their
permitted sites, inspect an exact site and read its evidence entirely in ChatGPT.
Check wrong-account access, denied sites, revoked permission and reconnection
after token expiry without falling back to a shared account. The owner must see
the correct requester in service audit records. Read-only inspection may save
evidence but must not change site code/content or submit forms.

Only after that succeeds should a controlled environment exercise a specifically
authorized repair, verified backup, execution, post-change verification and
recovery through the same conversation. Existing QA, backup, lock and release
gates remain required. The local HTTP/stdio fixtures do not replace this test.

The host, existing MRN identity provider and staff audience are identified.
The remaining delivery work is deployment, Operations-specific OAuth registration,
real ChatGPT acceptance and publication to MRN's workspace. Team members do none
of this setup.
