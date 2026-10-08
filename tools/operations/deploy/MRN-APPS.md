# MRN Apps delivery

The owner selected this host and approved all verified `@mrnwebdesigns.com`
staff on October 8, 2026, then approved activating the prepared Auth0 access.
The read-only candidate is hosted and its MCP connection is published in MRN's
ChatGPT workspace. It is not a merged Stack promotion or a qualified repair release.

## Verified target

- DigitalOcean droplet `571721080`, name `MRN-Apps`, NYC3, active.
- SSH through the existing `mrn-proposals-admin` alias and MRN business
  1Password agent. The alias retains the server's former name. SSH hostname
  and DigitalOcean metadata both confirmed MRN-Apps and this droplet ID.
- Origin `165.227.71.50`; private administration uses Tailscale.
- Node `24.15.0`, npm `11.12.1`, Nginx; no Docker or Caddy.
- Port `127.0.0.1:8812` was unused. Available disk about 4.9 GB, available RAM
  about 2.6 GB. Recheck immediately before installation; no resizing is planned.
- Existing Client Review, Proposals and Site Audit services remain outside this
  deployment. Do not replace their vhosts or restart their processes.

## Installed configuration

Use `mrn-apps.service.json`, `mrn-apps.permissions.json`, the existing systemd
unit and `nginx.conf.example`. Install configuration under `/etc/mrn-operations`
with operator ownership and service read access, code under
`/opt/mrn-operations/MRN`, and private state under `/var/lib/mrn-operations`.
Use a separate `mrn-operations` system account. The initial service discovers
MainWP automatically; laptop Local Hub records are not mounted on this server.

The service URL/audience is `https://operations.mrnwebdesigns.com/mcp`.
A new proxied Cloudflare A record points only this hostname to MRN Apps:
`b08036936e9037e2c75b598ddfad5f11`, in zone
`c78a46a1d9be0c24c2c4207122ee3970`. No existing record or zone security policy
changed. The separate Nginx vhost passed syntax checks and was reloaded. The
Let's Encrypt certificate expires January 6, 2027. The existing certbot timer
renews it through `/var/www/certbot`; `certbot-reload-nginx.sh` is installed as a
root-owned deploy hook and reloads Nginx only for this exact certificate.

Systemd `mrn-operations` is active and enabled, running under its dedicated
account. Code/dependencies are root-owned; only private state is service-writable.
The initial deployed source is immutable candidate
`b070501ef0026430b4dab2df60f8d23b506e66cc`, recorded at
`/opt/mrn-operations/SOURCE_COMMIT`; that file records the current revision after
qualified follow-up deployments. The initial source archive SHA-256 is
`b1f3092e4ca124022b735ef6c976e33ba49675892daa56cddb4678e70102f873`.
The preinstallation Nginx backup is
`/var/backups/mrn-operations/bootstrap-20261008/nginx-before.tar.gz`.

Public curl checks returned health 200, resource metadata 200 and anonymous MCP
401 with the expected resource challenge. Cloudflare rejected Python urllib's
default user-agent with error 1010; no security exception was added. ChatGPT's
own OAuth discovery and authenticated tool discovery succeeded.

The pinned MainWP MCP is the existing approved implementation at
`3249bde643f52f29b7b18db41f4c3c7bb88d2434`. Resolve its application-password
credential from the MRN business `Production Hub - MainWP` item at deployment;
never copy interactive Codex settings. Production Hub reported all three named
fields present: `MAINWP_URL`, `MAINWP_USER`, `MAINWP_APP_PASSWORD`.
Keep `OPS_MAINWP_SAFE_MODE=true`, `writesEnabled=false`, no member write grants,
and no source/artifact write qualification in this initial read-only rollout.
Secret values must travel through an approved private provisioning path, never
shell arguments, terminal output, Git or deployment logs.

## Existing MRN sign-in

The existing tenant is `dev-bhbm2z074birs7aj.us.auth0.com`, shared by Client Review
and Google reporting. Its live discovery metadata confirms the exact issuer
`https://dev-bhbm2z074birs7aj.us.auth0.com/`, JWKS and PKCE S256 support.
The separate API **MRN Website Operations** is active, ID
`6ac7d75a0699732d6ed78674`, with:

- identifier equal to the public MCP URL, RS256, access tokens no longer than
  one hour, and permission `mrn:operations`;
- per-application user-delegated access and no machine/client-credentials access;
- a separate `auth0-post-login-action.cjs` attached after existing Login Actions,
  preserving every existing Action and unrelated application's settings;
- an approved ChatGPT OAuth application with Authorization Code + PKCE, exact
  callback copied from ChatGPT, refresh access and only the Operations grant.

The Action adds `${audience}/email` and `${audience}/email_verified` only for this
API and verified identities. The backend opt-in `verifiedEmailClaims` consumes
those signed claims. The staff policy grants read access to MainWP sources;
explicit subject entries can restrict or revoke someone immediately. No name,
email supplied in chat, plain token email, or ChatGPT organization hint grants
access. Machine tokens and lookalike/subdomains are rejected.

Auth0 administration used the authenticated UI because no management API
credential was found in the mapped provider item. The owner approved the
concrete API, verified-email rule and persistent ChatGPT staff read grant. One separate CLI lookup of the
non-secret issuer timed out awaiting 1Password authorization; issuer discovery
was independently verified through public OIDC metadata. Do not bypass failed
credential authorization or request secrets in chat.

References: [OpenAI OAuth contract](https://developers.openai.com/plugins/build/auth),
[Auth0 namespaced claims](https://auth0.com/docs/secure/tokens/json-web-tokens/create-custom-claims).

## Activation and acceptance

1. Qualify and commit the exact source; retain the existing branch/PR as the
   review record. Install only the reviewed immutable revision and locked
   dependencies. Save hashes, ownership, unit and vhost rollback copies.
2. Provision the separate service account, private configuration/state and
   approved MainWP pin. Verify configuration and downstream `mainwp://status`
   from this host before relying on discovery. No managed-site write is needed.
3. Activate the reviewed Auth0 API, Action and exact ChatGPT client grant. Keep
   Client Review and Google reporting regressions healthy.
4. Test loopback health, exact Host handling, OAuth metadata, anonymous 401 and
   forbidden identities before enabling this vhost. Verify TLS and the same
   behavior publicly. Do not interpret HTTP health as authenticated readiness.
5. Connect privately in ChatGPT, perform a real staff login and test refresh,
   an outsider, explicit revocation, automatic discovery and an exact-site read.
   Use the service's durable requester audit; never log bearer tokens.
6. Publish to the MRN workspace after these checks. An ordinary staff member
   installs the plugin and signs in without terminal, Codex or site enrollment.
7. Verify consistent SQLite state backup/restore and supervision before calling
   hosted acceptance complete. Enabling repair/release remains a later controlled
   qualification with backup and coordinated-writer gates.

## Activation record — October 8, 2026

- All 100 Operations tests passed again on MRN Apps Node 24.15.0. Both GitHub
  checks passed on the exact deployed candidate.
- Hosted MainWP preflight: connected to `wpcontrol.mrndev.io`, 84 abilities,
  zero site calls and zero writes. Credentials were privately provisioned from
  the approved MRN business vault into a root-only environment file.
- Action `5ae7b65c-3898-483c-b280-2a1f7ecc09ee` is deployed and attached after
  the existing Client Review and Google Reporting Login Actions.
- Existing public OAuth client `R0GQ8FV2XEOD3T8Ym1bkLF4pl9OUEprl` received only
  `mrn:operations` for this API. Machine access remains denied. Existing callbacks
  were preserved and `https://chatgpt.com/connector/oauth/MbAPpAv-mkG3` appended.
  ChatGPT uses token auth method `none`, PKCE and base scopes `openid`, `email`,
  `offline_access`; no client secret is copied into ChatGPT.
- ChatGPT plugin `plugin_asdk_app_6ac7db7356f88191b866093a01c368f2` is enabled and
  available to everyone in workspace `23322f52-5246-4432-b36a-668ec67a1a94`.
  Each person uses their own verified MRN account. No shared workspace connection
  was created. Public Sites visitor use is off; new tools require review.
- Exactly four tools are enabled: `list_websites`, `inspect_website`,
  `get_operation`, `website_history`. Inspection is classified as a write in
  ChatGPT because it saves an evidence record; website-changing tools are off.
- Auth0 sign-in and ChatGPT tool discovery succeeded. Settings shows Kyle's
  connected Operations account, and the plugin appears in the chat mention menu.
  The directory continued displaying Install after connection; Settings is the
  authoritative connection evidence. Conversational acceptance is recorded in
  `../VALIDATION.md`.
- A consistent SQLite API backup at
  `/var/backups/mrn-operations/acceptance-20261008.sqlite` passed integrity checks
  and restoration into an isolated copy. A second populated snapshot,
  `acceptance-populated-20261008.sqlite`, restored one inspection and 61 audit
  records successfully. This does not establish scheduled off-host backup coverage.

[Team installation](https://chatgpt.com/plugins/plugin_asdk_app_6ac7db7356f88191b866093a01c368f2?account_id=23322f52-5246-4432-b36a-668ec67a1a94)

Conversational acceptance passed on MRN's own exact website: discovery of 107
records, one-site synchronization, public measurements and a saved inspection.
The first attempt guessed production despite the discovered environment being
unknown; retrying the discovered target without that guess succeeded. Tool input
guidance now explicitly forbids that inference. No permission matching was relaxed.

Outstanding acceptance: token-expiry refresh, non-admin staff installation, real
outsider identity and live revocation. These denial/revocation behaviors pass
controlled tests. Scheduled off-host state backup coverage is not established.
Repair workflows remain disabled and unqualified.
