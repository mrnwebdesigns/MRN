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
		var videoTitle = mediaElement.getAttribute( 'data-video-title' ) || 'Embedded video';
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
				video.autoplay = shouldAutoplay;
				video.muted = shouldMute;
				video.loop = shouldLoop;
				video.playsInline = true;
				video.controls = showControls;
				video.preload = isBackgroundVideo ? 'none' : 'metadata';

				var source = document.createElement( 'source' );
				source.src = videoSrc;
				if ( videoMime ) {
					source.type = videoMime;
				}
				video.appendChild( source );

				if ( isBackgroundVideo ) {
					video.setAttribute( 'aria-hidden', 'true' );
					video.setAttribute( 'tabindex', '-1' );
				} else if ( videoTitle ) {
					video.setAttribute( 'aria-label', videoTitle );
				}

				if ( videoPoster ) {
					video.poster = videoPoster;
				}

				mediaElement.appendChild( video );
			} else {
				var iframe = document.createElement( 'iframe' );
				iframe.className = 'mrn-deferred-media__frame';
				iframe.src = videoSrc;
				iframe.title = isBackgroundVideo ? 'Decorative background video' : videoTitle;
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

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initDeferredVideos );
	} else {
		initDeferredVideos();
	}
}() );
