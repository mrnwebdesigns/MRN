/* global wp, jQuery */
/**
 * File customizer.js.
 *
 * Theme Customizer enhancements for a better user experience.
 *
 * Contains handlers to make Theme Customizer preview reload changes asynchronously.
 */

( function( $ ) {
	function sanitizeLimitedInlineHtml( value ) {
		var template = document.createElement( 'template' );
		var allowed = {
			BR: [],
			EM: [],
			SPAN: [ 'class' ],
			STRONG: [],
		};

		function clean( parent ) {
			Array.prototype.slice.call( parent.childNodes ).forEach( function( child ) {
				var allowedAttributes;

				if ( 1 === child.nodeType ) {
					allowedAttributes = allowed[ child.nodeName ];

					if ( ! allowedAttributes ) {
						child.parentNode.replaceChild( document.createTextNode( child.textContent || '' ), child );
						return;
					}

					Array.prototype.slice.call( child.attributes ).forEach( function( attribute ) {
						if ( -1 === allowedAttributes.indexOf( attribute.name.toLowerCase() ) ) {
							child.removeAttribute( attribute.name );
						}
					} );

					clean( child );
				} else if ( 3 !== child.nodeType ) {
					child.parentNode.removeChild( child );
				}
			} );
		}

		template.innerHTML = String( value || '' );
		clean( template.content );

		return template.innerHTML;
	}

	// Site title and description.
	wp.customize( 'blogname', function( value ) {
		value.bind( function( to ) {
			$( '.site-title a' ).text( to );
		} );
	} );
	wp.customize( 'blogdescription', function( value ) {
		value.bind( function( to ) {
			$( '.site-description' ).html( sanitizeLimitedInlineHtml( to ) );
		} );
	} );

	// Header text color.
	wp.customize( 'header_textcolor', function( value ) {
		value.bind( function( to ) {
			if ( 'blank' === to ) {
				$( '.site-title, .site-description' ).css( {
					clip: 'rect(1px, 1px, 1px, 1px)',
					position: 'absolute',
				} );
			} else {
				$( '.site-title, .site-description' ).css( {
					clip: 'auto',
					position: 'relative',
				} );
				$( '.site-title a, .site-description' ).css( {
					color: to,
				} );
			}
		} );
	} );
}( jQuery ) );
