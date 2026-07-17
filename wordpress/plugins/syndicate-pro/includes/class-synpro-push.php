<?php
/**
 * Push notifications for the mobile app (Firebase Cloud Messaging).
 *
 * When new content is published, the plugin sends an FCM notification to
 * the app's topics: the general `new_content` topic (everyone) plus the
 * post's category topics (`cat_<id>`), so members who follow specific
 * categories are notified even if they muted everything else.
 *
 * Uses the FCM HTTP v1 API (the legacy server-key API is shut down):
 * a service-account JSON is exchanged for a short-lived OAuth2 token via
 * a signed JWT, then messages are posted to the project's send endpoint.
 * The service-account JSON is a secret — stored with autoload off and
 * never echoed back to the browser.
 *
 * @package Syndicate_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Synpro_Push' ) ) :

class Synpro_Push {

	const OPTION       = 'synpro_push_settings';
	const TOKEN_CACHE  = 'synpro_push_token';
	const PUSHABLE     = array( 'post', 'synpro_event', 'synpro_podcast', 'synpro_video' );

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 20, 3 );
		add_action( 'admin_post_synpro_push_test', array( __CLASS__, 'handle_test' ) );
	}

	/**
	 * Current settings, with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'enabled'         => 0,
				'project_id'      => '',
				'service_account' => '', // raw service-account JSON
			)
		);
	}

	/**
	 * Whether push is configured and enabled.
	 *
	 * @return bool
	 */
	public static function is_ready() {
		$s = self::settings();
		return ! empty( $s['enabled'] ) && ! empty( $s['project_id'] ) && ! empty( $s['service_account'] );
	}

	/**
	 * Notify the app when new content is published (mirrors the social
	 * backfill guard so historic imports never trigger a notification).
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function on_publish( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( ! in_array( $post->post_type, self::PUSHABLE, true ) || ! self::is_ready() ) {
			return;
		}
		if ( get_post_meta( $post->ID, '_synpro_pushed', true ) ) {
			return; // Already notified once.
		}

		// Backfill guard: a feed-imported post whose feed hasn't finished its
		// historic run yet must not notify.
		if ( class_exists( 'Synpro_Feeds' ) ) {
			$feed_id = (int) get_post_meta( $post->ID, '_synpro_feed_id', true );
			if ( $feed_id ) {
				$feed_row = Synpro_Feeds::get( $feed_id );
				if ( $feed_row && ! (int) $feed_row->backfilled ) {
					return;
				}
			}
		}

		// Freshness guard: imports keep their original (often old) publish
		// date; never notify for anything more than two days old.
		$age = time() - (int) get_post_time( 'U', true, $post );
		if ( $age > 2 * DAY_IN_SECONDS ) {
			update_post_meta( $post->ID, '_synpro_pushed', 1 );
			return;
		}

		update_post_meta( $post->ID, '_synpro_pushed', 1 );
		self::notify_post( $post );
	}

	/**
	 * Build and send the notification for a post.
	 *
	 * @param WP_Post $post Post.
	 * @return true|WP_Error
	 */
	public static function notify_post( $post ) {
		$labels = array(
			'post'           => __( 'New post', 'syndicate-pro' ),
			'synpro_event'   => __( 'New event', 'syndicate-pro' ),
			'synpro_podcast' => __( 'New episode', 'syndicate-pro' ),
			'synpro_video'   => __( 'New video', 'syndicate-pro' ),
		);
		$title = isset( $labels[ $post->post_type ] ) ? $labels[ $post->post_type ] : __( 'New content', 'syndicate-pro' );

		// Category topics (blogs only use the category taxonomy); cap at four
		// so with `new_content` the FCM condition stays within its 5-topic limit.
		$topics = array( 'new_content' );
		foreach ( array_slice( (array) get_the_category( $post->ID ), 0, 4 ) as $cat ) {
			$topics[] = 'cat_' . (int) $cat->term_id;
		}
		$condition = implode( ' || ', array_map( fn( $t ) => "'" . $t . "' in topics", $topics ) );

		return self::send(
			$condition,
			$title,
			wp_strip_all_tags( get_the_title( $post ) ),
			array(
				'post_id' => (string) $post->ID,
				'type'    => $post->post_type,
			)
		);
	}

	/**
	 * Send one message to a topic condition via FCM HTTP v1.
	 *
	 * @param string $condition FCM condition expression.
	 * @param string $title     Notification title.
	 * @param string $body      Notification body.
	 * @param array  $data      Data payload (string values).
	 * @return true|WP_Error
	 */
	public static function send( $condition, $title, $body, $data = array() ) {
		$settings = self::settings();
		$token    = self::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_post(
			'https://fcm.googleapis.com/v1/projects/' . rawurlencode( $settings['project_id'] ) . '/messages:send',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'message' => array(
							'condition'    => $condition,
							'notification' => array( 'title' => $title, 'body' => $body ),
							'data'         => array_map( 'strval', $data ),
						),
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'synpro_fcm_' . $code, 'FCM error ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
		}
		return true;
	}

	/**
	 * A cached OAuth2 access token for the service account (signed JWT
	 * exchanged at Google's token endpoint).
	 *
	 * @return string|WP_Error
	 */
	protected static function access_token() {
		$cached = get_transient( self::TOKEN_CACHE );
		if ( $cached ) {
			return $cached;
		}

		$settings = self::settings();
		$sa       = json_decode( (string) $settings['service_account'], true );
		if ( ! is_array( $sa ) || empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) {
			return new WP_Error( 'synpro_push_config', __( 'The Firebase service-account JSON is missing or invalid.', 'syndicate-pro' ) );
		}
		if ( ! function_exists( 'openssl_sign' ) ) {
			return new WP_Error( 'synpro_push_openssl', __( 'PHP OpenSSL is required to sign push tokens.', 'syndicate-pro' ) );
		}

		$now    = time();
		$header = self::b64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claim  = self::b64url(
			wp_json_encode(
				array(
					'iss'   => $sa['client_email'],
					'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
					'aud'   => 'https://oauth2.googleapis.com/token',
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);
		$signing_input = $header . '.' . $claim;
		$signature     = '';
		if ( ! openssl_sign( $signing_input, $signature, $sa['private_key'], 'SHA256' ) ) {
			return new WP_Error( 'synpro_push_sign', __( 'Could not sign the push token (check the private key).', 'syndicate-pro' ) );
		}
		$jwt = $signing_input . '.' . self::b64url( $signature );

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'synpro_push_token', __( 'Google did not return an access token — check the service account.', 'syndicate-pro' ) );
		}

		$ttl = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 300 );
		set_transient( self::TOKEN_CACHE, $body['access_token'], $ttl );
		return $body['access_token'];
	}

	/**
	 * URL-safe base64 without padding.
	 *
	 * @param string $data Raw data.
	 * @return string
	 */
	protected static function b64url( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Admin-post: send a test notification to the `new_content` topic.
	 */
	public static function handle_test() {
		check_admin_referer( 'synpro_push_test' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'syndicate-pro' ) );
		}
		$result = self::is_ready()
			? self::send( "'new_content' in topics", __( 'Test notification', 'syndicate-pro' ), __( 'Push is working 🎉', 'syndicate-pro' ), array( 'test' => '1' ) )
			: new WP_Error( 'synpro_push_config', __( 'Add and enable your Firebase settings first.', 'syndicate-pro' ) );

		$args = is_wp_error( $result )
			? array( 'synpro_push' => 'err', 'msg' => rawurlencode( $result->get_error_message() ) )
			: array( 'synpro_push' => 'ok' );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=synpro-syndication&tab=app' ) ) );
		exit;
	}
}

endif;
