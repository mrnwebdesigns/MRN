( function () {
	function mountFilters( root ) {
		var filters = Array.prototype.slice.call( root.querySelectorAll( '[data-gallery-filter]' ) );
		var items = Array.prototype.slice.call( root.querySelectorAll( '[data-gallery-item]' ) );

		if ( ! filters.length || ! items.length ) {
			return;
		}

		filters.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var filter = button.getAttribute( 'data-gallery-filter' ) || 'all';

				filters.forEach( function ( candidate ) {
					candidate.classList.toggle( 'is-active', candidate === button );
				} );

				items.forEach( function ( item ) {
					var itemFilters = ( item.getAttribute( 'data-gallery-filters' ) || '' ).split( /\s+/ ).filter( Boolean );
					var matches = filter === 'all' || itemFilters.indexOf( filter ) !== -1;

					item.hidden = ! matches;
					item.classList.toggle( 'is-filtered-out', ! matches );
					item.style.display = matches ? '' : 'none';
				} );
			} );
		} );
	}

	function mountLightbox( root ) {
		if ( ! root || typeof window.GLightbox === 'undefined' ) {
			return;
		}

		var galleryGroup = root.getAttribute( 'data-gallery-group' ) || '';
		if ( galleryGroup === '' ) {
			return;
		}

		var loop = root.getAttribute( 'data-gallery-lightbox-loop' ) !== 'false';
		var autoplayVideo = root.getAttribute( 'data-gallery-lightbox-autoplay-video' ) !== 'false';
		var animation = root.getAttribute( 'data-gallery-lightbox-animation' ) || 'zoom';

		window.GLightbox( {
			selector: '.glightbox[data-gallery="' + galleryGroup + '"]',
			loop: loop,
			autoplayVideos: autoplayVideo,
			zoomable: true,
			openEffect: animation,
			closeEffect: animation,
			touchNavigation: true,
			keyboardNavigation: true
		} );
	}

	function mountThumbnailVideos( root ) {
		Array.prototype.slice.call( root.querySelectorAll( '[data-gallery-thumbnail-video]' ) ).forEach( function ( video ) {
			var shouldAutoplay = video.getAttribute( 'data-gallery-thumbnail-autoplay' ) === 'true';

			video.muted = true;
			video.loop = true;
			video.playsInline = true;

			if ( shouldAutoplay ) {
				var playPromise = video.play();
				if ( playPromise && typeof playPromise.catch === 'function' ) {
					playPromise.catch( function () {} );
				}
			} else {
				video.pause();
			}
		} );
	}

	function mountGallery( root ) {
		if ( ! root ) {
			return;
		}

		mountFilters( root );
		mountLightbox( root );
		mountThumbnailVideos( root );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.slice.call( document.querySelectorAll( '[data-gallery-root]' ) ).forEach( mountGallery );
	} );
} )();
