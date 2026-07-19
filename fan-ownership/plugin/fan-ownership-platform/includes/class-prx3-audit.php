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
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );}

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
	/**
	 * The Audit Log viewer: every recorded action, newest first.
	 */
	public static function menu() {
		add_submenu_page(
			'prx3-settings',
			__( 'Audit Log', 'fan-ownership' ),
			__( 'Audit Log', 'fan-ownership' ),
			'prx3_admin',
			'prx3-audit-log',
			array( __CLASS__, 'render_screen' )
		);
	}

	/**
	 * Render the latest audit entries with an event filter.
	 */
	public static function render_screen() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		global $wpdb;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$event = isset( $_GET['prx3_event'] ) ? sanitize_key( $_GET['prx3_event'] ) : '';
		echo '<div class="wrap"><h1>' . esc_html__( 'Audit Log', 'fan-ownership' ) . '</h1>';
		echo '<form method="get"><input type="hidden" name="page" value="prx3-audit-log"><p><label for="prx3_event">' . esc_html__( 'Event filter', 'fan-ownership' ) . '</label> <input type="text" id="prx3_event" name="prx3_event" value="' . esc_attr( $event ) . '" placeholder="ballot_open"> <button class="button">' . esc_html__( 'Filter', 'fan-ownership' ) . '</button></p></form>';
		if ( ! is_callable( array( $wpdb, 'get_results' ) ) ) {
			echo '</div>';
			return;
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- plugin-owned audit table, read-only screen.
		if ( $event ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}prx3_audit WHERE event = %s ORDER BY id DESC LIMIT 100", $event ) );
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}prx3_audit ORDER BY id DESC LIMIT 100" );
		}
		// phpcs:enable
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'When', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Event', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Actor', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Summary', 'fan-ownership' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $rows as $row ) {
			$actor = ! empty( $row->user_id ) ? get_userdata( (int) $row->user_id ) : false;
			echo '<tr><td>' . esc_html( (string) ( $row->created_at ?? '' ) ) . '</td><td><code>' . esc_html( (string) $row->event ) . '</code></td><td>' . esc_html( $actor ? $actor->display_name : '—' ) . '</td><td>' . esc_html( (string) $row->summary ) . '</td></tr>';
		}
		echo '</tbody></table><p class="description">' . esc_html__( 'Latest 100 entries. Everything money, vote, identity, and moderation related is recorded here permanently.', 'fan-ownership' ) . '</p></div>';
	}
}
