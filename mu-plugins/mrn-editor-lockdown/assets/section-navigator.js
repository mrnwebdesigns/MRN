/* global acf, mrnSectionNavigator */
( function() {
	'use strict';
	if ( ! window.acf || ! window.mrnSectionNavigator ) { return; }
	var reconciled = false;
	var nativeReady = document.readyState === 'complete';
	var labels = mrnSectionNavigator, rows = [], fields = [], activeRow, mode = 'content', overview = false;
	var panel, list, search, status, empty, errorFilter, mobileToggle, modeButtons, viewButton, position, previous, next, hint;
	var observer, titleObserver, noticeObserver, frame = 0, refreshFrame = 0, dirtyRows = new Set(), moving = false, sorting = false, errorsOnly = false, mounted = false, lastInvoker, pendingInvalid = false;
	var richEditors = new WeakSet(), nestedRows = new WeakSet(), addedNestedRows = new WeakSet(), bandFrame = 0;
	var observed = new WeakSet(), captions = new WeakMap(), remembered = '';
	function rememberSection() {
		var item = rows.find( function( entry ) { return entry.row === activeRow; } ), id = document.getElementById( 'post_ID' );
		if ( item && id ) { try { var value = JSON.stringify( { field: item.field, index: item.index } ); if ( remembered === value ) { return; } window.sessionStorage.setItem( 'mrn-editor-section:' + id.value, value ); remembered = value; } catch ( error ) { /* Storage is optional. */ } }
	}
	function restoreSection() {
		var id = document.getElementById( 'post_ID' );
		try {
			var saved = id && JSON.parse( window.sessionStorage.getItem( 'mrn-editor-section:' + id.value ) );
			return saved && rows.find( function( item ) { return item.field === saved.field && item.index === saved.index; } );
		} catch ( error ) { return null; }
	}
	function element( tag, className, text ) {
		var node = document.createElement( tag ); node.className = className || '';
		if ( text ) { node.textContent = text; } return node;
	}
	function button( text, className ) { var node = element( 'button', className, text ); node.type = 'button'; return node; }
	function preservePanelPreferences() {
		// Core derives hidden preferences from :hidden, which also matches panels
		// parked by Content/Page settings. Only explicit native checkboxes should
		// change those preferences. Keep core's request, nonce and callback intact.
		window.jQuery.ajaxPrefilter( function( options, original ) {
			var data = original.data;
			if ( ! document.body.classList.contains( 'mrn-workspace' ) || ! data || typeof data !== 'object'
				|| data.action !== 'closed-postboxes' || ! window.postboxes || data.page !== window.postboxes.page
				|| options.url !== window.ajaxurl || typeof options.data !== 'string' ) { return; }
			var hidden = new Set( String( data.hidden || '' ).split( ',' ).filter( Boolean ) );
			document.querySelectorAll( '#screen-options-wrap .hide-postbox-tog' ).forEach( function( toggle ) {
				if ( toggle.checked ) { hidden.delete( toggle.value ); }
				else { hidden.add( toggle.value ); }
			} );
			var payload = new URLSearchParams( options.data );
			payload.set( 'hidden', Array.from( hidden ).join( ',' ) ); options.data = payload.toString();
		} );
	}
	// ACF's declared widths and -c0 markers identify field rows. Reuse that
	// metadata only on selection, visibility changes and resize: no geometry reads, descendant scans, or per-keystroke work.
	function queueFieldBands() {
		if ( ! mounted || ! activeRow || mode !== 'content' || overview || bandFrame ) { return; }
		bandFrame = window.requestAnimationFrame( function() {
			bandFrame = 0;
			if ( ! activeRow || ! activeRow.isConnected || mode !== 'content' || overview ) { return; }
			var index = -1, used = 0;
			activeRow.querySelectorAll( ':scope > .acf-fields > .acf-field' ).forEach( function( field ) {
				var hidden = field.matches( '.acf-hidden,.hidden,.acf-field-tab,.-endpoint,[hidden]' ) || field.style.display === 'none';
				if ( hidden ) { return; } // Keep parked tab decoration; avoid needless class mutations.
				var width = Math.min( 100, Math.max( 1, parseFloat( field.dataset.width ) || 100 ) );
				if ( index < 0 || used + width > 100.01 || ( used > 0 && field.classList.contains( '-c0' ) ) ) { index++; used = 0; }
				used += width;
				field.classList.toggle( 'mrn-field-band', index % 2 === 1 );
			} );
		} );
	}
	function visibleFieldChanged( field ) {
		var node = field && field.$el && field.$el[ 0 ];
		if ( mounted && activeRow && node && node.parentElement && node.parentElement.parentElement === activeRow ) { queueFieldBands(); }
	}
	function owner( row ) { return acf.getField( window.jQuery( row.closest( '.acf-field[data-type="flexible_content"]' ) ) ); }
	function eligibleFields() {
		return acf.getFields( { type: 'flexible_content' } ).filter( function( field ) {
			return String( field.get( 'key' ) ).indexOf( 'field_mrn_' ) !== -1
				&& ! field.$el.parents( '.acf-clone, .acf-field[data-type="flexible_content"], .acf-field[data-type="repeater"]' ).length;
		} );
	}
	function meaningfulTitle( row ) {
		var nativeTitle = row.querySelector( '.acf-fc-layout-title' );
		if ( row.getAttribute( 'data-renamed' ) === '1' && nativeTitle ) { return nativeTitle.textContent.trim(); }
		var names = [ 'internal_name', 'heading', 'label' ];
		for ( var n = 0; n < names.length; n++ ) {
			var candidates = row.querySelectorAll( '.acf-field[data-name="' + names[ n ] + '"]' );
			for ( var k = 0; k < candidates.length; k++ ) {
				if ( candidates[ k ].closest( '.layout' ) !== row ) { continue; }
				var input = candidates[ k ].querySelector( 'input[type="text"], textarea:not(.wp-editor-area)' );
				if ( input && input.value.trim() ) { return input.value.trim().replace( /\s+/g, ' ' ).slice( 0, 160 ); }
			}
		}
		var fallback = nativeTitle ? nativeTitle.textContent.trim() : labels.untitled;
		return fallback.indexOf( '|' ) === -1 ? fallback : fallback.split( ' - ' )[ 0 ];
	}
	function reveal( node ) {
		if ( ! nativeReady ) { return; }
		var ancestors = [], current = node;
		while ( current && current.closest( '.mrn-workspace-field' ) ) { ancestors.unshift( current ); current = current.parentElement; }
		ancestors.forEach( function( parent ) {
			var field;
			if ( parent.matches( '.layout' ) ) {
				field = owner( parent );
				if ( field && parent.classList.contains( '-collapsed' ) ) { field.openLayout( window.jQuery( parent ) ); }
			} else if ( parent.matches( '.acf-row' ) ) {
				field = acf.getField( window.jQuery( parent.closest( '.acf-field-repeater' ) ) );
				if ( field && typeof field.expand === 'function' && parent.classList.contains( '-collapsed' ) ) { field.expand( window.jQuery( parent ) ); }
			}
			if ( parent.matches( '.acf-field' ) ) {
				field = acf.getField( window.jQuery( parent ) );
				// Use ACF's own tab activation, including nested tabs. Conditional logic stays authoritative.
				if ( field && field.hiddenByTab && typeof field.hiddenByTab.toggle === 'function' ) { field.hiddenByTab.toggle(); }
			}
		} );
	}
	function plainText( value ) {
		var doc = new DOMParser().parseFromString( value, 'text/html' );
		return doc.body.textContent.replace( /\s+/g, ' ' ).trim();
	}
	function contentEntries( row ) {
		var entries = [];
		row.querySelectorAll( '.acf-field' ).forEach( function( field ) {
			if ( field.closest( '.acf-clone' ) || ! /^(text|textarea|wysiwyg|link|url|email)$/.test( field.dataset.type || '' )
				|| /(^|_)(class|classes|anchor|css|export|style|color|internal_name|id)($|_)/.test( field.dataset.name || '' ) ) { return; }
			var controls = Array.from( field.querySelectorAll( 'input[type="text"],input[type="url"],input[type="email"],textarea,input.input-title,input.input-url' ) ).filter( function( input ) {
				return input.closest( '.acf-field' ) === field && input.name && ! input.disabled;
			} );
			controls.forEach( function( input ) {
				var editor = window.tinymce && window.tinymce.get( input.id );
				var text = field.dataset.type === 'wysiwyg' ? ( editor && editor.initialized && ! editor.isHidden() ? editor.getContent( { format: 'text' } ).replace( /\s+/g, ' ' ).trim() : plainText( input.value ) ) : input.value.trim();
				if ( ! text ) { return; }
				var label = field.querySelector( ':scope > .acf-label label' );
				var context = '', nested = field.closest( '.acf-row' );
				if ( nested ) {
					var repeater = nested.closest( '.acf-field-repeater' ), parentLabel = repeater && repeater.querySelector( ':scope > .acf-label label' );
					var siblings = Array.from( nested.parentElement.children ).filter( function( item ) { return item.matches( '.acf-row:not(.acf-clone)' ); } );
					context = ( parentLabel ? parentLabel.textContent.trim() : labels.component ) + ' ' + ( siblings.indexOf( nested ) + 1 ) + ' · ';
				}
				entries.push( { input: input, field: field, text: text, lower: text.toLocaleLowerCase(), label: context + ( label ? label.textContent.trim() : field.dataset.name ) } );
			} );
		} );
		return entries;
	}
	function selectResult( item ) {
		select( item.row, ! item.match );
		if ( ! item.match ) { return; }
		var match = item.match; reveal( match.field );
		var editor = window.tinymce && window.tinymce.get( match.input.id );
		if ( editor && editor.initialized && ! editor.isHidden() ) { editor.focus(); }
		else {
			var target = match.input;
			if ( target.type === 'hidden' ) { target = Array.from( match.field.querySelectorAll( '.acf-link [data-name="edit"],.acf-link [data-name="add"]' ) ).find( function( control ) { return control.getClientRects().length; } ); }
			if ( target && ! target.disabled && target.getClientRects().length ) { target.focus( { preventScroll: true } ); }
		}
		match.field.scrollIntoView( { block: 'start', behavior: 'instant' } );
	}
	function nestedComponents( row ) {
		row.querySelectorAll( '.acf-field-repeater' ).forEach( function( container ) {
			if ( container.closest( '.acf-clone' ) ) { return; }
			var field = acf.getField( window.jQuery( container ) );
			if ( ! field || typeof field.collapse !== 'function' || typeof field.expand !== 'function' ) { return; }
			var nativeRows = field.$rows().toArray(), label = container.querySelector( ':scope > .acf-label' );
			// Block/row repeaters have a native fields cell. Table repeaters retain their column layout.
			var supported = nativeRows.filter( function( item ) { return item.querySelector( ':scope > td.acf-fields' ); } );
			if ( ! supported.length || ! label ) { return; }
			container.classList.add( 'mrn-nested-component' );
			var toggle = label.querySelector( '.mrn-nested-all' );
			if ( ! toggle ) {
				toggle = button( labels.collapseRows, 'mrn-nested-all' ); label.appendChild( toggle );
			}
			function update() {
				var currentRows = field.$rows().toArray(), collapsed = currentRows.every( function( item ) { return item.classList.contains( '-collapsed' ); } );
				toggle.textContent = ( collapsed ? labels.expandRows : labels.collapseRows ) + ' (' + currentRows.length + ')';
				var title = label.querySelector( 'label' ); toggle.setAttribute( 'aria-label', ( title ? title.textContent.trim() + ': ' : '' ) + toggle.textContent );
			}
			supported.forEach( function( item, index ) {
				var cell = item.querySelector( ':scope > td.acf-fields' ), header = cell.querySelector( ':scope > .mrn-nested-header' );
				if ( ! header ) {
					header = button( '', 'mrn-nested-header' ); cell.prepend( header );
				}
				var title;
				[ '.-collapsed-target', '[data-name="item_label"]', '[data-name="card_name"]', '[data-name="heading"]', '[data-name="question"]', '[data-name="title"]', '[data-name="label"]', '[data-name="internal_name"]' ].some( function( selector ) {
					title = Array.from( item.querySelectorAll( selector + ' input[type="text"]' ) ).find( function( input ) { return input.closest( '.acf-row' ) === item && ! input.disabled && input.value.trim(); } );
					return Boolean( title );
				} );
				var name = label.querySelector( 'label' ), text = ( index + 1 ) + ' · ' + ( title ? title.value.trim().slice( 0, 120 ) : ( name ? name.textContent.trim() : labels.component ) );
				if ( header.textContent !== text ) { header.textContent = text; }
				function state() { header.setAttribute( 'aria-expanded', String( ! item.classList.contains( '-collapsed' ) ) ); update(); }
				if ( ! nestedRows.has( item ) ) {
					nestedRows.add( item );
					// Existing subsections start compact once; new rows and editing choices stay open.
					if ( ! addedNestedRows.has( item ) && ! item.classList.contains( '-collapsed' ) ) { field.collapse( window.jQuery( item ) ); }
					new MutationObserver( state ).observe( item, { attributes: true, attributeFilter: [ 'class' ] } );
				}
				state();
			} );
			update();
		} );
	}
	function rootRow( node ) { return rows.find( function( item ) { return item.row === node || item.row.contains( node ); } ); }
	function scrollSelection() {
		var item = rows.find( function( entry ) { return entry.row === activeRow; } );
		if ( ! item || ! panel.getClientRects().length ) { return; }
		var bounds = panel.getBoundingClientRect(), link = item.link.getBoundingClientRect();
		var top = bounds.top + panel.querySelector( '.mrn-outline-controls' ).offsetHeight + 24;
		if ( link.bottom > bounds.bottom - 12 ) { panel.scrollTop += link.bottom - bounds.bottom + 12; }
		else if ( link.top < top ) { panel.scrollTop -= top - link.top; }
	}
	function openPanel( invoker ) {
		lastInvoker = invoker || document.activeElement;
		document.body.classList.add( 'mrn-outline-open' ); mobileToggle.setAttribute( 'aria-expanded', 'true' );
		scrollSelection(); search.focus( { preventScroll: true } );
	}
	function closePanel( restore ) {
		document.body.classList.remove( 'mrn-outline-open' ); mobileToggle.setAttribute( 'aria-expanded', 'false' );
		if ( restore && lastInvoker && lastInvoker.isConnected ) { lastInvoker.focus(); }
	}
	function select( row, focus ) {
		if ( ! row || ! row.isConnected ) { schedule(); return; }
		activeRow = row; mode = 'content'; overview = false;
		applyView(); reveal( row ); nestedComponents( row ); scrollSelection(); closePanel( false ); rememberSection();
		if ( focus ) {
			var heading = row.querySelector( '.acf-fc-layout-handle' );
			heading.setAttribute( 'tabindex', '-1' ); heading.focus( { preventScroll: true } );
			document.getElementById( 'mrn-editor-tools' ).scrollIntoView( { block: 'start', behavior: 'instant' } );
		}
	}
	function applyView() {
		document.body.classList.toggle( 'mrn-workspace-settings', mode === 'settings' );
		document.body.classList.toggle( 'mrn-workspace-overview', overview );
		document.body.classList.toggle( 'mrn-workspace-empty', rows.length === 0 );
		modeButtons.forEach( function( item ) { item.button.setAttribute( 'aria-pressed', String( item.mode === mode ) ); } );
		var active = rows.findIndex( function( item ) { return item.row === activeRow; } );
		rows.forEach( function( item ) {
			item.row.classList.toggle( 'mrn-section-parked', ! overview && item.row !== activeRow );
			if ( item.row === activeRow ) { item.link.setAttribute( 'aria-current', 'location' ); }
			else { item.link.removeAttribute( 'aria-current' ); }
		} );
		fields.forEach( function( field ) {
			var box = field.$el[ 0 ].closest( '.postbox' );
			if ( box ) { box.classList.toggle( 'mrn-region-parked', rows.length > 0 && ! overview && ( ! activeRow || ! field.$el[ 0 ].contains( activeRow ) ) ); }
		} );
		var positionText = active < 0 ? '0 / ' + rows.length : ( active + 1 ) + ' / ' + rows.length;
		if ( position.textContent !== positionText ) { position.textContent = positionText; }
		previous.disabled = active <= 0; next.disabled = active < 0 || active >= rows.length - 1;
		var moves = document.querySelectorAll( '.mrn-section-move' ), selected = rows[ active ];
		if ( moves.length ) { moves[ 0 ].disabled = moving || ! selected || ! rows[ active - 1 ] || rows[ active - 1 ].field !== selected.field; moves[ 1 ].disabled = moving || ! selected || ! rows[ active + 1 ] || rows[ active + 1 ].field !== selected.field; }
		viewButton.textContent = overview ? labels.focus : labels.overview;
		viewButton.setAttribute( 'aria-pressed', String( overview ) );
		hint.textContent = mode === 'settings' ? labels.settingsHint : ( ! rows.length ? labels.startHint : ( overview ? labels.arrangeHint : labels.hint ) );
		queueFieldBands();
	}
	function filter() {
		var query = search.value.trim().toLocaleLowerCase(), count = 0, errors = rows.filter( function( item ) { return item.errors; } ).length;
		if ( ! errors ) { errorsOnly = false; }
		rows.forEach( function( item ) {
			item.match = null;
			var titleMatch = item.text.toLocaleLowerCase().indexOf( query ) !== -1;
			if ( query && ! titleMatch ) {
				if ( ! item.searchEntries ) { item.searchEntries = contentEntries( item.row ); }
				item.match = item.searchEntries.find( function( entry ) { return ! entry.input.disabled && entry.lower.indexOf( query ) !== -1; } );
			}
			var excerpt = item.link.querySelector( '.mrn-section-match' );
			if ( item.match ) {
				if ( ! excerpt ) { excerpt = element( 'span', 'mrn-section-match' ); item.link.appendChild( excerpt ); }
				var start = Math.max( 0, item.match.lower.indexOf( query ) - 25 );
				excerpt.textContent = item.match.label + ': ' + ( start ? '…' : '' ) + item.match.text.slice( start, start + 100 );
			} else if ( excerpt ) { excerpt.remove(); }
			var match = ( ! errorsOnly || item.errors ) && ( ! query || titleMatch || Boolean( item.match ) );
			item.item.hidden = ! match; if ( match ) { count++; }
		} );
		list.querySelectorAll( '.mrn-section-group' ).forEach( function( group ) {
			// Empty eligible regions still own an Add button. Filtering a populated
			// page may hide unmatched groups, but an empty page must remain usable.
			group.hidden = rows.length > 0 && Boolean( query || errorsOnly ) && ! group.querySelector( 'ol > li:not([hidden])' );
		} );
		var statusText = count + ' / ' + rows.length + ' sections';
		if ( status.textContent !== statusText ) { status.textContent = statusText; }
		empty.textContent = rows.length ? labels.empty : labels.start;
		empty.hidden = count !== 0;
		errorFilter.hidden = ! errors; errorFilter.textContent = errorsOnly ? labels.all : labels.errors + ' (' + errors + ')';
		errorFilter.setAttribute( 'aria-pressed', String( errorsOnly ) );
		mobileToggle.textContent = labels.title + ( errors ? ' · ' + errors + ' ' + labels.errors : '' );
	}
	function decorate( row, title, type ) {
		var handle = row.querySelector( '.acf-fc-layout-handle' ); if ( ! handle ) { return; }
		var nativeTitle = handle.querySelector( '.acf-fc-layout-title' );
		if ( nativeTitle ) { titleObserver.observe( nativeTitle, { childList: true, subtree: true, characterData: true } ); }
		var summary = handle.querySelector( '.mrn-section-summary' );
		if ( ! summary ) { summary = element( 'span', 'mrn-section-summary' ); handle.appendChild( summary ); }
		summary.setAttribute( 'role', 'heading' ); summary.setAttribute( 'aria-level', '2' );
		var caption = captions.get( row );
		if ( ! caption || ! caption.isConnected ) { caption = handle.querySelector( '.mrn-section-caption' ) || element( 'span', 'mrn-section-caption' ); summary.appendChild( caption ); captions.set( row, caption ); }
		if ( caption.textContent !== title ) { caption.textContent = title; }
		var kind = handle.querySelector( '.mrn-section-kind' ) || element( 'span', 'mrn-section-kind' );
		if ( kind.textContent !== type ) { kind.textContent = type; } if ( ! kind.isConnected ) { summary.insertBefore( kind, caption ); }
		if ( ! row.querySelector( ':scope > .mrn-section-return' ) ) { row.appendChild( button( labels.return, 'button-link mrn-section-return' ) ); }
		row.classList.add( 'mrn-editor-section' );
	}
	function addTo( field ) {
		mode = 'content'; overview = true; applyView();
		var box = field.$el[ 0 ].closest( '.postbox' ); if ( box ) { box.classList.remove( 'closed' ); }
		// The top action inserts before the first row, which does not exist when empty.
		// Use this field's native append action, excluding top and nested clone controls.
		var add = field.$el[ 0 ].querySelector( ':scope > .acf-input > .acf-flexible-content > .acf-actions:not(.acf-fc-top-actions) [data-name="add-layout"]' );
		if ( add ) { add.scrollIntoView( { block: 'center', behavior: 'instant' } ); add.click(); }
		closePanel( false );
	}
	function refreshItem( item ) {
		var title = meaningfulTitle( item.row ), errors = item.row.querySelectorAll( '.acf-error' ).length;
		decorate( item.row, title, item.type );
		var name = item.link.querySelector( '.mrn-section-name' ); if ( name.textContent !== title ) { name.textContent = title; }
		var badge = item.link.querySelector( '.mrn-section-error' );
		if ( errors && ! badge ) { item.link.appendChild( element( 'span', 'mrn-section-error', labels.errors ) ); }
		else if ( ! errors && badge ) { badge.remove(); }
		item.text = item.type + ' ' + title; item.errors = errors;
		if ( item.searchEntries ) { item.searchEntries = contentEntries( item.row ); }
		if ( item.row === activeRow ) { nestedComponents( item.row ); }
	}
	function refresh( node ) {
		var item = node && rootRow( node ); if ( item ) { dirtyRows.add( item ); }
		if ( ! refreshFrame ) { refreshFrame = requestAnimationFrame( function() {
			refreshFrame = 0; dirtyRows.forEach( function( entry ) { if ( entry.row.isConnected ) { refreshItem( entry ); } } ); dirtyRows.clear(); filter();
		} ); }
	}
	function moveSection( direction ) {
		if ( moving ) { return; }
		var item = rows.find( function( entry ) { return entry.row === activeRow; } ); if ( ! item ) { return; }
		var siblings = rows.filter( function( entry ) { return entry.field === item.field; } ), index = siblings.indexOf( item ), target = siblings[ index + direction ];
		if ( ! target ) { return; }
		// ACF 6.8 has pointer sorting but no keyboard move action. Use its same
		// sortstart/sortstop lifecycle to detach/reattach TinyMCE, then its render/change path.
		var $row = window.jQuery( item.row ), field = owner( item.row );
		moving = true; acf.doAction( 'sortstart', $row );
		if ( direction < 0 ) { target.row.before( item.row ); } else { target.row.after( item.row ); }
		field.render(); field.$input().trigger( 'change' ); acf.doAction( 'sortstop', $row );
		schedule();
		var attempts = 0; window.setTimeout( function settled() {
			var waiting = window.tinymce && Array.from( item.row.querySelectorAll( '.tmce-active:not(.delay) textarea.wp-editor-area' ) ).some( function( input ) { var editor = window.tinymce.get( input.id ); return ! editor || ! editor.initialized; } );
			if ( waiting && attempts++ < 100 ) { window.setTimeout( settled, 50 ); return; }
			moving = false; applyView(); status.textContent = labels.moved + ' ' + ( index + direction + 1 ) + ' / ' + siblings.length;
			var current = document.activeElement; if ( current && current.matches( '.mrn-section-move:disabled' ) ) { document.querySelector( '.mrn-section-move:not(:disabled)' ).focus(); }
		}, 50 );
	}
	function reconcile() {
		frame = 0; if ( sorting ) { return; } fields = eligibleFields(); if ( ! fields.length ) { document.documentElement.classList.remove( 'mrn-editor-starting' ); return; }
		if ( ! mounted ) { mount(); }
		var focused = document.activeElement, oldRows = rows, outlineScroll = panel.scrollTop, oldIndex = oldRows.findIndex( function( item ) { return item.row === activeRow; } );
		// Disconnect before re-indexing: removed layouts must not be retained by observers.
		observer.disconnect(); titleObserver.disconnect(); rows = []; list.replaceChildren();
		fields.forEach( function( field ) {
			var container = field.$layoutsWrap && field.$layoutsWrap()[ 0 ]; if ( ! container ) { return; }
			observer.observe( container, { childList: true } );
			field.$el[ 0 ].classList.add( 'mrn-workspace-field' );
			var box = field.$el[ 0 ].closest( '.postbox' );
			if ( box ) {
				box.classList.add( 'mrn-workspace-region' ); box.classList.remove( 'closed' );
				// Content regions are managed by the workspace, not optional settings
				// panels. Restore only native hidden-box preferences; preserve ACF's
				// conditional field classes and keep every field in the original form.
				var preference = document.getElementById( box.id + '-hide' );
				if ( preference ) {
					if ( ! preference.checked ) { box.classList.remove( 'hide-if-js' ); box.style.removeProperty( 'display' ); preference.checked = true; }
					var preferenceLabel = preference.closest( 'label' );
					if ( preferenceLabel ) { preferenceLabel.classList.add( 'mrn-managed-region-toggle' ); }
				}
			}
			var label = field.$el.children( '.acf-label' ).text().trim() || field.get( 'name' );
			var group = element( 'li', 'mrn-section-group' ), header = element( 'div', 'mrn-group-heading' ), groupList = element( 'ol' );
			var add = button( '+ ' + labels.add, 'mrn-region-add' ); add.setAttribute( 'aria-label', labels.add + ': ' + label ); add.title = labels.add + ': ' + label;
			add.addEventListener( 'click', function() { addTo( field ); } );
			header.append( element( 'h3', '', label ), add ); group.append( header, groupList ); list.appendChild( group );
			Array.from( container.children ).filter( function( row ) { return row.matches( '.layout[data-layout]:not(.acf-clone):not(.ui-sortable-placeholder)' ); } ).forEach( function( row, index ) {
				var type = row.getAttribute( 'data-label' ) || row.getAttribute( 'data-layout' ) || labels.untitled;
				if ( type.indexOf( '|' ) !== -1 ) { type = type.split( ' - ' )[ 0 ]; }
				var entry = oldRows.find( function( item ) { return item.row === row; } );
				if ( ! entry ) {
					var item = element( 'li' ), link = button( '', 'mrn-section-link' );
					link.append( element( 'span', 'mrn-section-type' ), element( 'span', 'mrn-section-name' ) );
					link.addEventListener( 'click', function() { selectResult( entry ); } ); item.appendChild( link );
					entry = { row: row, link: link, item: item };
				}
				entry.type = type; entry.field = field.get( 'key' ); entry.index = index;
				entry.link.querySelector( '.mrn-section-type' ).textContent = ( index + 1 ) + ' · ' + type;
				refreshItem( entry ); groupList.appendChild( entry.item ); rows.push( entry );
			} );
		} );
		var added = rows.find( function( item ) { return ! oldRows.some( function( old ) { return old.row === item.row; } ); } );
		// Initial hydration must retain the saved section; the first appended row
		// on an already-mounted empty page should enter focused editing directly.
		if ( ! oldRows.length && ! document.body.classList.contains( 'mrn-workspace-empty' ) ) { added = null; }
		if ( added ) { select( added.row, false ); }
		else if ( ! activeRow || ! activeRow.isConnected ) { var restoredSection = ! oldRows.length && restoreSection(); activeRow = restoredSection ? restoredSection.row : ( rows.length ? rows[ Math.max( 0, Math.min( oldIndex, rows.length - 1 ) ) ].row : null ); }
		filter(); applyView(); if ( activeRow && ! overview && mode === 'content' ) { reveal( activeRow ); nestedComponents( activeRow ); }
		if ( ! added ) { panel.scrollTop = outlineScroll; } rememberSection();
		reconciled = true; updateChromeOffset();
		if ( focused && focused.isConnected && focused.matches( '.mrn-section-link' ) ) { focused.focus( { preventScroll: true } ); }
	}
	function schedule() { if ( sorting ) { return; } if ( ! frame ) { frame = window.requestAnimationFrame( reconcile ); } }
	function updateChromeOffset() {
		var bar = document.getElementById( 'mrn-universal-sticky-toolbar' ), adminBar = document.getElementById( 'wpadminbar' );
		var header = document.getElementById( 'mrn-workspace-header' );
		if ( header ) {
			document.body.style.setProperty( '--mrn-workspace-header-height', header.offsetHeight + 'px' );
			if ( window.ResizeObserver && ! observed.has( header ) ) { observed.add( header ); new ResizeObserver( updateChromeOffset ).observe( header ); }
		}
		var offset = adminBar ? adminBar.offsetHeight : 0;
		document.body.style.setProperty( '--mrn-adminbar-height', offset + 'px' );
		if ( adminBar && window.ResizeObserver && ! observed.has( adminBar ) ) { observed.add( adminBar ); new ResizeObserver( updateChromeOffset ).observe( adminBar ); }
		if ( bar ) {
			offset += bar.offsetHeight;
			if ( window.ResizeObserver && ! observed.has( bar ) ) { observed.add( bar ); new ResizeObserver( updateChromeOffset ).observe( bar ); }
		}
		document.body.style.setProperty( '--mrn-editor-offset', offset + 'px' );
		if ( reconciled && ( bar || ! document.querySelector( 'script[src*="/mrn-universal-sticky-bar/assets/universal-sticky-bar.js"]' ) ) ) {
			document.documentElement.classList.remove( 'mrn-editor-starting' );
		}
		queueFieldBands();
	}
	function collectNotices() {
		var wrap = document.querySelector( '#poststuff' ).closest( '.wrap' );
		var notices = Array.from( wrap.querySelectorAll( ':scope > .notice, :scope > .updated, :scope > .error' ) );
		var body = document.getElementById( 'wpbody-content' );
		notices = notices.concat( Array.from( body.querySelectorAll( ':scope > .notice, :scope > .updated, :scope > .error, :scope > .update-nag' ) ) ).filter( function( notice ) {
			return ! notice.classList.contains( 'hidden' ) && getComputedStyle( notice ).display !== 'none';
		} );
		var alerts = wrap.querySelector( ':scope > .mrn-workspace-alerts' );
		if ( ! notices.length && ! alerts ) { return; }
		if ( ! alerts ) {
			alerts = element( 'details', 'mrn-workspace-alerts' ); alerts.appendChild( element( 'summary' ) );
			wrap.insertBefore( alerts, document.getElementById( 'post' ) );
			noticeObserver.observe( alerts, { childList: true } );
		}
		// Keep original notice controls/events; no form field or metabox is relocated.
		notices.forEach( function( notice ) { if ( notice.matches( '.notice-error, .error, .updated, .notice-success' ) ) { alerts.open = true; } alerts.appendChild( notice ); } );
		var count = alerts.children.length - 1, summary = alerts.querySelector( 'summary' );
		var text = labels.alerts + ' (' + count + ')'; if ( summary.textContent !== text ) { summary.textContent = text; }
		if ( ! count ) { alerts.remove(); }
	}
	function mount() {
		mounted = true; observer = new MutationObserver( schedule ); titleObserver = new MutationObserver( function( changes ) { changes.forEach( function( change ) { refresh( change.target.parentElement ); } ); } ); document.body.classList.add( 'mrn-editor-experience', 'mrn-workspace' );
		var header = element( 'div', 'mrn-workspace-header' ); header.id = 'mrn-workspace-header';
		var menu = element( 'a', 'mrn-workspace-button mrn-site-menu', labels.menu ), back = element( 'a', 'mrn-workspace-back', '← ' + labels.back ); back.href = labels.backUrl;
		menu.href = '#adminmenumain'; menu.setAttribute( 'role', 'button' ); menu.setAttribute( 'aria-controls', 'adminmenumain' ); menu.setAttribute( 'aria-expanded', 'false' );
		var modes = element( 'div', 'mrn-workspace-modes' ); modes.setAttribute( 'role', 'group' ); modes.setAttribute( 'aria-label', 'Workspace' );
		modeButtons = [ { mode: 'content', button: button( labels.content, 'mrn-mode-content' ) }, { mode: 'settings', button: button( labels.settings, 'mrn-mode-settings' ) } ];
		modeButtons.forEach( function( item ) { item.button.addEventListener( 'click', function() { mode = item.mode; applyView(); window.scrollTo( { top: 0, behavior: 'instant' } ); } ); modes.appendChild( item.button ); } );
		header.append( menu, back, modes ); document.getElementById( 'wpbody-content' ).prepend( header );
		panel = element( 'nav', 'mrn-section-outline' ); panel.id = 'mrn-section-outline'; panel.setAttribute( 'aria-label', labels.title );
		var outlineHeader = element( 'div', 'mrn-outline-header' ), close = button( labels.close, 'mrn-outline-close' );
		close.addEventListener( 'click', function() { closePanel( true ); } ); outlineHeader.append( element( 'h2', '', labels.title ), close );
		var searchLabel = element( 'label', 'screen-reader-text', labels.search ); search = element( 'input' ); search.type = 'search'; search.id = 'mrn-section-search'; search.placeholder = labels.search; searchLabel.htmlFor = search.id;
		search.addEventListener( 'input', filter ); search.addEventListener( 'keydown', function( event ) {
			if ( event.key === 'Enter' ) { event.preventDefault(); var match = rows.find( function( item ) { return ! item.item.hidden; } ); if ( match ) { selectResult( match ); } }
		} );
		status = element( 'p', 'mrn-outline-status' ); status.setAttribute( 'role', 'status' );
		errorFilter = button( '', 'mrn-outline-errors' ); errorFilter.hidden = true;
		errorFilter.addEventListener( 'click', function() { errorsOnly = ! errorsOnly; search.value = ''; filter(); } );
		empty = element( 'p', 'mrn-outline-empty', labels.empty ); empty.hidden = true; list = element( 'ul', 'mrn-section-groups' );
		var skip = button( labels.editSelected, 'screen-reader-text mrn-outline-skip' ); skip.addEventListener( 'click', function() { select( activeRow, true ); } );
		var outlineControls = element( 'div', 'mrn-outline-controls' ); outlineControls.append( outlineHeader, searchLabel, search, skip, status, errorFilter );
		// New navigation precedes the native form in keyboard order. Existing fields stay put.
		panel.append( outlineControls, empty, list ); header.after( panel );
		var tools = element( 'div', 'mrn-editor-tools' ); tools.id = 'mrn-editor-tools';
		mobileToggle = button( labels.title, 'mrn-outline-toggle' ); mobileToggle.setAttribute( 'aria-controls', panel.id ); mobileToggle.setAttribute( 'aria-expanded', 'false' ); mobileToggle.addEventListener( 'click', function() { openPanel( mobileToggle ); } );
		viewButton = button( labels.overview, 'mrn-view-mode' ); viewButton.addEventListener( 'click', function() {
			overview = ! overview; mode = 'content';
			if ( overview ) { rows.forEach( function( item ) { if ( ! item.row.classList.contains( '-collapsed' ) ) { owner( item.row ).closeLayout( window.jQuery( item.row ) ); } } ); }
			applyView(); if ( ! overview && activeRow ) { reveal( activeRow ); }
		} );
		var steps = element( 'div', 'mrn-section-steps' ); position = element( 'span', 'mrn-section-position' ); position.setAttribute( 'aria-live', 'polite' );
		previous = button( '←', 'mrn-section-previous' ); previous.setAttribute( 'aria-label', labels.previous ); next = button( '→', 'mrn-section-next' ); next.setAttribute( 'aria-label', labels.next );
		[ previous, next ].forEach( function( node, index ) { node.addEventListener( 'click', function() { var current = rows.findIndex( function( item ) { return item.row === activeRow; } ); if ( rows[ current + ( index ? 1 : -1 ) ] ) { select( rows[ current + ( index ? 1 : -1 ) ].row, true ); } } ); } );
		var arrangement = element( 'div', 'mrn-section-arrangement' );
		[ { text: labels.moveUp, direction: -1 }, { text: labels.moveDown, direction: 1 } ].forEach( function( action ) { var move = button( action.text, 'mrn-section-move' ); move.addEventListener( 'click', function() { moveSection( action.direction ); } ); arrangement.appendChild( move ); } );
		steps.append( previous, position, next ); tools.append( mobileToggle, viewButton, arrangement, steps );
		hint = element( 'p', 'mrn-workspace-hint' ); var title = document.getElementById( 'titlediv' );
		var titleLabel = element( 'label', 'mrn-page-title-label', labels.pageName ); titleLabel.htmlFor = 'title'; title.prepend( titleLabel ); title.after( tools, hint );
		noticeObserver = new MutationObserver( function( changes ) {
			collectNotices();
			// The toolbar can mount after ACF's ready callback. Measure it on
			// insertion, before paint, rather than correcting the offset 500ms later.
			if ( changes.some( function( change ) { return Array.from( change.addedNodes ).some( function( node ) { return node.id === 'mrn-universal-sticky-toolbar'; } ); } ) ) { updateChromeOffset(); }
		} );
		noticeObserver.observe( document.getElementById( 'wpbody-content' ), { childList: true } );
		noticeObserver.observe( document.querySelector( '#poststuff' ).closest( '.wrap' ), { childList: true } );
		collectNotices(); preservePanelPreferences();
		var toolbarEntry = document.querySelector( '#wp-admin-bar-mrn-page-sections > a' ); if ( toolbarEntry ) { toolbarEntry.addEventListener( 'click', function( event ) { event.preventDefault(); openPanel( toolbarEntry ); } ); }
		panel.addEventListener( 'keydown', function( event ) { if ( event.key === 'Escape' ) { event.preventDefault(); closePanel( true ); } } );
		window.addEventListener( 'resize', updateChromeOffset ); updateChromeOffset();
	}
	// Reveal after ACF initializes its fields. Early show eagerly enhances Select2
	// controls in otherwise hidden panels and sections.
	acf.addAction( 'load', function() { nativeReady = true; schedule(); }, 100 );
	acf.addAction( 'show_field', visibleFieldChanged ); acf.addAction( 'hide_field', visibleFieldChanged );
	acf.addAction( 'ready', schedule ); acf.addAction( 'append', function( $el ) {
		$el.find( '.acf-row:not(.acf-clone)' ).addBack( '.acf-row:not(.acf-clone)' ).each( function() { addedNestedRows.add( this ); } );
		schedule();
	} ); acf.addAction( 'remove', schedule ); acf.addAction( 'sortstart', function() { sorting = true; } ); acf.addAction( 'sortstop', function() { sorting = false; schedule(); } );
	document.addEventListener( 'click', function( event ) {
		if ( event.target.closest( '.mrn-section-return' ) ) { openPanel( event.target ); }
		var control = event.target.closest( '.mrn-nested-header,.mrn-nested-all' );
		if ( ! control || ! control.closest( '.mrn-workspace-field' ) ) { return; }
		var field = acf.getField( window.jQuery( control.closest( '.acf-field-repeater' ) ) );
		if ( ! field || typeof field.collapse !== 'function' || typeof field.expand !== 'function' ) { return; }
		var items = control.matches( '.mrn-nested-header' ) ? [ control.closest( '.acf-row' ) ] : field.$rows().toArray();
		var collapse = items.some( function( item ) { return ! item.classList.contains( '-collapsed' ); } );
		items.forEach( function( item ) { field[ collapse ? 'collapse' : 'expand' ]( window.jQuery( item ) ); } );
	} );
	acf.addAction( 'invalid_field', function( field ) {
		if ( ! mounted ) { return; }
		if ( ! pendingInvalid ) {
			pendingInvalid = true; var item = rootRow( field.$el[ 0 ] );
			if ( item ) { select( item.row, false ); reveal( field.$el[ 0 ] ); }
			else { mode = 'settings'; applyView(); }
			window.setTimeout( function() { pendingInvalid = false; }, 0 );
		}
		search.value = ''; refresh( field.$el[ 0 ] );
	} );
	function watchEditor( editor ) {
		if ( richEditors.has( editor ) ) { return; } richEditors.add( editor );
		editor.on( 'input change Undo Redo', function() { var input = document.getElementById( editor.id ); if ( input && mounted ) { refresh( input ); } } );
	}
	acf.addAction( 'wysiwyg_tinymce_init', watchEditor );
	if ( window.tinymce ) { window.tinymce.editors.forEach( watchEditor ); }
	acf.addAction( 'valid_field', function( field ) { if ( mounted ) { refresh( field.$el[ 0 ] ); } } );
	document.addEventListener( 'input', function( event ) { if ( event.target.closest( '.mrn-workspace-field' ) ) { refresh( event.target ); } } );
	document.addEventListener( 'change', function( event ) { if ( event.target.closest( '.mrn-workspace-field' ) ) { refresh( event.target ); } } );
	// If ACF opens a nested field tab during validation, keep that field's section visible.
	document.addEventListener( 'focusin', function( event ) {
		var item = rootRow( event.target );
		if ( item && item.row !== activeRow ) { activeRow = item.row; applyView(); }
		/* UX-032: Disabled with the sticky section experiment.
		// Keep keyboard/validation focus below the CSS-sticky section heading.
		if ( item && event.target.matches( 'input,select,textarea,iframe,button,a[href],[tabindex],[contenteditable=true]' ) ) {
			var heading = item.row.querySelector( ':scope > .acf-fc-layout-actions-wrap' );
			if ( heading && ! heading.contains( event.target ) && getComputedStyle( heading ).position === 'sticky' ) {
				var bottom = heading.getBoundingClientRect().bottom, top = event.target.getBoundingClientRect().top;
				if ( top < bottom + 2 ) { window.scrollBy( { top: top - bottom - 12, behavior: 'instant' } ); }
			}
		}
		*/
	} );
} )();
