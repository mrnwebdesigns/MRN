# MRN WordPress Component Catalog

Last classified: 2026-08-14

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
| `mrn-environment-runtime` | 0.3.1 | MU loader | Platform required | Provides environment and runtime diagnostics. |
| `mrn-public-security-hardening` | 0.3.3 | MU loader | Platform required | Applies shared public REST and discovery hardening. |
| `mrn-shared-assets` | 0.2.0 | MU loader | Platform required | Provides shared asset and icon interfaces. |
| `mrn-site-colors` | 0.1.38 | MU loader | Platform required | Owns persistent site design tokens and CSS-variable output. |
| `mrn-updraft-local-retention` | 0.3.0 | MU loader | Platform required | Enforces shared backup schedule and retention policy. |

## Optional Shared Features

| Slug | Version | Current distribution | Target tier | Responsibility |
| --- | ---: | --- | --- | --- |
| `mrn-ai-assist` | 2.0.13 | Standard bootstrap | Optional shared | Queued AI-assisted content, SEO, and media-alt workflows. |
| `mrn-announcements` | 1.6.2 | Standard bootstrap | Optional shared | Scheduled and targeted announcement bars and modals. |
| `mrn-contextual-content-editor` | 0.4.10 | Standard bootstrap | Optional shared | Contextual logged-in content-editing links. |
| `mrn-editor-tools` | 1.8.25 | Standard bootstrap | Optional shared | Classic Editor, TinyMCE, and ACF WYSIWYG enhancements. |
| `mrn-media-bulk-tools` | 0.12.1 | Standard bootstrap | Optional shared | Media audit, usage indexing, and bulk maintenance. |
| `mrn-mega-menu` | 0.16.16 | Standard bootstrap | Optional shared | Accessible content-rich mega-menu administration and rendering. |
| `mrn-tokens` | 0.1.3 | Catalog only | Optional shared | Reusable content-token registry, shortcode, and authenticated REST API. |

## Optional Integration Adapters

| Slug | Version | Current distribution | Target tier | Integration |
| --- | ---: | --- | --- | --- |
| `mrn-acf-character-count` | 1.1.8 | Standard bootstrap | Optional integration | ACF editor character counts. |
| `mrn-acf-focal-point` | 1.1.2 | Standard bootstrap | Optional integration | ACF image focal-point metadata and rendering. |
| `mrn-cookie-consent` | 1.1.40 | Standard bootstrap | Optional integration | Silktide and Google Consent Mode. |
| `mrn-fontawesome-profile-manager` | 0.5.0 | Standard bootstrap | Optional integration | Font Awesome profiles and local assets. |
| `mrn-google-fonts` | 1.0.7 | Standard bootstrap | Optional integration | Google/local fonts and Site Styles. |
| `mrn-gtm-injector` | 1.0.13 | Standard bootstrap | Optional integration | Google Tag Manager. |
| `mrn-recaptcha-enterprise-manager` | 0.1.1 | Standard bootstrap | Optional integration | reCAPTCHA Enterprise and WPForms. |
| `mrn-schema-bridge` | 0.4.2 | MU loader | Optional integration | SmartCrawl, SEOPress, theme, and schema normalization. |
| `mrn-seo-helper` | 0.4.0 | Standard bootstrap | Optional integration | ACF SEO fields and supported SEO providers. |
| `mrn-pre-consent-update-backup` | 1.0.12 | Catalog only | Optional integration | UpdraftPlus/update-consent workflow. |

## Dashboard-Only Operations

| Slug | Version | Responsibility |
| --- | ---: | --- |
| `mrn-mainwp-operations-api` | 0.3.0 | Scoped MainWP operations for backups, package installation, and font management. |
| `mrn-wp-control` | 1.1.1 | Restricted MainWP account provisioning through WP-CLI. |
| `mrn-wp-control-table-exporter` | 1.4.1 | CSV export for supported WP Control/MainWP tables. |

## Development and Maintenance

| Slug | Version | Current distribution | Target tier | Responsibility |
| --- | ---: | --- | --- | --- |
| `mrn-active-style-guide` | 0.1.5 | MU loader | Development only | Logged-in design-system reference and diagnostics. |
| `mrn-template-inspector` | 0.2.7 | Standard bootstrap | Development only | Template and request-context inspection. |
| `searchwp-editor-performance` | 1.0.7 | Standard bootstrap | Development only | Development SearchWP editor-performance suppression. |
| `mrn-dummy-content` | 0.3.0 | Catalog only | Development only | Development content fixtures. |
| `mrn-comment-management` | 1.1.7 | Standard bootstrap | Maintenance only | Explicit comment audit and deletion. |
| `mrn-database-retention` | 1.0.0 | Catalog only | Maintenance only | Allowlisted third-party operational-data retention. |
| `mrn-layout-import-export` | 0.1.2 | Catalog only | Maintenance only | ACF builder layout migration. |

## Review Queue

No disposition in this section authorizes a code move, manifest change, deletion, or archive.

| Slug | Version | Current state | Decision needed |
| --- | ---: | --- | --- |
| `background-video-popout-disabler` | 1.0.1 | Standard bootstrap | Keep optional or move presentation behavior into the base theme. |
| `mrn-config-helper` | 0.1.54 | Standard bootstrap | Define the stable settings shell and later extract integration boundaries. |
| `mrn-google-reviews` | 1.0.0 | Incubator/catalog only | Complete source, secret-management, QA, and release-readiness review. |
| `mrn-hierarchical-menu-taxonomies` | 0.1.0 | Catalog only | Keep independently optional, integrate with Mega Menu, or archive. |
| `mrn-reusable-block-library` | 0.1.28 | MU loader | Decide whether it is a mandatory base-theme contract or optional shared feature. |
| `mrn-universal-sticky-bar` | 1.1.8 | Standard bootstrap | Keep independent or move implementation into the platform with compatibility shims. |
| `mrn-duplicate-enhance` | 1.1.1 | MU source only | Convert to an optional Post Duplicator adapter or archive. |
| `mrn-editor-ui-css` | 1.0.8 | MU source only | Confirm supersession and archive after explicit approval. |

## Canonical Source Decisions

- `mrn-mega-menu`: canonical in `MRN/plugins/mrn-mega-menu`; the unversioned, older `MRN-plugins` duplicate was retired without changing installed copies.
- `searchwp-editor-performance`: canonical in the independent `mrnwebdesigns/searchwp-editor-performance` repository; the stack records and packages its release but does not own a duplicate source tree.
- `MRN-disable-core-auto-updates`: sunset approved on 2026-08-14. It is not part of the target MRN product catalog; no existing-site action is authorized by this decision.

## Current Bootstrap Warning

The existing [`manifests/plugins.txt`](./manifests/plugins.txt) remains unchanged and currently installs several components classified above as optional, development-only, or maintenance-only. A later approved installer/profile phase will correct that behavior.
