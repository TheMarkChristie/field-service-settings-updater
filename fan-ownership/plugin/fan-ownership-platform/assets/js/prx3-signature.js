/**
 * Signature pad for agreement execution: draws into a canvas and stores
 * the stroke as a PNG data URL in the field's hidden input. Works with
 * mouse, touch, and stylus via pointer events, and survives WooCommerce
 * checkout fragment refreshes.
 */
( function () {
	'use strict';

	function initPad( wrap ) {
		if ( wrap.dataset.prx3SigReady ) {
			return;
		}
		wrap.dataset.prx3SigReady = '1';

		var input = wrap.querySelector( 'input.prx3-sig-value' );
		var clear = wrap.querySelector( '.prx3-sig-clear' );
		var canvas = document.createElement( 'canvas' );
		var ratio = window.devicePixelRatio || 1;
		var cssWidth = Math.min( 420, wrap.clientWidth || 420 );
		var cssHeight = 140;

		canvas.className = 'prx3-sig-canvas';
		canvas.width = cssWidth * ratio;
		canvas.height = cssHeight * ratio;
		canvas.style.width = cssWidth + 'px';
		canvas.style.height = cssHeight + 'px';
		canvas.style.touchAction = 'none';
		canvas.style.border = '1px solid #999';
		canvas.style.borderRadius = '4px';
		canvas.style.background = '#fff';
		canvas.style.cursor = 'crosshair';
		canvas.setAttribute( 'aria-label', wrap.dataset.label || 'Signature area' );
		wrap.insertBefore( canvas, wrap.firstChild );

		var ctx = canvas.getContext( '2d' );
		ctx.scale( ratio, ratio );
		ctx.lineWidth = 2;
		ctx.lineCap = 'round';
		ctx.lineJoin = 'round';
		ctx.strokeStyle = '#1a1a5e';

		var drawing = false;
		var drawn = false;

		function pos( e ) {
			var r = canvas.getBoundingClientRect();
			return { x: e.clientX - r.left, y: e.clientY - r.top };
		}

		canvas.addEventListener( 'pointerdown', function ( e ) {
			e.preventDefault();
			canvas.setPointerCapture( e.pointerId );
			drawing = true;
			var p = pos( e );
			ctx.beginPath();
			ctx.moveTo( p.x, p.y );
		} );

		canvas.addEventListener( 'pointermove', function ( e ) {
			if ( ! drawing ) {
				return;
			}
			e.preventDefault();
			var p = pos( e );
			ctx.lineTo( p.x, p.y );
			ctx.stroke();
			drawn = true;
		} );

		function endStroke() {
			if ( ! drawing ) {
				return;
			}
			drawing = false;
			if ( drawn && input ) {
				input.value = canvas.toDataURL( 'image/png' );
			}
		}
		canvas.addEventListener( 'pointerup', endStroke );
		canvas.addEventListener( 'pointercancel', endStroke );
		canvas.addEventListener( 'pointerleave', endStroke );

		if ( clear ) {
			clear.addEventListener( 'click', function () {
				ctx.clearRect( 0, 0, cssWidth, cssHeight );
				drawn = false;
				if ( input ) {
					input.value = '';
				}
			} );
		}
	}

	function initAll() {
		document.querySelectorAll( '.prx3-sigpad' ).forEach( initPad );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}

	// WooCommerce re-renders the review-order fragment via jQuery events.
	if ( window.jQuery ) {
		window.jQuery( document.body ).on( 'updated_checkout', initAll );
	}
}() );
