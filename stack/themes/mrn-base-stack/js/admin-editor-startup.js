/* global acf */
( function( $ ) {
	'use strict';
	if ( typeof acf === 'undefined' ) {
		return;
	}
	function isClosedBuilderField( field ) {
		return String( field.get( 'key' ) || '' ).indexOf( 'field_mrn_' ) !== -1
			&& field.$el.closest( '.mrn-acf-lazy-layouts' ).length
			&& field.$el.closest( '.layout.-collapsed, .acf-row.-collapsed' ).length
			&& ! field.$el.closest( '.acf-clone' ).length;
	}
	var selectTypes = [ 'select', 'post_object', 'page_link', 'user' ];
	selectTypes.forEach( function( type ) {
		var model = typeof acf.getFieldType === 'function' ? acf.getFieldType( type ) : null;
		var initialize = model && model.prototype.initialize;
		if ( typeof initialize !== 'function' || initialize.mrnStartupWrapped ) {
			return;
		}
		var deferredInitialize = function() {
			if ( isClosedBuilderField( this ) ) {
				// Keep native input attributes/values available before enhancement.
				this.inherit( this.$input() );
				if ( this.get( 'ui' ) ) {
					this.mrnInitializeSelect = initialize;
					return;
				}
			}
			return initialize.apply( this, arguments );
		};
		deferredInitialize.mrnStartupWrapped = true;
		model.prototype.initialize = deferredInitialize;
	} );
	function activateSelect( field ) {
		if ( ! field.mrnInitializeSelect || ! field.$el.is( ':visible' ) || isClosedBuilderField( field ) ) {
			return;
		}
		var initialize = field.mrnInitializeSelect;
		delete field.mrnInitializeSelect;
		initialize.call( field );
	}
	function activateEditor( field ) {
		if ( ! field.$el.hasClass( 'mrn-acf-deferred-editor' ) || ! field.$el.is( ':visible' )
			|| field.$el.closest( '.layout.-collapsed, .acf-row.-collapsed, .acf-clone' ).length
			|| typeof field.onMousedown !== 'function' || ! field.$control().hasClass( 'delay' ) ) {
			return;
		}
		// Use ACF's native delayed-editor action, preserving its textarea and value.
		field.onMousedown( { preventDefault: function() {} } );
	}
	acf.addAction( 'load', function() {
		acf.addAction( 'show_field/type=wysiwyg', activateEditor, 99 );
		acf.addAction( 'append_field/type=wysiwyg', activateEditor, 99 );
		acf.getFields( { type: 'wysiwyg' } ).forEach( activateEditor );
		selectTypes.forEach( function( type ) {
			acf.addAction( 'show_field/type=' + type, activateSelect, 99 );
			acf.getFields( { type: type } ).forEach( activateSelect );
		} );
	}, 99 );
} )( jQuery );
