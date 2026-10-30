/* global acf, mrnSectionNavigator */
( function() {
	'use strict';
	if ( ! window.acf || ! window.mrnSectionNavigator ) { return; }
	var nativeReady = document.readyState === 'complete';
	var labels = mrnSectionNavigator, rows = [], fields = [], activeRow, mode = 'content', overview = false;
	var panel, list, search, status, empty, errorFilter, mobileToggle, modeButtons, viewButton, position, previous, next, hint;
	var observer, titleObserver, noticeObserver, frame = 0, refreshFrame = 0, dirtyRows = new Set(), moving = false, sorting = false, errorsOnly = false, mounted = false, lastInvoker, pendingInvalid = false;
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
	function reveal( row ) {
		if ( ! nativeReady ) { return; }
		var ancestors = [], current = row;
		while ( current ) {
			if ( current.matches( '.layout' ) ) { ancestors.unshift( current ); }
			current = current.parentElement && current.parentElement.closest( '.layout' );
		}
		ancestors.forEach( function( layout ) {
			var field = owner( layout );
			if ( field && layout.classList.contains( '-collapsed' ) ) { field.openLayout( window.jQuery( layout ) ); }
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
		applyView(); reveal( row ); scrollSelection(); closePanel( false ); rememberSection();
		if ( focus ) {
			var heading = row.querySelector( '.acf-fc-layout-handle' );
			heading.setAttribute( 'tabindex', '-1' ); heading.focus( { preventScroll: true } );
			document.getElementById( 'mrn-editor-tools' ).scrollIntoView( { block: 'start', behavior: 'instant' } );
		}
	}
	function applyView() {
		document.body.classList.toggle( 'mrn-workspace-settings', mode === 'settings' );
		document.body.classList.toggle( 'mrn-workspace-overview', overview );
		modeButtons.forEach( function( item ) { item.button.setAttribute( 'aria-pressed', String( item.mode === mode ) ); } );
		var active = rows.findIndex( function( item ) { return item.row === activeRow; } );
		rows.forEach( function( item ) {
			item.row.classList.toggle( 'mrn-section-parked', ! overview && item.row !== activeRow );
			if ( item.row === activeRow ) { item.link.setAttribute( 'aria-current', 'location' ); }
			else { item.link.removeAttribute( 'aria-current' ); }
		} );
		fields.forEach( function( field ) {
			var box = field.$el[ 0 ].closest( '.postbox' );
			if ( box ) { box.classList.toggle( 'mrn-region-parked', ! overview && ( ! activeRow || ! field.$el[ 0 ].contains( activeRow ) ) ); }
		} );
		var positionText = active < 0 ? '0 / ' + rows.length : ( active + 1 ) + ' / ' + rows.length;
		if ( position.textContent !== positionText ) { position.textContent = positionText; }
		previous.disabled = active <= 0; next.disabled = active < 0 || active >= rows.length - 1;
		var moves = document.querySelectorAll( '.mrn-section-move' ), selected = rows[ active ];
		if ( moves.length ) { moves[ 0 ].disabled = moving || ! selected || ! rows[ active - 1 ] || rows[ active - 1 ].field !== selected.field; moves[ 1 ].disabled = moving || ! selected || ! rows[ active + 1 ] || rows[ active + 1 ].field !== selected.field; }
		viewButton.textContent = overview ? labels.focus : labels.overview;
		viewButton.setAttribute( 'aria-pressed', String( overview ) );
		hint.textContent = mode === 'settings' ? labels.settingsHint : ( overview ? labels.arrangeHint : labels.hint );
	}
	function filter() {
		var query = search.value.trim().toLocaleLowerCase(), count = 0, errors = rows.filter( function( item ) { return item.errors; } ).length;
		if ( ! errors ) { errorsOnly = false; }
		rows.forEach( function( item ) {
			var match = ( ! errorsOnly || item.errors ) && item.text.toLocaleLowerCase().indexOf( query ) !== -1;
			item.item.hidden = ! match; if ( match ) { count++; }
		} );
		list.querySelectorAll( '.mrn-section-group' ).forEach( function( group ) {
			group.hidden = ! group.querySelector( 'ol > li:not([hidden])' );
		} );
		var statusText = count + ' / ' + rows.length + ' sections';
		if ( status.textContent !== statusText ) { status.textContent = statusText; } empty.hidden = count !== 0;
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
		var add = field.$el[ 0 ].querySelector( '.acf-actions [data-name="add-layout"]' );
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
		frame = 0; if ( sorting ) { return; } fields = eligibleFields(); if ( ! fields.length ) { return; }
		if ( ! mounted ) { mount(); }
		var focused = document.activeElement, oldRows = rows, outlineScroll = panel.scrollTop, oldIndex = oldRows.findIndex( function( item ) { return item.row === activeRow; } );
		// Disconnect before re-indexing: removed layouts must not be retained by observers.
		observer.disconnect(); titleObserver.disconnect(); rows = []; list.replaceChildren();
		fields.forEach( function( field ) {
			var container = field.$layoutsWrap && field.$layoutsWrap()[ 0 ]; if ( ! container ) { return; }
			observer.observe( container, { childList: true } );
			field.$el[ 0 ].classList.add( 'mrn-workspace-field' );
			var box = field.$el[ 0 ].closest( '.postbox' ); if ( box ) { box.classList.add( 'mrn-workspace-region' ); box.classList.remove( 'closed' ); }
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
					link.addEventListener( 'click', function() { select( row, true ); } ); item.appendChild( link );
					entry = { row: row, link: link, item: item };
				}
				entry.type = type; entry.field = field.get( 'key' ); entry.index = index;
				entry.link.querySelector( '.mrn-section-type' ).textContent = ( index + 1 ) + ' · ' + type;
				refreshItem( entry ); groupList.appendChild( entry.item ); rows.push( entry );
			} );
		} );
		var added = oldRows.length && rows.find( function( item ) { return ! oldRows.some( function( old ) { return old.row === item.row; } ); } );
		if ( added ) { select( added.row, false ); }
		else if ( ! activeRow || ! activeRow.isConnected ) { var restoredSection = ! oldRows.length && restoreSection(); activeRow = restoredSection ? restoredSection.row : ( rows.length ? rows[ Math.max( 0, Math.min( oldIndex, rows.length - 1 ) ) ].row : null ); }
		filter(); applyView(); if ( activeRow && ! overview && mode === 'content' ) { reveal( activeRow ); }
		if ( ! added ) { panel.scrollTop = outlineScroll; } rememberSection();
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
		if ( bar ) {
			offset = Math.max( offset, bar.offsetHeight + ( parseFloat( getComputedStyle( bar ).top ) || 0 ) );
			if ( window.ResizeObserver && ! observed.has( bar ) ) { observed.add( bar ); new ResizeObserver( updateChromeOffset ).observe( bar ); }
		}
		document.body.style.setProperty( '--mrn-editor-offset', offset + 'px' );
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
		var menu = button( labels.menu, 'mrn-workspace-button mrn-site-menu' ), back = element( 'a', 'mrn-workspace-back', '← ' + labels.back ); back.href = labels.backUrl;
		menu.setAttribute( 'aria-controls', 'adminmenumain' ); menu.setAttribute( 'aria-expanded', 'false' );
		menu.addEventListener( 'click', function() { var open = document.body.classList.toggle( 'mrn-site-menu-open' ); menu.setAttribute( 'aria-expanded', String( open ) ); } );
		var modes = element( 'div', 'mrn-workspace-modes' ); modes.setAttribute( 'role', 'group' ); modes.setAttribute( 'aria-label', 'Workspace' );
		modeButtons = [ { mode: 'content', button: button( labels.content, 'mrn-mode-content' ) }, { mode: 'settings', button: button( labels.settings, 'mrn-mode-settings' ) } ];
		modeButtons.forEach( function( item ) { item.button.addEventListener( 'click', function() { mode = item.mode; applyView(); window.scrollTo( { top: 0, behavior: 'instant' } ); } ); modes.appendChild( item.button ); } );
		header.append( menu, back, modes ); document.getElementById( 'wpbody-content' ).prepend( header );
		panel = element( 'nav', 'mrn-section-outline' ); panel.id = 'mrn-section-outline'; panel.setAttribute( 'aria-label', labels.title );
		var outlineHeader = element( 'div', 'mrn-outline-header' ), close = button( labels.close, 'mrn-outline-close' );
		close.addEventListener( 'click', function() { closePanel( true ); } ); outlineHeader.append( element( 'h2', '', labels.title ), close );
		var searchLabel = element( 'label', 'screen-reader-text', labels.search ); search = element( 'input' ); search.type = 'search'; search.id = 'mrn-section-search'; search.placeholder = labels.search; searchLabel.htmlFor = search.id;
		search.addEventListener( 'input', filter ); search.addEventListener( 'keydown', function( event ) {
			if ( event.key === 'Enter' ) { event.preventDefault(); var match = rows.find( function( item ) { return ! item.item.hidden; } ); if ( match ) { select( match.row, true ); } }
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
		noticeObserver = new MutationObserver( collectNotices );
		noticeObserver.observe( document.getElementById( 'wpbody-content' ), { childList: true } );
		noticeObserver.observe( document.querySelector( '#poststuff' ).closest( '.wrap' ), { childList: true } );
		collectNotices();
		var toolbarEntry = document.querySelector( '#wp-admin-bar-mrn-page-sections > a' ); if ( toolbarEntry ) { toolbarEntry.addEventListener( 'click', function( event ) { event.preventDefault(); openPanel( toolbarEntry ); } ); }
		panel.addEventListener( 'keydown', function( event ) { if ( event.key === 'Escape' ) { event.preventDefault(); closePanel( true ); } } );
		document.addEventListener( 'keydown', function( event ) { if ( event.key === 'Escape' && document.body.classList.contains( 'mrn-site-menu-open' ) ) { document.body.classList.remove( 'mrn-site-menu-open' ); menu.setAttribute( 'aria-expanded', 'false' ); menu.focus(); } } );
		window.addEventListener( 'resize', updateChromeOffset ); updateChromeOffset(); window.setTimeout( updateChromeOffset, 500 );
	}
	// Reveal after ACF initializes its fields. Early show eagerly enhances Select2
	// controls in otherwise hidden panels and sections.
	acf.addAction( 'load', function() { nativeReady = true; schedule(); }, 100 );
	acf.addAction( 'ready', schedule ); acf.addAction( 'append', schedule ); acf.addAction( 'remove', schedule ); acf.addAction( 'sortstart', function() { sorting = true; } ); acf.addAction( 'sortstop', function() { sorting = false; schedule(); } );
	document.addEventListener( 'click', function( event ) { if ( event.target.closest( '.mrn-section-return' ) ) { openPanel( event.target ); } } );
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
	acf.addAction( 'valid_field', function( field ) { if ( mounted ) { refresh( field.$el[ 0 ] ); } } );
	document.addEventListener( 'input', function( event ) { if ( event.target.closest( '.mrn-workspace-field' ) ) { refresh( event.target ); } } );
	document.addEventListener( 'change', function( event ) { if ( event.target.closest( '.mrn-workspace-field' ) ) { refresh( event.target ); } } );
	// If ACF opens a nested field tab during validation, keep that field's section visible.
	document.addEventListener( 'focusin', function( event ) { var item = rootRow( event.target ); if ( item && item.row !== activeRow ) { activeRow = item.row; applyView(); } } );
} )();
