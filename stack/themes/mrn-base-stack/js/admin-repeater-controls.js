( function ( $, acf ) {
	'use strict';

	if ( typeof $ === 'undefined' || typeof acf === 'undefined' ) {
		return;
	}

	function getRepeaterFields( context ) {
		return $( context || document )
			.filter( '.acf-field[data-type="repeater"]' )
			.add( $( context || document ).find( '.acf-field[data-type="repeater"]' ) )
			.not( '.acf-clone' );
	}

	function getRepeaterRows( $field ) {
		return $field.find( '> .acf-input > .acf-repeater > .acf-table > tbody > .acf-row, > .acf-input > .acf-repeater > .acf-table > .acf-tbody > .acf-row, > .acf-input > .acf-repeater > table > tbody > .acf-row, > .acf-input > .acf-repeater > .values > .acf-row' ).not( '.acf-clone' );
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

	function ensureToolbar( $field ) {
		var $label = $field.children( '.acf-label' ).first();
		var $existing = $label.find( '.mrn-acf-repeater-toolbar' ).first();
		var $toolbar;

		if ( ! $label.length ) {
			return $();
		}

		if ( $existing.length ) {
			return $existing;
		}

		$toolbar = $(
			'<div class="mrn-acf-repeater-toolbar">' +
				'<button type="button" class="button button-secondary" data-mrn-repeater-collapse-all="true">Collapse All</button>' +
				'<button type="button" class="button button-secondary" data-mrn-repeater-expand-all="true">Expand All</button>' +
			'</div>'
		);

		$toolbar.css( {
			display: 'flex',
			gap: '0.5rem',
			marginTop: '0.5rem',
			marginBottom: '0.25rem',
			flexWrap: 'wrap'
		} );

		$label.append( $toolbar );

		return $toolbar;
	}

	function refreshToolbars( context ) {
		getRepeaterFields( context ).each( function () {
			ensureToolbar( $( this ) );
		} );
	}

	$( document ).on( 'click', '[data-mrn-repeater-collapse-all="true"]', function ( event ) {
		var $field;

		event.preventDefault();
		$field = $( this ).closest( '.acf-field[data-type="repeater"]' );

		getRepeaterRows( $field ).each( function () {
			setRowCollapsed( $( this ), true );
		} );
	} );

	$( document ).on( 'click', '[data-mrn-repeater-expand-all="true"]', function ( event ) {
		var $field;

		event.preventDefault();
		$field = $( this ).closest( '.acf-field[data-type="repeater"]' );

		getRepeaterRows( $field ).each( function () {
			setRowCollapsed( $( this ), false );
		} );
	} );

	acf.addAction( 'ready append', function ( $el ) {
		refreshToolbars( $el );
	} );

	$( function () {
		refreshToolbars( document );
	} );
}( jQuery, window.acf ) );
