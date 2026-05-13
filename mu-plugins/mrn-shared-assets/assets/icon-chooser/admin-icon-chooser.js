( function ( $, window ) {
	var config = window.mrnSharedIconChooserData || {};
	var dashicons = Array.isArray( config.dashicons ) ? config.dashicons : [];
	var fontawesome = config.fontawesome && typeof config.fontawesome === 'object' ? config.fontawesome : {};
	var fontawesomeStyleLabels = config.fontawesomeStyleLabels && typeof config.fontawesomeStyleLabels === 'object'
		? config.fontawesomeStyleLabels
		: {};
	var fontawesomePickerEndpoint = config.fontawesomePickerEndpoint && typeof config.fontawesomePickerEndpoint === 'object'
		? config.fontawesomePickerEndpoint
		: {};
	var strings = config.strings || {};
	var $modal = $();
	var currentRequest = null;
	var faStyle = 'solid';
	var faSelectionRegistrationCache = {};
	var faRegistrationState = {
		sessionAdded: 0,
		allowlistCount: null
	};

	function text( key, fallback ) {
		return Object.prototype.hasOwnProperty.call( strings, key ) ? strings[ key ] : fallback;
	}

	function faStyleOrder() {
		return [
			'solid',
			'regular',
			'brands',
			'light',
			'thin',
			'duotone',
			'sharp-solid',
			'sharp-regular',
			'sharp-light',
			'sharp-thin',
			'sharp-duotone'
		];
	}

	function defaultFaStyleLabel( style ) {
		var fallbackLabels = {
			solid: 'Solid',
			regular: 'Regular',
			brands: 'Brands',
			light: 'Light',
			thin: 'Thin',
			duotone: 'Duotone',
			'sharp-solid': 'Sharp Solid',
			'sharp-regular': 'Sharp Regular',
			'sharp-light': 'Sharp Light',
			'sharp-thin': 'Sharp Thin',
			'sharp-duotone': 'Sharp Duotone'
		};

		if ( Object.prototype.hasOwnProperty.call( fallbackLabels, style ) ) {
			return fallbackLabels[ style ];
		}

		return String( style || '' )
			.replace( /-/g, ' ' )
			.replace( /\b[a-z]/g, function ( letter ) {
				return letter.toUpperCase();
			} );
	}

	function faStyleLabel( style ) {
		var normalized = String( style || '' ).toLowerCase();

		if ( Object.prototype.hasOwnProperty.call( fontawesomeStyleLabels, normalized ) ) {
			return String( fontawesomeStyleLabels[ normalized ] || '' );
		}

		return defaultFaStyleLabel( normalized );
	}

	function availableFaStyles() {
		var known = faStyleOrder();
		var discovered = Object.keys( fontawesome || {} ).filter( function ( key ) {
			return Array.isArray( fontawesome[ key ] ) && fontawesome[ key ].length;
		} );
		var ordered = [];

		known.forEach( function ( key ) {
			if ( -1 !== discovered.indexOf( key ) && -1 === ordered.indexOf( key ) ) {
				ordered.push( key );
			}
		} );

		discovered.forEach( function ( key ) {
			if ( -1 === ordered.indexOf( key ) ) {
				ordered.push( key );
			}
		} );

		if ( ! ordered.length ) {
			ordered.push( 'solid' );
		}

		return ordered;
	}

	function activeFaStyle() {
		var available = availableFaStyles();
		if ( -1 !== available.indexOf( faStyle ) ) {
			return faStyle;
		}

		faStyle = available[ 0 ];
		return faStyle;
	}

	function faList( style ) {
		var normalizedStyle = String( style || '' ).toLowerCase();
		return Array.isArray( fontawesome[ normalizedStyle ] ) ? fontawesome[ normalizedStyle ] : [];
	}

	function faClass( style, name ) {
		var normalizedStyle = String( style || '' ).toLowerCase();

		if ( 'brands' === normalizedStyle ) {
			return 'fa-brands fa-' + name;
		}

		if ( 'regular' === normalizedStyle ) {
			return 'fa-regular fa-' + name;
		}

		if ( 'light' === normalizedStyle ) {
			return 'fa-light fa-' + name;
		}

		if ( 'thin' === normalizedStyle ) {
			return 'fa-thin fa-' + name;
		}

		if ( 'duotone' === normalizedStyle ) {
			return 'fa-duotone fa-solid fa-' + name;
		}

		if ( 'sharp-solid' === normalizedStyle ) {
			return 'fa-sharp fa-solid fa-' + name;
		}

		if ( 'sharp-regular' === normalizedStyle ) {
			return 'fa-sharp fa-regular fa-' + name;
		}

		if ( 'sharp-light' === normalizedStyle ) {
			return 'fa-sharp fa-light fa-' + name;
		}

		if ( 'sharp-thin' === normalizedStyle ) {
			return 'fa-sharp fa-thin fa-' + name;
		}

		if ( 'sharp-duotone' === normalizedStyle ) {
			return 'fa-sharp-duotone fa-solid fa-' + name;
		}

		return 'fa-solid fa-' + name;
	}

	function normalizeIconValue( value ) {
		var raw = String( value || '' ).trim().toLowerCase();
		var tokens = raw ? raw.split( /\s+/ ) : [];
		var clean = [];

		tokens.forEach( function ( token ) {
			var safe = token.replace( /[^a-z0-9-]/g, '' );
			if ( safe && -1 === clean.indexOf( safe ) ) {
				clean.push( safe );
			}
		} );

		clean.sort();
		return clean.join( ' ' );
	}

	function registerFontawesomeSelection( value ) {
		var normalized = normalizeIconValue( value );
		var baseStatus = '';

		if (
			! normalized ||
			! fontawesomePickerEndpoint ||
			! fontawesomePickerEndpoint.ajaxUrl ||
			! fontawesomePickerEndpoint.action ||
			! fontawesomePickerEndpoint.nonce
		) {
			renderFontAwesomeRegistrationStatus( null, 'muted' );
			return;
		}

		if ( Object.prototype.hasOwnProperty.call( faSelectionRegistrationCache, normalized ) ) {
			renderFontAwesomeRegistrationStatus( text( 'faTrackingAlready', 'Icon already tracked for local pack.' ), 'active' );
			return;
		}

		faSelectionRegistrationCache[ normalized ] = true;
		renderFontAwesomeRegistrationStatus( text( 'faTrackingSaving', 'Saving icon to local pack list...' ), 'pending' );

		$.post( fontawesomePickerEndpoint.ajaxUrl, {
			action: fontawesomePickerEndpoint.action,
			nonce: fontawesomePickerEndpoint.nonce,
			icon_class: normalized
		} ).done( function ( response ) {
			var allowlistCount = response && response.data ? parseInt( response.data.allowlist_count, 10 ) : NaN;
			var added = !! ( response && response.success && response.data && response.data.added );

			if ( ! Number.isNaN( allowlistCount ) ) {
				faRegistrationState.allowlistCount = allowlistCount;
			}

			if ( added ) {
				faRegistrationState.sessionAdded += 1;
			}

			baseStatus = added
				? text( 'faTrackingAdded', 'Added to local pack list.' )
				: text( 'faTrackingAlready', 'Icon already tracked for local pack.' );

			renderFontAwesomeRegistrationStatus( baseStatus, 'active' );
			$( document ).trigger( 'mrnSharedIconChooser:fontawesomeRegistration', [ response ] );
		} ).fail( function () {
			delete faSelectionRegistrationCache[ normalized ];
			renderFontAwesomeRegistrationStatus( text( 'faTrackingError', 'Could not register icon right now.' ), 'error' );
		} );
	}

	function fontAwesomeRegistrationIsAvailable() {
		return !! (
			fontawesomePickerEndpoint &&
			fontawesomePickerEndpoint.ajaxUrl &&
			fontawesomePickerEndpoint.action &&
			fontawesomePickerEndpoint.nonce
		);
	}

	function getFontAwesomeRegistrationStatusMessage( baseMessage ) {
		var message = String( baseMessage || '' ).trim();
		var pieces = [];

		if ( null !== faRegistrationState.allowlistCount ) {
			pieces.push( text( 'faTrackingAllowlistLabel', 'Allowlist' ) + ': ' + faRegistrationState.allowlistCount );
		}

		pieces.push( text( 'faTrackingSessionLabel', 'Session Adds' ) + ': ' + faRegistrationState.sessionAdded );

		if ( ! message ) {
			if ( fontAwesomeRegistrationIsAvailable() ) {
				message = text( 'faTrackingEnabled', 'Local pack tracking is on.' );
			} else {
				message = text( 'faTrackingUnavailable', 'Local pack tracking is unavailable on this screen.' );
			}
		}

		if ( pieces.length ) {
			message += ' ' + pieces.join( ' · ' );
		}

		return message;
	}

	function renderFontAwesomeRegistrationStatus( baseMessage, tone ) {
		var $status = ensureModal().find( '.mrn-shared-fa-registration-status' );
		var classes = 'mrn-shared-fa-registration-status';
		var resolvedTone = String( tone || '' );

		if ( ! $status.length ) {
			return;
		}

		if ( ! resolvedTone ) {
			resolvedTone = fontAwesomeRegistrationIsAvailable() ? 'active' : 'muted';
		}

		$status
			.attr( 'class', classes + ' is-' + resolvedTone )
			.text( getFontAwesomeRegistrationStatusMessage( baseMessage ) );
	}

	function detectStyleFromIconValue( value ) {
		var normalized = normalizeIconValue( value );
		var parts = normalized ? normalized.split( /\s+/ ) : [];
		var tokens = {};

		parts.forEach( function ( part ) {
			tokens[ part ] = true;
		} );

		if ( tokens[ 'fa-sharp-duotone' ] || tokens.fasds || tokens.fasdr || tokens.fasdl || tokens.fasdt ) {
			return 'sharp-duotone';
		}

		if ( tokens[ 'fa-sharp' ] ) {
			if ( tokens[ 'fa-regular' ] || tokens.fasr ) {
				return 'sharp-regular';
			}
			if ( tokens[ 'fa-light' ] || tokens.fasl ) {
				return 'sharp-light';
			}
			if ( tokens[ 'fa-thin' ] || tokens.fast ) {
				return 'sharp-thin';
			}
			return 'sharp-solid';
		}

		if ( tokens[ 'fa-duotone' ] || tokens.fad || tokens.fads || tokens.fadr || tokens.fadl || tokens.fadt ) {
			return 'duotone';
		}

		if ( tokens[ 'fa-brands' ] || tokens.fab ) {
			return 'brands';
		}

		if ( tokens[ 'fa-regular' ] || tokens.far ) {
			return 'regular';
		}

		if ( tokens[ 'fa-light' ] || tokens.fal ) {
			return 'light';
		}

		if ( tokens[ 'fa-thin' ] || tokens.fat ) {
			return 'thin';
		}

		return 'solid';
	}

	function ensureModal() {
		if ( $modal.length ) {
			return $modal;
		}

		$modal = $(
			'<div class="mrn-shared-icon-modal" style="display:none;">' +
				'<div class="mrn-shared-icon-modal__inner">' +
					'<div class="mrn-shared-icon-modal__header">' +
						'<strong>' + text( 'chooseIcon', 'Choose Icon' ) + '</strong>' +
						'<button type="button" class="button-link mrn-shared-icon-close" aria-label="' + text( 'close', 'Close' ) + '">×</button>' +
					'</div>' +
					'<div class="mrn-shared-icon-modal__tabs">' +
						'<button type="button" class="button mrn-shared-icon-tab is-active" data-tab="dashicons">' + text( 'dashicons', 'Dashicons' ) + '</button>' +
						'<button type="button" class="button mrn-shared-icon-tab" data-tab="fontawesome">' + text( 'fontAwesome', 'Font Awesome' ) + '</button>' +
						'<button type="button" class="button mrn-shared-icon-tab" data-tab="image">' + text( 'image', 'Image' ) + '</button>' +
						'<button type="button" class="button mrn-shared-icon-clear">' + text( 'clear', 'Clear' ) + '</button>' +
					'</div>' +
					'<div class="mrn-shared-icon-panel is-active" data-panel="dashicons">' +
						'<input type="text" class="mrn-shared-icon-search" placeholder="' + text( 'searchDashicons', 'Search dashicons...' ) + '" />' +
						'<div class="mrn-shared-icon-grid"></div>' +
					'</div>' +
					'<div class="mrn-shared-icon-panel" data-panel="fontawesome">' +
						'<div class="mrn-shared-fa-tabs"></div>' +
						'<input type="text" class="mrn-shared-fa-search" placeholder="' + text( 'searchFontAwesome', 'Search Font Awesome...' ) + '" />' +
						'<div class="mrn-shared-fa-registration-status" role="status" aria-live="polite"></div>' +
						'<div class="mrn-shared-fa-grid"></div>' +
						'<div class="mrn-shared-fa-empty" style="display:none;">' + text( 'noIconsFound', 'No icons found.' ) + '</div>' +
					'</div>' +
					'<div class="mrn-shared-icon-panel" data-panel="image">' +
						'<p>' + text( 'image', 'Image' ) + '</p>' +
						'<button type="button" class="button mrn-shared-image-select">' + text( 'chooseImage', 'Choose Image' ) + '</button>' +
						'<div class="mrn-shared-image-preview"></div>' +
					'</div>' +
				'</div>' +
			'</div>'
		);

		$( document.body ).append( $modal );
		renderDashicons();
		renderFontAwesomeStyleTabs();
		renderFontAwesome();
		renderFontAwesomeRegistrationStatus( null );
		return $modal;
	}

	function renderDashicons() {
		var $grid = ensureModal().find( '.mrn-shared-icon-grid' );
		var frag = document.createDocumentFragment();

		$grid.empty();

		dashicons.forEach( function ( icon ) {
			var full = 'dashicons-' + icon;
			var button = document.createElement( 'button' );
			var span = document.createElement( 'span' );
			button.type = 'button';
			button.className = 'mrn-shared-icon-grid__item';
			button.setAttribute( 'data-type', 'dashicons' );
			button.setAttribute( 'data-value', full );
			button.setAttribute( 'title', full );
			span.className = 'dashicons ' + full;
			button.appendChild( span );
			frag.appendChild( button );
		} );

		$grid.append( frag );
	}

	function renderFontAwesome() {
		var $grid = ensureModal().find( '.mrn-shared-fa-grid' );
		var $empty = $modal.find( '.mrn-shared-fa-empty' );
		var style = activeFaStyle();
		var query = String( $modal.find( '.mrn-shared-fa-search' ).val() || '' ).trim().toLowerCase();
		var frag = document.createDocumentFragment();
		var count = 0;

		$grid.empty();

		faList( style ).forEach( function ( icon ) {
			var name = icon && icon.name ? String( icon.name ) : '';
			var label = icon && icon.label ? String( icon.label ) : name;
			var klass;
			var button;
			var span;

			if ( ! name ) {
				return;
			}

			if ( query && -1 === name.toLowerCase().indexOf( query ) && -1 === label.toLowerCase().indexOf( query ) ) {
				return;
			}

			klass = faClass( style, name );
			button = document.createElement( 'button' );
			span = document.createElement( 'span' );
			button.type = 'button';
			button.className = 'mrn-shared-fa-grid__item';
			button.setAttribute( 'data-type', 'fontawesome' );
			button.setAttribute( 'data-value', klass );
			button.setAttribute( 'title', label );
			span.className = klass;
			button.appendChild( span );
			frag.appendChild( button );
			count++;
		} );

		$grid.append( frag );
		$empty.toggle( ! count );
	}

	function renderFontAwesomeStyleTabs() {
		var $tabs = ensureModal().find( '.mrn-shared-fa-tabs' );
		var styles = availableFaStyles();

		$tabs.empty();

		styles.forEach( function ( style ) {
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'button mrn-shared-fa-tab';
			button.setAttribute( 'data-style', style );
			button.appendChild( document.createTextNode( faStyleLabel( style ) ) );
			$tabs.append( button );
		} );

		faStyle = activeFaStyle();
		$tabs.find( '.mrn-shared-fa-tab' ).removeClass( 'is-active' );
		$tabs.find( '.mrn-shared-fa-tab[data-style="' + faStyle + '"]' ).addClass( 'is-active' );
	}

	function activateTab( tab ) {
		$modal.find( '.mrn-shared-icon-tab' ).removeClass( 'is-active' );
		$modal.find( '.mrn-shared-icon-tab[data-tab="' + tab + '"]' ).addClass( 'is-active' );
		$modal.find( '.mrn-shared-icon-panel' ).removeClass( 'is-active' );
		$modal.find( '.mrn-shared-icon-panel[data-panel="' + tab + '"]' ).addClass( 'is-active' );
	}

	function updateImagePreview() {
		var $preview = $modal.find( '.mrn-shared-image-preview' );
		$preview.empty();

		if ( currentRequest && currentRequest.previewUrl ) {
			$preview.append( $( '<img alt="" />' ).attr( 'src', currentRequest.previewUrl ) );
		}
	}

	function closeModal() {
		currentRequest = null;
		ensureModal().hide();
	}

	function openModal( request ) {
		currentRequest = $.extend(
			{
				current: { type: 'dashicons', value: '' },
				previewUrl: '',
				onSelect: function () {},
				onClear: function () {}
			},
			request || {}
		);

		ensureModal();
		faStyle = availableFaStyles()[ 0 ] || 'solid';
		if ( currentRequest.current && 'fontawesome' === currentRequest.current.type && currentRequest.current.value ) {
			faStyle = detectStyleFromIconValue( currentRequest.current.value );
		}
		renderFontAwesomeStyleTabs();
		$modal.find( '.mrn-shared-icon-search, .mrn-shared-fa-search' ).val( '' );
		renderFontAwesome();
		renderFontAwesomeRegistrationStatus( null );
		updateImagePreview();
		activateTab( currentRequest.current && currentRequest.current.type ? currentRequest.current.type : 'dashicons' );
		$modal.show();
	}

	function chooseImage() {
		var frame;

		if ( typeof wp === 'undefined' || ! wp.media || ! currentRequest ) {
			return;
		}

		frame = wp.media( {
			title: text( 'selectImage', 'Select Icon Image' ),
			button: { text: text( 'useImage', 'Use this image' ) },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();

			if ( attachment && attachment.id ) {
				currentRequest.onSelect( {
					type: 'media',
					value: attachment.url || '',
					attachment: attachment
				} );
				closeModal();
			}
		} );

		frame.open();
	}

	$( document ).on( 'click', '.mrn-shared-icon-close', closeModal );

	$( document ).on( 'click', '.mrn-shared-icon-modal', function ( event ) {
		if ( $( event.target ).is( '.mrn-shared-icon-modal' ) ) {
			closeModal();
		}
	} );

	$( document ).on( 'click', '.mrn-shared-icon-tab', function () {
		activateTab( $( this ).data( 'tab' ) || 'dashicons' );
	} );

	$( document ).on( 'click', '.mrn-shared-fa-tab', function () {
		faStyle = String( $( this ).data( 'style' ) || '' ).toLowerCase();
		if ( -1 === availableFaStyles().indexOf( faStyle ) ) {
			faStyle = availableFaStyles()[ 0 ] || 'solid';
		}
		$modal.find( '.mrn-shared-fa-tab' ).removeClass( 'is-active' );
		$( this ).addClass( 'is-active' );
		renderFontAwesome();
	} );

	$( document ).on( 'input', '.mrn-shared-fa-search', renderFontAwesome );

	$( document ).on( 'input', '.mrn-shared-icon-search', function () {
		var query = String( $( this ).val() || '' ).trim().toLowerCase();
		$modal.find( '.mrn-shared-icon-grid__item' ).each( function () {
			var title = String( $( this ).attr( 'title' ) || '' ).toLowerCase();
			$( this ).toggle( -1 !== title.indexOf( query ) );
		} );
	} );

	$( document ).on( 'click', '.mrn-shared-icon-grid__item, .mrn-shared-fa-grid__item', function () {
		if ( ! currentRequest ) {
			return;
		}

		if ( 'fontawesome' === $( this ).data( 'type' ) ) {
			registerFontawesomeSelection( $( this ).data( 'value' ) || '' );
		}

		currentRequest.onSelect( {
			type: $( this ).data( 'type' ) || 'dashicons',
			value: $( this ).data( 'value' ) || ''
		} );
		closeModal();
	} );

	$( document ).on( 'click', '.mrn-shared-icon-clear', function () {
		if ( currentRequest ) {
			currentRequest.onClear();
		}
		closeModal();
	} );

	$( document ).on( 'click', '.mrn-shared-image-select', chooseImage );

	window.MRNSharedIconChooser = {
		open: openModal,
		close: closeModal,
		registerFontawesomeSelection: registerFontawesomeSelection
	};
}( jQuery, window ) );
