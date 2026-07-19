<?php
/**
 * Gift shares: codes issued at purchase, redeemed into the recipient's
 * own account. FO-108.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gift share codes: issues a code and delivery email when a gift order
 * is paid, and redeems the code into the recipient's own account with
 * agreement acceptance, age, and cap checks (FO-108).
 */
class PRX3_Gifts {

	/**
	 * Hook the redemption form handler.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'init', array( __CLASS__, 'maybe_redeem' ) );
	}

	/**
	 * Provider-agnostic gift issue: mint the code, store it against the
	 * order reference, and email buyer (and recipient when known).
	 *
	 * @param int    $buyer_id        Buyer user id (0 when unknown).
	 * @param string $buyer_email     Buyer email for delivery.
	 * @param int    $shares          Shares gifted.
	 * @param string $order_ref       Order reference (Woo id or Shopify id).
	 * @param float  $consideration   Amount paid.
	 * @param string $recipient_email Optional recipient email.
	 * @return string The gift code.
	 */
	public static function issue_code( $buyer_id, $buyer_email, $shares, $order_ref, $consideration, $recipient_email = '' ) {
		$code           = strtoupper( wp_generate_password( 12, false, false ) );
		$gift           = array(
			'code'          => $code,
			'shares'        => (int) $shares,
			'order'         => $order_ref,
			'buyer'         => (int) $buyer_id,
			'issued_at'     => time(),
			'redeemed'      => 0,
			'recipient'     => sanitize_email( $recipient_email ),
			'consideration' => (float) $consideration,
		);
		$gifts          = get_option( 'prx3_gift_codes', array() );
		$gifts[ $code ] = $gift;
		update_option( 'prx3_gift_codes', $gifts, false );

		$message = sprintf(
			/* translators: 1: share count, 2: club, 3: code, 4: redeem URL. */
			__( "Your gift of %1\$d share(s) in %2\$s is ready.\n\nGift code: %3\$s\nThe recipient redeems it here: %4\$s\n\nGift codes never expire (FO-108).", 'fan-ownership' ),
			$shares,
			prx3_club_name(),
			$code,
			home_url( '/?prx3_redeem=1' )
		);
		if ( $buyer_email && class_exists( 'PRX3_Comms' ) && method_exists( 'PRX3_Comms', 'send' ) ) {
			PRX3_Comms::send( $buyer_email, sprintf( /* translators: %s club. */ __( 'Your %s gift code', 'fan-ownership' ), prx3_club_name() ), $message, 'governance' );
		}
		if ( $gift['recipient'] && class_exists( 'PRX3_Comms' ) && method_exists( 'PRX3_Comms', 'send' ) ) {
			PRX3_Comms::send(
				$gift['recipient'],
				sprintf( /* translators: %s club. */ __( 'Someone has gifted you shares in %s', 'fan-ownership' ), prx3_club_name() ),
				$message,
				'governance'
			);
		}
		return $code;
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
		$user_id       = get_current_user_id();
		$sha_signature = '';
		if ( PRX3_Agreements::agreement_url() ) {
			if ( empty( $_POST['prx3_sha_accept'] ) ) {
				self::back( __( 'Please read and accept the Shareholders\' Agreement to redeem shares — tick the box under the gift code field.', 'fan-ownership' ) );
			}
			$sha_signature = PRX3_Agreements::posted_signature();
			if ( ! $sha_signature ) {
				self::back( __( 'Please sign in the signature box — your signature goes on your executed copy of the Shareholders\' Agreement.', 'fan-ownership' ) );
			}
		}
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
		PRX3_Agreements::record_acceptance( $user_id, 'gift_redemption', 0, $sha_signature );
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

	/**
	 * Bounce back to the referring page with the message in prx3_error.
	 *
	 * @param string $message User-facing error message.
	 */
	private static function back( $message ) {
		$url = wp_get_referer() ? wp_get_referer() : home_url();
		wp_safe_redirect( add_query_arg( 'prx3_error', rawurlencode( $message ), remove_query_arg( 'prx3_error', $url ) ) );
		exit;
	}
	/**
	 * Gift codes screen under Owners: issued, redeemed, outstanding.
	 */
	public static function menu() {
		add_submenu_page(
			'prx3-owners',
			__( 'Gift Codes', 'fan-ownership' ),
			__( 'Gift Codes', 'fan-ownership' ),
			'prx3_admin',
			'prx3-gift-codes',
			array( __CLASS__, 'render_screen' )
		);
	}

	/**
	 * Render every gift code with its state.
	 */
	public static function render_screen() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		$gifts = (array) get_option( 'prx3_gift_codes', array() );
		echo '<div class="wrap"><h1>' . esc_html__( 'Gift Codes', 'fan-ownership' ) . '</h1>';
		if ( ! $gifts ) {
			echo '<p>' . esc_html__( 'No gift codes issued yet — gifts are bought through the share checkout.', 'fan-ownership' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Code', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Shares', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Buyer', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Status', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Redeemed by', 'fan-ownership' ) . '</th></tr></thead><tbody>';
		foreach ( $gifts as $code => $gift ) {
			$gift     = (array) $gift;
			$buyer    = get_userdata( (int) ( $gift['buyer_id'] ?? 0 ) );
			$redeemer = ! empty( $gift['redeemed_by'] ) ? get_userdata( (int) $gift['redeemed_by'] ) : false;
			$status   = ! empty( $gift['voided'] ) ? __( 'voided', 'fan-ownership' ) : ( ! empty( $gift['redeemed_by'] ) ? __( 'redeemed', 'fan-ownership' ) : __( 'outstanding', 'fan-ownership' ) );
			echo '<tr><td><code>' . esc_html( (string) $code ) . '</code></td><td>' . (int) ( $gift['shares'] ?? 0 ) . '</td><td>' . esc_html( $buyer ? $buyer->display_name : (string) ( $gift['buyer_email'] ?? '—' ) ) . '</td><td>' . esc_html( $status ) . '</td><td>' . esc_html( $redeemer ? $redeemer->display_name : '—' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
