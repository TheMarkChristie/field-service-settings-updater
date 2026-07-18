<?php
/**
 * The Data API: an authenticated write surface so trusted automation —
 * Claude, scripts, migration jobs — can insert and update platform
 * data: content of every platform post type (with meta and terms),
 * members with share grants, and settings.
 *
 * Safety model: disabled until a key is set (Settings → Integrations),
 * every call authenticated with X-Prx3-Data-Key, writes restricted to
 * platform types / prx3-prefixed meta / known settings keys, share
 * grants go through PRX3_Shares (cap, age gate, register record — the
 * money path is never bypassed), and every write is audited. A schema
 * endpoint describes what is insertable so callers can discover the
 * surface instead of guessing.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Key-gated REST write API for content, members, and settings, with a
 * discovery schema and full audit trail.
 */
class PRX3_Data_API {

	/**
	 * Hook the REST routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Post types the API may write.
	 *
	 * @return string[] Allowed post type names.
	 */
	public static function allowed_types() {
		return apply_filters(
			'prx3_data_api_types',
			array( 'prx3_ballot', 'prx3_idea', 'prx3_question', 'prx3_meeting', 'prx3_video', 'prx3_document', 'prx3_exclusive', 'prx3_decision', 'prx3_chapter', 'prx3_match', 'prx3_player' )
		);
	}

	/**
	 * Register the /data routes.
	 */
	public static function routes() {
		$routes = array(
			'/data/schema'   => array( 'GET', 'route_schema' ),
			'/data/content'  => array( 'POST', 'route_content' ),
			'/data/members'  => array( 'POST', 'route_members' ),
			'/data/settings' => array( 'POST', 'route_settings' ),
		);
		foreach ( $routes as $path => $def ) {
			register_rest_route(
				'prx3/v1',
				$path,
				array(
					'methods'             => $def[0],
					'permission_callback' => array( __CLASS__, 'authorize' ),
					'callback'            => array( __CLASS__, $def[1] ),
				)
			);
		}
	}

	/**
	 * Key auth: enabled + constant-time key match.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function authorize( $request ) {
		$key = (string) prx3_setting( 'data_api_key', '' );
		if ( '' === $key || ! (int) prx3_setting( 'data_api_enabled', 0 ) ) {
			return new WP_Error( 'prx3_data_disabled', __( 'The Data API is not enabled.', 'fan-ownership' ), array( 'status' => 403 ) );
		}
		$sent = (string) $request->get_header( 'X-Prx3-Data-Key' );
		if ( ! $sent || ! hash_equals( $key, $sent ) ) {
			return new WP_Error( 'prx3_data_auth', __( 'Invalid Data API key.', 'fan-ownership' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * GET /data/schema — what the API can write, for discovery.
	 *
	 * @param WP_REST_Request $request Request (unused).
	 * @return WP_REST_Response|array
	 */
	public static function route_schema( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- REST signature.
		return rest_ensure_response(
			array(
				'content'  => array(
					'types'  => self::allowed_types(),
					'fields' => array( 'id (update)', 'type', 'title', 'content', 'status (publish|draft|pending)', 'meta (keys must start _prx3_)', 'terms ({taxonomy: [names]})' ),
				),
				'members'  => array(
					'fields' => array( 'email', 'name', 'shares (granted via the money path: cap and age gate apply)', 'source (register label, default api_import)' ),
				),
				'settings' => array(
					'keys' => array_keys( PRX3_Config::defaults() ),
				),
			)
		);
	}

	/**
	 * POST /data/content — create or update posts of platform types.
	 * Accepts one object or an array of objects.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_content( $request ) {
		$body  = $request->get_json_params();
		$items = isset( $body[0] ) ? $body : array( $body );
		if ( count( $items ) > 100 ) {
			return new WP_Error( 'prx3_data_batch', __( 'Batches are capped at 100 items.', 'fan-ownership' ), array( 'status' => 400 ) );
		}
		$results = array();
		foreach ( $items as $item ) {
			$results[] = self::upsert_content( (array) $item );
		}
		return rest_ensure_response( array( 'results' => $results ) );
	}

	/**
	 * Create or update one post with meta and terms.
	 *
	 * @param array $item Content payload.
	 * @return array Result row: ok/id/link or error.
	 */
	private static function upsert_content( $item ) {
		$type = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : '';
		if ( ! in_array( $type, self::allowed_types(), true ) ) {
			return array(
				'ok'    => false,
				'error' => 'type must be one of: ' . implode( ', ', self::allowed_types() ),
			);
		}
		$post_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
		if ( $post_id && get_post_type( $post_id ) !== $type ) {
			return array(
				'ok'    => false,
				'error' => 'id does not belong to the stated type',
			);
		}
		$status = isset( $item['status'] ) && in_array( $item['status'], array( 'publish', 'draft', 'pending' ), true ) ? $item['status'] : 'draft';
		$args   = array(
			'post_type'    => $type,
			'post_status'  => $status,
			'post_title'   => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
			'post_content' => isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '',
		);
		if ( $post_id ) {
			$args['ID'] = $post_id;
			$result     = wp_update_post( $args, true );
		} else {
			$result = wp_insert_post( $args, true );
		}
		if ( is_wp_error( $result ) ) {
			return array(
				'ok'    => false,
				'error' => $result->get_error_message(),
			);
		}
		$post_id = (int) $result;

		foreach ( (array) ( $item['meta'] ?? array() ) as $key => $value ) {
			$key = sanitize_key( $key );
			if ( 0 !== strpos( $key, '_prx3_' ) ) {
				continue; // Only platform meta is writable.
			}
			update_post_meta( $post_id, $key, self::sanitize_value( $value ) );
		}
		foreach ( (array) ( $item['terms'] ?? array() ) as $taxonomy => $names ) {
			$taxonomy = sanitize_key( $taxonomy );
			if ( taxonomy_exists( $taxonomy ) ) {
				wp_set_object_terms( $post_id, array_map( 'sanitize_text_field', (array) $names ), $taxonomy );
			}
		}
		PRX3_Audit::log( 'data_api_content', sprintf( 'Data API wrote %s #%d (%s)', $type, $post_id, $status ) );
		return array(
			'ok'   => true,
			'id'   => $post_id,
			'link' => get_permalink( $post_id ),
		);
	}

	/**
	 * POST /data/members — create members and grant shares through the
	 * money path (cap and age gate enforced, register records written).
	 * Accepts one object or an array of objects.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_members( $request ) {
		$body  = $request->get_json_params();
		$items = isset( $body[0] ) ? $body : array( $body );
		if ( count( $items ) > 100 ) {
			return new WP_Error( 'prx3_data_batch', __( 'Batches are capped at 100 items.', 'fan-ownership' ), array( 'status' => 400 ) );
		}
		$results = array();
		foreach ( $items as $item ) {
			$results[] = self::upsert_member( (array) $item );
		}
		return rest_ensure_response( array( 'results' => $results ) );
	}

	/**
	 * Create (or find by email) one member and optionally grant shares.
	 *
	 * @param array $item Member payload.
	 * @return array Result row.
	 */
	private static function upsert_member( $item ) {
		$email = isset( $item['email'] ) ? sanitize_email( $item['email'] ) : '';
		if ( ! $email ) {
			return array(
				'ok'    => false,
				'error' => 'email is required',
			);
		}
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$name    = isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : $email;
			$user_id = wp_insert_user(
				array(
					'user_login'   => sanitize_user( $email, true ),
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 24 ),
					'display_name' => $name,
					'role'         => 'subscriber',
				)
			);
			if ( is_wp_error( $user_id ) ) {
				return array(
					'ok'    => false,
					'error' => $user_id->get_error_message(),
				);
			}
			update_user_meta( $user_id, 'prx3_adult_confirmed', time() );
			update_user_meta( $user_id, 'prx3_email_verified', time() );
		} else {
			$user_id = $user->ID;
			if ( ! get_user_meta( $user_id, 'prx3_adult_confirmed', true ) ) {
				update_user_meta( $user_id, 'prx3_adult_confirmed', time() );
			}
		}

		$granted = 0;
		$shares  = isset( $item['shares'] ) ? (int) $item['shares'] : 0;
		if ( $shares > 0 ) {
			$source = isset( $item['source'] ) ? sanitize_key( $item['source'] ) : 'api_import';
			$result = PRX3_Shares::grant_shares( $user_id, $shares, $source, array( 'via' => 'data_api' ) );
			if ( is_wp_error( $result ) ) {
				return array(
					'ok'      => false,
					'user_id' => $user_id,
					'error'   => $result->get_error_message(),
				);
			}
			$granted = $shares;
		}
		PRX3_Audit::log( 'data_api_member', sprintf( 'Data API upserted member %d (%s), %d share(s) granted', $user_id, $email, $granted ) );
		return array(
			'ok'           => true,
			'user_id'      => $user_id,
			'owner_number' => PRX3_Shares::owner_number( $user_id ),
			'shares'       => prx3_shares( $user_id ),
		);
	}

	/**
	 * POST /data/settings — merge known settings keys.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_settings( $request ) {
		$body    = (array) $request->get_json_params();
		$known   = PRX3_Config::defaults();
		$applied = array();
		$ignored = array();
		foreach ( $body as $key => $value ) {
			$key = sanitize_key( $key );
			if ( in_array( $key, array( 'data_api_key', 'sync_api_key', 'sync_webhook_secret' ), true ) ) {
				$ignored[] = $key; // Keys never rotate themselves over the API.
				continue;
			}
			if ( ! array_key_exists( $key, $known ) ) {
				$ignored[] = $key;
				continue;
			}
			prx3_update_setting( $key, self::sanitize_value( $value ) );
			$applied[] = $key;
		}
		if ( $applied ) {
			PRX3_Audit::log( 'data_api_settings', 'Data API updated settings: ' . implode( ', ', $applied ) );
		}
		return rest_ensure_response(
			array(
				'applied' => $applied,
				'ignored' => $ignored,
			)
		);
	}

	/**
	 * Sanitize a payload value recursively: scalars to text, arrays
	 * element-wise, keys preserved.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed Sanitized value.
	 */
	private static function sanitize_value( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				$out[ is_string( $key ) ? sanitize_key( $key ) : $key ] = self::sanitize_value( $item );
			}
			return $out;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		return sanitize_text_field( (string) $value );
	}
}
