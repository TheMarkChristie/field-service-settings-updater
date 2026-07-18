<?php
/**
 * Match Centre: fixtures, live state, minute-by-minute events from the
 * volunteer reporter console (idempotent, retry-safe), corrections.
 *
 * FO-305 (page states), FO-307 (console + retry queue), FO-308 (away
 * audio alongside the timeline), FO-309 (advert slots).
 *
 * Match meta: _fop_kickoff, _fop_venue ('home'|'away'), _fop_opponent,
 * _fop_stream_uid (Cloudflare Stream live input), _fop_audio_url,
 * _fop_score (array), _fop_ad_slots (array), _fop_reporters (user ids).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Match_Centre {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_fop_match', array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	/**
	 * Is this user an approved reporter for the match (P72 vetting)?
	 */
	public static function is_reporter( $match_id, $user_id ) {
		if ( user_can( $user_id, 'fop_edit_content' ) || user_can( $user_id, 'fop_admin' ) ) {
			return true;
		}
		$reporters = array_map( 'intval', (array) get_post_meta( $match_id, '_fop_reporters', true ) );
		return in_array( (int) $user_id, $reporters, true );
	}

	/**
	 * Record a minute-by-minute event. client_key makes retries idempotent
	 * (FO-307 AC3): the console generates a UUID per event; a resend after
	 * a dead spot cannot duplicate.
	 *
	 * @return array|WP_Error Event row.
	 */
	public static function record_event( $match_id, $user_id, $client_key, $event_type, $minute, $detail ) {
		global $wpdb;
		if ( 'fop_match' !== get_post_type( $match_id ) ) {
			return new WP_Error( 'fop_match', __( 'Match not found.', 'fan-ownership' ) );
		}
		if ( ! self::is_reporter( $match_id, $user_id ) ) {
			return new WP_Error( 'fop_reporter', __( 'You are not an approved reporter for this match.', 'fan-ownership' ) );
		}
		$allowed = array( 'goal', 'own_goal', 'card_yellow', 'card_red', 'sub', 'kickoff', 'half_time', 'full_time', 'note' );
		if ( ! in_array( $event_type, $allowed, true ) ) {
			return new WP_Error( 'fop_event', __( 'Unknown event type.', 'fan-ownership' ) );
		}
		$client_key = substr( preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) $client_key ), 0, 64 );
		if ( ! $client_key ) {
			return new WP_Error( 'fop_key', __( 'Missing event key.', 'fan-ownership' ) );
		}
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'fop_match_events',
			array(
				'match_id'   => $match_id,
				'client_key' => $client_key,
				'reporter'   => $user_id,
				'event_type' => $event_type,
				'minute'     => null === $minute ? null : (int) $minute,
				'detail'     => wp_json_encode( $detail ),
				'created_at' => fop_now(),
			),
			array( '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
		);
		if ( false === $inserted ) {
			// Unique (match_id, client_key) hit: the retry already landed. Success, no duplicate.
			return array( 'duplicate' => true );
		}
		if ( in_array( $event_type, array( 'goal', 'own_goal' ), true ) && isset( $detail['score'] ) ) {
			update_post_meta( $match_id, '_fop_score', sanitize_text_field( (string) $detail['score'] ) );
		}
		// Push within seconds (FO-307 AC2), match category, deep link to the match.
		$titles = array(
			'goal'      => __( 'GOAL!', 'fan-ownership' ),
			'own_goal'  => __( 'Goal (OG)', 'fan-ownership' ),
			'card_red'  => __( 'Red card', 'fan-ownership' ),
			'kickoff'   => __( 'Kick-off', 'fan-ownership' ),
			'half_time' => __( 'Half-time', 'fan-ownership' ),
			'full_time' => __( 'Full-time', 'fan-ownership' ),
		);
		if ( isset( $titles[ $event_type ] ) ) {
			$body = isset( $detail['text'] ) ? (string) $detail['text'] : get_the_title( $match_id );
			FOP_Comms::push( array(), $titles[ $event_type ], $body, 'match', get_permalink( $match_id ) );
		}
		return array( 'duplicate' => false );
	}

	/**
	 * FO-307 AC4: visible corrections, never silent.
	 */
	public static function correct_event( $event_id, $staff_id, $remove ) {
		global $wpdb;
		if ( ! user_can( $staff_id, 'fop_edit_content' ) && ! user_can( $staff_id, 'fop_admin' ) ) {
			return new WP_Error( 'fop_denied', __( 'Staff only.', 'fan-ownership' ) );
		}
		$wpdb->update(
			$wpdb->prefix . 'fop_match_events',
			array(
				'corrected_by' => $staff_id,
				'removed'      => $remove ? 1 : 0,
			),
			array( 'id' => (int) $event_id ),
			array( '%d', '%d' ),
			array( '%d' )
		);
		return true;
	}

	/**
	 * The timeline (corrections shown, removed events marked).
	 */
	public static function timeline( $match_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fop_match_events WHERE match_id = %d ORDER BY id ASC", $match_id ),
			ARRAY_A
		);
		return array_map(
			function ( $row ) {
				$row['detail'] = json_decode( (string) $row['detail'], true );
				return $row;
			},
			$rows
		);
	}

	/**
	 * Match page state (FO-305 AC2): countdown / live / delayed / ended.
	 */
	public static function live_state( $match_id ) {
		$kickoff = strtotime( (string) get_post_meta( $match_id, '_fop_kickoff', true ) );
		$live    = (bool) get_post_meta( $match_id, '_fop_stream_live', true );
		$ended   = (bool) get_post_meta( $match_id, '_fop_ended', true );
		if ( $ended ) {
			return 'ended';
		}
		if ( $live ) {
			return 'live';
		}
		if ( $kickoff && time() >= $kickoff ) {
			return 'delayed';
		}
		return 'countdown';
	}

	public static function meta_box() {
		add_meta_box(
			'fop_match_details',
			__( 'Match Details', 'fan-ownership' ),
			function ( $post ) {
				wp_nonce_field( 'fop_match_meta', 'fop_match_nonce' );
				$kickoff = get_post_meta( $post->ID, '_fop_kickoff', true );
				echo '<p><label>' . esc_html__( 'Kick-off', 'fan-ownership' ) . '</label> <input type="datetime-local" name="fop_kickoff" value="' . esc_attr( $kickoff ? gmdate( 'Y-m-d\TH:i', strtotime( $kickoff ) ) : '' ) . '"></p>';
				echo '<p><label>' . esc_html__( 'Opponent', 'fan-ownership' ) . '</label> <input type="text" class="widefat" name="fop_opponent" value="' . esc_attr( get_post_meta( $post->ID, '_fop_opponent', true ) ) . '"></p>';
				echo '<p><label>' . esc_html__( 'Venue', 'fan-ownership' ) . '</label> <select name="fop_venue"><option value="home" ' . selected( get_post_meta( $post->ID, '_fop_venue', true ), 'home', false ) . '>' . esc_html__( 'Home (video stream)', 'fan-ownership' ) . '</option><option value="away" ' . selected( get_post_meta( $post->ID, '_fop_venue', true ), 'away', false ) . '>' . esc_html__( 'Away (audio commentary)', 'fan-ownership' ) . '</option></select></p>';
				echo '<p><label>' . esc_html__( 'Cloudflare Stream live input UID (home video)', 'fan-ownership' ) . '</label> <input type="text" class="widefat" name="fop_stream_uid" value="' . esc_attr( get_post_meta( $post->ID, '_fop_stream_uid', true ) ) . '"></p>';
				echo '<p><label>' . esc_html__( 'Audio stream URL (away commentary)', 'fan-ownership' ) . '</label> <input type="url" class="widefat" name="fop_audio_url" value="' . esc_attr( get_post_meta( $post->ID, '_fop_audio_url', true ) ) . '"></p>';
				echo '<p><label><input type="checkbox" name="fop_stream_live" ' . checked( get_post_meta( $post->ID, '_fop_stream_live', true ), '1', false ) . '> ' . esc_html__( 'Stream is LIVE now', 'fan-ownership' ) . '</label> <label><input type="checkbox" name="fop_ended" ' . checked( get_post_meta( $post->ID, '_fop_ended', true ), '1', false ) . '> ' . esc_html__( 'Match ended', 'fan-ownership' ) . '</label></p>';
				echo '<p><label>' . esc_html__( 'Approved volunteer reporters (user IDs, comma-separated)', 'fan-ownership' ) . '</label> <input type="text" class="widefat" name="fop_reporters" value="' . esc_attr( implode( ',', array_map( 'intval', (array) get_post_meta( $post->ID, '_fop_reporters', true ) ) ) ) . '"></p>';
				echo '<p><label>' . esc_html__( 'Sponsor ident/advert URLs (one per line: pre-start, half-time, breaks — staff-controlled, FO-309)', 'fan-ownership' ) . '</label><textarea class="widefat" rows="3" name="fop_ad_slots">' . esc_textarea( implode( "\n", (array) get_post_meta( $post->ID, '_fop_ad_slots', true ) ) ) . '</textarea></p>';
			},
			'fop_match',
			'normal',
			'high'
		);
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['fop_match_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fop_match_nonce'] ), 'fop_match_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'fop_edit_content' ) && ! current_user_can( 'fop_admin' ) ) {
			return;
		}
		$kickoff = isset( $_POST['fop_kickoff'] ) ? sanitize_text_field( wp_unslash( $_POST['fop_kickoff'] ) ) : '';
		update_post_meta( $post_id, '_fop_kickoff', $kickoff ? gmdate( 'Y-m-d H:i:s', strtotime( $kickoff ) ) : '' );
		update_post_meta( $post_id, '_fop_opponent', isset( $_POST['fop_opponent'] ) ? sanitize_text_field( wp_unslash( $_POST['fop_opponent'] ) ) : '' );
		update_post_meta( $post_id, '_fop_venue', isset( $_POST['fop_venue'] ) && 'away' === $_POST['fop_venue'] ? 'away' : 'home' );
		update_post_meta( $post_id, '_fop_stream_uid', isset( $_POST['fop_stream_uid'] ) ? sanitize_text_field( wp_unslash( $_POST['fop_stream_uid'] ) ) : '' );
		update_post_meta( $post_id, '_fop_audio_url', isset( $_POST['fop_audio_url'] ) ? esc_url_raw( wp_unslash( $_POST['fop_audio_url'] ) ) : '' );
		$was_ended = (bool) get_post_meta( $post_id, '_fop_ended', true );
		update_post_meta( $post_id, '_fop_stream_live', isset( $_POST['fop_stream_live'] ) ? '1' : '' );
		update_post_meta( $post_id, '_fop_ended', isset( $_POST['fop_ended'] ) ? '1' : '' );
		update_post_meta( $post_id, '_fop_reporters', array_filter( array_map( 'absint', explode( ',', isset( $_POST['fop_reporters'] ) ? sanitize_text_field( wp_unslash( $_POST['fop_reporters'] ) ) : '' ) ) ) );
		update_post_meta( $post_id, '_fop_ad_slots', array_filter( array_map( 'esc_url_raw', array_map( 'trim', explode( "\n", isset( $_POST['fop_ad_slots'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fop_ad_slots'] ) ) : '' ) ) ) ) );
		// Replay pipeline: match just ended -> schedule the auto-publish check (FO-310).
		if ( ! $was_ended && isset( $_POST['fop_ended'] ) ) {
			wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, 'fop_publish_replay', array( $post_id ) );
		}
	}
}
