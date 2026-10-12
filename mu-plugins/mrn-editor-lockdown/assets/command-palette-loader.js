/* global mrnEditorPalette */
( function() {
	'use strict';
	var pending;
	var loaded = false;
	function activate( node ) {
		return new Promise( function( resolve, reject ) {
			var script = document.createElement( 'script' );
			Array.from( node.attributes ).forEach( function( attribute ) {
				if ( [ 'type', 'async', 'defer', 'data-mrn-palette-script' ].indexOf( attribute.name ) === -1 ) {
					script.setAttribute( attribute.name, attribute.value );
				}
			} );
			if ( node.nonce ) {
				script.nonce = node.nonce;
			}
			script.async = false;
			script.textContent = node.textContent;
			script.onload = function() { node.remove(); resolve(); };
			script.onerror = function() { script.remove(); reject( new Error( 'Command palette asset failed' ) ); };
			node.parentNode.insertBefore( script, node );
			if ( ! script.src ) {
				node.remove();
				resolve();
			}
		} );
	}
	function open() {
		if ( pending ) {
			return;
		}
		var button = document.querySelector( '#wp-admin-bar-command-palette .ab-item' );
		if ( button ) {
			button.setAttribute( 'aria-busy', 'true' );
		}
		pending = Array.from( document.querySelectorAll( 'script[data-mrn-palette-script]' ) ).reduce( function( sequence, node ) {
			return sequence.then( function() { return activate( node ); } );
		}, Promise.resolve() ).then( function() {
			loaded = true;
			// The public commands store also handles opening before React commits.
			window.wp.data.dispatch( 'core/commands' ).open();
		} ).catch( function() {
			if ( window.wp && window.wp.a11y ) {
				window.wp.a11y.speak( mrnEditorPalette.failed, 'assertive' );
			}
		} ).finally( function() {
			pending = null;
			if ( button ) {
				button.removeAttribute( 'aria-busy' );
			}
		} );
	}
	function request( event ) {
		if ( loaded || ! document.querySelector( 'script[data-mrn-palette-script]' ) ) {
			return;
		}
		var keyboard = event.type === 'keydown' && event.key.toLowerCase() === 'k' && ( event.metaKey || event.ctrlKey ) && ! event.altKey && ! event.shiftKey;
		var toolbar = event.type === 'click' && event.target.closest( '#wp-admin-bar-command-palette' );
		if ( ! keyboard && ! toolbar ) {
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		open();
	}
	document.addEventListener( 'keydown', request, true );
	document.addEventListener( 'click', request, true );
} )();
