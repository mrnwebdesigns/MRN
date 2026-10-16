( function() {
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
		var responsiveLogos = sliderElement.getAttribute( 'data-logos-responsive' ) === 'true';
		var continuous = responsiveLogos && sliderElement.getAttribute( 'data-continuous-scroll' ) === 'true';
		var autoplayMaximum = parseInt( sliderElement.getAttribute( 'data-autoplay-max-width' ) || '0', 10 );
		var breakpoints = {
			1200: { perPage: Math.min( 4, Math.max( 1, perPage || 1 ) ) },
			960: { perPage: Math.min( 3, Math.max( 1, perPage || 1 ) ) },
			680: { perPage: Math.min( 2, Math.max( 1, perPage || 1 ) ) },
			480: { perPage: 1 }
		};
		if ( responsiveLogos ) {
			try {
				var authoredViews = JSON.parse( sliderElement.getAttribute( 'data-slider-breakpoints' ) || '{}' );
				var widths = Object.keys( authoredViews );
				if ( widths.length > 0 && widths.length <= 4 ) {
					var validViews = {};
					widths.forEach( function( width ) {
						var maximum = Number( width );
						var count = Number( authoredViews[width].perPage );
						if ( Number.isInteger( maximum ) && maximum >= 320 && maximum <= 2560 && Number.isInteger( count ) && count >= 1 && count <= 7 ) {
							validViews[maximum] = { perPage: count };
						}
					} );
					if ( Object.keys( validViews ).length > 0 ) {
						breakpoints = validViews;
					}
				}
			} catch ( error ) {
				// Invalid optional configuration keeps the established responsive views.
			}
		}

		if ( ! showArrows && ! showPagination && ! sliderElement.hasAttribute( 'tabindex' ) ) {
			sliderElement.setAttribute( 'tabindex', '0' );
		}

		var options = {
			type: 'slide',
			perPage: Math.max( 1, Math.min( responsiveLogos ? 7 : 6, perPage || 1 ) ),
			perMove: 1,
			gap: responsiveLogos && sliderElement.getAttribute( 'data-slider-gap' ) ? sliderElement.getAttribute( 'data-slider-gap' ) : '1.5rem',
			arrows: showArrows,
			pagination: showPagination,
			autoplay: responsiveLogos && autoplay ? 'pause' : autoplay && autoplayDelay === 0,
			pauseOnHover: ! responsiveLogos && pauseOnHover,
			pauseOnFocus: ! responsiveLogos,
			interval: Math.max( 1000, Math.round( ( delayTime || 5 ) * 1000 ) ),
			speed: Math.max( 100, timeOnSlide || 600 ),
			keyboard: 'focused',
			rewind: true,
			breakpoints: breakpoints
		};
		if ( continuous ) {
			options.type = 'loop';
			options.clones = 0;
			options.easing = 'linear';
		}
		var splide = new window.Splide( sliderElement, options );

		splide.mount();
		if ( responsiveLogos && autoplay ) {
			configureLogoAutoplay( sliderElement, splide, continuous, pauseOnHover, autoplayMaximum, autoplayDelay );
		}

		if ( autoplay && ! responsiveLogos && autoplayDelay > 0 && splide.Components && splide.Components.Autoplay ) {
			window.setTimeout( function() {
				splide.Components.Autoplay.play();
			}, autoplayDelay );
		}

		sliderElement.dataset.mrnSliderMounted = 'true';
	}

	function configureLogoAutoplay( element, splide, continuous, pauseOnHover, maximum, delay ) {
		var reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		var viewport = maximum >= 320 && maximum <= 2560 ? window.matchMedia( '(max-width: ' + maximum + 'px)' ) : null;
		var toggle = element.querySelector( '[data-mrn-slider-toggle]' );
		var paused = false;
		var hovered = false;
		var focused = false;
		var visible = true;
		var ready = delay === 0;
		var overflow = element.classList.contains( 'is-overflow' );
		var wasPlaying = false;
		function sync() {
			var eligible = overflow && ! reduced.matches && ( ! viewport || viewport.matches );
			var playing = eligible && ready && ! paused && ! hovered && ! focused && visible && ! document.hidden;
			if ( splide.Components && splide.Components.Autoplay ) {
				splide.Components.Autoplay[playing ? 'play' : 'pause']();
				if ( continuous && wasPlaying && ! playing && splide.Components.Move ) {
					// Freeze the current linear transition as well as its timer.
					splide.Components.Move.cancel();
				}
			}
			wasPlaying = playing;
			if ( toggle ) {
				toggle.hidden = ! eligible;
				toggle.textContent = toggle.getAttribute( paused ? 'data-resume-label' : 'data-pause-label' );
				toggle.setAttribute( 'aria-pressed', paused ? 'true' : 'false' );
			}
		}
		function updateOverflow( value ) {
			if ( overflow !== value ) {
				overflow = value;
				if ( ! overflow ) { splide.go( 0 ); }
				if ( continuous ) {
					splide.options = { clones: overflow ? Math.max( 2, splide.options.perPage * 2 ) : 0 };
				}
			}
			sync();
		}
		splide.on( 'overflow', updateOverflow );
		if ( continuous && overflow ) {
			splide.options = { clones: Math.max( 2, splide.options.perPage * 2 ) };
		}
		element.addEventListener( 'mouseenter', function() { hovered = pauseOnHover; sync(); } );
		element.addEventListener( 'mouseleave', function() { hovered = false; sync(); } );
		element.addEventListener( 'focusin', function() { focused = true; sync(); } );
		element.addEventListener( 'focusout', function( event ) { focused = element.contains( event.relatedTarget ); sync(); } );
		document.addEventListener( 'visibilitychange', sync );
		if ( toggle ) {
			toggle.addEventListener( 'click', function() { paused = ! paused; sync(); } );
		}
		[ reduced, viewport ].forEach( function( query ) {
			if ( ! query ) { return; }
			if ( query.addEventListener ) { query.addEventListener( 'change', sync ); }
			else { query.addListener( sync ); }
		} );
		if ( typeof window.IntersectionObserver === 'function' ) {
			var observer = new window.IntersectionObserver( function( entries ) {
				visible = entries[0].isIntersecting;
				sync();
			} );
			observer.observe( element );
		}
		if ( delay > 0 ) {
			window.setTimeout( function() { ready = true; sync(); }, delay );
		}
		sync();
	}

	function initSliders() {
		var sliders = document.querySelectorAll( '.mrn-splide' );
		if ( ! sliders.length ) {
			return;
		}

		sliders.forEach( mountSlider );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initSliders );
	} else {
		initSliders();
	}
}() );
