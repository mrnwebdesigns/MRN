( function( $, window, document ) {
	'use strict';

	if ( typeof $ === 'undefined' || typeof window.mrnBaseStackBuilderAdmin === 'undefined' ) {
		return;
	}

	var adminConfig = window.mrnBaseStackBuilderAdmin;
	var chooserConfig = adminConfig.layoutChooser && typeof adminConfig.layoutChooser === 'object' ? adminConfig.layoutChooser : null;

	if ( ! chooserConfig ) {
		return;
	}

	var state = {
		hasSavedSelection: !! chooserConfig.hasSavedSelection,
		selectedLayouts: $.isArray( chooserConfig.selectedLayouts ) ? chooserConfig.selectedLayouts.slice() : [],
		canPersistSelection: !! chooserConfig.canPersistSelection,
		activeFieldName: '',
		autoAddAfterSave: false,
		isSaving: false
	};

	var ui = {
		overlay: null,
		dialog: null,
		optionsWrap: null,
		saveButton: null,
		closeButton: null
	};

	function getLayoutsByField() {
		return chooserConfig.layoutsByField && typeof chooserConfig.layoutsByField === 'object' ? chooserConfig.layoutsByField : {};
	}

	function getBuilderLayouts() {
		return $.isArray( adminConfig.builderLayouts ) ? adminConfig.builderLayouts : [];
	}

	function getManagedFieldNames() {
		var explicit = $.isArray( chooserConfig.managedFieldNames ) ? chooserConfig.managedFieldNames : [];
		var mapKeys = Object.keys( getLayoutsByField() );
		var names = explicit.concat( mapKeys );

		return names.filter( function( name, index ) {
			return !! name && names.indexOf( name ) === index;
		} );
	}

	function getDefaultManagedFieldName() {
		var names = getManagedFieldNames();

		if ( names.indexOf( 'page_content_rows' ) !== -1 ) {
			return 'page_content_rows';
		}

		return names[0] || '';
	}

	function getFlexibleFieldNameFromElement( element ) {
		var field = element && element.closest ? element.closest( '.acf-field-flexible-content' ) : null;

		if ( ! field ) {
			return '';
		}

		return String( field.getAttribute( 'data-name' ) || '' );
	}

	function isChooserManagedFlexibleField( element ) {
		var fieldName = getFlexibleFieldNameFromElement( element );

		return !! fieldName && getManagedFieldNames().indexOf( fieldName ) !== -1;
	}

	function getFieldLayouts( fieldName ) {
		var byField = getLayoutsByField();

		if ( fieldName && $.isArray( byField[ fieldName ] ) ) {
			return byField[ fieldName ];
		}

		if ( fieldName === 'page_content_rows' ) {
			return getBuilderLayouts();
		}

		return [];
	}

	function getHiddenLayouts( layouts ) {
		var hidden = [];
		var disabled = $.isArray( adminConfig.disabledLayouts ) ? adminConfig.disabledLayouts : [];
		var sourceLayouts = $.isArray( layouts ) ? layouts : getBuilderLayouts();

		$.each( sourceLayouts, function( index, layout ) {
			if ( ! layout || typeof layout !== 'object' || ! layout.name ) {
				return;
			}

			if ( layout.isPageOnly && hidden.indexOf( layout.name ) === -1 ) {
				hidden.push( layout.name );
			}
		} );

		$.each( disabled, function( index, name ) {
			if ( hidden.indexOf( name ) === -1 ) {
				hidden.push( name );
			}
		} );

		return hidden;
	}

	function getSelectableLayouts( fieldName ) {
		var sourceLayouts = getFieldLayouts( fieldName );
		var hiddenLayouts = getHiddenLayouts( sourceLayouts );
		var items = [];

		$.each( sourceLayouts, function( index, layout ) {
			var name;
			var label;

			if ( ! layout || typeof layout !== 'object' || ! layout.name ) {
				return;
			}

			name = String( layout.name );
			label = String( layout.label || layout.name );

			if ( hiddenLayouts.indexOf( name ) !== -1 ) {
				return;
			}

			if ( items.some( function( item ) { return item.name === name; } ) ) {
				return;
			}

			items.push( {
				name: name,
				label: label
			} );
		} );

		items.sort( function( left, right ) {
			return left.label.toLowerCase().localeCompare( right.label.toLowerCase() );
		} );

		return items;
	}

	function getLayoutNamesForField( fieldName ) {
		return getSelectableLayouts( fieldName ).map( function( layout ) {
			return String( layout.name || '' );
		} ).filter( function( name ) {
			return !! name;
		} );
	}

	function addSelectedLayoutRow( fieldName, layoutName ) {
		var safeFieldName = String( fieldName || '' );
		var safeLayoutName = String( layoutName || '' );
		var $field;
		var fieldObject;

		if ( ! safeFieldName || ! safeLayoutName ) {
			return;
		}

		$field = $( '.acf-field-flexible-content[data-name="' + safeFieldName + '"]' ).first();
		if ( ! $field.length ) {
			return;
		}

		fieldObject = window.acf && typeof window.acf.getField === 'function' ? window.acf.getField( $field ) : null;
		if ( fieldObject && typeof fieldObject.add === 'function' ) {
			fieldObject.add( { layout: safeLayoutName } );
			return;
		}

		var $addTrigger = $field.find( '[data-name="add-layout"], [data-event="add-row"]' ).first();
		if ( ! $addTrigger.length ) {
			return;
		}

		$addTrigger.trigger( 'click' );

		window.setTimeout( function() {
			var $layoutLink = $field.find( '[data-layout="' + safeLayoutName + '"]' ).first();
			if ( ! $layoutLink.length ) {
				return;
			}

			$layoutLink.trigger( 'click' );
		}, 40 );
	}

	function getPostId() {
		var postId = parseInt( chooserConfig.postId || 0, 10 );

		if ( postId ) {
			return postId;
		}

		var $postIdInput = $( '#post_ID' );

		if ( $postIdInput.length ) {
			postId = parseInt( $postIdInput.val() || 0, 10 );
		}

		return postId || 0;
	}

	function showNotice( text, type ) {
		if ( window.acf && typeof window.acf.newNotice === 'function' ) {
			window.acf.newNotice( {
				text: text,
				type: type || 'info',
				target: $( '.wrap' ).first(),
				location: 'prepend'
			} );
			return;
		}

		if ( type === 'error' ) {
			window.alert( text );
		}
	}

	function ensureStyles() {
		if ( document.getElementById( 'mrn-layout-chooser-style' ) ) {
			return;
		}

		var style = document.createElement( 'style' );
		style.id = 'mrn-layout-chooser-style';
		style.textContent = '' +
			'.mrn-layout-chooser-notice{margin-top:12px;}' +
			'.mrn-layout-chooser-overlay{position:fixed;inset:0;z-index:100500;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;padding:24px;}' +
			'.mrn-layout-chooser-overlay.is-open{display:flex;}' +
			'.mrn-layout-chooser-dialog{background:#fff;border-radius:8px;max-width:920px;width:100%;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 18px 42px rgba(0,0,0,.25);}' +
			'.mrn-layout-chooser-header{padding:20px 24px;border-bottom:1px solid #dcdcde;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;}' +
			'.mrn-layout-chooser-title{margin:0;font-size:18px;line-height:1.2;}' +
			'.mrn-layout-chooser-description{margin:8px 0 0;color:#50575e;}' +
			'.mrn-layout-chooser-close{border:none;background:transparent;font-size:24px;line-height:1;cursor:pointer;color:#646970;padding:0 4px;}' +
			'.mrn-layout-chooser-body{padding:16px 24px 8px;overflow:auto;}' +
			'.mrn-layout-chooser-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px;}' +
			'.mrn-layout-chooser-item{display:flex;gap:10px;align-items:flex-start;padding:10px;border:1px solid #dcdcde;border-radius:6px;background:#fff;}' +
			'.mrn-layout-chooser-item input{margin-top:2px;}' +
			'.mrn-layout-chooser-item-label{font-size:13px;line-height:1.35;color:#1d2327;}' +
			'.mrn-layout-chooser-footer{padding:16px 24px;border-top:1px solid #dcdcde;display:flex;justify-content:space-between;align-items:center;gap:12px;}' +
			'.mrn-layout-chooser-count{font-size:12px;color:#646970;}' +
			'@media (max-width:782px){.mrn-layout-chooser-overlay{padding:12px}.mrn-layout-chooser-grid{grid-template-columns:1fr}.mrn-layout-chooser-header,.mrn-layout-chooser-body,.mrn-layout-chooser-footer{padding-left:14px;padding-right:14px}}';

		document.head.appendChild( style );
	}

	function ensureDialog() {
		var dialogId = 'mrn-layout-chooser-overlay';

		if ( ui.overlay && ui.overlay.length ) {
			return;
		}

		var markup = '' +
			'<div id="' + dialogId + '" class="mrn-layout-chooser-overlay" aria-hidden="true">' +
				'<div class="mrn-layout-chooser-dialog" role="dialog" aria-modal="true" aria-labelledby="mrn-layout-chooser-title">' +
					'<div class="mrn-layout-chooser-header">' +
						'<div>' +
							'<h2 id="mrn-layout-chooser-title" class="mrn-layout-chooser-title"></h2>' +
							'<p class="mrn-layout-chooser-description"></p>' +
						'</div>' +
						'<button type="button" class="mrn-layout-chooser-close" aria-label="Close chooser">&times;</button>' +
					'</div>' +
					'<div class="mrn-layout-chooser-body">' +
						'<div class="mrn-layout-chooser-grid"></div>' +
					'</div>' +
					'<div class="mrn-layout-chooser-footer">' +
						'<div class="mrn-layout-chooser-count"></div>' +
						'<div>' +
							'<button type="button" class="button mrn-layout-chooser-cancel">Cancel</button> ' +
							'<button type="button" class="button button-primary mrn-layout-chooser-save"></button>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>';

		ui.overlay = $( markup );
		ui.dialog = ui.overlay.find( '.mrn-layout-chooser-dialog' ).first();
		ui.optionsWrap = ui.overlay.find( '.mrn-layout-chooser-grid' ).first();
		ui.saveButton = ui.overlay.find( '.mrn-layout-chooser-save' ).first();
		ui.closeButton = ui.overlay.find( '.mrn-layout-chooser-close, .mrn-layout-chooser-cancel' );

		ui.overlay.find( '.mrn-layout-chooser-title' ).text( chooserConfig.dialogTitle || 'Choose Allowed Layouts' );
		ui.overlay.find( '.mrn-layout-chooser-description' ).text( chooserConfig.dialogDescription || '' );
		ui.saveButton.text( chooserConfig.updateButton || 'Save Selection' );

		$( document.body ).append( ui.overlay );
	}

	function getPrimaryButtonLabel() {
		if ( state.autoAddAfterSave ) {
			return chooserConfig.insertButton || 'Insert Layout(s)';
		}

		return chooserConfig.updateButton || 'Save Selection';
	}

	function renderDialogOptions() {
		var layouts = getSelectableLayouts( state.activeFieldName );
		var selected = state.selectedLayouts.slice();

		ui.optionsWrap.empty();

		$.each( layouts, function( index, layout ) {
			var isChecked = selected.indexOf( layout.name ) !== -1;
			var $item = $(
				'<label class="mrn-layout-chooser-item">' +
					'<input type="checkbox" value="' + layout.name + '">' +
					'<span class="mrn-layout-chooser-item-label"></span>' +
				'</label>'
			);

			$item.find( 'input' ).prop( 'checked', isChecked );
			$item.find( '.mrn-layout-chooser-item-label' ).text( layout.label );
			ui.optionsWrap.append( $item );
		} );

		updateCountAndSaveState();
	}

	function getSelectedFromDialog() {
		return ui.optionsWrap.find( 'input:checked' ).map( function() {
			return String( $( this ).val() || '' );
		} ).get();
	}

	function updateCountAndSaveState() {
		var selectedCount = getSelectedFromDialog().length;

		ui.overlay.find( '.mrn-layout-chooser-count' ).text( selectedCount + ' layout' + ( selectedCount === 1 ? '' : 's' ) + ' selected' );
		ui.saveButton.prop( 'disabled', state.isSaving || selectedCount < 1 );
	}

	function setDialogOpen( isOpen ) {
		if ( ! ui.overlay || ! ui.overlay.length ) {
			return;
		}

		ui.overlay.toggleClass( 'is-open', !! isOpen );
		ui.overlay.attr( 'aria-hidden', isOpen ? 'false' : 'true' );

		if ( isOpen ) {
			window.setTimeout( function() {
				var $first = ui.optionsWrap.find( 'input' ).first();
				if ( $first.length ) {
					$first.trigger( 'focus' );
				}
			}, 0 );
		}
	}

	function openChooser( fieldName, options ) {
		var settings = $.extend(
			{
				autoAddAfterSave: false
			},
			options || {}
		);

		if ( ! getPostId() ) {
			showNotice( chooserConfig.cannotResolvePostIdNotice || 'Save this draft once, then choose layouts.', 'warning' );
			return;
		}

		if ( fieldName ) {
			state.activeFieldName = String( fieldName );
		} else if ( ! state.activeFieldName ) {
			state.activeFieldName = getDefaultManagedFieldName();
		}
		state.autoAddAfterSave = !! settings.autoAddAfterSave;

		ensureStyles();
		ensureDialog();

		ui.closeButton.show();
		ui.saveButton.text( getPrimaryButtonLabel() );
		renderDialogOptions();
		setDialogOpen( true );
	}

	function closeChooser( forceClose ) {
		if ( state.isSaving && ! forceClose ) {
			return;
		}

		state.autoAddAfterSave = false;
		setDialogOpen( false );
	}

	function saveSelection() {
		var postId = getPostId();
		var selected = getSelectedFromDialog();
		var fieldLayoutNames = getLayoutNamesForField( state.activeFieldName );
		var nextSelection = state.selectedLayouts.filter( function( layoutName ) {
			return fieldLayoutNames.indexOf( String( layoutName || '' ) ) === -1;
		} );

		if ( ! postId ) {
			showNotice( chooserConfig.cannotResolvePostIdNotice || 'Save this draft once, then choose layouts.', 'error' );
			return;
		}

		if ( selected.length < 1 ) {
			showNotice( chooserConfig.emptySelectionError || 'Choose at least one layout.', 'error' );
			return;
		}

		$.each( selected, function( index, layoutName ) {
			if ( nextSelection.indexOf( layoutName ) !== -1 ) {
				return;
			}

			nextSelection.push( layoutName );
		} );

		state.isSaving = true;
		updateCountAndSaveState();
		ui.saveButton.text( chooserConfig.savingButton || 'Saving...' );

		$.ajax( {
			url: adminConfig.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: chooserConfig.saveAction,
				nonce: chooserConfig.nonce,
				post_id: postId,
				layouts: nextSelection
			}
		} ).done( function( response ) {
			if ( ! response || ! response.success || ! response.data ) {
				showNotice( response && response.data && response.data.message ? response.data.message : ( chooserConfig.saveFailedNotice || 'Could not save the layout selection.' ), 'error' );
				return;
			}

			state.hasSavedSelection = true;
			state.selectedLayouts = $.isArray( response.data.selectedLayouts ) ? response.data.selectedLayouts : nextSelection.slice();
			showNotice( chooserConfig.saveSuccessNotice || 'Layouts saved.', 'success' );
			if ( state.autoAddAfterSave && selected.length ) {
				addSelectedLayoutRow( state.activeFieldName, selected[0] );
			}
			closeChooser( true );
		} ).fail( function( xhr ) {
			var message = chooserConfig.saveFailedNotice || 'Could not save the layout selection.';

			if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
				message = xhr.responseJSON.data.message;
			}

			showNotice( message, 'error' );
		} ).always( function() {
			state.isSaving = false;
			ui.saveButton.text( getPrimaryButtonLabel() );
			updateCountAndSaveState();
		} );
	}

	function ensureLaunchNotice() {
		if ( $( '#mrn-layout-chooser-notice' ).length ) {
			return;
		}

		var $wrap = $( '.wrap' ).first();
		if ( ! $wrap.length ) {
			return;
		}

		var noticeClass = state.hasSavedSelection ? 'notice-info' : 'notice-warning';
		var noticeText = state.hasSavedSelection ? ( chooserConfig.readyNotice || '' ) : ( chooserConfig.missingSelectionNotice || '' );
		var launchLabel = chooserConfig.launchButton || 'Choose Layouts';
		var $notice = $(
			'<div id="mrn-layout-chooser-notice" class="notice ' + noticeClass + ' mrn-layout-chooser-notice"><p></p></div>'
		);

		if ( ! state.hasSavedSelection && ! getPostId() ) {
			noticeText = chooserConfig.cannotResolvePostIdNotice || noticeText;
		}

		$notice.find( 'p' ).text( noticeText + ' ' );
		$notice.find( 'p' ).append(
			$( '<button type="button" class="button button-primary mrn-layout-chooser-launch"></button>' ).text( launchLabel )
		);

		$wrap.prepend( $notice );
	}

	$( function() {
		state.activeFieldName = getDefaultManagedFieldName();

		if ( ! getSelectableLayouts( state.activeFieldName ).length ) {
			return;
		}

		ensureLaunchNotice();
	} );

	$( document ).on( 'click', '.mrn-layout-chooser-launch', function( event ) {
		event.preventDefault();

		if ( ! getPostId() ) {
			showNotice( chooserConfig.cannotResolvePostIdNotice || 'Save this draft once, then choose layouts.', 'warning' );
			return;
		}

		openChooser( state.activeFieldName, { autoAddAfterSave: false } );
	} );

	$( document ).on( 'click', '.mrn-layout-chooser-close, .mrn-layout-chooser-cancel', function( event ) {
		event.preventDefault();
		closeChooser();
	} );

	$( document ).on( 'click', '.mrn-layout-chooser-overlay', function( event ) {
		if ( ! ui.dialog || ! ui.dialog.length ) {
			return;
		}

		if ( ui.dialog[0] === event.target || ui.dialog[0].contains( event.target ) ) {
			return;
		}

		closeChooser();
	} );

	$( document ).on( 'change', '.mrn-layout-chooser-grid input[type="checkbox"]', function() {
		updateCountAndSaveState();
	} );

	$( document ).on( 'click', '.mrn-layout-chooser-save', function( event ) {
		event.preventDefault();

		if ( state.isSaving ) {
			return;
		}

		saveSelection();
	} );

	$( document ).on( 'keydown', function( event ) {
		if ( event.key !== 'Escape' || ! ui.overlay || ! ui.overlay.length || ! ui.overlay.hasClass( 'is-open' ) ) {
			return;
		}

		closeChooser();
	} );

	document.addEventListener( 'click', function( event ) {
		if ( state.isSaving ) {
			return;
		}

		var target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}

		var addRowTrigger = target.closest(
			'.acf-field-flexible-content [data-event="add-row"], ' +
			'.acf-field-flexible-content [data-name="add-layout"]'
		);

		if ( ! addRowTrigger ) {
			return;
		}

		if ( ! isChooserManagedFlexibleField( addRowTrigger ) ) {
			return;
		}

		var fieldName = getFlexibleFieldNameFromElement( addRowTrigger );

		event.preventDefault();
		event.stopPropagation();

		if ( typeof event.stopImmediatePropagation === 'function' ) {
			event.stopImmediatePropagation();
		}

		openChooser( fieldName, { autoAddAfterSave: true } );
	}, true );
} )( jQuery, window, document );
