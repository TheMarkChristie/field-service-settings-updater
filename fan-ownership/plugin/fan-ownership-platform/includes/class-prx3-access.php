<?php
/**
 * Access gating: owner-only areas, teaser layer, board workspace guard.
 *
 * FO-101 (member gating), FO-226 AC1 (board isolation), P36 (paywall line).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Access {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'guard' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'hide_from_search_and_feeds' ) );
		add_filter( 'the_content', array( __CLASS__, 'gate_content' ), 5 );
		add_filter( 'the_excerpt', array( __CLASS__, 'gate_excerpt' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'track_view_activity' ), 20 );
	}

	/**
	 * Can this user open board workspace material? Board members only —
	 * observers via explicit per-item grants (FO-228), and a single
	 * audited break-glass path for administrators.
	 *
	 * @param int      $post_id Board post.
	 * @param int|null $user_id User, default current.
	 * @return bool
	 */
	public static function can_access_board_item( $post_id, $user_id = null ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		// Recusal lockout (FO-227 AC3).
		$recused = get_post_meta( $post_id, '_prx3_recused_users', true );
		if ( is_array( $recused ) && in_array( $user_id, array_map( 'intval', $recused ), true ) ) {
			return false;
		}
		if ( user_can( $user_id, 'prx3_board' ) ) {
			return true;
		}
		// Observer grant, per item, time-boxed (FO-228 AC2).
		if ( PRX3_Board::observer_has_access( $post_id, $user_id ) ) {
			return true;
		}
		// Break-glass: full admins may enter, always audited (FO-226 AC1).
		if ( user_can( $user_id, 'manage_options' ) ) {
			PRX3_Audit::log( 'board_break_glass', sprintf( 'Admin %d accessed board item %d', $user_id, $post_id ), array( 'post' => $post_id ) );
			return true;
		}
		return false;
	}

	/**
	 * Redirect non-members away from owner areas; block non-board from
	 * board material entirely.
	 */
	public static function guard() {
		// Board workspace: hard wall.
		if ( is_singular( prx3_board_post_types() ) ) {
			if ( ! self::can_access_board_item( get_queried_object_id() ) ) {
				wp_die( esc_html__( 'This area is restricted.', 'fan-ownership' ), 403 );
			}
			return;
		}
		if ( prx3_is_owner() ) {
			return;
		}
		if ( is_singular( prx3_gated_post_types() ) || is_post_type_archive( prx3_gated_post_types() ) || is_tax( array( 'prx3_video_type', 'prx3_document_type' ) ) ) {
			// Teaser exception: a gated item may expose its public teaser page (P36).
			if ( is_singular() && get_post_meta( get_queried_object_id(), '_prx3_teaser_public', true ) ) {
				return; // Content itself is still gated by gate_content().
			}
			wp_safe_redirect( self::join_url() );
			exit;
		}
	}

	/**
	 * The join page (owner-facing explanation + login), FO-101 AC1.
	 *
	 * @return string
	 */
	public static function join_url() {
		$page_id = (int) prx3_setting( 'join_page_id', 0 );
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return get_permalink( $page_id );
		}
		return wp_login_url( home_url( add_query_arg( array() ) ) );
	}

	/**
	 * Owner-only material never appears in search/feeds for outsiders (FO-101 AC2).
	 *
	 * @param WP_Query $query Query.
	 */
	public static function hide_from_search_and_feeds( $query ) {
		if ( is_admin() ) {
			return;
		}
		// Board content is excluded from every front-end query, member or not.
		if ( $query->is_main_query() || $query->is_search() || $query->is_feed() ) {
			$not_in = prx3_board_post_types();
			if ( ! prx3_is_owner() && ( $query->is_search() || $query->is_feed() ) ) {
				$not_in = array_merge( $not_in, prx3_gated_post_types() );
			}
			$post_type = (array) $query->get( 'post_type' );
			if ( $post_type && array_intersect( $post_type, $not_in ) ) {
				$query->set( 'post_type', array_values( array_diff( $post_type, $not_in ) ) );
			} elseif ( $query->is_search() || $query->is_feed() ) {
				$searchable = get_post_types( array( 'exclude_from_search' => false ) );
				$query->set( 'post_type', array_values( array_diff( $searchable, $not_in ) ) );
			}
		}
	}

	/**
	 * Body-level gate: non-members see the teaser (if any) plus a join
	 * prompt, never the content.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function gate_content( $content ) {
		$type = get_post_type();
		if ( in_array( $type, prx3_board_post_types(), true ) && ! self::can_access_board_item( get_the_ID() ) ) {
			return '';
		}
		if ( in_array( $type, prx3_gated_post_types(), true ) && ! prx3_is_owner() ) {
			$teaser = get_post_meta( get_the_ID(), '_prx3_teaser', true );
			$html   = $teaser ? '<div class="prx3-teaser">' . wp_kses_post( wpautop( $teaser ) ) . '</div>' : '';
			$html  .= '<div class="prx3-notice prx3-notice--join"><p>'
				. esc_html(
					sprintf(
					/* translators: %s club name. */
						__( 'The full version is exclusive to %s owners.', 'fan-ownership' ),
						prx3_club_name()
					)
				)
				. '</p><p><a class="prx3-button" href="' . esc_url( self::join_url() ) . '">'
				. esc_html__( 'Become an owner', 'fan-ownership' ) . '</a> <a class="prx3-button prx3-button--secondary" href="'
				. esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in', 'fan-ownership' ) . '</a></p></div>';
			return $html;
		}
		return $content;
	}

	/**
	 * Excerpts obey the same wall.
	 *
	 * @param string $excerpt Excerpt.
	 * @return string
	 */
	public static function gate_excerpt( $excerpt ) {
		if ( in_array( get_post_type(), array_merge( prx3_gated_post_types(), prx3_board_post_types() ), true ) && ! prx3_is_owner() ) {
			return esc_html__( 'Owners-only content.', 'fan-ownership' );
		}
		return $excerpt;
	}

	/**
	 * Viewing owner content counts as activity (P76).
	 */
	public static function track_view_activity() {
		if ( prx3_is_owner() && is_singular( prx3_gated_post_types() ) ) {
			prx3_touch_activity();
		}
	}
}
