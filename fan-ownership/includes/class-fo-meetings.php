<?php
/**
 * Meetings: RSVP handling and meeting queries.
 *
 * @package FanOwnership
 */

defined( 'ABSPATH' ) || exit;

class FO_Meetings {

	public static function init() {
		add_action( 'wp_ajax_fo_rsvp', array( __CLASS__, 'ajax_rsvp' ) );
	}

	/**
	 * AJAX: toggle attendance for a meeting.
	 */
	public static function ajax_rsvp() {
		check_ajax_referer( 'fo_ajax', 'nonce' );

		if ( ! fo_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Only fan owners can RSVP.', 'fan-ownership' ) ), 403 );
		}

		$meeting_id = isset( $_POST['meeting_id'] ) ? absint( $_POST['meeting_id'] ) : 0;
		if ( ! $meeting_id || get_post_type( $meeting_id ) !== 'fo_meeting' || get_post_status( $meeting_id ) !== 'publish' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid meeting.', 'fan-ownership' ) ), 400 );
		}

		$user_id   = get_current_user_id();
		$attendees = get_post_meta( $meeting_id, '_fo_meeting_attendees', true );
		$attendees = is_array( $attendees ) ? $attendees : array();

		if ( in_array( $user_id, $attendees, true ) ) {
			$attendees = array_values( array_diff( $attendees, array( $user_id ) ) );
			$attending = false;
		} else {
			$attendees[] = $user_id;
			$attending   = true;
		}
		update_post_meta( $meeting_id, '_fo_meeting_attendees', $attendees );

		$count = count( $attendees );
		wp_send_json_success(
			array(
				'attending' => $attending,
				'count'     => $count,
				'label'     => $attending ? __( 'Attending ✓', 'fan-ownership' ) : __( 'RSVP — I will attend', 'fan-ownership' ),
				'message'   => $attending
					? __( 'You are on the attendee list. See you there!', 'fan-ownership' )
					: __( 'Your RSVP has been withdrawn.', 'fan-ownership' ),
			)
		);
	}

	/**
	 * Attendee count for a meeting.
	 *
	 * @param int $meeting_id Meeting post ID.
	 * @return int
	 */
	public static function attendee_count( $meeting_id ) {
		$attendees = get_post_meta( $meeting_id, '_fo_meeting_attendees', true );
		return is_array( $attendees ) ? count( $attendees ) : 0;
	}

	/**
	 * Is the given user attending?
	 *
	 * @param int $meeting_id Meeting post ID.
	 * @param int $user_id    User ID.
	 * @return bool
	 */
	public static function is_attending( $meeting_id, $user_id ) {
		$attendees = get_post_meta( $meeting_id, '_fo_meeting_attendees', true );
		return is_array( $attendees ) && in_array( $user_id, $attendees, true );
	}

	/**
	 * Upcoming published meetings, soonest first.
	 *
	 * @param int $limit How many to fetch.
	 * @return WP_Post[]
	 */
	public static function upcoming( $limit = 5 ) {
		return get_posts(
			array(
				'post_type'      => 'fo_meeting',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_key'       => '_fo_meeting_date',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => '_fo_meeting_date',
						'value'   => fo_now(),
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				),
			)
		);
	}
}
