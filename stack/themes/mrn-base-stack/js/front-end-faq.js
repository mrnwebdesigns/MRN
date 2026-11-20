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
			if ( item.dataset.mrnFaqMounted === 'true' || ( item.closest && item.closest( '[data-mrn-faq-scroll]' ) ) ) {
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

/* Opt-in scroll-active FAQ. Native details remain usable without this enhancement. */
( function() {
	function initScrollFaqs() {
		var groups = [];
		var queued = false;
		var reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		var mobile = window.matchMedia( '(max-width: 767px)' );
		document.querySelectorAll( '[data-mrn-faq-scroll]' ).forEach( function( root ) {
			if ( root.dataset.mrnFaqScrollMounted ) { return; }
			var items = Array.from( root.querySelectorAll( '.mrn-faq__item, .mrn-process__item' ) );
			if ( ! items.length ) { return; }
			root.dataset.mrnFaqScrollMounted = 'true';
			var ratio = Number( root.dataset.mrnFaqScroll );
			if ( ! Number.isFinite( ratio ) || ratio < 0.1 || ratio > 0.9 ) { ratio = 0.45; }
			var group = { root: root, items: items, ratio: ratio, expanded: null };
			groups.push( group );
			// Manual controls keep focus and never initiate scrolling or motion.
			items.forEach( function( item ) {
				item.addEventListener( 'toggle', function() {
					if ( ! item.open || group.expanded || ! item.contains( document.activeElement ) ) { return; }
					items.forEach( function( peer ) { if ( peer !== item && ! peer.contains( document.activeElement ) ) { peer.open = false; } } );
				} );
			} );
			root.addEventListener( 'focusout', schedule );
		} );
		if ( ! groups.length ) { return; }
		function update() {
			queued = false;
			groups.forEach( function( group ) {
				var expanded = reduced.matches || ( mobile.matches && group.root.dataset.mrnFaqMobileAll === 'true' );
				var changed = expanded !== group.expanded;
				group.expanded = expanded;
				if ( expanded ) {
					// Expand on entry, but retain the reader's subsequent manual choices.
					if ( changed ) { group.items.forEach( function( item ) { item.open = true; } ); }
					return;
				}
				// Never close content around keyboard focus, including answer links.
				if ( group.root.contains( document.activeElement ) ) { return; }
				var threshold = window.innerHeight * group.ratio;
				var active = group.items[0];
				group.items.forEach( function( item ) { if ( item.getBoundingClientRect().top <= threshold ) { active = item; } } );
				group.items.forEach( function( item ) { item.open = item === active; } );
			} );
		}
		function schedule() {
			if ( ! queued ) { queued = true; window.requestAnimationFrame( update ); }
		}
		window.addEventListener( 'scroll', schedule, { passive: true } );
		window.addEventListener( 'resize', schedule );
		reduced.addEventListener( 'change', schedule );
		mobile.addEventListener( 'change', schedule );
		update();
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', initScrollFaqs ); } else { initScrollFaqs(); }
}() );
