( function() {
	'use strict';
	const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const wide = window.matchMedia( '(min-width: 1000px)' );
	const tasks = new Map();
	const active = new Set();
	let pending = false;
	let sequence = 0;
	const clamp = ( value ) => Math.max( 0, Math.min( 1, value ) );
	let headerOffset = 32;
	const topOffset = () => headerOffset;
	const measureHeader = () => {
		const header = document.querySelector( '#masthead, #site-header, .site-header' );
		headerOffset = Math.min( window.innerHeight / 3, header ? header.getBoundingClientRect().height + 16 : 32 );
		document.querySelectorAll( '.mrn-record-list--comparisons' ).forEach( ( list ) => {
			list.style.setProperty( '--mrn-presentation-top', headerOffset + 'px' );
		} );
	};
	const observer = 'IntersectionObserver' in window ? new IntersectionObserver( ( entries ) => {
		entries.forEach( ( entry ) => {
			if ( entry.isIntersecting ) {
				active.add( entry.target );
			} else {
				active.delete( entry.target );
			}
		} );
		schedule();
	}, { rootMargin: '150px' } ) : null;
	function schedule() {
		if ( pending ) { return; }
		pending = true;
		window.requestAnimationFrame( () => {
			pending = false;
			active.forEach( ( element ) => tasks.get( element )?.() );
		} );
	}
	function watch( element, update ) {
		tasks.set( element, update );
		active.add( element );
		observer?.observe( element );
		update();
	}
	function progress( element ) {
		const rect = element.getBoundingClientRect();
		return clamp( ( window.innerHeight - rect.top ) / ( window.innerHeight + rect.height ) );
	}
	function initRotation( group ) {
		const slots = Array.from( group.querySelectorAll( '[data-mrn-phrases]' ) ).map( ( element ) => {
			let phrases;
			try { phrases = JSON.parse( element.dataset.mrnPhrases ); } catch ( error ) { return null; }
			return Array.isArray( phrases ) && phrases.length ? { element, phrases, index: 0 } : null;
		} ).filter( Boolean );
		if ( ! slots.some( ( slot ) => slot.phrases.length > 1 ) ) { return; }
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'mrn-rotation-toggle';
		button.textContent = group.dataset.pauseLabel;
		button.setAttribute( 'aria-pressed', 'false' );
		group.parentElement.insertAdjacentElement( 'afterend', button );
		let timer = null;
		let paused = false;
		let visible = ! ( 'IntersectionObserver' in window );
		const stop = () => { window.clearInterval( timer ); timer = null; };
		const refresh = () => {
			stop();
			button.hidden = reduced.matches;
			if ( reduced.matches ) {
				slots.forEach( ( slot ) => {
					slot.index = 0;
					slot.element.querySelector( '.mrn-rotate__visual' ).textContent = slot.phrases[0];
				} );
			}
			if ( reduced.matches || paused || ! visible || document.hidden ) { return; }
			timer = window.setInterval( () => {
				slots.forEach( ( slot ) => {
					slot.index = ( slot.index + 1 ) % slot.phrases.length;
					slot.element.querySelector( '.mrn-rotate__visual' ).textContent = slot.phrases[ slot.index ];
				} );
			}, Math.max( 1500, Math.min( 30000, Number( group.dataset.interval ) || 4000 ) ) );
		};
		button.addEventListener( 'click', () => {
			paused = ! paused;
			button.textContent = paused ? group.dataset.resumeLabel : group.dataset.pauseLabel;
			button.setAttribute( 'aria-pressed', String( paused ) );
			refresh();
		} );
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( ( entries ) => { visible = entries[0].isIntersecting; refresh(); } ).observe( group );
		}
		document.addEventListener( 'visibilitychange', refresh );
		reduced.addEventListener( 'change', refresh );
		refresh();
	}
	function initImage( stage ) {
		const image = stage.querySelector( ':scope > img' );
		const columns = Array.from( stage.querySelectorAll( '.mrn-column-gallery__column' ) );
		const centerRail = stage.querySelector( '[data-mrn-gallery-mobile="rail"][data-mrn-gallery-start="center"]' );
		if ( centerRail ) {
			let centered = false;
			const centerOnce = () => {
				if ( centered || ! window.matchMedia( '(max-width: 767px)' ).matches ) { return; }
				centerRail.scrollLeft = Math.max( 0, ( centerRail.scrollWidth - centerRail.clientWidth ) / 2 );
				centered = true;
			};
			window.requestAnimationFrame( centerOnce );
			window.addEventListener( 'resize', centerOnce, { passive: true } );
		}
		const framed = stage.dataset.mrnImageStage === 'framed-zoom';
		const inset = Number( stage.dataset.mrnImageZoomInset );
		if ( framed && ( ! Number.isInteger( inset ) || inset < 0 || inset > 240 ) ) { return; }
		watch( stage, () => {
			const p = progress( stage );
			if ( image ) {
				let scale = 1 + .08 * Math.sin( p * Math.PI );
				if ( framed ) {
					const peak = image.offsetWidth > 0 ? Math.max( 1, Math.max( 0, window.innerWidth - inset ) / image.offsetWidth ) : 1;
					const t = p <= .5 ? 2 * p : 2 * ( 1 - p );
					scale = 1 + ( peak - 1 ) * ( 1 - Math.pow( 1 - t, 3 ) );
				}
				image.style.transform = reduced.matches ? '' : 'scale(' + scale + ')';
			}
			columns.forEach( ( column, index ) => {
				column.style.transform = reduced.matches ? '' : 'translateY(' + ( ( p - .5 ) * ( index % 2 ? -120 : 120 ) ) + 'px)';
			} );
		} );
	}
	function initImageDrift( stage ) {
		const image = stage.querySelector( ':scope > img, :scope > .mrn-image-action > img' );
		if ( ! image || ! ( 'translate' in image.style ) || image.style.translate ) { return; }
		let settings;
		try { settings = JSON.parse( stage.dataset.mrnImageDrift ); } catch ( error ) { return; }
		if ( ! settings || ! Number.isFinite( settings.speed ) || settings.speed < .01 || settings.speed > .15 || ! Number.isInteger( settings.maximum ) || settings.maximum < 1 || settings.maximum > 100 ) { return; }
		const section = stage.closest( '.mrn-content-builder__row--two-column-split' ) || stage.closest( '.mrn-content-builder__row, .mrn-hero' ) || stage;
		const pointer = window.matchMedia( '(hover: hover) and (pointer: fine)' );
		const desktop = window.matchMedia( '(min-width: 768px)' );
		const update = () => {
			if ( reduced.matches || ! pointer.matches || ! desktop.matches ) { image.style.translate = ''; return; }
			const rect = section.getBoundingClientRect();
			if ( rect.bottom < -200 || rect.top > window.innerHeight + 200 ) { return; }
			const distance = window.innerHeight / 2 - ( rect.top + rect.height / 2 );
			const travel = Math.max( -settings.maximum, Math.min( settings.maximum, distance * settings.speed ) );
			image.style.translate = '0 ' + travel.toFixed( 2 ) + 'px';
		};
		pointer.addEventListener( 'change', update );
		desktop.addEventListener( 'change', update );
		watch( stage, update );
		if ( 'ResizeObserver' in window ) { new ResizeObserver( update ).observe( section ); }
	}
	function initScrollWords( stage ) {
		if ( stage.dataset.mrnScrollWordsReady ) { return; }
		let settings;
		try { settings = JSON.parse( stage.dataset.mrnScrollWords ); } catch ( error ) { return; }
		if ( ! settings || ! [ 'row', 'split' ].includes( settings.scope ) ) { return; }
		const readColor = ( value ) => {
			if ( typeof value !== 'string' || ! /^var\(--site-color-[a-zA-Z0-9_-]+\)$/.test( value ) ) { return null; }
			const raw = getComputedStyle( stage ).getPropertyValue( value.slice( 4, -1 ) ).trim();
			if ( /^#[\da-f]{3}(?:[\da-f]{3})?$/i.test( raw ) ) {
				const hex = raw.length === 4 ? raw.slice( 1 ).split( '' ).map( ( part ) => part + part ).join( '' ) : raw.slice( 1 );
				return [ 0, 2, 4 ].map( ( offset ) => parseInt( hex.slice( offset, offset + 2 ), 16 ) );
			}
			const rgb = raw.match( /^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i );
			return rgb && rgb.slice( 1 ).every( ( part ) => Number( part ) <= 255 ) ? rgb.slice( 1 ).map( Number ) : null;
		};
		const from = readColor( settings.from ); const to = readColor( settings.to );
		if ( ! from || ! to ) { return; }
		const scope = ( settings.scope === 'split' && stage.closest( '.mrn-content-builder__row--two-column-split' ) ) || stage.closest( '.mrn-content-builder__row' ) || stage;
		const walker = document.createTreeWalker( stage, NodeFilter.SHOW_TEXT );
		const textNodes = [];
		while ( walker.nextNode() ) {
			const node = walker.currentNode;
			if ( /\S/.test( node.textContent ) && ! node.parentElement.closest( 'script, style, textarea, select, button, svg, math, pre, code, [aria-hidden="true"], [data-mrn-no-word-reveal]' ) ) { textNodes.push( node ); }
		}
		const words = [];
		textNodes.forEach( ( node ) => {
			const fragment = document.createDocumentFragment();
			node.textContent.split( /(\s+)/ ).filter( Boolean ).forEach( ( part ) => {
				if ( /^\s+$/.test( part ) ) { fragment.appendChild( document.createTextNode( part ) ); return; }
				const word = document.createElement( 'span' ); word.className = 'mrn-scroll-word'; word.textContent = part;
				fragment.appendChild( word ); words.push( word );
			} );
			node.replaceWith( fragment );
		} );
		if ( ! words.length ) { return; }
		stage.dataset.mrnScrollWordsReady = 'true';
		let previous = '';
		const update = () => {
			const rect = scope.getBoundingClientRect();
			const range = window.innerHeight - ( window.innerHeight - rect.height ) / 2;
			const p = reduced.matches || range <= 0 ? 1 : clamp( ( window.innerHeight - rect.top ) / range );
			if ( previous === p ) { return; } previous = p;
			words.forEach( ( word, index ) => {
				const fraction = clamp( p * words.length - index );
				word.style.color = 'rgb(' + from.map( ( channel, position ) => Math.round( channel + ( to[ position ] - channel ) * fraction ) ).join( ',' ) + ')';
			} );
		};
		watch( stage, update );
		if ( 'ResizeObserver' in window ) { new ResizeObserver( update ).observe( scope ); }
	}
	function initStrip( range ) {
		const viewport = range.querySelector( '.mrn-scroll-strip__viewport' );
		const track = viewport?.querySelector( '.mrn-showcase-row__grid' );
		if ( ! track ) { return; }
		const flow = range.dataset.mrnStripBehavior === 'flow';
		const flowWide = flow ? window.matchMedia( '(min-width: 769px)' ) : wide;
		const rawPace = Number( range.dataset.mrnStripPace ?? 1.6 );
		const pace = Number.isFinite( rawPace ) && rawPace >= .1 && rawPace <= 10 ? rawPace : 1.6;
		const trigger = flow ? range.closest( '.mrn-content-builder__row' ) || range : range;
		let keyboard = false;
		const end = document.createElement( 'span' );
		end.id = 'mrn-strip-end-' + ( ++sequence );
		end.tabIndex = -1;
		range.insertAdjacentElement( 'afterend', end );
		const skip = document.createElement( 'a' );
		skip.className = flow ? 'screen-reader-text mrn-strip-flow-skip' : 'mrn-presentation-skip';
		skip.href = '#' + end.id;
		skip.textContent = range.dataset.skipLabel || 'Skip gallery';
		range.insertAdjacentElement( 'beforebegin', skip );
		let overflow = 0;
		let distance = 0;
		const prepare = () => {
			range.removeAttribute( 'data-enhanced' );
			range.removeAttribute( 'data-flow-enhanced' );
			range.style.height = '';
			track.style.transform = '';
			overflow = Math.max( 0, track.scrollWidth - viewport.clientWidth );
			if ( reduced.matches || ! flowWide.matches || overflow < 1 || keyboard ) { return; }
			if ( flow ) {
				distance = Math.max( 1, Math.round( overflow * pace ) );
				range.dataset.flowEnhanced = 'true';
				viewport.scrollLeft = 0; schedule(); return;
			}
			distance = Math.max( 300, overflow * .75 );
			range.dataset.enhanced = 'true';
			range.style.height = ( viewport.offsetHeight + distance ) + 'px';
			range.style.setProperty( '--mrn-presentation-top', topOffset() + 'px' );
			schedule();
		};
		const reveal = ( offset ) => {
			if ( ! range.hasAttribute( 'data-enhanced' ) ) { return; }
			const p = clamp( offset / overflow );
			window.scrollTo( { top: window.scrollY + range.getBoundingClientRect().top - topOffset() + p * distance, behavior: 'auto' } );
			viewport.scrollLeft = 0;
			track.style.transform = 'translateX(' + ( -p * overflow ) + 'px)';
		};
		viewport.addEventListener( 'focusin', ( event ) => {
			if ( flow ) { keyboard = true; prepare(); return; }
			const item = event.target.closest( '.mrn-showcase-row__item' );
			if ( item ) { reveal( item.offsetLeft ); }
		} );
		viewport.addEventListener( 'focusout', ( event ) => {
			if ( flow && ! viewport.contains( event.relatedTarget ) ) { keyboard = false; prepare(); }
		} );
		viewport.addEventListener( 'keydown', ( event ) => {
			if ( event.target === viewport && range.hasAttribute( 'data-enhanced' ) && [ 'ArrowLeft', 'ArrowRight' ].includes( event.key ) ) {
				event.preventDefault();
				window.scrollBy( { top: event.key === 'ArrowRight' ? 250 : -250, behavior: 'auto' } );
			}
		} );
		skip.addEventListener( 'click', () => end.focus() );
		prepare();
		new ResizeObserver( prepare ).observe( viewport );
		window.addEventListener( 'resize', prepare, { passive: true } );
		reduced.addEventListener( 'change', prepare );
		if ( flow ) { flowWide.addEventListener( 'change', prepare ); }
		watch( trigger, () => {
			if ( flow ) {
				if ( range.hasAttribute( 'data-flow-enhanced' ) ) { const p = clamp( ( window.innerHeight - trigger.getBoundingClientRect().top ) / distance ); track.style.transform = 'translateX(' + ( -p * overflow ) + 'px)'; }
				return;
			}
			if ( ! range.hasAttribute( 'data-enhanced' ) ) { return; }
			const p = clamp( ( topOffset() - range.getBoundingClientRect().top ) / distance );
			track.style.transform = 'translateX(' + ( -p * overflow ) + 'px)';
		} );
	}
	function initTimeline( timeline ) {
		const range = timeline.querySelector( '.mrn-timeline__range' );
		const stage = timeline.querySelector( '.mrn-timeline__stage' );
		const steps = Array.from( timeline.querySelectorAll( '.mrn-timeline__steps > li > details' ) );
		const pinTablet = timeline.dataset.mrnTimelinePinTablet === 'true';
		const numberedSteps = timeline.dataset.mrnTimelineStepStyle === 'numbered';
		const tabletWide = window.matchMedia( '(min-width: 768px)' );
		if ( ! steps.length ) { return; }
		let distance = 0;
		let current = 0;
		const select = ( index ) => {
			current = index;
			steps.forEach( ( step, position ) => { step.open = position === index; } );
			timeline.dataset.activeStep = String( index + 1 );
		};
		const prepare = () => {
			timeline.removeAttribute( 'data-enhanced' );
			timeline.removeAttribute( 'data-active-step' );
			range.style.height = '';
			stage.style.minHeight = '';
			steps.forEach( ( step ) => { step.open = true; } );
			if ( reduced.matches || !( pinTablet ? tabletWide.matches : wide.matches ) || steps.length < 2 ) { return; }
			const summaries = steps.reduce( ( total, step ) => total + step.querySelector( 'summary' ).getBoundingClientRect().height + ( numberedSteps ? 0 : 25 ), 0 );
			const body = numberedSteps ? steps[ 0 ].querySelector( '.mrn-timeline__body' ).getBoundingClientRect().height : Math.max( ...steps.map( ( step ) => step.querySelector( '.mrn-timeline__body' ).getBoundingClientRect().height ) );
			const stepSpace = numberedSteps ? ( parseFloat( getComputedStyle( steps[ 0 ].parentElement ).marginBottom ) || 0 ) * ( steps.length - 1 ) : 0;
			const height = Math.max( summaries + body + stepSpace, timeline.querySelector( '.mrn-timeline__media, .mrn-timeline__image' )?.offsetHeight || 0 ) + ( numberedSteps ? 0 : 32 );
			if ( height > window.innerHeight - topOffset() - 32 ) { return; }
			distance = timeline.dataset.mrnTimelineScrollPace === 'viewport'
				? steps.length * Math.max( 320, Math.round( window.innerHeight * 0.55 ) )
				: ( steps.length - 1 ) * 240;
			range.style.height = ( height + distance ) + 'px';
			stage.style.minHeight = height + 'px';
			timeline.style.setProperty( '--mrn-presentation-top', topOffset() + 'px' );
			timeline.dataset.enhanced = 'true';
			select( Math.min( current, steps.length - 1 ) );
			schedule();
		};
		steps.forEach( ( step, index ) => {
			step.querySelector( 'summary' ).addEventListener( 'click', ( event ) => {
				if ( ! timeline.hasAttribute( 'data-enhanced' ) ) { return; }
				event.preventDefault();
				select( index );
				window.scrollTo( { top: window.scrollY + range.getBoundingClientRect().top - topOffset() + index / ( steps.length - 1 ) * distance, behavior: 'auto' } );
			} );
		} );
		timeline.querySelector( '.mrn-timeline__skip' ).addEventListener( 'click', () => timeline.querySelector( '.mrn-timeline__end' ).focus() );
		prepare();
		window.addEventListener( 'resize', prepare, { passive: true } );
		// Step heights depend on web fonts and images; measure again once they settle so the pinned height is not a startup race.
		window.addEventListener( 'load', prepare, { once: true } );
		if ( document.fonts && document.fonts.ready ) { document.fonts.ready.then( prepare ); }
		reduced.addEventListener( 'change', prepare );
		watch( timeline, () => {
			if ( ! timeline.hasAttribute( 'data-enhanced' ) || timeline.contains( document.activeElement ) ) { return; }
			const p = clamp( ( topOffset() - range.getBoundingClientRect().top ) / distance );
			select( Math.round( p * ( steps.length - 1 ) ) );
		} );
	}
	function initHover( video ) {
		const article = video.closest( 'article' );
		const record = video.closest( '.mrn-record' );
		const stop = () => { video.pause(); record.classList.remove( 'is-video-playing' ); };
		const play = () => {
			if ( reduced.matches || document.hidden ) { return; }
			if ( ! video.src ) { video.src = video.dataset.src; }
			video.muted = true;
			video.play().then( () => {
				if ( ! reduced.matches && ( article.matches( ':hover' ) || article.contains( document.activeElement ) ) ) {
					record.classList.add( 'is-video-playing' );
				} else { stop(); }
			} ).catch( stop );
		};
		article.addEventListener( 'mouseenter', play );
		article.addEventListener( 'mouseleave', () => { if ( ! article.contains( document.activeElement ) ) { stop(); } } );
		article.addEventListener( 'focusin', play );
		article.addEventListener( 'focusout', ( event ) => { if ( ! article.contains( event.relatedTarget ) ) { stop(); } } );
		document.addEventListener( 'visibilitychange', () => { if ( document.hidden ) { stop(); } } );
		reduced.addEventListener( 'change', stop );
	}
	function initVideo( section ) {
		const media = section.querySelector( '.mrn-video-row__media' );
		let next = section.nextElementSibling;
		while ( next && ! next.matches( '.mrn-content-builder__row' ) ) { next = next.nextElementSibling; }
		const surface = next?.querySelector( '.mrn-layout-surface' ) || next?.querySelector( '.mrn-layout-section' ) || next;
		const original = surface ? surface.style.backgroundColor : '';
		const base = surface ? getComputedStyle( surface ).backgroundColor : '';
		const color = section.dataset.mrnFollowColor;
		const release = 'release' === section.dataset.mrnFollowRelease;
		if ( release && next ) { next.classList.add( 'has-mrn-follow-release' ); }
		const from = parseFloat( section.dataset.mrnVideoExpandFrom ) || 0;
		if ( from ) {
			// The following section's own color, or the page color when it has none.
			const clear = /^(transparent|rgba\(0, 0, 0, 0\))$/;
			const after = base && ! clear.test( base ) ? base : getComputedStyle( document.body ).backgroundColor;
			if ( after && ! clear.test( after ) ) { section.style.setProperty( '--mrn-video-after-color', after ); }
		}
		watch( section, () => {
			const p = clamp( ( window.innerHeight - section.getBoundingClientRect().top ) / window.innerHeight );
			if ( media && from ) {
				// Grow from the start width to full width over 30% of the viewport (min 220px), anchored at the bottom edge,
				// then fade the row background as the expanded video scrolls past the top (smootherstep over 40%, min 200px).
				const still = reduced.matches || window.matchMedia( '(max-width: 767px)' ).matches;
				const vh = window.innerHeight;
				const start = Math.min( 1, from / ( media.offsetWidth || from ) );
				const grown = still ? 1 : clamp( ( vh - section.getBoundingClientRect().top ) / Math.max( 220, vh * .3 ) );
				media.style.transformOrigin = '50% 100%';
				media.style.transform = still ? '' : 'scale(' + ( start + ( 1 - start ) * grown ) + ')';
				const t = still || grown < .999 ? 0 : clamp( -media.getBoundingClientRect().top / Math.max( 200, vh * .4 ) );
				section.style.setProperty( '--mrn-video-after-progress', ( t * t * t * ( t * ( t * 6 - 15 ) + 10 ) ).toFixed( 4 ) );
			} else if ( media ) {
				const compact = section.classList.contains( 'has-video-frame' ) && window.matchMedia( '(max-width: 767px)' ).matches;
				media.style.transform = reduced.matches || compact ? '' : 'scale(' + ( .82 + .18 * p ) + ')';
				media.style.borderRadius = reduced.matches || compact ? '' : ( ( 1 - p ) * 24 ) + 'px';
			}
			if ( surface && color && /^--[a-zA-Z0-9_-]+$/.test( color ) ) {
				let amount = p;
				if ( release && next ) {
					// Fade back to the section's own color between 80% and 60% of the viewport height.
					const arrive = next.getBoundingClientRect().top / window.innerHeight;
					amount = Math.min( p, clamp( ( arrive - 0.6 ) / 0.2 ) );
				}
				surface.style.backgroundColor = reduced.matches ? original : 'color-mix(in srgb, var(' + color + ') ' + ( amount * 100 ) + '%, ' + base + ')';
				if ( release && next ) { next.classList.toggle( 'is-mrn-follow-dark', ! reduced.matches && amount >= 0.5 ); }
			}
		} );
	}
	function init() {
		measureHeader();
		window.addEventListener( 'resize', measureHeader, { passive: true } );
		const header = document.querySelector( '#masthead, #site-header, .site-header' );
		if ( header && 'ResizeObserver' in window ) { new ResizeObserver( measureHeader ).observe( header ); }
		document.querySelectorAll( '[data-mrn-rotation-group]' ).forEach( initRotation );
		document.querySelectorAll( '[data-mrn-image-stage]' ).forEach( initImage );
		document.querySelectorAll( '[data-mrn-image-drift]' ).forEach( initImageDrift );
		document.querySelectorAll( '[data-mrn-scroll-words]' ).forEach( initScrollWords );
		document.querySelectorAll( '[data-mrn-scroll-strip]' ).forEach( initStrip );
		document.querySelectorAll( '[data-mrn-timeline]' ).forEach( initTimeline );
		document.querySelectorAll( '[data-mrn-hover-video]' ).forEach( initHover );
		document.querySelectorAll( '[data-mrn-video-expand]' ).forEach( initVideo );
		window.addEventListener( 'scroll', schedule, { passive: true } );
		window.addEventListener( 'resize', schedule, { passive: true } );
		reduced.addEventListener( 'change', () => tasks.forEach( ( update ) => update() ) );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init, { once: true } ); } else { init(); }
}() );

( function() {
	'use strict';
	// Before / After stacked cards: pin below the site header; each covered card recedes and the oldest releases.
	const GAP = 10;
	const SCALE_MIN = 0.94;
	const LIFT = 40;
	const VISIBLE = 3;
	const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const small = window.matchMedia( '(max-width: 767px)' );
	const lists = document.querySelectorAll( '.has-comparison-frame .mrn-content-list-row__items' );
	lists.forEach( ( list ) => {
		const cards = Array.from( list.querySelectorAll( '[data-mrn-comparison-card]' ) );
		const section = list.closest( '.has-comparison-frame' );
		if ( ! section || cards.length < 1 ) { return; }
		const count = cards.length;
		const peek = () => parseFloat( getComputedStyle( section ).getPropertyValue( '--mrn-comparison-stack-peek' ) ) || 10;
		let stackTop = GAP;
		const shift = cards.map( () => 0 );
		const header = document.querySelector( '#masthead, #site-header, .site-header' );
		const measure = () => {
			stackTop = ( header ? header.getBoundingClientRect().height : 0 ) + GAP;
			section.style.setProperty( '--mrn-comparison-stack-top', stackTop + 'px' );
		};
		const enabled = () => ! reduced.matches && ! small.matches;
		const clear = () => cards.forEach( ( card, i ) => {
			card.style.transform = '';
			card.style.opacity = '';
			card.style.pointerEvents = '';
			card.style.setProperty( '--mrn-comparison-cover', 0 );
			shift[ i ] = 0;
		} );
		const pinProgress = ( i ) => {
			const card = cards[ i ];
			const pinned = stackTop + i * peek();
			const height = card.offsetHeight || 1;
			const top = card.getBoundingClientRect().top - shift[ i ];
			return Math.max( 0, Math.min( 1, ( pinned + height - top ) / height ) );
		};
		const update = () => {
			if ( ! enabled() ) { clear(); return; }
			let released = 0;
			cards.forEach( ( card, i ) => {
				card.style.setProperty( '--mrn-comparison-index', i );
				const cover = i < count - 1 ? pinProgress( i + 1 ) : 0;
				const scale = 1 - cover * ( 1 - SCALE_MIN );
				const exit = i + VISIBLE < count ? pinProgress( i + VISIBLE ) : 0;
				const e = exit * exit * ( 3 - 2 * exit );
				const ty = -( released * peek() ) - e * LIFT;
				card.style.transform = 'translateY(' + ty.toFixed( 1 ) + 'px) scale(' + ( scale * ( 1 - e * 0.04 ) ).toFixed( 4 ) + ')';
				card.style.opacity = ( 1 - e ).toFixed( 3 );
				card.style.pointerEvents = e > 0.95 ? 'none' : '';
				card.style.setProperty( '--mrn-comparison-cover', cover.toFixed( 4 ) );
				shift[ i ] = ty;
				released += e;
			} );
		};
		let ticking = false;
		const onScroll = () => {
			if ( ticking ) { return; }
			ticking = true;
			window.requestAnimationFrame( () => { ticking = false; update(); } );
		};
		measure();
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', () => { measure(); update(); } );
		reduced.addEventListener?.( 'change', update );
		small.addEventListener?.( 'change', update );
		update();
	} );
}() );

( function() {
	'use strict';
	// Project peek carousel: rotates the first/last card so no clones are needed; drag, buttons and optional autoplay.
	const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const SPEED = 500;
	document.querySelectorAll( '[data-mrn-project-carousel]' ).forEach( ( section ) => {
		const viewport = section.querySelector( '[data-mrn-project-viewport]' );
		const list = viewport && viewport.querySelector( '.mrn-content-list-row__items' );
		const prevButton = section.querySelector( '[data-mrn-project-prev]' );
		const nextButton = section.querySelector( '[data-mrn-project-next]' );
		if ( ! viewport || ! list || list.children.length < 2 || list.querySelector( '.mrn-content-list-row__item--empty' ) ) { return; }
		const step = () => list.children[ 0 ].getBoundingClientRect().width + ( parseFloat( getComputedStyle( list ).columnGap ) || 0 );
		let animating = false, dragging = false, pending = false, startX = 0, currentX = 0, pointer = null, preparedPrev = false, suppress = false, suppressTimer = 0, timer = 0, paused = false;
		const settle = ( done ) => {
			let called = false;
			const run = () => { if ( called ) { return; } called = true; list.removeEventListener( 'transitionend', onEnd ); done(); };
			const onEnd = ( event ) => { if ( event.target === list && ( ! event.propertyName || 'transform' === event.propertyName ) ) { run(); } };
			list.addEventListener( 'transitionend', onEnd );
			window.setTimeout( run, SPEED + 80 );
		};
		const animate = ( x ) => { list.style.transition = reduced.matches ? 'none' : 'transform ' + SPEED + 'ms ease'; list.style.transform = 'translateX(' + x + 'px)'; };
		const rotateForward = () => { list.style.transition = 'none'; list.appendChild( list.firstElementChild ); list.style.transform = 'translateX(0px)'; void list.offsetWidth; };
		const next = () => {
			if ( animating || dragging ) { return; }
			animating = true; animate( -step() );
			settle( () => { rotateForward(); animating = false; } );
		};
		const prev = () => {
			if ( animating || dragging ) { return; }
			animating = true; list.style.transition = 'none';
			list.insertBefore( list.lastElementChild, list.firstElementChild );
			list.style.transform = 'translateX(' + ( -step() ) + 'px)'; void list.offsetWidth;
			animate( 0 );
			settle( () => { list.style.transition = 'none'; animating = false; } );
		};
		const stopTimer = () => { if ( timer ) { window.clearInterval( timer ); timer = 0; } };
		const startTimer = () => {
			if ( 'autoplay' !== section.getAttribute( 'data-mrn-project-carousel' ) || reduced.matches || paused || timer ) { return; }
			timer = window.setInterval( () => { if ( ! paused && ! animating && ! dragging ) { next(); } }, 5200 );
		};
		const pause = () => { paused = true; stopTimer(); };
		const resume = () => { paused = false; startTimer(); };
		[ [ prevButton, prev ], [ nextButton, next ] ].forEach( ( [ button, action ] ) => {
			if ( ! button ) { return; }
			button.hidden = false;
			button.addEventListener( 'pointerdown', ( event ) => { event.stopPropagation(); pause(); } );
			button.addEventListener( 'click', ( event ) => { event.preventDefault(); event.stopPropagation(); pause(); action(); } );
		} );
		const clearDrag = () => { pending = false; dragging = false; preparedPrev = false; pointer = null; section.classList.remove( 'is-carousel-dragging' ); resume(); };
		const finishDrag = ( dx ) => {
			const threshold = Math.max( 48, step() * 0.16 );
			animating = true;
			const forward = () => { animate( -step() ); settle( () => { rotateForward(); animating = false; clearDrag(); } ); };
			const rest = () => { animate( 0 ); settle( () => { list.style.transition = 'none'; animating = false; clearDrag(); } ); };
			if ( preparedPrev ) { dx > threshold ? rest() : forward(); return; }
			dx < -threshold ? forward() : rest();
		};
		viewport.addEventListener( 'pointerdown', ( event ) => {
			if ( animating || event.button > 0 || event.target.closest( 'button' ) ) { return; }
			pending = true; dragging = false; startX = currentX = event.clientX; pointer = event.pointerId; preparedPrev = false; suppress = false;
		} );
		viewport.addEventListener( 'pointermove', ( event ) => {
			if ( ! pending || event.pointerId !== pointer ) { return; }
			currentX = event.clientX; const dx = currentX - startX;
			if ( ! dragging ) {
				if ( Math.abs( dx ) <= 10 ) { return; }
				pause(); dragging = true; suppress = true; window.clearTimeout( suppressTimer ); suppressTimer = window.setTimeout( () => { suppress = false; }, 350 );
				list.style.transition = 'none'; section.classList.add( 'is-carousel-dragging' );
				if ( viewport.setPointerCapture ) { viewport.setPointerCapture( pointer ); }
			}
			if ( event.cancelable ) { event.preventDefault(); }
			if ( dx > 0 && ! preparedPrev ) { preparedPrev = true; list.insertBefore( list.lastElementChild, list.firstElementChild ); }
			list.style.transform = 'translateX(' + ( preparedPrev ? -step() + Math.max( dx, 0 ) : Math.min( dx, 0 ) ) + 'px)';
		} );
		const end = ( event, cancelled ) => {
			if ( ! pending || event.pointerId !== pointer ) { return; }
			if ( ! dragging ) { pending = false; pointer = null; return; }
			try { viewport.releasePointerCapture( pointer ); } catch ( ignore ) {}
			if ( cancelled ) { suppress = false; }
			finishDrag( cancelled ? 0 : currentX - startX );
		};
		viewport.addEventListener( 'pointerup', ( event ) => end( event, false ) );
		viewport.addEventListener( 'pointercancel', ( event ) => end( event, true ) );
		viewport.addEventListener( 'click', ( event ) => { if ( suppress ) { event.preventDefault(); event.stopPropagation(); suppress = false; } }, true );
		viewport.addEventListener( 'dragstart', ( event ) => event.preventDefault() );
		section.addEventListener( 'mouseenter', pause ); section.addEventListener( 'mouseleave', resume );
		section.addEventListener( 'focusin', pause ); section.addEventListener( 'focusout', ( event ) => { if ( ! section.contains( event.relatedTarget ) ) { resume(); } } );
		let resizeTimer = 0;
		window.addEventListener( 'resize', () => { window.clearTimeout( resizeTimer ); resizeTimer = window.setTimeout( () => { list.style.transition = 'none'; list.style.transform = 'translateX(0px)'; animating = false; }, 150 ); }, { passive: true } );
		reduced.addEventListener( 'change', () => { reduced.matches ? stopTimer() : startTimer(); } );
		section.classList.add( 'is-carousel-ready' );
		startTimer();
	} );
}() );

( function() {
	'use strict';
	// Project filters: tabs, term dropdowns with live counts, reset, load more and return-to-list state.
	const split = ( value ) => ( value || '' ).split( /\s+/ ).filter( Boolean );
	document.querySelectorAll( '[data-mrn-project-filters]' ).forEach( ( root ) => {
		if ( root.dataset.mrnProjectsMounted ) { return; }
		const cards = Array.from( root.querySelectorAll( '[data-project-card]' ) );
		const list = cards.length ? cards[ 0 ].parentElement : null;
		if ( ! list ) { return; }
		const sizes = ( root.getAttribute( 'data-mrn-project-filters' ) || '' ).split( ',' ).map( Number );
		const initial = sizes[ 0 ] >= 1 ? sizes[ 0 ] : 9;
		const batch = sizes[ 1 ] >= 1 ? sizes[ 1 ] : 6;
		const tabs = Array.from( root.querySelectorAll( 'button[data-project-tab]' ) );
		const groups = Array.from( root.querySelectorAll( '[data-project-group]' ) ).map( ( group ) => ( {
			key: group.getAttribute( 'data-project-group' ),
			element: group,
			trigger: group.querySelector( '.mrn-project-filters__trigger' ),
			panel: group.querySelector( '.mrn-project-filters__panel' ),
			badge: group.querySelector( '[data-project-badge]' ),
			boxes: Array.from( group.querySelectorAll( 'input[type="checkbox"]' ) ),
			active: new Set(),
		} ) );
		const reset = root.querySelector( '[data-project-reset]' );
		const resetCount = root.querySelector( '[data-project-reset-count]' );
		const more = root.querySelector( '[data-project-more]' );
		const status = root.querySelector( '[data-project-status]' );
		const message = root.getAttribute( 'data-project-status-message' ) || 'Showing %1$d of %2$d projects';
		const empty = document.createElement( 'li' );
		empty.className = 'mrn-project-filters__empty'; empty.hidden = true; empty.textContent = root.getAttribute( 'data-project-empty' ) || 'No posts found.';
		list.appendChild( empty );
		const storageKey = 'mrn-project-filters:' + window.location.pathname + '#' + ( root.id || '' );
		let tab = '*', loaded = initial;
		const slugs = ( card, name ) => split( card.getAttribute( 'data-project-' + name ) );
		const inTab = ( card ) => '*' === tab || slugs( card, 'tabs' ).includes( tab );
		const matches = ( card ) => inTab( card ) && groups.every( ( group ) => 0 === group.active.size || slugs( card, group.key ).some( ( slug ) => group.active.has( slug ) ) );
		const badge = ( group ) => { if ( group.badge ) { group.badge.textContent = String( group.active.size ); group.badge.hidden = 0 === group.active.size; } };
		const syncOptions = () => {
			const tabCards = cards.filter( inTab );
			groups.forEach( ( group ) => {
				const counts = {};
				tabCards.forEach( ( card ) => slugs( card, group.key ).forEach( ( slug ) => { counts[ slug ] = ( counts[ slug ] || 0 ) + 1; } ) );
				group.boxes.forEach( ( box ) => {
					const item = box.closest( '[data-project-term]' ), count = counts[ box.value ] || 0, label = item && item.querySelector( '[data-project-count]' );
					if ( label ) { label.textContent = String( count ); }
					if ( item ) { item.hidden = 0 === count; }
					if ( 0 === count && box.checked ) { box.checked = false; group.active.delete( box.value ); }
				} );
				badge( group );
			} );
		};
		const render = ( resetVisible, announce ) => {
			if ( resetVisible ) { loaded = initial; }
			const filtered = cards.filter( matches );
			const visible = new Set( filtered.slice( 0, loaded ) );
			let position = 0;
			cards.forEach( ( card ) => {
				const show = visible.has( card );
				if ( show && card.hidden ) { card.style.setProperty( '--mrn-project-delay', ( position * 55 ) + 'ms' ); position++; }
				card.hidden = ! show;
			} );
			empty.hidden = 0 !== filtered.length;
			if ( more ) { more.parentElement.hidden = 0 === filtered.length || visible.size >= filtered.length; }
			const total = groups.reduce( ( sum, group ) => sum + group.active.size, 0 );
			if ( reset ) { reset.disabled = 0 === total; }
			if ( resetCount ) { resetCount.textContent = total > 0 ? '(' + total + ')' : ''; }
			if ( status && announce ) { status.textContent = message.replace( '%1$d', String( visible.size ) ).replace( '%2$d', String( filtered.length ) ); }
			return Array.from( visible );
		};
		const closeAll = ( except ) => groups.forEach( ( group ) => { if ( group !== except && group.trigger && ! group.panel.hidden ) { group.panel.hidden = true; group.trigger.setAttribute( 'aria-expanded', 'false' ); } } );
		groups.forEach( ( group ) => {
			if ( ! group.trigger || ! group.panel ) { return; }
			group.trigger.addEventListener( 'click', ( event ) => {
				event.stopPropagation();
				const open = group.panel.hidden;
				closeAll( group );
				group.panel.hidden = ! open; group.trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
			group.panel.addEventListener( 'click', ( event ) => event.stopPropagation() );
			group.boxes.forEach( ( box ) => box.addEventListener( 'change', () => {
				box.checked ? group.active.add( box.value ) : group.active.delete( box.value );
				badge( group ); render( true, true );
			} ) );
		} );
		document.addEventListener( 'click', () => closeAll() );
		document.addEventListener( 'keydown', ( event ) => {
			if ( 'Escape' !== event.key ) { return; }
			const open = groups.find( ( group ) => group.panel && ! group.panel.hidden );
			if ( ! open ) { return; }
			const inside = open.element.contains( document.activeElement );
			closeAll();
			if ( inside ) { open.trigger.focus(); }
		} );
		tabs.forEach( ( button ) => button.addEventListener( 'click', () => {
			tab = button.getAttribute( 'data-project-tab' ) || '*';
			tabs.forEach( ( other ) => other.setAttribute( 'aria-pressed', other === button ? 'true' : 'false' ) );
			syncOptions(); render( true, true );
		} ) );
		if ( reset ) { reset.addEventListener( 'click', () => {
			tab = '*';
			tabs.forEach( ( button ) => button.setAttribute( 'aria-pressed', '*' === button.getAttribute( 'data-project-tab' ) ? 'true' : 'false' ) );
			groups.forEach( ( group ) => { group.boxes.forEach( ( box ) => { box.checked = false; } ); group.active.clear(); } );
			syncOptions(); render( true, true );
		} ); }
		if ( more ) { more.addEventListener( 'click', () => {
			const before = new Set( cards.filter( ( card ) => ! card.hidden ) );
			loaded += batch;
			const visible = render( false, true );
			if ( more.parentElement.hidden && document.activeElement === more ) {
				const next = visible.find( ( card ) => ! before.has( card ) ), link = next && next.querySelector( 'a[href]' );
				if ( link ) { link.focus( { preventScroll: true } ); }
			}
		} ); }
		list.addEventListener( 'click', ( event ) => {
			if ( ! event.target.closest( '[data-project-card] a[href]' ) ) { return; }
			try { window.sessionStorage.setItem( storageKey, JSON.stringify( { tab, loaded, scroll: window.scrollY, groups: groups.map( ( group ) => [ group.key, Array.from( group.active ) ] ) } ) ); } catch ( ignore ) {}
		} );
		let restored = false;
		try {
			const saved = JSON.parse( window.sessionStorage.getItem( storageKey ) || 'null' );
			window.sessionStorage.removeItem( storageKey );
			if ( saved && 'object' === typeof saved ) {
				if ( 'string' === typeof saved.tab && tabs.some( ( button ) => button.getAttribute( 'data-project-tab' ) === saved.tab ) ) { tab = saved.tab; tabs.forEach( ( button ) => button.setAttribute( 'aria-pressed', button.getAttribute( 'data-project-tab' ) === tab ? 'true' : 'false' ) ); }
				( Array.isArray( saved.groups ) ? saved.groups : [] ).forEach( ( entry ) => {
					const group = groups.find( ( candidate ) => candidate.key === entry[ 0 ] );
					if ( group && Array.isArray( entry[ 1 ] ) ) { group.boxes.forEach( ( box ) => { if ( entry[ 1 ].includes( box.value ) ) { box.checked = true; group.active.add( box.value ); } } ); badge( group ); }
				} );
				loaded = Number.isInteger( saved.loaded ) && saved.loaded >= initial ? Math.min( saved.loaded, 1000 ) : initial;
				restored = Number.isFinite( saved.scroll ) ? saved.scroll : true;
			}
		} catch ( ignore ) {}
		root.querySelectorAll( '[data-project-controls]' ).forEach( ( controls ) => { controls.hidden = false; } );
		syncOptions(); render( false, false );
		root.classList.add( 'is-filters-ready' );
		root.dataset.mrnProjectsMounted = 'true';
		if ( true !== restored && false !== restored ) { window.requestAnimationFrame( () => window.scrollTo( { top: restored, behavior: 'instant' } ) ); }
	} );
}() );

( function() {
	'use strict';
	// Underlined WPForms: floating labels, a styled single-select panel and an all-required-filled submit state.
	const clean = ( text ) => ( text || '' ).replace( /\s*\*\s*$/, '' ).replace( /^choose\s+/i, '' ).trim();
	const hasValue = ( control ) => !! control && ( ( control.value == null ? '' : control.value ) + '' ).trim() !== '';
	const controlOf = ( field ) => field.querySelector( 'input:not([type="hidden"]):not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]), textarea, select' );
	const placeholderOf = ( control ) => {
		if ( ! control ) { return ''; }
		if ( 'SELECT' === control.tagName ) { const option = control.querySelector( 'option.placeholder, option[value=""]' ); return clean( option ? option.textContent : '' ); }
		return clean( control.getAttribute( 'placeholder' ) || control.getAttribute( 'aria-label' ) || '' );
	};
	let uid = 0;
	function floating( field ) {
		if ( field.dataset.mrnFloatingInit === '1' || field.matches( '.wpforms-field-payment-checkbox, .wpforms-field-file-upload' ) ) { return; }
		const control = controlOf( field );
		if ( ! control || 'true' === control.getAttribute( 'aria-hidden' ) || control.tabIndex < 0 ) { return; }
		let label = field.querySelector( ':scope > .wpforms-field-label' );
		if ( label && 'true' === label.getAttribute( 'aria-hidden' ) ) { return; }
		if ( ! ( label && label.classList.contains( 'wpforms-label-hide' ) ) && ! placeholderOf( control ) ) { return; }
		const placeholder = placeholderOf( control );
		if ( ! label && placeholder && control.id ) {
			label = document.createElement( 'label' );
			label.className = 'wpforms-field-label wpforms-label-hide mrn-generated-label';
			label.setAttribute( 'for', control.id ); label.textContent = placeholder;
			field.insertBefore( label, field.firstChild );
		} else if ( label && ! clean( label.textContent ) && placeholder ) { label.textContent = placeholder; }
		if ( ! label ) { return; }
		const name = clean( label.textContent ) || placeholder;
		if ( name && ! control.getAttribute( 'aria-label' ) ) { control.setAttribute( 'aria-label', name ); }
		field.dataset.mrnFloatingInit = '1'; field.classList.add( 'mrn-floating-field' );
		const refresh = () => { const value = hasValue( control ); field.classList.toggle( 'mrn-field-has-value', value ); field.classList.toggle( 'mrn-field-is-active', value || field.classList.contains( 'mrn-field-is-focused' ) ); };
		control.addEventListener( 'focus', () => { field.classList.add( 'mrn-field-is-focused' ); refresh(); } );
		control.addEventListener( 'blur', () => { field.classList.remove( 'mrn-field-is-focused' ); refresh(); } );
		control.addEventListener( 'input', refresh ); control.addEventListener( 'change', refresh );
		refresh();
	}
	function enhanceSelect( select ) {
		if ( select.dataset.mrnSelectInit === '1' || select.multiple || select.closest( '.choices' ) ) { return; }
		select.dataset.mrnSelectInit = '1'; select.setAttribute( 'tabindex', '-1' );
		const field = select.closest( '.wpforms-field' );
		const fieldLabel = field ? field.querySelector( ':scope > .wpforms-field-label' ) : null;
		const base = select.id || 'mrn-select-' + ( ++uid );
		if ( fieldLabel && ! fieldLabel.id ) { fieldLabel.id = base + '-label'; }
		const wrap = document.createElement( 'div' ); wrap.className = 'mrn-select';
		const trigger = document.createElement( 'button' ); trigger.type = 'button'; trigger.className = 'mrn-select__trigger';
		trigger.setAttribute( 'aria-haspopup', 'listbox' ); trigger.setAttribute( 'aria-expanded', 'false' );
		trigger.innerHTML = '<span class="mrn-select__label"></span><svg class="mrn-select__chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"></polyline></svg>';
		const value = trigger.querySelector( '.mrn-select__label' ); value.id = base + '-value';
		if ( fieldLabel && fieldLabel.id ) { trigger.setAttribute( 'aria-labelledby', fieldLabel.id + ' ' + value.id ); } else if ( fieldLabel ) { trigger.setAttribute( 'aria-label', clean( fieldLabel.textContent ) ); }
		const panel = document.createElement( 'div' ); panel.className = 'mrn-select__panel'; panel.id = base + '-listbox'; panel.setAttribute( 'role', 'listbox' ); panel.setAttribute( 'aria-hidden', 'true' );
		if ( fieldLabel ) { panel.setAttribute( 'aria-labelledby', fieldLabel.id ); } else { panel.setAttribute( 'aria-label', 'Options' ); }
		trigger.setAttribute( 'aria-controls', panel.id );
		const options = Array.from( select.options ).map( ( opt, i ) => {
			const item = document.createElement( 'div' ); item.className = 'mrn-select__option' + ( '' === opt.value ? ' is-placeholder' : '' );
			item.setAttribute( 'role', 'option' ); item.setAttribute( 'tabindex', '-1' ); item.dataset.index = String( i ); item.id = base + '-option-' + i; item.textContent = opt.textContent;
			item.addEventListener( 'click', () => { choose( i ); close(); next(); } ); panel.appendChild( item ); return item;
		} );
		const selectable = () => options.filter( ( item ) => ! item.classList.contains( 'is-placeholder' ) );
		const sync = () => {
			const opt = select.options[ select.selectedIndex ]; value.textContent = opt ? opt.textContent : ''; wrap.classList.toggle( 'is-empty', ! select.value );
			options.forEach( ( item ) => { const on = parseInt( item.dataset.index, 10 ) === select.selectedIndex; item.classList.toggle( 'is-selected', on ); item.setAttribute( 'aria-selected', on ? 'true' : 'false' ); } );
		};
		const choose = ( index ) => { select.selectedIndex = index; select.dispatchEvent( new Event( 'input', { bubbles: true } ) ); select.dispatchEvent( new Event( 'change', { bubbles: true } ) ); sync(); };
		const open = () => { wrap.classList.add( 'is-open' ); trigger.setAttribute( 'aria-expanded', 'true' ); panel.setAttribute( 'aria-hidden', 'false' ); };
		const close = () => { wrap.classList.remove( 'is-open' ); trigger.setAttribute( 'aria-expanded', 'false' ); panel.setAttribute( 'aria-hidden', 'true' ); };
		let skipFocusOpen = false;
		const next = () => {
			const fields = select.form ? Array.from( select.form.querySelectorAll( '.wpforms-field' ) ) : [];
			for ( let i = fields.indexOf( field ) + 1; field && i < fields.length; i++ ) {
				const control = fields[ i ].querySelector( 'input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), .mrn-select__trigger' );
				if ( control && null !== control.offsetParent ) { control.focus(); return; }
			}
			skipFocusOpen = true; trigger.focus(); window.setTimeout( () => { skipFocusOpen = false; }, 0 );
		};
		trigger.addEventListener( 'pointerdown', () => { skipFocusOpen = true; window.setTimeout( () => { skipFocusOpen = false; }, 0 ); } );
		trigger.addEventListener( 'focus', () => { if ( ! skipFocusOpen ) { open(); } } );
		trigger.addEventListener( 'click', ( event ) => { event.preventDefault(); wrap.classList.contains( 'is-open' ) ? close() : open(); } );
		trigger.addEventListener( 'keydown', ( event ) => {
			if ( [ 'ArrowDown', 'Enter', ' ' ].includes( event.key ) ) { event.preventDefault(); open(); const list = selectable(); const current = list.find( ( item ) => item.classList.contains( 'is-selected' ) ) || list[ 0 ]; if ( current ) { current.focus(); } }
		} );
		panel.addEventListener( 'keydown', ( event ) => {
			const list = selectable(), index = list.indexOf( document.activeElement );
			if ( 'ArrowDown' === event.key ) { event.preventDefault(); ( list[ index + 1 ] || list[ 0 ] ).focus(); }
			else if ( 'ArrowUp' === event.key ) { event.preventDefault(); ( list[ index - 1 ] || list[ list.length - 1 ] ).focus(); }
			else if ( ( 'Enter' === event.key || ' ' === event.key ) && document.activeElement.dataset.index ) { event.preventDefault(); choose( parseInt( document.activeElement.dataset.index, 10 ) ); close(); next(); }
			else if ( 'Escape' === event.key ) { close(); trigger.focus(); }
		} );
		document.addEventListener( 'click', ( event ) => { if ( ! wrap.contains( event.target ) ) { close(); } } );
		wrap.addEventListener( 'focusout', () => window.setTimeout( () => { if ( ! wrap.contains( document.activeElement ) ) { close(); } }, 0 ) );
		select.addEventListener( 'change', sync );
		select.parentNode.insertBefore( wrap, select ); wrap.appendChild( trigger ); wrap.appendChild( panel ); wrap.appendChild( select ); sync();
	}
	function filled( form ) {
		const usable = ( el ) => ! [ 'hidden', 'button', 'submit', 'reset' ].includes( ( el.type || '' ).toLowerCase() );
		const inputs = Array.from( form.querySelectorAll( 'input[required], select[required], textarea[required], [aria-required="true"], .wpforms-field-required input, .wpforms-field-required select, .wpforms-field-required textarea' ) ).filter( usable );
		const has = ( el ) => {
			const type = ( el.type || '' ).toLowerCase();
			if ( 'checkbox' === type || 'radio' === type ) { return el.name && el.form ? el.form.querySelectorAll( 'input[name="' + ( window.CSS && CSS.escape ? CSS.escape( el.name ) : el.name ) + '"]:checked' ).length > 0 : el.checked; }
			return hasValue( el );
		};
		form.classList.toggle( 'all-filled', inputs.length > 0 && inputs.every( has ) );
	}
	function init() {
		document.querySelectorAll( '[data-mrn-underline-form] .wpforms-form' ).forEach( ( form ) => {
			if ( form.dataset.mrnUnderlineMounted ) { return; }
			form.dataset.mrnUnderlineMounted = '1';
			form.querySelectorAll( '.wpforms-field' ).forEach( floating );
			form.querySelectorAll( '.wpforms-field select' ).forEach( enhanceSelect );
			form.querySelectorAll( '.select_inline select' ).forEach( ( select ) => select.addEventListener( 'change', () => { if ( '' !== select.value ) { select.closest( '.select_inline' ).classList.add( 'selected' ); } } ) );
			[ 'input', 'change', 'keyup', 'blur' ].forEach( ( type ) => form.addEventListener( type, () => filled( form ), true ) );
			filled( form ); window.setTimeout( () => filled( form ), 600 ); window.setTimeout( () => filled( form ), 1800 );
		} );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init, { once: true } ); } else { init(); }
}() );

/* Link-opened form dialogs: focus handoff, Tab containment, Escape, scroll lock and focus return. */
( function () {
	'use strict';
	const modals = () => Array.from( document.querySelectorAll( '[data-mrn-form-modal]' ) );
	let active = null, opener = null;
	const focusables = ( root ) => Array.from( root.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])' ) ).filter( ( el ) => el.offsetParent !== null && ! el.classList.contains( 'mrn-form-modal__backdrop' ) && ! el.closest( '.wpforms-field-hp' ) );
	function open( modal, trigger ) {
		active = modal; opener = trigger || null;
		modal.classList.add( 'is-open' ); document.body.classList.add( 'mrn-form-modal-open' );
		const dialog = modal.querySelector( '.mrn-form-modal__dialog' );
		const place = () => {
			if ( active !== modal || modal.contains( document.activeElement ) ) { return; }
			const first = focusables( modal ).find( ( el ) => ! el.classList.contains( 'mrn-form-modal__close' ) && ! el.closest( '[aria-hidden="true"]' ) ) || dialog;
			first.focus();
			if ( ! modal.contains( document.activeElement ) && dialog ) { dialog.focus(); }
		};
		place(); window.requestAnimationFrame( place ); window.setTimeout( place, 250 );
	}
	function close() {
		if ( ! active ) { return; }
		active.classList.remove( 'is-open' ); document.body.classList.remove( 'mrn-form-modal-open' );
		if ( window.location.hash === '#' + active.id ) { window.history.replaceState( null, '', window.location.pathname + window.location.search ); }
		const back = opener; active = null; opener = null;
		if ( back && document.contains( back ) ) { back.focus(); }
	}
	function init() {
		const all = modals();
		if ( ! all.length ) { return; }
		all.forEach( ( modal ) => document.body.appendChild( modal ) );
		document.addEventListener( 'click', ( event ) => {
			const link = event.target.closest( 'a[href^="#"]' );
			if ( link && link.hasAttribute( 'data-mrn-form-modal-close' ) ) { event.preventDefault(); close(); return; }
			if ( ! link ) { return; }
			const modal = all.find( ( m ) => link.getAttribute( 'href' ) === '#' + m.id );
			if ( modal ) { event.preventDefault(); open( modal, link ); }
		} );
		document.addEventListener( 'keydown', ( event ) => {
			if ( ! active ) { return; }
			if ( event.key === 'Escape' ) { event.preventDefault(); close(); return; }
			if ( event.key !== 'Tab' ) { return; }
			const items = focusables( active );
			if ( ! items.length ) { return; }
			const first = items[ 0 ], last = items[ items.length - 1 ];
			if ( ! active.contains( document.activeElement ) ) { event.preventDefault(); first.focus(); }
			else if ( event.shiftKey && document.activeElement === first ) { event.preventDefault(); last.focus(); }
			else if ( ! event.shiftKey && document.activeElement === last ) { event.preventDefault(); first.focus(); }
		} );
		const fromHash = all.find( ( m ) => window.location.hash === '#' + m.id );
		if ( fromHash ) { open( fromHash, null ); }
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init, { once: true } ); } else { init(); }
}() );
