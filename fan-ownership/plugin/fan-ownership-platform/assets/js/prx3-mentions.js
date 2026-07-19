/**
 * FanPress Chat @mention autosuggest: while typing @handle in a forum
 * or message box, suggest matching owners from the member directory.
 * Progressive enhancement — plain typed handles keep working without it.
 */
( function () {
	'use strict';

	var config = window.prx3Config || {};
	if ( ! config.restUrl ) {
		return;
	}

	function attach( area ) {
		var box = document.createElement( 'div' );
		box.className = 'prx3-mentions';
		box.setAttribute( 'role', 'listbox' );
		box.style.cssText = 'position:absolute;z-index:100;background:#fff;border:1px solid #ccd0d4;box-shadow:0 2px 6px rgba(0,0,0,.15);display:none;max-height:200px;overflow:auto;';
		area.parentNode.style.position = 'relative';
		area.parentNode.appendChild( box );

		var timer = null;

		function close() {
			box.style.display = 'none';
			box.innerHTML = '';
		}

		function currentHandle() {
			var upto = area.value.slice( 0, area.selectionStart );
			var match = upto.match( /(?:^|\s)@([a-z0-9_.\-]*)$/i );
			return match ? match[ 1 ] : null;
		}

		function pick( handle ) {
			var upto = area.value.slice( 0, area.selectionStart );
			var rest = area.value.slice( area.selectionStart );
			area.value = upto.replace( /@[a-z0-9_.\-]*$/i, '@' + handle + ' ' ) + rest;
			close();
			area.focus();
		}

		function render( members ) {
			box.innerHTML = '';
			if ( ! members.length ) {
				close();
				return;
			}
			members.forEach( function ( member ) {
				var item = document.createElement( 'button' );
				item.type = 'button';
				item.className = 'prx3-mentions-item';
				item.style.cssText = 'display:block;width:100%;text-align:left;padding:6px 10px;border:0;background:none;cursor:pointer;';
				item.textContent = member.name + ' (@' + member.handle + ')';
				item.addEventListener( 'click', function () {
					pick( member.handle );
				} );
				box.appendChild( item );
			} );
			box.style.display = 'block';
		}

		area.addEventListener( 'input', function () {
			var handle = currentHandle();
			if ( null === handle ) {
				close();
				return;
			}
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				window
					.fetch( config.restUrl + 'members/suggest?q=' + encodeURIComponent( handle ), {
						headers: { 'X-WP-Nonce': config.nonce },
						credentials: 'same-origin'
					} )
					.then( function ( response ) {
						return response.ok ? response.json() : { members: [] };
					} )
					.then( function ( data ) {
						render( ( data && data.members ) || [] );
					} )
					.catch( close );
			}, 200 );
		} );

		area.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				close();
			}
		} );

		area.addEventListener( 'blur', function () {
			window.setTimeout( close, 200 );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document
			.querySelectorAll( '.prx3-forum textarea, .prx3-messages textarea, #prx3_topic_body, #prx3_dm_body' )
			.forEach( attach );
	} );
} )();
