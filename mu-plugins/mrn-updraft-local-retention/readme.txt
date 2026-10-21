=== MRN Updraft Backup Policy ===
Contributors: mrnwebdesigns
Stable tag: 0.8.0
Tested up to: 7.1
Requires PHP: 7.4

Shared MRN must-use plugin for UpdraftPlus Premium backup policy enforcement.

== Description ==

Enforces daily staging/production backups, tiered remote retention, local
cleanup, complete wp-content coverage, and isolated S3 destination checks.
Supports the shared Stack loader or the standalone MU bootstrap. See README.md
for configuration, prerequisites, domain cutover, and verification details.

== Changelog ==

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
