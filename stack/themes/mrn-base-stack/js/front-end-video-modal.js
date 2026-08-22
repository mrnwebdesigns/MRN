( function () {
	function mountVideoModals() {
		if ( typeof window.GLightbox === 'undefined' ) {
			return;
		}

		var triggers = document.querySelectorAll( '.mrn-video-row__trigger.glightbox' );
		if ( ! triggers.length ) {
			return;
		}

		window.GLightbox( {
			selector: '.mrn-video-row__trigger.glightbox',
			zoomable: false,
			touchNavigation: true,
			keyboardNavigation: true
		} );
	}

	document.addEventListener( 'DOMContentLoaded', mountVideoModals );
} )();
