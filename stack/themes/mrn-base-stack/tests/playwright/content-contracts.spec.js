const { test, expect } = require('@playwright/test');

const themeCssPath = '/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/style.css';

const viewports = [
	{ label: 'wide', width: 1440, height: 2200, expectTeamFirstRow: 5, expectSplitOrientation: 'horizontal' },
	{ label: 'tablet', width: 960, height: 2600, expectTeamFirstRow: 3, expectSplitOrientation: 'vertical' },
	{ label: 'mobile', width: 390, height: 3400, expectTeamFirstRow: 1, expectSplitOrientation: 'vertical' },
];

function buildPortraitImage(label, fillColor) {
	const svg = `
		<svg xmlns="http://www.w3.org/2000/svg" width="400" height="500" viewBox="0 0 400 500">
			<defs>
				<linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
					<stop offset="0%" stop-color="${fillColor}" />
					<stop offset="100%" stop-color="#dbe4ee" />
				</linearGradient>
			</defs>
			<rect width="400" height="500" fill="url(#bg)" rx="40" ry="40" />
			<text x="50%" y="50%" fill="#22303d" font-family="Arial, sans-serif" font-size="34" font-weight="700" text-anchor="middle" dominant-baseline="middle">${label}</text>
		</svg>
	`;

	return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
}

function buildFixtureMarkup() {
	const splitImage = buildPortraitImage('Split', '#bfd7ea');
	const teamImages = Array.from({ length: 5 }, (_, index) => buildPortraitImage(`T${index + 1}`, index % 2 === 0 ? '#b7d4c8' : '#d8c7ef'));
	const teamMemberItems = teamImages
		.map(
			(imageSrc, index) => `
				<li
					class="mrn-content-list-row__item mrn-content-list-row__item--team-member mrn-content-list-row__item--display-grid mrn-content-list-row__item--display-style-grid mrn-content-list-row__item--has-image mrn-content-list-row__item--card-layout-vertical mrn-ui__item"
					data-display-style="grid"
					data-card-layout="vertical"
				>
					<article class="mrn-content-list-row__card">
						<div class="mrn-content-list-row__media mrn-ui__media">
							<img alt="Team member ${index + 1}" src="${imageSrc}" />
						</div>
						<div class="mrn-content-list-row__body mrn-ui__body">
							<div class="mrn-content-list-row__head mrn-ui__head">
								<h3 class="mrn-content-list-row__title mrn-ui__heading">Team Member ${index + 1}</h3>
								<p class="mrn-content-list-row__meta mrn-content-list-row__position">Role ${index + 1}</p>
							</div>
							<p class="mrn-content-list-row__excerpt mrn-ui__text">Portrait card copy ${index + 1}.</p>
						</div>
					</article>
				</li>
			`
		)
		.join('');

	return `
		<!doctype html>
		<html lang="en">
		<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<style>
				:root { color-scheme: light; }
				body.fixture-root {
					margin: 0;
					background: #f5f7fb;
					color: #202833;
					font-family: Arial, sans-serif;
				}
				.fixture-shell {
					box-sizing: border-box;
					display: grid;
					gap: 4rem;
					margin: 0 auto;
					max-width: 1440px;
					padding: 2rem 1rem 4rem;
					width: min(100%, 1440px);
				}
				.fixture-block {
					display: grid;
					gap: 1.5rem;
				}
				.fixture-block__heading {
					font-size: 0.875rem;
					font-weight: 700;
					letter-spacing: 0.08em;
					margin: 0;
					text-transform: uppercase;
				}
				.fixture-split-card .mrn-content-list-row__card {
					align-items: start;
				}
				.fixture-split-card .mrn-content-list-row__body {
					gap: 0.75rem;
				}
				.fixture-team-grid .mrn-content-list-row__body {
					gap: 0.5rem;
				}
				.fixture-card-deck .mrn-card-row__grid {
					grid-template-columns: minmax(0, 1fr);
				}
				.fixture-card-deck .mrn-card-row__item {
					background: #ffffff;
					border: 1px solid rgba(32, 40, 51, 0.12);
					border-radius: 24px;
					box-shadow: 0 18px 45px rgba(25, 34, 49, 0.06);
					padding: 1.5rem;
				}
				.fixture-card-deck .mrn-card-row__content .mrn-ui__text {
					color: inherit;
				}
				.fixture-stats {
					color: #202833;
				}
			</style>
		</head>
		<body class="fixture-root">
			<main class="fixture-shell">
				<section class="fixture-block fixture-split-card">
					<p class="fixture-block__heading">Explicit media split card</p>
					<div class="mrn-content-builder__row mrn-content-builder__row--content-lists">
						<ul class="mrn-content-list-row__items mrn-content-list-row__items--unordered mrn-ui__items">
							<li
								class="mrn-content-list-row__item mrn-content-list-row__item--card-layout-media-split mrn-content-list-row__item--has-image mrn-ui__item"
								data-card-layout="media-split"
							>
								<article class="mrn-content-list-row__card">
									<div class="mrn-content-list-row__media mrn-ui__media">
										<img alt="Split card portrait" src="${splitImage}" />
									</div>
									<div class="mrn-content-list-row__body mrn-ui__body">
										<div class="mrn-content-list-row__head mrn-ui__head">
											<h3 class="mrn-content-list-row__title mrn-ui__heading">Split card</h3>
										</div>
										<p class="mrn-content-list-row__excerpt mrn-ui__text">This card keeps the legacy split-media treatment when explicitly requested.</p>
									</div>
								</article>
							</li>
						</ul>
					</div>
				</section>

				<section class="fixture-block fixture-team-grid">
					<p class="fixture-block__heading">Team member grid</p>
					<div class="mrn-content-builder__row mrn-content-builder__row--content-lists mrn-content-builder__row--content-lists-display-grid mrn-content-builder__row--content-lists-style-grid">
						<ul class="mrn-content-list-row__items mrn-content-list-row__items--unordered mrn-ui__items">
							${teamMemberItems}
						</ul>
					</div>
				</section>

				<section class="fixture-block fixture-card-deck">
					<p class="fixture-block__heading">Card deck pills</p>
					<div class="mrn-content-builder__row mrn-content-builder__row--card">
						<div class="mrn-card-row__grid mrn-card-row__grid--card-deck mrn-ui__items">
							<article class="mrn-card-row__item mrn-card-row__item--card-deck mrn-ui__item">
								<div class="mrn-card-row__content">
									<div class="mrn-ui__text">
										<ul>
											<li>Strategy</li>
											<li>Delivery</li>
											<li>Operations</li>
											<li>Support</li>
										</ul>
									</div>
								</div>
							</article>
						</div>
					</div>
				</section>

				<section class="fixture-block fixture-stats">
					<p class="fixture-block__heading">Stats</p>
					<div class="mrn-content-builder__row mrn-content-builder__row--stats mrn-content-builder__row--stats-columns-3">
						<div class="mrn-stats-row__grid mrn-stats-row__grid--metrics-shell mrn-ui__items">
							<div class="mrn-stats-row__item mrn-stats-row__item--metrics-shell mrn-ui__item">
								<div class="mrn-stats-row__metric">
									<div class="mrn-stats-row__value mrn-ui__heading">128%</div>
								</div>
								<p class="mrn-ui__label">Growth</p>
							</div>
							<div class="mrn-stats-row__item mrn-stats-row__item--metrics-shell mrn-ui__item">
								<div class="mrn-stats-row__metric">
									<div class="mrn-stats-row__value mrn-ui__heading">24</div>
								</div>
								<p class="mrn-ui__label">Months</p>
							</div>
							<div class="mrn-stats-row__item mrn-stats-row__item--metrics-shell mrn-ui__item">
								<div class="mrn-stats-row__metric">
									<div class="mrn-stats-row__value mrn-ui__heading">99</div>
								</div>
								<p class="mrn-ui__label">Score</p>
							</div>
						</div>
					</div>
				</section>
			</main>
		</body>
		</html>
	`;
}

async function loadFixture(page) {
	await page.setContent(buildFixtureMarkup(), { waitUntil: 'domcontentloaded' });
	await page.addStyleTag({ path: themeCssPath });
	await page.addStyleTag({
		content: `
			.mrn-content-list-row__item--display-style-grid .mrn-content-list-row__media img {
				aspect-ratio: 236.8 / 262;
				object-fit: contain;
			}

			.mrn-card-row__content ul li {
				border-radius: 18px;
				padding: 0.75rem 1rem;
			}

			.mrn-stats-row__value {
				color: rgb(15, 92, 181);
				font-family: Georgia, "Times New Roman", serif;
				font-size: 2.75rem;
				line-height: 1;
			}
		`,
	});
	await page.waitForFunction(() => Array.from(document.images).every((image) => image.complete));
}

async function collectFixtureState(page) {
	return page.evaluate(() => {
		const rect = (element) => {
			const box = element.getBoundingClientRect();
			return {
				x: box.x,
				y: box.y,
				width: box.width,
				height: box.height,
				right: box.right,
				bottom: box.bottom,
			};
		};

		const splitItem = document.querySelector('.fixture-split-card [data-card-layout="media-split"]');
		const splitCard = splitItem ? splitItem.querySelector('.mrn-content-list-row__card') : null;
		const splitMedia = splitItem ? splitItem.querySelector('.mrn-content-list-row__media') : null;
		const splitBody = splitItem ? splitItem.querySelector('.mrn-content-list-row__body') : null;
		const teamItems = Array.from(document.querySelectorAll('.fixture-team-grid [data-card-layout="vertical"]')).map((item) => ({
			layout: item.getAttribute('data-card-layout'),
			item: rect(item),
			media: rect(item.querySelector('.mrn-content-list-row__media')),
			body: rect(item.querySelector('.mrn-content-list-row__body')),
			image: rect(item.querySelector('.mrn-content-list-row__media img')),
		}));
		const cardPill = document.querySelector('.fixture-card-deck .mrn-card-row__content ul li');
		const cardList = document.querySelector('.fixture-card-deck .mrn-card-row__content ul');
		const statsValue = document.querySelector('.fixture-stats .mrn-stats-row__value');
		const statsLabel = document.querySelector('.fixture-stats .mrn-ui__label');
		const viewportWidth = document.documentElement.clientWidth;

		return {
			overflowX: document.documentElement.scrollWidth > viewportWidth,
			split: {
				layout: splitItem ? splitItem.getAttribute('data-card-layout') : '',
				card: splitCard ? rect(splitCard) : null,
				media: splitMedia ? rect(splitMedia) : null,
				body: splitBody ? rect(splitBody) : null,
			},
			team: {
				firstRowCount: teamItems.length > 0
					? teamItems.filter((item) => Math.abs(item.item.y - teamItems[0].item.y) < 2).length
					: 0,
				items: teamItems,
			},
			pills: {
				ulDisplay: cardList ? window.getComputedStyle(cardList).display : '',
				ulFlexWrap: cardList ? window.getComputedStyle(cardList).flexWrap : '',
				ulListStyle: cardList ? window.getComputedStyle(cardList).listStyleType : '',
				liBorderRadius: cardPill ? window.getComputedStyle(cardPill).borderRadius : '',
				liPaddingLeft: cardPill ? window.getComputedStyle(cardPill).paddingLeft : '',
				liPaddingRight: cardPill ? window.getComputedStyle(cardPill).paddingRight : '',
			},
			stats: {
				valueColor: statsValue ? window.getComputedStyle(statsValue).color : '',
				valueFontFamily: statsValue ? window.getComputedStyle(statsValue).fontFamily : '',
				valueFontSize: statsValue ? window.getComputedStyle(statsValue).fontSize : '',
				labelTextTransform: statsLabel ? window.getComputedStyle(statsLabel).textTransform : '',
			},
		};
	});
}

function expectSplitOrientation(splitState, expectedOrientation) {
	expect(splitState.card, 'split card box').not.toBeNull();
	expect(splitState.media, 'split media box').not.toBeNull();
	expect(splitState.body, 'split body box').not.toBeNull();

	if (!splitState.card || !splitState.media || !splitState.body) {
		return;
	}

	if ('horizontal' === expectedOrientation) {
		expect(splitState.media.x, 'split media x').toBeLessThan(splitState.body.x);
		expect(Math.abs(splitState.media.y - splitState.body.y), 'split media/body y alignment').toBeLessThan(2);
	} else {
		expect(splitState.media.y, 'split media y').toBeLessThan(splitState.body.y);
	}
}

for (const viewport of viewports) {
	test(`content contracts stay intact at ${viewport.label}`, async ({ page }) => {
		await page.setViewportSize({ width: viewport.width, height: viewport.height });
		await loadFixture(page);

		const state = await collectFixtureState(page);

		expect(state.overflowX, 'viewport overflow').toBe(false);
		expect(state.split.layout, 'split card layout setting').toBe('media-split');
		expectSplitOrientation(state.split, viewport.expectSplitOrientation);

		expect(state.team.firstRowCount, 'team member first-row count').toBe(viewport.expectTeamFirstRow);
		expect(state.team.items.length, 'team member item count').toBe(5);
		expect(state.team.items[0].layout, 'team member card layout setting').toBe('vertical');
		expect(state.team.items[0].media.y, 'team member media position').toBeLessThan(state.team.items[0].body.y);
		expect(state.team.items[0].image.height, 'team member portrait override').toBeGreaterThan(state.team.items[0].image.width);

		expect(state.pills.ulDisplay, 'card pill list display').toBe('flex');
		expect(state.pills.ulFlexWrap, 'card pill list wrap').toBe('wrap');
		expect(state.pills.ulListStyle, 'card pill list style').toBe('none');
		expect(state.pills.liBorderRadius, 'card pill radius override').toBe('18px');
		expect(state.pills.liPaddingLeft, 'card pill padding override').toBe('16px');
		expect(state.pills.liPaddingRight, 'card pill padding override').toBe('16px');

		expect(state.stats.valueColor, 'stats color override').toBe('rgb(15, 92, 181)');
		expect(state.stats.valueFontFamily, 'stats font override').toContain('Georgia');
		expect(state.stats.valueFontSize, 'stats font size override').toBe('44px');
		expect(state.stats.labelTextTransform, 'stats label text transform contract').toBe('uppercase');
	});
}
