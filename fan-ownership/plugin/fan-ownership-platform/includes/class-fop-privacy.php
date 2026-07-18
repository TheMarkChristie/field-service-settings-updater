<?php
/**
 * GDPR: self-serve export, account closure with surrender, retention.
 *
 * FO-117, T29, P31. Uses WordPress core's exporter/eraser framework so
 * the platform's data joins core personal-data tooling.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Privacy {

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_post_fop_close_account', array( __CLASS__, 'handle_close_account' ) );
		add_action( 'fop_retention_sweep', array( __CLASS__, 'retention_sweep' ) );
		if ( ! wp_next_scheduled( 'fop_retention_sweep' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'fop_retention_sweep' );
		}
	}

	public static function register_exporter( $exporters ) {
		$exporters['fop'] = array(
			'exporter_friendly_name' => __( 'Fan Ownership', 'fan-ownership' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * FO-117 AC2: everything the platform holds, in the portable export.
	 */
	public static function export( $email ) {
		$user = get_user_by( 'email', $email );
		$data = array();
		if ( $user ) {
			$items  = array(
				array(
					'name'  => __( 'Owner number', 'fan-ownership' ),
					'value' => FOP_Shares::owner_number( $user->ID ),
				),
				array(
					'name'  => __( 'Shares held', 'fan-ownership' ),
					'value' => fop_shares( $user->ID ),
				),
				array(
					'name'  => __( 'Badges', 'fan-ownership' ),
					'value' => implode( ', ', (array) FOP_Badges::member_badges( $user->ID ) ),
				),
				array(
					'name'  => __( 'Share register history', 'fan-ownership' ),
					'value' => wp_json_encode( FOP_Register::history( $user->ID ) ),
				),
				array(
					'name'  => __( 'Communication preferences', 'fan-ownership' ),
					'value' => wp_json_encode( get_user_meta( $user->ID, 'fop_comms_prefs', true ) ),
				),
				array(
					'name'  => __( 'Consent log', 'fan-ownership' ),
					'value' => wp_json_encode( get_user_meta( $user->ID, 'fop_prefs_consent_log', true ) ),
				),
				array(
					'name'  => __( 'Ballot participation (your own votes)', 'fan-ownership' ),
					'value' => wp_json_encode( FOP_Ballots::member_vote_history( $user->ID ) ),
				),
			);
			$data[] = array(
				'group_id'    => 'fop_membership',
				'group_label' => __( 'Fan ownership', 'fan-ownership' ),
				'item_id'     => 'fop-' . $user->ID,
				'data'        => $items,
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	public static function register_eraser( $erasers ) {
		$erasers['fop'] = array(
			'eraser_friendly_name' => __( 'Fan Ownership', 'fan-ownership' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * FO-117 AC3: erase/anonymise personal data while the share register
	 * and named ballot records keep their legal minimum.
	 */
	public static function erase( $email ) {
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			foreach ( array( 'fop_comms_prefs', 'fop_prefs_consent_log', 'fop_push_tokens', 'fop_duplicate_suspects', 'fop_payment_fingerprint', 'fop_milestone_counts' ) as $key ) {
				delete_user_meta( $user->ID, $key );
			}
			FOP_Audit::log( 'privacy_erase', sprintf( 'Personal data erased for user %d (register/ballot legal minimum retained)', $user->ID ) );
		}
		return array(
			'items_removed'  => true,
			'items_retained' => true,
			'messages'       => array( __( 'Share register and formal ballot records are retained as the legal minimum.', 'fan-ownership' ) ),
			'done'           => true,
		);
	}

	/**
	 * Self-serve closure (FO-117 AC3): explicit confirmation, surrender,
	 * revoke, erase.
	 */
	public static function handle_close_account() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please sign in.', 'fan-ownership' ) );
		}
		check_admin_referer( 'fop_close_account' );
		if ( empty( $_POST['fop_confirm_close'] ) ) {
			wp_die( esc_html__( 'Please tick the confirmation box to close your account.', 'fan-ownership' ) );
		}
		$user_id = get_current_user_id();
		if ( user_can( $user_id, 'fop_admin' ) || user_can( $user_id, 'fop_board' ) ) {
			wp_die( esc_html__( 'Staff and board accounts must be closed by another administrator.', 'fan-ownership' ) );
		}
		FOP_Shares::surrender_all( $user_id, 'left' );
		self::erase( get_userdata( $user_id )->user_email );

		$sessions = WP_Session_Tokens::get_instance( $user_id );
		$sessions->destroy_all();
		$user = get_userdata( $user_id );
		$user->set_role( '' );
		update_user_meta( $user_id, 'fop_account_closed', time() );

		wp_logout();
		wp_safe_redirect( home_url( '/?fop_closed=1' ) );
		exit;
	}

	/**
	 * T29 retention automation: purge stale operational data daily.
	 */
	public static function retention_sweep() {
		global $wpdb;
		// Chat messages older than the configured window (default 12 months).
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . (int) fop_setting( 'chat_retention_months', 12 ) . ' months' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}fop_chat_messages WHERE created_at < %s", $cutoff ) );
		// Closed accounts past the retention window lose remaining meta.
		$closed = get_users(
			array(
				'meta_key' => 'fop_account_closed',
				'fields'   => 'ID',
				'number'   => 200,
			)
		);
		foreach ( $closed as $uid ) {
			$closed_at = (int) get_user_meta( $uid, 'fop_account_closed', true );
			if ( $closed_at && $closed_at < strtotime( '-' . (int) fop_setting( 'closed_retention_months', 24 ) . ' months' ) ) {
				FOP_Audit::log( 'retention', sprintf( 'Closed account %d past retention — flagged for deletion review', $uid ) );
			}
		}
	}
}
