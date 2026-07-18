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

/**
 * Member meetings: RSVPs feeding attendance badges, the tokenised
 * owners calendar (ICS) feed, start-time reminders, and meeting meta
 * (stream embed, recording, minutes, AGM flag).
 */
class PRX3_Meetings {

	/**
	 * Wire the meeting meta box, the ICS endpoint, and the hourly
	 * reminder cron.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_prx3_meeting', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'ics_endpoint' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_ics' ) );
		add_action( 'prx3_meeting_reminders', array( __CLASS__, 'send_reminders' ) );
		if ( ! wp_next_scheduled( 'prx3_meeting_reminders' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'prx3_meeting_reminders' );
		}
	}

	/**
	 * RSVP toggle (FO-213 AC1). Attendance feeds the milestone badges.
	 *
	 * @param int $meeting_id Meeting post ID.
	 * @param int $user_id    Owner toggling their RSVP.
	 * @return array|WP_Error Attendee count and attending state, or error.
	 */
	public static function toggle_rsvp( $meeting_id, $user_id ) {
		if ( 'publish' !== get_post_status( $meeting_id ) || 'prx3_meeting' !== get_post_type( $meeting_id ) ) {
			return new WP_Error( 'prx3_meeting', __( 'This meeting is not open for RSVPs.', 'fan-ownership' ) );
		}
		if ( ! prx3_is_owner( $user_id ) ) {
			return new WP_Error( 'prx3_owner_only', __( 'Only owners can RSVP.', 'fan-ownership' ) );
		}
		$attendees = array_map( 'intval', array_filter( (array) get_post_meta( $meeting_id, '_prx3_attendees', true ) ) );
		if ( in_array( $user_id, $attendees, true ) ) {
			$attendees = array_values( array_diff( $attendees, array( $user_id ) ) );
			$going     = false;
		} else {
			$attendees[] = $user_id;
			$going       = true;
		}
		update_post_meta( $meeting_id, '_prx3_attendees', $attendees );
		prx3_touch_activity( $user_id );
		return array(
			'count'     => count( $attendees ),
			'attending' => $going,
		);
	}

	/**
	 * Mark attendance when a member opens the live meeting (badge event).
	 *
	 * @param int $meeting_id Meeting post ID.
	 * @param int $user_id    Attending member.
	 */
	public static function mark_attended( $meeting_id, $user_id ) {
		$attended = array_map( 'intval', (array) get_post_meta( $meeting_id, '_prx3_attended', true ) );
		if ( ! in_array( $user_id, $attended, true ) ) {
			$attended[] = $user_id;
			update_post_meta( $meeting_id, '_prx3_attended', $attended );
			do_action( 'prx3_milestone_event', 'meeting_attended', $user_id );
		}
	}

	/**
	 * Upcoming published meetings, soonest first.
	 *
	 * @param int $limit Maximum number of meetings to return.
	 * @return WP_Post[] Meeting posts.
	 */
	public static function upcoming( $limit = 10 ) {
		return get_posts(
			array(
				'post_type'      => 'prx3_meeting',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_key'       => '_prx3_meeting_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded lookup ordering a small meeting CPT by start.
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- filters the same small meeting CPT to future dates.
					array(
						'key'     => '_prx3_meeting_start',
						'value'   => prx3_now(),
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
		add_rewrite_rule( '^owners-calendar/([a-f0-9]{32})\.ics$', 'index.php?prx3_ics_token=$matches[1]', 'top' );
		add_rewrite_tag( '%prx3_ics_token%', '([a-f0-9]{32})' );
	}

	/**
	 * Per-member calendar feed URL, minting the token on first use.
	 *
	 * @param int $user_id Member user ID.
	 * @return string Tokenised ICS feed URL.
	 */
	public static function member_ics_url( $user_id ) {
		$token = get_user_meta( $user_id, 'prx3_ics_token', true );
		if ( ! $token ) {
			$token = md5( wp_generate_password( 32, false ) );
			update_user_meta( $user_id, 'prx3_ics_token', $token );
		}
		return home_url( '/owners-calendar/' . $token . '.ics' );
	}

	/**
	 * Serve the calendar feed when a tokenised URL is requested:
	 * upcoming meetings plus open ballot deadlines.
	 */
	public static function maybe_serve_ics() {
		$token = get_query_var( 'prx3_ics_token' );
		if ( ! $token ) {
			return;
		}
		$users = get_users(
			array(
				'meta_key'   => 'prx3_ics_token', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- exact-match token lookup, one row.
				'meta_value' => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ID',
				'number'     => 1,
			)
		);
		if ( ! $users || ! prx3_is_owner( $users[0] ) ) {
			wp_die( esc_html__( 'Calendar link not recognised.', 'fan-ownership' ), 404 );
		}
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: inline; filename=owners-calendar.ics' );
		echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//" . esc_html( prx3_club_name() ) . "//Owners//EN\r\n";
		foreach ( self::upcoming( 50 ) as $meeting ) {
			$start = strtotime( get_post_meta( $meeting->ID, '_prx3_meeting_start', true ) );
			$end   = $start + HOUR_IN_SECONDS;
			echo "BEGIN:VEVENT\r\nUID:prx3-meeting-" . (int) $meeting->ID . '@' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . "\r\n";
			echo 'DTSTART:' . esc_html( gmdate( 'Ymd\THis\Z', $start ) ) . "\r\n";
			echo 'DTEND:' . esc_html( gmdate( 'Ymd\THis\Z', $end ) ) . "\r\n";
			echo 'SUMMARY:' . esc_html( $meeting->post_title ) . "\r\n";
			echo 'URL:' . esc_html( get_permalink( $meeting ) ) . "\r\nEND:VEVENT\r\n";
		}
		foreach ( PRX3_Ballots::open_ballots() as $ballot ) {
			$closes = strtotime( get_post_meta( $ballot->ID, '_prx3_closes', true ) );
			if ( ! $closes ) {
				continue;
			}
			echo "BEGIN:VEVENT\r\nUID:prx3-ballot-" . (int) $ballot->ID . '@' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . "\r\n";
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
			$start = strtotime( get_post_meta( $meeting->ID, '_prx3_meeting_start', true ) );
			if ( $start - time() > 2 * HOUR_IN_SECONDS || get_post_meta( $meeting->ID, '_prx3_reminded', true ) ) {
				continue;
			}
			update_post_meta( $meeting->ID, '_prx3_reminded', 1 );
			$attendees = array_map( 'intval', (array) get_post_meta( $meeting->ID, '_prx3_attendees', true ) );
			foreach ( $attendees as $uid ) {
				$user = get_userdata( $uid );
				if ( $user && PRX3_Comms::user_wants( $uid, 'meetings', 'email' ) ) {
					PRX3_Comms::send( $user->user_email, sprintf( /* translators: %s title. */ __( 'Starting soon: %s', 'fan-ownership' ), $meeting->post_title ), get_permalink( $meeting ), 'meetings' );
				}
			}
			PRX3_Comms::push( $attendees, __( 'Meeting starting soon', 'fan-ownership' ), $meeting->post_title, 'meetings', get_permalink( $meeting ) );
		}
	}

	/**
	 * Meeting details meta box: start time, stream embed, AGM flag,
	 * recording, and action minutes.
	 */
	public static function meta_box() {
		add_meta_box(
			'prx3_meeting_details',
			__( 'Meeting Details', 'fan-ownership' ),
			function ( $post ) {
				wp_nonce_field( 'prx3_meeting_meta', 'prx3_meeting_nonce' );
				$start  = get_post_meta( $post->ID, '_prx3_meeting_start', true );
				$embed  = get_post_meta( $post->ID, '_prx3_stream_embed', true );
				$is_agm = get_post_meta( $post->ID, '_prx3_is_agm', true );
				echo '<p><label>' . esc_html__( 'Starts', 'fan-ownership' ) . '</label> <input type="datetime-local" name="prx3_meeting_start" value="' . esc_attr( $start ? gmdate( 'Y-m-d\TH:i', strtotime( $start ) ) : '' ) . '"></p>';
				echo '<p><label>' . esc_html__( 'Live stream embed URL (StreamYard destination)', 'fan-ownership' ) . '</label> <input type="url" class="widefat" name="prx3_stream_embed" value="' . esc_attr( $embed ) . '"></p>';
				echo '<p><label><input type="checkbox" name="prx3_is_agm" ' . checked( $is_agm, '1', false ) . '> ' . esc_html__( 'This is the AGM (statutory resolutions run as ballots in the AGM window)', 'fan-ownership' ) . '</label></p>';
				echo '<p><label>' . esc_html__( 'Recording URL (available within 24h of the meeting)', 'fan-ownership' ) . '</label> <input type="url" class="widefat" name="prx3_recording" value="' . esc_attr( get_post_meta( $post->ID, '_prx3_recording', true ) ) . '"></p>';
				echo '<p><label>' . esc_html__( 'Action minutes', 'fan-ownership' ) . '</label><textarea class="widefat" rows="4" name="prx3_minutes">' . esc_textarea( get_post_meta( $post->ID, '_prx3_minutes', true ) ) . '</textarea></p>';
				echo '<p>' . esc_html( sprintf( /* translators: %d count. */ __( 'RSVPs: %d', 'fan-ownership' ), count( (array) get_post_meta( $post->ID, '_prx3_attendees', true ) ) ) ) . '</p>';
			},
			'prx3_meeting',
			'normal',
			'high'
		);
	}

	/**
	 * Save meeting meta from the meta box; logs when a recording is
	 * first published.
	 *
	 * @param int     $post_id Meeting post ID.
	 * @param WP_Post $post    Meeting post object.
	 */
	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['prx3_meeting_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['prx3_meeting_nonce'] ), 'prx3_meeting_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'prx3_governance' ) && ! current_user_can( 'prx3_admin' ) ) {
			return;
		}
		$start = isset( $_POST['prx3_meeting_start'] ) ? sanitize_text_field( wp_unslash( $_POST['prx3_meeting_start'] ) ) : '';
		update_post_meta( $post_id, '_prx3_meeting_start', $start ? gmdate( 'Y-m-d H:i:s', strtotime( $start ) ) : '' );
		update_post_meta( $post_id, '_prx3_stream_embed', isset( $_POST['prx3_stream_embed'] ) ? esc_url_raw( wp_unslash( $_POST['prx3_stream_embed'] ) ) : '' );
		update_post_meta( $post_id, '_prx3_is_agm', isset( $_POST['prx3_is_agm'] ) ? '1' : '' );
		$had_recording = get_post_meta( $post_id, '_prx3_recording', true );
		update_post_meta( $post_id, '_prx3_recording', isset( $_POST['prx3_recording'] ) ? esc_url_raw( wp_unslash( $_POST['prx3_recording'] ) ) : '' );
		update_post_meta( $post_id, '_prx3_minutes', isset( $_POST['prx3_minutes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prx3_minutes'] ) ) : '' );
		// FO-215 AC2: decisions in minutes feed the register via the admin
		// "create decision" flow on the decision register screen.
		if ( ! $had_recording && get_post_meta( $post_id, '_prx3_recording', true ) ) {
			PRX3_Audit::log( 'meeting_recording', sprintf( 'Recording published for meeting %d', $post_id ) );
		}
	}
}
