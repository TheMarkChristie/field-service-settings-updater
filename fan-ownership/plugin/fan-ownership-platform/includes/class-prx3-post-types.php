<?php
/**
 * Post types and taxonomies for the whole platform.
 *
 * Owner-gated: ballots, ideas, questions, meetings, videos, documents,
 * exclusive posts, decisions, chapters, matches.
 * Board-only: board meetings, papers, threads, votes, vault documents.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers every custom post type and taxonomy the platform uses,
 * owner-gated and board-only alike, on a shared gated base.
 */
class PRX3_Post_Types {

	/**
	 * Hook post type registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_all' ) );
	}

	/**
	 * Register all post types and taxonomies. Also called on activation
	 * before the rewrite flush.
	 */
	public static function register_all() {
		$gated_base = array(
			'public'          => true,
			'show_ui'         => true,
			'show_in_rest'    => false, // App access goes through the authenticated prx3/v1 API only.
			'has_archive'     => true,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'comments' ),
		);

		$types = array(
			'prx3_ballot'    => array( __( 'Ballot', 'fan-ownership' ), __( 'Ballots', 'fan-ownership' ), 'dashicons-yes-alt', 'ballot' ),
			'prx3_idea'      => array( __( 'Idea', 'fan-ownership' ), __( 'Ideas', 'fan-ownership' ), 'dashicons-lightbulb', 'idea' ),
			'prx3_question'  => array( __( 'Question', 'fan-ownership' ), __( 'Questions', 'fan-ownership' ), 'dashicons-format-chat', 'question' ),
			'prx3_meeting'   => array( __( 'Meeting', 'fan-ownership' ), __( 'Meetings', 'fan-ownership' ), 'dashicons-calendar-alt', 'meeting' ),
			'prx3_video'     => array( __( 'Video', 'fan-ownership' ), __( 'Video Library', 'fan-ownership' ), 'dashicons-video-alt3', 'video' ),
			'prx3_document'  => array( __( 'Document', 'fan-ownership' ), __( 'Documents', 'fan-ownership' ), 'dashicons-media-document', 'document' ),
			'prx3_exclusive' => array( __( 'Exclusive Post', 'fan-ownership' ), __( 'Behind the Scenes', 'fan-ownership' ), 'dashicons-hidden', 'exclusive' ),
			'prx3_decision'  => array( __( 'Decision', 'fan-ownership' ), __( 'Decision Register', 'fan-ownership' ), 'dashicons-clipboard', 'decision' ),
			'prx3_chapter'   => array( __( 'Chapter', 'fan-ownership' ), __( 'Owner Chapters', 'fan-ownership' ), 'dashicons-location-alt', 'chapter' ),
			'prx3_match'     => array( __( 'Match', 'fan-ownership' ), __( 'Match Centre', 'fan-ownership' ), 'dashicons-flag', 'match' ),
		);

		foreach ( $types as $slug => $def ) {
			register_post_type(
				$slug,
				array_merge(
					$gated_base,
					array(
						'labels'    => array(
							'name'          => $def[1],
							'singular_name' => $def[0],
						),
						'menu_icon' => $def[2],
						'rewrite'   => array( 'slug' => 'owners/' . $def[3] ),
					)
				)
			);
		}

		// Board-only types: no public surface whatsoever (FO-226 AC1).
		foreach ( prx3_board_post_types() as $board_type ) {
			register_post_type(
				$board_type,
				array(
					'public'          => false,
					'show_ui'         => true,
					'show_in_rest'    => false,
					'show_in_menu'    => 'prx3-board',
					'capability_type' => 'post',
					'map_meta_cap'    => true,
					'capabilities'    => array(
						'edit_post'          => 'prx3_board',
						'read_post'          => 'prx3_board',
						'delete_post'        => 'prx3_board',
						'edit_posts'         => 'prx3_board',
						'edit_others_posts'  => 'prx3_board',
						'publish_posts'      => 'prx3_board',
						'read_private_posts' => 'prx3_board',
					),
					'labels'          => array( 'name' => ucwords( str_replace( array( 'prx3_', '_' ), array( '', ' ' ), $board_type ) ) ),
					'supports'        => array( 'title', 'editor', 'author' ),
				)
			);
		}

		register_taxonomy(
			'prx3_video_type',
			'prx3_video',
			array(
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'labels'            => array( 'name' => __( 'Video Types', 'fan-ownership' ) ),
				'rewrite'           => array( 'slug' => 'owners/video-type' ),
			)
		);
		register_taxonomy(
			'prx3_document_type',
			'prx3_document',
			array(
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'labels'            => array( 'name' => __( 'Document Types', 'fan-ownership' ) ),
				'rewrite'           => array( 'slug' => 'owners/document-type' ),
			)
		);
	}
}
