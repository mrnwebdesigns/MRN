( function ( $, acf ) {
	'use strict';

	if ( typeof $ === 'undefined' || typeof acf === 'undefined' ) {
		return;
	}

	// Keep this off by default to avoid costly submit/heartbeat restore passes on very large editors.
	var enableRowBodyDetachment = false;

	function isInitialRepeaterCollapseEnabled() {
		return !! (
			window.mrnBaseStackBuilderAdmin &&
			window.mrnBaseStackBuilderAdmin.initialCollapseEnabled
		);
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
		return $row.find( '.wp-editor-wrap' ).length > 0;
	}

	function canDetachRowBodies( $row ) {
		if ( ! enableRowBodyDetachment ) {
			return false;
		}

		// Table-based repeater rows rely on native cell layout. Detaching table-cell
		// contents can collapse column widths and cause hover/collapse jitter.
		if ( $row.is( 'tr' ) ) {
			return false;
		}

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

	function getDetachedRows( context ) {
		return $( context || document )
			.find( '[data-mrn-body-detached="true"]' )
			.closest( '.acf-row' )
			.not( '.acf-clone' );
	}

	function restoreAllRowBodies( context ) {
		var $detachedRows = getDetachedRows( context );

		if ( ! $detachedRows.length ) {
			return;
		}

		$detachedRows.each( function () {
			restoreRowBodies( $( this ), { remount: false } );
		} );
	}

	function syncCloneRowBodyStates( context ) {
		getRepeaterFields( context ).each( function () {
			getRepeaterCloneRows( $( this ) ).each( function () {
				restoreCloneRowBodies( $( this ) );
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

	function expandNewestRepeaterRow( $field ) {
		var $rows = getRepeaterRows( $field );
		var $row = $rows.last();

		if ( ! $row.length ) {
			return;
		}

		if ( isRowCollapsed( $row ) ) {
			setRowCollapsed( $row, false );
			$row.removeClass( '-collapsed collapsed' );
		}

		restoreRowBodies( $row );
		syncRowBodyState( $row );
	}

	function isClassicPostEditorScreen() {
		var body = document.body;

		return !! body && ( body.classList.contains( 'post-php' ) || body.classList.contains( 'post-new-php' ) );
	}

		var initialRepeaterCollapseQueue = [];
		var initialRepeaterCollapseScheduled = false;
		var initialRepeaterPrecollapseReadyMarked = false;
		var deferCollapseUntil = 0;
		var interactionQuietPeriodMs = 900;
		var interactionRetryDelayMs = 220;
		var inputEditingRetryDelayMs = 260;
		var maxInitialRepeaterCollapseRows = 160;

	function markEditorInteraction() {
		var now = window.performance && typeof window.performance.now === 'function'
			? window.performance.now()
			: Date.now();

		deferCollapseUntil = now + interactionQuietPeriodMs;

		// Stop background initial collapsing after first user interaction.
		if ( initialRepeaterCollapseQueue.length ) {
			initialRepeaterCollapseQueue.length = 0;
			markRepeaterPrecollapseReady();
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

	function markRepeaterPrecollapseReady() {
		if ( initialRepeaterPrecollapseReadyMarked ) {
			return;
		}

		initialRepeaterPrecollapseReadyMarked = true;
		markInitialPrecollapseReady( 'data-mrn-repeater-precollapse' );
	}

	function scheduleInitialRepeaterCollapse() {
		if ( initialRepeaterCollapseScheduled ) {
			return;
		}

		initialRepeaterCollapseScheduled = true;

		if ( typeof window.requestAnimationFrame === 'function' ) {
			window.requestAnimationFrame( processInitialRepeaterCollapseQueue );
			return;
		}

		window.setTimeout( processInitialRepeaterCollapseQueue, 0 );
	}

	function processInitialRepeaterCollapseQueue() {
		var processed = 0;
		var maxPerPass = 3;
		var start = window.performance && typeof window.performance.now === 'function' ? window.performance.now() : 0;
		var maxDuration = 4;

		initialRepeaterCollapseScheduled = false;

		if ( ! initialRepeaterCollapseQueue.length ) {
			markRepeaterPrecollapseReady();
			return;
		}

		if ( shouldDeferCollapseForInteraction() ) {
			window.setTimeout( scheduleInitialRepeaterCollapse, interactionRetryDelayMs );
			return;
		}

		if ( isEditingInputControl() ) {
			window.setTimeout( scheduleInitialRepeaterCollapse, inputEditingRetryDelayMs );
			return;
		}

		while ( initialRepeaterCollapseQueue.length ) {
			var rowElement = initialRepeaterCollapseQueue.shift();
			var $row = $( rowElement );

			if ( ! $row.length || isRowCollapsed( $row ) ) {
				continue;
			}

			// Use ACF's own toggle path so collapsed state remains consistent.
			setRowCollapsed( $row, true );

			processed += 1;

			if ( processed >= maxPerPass ) {
				break;
			}

			if ( start && window.performance.now() - start >= maxDuration ) {
				break;
			}
		}

		if ( initialRepeaterCollapseQueue.length ) {
			scheduleInitialRepeaterCollapse();
			return;
		}

		markRepeaterPrecollapseReady();
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
		var queueCapped = false;

		if ( ! isInitialRepeaterCollapseEnabled() ) {
			markRepeaterPrecollapseReady();
			return;
		}

		if ( ! isClassicPostEditorScreen() ) {
			markRepeaterPrecollapseReady();
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

					if ( isRowCollapsed( $row ) ) {
						return;
					}

					if ( initialRepeaterCollapseQueue.length >= maxInitialRepeaterCollapseRows ) {
						queueCapped = true;
						return false;
					}

					initialRepeaterCollapseQueue.push( this );
				} );

				if ( queueCapped ) {
					return false;
				}
			} );

			scheduleInitialRepeaterCollapse();
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
		var $action = $( this );
		var $field = $( this ).closest( '.acf-field[data-type="repeater"]' );
		var isAddRowAction = $action.is( '[data-event="add-row"]' );

		restoreRepeaterCloneBodies( $field );

		window.setTimeout( function () {
			syncCloneRowBodyStates( $field );
		}, 80 );

		if ( isAddRowAction ) {
			window.setTimeout( function () {
				expandNewestRepeaterRow( $field );
			}, 120 );
		}
	} );

	$( document ).on( 'keydown', '.acf-field[data-type="repeater"] .acf-actions a, .acf-field[data-type="repeater"] .acf-actions button, .acf-field[data-type="repeater"] [data-event="add-row"]', function ( event ) {
		if ( 'Enter' !== event.key && ' ' !== event.key ) {
			return;
		}

		restoreRepeaterCloneBodies( $( this ).closest( '.acf-field[data-type="repeater"]' ) );
	} );

	$( document ).on( 'submit', '#post', function () {
		if ( ! enableRowBodyDetachment ) {
			return;
		}

		restoreAllRowBodies( this );
	} );

	var pendingHeartbeatResync = false;

	$( document ).on( 'heartbeat-send', function () {
		if ( ! enableRowBodyDetachment ) {
			pendingHeartbeatResync = false;
			return;
		}

		if ( ! getDetachedRows( document ).length ) {
			pendingHeartbeatResync = false;
			return;
		}

		try {
			restoreAllRowBodies( document );
			pendingHeartbeatResync = true;
		} catch ( error ) {
			pendingHeartbeatResync = false;
		}
	} );

	$( document ).on( 'heartbeat-tick', function () {
		if ( ! enableRowBodyDetachment ) {
			pendingHeartbeatResync = false;
			return;
		}

		if ( ! pendingHeartbeatResync ) {
			return;
		}

		pendingHeartbeatResync = false;

		window.setTimeout( function () {
			try {
				syncRowBodyStates( document );
			} catch ( error ) {
				/* no-op: never let repeater sync block heartbeat processing */
			}
		}, 0 );
	} );

	$( function () {
		collapseInitialRows( document );
		refreshToolbars( document );
		syncCloneRowBodyStates( document );
	} );

	acf.addAction( 'append', function ( $el ) {
		var context = $el || document;

		syncRowBodyStates( context );
		syncCloneRowBodyStates( context );
		refreshToolbars( context );
	} );

	$( document ).on(
		'mousedown touchstart keydown',
		'#post input, #post textarea, #post select, #post [contenteditable="true"]',
		function () {
			markEditorInteraction();
		}
	);

	$( document ).on( 'mouseenter', '.acf-field[data-type="repeater"]', function () {
		ensureToolbar( $( this ) );
	} );
}( jQuery, window.acf ) );
