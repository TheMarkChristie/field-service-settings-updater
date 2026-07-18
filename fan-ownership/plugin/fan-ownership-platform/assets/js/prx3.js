/**
 * Fan Ownership Platform — web front end.
 * Talks to the same prx3/v1 REST API the apps use (FO-302 parity).
 * Chat uses the polling transport behind a swap-ready interface (B4).
 */
( function () {
	'use strict';
	if ( typeof prx3Config === 'undefined' ) {
		return;
	}

	function api( path, method, body ) {
		return fetch( prx3Config.restUrl + path, {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': prx3Config.nonce
			},
			body: body ? JSON.stringify( body ) : undefined
		} ).then( function ( response ) {
			return response.json().then( function ( data ) {
				if ( ! response.ok ) {
					throw new Error( data.message || prx3Config.i18n.error );
				}
				return data;
			} );
		} );
	}

	function feedback( form, message, isError ) {
		var el = form.querySelector( '.prx3-feedback' );
		if ( el ) {
			el.textContent = message;
			el.setAttribute( 'data-state', isError ? 'error' : 'ok' );
		}
	}

	/* ---- Voting (FO-202) ---- */
	document.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest( '[data-prx3-vote]' );
		if ( ! form ) {
			return;
		}
		event.preventDefault();
		var choice = form.querySelector( 'input[name="prx3_choice"]:checked' );
		if ( ! choice ) {
			return;
		}
		feedback( form, prx3Config.i18n.working, false );
		api( 'ballots/' + form.getAttribute( 'data-prx3-vote' ) + '/vote', 'POST', { choice: parseInt( choice.value, 10 ) } )
			.then( function ( data ) {
				var msg = data.revised
					? 'Your vote has been updated. Only your final choice counts.'
					: 'Vote recorded — ' + data.weight + ' vote(s) cast. This is a secret ballot; results appear the moment it closes.';
				feedback( form, msg, false );
				var button = form.querySelector( 'button[type="submit"]' );
				if ( button ) {
					button.textContent = 'Change my vote';
				}
			} )
			.catch( function ( error ) { feedback( form, error.message, true ); } );
	} );

	/* ---- Ideas & questions submission ---- */
	document.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest( '[data-prx3-submit]' );
		if ( ! form ) {
			return;
		}
		event.preventDefault();
		var kind = form.getAttribute( 'data-prx3-submit' );
		var payload = {
			title: ( form.querySelector( '[name="title"]' ) || {} ).value || '',
			body: ( form.querySelector( '[name="body"]' ) || {} ).value || ''
		};
		feedback( form, prx3Config.i18n.working, false );
		api( kind, 'POST', payload )
			.then( function () {
				feedback( form, 'Submitted — it will appear once the club has checked it (legality and duplicates only).', false );
				form.reset();
			} )
			.catch( function ( error ) { feedback( form, error.message, true ); } );
	} );

	/* ---- Support / upvote / RSVP toggles ---- */
	function bindToggle( attribute, path, labelTemplate ) {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[' + attribute + ']' );
			if ( ! button ) {
				return;
			}
			event.preventDefault();
			api( path.replace( '%d', button.getAttribute( attribute ) ), 'POST' )
				.then( function ( data ) {
					button.textContent = labelTemplate.replace( '%d', data.count );
					button.setAttribute( 'aria-pressed', data.supporting || data.upvoted || data.attending ? 'true' : 'false' );
				} )
				.catch( function ( error ) { window.alert( error.message ); } );
		} );
	}
	bindToggle( 'data-prx3-support', 'ideas/%d/support', 'Support (%d)' );
	bindToggle( 'data-prx3-upvote', 'questions/%d/upvote', 'Upvote (%d)' );
	bindToggle( 'data-prx3-rsvp', 'meetings/%d/rsvp', 'RSVP (%d going)' );

	/* ---- Match centre (FO-305/306/307) ---- */
	var matchRoot = document.querySelector( '[data-prx3-match]' );
	if ( matchRoot ) {
		var matchId = matchRoot.getAttribute( 'data-prx3-match' );
		var room = matchRoot.getAttribute( 'data-room' );
		var lastChatId = 0;
		var player = matchRoot.querySelector( '.prx3-match__player' );
		var stateEl = matchRoot.querySelector( '.prx3-match__state' );
		var timeline = matchRoot.querySelector( '.prx3-match__timeline' );
		var messages = matchRoot.querySelector( '.prx3-chat__messages' );
		var chatForm = matchRoot.querySelector( '.prx3-chat__form' );

		function renderMatch( data ) {
			var states = {
				countdown: 'Kick-off ' + ( data.kickoff || '' ),
				live: 'LIVE',
				delayed: 'Kick-off due — the stream is on its way. If this persists, check the status page.',
				ended: 'Full-time' + ( data.score ? ' — ' + data.score : '' )
			};
			stateEl.textContent = ( states[ data.state ] || '' ) + ( data.score && data.state === 'live' ? ' · ' + data.score : '' );
			if ( data.state === 'live' && data.playback && ! player.hasChildNodes() ) {
				var frame = document.createElement( 'iframe' );
				frame.src = 'https://iframe.videodelivery.net/' + data.playback;
				frame.allow = 'accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen';
				frame.title = 'Live match stream';
				player.appendChild( frame );
			}
			if ( data.audio && data.venue === 'away' && ! player.querySelector( 'audio' ) ) {
				var audio = document.createElement( 'audio' );
				audio.controls = true;
				audio.src = data.audio;
				audio.setAttribute( 'aria-label', 'Live match commentary' );
				player.appendChild( audio );
			}
			timeline.innerHTML = '';
			( data.timeline || [] ).forEach( function ( entry ) {
				var item = document.createElement( 'li' );
				var detail = entry.detail || {};
				item.textContent = ( entry.minute ? entry.minute + "' " : '' ) + entry.event_type.replace( /_/g, ' ' ) + ( detail.text ? ' — ' + detail.text : '' );
				if ( parseInt( entry.removed, 10 ) === 1 ) {
					item.className = 'prx3-removed';
				}
				timeline.appendChild( item );
			} );
		}

		function pollMatch() {
			api( 'matches/' + matchId ).then( renderMatch ).catch( function () {} );
		}
		function pollChat() {
			api( 'chat/' + room + '?since=' + lastChatId ).then( function ( rows ) {
				rows.forEach( function ( row ) {
					lastChatId = Math.max( lastChatId, parseInt( row.id, 10 ) );
					var item = document.createElement( 'li' );
					item.textContent = row.author + ': ' + row.body + ( parseInt( row.held, 10 ) === 1 ? ' [held]' : '' );
					messages.appendChild( item );
				} );
				if ( rows.length ) {
					messages.scrollTop = messages.scrollHeight;
				}
			} ).catch( function () {} );
		}
		pollMatch();
		pollChat();
		setInterval( pollMatch, 20000 );
		setInterval( pollChat, 4000 );

		if ( chatForm ) {
			chatForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				var input = chatForm.querySelector( 'input' );
				var text = input.value.trim();
				if ( ! text ) {
					return;
				}
				api( 'chat/' + room, 'POST', { body: text } )
					.then( function () { input.value = ''; pollChat(); } )
					.catch( function ( error ) { window.alert( error.message ); } );
			} );
		}
	}

	/* ---- Reporter console retry queue (FO-307 AC3) ---- */
	window.prx3Reporter = {
		queueKey: 'prx3_reporter_queue',
		record: function ( matchId, eventType, minute, detail ) {
			var entry = {
				matchId: matchId,
				client_key: ( window.crypto && crypto.randomUUID ) ? crypto.randomUUID() : String( Date.now() ) + Math.random().toString( 16 ).slice( 2 ),
				event_type: eventType,
				minute: minute,
				detail: detail || {}
			};
			var queue = JSON.parse( localStorage.getItem( this.queueKey ) || '[]' );
			queue.push( entry );
			localStorage.setItem( this.queueKey, JSON.stringify( queue ) );
			this.flush();
		},
		flush: function () {
			var self = this;
			var queue = JSON.parse( localStorage.getItem( this.queueKey ) || '[]' );
			if ( ! queue.length ) {
				return;
			}
			var entry = queue[ 0 ];
			api( 'matches/' + entry.matchId + '/events', 'POST', entry )
				.then( function () {
					queue.shift();
					localStorage.setItem( self.queueKey, JSON.stringify( queue ) );
					self.flush(); // Order preserved; duplicates impossible via client_key.
				} )
				.catch( function () {
					setTimeout( function () { self.flush(); }, 10000 ); // Dead spot: retry until signal returns.
				} );
		}
	};
	window.addEventListener( 'online', function () { window.prx3Reporter.flush(); } );
	window.prx3Reporter.flush();
} )();
