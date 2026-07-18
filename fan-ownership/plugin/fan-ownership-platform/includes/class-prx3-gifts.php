<?php
/**
 * Gift shares: codes issued at purchase, redeemed into the recipient's
 * own account. FO-108.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Gifts {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_redeem' ) );
	}

	/**
	 * Issue a gift code after a paid gift order (called by PRX3_WooCommerce).
	 *
	 * @param WC_Order $order           The paid order.
	 * @param int      $shares          Shares gifted.
	 * @param string   $recipient_email Optional recipient email for delivery.
	 */
	public static function issue( $order, $shares, $recipient_email = '' ) {
		$code           = strtoupper( wp_generate_password( 12, false, false ) );
		$gift           = array(
			'code'          => $code,
			'shares'        => (int) $shares,
			'order'         => $order->get_id(),
			'buyer'         => $order->get_user_id(),
			'issued_at'     => time(),
			'redeemed'      => 0,
			'recipient'     => sanitize_email( $recipient_email ),
			'consideration' => (float) $order->get_total(),
		);
		$gifts          = get_option( 'prx3_gift_codes', array() );
		$gifts[ $code ] = $gift;
		update_option( 'prx3_gift_codes', $gifts, false );

		$buyer   = get_userdata( $order->get_user_id() );
		$to      = $buyer ? $buyer->user_email : $order->get_billing_email();
		$message = sprintf(
			/* translators: 1: share count, 2: club, 3: code, 4: redeem URL. */
			__( "Your gift of %1\$d share(s) in %2\$s is ready.\n\nGift code: %3\$s\nThe recipient redeems it here: %4\$s\n\nGift codes never expire (FO-108).", 'fan-ownership' ),
			$shares,
			prx3_club_name(),
			$code,
			home_url( '/?prx3_redeem=1' )
		);
		PRX3_Comms::send( $to, sprintf( /* translators: %s club. */ __( 'Your %s gift code', 'fan-ownership' ), prx3_club_name() ), $message, 'governance' );
		if ( $gift['recipient'] ) {
			PRX3_Comms::send(
				$gift['recipient'],
				sprintf( /* translators: %s club. */ __( 'Someone has gifted you shares in %s', 'fan-ownership' ), prx3_club_name() ),
				$message,
				'governance'
			);
		}
	}

	/**
	 * Redemption: recipient signs in (or registers first), enters the code.
	 * Cap and age enforced at redemption; failure never burns the code
	 * (FO-108 AC3).
	 */
	public static function maybe_redeem() {
		if ( ! isset( $_POST['prx3_gift_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['prx3_gift_nonce'] ), 'prx3_redeem_gift' ) ) {
			self::back( __( 'Your session expired. Please try again.', 'fan-ownership' ) );
		}
		if ( ! is_user_logged_in() ) {
			self::back( __( 'Create your account (or sign in) first, then redeem your gift code.', 'fan-ownership' ) );
		}
		$code  = isset( $_POST['prx3_gift_code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['prx3_gift_code'] ) ) ) : '';
		$gifts = get_option( 'prx3_gift_codes', array() );
		if ( ! $code || empty( $gifts[ $code ] ) ) {
			self::back( __( 'That gift code was not recognised.', 'fan-ownership' ) );
		}
		if ( ! empty( $gifts[ $code ]['redeemed'] ) ) {
			self::back( __( 'That gift code has already been redeemed.', 'fan-ownership' ) );
		}
		if ( ! empty( $gifts[ $code ]['voided'] ) ) {
			self::back( __( 'That gift code is no longer valid because its payment was reversed. Contact the club if this is unexpected.', 'fan-ownership' ) );
		}
		$user_id = get_current_user_id();
		if ( ! get_user_meta( $user_id, 'prx3_adult_confirmed', true ) ) {
			self::back( __( 'You must confirm you are 18 or over before holding shares. Update your account first — your gift code remains valid.', 'fan-ownership' ) );
		}
		$granted = PRX3_Shares::grant_shares(
			$user_id,
			(int) $gifts[ $code ]['shares'],
			'gift',
			array(
				'gift_code'     => $code,
				'order'         => $gifts[ $code ]['order'],
				'consideration' => $gifts[ $code ]['consideration'],
			)
		);
		if ( is_wp_error( $granted ) ) {
			self::back( $granted->get_error_message() . ' ' . __( 'Your gift code remains valid.', 'fan-ownership' ) );
		}
		$gifts[ $code ]['redeemed']    = time();
		$gifts[ $code ]['redeemed_by'] = $user_id;
		update_option( 'prx3_gift_codes', $gifts, false );

		wp_safe_redirect( add_query_arg( 'prx3_gift_redeemed', 1, wp_get_referer() ? wp_get_referer() : home_url() ) );
		exit;
	}

	/**
	 * A buyer's outstanding gift codes (FO-108 AC4).
	 *
	 * @param int $user_id Buyer.
	 * @return array[]
	 */
	public static function unredeemed_for( $user_id ) {
		$gifts = get_option( 'prx3_gift_codes', array() );
		return array_values(
			array_filter(
				$gifts,
				function ( $g ) use ( $user_id ) {
					return (int) $g['buyer'] === (int) $user_id && empty( $g['redeemed'] );
				}
			)
		);
	}

	private static function back( $message ) {
		$url = wp_get_referer() ? wp_get_referer() : home_url();
		wp_safe_redirect( add_query_arg( 'prx3_error', rawurlencode( $message ), remove_query_arg( 'prx3_error', $url ) ) );
		exit;
	}
}
