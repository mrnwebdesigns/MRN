( function () {
	const searchForms = document.querySelectorAll( '[data-mrn-search-toggle]' );

	if ( ! searchForms.length ) {
		return;
	}

	searchForms.forEach( function ( form ) {
		const toggle = form.querySelector( '.mrn-site-search__toggle' );
		const input = form.querySelector( '.mrn-site-search__input' );
		const clearButton = form.querySelector( '.mrn-site-search__clear' );
		const prompt = form.querySelector( '[data-mrn-search-prompt]' );

		if ( ! toggle || ! input ) {
			return;
		}

		const isExpanded = function () {
			return form.classList.contains( 'is-expanded' );
		};

		const setExpanded = function ( expanded ) {
			form.classList.toggle( 'is-expanded', expanded );
			toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		};

		const expand = function () {
			if ( isExpanded() ) {
				return;
			}

			setExpanded( true );

			window.requestAnimationFrame( function () {
				input.focus();
			} );
		};

		const syncClearButton = function () {
			if ( ! clearButton ) {
				return;
			}

			clearButton.hidden = '' === input.value.trim();
		};

		const syncPrompt = function () {
			if ( ! prompt ) {
				return;
			}

			prompt.hidden = '' !== input.value.trim();
		};

		const collapse = function ( moveFocus ) {
			if ( ! isExpanded() || '' !== input.value.trim() ) {
				return;
			}

			setExpanded( false );

			if ( moveFocus ) {
				toggle.focus();
			}
		};

		if ( clearButton ) {
			clearButton.addEventListener( 'click', function () {
				input.value = '';
				syncClearButton();
				syncPrompt();
				input.focus();
			} );
		}

		toggle.addEventListener( 'click', function () {
			if ( isExpanded() ) {
				input.focus();
				return;
			}

			expand();
		} );

		input.addEventListener( 'input', function () {
			syncClearButton();
			syncPrompt();
		} );

		form.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' !== event.key ) {
				return;
			}

			event.preventDefault();
			collapse( true );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( form.contains( event.target ) ) {
				return;
			}

			collapse( false );
		} );

		syncClearButton();
		syncPrompt();
	} );
}() );
