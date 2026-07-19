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
		add_action( 'prx3_commitments_tick', array( __CLASS__, 'daily_ops' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 31 );
		add_action( 'admin_post_prx3_commerce_resolve', array( __CLASS__, 'handle_resolve' ) );
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
		$topic = (string) $request->get_header( 'X-Shopify-Topic' );
		update_option(
			'prx3_shopify_last_webhook',
			array(
				'at'    => prx3_now(),
				'topic' => $topic,
			),
			false
		);
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
	 * Handle orders/paid: count mapped share variants, split gifts from own
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
				self::add_pending( $email, $order_id, $own_shares, $paid, true, 'mismatch' );
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
					self::add_pending( $email, $order_id, $own_shares, $paid, true, 'cap' );
					PRX3_Audit::log( 'shopify_grant_held', sprintf( 'Shopify order %s held: %s', $order_id, $granted->get_error_message() ) );
				} else {
					$status            = 'granted';
					$record['user_id'] = $user->ID;
				}
			} else {
				self::add_pending( $email, $order_id, $own_shares, $paid, false, 'signature' );
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
	 * @param string $reason   Why it waits: signature|mismatch|cap.
	 */
	private static function add_pending( $email, $order_id, $shares, $paid, $flagged, $reason = 'signature' ) {
		$pending             = (array) get_option( self::PENDING_OPTION, array() );
		$pending[ $email ][] = array(
			'order'    => $order_id,
			'shares'   => $shares,
			'paid'     => $paid,
			'flagged'  => $flagged,
			'reason'   => $reason,
			'reminded' => array(),
			'at'       => prx3_now(),
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
	 * Handle refunds/create: surrender granted shares, void the order's gift
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

	/* ---------------- Commerce operations (P107) ---------------- */

	/**
	 * Held and unclaimed counts plus the oldest unclaimed age, for the
	 * dashboard and the ops screen.
	 *
	 * @return array {held, unclaimed, oldest_days}
	 */
	public static function counts() {
		$held      = 0;
		$unclaimed = 0;
		$oldest    = 0;
		foreach ( (array) get_option( self::PENDING_OPTION, array() ) as $entries ) {
			foreach ( (array) $entries as $entry ) {
				if ( ! empty( $entry['flagged'] ) ) {
					++$held;
				} else {
					++$unclaimed;
				}
				$oldest = max( $oldest, self::age_days( $entry ) );
			}
		}
		return array(
			'held'        => $held,
			'unclaimed'   => $unclaimed,
			'oldest_days' => $oldest,
		);
	}

	/**
	 * Days since a pending entry arrived.
	 *
	 * @param array $entry Pending entry.
	 * @return int Whole days.
	 */
	private static function age_days( $entry ) {
		$at = isset( $entry['at'] ) ? strtotime( (string) $entry['at'] ) : 0;
		return $at ? (int) floor( ( time() - $at ) / DAY_IN_SECONDS ) : 0;
	}

	/**
	 * Move a pending purchase to the buyer's real platform email (the
	 * wrong-email support case), then claim it if they have signed.
	 *
	 * @param string $from_email Email the purchase waits under.
	 * @param string $order_id   Shopify order id.
	 * @param string $to_email   The member's actual email.
	 * @return bool True when an entry moved.
	 */
	public static function reassign_pending( $from_email, $order_id, $to_email ) {
		$from    = strtolower( sanitize_email( $from_email ) );
		$to      = strtolower( sanitize_email( $to_email ) );
		$pending = (array) get_option( self::PENDING_OPTION, array() );
		if ( '' === $to || empty( $pending[ $from ] ) ) {
			return false;
		}
		$moved = false;
		foreach ( $pending[ $from ] as $i => $entry ) {
			if ( (string) $entry['order'] === (string) $order_id ) {
				unset( $pending[ $from ][ $i ] );
				$pending[ $to ][] = $entry;
				$moved            = true;
			}
		}
		$pending[ $from ] = array_values( $pending[ $from ] );
		if ( ! $pending[ $from ] ) {
			unset( $pending[ $from ] );
		}
		if ( $moved ) {
			update_option( self::PENDING_OPTION, $pending, false );
			PRX3_Audit::log( 'shopify_reassigned', sprintf( 'Order %s reassigned from %s to %s', $order_id, $from, $to ) );
			$user = get_user_by( 'email', $to );
			if ( $user && PRX3_Agreements::is_current( $user->ID ) ) {
				self::claim_for( $user->ID );
			}
		}
		return $moved;
	}

	/**
	 * Release a held (flagged) purchase after human review: grant
	 * through the money path and clear the hold.
	 *
	 * @param string $email    Email the purchase waits under.
	 * @param string $order_id Shopify order id.
	 * @return true|WP_Error
	 */
	public static function release_pending( $email, $order_id ) {
		$email   = strtolower( sanitize_email( $email ) );
		$user    = get_user_by( 'email', $email );
		$pending = (array) get_option( self::PENDING_OPTION, array() );
		if ( ! $user ) {
			return new WP_Error( 'prx3_no_member', __( 'No member holds that email — reassign the purchase to the right member first.', 'fan-ownership' ) );
		}
		foreach ( (array) ( $pending[ $email ] ?? array() ) as $i => $entry ) {
			if ( (string) $entry['order'] !== (string) $order_id ) {
				continue;
			}
			$granted = PRX3_Shares::grant_shares(
				$user->ID,
				(int) $entry['shares'],
				'shopify',
				array(
					'order'         => $order_id,
					'consideration' => $entry['paid'],
					'released_by'   => get_current_user_id(),
				)
			);
			if ( is_wp_error( $granted ) ) {
				return $granted;
			}
			unset( $pending[ $email ][ $i ] );
			$pending[ $email ] = array_values( $pending[ $email ] );
			if ( ! $pending[ $email ] ) {
				unset( $pending[ $email ] );
			}
			update_option( self::PENDING_OPTION, $pending, false );
			$processed = (array) get_option( self::ORDERS_OPTION, array() );
			if ( isset( $processed[ $order_id ] ) ) {
				$processed[ $order_id ]['user_id'] = $user->ID;
				update_option( self::ORDERS_OPTION, $processed, false );
			}
			PRX3_Audit::log( 'shopify_released', sprintf( 'Held order %s released to user %d after review', $order_id, $user->ID ) );
			return true;
		}
		return new WP_Error( 'prx3_no_entry', __( 'No waiting purchase found for that order.', 'fan-ownership' ) );
	}

	/**
	 * Drop a pending purchase (refunded in Shopify, or discarded).
	 *
	 * @param string $email    Email the purchase waits under.
	 * @param string $order_id Shopify order id.
	 * @return bool True when removed.
	 */
	public static function drop_pending( $email, $order_id ) {
		$email   = strtolower( sanitize_email( $email ) );
		$pending = (array) get_option( self::PENDING_OPTION, array() );
		$found   = false;
		foreach ( (array) ( $pending[ $email ] ?? array() ) as $i => $entry ) {
			if ( (string) $entry['order'] === (string) $order_id ) {
				unset( $pending[ $email ][ $i ] );
				$found = true;
			}
		}
		if ( $found ) {
			$pending[ $email ] = array_values( $pending[ $email ] );
			if ( ! $pending[ $email ] ) {
				unset( $pending[ $email ] );
			}
			update_option( self::PENDING_OPTION, $pending, false );
			PRX3_Audit::log( 'shopify_dropped', sprintf( 'Pending order %s for %s dropped (refunded/discarded)', $order_id, $email ) );
		}
		return $found;
	}

	/**
	 * Daily operations: chase unclaimed purchases at 3 and 10 days,
	 * and alert staff when the webhook has gone quiet (P107).
	 */
	public static function daily_ops() {
		$pending = (array) get_option( self::PENDING_OPTION, array() );
		$changed = false;
		foreach ( $pending as $email => $entries ) {
			foreach ( $entries as $i => $entry ) {
				if ( ! empty( $entry['flagged'] ) ) {
					continue;
				}
				$age      = self::age_days( $entry );
				$reminded = (array) ( $entry['reminded'] ?? array() );
				foreach ( array( 3, 10 ) as $day ) {
					if ( $age >= $day && ! in_array( $day, $reminded, true ) ) {
						$reminded[]                          = $day;
						$pending[ $email ][ $i ]['reminded'] = $reminded;
						$changed                             = true;
						if ( class_exists( 'PRX3_Comms' ) && method_exists( 'PRX3_Comms', 'send' ) ) {
							PRX3_Comms::send(
								$email,
								sprintf( /* translators: %s club. */ __( 'Your %s shares are still waiting for you', 'fan-ownership' ), prx3_club_name() ),
								sprintf( /* translators: %s URL. */ __( "Your share purchase is waiting to be claimed. Sign in (or register with this email address) and sign the Shareholders' Agreement, and your shares are granted immediately: %s", 'fan-ownership' ), home_url( '/' ) ),
								'governance'
							);
						}
					}
				}
			}
		}
		if ( $changed ) {
			update_option( self::PENDING_OPTION, $pending, false );
		}

		// Webhook health: configured store but nothing heard in 7+ days.
		if ( '' !== (string) prx3_setting( 'shopify_webhook_secret', '' ) && '' !== (string) prx3_setting( 'shopify_domain', '' ) ) {
			$last       = (array) get_option( 'prx3_shopify_last_webhook', array() );
			$last_at    = isset( $last['at'] ) ? strtotime( (string) $last['at'] ) : 0;
			$quiet_days = $last_at ? floor( ( time() - $last_at ) / DAY_IN_SECONDS ) : 99;
			$alerted    = (int) get_option( 'prx3_shopify_quiet_alerted', 0 );
			if ( $quiet_days >= 7 && ( time() - $alerted ) > 7 * DAY_IN_SECONDS ) {
				update_option( 'prx3_shopify_quiet_alerted', time(), false );
				PRX3_Audit::log( 'shopify_webhook_quiet', sprintf( 'No Shopify webhook received for %d day(s)', (int) $quiet_days ) );
				if ( class_exists( 'PRX3_Comms' ) && method_exists( 'PRX3_Comms', 'send' ) ) {
					PRX3_Comms::send(
						get_option( 'admin_email' ),
						__( 'Shopify webhooks have gone quiet', 'fan-ownership' ),
						__( 'The store is configured but no webhook has arrived for at least 7 days. If the store has taken orders, check the webhook URL and signing secret in Shopify admin — purchases may not be reaching the platform.', 'fan-ownership' ),
						'governance'
					);
				}
			}
		}
	}

	/* ---------------- The Commerce Ops screen ---------------- */

	/**
	 * Register the Commerce Ops page under Settings.
	 */
	public static function menu() {
		add_submenu_page(
			'prx3-settings',
			__( 'Commerce Ops', 'fan-ownership' ),
			__( 'Commerce Ops', 'fan-ownership' ),
			'prx3_admin',
			'prx3-commerce',
			array( __CLASS__, 'render_ops' )
		);
	}

	/**
	 * The commerce operations screen: webhook health, held orders, and
	 * unclaimed purchases with their resolution actions.
	 */
	public static function render_ops() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		$last    = (array) get_option( 'prx3_shopify_last_webhook', array() );
		$pending = (array) get_option( self::PENDING_OPTION, array() );
		echo '<div class="wrap"><h1>' . esc_html__( 'Commerce Operations', 'fan-ownership' ) . '</h1>';
		echo '<p>' . esc_html(
			isset( $last['at'] )
				? sprintf( /* translators: 1: time, 2: topic. */ __( 'Last Shopify webhook: %1$s (%2$s).', 'fan-ownership' ), $last['at'], $last['topic'] )
				: __( 'No Shopify webhook has been received yet — check the webhook URL and secret once the store is live.', 'fan-ownership' )
		) . '</p>';

		if ( ! $pending ) {
			echo '<p><strong>' . esc_html__( 'Nothing waiting: no held orders and no unclaimed purchases.', 'fan-ownership' ) . '</strong></p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Buyer email', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Order', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Shares', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Paid', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Age', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Status', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Resolve', 'fan-ownership' ) . '</th></tr></thead><tbody>';
		foreach ( $pending as $email => $entries ) {
			foreach ( $entries as $entry ) {
				$age    = self::age_days( $entry );
				$status = ! empty( $entry['flagged'] )
					? sprintf( /* translators: %s reason. */ __( 'HELD — %s (review, then release or refund in Shopify)', 'fan-ownership' ), $entry['reason'] ?? 'review' )
					: __( 'Awaiting signature', 'fan-ownership' );
				if ( empty( $entry['flagged'] ) && $age >= 30 ) {
					$status .= ' — ' . __( 'REFUND REVIEW DUE (30+ days unclaimed)', 'fan-ownership' );
				}
				echo '<tr><td>' . esc_html( $email ) . '</td><td>' . esc_html( $entry['order'] ) . '</td><td>' . (int) $entry['shares'] . '</td><td>' . esc_html( prx3_money( (float) $entry['paid'] ) ) . '</td>';
				echo '<td>' . esc_html( sprintf( /* translators: %d days. */ __( '%d day(s)', 'fan-ownership' ), $age ) ) . '</td>';
				echo '<td>' . esc_html( $status ) . '</td><td>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( 'prx3_commerce_resolve' );
				echo '<input type="hidden" name="action" value="prx3_commerce_resolve">';
				echo '<input type="hidden" name="entry_email" value="' . esc_attr( $email ) . '">';
				echo '<input type="hidden" name="entry_order" value="' . esc_attr( $entry['order'] ) . '">';
				echo '<p style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">';
				if ( ! empty( $entry['flagged'] ) ) {
					echo '<button class="button button-primary" name="do" value="release">' . esc_html__( 'Release (grant after review)', 'fan-ownership' ) . '</button>';
				} else {
					echo '<button class="button" name="do" value="remind">' . esc_html__( 'Resend invite', 'fan-ownership' ) . '</button>';
				}
				echo '<input type="email" name="to_email" placeholder="' . esc_attr__( 'correct email', 'fan-ownership' ) . '">';
				echo '<button class="button" name="do" value="reassign">' . esc_html__( 'Reassign', 'fan-ownership' ) . '</button>';
				echo '<button class="button" name="do" value="drop" onclick="return confirm(\'' . esc_js( __( 'Only drop after refunding in Shopify. Continue?', 'fan-ownership' ) ) . '\')">' . esc_html__( 'Drop (refunded)', 'fan-ownership' ) . '</button>';
				echo '</p></form></td></tr>';
			}
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Unclaimed purchases are chased automatically at 3 and 10 days; at 30 days they are flagged for refund review. Held orders are never granted without the Release action. Refunds are always issued in Shopify — the refund webhook cleans up here automatically.', 'fan-ownership' ) . '</p></div>';
	}

	/**
	 * Resolve actions from the ops screen: release, reassign, remind, drop.
	 */
	public static function handle_resolve() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_commerce_resolve' );
		$email = isset( $_POST['entry_email'] ) ? sanitize_email( wp_unslash( $_POST['entry_email'] ) ) : '';
		$order = isset( $_POST['entry_order'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_order'] ) ) : '';
		$do    = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		if ( 'release' === $do ) {
			$result = self::release_pending( $email, $order );
			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ) );
			}
		} elseif ( 'reassign' === $do ) {
			$to = isset( $_POST['to_email'] ) ? sanitize_email( wp_unslash( $_POST['to_email'] ) ) : '';
			if ( ! $to || ! self::reassign_pending( $email, $order, $to ) ) {
				wp_die( esc_html__( 'Reassign needs a valid email and a matching waiting purchase.', 'fan-ownership' ) );
			}
		} elseif ( 'drop' === $do ) {
			self::drop_pending( $email, $order );
		} elseif ( 'remind' === $do && class_exists( 'PRX3_Comms' ) ) {
			PRX3_Comms::send(
				$email,
				sprintf( /* translators: %s club. */ __( 'Your %s shares are waiting for you', 'fan-ownership' ), prx3_club_name() ),
				sprintf( /* translators: %s URL. */ __( "Sign in (or register with this email address) and sign the Shareholders' Agreement to claim your shares: %s", 'fan-ownership' ), home_url( '/' ) ),
				'governance'
			);
		}
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-commerce' ) );
		exit;
	}
}
