# Current Work

## Active Scope
- Canonical workspace root: `/Users/khofmeyer/Development/MRN`
- Active implementation area: `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack`
- Local acceptance harness: seeded QA pages on the local stack test site at `/Users/khofmeyer/Local Sites/mrn-plugin-stack/app/public/wp-content`
- Monorepo migration pilots are in progress for `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-admin-ui-css`, `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-editor-ui-css`, `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-disable-comments`, `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-svg-support`, `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-dashboard-support`, `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-duplicate-enhance`, `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-site-colors`, `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-editor-lockdown`, `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-shared-assets`, `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-reusable-block-library`, and `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-active-style-guide`; their nested repo metadata was moved to `/Users/khofmeyer/Development/MRN/.repo-migration-backups/mu-plugins/` so the top-level repo can begin owning those plugin paths.

## Current Focus
- Modernize builder and page-shell behavior in the stack theme.
- Keep shared `Section Width` as the standard layout control:
  `Content`, `Wide`, `Full Width`
- Keep `After Content` as a placement bucket that follows the same layout vocabulary as `Content`.
- Centralize wrapper and shell logic in shared helpers instead of one-off layout patches.
- Treat width-class rendering as solved infrastructure and focus next on stronger visual expression by layout family, starting with `Basic` and likely `Image Content`.

## Active Product Decisions
- `Content Lists` display modes are a targeted system, not a stack-wide builder rule.
- Client management for display modes lives in `Site Configurations -> Display Modes`.
- Client management for WPForms notification recipient emails now lives in `Site Configurations -> Integrations -> WPForms Notifications`, including select-all bulk apply/remove actions, per-notification puck-style editing, and per-form opt-out from the saved Primary Notification Email.
- In builder UI, `Content Lists` should only expose post-type display modes that match the selected content type, with fallback to `Use Row Settings`.
- The implementation should stay helper-driven and filterable so other list-capable layouts can reuse the same registry and renderer later.
- Singular sidebar behavior is theme-owned in `mrn-base-stack`.
- Front-end singular-sidebar collapse was explored and intentionally deferred.
- Classic-editor sidebar collapse behavior is the active collapse contract and lives in `mu-plugins/mrn-editor-lockdown/mrn-editor-lockdown.php`.

## Recent Durable Decisions
- Current stack baseline is `2026.04.06-builder-width-and-social-polish`.
- Current stack theme version is `mrn-base-stack 1.1.13`.
- Current reusable block library version is `mrn-reusable-block-library 0.1.8`.
- Future sites should use a child theme for site-specific theming, and stack update work should preserve stable parent-theme theming hooks such as classes, CSS variables, and other child-theme styling targets unless a documented breaking change is truly necessary.
- Stack AME export payloads were refreshed twice on `2026-04-06`; the current canonical files are `/Users/khofmeyer/Development/MRN/stack/configs/exports/ame-config-container.json` and `/Users/khofmeyer/Development/MRN/stack/configs/exports/AME-configuration(2026-04-06).json`.
- The top-level repo now tracks the canonical stack manifests, importer/bootstrap helpers, archive docs, and surfaced wrapper/shim files that were previously hidden by the old allowlist `.gitignore`, so release and deployment QA can reason about the full stack source from one repo.
- `mrn-dummy-content` is now a stack-packaged standard plugin with canonical source at `/Users/khofmeyer/Development/MRN/plugins/mrn-dummy-content`, release artifact path `/Users/khofmeyer/Development/MRN/releases/plugins/mrn-dummy-content.zip`, and stack manifest entry `/home/mrndev-stack-manager/stack/packages/mrn-dummy-content.zip`.
- Theme rollout manifest must use the packaged stack theme zip path, not a bare slug:
  `/home/mrndev-stack-manager/stack/themes/mrn-base-stack.zip|active`
- `default-configs.mrndev.io` was refreshed on `2026-04-05` for the social-link icon fallback release.
- `default-configs.mrndev.io` is currently still running the cloned `default-configs` theme slug, not `mrn-base-stack` directly, so stack theme feature deploys must target the live active stylesheet directory as well as the canonical stack source.
- `default-configs.mrndev.io` also needs `/Users/khofmeyer/Development/MRN/shared` mirrored into `wp-content/shared`; otherwise Site Configurations can lose the sticky toolbar even when plugin/theme code is in sync.
- The stack server source-of-truth copy also needs `/Users/khofmeyer/Development/MRN/shared` mirrored into `/home/mrndev-stack-manager/stack/shared`; new-site bootstrap reads shared runtime files from the stack root before syncing them into each site.
- Settings-style sticky admin bars now have one canonical shared source at `/Users/khofmeyer/Development/MRN/shared/mrn-sticky-settings-toolbar.php`; consuming plugins should load it from `wp-content/shared` via thin local loaders and call the unique `mrn_sticky_toolbar_*` API instead of maintaining copied helper variants.
- `mrn-config-helper` now exposes a list-first WPForms notification editor in Site Configurations with bulk apply/remove actions, while the saved Primary Notification Email still auto-applies to all WPForms notifications by default and can be disabled per form.
- The WPForms notification puck editor in Site Configurations must emit native `input`/`change` events when recipients are added, removed, or the primary-email toggle changes so the shared sticky save bar registers unsaved changes.
- `mrn-reusable-block-library` now exposes `compact` and `feature` content-list display modes and defers reusable content-list item rendering to the shared theme renderer so reusable blocks and page rows stay aligned.
- Canonical stack feature deploy command is `/Users/khofmeyer/Development/MRN/stack/scripts/deploy-feature-stack-and-default-configs.sh`, which must refresh both the stack server copy and `default-configs.mrndev.io` for stack theme and stack MU changes.
- That feature deploy helper must also refresh stack-root `shared/` and `wp-content/shared` on `default-configs.mrndev.io`.
- `mrn-seo-helper` now owns sidebar placement for its SEO title/meta description ACF group and must register on WordPress `init` after CPT registration.
- `mrn-editor-lockdown` preserves the SEO Helper box in locked classic-editor sidebars.
- Stack-wide admin icon picking now has one canonical chooser in `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-shared-assets`; `mrn-base-stack` and `mrn-editor-tools` should consume `mrn_shared_assets_enqueue_admin_icon_chooser()` instead of shipping their own modal/picker catalogs.

## Recent Release Notes
- Stack release `2026.04.06-builder-width-and-social-polish` expands `mrn-base-stack` to `1.1.13` and `mrn-config-helper` to `0.1.35`.
- That release realigns seeded wide-layout QA sections with the shared width shells, standardizes `Two Column Split` on the shared intro-field contract, adds animated FAQ accordion behavior, and extends social-link settings with separate saved name plus hover/accessibility text.
- Release verification passed for `php -l`, `node --check`, `git diff --check`, targeted risky-pattern review, `qa-security.sh`, `qa-local-stack-site.sh`, `qa-page-speed.sh`, Playwright local stack smoke, and pre-deploy `qa-rollout-contract.sh` except for the expected live-version parity failure before rollout.
- Stack release `2026.04.06-ame-config-refresh` refreshed the canonical AME export payloads and importer mapping for fresh stack-site bootstrap.
- That release was then rebuilt on `2026-04-06` from a revised local AME snapshot that updated admin-menu, dashboard-widget-editor, roles-and-capabilities, and table-columns payload sections.
- Release verification passed for JSON validation, `git diff --check`, targeted risky-pattern review, local AME import smoke via WP-CLI, and stack-specific roles follow-up import verification.
- Social-link rendering in `mrn-base-stack` now verifies media icon attachments still resolve to a real file before outputting an `<img>`, and falls back cleanly to text when stale option data points at deleted media.
- Stack release `2026.04.05-social-link-icon-fallback` shipped as theme `1.1.12`, was pushed from commit `512830b`, packaged as `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack.zip`, and deployed to both the stack source and `default-configs.mrndev.io`.
- Release verification passed for `php -l`, `git diff --check`, `qa-security.sh`, targeted Playwright front-end smoke, `qa-page-speed.sh`, and post-deploy `qa-rollout-contract.sh`.
- Builder, posts, pages, and reusable blocks now share one canonical `label` / `heading` / `subheading` text-field schema with paired tag controls, and the reusable block library templates now render that canonical shape directly.
- CTA, Basic Block, Content Grid, Content Lists, and FAQ reusable blocks now use the same standard intro-field model and admin layout as the stack builder rows.
- Stack release `2026.04.05-builder-schema-standardization` shipped as theme `1.1.11` and reusable block library `0.1.8`, was pushed from commit `518dfa8`, packaged as `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack.zip`, and deployed to both the stack source and `default-configs.mrndev.io`.
- Release verification passed for `php -l`, `git diff --check`, `qa-security.sh`, `qa-page-speed.sh`, `qa-local-stack-site.sh`, targeted theme `phpcs`, and post-deploy `qa-rollout-contract.sh`.
- Local `qa-playwright-local-stack-site.sh` still reports a pre-existing front-end console `404` resource error on home and sample-page smoke tests; admin/settings smoke checks passed and the live deploy itself verified cleanly afterward.
- `mrn-config-helper` now shows the exact `define('MRN_SENDGRID_MANAGEMENT_API_KEY', 'your-sendgrid-management-api-key');` line in the SendGrid management-key help text and explicitly points admins to `wp-config.php` for host-managed setup.
- The stack baseline now separates testimonial position/company meta lines and restores website-link output in testimonial list views.
- Reusable content lists now support `compact` and `feature` display modes and keep pagination anchored to the same row after page changes.
- The stack theme now includes a theme-owned `Testimonial` CPT with archive support and local ACF fields for name, company, position, website URL, rich text content, and image/logo.
- The stack theme now includes a theme-owned `Case Study` CPT with local ACF fields for client overview, challenge, services repeater rows with image-position controls, and strategy/approach content plus image placement.
- The theme-owned `Testimonial` CPT now uses the shared editorial pattern too, including the `Standard Set` header fields and the shared singular shell structure instead of the older simpler wrapper.
- Shared builder wrappers must only render when at least one row outputs real markup; empty sidebar rows must not trigger the singular sidebar shell on otherwise normal pages.
- Editorial CPT sticky-bar and shared shell support now derive from a shared helper so new theme-owned editorial CPTs stay aligned automatically.
- `mrn-editor-lockdown` now applies its locked classic-editor metabox shell dynamically to supported non-reusable post types, keeping the SEO Helper box pinned at the top of the sidebar on new editorial CPTs.
- `mrn-seo-helper` keeps its dynamic post-type targeting while explicitly excluding reusable block library CPTs from SEO field registration and SmartCrawl template/sync coverage.
- The Card builder row now keeps shared row-level fields ahead of the card-specific repeater fields.
- The Add Row builder picker now discovers layouts from live registered builder metadata, keeps page-only conversion targets hidden automatically, and alphabetizes the visible list.

## Active Ops Caveats
- Run stack automation as `mrndev-stack-manager`; running as `kyle` can still produce runtime status-file warnings.
- Default stack SSH target is `mrndev-stack-manager@167.99.54.77`.
- Stack site/server credential details are stored locally at `/Users/khofmeyer/Development/MRN/.local/secrets/default-configs-server-info.txt`.
- If work is for a specific project/site, request that site's server information instead of relying on the default stack SSH target.
- Live stack files should be written as the destination owner, not as `kyle`.
- For `default-configs.mrndev.io`, the documented `sudo -n -u <site-user>` live-site sync path is still not provisioned for either `mrndev-stack-manager` or `mrn-ops`; direct site-owner SSH is the current working fallback.
- The current `default-configs.mrndev.io` live root is `/home/default-configs-stack/htdocs/default-configs.mrndev.io`; older `mrndev-default-configs-stack` paths are stale.
- `default-configs.mrndev.io` live theme files are currently readable, but ownership is still not normalized to the preferred site owner.
- Future live theme refreshes must avoid unreadable file modes like `670`, especially after manual or selective syncs.

## Current Tooling
- Local stack QA helpers now live in `/Users/khofmeyer/Development/MRN/stack/scripts`:
  - `qa-theme.sh`
  - `qa-risk-scan.sh`
  - `qa-security.sh`
  - `qa-local-stack-site.sh`
  - `qa-rollout-contract.sh`
  - `qa-page-speed.sh`
  - `qa-playwright-local-stack-site.sh`
- Shared cross-project QA starters now live in:
  - `/Users/khofmeyer/Development/Local QA/lib/common.sh`
  - `/Users/khofmeyer/Development/Local QA/templates/qa-wordpress-theme.sh`
  - `/Users/khofmeyer/Development/Local QA/templates/qa-laravel-node.sh`
- QA workflow reference doc:
  `/Users/khofmeyer/Development/MRN/stack/QA.md`
- Theme browser smoke QA now lives in:
  `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/tests/playwright`
- Browser smoke coverage now includes the page editor builder UI by default on the Local stack site by provisioning a local-only `codex_qa_admin` user when explicit admin credentials are not supplied.
- Browser smoke coverage should also be used for rendered admin sanity, including leaked CSS-text detection and sticky-toolbar layout checks on `Site Configurations` when `mrn-config-helper` is active.
- Visual admin fixes should be verified with a fresh screenshot after the code change, not just by code inspection.
- Browser smoke coverage should also include `Editor Enhancements` sticky-toolbar sanity when `mrn-editor-tools` is active.
- Security QA now has a dedicated script that combines the risk scan, focused WordPress security sniffs, a lightweight secret-pattern scan, and runtime dependency audits.
- Stack rollout QA now has a dedicated contract check at `/Users/khofmeyer/Development/MRN/stack/scripts/qa-rollout-contract.sh` to verify packaged theme parity, stack and live shared runtime presence, live active stylesheet parity, and rollout-owned CPT registration on `default-configs.mrndev.io`.
- Adoption guidance for other repos now lives in:
  `/Users/khofmeyer/Development/Local QA/README.md`

## Quality Priorities
- Performance is a first-class requirement.
- SEO is a first-class requirement.
- Accessibility is a first-class requirement.
- Use seeded local QA pages as the standard acceptance harness for builder and shell changes.
