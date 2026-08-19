# Third-Party Plugin Audit

Checked: 2026-08-13

Scope: non-custom plugins and package zips referenced by `stack/manifests/plugins.txt`.

## Stack Actions

- Added `happyfiles-pro.zip` from `/Users/khofmeyer/Downloads/Old/happyfiles-pro.zip`.
- Updated package manifest references where newer local archives were available:
  - SearchWP: `searchwp-4.5.7-1.zip` -> `searchwp-4.6.0.zip`
  - UpdraftPlus Premium: `updraftplus.2.26.1.zip` -> `updraftplus.2.26.5.zip`
  - Defender Pro: `1320813_defender-pro-5.9.zip` -> `1320813_defender-pro-5.11.zip`
  - SEOPress Pro: added `wp-seopress-pro-10-1-1.zip` from `/Users/khofmeyer/Downloads/wp-seopress-pro-10-1-1.zip`
  - WPMU DEV Dashboard: `1320813_wpmu-dev-dashboard-4.11.29.zip` -> `1320813_wpmu-dev-dashboard-5.0.0.zip`
- Refreshed fixed-name package zips from `/Users/khofmeyer/Downloads`:
  - `admin-menu-editor-pro.zip`
  - `advanced-custom-fields-pro.zip`
  - `ame-branding-add-on.zip`
  - `wp-toolbar-editor.zip`
  - `wpforms.zip`
- Public WordPress.org plugins remain as slugs, so stack bootstrap installs the current WordPress.org release at install time.

## Version Audit

| Plugin | Stack source | Stack/current version | Latest checked | Status |
| --- | --- | ---: | ---: | --- |
| Admin Menu Editor Pro | `admin-menu-editor-pro.zip` | 2.33.4 | 2.33.4 | Updated from Downloads. |
| Advanced Custom Fields Pro | `advanced-custom-fields-pro.zip` | 6.8.4 | 6.8.4 | Updated from Downloads. |
| AME Branding Add-on | `ame-branding-add-on.zip` | 1.3.12 | 1.3.12 | Updated from Downloads. |
| AME Toolbar Editor | `wp-toolbar-editor.zip` | 1.5.1 | not publicly confirmed separately | Kept available package. |
| Classic Editor | `classic-editor` slug | latest at install | 1.7.0 | Current via WordPress.org. |
| Enable Media Replace | `enable-media-replace` slug | latest at install | 4.2.2 | Current via WordPress.org. |
| FluentSMTP | `fluent-smtp` slug | latest at install | 2.2.95 | Current via WordPress.org. |
| HappyFiles Pro | `happyfiles-pro.zip` | 1.9.1 | not confirmed; vendor changelog is dynamic/account-gated | Added provided package. |
| Post Duplicator | `post-duplicator` slug | latest at install | 3.0.15 | Current via WordPress.org. |
| Post Types Order | `post-types-order` slug | latest at install | 2.4.8 | Current via WordPress.org. |
| SearchWP | `searchwp-4.6.0.zip` | 4.6.0 | 4.6.0 | Updated from Downloads. |
| SearchWP Live Ajax Search | `searchwp-live-ajax-search` slug | latest at install | 1.8.7 | Current via WordPress.org. |
| SEOPress | `wp-seopress` slug | latest at install | not checked | New preferred stack SEO provider installed from WordPress.org. |
| SEOPress Pro | `wp-seopress-pro-10-1-1.zip` | 10.1.1 | 10.1.1 | Added from Downloads. |
| Advanced Editor Tools | `tinymce-advanced` slug | latest at install | 5.9.2 | Current via WordPress.org. |
| UpdraftPlus Premium | `updraftplus.2.26.5.zip` | 2.26.5.26 | 2.26.5 | Updated to current patched premium branch. |
| Defender Pro | removed from stack manifest | 5.11.0 legacy package | 5.11.0 | Removed 2026-08-19 with all WPMU DEV plugins. Package retained on the stack manager, unreferenced. |
| SmartCrawl Pro | removed from new stack manifest | 3.16.2 legacy package | 3.16.2 | Supported by provider adapters for existing sites during migration. |
| WPMU DEV Dashboard | removed from stack manifest | 5.0.0 legacy package | 5.0.0 | Removed 2026-08-19 with all WPMU DEV plugins. Package retained on the stack manager, unreferenced. |
| WPForms | `wpforms.zip` | 1.10.2.1 | 1.10.2.1 public/Lite signal | Updated from Downloads. |

## Sources Checked

- WordPress.org plugin directory/API for public slugs.
- Admin Menu Editor Pro changelog: https://adminmenueditor.com/documentation/changelog/
- ACF changelog: https://www.advancedcustomfields.com/changelog/
- SearchWP changelog: https://searchwp.com/documentation/changelog/
- SEOPress Pro package header from `/Users/khofmeyer/Downloads/wp-seopress-pro-10-1-1.zip`.
- UpdraftPlus changelog/security notes: https://teamupdraft.com/updraftplus/changelog/
- WPMU DEV Dashboard changelog: https://wpmudev.com/project/wpmu-dev-dashboard/
- HappyFiles changelog page: https://happyfiles.io/changelog/

## Package Gaps

No known package gaps remain for the manifest package zips checked on 2026-08-13, assuming `wp-seopress-pro-10-1-1.zip` is present on the stack manager under `/home/mrndev-stack-manager/stack/packages/`.
