<?php
/**
 * The prx3/v1 REST API: everything a member can do, for the apps and the
 * web front end. FO-302 parity is served from here.
 *
 * Auth: Bearer JWT (apps) or logged-in cookie + nonce (web). Rate
 * limited per user on write routes (T32 hardening).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the prx3/v1 REST routes and Bearer-token authentication for
 * the native apps and the web front end (FO-302 parity).
 */
class PRX3_REST_API {

	/**
	 * Hook route registration and Bearer authentication.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'determine_current_user', array( __CLASS__, 'bearer_auth' ), 20 );
	}

	/**
	 * Resolve Bearer tokens to a WordPress user for our namespace.
	 *
	 * @param int|false $user_id User already determined upstream, if any.
	 * @return int|false User ID from the token, or the incoming value.
	 */
	public static function bearer_auth( $user_id ) {
		if ( $user_id ) {
			return $user_id;
		}
		$header = self::authorization_header();
		if ( ! preg_match( '/^Bearer\s+(.+)$/i', $header, $m ) ) {
			return $user_id;
		}
		$claims = PRX3_JWT::decode( trim( $m[1] ), 'access' );
		return is_wp_error( $claims ) ? $user_id : (int) $claims['sub'];
	}

	/**
	 * Read the Authorization header robustly. Many hosts (Apache + CGI/
	 * FastCGI, and setups behind a proxy) drop it from HTTP_AUTHORIZATION,
	 * moving it to REDIRECT_HTTP_AUTHORIZATION or exposing it only through
	 * getallheaders(). Without this, app tokens are silently ignored on
	 * production even though sign-in (which reads the POST body) works.
	 *
	 * @return string The raw Authorization header, or an empty string.
	 */
	private static function authorization_header() {
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) );
		}
		if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return trim( sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) );
		}
		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( is_array( $headers ) ) {
				foreach ( $headers as $key => $value ) {
					if ( 'authorization' === strtolower( (string) $key ) ) {
						return trim( sanitize_text_field( wp_unslash( $value ) ) );
					}
				}
			}
		}
		return '';
	}

	/**
	 * Permission callback for owner-only routes.
	 *
	 * @return true|WP_Error
	 */
	private static function owner_permission() {
		return prx3_is_owner() ? true : new WP_Error( 'prx3_owner_only', __( 'Owners only.', 'fan-ownership' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Login throttle: after 5 failures per IP or per username inside 15
	 * minutes, that key locks out; the lockout doubles on repeat strikes
	 * (15m, 30m, 60m… capped at 4 hours).
	 *
	 * @param string $username Attempted username.
	 * @return true|WP_Error
	 */
	private static function login_throttled( $username ) {
		foreach ( self::login_keys( $username ) as $key ) {
			if ( get_transient( 'prx3_lock_' . $key ) ) {
				return new WP_Error(
					'prx3_locked',
					__( 'Too many failed sign-in attempts. Please wait before trying again, or reset your password.', 'fan-ownership' ),
					array( 'status' => 429 )
				);
			}
		}
		return true;
	}

	/**
	 * Record a failed sign-in against both throttle keys and lock out on
	 * the fifth strike.
	 *
	 * @param string $username Attempted username.
	 */
	private static function login_failed( $username ) {
		foreach ( self::login_keys( $username ) as $key ) {
			$fails = (int) get_transient( 'prx3_fail_' . $key ) + 1;
			set_transient( 'prx3_fail_' . $key, $fails, 15 * MINUTE_IN_SECONDS );
			if ( $fails >= 5 ) {
				$strikes = (int) get_transient( 'prx3_strikes_' . $key ) + 1;
				set_transient( 'prx3_strikes_' . $key, $strikes, 12 * HOUR_IN_SECONDS );
				$lockout = min( 4 * HOUR_IN_SECONDS, 15 * MINUTE_IN_SECONDS * (int) pow( 2, $strikes - 1 ) );
				set_transient( 'prx3_lock_' . $key, 1, $lockout );
				delete_transient( 'prx3_fail_' . $key );
				PRX3_Audit::log( 'login_lockout', sprintf( 'Login lockout for %s (%d strikes, %d seconds)', $key, $strikes, $lockout ) );
			}
		}
	}

	/**
	 * Clear the failure counters after a successful sign-in.
	 *
	 * @param string $username Username that signed in.
	 */
	private static function login_succeeded( $username ) {
		foreach ( self::login_keys( $username ) as $key ) {
			delete_transient( 'prx3_fail_' . $key );
		}
	}

	/**
	 * Authenticate with a WordPress Application Password and, on success,
	 * issue the JWT pair. Application passwords are the WordPress-blessed
	 * credential for apps: each is per-application and revocable, and they
	 * are validated by core independently of interactive two-factor, so an
	 * app can sign in on a site that enforces 2FA for browser logins.
	 *
	 * @param string $username     WordPress username or account email.
	 * @param string $app_password The application password (spaces optional).
	 * @return array|WP_Error JWT pair, or an error.
	 */
	public static function app_password_login( $username, $app_password ) {
		if ( ! function_exists( 'wp_authenticate_application_password' ) ) {
			return new WP_Error( 'prx3_app_unavailable', __( 'Application passwords are not available on this site (they require HTTPS).', 'fan-ownership' ), array( 'status' => 501 ) );
		}
		// Core accepts the password with or without the display spaces.
		$user = wp_authenticate_application_password( null, $username, $app_password );
		if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
			return new WP_Error( 'prx3_login', __( 'Sign-in failed. Check your username and application password.', 'fan-ownership' ), array( 'status' => 401 ) );
		}
		prx3_touch_activity( $user->ID );
		return PRX3_JWT::issue_pair( $user->ID );
	}

	/**
	 * The two throttle keys for a sign-in attempt: per IP and per target
	 * username.
	 *
	 * @param string $username Attempted username.
	 * @return string[]
	 */
	private static function login_keys( $username ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'noip';
		return array( 'ip_' . md5( $ip ), 'user_' . md5( strtolower( $username ) ) );
	}

	/**
	 * Per-user per-minute rate limit on write routes (T32 hardening).
	 *
	 * @param string $key        Route bucket name.
	 * @param int    $per_minute Allowed requests per minute.
	 * @return true|WP_Error
	 */
	private static function rate_limit( $key, $per_minute = 20 ) {
		$user_id = get_current_user_id();
		$bucket  = 'prx3_rl_' . $key . '_' . $user_id . '_' . gmdate( 'YmdHi' );
		$count   = (int) get_transient( $bucket );
		if ( $count >= $per_minute ) {
			return new WP_Error( 'prx3_rate', __( 'Too many requests — slow down a little.', 'fan-ownership' ), array( 'status' => 429 ) );
		}
		set_transient( $bucket, $count + 1, 2 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Register every prx3/v1 route: auth, profile, ballots, ideas,
	 * questions, meetings, matches, chat, library, and decisions.
	 */
	public static function routes() {
		$ns = 'prx3/v1';

		// ---- Auth (FO-301) ----
		register_rest_route(
			$ns,
			'/auth/login',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $request ) {
					// Credential-stuffing defence: unauthenticated surface, so
					// throttle by IP and by target username with growing lockouts.
					$username  = sanitize_text_field( (string) $request['username'] );
					$throttled = self::login_throttled( $username );
					if ( is_wp_error( $throttled ) ) {
						return $throttled;
					}
					$user = wp_authenticate( $username, (string) $request['password'] );
					if ( is_wp_error( $user ) ) {
						self::login_failed( $username );
						return new WP_Error( 'prx3_login', __( 'Sign-in failed. Check your email and password.', 'fan-ownership' ), array( 'status' => 401 ) );
					}
					self::login_succeeded( $username );
					prx3_touch_activity( $user->ID );
					return PRX3_JWT::issue_pair( $user->ID );
				},
			)
		);
		register_rest_route(
			$ns,
			'/auth/refresh',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $request ) {
					$claims = PRX3_JWT::decode( (string) $request['refresh_token'], 'refresh' );
					if ( is_wp_error( $claims ) ) {
						return $claims;
					}
					return PRX3_JWT::issue_pair( (int) $claims['sub'] );
				},
			)
		);

		// Application-password sign-in for the app: works even where a
		// security/2FA plugin blocks password-only REST logins (FO-301).
		register_rest_route(
			$ns,
			'/auth/app-login',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $request ) {
					$username  = sanitize_text_field( (string) $request['username'] );
					$throttled = self::login_throttled( $username );
					if ( is_wp_error( $throttled ) ) {
						return $throttled;
					}
					$result = self::app_password_login( $username, (string) $request['app_password'] );
					if ( is_wp_error( $result ) ) {
						self::login_failed( $username );
						return $result;
					}
					self::login_succeeded( $username );
					return $result;
				},
			)
		);

		// ---- Me (profile, shares, badges, certificates) ----
		register_rest_route(
			$ns,
			'/me',
			array(
				'methods'             => 'GET',
				'permission_callback' => fn() => is_user_logged_in(),
				'callback'            => function () {
					$user = wp_get_current_user();
					prx3_touch_activity( $user->ID );
					return array(
						'id'               => $user->ID,
						'name'             => $user->display_name,
						'owner_number'     => PRX3_Shares::owner_number( $user->ID ),
						'shares'           => prx3_shares( $user->ID ),
						'max_shares'       => prx3_max_shares(),
						'next_share_price' => prx3_shares( $user->ID ) < prx3_max_shares() ? prx3_share_price( prx3_shares( $user->ID ) + 1 ) : null,
						'is_owner'         => prx3_is_owner( $user->ID ),
						'badges'           => PRX3_Badges::member_badges( $user->ID ),
						'discounts'        => PRX3_Shares::ticket_discounts( $user->ID ),
						'club'             => array_merge(
							array(
								'name'  => prx3_club_name(),
								'sport' => prx3_setting( 'sport', 'ice_hockey' ),
							),
							prx3_brand_pack() // Full uploadable brand pack: badges, wordmark, colours, font (app themes itself from this).
						),
						'ticketing'        => array(
							'provider' => PRX3_Ticketing::provider(),
							'code'     => PRX3_Ticketing::member_code( $user->ID ),
						),
						'checkout_url'     => PRX3_Shopify::checkout_url( $user->ID ) ? PRX3_Shopify::checkout_url( $user->ID ) : home_url(), // Apps link out to the Shopify cart (T33/P105).
						'calendar_url'     => PRX3_Meetings::member_ics_url( $user->ID ),
					);
				},
			)
		);
		register_rest_route(
			$ns,
			'/me/push-token',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'owner_permission' ),
				'callback'            => function ( WP_REST_Request $request ) {
					$tokens   = array_filter( (array) get_user_meta( get_current_user_id(), 'prx3_push_tokens', true ) );
					$tokens[] = sanitize_text_field( (string) $request['token'] );
					update_user_meta( get_current_user_id(), 'prx3_push_tokens', array_slice( array_unique( $tokens ), -5 ) );
					return array( 'ok' => true );
				},
			)
		);

		// ---- Ballots (FO-202/203/209) ----
		register_rest_route(
			$ns,
			'/ballots',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'owner_permission' ),
				'callback'            => function () {
					$out = array();
					foreach ( array_merge( PRX3_Ballots::open_ballots(), PRX3_Ballots::scheduled_ballots() ) as $ballot ) {
						$out[] = self::ballot_payload( $ballot->ID );
					}
					$closed = get_posts(
						array(
							'post_type'      => 'prx3_ballot',
							'post_status'    => 'publish',
							'posts_per_page' => 20,
							'no_found_rows'  => true,
							'meta_key'       => '_prx3_state', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded lookup of the last 20 published ballots for the app feed.
							'meta_value'     => 'published', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded lookup of the last 20 published ballots for the app feed.
						)
					);
					foreach ( $closed as $ballot ) {
						$out[] = self::ballot_payload( $ballot->ID );
					}
					return $out;
				},
			)
		);
		register_rest_route(
			$ns,
			'/ballots/(?P<id>\d+)/vote',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'owner_permission' ),
				'callback'            => function ( WP_REST_Request $request ) {
					$limited = self::rate_limit( 'vote', 10 );
					if ( is_wp_error( $limited ) ) {
						return $limited;
					}
					$result = PRX3_Ballots::cast( (int) $request['id'], get_current_user_id(), (int) $request['choice'] );
					return is_wp_error( $result ) ? $result : array_merge( $result, array( 'ballot' => self::ballot_payload( (int) $request['id'] ) ) );
				},
			)
		);

		// ---- Ideas ----
		register_rest_route(
			$ns,
			'/ideas',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( __CLASS__, 'owner_permission' ),
					'callback'            => function () {
						return array_map(
							function ( $idea ) {
								return array(
									'id'              => $idea->ID,
									'title'           => $idea->post_title,
									'body'            => wp_strip_all_tags( $idea->post_content ),
									'status'          => get_post_meta( $idea->ID, '_prx3_idea_status', true ),
									'supporters'      => count( (array) get_post_meta( $idea->ID, '_prx3_supporters', true ) ),
									'threshold'       => PRX3_Ideas::threshold_count(),
									'declined_reason' => get_post_meta( $idea->ID, '_prx3_declined_reason', true ),
								);
							},
							get_posts(
								array(
									'post_type'      => 'prx3_idea',
									'post_status'    => 'publish',
									'posts_per_page' => 50,
									'no_found_rows'  => true,
								)
							)
						);
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( __CLASS__, 'owner_permission' ),
					'callback'            => function ( WP_REST_Request $request ) {
						$limited = self::rate_limit( 'idea', 5 );
						if ( is_wp_error( $limited ) ) {
							return $limited;
						}
						$id = PRX3_Ideas::submit( get_current_user_id(), (string) $request['title'], (string) $request['body'] );
						return is_wp_error( $id ) ? $id : array(
							'id'     => $id,
							'status' => 'pending-moderation',
						);
					},
				),
			)
		);
		register_rest_route(
			$ns,
			'/ideas/(?P<id>\d+)/support',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'owner_permission' ),
				'callback'            => fn( WP_REST_Request $request ) => PRX3_Ideas::toggle_support( (int) $request['id'], get_current_user_id() ),
			)
		);

		// ---- Questions ----
		register_rest_route(
			$ns,
			'/questions',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( __CLASS__, 'owner_permission' ),
					'callback'            => function ( WP_REST_Request $request ) {
						$filter = sanitize_key( (string) $request['category'] );
						$out    = array();
						foreach ( get_posts(
							array(
								'post_type'      => 'prx3_question',
								'post_status'    => 'publish',
								'posts_per_page' => 100,
								'no_found_rows'  => true,
							)
						) as $q ) {
							$category = PRX3_Questions::category( $q->ID );
							if ( $filter && $category !== $filter ) {
								continue;
							}
							$out[] = array(
								'id'       => $q->ID,
								'title'    => $q->post_title,
								'category' => $category,
								'answer'   => get_post_meta( $q->ID, '_prx3_answer', true ),
								'video'    => get_post_meta( $q->ID, '_prx3_video_answer', true ),
								'upvotes'  => count( (array) get_post_meta( $q->ID, '_prx3_upvotes', true ) ),
							);
						}
						return $out;
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( __CLASS__, 'owner_permission' ),
					'callback'            => function ( WP_REST_Request $request ) {
						$limited = self::rate_limit( 'question', 5 );
						if ( is_wp_error( $limited ) ) {
							return $limited;
						}
						$id = PRX3_Questions::submit( get_current_user_id(), (string) $request['title'], (string) $request['body'], sanitize_key( (string) $request['category'] ) );
						return is_wp_error( $id ) ? $id : array(
							'id'     => $id,
							'status' => 'pending-moderation',
						);
					},
				),
			)
		);
		register_rest_route(
			$ns,
			'/questions/(?P<id>\d+)/upvote',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'owner_permission' ),
				'callback'            => fn( WP_REST_Request $request ) => PRX3_Questions::toggle_upvote( (int) $request['id'], get_current_user_id() ),
			)
		);

		// ---- Meetings ----
		register_rest_route(
			$ns,
			'/meetings',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'owner_permission' ),
				'callback'            => function () {
					return array_map(
						function ( $meeting ) {
							return array(
								'id'        => $meeting->ID,
								'title'     => $meeting->post_title,
								'starts'    => get_post_meta( $meeting->ID, '_prx3_meeting_start', true ),
								'stream'    => get_post_meta( $meeting->ID, '_prx3_stream_embed', true ),
								'recording' => get_post_meta( $meeting->ID, '_prx3_recording', true ),
								'rsvps'     => count( (array) get_post_meta( $meeting->ID, '_prx3_attendees', true ) ),
								'is_agm'    => (bool) get_post_meta( $meeting->ID, '_prx3_is_agm', true ),
							);
						},
						PRX3_Meetings::upcoming( 20 )
					);
				},
			)
		);
		register_rest_route(
			$ns,
			'/meetings/(?P<id>\d+)/rsvp',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'owner_permission' ),
				'callback'            => fn( WP_REST_Request $request ) => PRX3_Meetings::toggle_rsvp( (int) $request['id'], get_current_user_id() ),
			)
		);

		// ---- Matches, events, chat ----
		register_rest_route(
			$ns,
			'/matches/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'owner_permission' ),
				'callback'            => function ( WP_REST_Request $request ) {
					$id = (int) $request['id'];
					prx3_touch_activity();
					$uid = get_post_meta( $id, '_prx3_stream_uid', true );
					return array(
						'id'          => $id,
						'title'       => get_the_title( $id ),
						'state'       => PRX3_Match_Centre::live_state( $id ),
						'start_label' => PRX3_Config::sport()['start'],
						'event_types' => array_map( fn( $e ) => $e[0], PRX3_Config::sport()['events'] ),
						'venue'       => get_post_meta( $id, '_prx3_venue', true ),
						'score'       => get_post_meta( $id, '_prx3_score', true ),
						'kickoff'     => get_post_meta( $id, '_prx3_kickoff', true ),
						'playback'    => $uid && prx3_feature_on( 'streams' ) ? PRX3_Media::playback_token( $uid, get_current_user_id() ) : null,
						'audio'       => get_post_meta( $id, '_prx3_audio_url', true ),
						'ad_slots'    => (array) get_post_meta( $id, '_prx3_ad_slots', true ),
						'timeline'    => PRX3_Match_Centre::timeline( $id ),
					);
				},
			)
		);
		register_rest_route(
			$ns,
			'/matches/(?P<id>\d+)/events',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'owner_permission' ),
				'callback'            => function ( WP_REST_Request $request ) {
					return PRX3_Match_Centre::record_event(
						(int) $request['id'],
						get_current_user_id(),
						(string) $request['client_key'],
						sanitize_key( (string) $request['event_type'] ),
						$request['minute'],
						is_array( $request['detail'] ) ? $request['detail'] : array()
					);
				},
			)
		);
		register_rest_route(
			$ns,
			'/chat/(?P<room>[a-z0-9\-]+)',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( __CLASS__, 'owner_permission' ),
					'callback'            => fn( WP_REST_Request $request ) => PRX3_Chat::fetch( (string) $request['room'], (int) $request['since'], get_current_user_id() ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( __CLASS__, 'owner_permission' ),
					'callback'            => function ( WP_REST_Request $request ) {
							$limited = self::rate_limit( 'chat', 30 );
						if ( is_wp_error( $limited ) ) {
							return $limited;
						}
							return PRX3_Chat::post_message( (string) $request['room'], get_current_user_id(), (string) $request['body'] );
					},
				),
			)
		);

		// ---- Library & content (FO-311) ----
		register_rest_route(
			$ns,
			'/videos',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'owner_permission' ),
				'callback'            => function ( WP_REST_Request $request ) {
					$args = array(
						'post_type'      => 'prx3_video',
						'post_status'    => 'publish',
						'posts_per_page' => 30,
						'no_found_rows'  => true,
					);
					if ( $request['type'] ) {
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Bounded library filter (30 posts) by video type for the app feed.
						$args['tax_query'] = array(
							array(
								'taxonomy' => 'prx3_video_type',
								'field'    => 'slug',
								'terms'    => sanitize_key( (string) $request['type'] ),
							),
						);
					}
					if ( $request['search'] ) {
						$args['s'] = sanitize_text_field( (string) $request['search'] );
					}
					return array_map(
						function ( $video ) {
							$uid = get_post_meta( $video->ID, '_prx3_cf_uid', true );
							return array(
								'id'        => $video->ID,
								'title'     => $video->post_title,
								'types'     => wp_get_object_terms( $video->ID, 'prx3_video_type', array( 'fields' => 'slugs' ) ),
								'playback'  => $uid ? PRX3_Media::playback_token( $uid, get_current_user_id() ) : null,
								'media_url' => get_post_meta( $video->ID, '_prx3_media_url', true ),
								'resume'    => PRX3_Media::get_position( $video->ID, get_current_user_id() ),
							);
						},
						get_posts( $args )
					);
				},
			)
		);
		register_rest_route(
			$ns,
			'/videos/(?P<id>\d+)/position',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'owner_permission' ),
				'callback'            => function ( WP_REST_Request $request ) {
					PRX3_Media::save_position( (int) $request['id'], get_current_user_id(), (int) $request['seconds'] );
					return array( 'ok' => true );
				},
			)
		);

		// ---- Decisions & documents ----
		register_rest_route(
			$ns,
			'/decisions',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'owner_permission' ),
				'callback'            => function () {
					return array_map(
						function ( $decision ) {
							return array(
								'id'        => $decision->ID,
								'title'     => $decision->post_title,
								'status'    => get_post_meta( $decision->ID, '_prx3_decision_status', true ),
								'stalled'   => (bool) get_post_meta( $decision->ID, '_prx3_stalled', true ),
								'updates'   => (array) get_post_meta( $decision->ID, '_prx3_updates', true ),
								'board'     => (bool) get_post_meta( $decision->ID, '_prx3_board_decision', true ),
								'reasoning' => get_post_meta( $decision->ID, '_prx3_board_reasoning', true ),
							);
						},
						get_posts(
							array(
								'post_type'      => 'prx3_decision',
								'post_status'    => 'publish',
								'posts_per_page' => 100,
								'no_found_rows'  => true,
							)
						)
					);
				},
			)
		);
	}

	/**
	 * A ballot as the apps consume it, with tallies only when permitted
	 * (FO-203).
	 *
	 * @param int $ballot_id Ballot post ID.
	 * @return array
	 */
	private static function ballot_payload( $ballot_id ) {
		$state   = PRX3_Ballots::state( $ballot_id );
		$mine    = PRX3_Ballots::member_choice( $ballot_id, get_current_user_id() );
		$payload = array(
			'id'                   => $ballot_id,
			'title'                => get_the_title( $ballot_id ),
			'body'                 => wp_strip_all_tags( get_post_field( 'post_content', $ballot_id ) ),
			'options'              => (array) get_post_meta( $ballot_id, '_prx3_options', true ),
			'option_descriptions'  => (array) get_post_meta( $ballot_id, '_prx3_option_descs', true ),
			'type'                 => get_post_meta( $ballot_id, '_prx3_type', true ),
			'state'                => $state,
			'opens'                => get_post_meta( $ballot_id, '_prx3_opens', true ),
			'closes'               => get_post_meta( $ballot_id, '_prx3_closes', true ),
			'my_choice'            => $mine ? (int) $mine['choice'] : null,
			'my_weight'            => $mine ? (int) $mine['weight'] : prx3_shares( get_current_user_id() ),
			'board_recommendation' => get_post_meta( $ballot_id, '_prx3_board_recommendation', true ),
		);
		// FO-203: tallies only when permitted.
		$tallies = PRX3_Ballots::tallies( $ballot_id );
		if ( ! is_wp_error( $tallies ) ) {
			$payload['tallies'] = $tallies;
			$payload['result']  = get_post_meta( $ballot_id, '_prx3_result', true );
		} else {
			$payload['secret'] = true;
		}
		return $payload;
	}
}
