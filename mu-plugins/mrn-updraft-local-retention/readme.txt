=== MRN Updraft Backup Policy ===
Contributors: mrnwebdesigns
Stable tag: 0.8.10
Tested up to: 7.1
Requires PHP: 7.4

Shared MRN must-use plugin for UpdraftPlus Premium backup policy enforcement.

== Description ==

Enforces daily staging/production backups, tiered remote retention, local
cleanup, complete wp-content coverage, and isolated S3 destination checks.
Supports the shared Stack loader or the standalone MU bootstrap. See README.md
for configuration, prerequisites, domain cutover, and verification details.

== Changelog ==

= 0.8.10 =
* Correct the static-analysis return contract for the existing provider/settings tuple; runtime behavior is unchanged.

= 0.8.9 =
* Apply the owner-approved content-only exception for PHP-configured wp-admin/php_errorlog; preserve full diagnostic hashes and log presence.
* Refuse unsafe log paths and retain literal hashing when native configuration is unproven.

= 0.8.8 =
* Report bounded core-relative file/hash changes between authenticated preflight snapshots through existing backup-policy inspection.
* Keep complete fingerprints and every maintenance gate unchanged; fix the reported policy version.

= 0.8.7 =
* Retain bounded preflight phase checkpoints across request timeouts, without changing update validation or execution.

= 0.8.6 =
* Preserve activation during guarded maintenance and report each failed preflight or execution stage.
* Retain conservative mutation evidence for incomplete historical operation records.

= 0.8.5 =
* Advance a batch only after verifying the completed item's requested version.
* Preserve activation, theme selection, core and unrelated code between updates.

= 0.8.4 =
* Classify download refusals using fixed network, TLS, HTTP and size codes only.
* Preserve download limits and verified HTTPS, refresh file size after streaming.

= 0.8.3 =
* Initialize verified TLS and the native S3 bucket region before scoped backup proof.
* Expose bounded, secret-free preflight refusal codes in the existing runtime report.

= 0.8.2 =
* Accept normal ZIP directory entries while still rejecting aliases, traversal and unsafe types.

= 0.8.1 =
* Forward the fixed MRN label into new native MainWP backups before they start.

= 0.8.0 =
* Add closed, version-pinned plugin/theme maintenance through authenticated MainWP.
* Independently verify completed remote Updraft DB-only backups before replacement.
* Preserve activation, reject stale state, and retain once-only operation records.

= 0.7.0 =
* Add guarded MainWP operations for policy inspection, application, and exclusion.
* Preserve historic remote backup objects when disabling a site's destinations.
* Verify database-only pre-deployment backups before policy or cleanup writes.

= 0.6.0 =
* Retain seven daily, four weekly, and twelve thirty-day recovery points.
* Expire routine history after 395 days with numerical file/database caps of 23/100.
* Require active Premium retention and report non-secret policy compliance.
* Validate every active S3 instance against the full approved hostname.
* Preserve daily staging schedules and support stable identity during domain cutover.
* Include plugins, themes, uploads, MU plugins and other content, excluding WordPress core.
