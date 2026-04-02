# Stack Changelog

## 2026.04.02-editor-sidebar-collapse
- Expanded `mrn-editor-lockdown` to `1.0.2`.
- Added a classic-editor right-sidebar collapse control for posts, pages, editorial CPTs, and reusable-block screens that use the locked two-column edit shell.
- Kept the collapse state sticky while scrolling and preserved full-width `#post-body-content` expansion when the sidebar is hidden.
- Scoped the new interaction to the admin/editor shell and deferred any front-end singular-sidebar collapse behavior for a later pass.

## 2026.04.01-gallery-editorial-cpts
- Expanded `mrn-base-stack` to `1.1.0`.
- Expanded `mrn-editor-lockdown` to `1.0.1`.
- Added the theme-owned `gallery` CPT module with dedicated gallery fields, rendering helpers, front-end assets, and shared singular-shell support for Hero, After Content, and Sidebar.
- Split the theme post-type support lists so `gallery` can use the shared shell without inheriting the normal middle `Content` builder.
- Reorganized the `blog` and `gallery` add/edit screens so excerpt authoring sits directly after the title instead of in the default metabox flow.
- Moved the `blog` author box into the sidebar and extended metabox lockdown support to both `blog` and `gallery` screens.
- Extended SmartCrawl subject markup generation so gallery entries include gallery-body content in SEO analysis.

## 2026.04.01-blog-cpt
- Expanded `mrn-base-stack` to `1.0.9`.
- Added a theme-owned `blog` custom post type with the default WordPress admin list and add-new menu entries.
- Extended the theme-owned hero/content/after-content/sidebar builder shell so `blog` entries follow the same editing flow as regular posts.
- Normalized the theme runtime version constant with the packaged theme header version before release.

## 2026.04.01-content-lists-pagination-polish
- Expanded `mrn-base-stack` to `1.0.8`.
- Removed the temporary custom ordered-list badge styling from `Content Lists`.
- Simplified `Content Lists` pagination styling to a plain horizontal link row.
- Added row-anchor pagination behavior so next/previous paging returns the browser to the same content-list row instead of the top of the page.

## 2026.04.01-reusable-content-lists-fix
- Expanded `mrn-base-stack` to `1.0.7`.
- Expanded `mrn-reusable-block-library` to `0.1.5`.
- Shortened the reusable content-list post type slug to `mrn_reusable_list` so it registers correctly under WordPress' post-type length limit.
- Repackaged and redeployed the reusable content-list rollout after runtime QA caught the registration failure.

## 2026.04.01-reusable-content-lists
- Expanded `mrn-base-stack` to `1.0.6`.
- Expanded `mrn-reusable-block-library` to `0.1.4`.
- Added a reusable `Content Lists` block type to the reusable block library.
- Added `Display Mode` support to `Content Lists`, including a lighter `Title Only` presentation.
- Passed host page context into reusable block rendering so reusable content-list blocks can use current-page term filtering and pagination inside the page builder.
- Added shared label-tag controls across reusable block types so reusable labels follow the same HTML-tag contract as the theme builder.

## 2026.04.01-sidebar-builder
- Expanded `mrn-base-stack` to `1.0.5`.
- Added a theme-owned singular sidebar shell for posts and pages.
- Moved sidebar authoring into its own builder field group after `After Content`.
- Removed the widget-area dependency from the sidebar feature and let sidebar content use cloned `Content` layouts instead.
- Kept the normal singular title, featured image, and main `Content` / `After Content` flow in the primary column while the sidebar renders as a secondary builder column.

## 2026.04.01-content-lists
- Expanded `mrn-base-stack` to `1.0.4`.
- Added a new theme-owned `Content Lists` builder layout for query-driven content listings.
- Added builder controls for content type, list style, ordering, count, pagination, excerpt handling, read-more labels, and empty-state behavior.
- Added contextual and manual taxonomy filtering to `Content Lists`, including current-page term matching.
- Added builder-admin filtering so the `Content Lists` taxonomy and term controls narrow to the selected content type and taxonomy.
- Added the option to suppress rendering the entire `Content Lists` row when a query returns no results.

## 2026.04.01-effects-foundation
- Expanded `mrn-base-stack` to `1.0.3`.
- Expanded `mrn-site-colors` / `Site Styles` to `0.1.3`.
- Added selectable HTML tag support for builder and reusable-block label fields.
- Added Motion to the base theme as a shared front-end dependency for row-level effects.
- Added builder-level `Motion Effects` controls across theme layouts, nested Two Column layouts, and reusable-block wrapper rows.
- Added the first Site Styles-backed effect preset family for `Darken Card On Scroll`.
- Added the row contract for `data-mrn-effect-preset` so Site Styles can skin effect mechanics without owning the runtime.
- Added frontend documentation for Motion usage, Site Styles-backed effect presets, and the builder output contract.
- Cleaned the Site Styles admin UI for motion presets so the new controls fit the WordPress settings screen cleanly.

## 2026.03.29-theme-foundation
- Expanded `mrn-base-stack` to `1.0.2`.
- Added a source-controlled `Business Information` options page to the canonical theme.
- Added a source-controlled `Theme Header/Footer` options page to the canonical theme.
- Added starter header and footer rendering contracts backed by theme options, native menu locations, and business-information helpers.
- Added canonical business-logo priority and logo variants for header/footer usage.
- Added canonical business phone, text/SMS, address, weekday hours, and holiday hours data to the theme layer.
- Added theme-owned business JSON-LD output sourced from the canonical business-information contract.
- Added a separate `After Content` field group after the main `Content` builder for posts and pages.
- Modernized the singular page shell so builder sections can use centered containers and intentional wide/full-width behavior more cleanly on mobile and desktop.
- Added a shared `Section Width` contract for theme-owned builder layouts (`Content`, `Wide`, `Full Width`).
- Consolidated theme-owned builder wrapper behavior behind shared helper functions for width classes, accent attributes, and inline style output.
- Began layout-family normalization so key layouts visually express `Section Width` modes (first pass: Basic, Image Content, Card, Logos, Stats, Showcase).
- Extended layout-family normalization to include `Slider` so `Content/Wide/Full Width` differences are visually clearer on the QA harness pages.
- Extended layout-family normalization to `Video` and `Two Column Split` (width-aware padding, gaps, and header/video presentation).
- Documented width-mode QA and the list of CSS-normalized layouts for frontend/backend handoff in `BUILDER_CONVENTIONS.md`, `DEV_HANDOFF.md`, and `THEME_ROADMAP.md`.
- Added `Section Width` to theme builder **CTA** and **Grid** layouts (including page-only clones and nested Two Column variants), wrapped cloned reusable output with the shared shell in `render.php`, and added width-scoped CSS for `mrn-shell-section--reusable-cta` / `--reusable-grid`.
- Added a curated developer handoff doc plus theme roadmap/tasklist docs for backend/frontend delivery.

## 2026.03.27-foundation
- Established a stack-wide release record with a current baseline file and changelog.
- Packaged and synced the current builder foundation baseline to the stack server.
- Updated `mrn-base-stack` to `1.0.1`.
- Updated `mrn-reusable-block-library` to `0.1.3`.
- Updated `mrn-site-colors` to `0.1.2` while presenting in admin as `Site Styles`.
- Kept `mrn-editor-tools` at packaged baseline `1.8.13`.
- Added a dedicated Hero field group above Content for posts and pages.
- Capped Hero rows at one while keeping layout-based hero growth possible later.
- Added a shared bottom-accent contract for theme layouts and reusable blocks.
- Added Site Styles graphic element storage and accent spacing overrides.
- Normalized reusable block editors so WYSIWYG/media support works on the intended block types.
- Cleaned the tracked duplicate nested files from `mrn-reusable-block-library`.
- Manually refreshed `default-configs.mrndev.io` from the corrected stack baseline.

## Format
- Use one release heading per stack baseline or rollout milestone.
- Keep entries short and outcome-focused.
- Record stack-wide changes here even when the detailed implementation history lives in `THREAD_MEMORY.md`.
