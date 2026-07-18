<?php
/**
 * Append-only audit log for governed actions: role changes, kill
 * switches, register events, moderation, board actions, vault views.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Append-only audit log in its own custom table: who did what, when,
 * across governed actions platform-wide. Entries are never edited.
 */
class PRX3_Audit {

	/**
	 * No hooks of its own; modules call log() directly.
	 */
	public static function init() {}

	/**
	 * Create the audit table (called from PRX3_Register::install_tables).
	 *
	 * @param string $charset_collate Charset/collation clause from $wpdb.
	 * @param string $prefix          Table prefix.
	 * @return string CREATE TABLE statement for dbDelta.
	 */
	public static function table_sql( $charset_collate, $prefix ) {
		return "CREATE TABLE {$prefix}prx3_audit (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			logged_at DATETIME NOT NULL,
			actor BIGINT UNSIGNED NOT NULL DEFAULT 0,
			event VARCHAR(64) NOT NULL,
			summary TEXT NOT NULL,
			context LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY event (event),
			KEY actor (actor)
		) $charset_collate;";
	}

	/**
	 * Append an audit entry. Never updates or deletes existing rows.
	 *
	 * @param string $event   Machine event key.
	 * @param string $summary Human summary.
	 * @param array  $context Extra data.
	 */
	public static function log( $event, $summary, $context = array() ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- append to the plugin's own audit table; no WP API covers it.
		$wpdb->insert(
			$wpdb->prefix . 'prx3_audit',
			array(
				'logged_at' => prx3_now(),
				'actor'     => get_current_user_id(),
				'event'     => substr( $event, 0, 64 ),
				'summary'   => $summary,
				'context'   => wp_json_encode( $context ),
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Read entries (staff/board only screens use this).
	 *
	 * @param array $args event, actor, limit, offset.
	 * @return array[]
	 */
	public static function entries( $args = array() ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['event'] ) ) {
			$where[]  = 'event = %s';
			$params[] = $args['event'];
		}
		if ( ! empty( $args['actor'] ) ) {
			$where[]  = 'actor = %d';
			$params[] = (int) $args['actor'];
		}
		$limit    = isset( $args['limit'] ) ? (int) $args['limit'] : 100;
		$offset   = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
		$sql      = 'SELECT * FROM ' . $wpdb->prefix . 'prx3_audit WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$params[] = $limit;
		$params[] = $offset;
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded read of the plugin's own audit table; uncached so staff screens always show the live record.
	}
}
