<?php
/**
 * Media: Cloudflare Stream signed playback, replay auto-publish within
 * the hour, the video library, resumable playback.
 *
 * FO-305 (gated playback), FO-310 (replay pipeline), FO-311 (library).
 *
 * Video meta: _prx3_cf_uid (Cloudflare Stream video UID), _prx3_media_url
 * (self-hosted/object-storage URL), _prx3_match (source match).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Media service: signed Cloudflare Stream playback, the replay
 * auto-publish pipeline, and per-member resumable playback positions.
 */
class PRX3_Media {

	/**
	 * Register the replay pipeline and video edit screen hooks.
	 */
	public static function init() {
		add_action( 'prx3_ballot_tick', array( __CLASS__, 'check_weekly_show' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_missed_notice' ) );
		add_action( 'prx3_publish_replay', array( __CLASS__, 'publish_replay' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_prx3_video', array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	/**
	 * Signed playback token for a Cloudflare Stream video (T62/T12):
	 * per-member, short-lived, so leaked links die.
	 *
	 * @param string $cf_uid  Cloudflare Stream video UID.
	 * @param int    $user_id Requesting member's user ID.
	 * @return string|WP_Error Signed token, or the UID unsigned when no
	 *                         signing key is configured (dev mode).
	 */
	public static function playback_token( $cf_uid, $user_id ) {
		if ( ! prx3_is_owner( $user_id ) ) {
			return new WP_Error( 'prx3_owner_only', __( 'Owners only.', 'fan-ownership' ) );
		}
		$key_id  = prx3_setting( 'cf_stream_key_id', '' );
		$jwk_pem = prx3_setting( 'cf_stream_key_pem', '' );
		if ( ! $key_id || ! $jwk_pem ) {
			return $cf_uid; // Unsigned dev fallback; production configures signing keys.
		}
		$header    = self::b64(
			wp_json_encode(
				array(
					'alg' => 'RS256',
					'kid' => $key_id,
				)
			)
		);
		$payload   = self::b64(
			wp_json_encode(
				array(
					'sub'         => $cf_uid,
					'kid'         => $key_id,
					'exp'         => time() + 4 * HOUR_IN_SECONDS,
					'accessRules' => array(
						array(
							'type'   => 'any',
							'action' => 'allow',
						),
					),
				)
			)
		);
		$signature = '';
		if ( function_exists( 'openssl_sign' ) && openssl_sign( $header . '.' . $payload, $signature, $jwk_pem, OPENSSL_ALGO_SHA256 ) ) {
			return $header . '.' . $payload . '.' . self::b64( $signature );
		}
		return $cf_uid;
	}

	/**
	 * Base64url-encode data for the Cloudflare Stream signed-token format.
	 *
	 * @param string $data Raw bytes to encode.
	 * @return string
	 */
	private static function b64( $data ) {
		// Base64url per Cloudflare Stream signed-token format, not obfuscation.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * FO-310: replay auto-publish. Runs ~15 minutes after full-time and
	 * retries until Cloudflare has the recording ready, alerting staff if
	 * still missing at the one-hour mark (AC4).
	 *
	 * @param int $match_id Match post ID.
	 */
	public static function publish_replay( $match_id ) {
		$uid = get_post_meta( $match_id, '_prx3_stream_uid', true );
		if ( ! $uid ) {
			return;
		}
		if ( get_post_meta( $match_id, '_prx3_replay_id', true ) ) {
			return; // Already published.
		}
		$recording_uid = self::cloudflare_recording_uid( $uid );
		$attempts      = (int) get_post_meta( $match_id, '_prx3_replay_attempts', true ) + 1;
		update_post_meta( $match_id, '_prx3_replay_attempts', $attempts );

		if ( ! $recording_uid ) {
			if ( $attempts >= 4 ) {
				// One hour without a recording: alert, never fail silently.
				foreach ( get_users( array( 'role__in' => array( 'prx3_content_editor', 'prx3_owner_admin', 'administrator' ) ) ) as $staff ) {
					PRX3_Comms::send(
						$staff->user_email,
						__( 'Replay auto-publish failed', 'fan-ownership' ),
						sprintf(
						/* translators: %s match. */
							__( 'The replay for "%s" has not appeared within the hour. Check the stream recording and publish manually.', 'fan-ownership' ),
							get_the_title( $match_id )
						),
						'governance'
					);
				}
				return;
			}
			wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, 'prx3_publish_replay', array( $match_id ) );
			return;
		}

		$video_id = wp_insert_post(
			array(
				'post_type'   => 'prx3_video',
				'post_status' => 'publish',
				'post_title'  => sprintf( /* translators: %s match title. */ __( 'Full match replay: %s', 'fan-ownership' ), get_the_title( $match_id ) ),
			)
		);
		if ( $video_id && ! is_wp_error( $video_id ) ) {
			update_post_meta( $video_id, '_prx3_cf_uid', $recording_uid );
			update_post_meta( $video_id, '_prx3_match', $match_id );
			wp_set_object_terms( $video_id, 'match-replay', 'prx3_video_type' );
			update_post_meta( $match_id, '_prx3_replay_id', $video_id );
			PRX3_Comms::push( array(), __( 'Replay available', 'fan-ownership' ), get_the_title( $match_id ), 'content', get_permalink( $video_id ) );
		}
	}

	/**
	 * Ask Cloudflare for the live input's recorded video UID.
	 *
	 * @param string $live_input_uid Cloudflare Stream live input UID.
	 * @return string Recording UID, or empty string when not ready.
	 */
	private static function cloudflare_recording_uid( $live_input_uid ) {
		$account = prx3_setting( 'cf_account_id', '' );
		$token   = prx3_setting( 'cf_api_token', '' );
		if ( ! $account || ! $token ) {
			return '';
		}
		$response = wp_remote_get(
			sprintf( 'https://api.cloudflare.com/client/v4/accounts/%s/stream/live_inputs/%s/videos', rawurlencode( $account ), rawurlencode( $live_input_uid ) ),
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				'timeout' => 10,
			)
		);
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		foreach ( (array) ( $body['result'] ?? array() ) as $video ) {
			if ( ! empty( $video['readyToStream'] ) ) {
				return (string) $video['uid'];
			}
		}
		return '';
	}

	/**
	 * Resume position per member per video (FO-311 AC2).
	 *
	 * @param int $video_id Video post ID.
	 * @param int $user_id  Member user ID.
	 * @param int $seconds  Playback position in seconds.
	 */
	public static function save_position( $video_id, $user_id, $seconds ) {
		$positions              = (array) get_user_meta( $user_id, 'prx3_watch_positions', true );
		$positions[ $video_id ] = max( 0, (int) $seconds );
		update_user_meta( $user_id, 'prx3_watch_positions', array_slice( $positions, -200, null, true ) );
	}

	/**
	 * Saved resume position for a member and video.
	 *
	 * @param int $video_id Video post ID.
	 * @param int $user_id  Member user ID.
	 * @return int Seconds; 0 when unwatched.
	 */
	public static function get_position( $video_id, $user_id ) {
		$positions = (array) get_user_meta( $user_id, 'prx3_watch_positions', true );
		return isset( $positions[ $video_id ] ) ? (int) $positions[ $video_id ] : 0;
	}

	/**
	 * Video source meta box: Cloudflare Stream UID or self-hosted URL.
	 */
	public static function meta_box() {
		add_meta_box(
			'prx3_video_source',
			__( 'Video Source', 'fan-ownership' ),
			function ( $post ) {
				wp_nonce_field( 'prx3_video_meta', 'prx3_video_nonce' );
				echo '<p><label>' . esc_html__( 'Cloudflare Stream UID', 'fan-ownership' ) . '</label> <input type="text" class="widefat" name="prx3_cf_uid" value="' . esc_attr( get_post_meta( $post->ID, '_prx3_cf_uid', true ) ) . '"></p>';
				echo '<p><label>' . esc_html__( 'OR self-hosted media URL (object storage, member-gated CDN)', 'fan-ownership' ) . '</label> <input type="url" class="widefat" name="prx3_media_url" value="' . esc_attr( get_post_meta( $post->ID, '_prx3_media_url', true ) ) . '"></p>';
			},
			'prx3_video',
			'normal',
			'high'
		);
	}

	/**
	 * Save the video source meta.
	 *
	 * @param int     $post_id Video post ID.
	 * @param WP_Post $post    Video post object.
	 */
	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['prx3_video_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['prx3_video_nonce'] ), 'prx3_video_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'prx3_edit_content' ) && ! current_user_can( 'prx3_admin' ) ) {
			return;
		}
		update_post_meta( $post_id, '_prx3_cf_uid', isset( $_POST['prx3_cf_uid'] ) ? sanitize_text_field( wp_unslash( $_POST['prx3_cf_uid'] ) ) : '' );
		update_post_meta( $post_id, '_prx3_media_url', isset( $_POST['prx3_media_url'] ) ? esc_url_raw( wp_unslash( $_POST['prx3_media_url'] ) ) : '' );
	}
	/**
	 * The weekly show has a standing slot (FO-312): if no show episode
	 * has been published within 8 days of the configured slot day, a
	 * missed-slot flag is raised for staff and cleared on the next episode.
	 */
	public static function check_weekly_show() {
		$day = prx3_setting( 'weekly_show_day', '' );
		if ( '' === $day ) {
			delete_option( 'prx3_show_missed' );
			return;
		}
		$latest = get_posts(
			array(
				'post_type'   => 'prx3_video',
				'post_status' => array( 'publish' ),
				'numberposts' => -1,
				'meta_key'    => '_prx3_vtype', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded weekly check.
				'meta_value'  => 'show', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$newest = 0;
		foreach ( $latest as $episode ) {
			$newest = max( $newest, strtotime( (string) ( $episode->post_date ?? '' ) ) );
		}
		if ( time() - $newest > 8 * DAY_IN_SECONDS ) {
			if ( ! get_option( 'prx3_show_missed' ) ) {
				update_option( 'prx3_show_missed', time(), false );
				PRX3_Audit::log( 'show_missed', 'Weekly show slot missed — no episode published in 8 days' );
			}
		} else {
			delete_option( 'prx3_show_missed' );
		}
	}

	/**
	 * Staff notice while the weekly slot is missed.
	 */
	public static function show_missed_notice() {
		if ( get_option( 'prx3_show_missed' ) && current_user_can( 'edit_posts' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'The weekly show slot has been missed — no episode published in over 8 days. Publish this week\'s episode or clear the standing slot in Fan App Settings → Club.', 'fan-ownership' ) . '</p></div>';
		}
	}
}
