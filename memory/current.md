# Current Work

## Active Scope
- Canonical workspace root: `/Users/khofmeyer/Development/MRN`
- Active implementation area: `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack`
- Local acceptance harness: seeded QA pages on the local stack test site at `/Users/khofmeyer/Local Sites/mrn-plugin-stack/app/public/wp-content`

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
- In builder UI, `Content Lists` should only expose post-type display modes that match the selected content type, with fallback to `Use Row Settings`.
- The implementation should stay helper-driven and filterable so other list-capable layouts can reuse the same registry and renderer later.
- Singular sidebar behavior is theme-owned in `mrn-base-stack`.
- Front-end singular-sidebar collapse was explored and intentionally deferred.
- Classic-editor sidebar collapse behavior is the active collapse contract and lives in `mu-plugins/mrn-editor-lockdown/mrn-editor-lockdown.php`.

## Recent Durable Decisions
- Current stack baseline is `2026.04.03-builder-layout-menu-dynamic`.
- Current stack theme version is `mrn-base-stack 1.1.3`.
- Theme rollout manifest must use the packaged stack theme zip path, not a bare slug:
  `/home/mrndev-stack-manager/stack/themes/mrn-base-stack.zip|active`
- `default-configs.mrndev.io` was refreshed on `2026-04-03` for the `Content Lists` display-mode and shared repeater-controls release.
- `mrn-seo-helper` now owns sidebar placement for its SEO title/meta description ACF group and must register on WordPress `init` after CPT registration.
- `mrn-editor-lockdown` preserves the SEO Helper box in locked classic-editor sidebars.

## Recent Release Notes
- The Card builder row now keeps shared row-level fields ahead of the card-specific repeater fields.
- The Add Row builder picker now discovers layouts from live registered builder metadata, keeps page-only conversion targets hidden automatically, and alphabetizes the visible list.
- The stack theme now includes a theme-owned `Testimonial` CPT with archive support and local ACF fields for name, company, position, website URL, rich text content, and image/logo.

## Active Ops Caveats
- Run stack automation as `mrndev-stack-manager`; running as `kyle` can still produce runtime status-file warnings.
- Default stack SSH target is `mrndev-stack-manager@167.99.54.77`.
- Stack site/server credential details are stored locally at `/Users/khofmeyer/Development/MRN/.local/secrets/default-configs-server-info.txt`.
- If work is for a specific project/site, request that site's server information instead of relying on the default stack SSH target.
- Live stack files should be written as the destination owner, not as `kyle`.
- `default-configs.mrndev.io` live theme files are currently readable, but ownership is still not normalized to the preferred site owner.
- Future live theme refreshes must avoid unreadable file modes like `670`, especially after manual or selective syncs.

## Current Tooling
- Local stack QA helpers now live in `/Users/khofmeyer/Development/MRN/stack/scripts`:
  - `qa-theme.sh`
  - `qa-risk-scan.sh`
  - `qa-security.sh`
  - `qa-local-stack-site.sh`
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
- Security QA now has a dedicated script that combines the risk scan, focused WordPress security sniffs, a lightweight secret-pattern scan, and runtime dependency audits.
- Adoption guidance for other repos now lives in:
  `/Users/khofmeyer/Development/Local QA/README.md`

## Quality Priorities
- Performance is a first-class requirement.
- SEO is a first-class requirement.
- Accessibility is a first-class requirement.
- Use seeded local QA pages as the standard acceptance harness for builder and shell changes.
