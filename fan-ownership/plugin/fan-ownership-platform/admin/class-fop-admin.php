<?php
/**
 * Settings screen: identity, prices, governance numbers, integrations,
 * kill switches. Owner-Admins only.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_fop_save_settings', array( __CLASS__, 'save' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Fan Ownership', 'fan-ownership' ),
			__( 'Fan Ownership', 'fan-ownership' ),
			'fop_admin',
			'fop-settings',
			array( __CLASS__, 'render' ),
			'dashicons-groups',
			2
		);
	}

	private static function fields() {
		return array(
			'club'         => array(
				'club_name'       => array( __( 'Club name', 'fan-ownership' ), 'text' ),
				'club_primary'    => array( __( 'Primary colour', 'fan-ownership' ), 'text' ),
				'club_accent'     => array( __( 'Accent colour', 'fan-ownership' ), 'text' ),
				'currency_symbol' => array( __( 'Currency symbol', 'fan-ownership' ), 'text' ),
			),
			'shares'       => array(
				'share_base_price'  => array( __( 'Share 1 price', 'fan-ownership' ), 'number' ),
				'share_tier_growth' => array( __( 'Tier growth (0.25 = +25% per share)', 'fan-ownership' ), 'number' ),
				'max_shares'        => array( __( 'Maximum shares per member', 'fan-ownership' ), 'number' ),
				'share_product_id'  => array( __( 'WooCommerce share product ID', 'fan-ownership' ), 'number' ),
				'checkout_page_id'  => array( __( 'Checkout page ID', 'fan-ownership' ), 'number' ),
				'join_page_id'      => array( __( 'Join page ID', 'fan-ownership' ), 'number' ),
				'invoice_prefix'    => array( __( 'Invoice prefix', 'fan-ownership' ), 'text' ),
				'launch_moment'     => array( __( 'Public launch moment (Founders cutoff, e.g. 2026-09-01 12:00)', 'fan-ownership' ), 'text' ),
			),
			'governance'   => array(
				'quorum_percent'      => array( __( 'Quorum % of active owners', 'fan-ownership' ), 'number' ),
				'constitutional_pct'  => array( __( 'Constitutional supermajority %', 'fan-ownership' ), 'number' ),
				'idea_threshold_pct'  => array( __( 'Idea support threshold %', 'fan-ownership' ), 'number' ),
				'ballot_window_days'  => array( __( 'Default ballot window (days)', 'fan-ownership' ), 'number' ),
				'max_live_ballots'    => array( __( 'Max live ballots', 'fan-ownership' ), 'number' ),
				'board_chair_id'      => array( __( 'Board chair user ID (casting vote in board votes)', 'fan-ownership' ), 'number' ),
				'question_sla_days'   => array( __( 'Question answer target (days)', 'fan-ownership' ), 'number' ),
				'decision_stale_days' => array( __( 'Decision stalled after (days)', 'fan-ownership' ), 'number' ),
			),
			'integrations' => array(
				'cf_account_id'     => array( __( 'Cloudflare account ID', 'fan-ownership' ), 'text' ),
				'cf_api_token'      => array( __( 'Cloudflare API token', 'fan-ownership' ), 'password' ),
				'cf_stream_key_id'  => array( __( 'Stream signing key ID', 'fan-ownership' ), 'text' ),
				'cf_stream_key_pem' => array( __( 'Stream signing key (PEM)', 'fan-ownership' ), 'textarea' ),
				'fcm_server_key'    => array( __( 'Firebase FCM server key', 'fan-ownership' ), 'password' ),
				'chat_blocklist'    => array( __( 'Chat word filter (one per line)', 'fan-ownership' ), 'textarea' ),
			),
		);
	}

	public static function render() {
		if ( ! current_user_can( 'fop_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Fan Ownership Platform', 'fan-ownership' ) . '</h1>';

		// Kill switches (FO-103).
		echo '<h2>' . esc_html__( 'Feature switches', 'fan-ownership' ) . '</h2><p>';
		foreach ( FOP_Config::FEATURES as $feature ) {
			$on = fop_feature_on( $feature );
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 8px 8px 0;">';
			wp_nonce_field( 'fop_toggle_feature' );
			echo '<input type="hidden" name="action" value="fop_toggle_feature"><input type="hidden" name="feature" value="' . esc_attr( $feature ) . '">';
			echo '<button class="button ' . ( $on ? 'button-secondary' : 'button-primary' ) . '">' . esc_html( $feature . ': ' . ( $on ? __( 'ON — switch off', 'fan-ownership' ) : __( 'OFF — switch on', 'fan-ownership' ) ) ) . '</button></form>';
		}
		echo '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'fop_save_settings' );
		echo '<input type="hidden" name="action" value="fop_save_settings">';
		foreach ( self::fields() as $section => $fields ) {
			echo '<h2>' . esc_html( ucfirst( $section ) ) . '</h2><table class="form-table" role="presentation">';
			foreach ( $fields as $key => $def ) {
				$value = fop_setting( $key, FOP_Config::defaults()[ $key ] ?? '' );
				echo '<tr><th scope="row"><label for="fop_' . esc_attr( $key ) . '">' . esc_html( $def[0] ) . '</label></th><td>';
				if ( 'textarea' === $def[1] ) {
					echo '<textarea class="large-text" rows="3" id="fop_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
				} else {
					$type = 'password' === $def[1] ? 'password' : ( 'number' === $def[1] ? 'text' : 'text' );
					echo '<input type="' . esc_attr( $type ) . '" class="regular-text" id="fop_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">';
				}
				echo '</td></tr>';
			}
			echo '</table>';
		}
		echo '<p><button class="button button-primary">' . esc_html__( 'Save settings', 'fan-ownership' ) . '</button></p></form>';

		echo '<h2>' . esc_html__( 'Share register', 'fan-ownership' ) . '</h2>';
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fop_export_register' ), 'fop_export_register' ) ) . '">' . esc_html__( 'Export register of members (CSV)', 'fan-ownership' ) . '</a></p>';
		echo '</div>';
	}

	public static function save() {
		if ( ! current_user_can( 'fop_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'fop_save_settings' );
		foreach ( self::fields() as $fields ) {
			foreach ( $fields as $key => $def ) {
				if ( ! isset( $_POST[ $key ] ) ) {
					continue;
				}
				$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				fop_update_setting( $key, 'textarea' === $def[1] ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw ) );
			}
		}
		FOP_Audit::log( 'settings_saved', 'Platform settings updated' );
		wp_safe_redirect( admin_url( 'admin.php?page=fop-settings&saved=1' ) );
		exit;
	}
}
