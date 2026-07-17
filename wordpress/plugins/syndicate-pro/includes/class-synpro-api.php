<?php
/**
 * Mobile app REST API (namespace synpro/v1).
 *
 * - GET  /feed         Blog content for the app. Logged-in users (via core
 *                      Application Passwords) get posts from the categories
 *                      they selected; anonymous users get everything, but
 *                      only the last 5 days.
 * - GET  /categories   Category list for the app's picker.
 * - GET  /preferences  The logged-in user's selected category IDs.
 * - POST /preferences  Save the logged-in user's selected category IDs.
 *
 * @package Syndicate_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Synpro_Api' ) ) :

class Synpro_Api {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'synpro/v1',
			'/feed',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'feed' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'     => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
					'per_page' => array( 'default' => 20, 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			'synpro/v1',
			'/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'categories' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'synpro/v1',
			'/preferences',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_preferences' ),
					'permission_callback' => 'is_user_logged_in',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'set_preferences' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);
	}

	/**
	 * Blog feed for the app.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function feed( $request ) {
		$paged = max( 1, (int) $request['page'] );
		$args  = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => min( 50, max( 1, (int) $request['per_page'] ) ),
			'paged'               => $paged,
			// Stickies would be prepended on page 1 (breaking the page size),
			// duplicated on later pages, and bypass the anonymous date window.
			'ignore_sticky_posts' => true,
		);

		if ( is_user_logged_in() ) {
			$cats = array_filter( array_map( 'absint', (array) get_user_meta( get_current_user_id(), 'synpro_app_cats', true ) ) );
			if ( $cats ) {
				$args['category__in'] = $cats;
			}
		} else {
			// Anonymous: everything, but only the last 5 days.
			$args['date_query'] = array( array( 'after' => '5 days ago' ) );
		}

		$query = new WP_Query( $args );
		$items = array();
		// Set up real post context per item so the_content filters that rely
		// on get_the_ID() — including the paid-content disclosure — apply.
		global $post;
		foreach ( $query->posts as $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $post );
			$items[] = self::format_post( $post );
		}
		wp_reset_postdata();

		return rest_ensure_response(
			array(
				'items'       => $items,
				'page'        => $paged,
				'total_pages' => (int) $query->max_num_pages,
				'logged_in'   => is_user_logged_in(),
			)
		);
	}

	/**
	 * One post in the app's shape.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	protected static function format_post( $post ) {
		$categories = array();
		foreach ( (array) get_the_category( $post->ID ) as $category ) {
			$categories[] = array(
				'id'   => (int) $category->term_id,
				'name' => $category->name,
			);
		}

		return array(
			'id'         => (int) $post->ID,
			'title'      => wp_strip_all_tags( get_the_title( $post ) ),
			'excerpt'    => wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), 40 ),
			'content'    => apply_filters( 'the_content', $post->post_content ),
			'date'       => get_post_time( 'c', true, $post ),
			'link'       => get_permalink( $post ),
			'source_url' => (string) get_post_meta( $post->ID, '_synpro_source_url', true ),
			'image'      => (string) get_the_post_thumbnail_url( $post, 'large' ),
			'author'     => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'categories' => $categories,
		);
	}

	/**
	 * Category list for the app.
	 *
	 * @return WP_REST_Response
	 */
	public static function categories() {
		$out = array();
		foreach ( get_categories( array( 'hide_empty' => true ) ) as $category ) {
			$out[] = array(
				'id'    => (int) $category->term_id,
				'name'  => $category->name,
				'count' => isset( $category->count ) ? (int) $category->count : 0,
			);
		}
		return rest_ensure_response( $out );
	}

	/**
	 * The logged-in user's selected categories.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_preferences() {
		$cats = array_filter( array_map( 'absint', (array) get_user_meta( get_current_user_id(), 'synpro_app_cats', true ) ) );
		return rest_ensure_response( array( 'categories' => array_values( $cats ) ) );
	}

	/**
	 * Save the logged-in user's selected categories.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function set_preferences( $request ) {
		$cats = array_filter( array_map( 'absint', (array) $request['categories'] ) );
		update_user_meta( get_current_user_id(), 'synpro_app_cats', array_values( $cats ) );
		return rest_ensure_response( array( 'categories' => array_values( $cats ) ) );
	}
}

endif;
