/* FanPress Bot — talks to prx3/v1 /help/ask and opens a ticket when the
 * bot can't answer. WhatsApp-style bubbles, consistent with FanPress Chat. */
( function () {
	'use strict';

	function api( path, method, body ) {
		return fetch( prx3Config.restUrl + path, {
			method: method,
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': prx3Config.nonce },
			credentials: 'same-origin',
			body: body ? JSON.stringify( body ) : null
		} ).then( function ( r ) { return r.json(); } );
	}

	function bubble( log, text, mine, isHtml ) {
		var row = document.createElement( 'div' );
		row.className = 'prx3-bubble-row' + ( mine ? ' prx3-bubble-row--mine' : '' );
		var b = document.createElement( 'div' );
		b.className = 'prx3-bubble ' + ( mine ? 'prx3-bubble--mine' : 'prx3-bubble--theirs' );
		if ( isHtml ) { b.innerHTML = text; } else { b.textContent = text; }
		row.appendChild( b );
		log.appendChild( row );
		log.scrollTop = log.scrollHeight;
		return b;
	}

	function offerTicket( bot, log, lastQuestion ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'prx3-bot__ticket';
		wrap.innerHTML =
			'<input type="text" data-t-subject placeholder="Subject">' +
			'<textarea data-t-body rows="3" placeholder="Tell the club what you need"></textarea>' +
			'<button type="button" class="prx3-button" data-t-send>Create support ticket</button>' +
			'<p class="prx3-feedback" role="status"></p>';
		log.appendChild( wrap );
		var subj = wrap.querySelector( '[data-t-subject]' );
		subj.value = lastQuestion || '';
		wrap.querySelector( '[data-t-send]' ).addEventListener( 'click', function () {
			var fb = wrap.querySelector( '.prx3-feedback' );
			fb.textContent = 'Sending…';
			api( 'tickets', 'POST', { subject: subj.value, body: wrap.querySelector( '[data-t-body]' ).value } )
				.then( function ( res ) {
					if ( res && res.id ) {
						wrap.remove();
						bubble( log, 'Thanks — ticket #' + res.id + ' is open. The club will be in touch.', false );
					} else {
						fb.textContent = ( res && res.message ) || 'Please add a subject and message.';
					}
				} )
				.catch( function () { fb.textContent = 'Something went wrong. Please try again.'; } );
		} );
	}

	function wire( bot ) {
		if ( bot.dataset.prx3BotWired ) { return; }
		bot.dataset.prx3BotWired = '1';
		var log = bot.querySelector( '[data-prx3-bot-log]' );
		var form = bot.querySelector( '[data-prx3-bot-form]' );
		var input = bot.querySelector( '[data-prx3-bot-input]' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var q = input.value.trim();
			if ( ! q ) { return; }
			bubble( log, q, true );
			input.value = '';
			var thinking = bubble( log, '…', false );
			api( 'help/ask', 'POST', { q: q } )
				.then( function ( res ) {
					thinking.parentNode.remove();
					if ( res && res.answered ) {
						bubble( log, res.answer, false, true );
					} else {
						bubble( log, ( res && res.message ) || "I'm not sure — let's open a ticket.", false );
						offerTicket( bot, log, q );
					}
				} )
				.catch( function () { thinking.textContent = 'Sorry, I could not reach the club right now.'; } );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-prx3-bot]' ), wire );
		var launch = document.querySelector( '[data-prx3-bot-launch]' );
		if ( launch ) {
			var btn = launch.querySelector( '.prx3-bot-launch__btn' );
			var panel = launch.querySelector( '.prx3-bot-launch__panel' );
			btn.addEventListener( 'click', function () {
				panel.hidden = ! panel.hidden;
				if ( ! panel.hidden ) { wire( panel.querySelector( '[data-prx3-bot]' ) ); }
			} );
		}
	} );
}() );
