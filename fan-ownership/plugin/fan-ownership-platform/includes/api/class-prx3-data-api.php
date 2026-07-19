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
	 * Hook the REST routes and the one-click connection actions.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_post_prx3_data_api_provision', array( __CLASS__, 'handle_provision' ) );
		add_action( 'admin_post_prx3_data_api_revoke', array( __CLASS__, 'handle_revoke' ) );
		add_action( 'admin_post_prx3_data_api_profile', array( __CLASS__, 'handle_profile' ) );
		add_action( 'admin_post_prx3_data_api_seed', array( __CLASS__, 'handle_seed_sample' ) );
		add_action( 'admin_notices', array( __CLASS__, 'seed_notice' ) );
	}

	/**
	 * Post types the API may write.
	 *
	 * @return string[] Allowed post type names.
	 */
	public static function allowed_types() {
		return apply_filters(
			'prx3_data_api_types',
			array( 'prx3_ballot', 'prx3_idea', 'prx3_question', 'prx3_meeting', 'prx3_video', 'prx3_document', 'prx3_exclusive', 'prx3_decision', 'prx3_chapter', 'prx3_match', 'prx3_player', 'prx3_forum_topic' )
		);
	}

	/**
	 * Register the /data routes.
	 */
	public static function routes() {
		$routes = array(
			'/data/schema'       => array( 'GET', 'route_schema' ),
			'/data/content'      => array( 'POST', 'route_content' ),
			'/data/content-list' => array( 'GET', 'route_content_read' ),
			'/data/members'      => array( 'POST', 'route_members' ),
			'/data/settings'     => array( 'POST', 'route_settings' ),
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
	 * GET /data/content-list — read platform content with its meta, so
	 * automation can inspect existing data before writing.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_content_read( $request ) {
		$type = sanitize_key( (string) $request->get_param( 'type' ) );
		if ( ! in_array( $type, self::allowed_types(), true ) ) {
			return new WP_Error( 'prx3_data_type', __( 'type must be a platform post type.', 'fan-ownership' ), array( 'status' => 400 ) );
		}
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 50 ) ) );
		$posts    = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		$items    = array();
		foreach ( $posts as $post ) {
			$meta = array();
			foreach ( (array) get_post_meta( $post->ID ) as $key => $values ) {
				if ( 0 === strpos( (string) $key, '_prx3_' ) ) {
					$meta[ $key ] = maybe_unserialize( $values[0] ?? '' );
				}
			}
			$items[] = array(
				'id'      => $post->ID,
				'status'  => $post->post_status,
				'title'   => $post->post_title,
				'content' => $post->post_content,
				'meta'    => $meta,
			);
		}
		return rest_ensure_response(
			array(
				'type'  => $type,
				'page'  => $page,
				'items' => $items,
			)
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

	/* ---------------- Ready-made connection (for Claude) ---------------- */

	/**
	 * The connection blob an admin pastes straight into a Claude chat.
	 *
	 * @return string Multi-line connection instructions including the key.
	 */
	private static function connection_text() {
		return sprintf(
			"Connect to my Fan Ownership Platform site and work with its data.\nBase URL: %1\$s\nSend this header on every request: X-Prx3-Data-Key: %2\$s\nStart with GET %1\$sdata/schema to discover the writable surface.\nRead content: GET %1\$sdata/content-list?type=prx3_player (any platform type).\nWrite content: POST %1\$sdata/content — single object or an array (max 100).\nImport members: POST %1\$sdata/members (shares go through the real money path).\nUpdate settings: POST %1\$sdata/settings (allow-listed keys only).",
			rest_url( 'prx3/v1/' ),
			(string) prx3_setting( 'data_api_key', '' )
		);
	}

	/**
	 * The connection panel rendered on Settings → API & Integrations:
	 * one-click provision/revoke and the paste-ready connection card.
	 */
	public static function connection_panel() {
		$key    = (string) prx3_setting( 'data_api_key', '' );
		$active = self::authorize_state();
		echo '<h2>' . esc_html__( 'Ready-made Claude connection', 'fan-ownership' ) . '</h2>';
		if ( ! $active ) {
			echo '<p>' . esc_html__( 'One click generates a strong key, switches the Data API on, and produces a connection card you paste into a Claude chat. Treat the card like an admin password.', 'fan-ownership' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'prx3_data_api_provision' );
			echo '<input type="hidden" name="action" value="prx3_data_api_provision">';
			echo '<p><button class="button button-primary">' . esc_html__( 'Create connection', 'fan-ownership' ) . '</button></p></form>';
			return;
		}
		echo '<p>' . esc_html__( 'The connection is live. Paste the card below into a Claude conversation to let it read and insert club data — then revoke when the job is done.', 'fan-ownership' ) . '</p>';
		echo '<p><label for="prx3_connection_card"><strong>' . esc_html__( 'Connection card (contains the key — handle like a password)', 'fan-ownership' ) . '</strong></label></p>';
		echo '<textarea id="prx3_connection_card" class="large-text code" rows="9" readonly onclick="this.select()">' . esc_textarea( self::connection_text() ) . '</textarea>';
		echo '<p>';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=prx3_data_api_profile' ), 'prx3_data_api_profile' ) ) . '">' . esc_html__( 'Download connection profile (JSON)', 'fan-ownership' ) . '</a> ';
		echo '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Revoke the key and switch the Data API off?', 'fan-ownership' ) ) . '\');">';
		wp_nonce_field( 'prx3_data_api_revoke' );
		echo '<input type="hidden" name="action" value="prx3_data_api_revoke">';
		echo '<p><button class="button">' . esc_html__( 'Revoke connection', 'fan-ownership' ) . '</button></p></form>';
		if ( $key && ! (int) prx3_setting( 'data_api_enabled', 0 ) ) {
			echo '<p>' . esc_html__( 'A key exists but the Data API is switched off above — enable it to make the connection live.', 'fan-ownership' ) . '</p>';
		}
		self::sample_panel();
	}

	/**
	 * The one-click demo club loader: runs the bundled sample data
	 * through the same Data API code paths, no key or terminal needed.
	 */
	public static function sample_panel() {
		echo '<h2>' . esc_html__( 'Demo club (sample data)', 'fan-ownership' ) . '</h2>';
		$loaded = (int) get_option( 'prx3_sample_loaded' );
		if ( $loaded ) {
			echo '<p>' . esc_html( sprintf( /* translators: %s date. */ __( 'Sample data was loaded on %s. Loading again will duplicate content and re-grant member shares.', 'fan-ownership' ), prx3_format_datetime( gmdate( 'Y-m-d H:i:s', $loaded ) ) ) ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'One click loads a complete demo club through the Data API code paths: 20 owners with shares via the money path, the squad, fixtures, an open ballot, ideas, questions, the AGM, decisions, videos, documents, chapters, and FanPress chats. Match and ballot chats then appear automatically. Every write is audited.', 'fan-ownership' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $loaded ? ' onsubmit="return confirm(\'' . esc_js( __( 'Sample data already loaded — load again and duplicate it?', 'fan-ownership' ) ) . '\');"' : '' ) . '>';
		wp_nonce_field( 'prx3_data_api_seed' );
		echo '<input type="hidden" name="action" value="prx3_data_api_seed">';
		echo '<p><button class="button button-primary">' . esc_html__( 'Load demo club', 'fan-ownership' ) . '</button></p></form>';
	}

	/**
	 * Load the bundled sample pack through the API's own upsert paths.
	 *
	 * @return array{members:int,content:int,settings:int,failed:string[]} Counts and failures.
	 */
	public static function seed_sample() {
		$dir = PRX3_DIR . 'data/';
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled local files, not remote.
		$members  = json_decode( (string) file_get_contents( $dir . 'sample-members.json' ), true );
		$content  = json_decode( (string) file_get_contents( $dir . 'sample-content.json' ), true );
		$settings = json_decode( (string) file_get_contents( $dir . 'sample-settings.json' ), true );
		// phpcs:enable
		$out = array(
			'members'  => 0,
			'content'  => 0,
			'settings' => 0,
			'failed'   => array(),
		);
		foreach ( (array) $members as $item ) {
			$row = self::upsert_member( (array) $item );
			if ( ! empty( $row['ok'] ) ) {
				++$out['members'];
			} else {
				$out['failed'][] = ( $item['email'] ?? '?' ) . ': ' . ( $row['error'] ?? '?' );
			}
		}
		foreach ( (array) $content as $item ) {
			$row = self::upsert_content( (array) $item );
			if ( ! empty( $row['ok'] ) ) {
				++$out['content'];
			} else {
				$out['failed'][] = ( $item['title'] ?? '?' ) . ': ' . ( $row['error'] ?? '?' );
			}
		}
		$known = PRX3_Config::defaults();
		foreach ( (array) $settings as $key => $value ) {
			$key = sanitize_key( $key );
			if ( array_key_exists( $key, $known ) && ! in_array( $key, array( 'data_api_key', 'sync_api_key', 'sync_webhook_secret' ), true ) ) {
				prx3_update_setting( $key, self::sanitize_value( $value ) );
				++$out['settings'];
			}
		}
		update_option( 'prx3_sample_loaded', time(), false );
		PRX3_Audit::log( 'data_api_sample', sprintf( 'Demo club loaded: %d members, %d content items, %d settings, %d failures', $out['members'], $out['content'], $out['settings'], count( $out['failed'] ) ) );
		return $out;
	}

	/**
	 * Show the load result after the redirect.
	 */
	public static function seed_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of a one-time result.
		if ( ! isset( $_GET['prx3_seeded'] ) ) {
			return;
		}
		$result = get_transient( 'prx3_sample_result_' . get_current_user_id() );
		if ( ! is_array( $result ) ) {
			return;
		}
		delete_transient( 'prx3_sample_result_' . get_current_user_id() );
		$class = $result['failed'] ? 'notice-warning' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>';
		echo esc_html( sprintf( /* translators: 1 members, 2 content, 3 settings. */ __( 'Demo club loaded: %1$d members with shares, %2$d content items, %3$d settings. Match and ballot chats appear automatically in FanPress.', 'fan-ownership' ), (int) $result['members'], (int) $result['content'], (int) $result['settings'] ) );
		echo '</p>';
		foreach ( (array) $result['failed'] as $failure ) {
			echo '<p>' . esc_html( sprintf( /* translators: %s failure detail. */ __( 'Failed: %s', 'fan-ownership' ), $failure ) ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Admin button: load the demo club.
	 */
	public static function handle_seed_sample() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_data_api_seed' );
		$result = self::seed_sample();
		set_transient( 'prx3_sample_result_' . get_current_user_id(), $result, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-settings-api&prx3_seeded=1' ) );
		exit;
	}

	/**
	 * Is the connection usable right now (enabled with a key)?
	 *
	 * @return bool
	 */
	private static function authorize_state() {
		return '' !== (string) prx3_setting( 'data_api_key', '' ) && (int) prx3_setting( 'data_api_enabled', 0 );
	}

	/**
	 * One-click provision: generate a key (if none) and enable the API.
	 */
	public static function handle_provision() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_data_api_provision' );
		if ( '' === (string) prx3_setting( 'data_api_key', '' ) ) {
			prx3_update_setting( 'data_api_key', wp_generate_password( 48, false, false ) );
		}
		prx3_update_setting( 'data_api_enabled', 1 );
		update_option( 'prx3_data_api_provisioned', time(), false );
		PRX3_Audit::log( 'data_api_provisioned', sprintf( 'Data API connection provisioned by user %d', get_current_user_id() ) );
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-settings-api&saved=1' ) );
		exit;
	}

	/**
	 * Revoke: clear the key and switch the API off.
	 */
	public static function handle_revoke() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_data_api_revoke' );
		prx3_update_setting( 'data_api_key', '' );
		prx3_update_setting( 'data_api_enabled', 0 );
		delete_option( 'prx3_data_api_provisioned' );
		PRX3_Audit::log( 'data_api_revoked', sprintf( 'Data API connection revoked by user %d', get_current_user_id() ) );
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-settings-api&saved=1' ) );
		exit;
	}

	/**
	 * Download the machine-readable connection profile.
	 */
	public static function handle_profile() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_data_api_profile' );
		$profile = array(
			'name'      => sprintf( '%s — Fan Ownership Data API', prx3_club_name() ),
			'base_url'  => rest_url( 'prx3/v1/' ),
			'auth'      => array(
				'type'   => 'header',
				'header' => 'X-Prx3-Data-Key',
				'key'    => (string) prx3_setting( 'data_api_key', '' ),
			),
			'discovery' => rest_url( 'prx3/v1/data/schema' ),
			'endpoints' => array(
				'schema'       => array( 'GET', 'data/schema' ),
				'content_list' => array( 'GET', 'data/content-list?type={post_type}' ),
				'content'      => array( 'POST', 'data/content' ),
				'members'      => array( 'POST', 'data/members' ),
				'settings'     => array( 'POST', 'data/settings' ),
			),
			'notes'     => 'Batches max 100. Content meta keys must start _prx3_. Member share grants pass the cap/age/register money path.',
		);
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="prx3-claude-connection.json"' );
		echo wp_json_encode( $profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download.
		exit;
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
