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
 *  _fop_options (array), _fop_type ('standard'|'constitutional'),
 *  _fop_opens / _fop_closes (datetimes), _fop_state
 *  ('draft'|'scheduled'|'open'|'closed'|'rerun'|'unresolved'|'published'),
 *  _fop_electorate (user_id => weight, snapshot at open),
 *  _fop_quorum_denominator (int), _fop_rerun_of (ballot id),
 *  _fop_board_recommendation (text), _fop_result (array at close).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Ballots {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_fop_ballot', array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ */
	/* Casting                                                            */
	/* ------------------------------------------------------------------ */

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
		if ( ! fop_feature_on( 'voting' ) ) {
			return new WP_Error( 'fop_off', __( 'Voting is temporarily unavailable.', 'fan-ownership' ) );
		}
		if ( 'open' !== self::state( $ballot_id ) ) {
			return new WP_Error( 'fop_closed', __( 'This ballot is not open.', 'fan-ownership' ) );
		}
		$options = get_post_meta( $ballot_id, '_fop_options', true );
		if ( ! is_array( $options ) || ! isset( $options[ $choice ] ) ) {
			return new WP_Error( 'fop_choice', __( 'That option does not exist on this ballot.', 'fan-ownership' ) );
		}
		// FO-204: eligibility and weight from the snapshot at open.
		$electorate = get_post_meta( $ballot_id, '_fop_electorate', true );
		if ( ! is_array( $electorate ) || empty( $electorate[ $user_id ] ) ) {
			return new WP_Error(
				'fop_not_eligible',
				__( 'You were not an owner when this ballot opened, so you are not in its electorate. You can vote on every ballot that opens from now on.', 'fan-ownership' )
			);
		}
		$weight = (int) $electorate[ $user_id ];

		$table    = $wpdb->prefix . 'fop_ballot_votes';
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, revised FROM $table WHERE ballot_id = %d AND user_id = %d", $ballot_id, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $existing ) {
			// FO-202 AC2: revision replaces the final choice, exactly once counted.
			$wpdb->update(
				$table,
				array(
					'choice'  => $choice,
					'weight'  => $weight,
					'cast_at' => fop_now(),
					'revised' => (int) $existing->revised + 1,
				),
				array( 'id' => $existing->id ),
				array( '%d', '%d', '%s', '%d' ),
				array( '%d' )
			);
			$revised = true;
		} else {
			$inserted = $wpdb->insert(
				$table,
				array(
					'ballot_id' => $ballot_id,
					'user_id'   => $user_id,
					'choice'    => $choice,
					'weight'    => $weight,
					'cast_at'   => fop_now(),
					'revised'   => 0,
				),
				array( '%d', '%d', '%d', '%d', '%s', '%d' )
			);
			if ( false === $inserted ) {
				// Unique key collision under concurrency: treat as revision.
				return self::cast( $ballot_id, $user_id, $choice );
			}
			$revised = false;
			do_action( 'fop_milestone_event', 'ballot_voted', $user_id );
			FOP_Onboarding::mark_complete( $user_id, 'voted' );
		}
		fop_touch_activity( $user_id );
		return array(
			'weight'  => $weight,
			'revised' => $revised,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Reads                                                              */
	/* ------------------------------------------------------------------ */

	public static function state( $ballot_id ) {
		return get_post_meta( $ballot_id, '_fop_state', true );
	}

	public static function is_constitutional( $ballot_id ) {
		return 'constitutional' === get_post_meta( $ballot_id, '_fop_type', true );
	}

	/**
	 * Live tallies. FO-203: exposed only to fop_view_tally while open.
	 *
	 * @param int  $ballot_id Ballot.
	 * @param bool $bypass    Internal lifecycle use.
	 * @return array|WP_Error [choice => weight_total], plus turnout counts.
	 */
	public static function tallies( $ballot_id, $bypass = false ) {
		if ( ! $bypass && 'open' === self::state( $ballot_id ) && ! current_user_can( 'fop_view_tally' ) ) {
			return new WP_Error( 'fop_secret', __( 'This is a secret ballot — results are revealed when it closes.', 'fan-ownership' ) );
		}
		global $wpdb;
		$rows    = $wpdb->get_results(
			$wpdb->prepare( "SELECT choice, SUM(weight) AS votes, COUNT(*) AS members FROM {$wpdb->prefix}fop_ballot_votes WHERE ballot_id = %d GROUP BY choice", $ballot_id ),
			ARRAY_A
		);
		$options = (array) get_post_meta( $ballot_id, '_fop_options', true );
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
	 */
	public static function member_choice( $ballot_id, $user_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT choice, weight, cast_at, revised FROM {$wpdb->prefix}fop_ballot_votes WHERE ballot_id = %d AND user_id = %d", $ballot_id, $user_id ),
			ARRAY_A
		);
	}

	/**
	 * A member's full participation history (privacy export).
	 */
	public static function member_vote_history( $user_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT ballot_id, choice, weight, cast_at FROM {$wpdb->prefix}fop_ballot_votes WHERE user_id = %d ORDER BY id ASC", $user_id ),
			ARRAY_A
		);
	}

	/**
	 * Open ballots (max 2 by rule), and the queue behind them. FO-201 AC2.
	 */
	public static function open_ballots() {
		return get_posts(
			array(
				'post_type'      => 'fop_ballot',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_fop_state',
				'meta_value'     => 'open',
				'orderby'        => 'meta_value',
				'no_found_rows'  => true,
			)
		);
	}

	public static function scheduled_ballots() {
		return get_posts(
			array(
				'post_type'      => 'fop_ballot',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_fop_state',
				'meta_value'     => 'scheduled',
				'orderby'        => 'meta_value_datetime',
				'no_found_rows'  => true,
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Authoring                                                          */
	/* ------------------------------------------------------------------ */

	public static function meta_boxes() {
		add_meta_box( 'fop_ballot_setup', __( 'Ballot Setup', 'fan-ownership' ), array( __CLASS__, 'render_setup' ), 'fop_ballot', 'normal', 'high' );
	}

	public static function render_setup( $post ) {
		wp_nonce_field( 'fop_ballot_meta', 'fop_ballot_nonce' );
		$options = get_post_meta( $post->ID, '_fop_options', true );
		$type    = get_post_meta( $post->ID, '_fop_type', true );
		$opens   = get_post_meta( $post->ID, '_fop_opens', true );
		$closes  = get_post_meta( $post->ID, '_fop_closes', true );
		$state   = self::state( $post->ID );
		$locked  = in_array( $state, array( 'open', 'closed', 'published', 'rerun', 'unresolved' ), true );
		$rec     = get_post_meta( $post->ID, '_fop_board_recommendation', true );

		if ( $locked ) {
			// FO-201 AC4: no edits once voting has started.
			echo '<p><strong>' . esc_html__( 'Voting has started or finished — options and rules are locked. To correct an error, withdraw this ballot (recorded and announced) and issue a new one.', 'fan-ownership' ) . '</strong></p>';
		}
		$dis = $locked ? 'disabled' : '';
		echo '<p><label for="fop_options"><strong>' . esc_html__( 'Options (one per line, minimum two)', 'fan-ownership' ) . '</strong></label>';
		echo '<textarea class="widefat" rows="5" id="fop_options" name="fop_options" ' . esc_attr( $dis ) . '>' . esc_textarea( is_array( $options ) ? implode( "\n", $options ) : '' ) . '</textarea></p>';
		echo '<p><label for="fop_type"><strong>' . esc_html__( 'Type', 'fan-ownership' ) . '</strong></label> ';
		echo '<select id="fop_type" name="fop_type" ' . esc_attr( $dis ) . '>';
		echo '<option value="standard" ' . selected( $type, 'standard', false ) . '>' . esc_html__( 'Standard (simple majority)', 'fan-ownership' ) . '</option>';
		echo '<option value="constitutional" ' . selected( $type, 'constitutional', false ) . '>' . esc_html(
			sprintf( /* translators: %d percent. */ __( 'Constitutional (%d%% supermajority)', 'fan-ownership' ), (int) fop_setting( 'constitutional_pct', 75 ) )
		) . '</option></select></p>';
		echo '<p><label for="fop_opens"><strong>' . esc_html__( 'Opens', 'fan-ownership' ) . '</strong></label> ';
		echo '<input type="datetime-local" id="fop_opens" name="fop_opens" value="' . esc_attr( $opens ? gmdate( 'Y-m-d\TH:i', strtotime( $opens ) ) : '' ) . '" ' . esc_attr( $dis ) . '>';
		echo ' <label for="fop_closes"><strong>' . esc_html__( 'Closes', 'fan-ownership' ) . '</strong></label> ';
		echo '<input type="datetime-local" id="fop_closes" name="fop_closes" value="' . esc_attr( $closes ? gmdate( 'Y-m-d\TH:i', strtotime( $closes ) ) : '' ) . '" ' . esc_attr( $dis ) . '></p>';
		echo '<p class="description">' . esc_html(
			sprintf(
			/* translators: 1: window days, 2: max live. */
				__( 'Leave "closes" empty for the default %1$d-day window. At most %2$d ballots run at once — later ballots queue automatically.', 'fan-ownership' ),
				(int) fop_setting( 'ballot_window_days', 7 ),
				(int) fop_setting( 'max_live_ballots', 2 )
			)
		) . '</p>';
		echo '<p><label for="fop_board_rec"><strong>' . esc_html__( 'Board recommendation (optional, shown as the board position)', 'fan-ownership' ) . '</strong></label>';
		echo '<textarea class="widefat" rows="2" id="fop_board_rec" name="fop_board_rec">' . esc_textarea( $rec ) . '</textarea></p>';

		if ( 'open' === $state && current_user_can( 'fop_view_tally' ) ) {
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
						(int) ceil( (int) get_post_meta( $post->ID, '_fop_quorum_denominator', true ) * (int) fop_setting( 'quorum_percent', 25 ) / 100 )
					)
				) . '</p>';
			}
		}
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['fop_ballot_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fop_ballot_nonce'] ), 'fop_ballot_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'fop_governance' ) && ! current_user_can( 'fop_admin' ) ) {
			return;
		}
		$state = self::state( $post_id );
		if ( in_array( $state, array( 'open', 'closed', 'published', 'rerun', 'unresolved' ), true ) ) {
			// Locked except the board recommendation, which may still be attached.
			if ( isset( $_POST['fop_board_rec'] ) && 'open' === $state ) {
				update_post_meta( $post_id, '_fop_board_recommendation', sanitize_textarea_field( wp_unslash( $_POST['fop_board_rec'] ) ) );
			}
			return;
		}
		$raw     = isset( $_POST['fop_options'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fop_options'] ) ) : '';
		$options = array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
		update_post_meta( $post_id, '_fop_options', $options );
		$type = isset( $_POST['fop_type'] ) && 'constitutional' === $_POST['fop_type'] ? 'constitutional' : 'standard';
		update_post_meta( $post_id, '_fop_type', $type );

		$opens     = isset( $_POST['fop_opens'] ) ? sanitize_text_field( wp_unslash( $_POST['fop_opens'] ) ) : '';
		$closes    = isset( $_POST['fop_closes'] ) ? sanitize_text_field( wp_unslash( $_POST['fop_closes'] ) ) : '';
		$opens_ts  = $opens ? strtotime( $opens ) : time();
		$closes_ts = $closes ? strtotime( $closes ) : $opens_ts + (int) fop_setting( 'ballot_window_days', 7 ) * DAY_IN_SECONDS;
		update_post_meta( $post_id, '_fop_opens', gmdate( 'Y-m-d H:i:s', $opens_ts ) );
		update_post_meta( $post_id, '_fop_closes', gmdate( 'Y-m-d H:i:s', $closes_ts ) );
		update_post_meta( $post_id, '_fop_board_recommendation', isset( $_POST['fop_board_rec'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fop_board_rec'] ) ) : '' );
		if ( ! $state ) {
			update_post_meta( $post_id, '_fop_state', 'draft' );
		}
		if ( 'publish' === $post->post_status && count( $options ) >= 2 && 'draft' === self::state( $post_id ) ) {
			update_post_meta( $post_id, '_fop_state', 'scheduled' );
		}
	}
}
