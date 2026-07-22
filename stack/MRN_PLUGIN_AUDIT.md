# MRN Plugin Audit

Last audited: 2026-07-09

This audit tracks the active MRN WordPress plugin and MU-plugin sources, their Plugins-page display names, current header versions, repository ownership, and stack dependency posture.

## Naming Convention

- Plugins-page headers use `MRN {Plugin Name}`.
- MU-only notes such as `(MU)` stay out of the Plugins-page name.
- Admin menu labels stay task-oriented and omit `MRN` unless the label is describing another MRN integration.
- Versions below are the current plugin header versions. Runtime constants were checked where present.

## Repository Summary

- In-repo plugins and MU plugins are owned by the main MRN repo: <https://github.com/mrnwebdesigns/MRN>.
- Symlinked plugins under `plugins/` are owned by standalone repos under <https://github.com/mrnwebdesigns>.
- `mrn-pre-consent-update-backup` now has a standalone private repo: <https://github.com/mrnwebdesigns/mrn-pre-consent-update-backup>.

## QA Engine Readiness

All audited plugin roots now include the MRN QA Engine discovery files required by the `wordpress-plugin` profile:

- `.mrn-qa.env`
- `AGENTS.md`
- `README.md`
- `STACK_BASELINE.md`
- `stack.lock`
- `phpcs.xml.dist`

Run a plugin-specific static QA pass with:

```bash
MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /absolute/path/to/plugin
```

The committed `.mrn-qa.env` files default browser, accessibility, and performance rows to `never` for standalone plugin static QA. Run those rows separately with an explicit `--site-url` / `--site-path` when a plugin change affects live front-end, admin, REST/API, accessibility, or performance behavior.

## Standard Plugins

| Slug | Plugins-page name | Version | Source repo | Stack requirement |
| --- | --- | --- | --- | --- |
| `background-video-popout-disabler` | MRN Background Video Pop-Out Disabler | 1.0.1 | <https://github.com/mrnwebdesigns/background-video-popout-disabler> | No stack requirement. |
| `mrn-acf-character-count` | MRN ACF Character Count | 1.1.6 | <https://github.com/mrnwebdesigns/mrn-acf-character-count> | No stack requirement; ACF field context required. |
| `mrn-ai-assist` | MRN AI Assist | 2.0.12 | <https://github.com/mrnwebdesigns/mrn-ai-assist> | No hard stack requirement; MRN SEO Helper/SmartCrawl integrations are optional feature paths. |
| `mrn-announcements` | MRN Announcements | 1.6.1 | <https://github.com/mrnwebdesigns/mrn-announcements> | Standalone-capable; optional stack Business Information and USB integrations; admin/data-only CPT with no public URL or SEO surface. |
| `mrn-comment-management` | MRN Comment Management | 1.1.7 | <https://github.com/mrnwebdesigns/mrn-comment-management> | No stack requirement. |
| `mrn-config-helper` | MRN Config Helper | 0.1.43 | <https://github.com/mrnwebdesigns/mrn-config-helper> | Stack-aware admin utility; can run standalone, but stack sites use more of its integrations. |
| `mrn-contextual-content-editor` | MRN Contextual Content Editor | 0.1.0 | <https://github.com/mrnwebdesigns/MRN> | No hard stack requirement; ACF matching is optional. |
| `mrn-cookie-consent` | MRN Cookie Consent | 1.1.36 | <https://github.com/mrnwebdesigns/mrn-cookie-consent> | No hard stack requirement; MRN GTM Injector integration is optional. |
| `mrn-dummy-content` | MRN Dummy Content | 0.1.19 | <https://github.com/mrnwebdesigns/mrn-dummy-content> | Stack-aware QA/demo utility; can run outside the stack but is most useful against MRN builder fields. |
| `mrn-editor-tools` | MRN Editor Enhancements | 1.8.24 | <https://github.com/mrnwebdesigns/mrn-editor-tools> | No stack requirement; Classic Editor/ACF editor context expected. |
| `mrn-fontawesome-profile-manager` | MRN Font Awesome Profile Manager | 0.4.0 | <https://github.com/mrnwebdesigns/mrn-fontawesome-profile-manager> | Optional stack bridge for Site Configurations/Site Styles. |
| `mrn-google-fonts` | MRN Google Fonts | 0.5.2 | <https://github.com/mrnwebdesigns/MRN> | Optional stack bridge for Site Styles; standalone mode supported. |
| `mrn-gtm-injector` | MRN GTM Injector | 1.0.13 | <https://github.com/mrnwebdesigns/mrn-gtm-injector> | No stack requirement. |
| `mrn-media-bulk-tools` | MRN Media Bulk Tools | 0.1.0 | <https://github.com/mrnwebdesigns/MRN> | No stack requirement. |
| `mrn-mega-menu` | MRN Mega Menu | 0.16.2 | <https://github.com/mrnwebdesigns/MRN> | Stack-owned standard plugin; WooCommerce and shared stack integrations are optional and standalone-safe. |
| `mrn-pre-consent-update-backup` | MRN Pre-Consent Update Backup | 1.0.12 | <https://github.com/mrnwebdesigns/mrn-pre-consent-update-backup> | No stack requirement; requires UpdraftPlus to perform backups. |
| `mrn-recaptcha-enterprise-manager` | MRN reCAPTCHA Enterprise Manager | 0.1.1 | <https://github.com/mrnwebdesigns/MRN> | No stack requirement; WPForms sync is optional. |
| `mrn-seo-helper` | MRN SEO Helper | 0.3.4 | <https://github.com/mrnwebdesigns/mrn-seo-helper> | No hard stack requirement; ACF/SmartCrawl integrations are optional feature paths and admin/data-only announcements are excluded. |
| `mrn-template-inspector` | MRN Template Inspector | 0.2.7 | <https://github.com/mrnwebdesigns/MRN> | No stack requirement; local development tool. |
| `mrn-universal-sticky-bar` | MRN Universal Sticky Bar | 1.1.3 | <https://github.com/mrnwebdesigns/mrn-universal-sticky-bar> | No hard stack requirement; Classic Editor screens expected. |
| `searchwp-editor-performance` | MRN SearchWP Editor Performance | 1.0.6 | <https://github.com/mrnwebdesigns/MRN> | No stack requirement; SearchWP is required for runtime effect. |

## MU Plugins

| Slug | Plugins-page name | Version | Source repo | Stack requirement |
| --- | --- | --- | --- | --- |
| `mrn-loader` | MRN Loader | 1.3.2 | <https://github.com/mrnwebdesigns/MRN> | Stack-owned; required for stack MU subfolder loading. |
| `mrn-active-style-guide` | MRN Active Style Guide | 0.1.5 | <https://github.com/mrnwebdesigns/MRN> | Stack-owned front-end reference tool. |
| `mrn-admin-ui-css` | MRN Admin UI CSS | 3.1.13 | <https://github.com/mrnwebdesigns/MRN> | Stack-owned admin UI layer. |
| `mrn-dashboard-support` | MRN Dashboard Support | 1.0.3 | <https://github.com/mrnwebdesigns/MRN> | No stack requirement. |
| `mrn-disable-comments` | MRN Disable Comments | 1.2.3 | <https://github.com/mrnwebdesigns/MRN> | No stack requirement. |
| `mrn-duplicate-enhance` | MRN Post Duplicator Admin Bar Enhance | 1.1.1 | <https://github.com/mrnwebdesigns/MRN> | No stack requirement; depends on Post Duplicator behavior being available. |
| `mrn-editor-lockdown` | MRN Editor Lockdown | 1.0.25 | <https://github.com/mrnwebdesigns/MRN> | Stack-owned Classic Editor/AME lockdown layer. |
| `mrn-editor-ui-css` | MRN Admin UI CSS Legacy | 1.0.8 | <https://github.com/mrnwebdesigns/MRN> | Stack-owned legacy compatibility loader. |
| `mrn-public-security-hardening` | MRN Public Security Hardening | 0.3.2 | <https://github.com/mrnwebdesigns/MRN> | Stack-owned shared MU plugin; deployed from canonical `mu-plugins/` and loaded by MRN Loader. |
| `mrn-reusable-block-library` | MRN Reusable Block Library | 0.1.17 | <https://github.com/mrnwebdesigns/MRN> | Optional stack styling; usable without the stack, but MRN stack/theme CSS gives the finished presentation. |
| `mrn-schema-bridge` | MRN Schema Bridge | 0.4.1 | <https://github.com/mrnwebdesigns/MRN> | SmartCrawl is the preferred base graph; standalone supplemental schema remains available when it is absent. |
| `mrn-shared-assets` | MRN Shared Assets | 0.1.3 | <https://github.com/mrnwebdesigns/MRN> | Stack-owned shared asset provider. |
| `mrn-site-colors` | MRN Site Styles | 0.1.15 | <https://github.com/mrnwebdesigns/MRN> | Stack-owned design-token/configuration layer. |
| `mrn-updraft-local-retention` | MRN Updraft Local Retention | 0.1.0 | <https://github.com/mrnwebdesigns/MRN> | No stack requirement; requires UpdraftPlus to have an effect. |

## Stack MU Wrapper Notes

- Existing root wrappers in `stack/mu-plugins/*.php` now carry matching `MRN ...` headers and synced versions for the wrappers they represent.
- `stack/mu-plugins/mrn-editor-lockdown.php` is synced to `MRN Editor Lockdown 1.0.25`.
- `stack/mu-plugins/mrn-schema-bridge.php` is synced to `MRN Schema Bridge 0.4.1`.
- Missing root wrappers should not be added casually; load order is owned by `MRN Loader` so stack MU behavior stays explicit.
