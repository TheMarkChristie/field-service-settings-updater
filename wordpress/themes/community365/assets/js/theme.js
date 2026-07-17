/* Community 365 — mobile navigation toggle. */
( function () {
	'use strict';

	var toggle = document.querySelector( '.menu-toggle' );
	var nav = document.getElementById( 'site-navigation' );

	if ( ! toggle || ! nav ) {
		return;
	}

	toggle.addEventListener( 'click', function () {
		var open = nav.classList.toggle( 'is-open' );
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	} );

	document.addEventListener( 'keyup', function ( event ) {
		if ( 'Escape' === event.key && nav.classList.contains( 'is-open' ) ) {
			nav.classList.remove( 'is-open' );
			toggle.setAttribute( 'aria-expanded', 'false' );
			toggle.focus();
		}
	} );
} )();

/* Community 365 — header search toggle. */
( function () {
	'use strict';

	var toggle = document.querySelector( '.c365-search-toggle' );
	var panel = document.getElementById( 'c365-header-search' );

	if ( ! toggle || ! panel ) {
		return;
	}

	function close() {
		panel.hidden = true;
		toggle.setAttribute( 'aria-expanded', 'false' );
	}

	toggle.addEventListener( 'click', function () {
		var open = panel.hidden;
		panel.hidden = ! open;
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			var field = panel.querySelector( '.c365-search-field' );
			if ( field ) {
				field.focus();
			}
		}
	} );

	document.addEventListener( 'keyup', function ( event ) {
		if ( 'Escape' === event.key && ! panel.hidden ) {
			close();
			toggle.focus();
		}
	} );
} )();
