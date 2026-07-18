/**
 * Club dashboard interactivity: live refresh over admin-ajax, the
 * cumulative-shares sparkline, the holdings mini-chart with hover and
 * keyboard tooltips, per-ballot quorum meters with countdowns, and the
 * accessible table view mirroring every tile.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-prx3-dashboard]' );
	if ( ! root || 'undefined' === typeof window.prx3Dash ) {
		return;
	}

	var cfg = window.prx3Dash;
	var tip = root.querySelector( '[data-tip]' );
	var data = cfg.data || {};

	var SVG = 'http://www.w3.org/2000/svg';

	function el( name, attrs ) {
		var node = document.createElementNS( SVG, name );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			node.setAttribute( key, attrs[ key ] );
		} );
		return node;
	}

	function showTip( text, x, y ) {
		if ( ! tip ) {
			return;
		}
		tip.textContent = text;
		tip.hidden = false;
		tip.style.left = Math.min( x + 12, window.innerWidth - tip.offsetWidth - 8 ) + 'px';
		tip.style.top = y - 32 + 'px';
	}

	function hideTip() {
		if ( tip ) {
			tip.hidden = true;
		}
	}

	/* ---------------- Sparkline: cumulative shares by month ---------------- */

	function drawSpark() {
		var host = root.querySelector( '[data-spark]' );
		var series = data.series || [];
		if ( ! host ) {
			return;
		}
		host.textContent = '';
		if ( series.length < 2 ) {
			return;
		}
		var w = 300;
		var h = 56;
		var pad = 3;
		var max = series.reduce( function ( m, p ) {
			return Math.max( m, p[ 1 ] );
		}, 1 );
		var step = ( w - 2 * pad ) / ( series.length - 1 );
		var points = series.map( function ( p, i ) {
			return [ pad + i * step, h - pad - ( p[ 1 ] / max ) * ( h - 2 * pad ) ];
		} );
		var line = points.map( function ( p, i ) {
			return ( i ? 'L' : 'M' ) + p[ 0 ].toFixed( 1 ) + ' ' + p[ 1 ].toFixed( 1 );
		} ).join( ' ' );
		var svg = el( 'svg', { viewBox: '0 0 ' + w + ' ' + h, role: 'presentation', focusable: 'false' } );
		svg.appendChild( el( 'path', { class: 'area', d: line + ' L' + points[ points.length - 1 ][ 0 ].toFixed( 1 ) + ' ' + ( h - pad ) + ' L' + pad + ' ' + ( h - pad ) + ' Z' } ) );
		svg.appendChild( el( 'path', { class: 'line', d: line } ) );
		host.appendChild( svg );
	}

	/* ---------------- Holdings distribution mini columns ---------------- */

	function drawDist() {
		var host = root.querySelector( '[data-dist]' );
		var dist = data.dist || [];
		if ( ! host ) {
			return;
		}
		host.textContent = '';
		var w = 300;
		var h = 56;
		var gap = 2;
		var max = Math.max.apply( null, dist.concat( [ 1 ] ) );
		var bar = ( w - gap * ( dist.length - 1 ) ) / dist.length;
		var svg = el( 'svg', { viewBox: '0 0 ' + w + ' ' + h, focusable: 'false' } );
		dist.forEach( function ( count, i ) {
			var bh = Math.max( count > 0 ? 3 : 0, ( count / max ) * ( h - 14 ) );
			var rect = el( 'rect', {
				x: ( i * ( bar + gap ) ).toFixed( 1 ),
				y: ( h - bh ).toFixed( 1 ),
				width: bar.toFixed( 1 ),
				height: bh.toFixed( 1 ),
				rx: 2,
				tabindex: '0',
				'aria-label': count + ' ' + cfg.i18n.members + ', ' + ( i + 1 ) + ' ' + cfg.i18n.shares,
			} );
			var label = count + ' × ' + ( i + 1 ) + ' ' + cfg.i18n.shares;
			rect.addEventListener( 'mousemove', function ( e ) {
				showTip( label, e.clientX, e.clientY );
			} );
			rect.addEventListener( 'mouseleave', hideTip );
			rect.addEventListener( 'focus', function () {
				var box = rect.getBoundingClientRect();
				showTip( label, box.left, box.top );
			} );
			rect.addEventListener( 'blur', hideTip );
			svg.appendChild( rect );
		} );
		host.appendChild( svg );
	}

	/* ---------------- Ballot quorum tiles ---------------- */

	function countdown( closes ) {
		var end = new Date( closes.replace( ' ', 'T' ) ).getTime();
		var left = end - Date.now();
		if ( isNaN( end ) || left <= 0 ) {
			return cfg.i18n.closed;
		}
		var hours = Math.floor( left / 3600000 );
		var mins = Math.floor( ( left % 3600000 ) / 60000 );
		return cfg.i18n.closesIn + ' ' + ( hours > 0 ? hours + 'h ' : '' ) + mins + 'm';
	}

	function drawBallots() {
		var host = root.querySelector( '[data-ballots]' );
		var ballots = data.ballots || [];
		if ( ! host || ! ballots.length ) {
			return;
		}
		host.textContent = '';
		ballots.forEach( function ( ballot ) {
			var pct = Math.min( 100, Math.round( ( ballot.voted / ballot.needed ) * 100 ) );
			var tile = document.createElement( ballot.link ? 'a' : 'div' );
			tile.className = 'prx3-tile' + ( pct < 100 ? ' is-warning' : ' is-good' );
			if ( ballot.link ) {
				tile.href = ballot.link;
			}
			var label = document.createElement( 'span' );
			label.className = 'prx3-tile-label';
			label.textContent = ballot.title;
			var value = document.createElement( 'strong' );
			value.className = 'prx3-tile-value';
			value.textContent = ballot.voted + ' / ' + ballot.needed;
			var note = document.createElement( 'span' );
			note.className = 'prx3-tile-note prx3-ballot-countdown';
			note.textContent = countdown( ballot.closes );
			var meter = document.createElement( 'span' );
			meter.className = 'prx3-meter';
			meter.setAttribute( 'role', 'meter' );
			meter.setAttribute( 'aria-valuemin', '0' );
			meter.setAttribute( 'aria-valuemax', String( ballot.needed ) );
			meter.setAttribute( 'aria-valuenow', String( ballot.voted ) );
			meter.setAttribute( 'aria-label', label.textContent );
			var fill = document.createElement( 'span' );
			fill.className = 'prx3-meter-fill';
			fill.style.width = pct + '%';
			meter.appendChild( fill );
			tile.appendChild( label );
			tile.appendChild( value );
			tile.appendChild( note );
			tile.appendChild( meter );
			host.appendChild( tile );
		} );
	}

	/* ---------------- Table view ---------------- */

	function drawTable() {
		var body = root.querySelector( '[data-table] tbody' );
		if ( ! body ) {
			return;
		}
		body.textContent = '';
		var rows = [
			[ 'Owners', data.owners + ' / ' + data.target ],
			[ 'Active owners (12m)', data.active ],
			[ 'Shares issued', data.shares ],
			[ 'Share revenue', data.revenue ],
			[ 'Gifts unredeemed', data.gifts ],
			[ 'Surrender events', data.surrenders ],
			[ 'Moderation queue', data.queue ],
			[ 'Ideas pending / published', ( data.ideas || [ 0, 0 ] )[ 1 ] + ' / ' + ( data.ideas || [ 0, 0 ] )[ 0 ] ],
			[ 'Questions pending / answered', ( data.questions || [ 0, 0 ] )[ 1 ] + ' / ' + ( data.questions || [ 0, 0 ] )[ 0 ] ],
			[ 'Stalled decisions', data.stalled ],
			[ 'Commitments overdue', data.overdue ],
			[ 'CRM sync review / outbox', ( data.sync || {} ).review + ' / ' + ( data.sync || {} ).outbox ],
			[ 'Videos', data.videos ],
		];
		( data.dist || [] ).forEach( function ( count, i ) {
			rows.push( [ 'Members holding ' + ( i + 1 ) + ' ' + cfg.i18n.shares, count ] );
		} );
		( data.ballots || [] ).forEach( function ( ballot ) {
			rows.push( [ ballot.title, ballot.voted + ' voted of ' + ballot.needed + ' needed' ] );
		} );
		rows.forEach( function ( row ) {
			var tr = document.createElement( 'tr' );
			var th = document.createElement( 'th' );
			th.scope = 'row';
			th.textContent = row[ 0 ];
			var td = document.createElement( 'td' );
			td.textContent = String( row[ 1 ] );
			tr.appendChild( th );
			tr.appendChild( td );
			body.appendChild( tr );
		} );
	}

	/* ---------------- Binding + refresh ---------------- */

	function bindValues() {
		var map = {
			owners: data.owners,
			shares: data.shares,
			revenue: data.revenue,
			active: data.active,
			gifts: data.gifts,
			surrenders: data.surrenders,
			queue: data.queue,
			ideasPending: ( data.ideas || [ 0, 0 ] )[ 1 ],
			questionsPending: ( data.questions || [ 0, 0 ] )[ 1 ],
			stalled: data.stalled,
			overdue: data.overdue,
			syncReview: ( data.sync || {} ).review,
			videos: data.videos,
		};
		Object.keys( map ).forEach( function ( key ) {
			var node = root.querySelector( '[data-bind="' + key + '"]' );
			if ( node && 'undefined' !== typeof map[ key ] ) {
				node.textContent = String( map[ key ] );
			}
		} );
		var meter = root.querySelector( '[data-meter="owners"]' );
		if ( meter ) {
			meter.setAttribute( 'aria-valuenow', String( data.owners ) );
			meter.querySelector( '.prx3-meter-fill' ).style.width = Math.min( 100, Math.round( ( data.owners / Math.max( 1, data.target ) ) * 100 ) ) + '%';
		}
	}

	function drawAll() {
		bindValues();
		drawSpark();
		drawDist();
		drawBallots();
		drawTable();
	}

	function refresh() {
		window.fetch( cfg.ajaxUrl + '?action=prx3_dashboard_data&_wpnonce=' + encodeURIComponent( cfg.nonce ), { credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( payload && payload.success ) {
					data = payload.data;
					drawAll();
					var stamp = root.querySelector( '[data-stamp]' );
					if ( stamp ) {
						stamp.textContent = cfg.i18n.updated + ' ' + data.stamp;
					}
				}
			} )
			.catch( function () { /* Leave the last good numbers in place. */ } );
	}

	var button = root.querySelector( '[data-refresh]' );
	if ( button ) {
		button.addEventListener( 'click', refresh );
	}

	drawAll();
	window.setInterval( function () {
		if ( ! document.hidden ) {
			refresh();
		}
	}, 60000 );
	window.setInterval( function () {
		if ( ! document.hidden ) {
			drawBallots(); // Tick the countdowns.
		}
	}, 30000 );
}() );
