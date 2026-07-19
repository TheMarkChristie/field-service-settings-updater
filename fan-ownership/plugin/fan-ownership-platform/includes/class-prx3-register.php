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

/**
 * The statutory register of members: append-only event log in a custom
 * table, CSV export, and per-member history. Owns the plugin's custom
 * table schema on activation.
 */
class PRX3_Register {

	/**
	 * Hook the register export handler.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_prx3_export_register', array( __CLASS__, 'handle_export' ) );
		add_action( 'prx3_commitments_tick', array( __CLASS__, 'maybe_monthly_backup' ) );
	}

	/**
	 * Create custom tables (register + audit) on activation.
	 */
	public static function install_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}prx3_share_register (
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
		) $charset;"
		);

		dbDelta( PRX3_Audit::table_sql( $charset, $wpdb->prefix ) );

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}prx3_ballot_votes (
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
		) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}prx3_match_events (
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
		) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}prx3_chat_messages (
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
		) $charset;"
		);
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- append to the plugin's own statutory register table; no WP API covers it.
		$wpdb->insert(
			$wpdb->prefix . 'prx3_share_register',
			array(
				'recorded_at'   => prx3_now(),
				'user_id'       => $user_id,
				'event'         => $event,
				'shares'        => (int) $shares,
				'holding_after' => prx3_shares( $user_id ),
				'source'        => $source,
				'consideration' => isset( $context['consideration'] ) ? (float) $context['consideration'] : null,
				'context'       => wp_json_encode( $context ),
			),
			array( '%s', '%d', '%s', '%d', '%d', '%s', '%f', '%s' )
		);
	}

	/**
	 * Monthly safeguard: email the full register CSV to the site admin
	 * on the first tick of each month, so the statutory record survives
	 * anything that happens to the site (P107 / continuity policy).
	 */
	public static function maybe_monthly_backup() {
		$month = gmdate( 'Y-m' );
		if ( get_option( 'prx3_register_backup_last' ) === $month ) {
			return;
		}
		update_option( 'prx3_register_backup_last', $month, false );
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}prx3_share_register ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- statutory backup reads the authoritative register directly.
		$csv  = "Entry,Recorded at,User,Event,Shares,Holding after,Source,Consideration\n";
		foreach ( $rows as $row ) {
			$csv .= implode( ',', array( $row['id'], $row['recorded_at'], $row['user_id'], $row['event'], $row['shares'], $row['holding_after'], $row['source'], (string) $row['consideration'] ) ) . "\n";
		}
		$path = get_temp_dir() . 'prx3-register-' . $month . '.csv';
		file_put_contents( $path, $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- transient temp file for the mail attachment, removed below.
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( /* translators: %s month. */ __( 'Register of members — monthly safeguard copy (%s)', 'fan-ownership' ), $month ),
			__( 'Attached is the automatic monthly export of the register of members. Store it with the continuity records (two officers should hold copies).', 'fan-ownership' ),
			array( 'Content-Type: text/plain; charset=UTF-8' ),
			array( $path )
		);
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- removing our own temp file.
		PRX3_Audit::log( 'register_backup', sprintf( 'Monthly register safeguard emailed (%s)', $month ) );
	}

	/**
	 * FO-111 AC2: one-click export in an accountant-usable format (CSV).
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'You are not allowed to export the register.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_export_register' );
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}prx3_share_register ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- statutory export must read the authoritative register directly, never a cache.

		PRX3_Audit::log( 'register_export', 'Share register exported' );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=register-of-members-' . gmdate( 'Ymd-His' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Entry', 'Recorded at', 'Member name', 'Email', 'Owner number', 'Event', 'Shares', 'Holding after', 'Source', 'Consideration', 'Context' ) );
		foreach ( $rows as $row ) {
			$user = get_userdata( (int) $row['user_id'] );
			fputcsv(
				$out,
				array(
					$row['id'],
					$row['recorded_at'],
					$user ? $user->display_name : ( 'ERASED #' . $row['user_id'] ),
					$user ? $user->user_email : '',
					$user ? PRX3_Shares::owner_number( $user->ID ) : '',
					$row['event'],
					$row['shares'],
					$row['holding_after'],
					$row['source'],
					$row['consideration'],
					$row['context'],
				)
			);
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- money-path read of the plugin's own register table; deliberately uncached so it is always authoritative.
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}prx3_share_register WHERE user_id = %d ORDER BY id ASC", $user_id ),
			ARRAY_A
		);
	}
	/**
	 * The Share Register screen: the statutory list, in the Board menu
	 * only (P128) — visible to board members and Owner-Admins, nobody
	 * else.
	 */
	public static function menu() {
		add_submenu_page(
			'prx3-board',
			__( 'Share Register', 'fan-ownership' ),
			__( 'Share Register', 'fan-ownership' ),
			current_user_can( 'prx3_board' ) ? 'prx3_board' : 'prx3_admin',
			'prx3-share-register',
			array( __CLASS__, 'render_screen' )
		);
	}

	/**
	 * Render the register: holdings summary then the recent event log,
	 * or a single owner's full share record when one is selected.
	 */
	public static function render_screen() {
		if ( ! current_user_can( 'prx3_board' ) && ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Board members and Owner-Admins only.', 'fan-ownership' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only drill-down by user ID.
		$owner = isset( $_GET['owner'] ) ? absint( $_GET['owner'] ) : 0;
		if ( $owner ) {
			self::render_owner_detail( $owner );
			return;
		}
		global $wpdb;
		echo '<div class="wrap"><h1>' . esc_html__( 'Share Register', 'fan-ownership' ) . '</h1>';
		echo '<p>' . esc_html__( 'The statutory register of members: append-only, every movement recorded. Click an owner to see their full share record. The CSV export lives in FanPress Settings → Shares & Checkout.', 'fan-ownership' ) . '</p>';
		if ( ! is_callable( array( $wpdb, 'get_results' ) ) ) {
			echo '</div>';
			return;
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- plugin-owned register table, read-only screen.
		$latest = $wpdb->get_results( "SELECT r.user_id, r.holding_after, r.event, r.recorded_at FROM {$wpdb->prefix}prx3_share_register r INNER JOIN ( SELECT user_id, MAX(id) AS max_id FROM {$wpdb->prefix}prx3_share_register GROUP BY user_id ) x ON x.max_id = r.id ORDER BY r.holding_after DESC" );
		echo '<h2>' . esc_html__( 'Current holdings', 'fan-ownership' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Owner', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Owner #', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Holding', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Last event', 'fan-ownership' ) . '</th><th>' . esc_html__( 'When', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Links', 'fan-ownership' ) . '</th></tr></thead><tbody>';
		$total = 0;
		foreach ( (array) $latest as $row ) {
			$member = get_userdata( (int) $row->user_id );
			$total += (int) $row->holding_after;
			$detail = esc_url( admin_url( 'admin.php?page=prx3-share-register&owner=' . (int) $row->user_id ) );
			echo '<tr><td><a href="' . $detail . '"><strong>' . esc_html( $member ? $member->display_name : '#' . (int) $row->user_id ) . '</strong></a></td>';
			echo '<td>' . (int) PRX3_Shares::owner_number( (int) $row->user_id ) . '</td>';
			echo '<td><strong>' . (int) $row->holding_after . '</strong></td>';
			echo '<td>' . esc_html( $row->event ) . '</td>';
			echo '<td>' . esc_html( prx3_format_datetime( (string) $row->recorded_at ) ) . '</td>';
			echo '<td>' . self::owner_links( (int) $row->user_id ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped anchors below.
		}
		echo '</tbody></table>';
		echo '<p><strong>' . esc_html( sprintf( /* translators: 1 owners, 2 shares. */ __( '%1$d holders · %2$d shares in issue', 'fan-ownership' ), count( (array) $latest ), $total ) ) . '</strong></p>';
		$events = $wpdb->get_results( "SELECT recorded_at, user_id, event, shares, holding_after, source, consideration FROM {$wpdb->prefix}prx3_share_register ORDER BY id DESC LIMIT 50" );
		// phpcs:enable
		echo '<h2>' . esc_html__( 'Recent events (latest 50)', 'fan-ownership' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'When', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Owner', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Event', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Shares', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Holding after', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Source', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Consideration', 'fan-ownership' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $events as $row ) {
			$member = get_userdata( (int) $row->user_id );
			$name   = $member ? '<a href="' . esc_url( admin_url( 'admin.php?page=prx3-share-register&owner=' . (int) $row->user_id ) ) . '">' . esc_html( $member->display_name ) . '</a>' : esc_html( '#' . (int) $row->user_id );
			echo '<tr><td>' . esc_html( prx3_format_datetime( (string) $row->recorded_at ) ) . '</td><td>' . $name . '</td><td>' . esc_html( $row->event ) . '</td><td>' . (int) $row->shares . '</td><td>' . (int) $row->holding_after . '</td><td>' . esc_html( $row->source ) . '</td><td>' . esc_html( null === $row->consideration ? '—' : number_format_i18n( (float) $row->consideration, 2 ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $name is an escaped anchor or escaped text.
		}
		echo '</tbody></table></div>';
	}

	/**
	 * The three destinations for one owner: their WordPress account,
	 * their public web profile, and Board → Owner Management.
	 *
	 * @param int $user_id Owner user ID.
	 * @return string Escaped anchor HTML (space-separated).
	 */
	protected static function owner_links( $user_id ) {
		$links = array();
		$wp    = get_edit_user_link( $user_id );
		if ( $wp ) {
			$links[] = '<a href="' . esc_url( $wp ) . '">' . esc_html__( 'WP account', 'fan-ownership' ) . '</a>';
		}
		$web = class_exists( 'PRX3_Social' ) ? PRX3_Social::profile_url( $user_id ) : '';
		if ( $web ) {
			$links[] = '<a href="' . esc_url( $web ) . '">' . esc_html__( 'web profile', 'fan-ownership' ) . '</a>';
		}
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=prx3-board-owners&user=' . (int) $user_id ) ) . '">' . esc_html__( 'manage', 'fan-ownership' ) . '</a>';
		return implode( ' · ', $links );
	}

	/**
	 * One owner's full share record: current holding, owner number,
	 * total consideration, and every register movement — reached by
	 * clicking their name in the register.
	 *
	 * @param int $user_id Owner user ID.
	 */
	protected static function render_owner_detail( $user_id ) {
		global $wpdb;
		$member = get_userdata( $user_id );
		echo '<div class="wrap"><h1>' . esc_html( $member ? $member->display_name : '#' . $user_id ) . ' — ' . esc_html__( 'share record', 'fan-ownership' ) . '</h1>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=prx3-share-register' ) ) . '">&larr; ' . esc_html__( 'Back to the register', 'fan-ownership' ) . '</a></p>';
		echo '<p>' . self::owner_links( $user_id ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped anchors from owner_links().
		if ( ! $member || ! is_callable( array( $wpdb, 'get_results' ) ) ) {
			echo '</div>';
			return;
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- plugin-owned register table, read-only screen.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT recorded_at, event, shares, holding_after, source, consideration FROM {$wpdb->prefix}prx3_share_register WHERE user_id = %d ORDER BY id DESC", $user_id ) );
		// phpcs:enable
		$paid = 0.0;
		foreach ( (array) $rows as $row ) {
			$paid += (float) $row->consideration;
		}
		echo '<table class="widefat striped" style="max-width:640px;"><tbody>';
		echo '<tr><th style="text-align:left;width:220px;">' . esc_html__( 'Owner number', 'fan-ownership' ) . '</th><td>#' . (int) PRX3_Shares::owner_number( $user_id ) . '</td></tr>';
		echo '<tr><th style="text-align:left;">' . esc_html__( 'Current holding', 'fan-ownership' ) . '</th><td><strong>' . (int) prx3_shares( $user_id ) . '</strong> ' . esc_html__( 'shares', 'fan-ownership' ) . '</td></tr>';
		echo '<tr><th style="text-align:left;">' . esc_html__( 'Total consideration recorded', 'fan-ownership' ) . '</th><td>' . esc_html( prx3_money( $paid ) ) . '</td></tr>';
		echo '<tr><th style="text-align:left;">' . esc_html__( 'Movements', 'fan-ownership' ) . '</th><td>' . (int) count( (array) $rows ) . '</td></tr>';
		echo '</tbody></table>';
		echo '<h2>' . esc_html__( 'Every movement', 'fan-ownership' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'When', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Event', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Shares', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Holding after', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Source', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Consideration', 'fan-ownership' ) . '</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No register movements recorded for this owner.', 'fan-ownership' ) . '</td></tr>';
		}
		foreach ( (array) $rows as $row ) {
			echo '<tr><td>' . esc_html( prx3_format_datetime( (string) $row->recorded_at ) ) . '</td><td>' . esc_html( $row->event ) . '</td><td>' . (int) $row->shares . '</td><td>' . (int) $row->holding_after . '</td><td>' . esc_html( $row->source ) . '</td><td>' . esc_html( null === $row->consideration ? '—' : number_format_i18n( (float) $row->consideration, 2 ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
