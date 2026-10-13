( function () {
	function mountVideoModals() {
		if ( typeof window.GLightbox === 'undefined' ) {
			return;
		}

		var selector = '.mrn-video-row__trigger.glightbox, a[data-mrn-video-modal]';
		var triggers = document.querySelectorAll( selector );
		if ( ! triggers.length ) {
			return;
		}

		// A poster, title and CTA may open the same Resource. Each trigger opens
		// one video rather than navigating through duplicate slides for that item.
		document.querySelectorAll( 'a[data-mrn-video-modal]' ).forEach( function ( trigger, index ) {
			trigger.setAttribute( 'data-gallery', 'mrn-resource-video-' + index );
		} );
		document.querySelectorAll( '.mrn-video-row__trigger.glightbox' ).forEach( function ( trigger ) {
			if ( ! trigger.hasAttribute( 'data-gallery' ) ) {
				trigger.setAttribute( 'data-gallery', 'mrn-video-rows' );
			}
		} );

		var activeTrigger = null;
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( selector );
			if ( trigger ) {
				activeTrigger = trigger;
			}
		}, true );

		var lightbox = window.GLightbox( {
			selector: selector,
			zoomable: false,
			touchNavigation: true,
			keyboardNavigation: true
		} );
		lightbox.on( 'open', function () {
			var dialog = document.querySelector( '.glightbox-container' );
			if ( dialog && activeTrigger && activeTrigger.getAttribute( 'aria-label' ) ) {
				dialog.setAttribute( 'aria-label', activeTrigger.getAttribute( 'aria-label' ) );
				dialog.setAttribute( 'aria-modal', 'true' );
			}
		} );
		lightbox.on( 'slide_after_load', function ( event ) {
			var label = event.trigger && event.trigger.getAttribute( 'aria-label' );
			if ( label && event.slideNode ) {
				event.slideNode.querySelectorAll( 'iframe' ).forEach( function ( frame ) {
					if ( ! frame.getAttribute( 'title' ) ) {
						frame.setAttribute( 'title', label );
					}
				} );
			}
		} );
		lightbox.on( 'close', function () {
			if ( activeTrigger && activeTrigger.isConnected ) {
				activeTrigger.focus();
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', mountVideoModals );
} )();
