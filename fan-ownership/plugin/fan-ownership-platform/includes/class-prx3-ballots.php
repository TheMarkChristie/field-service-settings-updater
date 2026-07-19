<?php
/**
 * The ballot engine: authoring rules, eligibility snapshots, weighted
 * casting with revision, secrecy, and results.
 *
 * FO-201 (authoring, max 2 live), FO-202 (cast + change until close),
 * FO-203 (secret until closed), FO-204 (snapshot at open), FO-207
 * (constitutional 75%), FO-209 (permanent record).
 *
 * Ballot meta:
 *  _prx3_options (array), _prx3_type ('standard'|'constitutional'),
 *  _prx3_opens / _prx3_closes (datetimes), _prx3_state
 *  ('draft'|'scheduled'|'open'|'closed'|'rerun'|'unresolved'|'published'),
 *  _prx3_electorate (user_id => weight, snapshot at open),
 *  _prx3_quorum_denominator (int), _prx3_rerun_of (ballot id),
 *  _prx3_board_recommendation (text), _prx3_result (array at close).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * The ballot engine: authoring, weighted casting with revision, secrecy
 * while open, and permanent results against a snapshotted electorate.
 */
class PRX3_Ballots {

	/**
	 * Hook the ballot authoring meta box and its save handler.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_prx3_ballot', array( __CLASS__, 'save_meta' ), 10, 2 );
		// Ballot URLs carry the sequential ballot number, never the title.
		add_action( 'save_post_prx3_ballot', array( __CLASS__, 'assign_number' ), 5, 2 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_number_existing' ) );
	}

	/* ---------------- Sequential ballot numbers ---------------- */

	/**
	 * A ballot's sequential number (Ballot #N), assigned on first save.
	 *
	 * @param int $ballot_id Ballot.
	 * @return int The number, 0 if not yet assigned.
	 */
	public static function number( $ballot_id ) {
		return (int) get_post_meta( $ballot_id, '_prx3_ballot_no', true );
	}

	/**
	 * Assign the next sequential number and make it the URL slug, so a
	 * ballot's address never leaks its title (/owners/ballot/17/).
	 * WordPress keeps an old-slug redirect if the title slug existed.
	 *
	 * @param int          $post_id Ballot ID.
	 * @param WP_Post|null $post    Ballot post.
	 */
	public static function assign_number( $post_id, $post = null ) {
		if ( function_exists( 'wp_is_post_revision' ) && ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) ) {
			return;
		}
		$number = self::number( $post_id );
		if ( ! $number ) {
			$number = (int) get_option( 'prx3_ballot_seq', 0 ) + 1;
			update_option( 'prx3_ballot_seq', $number, false );
			update_post_meta( $post_id, '_prx3_ballot_no', $number );
		}
		$slug    = (string) $number;
		$current = $post && isset( $post->post_name ) ? (string) $post->post_name : null;
		if ( $slug !== $current ) {
			remove_action( 'save_post_prx3_ballot', array( __CLASS__, 'assign_number' ), 5 );
			wp_update_post(
				array(
					'ID'        => $post_id,
					'post_name' => $slug,
				)
			);
			add_action( 'save_post_prx3_ballot', array( __CLASS__, 'assign_number' ), 5, 2 );
		}
	}

	/**
	 * One-off upgrade: number every existing ballot (oldest first) and
	 * move its slug to the number, re-run safe via a version stamp.
	 */
	public static function maybe_number_existing() {
		if ( 'v1' === get_option( 'prx3_ballot_slugs' ) ) {
			return;
		}
		$ballots = get_posts(
			array(
				'post_type'   => 'prx3_ballot',
				'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'numberposts' => -1,
				'orderby'     => 'date',
				'order'       => 'ASC',
			)
		);
		foreach ( $ballots as $ballot ) {
			self::assign_number( $ballot->ID, $ballot );
		}
		update_option( 'prx3_ballot_slugs', 'v1', false );
	}

	/* ---------------- Casting ---------------- */

	/**
	 * Cast (or revise) a member's vote. FO-202.
	 *
	 * @param int $ballot_id Ballot.
	 * @param int $user_id   Member.
	 * @param int $choice    Option index.
	 * @return array|WP_Error ['weight' => int, 'revised' => bool]
	 */
	public static function cast( $ballot_id, $user_id, $choice ) {
		global $wpdb;
		if ( ! prx3_feature_on( 'voting' ) ) {
			return new WP_Error( 'prx3_off', __( 'Voting is temporarily unavailable.', 'fan-ownership' ) );
		}
		if ( 'open' !== self::state( $ballot_id ) ) {
			return new WP_Error( 'prx3_closed', __( 'This ballot is not open.', 'fan-ownership' ) );
		}
		$options = get_post_meta( $ballot_id, '_prx3_options', true );
		if ( ! is_array( $options ) || ! isset( $options[ $choice ] ) ) {
			return new WP_Error( 'prx3_choice', __( 'That option does not exist on this ballot.', 'fan-ownership' ) );
		}
		// FO-204: eligibility and weight from the snapshot at open.
		$electorate = get_post_meta( $ballot_id, '_prx3_electorate', true );
		if ( ! is_array( $electorate ) || empty( $electorate[ $user_id ] ) ) {
			return new WP_Error(
				'prx3_not_eligible',
				__( 'You were not an owner when this ballot opened, so you are not in its electorate. You can vote on every ballot that opens from now on.', 'fan-ownership' )
			);
		}
		$weight = (int) $electorate[ $user_id ];

		$table    = $wpdb->prefix . 'prx3_ballot_votes';
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, revised FROM $table WHERE ballot_id = %d AND user_id = %d", $ballot_id, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own votes table; vote reads are deliberately uncached so counts are always live.
		if ( $existing ) {
			// FO-202 AC2: revision replaces the final choice, exactly once counted.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- writing to the plugin's own votes table.
				$table,
				array(
					'choice'  => $choice,
					'weight'  => $weight,
					'cast_at' => prx3_now(),
					'revised' => (int) $existing->revised + 1,
				),
				array( 'id' => $existing->id ),
				array( '%d', '%d', '%s', '%d' ),
				array( '%d' )
			);
			$revised = true;
		} else {
			$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- inserting into the plugin's own votes table; the unique (ballot, user) key enforces one row per member.
				$table,
				array(
					'ballot_id' => $ballot_id,
					'user_id'   => $user_id,
					'choice'    => $choice,
					'weight'    => $weight,
					'cast_at'   => prx3_now(),
					'revised'   => 0,
				),
				array( '%d', '%d', '%d', '%d', '%s', '%d' )
			);
			if ( false === $inserted ) {
				// Unique key collision under concurrency: treat as revision.
				return self::cast( $ballot_id, $user_id, $choice );
			}
			$revised = false;
			do_action( 'prx3_milestone_event', 'ballot_voted', $user_id );
			PRX3_Onboarding::mark_complete( $user_id, 'voted' );
		}
		prx3_touch_activity( $user_id );
		return array(
			'weight'  => $weight,
			'revised' => $revised,
		);
	}

	/* ---------------- Reads ---------------- */

	/**
	 * A ballot's lifecycle state.
	 *
	 * @param int $ballot_id Ballot.
	 * @return string One of draft|scheduled|open|closed|rerun|unresolved|published.
	 */
	public static function state( $ballot_id ) {
		return get_post_meta( $ballot_id, '_prx3_state', true );
	}

	/**
	 * Whether a ballot requires the constitutional supermajority. FO-207.
	 *
	 * @param int $ballot_id Ballot.
	 * @return bool True for constitutional ballots.
	 */
	public static function is_constitutional( $ballot_id ) {
		return 'constitutional' === get_post_meta( $ballot_id, '_prx3_type', true );
	}

	/**
	 * Live tallies. FO-203: exposed only to prx3_view_tally while open.
	 *
	 * @param int  $ballot_id Ballot.
	 * @param bool $bypass    Internal lifecycle use.
	 * @return array|WP_Error [choice => weight_total], plus turnout counts.
	 */
	public static function tallies( $ballot_id, $bypass = false ) {
		if ( ! $bypass && 'open' === self::state( $ballot_id ) && ! current_user_can( 'prx3_view_tally' ) ) {
			return new WP_Error( 'prx3_secret', __( 'This is a secret ballot — results are revealed when it closes.', 'fan-ownership' ) );
		}
		global $wpdb;
		$rows    = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own votes table; tallies must be read live, never from cache.
			$wpdb->prepare( "SELECT choice, SUM(weight) AS votes, COUNT(*) AS members FROM {$wpdb->prefix}prx3_ballot_votes WHERE ballot_id = %d GROUP BY choice", $ballot_id ),
			ARRAY_A
		);
		$options = (array) get_post_meta( $ballot_id, '_prx3_options', true );
		$out     = array(
			'votes'       => array_fill( 0, count( $options ), 0 ),
			'members'     => 0,
			'total_votes' => 0,
		);
		foreach ( $rows as $row ) {
			$out['votes'][ (int) $row['choice'] ] = (int) $row['votes'];
			$out['members']                      += (int) $row['members'];
			$out['total_votes']                  += (int) $row['votes'];
		}
		return $out;
	}

	/**
	 * A member's own vote on a ballot (own-data view; never others').
	 *
	 * @param int $ballot_id Ballot.
	 * @param int $user_id   Member.
	 * @return array|null Vote row (choice, weight, cast_at, revised) or null.
	 */
	public static function member_choice( $ballot_id, $user_id ) {
		global $wpdb;
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own votes table; the member's current choice must be live.
			$wpdb->prepare( "SELECT choice, weight, cast_at, revised FROM {$wpdb->prefix}prx3_ballot_votes WHERE ballot_id = %d AND user_id = %d", $ballot_id, $user_id ),
			ARRAY_A
		);
	}

	/**
	 * A member's full participation history (privacy export).
	 *
	 * @param int $user_id Member.
	 * @return array Vote rows (ballot_id, choice, weight, cast_at), oldest first.
	 */
	public static function member_vote_history( $user_id ) {
		global $wpdb;
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own votes table; one-off privacy export read.
			$wpdb->prepare( "SELECT ballot_id, choice, weight, cast_at FROM {$wpdb->prefix}prx3_ballot_votes WHERE user_id = %d ORDER BY id ASC", $user_id ),
			ARRAY_A
		);
	}

	/**
	 * Open ballots (max 2 by rule), and the queue behind them. FO-201 AC2.
	 *
	 * @return WP_Post[] Ballots currently in the open state.
	 */
	public static function open_ballots() {
		return get_posts(
			array(
				'post_type'      => 'prx3_ballot',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_prx3_state', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded lifecycle lookup; at most two ballots are ever open.
				'meta_value'     => 'open', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'        => 'meta_value',
				'no_found_rows'  => true,
			)
		);
	}

	/**
	 * Ballots queued behind the live cap, awaiting their open time.
	 *
	 * @return WP_Post[] Ballots currently in the scheduled state.
	 */
	public static function scheduled_ballots() {
		return get_posts(
			array(
				'post_type'      => 'prx3_ballot',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_prx3_state', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded lifecycle lookup over the small ballot post type.
				'meta_value'     => 'scheduled', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'        => 'meta_value_datetime',
				'no_found_rows'  => true,
			)
		);
	}

	/* ---------------- Authoring ---------------- */

	/**
	 * Register the ballot setup meta box on the ballot editor.
	 */
	public static function meta_boxes() {
		add_meta_box( 'prx3_ballot_setup', __( 'Ballot Setup', 'fan-ownership' ), array( __CLASS__, 'render_setup' ), 'prx3_ballot', 'normal', 'high' );
	}

	/**
	 * Render the ballot setup meta box: options, type, window, board
	 * recommendation, and the staff-only running tally. FO-201, FO-203.
	 *
	 * @param WP_Post $post The ballot being edited.
	 */
	public static function render_setup( $post ) {
		wp_nonce_field( 'prx3_ballot_meta', 'prx3_ballot_nonce' );
		$options = get_post_meta( $post->ID, '_prx3_options', true );
		$type    = get_post_meta( $post->ID, '_prx3_type', true );
		$opens   = get_post_meta( $post->ID, '_prx3_opens', true );
		$closes  = get_post_meta( $post->ID, '_prx3_closes', true );
		$state   = self::state( $post->ID );
		$locked  = in_array( $state, array( 'open', 'closed', 'published', 'rerun', 'unresolved' ), true );
		$rec     = get_post_meta( $post->ID, '_prx3_board_recommendation', true );

		if ( $locked ) {
			// FO-201 AC4: no edits once voting has started.
			echo '<p><strong>' . esc_html__( 'Voting has started or finished — options and rules are locked. To correct an error, withdraw this ballot (recorded and announced) and issue a new one.', 'fan-ownership' ) . '</strong></p>';
		}
		$dis = $locked ? 'disabled' : '';
		echo '<p><label for="prx3_options"><strong>' . esc_html__( 'Options (one per line, minimum two)', 'fan-ownership' ) . '</strong></label>';
		echo '<textarea class="widefat" rows="5" id="prx3_options" name="prx3_options" ' . esc_attr( $dis ) . '>' . esc_textarea( is_array( $options ) ? implode( "\n", $options ) : '' ) . '</textarea></p>';
		echo '<p><label for="prx3_type"><strong>' . esc_html__( 'Type', 'fan-ownership' ) . '</strong></label> ';
		echo '<select id="prx3_type" name="prx3_type" ' . esc_attr( $dis ) . '>';
		echo '<option value="standard" ' . selected( $type, 'standard', false ) . '>' . esc_html__( 'Standard (simple majority)', 'fan-ownership' ) . '</option>';
		echo '<option value="constitutional" ' . selected( $type, 'constitutional', false ) . '>' . esc_html(
			sprintf( /* translators: %d percent. */ __( 'Constitutional (%d%% supermajority)', 'fan-ownership' ), (int) prx3_setting( 'constitutional_pct', 75 ) )
		) . '</option></select></p>';
		echo '<p><label for="prx3_opens"><strong>' . esc_html__( 'Opens', 'fan-ownership' ) . '</strong></label> ';
		echo '<input type="datetime-local" id="prx3_opens" name="prx3_opens" value="' . esc_attr( $opens ? gmdate( 'Y-m-d\TH:i', strtotime( $opens ) ) : '' ) . '" ' . esc_attr( $dis ) . '>';
		echo ' <label for="prx3_closes"><strong>' . esc_html__( 'Closes', 'fan-ownership' ) . '</strong></label> ';
		echo '<input type="datetime-local" id="prx3_closes" name="prx3_closes" value="' . esc_attr( $closes ? gmdate( 'Y-m-d\TH:i', strtotime( $closes ) ) : '' ) . '" ' . esc_attr( $dis ) . '></p>';
		echo '<p class="description">' . esc_html(
			sprintf(
			/* translators: 1: window days, 2: max live. */
				__( 'Leave "closes" empty for the default %1$d-day window. At most %2$d ballots run at once — later ballots queue automatically.', 'fan-ownership' ),
				(int) prx3_setting( 'ballot_window_days', 7 ),
				(int) prx3_setting( 'max_live_ballots', 2 )
			)
		) . '</p>';
		echo '<p><label for="prx3_board_rec"><strong>' . esc_html__( 'Board recommendation (optional, shown as the board position)', 'fan-ownership' ) . '</strong></label>';
		echo '<textarea class="widefat" rows="2" id="prx3_board_rec" name="prx3_board_rec">' . esc_textarea( $rec ) . '</textarea></p>';

		if ( 'open' === $state && current_user_can( 'prx3_view_tally' ) ) {
			$tallies = self::tallies( $post->ID );
			if ( ! is_wp_error( $tallies ) && is_array( $options ) ) {
				echo '<h4>' . esc_html__( 'Running tally (staff and board only)', 'fan-ownership' ) . '</h4><ul>';
				foreach ( $options as $i => $label ) {
					echo '<li>' . esc_html( $label ) . ' — <strong>' . (int) $tallies['votes'][ $i ] . '</strong></li>';
				}
				echo '</ul><p>' . esc_html(
					sprintf(
					/* translators: 1: members voted, 2: quorum needed. */
						__( '%1$d members have voted. Quorum requires %2$d.', 'fan-ownership' ),
						(int) $tallies['members'],
						(int) ceil( (int) get_post_meta( $post->ID, '_prx3_quorum_denominator', true ) * (int) prx3_setting( 'quorum_percent', 25 ) / 100 )
					)
				) . '</p>';
			}
		}
	}

	/**
	 * Persist ballot setup from the meta box; rules lock once voting has
	 * started (FO-201 AC4).
	 *
	 * @param int     $post_id Ballot post ID.
	 * @param WP_Post $post    The post being saved.
	 */
	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['prx3_ballot_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['prx3_ballot_nonce'] ), 'prx3_ballot_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'prx3_governance' ) && ! current_user_can( 'prx3_admin' ) ) {
			return;
		}
		$state = self::state( $post_id );
		if ( in_array( $state, array( 'open', 'closed', 'published', 'rerun', 'unresolved' ), true ) ) {
			// Locked except the board recommendation, which may still be attached.
			if ( isset( $_POST['prx3_board_rec'] ) && 'open' === $state ) {
				update_post_meta( $post_id, '_prx3_board_recommendation', sanitize_textarea_field( wp_unslash( $_POST['prx3_board_rec'] ) ) );
			}
			return;
		}
		$raw     = isset( $_POST['prx3_options'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prx3_options'] ) ) : '';
		$options = array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
		update_post_meta( $post_id, '_prx3_options', $options );
		$type = isset( $_POST['prx3_type'] ) && 'constitutional' === $_POST['prx3_type'] ? 'constitutional' : 'standard';
		update_post_meta( $post_id, '_prx3_type', $type );

		$opens     = isset( $_POST['prx3_opens'] ) ? sanitize_text_field( wp_unslash( $_POST['prx3_opens'] ) ) : '';
		$closes    = isset( $_POST['prx3_closes'] ) ? sanitize_text_field( wp_unslash( $_POST['prx3_closes'] ) ) : '';
		$opens_ts  = $opens ? strtotime( $opens ) : time();
		$closes_ts = $closes ? strtotime( $closes ) : $opens_ts + (int) prx3_setting( 'ballot_window_days', 7 ) * DAY_IN_SECONDS;
		update_post_meta( $post_id, '_prx3_opens', gmdate( 'Y-m-d H:i:s', $opens_ts ) );
		update_post_meta( $post_id, '_prx3_closes', gmdate( 'Y-m-d H:i:s', $closes_ts ) );
		update_post_meta( $post_id, '_prx3_board_recommendation', isset( $_POST['prx3_board_rec'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prx3_board_rec'] ) ) : '' );
		if ( ! $state ) {
			update_post_meta( $post_id, '_prx3_state', 'draft' );
		}
		if ( 'publish' === $post->post_status && count( $options ) >= 2 && 'draft' === self::state( $post_id ) ) {
			update_post_meta( $post_id, '_prx3_state', 'scheduled' );
		}
	}
}
