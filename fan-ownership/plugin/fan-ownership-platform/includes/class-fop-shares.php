<?php
/**
 * The shares engine: grants, owner numbers, caps, surrender.
 *
 * FO-106 (cap enforcement), FO-107 (top-ups), FO-112 (owner numbers),
 * P28/P30/P31 (surrender and transfer rules).
 *
 * Money flows arrive via FOP_WooCommerce (order completion) or
 * FOP_Gifts (redemption); both land here in grant_shares(), the single
 * write path to a member's holding. The register (FOP_Register) records
 * every event immutably.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Shares {

	public static function init() {}

	/**
	 * Grant shares to a member. The ONLY path that increases a holding.
	 *
	 * @param int    $user_id User.
	 * @param int    $count   Shares to add.
	 * @param string $source  'purchase'|'gift'|'admin'.
	 * @param array  $context Order/gift references, consideration paid.
	 * @return true|WP_Error
	 */
	public static function grant_shares( $user_id, $count, $source, $context = array() ) {
		$count   = (int) $count;
		$current = fop_shares( $user_id );
		if ( $count < 1 ) {
			return new WP_Error( 'fop_invalid_count', __( 'Invalid share count.', 'fan-ownership' ) );
		}
		// FO-106 AC3: no route exceeds the cap, combining all sources.
		if ( $current + $count > fop_max_shares() ) {
			return new WP_Error(
				'fop_cap',
				sprintf(
					/* translators: %d cap. */
					__( 'This would exceed the %d-share limit per member.', 'fan-ownership' ),
					fop_max_shares()
				)
			);
		}
		if ( ! get_user_meta( $user_id, 'fop_adult_confirmed', true ) ) {
			return new WP_Error( 'fop_age', __( 'Owners must confirm they are 18 or over before holding shares.', 'fan-ownership' ) );
		}

		update_user_meta( $user_id, 'fop_shares', $current + $count );

		// First shares: promote to owner, allocate owner number, founders check.
		if ( 0 === $current ) {
			$user = get_userdata( $user_id );
			if ( $user && ! user_can( $user, 'fop_member' ) ) {
				$user->add_role( 'fan_owner' );
			}
			self::allocate_owner_number( $user_id );
			do_action( 'fop_member_became_owner', $user_id );
		}

		FOP_Register::record( $user_id, 'acquisition', $count, $source, $context );
		fop_touch_activity( $user_id );

		/**
		 * Fires when shares land: certificates, badges, comms hang off this.
		 *
		 * @param int    $user_id User.
		 * @param int    $count   Shares added.
		 * @param int    $total   New holding.
		 * @param string $source  Source.
		 */
		do_action( 'fop_shares_granted', $user_id, $count, $current + $count, $source );
		return true;
	}

	/**
	 * Surrender shares back to the club (P28/P31/FO-221 expulsion).
	 *
	 * @param int    $user_id User.
	 * @param string $reason  'left'|'expelled'|'deceased'|'duplicate'.
	 * @param array  $context Extra register context.
	 * @return int Shares surrendered.
	 */
	public static function surrender_all( $user_id, $reason, $context = array() ) {
		$held = fop_shares( $user_id );
		if ( $held > 0 ) {
			update_user_meta( $user_id, 'fop_shares', 0 );
			FOP_Register::record( $user_id, 'surrender', $held, $reason, $context );
		}
		$user = get_userdata( $user_id );
		if ( $user ) {
			$user->remove_role( 'fan_owner' );
		}
		do_action( 'fop_shares_surrendered', $user_id, $held, $reason );
		return $held;
	}

	/**
	 * Transfer a deceased member's holding to next of kin (P30). The heir
	 * must hold an account with age confirmation; cap still applies.
	 *
	 * @param int $from_user Deceased member.
	 * @param int $to_user   Beneficiary.
	 * @return true|WP_Error
	 */
	public static function transfer_on_death( $from_user, $to_user ) {
		$held = fop_shares( $from_user );
		if ( $held < 1 ) {
			return new WP_Error( 'fop_none', __( 'No shares to transfer.', 'fan-ownership' ) );
		}
		$granted = self::grant_shares(
			$to_user,
			$held,
			'admin',
			array(
				'transfer' => 'death',
				'from'     => $from_user,
			)
		);
		if ( is_wp_error( $granted ) ) {
			return $granted;
		}
		update_user_meta( $from_user, 'fop_shares', 0 );
		FOP_Register::record( $from_user, 'surrender', $held, 'deceased', array( 'to' => $to_user ) );
		return true;
	}

	/**
	 * FO-112: sequential owner numbers, race-safe, never reused.
	 */
	private static function allocate_owner_number( $user_id ) {
		global $wpdb;
		if ( get_user_meta( $user_id, 'fop_owner_number', true ) ) {
			return;
		}
		// Atomic increment via the options table under a dedicated row.
		$wpdb->query( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES ('fop_owner_seq', '1', 'no') ON DUPLICATE KEY UPDATE option_value = option_value + 1" );
		$number = (int) $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'fop_owner_seq'" );
		update_user_meta( $user_id, 'fop_owner_number', $number );
		wp_cache_delete( 'fop_owner_seq', 'options' );
	}

	/**
	 * A member's owner number.
	 *
	 * @param int $user_id User.
	 * @return int 0 when not yet an owner.
	 */
	public static function owner_number( $user_id ) {
		return (int) get_user_meta( $user_id, 'fop_owner_number', true );
	}

	/**
	 * Ticket discount percentages from the holding (P46).
	 *
	 * @param int $user_id User.
	 * @return array{matchday:int,season:int} Percentages, capped at 100.
	 */
	public static function ticket_discounts( $user_id ) {
		$shares = fop_shares( $user_id );
		return array(
			'matchday' => min( 100, $shares * (int) fop_setting( 'matchday_ticket_discount_per_share', 5 ) ),
			'season'   => min( 100, $shares * (int) fop_setting( 'season_ticket_discount_per_share', 10 ) ),
		);
	}
}
