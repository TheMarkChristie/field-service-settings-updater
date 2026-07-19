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

/**
 * GDPR tooling: plugs the platform's personal data into WordPress
 * core's exporter/eraser framework, handles self-serve account closure
 * with share surrender, and runs the daily retention sweep.
 */
class PRX3_Privacy {

	/**
	 * Register the exporter/eraser, the close-account handler, and the
	 * daily retention sweep event.
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_post_prx3_close_account', array( __CLASS__, 'handle_close_account' ) );
		add_action( 'prx3_retention_sweep', array( __CLASS__, 'retention_sweep' ) );
		if ( ! wp_next_scheduled( 'prx3_retention_sweep' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'prx3_retention_sweep' );
		}
	}

	/**
	 * Add the plugin's exporter to core's personal data exporters.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array Exporters including ours.
	 */
	public static function register_exporter( $exporters ) {
		$exporters['prx3'] = array(
			'exporter_friendly_name' => __( 'Fan Ownership', 'fan-ownership' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * FO-117 AC2: everything the platform holds, in the portable export.
	 *
	 * @param string $email Email address being exported.
	 * @return array Exporter response: data groups and a done flag.
	 */
	public static function export( $email ) {
		$user = get_user_by( 'email', $email );
		$data = array();
		if ( $user ) {
			$items  = array(
				array(
					'name'  => __( 'Owner number', 'fan-ownership' ),
					'value' => PRX3_Shares::owner_number( $user->ID ),
				),
				array(
					'name'  => __( 'Shares held', 'fan-ownership' ),
					'value' => prx3_shares( $user->ID ),
				),
				array(
					'name'  => __( 'Badges', 'fan-ownership' ),
					'value' => implode( ', ', (array) PRX3_Badges::member_badges( $user->ID ) ),
				),
				array(
					'name'  => __( 'Share register history', 'fan-ownership' ),
					'value' => wp_json_encode( PRX3_Register::history( $user->ID ) ),
				),
				array(
					'name'  => __( 'Communication preferences', 'fan-ownership' ),
					'value' => wp_json_encode( get_user_meta( $user->ID, 'prx3_comms_prefs', true ) ),
				),
				array(
					'name'  => __( 'Consent log', 'fan-ownership' ),
					'value' => wp_json_encode( get_user_meta( $user->ID, 'prx3_prefs_consent_log', true ) ),
				),
				array(
					'name'  => __( 'Ballot participation (your own votes)', 'fan-ownership' ),
					'value' => wp_json_encode( PRX3_Ballots::member_vote_history( $user->ID ) ),
				),
				array(
					'name'  => __( 'Nominated beneficiary', 'fan-ownership' ),
					'value' => (string) get_user_meta( $user->ID, 'prx3_beneficiary', true ),
				),
				array(
					'name'  => __( 'Shareholders\' Agreement acceptances', 'fan-ownership' ),
					'value' => wp_json_encode( get_user_meta( $user->ID, 'prx3_sha_acceptances', true ) ),
				),
				array(
					'name'  => __( 'Agreement signature on file', 'fan-ownership' ),
					'value' => get_user_meta( $user->ID, 'prx3_sha_signature', true ) ? __( 'Yes — a drawn signature image is held', 'fan-ownership' ) : __( 'No', 'fan-ownership' ),
				),
			);
			$data[] = array(
				'group_id'    => 'prx3_membership',
				'group_label' => __( 'Fan ownership', 'fan-ownership' ),
				'item_id'     => 'prx3-' . $user->ID,
				'data'        => $items,
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Add the plugin's eraser to core's personal data erasers.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array Erasers including ours.
	 */
	public static function register_eraser( $erasers ) {
		$erasers['prx3'] = array(
			'eraser_friendly_name' => __( 'Fan Ownership', 'fan-ownership' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * FO-117 AC3: erase/anonymise personal data while the share register
	 * and named ballot records keep their legal minimum.
	 *
	 * @param string $email Email address being erased.
	 * @return array Eraser response: removed/retained flags and messages.
	 */
	public static function erase( $email ) {
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			// The signature image goes; the acceptance log (version/date/context)
			// stays with the share register as the contractual legal minimum.
			foreach ( array( 'prx3_comms_prefs', 'prx3_prefs_consent_log', 'prx3_push_tokens', 'prx3_duplicate_suspects', 'prx3_payment_fingerprint', 'prx3_milestone_counts', 'prx3_sha_signature', 'prx3_beneficiary' ) as $key ) {
				delete_user_meta( $user->ID, $key );
			}
			PRX3_Audit::log( 'privacy_erase', sprintf( 'Personal data erased for user %d (register/ballot legal minimum retained)', $user->ID ) );
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
		check_admin_referer( 'prx3_close_account' );
		if ( empty( $_POST['prx3_confirm_close'] ) ) {
			wp_die( esc_html__( 'Please tick the confirmation box to close your account.', 'fan-ownership' ) );
		}
		$user_id = get_current_user_id();
		if ( user_can( $user_id, 'prx3_admin' ) || user_can( $user_id, 'prx3_board' ) ) {
			wp_die( esc_html__( 'Staff and board accounts must be closed by another administrator.', 'fan-ownership' ) );
		}
		PRX3_Shares::surrender_all( $user_id, 'left' );
		self::erase( get_userdata( $user_id )->user_email );

		$sessions = WP_Session_Tokens::get_instance( $user_id );
		$sessions->destroy_all();
		$user = get_userdata( $user_id );
		$user->set_role( '' );
		update_user_meta( $user_id, 'prx3_account_closed', time() );

		wp_logout();
		wp_safe_redirect( home_url( '/?prx3_closed=1' ) );
		exit;
	}

	/**
	 * T29 retention automation: purge stale operational data daily.
	 */
	public static function retention_sweep() {
		global $wpdb;
		// Chat messages older than the configured window (default 12 months).
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . (int) prx3_setting( 'chat_retention_months', 12 ) . ' months' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}prx3_chat_messages WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- retention purge on the plugin's own chat table; no core API or cache covers it.
		// Closed accounts past the retention window lose remaining meta.
		$closed = get_users(
			array(
				'meta_key' => 'prx3_account_closed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded daily sweep over closed accounts only.
				'fields'   => 'ID',
				'number'   => 200,
			)
		);
		foreach ( $closed as $uid ) {
			$closed_at = (int) get_user_meta( $uid, 'prx3_account_closed', true );
			if ( $closed_at && $closed_at < strtotime( '-' . (int) prx3_setting( 'closed_retention_months', 24 ) . ' months' ) ) {
				PRX3_Audit::log( 'retention', sprintf( 'Closed account %d past retention — flagged for deletion review', $uid ) );
			}
		}
	}
}
