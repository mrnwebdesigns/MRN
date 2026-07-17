# Stack Changelog

## 2026.07.17-business-information-announcements
- Expanded `mrn-announcements` to `1.6.1` while preserving standalone manual scheduling.
- Added optional Business Information rules for outside-hours, closed-holiday, and modified-holiday announcements.
- Added three published-but-Off presets with render-time hours, holiday, and next-opening message tokens; no announcement is automatically enabled.
- Restored the Announcements navigation after curated Admin Menu Editor profiles that predate the plugin replace the generated post type menu.

## 2026.07.17-testimonial-schema
- Expanded `mrn-base-stack` to `1.2.83` and `mrn-schema-bridge` to `0.4.1`.
- Added render-aware testimonial schema: only testimonial quotes visibly output by the base theme are registered, deduplicated, and emitted as one footer `Quotation` graph.
- Mapped visible testimonial text and attribution to `Quotation`, `Person`, and optional employer `Organization` data without adding `Review`, `Rating`, or `AggregateRating` markup.

## 2026.07.17-schema-discovery-baseline
- Expanded `mrn-base-stack` to `1.2.82` and `mrn-schema-bridge` to `0.4.0`.
- Made Business Information the canonical organization identity source and added an Identity & Schema tab for organization type, names, public email, service area, coordinates, author policy, and separate AI search/training crawler policies.
- Added public-content SEO & Schema controls for automatic output, semantic page-intent overrides, supplemental-schema suppression, and schema description overrides.
- Merged bridge-owned Service, ContactPage, case-study, ProfilePage, and ImageGallery nodes into SmartCrawl's graph with entity/type deduplication and stable WebPage references.
- Fixed base-stack `case_study` schema coverage and added field-aware case-study and gallery descriptions.
- Added non-destructive SmartCrawl defaults for schema, social identity, sitemap ownership, instant indexing, analysis, and conservative archive/media behavior while retaining MRN SEO Helper ownership of title/meta templates.
- Added virtual robots.txt policy for AI search/retrieval versus training crawlers; no `llms.txt` output was added.
- Expanded Schema Health with canonical/noindex checks, repeated-entity-ID detection, and required property checks for Service, Article, ProfilePage, and LocalBusiness nodes.
- Added focused schema bridge contract regression coverage.

## 2026.07.16-team-members-locations-cpts
- Expanded `mrn-base-stack` to `1.2.81`.
- Expanded `mrn-editor-lockdown` to `1.0.32`.
- Registered Team Members (`team_member`) as a public, REST-enabled custom post type with a `/team/` archive route.
- Added a per-member Public Profile Page control that defaults on; disabled records remain available to team components but lose their standalone page, preview/permalink actions, search exposure, and core sitemap entry.
- Registered Locations (`location`) with the shared admin/data-only CPT contract so records remain queryable by stack components without public single pages, an archive/index, search exposure, previews, navigation visibility, or sitemap entries.
- Left both post types ready for their dedicated content fields without introducing placeholder content groups.
- Enrolled both CPTs in the shared locked two-column metabox layout and added a registry-driven placement contract so future stack-owned CPT settings boxes can be positioned after SEO without one-off screen-order code.
- Standardized the Page sidebar order as SEO Helper, Featured Image, Publish, available category/tag boxes, Page Attributes, collapsed Available Builders, collapsed Breadcrumb Trail, and Author last.
- Applied the same SEO-first sidebar and collapsed-utility contract to Posts, reusable blocks, explicit editorial CPT layouts, and all dynamically discovered Classic Editor CPTs; unsupported boxes remain absent while registered boxes retain the canonical relative order.
- Added an Admin Menu Editor metabox-config repair so stale imports cannot mark the required SEO Helper box absent and remove it from Posts, Pages, or CPT editor screens.

## 2026.07.16-tabbed-layout-tab-options
- Expanded `mrn-base-stack` to `1.2.80`.
- Added Tabbed Layout tab position controls for top left/center/right and left-of-content top/center/bottom.
- Added Tabbed Layout tab style controls for text, dividers, underline, underline track, outline pill, soft pill, button, segmented, filled, filled segmented, and tab treatments under Display Styles.

## 2026.07.16-tabbed-layout-contract
- Expanded `mrn-base-stack` to `1.2.79`.
- Renamed the Tabbed Layout builder label so the picker no longer exposes internal field hints.
- Moved Tabbed Layout orientation and equal-height controls into the shared Layout contract.
- Applied the shared Display Styles contract and background color surface handling to Tabbed Layout front-end output.
- Simplified each Tabbed Layout tab to a tab name, shared icon controls, and one nested row.

## 2026.07.16-slider-layout-contract
- Expanded `mrn-base-stack` to `1.2.78`.
- Renamed the Slider builder layout label so the picker no longer exposes internal field hints.
- Moved Slider per-view, navigation, autoplay, and timing controls into the shared Layout contract.
- Applied the shared Display Styles contract to Slider front-end output.
- Removed Slider-specific hardcoded surface padding so spacing is controlled by the spacing contract.
- Renamed the injected row-flex admin tab to Flexbox so it no longer masks layout-specific controls.
- Added focused keyboard navigation for slider touch and keyboard use.
- Added item-level Slider subheadings and a per-slide option to hide the visible link while making the full slide clickable.

## 2026.07.16-layout-mode-contract
- Expanded `mrn-base-stack` to `1.2.77`.
- Added a shared structural Layout Mode helper for item-based layouts that need multiple arrangements.
- Moved Logos Grid/Slider control into the Layout contract while preserving the saved `display_mode` key and front-end classes.
- Updated Showcase to use the shared Layout Mode helper while preserving the saved `stagger_style` key.
- Kept Display Styles focused on visual treatment instead of structural arrangement.

## 2026.07.16-showcase-image-layout
- Expanded `mrn-base-stack` to `1.2.76`.
- Changed Showcase Image Layout to default to a clean grid instead of collage for simple image groups.
- Added Showcase item-count classes so one, two, and three image rows render with balanced column spans, including older rows saved with the collage option.
- Removed overlapping collage grid placements so editorial Showcase layouts remain deterministic.

## 2026.07.16-stats-stack-animation-runtime
- Expanded `mrn-base-stack` to `1.2.75`.
- Updated the per-stat value animation to use the stack-provided Motion runtime for numeric interpolation.
- Kept the Stats visual spin/lock styling in CSS and preserved reduced-motion fallback behavior.

## 2026.07.16-stats-item-animation
- Expanded `mrn-base-stack` to `1.2.74`.
- Moved Stats value spin-in animation from a row-level effect to a per-stat toggle.
- Aligned Stats icon/value rendering so icon position applies beside the stat value.

## 2026.07.16-repeater-bulk-controls-disabled
- Expanded `mrn-base-stack` to `1.2.73`.
- Disabled the shared ACF repeater Collapse All / Expand All toolbar because it adds confusing UI to nested row editors.
- Left individual ACF row collapse behavior intact.

## 2026.07.16-stats-value-animation
- Expanded `mrn-base-stack` to `1.2.72`.
- Added a Stats-only `Stat Value Animation` effect with a `Spin In` option and configurable duration.
- Added front-end stat value spin/count animation that triggers once when numeric stats enter view and locks to the authored final value.
- Preserved static stat output by default and respected reduced-motion preferences.

## 2026.07.16-showcase-layout-contract
- Expanded `mrn-base-stack` to `1.2.71`.
- Renamed the Showcase builder layout label so the picker no longer exposes internal field hints.
- Moved Showcase Stagger Style and full-item-link controls into the shared Layout tab while preserving existing field keys and saved values.
- Moved Showcase Hover Effect into the shared Effects tab.
- Applied the shared Display Styles contract to the Showcase front-end section output.
- Removed legacy Showcase surface padding and duplicate shell overrides so row spacing controls own section spacing.

## 2026.07.16-stats-layout-contract
- Expanded `mrn-base-stack` to `1.2.70`.
- Renamed the Stats builder layout label so the picker no longer exposes internal field hints.
- Moved Stats Columns and Show Dividers into the shared Layout tab while preserving existing field keys and saved values.
- Applied the shared Display Styles contract to the Stats front-end section output.
- Removed legacy Stats shell padding and decorative item overrides so row spacing controls own section spacing.
- Cleaned Stats typography to avoid viewport-scaled font sizes and non-zero letter spacing.
- Cleaned the dynamic row-spacing save helper PHPCS annotations that support the shared spacing contract.

## 2026.07.16-logos-layout-contract
- Expanded `mrn-base-stack` to `1.2.69`.
- Expanded `mrn-reusable-block-library` to `0.1.26`.
- Renamed the page-owned Logos/Partners builder layout to Page Specific Logos/Partners.
- Mapped reusable Partners fields to the shared Logos layout contract so it receives Display Styles, Spacing, Layout, and Effects tabs.
- Moved Logos row/view and slider mechanics into the shared Layout tab while keeping Display Mode and Display Style in Display Styles.
- Updated reusable Partners rendering to honor Logos grid/slider display contracts and removed legacy width/margin shell CSS from its block styling.

## 2026.07.15-default-row-anchors
- Expanded `mrn-base-stack` to `1.2.68`.
- Expanded `mrn-reusable-block-library` to `0.1.25`.
- Made Name (admin use only) the default row anchor source whenever Anchor ID is blank.
- Preserved explicit Anchor ID as the highest-priority anchor source.
- Aligned FAQ Jump Nav, reusable block placements, cloned reusable layouts, and standalone reusable-block rendering with the same default-anchor contract.

## 2026.07.15-faq-jump-nav
- Expanded `mrn-base-stack` to `1.2.67`.
- Expanded `mrn-reusable-block-library` to `0.1.24`.
- Added an explicit FAQ Jump Nav builder layout for linking between multiple FAQ/Accordion sections on a page.
- Added FAQ nav opt-in fields to the reusable FAQ contract: Include in FAQ Jump Nav and Jump Nav Label.
- Added page-placement FAQ nav controls to saved reusable block placements so reusable FAQs can be used more than once with unique page anchors/labels.
- Rendered FAQ Jump Nav links only when a FAQ section has the nav toggle enabled and a Jump Nav Label, with Anchor ID available as an override.

## 2026.07.15-faq-layout-contract
- Expanded `mrn-base-stack` to `1.2.66`.
- Expanded `mrn-reusable-block-library` to `0.1.23`.
- Added the shared Display Styles, Spacing, Layout, Effects tab order to the standalone FAQs/Accordion editor, including a real FAQ Layout control for stacked or split heading/items presentation.
- Prevented page-builder FAQ clones from receiving a duplicate row-level tab stack when the reusable FAQ contract already provides it.
- Renamed the page-owned FAQ builder layout to Page Specific FAQ/Accordion.
- Suppressed empty FAQs/Accordion sections and answer-only rows before rendering so the output keeps valid native details/summary structure.
- Cleaned FAQ CSS to use stable type sizes, zero letter spacing, inherited text color, and native details-open visibility without relying on JavaScript.
- Cleaned ACF WYSIWYG editor controls so media, form, snippet, AI Assist, and Visual/Text controls align predictably in cloned builder layouts.
- Stacked FAQ item question and answer fields at full row width so WYSIWYG editors span the available builder editor space.

## 2026.07.15-grid-layout-contract
- Expanded `mrn-base-stack` to `1.2.65`.
- Expanded `mrn-reusable-block-library` to `0.1.22`.
- Added the shared Display Styles, Spacing, Layout, Effects tab order to the standalone Content Grid editor.
- Prevented page-builder Content Grid clones from receiving a duplicate row-level tab stack when the reusable Grid contract already provides it.
- Suppressed empty Content Grid sections/items before rendering so required blank repeater rows do not output empty markup.
- Cleaned Grid CSS to use stable type sizes, zero letter spacing, and the shared grid spacing variables without redundant shell gap overrides.
- Resolved adjacent theme QA blockers for footer/tabbed image helper escaping, logo-link accessible names, and footer copyright contrast.

## 2026.07.15-cta-layout-contract
- Expanded `mrn-base-stack` to `1.2.64`.
- Expanded `mrn-reusable-block-library` to `0.1.21`.
- Normalized CTA editor tabs to the shared Content, Configs, Display Styles, Spacing, Layout, Effects order.
- Removed duplicate Effects segments created by cloned reusable CTA fields in page builder rows.
- Applied Display Style classes and attributes to cloned reusable row shells, including CTA.
- Renamed page-owned CTA choices to Page Specific CTA and kept the legacy page-only CTA clone out of per-entry layout pickers.
- Added Basic-style CTA appearance controls for gradients and background video, with matching front-end rendering.
- Kept CTA width controls aligned with the shared Basic Configs > Appearance contract.
- Updated CTA rendering so rows output when any CTA field has data, including background-only rows, while fully empty CTA rows stay suppressed.
- Added CTA content image support with left/right placement controls and responsive media-stack rendering.
- Limited Section Width (Sub-content) to collection-style layouts with real inner item wrappers.

## 2026.07.15-content-lists-layout-contract
- Expanded `mrn-base-stack` to `1.2.59`.
- Restored Content Lists Display Mode to the Display Styles tab while preserving its query-driven display choices.
- Applied the tighter builder row render contract to Content Lists and removed its built-in surface padding fallback.
- Loaded public custom post types into the Content Lists source and manual content picker so rows can query sources such as Testimonials.
- Sourced Content Lists Display Mode choices only from Site Configurations display modes, filtered by the selected Content Source.
- Honored selected Display Mode field lists when rendering Testimonial items in Content Lists.

## 2026.07.15-body-text-layout-contract
- Expanded `mrn-base-stack` to `1.2.58`.
- Applied the shared Display Styles and row spacing runtime contracts to the Body Text layout.
- Removed Body Text's built-in surface padding fallback so spacing is controlled by the shared spacing presets.
- Restored Body Text to the per-entry Available Builder Layout Types selector and alphabetized those layout lists by label.

## 2026.07.10-two-column-split-gradient-controls
- Expanded `mrn-base-stack` to `1.2.57`.
- Added the shared gradient controls and overlay rendering contract to Two Column Split, including hero-builder usage.

## 2026.07.10-gradient-color-opacity-controls
- Expanded `mrn-base-stack` to `1.2.56`.
- Added start and end opacity range controls for Basic/Hero gradient colors while preserving Site Color variables.

## 2026.07.10-gradient-media-overlay-layer
- Expanded `mrn-base-stack` to `1.2.55`.
- Rendered Basic and Hero row gradients as overlay layers above decorative background image/video media and below row content.

## 2026.07.10-basic-gradient-stop-controls
- Expanded `mrn-base-stack` to `1.2.54`.
- Replaced the Basic gradient direction dropdown with draggable angle and color-stop range controls.
- Rendered Basic gradient styles through the Hero template when Basic is used in the Hero builder.

## 2026.07.10-basic-gradient-controls
- Expanded `mrn-base-stack` to `1.2.53`.
- Added Basic layout background gradient controls under Configs > Appearance using Site Colors and directional rendering.

## 2026.07.10-shared-link-button-contract
- Expanded `mrn-base-stack` to `1.2.52`.
- Made shared `mrn-ui__link--button` and link icon styles apply anywhere the shared class is rendered, including Hero buttons.

## 2026.07.10-remove-hero-link-button-styling
- Expanded `mrn-base-stack` to `1.2.51`.
- Removed Hero-specific link border, padding, and no-underline styling so hero links are not treated as buttons by default.

## 2026.07.10-hero-basic-link-style-contract
- Expanded `mrn-base-stack` to `1.2.50`.
- Scoped Hero Basic CTA border/padding styles to explicit button links so normal hero links render as links.

## 2026.07.09-idle-acf-row-collapse-dirty-state-fix
- Expanded `mrn-base-stack` to `1.2.49`.
- Reset ACF's unload dirty-state after the automatic initial row-collapse pass, only when no editor interaction happened.
- Prevented page-load collapse from causing a false "unsaved changes" browser warning when leaving an unchanged editor screen.

## 2026.07.09-idle-acf-row-collapse
- Expanded `mrn-base-stack` to `1.2.48`.
- Restored automatic initial ACF flexible-content and repeater row collapsing for stack singular editors.
- Scheduled initial collapse after a short idle delay, batched row toggles in tiny frames, capped row counts, and aborts the pass once the editor starts interacting with fields.
- Added filters for the collapse delay and maximum initial flexible/repeater row counts so client sites can tune or disable the behavior without editing theme assets.

## 2026.07.09-layout-engine-display-styles-and-sidebar-templates
- Expanded `mrn-base-stack` to `1.2.47`.
- Expanded `mrn-base-stack-child` to `1.0.1`.
- Expanded `mrn-editor-lockdown` to `1.0.25`.
- Expanded `mrn-template-inspector` to `0.2.7`.
- Added the Display Styles registry and layout field wiring so layouts can offer style choices that work alongside Display Modes without bloating row templates.
- Split front-end layout assets into targeted files and added shared semantic image/media helpers for responsive row background imagery and background video.
- Converted page sidebar selection from ACF position controls into WordPress page templates: `Sidebar Left` and `Sidebar Right`.
- Removed the child-theme rollout starter template from selectable Page Attributes while keeping the child scaffold focused on site-specific overrides.
- Kept the Page Attributes metabox open by default while preserving locked editor metabox ordering.

## 2026.07.08-primary-menu-standalone-row
- Expanded `mrn-base-stack` to `1.2.35`.
- Moved the Primary menu out of the Header layout grid and rendered it as a standalone navigation row directly below the header.
- Removed Primary menu from the Header Layout editor contract so it is no longer draggable with header components.

## 2026.07.08-header-footer-appearance-spacing
- Expanded `mrn-base-stack` to `1.2.34`.
- Added Header/Footer Config appearance controls for background color, font color, link colors, and font family.
- Added a Header/Footer Spacing subtab that reuses the shared row spacing preset contract.

## 2026.07.08-header-footer-remove-content-tab
- Expanded `mrn-base-stack` to `1.2.33`.
- Removed the placeholder Content subtab from Header/Footer options so the subtab set only shows active control areas.

## 2026.07.08-header-footer-content-width-contract
- Expanded `mrn-base-stack` to `1.2.32`.
- Aligned Header/Footer Content Width behavior with builder row width contracts so Full Width removes the inline gutter.

## 2026.07.08-header-footer-content-width
- Expanded `mrn-base-stack` to `1.2.31`.
- Added Header/Footer Layout controls for Content Width using the same Content, Wide, and Full Width choices as builder rows.
- Applied saved Header/Footer width choices to the front-end layout grid shell.

## 2026.07.08-header-footer-layout-external-save-freeze
- Expanded `mrn-base-stack` to `1.2.30`.
- Bound the Header/Footer layout save freeze at the document level so the external `form="post"` Save Settings button is caught before blur/change handlers run.
- Added version-prefixed admin asset URLs and no-cache headers for the Header/Footer settings screen to prevent stale editor JavaScript.

## 2026.07.08-header-footer-layout-save-click-freeze
- Expanded `mrn-base-stack` to `1.2.29`.
- Froze Header/Footer layout editor re-renders as soon as the Save control is pressed so blur/change events cannot move chips before navigation.
- Kept save payload mirroring intact while preventing click-time grid mutation.

## 2026.07.08-header-footer-layout-submit-state-fix
- Expanded `mrn-base-stack` to `1.2.28`.
- Prevented Header/Footer layout Save from mutating the live grid editor state before the admin page refreshes.
- Mirrored save-only layout JSON into hidden ACF request fields without dispatching editor change events during submit.

## 2026.07.08-header-footer-layout-save-stability
- Expanded `mrn-base-stack` to `1.2.27`.
- Stabilized Header/Footer layout saves by preserving the full layout item map during submit and ACF persistence.
- Kept disabled Config components hidden from the editor/front end without compacting or moving their saved grid positions.

## 2026.07.08-header-footer-config-heading-dedupe
- Expanded `mrn-base-stack` to `1.2.26`.
- Prevented the Header/Footer Config fallback organizer from creating duplicate group headings when PHP-rendered headings are already present.
- Kept the Config field layout polish without moving server-rendered ACF heading fields across Header/Footer tabs.

## 2026.07.08-header-footer-config-organization
- Expanded `mrn-base-stack` to `1.2.25`.
- Reordered Header/Footer Config controls into user-facing groups for Navigation, Business Info, Search/Social, and Footer Text.
- Added compact two-column admin layout hints for Config toggles and detail controls.

## 2026.07.08-header-footer-layout-active-components
- Expanded `mrn-base-stack` to `1.2.24`.
- Removed disabled Header/Footer Config components from the layout-grid editor and saved layout JSON.
- Filtered front-end layout placement data to enabled components so turned-off config components do not reserve grid slots or render.

## 2026.07.08-header-footer-layout-save-fix
- Expanded `mrn-base-stack` to `1.2.23`.
- Fixed Header/Footer layout-grid saves so the custom grid editor mirrors its JSON into submitted ACF fields and a dedicated fallback request key before the options form posts.
- Kept hidden layout storage fields submittable while visually removing them from the admin UI, and preserved direct Footer Layout tab links after save/reload.

## 2026.07.07-bootstrap-credential-provisioning-hardening
- Expanded `mrn-config-helper` to `0.1.43`.
- Hardened fresh-site bootstrap credential writes so the reCAPTCHA Enterprise private key is written as a raw PHP string literal instead of being parsed as a WP-CLI option.
- Improved UptimeRobot bootstrap diagnostics and treated duplicate-monitor API conflicts as an already-ready state.

## 2026.05.06-front-end-runtime-autoload-for-reusable-interactions
- Expanded `mrn-base-stack` to `1.2.1`.
- Updated stack runtime enqueue behavior so front-end interaction assets can load on singular shell content when layout builder is disabled but runtime markers are present in content/meta.
- Preserved compatibility by keeping the marker list filterable (`mrn_base_stack_front_end_runtime_markers`) and the final runtime enqueue decision filterable (`mrn_base_stack_should_enqueue_front_end_runtime`).
- Fixed FAQ/tabs/slider interaction regressions on pages that rely on reusable-block or stack runtime features outside the layout-builder path.

## 2026.04.22-developer-reference-sidebar-and-editor-runtime-hardening
- Expanded `mrn-base-stack` to `1.1.34`.
- Expanded `mrn-active-style-guide` to `0.1.3`.
- Expanded `mrn-editor-lockdown` to `1.0.17`.
- Expanded `mrn-reusable-block-library` to `0.1.16`.
- Expanded `mrn-dummy-content` to `0.1.14`.
- Expanded `mrn-universal-sticky-bar` to `1.1.1`.
- Added a new `Appearance -> Developer Reference` admin screen with tabbed quick-copy references for templates, CSS variables, hooks/assets, shell contracts, reusable-block shortcodes, and starter snippets.
- Kept SmartCrawl visible by default on edit screens by making legacy metabox removal opt-in through `mrn_editor_lockdown_remove_legacy_seo_metabox`.
- Added manual post-selection support to theme and reusable Content Lists (`filter_source=manual_posts`) with published-item and post-type guards, preserving editor-chosen ordering.
- Restored singular sidebar support when builder runtime is disabled by always loading the sidebar module and falling back to sanitized `sidebar_content` output.
- Simplified sidebar layout choices to left/right only and removed the legacy no-sidebar mode in the singular-sidebar contract.
- Lowered shared/admin sticky-toolbar z-index levels to prevent media picker and admin-bar overlap regressions.

## 2026.04.22-layout-builder-hard-disable-and-loader-contrast
- Expanded `mrn-base-stack` to `1.1.33`.
- Expanded `mrn-editor-lockdown` to `1.0.16`.
- Hard-disabled the heavy theme layout-builder runtime by default in the rollback track and kept safe content fallbacks so classic content rendering remains stable.
- Restored shared ACF text/tag helper functions in the builder-disabled path so testimonial and case-study field groups continue to register cleanly.
- Improved non-blocking editor loading-indicator readability with dark text on a light scrim to avoid invisible white-on-light loader copy.

## 2026.04.22-editor-loading-indicator-text-black
- Expanded `mrn-editor-lockdown` to `1.0.15`.
- Updated the centered non-blocking editor loading indicator label text to black for improved visual preference/readability.

## 2026.04.22-editor-loading-indicator-strong-nonblocking
- Expanded `mrn-editor-lockdown` to `1.0.14`.
- Strengthened the classic-editor non-blocking loading indicator with a clearer centered spinner/message treatment and a light non-interfering scrim.
- Reduced indicator linger timing so feedback appears fast but clears quickly once the editor is ready.

## 2026.04.22-editor-loading-indicator-centered-visibility
- Expanded `mrn-editor-lockdown` to `1.0.13`.
- Updated the non-blocking classic-editor loading indicator visual treatment to a centered spinner/message so editors can clearly perceive feedback during long loads.
- Kept the indicator non-blocking (`pointer-events: none`) and retained the early `admin_head` bootstrap path.

## 2026.04.22-editor-loading-indicator-pseudo-head
- Expanded `mrn-editor-lockdown` to `1.0.12`.
- Switched the non-blocking classic-editor loading indicator to an early `admin_head` pseudo-element (`html::before/::after`) so it is visible during long pre-footer admin loads.
- Removed indicator DOM-injection timing dependencies and extended the post-load visibility window slightly for clearer user feedback.

## 2026.04.22-editor-loading-indicator-head-bootstrap
- Expanded `mrn-editor-lockdown` to `1.0.11`.
- Moved non-blocking editor loading-indicator bootstrap to `admin_head` so it can appear before footer scripts execute on slow classic-editor loads.
- Increased non-blocking indicator visibility timing slightly so editors can reliably see feedback while preserving the no-blocking interaction model.

## 2026.04.22-editor-loading-feedback-nonblocking
- Expanded `mrn-editor-lockdown` to `1.0.10`.
- Kept the heavyweight classic-editor loading mask opt-in only, but restored editor load feedback with a lightweight non-blocking loading indicator.
- Ensured the indicator no longer blocks clicks/typing while the editor warms up, preserving the interactivity gains from the mask-default-off performance rollout.

## 2026.04.22-effects-permissions-removed
- Expanded `mrn-base-stack` to `1.1.32`.
- Removed the builder `layout_effects` capability gating hooks from the global ACF field-prepare/save path so Effects controls are no longer permission-gated in admin.
- Eliminated repeated `current_user_can( 'layout_effects' )` checks on ACF-heavy editor screens, reducing edit-page admin latency on large layout payloads.

## 2026.04.21-editor-ready-mask-disabled
- Expanded `mrn-editor-lockdown` to `1.0.9`.
- Disabled the heavyweight classic-editor loading mask by default so post edit screens become interactive as soon as WordPress/ACF can accept input, instead of waiting for the deferred ready-state gate.
- Added the `mrn_editor_lockdown_loading_mask_enabled` filter so environments that still want the legacy full-screen loading overlay can opt in explicitly.

## 2026.04.21-editor-load-latency-collapse-default-off
- Expanded `mrn-base-stack` to `1.1.31`.
- Disabled automatic initial builder/repeater row precollapse by default on classic editor screens so the editor stays interactive sooner on ACF-heavy pages.
- Added a stack filter (`mrn_base_stack_admin_initial_collapse_enabled`) to opt back into the previous precollapse behavior when needed for specific environments.

## 2026.04.21-editor-latency-hotfix-wpnonce-bootstrap
- Expanded `searchwp-editor-performance` to `1.0.6`.
- Fixed a plugin bootstrap fatal on classic editor admin requests by guarding `wp_verify_nonce()` usage until the pluggable function is available.
- Preserved the existing SearchWP editor-request performance suppression behavior while removing the early-load crash path.

## 2026.04.21-editor-latency-hardening-v3
- Expanded `searchwp-editor-performance` to `1.0.5`.
- Made SearchWP bootstrap suppression load-order-safe by removing SearchWP `init` callbacks again on `plugins_loaded` at max priority for classic post editor requests.
- Preserved the existing local/development guardrails and production inert behavior.

## 2026.04.21-editor-latency-hardening-v2
- Expanded `mrn-base-stack` to `1.1.30`.
- Expanded `searchwp-editor-performance` to `1.0.4`.
- Hardened the SearchWP editor performance guard to disable SearchWP core `init` bootstrap on classic post editor requests (`post.php` / `post-new.php`) in local/development, preventing source-hook registration and index-drop churn during heavy ACF saves.
- Added an extra admin-init safety pass for SearchWP source callback removal on classic editor save requests.
- Updated builder/repeater admin collapse batching to defer while users interact with editor inputs, reducing residual click-to-caret lag in large flexible/repeater screens.

## 2026.04.21-editor-save-and-focus-latency-hardening
- Expanded `searchwp-editor-performance` to `1.0.3`.
- Added an editor-save request guard that short-circuits SearchWP source hook registration during classic `editpost` saves, preventing expensive index-drop hook churn on large ACF submissions.
- Relaxed strict nonce gating for editor-save detection to avoid false negatives on stacked/classic admin save flows where nonce transport can vary but request action remains `editpost`.
- Kept the existing environment guardrails so production remains inert unless explicitly forced.

## 2026.04.21-editor-focus-lag-smoothing
- Expanded `mrn-base-stack` to `1.1.29`.
- Reworked initial Classic Editor builder/repeater precollapse behavior so row collapsing runs in small animation-frame batches instead of a single long synchronous pass.
- Added a guard that pauses collapse batching while an editor input is actively focused, reducing cursor/caret delay when clicking into heading text fields during heavy page loads.
- Preserved existing collapsed-row behavior and repeater detachment contracts after the initial pass so large ACF pages keep their memory/load benefits without blocking early input focus.

## 2026.04.21-admin-input-lag-reduction
- Expanded `mrn-base-stack` to `1.1.28`.
- Expanded `mrn-reusable-block-library` to `0.1.15`.
- Switched shared heading/subheading/label tag chooser fields from Select2-enhanced selects to native selects in both the stack builder and reusable block field helpers.
- Reduced editor-side Select2 initialization overhead on large ACF pages with many cloned layouts, improving typing and dropdown responsiveness in heavy classic-editor builder screens.

## 2026.04.21-tabbed-editor-load-reduction
- Expanded `mrn-base-stack` to `1.1.27`.
- Reduced classic editor payload size for tabbed-builder rows by applying per-entry page-content allowlist filtering to nested tab panel row layouts.
- Preserved edit safety for existing tab-panel content by auto-including already-saved nested panel layout slugs in the filtered nested layout set.
- Kept nested `tabbed_layout` recursion blocked while reducing unnecessary cloned ACF layout markup that was inflating editor load time on heavy pages.

## 2026.04.21-editor-save-latency-hardening
- Expanded `mrn-base-stack` to `1.1.26`.
- Expanded `searchwp-editor-performance` to `1.0.2`.
- Hardened the builder allowlist save path so raw `php://input` parsing only runs as a truncation-recovery fallback instead of on every nonce-valid editor save request.
- Expanded the SearchWP editor performance runtime guard to remove both `Post` and `Attachment` source drop/meta callbacks during classic `editpost` saves in local/development.
- Kept the existing environment guardrails so production remains inert unless explicitly forced.

## 2026.04.21-builder-layout-canonicalization
- Expanded `mrn-base-stack` to `1.1.25`.
- Expanded `mrn-dummy-content` to `0.1.13`.
- Canonicalized Hero builder layout slugs to use `basic` and `two_column_split` directly, removing Hero-only alias behavior.
- Aligned reusable conversion mapping with canonical page-builder layout targets (`cta`, `grid`, `faq`) while keeping `basic_block` for the existing reusable basic schema contract.
- Updated Dummy Content layout detection and generation to prefer canonical Hero-compatible layout names (`basic` first, `hero` fallback) so generated QA content follows the stack builder contract.
- Resolved theme QA/security blocker findings in the stack theme baseline so release gates can run cleanly in the current source state.
- Added a documented versioning policy at `stack/RELEASE_VERSIONING_STRATEGY.md` and linked it from the stack README for future release consistency.

## 2026.04.20-searchwp-editor-performance
- Added a new standard plugin, `searchwp-editor-performance` (`SearchWP Editor Performance` `1.0.0`), to enforce SearchWP alternate indexer mode in local/development editor requests and avoid the expensive loopback method check that was slowing post edit/save operations.
- Kept the behavior environment-gated by default (`local` and `development` only) with explicit constants for force/disable overrides so production indexing behavior is unchanged unless intentionally overridden.
- Added the stack package path for this plugin to the canonical bootstrap plugin manifest so new rollouts include the performance guard by default.

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
