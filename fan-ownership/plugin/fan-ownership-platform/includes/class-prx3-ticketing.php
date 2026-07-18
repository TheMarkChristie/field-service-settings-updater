<?php
/**
 * Ticketing integration (external provider — Fanbase by default).
 *
 * P46: 5% matchday discount per share, 10% season-ticket discount per
 * share (a full 10-share holding = free season ticket). Tickets sell on
 * the external provider, so the platform issues each owner a personal
 * discount code reflecting their entitlement, and gives the club a CSV
 * export to load into the provider. The prx3_ticketing_entitlement
 * action fires whenever an owner's entitlement changes, ready for a
 * direct API integration if the provider offers one.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Ticketing {

	public static function init() {
		add_action( 'prx3_shares_granted', array( __CLASS__, 'refresh_entitlement' ), 20 );
		add_action( 'prx3_shares_surrendered', array( __CLASS__, 'refresh_entitlement' ), 20 );
		add_action( 'admin_post_prx3_export_ticketing', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * The provider's display name (configurable; Fanbase by default).
	 *
	 * @return string
	 */
	public static function provider() {
		return prx3_setting( 'ticketing_provider', 'Fanbase' );
	}

	/**
	 * An owner's personal ticketing discount code. Stable while their
	 * holding is stable; rotates when their entitlement changes so stale
	 * codes at the provider stop matching.
	 *
	 * @param int $user_id Owner.
	 * @return string Empty for non-owners.
	 */
	public static function member_code( $user_id ) {
		if ( ! prx3_is_owner( $user_id ) ) {
			return '';
		}
		$code = get_user_meta( $user_id, 'prx3_ticket_code', true );
		if ( ! $code ) {
			$code = self::generate_code( $user_id );
		}
		return $code;
	}

	private static function generate_code( $user_id ) {
		$shares = prx3_shares( $user_id );
		$code   = strtoupper(
			sprintf(
				'%s%d-%s',
				preg_replace( '/[^A-Z]/', '', strtoupper( substr( prx3_club_name(), 0, 3 ) ) ),
				PRX3_Shares::owner_number( $user_id ),
				substr( wp_hash( $user_id . '|' . $shares . '|' . wp_salt( 'nonce' ) ), 0, 6 )
			)
		);
		update_user_meta( $user_id, 'prx3_ticket_code', $code );
		return $code;
	}

	/**
	 * Entitlement changed (shares bought or surrendered): rotate the code
	 * and tell any connected provider integration.
	 *
	 * @param int $user_id Owner.
	 */
	public static function refresh_entitlement( $user_id ) {
		delete_user_meta( $user_id, 'prx3_ticket_code' );
		$code      = self::member_code( $user_id );
		$discounts = PRX3_Shares::ticket_discounts( $user_id );

		/**
		 * Fires when an owner's ticketing entitlement changes.
		 * A provider API integration (e.g. Fanbase, when available) hooks
		 * here to sync the code and percentages directly.
		 *
		 * @param int    $user_id   Owner.
		 * @param string $code      Personal discount code ('' if no longer an owner).
		 * @param array  $discounts ['matchday' => int, 'season' => int] percentages.
		 */
		do_action( 'prx3_ticketing_entitlement', $user_id, $code, $discounts );
	}

	/**
	 * CSV export for upload to the provider: one row per owner with their
	 * code and discount percentages. Owner-Admins only, audited.
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'You are not allowed to export ticketing codes.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_export_ticketing' );
		PRX3_Audit::log( 'ticketing_export', sprintf( 'Ticketing discount codes exported for %s', self::provider() ) );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( strtolower( self::provider() ) ) . '-discount-codes-' . gmdate( 'Ymd' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Owner number', 'Name', 'Email', 'Discount code', 'Matchday discount %', 'Season ticket discount %', 'Shares' ) );
		foreach ( get_users(
			array(
				'role'   => 'fan_owner',
				'number' => -1,
			)
		) as $owner ) {
			$discounts = PRX3_Shares::ticket_discounts( $owner->ID );
			fputcsv(
				$out,
				array(
					PRX3_Shares::owner_number( $owner->ID ),
					$owner->display_name,
					$owner->user_email,
					self::member_code( $owner->ID ),
					$discounts['matchday'],
					$discounts['season'],
					prx3_shares( $owner->ID ),
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
