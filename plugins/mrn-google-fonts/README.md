# Google Fonts

Phase 2 runtime for a performance-first Google Fonts workflow.

## Goals

- Run on non-stack WordPress sites as an independent plugin.
- Detect MRN stack contracts and switch to stack-aware bridge mode when available.
- Support frontend and Classic Editor (TinyMCE/ACF WYSIWYG) typography paths.

## Current Scope

- Settings UI with tabs:
  - Font Builder
  - Font Settings
  - Stack Status
  - Import|Export
- Frontend runtime now builds and enqueues a constrained Google Fonts CSS2 request.
- Frontend load scope control for performance targeting:
  - all frontend requests
  - front page only
  - singular only
  - archive/search/posts index only
  - posts index only
- Classic Editor runtime now appends the same Google Fonts request through `mce_css`.
- Resource hints for Google Fonts origins on frontend:
  - `preconnect`
  - `dns-prefetch`
- Frontend and editor CSS use CSS-variable-based font stacks.
- Extension hook for stack/custom runtime font-face injection:
  - `mrn_google_fonts_font_face_css`
- Site Styles incorporation (when Site Styles extension hooks are available):
  - adds a `Google Fonts` tab inside `Settings -> Site Styles`
  - provides local build controls and stack diagnostics
  - saves to the same `mrn_google_fonts_settings` option as the standalone settings page
  - hides `Settings -> Google Fonts` to avoid duplicate admin surfaces on stack sites
- Local Font Builder:
  - on stack sites: `Settings -> Site Styles -> Google Fonts -> Font Builder`
  - on non-stack/standalone sites: `Settings -> Google Fonts -> Font Builder`
  - `Build Local Fonts` downloads selected `.woff2` files from Google CSS2 into uploads
  - saves a local manifest in `mrn_google_fonts_local_manifest`
  - frontend/editor automatically prefer the matching local CSS build and skip Google CDN
  - build now persists posted builder values first (so separate Save is not required)
  - build auto-enables frontend runtime when disabled and reports that in the success notice
  - `Clear Local Build` removes cached local files and falls back to remote runtime
- Settings transfer:
  - Google Fonts appears as a selectable section in the existing Site Styles Import/Export box
  - local built files are not exported and should be rebuilt after import

## Frontend Verification (Playwright)

Verified against local stack site `http://mrn-plugin-stack.local` on April 17, 2026.

- Frontend includes:
  - `mrn-google-fonts-local-css` (self-hosted font CSS from uploads)
  - `mrn-google-fonts-frontend-css` (variable scaffold)
  - inline root variables:
    - `--mrn-font-body`
    - `--mrn-font-heading`
- Local built CSS contains `@font-face` entries for configured families and weights.
- `document.fonts.load`/`document.fonts.check` confirms configured weights are loadable.
- Computed frontend family values match configured body/heading families.

### Weight Behavior Note

Weight availability and weight usage are not the same thing:

- This plugin guarantees weight files are built and loadable for configured weights.
- Actual rendered weight on a given element depends on the active theme CSS selectors.
- Example observed during verification: heading `600` and `700` were both loadable, but heading elements rendered at `700` where theme CSS set that weight.

## Child Theme Override Contract

Front-end typography is intentionally overrideable via CSS custom properties.

Override in a child theme stylesheet loaded after Google Fonts runtime:

```css
:root {
  --mrn-font-body: "Your Body Family", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  --mrn-font-heading: "Your Heading Family", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
```

If enqueueing a separate child-theme CSS file, ensure it is loaded after runtime styles so the cascade wins.

## Frontend QA Checklist

Use this quick pass before release on stack-owned pages:

1. In `Settings -> Site Styles -> Google Fonts -> Font Builder`, choose body + heading families and keep family count at 2 or fewer.
2. Set only required weights for each family and click `Build Local Fonts`.
3. Confirm build notice reports files/families and no errors.
4. On a frontend page, verify in devtools that:
   - `mrn-google-fonts-local-css` is loaded (preferred) or `mrn-google-fonts-remote-css` fallback.
   - `mrn-google-fonts-frontend-css` is loaded.
   - `--mrn-font-body` and `--mrn-font-heading` are present on `:root`.
5. Check computed typography on representative body and heading elements.
6. Confirm configured weights are loadable (network or `document.fonts` checks) and that rendered weights align with theme selectors.
7. If using child-theme overrides, confirm override CSS loads after runtime and that computed font stacks reflect the override vars.
8. Run a quick performance sanity check (no duplicate remote/local font payloads, no unexpected render-blocking additions).

## Not Implemented Yet

- In-admin visual preview (the chooser supports typeahead family lookup, but not specimen previews yet).
- Unicode-range subsetting controls for local builds.
- Template-specific preload optimization (deferred to avoid accidental duplicate cross-origin downloads).
