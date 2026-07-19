<?php
/**
 * Players and fan engagement voting: live Player of the Match during
 * each game, and Player of the Month.
 *
 * These are engagement polls, deliberately NOT the governance ballot
 * engine: one vote per member regardless of shares (who played well is
 * not a shareholding matter), results visible live, no quorum.
 *
 * POTM: opens when the match goes live, closes 30 minutes after the
 * final buzzer, winner announced by push and stored on the match.
 * Player of the Month: opens for the last 7 days of each calendar
 * month with the active roster as candidates; winner announced on the
 * 1st and archived.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Player roster and engagement voting: live Player of the Match during
 * each game and the monthly Player of the Month poll.
 */
class PRX3_Players {

	const POTM_CLOSE_AFTER = 30 * MINUTE_IN_SECONDS;

	/**
	 * Register the player post type, admin screens, REST routes and the
	 * poll-closing ticks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_type' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_prx3_player', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'prx3_commitments_tick', array( __CLASS__, 'monthly_tick' ) );
		add_action( 'prx3_ballot_tick', array( __CLASS__, 'close_due_potm' ) );
		add_filter( 'the_content', array( __CLASS__, 'single_content' ) );
	}

	/**
	 * The roster: public content (fans should meet the squad), players
	 * are club-managed, not user accounts.
	 */
	public static function register_type() {
		// When the club points the player features at an existing squad
		// table from another plugin, our built-in type is registered but
		// hidden — no second Players list, and any existing data stays
		// reachable in the database.
		$own = 'prx3_player' === prx3_player_post_type();
		register_post_type(
			'prx3_player',
			array(
				'public'          => $own,
				'show_ui'         => $own,
				'show_in_menu'    => $own ? 'prx3-owners' : false,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-id-alt',
				'has_archive'     => $own,
				'rewrite'         => $own ? array( 'slug' => 'squad' ) : false,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'labels'          => array(
					'name'          => __( 'Players', 'fan-ownership' ),
					'singular_name' => __( 'Player', 'fan-ownership' ),
				),
			)
		);
	}

	/**
	 * Player details meta box: squad number, position, active flag.
	 */
	public static function meta_box() {
		add_meta_box(
			'prx3_player_details',
			__( 'Player Details', 'fan-ownership' ),
			function ( $post ) {
				wp_nonce_field( 'prx3_player_meta', 'prx3_player_nonce' );
				echo '<p><label>' . esc_html__( 'Squad number', 'fan-ownership' ) . '</label> <input type="number" name="prx3_number" value="' . esc_attr( get_post_meta( $post->ID, '_prx3_number', true ) ) . '" min="0"></p>';
				echo '<p><label>' . esc_html__( 'Position', 'fan-ownership' ) . '</label> <input type="text" class="widefat" name="prx3_position" value="' . esc_attr( get_post_meta( $post->ID, '_prx3_position', true ) ) . '"></p>';
				echo '<p><label><input type="checkbox" name="prx3_active" ' . checked( get_post_meta( $post->ID, '_prx3_active', true ), '1', false ) . '> ' . esc_html__( 'Active squad member (appears in fan votes)', 'fan-ownership' ) . '</label></p>';
			},
			'prx3_player',
			'side',
			'high'
		);
	}

	/**
	 * Save the player details meta.
	 *
	 * @param int     $post_id Player post ID.
	 * @param WP_Post $post    Player post object.
	 */
	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['prx3_player_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['prx3_player_nonce'] ), 'prx3_player_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'prx3_edit_content' ) && ! current_user_can( 'prx3_admin' ) ) {
			return;
		}
		update_post_meta( $post_id, '_prx3_number', isset( $_POST['prx3_number'] ) ? absint( $_POST['prx3_number'] ) : 0 );
		update_post_meta( $post_id, '_prx3_position', isset( $_POST['prx3_position'] ) ? sanitize_text_field( wp_unslash( $_POST['prx3_position'] ) ) : '' );
		update_post_meta( $post_id, '_prx3_active', isset( $_POST['prx3_active'] ) ? '1' : '' );
	}

	/**
	 * The roster query for candidates. Our own type is filtered to the
	 * active flag; an external squad table lists all its published
	 * players (that plugin manages who is current).
	 *
	 * @return array get_posts() args.
	 */
	public static function candidate_query() {
		$args = array(
			'post_type'      => prx3_player_post_type(),
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( 'prx3_player' === prx3_player_post_type() ) {
			$args['meta_key']   = '_prx3_active'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded active-roster lookup, capped at 50.
			$args['meta_value'] = '1'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
		return $args;
	}

	/**
	 * Whether a post ID is a valid, votable player of the current squad
	 * source: right post type, and (for our own type) flagged active.
	 *
	 * @param int $player_id Candidate post ID.
	 * @return bool
	 */
	public static function is_votable_player( $player_id ) {
		$type = prx3_player_post_type();
		if ( $type !== get_post_type( $player_id ) ) {
			return false;
		}
		return 'prx3_player' !== $type || (bool) get_post_meta( $player_id, '_prx3_active', true );
	}

	/**
	 * The active roster as vote candidates.
	 *
	 * @return array[]
	 */
	public static function candidates() {
		return array_map(
			function ( $player ) {
				return array(
					'id'       => $player->ID,
					'name'     => $player->post_title,
					'number'   => (int) get_post_meta( $player->ID, '_prx3_number', true ),
					'position' => get_post_meta( $player->ID, '_prx3_position', true ),
					'photo'    => get_the_post_thumbnail_url( $player, 'medium' ),
				);
			},
			get_posts( self::candidate_query() )
		);
	}

	/* ---------------- Player of the Match ---------------- */

	/**
	 * POTM is open from the moment the match is live until 30 minutes
	 * after it is marked ended.
	 *
	 * @param int $match_id Match post ID.
	 * @return bool
	 */
	public static function potm_open( $match_id ) {
		$state = PRX3_Match_Centre::live_state( $match_id );
		if ( 'live' === $state ) {
			return true;
		}
		if ( 'ended' === $state ) {
			$ended_at = (int) get_post_meta( $match_id, '_prx3_ended_at', true );
			return $ended_at && ( time() - $ended_at ) < self::POTM_CLOSE_AFTER;
		}
		return false;
	}

	/**
	 * Cast/revise a POTM vote: one per member, live results.
	 *
	 * @param int $match_id  Match post ID.
	 * @param int $user_id   Voting member's user ID.
	 * @param int $player_id Player post ID voted for.
	 * @return array|WP_Error
	 */
	public static function potm_vote( $match_id, $user_id, $player_id ) {
		if ( 'prx3_match' !== get_post_type( $match_id ) ) {
			return new WP_Error( 'prx3_match', __( 'Match not found.', 'fan-ownership' ) );
		}
		if ( ! prx3_is_owner( $user_id ) ) {
			return new WP_Error( 'prx3_owner_only', __( 'Only owners can vote for player of the match.', 'fan-ownership' ) );
		}
		if ( ! self::potm_open( $match_id ) ) {
			return new WP_Error( 'prx3_closed', __( 'Player of the match voting is not open.', 'fan-ownership' ) );
		}
		if ( ! self::is_votable_player( $player_id ) ) {
			return new WP_Error( 'prx3_player', __( 'That player is not on the ballot.', 'fan-ownership' ) );
		}
		$votes             = (array) get_post_meta( $match_id, '_prx3_potm_votes', true );
		$votes[ $user_id ] = (int) $player_id;
		update_post_meta( $match_id, '_prx3_potm_votes', $votes );
		prx3_touch_activity( $user_id );
		return self::potm_results( $match_id, $user_id );
	}

	/**
	 * Live POTM tallies (engagement poll — results visible while open).
	 *
	 * @param int $match_id Match post ID.
	 * @param int $user_id  Optional. Requesting user ID, for 'my_vote'. Default 0.
	 * @return array
	 */
	public static function potm_results( $match_id, $user_id = 0 ) {
		$votes = (array) get_post_meta( $match_id, '_prx3_potm_votes', true );
		$tally = array_count_values( array_map( 'intval', $votes ) );
		arsort( $tally );
		$out = array(
			'open'    => self::potm_open( $match_id ),
			'winner'  => (int) get_post_meta( $match_id, '_prx3_potm_winner', true ),
			'my_vote' => isset( $votes[ $user_id ] ) ? (int) $votes[ $user_id ] : null,
			'total'   => count( $votes ),
			'tally'   => array(),
		);
		foreach ( $tally as $player_id => $count ) {
			$out['tally'][] = array(
				'player' => $player_id,
				'name'   => get_the_title( $player_id ),
				'votes'  => $count,
			);
		}
		return $out;
	}

	/**
	 * Close POTM polls whose window has passed: crown the winner, push
	 * the announcement, record it for the season stats. Rides the
	 * 5-minute ballot tick.
	 */
	public static function close_due_potm() {
		$matches = get_posts(
			array(
				'post_type'      => 'prx3_match',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded poll-closing sweep on the 5-minute tick, capped at 10 matches.
					array(
						'key'   => '_prx3_ended',
						'value' => '1',
					),
					array(
						'key'     => '_prx3_potm_winner',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		foreach ( $matches as $match ) {
			if ( self::potm_open( $match->ID ) ) {
				continue; // Window still open.
			}
			$votes = (array) get_post_meta( $match->ID, '_prx3_potm_votes', true );
			if ( ! $votes ) {
				update_post_meta( $match->ID, '_prx3_potm_winner', 0 );
				continue;
			}
			$tally = array_count_values( array_map( 'intval', $votes ) );
			arsort( $tally );
			$winner = (int) array_key_first( $tally );
			update_post_meta( $match->ID, '_prx3_potm_winner', $winner );
			$wins   = (array) get_post_meta( $winner, '_prx3_potm_wins', true );
			$wins[] = array(
				'match' => $match->ID,
				'date'  => prx3_now(),
				'votes' => $tally[ $winner ],
			);
			update_post_meta( $winner, '_prx3_potm_wins', $wins );
			PRX3_Comms::push(
				array(),
				__( 'Player of the Match', 'fan-ownership' ),
				sprintf(
					/* translators: 1: player, 2: match. */
					__( 'Your player of the match: %1$s (%2$s)', 'fan-ownership' ),
					get_the_title( $winner ),
					get_the_title( $match->ID )
				),
				'match',
				get_permalink( $match->ID )
			);
			PRX3_Audit::log( 'potm_result', sprintf( 'POTM for match %d: player %d', $match->ID, $winner ) );
		}
	}

	/* ---------------- Player of the Month ---------------- */

	/**
	 * The month poll is open during the last 7 days of each month.
	 */
	public static function month_poll_open() {
		return (int) gmdate( 'j' ) > (int) gmdate( 't' ) - 7;
	}

	/**
	 * Key for the current month's poll.
	 *
	 * @return string 'Y-m' month key.
	 */
	public static function month_key() {
		return gmdate( 'Y-m' );
	}

	/**
	 * Cast/revise a Player of the Month vote: one per member.
	 *
	 * @param int $user_id   Voting member's user ID.
	 * @param int $player_id Player post ID voted for.
	 * @return array|WP_Error Month results on success.
	 */
	public static function month_vote( $user_id, $player_id ) {
		if ( ! prx3_is_owner( $user_id ) ) {
			return new WP_Error( 'prx3_owner_only', __( 'Only owners can vote for player of the month.', 'fan-ownership' ) );
		}
		if ( ! self::month_poll_open() ) {
			return new WP_Error( 'prx3_closed', __( 'Player of the month voting opens in the last week of the month.', 'fan-ownership' ) );
		}
		if ( ! self::is_votable_player( $player_id ) ) {
			return new WP_Error( 'prx3_player', __( 'That player is not on the ballot.', 'fan-ownership' ) );
		}
		$key               = 'prx3_potm_month_' . self::month_key();
		$votes             = (array) get_option( $key, array() );
		$votes[ $user_id ] = (int) $player_id;
		update_option( $key, $votes, false );
		prx3_touch_activity( $user_id );
		return self::month_results( $user_id );
	}

	/**
	 * Live Player of the Month tallies plus the winners archive.
	 *
	 * @param int $user_id Optional. Requesting user ID, for 'my_vote'. Default 0.
	 * @return array
	 */
	public static function month_results( $user_id = 0 ) {
		$votes = (array) get_option( 'prx3_potm_month_' . self::month_key(), array() );
		$tally = array_count_values( array_map( 'intval', $votes ) );
		arsort( $tally );
		$history = (array) get_option( 'prx3_potm_month_winners', array() );
		$out     = array(
			'open'    => self::month_poll_open(),
			'month'   => self::month_key(),
			'my_vote' => isset( $votes[ $user_id ] ) ? (int) $votes[ $user_id ] : null,
			'total'   => count( $votes ),
			'tally'   => array(),
			'history' => $history,
		);
		foreach ( $tally as $player_id => $count ) {
			$out['tally'][] = array(
				'player' => $player_id,
				'name'   => get_the_title( $player_id ),
				'votes'  => $count,
			);
		}
		return $out;
	}

	/**
	 * On the daily tick: if a finished month has unprocessed votes,
	 * crown and announce the winner and archive the result.
	 */
	public static function monthly_tick() {
		$last_month = gmdate( 'Y-m', strtotime( 'first day of last month' ) );
		$key        = 'prx3_potm_month_' . $last_month;
		$votes      = (array) get_option( $key, array() );
		$history    = (array) get_option( 'prx3_potm_month_winners', array() );
		if ( ! $votes || isset( $history[ $last_month ] ) ) {
			return;
		}
		$tally = array_count_values( array_map( 'intval', $votes ) );
		arsort( $tally );
		$winner                 = (int) array_key_first( $tally );
		$history[ $last_month ] = array(
			'player' => $winner,
			'name'   => get_the_title( $winner ),
			'votes'  => $tally[ $winner ],
			'total'  => count( $votes ),
		);
		update_option( 'prx3_potm_month_winners', $history, false );
		PRX3_Comms::push(
			array(),
			__( 'Player of the Month', 'fan-ownership' ),
			sprintf(
				/* translators: 1: player, 2: month. */
				__( 'Your player of the month for %2$s: %1$s', 'fan-ownership' ),
				get_the_title( $winner ),
				date_i18n( 'F Y', strtotime( $last_month . '-01' ) )
			),
			'match',
			home_url( '/squad/' )
		);
		PRX3_Audit::log( 'potm_month_result', sprintf( 'Player of the month %s: player %d', $last_month, $winner ) );
	}

	/* ---------------- REST ---------------- */

	/**
	 * Register the POTM and Player of the Month routes (owners only).
	 */
	public static function routes() {
		$owner_only = function () {
			return prx3_is_owner() ? true : new WP_Error( 'prx3_owner_only', __( 'Owners only.', 'fan-ownership' ), array( 'status' => rest_authorization_required_code() ) );
		};
		register_rest_route(
			'prx3/v1',
			'/matches/(?P<id>\d+)/potm',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $owner_only,
					'callback'            => fn( WP_REST_Request $request ) => array_merge(
						self::potm_results( (int) $request['id'], get_current_user_id() ),
						array( 'candidates' => self::candidates() )
					),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $owner_only,
					'callback'            => fn( WP_REST_Request $request ) => self::potm_vote( (int) $request['id'], get_current_user_id(), (int) $request['player'] ),
				),
			)
		);
		register_rest_route(
			'prx3/v1',
			'/potm-month',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $owner_only,
					'callback'            => fn() => array_merge( self::month_results( get_current_user_id() ), array( 'candidates' => self::candidates() ) ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $owner_only,
					'callback'            => fn( WP_REST_Request $request ) => self::month_vote( get_current_user_id(), (int) $request['player'] ),
				),
			)
		);
	}
	/**
	 * A player's own page shows their number, position, and honours
	 * (block-theme safe: keyed off the queried post).
	 *
	 * @param string $content Post content.
	 * @return string Content plus the player card.
	 */
	public static function single_content( $content ) {
		if ( is_admin() || ! function_exists( 'is_singular' ) || ! is_singular( prx3_player_post_type() ) ) {
			return $content;
		}
		$post_id = get_queried_object_id();
		if ( get_the_ID() && (int) get_the_ID() !== (int) $post_id ) {
			return $content;
		}
		$number   = (int) get_post_meta( $post_id, '_prx3_number', true );
		$position = (string) get_post_meta( $post_id, '_prx3_position', true );
		$potm     = (int) get_post_meta( $post_id, '_prx3_wins', true );
		$months   = (int) get_post_meta( $post_id, '_prx3_month_wins', true );
		$html     = '<div class="prx3-player-card"><p>';
		if ( $number ) {
			$html .= '<strong>#' . (int) $number . '</strong> ';
		}
		$html .= esc_html( $position ) . '</p>';
		if ( $potm || $months ) {
			$html .= '<p class="prx3-player-honours">' . esc_html( trim( ( $potm ? sprintf( /* translators: %d wins. */ _n( '%d Player of the Match award', '%d Player of the Match awards', $potm, 'fan-ownership' ), $potm ) : '' ) . ( $months ? ' · ' . sprintf( /* translators: %d wins. */ _n( '%d Player of the Month', '%d Players of the Month', $months, 'fan-ownership' ), $months ) : '' ), ' ·' ) ) . '</p>';
		}
		return $content . $html . '</div>';
	}
}
