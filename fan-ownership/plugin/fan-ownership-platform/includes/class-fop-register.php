<?php
/**
 * The statutory register of members: immutable event log + export.
 *
 * FO-111. Every acquisition and surrender is an append-only row;
 * corrections are new entries, never edits (AC4).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Register {

	public static function init() {
		add_action( 'admin_post_fop_export_register', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Create custom tables (register + audit) on activation.
	 */
	public static function install_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$wpdb->prefix}fop_share_register (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			recorded_at DATETIME NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			event VARCHAR(20) NOT NULL,
			shares INT NOT NULL,
			holding_after INT NOT NULL,
			source VARCHAR(40) NOT NULL,
			consideration DECIMAL(12,2) NULL,
			context LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY event (event)
		) $charset;" );

		dbDelta( FOP_Audit::table_sql( $charset, $wpdb->prefix ) );

		dbDelta( "CREATE TABLE {$wpdb->prefix}fop_ballot_votes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ballot_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			choice INT NOT NULL,
			weight INT NOT NULL,
			cast_at DATETIME NOT NULL,
			revised INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY ballot_user (ballot_id, user_id),
			KEY ballot_id (ballot_id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}fop_match_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			match_id BIGINT UNSIGNED NOT NULL,
			client_key VARCHAR(64) NOT NULL,
			reporter BIGINT UNSIGNED NOT NULL,
			event_type VARCHAR(20) NOT NULL,
			minute SMALLINT NULL,
			detail LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			corrected_by BIGINT UNSIGNED NULL,
			removed TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY match_client (match_id, client_key),
			KEY match_id (match_id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}fop_chat_messages (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			room VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			body TEXT NOT NULL,
			created_at DATETIME NOT NULL,
			held TINYINT(1) NOT NULL DEFAULT 0,
			removed TINYINT(1) NOT NULL DEFAULT 0,
			removed_by BIGINT UNSIGNED NULL,
			PRIMARY KEY (id),
			KEY room_id (room, id)
		) $charset;" );
	}

	/**
	 * Record a register event. Append-only by construction.
	 *
	 * @param int    $user_id User.
	 * @param string $event   'acquisition'|'surrender'|'correction'.
	 * @param int    $shares  Shares in the event.
	 * @param string $source  purchase|gift|admin|left|expelled|deceased|duplicate.
	 * @param array  $context May include 'consideration' (money paid).
	 */
	public static function record( $user_id, $event, $shares, $source, $context = array() ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'fop_share_register',
			array(
				'recorded_at'   => fop_now(),
				'user_id'       => $user_id,
				'event'         => $event,
				'shares'        => (int) $shares,
				'holding_after' => fop_shares( $user_id ),
				'source'        => $source,
				'consideration' => isset( $context['consideration'] ) ? (float) $context['consideration'] : null,
				'context'       => wp_json_encode( $context ),
			),
			array( '%s', '%d', '%s', '%d', '%d', '%s', '%f', '%s' )
		);
	}

	/**
	 * FO-111 AC2: one-click export in an accountant-usable format (CSV).
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'fop_admin' ) ) {
			wp_die( esc_html__( 'You are not allowed to export the register.', 'fan-ownership' ) );
		}
		check_admin_referer( 'fop_export_register' );
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}fop_share_register ORDER BY id ASC", ARRAY_A );

		FOP_Audit::log( 'register_export', 'Share register exported' );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=register-of-members-' . gmdate( 'Ymd-His' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Entry', 'Recorded at', 'Member name', 'Email', 'Owner number', 'Event', 'Shares', 'Holding after', 'Source', 'Consideration', 'Context' ) );
		foreach ( $rows as $row ) {
			$user = get_userdata( (int) $row['user_id'] );
			fputcsv( $out, array(
				$row['id'],
				$row['recorded_at'],
				$user ? $user->display_name : ( 'ERASED #' . $row['user_id'] ),
				$user ? $user->user_email : '',
				$user ? FOP_Shares::owner_number( $user->ID ) : '',
				$row['event'],
				$row['shares'],
				$row['holding_after'],
				$row['source'],
				$row['consideration'],
				$row['context'],
			) );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}

	/**
	 * A member's register history (their own data view + admin).
	 *
	 * @param int $user_id User.
	 * @return array[]
	 */
	public static function history( $user_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fop_share_register WHERE user_id = %d ORDER BY id ASC", $user_id ),
			ARRAY_A
		);
	}
}
