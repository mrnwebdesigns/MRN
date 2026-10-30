# Mail, media and security defaults

Owner decision, 2026-10-30: replace Post SMTP with FluentSMTP; add Converter for Media and Wordfence. Wordfence Free is the baseline, with Premium supported per site. This supersedes the mail choice in the October simplification and the fallback-only Wordfence policy. SendGrid Provisioning remains retired; choosing FluentSMTP does not choose or provision an email provider.

## Source status

The new-site manifest pins FluentSMTP 2.4.1, Converter for Media 6.7.0 and Wordfence 9.0.2. Their official WordPress.org ZIP hashes are recorded in the bootstrap input lock. The candidate has 36 Stack / 35 plain plugins. Source acceptance, merged-source qualification/publication and site rollout remain separate gates. No existing site, provider credential, MainWP default or Cloudflare rule changes just because these source files change.

## Wordfence and MainWP

| Setting | Cloudflare-proxied site | Site without Cloudflare |
| --- | --- | --- |
| Preset | `configs/wordfence/cloudflare.json` | `configs/wordfence/standalone.json` |
| Application WAF | Keep enabled; review learning and optimize per host | Keep enabled; review learning and optimize per host |
| Scheduled malware/vulnerability scans | Enabled, low-resource mode | Enabled, low-resource mode |
| Visitor IP | Vendor recommended automatic detection until verified | Vendor recommended automatic detection until verified |
| Broad traffic rate limits | Owned by Cloudflare | No blanket human/global throttle; crawler limit 240/minute and 404 limits 60/minute |
| Login failures / password reset attempts | 20 within 60 minutes; 60-minute lockout | 10 within 60 minutes; 60-minute lockout |
| Live Traffic | Security events only; cap 1,000 rows / seven days | Same |
| Alerts | Scan issues and disabled security controls; suppress routine block/login noise | Same |
| API access | Preserve approved WordPress Application Passwords | Same |
| Deactivation | Retain security configuration and data | Same |

Wordfence Free runs daily quick scans and full scans every 72 hours. Custom scheduling and immediate new firewall rules/malware signatures require Premium. Licenses are site-specific; never clone a key into the shared preset. Keep vendor firewall rules active in both profiles. Cloudflare cannot scan WordPress files, and its edge controls do not replace application protection.

The standalone numbers are initial conservative defaults and must be tuned against forms, searches, crawlers and legitimate traffic. Do not apply them to an existing store or integration without its normal smoke tests. A site using a non-Cloudflare host proxy still needs proxy-specific IP verification.

The bootstrap defaults to the Cloudflare preset for the usual CloudPanel path. Set `STACK_WORDFENCE_PROFILE=standalone` before bootstrapping a confirmed non-Cloudflare site. The helper is only called on the fresh-install path, not the already-bootstrapped credential reconciliation path. It validates the exact host, installed Wordfence version and all setting keys through the vendor API, saves changed keys and reads them back. It never sets a license, changes allowlists, accepts terms, forces a WAF mode, restarts learning or optimizes server files.

For a separately approved existing-site change, first create and verify the labeled remote database backup, then preview against the exact site's host:

```bash
wp eval-file /path/to/stack/scripts/configure-wordfence.php cloudflare example.org preview
# After reviewing the preview under that site's deployment authorization:
wp eval-file /path/to/stack/scripts/configure-wordfence.php cloudflare example.org apply
```

These files are Wordfence vendor settings presets, **not MainWP import/export payloads**. The MainWP Wordfence extension belongs on the Dashboard; Wordfence Security belongs on each managed child. Verify that the Dashboard extension is installed, active, enabled and licensed through MainWP Pro before declaring central management ready. The 2026-10-30 authenticated inspection found extension 5.2.1 enabled with working management/options screens, but its add-on license was marked unactivated. The current global options show no alert recipient and enable deletion of Wordfence data on deactivation; resolve those before using global defaults for enrollment. No settings were saved during this inspection.

MainWP has one network-default configuration and per-site overrides; it does not provide two independent network defaults. Use the appropriate complete preset with **Override general settings enabled on each enrolled site**, then read back its options after a targeted sync. Never save a Cloudflare-specific IP choice as a global default: saving a MainWP section syncs it to every child without individual overrides. Existing child-site settings and extension behavior must be inspected before any save. The current MCP tool catalog exposes no Wordfence settings operation; use the supported authenticated Dashboard UI when needed, under exact-site authorization. Do not use Code Snippets to bypass a blocked operation.

Before launch, verify:

1. The hostname really is Cloudflare-proxied (DNS-only Cloudflare hosting is not enough). Confirm the real visitor IP in Wordfence diagnostics. If the host restores it into `REMOTE_ADDR`, use that. Select `CF-Connecting-IP` only with a verified trusted proxy path and protection against spoofed direct-origin requests. Never bypass all rules for Cloudflare's entire IP range.
2. Complete that site's free license/terms or its existing Premium enrollment. Do not copy keys into source.
3. Review learning results and legitimate traffic before enabling full blocking. Optimize the WAF using the host-supported method, with file backup/recovery for `.user.ini`, `.htaccess` or equivalent changes; a database backup does not cover these files.
4. Enroll administrators in 2FA with a recovery path before enforcing it. Preserve approved machine-to-machine authentication; test MainWP sync and REST API access.
5. Set the authorized alert recipient and verify delivery separately. Confirm scan completion, admin login, forms, REST, cron and any store/integration paths.
6. Verify the MainWP extension reads the correct per-site settings and does not overwrite the selected preset on later saves/syncs.

## FluentSMTP cutover

New development bootstraps install FluentSMTP without activating it, including the later activate-remaining-plugins pass. No provider, key, sender address or live test send is provisioned by this change.

For an existing site, preserve the current provider, verified From address/name, Reply-To behavior, connection/routing rules and DNS authentication. Back up first. Install FluentSMTP inactive and configure its connection using the approved secret path. Native Postmark API transport is supported when Postmark is the site's provider; other providers remain valid. Do not claim an automatic Post SMTP import: settings, OAuth and logs are not interchangeable.

During the authorized cutover, deactivate Post SMTP before activating FluentSMTP, so only one `wp_mail` transport runs. Use an explicitly approved test recipient/form and verify the provider result plus recipient acceptance; saved configuration alone does not prove delivery. Retain inactive Post SMTP settings and logs for rollback. If verification fails, deactivate FluentSMTP and restore the prior transport. Do not uninstall either plugin or delete its data during the first pass. Review message-body logging and retention for the site's privacy needs.

## Converter for Media

Use native WebP output and automatic conversion of new uploads. Preserve original files, attachment records, alt text and stable content URLs. AVIF is an optional paid feature; do not enable it without the site's license. Do not run two image conversion/delivery systems on the same site without a verified compatibility plan.

Before bulk conversion, verify GD/Imagick WebP support, writable output space, host rewrite support, backups of the relevant files and enough free disk for derivatives. Nginx may require host-specific server configuration; LiteSpeed/Apache must also pass the plugin's rewrite diagnostics. Installation is not delivery verification.

Convert a small representative sample first. Check actual image response bytes and Content-Type, original fallback, responsive sizes, transparency, visual quality and cache behavior through the public hostname. Review Cloudflare caching with modern and fallback Accept headers; do not add broad cache bypasses or global `Vary` changes without testing. Once accepted, run bulk conversion in controlled batches. Leave quality at the vendor's native Optimal setting unless the sample warrants a deliberate change.

## Primary references

- [FluentSMTP installation and single-mailer guidance](https://fluentsmtp.com/docs/installing-fluent-smtp/)
- [FluentSMTP Postmark setup](https://fluentsmtp.com/docs/configure-postmark-in-fluent-smtp-to-send-emails/)
- [Converter for Media formats, originals and Nginx requirements](https://wordpress.org/plugins/webp-converter-for-media/)
- [MainWP Wordfence integration and per-site overrides](https://mainwp.com/add-on/wordfence/)
- [Wordfence visitor-IP detection](https://www.wordfence.com/help/dashboard/options/)
- [Wordfence Free limitations](https://www.wordfence.com/help/wordfence-free/)
- [Wordfence scan scheduling](https://www.wordfence.com/help/scan/scheduling/)
