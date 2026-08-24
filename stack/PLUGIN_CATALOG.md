# MRN WordPress Component Catalog

Last classified: 2026-08-19

This is the human-readable index of MRN-owned WordPress components. The authoritative machine-readable source is [`manifests/component-catalog.json`](./manifests/component-catalog.json), and the rules governing it are in [`PLUGIN_GOVERNANCE.md`](./PLUGIN_GOVERNANCE.md).

Catalog inclusion means that MRN owns, supports, is evaluating, or is deliberately retaining the component. It does **not** mean the component should be installed on every website.

`Current distribution` describes today's stack behavior. `Target tier` is the approved Phase 1 classification and does not itself change bootstrap, packaging, activation, deployment, or MU loading.

## Production Platform

| Slug | Version | Current distribution | Target tier | Responsibility |
| --- | ---: | --- | --- | --- |
| `mrn-loader` | 1.4.0 | MU loader | Platform required | Loads explicitly approved MU component entrypoints. |
| `mrn-admin-data-post-types` | 0.1.0 | MU loader | Platform required | Applies shared admin/data-only post-type policy. |
| `mrn-admin-ui-css` | 3.2.3 | MU loader | Platform required | Provides shared WordPress admin presentation and usability rules. |
| `mrn-dashboard-support` | 1.0.3 | MU loader | Platform required | Provides MRN support information and dashboard metadata. |
| `mrn-disable-comments` | 1.2.4 | MU loader | Platform required | Enforces the MRN no-comments policy. |
| `mrn-editor-lockdown` | 1.0.32 | MU loader | Platform required | Applies shared editor, metabox, and capability policy. |
| `mrn-environment-runtime` | 0.4.0 | MU loader | Platform required | Provides environment and runtime diagnostics. |
| `mrn-public-security-hardening` | 0.3.3 | MU loader | Platform required | Applies shared public REST and discovery hardening. |
| `mrn-shared-assets` | 0.2.0 | MU loader | Platform required | Provides shared asset and icon interfaces. |
| `mrn-site-colors` | 0.1.38 | MU loader | Platform required | Owns persistent site design tokens and CSS-variable output. |
| `mrn-updraft-local-retention` | 0.3.0 | MU loader | Platform required | Enforces shared backup schedule and retention policy. |
| `mrn-schema-bridge` | 0.4.2 | MU loader | Platform required | SEOPress (preferred), legacy SmartCrawl, theme, and schema normalization. |
| `mrn-active-style-guide` | 0.1.6 | MU loader | Platform required | Logged-in design-system reference and diagnostics. |
| `mrn-config-helper` | 0.1.55 | Standard bootstrap | Platform required | Shared site configuration shell, breadcrumb runtime, and launch/admin integrations. |
| `mrn-sendgrid-provisioning` | 0.1.0 | Standard bootstrap | Platform required | Provisions a per-site SendGrid Subuser, mail-only site API key, and domain authentication; split out of `mrn-config-helper`. |
| `mrn-universal-sticky-bar` | 1.1.8 | Standard bootstrap | Platform required | Provides the shared settings/editor action bar; independently released for non-Stack use. |

## Optional Shared Features

| Slug | Version | Current distribution | Target tier | Responsibility |
| --- | ---: | --- | --- | --- |
| `background-video-popout-disabler` | 1.0.1 | Standard bootstrap | Optional shared | Front-end helper for stack-profile background-video markup; suppresses browser picture-in-picture/pop-out controls on likely background videos. |
| `mrn-ai-assist` | 2.0.13 | Standard bootstrap | Optional shared | Queued AI-assisted content, SEO, and media-alt workflows. |
| `mrn-announcements` | 1.6.2 | Standard bootstrap | Optional shared | Scheduled and targeted announcement bars and modals. |
| `mrn-editor-tools` | 1.8.25 | Standard bootstrap | Optional shared | Classic Editor, TinyMCE, and ACF WYSIWYG enhancements. |
| `mrn-media-bulk-tools` | 0.12.1 | Standard bootstrap | Optional shared | Media audit, usage indexing, and bulk maintenance. |
| `mrn-mega-menu` | 0.16.17 | Standard bootstrap | Optional shared | Accessible content-rich mega-menu administration and rendering. |
| `mrn-reusable-block-library` | 0.1.28 | Standard bootstrap | Optional shared | Shared reusable block content types and render helpers for stack-profile sites that need reusable content workflows. |
| `mrn-tokens` | 0.1.3 | Standard bootstrap | Optional shared | Reusable content-token registry, shortcode, and authenticated REST API. |

## Optional Integration Adapters

| Slug | Version | Current distribution | Target tier | Integration |
| --- | ---: | --- | --- | --- |
| `mrn-acf-character-count` | 1.1.8 | Standard bootstrap | Optional integration | ACF editor character counts. |
| `mrn-acf-focal-point` | 1.1.2 | Standard bootstrap | Optional integration | ACF image focal-point metadata and rendering. |
| `mrn-cookie-consent` | 1.1.40 | Standard bootstrap | Optional integration | Silktide and Google Consent Mode. |
| `mrn-fontawesome-profile-manager` | 0.5.0 | Standard bootstrap | Optional integration | Font Awesome profiles and local assets. |
| `mrn-google-fonts` | 1.0.7 | Standard bootstrap | Optional integration | Google/local fonts and Site Styles. |
| `mrn-hierarchical-menu-taxonomies` | 0.1.0 | Standard bootstrap | Optional integration | Expands classic menu-builder taxonomy panels for hierarchical terms such as WooCommerce product categories. |
| `mrn-gtm-injector` | 1.0.13 | Standard bootstrap | Optional integration | Google Tag Manager. |
| `mrn-recaptcha-enterprise-manager` | 0.1.1 | Standard bootstrap | Optional integration | reCAPTCHA Enterprise and WPForms. |
| `mrn-seo-helper` | 0.4.0 | Standard bootstrap | Optional integration | ACF SEO fields and supported SEO providers. |
| `mrn-pre-consent-update-backup` | 1.0.12 | Catalog only | Optional integration | UpdraftPlus/update-consent workflow. |

## Dashboard-Only Operations

| Slug | Version | Responsibility |
| --- | ---: | --- |
| `mrn-mainwp-operations-api` | 0.3.0 | Scoped MainWP operations for backups, package installation, and font management. |
| `mrn-wp-control` | 1.1.1 | Restricted MainWP account provisioning through WP-CLI. |
| `mrn-wp-control-table-exporter` | 1.4.4 | CSV export for supported WP Control/MainWP tables. |
| `mrn-mainwp-mcp` | 0.1.0 | Node MCP adapter exposing MainWP/WPControl workflows to Codex and Claude Code. Agent tooling only; never installed on a WordPress site. |

## Development and Maintenance

| Slug | Version | Current distribution | Target tier | Responsibility |
| --- | ---: | --- | --- | --- |
| `mrn-template-inspector` | 0.2.7 | Standard bootstrap | Development only | Template and request-context inspection. |
| `mrn-dummy-content` | 0.3.0 | Catalog only | Development only | Development content fixtures. |
| `mrn-comment-management` | 1.1.7 | Standard bootstrap | Maintenance only | Explicit comment audit and deletion. |
| `mrn-database-retention` | 1.1.0 | Standard bootstrap | Maintenance only | Allowlisted third-party operational-data retention. |
| `mrn-layout-import-export` | 0.1.2 | Standard bootstrap | Maintenance only | ACF builder layout migration. |

## Review Queue

No disposition in this section authorizes a code move, manifest change, deletion, or archive.

| Slug | Version | Current state | Decision needed |
| --- | ---: | --- | --- |
| `mrn-contextual-content-editor` | 0.4.10 | Catalog only | Removed from the bootstrap manifest on 2026-08-19 because it is not production ready. Re-entry requires a production-readiness review. |
| `mrn-google-reviews` | 1.0.0 | Incubator/catalog only | Held out of the stack on 2026-08-17 because the plugin is incomplete. Source was committed and pushed on 2026-08-19 (`mrnwebdesigns/mrn-google-reviews`, private). Completion still requires secret-management, QA, and release-readiness review. |

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
- `mrn-sendgrid-provisioning`: canonical in the independent `mrnwebdesigns/mrn-sendgrid-provisioning` repository, symlinked into the stack the same way as `mrn-config-helper`. SendGrid Subuser/domain-auth/site-key provisioning moved here from `mrn-config-helper` on 2026-08-19; `mrn-config-helper` keeps only a settings-page link and the public `mrn_config_helper_get_site_sender_name()`/`_email()` wrappers this plugin consumes.
- `mrn-universal-sticky-bar`: canonical in its independent repository and required by the Stack profile. It remains a standard plugin because it is independently useful off-stack; the Stack manifest and rollout contract enforce installation and shared-helper compatibility.
- `MRN-disable-core-auto-updates`: sunset approved on 2026-08-14. It is not part of the target MRN product catalog; no existing-site action is authorized by this decision.

## Current Bootstrap Warning

The existing [`manifests/plugins.txt`](./manifests/plugins.txt) now supports profile-scoped entries such as `|stack` and `|plain`, which keeps selected optional components out of the plain-profile bootstrap. Remaining optional and maintenance components still require feature-selection support in the hosting platform before they can be removed safely from the shared bundle input.
