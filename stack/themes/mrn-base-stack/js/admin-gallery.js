( function ( $, acf ) {
	'use strict';

	if ( typeof $ === 'undefined' || typeof acf === 'undefined' ) {
		return;
	}

	function getRow( element ) {
		return $( element ).closest( '.acf-row' );
	}

	function getField( $row, name ) {
		return $row.find( '.acf-field[data-name="' + name + '"]' ).first();
	}

	function getCheckedType( $row ) {
		return getField( $row, 'media_type' ).find( 'input[type="radio"]:checked' ).val() || 'image';
	}

	function getImageValue( $row, fieldName ) {
		return $.trim( getField( $row, fieldName ).find( 'input[type="hidden"]' ).first().val() || '' );
	}

	function getMediaUrlValue( $row ) {
		return $.trim( getField( $row, 'media_url' ).find( 'input[type="url"]' ).first().val() || '' );
	}

	function clearImageField( $row, fieldName ) {
		var $field = getField( $row, fieldName );
		var $remove = $field.find( 'a[data-name="remove"], .acf-icon.-cancel' ).first();
		var $hidden = $field.find( 'input[type="hidden"]' ).first();

		if ( ! $field.length ) {
			return;
		}

		if ( $remove.length && $.trim( $hidden.val() || '' ) !== '' ) {
			$remove.trigger( 'click' );
			return;
		}

		$hidden.val( '' ).trigger( 'change' );
	}

	function clearMediaUrlField( $row ) {
		var $input = getField( $row, 'media_url' ).find( 'input[type="url"]' ).first();

		if ( $input.length ) {
			$input.val( '' ).trigger( 'change' );
		}
	}

	function setTrueFalseFieldValue( $row, fieldName, enabled ) {
		var $field = getField( $row, fieldName );
		var $checkbox = $field.find( 'input[type="checkbox"]' ).first();
		var $hidden = $field.find( 'input[type="hidden"]' ).first();

		if ( $checkbox.length ) {
			$checkbox.prop( 'checked', !! enabled ).trigger( 'change' );
		}

		if ( $hidden.length ) {
			$hidden.val( enabled ? '1' : '0' );
		}
	}

	function getHelper( $row ) {
		return getField( $row, 'media_type' ).find( '.mrn-gallery-item-type-help' ).first();
	}

	function ensureHelper( $row ) {
		var $field = getField( $row, 'media_type' );
		var $input = $field.find( '.acf-input' ).first();
		var fallbackText = $.trim( $field.find( '.acf-label .description' ).first().text() || '' );
		var $helper = getHelper( $row );

		if ( ! $input.length ) {
			return $();
		}

		if ( ! $helper.length ) {
			$helper = $( '<span class="mrn-gallery-item-type-help" />' ).text(
				fallbackText || 'Clear existing media before switching this item to a different media type.'
			);
			$input.css( {
				display: 'flex',
				alignItems: 'flex-start',
				gap: '0.75rem',
				flexWrap: 'wrap'
			} );
			$input.append( $helper );
		}

		return $helper;
	}

	function setNotice( $row, message ) {
		var $helper = ensureHelper( $row );
		if ( ! $helper.length ) {
			return;
		}

		$helper.text( message ).css( {
			fontSize: '12px',
			lineHeight: '1.4',
			color: '#50575e',
			marginLeft: '0',
			maxWidth: 'none',
			whiteSpace: 'normal',
			flex: '1 0 100%'
		} );
	}

	function setChoiceState( $choice, disabled ) {
		$choice.toggleClass( 'is-disabled', disabled );
		$choice.attr( 'aria-disabled', disabled ? 'true' : 'false' );
		$choice.css( {
			opacity: disabled ? '0.45' : '',
			cursor: disabled ? 'not-allowed' : '',
			pointerEvents: disabled ? 'none' : ''
		} );
		$choice.find( 'input[type="radio"]' ).prop( 'disabled', disabled );
	}

	function getGalleryRepeaterFields( context ) {
		return $( context || document ).find( '.acf-field[data-name="gallery_items"]' );
	}

	function getGalleryRows( $field ) {
		return $field.find( '> .acf-input > .acf-repeater > .acf-table > tbody > .acf-row, > .acf-input > .acf-repeater > .values > .acf-row' ).not( '.acf-clone' );
	}

	function getRowCollapseToggle( $row ) {
		var $toggle = $row.find( '> .acf-row-handle .acf-icon.-collapse, > .acf-row-handle.order .acf-icon.-collapse, > .acf-row-handle .acf-js-tooltip' ).first();

		if ( ! $toggle.length ) {
			$toggle = $row.find( '> .acf-row-handle, > .acf-row-handle.order' ).first();
		}

		return $toggle;
	}

	function isRowCollapsed( $row ) {
		return $row.hasClass( '-collapsed' ) || $row.hasClass( 'collapsed' );
	}

	function setRowCollapsed( $row, collapsed ) {
		var $toggle;

		if ( isRowCollapsed( $row ) === collapsed ) {
			return;
		}

		$toggle = getRowCollapseToggle( $row );
		if ( $toggle.length ) {
			$toggle.trigger( 'click' );
		}
	}

	function ensureGalleryToolbar( $field ) {
		var $label = $field.children( '.acf-label' ).first();
		var $existing = $label.find( '.mrn-gallery-items-toolbar' ).first();
		var $toolbar;

		if ( ! $label.length ) {
			return $();
		}

		if ( $existing.length ) {
			return $existing;
		}

		$toolbar = $(
			'<div class="mrn-gallery-items-toolbar">' +
				'<button type="button" class="button button-secondary" data-gallery-collapse-all="true">Collapse All</button> ' +
				'<button type="button" class="button button-secondary" data-gallery-expand-all="true">Expand All</button>' +
			'</div>'
		);

		$toolbar.css( {
			display: 'flex',
			gap: '0.5rem',
			marginTop: '0.5rem',
			marginBottom: '0.25rem'
		} );

		$label.append( $toolbar );

		return $toolbar;
	}

	function refreshGalleryToolbars( context ) {
		getGalleryRepeaterFields( context ).each( function () {
			ensureGalleryToolbar( $( this ) );
		} );
	}

	function refreshRow( $row ) {
		var currentType = getCheckedType( $row );
		var hasImage = getImageValue( $row, 'image' ) !== '';
		var hasPreviewImage = getImageValue( $row, 'preview_image' ) !== '';
		var hasMediaUrl = getMediaUrlValue( $row ) !== '';
		var hasNonImageContent = hasPreviewImage || hasMediaUrl;
		var lockedType = '';
		var message = 'Once a row has media content, clear that content before switching to a different media type.';

		if ( hasImage ) {
			lockedType = 'image';
			message = 'This row is locked to Image. Remove the selected image before switching to Video or External Embed.';
		} else if ( hasNonImageContent ) {
			lockedType = currentType !== 'image' ? currentType : 'video';
			message = 'This row is locked to ' + ( lockedType === 'video' ? 'Video' : 'External Embed' ) + '. Clear the media URL and preview image before switching types.';
		}

		if ( hasImage && hasNonImageContent ) {
			message = 'This row has content for multiple media types. Remove the conflicting media before switching types or saving.';
		}

		getField( $row, 'media_type' ).find( '.acf-button-group label' ).each( function () {
			var $choice = $( this );
			var value = $choice.find( 'input[type="radio"]' ).val() || '';
			var shouldDisable = lockedType !== '' && value !== lockedType;

			setChoiceState( $choice, shouldDisable );
		} );

		setNotice( $row, message );
	}

	function refreshAllRows( context ) {
		$( context || document ).find( '.acf-row' ).each( function () {
			refreshRow( $( this ) );
		} );
		refreshGalleryToolbars( context );
	}

	$( document ).on( 'change keyup', '.acf-field[data-name="media_url"] input[type="url"]', function () {
		refreshRow( getRow( this ) );
	} );

	$( document ).on( 'change', '.acf-field[data-name="image"] input[type="hidden"], .acf-field[data-name="preview_image"] input[type="hidden"]', function () {
		refreshRow( getRow( this ) );
	} );

	$( document ).on( 'click', '.acf-field[data-name="image"] .acf-actions a, .acf-field[data-name="preview_image"] .acf-actions a, .acf-field[data-name="image"] .acf-button, .acf-field[data-name="preview_image"] .acf-button', function () {
		var $row = getRow( this );
		window.setTimeout( function () {
			refreshRow( $row );
		}, 75 );
	} );

	$( document ).on( 'click', '.acf-field[data-name="media_type"] .acf-button-group label', function ( event ) {
		var $choice = $( this );
		var $row = getRow( this );

		if ( $choice.hasClass( 'is-disabled' ) ) {
			event.preventDefault();
			event.stopImmediatePropagation();
			refreshRow( $row );
		}
	} );

	$( document ).on( 'change', '.acf-field[data-name="media_type"] input[type="radio"]', function () {
		var $row = getRow( this );
		var selectedType = $( this ).val() || 'image';

		if ( selectedType === 'image' ) {
			clearImageField( $row, 'preview_image' );
			clearMediaUrlField( $row );
			setTrueFalseFieldValue( $row, 'autoplay_thumbnail', false );
		} else {
			clearImageField( $row, 'image' );
		}

		window.setTimeout( function () {
			refreshRow( $row );
		}, 75 );
	} );

	$( document ).on( 'click', '[data-gallery-collapse-all="true"]', function ( event ) {
		var $field;

		event.preventDefault();
		$field = $( this ).closest( '.acf-field[data-name="gallery_items"]' );

		getGalleryRows( $field ).each( function () {
			setRowCollapsed( $( this ), true );
		} );
	} );

	$( document ).on( 'click', '[data-gallery-expand-all="true"]', function ( event ) {
		var $field;

		event.preventDefault();
		$field = $( this ).closest( '.acf-field[data-name="gallery_items"]' );

		getGalleryRows( $field ).each( function () {
			setRowCollapsed( $( this ), false );
		} );
	} );

	acf.addAction( 'ready append', function ( $el ) {
		refreshAllRows( $el );
	} );

	$( function () {
		refreshAllRows( document );
	} );
}( jQuery, window.acf ) );
