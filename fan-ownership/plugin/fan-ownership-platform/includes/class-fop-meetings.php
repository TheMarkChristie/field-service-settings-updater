<?php
/**
 * Member meetings: scheduling, RSVP, ICS feed, live embed, minutes,
 * AGM resolutions.
 *
 * FO-213, FO-214 (embed + chat room), FO-215, FO-216.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Meetings {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_fop_meeting', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'ics_endpoint' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_ics' ) );
		add_action( 'fop_meeting_reminders', array( __CLASS__, 'send_reminders' ) );
		if ( ! wp_next_scheduled( 'fop_meeting_reminders' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'fop_meeting_reminders' );
		}
	}

	/**
	 * RSVP toggle (FO-213 AC1). Attendance feeds the milestone badges.
	 */
	public static function toggle_rsvp( $meeting_id, $user_id ) {
		if ( 'publish' !== get_post_status( $meeting_id ) || 'fop_meeting' !== get_post_type( $meeting_id ) ) {
			return new WP_Error( 'fop_meeting', __( 'This meeting is not open for RSVPs.', 'fan-ownership' ) );
		}
		if ( ! fop_is_owner( $user_id ) ) {
			return new WP_Error( 'fop_owner_only', __( 'Only owners can RSVP.', 'fan-ownership' ) );
		}
		$attendees = array_map( 'intval', array_filter( (array) get_post_meta( $meeting_id, '_fop_attendees', true ) ) );
		if ( in_array( $user_id, $attendees, true ) ) {
			$attendees = array_values( array_diff( $attendees, array( $user_id ) ) );
			$going     = false;
		} else {
			$attendees[] = $user_id;
			$going       = true;
		}
		update_post_meta( $meeting_id, '_fop_attendees', $attendees );
		fop_touch_activity( $user_id );
		return array(
			'count'     => count( $attendees ),
			'attending' => $going,
		);
	}

	/**
	 * Mark attendance when a member opens the live meeting (badge event).
	 */
	public static function mark_attended( $meeting_id, $user_id ) {
		$attended = array_map( 'intval', (array) get_post_meta( $meeting_id, '_fop_attended', true ) );
		if ( ! in_array( $user_id, $attended, true ) ) {
			$attended[] = $user_id;
			update_post_meta( $meeting_id, '_fop_attended', $attended );
			do_action( 'fop_milestone_event', 'meeting_attended', $user_id );
		}
	}

	/**
	 * Upcoming published meetings, soonest first.
	 */
	public static function upcoming( $limit = 10 ) {
		return get_posts(
			array(
				'post_type'      => 'fop_meeting',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_key'       => '_fop_meeting_start',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => '_fop_meeting_start',
						'value'   => fop_now(),
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				),
				'no_found_rows'  => true,
			)
		);
	}

	/**
	 * Members-only ICS feed (FO-213 AC2): meetings + ballot deadlines.
	 * Feed URL carries a per-member token, so calendar apps can subscribe
	 * without a session.
	 */
	public static function ics_endpoint() {
		add_rewrite_rule( '^owners-calendar/([a-f0-9]{32})\.ics$', 'index.php?fop_ics_token=$matches[1]', 'top' );
		add_rewrite_tag( '%fop_ics_token%', '([a-f0-9]{32})' );
	}

	public static function member_ics_url( $user_id ) {
		$token = get_user_meta( $user_id, 'fop_ics_token', true );
		if ( ! $token ) {
			$token = md5( wp_generate_password( 32, false ) );
			update_user_meta( $user_id, 'fop_ics_token', $token );
		}
		return home_url( '/owners-calendar/' . $token . '.ics' );
	}

	public static function maybe_serve_ics() {
		$token = get_query_var( 'fop_ics_token' );
		if ( ! $token ) {
			return;
		}
		$users = get_users(
			array(
				'meta_key'   => 'fop_ics_token',
				'meta_value' => $token,
				'fields'     => 'ID',
				'number'     => 1,
			)
		);
		if ( ! $users || ! fop_is_owner( $users[0] ) ) {
			wp_die( esc_html__( 'Calendar link not recognised.', 'fan-ownership' ), 404 );
		}
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: inline; filename=owners-calendar.ics' );
		echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//" . esc_html( fop_club_name() ) . "//Owners//EN\r\n";
		foreach ( self::upcoming( 50 ) as $meeting ) {
			$start = strtotime( get_post_meta( $meeting->ID, '_fop_meeting_start', true ) );
			$end   = $start + HOUR_IN_SECONDS;
			echo "BEGIN:VEVENT\r\nUID:fop-meeting-" . (int) $meeting->ID . '@' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . "\r\n";
			echo 'DTSTART:' . esc_html( gmdate( 'Ymd\THis\Z', $start ) ) . "\r\n";
			echo 'DTEND:' . esc_html( gmdate( 'Ymd\THis\Z', $end ) ) . "\r\n";
			echo 'SUMMARY:' . esc_html( $meeting->post_title ) . "\r\n";
			echo 'URL:' . esc_html( get_permalink( $meeting ) ) . "\r\nEND:VEVENT\r\n";
		}
		foreach ( FOP_Ballots::open_ballots() as $ballot ) {
			$closes = strtotime( get_post_meta( $ballot->ID, '_fop_closes', true ) );
			if ( ! $closes ) {
				continue;
			}
			echo "BEGIN:VEVENT\r\nUID:fop-ballot-" . (int) $ballot->ID . '@' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . "\r\n";
			echo 'DTSTART:' . esc_html( gmdate( 'Ymd\THis\Z', $closes ) ) . "\r\n";
			echo 'DTEND:' . esc_html( gmdate( 'Ymd\THis\Z', $closes ) ) . "\r\n";
			echo 'SUMMARY:' . esc_html( sprintf( /* translators: %s ballot. */ __( 'Voting closes: %s', 'fan-ownership' ), $ballot->post_title ) ) . "\r\n";
			echo 'URL:' . esc_html( get_permalink( $ballot ) ) . "\r\nEND:VEVENT\r\n";
		}
		echo "END:VCALENDAR\r\n";
		exit;
	}

	/**
	 * Reminders ahead of start to RSVP'd owners (FO-213 AC3).
	 */
	public static function send_reminders() {
		foreach ( self::upcoming( 20 ) as $meeting ) {
			$start = strtotime( get_post_meta( $meeting->ID, '_fop_meeting_start', true ) );
			if ( $start - time() > 2 * HOUR_IN_SECONDS || get_post_meta( $meeting->ID, '_fop_reminded', true ) ) {
				continue;
			}
			update_post_meta( $meeting->ID, '_fop_reminded', 1 );
			$attendees = array_map( 'intval', (array) get_post_meta( $meeting->ID, '_fop_attendees', true ) );
			foreach ( $attendees as $uid ) {
				$user = get_userdata( $uid );
				if ( $user && FOP_Comms::user_wants( $uid, 'meetings', 'email' ) ) {
					FOP_Comms::send( $user->user_email, sprintf( /* translators: %s title. */ __( 'Starting soon: %s', 'fan-ownership' ), $meeting->post_title ), get_permalink( $meeting ), 'meetings' );
				}
			}
			FOP_Comms::push( $attendees, __( 'Meeting starting soon', 'fan-ownership' ), $meeting->post_title, 'meetings', get_permalink( $meeting ) );
		}
	}

	public static function meta_box() {
		add_meta_box(
			'fop_meeting_details',
			__( 'Meeting Details', 'fan-ownership' ),
			function ( $post ) {
				wp_nonce_field( 'fop_meeting_meta', 'fop_meeting_nonce' );
				$start  = get_post_meta( $post->ID, '_fop_meeting_start', true );
				$embed  = get_post_meta( $post->ID, '_fop_stream_embed', true );
				$is_agm = get_post_meta( $post->ID, '_fop_is_agm', true );
				echo '<p><label>' . esc_html__( 'Starts', 'fan-ownership' ) . '</label> <input type="datetime-local" name="fop_meeting_start" value="' . esc_attr( $start ? gmdate( 'Y-m-d\TH:i', strtotime( $start ) ) : '' ) . '"></p>';
				echo '<p><label>' . esc_html__( 'Live stream embed URL (StreamYard destination)', 'fan-ownership' ) . '</label> <input type="url" class="widefat" name="fop_stream_embed" value="' . esc_attr( $embed ) . '"></p>';
				echo '<p><label><input type="checkbox" name="fop_is_agm" ' . checked( $is_agm, '1', false ) . '> ' . esc_html__( 'This is the AGM (statutory resolutions run as ballots in the AGM window)', 'fan-ownership' ) . '</label></p>';
				echo '<p><label>' . esc_html__( 'Recording URL (available within 24h of the meeting)', 'fan-ownership' ) . '</label> <input type="url" class="widefat" name="fop_recording" value="' . esc_attr( get_post_meta( $post->ID, '_fop_recording', true ) ) . '"></p>';
				echo '<p><label>' . esc_html__( 'Action minutes', 'fan-ownership' ) . '</label><textarea class="widefat" rows="4" name="fop_minutes">' . esc_textarea( get_post_meta( $post->ID, '_fop_minutes', true ) ) . '</textarea></p>';
				echo '<p>' . esc_html( sprintf( /* translators: %d count. */ __( 'RSVPs: %d', 'fan-ownership' ), count( (array) get_post_meta( $post->ID, '_fop_attendees', true ) ) ) ) . '</p>';
			},
			'fop_meeting',
			'normal',
			'high'
		);
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['fop_meeting_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fop_meeting_nonce'] ), 'fop_meeting_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'fop_governance' ) && ! current_user_can( 'fop_admin' ) ) {
			return;
		}
		$start = isset( $_POST['fop_meeting_start'] ) ? sanitize_text_field( wp_unslash( $_POST['fop_meeting_start'] ) ) : '';
		update_post_meta( $post_id, '_fop_meeting_start', $start ? gmdate( 'Y-m-d H:i:s', strtotime( $start ) ) : '' );
		update_post_meta( $post_id, '_fop_stream_embed', isset( $_POST['fop_stream_embed'] ) ? esc_url_raw( wp_unslash( $_POST['fop_stream_embed'] ) ) : '' );
		update_post_meta( $post_id, '_fop_is_agm', isset( $_POST['fop_is_agm'] ) ? '1' : '' );
		$had_recording = get_post_meta( $post_id, '_fop_recording', true );
		update_post_meta( $post_id, '_fop_recording', isset( $_POST['fop_recording'] ) ? esc_url_raw( wp_unslash( $_POST['fop_recording'] ) ) : '' );
		update_post_meta( $post_id, '_fop_minutes', isset( $_POST['fop_minutes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fop_minutes'] ) ) : '' );
		// FO-215 AC2: decisions in minutes feed the register via the admin
		// "create decision" flow on the decision register screen.
		if ( ! $had_recording && get_post_meta( $post_id, '_fop_recording', true ) ) {
			FOP_Audit::log( 'meeting_recording', sprintf( 'Recording published for meeting %d', $post_id ) );
		}
	}
}
