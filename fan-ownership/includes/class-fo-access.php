<?php
/**
 * Content restriction: only fan owners can view portal content.
 *
 * @package FanOwnership
 */

defined( 'ABSPATH' ) || exit;

class FO_Access {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'guard_singular_and_archives' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'hide_from_search_and_feeds' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 5 );
		add_filter( 'the_excerpt', array( __CLASS__, 'filter_excerpt' ), 5 );
	}

	/**
	 * Send non-members who open a portal URL to the join/login page.
	 */
	public static function guard_singular_and_archives() {
		if ( fo_user_can_access() ) {
			return;
		}
		$is_portal = is_singular( fo_post_types() ) || is_post_type_archive( fo_post_types() ) || is_tax( array( 'fo_video_type', 'fo_document_type' ) );
		if ( ! $is_portal ) {
			return;
		}
		global $wp;
		$requested = home_url( add_query_arg( array(), $wp->request ) );
		wp_safe_redirect( fo_join_url( $requested ) );
		exit;
	}

	/**
	 * Keep portal content out of site search and feeds for non-members.
	 *
	 * @param WP_Query $query The query being run.
	 */
	public static function hide_from_search_and_feeds( $query ) {
		if ( is_admin() || fo_user_can_access() ) {
			return;
		}
		if ( ! $query->is_search() && ! $query->is_feed() ) {
			return;
		}
		$post_type = $query->get( 'post_type' );
		if ( empty( $post_type ) ) {
			$searchable = get_post_types( array( 'exclude_from_search' => false ) );
			$query->set( 'post_type', array_values( array_diff( $searchable, fo_post_types() ) ) );
		} elseif ( array_intersect( (array) $post_type, fo_post_types() ) ) {
			$query->set( 'post_type', array_values( array_diff( (array) $post_type, fo_post_types() ) ) );
		}
	}

	/**
	 * Belt and braces: never render portal content bodies to non-members.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( in_array( get_post_type(), fo_post_types(), true ) && ! fo_user_can_access() ) {
			return fo_members_only_notice();
		}
		return $content;
	}

	/**
	 * Same for excerpts (some themes print excerpts in listings).
	 *
	 * @param string $excerpt Post excerpt.
	 * @return string
	 */
	public static function filter_excerpt( $excerpt ) {
		if ( in_array( get_post_type(), fo_post_types(), true ) && ! fo_user_can_access() ) {
			return esc_html__( 'Members-only content.', 'fan-ownership' );
		}
		return $excerpt;
	}
}
