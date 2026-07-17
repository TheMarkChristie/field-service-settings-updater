<?php
/**
 * Email subscribers: site visitors (not WP users) who sign up for the
 * weekly digest via the website. Stored in their own table with an
 * unsubscribe token; the [synpro_subscribe] shortcode renders the form.
 *
 * @package Syndicate_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Synpro_Subscribers' ) ) :

class Synpro_Subscribers {

	const DB_VERSION = '2';

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'install' ), 5 );
		add_shortcode( 'synpro_subscribe', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_post_nopriv_synpro_subscribe', array( __CLASS__, 'handle_subscribe' ) );
		add_action( 'admin_post_synpro_subscribe', array( __CLASS__, 'handle_subscribe' ) );
		add_action( 'admin_post_nopriv_synpro_unsubscribe', array( __CLASS__, 'handle_unsubscribe' ) );
		add_action( 'admin_post_synpro_unsubscribe', array( __CLASS__, 'handle_unsubscribe' ) );
		add_action( 'admin_post_nopriv_synpro_confirm', array( __CLASS__, 'handle_confirm' ) );
		add_action( 'admin_post_synpro_confirm', array( __CLASS__, 'handle_confirm' ) );
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'synpro_subscribers';
	}

	/**
	 * Create the table.
	 */
	public static function install() {
		if ( get_option( 'synpro_subscribers_db_version' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		$existing = get_option( 'synpro_subscribers_db_version' );
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			'CREATE TABLE ' . self::table() . " (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				email VARCHAR(190) NOT NULL,
				token VARCHAR(64) NOT NULL,
				confirmed TINYINT(1) NOT NULL DEFAULT 0,
				confirmed_at DATETIME DEFAULT NULL,
				created_at DATETIME DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY email (email)
			) " . $wpdb->get_charset_collate() . ';'
		);
		// Grandfather any rows that pre-date double opt-in (v1 → v2) so
		// existing subscribers aren't silently dropped from the digest.
		if ( $existing && version_compare( $existing, '2', '<' ) ) {
			$wpdb->query( 'UPDATE ' . self::table() . ' SET confirmed = 1 WHERE confirmed = 0' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		update_option( 'synpro_subscribers_db_version', self::DB_VERSION );
	}

	/**
	 * Confirmed subscribers (the digest recipients).
	 *
	 * @return object[] Rows with email + token.
	 */
	public static function all() {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT email, token FROM ' . self::table() . ' WHERE confirmed = 1 ORDER BY id' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Confirmed subscriber count (digest recipients).
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE confirmed = 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Count of subscribers who signed up but haven't confirmed yet.
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE confirmed = 0' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Add a subscriber (idempotent) and send a confirmation email when a
	 * new, unconfirmed row is created (double opt-in). Returns a status:
	 * 'sent' (confirmation emailed), 'exists' (already confirmed), or
	 * false (invalid email).
	 *
	 * @param string $email Email address.
	 * @return string|false
	 */
	public static function add( $email ) {
		global $wpdb;
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return false;
		}

		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id, token, confirmed FROM ' . self::table() . ' WHERE email = %s', $email ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $existing && (int) $existing->confirmed === 1 ) {
			return 'exists'; // Already on the list.
		}

		$token = $existing ? $existing->token : wp_generate_password( 40, false );
		if ( ! $existing ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'INSERT IGNORE INTO ' . self::table() . ' (email, token, confirmed, created_at) VALUES (%s, %s, 0, %s)',
					$email,
					$token,
					current_time( 'mysql', true )
				)
			);
		}

		self::send_confirmation( $email, $token );
		return 'sent';
	}

	/**
	 * Email the double opt-in confirmation link.
	 *
	 * @param string $email Email address.
	 * @param string $token Row token.
	 */
	protected static function send_confirmation( $email, $token ) {
		$confirm_url = add_query_arg(
			array( 'action' => 'synpro_confirm', 'token' => rawurlencode( $token ) ),
			admin_url( 'admin-post.php' )
		);
		$site = get_bloginfo( 'name' );
		$body = sprintf(
			/* translators: 1: site name, 2: confirmation URL. */
			__( "Thanks for subscribing to the %1\$s weekly digest.\n\nPlease confirm your subscription by clicking this link:\n%2\$s\n\nIf you didn't request this, just ignore this email — you won't be added.", 'syndicate-pro' ),
			$site,
			$confirm_url
		);
		wp_mail(
			$email,
			/* translators: %s: site name. */
			sprintf( __( 'Confirm your subscription to %s', 'syndicate-pro' ), $site ),
			$body
		);
	}

	/**
	 * The unsubscribe URL for a subscriber row.
	 *
	 * @param object $row Subscriber row.
	 * @return string
	 */
	public static function unsubscribe_url( $row ) {
		return add_query_arg(
			array(
				'action' => 'synpro_unsubscribe',
				'token'  => rawurlencode( $row->token ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Subscribe form shortcode.
	 *
	 * @return string
	 */
	public static function shortcode() {
		$out  = '<form class="synpro-subscribe" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="synpro_subscribe">';
		$out .= wp_nonce_field( 'synpro_subscribe', '_wpnonce', true, false );
		// Honeypot: bots fill it, humans never see it.
		$out .= '<input type="text" name="synpro_website" value="" style="position:absolute;left:-9999px" tabindex="-1" autocomplete="off" aria-hidden="true">';
		$out .= '<label class="screen-reader-text" for="synpro-sub-email">' . esc_html__( 'Email address', 'syndicate-pro' ) . '</label>';
		$out .= '<input id="synpro-sub-email" type="email" name="synpro_email" required placeholder="' . esc_attr__( 'you@example.com', 'syndicate-pro' ) . '">';
		$out .= '<button type="submit">' . esc_html__( 'Subscribe', 'syndicate-pro' ) . '</button>';
		if ( isset( $_GET['synpro_check_email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$out .= '<p class="synpro-subscribe-done">' . esc_html__( 'Almost there! Check your inbox for a confirmation link to complete your subscription.', 'syndicate-pro' ) . '</p>';
		} elseif ( isset( $_GET['synpro_subscribed'] ) ) { // legacy success flag. phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$out .= '<p class="synpro-subscribe-done">' . esc_html__( 'You’re subscribed — see you in the next digest!', 'syndicate-pro' ) . '</p>';
		} elseif ( isset( $_GET['synpro_sub_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$out .= '<p class="synpro-subscribe-done">' . esc_html__( 'That didn’t work — please check the address and try again in a minute.', 'syndicate-pro' ) . '</p>';
		}
		$out .= '</form>';
		return $out;
	}

	/**
	 * Handle a subscribe submission.
	 */
	public static function handle_subscribe() {
		check_admin_referer( 'synpro_subscribe' );

		// Honeypot filled = bot.
		if ( ! empty( $_POST['synpro_website'] ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$back = remove_query_arg( array( 'synpro_subscribed', 'synpro_sub_error' ), $back );

		// Light rate limit: one signup per IP per minute (script deterrent).
		$ip_key = 'synpro_sub_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( get_transient( $ip_key ) ) {
			wp_safe_redirect( add_query_arg( 'synpro_sub_error', '1', $back ) );
			exit;
		}

		$email  = isset( $_POST['synpro_email'] ) ? sanitize_email( wp_unslash( $_POST['synpro_email'] ) ) : '';
		$result = $email ? self::add( $email ) : false;
		if ( $result ) {
			set_transient( $ip_key, 1, MINUTE_IN_SECONDS );
		}
		// 'sent' = confirmation emailed; 'exists' = already confirmed;
		// false = invalid. All non-false cases show the "check your inbox"
		// message so we never disclose whether an address is already on the
		// list.
		$flag = $result ? 'synpro_check_email' : 'synpro_sub_error';
		wp_safe_redirect( add_query_arg( $flag, '1', $back ) );
		exit;
	}

	/**
	 * Handle a confirmation (double opt-in) link.
	 */
	public static function handle_confirm() {
		global $wpdb;
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows  = $token
			? (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET confirmed = 1, confirmed_at = %s WHERE token = %s AND confirmed = 0', current_time( 'mysql', true ), $token ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			: 0;

		// Already-confirmed tokens still resolve to a friendly message.
		$known = $rows || ( $token && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE token = %s', $token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $known ) {
			wp_die(
				esc_html__( 'Thanks — your subscription is confirmed. See you in the next digest!', 'syndicate-pro' ),
				esc_html__( 'Subscription confirmed', 'syndicate-pro' ),
				array( 'response' => 200 )
			);
		}
		wp_die(
			esc_html__( 'This confirmation link is invalid or has expired. Please subscribe again.', 'syndicate-pro' ),
			esc_html__( 'Link not recognised', 'syndicate-pro' ),
			array( 'response' => 404 )
		);
	}

	/**
	 * Handle an unsubscribe link.
	 */
	public static function handle_unsubscribe() {
		global $wpdb;
		$token   = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$removed = $token ? (int) $wpdb->delete( self::table(), array( 'token' => $token ), array( '%s' ) ) : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( $removed ) {
			wp_die(
				esc_html__( 'You have been unsubscribed from the weekly digest.', 'syndicate-pro' ),
				esc_html__( 'Unsubscribed', 'syndicate-pro' ),
				array( 'response' => 200 )
			);
		}
		// Don't claim success for a mangled or already-used link — the
		// reader would think they'd unsubscribed while still on the list.
		wp_die(
			esc_html__( 'This unsubscribe link is invalid or was already used. If you still receive the digest, reply to it and we’ll remove you by hand.', 'syndicate-pro' ),
			esc_html__( 'Link not recognised', 'syndicate-pro' ),
			array( 'response' => 404 )
		);
	}

	/**
	 * Drop the table (uninstall).
	 */
	public static function drop() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		delete_option( 'synpro_subscribers_db_version' );
	}
}

endif;
