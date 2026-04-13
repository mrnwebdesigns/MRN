( function() {
	'use strict';

	function getTabbedLayouts() {
		return document.querySelectorAll( '[data-mrn-tabbed-layout]' );
	}

	function getTabButtons( root ) {
		return root ? root.querySelectorAll( '[data-mrn-tab-button]' ) : [];
	}

	function getTabPanels( root ) {
		return root ? root.querySelectorAll( '[data-mrn-tab-panel]' ) : [];
	}

	function getTabPanelContents( root ) {
		return root ? root.querySelectorAll( '[data-mrn-tab-panel-content]' ) : [];
	}

	function getTabSliderElement( root ) {
		return root ? root.querySelector( '[data-mrn-tab-slider]' ) : null;
	}

	function getTransitionEffect( root ) {
		if ( ! root ) {
			return 'instant';
		}

		if ( root.classList.contains( 'mrn-tabbed-layout--transition-slide' ) ) {
			return 'slide';
		}

		if ( root.classList.contains( 'mrn-tabbed-layout--transition-fade' ) ) {
			return 'fade';
		}

		return 'instant';
	}

	function isSlideEffect( root ) {
		return getTransitionEffect( root ) === 'slide';
	}

	function isVerticalOrientation( root ) {
		return !! root && root.classList.contains( 'mrn-tabbed-layout--orientation-vertical' );
	}

	function getMountedSlider( root ) {
		return root && root.mrnTabSlider ? root.mrnTabSlider : null;
	}

	function shouldEqualizePanelHeights( root ) {
		return !! root && root.getAttribute( 'data-mrn-equal-panel-heights' ) === 'true';
	}

	function shouldManagePanelHeights( root ) {
		return shouldEqualizePanelHeights( root ) || ( isSlideEffect( root ) && isVerticalOrientation( root ) );
	}

	function getCurrentTabIndex( buttons, panels ) {
		var index;

		for ( index = 0; index < buttons.length; index++ ) {
			if ( buttons[ index ].getAttribute( 'aria-selected' ) === 'true' ) {
				return index;
			}
		}

		for ( index = 0; index < panels.length; index++ ) {
			if ( panels[ index ].classList.contains( 'is-active' ) ) {
				return index;
			}
		}

		return 0;
	}

	function getActivePanelIndex( root, buttons, panels ) {
		var slider = getMountedSlider( root );

		if ( slider ) {
			return slider.index;
		}

		return getCurrentTabIndex( buttons || getTabButtons( root ), panels || getTabPanels( root ) );
	}

	function getPanelMeasurementTarget( panel ) {
		var panelContent;

		if ( ! panel ) {
			return null;
		}

		panelContent = panel.querySelector( '[data-mrn-tab-panel-content]' );

		if ( panelContent ) {
			return panelContent.firstElementChild || panelContent;
		}

		return panel;
	}

	function getElementNaturalHeight( element ) {
		if ( ! element ) {
			return 0;
		}

		return Math.ceil( element.scrollHeight || element.offsetHeight || element.getBoundingClientRect().height || 0 );
	}

	function updateTabButtons( buttons, activeIndex ) {
		for ( var index = 0; index < buttons.length; index++ ) {
			var isActive = index === activeIndex;
			var button = buttons[ index ];

			button.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			button.setAttribute( 'tabindex', isActive ? '0' : '-1' );
			button.classList.toggle( 'is-active', isActive );
		}
	}

	function syncTabPanels( root, activeIndex ) {
		var panels = getTabPanels( root );
		var panelContents = getTabPanelContents( root );
		var usesSlider = isSlideEffect( root );

		panels.forEach( function( panel, index ) {
			var isActive = index === activeIndex;

			panel.classList.toggle( 'is-active', isActive );

			if ( usesSlider ) {
				panel.hidden = false;
			} else {
				panel.hidden = ! isActive;
			}
		} );

		panelContents.forEach( function( panelContent, index ) {
			panelContent.setAttribute( 'aria-hidden', index === activeIndex ? 'false' : 'true' );
		} );
	}

	function measurePanelHeight( root ) {
		var panels = getTabPanels( root );
		var activeIndex = getActivePanelIndex( root );
		var maxHeight = 0;

		if ( ! root || ! panels.length || ! shouldManagePanelHeights( root ) ) {
			if ( root ) {
				root.style.removeProperty( '--mrn-tabbed-layout-panel-height' );
			}
			return;
		}

		root.style.removeProperty( '--mrn-tabbed-layout-panel-height' );

		if ( shouldEqualizePanelHeights( root ) ) {
			if ( isSlideEffect( root ) ) {
				panels.forEach( function( panel ) {
					maxHeight = Math.max( maxHeight, getElementNaturalHeight( getPanelMeasurementTarget( panel ) ) );
				} );
			} else {
				panels.forEach( function( panel ) {
					panel.dataset.mrnWasHidden = panel.hidden ? 'true' : 'false';
					panel.hidden = false;
					panel.classList.add( 'is-measuring' );
				} );

				panels.forEach( function( panel ) {
					maxHeight = Math.max( maxHeight, getElementNaturalHeight( getPanelMeasurementTarget( panel ) ) );
				} );

				panels.forEach( function( panel ) {
					panel.classList.remove( 'is-measuring' );
					panel.hidden = panel.dataset.mrnWasHidden === 'true';
					delete panel.dataset.mrnWasHidden;
				} );
			}
		} else if ( panels[ activeIndex ] ) {
			maxHeight = getElementNaturalHeight( getPanelMeasurementTarget( panels[ activeIndex ] ) );
		}

		if ( maxHeight > 0 ) {
			root.style.setProperty( '--mrn-tabbed-layout-panel-height', maxHeight + 'px' );
		} else {
			root.style.removeProperty( '--mrn-tabbed-layout-panel-height' );
		}
	}

	function bindPanelHeightRecalculation( root ) {
		if ( ! shouldManagePanelHeights( root ) || root.getAttribute( 'data-mrn-panel-height-bound' ) === 'true' ) {
			return;
		}

		window.addEventListener( 'resize', function() {
			measurePanelHeight( root );
		} );

		window.addEventListener( 'load', function() {
			measurePanelHeight( root );
		} );

		root.querySelectorAll( 'img' ).forEach( function( image ) {
			if ( image.complete ) {
				return;
			}

			image.addEventListener( 'load', function() {
				measurePanelHeight( root );
			} );

			image.addEventListener( 'error', function() {
				measurePanelHeight( root );
			} );
		} );

		root.setAttribute( 'data-mrn-panel-height-bound', 'true' );
	}

	function mountTabSlider( root, initialIndex ) {
		var sliderElement = getTabSliderElement( root );
		var sliderOptions;
		var slider;

		if ( ! isSlideEffect( root ) || ! sliderElement || sliderElement.getAttribute( 'data-mrn-tab-slider-mounted' ) === 'true' || typeof window.Splide === 'undefined' ) {
			return false;
		}

		sliderOptions = {
			type: 'slide',
			perPage: 1,
			perMove: 1,
			arrows: false,
			pagination: false,
			drag: false,
			speed: 600,
			rewind: true,
			waitForTransition: true,
			updateOnMove: true,
			keyboard: false,
			slideFocus: false
		};

		if ( isVerticalOrientation( root ) ) {
			sliderOptions.direction = 'ttb';
			sliderOptions.height = 'var(--mrn-tabbed-layout-panel-height, auto)';
		} else {
			sliderOptions.direction = 'ltr';
			sliderOptions.autoHeight = true;
		}

		slider = new window.Splide( sliderElement, sliderOptions );
		root.mrnTabSlider = slider;

		slider.on( 'mounted moved', function() {
			updateTabButtons( getTabButtons( root ), slider.index );
			syncTabPanels( root, slider.index );
			measurePanelHeight( root );
		} );

		slider.mount();
		sliderElement.setAttribute( 'data-mrn-tab-slider-mounted', 'true' );

		if ( initialIndex > 0 ) {
			slider.go( initialIndex );
		}

		return true;
	}

	function activateTab( root, nextIndex, moveFocus ) {
		var buttons = getTabButtons( root );
		var panels = getTabPanels( root );
		var slider = getMountedSlider( root );

		if ( ! buttons.length || buttons.length !== panels.length ) {
			return;
		}

		updateTabButtons( buttons, nextIndex );

		if ( moveFocus && buttons[ nextIndex ] ) {
			buttons[ nextIndex ].focus();
		}

		if ( slider && isSlideEffect( root ) ) {
			slider.go( nextIndex );
			return;
		}

		syncTabPanels( root, nextIndex );
		measurePanelHeight( root );
	}

	function onTabKeydown( event ) {
		var button = event.currentTarget;
		var root = button ? button.closest( '[data-mrn-tabbed-layout]' ) : null;
		var buttons = getTabButtons( root );
		var currentIndex = Array.prototype.indexOf.call( buttons, button );
		var nextIndex = currentIndex;

		if ( ! buttons.length || currentIndex < 0 ) {
			return;
		}

		switch ( event.key ) {
			case 'ArrowRight':
			case 'ArrowDown':
				nextIndex = ( currentIndex + 1 ) % buttons.length;
				break;
			case 'ArrowLeft':
			case 'ArrowUp':
				nextIndex = ( currentIndex - 1 + buttons.length ) % buttons.length;
				break;
			case 'Home':
				nextIndex = 0;
				break;
			case 'End':
				nextIndex = buttons.length - 1;
				break;
			default:
				return;
		}

		event.preventDefault();
		activateTab( root, nextIndex, true );
	}

	function mountTabbedLayout( root ) {
		var buttons;
		var initialIndex = 0;

		if ( ! root || root.getAttribute( 'data-mrn-tabs-mounted' ) === 'true' ) {
			return;
		}

		buttons = getTabButtons( root );

		if ( ! buttons.length || buttons.length !== getTabPanels( root ).length ) {
			return;
		}

		initialIndex = getCurrentTabIndex( buttons, getTabPanels( root ) );

		buttons.forEach( function( button, index ) {
			button.addEventListener( 'click', function() {
				activateTab( root, index, false );
			} );

			button.addEventListener( 'keydown', onTabKeydown );
		} );

		bindPanelHeightRecalculation( root );

		if ( ! isSlideEffect( root ) ) {
			measurePanelHeight( root );
		}

		if ( ! mountTabSlider( root, initialIndex ) ) {
			activateTab( root, initialIndex, false );
		}

		root.setAttribute( 'data-mrn-tabs-mounted', 'true' );
	}

	function initTabbedLayouts() {
		var tabbedLayouts = getTabbedLayouts();

		if ( ! tabbedLayouts.length ) {
			return;
		}

		tabbedLayouts.forEach( mountTabbedLayout );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initTabbedLayouts );
	} else {
		initTabbedLayouts();
	}
} )();
