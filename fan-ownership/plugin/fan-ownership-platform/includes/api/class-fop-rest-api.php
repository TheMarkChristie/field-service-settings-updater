<?php
/**
 * The fop/v1 REST API: everything a member can do, for the apps and the
 * web front end. FO-302 parity is served from here.
 *
 * Auth: Bearer JWT (apps) or logged-in cookie + nonce (web). Rate
 * limited per user on write routes (T32 hardening).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_REST_API {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'determine_current_user', array( __CLASS__, 'bearer_auth' ), 20 );
	}

	/**
	 * Resolve Bearer tokens to a WordPress user for our namespace.
	 */
	public static function bearer_auth( $user_id ) {
		if ( $user_id ) {
			return $user_id;
		}
		$header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';
		if ( ! preg_match( '/^Bearer\s+(.+)$/i', $header, $m ) ) {
			return $user_id;
		}
		$claims = FOP_JWT::decode( $m[1], 'access' );
		return is_wp_error( $claims ) ? $user_id : (int) $claims['sub'];
	}

	private static function owner_permission() {
		return fop_is_owner() ? true : new WP_Error( 'fop_owner_only', __( 'Owners only.', 'fan-ownership' ), array( 'status' => rest_authorization_required_code() ) );
	}

	private static function rate_limit( $key, $per_minute = 20 ) {
		$user_id = get_current_user_id();
		$bucket  = 'fop_rl_' . $key . '_' . $user_id . '_' . gmdate( 'YmdHi' );
		$count   = (int) get_transient( $bucket );
		if ( $count >= $per_minute ) {
			return new WP_Error( 'fop_rate', __( 'Too many requests — slow down a little.', 'fan-ownership' ), array( 'status' => 429 ) );
		}
		set_transient( $bucket, $count + 1, 2 * MINUTE_IN_SECONDS );
		return true;
	}

	public static function routes() {
		$ns = 'fop/v1';

		// ---- Auth (FO-301) ----
		register_rest_route(
			$ns,
			'/auth/login',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $request ) {
					$user = wp_authenticate( sanitize_text_field( (string) $request['username'] ), (string) $request['password'] );
					if ( is_wp_error( $user ) ) {
						return new WP_Error( 'fop_login', __( 'Sign-in failed. Check your email and password.', 'fan-ownership' ), array( 'status' => 401 ) );
					}
					fop_touch_activity( $user->ID );
					return FOP_JWT::issue_pair( $user->ID );
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
					$claims = FOP_JWT::decode( (string) $request['refresh_token'], 'refresh' );
					if ( is_wp_error( $claims ) ) {
						return $claims;
					}
					return FOP_JWT::issue_pair( (int) $claims['sub'] );
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
					fop_touch_activity( $user->ID );
					return array(
						'id'               => $user->ID,
						'name'             => $user->display_name,
						'owner_number'     => FOP_Shares::owner_number( $user->ID ),
						'shares'           => fop_shares( $user->ID ),
						'max_shares'       => fop_max_shares(),
						'next_share_price' => fop_shares( $user->ID ) < fop_max_shares() ? fop_share_price( fop_shares( $user->ID ) + 1 ) : null,
						'is_owner'         => fop_is_owner( $user->ID ),
						'badges'           => FOP_Badges::member_badges( $user->ID ),
						'discounts'        => FOP_Shares::ticket_discounts( $user->ID ),
						'club'             => array(
							'name'    => fop_club_name(),
							'primary' => fop_setting( 'club_primary', '#1a1a2e' ),
							'accent'  => fop_setting( 'club_accent', '#e2b007' ),
						),
						'checkout_url'     => fop_setting( 'checkout_page_id' ) ? get_permalink( (int) fop_setting( 'checkout_page_id' ) ) : home_url(), // Apps link out (T33).
						'calendar_url'     => FOP_Meetings::member_ics_url( $user->ID ),
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
					$tokens   = array_filter( (array) get_user_meta( get_current_user_id(), 'fop_push_tokens', true ) );
					$tokens[] = sanitize_text_field( (string) $request['token'] );
					update_user_meta( get_current_user_id(), 'fop_push_tokens', array_slice( array_unique( $tokens ), -5 ) );
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
					foreach ( array_merge( FOP_Ballots::open_ballots(), FOP_Ballots::scheduled_ballots() ) as $ballot ) {
						$out[] = self::ballot_payload( $ballot->ID );
					}
					$closed = get_posts(
						array(
							'post_type'      => 'fop_ballot',
							'post_status'    => 'publish',
							'posts_per_page' => 20,
							'no_found_rows'  => true,
							'meta_key'       => '_fop_state',
							'meta_value'     => 'published',
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
					$result = FOP_Ballots::cast( (int) $request['id'], get_current_user_id(), (int) $request['choice'] );
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
									'status'          => get_post_meta( $idea->ID, '_fop_idea_status', true ),
									'supporters'      => count( (array) get_post_meta( $idea->ID, '_fop_supporters', true ) ),
									'threshold'       => FOP_Ideas::threshold_count(),
									'declined_reason' => get_post_meta( $idea->ID, '_fop_declined_reason', true ),
								);
							},
							get_posts(
								array(
									'post_type'      => 'fop_idea',
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
						$id = FOP_Ideas::submit( get_current_user_id(), (string) $request['title'], (string) $request['body'] );
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
				'callback'            => fn( WP_REST_Request $request ) => FOP_Ideas::toggle_support( (int) $request['id'], get_current_user_id() ),
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
					'callback'            => function () {
						return array_map(
							function ( $q ) {
								return array(
									'id'      => $q->ID,
									'title'   => $q->post_title,
									'answer'  => get_post_meta( $q->ID, '_fop_answer', true ),
									'video'   => get_post_meta( $q->ID, '_fop_video_answer', true ),
									'upvotes' => count( (array) get_post_meta( $q->ID, '_fop_upvotes', true ) ),
								);
							},
							get_posts(
								array(
									'post_type'      => 'fop_question',
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
						$limited = self::rate_limit( 'question', 5 );
						if ( is_wp_error( $limited ) ) {
							return $limited;
						}
						$id = FOP_Questions::submit( get_current_user_id(), (string) $request['title'], (string) $request['body'] );
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
				'callback'            => fn( WP_REST_Request $request ) => FOP_Questions::toggle_upvote( (int) $request['id'], get_current_user_id() ),
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
								'starts'    => get_post_meta( $meeting->ID, '_fop_meeting_start', true ),
								'stream'    => get_post_meta( $meeting->ID, '_fop_stream_embed', true ),
								'recording' => get_post_meta( $meeting->ID, '_fop_recording', true ),
								'rsvps'     => count( (array) get_post_meta( $meeting->ID, '_fop_attendees', true ) ),
								'is_agm'    => (bool) get_post_meta( $meeting->ID, '_fop_is_agm', true ),
							);
						},
						FOP_Meetings::upcoming( 20 )
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
				'callback'            => fn( WP_REST_Request $request ) => FOP_Meetings::toggle_rsvp( (int) $request['id'], get_current_user_id() ),
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
					fop_touch_activity();
					$uid = get_post_meta( $id, '_fop_stream_uid', true );
					return array(
						'id'       => $id,
						'title'    => get_the_title( $id ),
						'state'    => FOP_Match_Centre::live_state( $id ),
						'venue'    => get_post_meta( $id, '_fop_venue', true ),
						'score'    => get_post_meta( $id, '_fop_score', true ),
						'kickoff'  => get_post_meta( $id, '_fop_kickoff', true ),
						'playback' => $uid && fop_feature_on( 'streams' ) ? FOP_Media::playback_token( $uid, get_current_user_id() ) : null,
						'audio'    => get_post_meta( $id, '_fop_audio_url', true ),
						'ad_slots' => (array) get_post_meta( $id, '_fop_ad_slots', true ),
						'timeline' => FOP_Match_Centre::timeline( $id ),
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
					return FOP_Match_Centre::record_event(
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
					'callback'            => fn( WP_REST_Request $request ) => FOP_Chat::fetch( (string) $request['room'], (int) $request['since'], get_current_user_id() ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( __CLASS__, 'owner_permission' ),
					'callback'            => function ( WP_REST_Request $request ) {
							$limited = self::rate_limit( 'chat', 30 );
						if ( is_wp_error( $limited ) ) {
							return $limited;
						}
							return FOP_Chat::post_message( (string) $request['room'], get_current_user_id(), (string) $request['body'] );
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
						'post_type'      => 'fop_video',
						'post_status'    => 'publish',
						'posts_per_page' => 30,
						'no_found_rows'  => true,
					);
					if ( $request['type'] ) {
						$args['tax_query'] = array(
							array(
								'taxonomy' => 'fop_video_type',
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
							$uid = get_post_meta( $video->ID, '_fop_cf_uid', true );
							return array(
								'id'        => $video->ID,
								'title'     => $video->post_title,
								'types'     => wp_get_object_terms( $video->ID, 'fop_video_type', array( 'fields' => 'slugs' ) ),
								'playback'  => $uid ? FOP_Media::playback_token( $uid, get_current_user_id() ) : null,
								'media_url' => get_post_meta( $video->ID, '_fop_media_url', true ),
								'resume'    => FOP_Media::get_position( $video->ID, get_current_user_id() ),
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
					FOP_Media::save_position( (int) $request['id'], get_current_user_id(), (int) $request['seconds'] );
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
								'status'    => get_post_meta( $decision->ID, '_fop_decision_status', true ),
								'stalled'   => (bool) get_post_meta( $decision->ID, '_fop_stalled', true ),
								'updates'   => (array) get_post_meta( $decision->ID, '_fop_updates', true ),
								'board'     => (bool) get_post_meta( $decision->ID, '_fop_board_decision', true ),
								'reasoning' => get_post_meta( $decision->ID, '_fop_board_reasoning', true ),
							);
						},
						get_posts(
							array(
								'post_type'      => 'fop_decision',
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

	private static function ballot_payload( $ballot_id ) {
		$state   = FOP_Ballots::state( $ballot_id );
		$mine    = FOP_Ballots::member_choice( $ballot_id, get_current_user_id() );
		$payload = array(
			'id'                   => $ballot_id,
			'title'                => get_the_title( $ballot_id ),
			'body'                 => wp_strip_all_tags( get_post_field( 'post_content', $ballot_id ) ),
			'options'              => (array) get_post_meta( $ballot_id, '_fop_options', true ),
			'type'                 => get_post_meta( $ballot_id, '_fop_type', true ),
			'state'                => $state,
			'opens'                => get_post_meta( $ballot_id, '_fop_opens', true ),
			'closes'               => get_post_meta( $ballot_id, '_fop_closes', true ),
			'my_choice'            => $mine ? (int) $mine['choice'] : null,
			'my_weight'            => $mine ? (int) $mine['weight'] : fop_shares( get_current_user_id() ),
			'board_recommendation' => get_post_meta( $ballot_id, '_fop_board_recommendation', true ),
		);
		// FO-203: tallies only when permitted.
		$tallies = FOP_Ballots::tallies( $ballot_id );
		if ( ! is_wp_error( $tallies ) ) {
			$payload['tallies'] = $tallies;
			$payload['result']  = get_post_meta( $ballot_id, '_fop_result', true );
		} else {
			$payload['secret'] = true;
		}
		return $payload;
	}
}
