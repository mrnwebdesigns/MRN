( function() {
	function normalizeMargin( value ) {
		if ( typeof value !== 'string' || '' === value.trim() ) {
			return '0px';
		}

		return value.trim();
	}

	function normalizeMotionTarget( value ) {
		if ( typeof value !== 'string' || '' === value.trim() ) {
			return 'row';
		}

		return value.trim();
	}

	function getMotionTargetSelectorMap() {
		// Shared front-end contract: these selectors are the stable targeting hooks
		// for builder rows and reusable blocks. Do not rename/remove them in
		// templates without updating this map, the implementation guide, and the
		// motion-target smoke coverage.
		return {
			surface: [
				'.mrn-layout-surface',
				'.mrn-reusable-block__inner',
				'.mrn-reusable-block',
				'.mrn-hero__inner',
				'.mrn-hero'
			],
			content: [
				'.mrn-layout-content--text',
				'.mrn-reusable-block__content',
				'.mrn-hero__content',
				'.mrn-reusable-block__inner',
				'.mrn-ui__body'
			],
			media: [
				'.mrn-ui__media',
				'.mrn-reusable-block__media',
				'.mrn-hero__media',
				'.mrn-section-background-media'
			],
			header: [
				'.mrn-ui__head',
				'.mrn-card-row__head',
				'.mrn-content-list-row__header',
				'.mrn-hero__content'
			],
			items: [
				'.mrn-ui__items',
				'.mrn-card-row__grid',
				'.mrn-content-list-row__items',
				'.mrn-faq__items'
			],
			'left-column': [
				'.mrn-two-column-split__column--left > .mrn-content-builder__row',
				'.mrn-two-column-split__column--left .mrn-content-builder__row',
				'.mrn-two-column-split__column--left'
			],
			'right-column': [
				'.mrn-two-column-split__column--right > .mrn-content-builder__row',
				'.mrn-two-column-split__column--right .mrn-content-builder__row',
				'.mrn-two-column-split__column--right'
			]
		};
	}

	function findMotionTarget( sectionElement ) {
		var target = normalizeMotionTarget( sectionElement.getAttribute( 'data-mrn-motion-target' ) );
		var selectorMap = getMotionTargetSelectorMap();
		var selectors = selectorMap[ target ] || [];
		var match = null;

		if ( 'row' === target || ! selectors.length ) {
			return sectionElement;
		}

		selectors.some( function( selector ) {
			if ( ! selector ) {
				return false;
			}

			if ( sectionElement.matches && sectionElement.matches( selector ) ) {
				match = sectionElement;
				return true;
			}

			match = sectionElement.querySelector( selector );
			return !! match;
		} );

		return match || sectionElement;
	}

	function getSurfaceTarget() {
		return document.body;
	}

	function activateSurface( sectionElement, surfaceValue ) {
		var target = getSurfaceTarget();

		if ( ! target || ! surfaceValue ) {
			return;
		}

		target.dataset.mrnSurface = surfaceValue;
		sectionElement.classList.add( 'is-mrn-active-surface' );
	}

	function deactivateSurface( sectionElement, surfaceValue ) {
		var target = getSurfaceTarget();

		sectionElement.classList.remove( 'is-mrn-active-surface' );

		if ( ! target || ! surfaceValue ) {
			return;
		}

		if ( target.dataset.mrnSurface === surfaceValue ) {
			delete target.dataset.mrnSurface;
		}
	}

	function initSurfaceSections( inView ) {
		var surfaceSections = document.querySelectorAll( '[data-mrn-surface]' );

		if ( ! surfaceSections.length ) {
			return;
		}

		surfaceSections.forEach( function( sectionElement ) {
			var surfaceValue = sectionElement.getAttribute( 'data-mrn-surface' );
			var marginValue = normalizeMargin( sectionElement.getAttribute( 'data-mrn-surface-margin' ) || '-35% 0px -35% 0px' );

			if ( ! surfaceValue ) {
				return;
			}

			inView( sectionElement, function() {
				activateSurface( sectionElement, surfaceValue );

				return function() {
					deactivateSurface( sectionElement, surfaceValue );
				};
			}, { margin: marginValue } );
		} );
	}

	function initActiveClassEffects( inView ) {
		var motionSections = document.querySelectorAll( '[data-mrn-motion-effect="active-class"]' );

		if ( ! motionSections.length ) {
			return;
		}

		motionSections.forEach( function( sectionElement ) {
			var targetElement = findMotionTarget( sectionElement );
			var activeClass = sectionElement.getAttribute( 'data-mrn-motion-class' ) || 'is-mrn-in-view';
			var marginValue = normalizeMargin( sectionElement.getAttribute( 'data-mrn-motion-margin' ) || '-35% 0px -35% 0px' );

			inView( targetElement, function() {
				targetElement.classList.add( activeClass );

				return function() {
					targetElement.classList.remove( activeClass );
				};
			}, { margin: marginValue } );
		} );
	}

	function mix( start, end, progress ) {
		return start + ( end - start ) * progress;
	}

	function clamp( value, min, max ) {
		var lower = typeof min === 'number' ? min : 0;
		var upper = typeof max === 'number' ? max : 1;

		return Math.min( Math.max( value, lower ), upper );
	}

	function parseRgbVar( value, fallback ) {
		var parts = typeof value === 'string'
			? value.trim().split( /[\s,]+/ ).filter( Boolean ).slice( 0, 3 )
			: [];

		if ( 3 !== parts.length ) {
			return fallback;
		}

		return parts.map( function( part, index ) {
			var fallbackValue = Array.isArray( fallback ) && typeof fallback[ index ] === 'number' ? fallback[ index ] : 0;
			var numericValue = parseFloat( part );

			if ( Number.isNaN( numericValue ) ) {
				return fallbackValue;
			}

			return Math.round( clamp( numericValue, 0, 255 ) );
		} );
	}

	function getCssNumberVar( element, name, fallback ) {
		if ( ! element || 'function' !== typeof window.getComputedStyle ) {
			return fallback;
		}

		var rawValue = window.getComputedStyle( element ).getPropertyValue( name );
		var numericValue = parseFloat( rawValue );

		return Number.isNaN( numericValue ) ? fallback : numericValue;
	}

	function getCssRgbVar( element, name, fallback ) {
		if ( ! element || 'function' !== typeof window.getComputedStyle ) {
			return fallback;
		}

		return parseRgbVar( window.getComputedStyle( element ).getPropertyValue( name ), fallback );
	}

	function findDarkScrollCardSurface( sectionElement ) {
		if ( sectionElement.classList && sectionElement.classList.contains( 'mrn-layout-surface' ) ) {
			return sectionElement;
		}

		return sectionElement.querySelector( '.mrn-layout-surface' ) || sectionElement;
	}

	function setStyles( elements, styles ) {
		elements.forEach( function( element ) {
			if ( ! element ) {
				return;
			}

			Object.keys( styles ).forEach( function( property ) {
				element.style[ property ] = styles[ property ];
			} );
		} );
	}

	function initDarkScrollCardEffects() {
		if ( ! window.Motion || 'function' !== typeof window.Motion.scroll ) {
			return;
		}

		var motionSections = document.querySelectorAll( '[data-mrn-motion-effect="dark-scroll-card"]' );

		if ( ! motionSections.length ) {
			return;
		}

		motionSections.forEach( function( sectionElement ) {
			var targetElement = findMotionTarget( sectionElement );
			var surfaceElement = findDarkScrollCardSurface( targetElement );
			var titleElements = Array.prototype.slice.call( targetElement.querySelectorAll( 'h1, h2, h3, h4, h5, h6' ) );
			var bodyTextElements = Array.prototype.slice.call( targetElement.querySelectorAll( '.mrn-ui__text, .mrn-hero__text, .mrn-faq__answer, .mrn-content-list-row__intro' ) );
			var labelElements = Array.prototype.slice.call( targetElement.querySelectorAll( '.mrn-ui__label, .mrn-hero__label' ) );
			var actionElements = Array.prototype.slice.call( targetElement.querySelectorAll( '.mrn-ui__link--button, .mrn-hero__link, button' ) );
			var imageElements = Array.prototype.slice.call( targetElement.querySelectorAll( 'img' ) );
			var targetBackground = getCssRgbVar( surfaceElement, '--mrn-dark-scroll-card-bg-rgb', [ 15, 15, 21 ] );
			var targetText = getCssRgbVar( surfaceElement, '--mrn-dark-scroll-card-text-rgb', [ 245, 245, 245 ] );
			var targetMuted = getCssRgbVar( surfaceElement, '--mrn-dark-scroll-card-muted-rgb', [ 182, 190, 201 ] );
			var targetButtonBackground = getCssRgbVar( surfaceElement, '--mrn-dark-scroll-card-button-bg-rgb', [ 255, 255, 255 ] );
			var targetButtonText = getCssRgbVar( surfaceElement, '--mrn-dark-scroll-card-button-text-rgb', [ 17, 17, 17 ] );
			var targetBorderAlpha = getCssNumberVar( surfaceElement, '--mrn-dark-scroll-card-border-alpha', 0.12 );
			var targetShadowAlpha = getCssNumberVar( surfaceElement, '--mrn-dark-scroll-card-shadow-alpha', 0.35 );
			var targetImageBrightness = getCssNumberVar( surfaceElement, '--mrn-dark-scroll-card-image-brightness', 0.72 );
			var targetImageSaturation = getCssNumberVar( surfaceElement, '--mrn-dark-scroll-card-image-saturation', 0.85 );

			window.Motion.scroll(
				function( progress ) {
					var p = clamp( progress );
					var cardBackground = [
						Math.round( mix( 255, targetBackground[ 0 ], p ) ),
						Math.round( mix( 255, targetBackground[ 1 ], p ) ),
						Math.round( mix( 255, targetBackground[ 2 ], p ) )
					];
					var cardText = [
						Math.round( mix( 17, targetText[ 0 ], p ) ),
						Math.round( mix( 17, targetText[ 1 ], p ) ),
						Math.round( mix( 17, targetText[ 2 ], p ) )
					];
					var mutedText = [
						Math.round( mix( 95, targetMuted[ 0 ], p ) ),
						Math.round( mix( 102, targetMuted[ 1 ], p ) ),
						Math.round( mix( 115, targetMuted[ 2 ], p ) )
					];
					var buttonBackground = [
						Math.round( mix( 17, targetButtonBackground[ 0 ], p ) ),
						Math.round( mix( 17, targetButtonBackground[ 1 ], p ) ),
						Math.round( mix( 17, targetButtonBackground[ 2 ], p ) )
					];
					var buttonText = [
						Math.round( mix( 255, targetButtonText[ 0 ], p ) ),
						Math.round( mix( 255, targetButtonText[ 1 ], p ) ),
						Math.round( mix( 255, targetButtonText[ 2 ], p ) )
					];
					var borderAlpha = mix( 0.08, targetBorderAlpha, p );
					var shadowAlpha = mix( 0.08, targetShadowAlpha, p );
					var imageBrightness = mix( 1, targetImageBrightness, p );
					var imageSaturation = mix( 1, targetImageSaturation, p );

					surfaceElement.style.backgroundColor = 'rgb(' + cardBackground.join( ', ' ) + ')';
					surfaceElement.style.color = 'rgb(' + cardText.join( ', ' ) + ')';
					surfaceElement.style.borderTopColor = 'rgba(255, 255, 255, ' + borderAlpha + ')';
					surfaceElement.style.borderBottomColor = 'rgba(255, 255, 255, ' + borderAlpha + ')';
					surfaceElement.style.boxShadow = '0 24px 80px rgba(0, 0, 0, ' + shadowAlpha + ')';

					setStyles( titleElements, {
						color: 'rgb(' + cardText.join( ', ' ) + ')'
					} );

					setStyles( labelElements.concat( bodyTextElements ), {
						color: 'rgb(' + mutedText.join( ', ' ) + ')'
					} );

					setStyles( actionElements, {
						backgroundColor: 'rgb(' + buttonBackground.join( ', ' ) + ')',
						color: 'rgb(' + buttonText.join( ', ' ) + ')',
						borderColor: 'rgb(' + buttonBackground.join( ', ' ) + ')'
					} );

					setStyles( imageElements, {
						filter: 'brightness(' + imageBrightness + ') saturate(' + imageSaturation + ') contrast(1.02)'
					} );
				},
				{
					target: surfaceElement,
					offset: [ 'start 90%', 'start 35%' ],
					axis: 'y'
				}
			);
		} );
	}

	function navigateButtonLink( buttonElement ) {
		var url = buttonElement && buttonElement.getAttribute ? buttonElement.getAttribute( 'data-mrn-link-url' ) : '';
		var target = buttonElement && buttonElement.getAttribute ? buttonElement.getAttribute( 'data-mrn-link-target' ) : '';
		var rel = buttonElement && buttonElement.getAttribute ? ( buttonElement.getAttribute( 'data-mrn-link-rel' ) || '' ).toLowerCase() : '';
		var shouldHardenNewWindow = rel.indexOf( 'noopener' ) !== -1 || rel.indexOf( 'noreferrer' ) !== -1;
		var newWindow = null;

		if ( ! url ) {
			return;
		}

		if ( '_blank' === target ) {
			newWindow = window.open( url, '_blank', shouldHardenNewWindow ? 'noopener' : '' );

			if ( newWindow && shouldHardenNewWindow ) {
				newWindow.opener = null;
			}

			return;
		}

		try {
			if ( '_parent' === target && window.parent && window.parent !== window ) {
				window.parent.location.assign( url );
				return;
			}

			if ( '_top' === target && window.top && window.top !== window ) {
				window.top.location.assign( url );
				return;
			}
		} catch ( error ) {
			// Fall back to the current window when cross-frame navigation is blocked.
		}

		window.location.assign( url );
	}

	function initContentLinkButtons() {
		document.addEventListener( 'click', function( event ) {
			var target = event.target;
			var buttonElement = target && target.closest ? target.closest( 'button[data-mrn-link-url]' ) : null;

			if ( ! buttonElement || buttonElement.disabled ) {
				return;
			}

			event.preventDefault();
			navigateButtonLink( buttonElement );
		} );
	}

	function initGlobalApi( inView ) {
		window.mrnBaseStack = window.mrnBaseStack || {};
		window.mrnBaseStack.motion = window.Motion || {};
		window.mrnBaseStack.inView = inView;
		window.mrnBaseStack.initSurfaceSections = function() {
			initSurfaceSections( inView );
		};
	}

	function initEffects() {
		initContentLinkButtons();

		if ( ! window.Motion || 'function' !== typeof window.Motion.inView ) {
			return;
		}

		initGlobalApi( window.Motion.inView );
		initSurfaceSections( window.Motion.inView );
		initActiveClassEffects( window.Motion.inView );
		initDarkScrollCardEffects();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initEffects );
	} else {
		initEffects();
	}
}() );
