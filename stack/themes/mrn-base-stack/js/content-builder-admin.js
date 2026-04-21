( function( $, acf, window ) {
	'use strict';

	if ( typeof acf === 'undefined' || typeof mrnBaseStackBuilderAdmin === 'undefined' ) {
		return;
	}

	var config = mrnBaseStackBuilderAdmin;

	function getContentListTaxonomyMap() {
		if ( config.contentListTaxonomies && typeof config.contentListTaxonomies === 'object' ) {
			return config.contentListTaxonomies;
		}

		return {};
	}

	function getContentListDisplayModeMap() {
		if ( config.contentListDisplayModes && typeof config.contentListDisplayModes === 'object' ) {
			return config.contentListDisplayModes;
		}

		return {};
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
		ensureConversionActions( context );
		scheduleContentListFilterSync( context );
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

	function getObjectKeys( object ) {
		if ( ! object || typeof object !== 'object' ) {
			return [];
		}

		return Object.keys( object );
	}

	function getChoicesSignature( choices ) {
		return getObjectKeys( choices ).map( function( key ) {
			return key + ':' + String( choices[ key ] );
		} ).join( '|' );
	}

	function getCachedTermOptions( $select ) {
		var cached = $select.data( 'mrnTermOptions' );

		if ( cached ) {
			return cached;
		}

		cached = $select.find( 'option' ).map( function() {
			var $option = $( this );
			var value = String( $option.attr( 'value' ) || '' );

			return {
				value: value,
				label: String( $option.text() || '' ),
				taxonomy: value ? ( value.split( ':' )[0] || '' ) : ''
			};
		} ).get();

		$select.data( 'mrnTermOptions', cached );

		return cached;
	}

	function rebuildSelectOptions( $select, choices, selectedValue, options ) {
		var settings = $.extend(
			{
				allowBlank: false,
				blankLabel: ''
			},
			options || {}
		);
		var signature = getChoicesSignature( choices ) + '|blank:' + ( settings.allowBlank ? '1' : '0' ) + ':' + settings.blankLabel;
		var previousSignature = $select.data( 'mrnChoicesSignature' ) || '';
		var isMultiple = $select.prop( 'multiple' );
		var nextValue = selectedValue;
		var fragment;

		if ( previousSignature === signature ) {
			if ( isMultiple ) {
				nextValue = $.isArray( nextValue ) ? nextValue : ( nextValue ? [ nextValue ] : [] );
			}

			$select.val( nextValue );
			return false;
		}

		fragment = document.createDocumentFragment();

		if ( settings.allowBlank ) {
			fragment.appendChild( new window.Option( settings.blankLabel, '' ) );
		}

		$.each( choices, function( value, label ) {
			fragment.appendChild( new window.Option( String( label ), String( value ) ) );
		} );

		$select.empty().append( fragment );
		$select.data( 'mrnChoicesSignature', signature );

		if ( isMultiple ) {
			nextValue = $.isArray( nextValue ) ? nextValue : ( nextValue ? [ nextValue ] : [] );
		}

		$select.val( nextValue );
		return true;
	}

	function setContentListSyncState( $row, isSyncing ) {
		$row.toggleClass( 'mrn-content-list-is-syncing', !! isSyncing );
	}

	function setContentListLegacyPresentationState( $row, useRowSettings ) {
		var legacyFieldNames = [
			'show_featured_image',
			'show_publish_date',
			'show_excerpt',
			'excerpt_length',
			'show_read_more',
			'read_more_label'
		];

		$.each( legacyFieldNames, function( index, fieldName ) {
			var $field = getContentListField( $row, fieldName );

			if ( ! $field.length ) {
				return;
			}

			$field.toggleClass( 'mrn-content-list-legacy-field-disabled', ! useRowSettings );
			$field.attr( 'aria-disabled', useRowSettings ? 'false' : 'true' );
			$field.find( 'input, select, textarea, button, a' ).not( '[type="hidden"]' ).each( function() {
				var $control = $( this );
				var originalTabIndex = $control.data( 'mrnOriginalTabIndex' );

				if ( ! useRowSettings ) {
					if ( typeof originalTabIndex === 'undefined' ) {
						$control.data( 'mrnOriginalTabIndex', $control.attr( 'tabindex' ) );
					}

					$control.attr( 'tabindex', '-1' );
					return;
				}

				if ( typeof originalTabIndex !== 'undefined' ) {
					if ( false === originalTabIndex || null === originalTabIndex || '' === originalTabIndex ) {
						$control.removeAttr( 'tabindex' );
					} else {
						$control.attr( 'tabindex', originalTabIndex );
					}

					$control.removeData( 'mrnOriginalTabIndex' );
				} else {
					$control.removeAttr( 'tabindex' );
				}
			} );
		} );
	}

	function refreshContentListLegacyPresentationState( context ) {
		$( context || document ).filter( '.layout[data-layout="content_lists"]' ).add( $( context || document ).find( '.layout[data-layout="content_lists"]' ) ).not( '.acf-clone' ).each( function() {
			var $row = $( this );
			var $displayModeField = getContentListField( $row, 'display_mode' );
			var $displayModeSelect = getContentListSelect( $displayModeField );
			var displayMode = $displayModeSelect.val() || '';

			setContentListLegacyPresentationState( $row, '' === displayMode );
		} );
	}

	function updateSelectUi( $field ) {
		var $select = getContentListSelect( $field );

		if ( ! $field || ! $field.length || ! $select.length ) {
			return;
		}

		var fieldObject = ( typeof acf !== 'undefined' && typeof acf.getField === 'function' ) ? acf.getField( $field ) : null;
		var hasEnhancedUi = $select.hasClass( 'select2-hidden-accessible' ) || !! $select.next( '.select2' ).length || !! $select.closest( '.acf-input' ).find( '> .select2, .select2-container' ).length;

		if ( fieldObject && fieldObject.select2 && typeof fieldObject.select2.destroy === 'function' ) {
			fieldObject.select2.destroy();
			fieldObject.select2 = null;
		} else if ( hasEnhancedUi && typeof $select.select2 === 'function' && $select.data( 'select2' ) ) {
			$select.select2( 'destroy' );
		}

		if ( hasEnhancedUi ) {
			$select.removeClass( 'select2-hidden-accessible' );
			$select.removeAttr( 'data-select2-id' );
			$select.find( 'option' ).removeAttr( 'data-select2-id' );
			$select.next( '.select2' ).remove();
			$select.closest( '.acf-input' ).find( '> .select2, > .select2-container' ).remove();
		}

		if ( fieldObject && typeof acf !== 'undefined' && typeof acf.newSelect2 === 'function' && fieldObject.get && fieldObject.get( 'ui' ) ) {
			var ajaxAction = fieldObject.get( 'ajax_action' );

			if ( ! ajaxAction ) {
				ajaxAction = 'acf/fields/' + fieldObject.get( 'type' ) + '/query';
			}

			fieldObject.select2 = acf.newSelect2( $select, {
				field: fieldObject,
				ajax: fieldObject.get( 'ajax' ),
				multiple: fieldObject.get( 'multiple' ),
				placeholder: fieldObject.get( 'placeholder' ),
				allowNull: fieldObject.get( 'allow_null' ),
				tags: fieldObject.get( 'create_options' ),
				ajaxAction: ajaxAction
			} );
		}

		$select.trigger( 'change' );
	}

	function syncContentListFilters( context ) {
		$( context || document ).filter( '.layout[data-layout="content_lists"]' ).add( $( context || document ).find( '.layout[data-layout="content_lists"]' ) ).not( '.acf-clone' ).each( function() {
			var $row = $( this );
			var $postTypeField = getContentListField( $row, 'list_post_type' );
			var $taxonomyField = getContentListField( $row, 'filter_taxonomy' );
			var $termsField = getContentListField( $row, 'filter_terms' );
			var $displayModeField = getContentListField( $row, 'display_mode' );
			var $postTypeSelect = getContentListSelect( $postTypeField );
			var $taxonomySelect = getContentListSelect( $taxonomyField );
			var $termsSelect = getContentListSelect( $termsField );
			var $displayModeSelect = getContentListSelect( $displayModeField );
			var postType = $postTypeSelect.val() || '';
			var taxonomy = $taxonomySelect.val() || '';
			var taxonomyMap = getContentListTaxonomyMap();
			var displayModeMap = getContentListDisplayModeMap();
			var allowedTaxonomies = taxonomyMap[ postType ] || {};
			var allowedDisplayModes = displayModeMap[ postType ] || {};
			var displayModeUiChanged = false;
			var taxonomyUiChanged = false;
			var termsUiChanged = false;

			$row.data( 'mrnSuppressContentListSync', true );

			if ( $displayModeSelect.length ) {
				var displayMode = $displayModeSelect.val() || '';

				if ( displayMode && ! Object.prototype.hasOwnProperty.call( allowedDisplayModes, displayMode ) ) {
					displayMode = '';
				}

				if ( ! displayMode ) {
					displayMode = '';
				}

				displayModeUiChanged = rebuildSelectOptions( $displayModeSelect, allowedDisplayModes, displayMode, {
					allowBlank: true,
					blankLabel: 'Use Row Settings'
				} );

				if ( displayModeUiChanged ) {
					$displayModeSelect.trigger( 'change' );
				}
			}

			if ( $taxonomySelect.length ) {
				if ( taxonomy && ! Object.prototype.hasOwnProperty.call( allowedTaxonomies, taxonomy ) ) {
					taxonomy = '';
				}

				if ( ! taxonomy ) {
					var firstTaxonomy = Object.keys( allowedTaxonomies )[0] || '';
					if ( firstTaxonomy ) {
						taxonomy = firstTaxonomy;
					}
				}

				taxonomyUiChanged = rebuildSelectOptions( $taxonomySelect, allowedTaxonomies, taxonomy, {
					allowBlank: true,
					blankLabel: 'Select'
				} );

				if ( taxonomyUiChanged ) {
					updateSelectUi( $taxonomyField );
				}
			}

			if ( $termsSelect.length ) {
				var selectedTerms = $termsSelect.val();
				var availableTerms = getCachedTermOptions( $termsSelect );
				var allowedTerms = {};

				if ( ! $.isArray( selectedTerms ) ) {
					selectedTerms = selectedTerms ? [ selectedTerms ] : [];
				}

				$.each( availableTerms, function( index, option ) {
					if ( ! option.value || ! taxonomy || option.taxonomy === taxonomy ) {
						allowedTerms[ option.value ] = option.label;
					}
				} );

				selectedTerms = $.grep( selectedTerms, function( value ) {
					return ! taxonomy || ( value.split( ':' )[0] || '' ) === taxonomy;
				} );

				termsUiChanged = rebuildSelectOptions( $termsSelect, allowedTerms, selectedTerms );

				if ( termsUiChanged ) {
					updateSelectUi( $termsField );
				}
			}

			setContentListLegacyPresentationState( $row, '' === ( $displayModeSelect.val() || '' ) );
			$row.removeData( 'mrnSuppressContentListSync' );
			setContentListSyncState( $row, false );
		} );
	}

	function scheduleContentListFilterSync( context ) {
		$( context || document ).filter( '.layout[data-layout="content_lists"]' ).add( $( context || document ).find( '.layout[data-layout="content_lists"]' ) ).not( '.acf-clone' ).each( function() {
			var $row = $( this );
			var pendingTimer = $row.data( 'mrnContentListSyncTimer' );

			if ( pendingTimer ) {
				window.clearTimeout( pendingTimer );
			}

			setContentListSyncState( $row, true );

			pendingTimer = window.setTimeout( function() {
				$row.removeData( 'mrnContentListSyncTimer' );
				syncContentListFilters( $row );
			}, 16 );

			$row.data( 'mrnContentListSyncTimer', pendingTimer );
		} );
	}

		var initialBuilderBootstrapped = false;
		var initialFlexibleCollapseQueue = [];
		var initialFlexibleCollapseScheduled = false;
		var deferCollapseUntil = 0;
		var interactionQuietPeriodMs = 900;
		var interactionRetryDelayMs = 220;
		var inputEditingRetryDelayMs = 260;
		var maxInitialFlexibleCollapseRows = 120;

	function markEditorInteraction() {
		var now = window.performance && typeof window.performance.now === 'function'
			? window.performance.now()
			: Date.now();

		deferCollapseUntil = now + interactionQuietPeriodMs;

		// Prioritize editor responsiveness once the user starts interacting.
		if ( initialFlexibleCollapseQueue.length ) {
			initialFlexibleCollapseQueue.length = 0;
		}
	}

	function shouldDeferCollapseForInteraction() {
		var now = window.performance && typeof window.performance.now === 'function'
			? window.performance.now()
			: Date.now();

		return deferCollapseUntil > now;
	}

	function isEditingInputControl() {
		var active = document.activeElement;

		if ( ! active ) {
			return false;
		}

		if ( active.isContentEditable ) {
			return true;
		}

		return /^(INPUT|TEXTAREA|SELECT)$/.test( active.tagName || '' );
	}

	function scheduleInitialFlexibleCollapse() {
		if ( initialFlexibleCollapseScheduled ) {
			return;
		}

		initialFlexibleCollapseScheduled = true;

		if ( typeof window.requestAnimationFrame === 'function' ) {
			window.requestAnimationFrame( processInitialFlexibleCollapseQueue );
			return;
		}

		window.setTimeout( processInitialFlexibleCollapseQueue, 0 );
	}

	function processInitialFlexibleCollapseQueue() {
		var processed = 0;
		var maxPerPass = 2;
		var start = window.performance && typeof window.performance.now === 'function' ? window.performance.now() : 0;
		var maxDuration = 4;

		initialFlexibleCollapseScheduled = false;

		if ( ! initialFlexibleCollapseQueue.length ) {
			return;
		}

		if ( shouldDeferCollapseForInteraction() ) {
			window.setTimeout( scheduleInitialFlexibleCollapse, interactionRetryDelayMs );
			return;
		}

		if ( isEditingInputControl() ) {
			window.setTimeout( scheduleInitialFlexibleCollapse, inputEditingRetryDelayMs );
			return;
		}

		while ( initialFlexibleCollapseQueue.length ) {
			var rowElement = initialFlexibleCollapseQueue.shift();
			var $row = $( rowElement );
			var $toggle;

			if ( ! $row.length || $row.hasClass( '-collapsed' ) || $row.hasClass( 'collapsed' ) ) {
				continue;
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

			processed += 1;

			if ( processed >= maxPerPass ) {
				break;
			}

			if ( start && window.performance.now() - start >= maxDuration ) {
				break;
			}
		}

		if ( initialFlexibleCollapseQueue.length ) {
			scheduleInitialFlexibleCollapse();
		}
	}

		function queueInitialFlexibleRows( context ) {
			var queueCapped = false;

			$( context || document ).find( '.acf-field-flexible-content' ).each( function() {
				var $flexField = $( this );

				if ( $flexField.data( 'mrn-initial-collapse-done' ) ) {
				return;
			}

			$flexField.data( 'mrn-initial-collapse-done', true );

			getRows( $flexField ).each( function() {
				var $row = $( this );

					if ( $row.hasClass( '-collapsed' ) || $row.hasClass( 'collapsed' ) ) {
						return;
					}

					if ( initialFlexibleCollapseQueue.length >= maxInitialFlexibleCollapseRows ) {
						queueCapped = true;
						return false;
					}

					initialFlexibleCollapseQueue.push( this );
				} );

				if ( queueCapped ) {
					return false;
				}
			} );

			scheduleInitialFlexibleCollapse();
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

	function bootBuilderAdminUiOnce( context ) {
		var bootContext = context || document;

		if ( initialBuilderBootstrapped ) {
			return;
		}

		initialBuilderBootstrapped = true;
		bootBuilderAdminUi( bootContext );
		window.setTimeout( function() {
			queueInitialFlexibleRows( bootContext );
		}, 40 );
	}

	$( function() {
		bootBuilderAdminUiOnce( document );
	} );

	$( document ).on(
		'mousedown touchstart keydown',
		'#post input, #post textarea, #post select, #post [contenteditable="true"]',
		function() {
			markEditorInteraction();
		}
	);

	acf.addAction( 'ready', function( $el ) {
		bootBuilderAdminUiOnce( $el || document );
	} );

	acf.addAction( 'append', function( $el ) {
		bootBuilderAdminUi( $el || document );
	} );

	$( document ).on( 'change select2:select select2:clear', '.layout[data-layout="content_lists"] .acf-field[data-name="list_post_type"] select, .layout[data-layout="content_lists"] .acf-field[data-name="filter_taxonomy"] select', function() {
		var $row = $( this ).closest( '.layout[data-layout="content_lists"]' );

		if ( $row.data( 'mrnSuppressContentListSync' ) ) {
			return;
		}

		scheduleContentListFilterSync( $row );
	} );

	$( document ).on( 'change', '.layout[data-layout="content_lists"] .acf-field[data-name="display_mode"] select', function() {
		refreshContentListLegacyPresentationState( $( this ).closest( '.layout[data-layout="content_lists"]' ) );
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
