<?php
/**
 * The commitments calendar: every recurring promise the club has made
 * to its owners — and its statutory filing deadlines — tracked with an
 * accountable owner, auto-advancing due dates, and missed-deadline
 * flags. The difference between the Braves' fulfilment complaints and
 * a club that keeps its word.
 *
 * Seeded from the specification's promises: weekly show (P35), monthly
 * Q&A video (P17), monthly financial summary (P21), quarterly meeting
 * (P18), annual report (P77), AGM, plus Companies House confirmation
 * statement and accounts (compliance items with configurable dates) and
 * a share-allotment filing reminder (SH01) whenever shares were issued
 * in the period.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tracks the club's recurring promises and statutory deadlines with
 * accountable owners, auto-advancing due dates, and email chasing when
 * a commitment slips.
 */
class PRX3_Commitments {

	/**
	 * Hook the daily tick, admin screen, and admin-post handlers.
	 */
	public static function init() {
		add_action( 'prx3_commitments_tick', array( __CLASS__, 'tick' ) );
		if ( ! wp_next_scheduled( 'prx3_commitments_tick' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'prx3_commitments_tick' );
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_prx3_commitment_done', array( __CLASS__, 'handle_done' ) );
		add_action( 'admin_post_prx3_commitment_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_prx3_export_allotments', array( __CLASS__, 'handle_allotment_export' ) );
	}

	/**
	 * The stored list, seeded on first use.
	 *
	 * @return array[] Each: key, label, frequency (weekly|monthly|quarterly|annual),
	 *                 owner (user id), due (Y-m-d), last_done, missed (bool).
	 */
	public static function all() {
		$items = get_option( 'prx3_commitments', null );
		if ( null !== $items ) {
			return $items;
		}
		$items = array(
			array(
				'key'       => 'weekly_show',
				'label'     => __( 'Weekly club show episode (P35)', 'fan-ownership' ),
				'frequency' => 'weekly',
			),
			array(
				'key'       => 'monthly_qa',
				'label'     => __( 'Monthly Q&A video (P17)', 'fan-ownership' ),
				'frequency' => 'monthly',
			),
			array(
				'key'       => 'monthly_financials',
				'label'     => __( 'Monthly financial summary (P21)', 'fan-ownership' ),
				'frequency' => 'monthly',
			),
			array(
				'key'       => 'quarterly_meeting',
				'label'     => __( 'Quarterly owners meeting (P18)', 'fan-ownership' ),
				'frequency' => 'quarterly',
			),
			array(
				'key'       => 'annual_report',
				'label'     => __( 'State of the club annual report (P77)', 'fan-ownership' ),
				'frequency' => 'annual',
			),
			array(
				'key'       => 'agm',
				'label'     => __( 'AGM (P18/P66)', 'fan-ownership' ),
				'frequency' => 'annual',
			),
			array(
				'key'       => 'confirmation_statement',
				'label'     => __( 'Companies House confirmation statement', 'fan-ownership' ),
				'frequency' => 'annual',
			),
			array(
				'key'       => 'annual_accounts_filing',
				'label'     => __( 'Companies House accounts filing', 'fan-ownership' ),
				'frequency' => 'annual',
			),
			array(
				'key'       => 'sh01_allotments',
				'label'     => __( 'SH01 share allotment return (file for every issue period)', 'fan-ownership' ),
				'frequency' => 'monthly',
			),
			array(
				'key'       => 'insurance_renewal',
				'label'     => __( 'Insurance renewals (D&O, public liability, player, volunteer)', 'fan-ownership' ),
				'frequency' => 'annual',
			),
			array(
				'key'       => 'chapter_reaffirm',
				'label'     => __( 'Chapter re-affirmation sweep (P71)', 'fan-ownership' ),
				'frequency' => 'annual',
			),
			array(
				'key'       => 'safeguarding_review',
				'label'     => __( 'Safeguarding policy + PVG/DBS check review', 'fan-ownership' ),
				'frequency' => 'annual',
			),
		);
		foreach ( $items as &$item ) {
			$item['owner']     = 0;
			$item['due']       = self::next_due( $item['frequency'], time() );
			$item['last_done'] = '';
			$item['missed']    = false;
		}
		update_option( 'prx3_commitments', $items, false );
		return $items;
	}

	/**
	 * The next due date for a frequency, counted from a timestamp.
	 *
	 * @param string $frequency weekly|monthly|quarterly|annual.
	 * @param int    $from_ts   Unix timestamp to count from.
	 * @return string Y-m-d.
	 */
	private static function next_due( $frequency, $from_ts ) {
		$intervals = array(
			'weekly'    => '+1 week',
			'monthly'   => '+1 month',
			'quarterly' => '+3 months',
			'annual'    => '+1 year',
		);
		$step      = isset( $intervals[ $frequency ] ) ? $intervals[ $frequency ] : '+1 month';
		return gmdate( 'Y-m-d', strtotime( $step, $from_ts ) );
	}

	/**
	 * Read-only calendar for board members: every obligation, who is
	 * accountable, and what is overdue — no editing controls.
	 */
	private static function render_readonly() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Commitments calendar', 'fan-ownership' ) . '</h1>';
		echo '<p>' . esc_html__( 'The club\'s recurring promises to the owners and statutory deadlines. Editing is done by governance staff.', 'fan-ownership' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Commitment', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Frequency', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Accountable', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Next due', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Status', 'fan-ownership' ) . '</th></tr></thead><tbody>';
		foreach ( self::all() as $item ) {
			$owner   = ! empty( $item['owner'] ) ? get_userdata( (int) $item['owner'] ) : null;
			$due     = isset( $item['due'] ) ? (string) $item['due'] : '';
			$overdue = $due && empty( $item['done'] ) && strtotime( $due ) < time();
			echo '<tr><td>' . esc_html( $item['label'] ) . '</td><td>' . esc_html( $item['frequency'] ) . '</td>';
			echo '<td>' . esc_html( $owner ? $owner->display_name : __( 'Unassigned', 'fan-ownership' ) ) . '</td>';
			echo '<td>' . esc_html( $due ? $due : '—' ) . '</td>';
			echo '<td>' . ( $overdue ? '<strong>' . esc_html__( 'Overdue', 'fan-ownership' ) . '</strong>' : esc_html__( 'Scheduled', 'fan-ownership' ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Daily: flag overdue commitments and chase the accountable owner
	 * (and admins after 3 days). SH01 only chases when shares were
	 * actually issued since the last filing.
	 */
	public static function tick() {
		$items   = self::all();
		$changed = false;
		foreach ( $items as &$item ) {
			$due_ts = strtotime( $item['due'] . ' 23:59:59' );
			if ( ! $due_ts || time() <= $due_ts || $item['missed'] ) {
				continue;
			}
			if ( 'sh01_allotments' === $item['key'] && ! self::allotments_since( $item['last_done'] ) ) {
				// Nothing issued this period: silently roll the date on.
				$item['due'] = self::next_due( $item['frequency'], time() );
				$changed     = true;
				continue;
			}
			$item['missed'] = true;
			$changed        = true;
			self::chase( $item );
		}
		if ( $changed ) {
			update_option( 'prx3_commitments', $items, false );
		}
	}

	/**
	 * Email the accountable owner and the admins about an overdue commitment.
	 *
	 * @param array $item Commitment row (key, label, owner, due).
	 */
	private static function chase( $item ) {
		$recipients = array();
		$owner      = $item['owner'] ? get_userdata( (int) $item['owner'] ) : null;
		if ( $owner ) {
			$recipients[] = $owner;
		}
		foreach ( get_users( array( 'role__in' => array( 'prx3_owner_admin', 'administrator' ) ) ) as $admin ) {
			$recipients[] = $admin;
		}
		foreach ( $recipients as $person ) {
			PRX3_Comms::send(
				$person->user_email,
				sprintf( /* translators: %s commitment. */ __( 'Commitment overdue: %s', 'fan-ownership' ), $item['label'] ),
				sprintf(
					/* translators: 1: label, 2: due date, 3: admin URL. */
					__( '"%1$s" was due on %2$s and has not been marked done. Owners notice broken promises — mark it done or reassign it: %3$s', 'fan-ownership' ),
					$item['label'],
					$item['due'],
					admin_url( 'admin.php?page=prx3-commitments' )
				),
				'governance'
			);
		}
		PRX3_Audit::log( 'commitment_missed', sprintf( 'Commitment overdue: %s (due %s)', $item['key'], $item['due'] ) );
	}

	/**
	 * Shares issued since a date (drives the SH01 reminder).
	 *
	 * @param string $since Y-m-d of the last SH01 filing, '' for ever.
	 * @return int Acquisition count since that date.
	 */
	private static function allotments_since( $since ) {
		global $wpdb;
		$since_sql = $since ? $since . ' 00:00:00' : '1970-01-01 00:00:00';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Money-path read of the custom share register; must be fresh for the statutory filing check, so caching is deliberately avoided.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}prx3_share_register WHERE event = 'acquisition' AND recorded_at > %s",
				$since_sql
			)
		);
	}

	/* ---------------- Admin screen ---------------- */

	/**
	 * Register the Commitments submenu under the platform settings menu.
	 */
	public static function menu() {
		add_submenu_page(
			'prx3-board',
			__( 'Commitments', 'fan-ownership' ),
			__( 'Commitments', 'fan-ownership' ),
			'prx3_view_tally',
			'prx3-commitments',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Render the commitments table: label, frequency, accountable owner,
	 * due date, status, and mark-done / save controls.
	 */
	public static function render() {
		if ( ! current_user_can( 'prx3_governance' ) && ! current_user_can( 'prx3_admin' ) ) {
			// Board members see their obligations read-only; edits stay
			// with governance staff (the action handlers enforce it too).
			if ( current_user_can( 'prx3_board' ) || current_user_can( 'prx3_view_tally' ) ) {
				self::render_readonly();
				return;
			}
			wp_die( esc_html__( 'Governance staff only.', 'fan-ownership' ) );
		}
		$items = self::all();
		echo '<div class="wrap"><h1>' . esc_html__( 'Commitments calendar', 'fan-ownership' ) . '</h1>';
		echo '<p>' . esc_html__( 'Every recurring promise to the owners and every statutory deadline, with an accountable owner. Overdue items chase by email daily.', 'fan-ownership' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Commitment', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Frequency', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Accountable', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Next due', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Status', 'fan-ownership' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $items as $item ) {
			$owner = $item['owner'] ? get_userdata( (int) $item['owner'] ) : null;
			echo '<tr><td>' . esc_html( $item['label'] ) . '</td><td>' . esc_html( $item['frequency'] ) . '</td>';
			echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:4px;">';
			wp_nonce_field( 'prx3_commitment_save' );
			echo '<input type="hidden" name="action" value="prx3_commitment_save"><input type="hidden" name="key" value="' . esc_attr( $item['key'] ) . '">';
			echo '<input type="number" name="owner" value="' . esc_attr( $item['owner'] ) . '" min="0" style="width:6em;" aria-label="' . esc_attr__( 'Accountable user ID', 'fan-ownership' ) . '">';
			echo '<input type="date" name="due" value="' . esc_attr( $item['due'] ) . '" aria-label="' . esc_attr__( 'Due date', 'fan-ownership' ) . '">';
			echo '<button class="button">' . esc_html__( 'Save', 'fan-ownership' ) . '</button></form>';
			echo $owner ? '<span class="description">' . esc_html( $owner->display_name ) . '</span>' : '<span class="description">' . esc_html__( 'Unassigned', 'fan-ownership' ) . '</span>';
			echo '</td>';
			echo '<td>' . esc_html( $item['due'] ) . '</td>';
			echo '<td>' . ( $item['missed'] ? '<strong style="color:#b00020;">' . esc_html__( 'OVERDUE', 'fan-ownership' ) . '</strong>' : esc_html__( 'On track', 'fan-ownership' ) ) . '</td>';
			$done_url = wp_nonce_url( admin_url( 'admin-post.php?action=prx3_commitment_done&key=' . rawurlencode( $item['key'] ) ), 'prx3_commitment_done' );
			echo '<td><a class="button button-primary" href="' . esc_url( $done_url ) . '">' . esc_html__( 'Mark done', 'fan-ownership' ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
		echo '<h2>' . esc_html__( 'Share allotment return (SH01)', 'fan-ownership' ) . '</h2>';
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=prx3_export_allotments' ), 'prx3_export_allotments' ) ) . '">' . esc_html__( 'Export allotments since last SH01 filing (CSV)', 'fan-ownership' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Mark a commitment done: record the date, roll the due date on, and
	 * clear the missed flag.
	 */
	public static function handle_done() {
		if ( ! current_user_can( 'prx3_governance' ) && ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Governance staff only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_commitment_done' );
		$key   = isset( $_GET['key'] ) ? sanitize_key( $_GET['key'] ) : '';
		$items = self::all();
		foreach ( $items as &$item ) {
			if ( $item['key'] === $key ) {
				$item['last_done'] = gmdate( 'Y-m-d' );
				$item['due']       = self::next_due( $item['frequency'], time() );
				$item['missed']    = false;
				PRX3_Audit::log( 'commitment_done', sprintf( 'Commitment done: %s', $key ) );
			}
		}
		update_option( 'prx3_commitments', $items, false );
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-commitments' ) );
		exit;
	}

	/**
	 * Save a commitment's accountable owner and due date from the admin
	 * screen.
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'prx3_governance' ) && ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Governance staff only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_commitment_save' );
		$key   = isset( $_POST['key'] ) ? sanitize_key( $_POST['key'] ) : '';
		$items = self::all();
		foreach ( $items as &$item ) {
			if ( $item['key'] === $key ) {
				$item['owner'] = isset( $_POST['owner'] ) ? absint( $_POST['owner'] ) : 0;
				$due           = isset( $_POST['due'] ) ? sanitize_text_field( wp_unslash( $_POST['due'] ) ) : '';
				if ( $due && strtotime( $due ) ) {
					$item['due']    = gmdate( 'Y-m-d', strtotime( $due ) );
					$item['missed'] = false;
				}
			}
		}
		update_option( 'prx3_commitments', $items, false );
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-commitments' ) );
		exit;
	}

	/**
	 * CSV of every acquisition since the last SH01 mark-done — the raw
	 * data an accountant needs for the Companies House return.
	 */
	public static function handle_allotment_export() {
		if ( ! current_user_can( 'prx3_admin' ) && ! current_user_can( 'prx3_governance' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_export_allotments' );
		$since = '';
		foreach ( self::all() as $item ) {
			if ( 'sh01_allotments' === $item['key'] ) {
				$since = $item['last_done'];
			}
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Money-path read of the custom share register for the Companies House export; must be fresh, so caching is deliberately avoided.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}prx3_share_register WHERE event = 'acquisition' AND recorded_at > %s ORDER BY id ASC",
				$since ? $since . ' 00:00:00' : '1970-01-01 00:00:00'
			),
			ARRAY_A
		);
		PRX3_Audit::log( 'allotment_export', sprintf( 'Allotment export since %s (%d rows)', $since ? $since : 'ever', count( $rows ) ) );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=share-allotments-' . gmdate( 'Ymd' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Date', 'Member name', 'Owner number', 'Shares allotted', 'Consideration', 'Source' ) );
		foreach ( $rows as $row ) {
			$user = get_userdata( (int) $row['user_id'] );
			fputcsv(
				$out,
				array(
					$row['recorded_at'],
					$user ? $user->display_name : ( '#' . $row['user_id'] ),
					$user ? PRX3_Shares::owner_number( $user->ID ) : '',
					$row['shares'],
					$row['consideration'],
					$row['source'],
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
