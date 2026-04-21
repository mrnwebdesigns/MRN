# Stack Changelog

## 2026.04.21-builder-layout-canonicalization
- Expanded `mrn-base-stack` to `1.1.25`.
- Expanded `mrn-dummy-content` to `0.1.13`.
- Canonicalized Hero builder layout slugs to use `basic` and `two_column_split` directly, removing Hero-only alias behavior.
- Aligned reusable conversion mapping with canonical page-builder layout targets (`cta`, `grid`, `faq`) while keeping `basic_block` for the existing reusable basic schema contract.
- Updated Dummy Content layout detection and generation to prefer canonical Hero-compatible layout names (`basic` first, `hero` fallback) so generated QA content follows the stack builder contract.
- Resolved theme QA/security blocker findings in the stack theme baseline so release gates can run cleanly in the current source state.
- Added a documented versioning policy at `stack/RELEASE_VERSIONING_STRATEGY.md` and linked it from the stack README for future release consistency.

## 2026.04.17-layout-contract-standardization
- Standardized the shared primary layout field contract across theme-owned layouts, reusable-block conversion surfaces, and nested non-link repeaters, including `Label`, `Heading`, `Subheading`, `Text`, and links plus row-level `Name (admin use only)`.
- Removed legacy admin label suffix copy (`(full editor)`, `(allowed html)`, `(tag chooser)`) and normalized heading-tag controls to the `Tag` label with the text/tag side-by-side pattern across repeaters and subfields.
- Consolidated non-link repeater item UX onto `Content | Configs | Effects`, added grouped/collapsible config organization, and kept `Effects` in its own tab while placing `Section Width` in the first `Basic Setting` group.
- Added/extended recursive repeater contract behavior (including subheading support), improved repeater discoverability with plural naming like `Grids`, and applied zebra striping between repeater/subfield rows for faster scanning.
- Unified link contract behavior across layouts by removing link-level `Background Color`, keeping link controls on shared link tabs, defaulting icon source to empty, and preventing icon-gap spacing when no icon is set.
- Added the lightweight non-ACF row `Layout` tab for flexbox controls and wired frontend rendering through the shared row contract using `mrn-content-builder__row--layout-flex` plus row CSS variables.
- Added an `Apply To` scope on that row-level flex control (`Row` vs `Repeaters Only`) so grid/repeater item collections can be centered without forcing the row intro/header wrapper into flex layout.
- Added child-theme override points for row flex behavior:
  - PHP filters: `mrn_base_stack_builder_row_flex_payload`, `mrn_base_stack_builder_row_flex_settings`, `mrn_base_stack_builder_flex_contract`
  - CSS override variables: `--mrn-row-flex-direction-override`, `--mrn-row-flex-justify-override`, `--mrn-row-flex-align-override`, `--mrn-row-flex-wrap-override`, `--mrn-row-flex-gap-override`

## 2026.04.13-page-edit-speed
- Expanded `mrn-base-stack` to `1.1.21`.
- Expanded `mrn-editor-lockdown` to `1.0.7`.
- Reduced repeated Classic Editor builder-admin work by caching post-init `Content Lists` post-type, display-mode, and taxonomy choice generation within the request instead of rebuilding the same ACF choice data for every prepared field instance.
- Reduced repeated classic-editor metabox layout writes by caching `mrn-editor-lockdown` layout lookups and skipping no-op user-meta updates when the enforced layout payload has not changed.
- Refined the builder Add Row menu ordering so standard layouts and reusable/shared layouts each stay alphabetized while preserving the reusable/shared section break in the editor menu.
- Documented the deploy rule that stack-managed sites run a cloned active theme by default, and that child themes are introduced later only during explicit development/front-end handoff.

## 2026.04.10-layout-effects-permissions
- Expanded `mrn-base-stack` to `1.1.19`.
- Expanded `mrn-site-colors` / `Site Styles` to `0.1.4`.
- Added a shared `layout_effects` capability that controls visibility of builder and reusable-block `Effects` tabs through Advanced Menu Editor role permissions.
- Enforced that same `layout_effects` capability at ACF save time so unauthorized users cannot change `motion_settings` by submitting hidden field data directly.
- Cleaned up the two earlier Effects-capability slugs from stored role records so the stack exposes one canonical permission in AME.
- Fixed the Site Styles settings screen so Site Colors, Graphic Elements, and Motion Presets keep unique row indexes across add/remove cycles and no longer silently overwrite later rows on save.

## 2026.04.09-effects-targets
- Expanded `mrn-base-stack` to `1.1.18`.
- Expanded `mrn-reusable-block-library` to `0.1.12`.
- Added shared `Apply To` targeting for non-surface motion effects so rows can direct effects to media, content, headings, item grids, or left/right sub-layout shells instead of only the outer row wrapper.
- Added reusable-block-native `Motion Effects` controls so saved reusable CTAs, basic blocks, FAQs, content grids, and content lists can carry their own motion contract when rendered outside or inside the page builder.
- Moved the effect-field injection into automatic field-group enhancement so future MRN flexible-content layouts and `mrn_reusable_*` field groups inherit motion controls without manual per-layout wiring.
- Updated the Motion runtime and frontend guide to honor the new `data-mrn-motion-target` contract while preserving the existing row-level defaults.

## 2026.04.06-neutral-layout-baseline
- Expanded `mrn-base-stack` to `1.1.17`.
- Expanded `mrn-reusable-block-library` to `0.1.11`.
- Normalized builder and reusable-block inner markup around the shared `mrn-ui__*` contract so layouts and reusable blocks expose one clearer semantic front-end API.
- Removed parent-theme default box chrome and reduced row spacing to a minimal anti-collision fallback so first-site child themes can own visual rhythm and broad boxed treatments without fighting per-layout defaults.
- Tightened shell-width compliance and moved more internal row/repeater spacing onto shared tokens so wide/full sections stay honest to the shell contract and future theming can target a smaller shared surface area.

## 2026.04.06-builder-anchor-width-polish
- Expanded `mrn-base-stack` to `1.1.16`.
- Expanded `mrn-reusable-block-library` to `0.1.10`.
- Expanded `mrn-dummy-content` to `0.1.9`.
- Added shared optional anchor fields across theme-owned builder rows and reusable block library items, and render those anchor targets at the top of the row/block so in-page anchor links land at the intended visual start.
- Tightened the full-width builder shell behavior so `Basic`, `Image Content`, and reusable CTA/full-width content stay inside the shared inset contract instead of drifting wide or stretching past the intended shell bounds on QA pages.

## 2026.04.06-image-content-and-grid-polish
- Expanded `mrn-base-stack` to `1.1.15`.
- Expanded `mrn-reusable-block-library` to `0.1.9`.
- Restored Content Grid column controls in the shared reusable-grid schema, re-enabled the matching rendered column classes, and added an opt-in equal-height mode that keeps item links pinned to the bottom without runaway card heights.
- Corrected the Image Content builder contract so the standard intro/content fields come before the image in the editor and on the front end, and tightened the full-width top/bottom presentation so the content card stays centered and the image behaves like a shallower banner instead of an oversized split-style panel.

## 2026.04.06-sticky-toolbar-overlap-fixes
- Expanded `mrn-base-stack` to `1.1.14`.
- Expanded `mrn-universal-sticky-bar` to `1.0.9`.
- Raised the shared settings-style sticky toolbar above high-z-index admin controls and switched its spacer handling to measure the rendered toolbar height instead of relying on hardcoded offsets.
- Restored the icon-only header search pill to its intended expanded appearance while keeping it as an overlay so it no longer shifts header layout or leaves the icon hover background behind.
- Raised the classic-editor universal sticky bar above overlapping inputs so admin field controls no longer paint over the toolbar.

## 2026.04.06-builder-width-and-social-polish
- Expanded `mrn-base-stack` to `1.1.13`.
- Expanded `mrn-config-helper` to `0.1.35`.
- Brought the wide-layout seeded QA page back onto the shared width system by aligning slider, text, and FAQ/accordion rows with the intended wide or content shells.
- Standardized `Two Column Split` on the shared `label` / `heading` / `subheading` intro-field contract and neutralized forced centered intro styling in `Showcase` and `Logos`.
- Added animated FAQ accordion open/close behavior and extended social-link settings so admins can save a distinct social name plus hover/accessibility text without misleading placeholders.

## 2026.04.06-stack-source-tracking
- Kept the surfaced stack source-of-truth files tracked after the `.gitignore` cleanup, including stack manifests, bootstrap/importer helpers, reference exports, archive docs, and the compatibility shim for the shared sticky-toolbar loader.
- Added the tracked stack wrapper loaders for `mrn-active-style-guide`, `mrn-editor-lockdown`, `mrn-reusable-block-library`, and `mrn-site-colors` so stack MU deploys and bootstrap paths can be verified from the main repo.
- Documented the child-theme compatibility rule across stack docs so future site updates preserve stable parent-theme theming hooks.

## 2026.04.06-ame-config-refresh
- Refreshed the canonical stack AME container export to the `2026-04-06` snapshot and kept the dated export alongside it for reference.
- Refreshed that same-day snapshot again after local AME changes updated the exported admin-menu tree, dashboard widgets, role capability index data, and table-column screen settings.
- Verified the refreshed AME payload imports cleanly on the local stack test site, including the stack-specific follow-up handling for the `roles-and-capabilities` component.

## 2026.04.05-social-link-icon-fallback
- Expanded `mrn-base-stack` to `1.1.12`.
- Hardened social-link rendering so media-based icons only output when the saved attachment still resolves to a real file.
- Added a safe fallback to text when a saved social icon points at deleted local media, preventing front-end `404` noise in QA and on sites with stale option data.

## 2026.04.05-builder-schema-standardization
- Expanded `mrn-base-stack` to `1.1.11`.
- Expanded `mrn-reusable-block-library` to `0.1.8`.
- Standardized builder, nested builder, reusable block, page, and editorial admin field layouts around the same `label`, `heading`, `subheading`, and matching tag-field contract.
- Added missing reusable-block subheading support for CTA, Content Grid, Content Lists, Basic Block, and FAQ so their field schema and rendered output match the main builder pattern.
- Removed old reusable/admin fallback naming like `text_field`, `text_field_tag`, and item `title`/`title_tag` from the active render path so the stack uses one canonical content-field model.

## 2026.04.05-admin-ui-and-search-fixes
- Expanded `mrn-base-stack` to `1.1.8`.
- Expanded `mrn-admin-ui-css` to `3.1.12`.
- Fixed the icon-only header search field so the inline `Search` prompt and typed value share a stable input lane again.
- Added shared admin suppression rules for the Media Library Organizer Pro notice and the Themeisle `WordPress Guides/Tutorials` dashboard widget plus its Screen Options toggle.

## 2026.04.04-shared-icon-chooser-rollout
- Expanded `mrn-base-stack` to `1.1.7`.
- Expanded `mrn-shared-assets` to `0.1.1`.
- Expanded `mrn-config-helper` to `0.1.34`.
- Expanded `mrn-editor-tools` to `1.8.17`.
- Added one canonical shared admin icon chooser in `mrn-shared-assets` and migrated `Theme Header/Footer`, `Editor Enhancements`, and `Site Configurations -> Social` onto that shared Dashicons / Font Awesome / media picker.
- Added icon-only header search controls and front-end behavior in `mrn-base-stack`, including expandable search UI, inline clear affordance, and shared chooser-backed admin controls.
- Extended configured social links so Site Configurations can save and render Dashicons alongside Font Awesome and media icons.

## 2026.04.04-shared-sticky-toolbar-rollout
- Expanded `mrn-base-stack` to `1.1.6`.
- Expanded `mrn-config-helper` to `0.1.33`.
- Expanded `mrn-editor-tools` to `1.8.16`.
- Expanded `mrn-acf-character-count` to `1.1.6`.
- Expanded `mrn-cookie-consent` to `1.1.21`.
- Expanded `mrn-gtm-injector` to `1.0.9`.
- Expanded `mrn-comment-management` to `1.1.7`.
- Expanded `mrn-license-vault` to `0.2.6`.
- Expanded `mrn-unified-exporter` to `1.2.6`.
- Consolidated settings-style sticky admin bars onto one canonical shared source with thin plugin loaders and a unique shared API so toolbar behavior no longer depends on plugin load order.
- Re-enabled `Theme Header/Footer` and `Business Information` on top of the shared toolbar contract with full-width content layouts and screenshot-backed admin QA.
- Expanded local Playwright admin smoke coverage to include `Site Configurations`, `Editor Enhancements`, `Theme Header/Footer`, and `Business Information`.

## 2026.04.04-bootstrap-reset-and-shared-runtime
- Updated the stack site bootstrap flow to remove any host-provided standard plugins before installing the MRN plugin manifest so fresh sites do not inherit extras like `hello` or provider defaults.
- Updated the stack site bootstrap flow to sync the shared runtime into `wp-content/shared` on first bootstrap so fresh sites match the stack runtime contract.

## 2026.04.03-testimonial-meta-content-list-modes
- Expanded `mrn-base-stack` to `1.1.5`.
- Expanded `mrn-reusable-block-library` to `0.1.7`.
- Split testimonial position and company onto separate meta lines in the theme’s testimonial templates and restored website-link output in list views.
- Added `compact` and `feature` as reusable content-list display mode choices and routed reusable content-list item rendering through the shared theme display-mode renderer.
- Added stable row-anchor IDs to reusable content lists so pagination links return to the same row after paging.

## 2026.04.03-config-helper-sendgrid-guidance
- Expanded `mrn-config-helper` to `0.1.30`.
- Updated the SendGrid management-key help text in `Site Configurations` to show the exact `wp-config.php` constant line for `MRN_SENDGRID_MANAGEMENT_API_KEY` while keeping the host-managed source guidance.

## 2026.04.03-testimonial-editorial-shell
- Expanded `mrn-base-stack` to `1.1.4`.
- Expanded `mrn-editor-lockdown` to `1.0.6`.
- Expanded `mrn-seo-helper` to `0.2.9`.
- Added the theme-owned `testimonial` CPT with archive support, dedicated ACF fields, and a testimonial-specific singular/archive template.
- Replaced the theme’s hardcoded editorial-CPT support lists with a shared helper so sticky-bar, hero/after-content shell support, and other editorial hooks stay aligned when new theme-owned CPTs are introduced.
- Extended classic-editor lockdown to dynamically lock supported non-reusable post types so SEO Helper stays pinned at the top of the sidebar and metabox movement locks continue to apply on new editorial CPTs.
- Updated SEO Helper’s dynamic post-type targeting to continue excluding reusable block library CPTs while still covering new standard/editorial post types.

## 2026.04.03-builder-layout-menu-dynamic
- Expanded `mrn-base-stack` to `1.1.3`.
- Moved the Card row builder order so the shared row-level fields appear before the card-specific repeater fields.
- Replaced the hardcoded Add Row layout/menu lists with live builder-layout metadata so new page-only conversion targets and reusable/shared layouts register automatically.
- Alphabetized the Add Row builder picker while keeping page-only conversion targets hidden from the normal editor menu.

## 2026.04.03-content-lists-display-modes
- Expanded `mrn-base-stack` to `1.1.2`.
- Expanded `mrn-config-helper` to `0.1.29`.
- Added a helper-driven `Content Lists` display-mode registry that can ingest client-managed `Site Configurations -> Display Modes` entries and limit the builder dropdown to modes that match the selected post type.
- Added a client-managed `Site Configurations -> Display Modes` admin workflow with a list-first editor, entity-aware mode definitions, field ordering, and `Content Lists` integration.
- Let `Content Lists` fall back to `Use Row Settings` when no display mode is selected, and updated builder-admin behavior so taxonomy, term, and display-mode controls stay in sync without stale select UI.
- Moved Content List item rendering behind shared theme helpers so reusable blocks and page-level rows use the same display-mode rendering contract.
- Promoted repeater `Collapse All` / `Expand All` controls into a shared ACF admin resource instead of keeping that affordance gallery-specific.

## 2026.04.02-gallery-mixed-media-polish
- Expanded `mrn-base-stack` to `1.1.1`.
- Polished the theme-owned `gallery` CPT editor for mixed-media authoring, including stronger per-item media-type locking, repeater collapse controls, and cleaner settings placement.
- Moved gallery item filtering onto the attachment-backed `gallery_media_category` taxonomy instead of freeform labels.
- Replaced the custom gallery lightbox with GLightbox and expanded the gallery body to support images, direct video files, and external video/embed URLs.
- Added gallery hover-effect controls, richer video/embed thumbnail handling, and centered play overlays while keeping gallery tiles on a consistent aspect-ratio contract.
- Corrected the workspace `.gitignore` / source-control surface so packaged theme exports include the full starter-theme runtime instead of a partial subset.

## 2026.04.02-editor-sidebar-chevron-fix
- Expanded `mrn-editor-lockdown` to `1.0.4`.
- Corrected the top-bar sidebar toggle chevron directions so expanded and collapsed states point the intended way.

## 2026.04.02-editor-sidebar-topbar-toggle
- Expanded `mrn-editor-lockdown` to `1.0.3`.
- Moved the classic-editor sidebar collapse control out of the metabox column and into the top admin tab row beside `Screen Options`.
- Matched the control more closely to native WordPress screen-option tabs, including a fixed-width single-line label and left/right chevron swap between states.
- Kept the right editor column collapse behavior and full-width `#post-body-content` expansion while removing the sidebar-edge/scrollbar placement issues from the earlier pass.

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
- Record stack-wide changes here even when the detailed implementation history lives in `memory.md`.
