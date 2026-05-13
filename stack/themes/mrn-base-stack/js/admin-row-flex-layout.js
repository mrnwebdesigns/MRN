( function( $, window ) {
	'use strict';

	if ( typeof mrnBaseStackBuilderAdmin === 'undefined' ) {
		return;
	}

	var config = mrnBaseStackBuilderAdmin;
	var rowFlexConfig = getRowFlexConfig();
	var initialLayoutTabsBootstrapped = false;

	if ( ! rowFlexConfig.supportedFields.length ) {
		return;
	}

	function getRowFlexConfig() {
		var rowFlex = config.rowFlex && typeof config.rowFlex === 'object' ? config.rowFlex : {};
		var supported = $.isArray( rowFlex.supportedFields ) ? rowFlex.supportedFields : [];
		var normalizedSupported = [];

		$.each( supported, function( index, fieldName ) {
			fieldName = sanitizeFieldName( fieldName );
			if ( fieldName && normalizedSupported.indexOf( fieldName ) === -1 ) {
				normalizedSupported.push( fieldName );
			}
		} );

		return {
			nonceField: typeof rowFlex.nonceField === 'string' && rowFlex.nonceField ? rowFlex.nonceField : 'mrn_base_stack_row_flex_nonce',
			nonce: typeof rowFlex.nonce === 'string' ? rowFlex.nonce : '',
			payloadField: typeof rowFlex.payloadField === 'string' && rowFlex.payloadField ? rowFlex.payloadField : 'mrn_base_stack_row_flex_payload',
			supportedFields: normalizedSupported,
			savedSettings: rowFlex.savedSettings && typeof rowFlex.savedSettings === 'object' ? rowFlex.savedSettings : {}
		};
	}

	function sanitizeFieldName( value ) {
		value = String( value || '' ).toLowerCase();
		value = value.replace( /[^a-z0-9_]/g, '' );
		return value;
	}

	function getDefaultFlexSettings() {
		return {
			enabled: false,
			scope: 'row',
			direction: 'row',
			justify: 'flex-start',
			align: 'stretch',
			wrap: 'nowrap',
			gap: '0'
		};
	}

	function normalizeChoice( value, allowed, fallback ) {
		value = String( value || '' ).toLowerCase();
		return allowed.indexOf( value ) === -1 ? fallback : value;
	}

	function normalizeGap( value ) {
		var numeric = parseFloat( value );
		if ( isNaN( numeric ) ) {
			numeric = 0;
		}

		numeric = Math.max( 0, Math.min( 160, numeric ) );

		var rounded = Math.round( numeric * 100 ) / 100;
		var gap = String( rounded );
		if ( gap.indexOf( '.' ) !== -1 ) {
			gap = gap.replace( /0+$/, '' ).replace( /\.$/, '' );
		}

		return gap || '0';
	}

	function normalizeFlexSettings( settings ) {
		var source = settings && typeof settings === 'object' ? settings : {};
		var defaults = getDefaultFlexSettings();

		return {
			enabled: !! source.enabled,
			scope: normalizeChoice( source.scope, [ 'row', 'repeaters' ], defaults.scope ),
			direction: normalizeChoice( source.direction, [ 'row', 'row-reverse', 'column', 'column-reverse' ], defaults.direction ),
			justify: normalizeChoice( source.justify, [ 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly' ], defaults.justify ),
			align: normalizeChoice( source.align, [ 'stretch', 'flex-start', 'center', 'flex-end', 'baseline' ], defaults.align ),
			wrap: normalizeChoice( source.wrap, [ 'nowrap', 'wrap', 'wrap-reverse' ], defaults.wrap ),
			gap: normalizeGap( source.gap )
		};
	}

	function isSupportedFieldName( fieldName ) {
		return rowFlexConfig.supportedFields.indexOf( fieldName ) !== -1;
	}

	function getRows( $flexField ) {
		var $values = $flexField.find( '> .acf-input > .acf-flexible-content > .values, > .acf-input > .values' ).first();
		return $values.children( '.layout' ).not( '.acf-clone' );
	}

	function getFieldNameForRow( $row ) {
		var $flexField = $row.closest( '.acf-field-flexible-content' );
		return sanitizeFieldName( $flexField.attr( 'data-name' ) || '' );
	}

	function getRowIndex( $row ) {
		var $flexField = $row.closest( '.acf-field-flexible-content' );
		if ( ! $flexField.length ) {
			return -1;
		}

		return getRows( $flexField ).index( $row );
	}

	function getSavedSettings( fieldName, rowIndex ) {
		var defaults = getDefaultFlexSettings();
		var fieldSettings = rowFlexConfig.savedSettings[ fieldName ];
		var rowKey = String( rowIndex );

		if ( ! fieldSettings || typeof fieldSettings !== 'object' || ! fieldSettings[ rowKey ] || typeof fieldSettings[ rowKey ] !== 'object' ) {
			return defaults;
		}

		return normalizeFlexSettings( fieldSettings[ rowKey ] );
	}

	function ensurePayloadInputs() {
		var $form = $( '#post' );
		if ( ! $form.length ) {
			return $();
		}

		var $payload = $form.find( 'input[name="' + rowFlexConfig.payloadField + '"]' ).first();
		if ( ! $payload.length ) {
			$payload = $( '<input type="hidden" />' ).attr( 'name', rowFlexConfig.payloadField );
			$form.append( $payload );
		}

		var $nonce = $form.find( 'input[name="' + rowFlexConfig.nonceField + '"]' ).first();
		if ( ! $nonce.length ) {
			$nonce = $( '<input type="hidden" />' ).attr( 'name', rowFlexConfig.nonceField );
			$form.append( $nonce );
		}

		$nonce.val( rowFlexConfig.nonce );

		return $payload;
	}

	function buildPanelMarkup( panelId ) {
		return $(
			'<div class="acf-field mrn-row-flex-panel" id="' + panelId + '" data-mrn-row-flex-panel="1">' +
					'<p class="mrn-row-flex-panel__description">Configure a lightweight row-level flex wrapper without adding ACF fields.</p>' +
					'<div class="mrn-row-flex-panel__grid">' +
						'<div class="mrn-row-flex-panel__control">' +
						'<label class="mrn-row-flex-panel__checkbox">' +
							'<input type="checkbox" data-mrn-row-flex-control="enabled" />' +
							'<span>Enable Flexbox</span>' +
							'</label>' +
						'</div>' +
						'<div class="mrn-row-flex-panel__control">' +
							'<label class="mrn-row-flex-panel__control-label">Apply To</label>' +
							'<select data-mrn-row-flex-control="scope">' +
								'<option value="row">Row</option>' +
								'<option value="repeaters">Repeaters Only</option>' +
							'</select>' +
						'</div>' +
						'<div class="mrn-row-flex-panel__control">' +
							'<label class="mrn-row-flex-panel__control-label">Direction</label>' +
							'<select data-mrn-row-flex-control="direction">' +
								'<option value="row">Row</option>' +
							'<option value="row-reverse">Row Reverse</option>' +
							'<option value="column">Column</option>' +
							'<option value="column-reverse">Column Reverse</option>' +
						'</select>' +
					'</div>' +
					'<div class="mrn-row-flex-panel__control">' +
						'<label class="mrn-row-flex-panel__control-label">Justify Content</label>' +
						'<select data-mrn-row-flex-control="justify">' +
							'<option value="flex-start">Start</option>' +
							'<option value="center">Center</option>' +
							'<option value="flex-end">End</option>' +
							'<option value="space-between">Space Between</option>' +
							'<option value="space-around">Space Around</option>' +
							'<option value="space-evenly">Space Evenly</option>' +
						'</select>' +
					'</div>' +
					'<div class="mrn-row-flex-panel__control">' +
						'<label class="mrn-row-flex-panel__control-label">Align Items</label>' +
						'<select data-mrn-row-flex-control="align">' +
							'<option value="stretch">Stretch</option>' +
							'<option value="flex-start">Start</option>' +
							'<option value="center">Center</option>' +
							'<option value="flex-end">End</option>' +
							'<option value="baseline">Baseline</option>' +
						'</select>' +
					'</div>' +
					'<div class="mrn-row-flex-panel__control">' +
						'<label class="mrn-row-flex-panel__control-label">Wrap</label>' +
						'<select data-mrn-row-flex-control="wrap">' +
							'<option value="nowrap">No Wrap</option>' +
							'<option value="wrap">Wrap</option>' +
							'<option value="wrap-reverse">Wrap Reverse</option>' +
						'</select>' +
					'</div>' +
					'<div class="mrn-row-flex-panel__control">' +
						'<label class="mrn-row-flex-panel__control-label">Gap (px)</label>' +
						'<input type="number" min="0" max="160" step="0.5" data-mrn-row-flex-control="gap" />' +
					'</div>' +
				'</div>' +
			'</div>'
		);
	}

	function applySettingsToPanel( $panel, settings ) {
		var normalized = normalizeFlexSettings( settings );
		$panel.find( '[data-mrn-row-flex-control="enabled"]' ).prop( 'checked', !! normalized.enabled );
		$panel.find( '[data-mrn-row-flex-control="scope"]' ).val( normalized.scope );
		$panel.find( '[data-mrn-row-flex-control="direction"]' ).val( normalized.direction );
		$panel.find( '[data-mrn-row-flex-control="justify"]' ).val( normalized.justify );
		$panel.find( '[data-mrn-row-flex-control="align"]' ).val( normalized.align );
		$panel.find( '[data-mrn-row-flex-control="wrap"]' ).val( normalized.wrap );
		$panel.find( '[data-mrn-row-flex-control="gap"]' ).val( normalized.gap );
		togglePanelEnabledState( $panel );
	}

	function readSettingsFromPanel( $panel ) {
		return normalizeFlexSettings( {
			enabled: $panel.find( '[data-mrn-row-flex-control="enabled"]' ).is( ':checked' ),
			scope: $panel.find( '[data-mrn-row-flex-control="scope"]' ).val(),
			direction: $panel.find( '[data-mrn-row-flex-control="direction"]' ).val(),
			justify: $panel.find( '[data-mrn-row-flex-control="justify"]' ).val(),
			align: $panel.find( '[data-mrn-row-flex-control="align"]' ).val(),
			wrap: $panel.find( '[data-mrn-row-flex-control="wrap"]' ).val(),
			gap: $panel.find( '[data-mrn-row-flex-control="gap"]' ).val()
		} );
	}

	function togglePanelEnabledState( $panel ) {
		var enabled = $panel.find( '[data-mrn-row-flex-control="enabled"]' ).is( ':checked' );
		$panel.toggleClass( 'is-disabled', ! enabled );
		$panel.find( 'select, input[type="number"]' ).prop( 'disabled', ! enabled );
	}

	function ensureLayoutTabForRow( $row ) {
		if ( ! $row || ! $row.length || $row.hasClass( 'acf-clone' ) ) {
			return;
		}

		var fieldName = getFieldNameForRow( $row );
		if ( ! isSupportedFieldName( fieldName ) ) {
			return;
		}

		var rowIndex = getRowIndex( $row );
		if ( rowIndex < 0 ) {
			return;
		}

		var $fields = $row.children( '.acf-fields' ).first();
		if ( ! $fields.length ) {
			return;
		}

		var $tabGroup = $fields.find( '> .acf-tab-wrap .acf-tab-group, > .acf-tab-group' ).first();
		if ( ! $tabGroup.length ) {
			return;
		}

		var $tabItem = $tabGroup.find( 'li.mrn-row-flex-tab' ).first();
		var panelId = 'mrn-row-flex-panel-' + fieldName + '-' + rowIndex + '-' + Math.floor( Math.random() * 1000000 );
		var $panel = $fields.children( '.mrn-row-flex-panel' ).first();

		if ( ! $tabItem.length ) {
			$tabItem = $( '<li class="mrn-row-flex-tab"><a href="#" data-mrn-row-flex-tab="1">Layout</a></li>' );
			$tabGroup.append( $tabItem );
		}

		if ( ! $panel.length ) {
			$panel = buildPanelMarkup( panelId );
			$fields.append( $panel );
		}

		$tabItem.find( 'a' ).attr( 'aria-controls', $panel.attr( 'id' ) || panelId );
		applySettingsToPanel( $panel, getSavedSettings( fieldName, rowIndex ) );
	}

	function ensureLayoutTabs( context ) {
		$( context || document ).find( '.acf-field-flexible-content' ).each( function() {
			var $flexField = $( this );
			var fieldName = sanitizeFieldName( $flexField.attr( 'data-name' ) || '' );

			if ( ! isSupportedFieldName( fieldName ) ) {
				return;
			}

			getRows( $flexField ).each( function() {
				ensureLayoutTabForRow( $( this ) );
			} );
		} );

		syncLayoutPayload();
	}

	function ensureLayoutTabsOnce( context ) {
		if ( initialLayoutTabsBootstrapped ) {
			return;
		}

		initialLayoutTabsBootstrapped = true;
		ensureLayoutTabs( context || document );
	}

	function collectLayoutPayload() {
		var payload = {};

		$( '.acf-field-flexible-content' ).each( function() {
			var $flexField = $( this );
			var fieldName = sanitizeFieldName( $flexField.attr( 'data-name' ) || '' );

			if ( ! isSupportedFieldName( fieldName ) ) {
				return;
			}

			getRows( $flexField ).each( function( index ) {
				var $row = $( this );
				var $panel = $row.children( '.acf-fields' ).children( '.mrn-row-flex-panel' ).first();

				if ( ! $panel.length ) {
					return;
				}

				var settings = readSettingsFromPanel( $panel );
				if ( ! settings.enabled ) {
					return;
				}

				if ( ! payload[ fieldName ] ) {
					payload[ fieldName ] = {};
				}

				payload[ fieldName ][ String( index ) ] = settings;
			} );
		} );

		return payload;
	}

	function syncLayoutPayload() {
		var $payload = ensurePayloadInputs();
		if ( ! $payload.length ) {
			return;
		}

		$payload.val( JSON.stringify( collectLayoutPayload() ) );
	}

	$( function() {
		ensureLayoutTabsOnce( document );
	} );

	if ( window.acf && typeof window.acf.addAction === 'function' ) {
		window.acf.addAction( 'ready', function( $el ) {
			ensureLayoutTabsOnce( $el || document );
		} );

		window.acf.addAction( 'append', function( $el ) {
			ensureLayoutTabs( $el || document );
		} );
	}

	$( document ).on( 'click', '.layout .acf-tab-group li.mrn-row-flex-tab > a', function( event ) {
		event.preventDefault();
		var $row = $( this ).closest( '.layout' );
		var $tabItem = $( this ).closest( 'li' );

		$tabItem.addClass( 'active' );
		$tabItem.siblings().removeClass( 'active' );
		$row.addClass( 'mrn-row-flex-tab-active' );
	} );

	$( document ).on( 'click', '.layout .acf-tab-group li:not(.mrn-row-flex-tab) > a', function() {
		var $row = $( this ).closest( '.layout' );
		$row.removeClass( 'mrn-row-flex-tab-active' );
		$row.find( '.acf-tab-group li.mrn-row-flex-tab' ).removeClass( 'active' );
	} );

	$( document ).on( 'change', '.layout .mrn-row-flex-panel [data-mrn-row-flex-control="enabled"]', function() {
		var $panel = $( this ).closest( '.mrn-row-flex-panel' );
		togglePanelEnabledState( $panel );
		syncLayoutPayload();
	} );

	$( document ).on( 'input change', '.layout .mrn-row-flex-panel select, .layout .mrn-row-flex-panel input[type="number"]', function() {
		syncLayoutPayload();
	} );

	$( document ).on( 'submit', '#post', function() {
		syncLayoutPayload();
	} );
} )( jQuery, window );
