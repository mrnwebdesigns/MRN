/* Linked project filters: ?<taxonomy>=slug,slug preselects dropdown terms, announces the count and brings the list in under a sticky header. */
const fs = require( 'node:fs' ), path = require( 'node:path' ), { execFileSync } = require( 'node:child_process' ), assert = require( 'node:assert/strict' );
const { chromium } = require( process.env.MRN_PLAYWRIGHT_MODULE || 'playwright' ), axe = require( process.env.MRN_AXE_MODULE || 'axe-core' );
const root = path.resolve( __dirname, '../..' );
const base = fs.readFileSync( root + '/style.css', 'utf8' ), css = fs.readFileSync( root + '/css/presentation.css', 'utf8' ), controller = fs.readFileSync( root + '/js/presentation.js', 'utf8' );
const render = ( ...args ) => execFileSync( process.env.MRN_PHP_BINARY || 'php', [ root + '/tests/php/content-list-project-carousel-filters.php', '--render', ...args ], { encoding: 'utf8' } );
const fixtures = { taxonomy: render(), custom: render( '--param=sector' ), reserved: render( '--param=page_id' ) };
assert( fixtures.taxonomy.includes( 'data-project-param="industry"' ) && fixtures.custom.includes( 'data-project-param="sector"' ) && ! fixtures.reserved.includes( 'page_id' ), 'The PHP fixtures render the real filter controls' );
const HEADER = 80;
// The page asks for smooth document scrolling, so an instant jump proves the script does not animate.
const shell = ( before = '', fixture = fixtures.taxonomy ) => `<!doctype html><html lang="en"><head><title>Our work</title><style>${ base }\n${ css }</style><style>html{scroll-behavior:smooth}body{margin:0;font:16px/1.5 Arial,sans-serif;color:#172033;background:#fff}.site-header{position:sticky;top:0;height:${ HEADER }px;background:#fff;z-index:5}.spacer{height:1600px}.after{height:2400px}</style></head><body><header class="site-header" id="masthead"><a href="/">Home</a></header><main><h1>Our work</h1><div class="spacer"><img src="/slow.png" alt="" width="10" height="10"></div>${ fixture }<div class="after"></div></main>${ before }<script>${ controller }</script></body></html>`;

( async () => {
	const browser = await chromium.launch( { headless: true } );
	const checks = [], expect = ( ok, label ) => { assert( ok, label ); checks.push( label ); };
	try {
		const context = await browser.newContext( { viewport: { width: 1280, height: 800 } } );
		let html = shell(), slow = 0;
		await context.route( '**/*', async ( route ) => {
			const url = new URL( route.request().url() );
			if ( 'example.test' !== url.hostname ) { return route.abort(); }
			if ( '/slow.png' === url.pathname ) { await new Promise( ( resolve ) => setTimeout( resolve, slow ) ); return route.fulfill( { status: 404, body: '' } ); }
			if ( url.pathname.startsWith( '/our-work' ) ) { return route.fulfill( { status: 200, contentType: 'text/html', body: html } ); }
			return route.abort();
		} );
		const page = await context.newPage();
		const open = async ( query, options = {} ) => {
			html = shell( options.before || '', fixtures[ options.fixture || 'taxonomy' ] ); slow = options.slow || 0;
			await page.goto( 'https://example.test/our-work/' + query, { waitUntil: options.waitUntil || 'load' } );
			await page.waitForSelector( '[data-mrn-projects-mounted]', { state: 'attached' } );
			if ( ! options.waitUntil ) { await page.evaluate( () => new Promise( ( resolve ) => requestAnimationFrame( () => requestAnimationFrame( resolve ) ) ) ); }
		};
		const state = () => page.evaluate( () => {
			const section = document.getElementById( 'our-work' );
			const visible = [ ...section.querySelectorAll( '[data-project-card]' ) ].filter( ( card ) => ! card.hidden );
			return {
				checked: [ ...section.querySelectorAll( '[data-project-group="one"] input:checked' ) ].map( ( box ) => box.value ),
				visible: visible.length,
				industries: [ ...new Set( visible.map( ( card ) => card.getAttribute( 'data-project-one' ) ) ) ].sort(),
				status: section.querySelector( '[data-project-status]' ).textContent,
				badge: section.querySelector( '[data-project-group="one"] [data-project-badge]' ),
				badgeText: section.querySelector( '[data-project-group="one"] [data-project-badge]' ).textContent,
				badgeHidden: section.querySelector( '[data-project-group="one"] [data-project-badge]' ).hidden,
				reset: section.querySelector( '[data-project-reset]' ).disabled,
				resetCount: section.querySelector( '[data-project-reset-count]' ).textContent,
				scrollY: Math.round( window.scrollY ),
				top: Math.round( section.getBoundingClientRect().top ),
			};
		} );

		// No parameter: nothing changes.
		await open( '' );
		let s = await state();
		expect( 0 === s.checked.length && 9 === s.visible && '' === s.status && 0 === s.scrollY && s.reset, 'Without a parameter the first batch shows, nothing is checked, announced or scrolled' );

		// One known slug plus an unknown one.
		await open( '?industry=non-profit,not-a-term' );
		s = await state();
		expect( 'non-profit' === s.checked.join() && 5 === s.visible && 'non-profit' === s.industries.join(), '?industry=non-profit checks that term only and renders the filtered list; the unknown slug is ignored' );
		expect( 'Showing 5 of 5 projects' === s.status, 'The status region announces the filtered count the same way a manual filter does' );
		expect( ! s.badgeHidden && '1' === s.badgeText && ! s.reset && '(1)' === s.resetCount, 'Badge and reset reflect the preselected term' );
		expect( HEADER === s.top, 'The list is scrolled to sit directly under the sticky header (top ' + s.top + ')' );

		// Several slugs, repeated parameters and whitespace.
		await open( '?industry=non-profit,%20healthcare&industry=retail' );
		s = await state();
		expect( 'healthcare,non-profit,retail' === s.checked.join() && 9 === s.visible && 'Showing 9 of 12 projects' === s.status, 'Comma-separated and repeated values check every listed term and keep Load More batching' );

		// Unknown or empty-in-this-list terms behave like no parameter.
		for ( const query of [ '?industry=bogus', '?industry=', '?industry=elsewhere', '?feature-x=non-profit', '?service=non-profit' ] ) {
			await open( query );
			s = await state();
			expect( 0 === s.checked.length && 9 === s.visible && '' === s.status && 0 === s.scrollY, query + ' changes nothing' );
		}

		// An editor-named link parameter replaces the taxonomy name.
		await open( '?sector=retail', { fixture: 'custom' } );
		s = await state();
		expect( 'retail' === s.checked.join() && 'Showing 4 of 4 projects' === s.status && HEADER === s.top, 'A custom link parameter (?sector=retail) preselects, announces and scrolls' );
		await open( '?industry=retail', { fixture: 'custom' } );
		s = await state();
		expect( 0 === s.checked.length && '' === s.status && 0 === s.scrollY, 'With a custom name the taxonomy name no longer preselects' );
		await open( '?page_id=retail&industry=retail', { fixture: 'reserved' } );
		s = await state();
		expect( 0 === s.checked.length && '' === s.status && 0 === s.scrollY, 'A refused reserved name leaves that dropdown without a link parameter' );

		// Second dropdown uses its own taxonomy name.
		await open( '?feature=blog' );
		s = await state();
		expect( 12 > s.visible && 'Showing 9 of 12 projects' === s.status && await page.locator( '[data-project-group="two"] input[value="blog"]' ).isChecked(), '?feature=blog preselects the second dropdown' );

		// Reduced motion keeps the jump instant even though the page asks for smooth scrolling.
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await open( '?industry=retail' );
		s = await state();
		expect( HEADER === s.top && 'Showing 4 of 4 projects' === s.status, 'Reduced motion: the list is in place immediately, with no smooth scroll' );
		await page.emulateMedia( { reducedMotion: 'no-preference' } );

		// A visitor who scrolled before the script ran keeps their position.
		await open( '?industry=non-profit', { before: '<script>scrollTo({top:300,behavior:"instant"})</script>' } );
		s = await state();
		expect( 'non-profit' === s.checked.join() && 300 === s.scrollY, 'An already scrolled visitor is filtered but not moved' );

		// A visitor who moves while media is still loading is not pulled back on load.
		await open( '?industry=non-profit', { slow: 1200, waitUntil: 'domcontentloaded' } );
		await page.waitForFunction( () => window.scrollY > 0 );
		await page.keyboard.press( 'Home' );
		await page.evaluate( () => window.scrollTo( { top: 0, behavior: 'instant' } ) );
		await page.waitForLoadState( 'load' );
		await page.waitForTimeout( 100 );
		expect( 0 === ( await state() ).scrollY, 'Visitor input before load cancels the load-time re-measure' );

		// A return from a card restores the saved filter instead of the link.
		await page.evaluate( () => sessionStorage.setItem( 'mrn-project-filters:/our-work/#our-work', JSON.stringify( { tab: '*', loaded: 9, scroll: 40, groups: [ [ 'one', [ 'retail' ] ] ] } ) ) );
		await open( '?industry=non-profit' );
		s = await state();
		expect( 'retail' === s.checked.join() && '' === s.status && 40 === s.scrollY, 'Saved return-to-list state wins over the linked filter' );

		// Keyboard and assistive technology: controls match the state and a manual change announces as usual.
		await open( '?industry=non-profit' );
		const trigger = page.locator( '[data-project-group="one"] .mrn-project-filters__trigger' );
		await trigger.focus(); await page.keyboard.press( 'Enter' );
		expect( 'true' === await trigger.getAttribute( 'aria-expanded' ), 'The dropdown opens from the keyboard' );
		const box = page.locator( '[data-project-group="one"] input[value="non-profit"]' );
		expect( await box.isChecked(), 'The preselected term is a checked, focusable checkbox' );
		// Scan the settled state: the card entrance animation fades text in.
		await page.evaluate( () => Promise.all( document.getAnimations().map( ( animation ) => animation.finished ) ) );
		await page.addScriptTag( { content: axe.source } );
		const result = await page.evaluate( () => axe.run( document.getElementById( 'our-work' ), { runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ] } } ) );
		expect( 0 === result.violations.length, 'Linked filter state passes WCAG A/AA' + ( result.violations.length ? ': ' : '' ) + JSON.stringify( result.violations.map( ( v ) => [ v.id, v.nodes.map( ( n ) => [ n.target.join(), n.any.map( ( a ) => a.message ).join() ] ) ] ) ).replace( /^\[\]$/, '' ) );
		await box.focus(); await page.keyboard.press( 'Space' );
		s = await state();
		expect( 0 === s.checked.length && 9 === s.visible && 'Showing 9 of 12 projects' === s.status && s.reset, 'Unchecking from the keyboard restores the full list and announces it' );
		await page.keyboard.press( 'Escape' );
		expect( 'false' === await trigger.getAttribute( 'aria-expanded' ) && await trigger.evaluate( ( e ) => e === document.activeElement ), 'Escape closes the dropdown and returns focus to its trigger' );

		// A fragment keeps the browser's own anchor behaviour; filters still apply.
		await open( '?industry=healthcare#masthead' );
		s = await state();
		expect( 'healthcare' === s.checked.join() && 'Showing 3 of 3 projects' === s.status, 'With a #fragment the filter still applies and the browser keeps anchor scrolling' );

		console.log( JSON.stringify( { result: 'passed', assertions: checks.length, checks, scope: 'Real PHP filter markup, Stack CSS and presentation.js in Chromium on a routed example.test page; a registered WordPress runtime remains separate.' }, null, 2 ) );
	} finally { await browser.close(); }
} )().catch( ( error ) => { console.error( error ); process.exitCode = 1; } );
