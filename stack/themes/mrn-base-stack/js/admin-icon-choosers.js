( function ( $, window ) {
	function getFieldObject( $field ) {
		if ( ! $field || ! $field.length || typeof acf === 'undefined' || typeof acf.getField !== 'function' ) {
			return null;
		}

		return acf.getField( $field ) || null;
	}

	function setFieldValue( $field, value ) {
		var fieldObject = getFieldObject( $field );

		if ( fieldObject && typeof fieldObject.val === 'function' ) {
			fieldObject.val( value );
		} else if ( $field.is( '.acf-field-button-group' ) ) {
			$field.find( 'input[type="radio"][value="' + value + '"]' ).prop( 'checked', true ).trigger( 'change' );
		} else {
			$field.find( 'select, input[type="hidden"], input[type="text"]' ).first().val( value ).trigger( 'change' );
		}
	}

	function getFieldValue( $field ) {
		var fieldObject = getFieldObject( $field );

		if ( fieldObject && typeof fieldObject.val === 'function' ) {
			return fieldObject.val() || '';
		}

		if ( $field.is( '.acf-field-button-group' ) ) {
			return $field.find( 'input[type="radio"]:checked' ).val() || $field.find( 'input[type="hidden"]' ).val() || '';
		}

		return $field.find( 'select, input[type="hidden"], input[type="text"]' ).first().val() || '';
	}

	function groupAllowsEmpty( group ) {
		return !! ( group && group.source && group.source.hasClass( 'mrn-icon-chooser-field--allow-empty' ) );
	}

	function normalizeDashiconClass( rawValue ) {
		var value = String( rawValue || '' ).trim();
		var match;

		if ( ! value || 'dashicons' === value.toLowerCase() ) {
			return '';
		}

		match = value.match( /dashicons-[a-z0-9-]+/i );
		if ( match && match[ 0 ] ) {
			value = match[ 0 ].toLowerCase();
		}

		if ( 0 !== value.indexOf( 'dashicons-' ) ) {
			value = 'dashicons-' + value.replace( /^dashicons[\s_-]*/i, '' );
		}

		value = value.toLowerCase();

		if ( 'dashicons-' === value || 'dashicons-dashicons' === value ) {
			return '';
		}

		return value;
	}

	function getImageFieldAttachmentId( $field ) {
		return parseInt( $field.find( '.acf-image-uploader input[type="hidden"]' ).first().val() || '0', 10 ) || 0;
	}

	function getImageFieldPreviewUrl( $field ) {
		return $field.find( '.acf-image-uploader .image-wrap img' ).attr( 'src' ) || '';
	}

	function setImageFieldValue( $field, attachment ) {
		var fieldObject = getFieldObject( $field );
		var $uploader = $field.find( '.acf-image-uploader' ).first();
		var $hidden = $uploader.find( 'input[type="hidden"]' ).first();
		var previewUrl = attachment.url || '';

		if ( attachment.sizes ) {
			previewUrl = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : previewUrl;
			previewUrl = attachment.sizes.medium ? attachment.sizes.medium.url : previewUrl;
		}

		if ( fieldObject && typeof fieldObject.val === 'function' ) {
			fieldObject.val( attachment.id );
		}

		$hidden.val( attachment.id ).trigger( 'change' );

		if ( $uploader.length ) {
			$uploader.addClass( 'has-value' );
			$uploader.find( '.hide-if-value' ).hide();
			$uploader.find( '.show-if-value' ).show();
			$uploader.find( '.image-wrap img' ).attr( 'src', previewUrl );
		}
	}

	function clearImageFieldValue( $field ) {
		var fieldObject = getFieldObject( $field );
		var $uploader = $field.find( '.acf-image-uploader' ).first();
		var $hidden = $uploader.find( 'input[type="hidden"]' ).first();

		if ( fieldObject && typeof fieldObject.val === 'function' ) {
			fieldObject.val( '' );
		}

		$hidden.val( '' ).trigger( 'change' );

		if ( $uploader.length ) {
			$uploader.removeClass( 'has-value' );
			$uploader.find( '.hide-if-value' ).show();
			$uploader.find( '.show-if-value' ).hide();
			$uploader.find( '.image-wrap img' ).attr( 'src', '' );
		}
	}

	function renderInlinePreview( $preview, selection ) {
		$preview.empty();

		if ( ! selection ) {
			return;
		}

		if ( 'media' === selection.type && selection.value ) {
			$preview.append( $( '<img alt="" />' ).attr( 'src', selection.value ) );
			return;
		}

		if ( 'fontawesome' === selection.type && selection.value ) {
			$preview.append( $( '<span aria-hidden="true"></span>' ).addClass( selection.value ) );
			return;
		}

		if ( selection.value ) {
			$preview.append( $( '<span aria-hidden="true"></span>' ).addClass( 'dashicons ' + selection.value ) );
		}
	}

	function getChooserGroups( context ) {
		var $context = $( context || document );
		var groups = [];

		$context.find( '.mrn-icon-chooser-field--source' ).each( function () {
			var $source = $( this );
			var $fields = $source.closest( '.acf-fields' );
			var group = {
				source: $source,
				dashicons: $fields.find( '.mrn-icon-chooser-field--dashicons' ).first(),
				fontawesome: $fields.find( '.mrn-icon-chooser-field--fontawesome' ).first(),
				media: $fields.find( '.mrn-icon-chooser-field--media' ).first()
			};

			if ( group.dashicons.length && group.fontawesome.length && group.media.length ) {
				groups.push( group );
			}
		} );

		return groups;
	}

	function normalizeSelection( group ) {
		var source = getFieldValue( group.source );
		var dashicon = normalizeDashiconClass( getFieldValue( group.dashicons ) || '' );
		var fontAwesomeClass = getFieldValue( group.fontawesome ) || '';
		var imageId = getImageFieldAttachmentId( group.media );
		var imageUrl = getImageFieldPreviewUrl( group.media );
		var allowEmpty = groupAllowsEmpty( group );

		if ( 'media' === source && ( imageId || imageUrl ) ) {
			return {
				type: 'image',
				value: imageUrl,
				label: imageUrl ? imageUrl.split( '/' ).pop() : 'Image'
			};
		}

		if ( ! source && imageId ) {
			return {
				type: 'image',
				value: imageUrl,
				label: imageUrl ? imageUrl.split( '/' ).pop() : 'Image'
			};
		}

		if ( 'fontawesome' === source && fontAwesomeClass ) {
			return {
				type: 'fontawesome',
				value: fontAwesomeClass,
				label: fontAwesomeClass
			};
		}

		if ( ! source && fontAwesomeClass ) {
			return {
				type: 'fontawesome',
				value: fontAwesomeClass,
				label: fontAwesomeClass
			};
		}

		if ( 'dashicons' === source && dashicon ) {
			return {
				type: 'dashicons',
				value: dashicon,
				label: dashicon
			};
		}

		if ( ! source && dashicon ) {
			return {
				type: 'dashicons',
				value: dashicon,
				label: dashicon
			};
		}

		if ( allowEmpty ) {
			return null;
		}

		return {
			type: 'dashicons',
			value: dashicon || 'dashicons-search',
			label: dashicon || 'dashicons-search'
		};
	}

	function updateChooserDisplay( group ) {
		var selection = normalizeSelection( group );
		var $control = group.source.find( '.mrn-icon-chooser-control' );

		if ( ! $control.length ) {
			return;
		}

		renderInlinePreview( $control.find( '.mrn-icon-chooser-preview' ), selection );
		$control.find( '.mrn-icon-chooser-selection' ).text( selection && selection.label ? selection.label : 'No icon selected' );
	}

	function hideStorageFields( group ) {
		group.source.addClass( 'mrn-icon-chooser-is-enhanced' );
		group.dashicons.addClass( 'mrn-icon-chooser-storage' );
		group.fontawesome.addClass( 'mrn-icon-chooser-storage' );
		group.media.addClass( 'mrn-icon-chooser-storage' );
	}

	function openChooser( group ) {
		if ( ! window.MRNSharedIconChooser || typeof window.MRNSharedIconChooser.open !== 'function' ) {
			return;
		}

		window.MRNSharedIconChooser.open( {
			current: normalizeSelection( group ),
			previewUrl: getImageFieldPreviewUrl( group.media ),
			onSelect: function ( selection ) {
				if ( 'media' === selection.type ) {
					setFieldValue( group.source, 'media' );
					if ( selection.attachment ) {
						setImageFieldValue( group.media, selection.attachment );
					}
				} else if ( 'fontawesome' === selection.type ) {
					setFieldValue( group.source, 'fontawesome' );
					setFieldValue( group.fontawesome, selection.value );
				} else {
					setFieldValue( group.source, 'dashicons' );
					setFieldValue( group.dashicons, selection.value );
				}

				updateChooserDisplay( group );
			},
			onClear: function () {
				if ( groupAllowsEmpty( group ) ) {
					setFieldValue( group.source, '' );
					setFieldValue( group.dashicons, '' );
					setFieldValue( group.fontawesome, '' );
					clearImageFieldValue( group.media );
				} else {
					setFieldValue( group.source, 'dashicons' );
					setFieldValue( group.dashicons, 'dashicons-search' );
				}
				updateChooserDisplay( group );
			}
		} );
	}

	function addChooserControl( group ) {
		var $input = group.source.find( '.acf-input' ).first();
		var $control;

		if ( ! $input.length || $input.find( '.mrn-icon-chooser-control' ).length ) {
			return;
		}

		$control = $(
			'<div class="mrn-icon-chooser-control">' +
				'<button type="button" class="button mrn-icon-chooser-open">Choose Icon</button>' +
				'<span class="mrn-icon-chooser-preview"></span>' +
				'<span class="mrn-icon-chooser-selection"></span>' +
			'</div>'
		);

		$control.data( 'mrnIconChooserGroup', group );
		$input.prepend( $control );
		hideStorageFields( group );
		updateChooserDisplay( group );
	}

	function initChooserFields( context ) {
		getChooserGroups( context ).forEach( function ( group ) {
			addChooserControl( group );
		} );
	}

	$( document ).on( 'click', '.mrn-icon-chooser-open', function () {
		var group = $( this ).closest( '.mrn-icon-chooser-control' ).data( 'mrnIconChooserGroup' );

		if ( group ) {
			openChooser( group );
		}
	} );

	if ( typeof acf !== 'undefined' ) {
		acf.addAction( 'ready', initChooserFields );
		acf.addAction( 'append', initChooserFields );
	}

	$( initChooserFields );
}( jQuery, window ) );
