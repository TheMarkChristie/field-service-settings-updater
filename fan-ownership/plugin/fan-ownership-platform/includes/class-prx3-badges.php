<?php
/**
 * Badge integration contract + Founders and milestone rules.
 *
 * FO-114, T61. The club's custom badge plugin implements:
 *   do_action( 'prx3_award_badge', $user_id, $badge_key, $context )  — receiver
 *   apply_filters( 'prx3_get_member_badges', array(), $user_id )     — provider
 * Awards attempted with no handler queue and retry (FO-114 AC4).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Badges {

	public static function init() {
		add_action( 'prx3_shares_granted', array( __CLASS__, 'maybe_award_founder' ), 10, 4 );
		add_action( 'prx3_milestone_event', array( __CLASS__, 'evaluate_milestones' ), 10, 2 );
		add_action( 'prx3_retry_badge_queue', array( __CLASS__, 'retry_queue' ) );
		if ( ! wp_next_scheduled( 'prx3_retry_badge_queue' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'prx3_retry_badge_queue' );
		}
	}

	/**
	 * Award a badge through the contract; queue when no handler is present.
	 *
	 * @param int    $user_id User.
	 * @param string $badge   Badge key.
	 * @param array  $context Context.
	 */
	public static function award( $user_id, $badge, $context = array() ) {
		$awarded = get_user_meta( $user_id, 'prx3_badges_awarded', true );
		$awarded = is_array( $awarded ) ? $awarded : array();
		if ( isset( $awarded[ $badge ] ) ) {
			return; // Idempotent.
		}
		if ( has_action( 'prx3_award_badge' ) ) {
			do_action( 'prx3_award_badge', $user_id, $badge, $context );
			$awarded[ $badge ] = time();
			update_user_meta( $user_id, 'prx3_badges_awarded', $awarded );
		} else {
			$queue   = get_option( 'prx3_badge_queue', array() );
			$queue[] = array(
				'user'      => $user_id,
				'badge'     => $badge,
				'context'   => $context,
				'queued_at' => time(),
			);
			update_option( 'prx3_badge_queue', $queue, false );
		}
	}

	/**
	 * Retry queued awards once a handler exists (FO-114 AC4).
	 */
	public static function retry_queue() {
		if ( ! has_action( 'prx3_award_badge' ) ) {
			return;
		}
		$queue = get_option( 'prx3_badge_queue', array() );
		foreach ( $queue as $entry ) {
			self::award( (int) $entry['user'], (string) $entry['badge'], (array) $entry['context'] );
		}
		update_option( 'prx3_badge_queue', array(), false );
	}

	/**
	 * FO-114 AC1: Founders badge for first purchases before the configured
	 * launch moment (P56).
	 */
	public static function maybe_award_founder( $user_id, $count, $total, $source ) {
		if ( $total !== $count ) {
			return; // Not their first shares.
		}
		$launch = prx3_setting( 'launch_moment', '' );
		if ( ! $launch || time() < strtotime( $launch ) ) {
			self::award( $user_id, 'founder', array( 'reason' => 'pre-launch owner' ) );
		}
	}

	/**
	 * Configurable milestone rules (FO-114 AC3): stored as option rows
	 * {key, event, threshold}. Modules fire prx3_milestone_event with an
	 * event name and the platform counts occurrences per member.
	 *
	 * @param string $event   Event key, e.g. 'ballot_voted', 'meeting_attended', 'idea_reached_ballot', 'referral'.
	 * @param int    $user_id Member.
	 */
	public static function evaluate_milestones( $event, $user_id ) {
		$counts           = get_user_meta( $user_id, 'prx3_milestone_counts', true );
		$counts           = is_array( $counts ) ? $counts : array();
		$counts[ $event ] = ( isset( $counts[ $event ] ) ? (int) $counts[ $event ] : 0 ) + 1;
		update_user_meta( $user_id, 'prx3_milestone_counts', $counts );

		$rules = get_option(
			'prx3_milestone_rules',
			array(
				array(
					'key'       => 'ten_ballots',
					'event'     => 'ballot_voted',
					'threshold' => 10,
				),
				array(
					'key'       => 'first_idea_to_ballot',
					'event'     => 'idea_reached_ballot',
					'threshold' => 1,
				),
				array(
					'key'       => 'five_referrals',
					'event'     => 'referral',
					'threshold' => 5,
				),
				array(
					'key'       => 'every_quarterly',
					'event'     => 'meeting_attended',
					'threshold' => 4,
				),
			)
		);
		foreach ( $rules as $rule ) {
			if ( $rule['event'] === $event && $counts[ $event ] >= (int) $rule['threshold'] ) {
				self::award(
					$user_id,
					$rule['key'],
					array(
						'event' => $event,
						'count' => $counts[ $event ],
					)
				);
			}
		}
	}

	/**
	 * Badges for display (profile, apps).
	 *
	 * @param int $user_id Member.
	 * @return array
	 */
	public static function member_badges( $user_id ) {
		return apply_filters( 'prx3_get_member_badges', array_keys( (array) get_user_meta( $user_id, 'prx3_badges_awarded', true ) ), $user_id );
	}
}
