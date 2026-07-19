<?php
/**
 * Data columns on every content type's list screen. The posts store
 * structured data — ballots have state and turnout, matches have
 * kick-off and status, players have numbers — so the list tables show
 * that data instead of bare titles.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin list-table columns per post type, with sortable date/number
 * columns, so each list screen reads as the data table it really is.
 */
class PRX3_Admin_Columns {

	/**
	 * Meta keys behind the sortable columns, per orderby key.
	 *
	 * @var array
	 */
	private static $sortable = array(
		'prx3_closes'  => array( '_prx3_closes', 'meta_value' ),
		'prx3_start'   => array( '_prx3_meeting_start', 'meta_value' ),
		'prx3_kickoff' => array( '_prx3_kickoff', 'meta_value' ),
		'prx3_number'  => array( '_prx3_number', 'meta_value_num' ),
	);

	/**
	 * Hook column filters for every platform post type.
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		foreach ( array_keys( self::defs() ) as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'columns' ) );
			add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'cell' ), 10, 2 );
			add_filter( "manage_edit-{$type}_sortable_columns", array( __CLASS__, 'sortable_columns' ) );
		}
		add_action( 'pre_get_posts', array( __CLASS__, 'sort_query' ) );
	}

	/**
	 * Column definitions per type: key => [heading, renderer, sortable-orderby|''].
	 *
	 * @return array Definitions keyed by post type.
	 */
	private static function defs() {
		return array(
			'prx3_ballot'      => array(
				'prx3_state'   => array( __( 'State', 'fan-ownership' ), 'ballot_state', '' ),
				'prx3_kind'    => array( __( 'Type', 'fan-ownership' ), 'ballot_kind', '' ),
				'prx3_turnout' => array( __( 'Turnout', 'fan-ownership' ), 'ballot_turnout', '' ),
				'prx3_window'  => array( __( 'Closes', 'fan-ownership' ), 'ballot_window', 'prx3_closes' ),
			),
			'prx3_idea'        => array(
				'prx3_support' => array( __( 'Support', 'fan-ownership' ), 'idea_support', '' ),
			),
			'prx3_question'    => array(
				'prx3_answer' => array( __( 'Answered', 'fan-ownership' ), 'question_answered', '' ),
			),
			'prx3_meeting'     => array(
				'prx3_start' => array( __( 'Starts', 'fan-ownership' ), 'meeting_start', 'prx3_start' ),
				'prx3_rsvps' => array( __( 'RSVPs', 'fan-ownership' ), 'meeting_rsvps', '' ),
			),
			'prx3_document'    => array(
				'prx3_dtype' => array( __( 'Document type', 'fan-ownership' ), 'document_type', '' ),
			),
			'prx3_chapter'     => array(
				'prx3_city'    => array( __( 'City', 'fan-ownership' ), 'chapter_city', '' ),
				'prx3_lead'    => array( __( 'Lead', 'fan-ownership' ), 'chapter_lead', '' ),
				'prx3_members' => array( __( 'Members', 'fan-ownership' ), 'chapter_members', '' ),
				'prx3_cstatus' => array( __( 'Status', 'fan-ownership' ), 'chapter_status', '' ),
			),
			'prx3_board_vote'  => array(
				'prx3_voutcome' => array( __( 'Outcome', 'fan-ownership' ), 'board_vote_outcome', '' ),
				'prx3_vcast'    => array( __( 'Votes cast', 'fan-ownership' ), 'board_vote_cast', '' ),
			),
			'prx3_board_paper' => array(
				'prx3_released' => array( __( 'Transparency', 'fan-ownership' ), 'board_paper_released', '' ),
			),
			'prx3_video'       => array(
				'prx3_vtype'  => array( __( 'Type', 'fan-ownership' ), 'video_type', '' ),
				'prx3_teaser' => array( __( 'Teaser', 'fan-ownership' ), 'teaser_flag', '' ),
			),
			'prx3_exclusive'   => array(
				'prx3_teaser' => array( __( 'Teaser', 'fan-ownership' ), 'teaser_flag', '' ),
			),
			'prx3_decision'    => array(
				'prx3_decided' => array( __( 'Decided', 'fan-ownership' ), 'decision_decided', '' ),
				'prx3_status'  => array( __( 'Delivery', 'fan-ownership' ), 'decision_status', '' ),
			),
			'prx3_match'       => array(
				'prx3_kickoff'  => array( __( 'Kick-off', 'fan-ownership' ), 'match_kickoff', 'prx3_kickoff' ),
				'prx3_opponent' => array( __( 'Opponent', 'fan-ownership' ), 'match_opponent', '' ),
				'prx3_mstate'   => array( __( 'Status', 'fan-ownership' ), 'match_state', '' ),
				'prx3_potm'     => array( __( 'Player of the Match', 'fan-ownership' ), 'match_potm', '' ),
			),
			'prx3_forum_topic' => array(
				'prx3_board'   => array( __( 'Board', 'fan-ownership' ), 'topic_board', '' ),
				'prx3_replies' => array( __( 'Replies', 'fan-ownership' ), 'topic_replies', '' ),
				'prx3_origin'  => array( __( 'Origin', 'fan-ownership' ), 'topic_origin', '' ),
				'prx3_tballot' => array( __( 'Ballot', 'fan-ownership' ), 'topic_ballot', '' ),
			),
			'prx3_player'      => array(
				'prx3_number'   => array( __( 'No.', 'fan-ownership' ), 'player_number', 'prx3_number' ),
				'prx3_position' => array( __( 'Position', 'fan-ownership' ), 'player_position', '' ),
				'prx3_active'   => array( __( 'Active', 'fan-ownership' ), 'player_active', '' ),
				'prx3_wins'     => array( __( 'POTM wins', 'fan-ownership' ), 'player_wins', '' ),
			),
		);
	}

	/**
	 * Insert the data columns after the title column.
	 *
	 * @param array $columns Existing list-table columns.
	 * @return array Columns with the data columns added.
	 */
	public static function columns( $columns ) {
		$screen = get_current_screen();
		$defs   = self::defs()[ $screen ? $screen->post_type : '' ] ?? array();
		$out    = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				foreach ( $defs as $col => $def ) {
					$out[ $col ] = $def[0];
				}
			}
		}
		return $out;
	}

	/**
	 * Render one data cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post being listed.
	 */
	public static function cell( $column, $post_id ) {
		$defs = self::defs()[ get_post_type( $post_id ) ] ?? array();
		if ( isset( $defs[ $column ] ) ) {
			$method = $defs[ $column ][1];
			echo esc_html( self::$method( $post_id ) );
		}
	}

	/**
	 * Declare the sortable columns for the current screen.
	 *
	 * @param array $columns Sortable columns.
	 * @return array With the platform's date/number columns added.
	 */
	public static function sortable_columns( $columns ) {
		$screen = get_current_screen();
		foreach ( self::defs()[ $screen ? $screen->post_type : '' ] ?? array() as $col => $def ) {
			if ( $def[2] ) {
				$columns[ $col ] = $def[2];
			}
		}
		return $columns;
	}

	/**
	 * Apply meta sorting when a platform column heading is clicked.
	 *
	 * @param WP_Query $query The list-table query.
	 */
	public static function sort_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$orderby = (string) $query->get( 'orderby' );
		if ( isset( self::$sortable[ $orderby ] ) ) {
			$query->set( 'meta_key', self::$sortable[ $orderby ][0] );
			$query->set( 'orderby', self::$sortable[ $orderby ][1] );
		}
	}

	/* ---------------- Renderers ---------------- */

	/**
	 * Ballot lifecycle state.
	 *
	 * @param int $post_id Ballot.
	 * @return string
	 */
	private static function ballot_state( $post_id ) {
		$state = (string) get_post_meta( $post_id, '_prx3_state', true );
		return $state ? ucfirst( $state ) : __( 'Draft', 'fan-ownership' );
	}

	/**
	 * Standard or constitutional.
	 *
	 * @param int $post_id Ballot.
	 * @return string
	 */
	private static function ballot_kind( $post_id ) {
		return 'constitutional' === get_post_meta( $post_id, '_prx3_type', true ) ? __( 'Constitutional (75%)', 'fan-ownership' ) : __( 'Standard', 'fan-ownership' );
	}

	/**
	 * Members voted against the quorum requirement.
	 *
	 * @param int $post_id Ballot.
	 * @return string
	 */
	private static function ballot_turnout( $post_id ) {
		if ( ! get_post_meta( $post_id, '_prx3_state', true ) ) {
			return '—';
		}
		$tallies     = PRX3_Ballots::tallies( $post_id, true );
		$denominator = (int) get_post_meta( $post_id, '_prx3_quorum_denominator', true );
		$needed      = max( 1, (int) ceil( $denominator * (int) prx3_setting( 'quorum_percent', 25 ) / 100 ) );
		return sprintf( /* translators: 1: voted, 2: needed. */ __( '%1$d voted / %2$d needed', 'fan-ownership' ), (int) $tallies['members'], $needed );
	}

	/**
	 * Closing time.
	 *
	 * @param int $post_id Ballot.
	 * @return string
	 */
	private static function ballot_window( $post_id ) {
		$closes = (string) get_post_meta( $post_id, '_prx3_closes', true );
		return $closes ? prx3_format_datetime( $closes ) : '—';
	}

	/**
	 * Idea support against the auto-draft threshold.
	 *
	 * @param int $post_id Idea.
	 * @return string
	 */
	private static function idea_support( $post_id ) {
		$upvotes   = (int) get_post_meta( $post_id, '_prx3_upvotes', true );
		$threshold = max( 1, (int) ceil( prx3_active_owner_count() * (int) prx3_setting( 'idea_threshold_pct', 5 ) / 100 ) );
		return sprintf( /* translators: 1: votes, 2: threshold. */ __( '%1$d of %2$d for auto-ballot', 'fan-ownership' ), $upvotes, $threshold );
	}

	/**
	 * Answered timestamp or waiting state.
	 *
	 * @param int $post_id Question.
	 * @return string
	 */
	private static function question_answered( $post_id ) {
		$at = (string) get_post_meta( $post_id, '_prx3_answered_at', true );
		return $at ? prx3_format_datetime( $at ) : __( 'Awaiting answer', 'fan-ownership' );
	}

	/**
	 * Meeting start.
	 *
	 * @param int $post_id Meeting.
	 * @return string
	 */
	private static function meeting_start( $post_id ) {
		$start = (string) get_post_meta( $post_id, '_prx3_meeting_start', true );
		return $start ? prx3_format_datetime( $start ) : '—';
	}

	/**
	 * RSVP count, flagged when the meeting is the AGM.
	 *
	 * @param int $post_id Meeting.
	 * @return string
	 */
	private static function meeting_rsvps( $post_id ) {
		$count = count( array_filter( (array) get_post_meta( $post_id, '_prx3_attendees', true ) ) );
		return $count . ( get_post_meta( $post_id, '_prx3_is_agm', true ) ? ' — ' . __( 'AGM', 'fan-ownership' ) : '' );
	}

	/**
	 * Document type terms (monthly summary, annual accounts…).
	 *
	 * @param int $post_id Document.
	 * @return string
	 */
	private static function document_type( $post_id ) {
		$terms = get_the_terms( $post_id, 'prx3_document_type' );
		return $terms && ! is_wp_error( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '—';
	}

	/**
	 * Chapter home city.
	 *
	 * @param int $post_id Chapter.
	 * @return string
	 */
	private static function chapter_city( $post_id ) {
		$city = (string) get_post_meta( $post_id, '_prx3_chapter_city', true );
		return $city ? $city : '—';
	}

	/**
	 * Chapter lead's display name.
	 *
	 * @param int $post_id Chapter.
	 * @return string
	 */
	private static function chapter_lead( $post_id ) {
		$lead = get_userdata( (int) get_post_meta( $post_id, '_prx3_chapter_lead', true ) );
		return $lead ? $lead->display_name : '—';
	}

	/**
	 * Chapter member count.
	 *
	 * @param int $post_id Chapter.
	 * @return string
	 */
	private static function chapter_members( $post_id ) {
		return (string) count( array_filter( (array) get_post_meta( $post_id, '_prx3_chapter_members', true ) ) );
	}

	/**
	 * Chapter lifecycle: affirmed, lapsed, or derecognised.
	 *
	 * @param int $post_id Chapter.
	 * @return string
	 */
	private static function chapter_status( $post_id ) {
		if ( get_post_meta( $post_id, '_prx3_derecognised', true ) ) {
			return __( 'Derecognised', 'fan-ownership' );
		}
		if ( get_post_meta( $post_id, '_prx3_lapsed', true ) ) {
			return __( 'Lapsed', 'fan-ownership' );
		}
		$affirmed = (string) get_post_meta( $post_id, '_prx3_affirmed_at', true );
		return $affirmed ? sprintf( /* translators: %s date. */ __( 'Affirmed %s', 'fan-ownership' ), prx3_format_datetime( $affirmed ) ) : __( 'Awaiting affirmation', 'fan-ownership' );
	}

	/**
	 * Board vote outcome once every eligible director has voted.
	 *
	 * @param int $post_id Board vote.
	 * @return string
	 */
	private static function board_vote_outcome( $post_id ) {
		$outcome = get_post_meta( $post_id, '_prx3_board_outcome', true );
		if ( is_array( $outcome ) && ! empty( $outcome['outcome'] ) ) {
			$outcome = $outcome['outcome'];
		}
		return $outcome && is_string( $outcome ) ? ucfirst( $outcome ) : __( 'Open', 'fan-ownership' );
	}

	/**
	 * Directors who have voted so far.
	 *
	 * @param int $post_id Board vote.
	 * @return string
	 */
	private static function board_vote_cast( $post_id ) {
		return (string) count( array_filter( (array) get_post_meta( $post_id, '_prx3_board_votes', true ) ) );
	}

	/**
	 * Whether the paper has been released to the owner-facing register.
	 *
	 * @param int $post_id Board paper.
	 * @return string
	 */
	private static function board_paper_released( $post_id ) {
		return get_post_meta( $post_id, '_prx3_released_as', true ) ? __( 'Released to owners', 'fan-ownership' ) : __( 'Board only', 'fan-ownership' );
	}

	/**
	 * Video type terms.
	 *
	 * @param int $post_id Video.
	 * @return string
	 */
	private static function video_type( $post_id ) {
		$terms = get_the_terms( $post_id, 'prx3_video_type' );
		return $terms && ! is_wp_error( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '—';
	}

	/**
	 * Public-teaser flag.
	 *
	 * @param int $post_id Post.
	 * @return string
	 */
	private static function teaser_flag( $post_id ) {
		return get_post_meta( $post_id, '_prx3_teaser_public', true ) || get_post_meta( $post_id, '_prx3_teaser', true ) ? __( 'Public teaser', 'fan-ownership' ) : __( 'Members only', 'fan-ownership' );
	}

	/**
	 * Decision date.
	 *
	 * @param int $post_id Decision.
	 * @return string
	 */
	private static function decision_decided( $post_id ) {
		$at = (string) get_post_meta( $post_id, '_prx3_decided_at', true );
		return $at ? prx3_format_datetime( $at ) : '—';
	}

	/**
	 * Delivery state: on track or stalled.
	 *
	 * @param int $post_id Decision.
	 * @return string
	 */
	private static function decision_status( $post_id ) {
		return get_post_meta( $post_id, '_prx3_stalled', true ) ? __( 'Stalled — needs an update', 'fan-ownership' ) : __( 'On track', 'fan-ownership' );
	}

	/**
	 * Kick-off time.
	 *
	 * @param int $post_id Match.
	 * @return string
	 */
	private static function match_kickoff( $post_id ) {
		$kickoff = (string) get_post_meta( $post_id, '_prx3_kickoff', true );
		return $kickoff ? prx3_format_datetime( $kickoff ) : '—';
	}

	/**
	 * Opponent and venue.
	 *
	 * @param int $post_id Match.
	 * @return string
	 */
	private static function match_opponent( $post_id ) {
		$opponent = (string) get_post_meta( $post_id, '_prx3_opponent', true );
		$venue    = (string) get_post_meta( $post_id, '_prx3_venue', true );
		$label    = trim( $opponent . ( $venue ? ' (' . $venue . ')' : '' ) );
		return '' !== $label ? $label : '—';
	}

	/**
	 * Live pipeline state.
	 *
	 * @param int $post_id Match.
	 * @return string
	 */
	private static function match_state( $post_id ) {
		return ucfirst( PRX3_Match_Centre::live_state( $post_id ) );
	}

	/**
	 * The crowned player of the match.
	 *
	 * @param int $post_id Match.
	 * @return string
	 */
	private static function match_potm( $post_id ) {
		$winner = (int) get_post_meta( $post_id, '_prx3_potm_winner', true );
		return $winner ? get_the_title( $winner ) : '—';
	}

	/**
	 * Topic board terms.
	 *
	 * @param int $post_id Topic.
	 * @return string
	 */
	private static function topic_board( $post_id ) {
		$terms = get_the_terms( $post_id, 'prx3_forum_board' );
		return $terms && ! is_wp_error( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '—';
	}

	/**
	 * Approved reply count.
	 *
	 * @param int $post_id Topic.
	 * @return string
	 */
	private static function topic_replies( $post_id ) {
		return (string) (int) get_comments_number( $post_id );
	}

	/**
	 * Member-started or automated (and from what).
	 *
	 * @param int $post_id Topic.
	 * @return string
	 */
	private static function topic_origin( $post_id ) {
		$source = (string) get_post_meta( $post_id, '_prx3_source_key', true );
		return $source ? sprintf( /* translators: %s source key. */ __( 'Automated (%s)', 'fan-ownership' ), $source ) : __( 'Member', 'fan-ownership' );
	}

	/**
	 * The draft ballot this thread became, if converted.
	 *
	 * @param int $post_id Topic.
	 * @return string
	 */
	private static function topic_ballot( $post_id ) {
		$ballot = (int) get_post_meta( $post_id, '_prx3_ballot_id', true );
		return $ballot ? sprintf( /* translators: %d ballot id. */ __( 'Converted (#%d)', 'fan-ownership' ), $ballot ) : '—';
	}

	/**
	 * Squad number.
	 *
	 * @param int $post_id Player.
	 * @return string
	 */
	private static function player_number( $post_id ) {
		$number = get_post_meta( $post_id, '_prx3_number', true );
		return '' !== (string) $number ? (string) (int) $number : '—';
	}

	/**
	 * Playing position.
	 *
	 * @param int $post_id Player.
	 * @return string
	 */
	private static function player_position( $post_id ) {
		$position = (string) get_post_meta( $post_id, '_prx3_position', true );
		return $position ? $position : '—';
	}

	/**
	 * Active-roster flag.
	 *
	 * @param int $post_id Player.
	 * @return string
	 */
	private static function player_active( $post_id ) {
		return get_post_meta( $post_id, '_prx3_active', true ) ? __( 'Active', 'fan-ownership' ) : __( 'Not in polls', 'fan-ownership' );
	}

	/**
	 * Career player-of-the-match wins.
	 *
	 * @param int $post_id Player.
	 * @return string
	 */
	private static function player_wins( $post_id ) {
		return (string) count( array_filter( (array) get_post_meta( $post_id, '_prx3_potm_wins', true ) ) );
	}
}
