<?php
/**
 * Shared helpers: settings, access, the share price ladder, activity.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get a platform setting (identity, prices, toggles) with default.
 *
 * @param string $key           Setting key.
 * @param mixed  $default_value Fallback when unset.
 * @return mixed
 */
function fop_setting( $key, $default_value = '' ) {
	$settings = get_option( 'fop_settings', array() );
	return isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : $default_value;
}

/**
 * Update one platform setting.
 *
 * @param string $key   Setting key.
 * @param mixed  $value Value.
 */
function fop_update_setting( $key, $value ) {
	$settings         = get_option( 'fop_settings', array() );
	$settings[ $key ] = $value;
	update_option( 'fop_settings', $settings );
}

/**
 * Club name — configuration, never hard-coded (FO-104 / T46).
 *
 * @return string
 */
function fop_club_name() {
	return fop_setting( 'club_name', 'Perth Panthers' );
}

/**
 * Is a feature enabled? Kill switches per FO-103 / T74.
 *
 * @param string $feature One of: registration, checkout, voting, forum, chat, streams, meetings, ideas, questions.
 * @return bool
 */
function fop_feature_on( $feature ) {
	$switches = get_option( 'fop_kill_switches', array() );
	return empty( $switches[ $feature ]['off'] );
}

/**
 * Current user is an owner (fan owner, staff, or board)?
 *
 * @param int|null $user_id User ID, default current.
 * @return bool
 */
function fop_is_owner( $user_id = null ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return $user_id && ( user_can( $user_id, 'fop_member' ) || user_can( $user_id, 'manage_options' ) );
}

/**
 * Shares held by a member. 0 for non-owners.
 *
 * @param int|null $user_id User ID, default current.
 * @return int
 */
function fop_shares( $user_id = null ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return 0;
	}
	return max( 0, min( fop_max_shares(), (int) get_user_meta( $user_id, 'fop_shares', true ) ) );
}

/**
 * The share cap per member (P5).
 *
 * @return int
 */
function fop_max_shares() {
	return (int) apply_filters( 'fop_max_shares', (int) fop_setting( 'max_shares', 10 ) );
}

/**
 * Price of the Nth share on the ladder (P46): share 1 = base, each
 * subsequent tier 25% higher, rounded to the penny.
 *
 * @param int $n Tier number, 1-based.
 * @return float
 */
function fop_share_price( $n ) {
	$base   = (float) fop_setting( 'share_base_price', 50.0 );
	$growth = (float) fop_setting( 'share_tier_growth', 0.25 );
	return round( $base * pow( 1 + $growth, max( 0, (int) $n - 1 ) ), 2 );
}

/**
 * Total price to go from a current holding to a target holding,
 * continuing from the buyer's position on the ladder (FO-107 AC1).
 *
 * @param int $current_shares Shares already held.
 * @param int $buying         Shares being bought now.
 * @return float
 */
function fop_ladder_total( $current_shares, $buying ) {
	$total = 0.0;
	for ( $i = 1; $i <= $buying; $i++ ) {
		$total += fop_share_price( $current_shares + $i );
	}
	return round( $total, 2 );
}

/**
 * Currency formatting for member-facing prices.
 *
 * @param float $amount Amount.
 * @return string
 */
function fop_money( $amount ) {
	return fop_setting( 'currency_symbol', '£' ) . number_format_i18n( (float) $amount, 2 );
}

/**
 * Record member activity (login, vote, view) for the active-owner
 * quorum denominator (P76) and retention automation.
 *
 * @param int|null $user_id User ID, default current.
 */
function fop_touch_activity( $user_id = null ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( $user_id ) {
		update_user_meta( $user_id, 'fop_last_active', time() );
	}
}

/**
 * Count owners active within the window (P76 default 12 months).
 *
 * @param int|null $since_timestamp Cutoff; default 12 months ago.
 * @return int
 */
function fop_active_owner_count( $since_timestamp = null ) {
	$since = $since_timestamp ? $since_timestamp : strtotime( '-12 months' );
	$query = new WP_User_Query(
		array(
			'role'        => 'fan_owner',
			'count_total' => true,
			'fields'      => 'ID',
			'number'      => 1,
			'meta_query'  => array(
				array(
					'key'     => 'fop_last_active',
					'value'   => $since,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	return (int) $query->get_total();
}

/**
 * IDs of owners active within the window.
 *
 * @param int|null $since_timestamp Cutoff; default 12 months ago.
 * @return int[]
 */
function fop_active_owner_ids( $since_timestamp = null ) {
	$since = $since_timestamp ? $since_timestamp : strtotime( '-12 months' );
	return get_users(
		array(
			'role'       => 'fan_owner',
			'fields'     => 'ID',
			'number'     => -1,
			'meta_query' => array(
				array(
					'key'     => 'fop_last_active',
					'value'   => $since,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
}

/**
 * Site-local "now" as a MySQL datetime string.
 *
 * @return string
 */
function fop_now() {
	return current_time( 'mysql' );
}

/**
 * Format a stored datetime in the site's formats.
 *
 * @param string $datetime MySQL datetime.
 * @return string
 */
function fop_format_datetime( $datetime ) {
	$ts = $datetime ? strtotime( $datetime ) : false;
	return $ts ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) : '';
}

/**
 * The owner-only post types (guarded by FOP_Access).
 *
 * @return string[]
 */
function fop_gated_post_types() {
	return array(
		'fop_ballot',
		'fop_idea',
		'fop_question',
		'fop_meeting',
		'fop_video',
		'fop_document',
		'fop_exclusive',
		'fop_decision',
		'fop_chapter',
		'fop_match',
	);
}

/**
 * The board-only post types (guarded harder — FO-226 AC1).
 *
 * @return string[]
 */
function fop_board_post_types() {
	return array( 'fop_board_meeting', 'fop_board_paper', 'fop_board_thread', 'fop_board_vote', 'fop_vault_doc' );
}
