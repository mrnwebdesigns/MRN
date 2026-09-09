# MRN Cloudflare Security Policy

## Decision

Cloudflare is MRN's default security control plane for sites where MRN can manage the authoritative DNS zone. Cloudflare provides the edge WAF, TLS termination, bot and abuse controls, DNS protection, security events, and launch-time verification boundary.

Wordfence is the approved fallback when a site cannot be placed in the MRN Cloudflare account or when the site's host, registrar, contract, or application requirements prevent safe Cloudflare proxying. Do not install and activate Wordfence as a default companion to Cloudflare. Running two independent firewalls creates duplicated controls, noisy alerts, and harder incident response.

This policy complements the [DNS migration policy](https://docs.google.com/document/d/1wDrYsLlt-LVf1hnJgBtYk2qeshony-wtOVfIkTOlN1o/edit) and does not replace the MRN backup, deployment, or QA gates.

## Profiles

The stack carries two declarative profiles:

- [`configs/cloudflare/security-development.json`](configs/cloudflare/security-development.json): development and review defaults. Protection is enabled, but disruptive bot controls and restrictive admin rules are deferred until verified.
- [`configs/cloudflare/security-production.json`](configs/cloudflare/security-production.json): production defaults. Managed WAF, strict TLS, alerting, rate controls, and DNSSEC expectations are enabled or required when the account and registrar support them.

These files are policy inputs, not secrets and not blindly executable API payloads. The setup operator must translate them into the current Cloudflare API schema, verify each response, and record any account-plan limitation or site exception.

The profile vocabulary was reviewed against Cloudflare's official documentation on 2026-09-06. Cloudflare feature availability is plan-specific: Free provides the Free Managed Ruleset and Bot Fight Mode, Pro and Business can use broader Managed Rules and Super Bot Fight Mode, and Bot Management is an Enterprise add-on. Apply the strongest profile-compatible control available to the resolved account plan; never substitute an unavailable product or silently weaken the profile.

Official baseline references:

- [Full (strict) SSL/TLS](https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/)
- [Minimum TLS Version](https://developers.cloudflare.com/ssl/edge-certificates/additional-options/minimum-tls/)
- [WAF plan availability and setup](https://developers.cloudflare.com/waf/get-started/)
- [Bot protection plan differences](https://developers.cloudflare.com/waf/feature-interoperability/)
- [DNSSEC activation and migration safety](https://developers.cloudflare.com/dns/dnssec/)

Validate both profile files before committing or using them:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-cloudflare-security-policy.sh
```

## Required Cloudflare configuration

For every Cloudflare-managed site:

1. Resolve the intended MRN Cloudflare account and zone. Never select a zone solely because it is visible.
2. Audit the existing authoritative DNS before creating or changing records. Preserve web, mail, verification, vendor, certificate, and hosting records.
3. Create the zone with the Cloudflare quick scan disabled when deterministic record recreation is possible.
4. Keep MX, TXT, CAA, mail, autodiscover, cPanel, webmail, FTP, and SSH records DNS-only.
5. Use `Full (strict)` only after origin HTTPS has been verified. Do not treat certificate dwell time as proof of readiness.
6. Proxy only public web records after origin and application smoke checks pass.
7. Enable the managed WAF rulesets available to the resolved account plan and review security events after activation.
8. Apply the profile's login rate-control rule. Block XML-RPC only when the site has no verified XML-RPC dependency.
9. Do not block `/wp-admin/` or broad `/wp-json/` traffic without a site-specific access and integration test.
10. Enable alerting for WAF spikes, origin health, and DNS/SSL failures where the account plan supports each alert type.
11. Before changing nameservers, inspect existing registrar DS records and either use a supported active DNSSEC migration or remove the old delegation and wait for its TTL to expire. After the Cloudflare zone is active, enable DNSSEC in production when the registrar can safely publish the resulting DS record. DNSSEC is incomplete until both Cloudflare and the registrar show the matching state.

## API access

Use a dedicated, narrowly scoped Cloudflare API token. The token must be stored in MRN's approved secret manager and must not appear in prompts, logs, commits, screenshots, or generated configuration files.

The operator should request only the permissions required for the selected task:

- Zone read
- Zone DNS edit
- Zone settings edit
- Zone WAF/rulesets edit, when configuring WAF rules
- Zone analytics read, when verifying events
- Account zone create, only when the task includes creating a zone

Registrar nameserver changes are a separate production mutation. A Cloudflare API token does not automatically authorize a registrar change. Pause for explicit approval unless an approved registrar API workflow is in scope and independently authorized.

## Development versus production

Development sites receive the development profile, remain clearly marked as non-production, and must not send unapproved real client mail. Development may use less disruptive bot behavior and may defer DNSSEC, but it still receives HTTPS, managed WAF protection, DNS record preservation, and security-event verification.

Production sites receive the production profile. Before launch, MRN must verify DNS, TLS, WAF behavior, forms, mail, admin access, uptime monitoring, sitemap/robots behavior, and rollback information. A production launch is not complete merely because the nameservers show Cloudflare.

## Wordfence fallback

Use Wordfence only when Cloudflare cannot be safely used for that site. Record the reason in the site's deployment notes. The fallback configuration is:

- Development: firewall enabled in learning mode, scans enabled, administrator 2FA encouraged, low-noise notifications, and no destructive blocking until legitimate traffic is observed.
- Production: firewall optimized after a learning period, administrator 2FA required, brute-force protection enabled, daily malware/vulnerability scans, update/security notifications enabled, and XML-RPC disabled only when the site does not require it.
- If Cloudflare is later adopted, disable or remove Wordfence after verifying that the Cloudflare profile is active. Do not leave both systems making overlapping block decisions without a documented exception.

Wordfence does not replace the MRN backup gate, QA, patching, least-privilege administration, or server hardening.

## Launch acceptance

A site passes the security portion of launch when:

- the selected security path is recorded as `cloudflare` or `wordfence`;
- the correct account, zone, profile, and environment are recorded;
- no unresolved DNS, mail, TLS, WAF, or origin-health exception remains;
- the security controls were verified from outside the origin network;
- the required MRN backup and QA gates passed;
- the owner knows the rollback nameservers or fallback procedure; and
- monitoring and post-launch ownership are assigned.

## Reusable AI/API setup prompt

Use the following prompt with an AI operator that has an approved Cloudflare API connector. Supply the site domain and environment separately; never paste the API token into the prompt.

```text
You are the MRN Cloudflare launch operator. Configure one WordPress site using the MRN Cloudflare Security Policy and the appropriate profile below.

Inputs:
- Domain: <domain>
- Environment: <development|production>
- Cloudflare account: MRN account, resolved from current authorized state
- Existing DNS provider/registrar: <provider>
- Origin hostname or IP: <origin>
- Required vendor/mail services: <list>
- Profile file: stack/configs/cloudflare/security-<environment>.json

Security and authorization rules:
1. Use only the approved Cloudflare API connection. Never print, echo, persist, or expose the token.
2. Resolve the account and zone by current API lookup and prove the zone belongs to the intended MRN account before any write.
3. First perform a read-only audit: current nameservers, SOA, A, AAAA, CNAME, MX, TXT, CAA, DKIM selectors, DMARC, MTA-STS, BIMI, autodiscover, mail, webmail, cPanel, FTP, SSH, wildcard DNS, and certificate/SSL state.
4. Produce a complete proposed record table and identify ambiguous or missing records. Do not overwrite SPF, DKIM, DMARC, verification, or vendor records.
5. Create the Cloudflare zone only if it does not exist. Prefer deterministic manual record creation over quick-scan import. Preserve the old DNS provider and do not delete anything.
6. Create records from the verified inventory. Keep mail, verification, CAA, hosting-control, and SSH records DNS-only. Proxy only public web records after origin HTTPS is verified.
7. Apply the selected development or production profile: Full (strict), HTTPS redirect, minimum TLS 1.2, TLS 1.3, managed WAF, appropriate bot behavior, login rate control, XML-RPC rule only when dependency-free, and alerting supported by the account plan.
8. Do not block wp-admin or broad wp-json traffic without a site-specific access and integration test. Do not invent firewall expressions; validate each expression against the current Cloudflare rulesets API.
9. If DNSSEC is required by the production profile, enable it and report the exact DS record. Do not claim DNSSEC is complete until the registrar DS record matches.
10. Verify the Cloudflare nameservers, zone status, DNS answers from independent resolvers, HTTPS certificate chain, redirects, origin health, WAF events, WordPress admin, forms, outbound mail, inbound mail, and vendor integrations.
11. Treat the registrar nameserver change as a separate production mutation. Stop and request explicit human approval immediately before changing nameservers unless an independently approved registrar API operation is explicitly provided.
12. After approval, change only the intended nameservers, monitor propagation, retain the old provider for rollback, and do not deprovision it for at least 30 days.
13. Return a concise change report containing: account and zone IDs, records changed, proxy states, profile settings, WAF/rate rules, DNSSEC state, verification results, warnings, rollback nameservers, and the exact next owner action. Never include secrets.
```

## Ownership and review

The stack owner maintains the profiles and this policy. Site owners approve registrar and production changes. The operator records exceptions and verification results in the site's deployment notes. Review the profiles whenever Cloudflare changes API behavior, account-plan capabilities, or MRN's WordPress launch contract.
