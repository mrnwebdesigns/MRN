( function ( $, acf ) {
	'use strict';

	if ( typeof $ === 'undefined' || typeof acf === 'undefined' ) {
		return;
	}

	function getRepeaterFields( context ) {
		var $context = $( context || document );

		return $context
			.filter( '.acf-field[data-type="repeater"]' )
			.add( $context.find( '.acf-field[data-type="repeater"]' ) )
			.add( $context.closest( '.acf-field[data-type="repeater"]' ) )
			.not( '.acf-clone' );
	}

	function getRepeaterRows( $field ) {
		return $field.find( '> .acf-input > .acf-repeater > .acf-table > tbody > .acf-row, > .acf-input > .acf-repeater > .acf-table > .acf-tbody > .acf-row, > .acf-input > .acf-repeater > table > tbody > .acf-row, > .acf-input > .acf-repeater > .values > .acf-row' ).not( '.acf-clone' );
	}

	function markInitialPrecollapseReady( attributeName ) {
		var root = document.documentElement;

		if ( ! root || ! attributeName ) {
			return;
		}

		root.setAttribute( attributeName, 'done' );

		if ( root.getAttribute( 'data-mrn-builder-precollapse' ) !== 'done' ) {
			return;
		}

		if ( root.getAttribute( 'data-mrn-repeater-precollapse' ) !== 'done' ) {
			return;
		}

		root.classList.remove( 'mrn-base-stack-admin-precollapse' );
		root.removeAttribute( 'data-mrn-builder-precollapse' );
		root.removeAttribute( 'data-mrn-repeater-precollapse' );
	}

	function getRepeaterCloneRows( $field ) {
		return $field.find( '> .acf-input > .acf-repeater > .acf-table > tbody > .acf-row.acf-clone, > .acf-input > .acf-repeater > .acf-table > .acf-tbody > .acf-row.acf-clone, > .acf-input > .acf-repeater > table > tbody > .acf-row.acf-clone, > .acf-input > .acf-repeater > .values > .acf-row.acf-clone' );
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

	function getRowBodyTargets( $row ) {
		return $row.children( '.acf-fields, td.acf-fields, td:not(.acf-row-handle):not(.acf-row-handle.order)' );
	}

	function rowContainsLiveEditor( $row ) {
		return $row.find( '.wp-editor-wrap' ).filter( function () {
			var $wrap = $( this );

			if ( ! $wrap.hasClass( 'delay' ) ) {
				return true;
			}

			return $wrap.find( '.mce-tinymce, .quicktags-toolbar' ).length > 0;
		} ).length > 0;
	}

	function canDetachRowBodies( $row ) {
		if ( rowContainsLiveEditor( $row ) ) {
			return false;
		}

		return getRowBodyTargets( $row ).filter( function () {
			return !! this && this.childNodes.length > 0;
		} ).length > 0;
	}

	function detachCloneRowBodies( $row ) {
		var snapshots = [];

		if ( $row.data( 'mrnDetachedRepeaterCloneBodies' ) ) {
			return;
		}

		getRowBodyTargets( $row ).filter( function () {
			return !! this && this.childNodes.length > 0;
		} ).each( function () {
			var target = this;
			var fragment = document.createDocumentFragment();

			while ( target.firstChild ) {
				fragment.appendChild( target.firstChild );
			}

			target.style.display = 'none';
			target.setAttribute( 'data-mrn-clone-body-detached', 'true' );
			snapshots.push( {
				target: target,
				fragment: fragment
			} );
		} );

		if ( snapshots.length ) {
			$row.data( 'mrnDetachedRepeaterCloneBodies', snapshots );
		}
	}

	function restoreCloneRowBodies( $row ) {
		var snapshots = $row.data( 'mrnDetachedRepeaterCloneBodies' ) || [];

		if ( ! snapshots.length ) {
			return;
		}

		$.each( snapshots, function ( index, snapshot ) {
			if ( ! snapshot || ! snapshot.target ) {
				return;
			}

			snapshot.target.style.display = '';
			snapshot.target.removeAttribute( 'data-mrn-clone-body-detached' );

			if ( snapshot.fragment ) {
				snapshot.target.appendChild( snapshot.fragment );
			}
		} );

		$row.removeData( 'mrnDetachedRepeaterCloneBodies' );
	}

	function unmountRow( $row ) {
		if ( ! $row || ! $row.length || $row.data( 'mrnRepeaterRowUnmounted' ) ) {
			return;
		}

		if ( typeof acf.doAction === 'function' ) {
			acf.doAction( 'unmount', $row );
		}

		$row.data( 'mrnRepeaterRowUnmounted', true );
	}

	function remountRow( $row ) {
		if ( ! $row || ! $row.length || ! $row.data( 'mrnRepeaterRowUnmounted' ) ) {
			return;
		}

		$row.removeData( 'mrnRepeaterRowUnmounted' );

		if ( typeof acf.doAction === 'function' ) {
			acf.doAction( 'remount', $row );
		}
	}

	function detachRowBodies( $row ) {
		var $targets;
		var snapshots = [];

		if ( $row.data( 'mrnDetachedRepeaterBodies' ) || ! canDetachRowBodies( $row ) ) {
			return;
		}

		$targets = getRowBodyTargets( $row ).filter( function () {
			return !! this && this.childNodes.length > 0;
		} );

		if ( ! $targets.length ) {
			return;
		}

		unmountRow( $row );

		$targets.each( function () {
			var target = this;
			var fragment;

			fragment = document.createDocumentFragment();

			while ( target.firstChild ) {
				fragment.appendChild( target.firstChild );
			}

			target.style.display = 'none';
			target.setAttribute( 'data-mrn-body-detached', 'true' );
			snapshots.push( {
				target: target,
				fragment: fragment
			} );
		} );

		if ( snapshots.length ) {
			$row.data( 'mrnDetachedRepeaterBodies', snapshots );
		}
	}

	function restoreRowBodies( $row, options ) {
		var settings = $.extend(
			{
				remount: true
			},
			options || {}
		);
		var snapshots = $row.data( 'mrnDetachedRepeaterBodies' ) || [];

		if ( ! snapshots.length ) {
			if ( settings.remount ) {
				remountRow( $row );
			}
			return;
		}

		$.each( snapshots, function ( index, snapshot ) {
			if ( ! snapshot || ! snapshot.target ) {
				return;
			}

			snapshot.target.style.display = '';
			snapshot.target.removeAttribute( 'data-mrn-body-detached' );

			if ( snapshot.fragment ) {
				snapshot.target.appendChild( snapshot.fragment );
			}
		} );

		$row.removeData( 'mrnDetachedRepeaterBodies' );

		if ( settings.remount ) {
			remountRow( $row );
		}
	}

	function syncRowBodyState( $row ) {
		if ( ! $row || ! $row.length ) {
			return;
		}

		if ( isRowCollapsed( $row ) ) {
			if ( ! $row.data( 'mrnDetachedRepeaterBodies' ) && canDetachRowBodies( $row ) ) {
				detachRowBodies( $row );
			}
			return;
		}

		restoreRowBodies( $row );
	}

	function syncRowBodyStates( context ) {
		getRepeaterFields( context ).each( function () {
			getRepeaterRows( $( this ) ).each( function () {
				syncRowBodyState( $( this ) );
			} );
		} );
	}

	function restoreAllRowBodies( context ) {
		getRepeaterFields( context ).each( function () {
			getRepeaterRows( $( this ) ).each( function () {
				restoreRowBodies( $( this ), { remount: false } );
			} );
		} );
	}

	function syncCloneRowBodyStates( context ) {
		getRepeaterFields( context ).each( function () {
			getRepeaterCloneRows( $( this ) ).each( function () {
				detachCloneRowBodies( $( this ) );
			} );
		} );
	}

	function restoreRepeaterCloneBodies( context ) {
		getRepeaterFields( context ).each( function () {
			getRepeaterCloneRows( $( this ) ).each( function () {
				restoreCloneRowBodies( $( this ) );
			} );
		} );
	}

	function isClassicPostEditorScreen() {
		var body = document.body;

		return !! body && ( body.classList.contains( 'post-php' ) || body.classList.contains( 'post-new-php' ) );
	}

	function canDirectlyCollapseRow( $row ) {
		return $row.find( '.-collapsed-target' ).length > 0;
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

	function collapseInitialRows( context ) {
		if ( ! isClassicPostEditorScreen() ) {
			return;
		}

		getRepeaterFields( context ).each( function () {
			var $field = $( this );

			if ( $field.data( 'mrnInitialCollapseDone' ) ) {
				return;
			}

			$field.data( 'mrnInitialCollapseDone', true );

			getRepeaterRows( $field ).each( function () {
				var $row = $( this );

				if ( isRowCollapsed( $row ) || ! canDirectlyCollapseRow( $row ) ) {
					if ( isRowCollapsed( $row ) ) {
						detachRowBodies( $row );
					}

					return;
				}

				$row.addClass( '-collapsed' );
				detachRowBodies( $row );
			} );
		} );
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

	$( document ).on( 'mousedown touchstart', '.acf-field[data-type="repeater"] .acf-row:not(.acf-clone) > .acf-row-handle, .acf-field[data-type="repeater"] .acf-row:not(.acf-clone) > .acf-row-handle.order, .acf-field[data-type="repeater"] .acf-row:not(.acf-clone) .acf-icon.-collapse', function () {
		var $row = $( this ).closest( '.acf-row' );

		if ( isRowCollapsed( $row ) ) {
			restoreRowBodies( $row );
		}
	} );

	$( document ).on( 'click', '.acf-field[data-type="repeater"] .acf-row:not(.acf-clone) > .acf-row-handle, .acf-field[data-type="repeater"] .acf-row:not(.acf-clone) > .acf-row-handle.order, .acf-field[data-type="repeater"] .acf-row:not(.acf-clone) .acf-icon.-collapse', function () {
		var $row = $( this ).closest( '.acf-row' );

		window.setTimeout( function () {
			syncRowBodyState( $row );
		}, 0 );
	} );

	$( document ).on( 'mousedown touchstart', '.acf-field[data-type="repeater"] .acf-actions a, .acf-field[data-type="repeater"] .acf-actions button, .acf-field[data-type="repeater"] [data-event="add-row"]', function () {
		restoreRepeaterCloneBodies( $( this ).closest( '.acf-field[data-type="repeater"]' ) );
	} );

	$( document ).on( 'click', '.acf-field[data-type="repeater"] .acf-actions a, .acf-field[data-type="repeater"] .acf-actions button, .acf-field[data-type="repeater"] [data-event="add-row"]', function () {
		var $field = $( this ).closest( '.acf-field[data-type="repeater"]' );

		window.setTimeout( function () {
			syncCloneRowBodyStates( $field );
		}, 80 );
	} );

	$( document ).on( 'submit', '#post', function () {
		restoreAllRowBodies( this );
	} );

	$( document ).on( 'heartbeat-send', function () {
		restoreAllRowBodies( document );
	} );

	$( document ).on( 'heartbeat-tick', function () {
		window.setTimeout( function () {
			syncRowBodyStates( document );
		}, 0 );
	} );

	$( function () {
		collapseInitialRows( document );
		syncCloneRowBodyStates( document );
		markInitialPrecollapseReady( 'data-mrn-repeater-precollapse' );
	} );

	acf.addAction( 'append', function ( $el ) {
		var context = $el || document;

		syncRowBodyStates( context );
		syncCloneRowBodyStates( context );
	} );

	$( document ).on( 'mouseenter focusin', '.acf-field[data-type="repeater"]', function () {
		ensureToolbar( $( this ) );
	} );
}( jQuery, window.acf ) );
