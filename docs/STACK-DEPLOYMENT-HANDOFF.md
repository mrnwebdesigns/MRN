# Stack deployment task handoff

Use this handoff after the owner has created and accepted a new test site from
`2026.10.08-fleet-readiness-r1`. Record that site's exact URL and its test results
before starting existing-site adoption. This file does not authorize site writes.

## Stack deliverable

- The executable plugin manifest selects 33 standard plugins for `stack`, or 32
  for `plain`; only Reusable Block Library is profile-specific.
- SEOPress Free/PRO 10.3 replaces SmartCrawl and redundant custom SEO/editor
  defaults. Schema Bridge and Business Information remain. Historical metadata
  is preserved; replacing code alone does not migrate an existing site's data.
- Post SMTP 4.0.2 replaces FluentSMTP in defaults. Development mail transport is
  inactive; provider selection and real mail delivery need site-specific setup.
- SendGrid provisioning/key delivery and Config Helper integration are retired.
  Existing mail services, credentials, DNS and saved settings remain untouched.
- Background Video Pop-Out Disabler is optional and excluded from bootstrap.
- reCAPTCHA remains on qualified 0.1.4. Its accepted 0.2.2 pilot source and the operations
  service are separate releases. The immutable component transport prototype
  is excluded from this bootstrap and is not a prerequisite for owner testing.
- Parent 1.5.8 retains After Content, ACF performance and Text Swipe, and fixes
  drawer action palette precedence. Agent 0.3.2 is a separately seeded prerequisite.
  Trilliant site qualification is explicitly deferred and is not an approved
  remote target in this release preparation.
- Plugin package identities are in `stack/manifests/bootstrap-packages.lock.json`.
  Platform sources, theme pair and MU hashes are in `stack-release.lock.json`.
  Recovery material is indexed in `retired-capabilities.json` and the restoration
  runbook. Preserve these records and archives.

## Copy-ready prompt for the deployment task

```text
Continue from MRN Stack release 2026.10.08-fleet-readiness-r1 after the owner
supplies the accepted new-site test URL and results. Read
stack/BOOTSTRAP_RELEASE.md, both current release/package locks,
docs/STACK-SIMPLIFICATION-2026-10.md, stack/RESTORING-RETIRED-CAPABILITIES.md,
and the final bootstrap release receipt. Confirm the exact merged source,
package SHA-256 values and hosted installer publication receipt before planning.

Treat this as deployment/adoption work. Do not redesign the Stack, enable
CAPTCHA 0.2.2, install the immutable component prototype, or host the operations
service as an incidental dependency. Report an actual blocker before proposing
a source change in the dedicated Stack task.

Start read-only. Obtain the exact rollout targets and environments from the
owner. Follow the canonical MainWP REST v2/MCP route, exact-domain resolution
and targeted fresh sync. Inventory the active parent/child, plugins/MU hashes,
SEO provider and metadata, schema, tracking/consent ownership, mail provider,
managed credentials, backups, and site-specific integrations.

Produce one explicit, reversible plan per site. Keep child themes and content
site-owned. Use the guarded retirement migrations where applicable; do not
equate plugin removal with successful SEO/consent/mail migration. Preserve old
plugin packages, settings and metadata, and refuse ambiguous conflicts.

Before each authorized development/staging/production runtime write, create
and verify the required labeled database-only Updraft remote backup. Qualify
the exact agent separately when required. Apply only the accepted plan, then
verify actual plugin/version/hash readback, frontend and Classic Editor,
SEOPress metadata/schema/sitemaps, consent/tracking, mail delivery, backups,
accessibility/performance and rollback readiness.

Report source readiness, hosted publication, individual-site adoption and fleet
parity separately. Stop on unknown drift, failed backup, unsupported theme
shape or failed verification. Do not start a fleet-wide operation from an empty
site list. No blanket authorization to modify existing sites is implied here.
```

## Owner new-site acceptance

Verify the exact 33-plugin inventory (32 active in development because Post SMTP
is inactive), the active child/parent pair, all required MU components and no
legacy collisions. Confirm native SEOPress fields and the absence of the retired
SEO Helper Content Types and SEO & Schema panels. Save/reload content through
Classic Editor/ACF, inspect the public page, sitemap and schema, and check license
and managed-credential presence without exposing values. Test real backup storage,
reCAPTCHA assessment and mail delivery only within the selected environment's
policy. Record any warnings before deciding on existing-site rollout.
