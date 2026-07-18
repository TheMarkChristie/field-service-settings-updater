<?php
/**
 * WooCommerce integration: the tiered share checkout.
 *
 * FO-106, FO-107, FO-109 (sequential invoices), plus the payment-identity
 * side of FO-110. Degrades to a disabled checkout when WooCommerce is
 * absent (B3 decision).
 *
 * Model: one configured "Club Share" product. Quantity = shares bought.
 * The line price is computed from the buyer's position on the ladder at
 * cart time and re-verified at order creation, so the charged amount
 * always matches the published ladder (FO-106 AC1).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_WooCommerce {

	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_ladder_pricing' ), 20 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'verify_order_pricing' ), 10, 2 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'fulfil_order' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'fulfil_order' ) );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'note_invoice' ) );
		add_filter( 'woocommerce_order_number', array( __CLASS__, 'sequential_invoice_number' ), 10, 2 );
	}

	/**
	 * The configured share product ID.
	 *
	 * @return int
	 */
	public static function share_product_id() {
		return (int) prx3_setting( 'share_product_id', 0 );
	}

	private static function is_share_product( $product_id ) {
		return $product_id && self::share_product_id() === (int) $product_id;
	}

	/**
	 * Cap, age, verification, and kill-switch checks before the cart.
	 */
	public static function validate_add_to_cart( $passed, $product_id, $quantity ) {
		if ( ! self::is_share_product( $product_id ) ) {
			return $passed;
		}
		if ( ! prx3_feature_on( 'checkout' ) ) {
			wc_add_notice( __( 'The share checkout is temporarily unavailable.', 'fan-ownership' ), 'error' );
			return false;
		}
		$is_gift = ! empty( $_REQUEST['prx3_gift'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $is_gift ) {
			return $passed; // Gifts validate the recipient's cap at redemption (FO-108 AC3).
		}
		if ( ! is_user_logged_in() ) {
			wc_add_notice( __( 'Please create your account and confirm your email before buying shares.', 'fan-ownership' ), 'error' );
			return false;
		}
		$user_id = get_current_user_id();
		if ( ! PRX3_Membership::is_verified( $user_id ) && ! prx3_is_owner( $user_id ) ) {
			wc_add_notice( __( 'Please confirm your email address first — check your inbox for the confirmation link.', 'fan-ownership' ), 'error' );
			return false;
		}
		$held = prx3_shares( $user_id );
		if ( $held >= prx3_max_shares() ) {
			wc_add_notice(
				sprintf(
				/* translators: %d cap. */
					__( 'You already hold the maximum of %d shares. Thank you for going all in.', 'fan-ownership' ),
					prx3_max_shares()
				),
				'error'
			);
			return false;
		}
		if ( $held + $quantity > prx3_max_shares() ) {
			wc_add_notice(
				sprintf(
				/* translators: %d remaining. */
					__( 'You can buy up to %d more share(s).', 'fan-ownership' ),
					prx3_max_shares() - $held
				),
				'error'
			);
			return false;
		}
		return $passed;
	}

	/**
	 * Ladder pricing at cart time (FO-106 AC1, FO-107 AC1).
	 */
	public static function apply_ladder_pricing( $cart ) {
		foreach ( $cart->get_cart() as $item ) {
			if ( ! self::is_share_product( $item['product_id'] ) ) {
				continue;
			}
			$is_gift = ! empty( $item['prx3_gift'] );
			// Gifts price from the bottom of the ladder (recipient position unknown).
			$held  = $is_gift ? 0 : prx3_shares( get_current_user_id() );
			$qty   = max( 1, (int) $item['quantity'] );
			$total = prx3_ladder_total( $held, $qty );
			$item['data']->set_price( $total / $qty );
		}
	}

	/**
	 * Belt and braces: re-verify at order creation that the charged total
	 * equals the ladder for this buyer at this moment.
	 */
	public static function verify_order_pricing( $order, $data ) {
		foreach ( $order->get_items() as $order_item ) {
			if ( ! self::is_share_product( $order_item->get_product_id() ) ) {
				continue;
			}
			$is_gift  = (bool) $order_item->get_meta( 'prx3_gift' );
			$held     = $is_gift ? 0 : prx3_shares( get_current_user_id() );
			$qty      = max( 1, (int) $order_item->get_quantity() );
			$expected = prx3_ladder_total( $held, $qty );
			if ( abs( (float) $order_item->get_total() - $expected ) > 0.01 ) {
				throw new Exception( esc_html__( 'Share pricing changed while you were checking out — please re-add the shares to your basket.', 'fan-ownership' ) );
			}
			$order_item->add_meta_data( 'prx3_shares_qty', $qty, true );
			$order_item->add_meta_data( 'prx3_ladder_from', $held + 1, true );
			if ( $is_gift ) {
				$order_item->add_meta_data( 'prx3_gift', 1, true );
				$order_item->add_meta_data( 'prx3_gift_recipient', sanitize_email( $order_item->get_meta( 'prx3_gift_recipient' ) ), true );
			}
		}
	}

	/**
	 * Payment success: grant shares (or issue the gift code). Idempotent —
	 * a re-fired status hook cannot double-grant (FO-106 AC4/AC5).
	 */
	public static function fulfil_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_prx3_fulfilled' ) ) {
			return;
		}
		$user_id = $order->get_user_id();
		foreach ( $order->get_items() as $item ) {
			if ( ! self::is_share_product( $item->get_product_id() ) ) {
				continue;
			}
			$qty = (int) $item->get_meta( 'prx3_shares_qty' );
			$qty = $qty ? $qty : (int) $item->get_quantity();

			if ( $item->get_meta( 'prx3_gift' ) ) {
				PRX3_Gifts::issue( $order, $qty, (string) $item->get_meta( 'prx3_gift_recipient' ) );
				continue;
			}
			if ( ! $user_id ) {
				$order->add_order_note( __( 'Fan Ownership: no member account on this order — shares not granted. Resolve manually.', 'fan-ownership' ) );
				continue;
			}
			$granted = PRX3_Shares::grant_shares(
				$user_id,
				$qty,
				'purchase',
				array(
					'order'         => $order_id,
					'consideration' => (float) $item->get_total(),
				)
			);
			if ( is_wp_error( $granted ) ) {
				$order->add_order_note( 'Fan Ownership: ' . $granted->get_error_message() );
				continue;
			}
		}
		// Payment-identity duplicate signal (FO-110 AC1).
		self::flag_payment_identity( $order );

		$order->update_meta_data( '_prx3_fulfilled', time() );
		$order->save();
	}

	/**
	 * Same payment fingerprint on two accounts = duplicate suspect.
	 */
	private static function flag_payment_identity( $order ) {
		$fingerprint = apply_filters( 'prx3_payment_identity', '', $order ); // Stripe gateway hook supplies the card fingerprint.
		$user_id     = $order->get_user_id();
		if ( ! $fingerprint || ! $user_id ) {
			return;
		}
		$existing = get_users(
			array(
				'meta_key'   => 'prx3_payment_fingerprint',
				'meta_value' => $fingerprint,
				'fields'     => 'ID',
				'exclude'    => array( $user_id ),
			)
		);
		update_user_meta( $user_id, 'prx3_payment_fingerprint', $fingerprint );
		if ( $existing ) {
			$suspects = array_map( 'intval', (array) get_user_meta( $user_id, 'prx3_duplicate_suspects', true ) );
			update_user_meta( $user_id, 'prx3_duplicate_suspects', array_unique( array_merge( $suspects, array_map( 'intval', $existing ) ) ) );
			PRX3_Audit::log( 'duplicate_flag', sprintf( 'Payment identity match: user %d and %s', $user_id, implode( ',', $existing ) ) );
		}
	}

	/**
	 * FO-109: gapless sequential invoice numbers, allocated once per order
	 * at payment, prefixed for the club.
	 */
	public static function sequential_invoice_number( $number, $order ) {
		$invoice = $order->get_meta( '_prx3_invoice_no' );
		if ( $invoice ) {
			return $invoice;
		}
		if ( ! $order->is_paid() ) {
			return $number;
		}
		global $wpdb;
		$wpdb->query( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES ('prx3_invoice_seq', '1', 'no') ON DUPLICATE KEY UPDATE option_value = option_value + 1" );
		$seq     = (int) $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'prx3_invoice_seq'" );
		$invoice = sprintf( '%s-%06d', prx3_setting( 'invoice_prefix', 'PP' ), $seq );
		$order->update_meta_data( '_prx3_invoice_no', $invoice );
		$order->save();
		wp_cache_delete( 'prx3_invoice_seq', 'options' );
		return $invoice;
	}

	public static function note_invoice( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_meta( '_prx3_invoice_no' ) ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: %s invoice number. */
					__( 'Your invoice number is %s. Invoices are kept permanently in your account.', 'fan-ownership' ),
					$order->get_meta( '_prx3_invoice_no' )
				)
			) . '</p>';
		}
	}
}
