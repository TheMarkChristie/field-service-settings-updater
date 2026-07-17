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

	const DB_VERSION = '1';

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
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			'CREATE TABLE ' . self::table() . " (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				email VARCHAR(190) NOT NULL,
				token VARCHAR(64) NOT NULL,
				created_at DATETIME DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY email (email)
			) " . $wpdb->get_charset_collate() . ';'
		);
		update_option( 'synpro_subscribers_db_version', self::DB_VERSION );
	}

	/**
	 * All subscribers.
	 *
	 * @return object[] Rows with email + token.
	 */
	public static function all() {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT email, token FROM ' . self::table() . ' ORDER BY id' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Subscriber count.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Add a subscriber (idempotent).
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public static function add( $email ) {
		global $wpdb;
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return false;
		}
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::table() . ' (email, token, created_at) VALUES (%s, %s, %s)',
				$email,
				wp_generate_password( 40, false ),
				current_time( 'mysql', true )
			)
		);
		return true;
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
		if ( isset( $_GET['synpro_subscribed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

		$email = isset( $_POST['synpro_email'] ) ? sanitize_email( wp_unslash( $_POST['synpro_email'] ) ) : '';
		$added = $email && self::add( $email );
		if ( $added ) {
			set_transient( $ip_key, 1, MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( add_query_arg( $added ? 'synpro_subscribed' : 'synpro_sub_error', '1', $back ) );
		exit;
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
