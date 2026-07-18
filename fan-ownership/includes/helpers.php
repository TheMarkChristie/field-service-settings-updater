<?php
/**
 * Shared helper functions.
 *
 * @package FanOwnership
 */

defined( 'ABSPATH' ) || exit;

/**
 * The post types that make up the members portal.
 *
 * @return string[]
 */
function fo_post_types() {
	return array( 'fo_video', 'fo_meeting', 'fo_vote', 'fo_idea', 'fo_question', 'fo_document', 'fo_exclusive' );
}

/**
 * Can the given (or current) user access members-only content?
 *
 * @param int|null $user_id User ID, defaults to current user.
 * @return bool
 */
function fo_user_can_access( $user_id = null ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	return user_can( $user_id, 'fo_access' ) || user_can( $user_id, 'manage_options' );
}

/**
 * Get a plugin setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Fallback value.
 * @return mixed
 */
function fo_get_setting( $key, $default = '' ) {
	$settings = get_option( 'fo_settings', array() );
	return isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : $default;
}

/**
 * The club name used in front-end copy.
 *
 * @return string
 */
function fo_club_name() {
	return fo_get_setting( 'club_name', get_bloginfo( 'name' ) );
}

/**
 * URL non-members are sent to when they hit restricted content.
 *
 * Falls back to the WordPress login screen with a redirect back.
 *
 * @param string $requested_url The URL that was blocked.
 * @return string
 */
function fo_join_url( $requested_url = '' ) {
	$page_id = (int) fo_get_setting( 'join_page_id', 0 );
	if ( $page_id && get_post_status( $page_id ) === 'publish' ) {
		return get_permalink( $page_id );
	}
	return wp_login_url( $requested_url ? $requested_url : home_url() );
}

/**
 * Render the "members only" notice shown to logged-out visitors.
 *
 * @return string
 */
function fo_members_only_notice() {
	$login = wp_login_url( get_permalink() ? get_permalink() : home_url() );
	return sprintf(
		'<div class="fo-notice fo-notice--locked"><p>%s</p><p><a class="fo-button" href="%s">%s</a> <a class="fo-button fo-button--secondary" href="%s">%s</a></p></div>',
		esc_html(
			sprintf(
				/* translators: %s: club name. */
				__( 'This area is exclusive to %s fan owners.', 'fan-ownership' ),
				fo_club_name()
			)
		),
		esc_url( $login ),
		esc_html__( 'Log in', 'fan-ownership' ),
		esc_url( fo_join_url( get_permalink() ) ),
		esc_html__( 'Become a fan owner', 'fan-ownership' )
	);
}

/**
 * Maximum shares any one member may own.
 *
 * @return int
 */
function fo_max_shares() {
	return (int) apply_filters( 'fo_max_shares', 10 );
}

/**
 * How many shares a member owns (1–max). Every member holds at least one.
 *
 * @param int|null $user_id User ID, defaults to current user.
 * @return int
 */
function fo_get_shares( $user_id = null ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return 0;
	}
	$shares = (int) get_user_meta( $user_id, 'fo_shares', true );
	return max( 1, min( fo_max_shares(), $shares ? $shares : 1 ) );
}

/**
 * Set a member's share count, clamped to the allowed range.
 *
 * @param int $user_id User ID.
 * @param int $shares  Shares owned.
 */
function fo_set_shares( $user_id, $shares ) {
	update_user_meta( $user_id, 'fo_shares', max( 1, min( fo_max_shares(), (int) $shares ) ) );
}

/**
 * Price of a single share, as configured.
 *
 * @return string e.g. "£50".
 */
function fo_share_price() {
	return fo_get_setting( 'share_price', '£50' );
}

/**
 * Format a stored Y-m-d H:i datetime in the site's date/time format.
 *
 * @param string $datetime MySQL-ish datetime string.
 * @return string Empty string when unparsable.
 */
function fo_format_datetime( $datetime ) {
	if ( ! $datetime ) {
		return '';
	}
	$timestamp = strtotime( $datetime );
	if ( ! $timestamp ) {
		return '';
	}
	return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
}

/**
 * Current time in site timezone as a comparable Y-m-d H:i:s string.
 *
 * @return string
 */
function fo_now() {
	return current_time( 'mysql' );
}
