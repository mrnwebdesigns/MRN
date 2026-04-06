( function ( $, window ) {
	var config = window.mrnSharedIconChooserData || {};
	var dashicons = Array.isArray( config.dashicons ) ? config.dashicons : [];
	var fontawesome = config.fontawesome && typeof config.fontawesome === 'object' ? config.fontawesome : {};
	var strings = config.strings || {};
	var $modal = $();
	var currentRequest = null;
	var faStyle = 'solid';

	function text( key, fallback ) {
		return Object.prototype.hasOwnProperty.call( strings, key ) ? strings[ key ] : fallback;
	}

	function faList( style ) {
		return Array.isArray( fontawesome[ style ] ) ? fontawesome[ style ] : [];
	}

	function faClass( style, name ) {
		if ( 'brands' === style ) {
			return 'fa-brands fa-' + name;
		}

		if ( 'regular' === style ) {
			return 'fa-regular fa-' + name;
		}

		return 'fa-solid fa-' + name;
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
						'<div class="mrn-shared-fa-tabs">' +
							'<button type="button" class="button mrn-shared-fa-tab is-active" data-style="solid">Solid</button>' +
							'<button type="button" class="button mrn-shared-fa-tab" data-style="regular">Regular</button>' +
							'<button type="button" class="button mrn-shared-fa-tab" data-style="brands">Brands</button>' +
						'</div>' +
						'<input type="text" class="mrn-shared-fa-search" placeholder="' + text( 'searchFontAwesome', 'Search Font Awesome...' ) + '" />' +
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
		renderFontAwesome();
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
		var query = String( $modal.find( '.mrn-shared-fa-search' ).val() || '' ).trim().toLowerCase();
		var frag = document.createDocumentFragment();
		var count = 0;

		$grid.empty();

		faList( faStyle ).forEach( function ( icon ) {
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

			klass = faClass( faStyle, name );
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
		faStyle = 'solid';
		$modal.find( '.mrn-shared-fa-tab' ).removeClass( 'is-active' );
		$modal.find( '.mrn-shared-fa-tab[data-style="solid"]' ).addClass( 'is-active' );
		$modal.find( '.mrn-shared-icon-search, .mrn-shared-fa-search' ).val( '' );
		renderFontAwesome();
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
		faStyle = $( this ).data( 'style' ) || 'solid';
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
		close: closeModal
	};
}( jQuery, window ) );
