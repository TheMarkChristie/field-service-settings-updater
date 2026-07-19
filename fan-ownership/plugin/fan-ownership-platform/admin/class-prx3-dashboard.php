<?php
/**
 * The club engagement dashboard: membership & revenue, ballot health,
 * content performance, community health. FO-120, FO-315, T28.
 *
 * Interactive tile layout: KPI stat tiles with hero numbers, a meter
 * against the owner target, a cumulative-shares sparkline, a holdings
 * distribution mini-chart, per-ballot quorum meters with countdowns,
 * and click-through operations tiles — refreshed live over admin-ajax.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * The staff/board club dashboard: interactive tiles over live club
 * numbers, with drill-through links into each working screen.
 */
class PRX3_Dashboard {

	/**
	 * Hook the menu, the data endpoint, and the dashboard assets.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'wp_ajax_prx3_dashboard_data', array( __CLASS__, 'ajax_data' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Admin page hook for the dashboard, set at registration.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Register the Owners top-level menu (content home; every
	 * member-facing content type attaches beneath it via show_in_menu)
	 * and the Club Dashboard as a Board submenu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'Owners', 'fan-ownership' ),
			__( 'Owners', 'fan-ownership' ),
			'edit_posts',
			'prx3-owners',
			array( __CLASS__, 'render_owners_home' ),
			'dashicons-groups',
			3
		);
		add_submenu_page(
			'prx3-owners',
			__( 'Owners', 'fan-ownership' ),
			__( 'Overview', 'fan-ownership' ),
			'edit_posts',
			'prx3-owners',
			array( __CLASS__, 'render_owners_home' )
		);
		self::$hook = (string) add_submenu_page(
			'prx3-board',
			__( 'Club Dashboard', 'fan-ownership' ),
			__( 'Dashboard', 'fan-ownership' ),
			'prx3_view_tally',
			'prx3-dashboard',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * The Owners landing: where each working area lives.
	 */
	public static function render_owners_home() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Owners', 'fan-ownership' ) . '</h1>';
		echo '<p>' . esc_html__( 'Everything the members see and do lives in this menu: ballots, ideas, questions, meetings, the video library, documents, behind-the-scenes posts, the decision register, chapters, the Match Centre, and the squad. Board-facing tools — the dashboard, decision register, commitments calendar, and owner signatures — are in the Board menu.', 'fan-ownership' ) . '</p>';
		if ( self::can_view() ) {
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=prx3-dashboard' ) ) . '">' . esc_html__( 'Open the Club Dashboard (Board menu)', 'fan-ownership' ) . '</a></p>';
		}
		echo '</div>';
	}

	/**
	 * Enqueue the tile styles and refresh script on the dashboard only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public static function assets( $hook ) {
		if ( ! self::$hook || $hook !== self::$hook ) {
			return;
		}
		wp_enqueue_style( 'prx3-dashboard', PRX3_URL . 'assets/css/prx3-dashboard.css', array(), PRX3_VERSION );
		wp_enqueue_script( 'prx3-dashboard', PRX3_URL . 'assets/js/prx3-dashboard.js', array(), PRX3_VERSION, true );
		wp_localize_script(
			'prx3-dashboard',
			'prx3Dash',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'prx3_dashboard' ),
				'data'    => self::can_view() ? self::data() : array(),
				'i18n'    => array(
					'updated'  => __( 'Updated', 'fan-ownership' ),
					'closesIn' => __( 'closes in', 'fan-ownership' ),
					'closed'   => __( 'closing now', 'fan-ownership' ),
					'members'  => __( 'members', 'fan-ownership' ),
					'shares'   => __( 'shares', 'fan-ownership' ),
					'holding'  => __( 'holding', 'fan-ownership' ),
				),
			)
		);
	}

	/**
	 * Can the current user see club figures?
	 *
	 * @return bool True for staff with tally view or board members.
	 */
	private static function can_view() {
		return current_user_can( 'prx3_view_tally' ) || current_user_can( 'prx3_board' );
	}

	/**
	 * Assemble every dashboard metric in one pass.
	 *
	 * @return array Metrics, series, and drill-through links.
	 */
	public static function data() {
		global $wpdb;

		$owners  = count(
			get_users(
				array(
					'role'   => 'fan_owner',
					'fields' => 'ID',
				)
			)
		);
		$shares  = (int) $wpdb->get_var( "SELECT SUM(meta_value+0) FROM {$wpdb->usermeta} WHERE meta_key = 'prx3_shares'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live share total; caching deliberately avoided on money/vote reads.
		$revenue = (float) $wpdb->get_var( "SELECT SUM(consideration) FROM {$wpdb->prefix}prx3_share_register WHERE event = 'acquisition'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the custom register table is its own record; live money read.

		// Cumulative shares by month for the sparkline.
		$monthly = $wpdb->get_results( "SELECT DATE_FORMAT(recorded_at, '%Y-%m') AS m, SUM(CASE WHEN event = 'acquisition' THEN shares ELSE -shares END) AS s FROM {$wpdb->prefix}prx3_share_register GROUP BY m ORDER BY m ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- append-only register aggregation for the dashboard trend.
		$series  = array();
		$running = 0;
		foreach ( (array) $monthly as $row ) {
			$running += (int) $row['s'];
			$series[] = array( $row['m'], $running );
		}

		// Holdings distribution 1..cap.
		$dist = array_fill( 1, prx3_max_shares(), 0 );
		$rows = $wpdb->get_results( "SELECT meta_value+0 AS held, COUNT(*) AS n FROM {$wpdb->usermeta} WHERE meta_key = 'prx3_shares' AND meta_value+0 > 0 GROUP BY held", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live holdings histogram for the dashboard.
		foreach ( (array) $rows as $row ) {
			$held = (int) $row['held'];
			if ( isset( $dist[ $held ] ) ) {
				$dist[ $held ] = (int) $row['n'];
			}
		}

		$ballots = array();
		foreach ( PRX3_Ballots::open_ballots() as $ballot ) {
			$tallies     = PRX3_Ballots::tallies( $ballot->ID, true );
			$denominator = (int) get_post_meta( $ballot->ID, '_prx3_quorum_denominator', true );
			$needed      = max( 1, (int) ceil( $denominator * (int) prx3_setting( 'quorum_percent', 25 ) / 100 ) );
			$ballots[]   = array(
				'title'  => $ballot->post_title,
				'voted'  => (int) $tallies['members'],
				'needed' => $needed,
				'closes' => (string) get_post_meta( $ballot->ID, '_prx3_closes', true ),
				'link'   => get_edit_post_link( $ballot->ID, 'raw' ),
			);
		}

		$ideas     = wp_count_posts( 'prx3_idea' );
		$questions = wp_count_posts( 'prx3_question' );
		$overdue   = 0;
		foreach ( (array) get_option( 'prx3_commitments', array() ) as $commitment ) {
			if ( empty( $commitment['done'] ) && ! empty( $commitment['due'] ) && strtotime( $commitment['due'] ) < time() ) {
				++$overdue;
			}
		}

		return array(
			'owners'               => $owners,
			'target'               => max( 1, (int) prx3_setting( 'target_owners', 1000 ) ),
			'active'               => prx3_active_owner_count(),
			'shares'               => $shares,
			'revenue'              => prx3_money( $revenue ),
			'revenue_raw'          => $revenue,
			'target_revenue'       => (float) prx3_setting( 'target_revenue', 0 ),
			'target_revenue_label' => (float) prx3_setting( 'target_revenue', 0 ) > 0 ? prx3_money( (float) prx3_setting( 'target_revenue', 0 ) ) : '',
			'gifts'                => count( array_filter( get_option( 'prx3_gift_codes', array() ), fn( $g ) => empty( $g['redeemed'] ) && empty( $g['voided'] ) ) ),
			'surrenders'           => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}prx3_share_register WHERE event = 'surrender'" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the custom register table is its own record; live count.
			'series'               => $series,
			'dist'                 => array_values( $dist ),
			'ballots'              => $ballots,
			'videos'               => (int) ( wp_count_posts( 'prx3_video' )->publish ?? 0 ),
			'ideas'                => array( (int) $ideas->publish, (int) $ideas->pending ),
			'questions'            => array( (int) $questions->publish, (int) $questions->pending ),
			'queue'                => count( array_filter( get_option( 'prx3_mod_queue', array() ), fn( $q ) => empty( $q['resolved'] ) ) ),
			'stalled'              => count(
				get_posts(
					array(
						'post_type'      => 'prx3_decision',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
						'meta_key'       => '_prx3_stalled', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded lookup on an admin-only dashboard.
						'meta_value'     => 1, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					)
				)
			),
			'sync'                 => array(
				'outbox' => count( (array) get_option( 'prx3_sync_outbox', array() ) ),
				'review' => count( (array) get_option( 'prx3_sync_review', array() ) ),
			),
			'overdue'              => $overdue,
			'commerce'             => class_exists( 'PRX3_Shopify' ) ? PRX3_Shopify::counts() : array(
		'held'        => 0,
		'unclaimed'   => 0,
		'oldest_days' => 0,
		),
			'api_age'              => (int) prx3_setting( 'data_api_enabled', 0 ) && get_option( 'prx3_data_api_provisioned' ) ? (int) floor( ( time() - (int) get_option( 'prx3_data_api_provisioned' ) ) / DAY_IN_SECONDS ) : -1,
			'links'                => array(
				'owners'      => admin_url( 'users.php?role=fan_owner' ),
				'ballots'     => admin_url( 'edit.php?post_type=prx3_ballot' ),
				'ideas'       => admin_url( 'edit.php?post_status=pending&post_type=prx3_idea' ),
				'questions'   => admin_url( 'edit.php?post_status=pending&post_type=prx3_question' ),
				'videos'      => admin_url( 'edit.php?post_type=prx3_video' ),
				'decisions'   => admin_url( 'edit.php?post_type=prx3_decision' ),
				'sync'        => admin_url( 'admin.php?page=prx3-sync' ),
				'commitments' => admin_url( 'admin.php?page=prx3-commitments' ),
				'settings'    => admin_url( 'admin.php?page=prx3-settings' ),
			),
			'stamp'                => prx3_format_datetime( prx3_now() ),
		);
	}

	/**
	 * Live-refresh endpoint for the tiles.
	 */
	public static function ajax_data() {
		check_ajax_referer( 'prx3_dashboard' );
		if ( ! self::can_view() ) {
			wp_send_json_error( array( 'message' => __( 'Staff and board only.', 'fan-ownership' ) ), 403 );
		}
		wp_send_json_success( self::data() );
	}

	/**
	 * One stat tile.
	 *
	 * @param string $key   Data key the refresh script binds to.
	 * @param string $label Tile label.
	 * @param string $value Formatted value.
	 * @param string $link  Drill-through URL ('' for none).
	 * @param string $note  Secondary line ('' for none).
	 * @param string $state ''|'good'|'warning'|'serious' status accent.
	 */
	private static function tile( $key, $label, $value, $link = '', $note = '', $state = '' ) {
		$tag  = $link ? 'a' : 'div';
		$attr = $link ? ' href="' . esc_url( $link ) . '"' : '';
		echo '<' . esc_attr( $tag ) . $attr . ' class="prx3-tile' . ( $state ? ' is-' . esc_attr( $state ) : '' ) . '" data-tile="' . esc_attr( $key ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attr escaped above.
		echo '<span class="prx3-tile-label">' . esc_html( $label ) . '</span>';
		echo '<strong class="prx3-tile-value" data-bind="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</strong>';
		if ( $note ) {
			echo '<span class="prx3-tile-note" data-note="' . esc_attr( $key ) . '">' . esc_html( $note ) . '</span>';
		}
		if ( $state && 'good' !== $state ) {
			echo '<span class="prx3-tile-flag" role="img" aria-label="' . esc_attr__( 'Needs attention', 'fan-ownership' ) . '">&#9888;</span>';
		}
		echo '</' . esc_attr( $tag ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tag name from fixed set.
	}

	/**
	 * Render the tile dashboard (FO-120, FO-315, T28).
	 */
	public static function render() {
		if ( ! self::can_view() ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Club Dashboard', 'fan-ownership' ) . '</h1><p>' . esc_html__( 'Club figures are visible to staff and board roles.', 'fan-ownership' ) . '</p></div>';
			return;
		}
		$d = self::data();

		echo '<div class="wrap prx3-dash" data-prx3-dashboard>';
		echo '<h1>' . esc_html( sprintf( /* translators: %s club. */ __( '%s — Club Dashboard', 'fan-ownership' ), prx3_club_name() ) ) . '</h1>';
		echo '<p class="prx3-dash-bar"><button type="button" class="button" data-refresh>' . esc_html__( 'Refresh now', 'fan-ownership' ) . '</button> <span class="prx3-dash-stamp" data-stamp aria-live="polite">' . esc_html( sprintf( /* translators: %s time. */ __( 'Updated %s', 'fan-ownership' ), $d['stamp'] ) ) . '</span></p>';

		// Hero tile: owners against the target, with a meter.
		echo '<section class="prx3-tiles" aria-label="' . esc_attr__( 'Membership and revenue', 'fan-ownership' ) . '">';
		echo '<a class="prx3-tile prx3-tile--hero" href="' . esc_url( $d['links']['owners'] ) . '" data-tile="owners">';
		echo '<span class="prx3-tile-label">' . esc_html__( 'Owners', 'fan-ownership' ) . '</span>';
		echo '<strong class="prx3-hero" data-bind="owners">' . esc_html( (string) $d['owners'] ) . '</strong>';
		echo '<span class="prx3-tile-note">' . esc_html( sprintf( /* translators: %d target. */ __( 'of the %d season-one target', 'fan-ownership' ), $d['target'] ) ) . '</span>';
		echo '<span class="prx3-meter" role="meter" aria-label="' . esc_attr__( 'Progress to owner target', 'fan-ownership' ) . '" aria-valuemin="0" aria-valuemax="' . esc_attr( (string) $d['target'] ) . '" aria-valuenow="' . esc_attr( (string) $d['owners'] ) . '" data-meter="owners"><span class="prx3-meter-fill" style="width:' . esc_attr( (string) min( 100, round( $d['owners'] / max( 1, $d['target'] ) * 100 ) ) ) . '%"></span></span>';
		echo '</a>';

		// Shares tile with the cumulative sparkline (drawn by JS).
		echo '<div class="prx3-tile prx3-tile--spark" data-tile="shares">';
		echo '<span class="prx3-tile-label">' . esc_html__( 'Shares issued', 'fan-ownership' ) . '</span>';
		echo '<strong class="prx3-tile-value" data-bind="shares">' . esc_html( (string) $d['shares'] ) . '</strong>';
		echo '<span class="prx3-tile-note">' . esc_html__( 'cumulative by month', 'fan-ownership' ) . '</span>';
		echo '<span class="prx3-spark" data-spark aria-hidden="true"></span>';
		echo '</div>';

		// Revenue tile: meter against the financial target when one is set.
		echo '<a class="prx3-tile" href="' . esc_url( $d['links']['settings'] ) . '" data-tile="revenue">';
		echo '<span class="prx3-tile-label">' . esc_html__( 'Share revenue', 'fan-ownership' ) . '</span>';
		echo '<strong class="prx3-tile-value" data-bind="revenue">' . esc_html( $d['revenue'] ) . '</strong>';
		if ( $d['target_revenue'] > 0 ) {
			echo '<span class="prx3-tile-note" data-note="revenue">' . esc_html( sprintf( /* translators: %s target. */ __( 'of the %s target', 'fan-ownership' ), $d['target_revenue_label'] ) ) . '</span>';
			echo '<span class="prx3-meter" role="meter" aria-label="' . esc_attr__( 'Progress to financial target', 'fan-ownership' ) . '" aria-valuemin="0" aria-valuemax="' . esc_attr( (string) $d['target_revenue'] ) . '" aria-valuenow="' . esc_attr( (string) $d['revenue_raw'] ) . '" data-meter="revenue"><span class="prx3-meter-fill" style="width:' . esc_attr( (string) min( 100, round( $d['revenue_raw'] / $d['target_revenue'] * 100 ) ) ) . '%"></span></span>';
		} else {
			echo '<span class="prx3-tile-note">' . esc_html__( 'set a financial target in Settings → Targets to track progress', 'fan-ownership' ) . '</span>';
		}
		echo '</a>';
		self::tile( 'active', __( 'Active owners', 'fan-ownership' ), (string) $d['active'], $d['links']['owners'], __( 'quorum denominator (12 months)', 'fan-ownership' ) );
		self::tile( 'gifts', __( 'Gifts unredeemed', 'fan-ownership' ), (string) $d['gifts'], admin_url( 'admin.php?page=prx3-settings-shares' ), __( 'codes never expire', 'fan-ownership' ) );
		self::tile( 'surrenders', __( 'Surrender events', 'fan-ownership' ), (string) $d['surrenders'], $d['links']['owners'] );

		// Holdings distribution mini-chart (drawn by JS, table fallback below).
		echo '<div class="prx3-tile prx3-tile--chart" data-tile="dist">';
		echo '<span class="prx3-tile-label">' . esc_html__( 'Members by holding', 'fan-ownership' ) . '</span>';
		echo '<span class="prx3-dist" data-dist role="img" aria-label="' . esc_attr__( 'Distribution of members by shares held; the table view lists the numbers.', 'fan-ownership' ) . '"></span>';
		echo '</div>';
		echo '</section>';

		// Ballot health tiles.
		echo '<h2>' . esc_html__( 'Ballot health', 'fan-ownership' ) . '</h2>';
		echo '<section class="prx3-tiles" aria-label="' . esc_attr__( 'Open ballots', 'fan-ownership' ) . '" data-ballots>';
		if ( ! $d['ballots'] ) {
			echo '<div class="prx3-tile"><span class="prx3-tile-label">' . esc_html__( 'No ballots open', 'fan-ownership' ) . '</span><span class="prx3-tile-note"><a href="' . esc_url( $d['links']['ballots'] ) . '">' . esc_html__( 'Schedule the next one', 'fan-ownership' ) . '</a></span></div>';
		}
		echo '</section>';

		// Operations tiles: anything non-zero needs eyes.
		echo '<h2>' . esc_html__( 'Operations', 'fan-ownership' ) . '</h2>';
		echo '<section class="prx3-tiles" aria-label="' . esc_attr__( 'Operations queues', 'fan-ownership' ) . '">';
		self::tile( 'queue', __( 'Moderation queue', 'fan-ownership' ), (string) $d['queue'], '', '', $d['queue'] ? 'serious' : 'good' );
		self::tile( 'ideasPending', __( 'Ideas awaiting review', 'fan-ownership' ), (string) $d['ideas'][1], $d['links']['ideas'], sprintf( /* translators: %d published. */ __( '%d published', 'fan-ownership' ), $d['ideas'][0] ), $d['ideas'][1] ? 'warning' : 'good' );
		self::tile( 'questionsPending', __( 'Questions awaiting answer', 'fan-ownership' ), (string) $d['questions'][1], $d['links']['questions'], sprintf( /* translators: %d published. */ __( '%d answered', 'fan-ownership' ), $d['questions'][0] ), $d['questions'][1] ? 'warning' : 'good' );
		self::tile( 'stalled', __( 'Stalled decisions', 'fan-ownership' ), (string) $d['stalled'], $d['links']['decisions'], '', $d['stalled'] ? 'serious' : 'good' );
		self::tile( 'overdue', __( 'Commitments overdue', 'fan-ownership' ), (string) $d['overdue'], $d['links']['commitments'], '', $d['overdue'] ? 'serious' : 'good' );
		self::tile( 'syncReview', __( 'CRM sync review', 'fan-ownership' ), (string) $d['sync']['review'], $d['links']['sync'], sprintf( /* translators: %d outbox. */ __( '%d awaiting delivery', 'fan-ownership' ), $d['sync']['outbox'] ), $d['sync']['review'] ? 'warning' : 'good' );
		self::tile( 'videos', __( 'Videos in the library', 'fan-ownership' ), (string) $d['videos'], $d['links']['videos'] );
		$commerce_open = $d['commerce']['held'] + $d['commerce']['unclaimed'];
		self::tile( 'commerce', __( 'Commerce: held + unclaimed', 'fan-ownership' ), $d['commerce']['held'] . ' + ' . $d['commerce']['unclaimed'], admin_url( 'admin.php?page=prx3-commerce' ), $d['commerce']['oldest_days'] >= 30 ? __( 'refund review due', 'fan-ownership' ) : __( 'orders awaiting review or signature', 'fan-ownership' ), $commerce_open ? ( $d['commerce']['held'] || $d['commerce']['oldest_days'] >= 30 ? 'serious' : 'warning' ) : 'good' );
		if ( $d['api_age'] >= 0 ) {
			self::tile( 'apiAge', __( 'Data API connection', 'fan-ownership' ), sprintf( /* translators: %d days. */ __( 'live %d day(s)', 'fan-ownership' ), $d['api_age'] ), admin_url( 'admin.php?page=prx3-settings-api' ), __( 'revoke when the job is done', 'fan-ownership' ), $d['api_age'] > 30 ? 'warning' : '' );
		}
		echo '</section>';

		// Accessible table view of everything on the tiles.
		echo '<details class="prx3-dash-table"><summary>' . esc_html__( 'View the numbers as a table', 'fan-ownership' ) . '</summary>';
		echo '<table class="widefat striped" data-table><caption class="screen-reader-text">' . esc_html__( 'Dashboard metrics', 'fan-ownership' ) . '</caption><tbody></tbody></table></details>';

		echo '<div class="prx3-viz-tip" data-tip role="status" hidden></div>';
		echo '</div>';
	}
}
