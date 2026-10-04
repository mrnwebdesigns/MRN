# Existing-site simplification

New installations use `stack/manifests/plugins.txt`. Existing sites need a reviewed migration; installing a new Stack package does not authorize deleting site plugins or data.

## Duplicate editors

`retire-duplicate-editors.php` removes the four redundant editors from the active plugin set: SEO Helper, ACF Character Count, AI Assist and Editor Enhancements. It preserves their files, options, tables, original post metadata and Schema Bridge overrides. Native SEOPress receives missing title, description and target-keyword values. A differing or ambiguous native value stops the entire apply for review.

1. Qualify the exact site through MainWP, refresh its inventory, and confirm both native SEOPress plugins are active. Complete SEOPress's supported SmartCrawl import and independently verify titles, descriptions, canonical/robots, social data, taxonomy metadata, redirects, schema and sitemap parity before retiring the former provider. This script deliberately refuses an active SmartCrawl installation.
2. Run `wp eval-file <script> plan`. Review the exact home URL, deactivations, metadata count, conflicts and SHA-256. The plan is read-only and its hash includes the plugin versions, active set and inspected metadata.
3. Immediately before a remote mutation, use the approved deployment workflow to create and verify a labeled database-only Updraft backup at its configured remote destination. Preserve the backup receipt with the migration record. Local Hub qualification fixtures are exempt. The PHP script does not manufacture a backup receipt or bypass this gate.
4. Run `wp eval-file <script> apply <reviewed-sha256>`. A changed plan, pre-existing journal or unresolved lock refuses apply. Real deactivation hooks stop the AI schedulers. A non-autoloaded database journal is stored before any mutation; incomplete outcomes retain their lock.
5. Verify the page editor and General Settings: native SEO remains editable, **SEO Helper Content Types** and **SEO & Schema** are absent, and the published metadata/schema match the baseline. Check any site-specific editor dependencies. Purge only the relevant HTML/object cache through the site's normal workflow.
6. If necessary, obtain another fresh remote backup and run `wp eval-file <script> rollback <original-sha256>`. Rollback refuses metadata changed since apply, removes only migration-added values, and restores the original active plugins. It restores activation state silently so SEO Helper's bulk activation seed cannot overwrite unrelated metadata. The original content is never removed. Journals remain for audit; do not delete an uncertain lock automatically.

The Schema Bridge 0.7.0 code removes its editor panel and save handler while retaining historical schema readers. It does not silently translate or erase page intent, schema mode or description overrides.

## Site-specific provider cutover

Analytics, consent, breadcrumbs and mail have additional site-owned inputs. Preserve their baseline and record their own cutover/rollback receipts; the editor script does not infer them.

- **Analytics/consent:** SEOPress owns native GA4 and GTM insertion. Confirm the correct properties/container and published GTM configuration without a duplicate GA4 configuration tag. Migrate approved banner text and privacy destination, verify first visit/accept/reject/reload/change-choice behavior with the real cache path, and then deactivate MRN GTM Injector and MRN Cookie Consent. Ecommerce acceptance includes real GA4 receipt plus amount/items/currency and duplicate/reload coverage. Retain historical settings and source. These checks do not impose a new zero-network-after-withdrawal policy.
- **Breadcrumbs:** select `provider=seopress`, enable SEOPress visible breadcrumbs and JSON-LD, and preserve MRN placement/style/menu/WooCommerce context. Compare category entry versus direct product navigation, primary category and schema/visible trail parity. Review legacy manual overrides before switching. New-site bootstrap sets the native provider; existing sites must pass their own trail comparison.
- **Mail:** install/configure Post SMTP with the site's approved provider and sender, verify delivery through an approved test route, then deactivate FluentSMTP. Do not copy provider passwords into source, logs or migration reports. Mail transport stays inactive on new development bootstraps until configured.
- **Comments/reviews:** retain existing protection until Enterprise key/domain/assessment access and real submission behavior pass. New support is opt-in. A mocked Google test is not production assessment permission.
