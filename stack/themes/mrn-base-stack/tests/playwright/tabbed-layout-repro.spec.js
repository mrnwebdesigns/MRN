const { test, expect } = require('@playwright/test');

const vendorCssPath = '/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/css/vendor/splide.min.css';
const themeCssPath = '/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/style.css';
const splideJsPath = '/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/js/vendor/splide.min.js';
const tabsJsPath = '/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/js/front-end-tabs.js';

test('tabbed slide mode mounts slider and applies equal height', async ({ page }) => {
	page.on('pageerror', (error) => {
		console.log('pageerror', String(error));
	});

	page.on('console', (message) => {
		console.log('browser-console', message.type(), message.text());
	});

	await page.setContent(`
		<!doctype html>
		<html lang="en">
		<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<style>
				body { margin: 0; padding: 40px; }
				.mrn-layout-container { max-width: 1000px; margin: 0 auto; }
				.mrn-content-builder__row { margin: 0; }
				.panel-box { padding: 24px; background: #f4f4f4; border: 1px solid #ddd; }
				.panel-box--tall { min-height: 420px; }
				.panel-box--mid { min-height: 260px; }
				.panel-box--short { min-height: 120px; }
			</style>
		</head>
		<body>
			<section class="mrn-content-builder__row mrn-content-builder__row--tabbed-layout">
				<div class="mrn-tabbed-layout mrn-tabbed-layout--orientation-horizontal mrn-tabbed-layout--transition-slide mrn-tabbed-layout--equal-heights" data-mrn-tabbed-layout data-mrn-equal-panel-heights="true">
					<div class="mrn-layout-section mrn-layout-section--tabbed-layout mrn-layout-section--contained">
						<div class="mrn-layout-container mrn-layout-container--wide">
							<div class="mrn-layout-grid mrn-layout-grid--tabbed-layout mrn-ui__body">
								<div class="mrn-tabbed-layout__body">
									<div class="mrn-tabbed-layout__nav-wrap">
										<div class="mrn-tabbed-layout__nav" role="tablist" aria-label="Tabbed content">
											<button id="tab-1" class="mrn-tabbed-layout__tab is-active" type="button" role="tab" aria-selected="true" aria-controls="panel-1" tabindex="0" data-mrn-tab-button>
												<span class="mrn-tabbed-layout__tab-label">Tab One</span>
											</button>
											<button id="tab-2" class="mrn-tabbed-layout__tab" type="button" role="tab" aria-selected="false" aria-controls="panel-2" tabindex="-1" data-mrn-tab-button>
												<span class="mrn-tabbed-layout__tab-label">Tab Two</span>
											</button>
											<button id="tab-3" class="mrn-tabbed-layout__tab" type="button" role="tab" aria-selected="false" aria-controls="panel-3" tabindex="-1" data-mrn-tab-button>
												<span class="mrn-tabbed-layout__tab-label">Tab Three</span>
											</button>
										</div>
									</div>

									<div class="mrn-tabbed-layout__panels mrn-tabbed-layout__panels--slider splide" data-mrn-tab-slider>
										<div class="splide__track mrn-tabbed-layout__panel-track">
											<ul class="splide__list mrn-tabbed-layout__panel-list">
												<li class="mrn-tabbed-layout__panel splide__slide is-active" data-mrn-tab-panel>
													<div id="panel-1" class="mrn-tabbed-layout__panel-body" role="tabpanel" aria-labelledby="tab-1" aria-hidden="false" data-mrn-tab-panel-content>
														<div class="mrn-content-builder__row">
															<div class="panel-box panel-box--short">Short panel</div>
														</div>
													</div>
												</li>
												<li class="mrn-tabbed-layout__panel splide__slide" data-mrn-tab-panel>
													<div id="panel-2" class="mrn-tabbed-layout__panel-body" role="tabpanel" aria-labelledby="tab-2" aria-hidden="true" data-mrn-tab-panel-content>
														<div class="mrn-content-builder__row">
															<div class="panel-box panel-box--tall">Tall panel</div>
														</div>
													</div>
												</li>
												<li class="mrn-tabbed-layout__panel splide__slide" data-mrn-tab-panel>
													<div id="panel-3" class="mrn-tabbed-layout__panel-body" role="tabpanel" aria-labelledby="tab-3" aria-hidden="true" data-mrn-tab-panel-content>
														<div class="mrn-content-builder__row">
															<div class="panel-box panel-box--mid">Mid panel</div>
														</div>
													</div>
												</li>
											</ul>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>
		</body>
		</html>
	`);

	await page.addStyleTag({ path: vendorCssPath });
	await page.addStyleTag({ path: themeCssPath });
	await page.addScriptTag({ path: splideJsPath });
	await page.addScriptTag({ path: tabsJsPath });

	const debugState = await page.evaluate(() => {
		const root = document.querySelector('[data-mrn-tabbed-layout]');
		const sliderRoot = document.querySelector('[data-mrn-tab-slider]');

		return {
			splideType: typeof window.Splide,
			hasRoot: !!root,
			hasSliderRoot: !!sliderRoot,
			buttonCount: document.querySelectorAll('[data-mrn-tab-button]').length,
			panelCount: document.querySelectorAll('[data-mrn-tab-panel]').length,
			rootClass: root ? root.className : '',
			sliderRootClass: sliderRoot ? sliderRoot.className : '',
			sliderMountedAttr: sliderRoot ? sliderRoot.getAttribute('data-mrn-tab-slider-mounted') : '',
			rootHasMountedSlider: !!(root && root.mrnTabSlider),
		};
	});

	console.log('debugState', debugState);

	await page.waitForFunction(() => {
		const root = document.querySelector('[data-mrn-tabbed-layout]');
		return !!(root && root.mrnTabSlider);
	});

	const initialState = await page.evaluate(() => {
		const root = document.querySelector('[data-mrn-tabbed-layout]');
		const slider = root.mrnTabSlider;
		const list = root.querySelector('.splide__list');
		const slides = Array.from(root.querySelectorAll('.splide__slide')).map((slide) => {
			const rect = slide.getBoundingClientRect();
			return {
				className: slide.className,
				style: slide.getAttribute('style') || '',
				hiddenAttr: slide.getAttribute('hidden'),
				hiddenProp: slide.hidden,
				display: window.getComputedStyle(slide).display,
				flexBasis: window.getComputedStyle(slide).flexBasis,
				flexShrink: window.getComputedStyle(slide).flexShrink,
				width: rect.width,
				height: rect.height,
				left: rect.left,
				top: rect.top,
				text: slide.textContent.trim(),
			};
		});

		return {
			index: slider.index,
			cssHeight: root.style.getPropertyValue('--mrn-tabbed-layout-panel-height'),
			transform: list.style.transform,
			trackWidth: root.querySelector('.splide__track').getBoundingClientRect().width,
			listWidth: list.getBoundingClientRect().width,
			listScrollWidth: list.scrollWidth,
			listDisplay: window.getComputedStyle(list).display,
			slides,
		};
	});

	console.log('initialState', initialState);

	await page.getByRole('tab', { name: 'Tab Two' }).click();
	await page.waitForTimeout(700);

	const nextState = await page.evaluate(() => {
		const root = document.querySelector('[data-mrn-tabbed-layout]');
		const slider = root.mrnTabSlider;
		const list = root.querySelector('.splide__list');
		const slides = Array.from(root.querySelectorAll('.splide__slide')).map((slide) => {
			const rect = slide.getBoundingClientRect();
			return {
				className: slide.className,
				style: slide.getAttribute('style') || '',
				hiddenAttr: slide.getAttribute('hidden'),
				hiddenProp: slide.hidden,
				display: window.getComputedStyle(slide).display,
				flexBasis: window.getComputedStyle(slide).flexBasis,
				flexShrink: window.getComputedStyle(slide).flexShrink,
				width: rect.width,
				height: rect.height,
				left: rect.left,
				top: rect.top,
				text: slide.textContent.trim(),
			};
		});

		return {
			index: slider.index,
			cssHeight: root.style.getPropertyValue('--mrn-tabbed-layout-panel-height'),
			transform: list.style.transform,
			activeLabel: document.querySelector('[data-mrn-tab-button].is-active .mrn-tabbed-layout__tab-label')?.textContent || '',
			visibleText: root.querySelector('.splide__slide.is-active')?.textContent.trim() || '',
			trackWidth: root.querySelector('.splide__track').getBoundingClientRect().width,
			listWidth: list.getBoundingClientRect().width,
			listScrollWidth: list.scrollWidth,
			listDisplay: window.getComputedStyle(list).display,
			slides,
		};
	});

	console.log('nextState', nextState);

	expect(initialState.index).toBe(0);
	expect(initialState.cssHeight).toBe('420px');
	expect(initialState.listScrollWidth).toBeGreaterThan(initialState.trackWidth);
	expect(initialState.slides.every((slide) => false === slide.hiddenProp)).toBeTruthy();
	expect(nextState.index).toBe(1);
	expect(nextState.activeLabel).toBe('Tab Two');
	expect(nextState.cssHeight).toBe('420px');
	expect(nextState.transform).not.toBe('translateX(0px)');
	expect(nextState.visibleText).toContain('Tall panel');
	expect(nextState.slides.every((slide) => false === slide.hiddenProp)).toBeTruthy();
});
