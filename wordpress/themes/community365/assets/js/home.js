/* Community 365 home components: tabs, in-place media swap, strip slider. */
( function () {
	'use strict';

	// Popular/Recent tabs.
	document.querySelectorAll( '[data-c365-tabs]' ).forEach( function ( root ) {
		root.querySelectorAll( '.c365-tab' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				root.querySelectorAll( '.c365-tab' ).forEach( function ( t ) {
					t.classList.toggle( 'is-active', t === tab );
					t.setAttribute( 'aria-selected', t === tab ? 'true' : 'false' );
				} );
				root.querySelectorAll( '.c365-tab-panel' ).forEach( function ( panel ) {
					panel.classList.toggle( 'is-active', panel.getAttribute( 'data-panel' ) === tab.getAttribute( 'data-tab' ) );
				} );
			} );
		} );
	} );

	// Media components: clicking a list item loads it into the main player.
	document.querySelectorAll( '[data-c365-media]' ).forEach( function ( root ) {
		var player = root.querySelector( '[data-c365-player]' );
		var title  = root.querySelector( '[data-c365-media-title]' );
		var art    = root.querySelector( '[data-c365-art]' );
		var badge  = root.querySelector( '[data-c365-new]' );

		root.querySelectorAll( '.c365-media-item' ).forEach( function ( item ) {
			item.addEventListener( 'click', function () {
				root.querySelectorAll( '.c365-media-item' ).forEach( function ( other ) {
					other.classList.toggle( 'is-active', other === item );
				} );

				var embed = item.getAttribute( 'data-embed' );
				var audio = item.getAttribute( 'data-audio' );
				if ( player && embed ) {
					player.src = embed + '?autoplay=1';
				} else if ( player && audio ) {
					player.src = audio;
					player.play && player.play();
				}
				if ( title ) {
					var link = title.querySelector( 'a' );
					if ( link ) {
						link.textContent = item.getAttribute( 'data-title' ) || '';
						link.href = item.getAttribute( 'data-link' ) || '#';
					}
				}
				if ( art && item.getAttribute( 'data-art' ) ) {
					art.src = item.getAttribute( 'data-art' );
				}
				if ( badge ) {
					badge.hidden = '1' !== item.getAttribute( 'data-new' );
				}
			} );
		} );
	} );

	// Strip sliders: arrows page the scroll container; swipe is native.
	document.querySelectorAll( '[data-c365-strip]' ).forEach( function ( wrap ) {
		var strip = wrap.querySelector( '.c365-strip' );
		if ( ! strip ) {
			return;
		}
		var page = function ( dir ) {
			strip.scrollBy( { left: dir * strip.clientWidth * 0.9, behavior: 'smooth' } );
		};
		var prev = wrap.querySelector( '.c365-strip-prev' );
		var next = wrap.querySelector( '.c365-strip-next' );
		prev && prev.addEventListener( 'click', function () { page( -1 ); } );
		next && next.addEventListener( 'click', function () { page( 1 ); } );
	} );
} )();
