<?php
/**
 * Legal documents supplied to members, with versioned acceptance.
 *
 * The Shareholders' Agreement (with its morality/conduct clauses) and
 * the Terms of Membership are WordPress pages the club controls. This
 * module:
 *  - requires explicit acceptance of the current agreement version at
 *    the share checkout and at gift redemption (becoming a shareholder
 *    is what triggers it);
 *  - records every acceptance permanently (version, timestamp, IP,
 *    order) — the evidential record that a member agreed;
 *  - links the documents from the confirmation email and the member's
 *    account page (printable pages; print-to-PDF for personal copies);
 *  - when the club publishes a new version, banners existing owners
 *    until they re-accept, and records that too.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Agreements {

	public static function init() {
		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'checkout_checkbox' ) );
			add_action( 'woocommerce_checkout_process', array( __CLASS__, 'checkout_validate' ) );
			add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'record_from_order' ), 10, 1 );
		}
		add_action( 'admin_post_prx3_accept_agreement', array( __CLASS__, 'handle_reaccept' ) );
		add_action( 'admin_post_nopriv_prx3_accept_agreement', '__return_false' );
	}

	public static function version() {
		return (string) prx3_setting( 'sha_version', '1.0' );
	}

	public static function agreement_url() {
		$page_id = (int) prx3_setting( 'sha_page_id', 0 );
		return $page_id && 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : '';
	}

	/**
	 * Does the cart contain shares (own purchase or gift)?
	 */
	private static function cart_has_shares() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( PRX3_WooCommerce::share_product_id() && PRX3_WooCommerce::share_product_id() === (int) $item['product_id'] ) {
				return true;
			}
		}
		return false;
	}

	public static function checkout_checkbox() {
		if ( ! self::cart_has_shares() ) {
			return;
		}
		$url = self::agreement_url();
		woocommerce_form_field(
			'prx3_sha_accept',
			array(
				'type'     => 'checkbox',
				'class'    => array( 'form-row', 'prx3-sha' ),
				'required' => true,
				'label'    => $url
					? sprintf(
						/* translators: 1: URL, 2: version. */
						__( 'I have read and agree to the <a href="%1$s" target="_blank" rel="noopener">Shareholders\' Agreement</a> (v%2$s), including its conduct and morality provisions.', 'fan-ownership' ),
						esc_url( $url ),
						esc_html( self::version() )
					)
					: sprintf(
						/* translators: %s version. */
						__( 'I agree to the Shareholders\' Agreement (v%s), including its conduct and morality provisions.', 'fan-ownership' ),
						esc_html( self::version() )
					),
			)
		);
	}

	public static function checkout_validate() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout carries its own nonce.
		if ( self::cart_has_shares() && empty( $_POST['prx3_sha_accept'] ) ) {
			wc_add_notice( __( 'Please read and accept the Shareholders\' Agreement to buy shares.', 'fan-ownership' ), 'error' );
		}
	}

	public static function record_from_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_user_id() ) {
			return;
		}
		// Only record when shares are in the order.
		$has_shares = false;
		foreach ( $order->get_items() as $item ) {
			if ( PRX3_WooCommerce::share_product_id() && (int) $item->get_product_id() === PRX3_WooCommerce::share_product_id() ) {
				$has_shares = true;
			}
		}
		if ( $has_shares ) {
			self::record_acceptance( $order->get_user_id(), 'checkout', $order_id );
		}
	}

	/**
	 * The permanent acceptance record: append-only user meta + audit.
	 *
	 * @param int    $user_id Member.
	 * @param string $context 'checkout'|'gift_redemption'|'reaccept'.
	 * @param int    $order_id Optional order reference.
	 */
	public static function record_acceptance( $user_id, $context, $order_id = 0 ) {
		$log   = (array) get_user_meta( $user_id, 'prx3_sha_acceptances', true );
		$log[] = array(
			'version' => self::version(),
			'at'      => prx3_now(),
			'ip'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'context' => sanitize_key( $context ),
			'order'   => (int) $order_id,
		);
		update_user_meta( $user_id, 'prx3_sha_acceptances', $log );
		update_user_meta( $user_id, 'prx3_sha_version', self::version() );
		PRX3_Audit::log( 'sha_accepted', sprintf( 'User %d accepted Shareholders\' Agreement v%s (%s)', $user_id, self::version(), $context ) );
	}

	/**
	 * Has this owner accepted the current version?
	 */
	public static function is_current( $user_id ) {
		return get_user_meta( $user_id, 'prx3_sha_version', true ) === self::version();
	}

	/**
	 * Owner-facing status block for the account page: accepted version,
	 * document links, and a re-accept button when a new version ships.
	 */
	public static function account_block( $user_id ) {
		$url     = self::agreement_url();
		$version = get_user_meta( $user_id, 'prx3_sha_version', true );
		$html    = '<h3>' . esc_html__( 'My documents', 'fan-ownership' ) . '</h3><ul>';
		if ( $url ) {
			$html .= '<li><a href="' . esc_url( $url ) . '">' . esc_html__( 'Shareholders\' Agreement', 'fan-ownership' ) . '</a> — '
				. esc_html(
					$version
						? sprintf( /* translators: %s version. */ __( 'you accepted v%s', 'fan-ownership' ), $version )
						: __( 'not yet accepted', 'fan-ownership' )
				) . '</li>';
		}
		$terms = (int) prx3_setting( 'terms_page_id', 0 );
		if ( $terms && 'publish' === get_post_status( $terms ) ) {
			$html .= '<li><a href="' . esc_url( get_permalink( $terms ) ) . '">' . esc_html__( 'Terms of Membership', 'fan-ownership' ) . '</a></li>';
		}
		$html .= '<li>' . esc_html__( 'Open a document and use your browser\'s print / save-as-PDF for your personal copy.', 'fan-ownership' ) . '</li></ul>';

		if ( prx3_is_owner( $user_id ) && $url && ! self::is_current( $user_id ) ) {
			$html .= '<div class="prx3-notice" role="status"><p>' . esc_html(
				sprintf(
					/* translators: %s version. */
					__( 'The Shareholders\' Agreement has been updated to v%s. Please read and re-accept it.', 'fan-ownership' ),
					self::version()
				)
			) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$html .= wp_nonce_field( 'prx3_accept_agreement', '_wpnonce', true, false );
			$html .= '<input type="hidden" name="action" value="prx3_accept_agreement">';
			$html .= '<p><label><input type="checkbox" name="prx3_sha_accept" value="1" required> ' . esc_html__( 'I have read and accept the updated agreement.', 'fan-ownership' ) . '</label></p>';
			$html .= '<p><button class="prx3-button" type="submit">' . esc_html__( 'Accept', 'fan-ownership' ) . '</button></p></form></div>';
		}
		return $html;
	}

	public static function handle_reaccept() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please sign in.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_accept_agreement' );
		if ( empty( $_POST['prx3_sha_accept'] ) ) {
			wp_die( esc_html__( 'Please tick the acceptance box.', 'fan-ownership' ) );
		}
		self::record_acceptance( get_current_user_id(), 'reaccept' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}
}
