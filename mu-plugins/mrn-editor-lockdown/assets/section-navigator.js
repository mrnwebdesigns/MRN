/* global acf, mrnSectionNavigator */
( function() {
	'use strict';
	if ( ! window.acf || ! window.mrnSectionNavigator ) { return; }
	var labels = mrnSectionNavigator, rows = [];
	var panel, list, search, status, empty, toggle, alternateToggle, errorFilter, errorsOnly = false, frame = 0, observer, activeRow, lastInvoker;
	var observed = new WeakSet(), captions = new WeakMap();
	function element( tag, className, text ) {
		var node = document.createElement( tag );
		node.className = className || '';
		if ( text ) { node.textContent = text; }
		return node;
	}
	function button( text, className ) {
		var node = element( 'button', className, text ); node.type = 'button'; return node;
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
		var fieldNames = [ 'internal_name', 'heading', 'label' ];
		for ( var n = 0; n < fieldNames.length; n++ ) {
			var fields = row.querySelectorAll( '.acf-field[data-name="' + fieldNames[ n ] + '"]' );
			for ( var k = 0; k < fields.length; k++ ) {
				if ( fields[ k ].closest( '.layout' ) !== row ) { continue; }
				var input = fields[ k ].querySelector( 'input[type="text"], textarea:not(.wp-editor-area)' );
				if ( input && input.value.trim() ) { return input.value.trim().replace( /\s+/g, ' ' ).slice( 0, 160 ); }
			}
		}
		return nativeTitle ? nativeTitle.textContent.trim() : labels.untitled;
	}
	function isOpen() { return panel && ! panel.hidden; }
	function openPanel( invoker ) {
		updateChromeOffset();
		lastInvoker = invoker || document.activeElement;
		panel.hidden = false; toggle.setAttribute( 'aria-expanded', 'true' ); alternateToggle.setAttribute( 'aria-expanded', 'true' ); search.focus();
	}
	function closePanel( restoreFocus ) {
		panel.hidden = true; toggle.setAttribute( 'aria-expanded', 'false' ); alternateToggle.setAttribute( 'aria-expanded', 'false' );
		if ( restoreFocus && lastInvoker && lastInvoker.isConnected ) { lastInvoker.focus(); }
	}
	function reveal( row ) {
		var ancestors = [], current = row;
		while ( current ) {
			if ( current.matches( '.layout' ) ) { ancestors.unshift( current ); }
			current = current.parentElement && current.parentElement.closest( '.layout' );
		}
		ancestors.forEach( function( layout ) {
			var field = owner( layout );
			if ( field && typeof field.openLayout === 'function' && layout.classList.contains( '-collapsed' ) ) { field.openLayout( window.jQuery( layout ) ); }
		} );
	}
	function jump( row ) {
		if ( ! row.isConnected ) { schedule(); return; }
		updateChromeOffset();
		reveal( row ); activeRow = row;
		var heading = row.querySelector( '.acf-fc-layout-handle' );
		if ( ! heading ) { return; }
		heading.setAttribute( 'tabindex', '-1' );
		if ( window.matchMedia( '(max-width: 1100px)' ).matches ) { closePanel( false ); }
		heading.focus( { preventScroll: true } );
		row.scrollIntoView( { block: 'start', behavior: 'instant' } );
		updateCurrent();
	}
	function updateCurrent() {
		rows.forEach( function( item ) {
			if ( item.row === activeRow ) { item.link.setAttribute( 'aria-current', 'location' ); }
			else { item.link.removeAttribute( 'aria-current' ); }
		} );
	}
	function filter() {
		var query = search.value.trim().toLocaleLowerCase(), count = 0, errors = rows.filter( function( item ) { return item.errors; } ).length;
		if ( ! errors ) { errorsOnly = false; }
		rows.forEach( function( item ) { var match = ( ! errorsOnly || item.errors ) && item.text.toLocaleLowerCase().indexOf( query ) !== -1; item.item.hidden = ! match; if ( match ) { count++; } } );
		list.querySelectorAll( '.mrn-section-group' ).forEach( function( group ) { group.hidden = ! group.querySelector( 'ol > li:not([hidden])' ); } );
		status.textContent = count + ' / ' + rows.length; empty.hidden = count !== 0;
		errorFilter.hidden = ! errors; errorFilter.textContent = errorsOnly ? labels.all : labels.errors + ' (' + errors + ')'; errorFilter.setAttribute( 'aria-pressed', String( errorsOnly ) );
		alternateToggle.textContent = labels.title + ( errors ? ' · ' + labels.errors + ' (' + errors + ')' : '' );
	}
	function decorate( row, title, type ) {
		var handle = row.querySelector( '.acf-fc-layout-handle' );
		if ( ! handle ) { return; }
		var nativeTitle = handle.querySelector( '.acf-fc-layout-title' );
		if ( nativeTitle && ! observed.has( nativeTitle ) ) { observer.observe( nativeTitle, { childList: true, subtree: true, characterData: true } ); observed.add( nativeTitle ); }
		var summary = handle.querySelector( '.mrn-section-summary' );
		if ( ! summary ) { summary = element( 'span', 'mrn-section-summary' ); handle.appendChild( summary ); }
		var caption = captions.get( row );
		if ( ! caption || ! caption.isConnected ) {
			caption = handle.querySelector( '.mrn-section-caption' ) || element( 'span', 'mrn-section-caption' ); summary.appendChild( caption ); captions.set( row, caption );
		}
		if ( caption.textContent !== title ) { caption.textContent = title; }
		var kind = handle.querySelector( '.mrn-section-kind' ) || element( 'span', 'mrn-section-kind' );
		kind.textContent = type; summary.insertBefore( kind, caption );
		var returnButton = row.querySelector( ':scope > .mrn-section-return' );
		if ( ! returnButton ) {
			returnButton = button( labels.return, 'button-link mrn-section-return' );
			row.appendChild( returnButton );
		}
		row.classList.add( 'mrn-editor-section' );
		handle.setAttribute( 'data-mrn-layout-type', type );
	}
	function reconcile() {
		frame = 0;
		var fields = eligibleFields();
		if ( ! fields.length ) { return; }
		if ( ! panel ) { mount(); }
		var focused = document.activeElement, focusedRow = rows.find( function( item ) { return item.link === focused; } );
		rows = []; list.replaceChildren();
		fields.forEach( function( field ) {
			var container = field.$layoutsWrap && field.$layoutsWrap()[ 0 ];
			if ( ! container ) { return; }
			if ( ! observed.has( container ) ) { observer.observe( container, { childList: true } ); observed.add( container ); }
			var label = field.$el.children( '.acf-label' ).text().trim() || field.get( 'name' );
			var group = element( 'li', 'mrn-section-group' ), groupTitle = element( 'h3', '', label ), groupList = element( 'ol' );
			group.append( groupTitle, groupList ); list.appendChild( group );
			Array.from( container.children ).filter( function( row ) { return row.matches( '.layout:not(.acf-clone)' ); } ).forEach( function( row, index ) {
				var type = row.getAttribute( 'data-label' ) || row.getAttribute( 'data-layout' );
				// Legacy labels sometimes include pipe-delimited developer hints.
				if ( type.indexOf( '|' ) !== -1 ) { type = type.split( ' - ' )[ 0 ]; }
				var title = meaningfulTitle( row ), errors = row.querySelectorAll( '.acf-error' ).length;
				decorate( row, title, type );
				var item = element( 'li' ), link = button( '', 'mrn-section-link' );
				link.append( element( 'span', 'mrn-section-type', ( index + 1 ) + ' · ' + type ), element( 'span', 'mrn-section-name', title ) );
				if ( errors ) { link.appendChild( element( 'span', 'mrn-section-error', labels.errors ) ); }
				link.addEventListener( 'click', function() { jump( row ); } ); item.appendChild( link ); groupList.appendChild( item );
				rows.push( { row: row, link: link, item: item, text: type + ' ' + title, errors: errors } );
			} );
		} );
		filter(); updateCurrent();
		if ( focusedRow ) { var restored = rows.find( function( item ) { return item.row === focusedRow.row; } ); if ( restored ) { restored.link.focus(); } }
	}
	function schedule() { if ( ! frame ) { frame = window.requestAnimationFrame( reconcile ); } }
	function updateChromeOffset() {
		var bar = document.getElementById( 'mrn-universal-sticky-toolbar' ), adminBar = document.getElementById( 'wpadminbar' );
		var offset = adminBar ? adminBar.offsetHeight : 0;
		if ( bar ) {
			offset = Math.max( offset, bar.offsetHeight + ( parseFloat( getComputedStyle( bar ).top ) || 0 ) );
			// The existing sticky bar can mount after ACF's ready action.
			if ( window.ResizeObserver && ! observed.has( bar ) ) { observed.add( bar ); new ResizeObserver( updateChromeOffset ).observe( bar ); }
		}
		document.body.style.setProperty( '--mrn-editor-offset', ( offset + 12 ) + 'px' );
	}
	function mount() {
		document.body.classList.add( 'mrn-editor-experience' );
		panel = element( 'nav', 'mrn-section-outline' ); panel.id = 'mrn-section-outline'; panel.hidden = true; panel.setAttribute( 'aria-label', labels.title );
		var header = element( 'div', 'mrn-outline-header' ), title = element( 'h2', '', labels.title ), close = button( labels.close, 'button mrn-outline-close' );
		close.addEventListener( 'click', function() { closePanel( true ); } ); header.append( title, close );
		var searchLabel = element( 'label', '', labels.search ); search = element( 'input' ); search.type = 'search'; search.id = 'mrn-section-search'; searchLabel.htmlFor = search.id;
		search.addEventListener( 'input', filter ); search.addEventListener( 'keydown', function( event ) { if ( event.key === 'Enter' ) { event.preventDefault(); } } );
		status = element( 'p', 'mrn-outline-status' ); status.setAttribute( 'role', 'status' );
		errorFilter = button( '', 'button-link mrn-outline-errors' ); errorFilter.hidden = true; errorFilter.addEventListener( 'click', function() { errorsOnly = ! errorsOnly; search.value = ''; filter(); } );
		empty = element( 'p', '', labels.empty ); empty.hidden = true; list = element( 'ul', 'mrn-section-groups' );
		panel.append( header, searchLabel, search, status, errorFilter, empty, list ); document.body.appendChild( panel );
		toggle = document.querySelector( '#wp-admin-bar-mrn-page-sections > a' );
		alternateToggle = button( labels.title, 'button mrn-outline-toggle' + ( toggle ? ' mrn-outline-mobile' : '' ) ); document.body.appendChild( alternateToggle );
		if ( ! toggle ) { toggle = alternateToggle; }
		Array.from( new Set( [ toggle, alternateToggle ] ) ).forEach( function( trigger ) {
			trigger.setAttribute( 'aria-controls', panel.id ); trigger.setAttribute( 'aria-expanded', 'false' );
			trigger.addEventListener( 'click', function( event ) { event.preventDefault(); if ( isOpen() ) { closePanel( true ); } else { openPanel( trigger ); } } );
		} );
		panel.addEventListener( 'keydown', function( event ) { if ( event.key === 'Escape' ) { event.preventDefault(); closePanel( true ); } } );
		observer = new MutationObserver( schedule );
		window.addEventListener( 'resize', updateChromeOffset ); updateChromeOffset();
	}
	acf.addAction( 'ready', schedule ); acf.addAction( 'append', schedule ); acf.addAction( 'remove', schedule ); acf.addAction( 'sortstop', schedule );
	document.addEventListener( 'click', function( event ) { var target = event.target.closest( '.mrn-section-return' ); if ( target ) { openPanel( target ); } } );
	acf.addAction( 'invalid_field', function( field ) {
		var row = field.$el[ 0 ].closest( '.layout' );
		if ( row ) {
			updateChromeOffset();
			search && ( search.value = '' ); reveal( row ); activeRow = row; schedule();
			// Show the error index without replacing ACF's native error focus.
			if ( panel && ! window.matchMedia( '(max-width: 1100px)' ).matches ) { panel.hidden = false; toggle.setAttribute( 'aria-expanded', 'true' ); alternateToggle.setAttribute( 'aria-expanded', 'true' ); }
		}
	} );
	acf.addAction( 'valid_field', schedule );
	document.addEventListener( 'input', function( event ) { if ( event.target.closest( '.acf-field[data-type="flexible_content"]' ) ) { schedule(); } } );
	document.addEventListener( 'focusin', function( event ) { var row = event.target.closest( '.layout.mrn-editor-section' ); if ( row ) { activeRow = row; updateCurrent(); } } );
	document.addEventListener( 'change', function( event ) { if ( event.target.closest( '.acf-field[data-type="flexible_content"]' ) ) { schedule(); } } );
} )();
