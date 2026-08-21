/**
 * Keep the footer back-to-top control clear of consent and plugin overlays.
 */
( function() {
	const control = document.querySelector( '[data-mrn-back-to-top]' );

	if ( ! control ) {
		return;
	}

	const obstructionSelector = [
		'#stcm-banner',
		'#silktide-banner',
		'#stcm-modal',
		'#silktide-modal',
		'[data-mrn-back-to-top-obstruction]',
	].join( ',' );
	let scheduled = false;

	function rectanglesOverlap( first, second ) {
		return first.left < second.right && first.right > second.left && first.top < second.bottom && first.bottom > second.top;
	}

	function isVisible( element ) {
		const style = window.getComputedStyle( element );
		const rectangle = element.getBoundingClientRect();

		return 'none' !== style.display && 'hidden' !== style.visibility && 0 !== parseFloat( style.opacity ) && rectangle.width > 0 && rectangle.height > 0;
	}

	function updateObstructionState() {
		scheduled = false;

		const controlRectangle = control.getBoundingClientRect();
		const obstructed = Array.from( document.querySelectorAll( obstructionSelector ) ).some( function( element ) {
			return element !== control && isVisible( element ) && rectanglesOverlap( controlRectangle, element.getBoundingClientRect() );
		} );

		control.classList.toggle( 'is-obstructed', obstructed );
		control.setAttribute( 'aria-hidden', obstructed ? 'true' : 'false' );
	}

	function scheduleUpdate() {
		if ( scheduled ) {
			return;
		}

		scheduled = true;
		window.requestAnimationFrame( updateObstructionState );
	}

	const observer = new MutationObserver( scheduleUpdate );
	observer.observe( document.body, {
		attributes: true,
		attributeFilter: [ 'class', 'hidden', 'style' ],
		childList: true,
		subtree: true,
	} );

	window.addEventListener( 'resize', scheduleUpdate, { passive: true } );
	scheduleUpdate();
}() );
