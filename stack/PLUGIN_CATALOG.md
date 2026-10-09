# MRN WordPress Component Catalog

Last reconciled: 2026-10-05

This is the human-readable index of MRN-owned WordPress components. The authoritative machine-readable source is [`manifests/component-catalog.json`](./manifests/component-catalog.json), and the rules governing it are in [`PLUGIN_GOVERNANCE.md`](./PLUGIN_GOVERNANCE.md).

Catalog inclusion means that MRN owns, supports, is evaluating, or is deliberately retaining the component. It does **not** mean the component should be installed on every website.

The exact new-site selection and package versions are listed in [BOOTSTRAP_RELEASE.md](BOOTSTRAP_RELEASE.md) and `manifests/bootstrap-packages.lock.json`: 33 standard plugins for Stack, 32 for plain. Source candidate versions may differ from the qualified bootstrap package, notably reCAPTCHA 0.2.0 versus 0.1.4.

`Current distribution` describes today's stack behavior. `Target tier` is the approved Phase 1 classification and does not itself change bootstrap, packaging, activation, deployment, or MU loading.

## Production Platform

| Slug | Version | Current distribution | Target tier | Responsibility |
| --- | ---: | --- | --- | --- |
| `mrn-loader` | 1.6.1 | MU loader | Platform required | Loads approved MU component entrypoints and exposes the signed runtime report transport. |
| `mrn-admin-data-post-types` | 0.2.1 | MU loader | Platform required | Applies shared admin/data-only post-type policy and SEOPress post-type discovery exclusions. |
| `mrn-admin-ui-css` | 3.2.4 | MU loader | Platform required | Provides shared WordPress admin presentation and usability rules. |
| `mrn-dashboard-support` | 1.3.0 | MU loader | Platform required | Provides MRN support information, dashboard metadata, and the admin-only Notifications Center with unread/read views, per-admin read/remove controls, and centralized admin notices. |
| `mrn-disable-comments` | 1.2.5 | MU loader | Platform required | Enforces the MRN no-comments policy. |
| `mrn-editor-lockdown` | 1.0.33 | MU loader | Platform required | Applies shared editor, metabox, and capability policy. |
| `mrn-environment-runtime` | 0.6.0 | MU loader | Platform required | Provides environment, runtime, and notification diagnostics. |
| `mrn-public-security-hardening` | 0.4.3 | MU loader | Platform required | Applies shared public REST and discovery hardening and preserves AME decisions for the native Advanced admin menu. |
| `mrn-shared-assets` | 0.2.1 | MU loader | Platform required | Provides shared asset and icon interfaces, including consumer-aware ACF admin asset detection. |
| `mrn-site-colors` | 0.1.39 | MU loader | Platform required | Owns persistent site design tokens and CSS-variable output. |
| `mrn-updraft-local-retention` | 0.6.1 | MU loader | Platform required | Enforces seven daily, four weekly, and twelve 30-day remote recovery points, uses a stable cross-environment S3 site slug, exposes MainWP compliance evidence, and routes backup-policy warnings to Notifications Center. |
| `mrn-schema-bridge` | 0.7.0 | MU loader | Platform required | Normalizes provider and theme schema behavior, including automatic Article and visible-content JobPosting contracts. |
| `mrn-active-style-guide` | 0.1.7 | MU loader | Platform required | Logged-in design-system reference and diagnostics. |
| `mrn-config-helper` | 0.1.72 | Standard bootstrap | Platform required | Provides the shared site configuration shell, native SEOPress breadcrumb integration and launch/admin integrations; retired SendGrid and GTM Injector controls and synchronization are excluded. |
| `mrn-media-bulk-tools` | 0.13.1 | Standard bootstrap | Platform required | Media audit, usage indexing, and bulk maintenance; exact version and tree are enforced by the full Stack release lock. |
| `mrn-stack-deployment-agent` | 0.2.6 | Standard bootstrap | Platform required | Provides the MainWP-authenticated, checksum-verified Stack deployment target, exact child-theme preservation, and environment-aware secret-free managed-credential readiness. |
| `mrn-universal-sticky-bar` | 1.1.10 | Standard bootstrap | Platform required | Provides the shared settings/editor action bar; independently released for non-Stack use. |

The machine catalog also records each tracked top-level MU wrapper as its own required loader artifact. Wrapper versions mirror the components they load, and `deployed_path` preserves the actual WordPress filename rather than deriving one from the catalog slug.

## Optional Shared Features

| Slug | Version | Current distribution | Target tier | Responsibility |
| --- | ---: | --- | --- | --- |
| `background-video-popout-disabler` | 1.0.2 | Catalog-only; retained for deliberate use | Optional shared | Optional front-end helper that suppresses browser picture-in-picture/pop-out controls on likely background videos. Excluded from new-site bootstrap; existing installations are unchanged. |
| `mrn-ai-assist` | 2.0.14 | Catalog-only; retained for deliberate use | Optional shared | Queued AI-assisted content, SEO, and media-alt workflows. |
| `mrn-announcements` | 1.8.2 | Standard bootstrap | Optional shared | Scheduled and targeted announcement bars and modals. |
| `mrn-editor-tools` | 1.8.25 | Catalog-only; retained for deliberate use | Optional shared | Classic Editor, TinyMCE, and ACF WYSIWYG enhancements. |
| `mrn-mega-menu` | 0.17.2 | Standard bootstrap | Optional shared | Accessible content-rich mega-menu administration and rendering. |
| `mrn-reusable-block-library` | 0.2.0 | Standard bootstrap | Optional shared | Shared reusable block content types and render helpers, including sanitized outer-layout classes. |
| `mrn-tokens` | 0.1.4 | Standard bootstrap | Platform required | Reusable content-token registry, shortcode, and authenticated REST API; required in bootstrap and full Fleet releases. |

## Optional Integration Adapters

| Slug | Version | Current distribution | Target tier | Integration |
| --- | ---: | --- | --- | --- |
| `mrn-acf-character-count` | 1.1.9 | Catalog-only; retained for deliberate use | Optional integration | ACF editor character counts with field-aware asset loading. |
| `mrn-acf-focal-point` | 1.1.2 | Standard bootstrap | Optional integration | ACF image focal-point metadata and rendering. |
| `mrn-ai-guardrails` | 0.1.1 | Catalog-only; retained for deliberate use | Optional integration | SEOPress AI policy enforcement and human approval for generated image alt text. |
| `mrn-cookie-consent` | 1.1.43 | Catalog-only; retained for deliberate use | Optional integration | Silktide and Google Consent Mode. |
| `mrn-fontawesome-profile-manager` | 0.5.1 | Standard bootstrap | Optional integration | Font Awesome profiles and local assets. |
| `mrn-google-fonts` | 1.0.7 | Standard bootstrap | Optional integration | Google/local fonts and Site Styles; existing site behavior is unchanged. |
| `mrn-hierarchical-menu-taxonomies` | 0.1.1 | Standard bootstrap | Optional integration | Expands classic menu-builder taxonomy panels for hierarchical terms such as WooCommerce product categories; existing menu behavior is unchanged. The stable main-file path preserves activation during package replacement. |
| `mrn-gtm-injector` | 1.0.14 | Catalog-only; retained for deliberate use | Optional integration | Google Tag Manager. |
| `mrn-recaptcha-enterprise-manager` | 0.2.0 source / 0.1.4 bootstrap | Standard bootstrap | Optional integration | reCAPTCHA Enterprise and idempotent WPForms provisioning; security and credential contracts retained. |
| `mrn-relevanssi-ai-search` | 0.6.1 | Catalog-only; retained for deliberate use | Optional integration | Guarded AI query interpretation and hybrid semantic matching for Relevanssi. |
| `mrn-sendgrid-provisioning` | 0.1.0 | Catalog-only; retained for deliberate use | Retired, recoverable | Retired from active Stack integration. Source and restoration references preserve per-site SendGrid Subuser, mail-only API key, domain authentication and legacy mail synchronization; no bootstrap, credential delivery or automatic site removal. |
| `mrn-seo-helper` | 0.5.0 | Catalog-only; retained for deliberate use | Optional integration | ACF SEO fields and supported SEO providers; administrator-managed post-type allow-list with WooCommerce internal order and coupon types hard-excluded. |

## Dashboard-Only Operations

| Slug | Version | Responsibility |
| --- | ---: | --- |
| `mrn-mainwp-operations-api` | 0.9.9 | Independent repository (`mrnwebdesigns/mrn-mainwp-operations-api`); dashboard-only controller that records exact post-write Stack-plugin overlays, labels matching sites `current_with_approved_overlays`, preserves raw drift evidence, and fails changed or unrecorded artifacts back to hard drift; never installed on child sites. |
| `mrn-wp-control` | 1.1.1 | Independent repository (`mrnwebdesigns/mrn-wp-control`); dashboard-only, not installed in client-site plugins. |
| `mrn-wp-control-table-exporter` | 1.4.4 | Independent repository (`mrnwebdesigns/mrn-wp-control-table-exporter`); dashboard-only, not installed in client-site plugins. |
| `mrn-mainwp-mcp` | 0.1.1 | Node MCP adapter exposing MainWP/WPControl workflows to Codex and Claude Code. Agent tooling only; never installed on a WordPress site. |

## Development and Maintenance

| Slug | Version | Current distribution | Target tier | Responsibility |
| --- | ---: | --- | --- | --- |
| `mrn-template-inspector` | 0.2.7 | Standard bootstrap | Development only | Template and request-context inspection; local-only opener scope retained. |
| `mrn-dummy-content` | 0.3.1 | Catalog-only; retained for deliberate use | Development only | Development content fixtures; excluded from production bootstrap. |
| `mrn-comment-management` | 1.3.0 | Standard bootstrap | Maintenance only | Audits and manages comments with administrator-selected role access, additional native email recipients, and confirmed maintenance cleanup. |
| `mrn-database-retention` | 1.1.1 | Catalog-only; retained for deliberate use | Maintenance only | Allowlisted third-party operational-data retention; existing installations may use the guarded optional-plugin upgrade plan. Defender support remains code-level legacy compatibility and is not a Stack dependency. FluentSMTP policy is transport-provider agnostic during the SendGrid transition. |
| `mrn-layout-import-export` | 0.1.2 | Standard bootstrap | Maintenance only | ACF builder layout migration. |

## Review Queue

No disposition in this section authorizes a code move, manifest change, deletion, or archive.

## Archived Plugins

| Slug | Version | Source | Archive state |
| --- | ---: | --- | --- |
| `mrn-pre-consent-update-backup` | 1.0.12 | Independent repository (`mrnwebdesigns/mrn-pre-consent-update-backup`) | Retained as historical source; not distributed or runtime-wired. |

| Slug | Version | Current state | Decision needed |
| --- | ---: | --- | --- |
| `mrn-contextual-content-editor` | 0.4.10 | Catalog only | Removed from the bootstrap manifest on 2026-08-19 because it is not production ready. Source now lives in the independent `mrnwebdesigns/mrn-contextual-content-editor` repository; existing sites are unchanged. Re-entry requires a production-readiness review. |
| `mrn-google-reviews` | 1.0.0 | Incubator/catalog only | Independent source verified at `mrnwebdesigns/mrn-google-reviews` (`9cea85e`); held out of the stack because the plugin is incomplete. Completion still requires secret-management, QA, and release-readiness review. |

## Archived Components

| Slug | Version | Disposition | Evidence |
| --- | ---: | --- | --- |
| `mrn-editor-ui-css` | 1.0.8 | Archived | Superseded by `mrn-admin-ui-css`; absent from the MU loader and runtime sync source. |
| `mrn-duplicate-enhance` | 1.1.1 | Archived | Archived 2026-08-19. Optional Post Duplicator admin-bar adapter; removed from the bootstrap manifest. Source retained at `plugins/mrn-duplicate-enhance`. |
| `mrn-license-vault` | 0.2.5 | Archived | Archived 2026-08-19. Credential-handling admin tool with no canonical source; only the packaged zip on the stack manager exists. Zip retained, not deleted. |
| `mrn-unified-exporter` | 1.2.5 | Archived | Archived 2026-08-19. Settings-export maintenance tool with no canonical source; only the packaged zip on the stack manager exists. Zip retained, not deleted. |
| `searchwp-editor-performance` | 1.0.7 | Archived | Archived 2026-08-19. SearchWP was removed stack-wide in favor of Relevanssi, which indexes synchronously on save with no persistent background indexer/cron for this plugin to protect against. Source retained at the independent `mrnwebdesigns/searchwp-editor-performance` repository, marked retired; removed from the bootstrap manifest. |

## Canonical Source Decisions

- `mrn-mega-menu`: canonical in the independent `mrnwebdesigns/mrn-mega-menu` repository and symlinked into the stack through `MRN-plugins`; the former in-repo source was migrated with its history preserved.
- `mrn-sendgrid-provisioning`: canonical in the independent `mrnwebdesigns/mrn-sendgrid-provisioning` repository, symlinked into the Stack the same way as `mrn-config-helper`, and retained catalog-only. SendGrid Subuser/domain-auth/site-key provisioning moved here from `mrn-config-helper` on 2026-08-19; Config Helper 0.1.71 removes the settings link and sender-sync call, retaining generic sender wrappers. The connector and prior integration source are recoverable through `RESTORING-RETIRED-CAPABILITIES.md`.
- `mrn-universal-sticky-bar`: canonical in its independent repository and required by the Stack profile. It remains a standard plugin because it is independently useful off-stack; the Stack manifest and rollout contract enforce installation and shared-helper compatibility.
- `MRN-disable-core-auto-updates`: sunset approved on 2026-08-14. It is not part of the target MRN product catalog; no existing-site action is authorized by this decision.

## Current Bootstrap Warning

The existing [`manifests/plugins.txt`](./manifests/plugins.txt) supports profile-scoped entries such as `|stack` and `|plain`, which keeps selected optional components out of the plain-profile bootstrap. MRN Database Retention is no longer a bootstrap default; its checksum-locked release is managed through the upgrade-only optional-plugin plan. Cookie Consent, GTM Injector, and SendGrid Provisioning are excluded from the bootstrap input. SendGrid is retired and recoverable; its former bootstrap opt-in and credential-delivery path are removed. Existing installations are untouched. A component may remain a standard bootstrap default while also having an independently released, checksum-locked one-site upgrade record; that availability does not install it on an absent site or remove it from the full Stack baseline.
