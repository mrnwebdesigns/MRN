/* global acf, mrnBuilderLayouts */
( function( $ ) {
	'use strict';
	if ( typeof acf === 'undefined' || typeof mrnBuilderLayouts === 'undefined' ) {
		return;
	}

	var pending = 0;
	function install( field ) {
		if ( ! field.$el.hasClass( 'mrn-acf-lazy-layouts' ) || field.mrnLazyInstalled ) {
			return;
		}
		field.mrnLazyInstalled = true;
		var add = field.add;
		function addLoaded( args ) {
			// A duplicate or another action may have filled this layout's limit while loading.
			if ( ! field.countLayoutsByName( field.$clone( args.layout ) ) ) {
				return false;
			}
			var $row = add.call( field, args );
			if ( $row && typeof args.mrnComplete === 'function' ) {
				args.mrnComplete( $row );
			}
			return $row;
		}
		field.add = function( args ) {
			args = acf.parseArgs( args, { layout: '', before: false } );
			var $clone = field.$clone( args.layout );
			if ( $clone.attr( 'data-mrn-loaded' ) === '1' ) {
				return addLoaded( args );
			}
			if ( field.mrnLoading || ! field.validateAdd() || ! $clone.length ) {
				return false;
			}
			field.mrnLoading = true;
			pending++;
			field.$control().attr( 'aria-busy', 'true' );
			field.showNotice( { text: mrnBuilderLayouts.loading, type: 'info' } );
			$.ajax( {
				url: acf.get( 'ajaxurl' ),
				type: 'POST',
				dataType: 'json',
				timeout: 30000,
				data: acf.prepareForAjax( {
					action: 'mrn_base_stack_builder_layout',
					field_key: field.get( 'key' ),
					post_id: acf.get( 'post_id' ),
					nonce: field.get( 'nonce' ),
					layout: args.layout,
					input_name: field.$control().children( 'input[type="hidden"]' ).first().attr( 'name' )
				} )
			} ).done( function( response ) {
				if ( ! response || ! response.success || ! response.data || typeof response.data.html !== 'string' ) {
					field.showNotice( { text: mrnBuilderLayouts.failed, type: 'error' } );
					return;
				}
				var $template = $( response.data.html ).filter( '.layout.acf-clone' );
				if ( $template.length !== 1 || $template.attr( 'data-layout' ) !== args.layout || ! field.$el.closest( 'html' ).length ) {
					field.showNotice( { text: mrnBuilderLayouts.failed, type: 'error' } );
					return;
				}
				$template.attr( 'data-mrn-loaded', '1' );
				acf.disable( $template, field.cid );
				$clone.replaceWith( $template );
				field.removeNotice();
				// Recheck limits in the native method after the asynchronous load.
				addLoaded( args );
			} ).fail( function() {
				field.showNotice( { text: mrnBuilderLayouts.failed, type: 'error' } );
			} ).always( function() {
				field.mrnLoading = false;
				pending--;
				field.$control().removeAttr( 'aria-busy' );
			} );
			return false;
		};
	}
	acf.addAction( 'new_field/type=flexible_content', install );
	acf.addAction( 'ready_field/type=flexible_content', install );
	acf.addFilter( 'validation_complete', function( result, $form ) {
		if ( pending && $form.attr( 'id' ) === 'post' ) {
			result.valid = false;
			result.errors = result.errors || [];
			result.errors.push( { input: '', message: mrnBuilderLayouts.pending } );
		}
		return result;
	} );

	// Capture submission before ACF/WordPress validation can save a partial row.
	document.addEventListener( 'submit', function( event ) {
		if ( pending && event.target.id === 'post' ) {
			event.preventDefault();
			event.stopImmediatePropagation();
			acf.newNotice( { text: mrnBuilderLayouts.pending, type: 'warning', target: $( '#post' ) } );
		}
	}, true );
} )( jQuery );
