<?php
/**
 * Shopify commerce integration (P105): shares are sold in a Shopify
 * store; the platform stays the system of record for ownership.
 *
 * Flow: Shopify fires orders/paid and refunds/create webhooks at
 * /wp-json/prx3/v1/shopify/webhook (HMAC-verified). Each ladder tier
 * is a Shopify variant; the platform builds cart permalinks so buyers
 * land on the right tiers, and re-verifies the paid amount against the
 * ladder when the webhook arrives. Because Shopify checkout cannot
 * capture the Shareholders' Agreement signature, shares are granted
 * immediately only for members who have already signed the current
 * version — everyone else's purchase waits as a pending claim until
 * they sign on the platform (the same drawn-signature flow), keeping
 * P97/P98 intact. Refunds surrender the granted shares and void gift
 * codes, mirroring the chargeback rules.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shopify webhooks in, ladder-verified share grants out, with a
 * sign-to-claim gate and refund clawback.
 */
class PRX3_Shopify {

	const ORDERS_OPTION  = 'prx3_shopify_orders';
	const PENDING_OPTION = 'prx3_shopify_pending';

	/**
	 * Hook the webhook route and the claim triggers.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'prx3_sha_accepted', array( __CLASS__, 'on_agreement_signed' ) );
		add_action( 'wp_login', array( __CLASS__, 'on_login' ), 10, 2 );
	}

	/**
	 * Register the webhook receiver.
	 */
	public static function routes() {
		register_rest_route(
			'prx3/v1',
			'/shopify/webhook',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Authenticated by the Shopify HMAC below.
				'callback'            => array( __CLASS__, 'route_webhook' ),
			)
		);
	}

	/**
	 * Verify a Shopify webhook signature.
	 *
	 * @param string $raw    Raw request body.
	 * @param string $header X-Shopify-Hmac-Sha256 header value.
	 * @param string $secret Webhook signing secret.
	 * @return bool True when genuine.
	 */
	public static function verify_hmac( $raw, $header, $secret ) {
		if ( '' === $secret || '' === $header ) {
			return false;
		}
		return hash_equals( base64_encode( hash_hmac( 'sha256', $raw, $secret, true ) ), $header ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Shopify's documented HMAC wire format.
	}

	/**
	 * The webhook endpoint: verify, then dispatch by topic.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_webhook( $request ) {
		$secret = (string) prx3_setting( 'shopify_webhook_secret', '' );
		if ( ! self::verify_hmac( (string) $request->get_body(), (string) $request->get_header( 'X-Shopify-Hmac-Sha256' ), $secret ) ) {
			return new WP_Error( 'prx3_shopify_hmac', __( 'Invalid webhook signature.', 'fan-ownership' ), array( 'status' => 401 ) );
		}
		$topic   = (string) $request->get_header( 'X-Shopify-Topic' );
		$payload = (array) $request->get_json_params();
		if ( 'orders/paid' === $topic ) {
			return rest_ensure_response( self::process_order( $payload ) );
		}
		if ( 'refunds/create' === $topic ) {
			return rest_ensure_response( self::process_refund( $payload ) );
		}
		return rest_ensure_response( array( 'ignored' => $topic ) );
	}

	/**
	 * Tier → Shopify variant id map from settings (tier 1 first).
	 *
	 * @return string[] Variant ids indexed from tier 1.
	 */
	public static function variant_map() {
		$raw = (string) prx3_setting( 'shopify_share_variants', '' );
		return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
	}

	/**
	 * The cart permalink that puts a member's next tiers in a Shopify
	 * cart — so the buyer always pays the published ladder.
	 *
	 * @param int $user_id Member.
	 * @param int $count   Shares to buy.
	 * @return string Cart URL, or '' when unconfigured or over the cap.
	 */
	public static function checkout_url( $user_id, $count = 1 ) {
		$domain = (string) prx3_setting( 'shopify_domain', '' );
		$map    = self::variant_map();
		$held   = prx3_shares( $user_id );
		if ( '' === $domain || ! $map || $held + $count > prx3_max_shares() ) {
			return '';
		}
		$parts = array();
		for ( $tier = $held + 1; $tier <= $held + $count; $tier++ ) {
			if ( empty( $map[ $tier - 1 ] ) ) {
				return '';
			}
			$parts[] = rawurlencode( $map[ $tier - 1 ] ) . ':1';
		}
		return 'https://' . sanitize_text_field( $domain ) . '/cart/' . implode( ',', $parts );
	}

	/**
	 * orders/paid: count mapped share variants, split gifts from own
	 * shares, verify the ladder price, then grant or hold for claim.
	 *
	 * @param array $order Shopify order payload.
	 * @return array Outcome summary.
	 */
	public static function process_order( $order ) {
		$order_id = isset( $order['id'] ) ? (string) $order['id'] : '';
		$email    = isset( $order['email'] ) ? strtolower( sanitize_email( $order['email'] ) ) : '';
		if ( '' === $order_id || '' === $email ) {
			return array( 'status' => 'ignored' );
		}
		$processed = (array) get_option( self::ORDERS_OPTION, array() );
		if ( isset( $processed[ $order_id ] ) ) {
			return array( 'status' => 'duplicate' ); // Idempotent: webhooks retry.
		}

		$map         = self::variant_map();
		$own_shares  = 0;
		$gift_shares = 0;
		$gift_to     = '';
		$paid        = 0.0;
		foreach ( (array) ( $order['line_items'] ?? array() ) as $line ) {
			$variant = isset( $line['variant_id'] ) ? (string) $line['variant_id'] : '';
			if ( ! in_array( $variant, $map, true ) ) {
				continue;
			}
			$quantity = max( 1, (int) ( $line['quantity'] ?? 1 ) );
			$paid    += (float) ( $line['price'] ?? 0 ) * $quantity;
			$is_gift  = false;
			foreach ( (array) ( $line['properties'] ?? array() ) as $property ) {
				if ( isset( $property['name'] ) && 'gift' === strtolower( (string) $property['name'] ) && ! empty( $property['value'] ) ) {
					$is_gift = true;
				}
				if ( isset( $property['name'] ) && 'recipient_email' === strtolower( (string) $property['name'] ) ) {
					$gift_to = sanitize_email( (string) $property['value'] );
				}
			}
			if ( $is_gift ) {
				$gift_shares += $quantity;
			} else {
				$own_shares += $quantity;
			}
		}
		if ( 0 === $own_shares && 0 === $gift_shares ) {
			return array( 'status' => 'no_share_lines' );
		}

		$user  = get_user_by( 'email', $email );
		$codes = array();
		if ( $gift_shares > 0 ) {
			$codes[] = PRX3_Gifts::issue_code( $user ? $user->ID : 0, $email, $gift_shares, $order_id, $paid, $gift_to );
		}

		$record = array(
			'email'   => $email,
			'shares'  => $own_shares,
			'paid'    => $paid,
			'user_id' => 0,
			'gifts'   => $codes,
			'at'      => prx3_now(),
		);

		$status = 'pending_signature';
		if ( $own_shares > 0 ) {
			$held     = $user ? prx3_shares( $user->ID ) : 0;
			$expected = prx3_ladder_total( $held, $own_shares ) - ( $gift_shares > 0 ? 0 : 0 );
			$mismatch = $gift_shares > 0 ? false : abs( $expected - $paid ) > 0.02 * $own_shares;
			if ( $mismatch ) {
				$status = 'price_mismatch';
				self::add_pending( $email, $order_id, $own_shares, $paid, true );
				PRX3_Audit::log( 'shopify_price_mismatch', sprintf( 'Shopify order %s paid %.2f, ladder expects %.2f for %d share(s) — held for review', $order_id, $paid, $expected, $own_shares ) );
			} elseif ( $user && PRX3_Agreements::is_current( $user->ID ) ) {
				$granted = PRX3_Shares::grant_shares(
					$user->ID,
					$own_shares,
					'shopify',
					array(
						'order'         => $order_id,
						'consideration' => $paid,
					)
				);
				if ( is_wp_error( $granted ) ) {
					$status = 'held_' . $granted->get_error_code();
					self::add_pending( $email, $order_id, $own_shares, $paid, true );
					PRX3_Audit::log( 'shopify_grant_held', sprintf( 'Shopify order %s held: %s', $order_id, $granted->get_error_message() ) );
				} else {
					$status            = 'granted';
					$record['user_id'] = $user->ID;
				}
			} else {
				self::add_pending( $email, $order_id, $own_shares, $paid, false );
				if ( class_exists( 'PRX3_Comms' ) && method_exists( 'PRX3_Comms', 'send' ) ) {
					PRX3_Comms::send(
						$email,
						sprintf( /* translators: %s club. */ __( 'One step left to claim your %s shares', 'fan-ownership' ), prx3_club_name() ),
						sprintf( /* translators: %s URL. */ __( "Thanks for your purchase. To complete it, sign in (or register with this email address) and sign the Shareholders' Agreement — your shares are granted the moment you sign: %s", 'fan-ownership' ), home_url( '/' ) ),
						'governance'
					);
				}
			}
		} elseif ( $gift_shares > 0 ) {
			$status = 'gift_issued';
		}

		$processed[ $order_id ] = $record;
		update_option( self::ORDERS_OPTION, $processed, false );
		PRX3_Audit::log( 'shopify_order', sprintf( 'Shopify order %s: %d own / %d gift share(s), %s', $order_id, $own_shares, $gift_shares, $status ) );
		return array(
			'status' => $status,
			'order'  => $order_id,
		);
	}

	/**
	 * Queue a purchase awaiting signature (or admin review on mismatch).
	 *
	 * @param string $email    Buyer email (lowercase).
	 * @param string $order_id Shopify order id.
	 * @param int    $shares   Shares bought.
	 * @param float  $paid     Amount paid.
	 * @param bool   $flagged  True when held for admin review.
	 */
	private static function add_pending( $email, $order_id, $shares, $paid, $flagged ) {
		$pending             = (array) get_option( self::PENDING_OPTION, array() );
		$pending[ $email ][] = array(
			'order'   => $order_id,
			'shares'  => $shares,
			'paid'    => $paid,
			'flagged' => $flagged,
			'at'      => prx3_now(),
		);
		update_option( self::PENDING_OPTION, $pending, false );
	}

	/**
	 * Pending (claimable) shares for an email address.
	 *
	 * @param string $email Email.
	 * @return int Unflagged shares awaiting claim.
	 */
	public static function pending_for( $email ) {
		$pending = (array) get_option( self::PENDING_OPTION, array() );
		$total   = 0;
		foreach ( (array) ( $pending[ strtolower( $email ) ] ?? array() ) as $entry ) {
			if ( empty( $entry['flagged'] ) ) {
				$total += (int) $entry['shares'];
			}
		}
		return $total;
	}

	/**
	 * The member signed the agreement — claim their waiting purchases.
	 *
	 * @param int $user_id Member.
	 */
	public static function on_agreement_signed( $user_id ) {
		self::claim_for( $user_id );
	}

	/**
	 * Signed-in member with a current agreement — sweep any claims.
	 *
	 * @param string  $user_login Login name (unused).
	 * @param WP_User $user       User object.
	 */
	public static function on_login( $user_login, $user ) {
		if ( is_object( $user ) && PRX3_Agreements::is_current( $user->ID ) ) {
			self::claim_for( $user->ID );
		}
	}

	/**
	 * Grant every unflagged pending purchase for the member's email.
	 *
	 * @param int $user_id Member.
	 */
	public static function claim_for( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || ! PRX3_Agreements::is_current( $user_id ) ) {
			return;
		}
		$email   = strtolower( $user->user_email );
		$pending = (array) get_option( self::PENDING_OPTION, array() );
		if ( empty( $pending[ $email ] ) ) {
			return;
		}
		$keep      = array();
		$processed = (array) get_option( self::ORDERS_OPTION, array() );
		foreach ( $pending[ $email ] as $entry ) {
			if ( ! empty( $entry['flagged'] ) ) {
				$keep[] = $entry; // Admin review only.
				continue;
			}
			$granted = PRX3_Shares::grant_shares(
				$user_id,
				(int) $entry['shares'],
				'shopify',
				array(
					'order'         => $entry['order'],
					'consideration' => $entry['paid'],
				)
			);
			if ( is_wp_error( $granted ) ) {
				$keep[] = $entry;
				PRX3_Audit::log( 'shopify_claim_held', sprintf( 'Claim for order %s held: %s', $entry['order'], $granted->get_error_message() ) );
				continue;
			}
			if ( isset( $processed[ $entry['order'] ] ) ) {
				$processed[ $entry['order'] ]['user_id'] = $user_id;
			}
			PRX3_Audit::log( 'shopify_claimed', sprintf( 'Order %s claimed by user %d (%d shares)', $entry['order'], $user_id, $entry['shares'] ) );
		}
		if ( $keep ) {
			$pending[ $email ] = $keep;
		} else {
			unset( $pending[ $email ] );
		}
		update_option( self::PENDING_OPTION, $pending, false );
		update_option( self::ORDERS_OPTION, $processed, false );
	}

	/**
	 * refunds/create: surrender granted shares, void the order's gift
	 * codes, and drop unclaimed pendings — the chargeback rules (P28).
	 *
	 * @param array $refund Shopify refund payload.
	 * @return array Outcome summary.
	 */
	public static function process_refund( $refund ) {
		$order_id  = isset( $refund['order_id'] ) ? (string) $refund['order_id'] : '';
		$processed = (array) get_option( self::ORDERS_OPTION, array() );
		if ( '' === $order_id || empty( $processed[ $order_id ] ) ) {
			return array( 'status' => 'unknown_order' );
		}
		$record = $processed[ $order_id ];
		if ( ! empty( $record['surrendered'] ) ) {
			return array( 'status' => 'duplicate' );
		}

		// Surrender granted shares back to the club.
		if ( ! empty( $record['user_id'] ) && $record['shares'] > 0 ) {
			$user_id = (int) $record['user_id'];
			$held    = prx3_shares( $user_id );
			$take    = min( $held, (int) $record['shares'] );
			if ( $take > 0 ) {
				update_user_meta( $user_id, 'prx3_shares', $held - $take );
				PRX3_Register::record( $user_id, 'surrender', $take, 'chargeback', array( 'order' => $order_id ) );
				do_action( 'prx3_shares_surrendered', $user_id, $take, 'chargeback' );
			}
		}

		// Void unredeemed gift codes; surrender redeemed ones from the redeemer.
		$gifts   = (array) get_option( 'prx3_gift_codes', array() );
		$changed = false;
		foreach ( $gifts as $code => $gift ) {
			if ( (string) ( $gift['order'] ?? '' ) !== $order_id ) {
				continue;
			}
			$changed                  = true;
			$gifts[ $code ]['voided'] = time();
			if ( ! empty( $gift['redeemed_by'] ) ) {
				$redeemer = (int) $gift['redeemed_by'];
				$held     = prx3_shares( $redeemer );
				$take     = min( $held, (int) $gift['shares'] );
				if ( $take > 0 ) {
					update_user_meta( $redeemer, 'prx3_shares', $held - $take );
					PRX3_Register::record(
						$redeemer,
						'surrender',
						$take,
						'chargeback',
						array(
							'order'     => $order_id,
							'gift_code' => $code,
						)
					);
					do_action( 'prx3_shares_surrendered', $redeemer, $take, 'chargeback' );
				}
			}
		}
		if ( $changed ) {
			update_option( 'prx3_gift_codes', $gifts, false );
		}

		// Drop any unclaimed pending entries for this order.
		$pending = (array) get_option( self::PENDING_OPTION, array() );
		foreach ( $pending as $email => $entries ) {
			$pending[ $email ] = array_values( array_filter( $entries, fn( $e ) => (string) $e['order'] !== $order_id ) );
			if ( ! $pending[ $email ] ) {
				unset( $pending[ $email ] );
			}
		}
		update_option( self::PENDING_OPTION, $pending, false );

		$processed[ $order_id ]['surrendered'] = time();
		update_option( self::ORDERS_OPTION, $processed, false );
		PRX3_Audit::log( 'shopify_refund', sprintf( 'Shopify refund for order %s: shares surrendered, gift codes voided', $order_id ) );
		return array(
			'status' => 'clawed_back',
			'order'  => $order_id,
		);
	}
}
