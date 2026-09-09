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

					answer.addEventListener( 'transitionend', function handleClose( transitionEvent ) {
						if ( transitionEvent.propertyName !== 'height' ) {
							return;
						}

						item.open = false;
						item.dataset.mrnFaqAnimating = 'false';
						answer.removeEventListener( 'transitionend', handleClose );
					} );

					return;
				}

				item.open = true;
				answer.classList.add( 'is-expanded' );
				setFaqAnswerHeight( answer, '0px' );
				answer.offsetHeight;
				endHeight = answer.scrollHeight;
				setFaqAnswerHeight( answer, endHeight + 'px' );

				answer.addEventListener( 'transitionend', function handleOpen( transitionEvent ) {
					if ( transitionEvent.propertyName !== 'height' ) {
						return;
					}

					setFaqAnswerHeight( answer, 'auto' );
					item.dataset.mrnFaqAnimating = 'false';
					answer.removeEventListener( 'transitionend', handleOpen );
				} );
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
