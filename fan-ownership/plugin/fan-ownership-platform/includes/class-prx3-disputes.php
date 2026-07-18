<?php
/**
 * Payment disputes and chargebacks vs the no-refund policy.
 *
 * A card dispute or refund claws the money back while the member keeps
 * the shares — this module closes that hole. When a share order moves
 * to refunded (or a Stripe dispute is lost and the gateway refunds the
 * order), the shares granted by THAT order are surrendered with reason
 * 'chargeback', gift codes issued by the order are voided (or, if
 * already redeemed, surrendered from the redeemer), the member is told,
 * and everything is audited. Orders merely disputed (on-hold) flag for
 * admin attention without touching shares until the outcome is known.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Disputes {

	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'handle_clawback' ) );
		add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'flag_possible_dispute' ) );
	}

	/**
	 * A dispute has been raised (Stripe gateways hold the order): flag it
	 * to admins; shares stay untouched pending the outcome.
	 *
	 * @param int $order_id Order.
	 */
	public static function flag_possible_dispute( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( '_prx3_fulfilled' ) || $order->get_meta( '_prx3_dispute_flagged' ) ) {
			return;
		}
		$order->update_meta_data( '_prx3_dispute_flagged', time() );
		$order->save();
		PRX3_Audit::log( 'dispute_flag', sprintf( 'Share order %d moved to on-hold after fulfilment — possible payment dispute', $order_id ) );
		foreach ( get_users( array( 'role__in' => array( 'prx3_owner_admin', 'administrator' ) ) ) as $admin ) {
			PRX3_Comms::send(
				$admin->user_email,
				__( 'Possible payment dispute on a share order', 'fan-ownership' ),
				sprintf(
					/* translators: 1: order id, 2: admin URL. */
					__( "Order %1\$d was placed on hold after shares were granted — this is usually a card dispute. Shares are untouched until the outcome; if the payment is clawed back (order refunded), the shares will be surrendered automatically.\n\nReview: %2\$s", 'fan-ownership' ),
					$order_id,
					admin_url( 'post.php?post=' . $order_id . '&action=edit' )
				),
				'governance'
			);
		}
	}

	/**
	 * Money gone (refund processed / dispute lost): unwind the order's
	 * share grants and gifts. Idempotent per order.
	 *
	 * @param int $order_id Order.
	 */
	public static function handle_clawback( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( '_prx3_fulfilled' ) || $order->get_meta( '_prx3_clawback_done' ) ) {
			return;
		}
		$user_id = $order->get_user_id();

		foreach ( $order->get_items() as $item ) {
			$qty = (int) $item->get_meta( 'prx3_shares_qty' );
			if ( ! $qty ) {
				continue;
			}
			if ( $item->get_meta( 'prx3_gift' ) ) {
				self::unwind_gifts( $order_id );
				continue;
			}
			if ( $user_id ) {
				self::surrender_from_order( $user_id, $qty, $order_id );
			}
		}
		$order->update_meta_data( '_prx3_clawback_done', time() );
		$order->save();
		$order->add_order_note( __( 'Fan Ownership: payment clawed back — shares from this order surrendered and gift codes voided.', 'fan-ownership' ) );
		PRX3_Audit::log( 'chargeback', sprintf( 'Order %d refunded/disputed — share grants unwound', $order_id ) );
	}

	/**
	 * Surrender up to $qty shares from a member because this order's
	 * payment failed to stick. Partial holdings are respected: we never
	 * take more than they currently hold.
	 */
	private static function surrender_from_order( $user_id, $qty, $order_id ) {
		$held = prx3_shares( $user_id );
		$take = min( $held, $qty );
		if ( $take < 1 ) {
			return;
		}
		update_user_meta( $user_id, 'prx3_shares', $held - $take );
		PRX3_Register::record(
			$user_id,
			'surrender',
			$take,
			'chargeback',
			array( 'order' => $order_id )
		);
		if ( $held - $take < 1 ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$user->remove_role( 'fan_owner' );
			}
		}
		do_action( 'prx3_shares_surrendered', $user_id, $take, 'chargeback' );
		$user = get_userdata( $user_id );
		if ( $user ) {
			PRX3_Comms::send(
				$user->user_email,
				__( 'Your share payment was reversed', 'fan-ownership' ),
				sprintf(
					/* translators: 1: count, 2: club. */
					_n(
						'The payment for %1$d share in %2$s was reversed (refund or card dispute), so that share has been returned to the club as the terms of membership provide. If you believe this is a mistake, reply to this email and we will put it right.',
						'The payment for %1$d shares in %2$s was reversed (refund or card dispute), so those shares have been returned to the club as the terms of membership provide. If you believe this is a mistake, reply to this email and we will put it right.',
						$take,
						'fan-ownership'
					),
					$take,
					prx3_club_name()
				),
				'governance'
			);
		}
	}

	/**
	 * Gift codes from a clawed-back order: void the unredeemed; surrender
	 * from the redeemer where already redeemed.
	 */
	private static function unwind_gifts( $order_id ) {
		$gifts   = get_option( 'prx3_gift_codes', array() );
		$changed = false;
		foreach ( $gifts as $code => $gift ) {
			if ( (int) $gift['order'] !== (int) $order_id ) {
				continue;
			}
			if ( empty( $gift['redeemed'] ) ) {
				$gifts[ $code ]['voided'] = time();
				PRX3_Audit::log( 'gift_voided', sprintf( 'Gift code %s voided (order %d clawed back)', $code, $order_id ) );
			} else {
				self::surrender_from_order( (int) $gift['redeemed_by'], (int) $gift['shares'], $order_id );
			}
			$changed = true;
		}
		if ( $changed ) {
			update_option( 'prx3_gift_codes', $gifts, false );
		}
	}
}
