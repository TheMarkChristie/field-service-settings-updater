<?php
/**
 * Guided first week for new owners. FO-115.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Guided first week for new owners: a scheduled three-step email series
 * that skips completed actions and can be dismissed at any time.
 */
class PRX3_Onboarding {

	/**
	 * Hook the journey start, scheduled steps, and dismiss handler.
	 */
	public static function init() {
		add_action( 'prx3_member_became_owner', array( __CLASS__, 'start_journey' ) );
		add_action( 'prx3_onboarding_step', array( __CLASS__, 'send_step' ), 10, 2 );
		add_action( 'admin_post_prx3_dismiss_onboarding', array( __CLASS__, 'dismiss' ) );
	}

	/**
	 * Kick off: welcome view flag + scheduled email series over week one
	 * (FO-115 AC3). Steps stop early once completed.
	 *
	 * @param int $user_id Member who just became an owner.
	 */
	public static function start_journey( $user_id ) {
		update_user_meta(
			$user_id,
			'prx3_onboarding',
			array(
				'started'   => time(),
				'dismissed' => 0,
				'completed' => array(),
			)
		);
		foreach ( array(
			1 => DAY_IN_SECONDS,
			2 => 3 * DAY_IN_SECONDS,
			3 => 6 * DAY_IN_SECONDS,
		) as $step => $delay ) {
			wp_schedule_single_event( time() + $delay, 'prx3_onboarding_step', array( $user_id, $step ) );
		}
	}

	/**
	 * The email series: skipped for steps whose action is already done.
	 */
	public static function send_step( $user_id, $step ) {
		$state = get_user_meta( $user_id, 'prx3_onboarding', true );
		if ( ! is_array( $state ) || ! empty( $state['dismissed'] ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$steps = array(
			1 => array(
				'done_key' => 'voted',
				'subject'  => __( 'Cast your first vote', 'fan-ownership' ),
				'body'     => __( 'Your ownership comes with a voice. There is a starter ballot waiting in the Boardroom — cast your first vote and see how the club decides things together.', 'fan-ownership' ),
			),
			2 => array(
				'done_key' => 'visited_boardroom',
				'subject'  => __( 'A tour of your Boardroom', 'fan-ownership' ),
				'body'     => __( "Ballots, ideas, questions to the board, meetings, and the club's accounts — it is all in your Boardroom. Take five minutes to look around.", 'fan-ownership' ),
			),
			3 => array(
				'done_key' => 'watched',
				'subject'  => __( 'Your first week as an owner', 'fan-ownership' ),
				'body'     => __( "You now own a piece of the club. Watch this week's episode, say hello in the forum, and if you believe in where this is going — there is room on the ladder for more shares.", 'fan-ownership' ),
			),
		);
		if ( ! isset( $steps[ $step ] ) || ! empty( $state['completed'][ $steps[ $step ]['done_key'] ] ) ) {
			return; // FO-115 AC3: stops early when the action is done.
		}
		PRX3_Comms::send( $user->user_email, $steps[ $step ]['subject'], $steps[ $step ]['body'], 'governance' );
	}

	/**
	 * Modules report completed actions (vote cast, boardroom visited).
	 */
	public static function mark_complete( $user_id, $key ) {
		$state = get_user_meta( $user_id, 'prx3_onboarding', true );
		if ( is_array( $state ) ) {
			$state['completed'][ $key ] = time();
			update_user_meta( $user_id, 'prx3_onboarding', $state );
		}
	}

	/**
	 * FO-115 AC4: dismissible any time, never blocking.
	 */
	public static function dismiss() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please sign in.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_dismiss_onboarding' );
		$state              = get_user_meta( get_current_user_id(), 'prx3_onboarding', true );
		$state              = is_array( $state ) ? $state : array();
		$state['dismissed'] = time();
		update_user_meta( get_current_user_id(), 'prx3_onboarding', $state );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}
}
