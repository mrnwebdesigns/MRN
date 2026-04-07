# Architecture

## Workspace Root
- Canonical workspace root: `/Users/khofmeyer/Development/MRN`
- Historical references to `/Users/khofmeyer/Sites/MRNPlugins` are archival only.

## Repository Layout
- `plugins/`: canonical source for normal plugins
- `mu-plugins/`: canonical source for MU plugins
- `shared/`: intentionally shared cross-plugin source
- `stack/`: stack orchestration, manifests, exports, themes, scripts, wrappers
- `clone/`: clone and import/export tooling
- `server/`: server-side helpers and ops scripts
- `releases/`: build artifacts only, never source of truth

## Stack Theme Topology
- Active stack theme source:
  `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack`
- `stack/mu-plugins/` is not the canonical home for MU plugin source.
- Top-level `mu-plugins/` is canonical.
- `stack/mu-plugins/` contains stack loader or root entry files that point at the canonical MU plugin set.
- Future site theming should happen in a site-specific child theme layered on top of the stack parent theme.

## Local QA Environment
- Local stack test site:
  `/Users/khofmeyer/Local Sites/mrn-plugin-stack/app/public/wp-content`
- The local test site points to the rebuilt workspace via symlinks for the active plugin and MU plugin set.
- The active local theme slug `default-configs` symlinks to:
  `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack`
- Local WP-CLI path:
  `/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp`

## Release Artifact Locations
- Plugin artifacts:
  `/Users/khofmeyer/Development/MRN/releases/plugins`
- MU plugin artifacts:
  `/Users/khofmeyer/Development/MRN/releases/mu-plugins`
- Stack artifacts:
  `/Users/khofmeyer/Development/MRN/releases/stack`
- Clone artifacts:
  `/Users/khofmeyer/Development/MRN/releases/clone`

## Theme And Plugin Ownership Model
- The theme owns the universal `MRN Content Builder` experience for `post` and `page`.
- Reusable blocks are plugin or MU-owned content primitives for centrally managed, shared content patterns.
- Theme ACF layouts are page- or post-owned composition tools for one-off or page-specific content.
- Site-specific styling and branding overrides should live in the site child theme rather than modifying the shared parent stack theme per site.
- If content is edited once and reused in many places, prefer a reusable block type.
- If content belongs only to the current entry, prefer a theme builder layout.
- Converting a reusable block into page-specific content is a theme-level editor action, not a persistent toggle field.
- The theme owns builder-aware rendering and template parts.
- The reusable block library owns reusable block data models, block post types, and reusable block admin UX.

## Builder And Shell Architecture
- Shared `Section Width` is the standard body layout control:
  `Content`, `Wide`, `Full Width`
- `After Content` is a placement bucket that uses the same layout vocabulary as `Content`.
- Wrapper and shell logic should be centralized in shared helpers.
- The shell remains the durable contract for builder layouts and reusable-block wrappers.
- `Content`, `Wide`, and `Full Width` are real shell contracts, not hints; inner layout CSS must not silently re-clamp wide/full rows back to content width with one-off `max-width` caps.
- Parent-theme row spacing should only provide a minimal anti-collision fallback by default; child themes own the page rhythm.
- Row or block variants may choose a distinct composition, but that should come from explicit row/root modifiers and shared grid structure, not random narrow inner wrappers.
- Future layout and reusable-block work should keep shell classes stable and normalize inner markup instead of inventing new inner structures per family.
- Hero is a separate contract from the body width system and should not be forced into the layered `Content` / `Wide` / `Full Width` naming model.
- Singular sidebar behavior is theme-owned in `mrn-base-stack`, not a one-off page template pattern.
- Sidebar content is builder-owned and uses cloned `Content` layouts.
- The stack does not use WordPress widgets as the source for the singular sidebar feature.

## Inner Markup Architecture
- Future builder layouts and reusable blocks should use one shared semantic inner-markup model wherever the concept matches.
- Row or block modifiers should decide presentation; inner child classes should describe structure and content role, not layout-specific styling tricks.
- Prefer one stable inner root per layout or block, then short shared descendants beneath it.
- Preferred shared inner targets are:
  - `__head` for intro/header wrapper
  - `__body` for primary text/body wrapper
  - `__items` for a repeated-item collection
  - `__item` for one repeated entry
  - `__media` for media content
  - `__label` for supporting label text
  - `__heading` for the primary item or section heading
  - `__sub` for subheading/supporting heading text
  - `__text` for rich text or descriptive copy
  - `__link` for a single action link
  - `__actions` for grouped actions
- When a repeater item uses the normal collection pattern, do not invent family-specific equivalents such as `__slide-heading`, `__item-title`, `__question`, or `__answer`; prefer the shared names above unless the content model is genuinely unique.
- Repeater-driven layouts should default to `__items > __item` and then reuse the same child vocabulary inside each item.
- Existing legacy inner classes can remain for compatibility, but new work should add or prefer the normalized inner names.

## Parent Theme Front-End Baseline
- `mrn-base-stack` should be a neutral front-end base for child themes.
- Shared theme variables and normalized `mrn-ui__*` inner wrappers are the preferred styling API.
- The parent theme should not own visual row rhythm beyond the minimal anti-collision fallback token.
- A child theme should be able to create a boxed or more opinionated system across layouts by styling shared wrappers and variables instead of many layout-specific classes.

## Content Lists Architecture
- `Content Lists` is a targeted system, not a stack-wide builder rule.
- Client-facing display mode management lives in `Site Configurations -> Display Modes`.
- Builder `Content Lists` should only surface post-type display modes whose subtype matches the selected list content type.
- The implementation should remain helper-driven and filterable so future list-capable layouts can reuse the mode registry and item renderer.
- `Content Lists` is also available as a reusable block type through `mrn-reusable-block-library`.
- The reusable content-list CPT slug is `mrn_reusable_list`.

## Theme Options And Business Data
- Header/footer options are theme-owned, not Config Helper-owned.
- Canonical theme module:
  `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/inc/theme-options.php`
- Theme-owned options pages:
  - `Theme Header/Footer`
  - `Business Information`
- Shared business information is exposed through theme helpers and powers header, footer, and schema output.

## Deployment Topology
- Theme rollout manifest should install from the packaged zip path:
  `/home/mrndev-stack-manager/stack/themes/mrn-base-stack.zip|active`
- Canonical local helper for live theme refreshes:
  `/Users/khofmeyer/Development/MRN/stack/scripts/deploy-live-theme.sh`
- Stack automation is designed to run as `mrndev-stack-manager`.
- Live site syncs should run as the destination site owner via `sudo -n -u <site-user> rsync`.
- Site update planning should consider both parent-theme updates and any required child-theme follow-up when the shared rendering contract changes.

## Parent Theme Front-End Baseline
- The stack parent theme should be a neutral front-end base for future site child themes.
- Shells own width and section spacing; parent-theme row CSS should avoid one-off box chrome, decorative hover effects, or narrow inner clamps by default.
- Shared inner markup and shared CSS variables are the primary theming contract for child themes.
- Child themes should be able to apply broad visual systems, including boxed-item treatments, through shared variables and shared semantic classes instead of per-layout override selectors.
- Parent-theme defaults should prefer:
  - stable shell/container/grid structure
  - shared `mrn-ui__*` semantic inner targets
  - shared spacing and panel variables
  - neutral media presentation unless a behavior truly requires otherwise
