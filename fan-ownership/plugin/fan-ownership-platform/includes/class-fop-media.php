<?php
/**
 * Media: Cloudflare Stream signed playback, replay auto-publish within
 * the hour, the video library, resumable playback.
 *
 * FO-305 (gated playback), FO-310 (replay pipeline), FO-311 (library).
 *
 * Video meta: _fop_cf_uid (Cloudflare Stream video UID), _fop_media_url
 * (self-hosted/object-storage URL), _fop_match (source match).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Media {

	public static function init() {
		add_action( 'fop_publish_replay', array( __CLASS__, 'publish_replay' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_fop_video', array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	/**
	 * Signed playback token for a Cloudflare Stream video (T62/T12):
	 * per-member, short-lived, so leaked links die.
	 *
	 * @return string|WP_Error Signed token, or the UID unsigned when no
	 *                         signing key is configured (dev mode).
	 */
	public static function playback_token( $cf_uid, $user_id ) {
		if ( ! fop_is_owner( $user_id ) ) {
			return new WP_Error( 'fop_owner_only', __( 'Owners only.', 'fan-ownership' ) );
		}
		$key_id  = fop_setting( 'cf_stream_key_id', '' );
		$jwk_pem = fop_setting( 'cf_stream_key_pem', '' );
		if ( ! $key_id || ! $jwk_pem ) {
			return $cf_uid; // Unsigned dev fallback; production configures signing keys.
		}
		$header  = self::b64( wp_json_encode( array( 'alg' => 'RS256', 'kid' => $key_id ) ) );
		$payload = self::b64( wp_json_encode( array(
			'sub'      => $cf_uid,
			'kid'      => $key_id,
			'exp'      => time() + 4 * HOUR_IN_SECONDS,
			'accessRules' => array( array( 'type' => 'any', 'action' => 'allow' ) ),
		) ) );
		$signature = '';
		if ( function_exists( 'openssl_sign' ) && openssl_sign( $header . '.' . $payload, $signature, $jwk_pem, OPENSSL_ALGO_SHA256 ) ) {
			return $header . '.' . $payload . '.' . self::b64( $signature );
		}
		return $cf_uid;
	}

	private static function b64( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * FO-310: replay auto-publish. Runs ~15 minutes after full-time and
	 * retries until Cloudflare has the recording ready, alerting staff if
	 * still missing at the one-hour mark (AC4).
	 */
	public static function publish_replay( $match_id ) {
		$uid = get_post_meta( $match_id, '_fop_stream_uid', true );
		if ( ! $uid ) {
			return;
		}
		if ( get_post_meta( $match_id, '_fop_replay_id', true ) ) {
			return; // Already published.
		}
		$recording_uid = self::cloudflare_recording_uid( $uid );
		$attempts      = (int) get_post_meta( $match_id, '_fop_replay_attempts', true ) + 1;
		update_post_meta( $match_id, '_fop_replay_attempts', $attempts );

		if ( ! $recording_uid ) {
			if ( $attempts >= 4 ) {
				// One hour without a recording: alert, never fail silently.
				foreach ( get_users( array( 'role__in' => array( 'fop_content_editor', 'fop_owner_admin', 'administrator' ) ) ) as $staff ) {
					FOP_Comms::send( $staff->user_email, __( 'Replay auto-publish failed', 'fan-ownership' ), sprintf(
						/* translators: %s match. */
						__( 'The replay for "%s" has not appeared within the hour. Check the stream recording and publish manually.', 'fan-ownership' ),
						get_the_title( $match_id )
					), 'governance' );
				}
				return;
			}
			wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, 'fop_publish_replay', array( $match_id ) );
			return;
		}

		$video_id = wp_insert_post( array(
			'post_type'   => 'fop_video',
			'post_status' => 'publish',
			'post_title'  => sprintf( /* translators: %s match title. */ __( 'Full match replay: %s', 'fan-ownership' ), get_the_title( $match_id ) ),
		) );
		if ( $video_id && ! is_wp_error( $video_id ) ) {
			update_post_meta( $video_id, '_fop_cf_uid', $recording_uid );
			update_post_meta( $video_id, '_fop_match', $match_id );
			wp_set_object_terms( $video_id, 'match-replay', 'fop_video_type' );
			update_post_meta( $match_id, '_fop_replay_id', $video_id );
			FOP_Comms::push( array(), __( 'Replay available', 'fan-ownership' ), get_the_title( $match_id ), 'content', get_permalink( $video_id ) );
		}
	}

	/**
	 * Ask Cloudflare for the live input's recorded video UID.
	 */
	private static function cloudflare_recording_uid( $live_input_uid ) {
		$account = fop_setting( 'cf_account_id', '' );
		$token   = fop_setting( 'cf_api_token', '' );
		if ( ! $account || ! $token ) {
			return '';
		}
		$response = wp_remote_get(
			sprintf( 'https://api.cloudflare.com/client/v4/accounts/%s/stream/live_inputs/%s/videos', rawurlencode( $account ), rawurlencode( $live_input_uid ) ),
			array( 'headers' => array( 'Authorization' => 'Bearer ' . $token ), 'timeout' => 10 )
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
	 */
	public static function save_position( $video_id, $user_id, $seconds ) {
		$positions              = (array) get_user_meta( $user_id, 'fop_watch_positions', true );
		$positions[ $video_id ] = max( 0, (int) $seconds );
		update_user_meta( $user_id, 'fop_watch_positions', array_slice( $positions, -200, null, true ) );
	}

	public static function get_position( $video_id, $user_id ) {
		$positions = (array) get_user_meta( $user_id, 'fop_watch_positions', true );
		return isset( $positions[ $video_id ] ) ? (int) $positions[ $video_id ] : 0;
	}

	public static function meta_box() {
		add_meta_box( 'fop_video_source', __( 'Video Source', 'fan-ownership' ), function ( $post ) {
			wp_nonce_field( 'fop_video_meta', 'fop_video_nonce' );
			echo '<p><label>' . esc_html__( 'Cloudflare Stream UID', 'fan-ownership' ) . '</label> <input type="text" class="widefat" name="fop_cf_uid" value="' . esc_attr( get_post_meta( $post->ID, '_fop_cf_uid', true ) ) . '"></p>';
			echo '<p><label>' . esc_html__( 'OR self-hosted media URL (object storage, member-gated CDN)', 'fan-ownership' ) . '</label> <input type="url" class="widefat" name="fop_media_url" value="' . esc_attr( get_post_meta( $post->ID, '_fop_media_url', true ) ) . '"></p>';
		}, 'fop_video', 'normal', 'high' );
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['fop_video_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fop_video_nonce'] ), 'fop_video_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'fop_edit_content' ) && ! current_user_can( 'fop_admin' ) ) {
			return;
		}
		update_post_meta( $post_id, '_fop_cf_uid', isset( $_POST['fop_cf_uid'] ) ? sanitize_text_field( wp_unslash( $_POST['fop_cf_uid'] ) ) : '' );
		update_post_meta( $post_id, '_fop_media_url', isset( $_POST['fop_media_url'] ) ? esc_url_raw( wp_unslash( $_POST['fop_media_url'] ) ) : '' );
	}
}
