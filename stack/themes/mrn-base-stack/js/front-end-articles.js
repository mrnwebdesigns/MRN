( function() {
	'use strict';
	function mount( root ) {
		if ( root.dataset.mrnArticlesMounted ) { return; }
		var cards = Array.from( root.querySelectorAll( '[data-article-card]' ) );
		var filters = Array.from( root.querySelectorAll( '[data-article-filter]' ) );
		var more = root.querySelector( '[data-article-more]' );
		var empty = root.querySelector( '[data-article-empty]' );
		var status = root.querySelector( '[data-article-status]' );
		var batch = Number( root.getAttribute( 'data-mrn-articles' ) );
		batch = Number.isInteger( batch ) && batch >= 1 && batch <= 1000 ? batch : 9;
		var incremental = root.getAttribute( 'data-article-incremental' ) === '1';
		var shown = batch;
		var active = '*';
		var message = root.getAttribute( 'data-article-status-message' ) || 'Showing %1$d of %2$d posts';
		function render( announce ) {
			var matches = cards.filter( function( card ) { return active === '*' || ( card.getAttribute( 'data-categories' ) || '' ).split( /\s+/ ).includes( active ); } );
			var visible = incremental ? matches.slice( 0, shown ) : matches;
			var visibleSet = new Set( visible );
			cards.forEach( function( card ) { card.hidden = ! visibleSet.has( card ); } );
			filters.forEach( function( filter ) { filter.setAttribute( 'aria-pressed', filter.getAttribute( 'data-article-filter' ) === active ? 'true' : 'false' ); } );
			if ( empty ) { empty.hidden = matches.length !== 0; }
			if ( more ) { more.parentElement.hidden = ! incremental || visible.length >= matches.length; }
			if ( status && announce ) { status.textContent = message.replace( '%1$d', String( visible.length ) ).replace( '%2$d', String( matches.length ) ); }
			return visible;
		}
		filters.forEach( function( filter ) { filter.addEventListener( 'click', function() { active = filter.getAttribute( 'data-article-filter' ) || '*'; shown = batch; render( true ); } ); } );
		if ( more ) { more.addEventListener( 'click', function() {
			var before = new Set( cards.filter( function( card ) { return ! card.hidden; } ) );
			shown += batch;
			var visible = render( true );
			// Preserve keyboard focus when the exhausted button leaves the document flow.
			if ( more.parentElement.hidden && document.activeElement === more ) {
				var next = visible.find( function( card ) { return ! before.has( card ); } );
				var link = next && next.querySelector( 'a[href]' );
				if ( link ) { link.focus( { preventScroll: true } ); }
			}
		} ); }
		root.querySelectorAll( '[data-article-controls]' ).forEach( function( controls ) { controls.hidden = false; } );
		render( false );
		root.dataset.mrnArticlesMounted = 'true';
	}
	function init() { document.querySelectorAll( '[data-article-interactive]' ).forEach( mount ); }
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
} )();
