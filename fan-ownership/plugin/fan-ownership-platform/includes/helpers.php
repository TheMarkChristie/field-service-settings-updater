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
function prx3_setting( $key, $default_value = '' ) {
	$settings = get_option( 'prx3_settings', array() );
	return isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : $default_value;
}

/**
 * Update one platform setting.
 *
 * @param string $key   Setting key.
 * @param mixed  $value Value.
 */
function prx3_update_setting( $key, $value ) {
	$settings         = get_option( 'prx3_settings', array() );
	$settings[ $key ] = $value;
	update_option( 'prx3_settings', $settings );
}

/**
 * Club name — configuration, never hard-coded (FO-104 / T46).
 *
 * @return string
 */
function prx3_club_name() {
	return prx3_setting( 'club_name', (string) ( PRX3_Config::defaults()['club_name'] ?? '' ) );
}

/**
 * A brand-pack asset URL by key (badge, badge_inverted, badge_social,
 * badge_svg, wordmark, favicon, app_icon, email_header, font_file).
 * Assets are uploaded through the Media Library; settings store the
 * attachment IDs.
 *
 * @param string $key Asset key without the brand_/_id wrapping.
 * @return string URL, or '' when not uploaded.
 */
function prx3_brand_asset( $key ) {
	$attachment_id = (int) prx3_setting( 'brand_' . sanitize_key( $key ) . '_id', 0 );
	if ( ! $attachment_id ) {
		return '';
	}
	$url = wp_get_attachment_url( $attachment_id );
	return $url ? $url : '';
}

/**
 * A brand gallery: a multi-select media field stored as comma-separated
 * attachment IDs. Each image's caption (or title) is its label — e.g. a
 * historic kit's season "2019-20".
 *
 * @param string $key Gallery key without the brand_/_ids wrapping.
 * @return array<int,array{url:string,label:string}>
 */
function prx3_brand_gallery( $key ) {
	$ids = array_filter( array_map( 'absint', explode( ',', (string) prx3_setting( 'brand_' . sanitize_key( $key ) . '_ids', '' ) ) ) );
	$out = array();
	foreach ( $ids as $attachment_id ) {
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			continue;
		}
		$caption = wp_get_attachment_caption( $attachment_id );
		$out[]   = array(
			'url'   => $url,
			'label' => $caption ? $caption : get_the_title( $attachment_id ),
		);
	}
	return $out;
}

/**
 * The full brand pack for consumers (API /me, emails, certificates).
 *
 * @return array<string,mixed>
 */
function prx3_brand_pack() {
	return array(
		'badge'            => prx3_brand_asset( 'badge' ),
		'badge_inverted'   => prx3_brand_asset( 'badge_inverted' ),
		'badge_social'     => prx3_brand_asset( 'badge_social' ),
		'badge_svg'        => prx3_brand_asset( 'badge_svg' ),
		'wordmark'         => prx3_brand_asset( 'wordmark' ),
		'favicon'          => prx3_brand_asset( 'favicon' ),
		'app_icon'         => prx3_brand_asset( 'app_icon' ),
		'email_header'     => prx3_brand_asset( 'email_header' ),
		'font_file'        => prx3_brand_asset( 'font_file' ),
		'badge_mono_dark'  => prx3_brand_asset( 'badge_mono_dark' ),
		'badge_mono_light' => prx3_brand_asset( 'badge_mono_light' ),
		'font_name'        => prx3_setting( 'brand_font_name', '' ),
		'secondary_font'   => prx3_setting( 'brand_secondary_font_name', '' ),
		'tagline'          => prx3_setting( 'club_tagline', '' ),
		'mission'          => prx3_setting( 'club_mission', '' ),
		'brand_pack_url'   => home_url( '/brand-pack/' ),
		'primary'          => prx3_setting( 'club_primary', '#1a1a2e' ),
		'secondary'        => prx3_setting( 'club_secondary', '#ffffff' ),
		'tertiary'         => prx3_setting( 'club_tertiary', '#e2b007' ),
	);
}

/**
 * Is a feature enabled? Kill switches per FO-103 / T74.
 *
 * @param string $feature One of: registration, checkout, voting, forum, chat, streams, meetings, ideas, questions.
 * @return bool
 */
function prx3_feature_on( $feature ) {
	$switches = get_option( 'prx3_kill_switches', array() );
	return empty( $switches[ $feature ]['off'] );
}

/**
 * Current user is an owner (fan owner, staff, or board)?
 *
 * @param int|null $user_id User ID, default current.
 * @return bool
 */
function prx3_is_owner( $user_id = null ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return $user_id && ( user_can( $user_id, 'prx3_member' ) || user_can( $user_id, 'manage_options' ) );
}

/**
 * Shares held by a member. 0 for non-owners.
 *
 * @param int|null $user_id User ID, default current.
 * @return int
 */
function prx3_shares( $user_id = null ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return 0;
	}
	return max( 0, min( prx3_max_shares(), (int) get_user_meta( $user_id, 'prx3_shares', true ) ) );
}

/**
 * The share cap per member (P5).
 *
 * @return int
 */
function prx3_max_shares() {
	return (int) apply_filters( 'prx3_max_shares', (int) prx3_setting( 'max_shares', 10 ) );
}

/**
 * Price of the Nth share on the ladder (P46): share 1 = base, each
 * subsequent tier 25% higher, rounded to the penny.
 *
 * @param int $n Tier number, 1-based.
 * @return float
 */
function prx3_share_price( $n ) {
	$base   = (float) prx3_setting( 'share_base_price', 50.0 );
	$growth = (float) prx3_setting( 'share_tier_growth', 0.25 );
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
function prx3_ladder_total( $current_shares, $buying ) {
	$total = 0.0;
	for ( $i = 1; $i <= $buying; $i++ ) {
		$total += prx3_share_price( $current_shares + $i );
	}
	return round( $total, 2 );
}

/**
 * Currency formatting for member-facing prices.
 *
 * @param float $amount Amount.
 * @return string
 */
function prx3_money( $amount ) {
	return prx3_setting( 'currency_symbol', '£' ) . number_format_i18n( (float) $amount, 2 );
}

/**
 * The post type that holds the squad. Defaults to the built-in
 * `prx3_player`, but a club can point the player features at an
 * existing players table from another plugin so there is only one
 * squad list (FanPress Settings → Club).
 *
 * @return string Post type name.
 */
function prx3_player_post_type() {
	$type = (string) prx3_setting( 'player_post_type', 'prx3_player' );
	return '' !== $type ? $type : 'prx3_player';
}

/**
 * The post type that holds matches/fixtures. Defaults to the built-in
 * `prx3_match`, but a club can point the match-linked features (player
 * of the match) at an existing fixtures table from another plugin
 * (FanPress Settings → Club).
 *
 * @return string Post type name.
 */
function prx3_match_post_type() {
	$type = (string) prx3_setting( 'match_post_type', 'prx3_match' );
	return '' !== $type ? $type : 'prx3_match';
}

/**
 * Record member activity (login, vote, view) for the active-owner
 * quorum denominator (P76) and retention automation.
 *
 * @param int|null $user_id User ID, default current.
 */
function prx3_touch_activity( $user_id = null ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( $user_id ) {
		update_user_meta( $user_id, 'prx3_last_active', time() );
	}
}

/**
 * Count owners active within the window (P76 default 12 months).
 *
 * @param int|null $since_timestamp Cutoff; default 12 months ago.
 * @return int
 */
function prx3_active_owner_count( $since_timestamp = null ) {
	$since = $since_timestamp ? $since_timestamp : strtotime( '-12 months' );
	$query = new WP_User_Query(
		array(
			'role'        => 'fan_owner',
			'count_total' => true,
			'fields'      => 'ID',
			'number'      => 1,
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded count on the quorum denominator; caching deliberately avoided on vote reads.
				array(
					'key'     => 'prx3_last_active',
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
function prx3_active_owner_ids( $since_timestamp = null ) {
	$since = $since_timestamp ? $since_timestamp : strtotime( '-12 months' );
	return get_users(
		array(
			'role'       => 'fan_owner',
			'fields'     => 'ID',
			'number'     => -1,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded lookup for governance sends; caching deliberately avoided on vote reads.
				array(
					'key'     => 'prx3_last_active',
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
function prx3_now() {
	return current_time( 'mysql' );
}

/**
 * Format a stored datetime in the site's formats.
 *
 * @param string $datetime MySQL datetime.
 * @return string
 */
function prx3_format_datetime( $datetime ) {
	$ts = $datetime ? strtotime( $datetime ) : false;
	return $ts ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) : '';
}

/**
 * The owner-only post types (guarded by PRX3_Access).
 *
 * @return string[]
 */
function prx3_gated_post_types() {
	return array(
		'prx3_forum_topic',
		'prx3_ballot',
		'prx3_idea',
		'prx3_question',
		'prx3_meeting',
		'prx3_video',
		'prx3_document',
		'prx3_exclusive',
		'prx3_decision',
		'prx3_chapter',
		'prx3_match',
	);
}

/**
 * The board-only post types (guarded harder — FO-226 AC1).
 *
 * @return string[]
 */
function prx3_board_post_types() {
	return array( 'prx3_board_meeting', 'prx3_board_paper', 'prx3_board_thread', 'prx3_board_vote', 'prx3_vault_doc' );
}
