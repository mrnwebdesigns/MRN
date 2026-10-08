# MRN Apps delivery

The owner selected this host and approved all verified `@mrnwebdesigns.com`
staff on October 8, 2026. This record is a prepared deployment, not a live release.

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

## Prepared configuration

Use `mrn-apps.service.json`, `mrn-apps.permissions.json`, the existing systemd
unit and `nginx.conf.example`. Install configuration under `/etc/mrn-operations`
with operator ownership and service read access, code under
`/opt/mrn-operations/MRN`, and private state under `/var/lib/mrn-operations`.
Use a separate `mrn-operations` system account. The initial service discovers
MainWP automatically; laptop Local Hub records are not mounted on this server.

The service URL/audience is proposed as
`https://operations.mrnwebdesigns.com/mcp`. The hostname has not been provisioned.
Live Cloudflare lookup confirmed the active `mrnwebdesigns.com` zone belongs to
the MRN Web Designs account and has no existing record at this exact hostname.
Use only an exact-host DNS record in the verified `mrnwebdesigns.com` zone.
Preserve the zone's current SSL and security policy. Issue this hostname's own
certificate using the host's established ACME method, validate the new vhost and
reload Nginx only after local health and authentication checks pass.

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
Auth0's current API list has Client Review and Google Reporting; no Operations
API is registered. The administration session is available in the browser.

Prepare a separate API named **MRN Website Operations** with:

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

Auth0 administration currently requires the authenticated UI; no management API
credential was found in the mapped provider item. Confirm the concrete access
change at the browser's grant/publish action. One separate CLI lookup of the
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

At this preparation point no service, DNS, certificate, Auth0 API/Action/grant or
ChatGPT plugin was changed or published. No client site was synced or modified.
