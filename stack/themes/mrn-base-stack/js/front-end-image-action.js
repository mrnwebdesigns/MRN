( function() {
	'use strict';
	if ( ! window.matchMedia || ! window.requestAnimationFrame ) { return; }
	var pointer = window.matchMedia( '(hover: hover) and (pointer: fine)' );
	var motion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	function mount( action ) {
		var marker = action.querySelector( '.mrn-image-action__marker' );
		if ( ! marker || action.dataset.mrnImageActionMounted ) { return; }
		action.dataset.mrnImageActionMounted = 'true';
		var frame = null;
		var bounds = null;
		var x = 0;
		var y = 0;
		var inset = Number( action.getAttribute( 'data-mrn-image-action-follow' ) );
		inset = Number.isInteger( inset ) && inset >= 0 && inset <= 100 ? inset : 18;
		function enabled() { return pointer.matches && ! motion.matches; }
		function reset() {
			if ( frame !== null ) { window.cancelAnimationFrame( frame ); frame = null; }
			bounds = null;
			action.classList.remove( 'is-following' );
			action.style.removeProperty( '--mrn-image-action-x' );
			action.style.removeProperty( '--mrn-image-action-y' );
		}
		function refresh() { reset(); action.classList.toggle( 'has-pointer-follow', enabled() ); }
		function clamp( value, size, half ) {
			// Small media centers an oversized marker instead of overflowing one edge.
			var edge = Math.min( size / 2, inset + half );
			return Math.min( size - edge, Math.max( edge, value ) );
		}
		function paint() {
			frame = null;
			if ( ! bounds || ! enabled() ) { return; }
			action.style.setProperty( '--mrn-image-action-x', clamp( x, bounds.width, marker.offsetWidth / 2 ) + 'px' );
			action.style.setProperty( '--mrn-image-action-y', clamp( y, bounds.height, marker.offsetHeight / 2 ) + 'px' );
		}
		function move( event ) {
			if ( ! enabled() || ( event.pointerType && event.pointerType !== 'mouse' ) ) { return; }
			if ( ! bounds ) { bounds = action.getBoundingClientRect(); }
			x = event.clientX - bounds.left;
			y = event.clientY - bounds.top;
			action.classList.add( 'is-following' );
			if ( frame === null ) { frame = window.requestAnimationFrame( paint ); }
		}
		action.addEventListener( 'pointerenter', move );
		action.addEventListener( 'pointermove', move );
		action.addEventListener( 'pointerleave', reset );
		action.addEventListener( 'pointercancel', reset );
		action.addEventListener( 'focus', reset );
		// Scrolling invalidates cached coordinates, including nested scroll containers.
		window.addEventListener( 'scroll', reset, { passive: true, capture: true } );
		window.addEventListener( 'resize', reset, { passive: true } );
		[ pointer, motion ].forEach( function( query ) {
			if ( typeof query.addEventListener === 'function' ) { query.addEventListener( 'change', refresh ); } else { query.addListener( refresh ); }
		} );
		refresh();
	}
	function init() { document.querySelectorAll( '[data-mrn-image-action-follow]' ).forEach( mount ); }
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
} )();
