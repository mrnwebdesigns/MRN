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
  - supports three configurable families (`Body`, `Heading`, `Accent`)
  - each family can be assigned to multiple selector groups via multiselect targets
  - heading targets include both `All Headings (H1-H6)` and individual `H1`..`H6` options
  - `Build Local Fonts` downloads selected `.woff2` files from Google CSS2 into uploads
  - per-family italic toggles use Google CSS2 `ital,wght` tuples for variable-font-safe requests
  - saves a local manifest in `mrn_google_fonts_local_manifest`
  - frontend/editor automatically prefer the matching local CSS build and skip Google CDN
  - build now persists posted builder values first (so separate Save is not required)
  - build auto-enables frontend runtime when disabled and reports that in the success notice
  - `Clear Local Build` removes cached local files and falls back to remote runtime
- Lean remote request contract:
  - `font_faces` settings can override requested faces per family
  - `mrn_google_fonts_family_faces` can filter requested faces before the Google CSS2 URL is built
  - legacy weight fields and italic toggles keep their previous behavior when no face map is supplied
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
    - `--mrn-font-accent`
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
  --mrn-font-accent: "Your Accent Family", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
```

If enqueueing a separate child-theme CSS file, ensure it is loaded after runtime styles so the cascade wins.

## Lean Face Request Contract

Sites can trim unused remote font faces without changing CSS variable output or style handles. Return a family-keyed face map from `mrn_google_fonts_family_faces`:

```php
add_filter(
	'mrn_google_fonts_family_faces',
	function ( array $families ): array {
		$families['Source Sans 3'] = array(
			'normal' => array( 300, 400, 600, 700 ),
			'italic' => array(),
		);
		$families['Lora'] = array(
			'normal' => array( 600, 700 ),
			'italic' => array( 600, 700 ),
		);

		return $families;
	}
);
```

The same shape is accepted in the optional `font_faces` settings key. When no face map is supplied, the builder keeps the older behavior: configured weights are requested as normal faces, and the italic toggle requests matching italic faces.

## Frontend QA Checklist

Use this quick pass before release on stack-owned pages:

1. In `Settings -> Site Styles -> Google Fonts -> Font Builder`, choose up to three families and assign each to the needed selector groups.
2. Set only required weights/italics for each family and click `Build Local Fonts`.
3. Confirm build notice reports files/families and no errors.
4. On a frontend page, verify in devtools that:
   - `mrn-google-fonts-local-css` is loaded (preferred) or `mrn-google-fonts-remote-css` fallback.
   - `mrn-google-fonts-frontend-css` is loaded.
   - `--mrn-font-body`, `--mrn-font-heading`, and `--mrn-font-accent` are present on `:root`.
5. Check computed typography on representative selectors for each assigned family target group.
6. Confirm configured weights are loadable (network or `document.fonts` checks) and that rendered weights align with theme selectors.
7. If using child-theme overrides, confirm override CSS loads after runtime and that computed font stacks reflect the override vars.
8. Run a quick performance sanity check (no duplicate remote/local font payloads, no unexpected render-blocking additions).

## Editor Regression Check

Run this lightweight check before release when TinyMCE font-format behavior changes:

```bash
php plugins/mrn-google-fonts/tests/tinymce-font-formats-regression.php
php plugins/mrn-google-fonts/tests/google-fonts-request-faces-regression.php
```

These checks assert that injected TinyMCE `font_formats` remain alphabetically sorted and free of duplicate labels, and that the Google Fonts CSS2 URL builder preserves legacy output while supporting lean per-family face maps.

## Not Implemented Yet

- In-admin visual preview (the chooser supports typeahead family lookup, but not specimen previews yet).
- Unicode-range subsetting controls for local builds.
- Template-specific preload optimization (deferred to avoid accidental duplicate cross-origin downloads).
