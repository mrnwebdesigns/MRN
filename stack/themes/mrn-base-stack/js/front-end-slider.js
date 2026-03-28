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

		var splide = new window.Splide( sliderElement, {
			type: 'slide',
			perPage: Math.max( 1, Math.min( 3, perPage || 1 ) ),
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
				960: {
					perPage: Math.min( 2, Math.max( 1, perPage || 1 ) )
				},
				680: {
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
		var sliders = document.querySelectorAll( '.mrn-slider-row__splide' );
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
