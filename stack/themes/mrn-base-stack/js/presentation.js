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
		watch( stage, () => {
			const p = progress( stage );
			if ( image ) { image.style.transform = reduced.matches ? '' : 'scale(' + ( 1 + .08 * Math.sin( p * Math.PI ) ) + ')'; }
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
		const section = stage.closest( '.mrn-content-builder__row--two-column-split' ) || stage.closest( '.mrn-content-builder__row' ) || stage;
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
		const end = document.createElement( 'span' );
		end.id = 'mrn-strip-end-' + ( ++sequence );
		end.tabIndex = -1;
		range.insertAdjacentElement( 'afterend', end );
		const skip = document.createElement( 'a' );
		skip.className = 'mrn-presentation-skip';
		skip.href = '#' + end.id;
		skip.textContent = range.dataset.skipLabel || 'Skip gallery';
		range.insertAdjacentElement( 'beforebegin', skip );
		let overflow = 0;
		let distance = 0;
		const prepare = () => {
			range.removeAttribute( 'data-enhanced' );
			range.style.height = '';
			track.style.transform = '';
			overflow = Math.max( 0, track.scrollWidth - viewport.clientWidth );
			if ( reduced.matches || ! wide.matches || overflow < 1 ) { return; }
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
			const item = event.target.closest( '.mrn-showcase-row__item' );
			if ( item ) { reveal( item.offsetLeft ); }
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
		watch( range, () => {
			if ( ! range.hasAttribute( 'data-enhanced' ) ) { return; }
			const p = clamp( ( topOffset() - range.getBoundingClientRect().top ) / distance );
			track.style.transform = 'translateX(' + ( -p * overflow ) + 'px)';
		} );
	}
	function initTimeline( timeline ) {
		const range = timeline.querySelector( '.mrn-timeline__range' );
		const stage = timeline.querySelector( '.mrn-timeline__stage' );
		const steps = Array.from( timeline.querySelectorAll( '.mrn-timeline__steps > li > details' ) );
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
			if ( reduced.matches || ! wide.matches || steps.length < 2 ) { return; }
			const summaries = steps.reduce( ( total, step ) => total + step.querySelector( 'summary' ).getBoundingClientRect().height + 25, 0 );
			const body = Math.max( ...steps.map( ( step ) => step.querySelector( '.mrn-timeline__body' ).getBoundingClientRect().height ) );
			const height = Math.max( summaries + body, timeline.querySelector( '.mrn-timeline__image' )?.offsetHeight || 0 ) + 32;
			if ( height > window.innerHeight - topOffset() - 32 ) { return; }
			distance = ( steps.length - 1 ) * 240;
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
		watch( section, () => {
			const p = clamp( ( window.innerHeight - section.getBoundingClientRect().top ) / window.innerHeight );
			if ( media ) {
				media.style.transform = reduced.matches ? '' : 'scale(' + ( .82 + .18 * p ) + ')';
				media.style.borderRadius = reduced.matches ? '' : ( ( 1 - p ) * 24 ) + 'px';
			}
			if ( surface && color && /^--[a-zA-Z0-9_-]+$/.test( color ) ) {
				surface.style.backgroundColor = reduced.matches ? original : 'color-mix(in srgb, var(' + color + ') ' + ( p * 100 ) + '%, ' + base + ')';
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
