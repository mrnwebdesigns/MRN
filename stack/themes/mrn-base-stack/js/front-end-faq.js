( function() {
	function setFaqAnswerHeight( answer, height ) {
		if ( ! answer ) {
			return;
		}

		answer.style.height = height;
	}

	function initAnimatedFaqs() {
		var faqItems = document.querySelectorAll( '.mrn-faq__item' );
		if ( ! faqItems.length ) {
			return;
		}

		faqItems.forEach( function( item ) {
			if ( item.dataset.mrnFaqMounted === 'true' ) {
				return;
			}

			var summary = item.querySelector( '.mrn-faq__question' );
			var answer = item.querySelector( '.mrn-faq__answer' );
			var transitionHandler;
			var prefersReducedMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

			if ( ! summary || ! answer ) {
				return;
			}

			if ( item.open ) {
				answer.classList.add( 'is-expanded' );
				answer.style.height = 'auto';
			} else {
				answer.classList.remove( 'is-expanded' );
				answer.style.height = '0px';
			}

			// Native named details may close a sibling without clicking its summary.
			item.addEventListener( 'toggle', function() {
				if ( ! item.open && item.hasAttribute( 'name' ) ) {
					if ( transitionHandler ) {
						answer.removeEventListener( 'transitionend', transitionHandler );
						transitionHandler = null;
					}
					answer.classList.remove( 'is-expanded' );
					answer.style.height = '0px';
					item.dataset.mrnFaqAnimating = 'false';
				}
			} );

			summary.addEventListener( 'click', function( event ) {
				var startHeight;
				var endHeight;

				event.preventDefault();

				if ( item.dataset.mrnFaqAnimating === 'true' ) {
					return;
				}

				if ( prefersReducedMotion ) {
					item.open = ! item.open;
					answer.classList.toggle( 'is-expanded', item.open );
					answer.style.height = item.open ? 'auto' : '0px';
					return;
				}

				item.dataset.mrnFaqAnimating = 'true';

				if ( item.open ) {
					startHeight = answer.scrollHeight;
					setFaqAnswerHeight( answer, startHeight + 'px' );
					answer.offsetHeight;
					answer.classList.remove( 'is-expanded' );
					setFaqAnswerHeight( answer, '0px' );

					transitionHandler = function handleClose( transitionEvent ) {
						if ( transitionEvent.propertyName !== 'height' ) {
							return;
						}

						item.open = false;
						item.dataset.mrnFaqAnimating = 'false';
						answer.removeEventListener( 'transitionend', handleClose );
						transitionHandler = null;
					};
					answer.addEventListener( 'transitionend', transitionHandler );

					return;
				}

				item.open = true;
				answer.classList.add( 'is-expanded' );
				setFaqAnswerHeight( answer, '0px' );
				answer.offsetHeight;
				endHeight = answer.scrollHeight;
				setFaqAnswerHeight( answer, endHeight + 'px' );

				transitionHandler = function handleOpen( transitionEvent ) {
					if ( transitionEvent.propertyName !== 'height' ) {
						return;
					}

					setFaqAnswerHeight( answer, 'auto' );
					item.dataset.mrnFaqAnimating = 'false';
					answer.removeEventListener( 'transitionend', handleOpen );
					transitionHandler = null;
				};
				answer.addEventListener( 'transitionend', transitionHandler );
			} );

			item.dataset.mrnFaqMounted = 'true';
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAnimatedFaqs );
	} else {
		initAnimatedFaqs();
	}
}() );
