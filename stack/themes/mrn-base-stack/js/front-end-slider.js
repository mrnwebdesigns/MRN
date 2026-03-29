( function() {
	function canLoadDeferredVideo( mediaElement ) {
		if ( ! mediaElement ) {
			return false;
		}

		var isBackgroundVideo = mediaElement.getAttribute( 'data-video-background' ) === 'true';

		if (
			isBackgroundVideo &&
			window.matchMedia &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
		) {
			return false;
		}

		if (
			isBackgroundVideo &&
			mediaElement.getAttribute( 'data-video-desktop-only' ) === 'true' &&
			window.matchMedia &&
			window.matchMedia( '(max-width: 782px)' ).matches
		) {
			return false;
		}

		if (
			isBackgroundVideo &&
			typeof navigator !== 'undefined' &&
			navigator.connection &&
			navigator.connection.saveData
		) {
			return false;
		}

		return true;
	}

	function mountDeferredVideo( mediaElement ) {
		if ( ! mediaElement || mediaElement.dataset.mrnBackgroundVideoMounted === 'true' ) {
			return;
		}

		var videoSrc = mediaElement.getAttribute( 'data-video-src' ) || '';
		var videoKind = mediaElement.getAttribute( 'data-video-kind' ) || 'remote';
		var videoMime = mediaElement.getAttribute( 'data-video-mime' ) || '';
		var videoPoster = mediaElement.getAttribute( 'data-video-poster' ) || '';
		var isBackgroundVideo = mediaElement.getAttribute( 'data-video-background' ) === 'true';
		var shouldAutoplay = mediaElement.getAttribute( 'data-video-autoplay' ) === 'true';
		var shouldMute = mediaElement.getAttribute( 'data-video-muted' ) === 'true';
		var shouldLoop = mediaElement.getAttribute( 'data-video-loop' ) === 'true';
		var showControls = mediaElement.getAttribute( 'data-video-controls' ) !== 'false';

		if ( ! videoSrc || ! canLoadDeferredVideo( mediaElement ) ) {
			return;
		}

		var delayMs = parseInt( mediaElement.getAttribute( 'data-video-delay' ) || '2000', 10 );
		var mount = function() {
			if ( mediaElement.dataset.mrnBackgroundVideoMounted === 'true' ) {
				return;
			}

			if ( videoKind === 'local' ) {
				var video = document.createElement( 'video' );
				video.className = 'mrn-deferred-media__frame';
				video.src = videoSrc;
				video.autoplay = shouldAutoplay;
				video.muted = shouldMute;
				video.loop = shouldLoop;
				video.playsInline = true;
				video.controls = showControls;
				video.preload = isBackgroundVideo ? 'none' : 'metadata';
				if ( isBackgroundVideo ) {
					video.setAttribute( 'aria-hidden', 'true' );
					video.setAttribute( 'tabindex', '-1' );
				}

				if ( videoPoster ) {
					video.poster = videoPoster;
				}

				if ( videoMime ) {
					video.setAttribute( 'type', videoMime );
				}

				mediaElement.appendChild( video );
			} else {
				var iframe = document.createElement( 'iframe' );
				iframe.className = 'mrn-deferred-media__frame';
				iframe.src = videoSrc;
				iframe.title = isBackgroundVideo ? '' : 'Embedded video';
				iframe.setAttribute( 'loading', 'lazy' );
				iframe.setAttribute( 'allow', 'autoplay; fullscreen; picture-in-picture' );
				iframe.setAttribute( 'allowfullscreen', 'allowfullscreen' );
				if ( isBackgroundVideo ) {
					iframe.setAttribute( 'aria-hidden', 'true' );
					iframe.setAttribute( 'tabindex', '-1' );
				}
				iframe.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );

				mediaElement.appendChild( iframe );
			}

			mediaElement.dataset.mrnBackgroundVideoMounted = 'true';
		};

		window.setTimeout( mount, Math.max( 0, delayMs || 0 ) );
	}

	function initDeferredVideos() {
		var deferredMedia = document.querySelectorAll( '[data-video-src]' );
		if ( ! deferredMedia.length ) {
			return;
		}

		if ( typeof window.IntersectionObserver === 'undefined' ) {
			deferredMedia.forEach( mountDeferredVideo );
			return;
		}

		var observer = new window.IntersectionObserver( function( entries ) {
			entries.forEach( function( entry ) {
				if ( ! entry.isIntersecting ) {
					return;
				}

				mountDeferredVideo( entry.target );
				observer.unobserve( entry.target );
			} );
		}, {
			rootMargin: '150px 0px'
		} );

		deferredMedia.forEach( function( mediaElement ) {
			observer.observe( mediaElement );
		} );
	}

	function mountSlider( sliderElement ) {
		if ( ! sliderElement || typeof window.Splide === 'undefined' ) {
			return;
		}

		if ( sliderElement.dataset.mrnSliderMounted === 'true' ) {
			return;
		}

		var perPage = parseInt( sliderElement.getAttribute( 'data-per-page' ) || '1', 10 );
		var showArrows = sliderElement.getAttribute( 'data-arrows' ) === 'true';
		var showPagination = sliderElement.getAttribute( 'data-pagination' ) === 'true';
		var pauseOnHover = sliderElement.getAttribute( 'data-pause-on-hover' ) !== 'false';
		var autoplay = sliderElement.getAttribute( 'data-autoplay' ) === 'true';
		var delayStart = parseFloat( sliderElement.getAttribute( 'data-delay-start' ) || '0' );
		var delayTime = parseFloat( sliderElement.getAttribute( 'data-delay-time' ) || '5' );
		var timeOnSlide = parseInt( sliderElement.getAttribute( 'data-time-on-slide' ) || '600', 10 );
		var autoplayDelay = Math.max( 0, Math.round( ( delayStart || 0 ) * 1000 ) );

		var splide = new window.Splide( sliderElement, {
			type: 'slide',
			perPage: Math.max( 1, Math.min( 6, perPage || 1 ) ),
			perMove: 1,
			gap: '1.5rem',
			arrows: showArrows,
			pagination: showPagination,
			autoplay: autoplay && autoplayDelay === 0,
			pauseOnHover: pauseOnHover,
			pauseOnFocus: true,
			interval: Math.max( 1000, Math.round( ( delayTime || 5 ) * 1000 ) ),
			speed: Math.max( 100, timeOnSlide || 600 ),
			rewind: true,
			breakpoints: {
				1200: {
					perPage: Math.min( 4, Math.max( 1, perPage || 1 ) )
				},
				960: {
					perPage: Math.min( 3, Math.max( 1, perPage || 1 ) )
				},
				680: {
					perPage: Math.min( 2, Math.max( 1, perPage || 1 ) )
				},
				480: {
					perPage: 1
				}
			}
		} );

		splide.mount();

		if ( autoplay && autoplayDelay > 0 && splide.Components && splide.Components.Autoplay ) {
			window.setTimeout( function() {
				splide.Components.Autoplay.play();
			}, autoplayDelay );
		}

		sliderElement.dataset.mrnSliderMounted = 'true';
	}

	function initSliders() {
		var sliders = document.querySelectorAll( '.mrn-splide' );
		if ( ! sliders.length ) {
			return;
		}

		sliders.forEach( mountSlider );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			initDeferredVideos();
			initSliders();
		} );
	} else {
		initDeferredVideos();
		initSliders();
	}
}() );
