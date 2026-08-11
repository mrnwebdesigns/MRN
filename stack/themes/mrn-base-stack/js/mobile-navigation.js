/**
 * Standard mobile drawer and accessible submenu controls.
 */
( function() {
	const navigation = document.querySelector( '[data-mrn-mobile-navigation]' );

	if ( ! navigation ) {
		return;
	}

	const button = navigation.querySelector( ':scope > .menu-toggle' );
	const panel = navigation.querySelector( ':scope > .mrn-mobile-navigation__panel' );
	const menu = panel ? panel.querySelector( ':scope > .menu' ) : null;
	const configuredBreakpoint = parseInt( window.getComputedStyle( navigation ).getPropertyValue( '--mrn-mobile-menu-breakpoint' ), 10 );
	const mobileBreakpoint = Number.isFinite( configuredBreakpoint ) && configuredBreakpoint >= 320 && configuredBreakpoint <= 1600 ? configuredBreakpoint : 1199;
	const mobileQuery = window.matchMedia( '(max-width: ' + mobileBreakpoint + 'px)' );
	const submenuOpenLabel = navigation.dataset.submenuOpenLabel || 'Open %s submenu';
	const submenuCloseLabel = navigation.dataset.submenuCloseLabel || 'Close %s submenu';
	let restoreFocus = null;
	let scrollY = 0;
	let bodyStyle = null;
	let documentStyle = null;

	if ( ! button || ! panel || ! menu ) {
		return;
	}

	function getFocusableElements() {
		const panelElements = Array.from( panel.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])' ) ).filter( function( element ) {
			return ! element.hidden && element.getClientRects().length > 0;
		} );

		return [ button ].concat( panelElements );
	}

	function setSubmenuState( item, expanded ) {
		const toggle = item.querySelector( ':scope > .mrn-mobile-navigation__submenu-toggle' );
		const submenu = item.querySelector( ':scope > .sub-menu' );

		if ( ! toggle || ! submenu ) {
			return;
		}

		toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		toggle.setAttribute( 'aria-label', expanded ? toggle.dataset.closeLabel : toggle.dataset.openLabel );
		submenu.hidden = ! expanded;
		item.classList.toggle( 'is-submenu-open', expanded );
	}

	function prepareSubmenus() {
		menu.querySelectorAll( '.menu-item-has-children' ).forEach( function( item, index ) {
			const submenu = item.querySelector( ':scope > .sub-menu' );
			const link = item.querySelector( ':scope > a' );

			if ( ! submenu || item.querySelector( ':scope > .mrn-mobile-navigation__submenu-toggle' ) ) {
				return;
			}

			const label = link ? link.textContent.trim() : '';
			const submenuId = submenu.id || 'mrn-mobile-submenu-' + ( index + 1 );
			const toggle = document.createElement( 'button' );

			submenu.id = submenuId;
			toggle.type = 'button';
			toggle.className = 'mrn-mobile-navigation__submenu-toggle';
			toggle.setAttribute( 'aria-controls', submenuId );
			toggle.dataset.openLabel = submenuOpenLabel.replace( '%s', label );
			toggle.dataset.closeLabel = submenuCloseLabel.replace( '%s', label );
			toggle.innerHTML = '<svg viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false"><path d="M3 5.5l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
			item.insertBefore( toggle, submenu );

			toggle.addEventListener( 'click', function() {
				setSubmenuState( item, toggle.getAttribute( 'aria-expanded' ) !== 'true' );
			} );
		} );
	}

	function updateOffset() {
		if ( ! mobileQuery.matches ) {
			return;
		}

		const rectangle = navigation.getBoundingClientRect();
		navigation.style.setProperty( '--mrn-mobile-menu-top', Math.max( 0, Math.round( rectangle.bottom ) ) + 'px' );
	}

	function unlockPage() {
		document.documentElement.classList.remove( 'mrn-mobile-navigation-locked' );
		document.body.classList.remove( 'mrn-mobile-navigation-locked' );
		if ( documentStyle ) {
			document.documentElement.style.overflow = documentStyle.overflow;
			documentStyle = null;
		}
		if ( bodyStyle ) {
			document.body.style.overflow = bodyStyle.overflow;
			document.body.style.paddingRight = bodyStyle.paddingRight;
			bodyStyle = null;
		}
		window.scrollTo( 0, scrollY );
	}

	function closeDrawer( shouldRestoreFocus ) {
		if ( ! navigation.classList.contains( 'is-open' ) ) {
			return;
		}

		navigation.classList.remove( 'is-open' );
		button.setAttribute( 'aria-expanded', 'false' );
		button.setAttribute( 'aria-label', button.dataset.openLabel );
		unlockPage();

		if ( shouldRestoreFocus && restoreFocus ) {
			restoreFocus.focus();
		}
	}

	function openDrawer() {
		updateOffset();
		restoreFocus = document.activeElement;
		scrollY = window.scrollY || 0;
		const scrollbarWidth = Math.max( 0, window.innerWidth - document.documentElement.clientWidth );
		const bodyPaddingRight = parseFloat( window.getComputedStyle( document.body ).paddingRight ) || 0;
		documentStyle = {
			overflow: document.documentElement.style.overflow,
		};
		bodyStyle = {
			overflow: document.body.style.overflow,
			paddingRight: document.body.style.paddingRight,
		};
		document.documentElement.style.overflow = 'hidden';
		document.body.style.overflow = 'hidden';
		if ( scrollbarWidth > 0 ) {
			document.body.style.paddingRight = ( bodyPaddingRight + scrollbarWidth ) + 'px';
		}
		document.documentElement.classList.add( 'mrn-mobile-navigation-locked' );
		document.body.classList.add( 'mrn-mobile-navigation-locked' );
		updateOffset();
		navigation.classList.add( 'is-open' );
		button.setAttribute( 'aria-expanded', 'true' );
		button.setAttribute( 'aria-label', button.dataset.closeLabel );
		panel.scrollTop = 0;

		button.focus();
	}

	function syncMode() {
		const isMobile = mobileQuery.matches;
		navigation.dataset.mrnMobileActive = isMobile ? 'true' : 'false';
		button.hidden = ! isMobile;

		if ( ! isMobile ) {
			closeDrawer( false );
			navigation.querySelectorAll( '.menu-item-has-children' ).forEach( function( item ) {
				const toggle = item.querySelector( ':scope > .mrn-mobile-navigation__submenu-toggle' );
				const submenu = item.querySelector( ':scope > .sub-menu' );
				if ( toggle ) {
					toggle.hidden = true;
				}
				if ( submenu ) {
					submenu.hidden = false;
				}
			} );
			return;
		}

		navigation.querySelectorAll( '.menu-item-has-children' ).forEach( function( item ) {
			const toggle = item.querySelector( ':scope > .mrn-mobile-navigation__submenu-toggle' );
			if ( toggle ) {
				toggle.hidden = false;
				setSubmenuState( item, false );
			}
		} );
		updateOffset();
	}

	button.dataset.openLabel = button.getAttribute( 'aria-label' );
	button.dataset.closeLabel = button.dataset.closeLabel || 'Close navigation';
	prepareSubmenus();
	syncMode();

	button.addEventListener( 'click', function() {
		if ( navigation.classList.contains( 'is-open' ) ) {
			closeDrawer( true );
		} else {
			openDrawer();
		}
	} );

	panel.addEventListener( 'click', function( event ) {
		const link = event.target.closest( 'a' );
		if ( link && link.href && ! link.hasAttribute( 'data-mrn-keep-mobile-menu-open' ) ) {
			closeDrawer( false );
		}
	} );

	document.addEventListener( 'keydown', function( event ) {
		if ( ! navigation.classList.contains( 'is-open' ) ) {
			return;
		}

		if ( event.key === 'Escape' ) {
			event.preventDefault();
			closeDrawer( true );
			return;
		}

		if ( event.key !== 'Tab' ) {
			return;
		}

		const focusable = getFocusableElements();
		if ( ! focusable.length ) {
			return;
		}

		const first = focusable[ 0 ];
		const last = focusable[ focusable.length - 1 ];
		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	} );

	if ( typeof mobileQuery.addEventListener === 'function' ) {
		mobileQuery.addEventListener( 'change', syncMode );
	} else {
		mobileQuery.addListener( syncMode );
	}

	window.addEventListener( 'resize', updateOffset );
	window.addEventListener( 'orientationchange', updateOffset );
}() );
