<?php
/**
 * The automated ballot lifecycle. FO-205, FO-206, FO-207, FO-208.
 *
 * Every five minutes the scheduler:
 *  1. Opens due scheduled ballots (respecting the max-2 rule) with an
 *     electorate snapshot and quorum denominator.
 *  2. Sends closing-soon quorum reminders to non-voters.
 *  3. Closes due ballots: snapshot, quorum check, result computation
 *     (simple majority / 75% constitutional / tie -> board casting vote),
 *     automatic re-run once on failed quorum, instant publication.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Ballot_Lifecycle {

	public static function init() {
		add_action( 'prx3_ballot_tick', array( __CLASS__, 'tick' ) );
		// 5-minute heartbeat is required for timely ballot open/close (FO-206);
		// the tick is cheap when nothing is due.
		// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected, WordPress.WP.CronInterval.CronSchedulesInterval
		add_filter( 'cron_schedules', array( __CLASS__, 'five_minutes' ) );
	}

	public static function five_minutes( $schedules ) {
		$schedules['prx3_5min'] = array(
			'interval' => 300,
			'display'  => 'Every 5 minutes (Fan Ownership)',
		);
		return $schedules;
	}

	public static function schedule_cron() {
		if ( ! wp_next_scheduled( 'prx3_ballot_tick' ) ) {
			wp_schedule_event( time() + 60, 'prx3_5min', 'prx3_ballot_tick' );
		}
	}

	public static function unschedule_cron() {
		wp_clear_scheduled_hook( 'prx3_ballot_tick' );
	}

	public static function tick() {
		self::open_due_ballots();
		self::remind_closing_soon();
		self::close_due_ballots();
		PRX3_Decisions::flag_stalled();
	}

	/**
	 * FO-201 AC2 + FO-204: open due ballots up to the live cap, snapshot
	 * the electorate at the moment of opening.
	 */
	public static function open_due_ballots() {
		$live_cap = (int) prx3_setting( 'max_live_ballots', 2 );
		$open_now = count( PRX3_Ballots::open_ballots() );
		if ( $open_now >= $live_cap ) {
			return;
		}
		foreach ( PRX3_Ballots::scheduled_ballots() as $ballot ) {
			if ( $open_now >= $live_cap ) {
				break;
			}
			$opens = get_post_meta( $ballot->ID, '_prx3_opens', true );
			if ( ! $opens || strtotime( $opens ) > time() ) {
				continue;
			}
			self::open_ballot( $ballot->ID );
			++$open_now;
		}
	}

	public static function open_ballot( $ballot_id ) {
		// Electorate snapshot: active owners and their weights at open (P61/P76).
		$active     = prx3_active_owner_ids();
		$electorate = array();
		foreach ( $active as $uid ) {
			$shares = prx3_shares( $uid );
			if ( $shares > 0 ) {
				$electorate[ $uid ] = $shares;
			}
		}
		update_post_meta( $ballot_id, '_prx3_electorate', $electorate );
		update_post_meta( $ballot_id, '_prx3_quorum_denominator', count( $electorate ) );
		update_post_meta( $ballot_id, '_prx3_state', 'open' );
		update_post_meta( $ballot_id, '_prx3_opened_at', prx3_now() );

		PRX3_Audit::log( 'ballot_open', sprintf( 'Ballot %d opened; electorate %d members', $ballot_id, count( $electorate ) ) );
		self::notify_electorate(
			$ballot_id,
			__( 'Voting is open', 'fan-ownership' ),
			sprintf(
				/* translators: 1: ballot title, 2: close date. */
				__( '"%1$s" is open for your vote. Voting closes %2$s.', 'fan-ownership' ),
				get_the_title( $ballot_id ),
				prx3_format_datetime( get_post_meta( $ballot_id, '_prx3_closes', true ) )
			)
		);
	}

	/**
	 * FO-205 AC4: pre-close reminder to non-voters when quorum is at risk.
	 */
	public static function remind_closing_soon() {
		foreach ( PRX3_Ballots::open_ballots() as $ballot ) {
			$closes = strtotime( get_post_meta( $ballot->ID, '_prx3_closes', true ) );
			if ( ! $closes || $closes - time() > DAY_IN_SECONDS || get_post_meta( $ballot->ID, '_prx3_reminded', true ) ) {
				continue;
			}
			update_post_meta( $ballot->ID, '_prx3_reminded', 1 );
			$tallies     = PRX3_Ballots::tallies( $ballot->ID, true );
			$denominator = (int) get_post_meta( $ballot->ID, '_prx3_quorum_denominator', true );
			$needed      = (int) ceil( $denominator * (int) prx3_setting( 'quorum_percent', 25 ) / 100 );
			$electorate  = (array) get_post_meta( $ballot->ID, '_prx3_electorate', true );
			$voted       = self::voter_ids( $ballot->ID );
			$non_voters  = array_diff( array_keys( $electorate ), $voted );
			$at_risk     = is_array( $tallies ) ? $tallies['members'] < $needed : true;
			$subject     = $at_risk ? __( 'Last day — your club needs your vote', 'fan-ownership' ) : __( 'Voting closes tomorrow', 'fan-ownership' );
			foreach ( $non_voters as $uid ) {
				$user = get_userdata( $uid );
				if ( $user ) {
					PRX3_Comms::send(
						$user->user_email,
						$subject,
						sprintf(
						/* translators: %s ballot title. */
							__( '"%s" closes within 24 hours and your vote has not been cast yet.', 'fan-ownership' ),
							get_the_title( $ballot->ID )
						),
						'governance'
					);
				}
			}
			PRX3_Comms::push( $non_voters, $subject, get_the_title( $ballot->ID ), 'governance', get_permalink( $ballot->ID ) );
		}
	}

	private static function voter_ids( $ballot_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}prx3_ballot_votes WHERE ballot_id = %d", $ballot_id ) ) );
	}

	/**
	 * Close due ballots and publish results instantly (FO-206).
	 */
	public static function close_due_ballots() {
		foreach ( PRX3_Ballots::open_ballots() as $ballot ) {
			$closes = get_post_meta( $ballot->ID, '_prx3_closes', true );
			if ( $closes && strtotime( $closes ) <= time() ) {
				self::close_ballot( $ballot->ID );
			}
		}
	}

	public static function close_ballot( $ballot_id ) {
		// FO-206 AC4: audit snapshot before results.
		$tallies = PRX3_Ballots::tallies( $ballot_id, true );
		update_post_meta(
			$ballot_id,
			'_prx3_close_snapshot',
			array(
				'at'      => prx3_now(),
				'tallies' => $tallies,
			)
		);
		PRX3_Audit::log( 'ballot_close_snapshot', sprintf( 'Ballot %d snapshot at close', $ballot_id ), $tallies );

		$denominator = (int) get_post_meta( $ballot_id, '_prx3_quorum_denominator', true );
		$needed      = (int) ceil( $denominator * (int) prx3_setting( 'quorum_percent', 25 ) / 100 );
		$turnout_ok  = $tallies['members'] >= $needed;

		if ( ! $turnout_ok ) {
			self::handle_failed_quorum( $ballot_id, $tallies, $needed );
			return;
		}
		self::publish_result( $ballot_id, $tallies, $needed );
	}

	/**
	 * FO-205 AC2/AC3: one automatic 7-day re-run, then unresolved -> board.
	 */
	private static function handle_failed_quorum( $ballot_id, $tallies, $needed ) {
		$is_rerun = (bool) get_post_meta( $ballot_id, '_prx3_rerun_of', true );
		if ( ! $is_rerun ) {
			update_post_meta( $ballot_id, '_prx3_state', 'rerun' );
			// Re-open the same ballot for another window; votes already cast stand.
			update_post_meta( $ballot_id, '_prx3_rerun_of', $ballot_id );
			update_post_meta( $ballot_id, '_prx3_closes', gmdate( 'Y-m-d H:i:s', time() + (int) prx3_setting( 'ballot_window_days', 7 ) * DAY_IN_SECONDS ) );
			delete_post_meta( $ballot_id, '_prx3_reminded' );
			update_post_meta( $ballot_id, '_prx3_state', 'open' );
			PRX3_Audit::log( 'ballot_rerun', sprintf( 'Ballot %d missed quorum (%d needed, %d voted) — re-running once', $ballot_id, $needed, $tallies['members'] ) );
			self::notify_electorate(
				$ballot_id,
				__( 'Ballot re-opened — quorum was missed', 'fan-ownership' ),
				sprintf(
					/* translators: 1: title, 2: needed. */
					__( '"%1$s" did not reach the %2$d-member quorum, so it has re-opened for a final 7 days. If quorum is missed again, the board will decide and publish its reasoning.', 'fan-ownership' ),
					get_the_title( $ballot_id ),
					$needed
				)
			);
			return;
		}
		// Second failure: unresolved, flagged for a board decision (FO-225).
		update_post_meta( $ballot_id, '_prx3_state', 'unresolved' );
		update_post_meta(
			$ballot_id,
			'_prx3_result',
			array(
				'outcome' => 'unresolved',
				'tallies' => $tallies,
				'needed'  => $needed,
				'closed'  => prx3_now(),
			)
		);
		PRX3_Board::open_action( 'failed_quorum', $ballot_id, sprintf( 'Ballot %d failed quorum twice — board decision required', $ballot_id ) );
		self::notify_electorate(
			$ballot_id,
			__( 'Ballot unresolved — board will decide', 'fan-ownership' ),
			sprintf(
				/* translators: %s title. */
				__( "\"%s\" missed quorum twice. As set out in the club's rules, the board will now decide and publish its reasoning in the decision register.", 'fan-ownership' ),
				get_the_title( $ballot_id )
			)
		);
	}

	/**
	 * Compute and publish the result (FO-206 AC2/AC3, FO-207, FO-208).
	 */
	private static function publish_result( $ballot_id, $tallies, $needed ) {
		$options = (array) get_post_meta( $ballot_id, '_prx3_options', true );
		$votes   = $tallies['votes'];
		$total   = max( 1, (int) $tallies['total_votes'] );
		arsort( $votes );
		$ranked = array_keys( $votes );
		$top    = (int) $ranked[0];
		$second = isset( $ranked[1] ) ? (int) $ranked[1] : null;
		$tie    = null !== $second && $votes[ $top ] === $votes[ $second ];

		$result = array(
			'tallies'   => $tallies,
			'needed'    => $needed,
			'closed'    => prx3_now(),
			'quorum_ok' => true,
		);

		if ( $tie ) {
			// FO-208: withhold pending the recorded board casting vote.
			update_post_meta( $ballot_id, '_prx3_state', 'closed' );
			$result['outcome'] = 'tie_pending_board';
			update_post_meta( $ballot_id, '_prx3_result', $result );
			PRX3_Board::open_action( 'casting_vote', $ballot_id, sprintf( 'Ballot %d tied — board casting vote required', $ballot_id ) );
			self::notify_electorate(
				$ballot_id,
				__( 'Dead heat — board casting vote to follow', 'fan-ownership' ),
				sprintf( /* translators: %s title. */ __( "\"%s\" finished level. Under the club's rules the board holds a casting vote; the result and reasoning will be published shortly.", 'fan-ownership' ), get_the_title( $ballot_id ) )
			);
			return;
		}

		$share = $votes[ $top ] / $total * 100;
		if ( PRX3_Ballots::is_constitutional( $ballot_id ) && $share < (float) prx3_setting( 'constitutional_pct', 75 ) ) {
			$result['outcome'] = 'fell_supermajority';
			$result['winning'] = null;
		} else {
			$result['outcome'] = 'passed';
			$result['winning'] = $top;
		}
		update_post_meta( $ballot_id, '_prx3_result', $result );
		update_post_meta( $ballot_id, '_prx3_state', 'published' );

		if ( 'passed' === $result['outcome'] ) {
			PRX3_Decisions::create_from_ballot( $ballot_id, $options[ $top ] );
		}

		$summary = 'passed' === $result['outcome']
			? sprintf(
				/* translators: 1: option, 2: pct, 3: members. */
				__( 'Result: "%1$s" — %2$d%% of votes cast, %3$d members voting.', 'fan-ownership' ),
				$options[ $top ],
				(int) round( $share ),
				(int) $tallies['members']
			)
			: sprintf(
				/* translators: %d percent needed. */
				__( 'The proposal fell: the leading option did not reach the %d%% supermajority this constitutional ballot required.', 'fan-ownership' ),
				(int) prx3_setting( 'constitutional_pct', 75 )
			);
		PRX3_Audit::log( 'ballot_result', sprintf( 'Ballot %d published: %s', $ballot_id, $result['outcome'] ), $result );
		self::notify_electorate( $ballot_id, sprintf( /* translators: %s title. */ __( 'Result: %s', 'fan-ownership' ), get_the_title( $ballot_id ) ), $summary );
	}

	/**
	 * Board casting vote lands (called from PRX3_Board): finish the tie.
	 */
	public static function resolve_tie( $ballot_id, $winning_choice, $reasoning ) {
		$result = get_post_meta( $ballot_id, '_prx3_result', true );
		if ( ! is_array( $result ) || 'tie_pending_board' !== ( $result['outcome'] ?? '' ) ) {
			return new WP_Error( 'prx3_not_tied', __( 'This ballot is not awaiting a casting vote.', 'fan-ownership' ) );
		}
		$options           = (array) get_post_meta( $ballot_id, '_prx3_options', true );
		$result['outcome'] = 'passed';
		$result['winning'] = (int) $winning_choice;
		$result['tie']     = array(
			'casting_vote' => true,
			'reasoning'    => $reasoning,
			'at'           => prx3_now(),
		);
		update_post_meta( $ballot_id, '_prx3_result', $result );
		update_post_meta( $ballot_id, '_prx3_state', 'published' );
		PRX3_Decisions::create_from_ballot( $ballot_id, $options[ (int) $winning_choice ], array( 'casting_vote' => $reasoning ) );
		self::notify_electorate(
			$ballot_id,
			sprintf( /* translators: %s title. */ __( 'Result (board casting vote): %s', 'fan-ownership' ), get_the_title( $ballot_id ) ),
			sprintf(
				/* translators: 1: option, 2: reasoning. */
				__( "The tie was resolved by the board's casting vote for \"%1\$s\". The board's reasoning: %2\$s", 'fan-ownership' ),
				$options[ (int) $winning_choice ],
				$reasoning
			)
		);
		return true;
	}

	/**
	 * Notify the ballot's electorate by email + push (governance category).
	 */
	private static function notify_electorate( $ballot_id, $subject, $body ) {
		$electorate = array_keys( (array) get_post_meta( $ballot_id, '_prx3_electorate', true ) );
		if ( ! $electorate ) {
			$electorate = get_users(
				array(
					'role'   => 'fan_owner',
					'fields' => 'ID',
					'number' => -1,
				)
			);
		}
		foreach ( $electorate as $uid ) {
			$user = get_userdata( $uid );
			if ( $user ) {
				PRX3_Comms::send( $user->user_email, $subject, $body, 'governance' );
			}
		}
		PRX3_Comms::push( $electorate, $subject, wp_strip_all_tags( $body ), 'governance', get_permalink( $ballot_id ) );
	}
}
