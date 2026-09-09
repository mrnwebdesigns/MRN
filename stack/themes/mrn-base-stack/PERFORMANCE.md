# Front-end Performance Contract

This theme keeps shared markup and styles stable while loading feature assets
only when the current rendered Stack page can use them. Site and third-party
plugin assets remain outside this contract.

## Asset ownership and loading

The following audit reflects `mrn-base-stack` 1.3.3.

| Surface | Loading contract |
| --- | --- |
| `style.css` | Global parent-theme foundation and component CSS. |
| Hero and row-background layout CSS | Existing post-meta-indexed conditional styles, dependent on `mrn-base-stack-style`. |
| Mobile or standard navigation | One footer script selected from the saved mobile-navigation setting; mobile CSS depends on the parent style. |
| Header search and back-to-top | Footer scripts loaded only when their rendered controls are enabled. |
| Font Awesome and Dashicons | Loaded only when rendered header, footer, breadcrumb, or builder icon choices require them. |
| Motion | Motion plus `front-end-effects.js`, only for a saved or authored motion/surface contract. |
| Slider | Splide CSS/JS plus `front-end-slider.js`, only for a slider or slider-mode logo row with items. |
| Tabs | Splide CSS/JS plus `front-end-tabs.js`, only for a tabbed layout with tabs. |
| Video modal | GLightbox CSS/JS plus `front-end-video-modal.js`, only for a modal video with media and a thumbnail. |
| Gallery | GLightbox CSS/JS plus `front-end-gallery.js`, only for the singular gallery renderer or authored gallery markup. |
| Deferred media | `front-end-deferred-media.js`, only for rendered inline/background video or a testimonial surface that can emit deferred media. |
| FAQ | `front-end-faq.js`, only for FAQ markup or an FAQ/FAQ-block layout. |

Component discovery reads raw saved meta and authored marker contracts before
enqueue time; it does not hydrate full ACF trees. Referenced reusable blocks are
inspected recursively. An unresolved legacy `[mrn_block]` shortcode retains the
complete historical runtime as a compatibility fallback.

Extensions can add or remove a need with
`mrn_base_stack_front_end_component_needs` and can extend handle mappings with
`mrn_base_stack_front_end_component_asset_manifest`. The older
`mrn_base_stack_should_enqueue_front_end_runtime` filter remains compatible:
`false` disables the discovered component bundle, while an explicit `true` in a
request with no discovered components loads the complete legacy bundle.

All Stack-owned public scripts remain in the footer. The front end has no
jQuery dependency. The only Stack-owned head script is the small, conditional
motion-preparation inline script; it is printed only when motion effects are
enqueued and must run before paint. No script was moved or given `defer` because
there was no remaining head script for which that change was both useful and
behaviorally neutral.

## Critical media

Hero background and foreground images remain responsive, sized through their
registered image sizes, discoverable in the initial HTML, and explicitly use
`loading="eager"`, `fetchpriority="high"`, and `decoding="async"`.

Ordinary images rendered through the Stack attachment helper already carry
explicit lazy/async attributes. Content-list and archive-thumbnail renderers
now use the same explicit contract so WordPress cannot omit `loading="lazy"`
from the first few below-the-fold items. Singular featured images retain
WordPress's normal loading heuristic because they may be above the fold.

For one image that is truly critical but is only discoverable through critical
CSS, a child theme can declare an attachment:

```php
add_filter(
	'mrn_base_stack_critical_css_image',
	static function () {
		return array(
			'attachment_id' => 123,
			'size'          => 'full',
		);
	}
);
```

A URL declaration is also supported when its MIME type is explicit:

```php
return array(
	'url'  => get_stylesheet_directory_uri() . '/images/critical-mark.svg',
	'type' => 'image/svg+xml',
);
```

Optional `media`, `imagesrcset`, and `imagesizes` values pass through to
WordPress's preload API. Invalid/non-image declarations are ignored, and an
identical URL is added at most once. The default is empty; the theme never
guesses or preloads every CSS background image.

## Parent stylesheet split evaluation

At the 1.3.3 audit point, `style.css` is 219,880 bytes. Hero (3,277 bytes) and
row-background media (991 bytes) already have conditional companion files, but
the repository has no tracked Sass/Less/component source graph from which the
remaining monolith can be partitioned safely. Its foundation, components,
responsive rules, and child-theme override surfaces are interleaved. Splitting
that compiled file in this release would change source order and cascade risk
without a reliable selector-level regression oracle. The stylesheet therefore
remains byte-for-byte unchanged apart from its version header. A future split
should first establish modular source ownership, preserve exact source order,
and compare computed styles across the full Stack fixture matrix.

## Throttled simple-page evidence

The self-contained Playwright fixture compares the pre-1.3.3 full component
bundle with the 1.3.3 simple-page bundle on Chromium at 390 x 844, 4x CPU,
150 ms latency, 1.6 Mbps download, 750 Kbps upload, and disabled cache. It uses
the same theme assets on both sides and no third-party requests.

| Metric | Legacy bundle | Conditional bundle | Change |
| --- | ---: | ---: | ---: |
| Requests | 18 | 9 | -50.0% |
| Transferred bytes | 528,380 | 245,816 | -53.5% |
| FCP | 2,932 ms | 1,524 ms | -48.0% |
| LCP | 2,932 ms | 1,524 ms | -48.0% |
| CLS | 0 | 0 | No change |
| INP | 16 ms | 16 ms | No change |
| TBT | 0 ms | 0 ms | No change |
| Render-blocking resources | 5 | 3 | -40.0% |

These numbers are deterministic fixture evidence for the Stack-owned delta,
not a claim about a live site's Lighthouse score. Network, server, content,
child-theme CSS, and third-party tags still affect field results.

## Site migration notes

After 1.3.3 is deployed and the affected templates are verified, a child theme
can remove site-level copies of the following workarounds:

- blanket dequeues for Motion, Splide, tabs, GLightbox, gallery, video-modal,
  slider, FAQ, or deferred-media handles;
- filters that force lazy loading on Stack content-list or archive thumbnails;
- duplicate hero eager/fetch-priority attribute filters; and
- direct `<link rel="preload">` output for the single CSS-backed critical image,
  after moving that declaration to `mrn_base_stack_critical_css_image`.

Do not remove unrelated plugin optimization, third-party tag configuration, or
site-specific preload logic until its runtime owner and rendered need are
verified. In particular, GTM, GA4, reCAPTCHA, GTranslate, and WPForms are not
managed by this theme release.
