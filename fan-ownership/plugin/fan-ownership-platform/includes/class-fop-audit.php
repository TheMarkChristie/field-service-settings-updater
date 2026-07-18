<?php
/**
 * Append-only audit log for governed actions: role changes, kill
 * switches, register events, moderation, board actions, vault views.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Audit {

	public static function init() {}

	/**
	 * Create the audit table (called from FOP_Register::install_tables).
	 */
	public static function table_sql( $charset_collate, $prefix ) {
		return "CREATE TABLE {$prefix}fop_audit (
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
		$wpdb->insert(
			$wpdb->prefix . 'fop_audit',
			array(
				'logged_at' => fop_now(),
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
		$sql      = 'SELECT * FROM ' . $wpdb->prefix . 'fop_audit WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$params[] = $limit;
		$params[] = $offset;
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
