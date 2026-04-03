( function( $, acf, window ) {
	'use strict';

	if ( typeof acf === 'undefined' || typeof mrnBaseStackBuilderAdmin === 'undefined' ) {
		return;
	}

	var config = mrnBaseStackBuilderAdmin;

	function getMenuDecorations() {
		return $.isArray( config.menuDecorations ) ? config.menuDecorations : [];
	}

	function getHiddenLayouts() {
		var hiddenLayouts = $.isArray( config.hiddenLayouts ) ? config.hiddenLayouts.slice() : [];
		var disabledLayouts = $.isArray( config.disabledLayouts ) ? config.disabledLayouts : [];

		$.each( disabledLayouts, function( index, layoutName ) {
			if ( hiddenLayouts.indexOf( layoutName ) === -1 ) {
				hiddenLayouts.push( layoutName );
			}
		} );

		return hiddenLayouts;
	}

	function getContentListTaxonomyMap() {
		if ( config.contentListTaxonomies && typeof config.contentListTaxonomies === 'object' ) {
			return config.contentListTaxonomies;
		}

		return {};
	}

	function hideFlexibleContentLayouts( context ) {
		var hiddenLayouts = getHiddenLayouts();

		if ( ! hiddenLayouts.length ) {
			return;
		}

		$( context || document ).find( 'li [data-layout]' ).each( function() {
			var $link = $( this );
			var layoutName = $link.attr( 'data-layout' ) || '';
			var $item = $link.closest( 'li' );

			if ( hiddenLayouts.indexOf( layoutName ) === -1 || ! $item.length ) {
				return;
			}

			$item.hide();
		} );
	}

	function decorateFlexibleContentMenus( context ) {
		var decorations = getMenuDecorations();

		if ( ! decorations.length ) {
			return;
		}

		$( context || document ).find( 'li [data-layout]' ).each( function() {
			var $link = $( this );
			var $item = $link.closest( 'li' );
			var layoutName = $link.attr( 'data-layout' ) || '';

			if ( ! $item.length ) {
				return;
			}

			$.each( decorations, function( index, decoration ) {
				var identifier;
				var $header;

				if ( layoutName !== decoration.beforeLayout ) {
					return;
				}

				identifier = decoration.styleIdentifier || ( decoration.beforeLayout + '-' + index );

				if ( $item.attr( 'data-mrn-decoration' ) === identifier || $item.prev( '.mrn-builder-menu-header[data-mrn-decoration="' + identifier + '"]' ).length ) {
					return;
				}

				$item.attr( 'data-mrn-decoration', identifier );

				$header = $( '<li class="mrn-builder-menu-header" aria-hidden="true"></li>' );
				$header.attr( 'data-mrn-decoration', identifier );
				$header.text( decoration.label || '' );

				$item.before( $header );
			} );
		} );
	}

	function getRowActionWrap( $row ) {
		var $target = $row.find( '.acf-fc-layout-controls, .acf-fc-layout-actions, .acf-fc-layout-controlls' ).first();

		if ( $target.length ) {
			return $target;
		}

		var $moreAction = $row.find( '[data-name="more-layout-actions"]' ).first();
		if ( $moreAction.length ) {
			return $moreAction.parent();
		}

		return $();
	}

	function ensureConversionActions( context ) {
		$( context || document ).find( '.layout' ).filter( function() {
			var $row = $( this );
			var $flexField = $row.closest( '.acf-field-flexible-content' );

			return $row.find( '.acf-field[data-name="block"]' ).length > 0 &&
				$flexField.length &&
				$pageContentRowsFieldName( $flexField );
		} ).each( function() {
			var $row = $( this );
			var $wrap = getRowActionWrap( $row );

			if ( ! $wrap.length || $wrap.find( '.mrn-convert-reusable-block-action' ).length ) {
				return;
			}

			var $icon = $(
				'<a href="#" class="mrn-convert-reusable-block-action acf-js-tooltip" data-name="mrn-convert-reusable-block-action" title="' + config.actionTitle + '" aria-label="' + config.actionTitle + '">' +
					'<span class="dashicons dashicons-randomize" aria-hidden="true"></span>' +
				'</a>'
			);

			var $moreAction = $wrap.find( '[data-name="more-layout-actions"]' ).first();
			if ( $moreAction.length ) {
				$moreAction.before( $icon );
				return;
			}

			$wrap.append( $icon );
		} );
	}

	function $pageContentRowsFieldName( $flexField ) {
		return $flexField.attr( 'data-name' ) === 'page_content_rows';
	}

	function bootBuilderAdminUi( context ) {
		hideFlexibleContentLayouts( context );
		ensureConversionActions( context );
		decorateFlexibleContentMenus( context );
		syncContentListFilters( context );
	}

	function getContentListField( $row, name ) {
		return $row.find( '.acf-field[data-name="' + name + '"]' ).first();
	}

	function getContentListSelect( $field ) {
		if ( ! $field || ! $field.length ) {
			return $();
		}

		return $field.find( 'select' ).first();
	}

	function updateSelectUi( $select ) {
		if ( ! $select.length ) {
			return;
		}

		$select.trigger( 'change' );
	}

	function syncContentListFilters( context ) {
		$( context || document ).find( '.layout[data-layout="content_lists"]' ).each( function() {
			var $row = $( this );
			var $postTypeField = getContentListField( $row, 'list_post_type' );
			var $taxonomyField = getContentListField( $row, 'filter_taxonomy' );
			var $termsField = getContentListField( $row, 'filter_terms' );
			var $postTypeSelect = getContentListSelect( $postTypeField );
			var $taxonomySelect = getContentListSelect( $taxonomyField );
			var $termsSelect = getContentListSelect( $termsField );
			var postType = $postTypeSelect.val() || '';
			var taxonomy = $taxonomySelect.val() || '';
			var taxonomyMap = getContentListTaxonomyMap();
			var allowedTaxonomies = taxonomyMap[ postType ] || {};

			if ( $taxonomySelect.length ) {
				$taxonomySelect.find( 'option' ).each( function() {
					var $option = $( this );
					var value = $option.attr( 'value' ) || '';
					var shouldShow = ! value || Object.prototype.hasOwnProperty.call( allowedTaxonomies, value );

					$option.prop( 'disabled', ! shouldShow );
					$option.toggle( shouldShow );
				} );

				if ( taxonomy && ! Object.prototype.hasOwnProperty.call( allowedTaxonomies, taxonomy ) ) {
					taxonomy = '';
					$taxonomySelect.val( '' );
				}

				if ( ! taxonomy ) {
					var firstTaxonomy = Object.keys( allowedTaxonomies )[0] || '';
					if ( firstTaxonomy ) {
						taxonomy = firstTaxonomy;
						$taxonomySelect.val( firstTaxonomy );
					}
				}

				updateSelectUi( $taxonomySelect );
			}

			if ( $termsSelect.length ) {
				var selectedTerms = $termsSelect.val();

				if ( ! $.isArray( selectedTerms ) ) {
					selectedTerms = selectedTerms ? [ selectedTerms ] : [];
				}

				$termsSelect.find( 'option' ).each( function() {
					var $option = $( this );
					var value = $option.attr( 'value' ) || '';
					var termTaxonomy = value.split( ':' )[0] || '';
					var shouldShow = ! taxonomy || termTaxonomy === taxonomy;

					$option.prop( 'disabled', ! shouldShow );
					$option.toggle( shouldShow );
				} );

				selectedTerms = $.grep( selectedTerms, function( value ) {
					return ! taxonomy || ( value.split( ':' )[0] || '' ) === taxonomy;
				} );

				$termsSelect.val( selectedTerms );
				updateSelectUi( $termsSelect );
			}
		} );
	}

	function collapseInitialFlexibleRows( context ) {
		$( context || document ).find( '.acf-field-flexible-content' ).each( function() {
			var $flexField = $( this );

			if ( $flexField.data( 'mrn-initial-collapse-done' ) ) {
				return;
			}

			$flexField.data( 'mrn-initial-collapse-done', true );

			getRows( $flexField ).each( function() {
				var $row = $( this );
				var $toggle;

				if ( $row.hasClass( '-collapsed' ) || $row.hasClass( 'collapsed' ) ) {
					return;
				}

				$toggle = $row.find( '> .acf-fc-layout-controls .acf-icon.-collapse, > .acf-fc-layout-actions .acf-icon.-collapse, .acf-fc-layout-controls .acf-icon.-collapse, .acf-fc-layout-actions .acf-icon.-collapse' ).first();

				if ( ! $toggle.length ) {
					$toggle = $row.find( '> .acf-fc-layout-handle .acf-icon.-collapse, .acf-fc-layout-handle .acf-icon.-collapse' ).first();
				}

				if ( ! $toggle.length ) {
					$toggle = $row.children( '.acf-fc-layout-handle' ).first();
				}

				if ( $toggle.length ) {
					$toggle.trigger( 'click' );
				}
			} );
		} );
	}

	function getRows( $flexField ) {
		var $values = $flexField.find( '> .acf-input > .acf-flexible-content > .values, > .acf-input > .values' ).first();

		return $values.children( '.layout' ).not( '.acf-clone' );
	}

	function getRowIds( $flexField ) {
		return getRows( $flexField ).map( function() {
			return $( this ).attr( 'data-id' ) || '';
		} ).get();
	}

	function findNewRow( $flexField, originalIds, layoutName, $currentRow ) {
		var $rows = getRows( $flexField );
		var $newRow = $rows.filter( function() {
			var rowId = $( this ).attr( 'data-id' ) || '';
			return originalIds.indexOf( rowId ) === -1;
		} ).first();

		if ( $newRow.length ) {
			return $newRow;
		}

		if ( $currentRow && $currentRow.length ) {
			$newRow = $currentRow.prevAll( '.layout[data-layout="' + layoutName + '"]' ).first();
			if ( $newRow.length ) {
				return $newRow;
			}
		}

		return $rows.filter( '[data-layout="' + layoutName + '"]' ).last();
	}

	function showNotice( text, type ) {
		if ( typeof acf.newNotice === 'function' ) {
			acf.newNotice( {
				text: text,
				type: type || 'success',
				target: $( '.wrap' ).first(),
				location: 'prepend'
			} );
			return;
		}

		window.alert( text );
	}

	function confirmConversion( $button, onConfirm ) {
		if ( typeof acf.newPopup === 'function' ) {
			acf.newPopup( {
				confirmRemove: true,
				title: config.confirmTitle || 'Replace With Page-Specific Copy',
				text: config.confirmText,
				textConfirm: config.confirmButton,
				textCancel: config.cancelButton,
				openedBy: $button,
				width: '500px',
				confirm: onConfirm
			} );
			return;
		}

		if ( window.confirm( config.confirmText ) ) {
			onConfirm();
		}
	}

	function getSelectedBlockId( $row ) {
		var blockField = acf.getField( $row.find( '.acf-field[data-name="block"]' ).first() );
		var value = blockField && typeof blockField.val === 'function' ? blockField.val() : null;

		if ( $.isArray( value ) ) {
			value = value[0];
		}

		if ( value && ! isNaN( value ) ) {
			return parseInt( value, 10 );
		}

		var rawValue = $row.find( '.acf-field[data-name="block"] input[type="hidden"], .acf-field[data-name="block"] select' ).first().val();
		if ( rawValue && ! isNaN( rawValue ) ) {
			return parseInt( rawValue, 10 );
		}

		return 0;
	}

	function normalizeMediaValue( value ) {
		if ( value && typeof value === 'object' ) {
			if ( typeof value.ID !== 'undefined' ) {
				return value.ID;
			}

			if ( typeof value.id !== 'undefined' ) {
				return value.id;
			}
		}

		return value;
	}

	function getRepeaterRows( field ) {
		if ( field && typeof field.$rows === 'function' ) {
			return field.$rows();
		}

		return field.$el.find( '> .acf-input > .acf-repeater > .acf-table > .acf-tbody > .acf-row, > .acf-input > .acf-repeater > table > tbody > .acf-row' ).not( '.acf-clone' );
	}

	function setFieldValue( field, value ) {
		if ( ! field ) {
			return;
		}

		var type = field.get( 'type' );

		if ( type === 'repeater' ) {
			populateRepeater( field, value );
			return;
		}

		if ( type === 'image' || type === 'file' || type === 'gallery' ) {
			value = normalizeMediaValue( value );
		}

		if ( typeof field.val === 'function' ) {
			field.val( value );
			return;
		}

		var $input = field.$input();
		if ( ! $input.length ) {
			return;
		}

		if ( $input.is( ':checkbox' ) ) {
			$input.prop( 'checked', !! value ).trigger( 'change' );
			return;
		}

		$input.val( value ).trigger( 'change' );
	}

	function populateContainer( $container, values ) {
		$.each( values || {}, function( name, value ) {
			var $field = $container.find( '.acf-field[data-name="' + name + '"]' ).first();

			if ( ! $field.length ) {
				return;
			}

			setFieldValue( acf.getField( $field ), value );
		} );
	}

	function populateRepeater( field, rows ) {
		rows = $.isArray( rows ) ? rows : [];

		if ( ! rows.length ) {
			return;
		}

		var existingCount = getRepeaterRows( field ).length;

		while ( existingCount < rows.length && typeof field.add === 'function' ) {
			field.add();
			existingCount += 1;
		}

		getRepeaterRows( field ).each( function( index ) {
			if ( typeof rows[ index ] === 'undefined' ) {
				return false;
			}

			populateContainer( $( this ), rows[ index ] );
		} );
	}

	function removeRow( flexibleField, $row, $newRow ) {
		if ( flexibleField && typeof flexibleField.removeLayout === 'function' ) {
			flexibleField.removeLayout( $row );

			if ( flexibleField && typeof flexibleField.setActiveLayout === 'function' && $newRow && $newRow.length ) {
				flexibleField.setActiveLayout( $newRow );
			}

			return;
		}

		if ( typeof acf.remove === 'function' ) {
			acf.remove( {
				target: $row,
				endHeight: 0,
				complete: function() {
					if ( flexibleField && typeof flexibleField.$input === 'function' ) {
						flexibleField.$input().trigger( 'change' );
					}

					if ( flexibleField && typeof flexibleField.render === 'function' ) {
						flexibleField.render();
					}

					if ( flexibleField && typeof flexibleField.setActiveLayout === 'function' && $newRow && $newRow.length ) {
						flexibleField.setActiveLayout( $newRow );
					}
				}
			} );
			return;
		}

		$row.remove();
	}

	function convertRow( $row, payload ) {
		var $flexField = $row.closest( '.acf-field-flexible-content' );
		var flexibleField = acf.getField( $flexField );
		var originalIds = getRowIds( $flexField );

		if ( ! flexibleField || typeof flexibleField.add !== 'function' ) {
			showNotice( config.errorText, 'error' );
			return;
		}

		flexibleField.add( {
			layout: payload.layout,
			before: $row
		} );

		window.setTimeout( function() {
			var $newRow = findNewRow( $flexField, originalIds, payload.layout, $row );

			if ( ! $newRow.length ) {
				showNotice( config.errorText, 'error' );
				return;
			}

			populateContainer( $newRow, payload.fields );
			removeRow( flexibleField, $row, $newRow );
			showNotice( config.successText, 'success' );
			$newRow.find( '.acf-fc-layout-handle' ).first().trigger( 'focus' );
		}, 80 );
	}

	$( function() {
		bootBuilderAdminUi( document );
		window.setTimeout( function() {
			collapseInitialFlexibleRows( document );
		}, 40 );
	} );

	acf.addAction( 'ready', function( $el ) {
		bootBuilderAdminUi( $el || document );
	} );

	acf.addAction( 'append', function( $el ) {
		bootBuilderAdminUi( $el || document );
	} );

	$( document ).on( 'click', '[data-name="add-layout"]', function() {
		window.setTimeout( function() {
			hideFlexibleContentLayouts( document );
			decorateFlexibleContentMenus( document );
		}, 40 );
	} );

	$( document ).on( 'change', '.layout[data-layout="content_lists"] .acf-field[data-name="list_post_type"] select, .layout[data-layout="content_lists"] .acf-field[data-name="filter_taxonomy"] select', function() {
		syncContentListFilters( $( this ).closest( '.layout[data-layout="content_lists"]' ) );
	} );

	$( document ).on( 'click', '.mrn-convert-reusable-block, .mrn-convert-reusable-block-action', function( event ) {
		event.preventDefault();

		var $button = $( this );
		var $row = $button.closest( '.layout[data-layout="reusable_block"]' );
		var blockId = getSelectedBlockId( $row );

		if ( ! blockId ) {
			showNotice( config.emptySelectionText, 'error' );
			return;
		}

		confirmConversion( $button, function() {
			$button.prop( 'disabled', true ).addClass( 'is-busy' ).text( config.loadingText );

			$.ajax( {
				url: config.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: config.action,
					nonce: config.nonce,
					block_id: blockId
				}
			} ).done( function( response ) {
				if ( ! response || ! response.success || ! response.data ) {
					showNotice( response && response.data && response.data.message ? response.data.message : config.errorText, 'error' );
					return;
				}

				convertRow( $row, response.data );
			} ).fail( function( xhr ) {
				var message = config.errorText;

				if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
					message = xhr.responseJSON.data.message;
				}

				showNotice( message, 'error' );
			} ).always( function() {
				$button.prop( 'disabled', false ).removeClass( 'is-busy' ).text( config.confirmButton );
			} );
		} );
	} );
} )( jQuery, window.acf || null, window );
