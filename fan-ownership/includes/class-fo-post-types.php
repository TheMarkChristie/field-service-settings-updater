<?php
/**
 * Custom post types and taxonomies for the portal.
 *
 * @package FanOwnership
 */

defined( 'ABSPATH' ) || exit;

class FO_Post_Types {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'register_default_terms' ), 20 );
	}

	public static function register() {
		$base = array(
			'public'          => true,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => false, // Classic editor + keeps members-only content out of the public REST API.
			'menu_icon'       => 'dashicons-groups',
			'has_archive'     => true,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' ),
		);

		register_post_type(
			'fo_video',
			array_merge(
				$base,
				array(
					'labels'      => self::labels( __( 'Club Video', 'fan-ownership' ), __( 'Club Videos', 'fan-ownership' ) ),
					'menu_icon'   => 'dashicons-video-alt3',
					'rewrite'     => array( 'slug' => 'club-video' ),
					'description' => __( 'Members-only video: full match replays, training sessions, post-match interviews and behind the scenes.', 'fan-ownership' ),
				)
			)
		);

		register_taxonomy(
			'fo_video_type',
			'fo_video',
			array(
				'labels'            => array(
					'name'          => __( 'Video Types', 'fan-ownership' ),
					'singular_name' => __( 'Video Type', 'fan-ownership' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'rewrite'           => array( 'slug' => 'video-type' ),
			)
		);

		register_post_type(
			'fo_meeting',
			array_merge(
				$base,
				array(
					'labels'    => self::labels( __( 'Meeting', 'fan-ownership' ), __( 'Meetings', 'fan-ownership' ) ),
					'menu_icon' => 'dashicons-calendar-alt',
					'rewrite'   => array( 'slug' => 'club-meeting' ),
				)
			)
		);

		register_post_type(
			'fo_vote',
			array_merge(
				$base,
				array(
					'labels'    => self::labels( __( 'Vote', 'fan-ownership' ), __( 'Votes & Decisions', 'fan-ownership' ) ),
					'menu_icon' => 'dashicons-yes-alt',
					'rewrite'   => array( 'slug' => 'club-vote' ),
				)
			)
		);

		register_post_type(
			'fo_idea',
			array_merge(
				$base,
				array(
					'labels'    => self::labels( __( 'Fan Idea', 'fan-ownership' ), __( 'Fan Ideas', 'fan-ownership' ) ),
					'menu_icon' => 'dashicons-lightbulb',
					'rewrite'   => array( 'slug' => 'fan-idea' ),
					'supports'  => array( 'title', 'editor', 'author' ),
				)
			)
		);

		register_post_type(
			'fo_question',
			array_merge(
				$base,
				array(
					'labels'    => self::labels( __( 'Fan Question', 'fan-ownership' ), __( 'Fan Questions', 'fan-ownership' ) ),
					'menu_icon' => 'dashicons-format-chat',
					'rewrite'   => array( 'slug' => 'fan-question' ),
					'supports'  => array( 'title', 'editor', 'author' ),
				)
			)
		);

		register_post_type(
			'fo_document',
			array_merge(
				$base,
				array(
					'labels'    => self::labels( __( 'Club Document', 'fan-ownership' ), __( 'Club Documents', 'fan-ownership' ) ),
					'menu_icon' => 'dashicons-media-document',
					'rewrite'   => array( 'slug' => 'club-document' ),
				)
			)
		);

		register_taxonomy(
			'fo_document_type',
			'fo_document',
			array(
				'labels'            => array(
					'name'          => __( 'Document Types', 'fan-ownership' ),
					'singular_name' => __( 'Document Type', 'fan-ownership' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'rewrite'           => array( 'slug' => 'document-type' ),
			)
		);

		register_post_type(
			'fo_exclusive',
			array_merge(
				$base,
				array(
					'labels'    => self::labels( __( 'Behind the Scenes Post', 'fan-ownership' ), __( 'Behind the Scenes', 'fan-ownership' ) ),
					'menu_icon' => 'dashicons-hidden',
					'rewrite'   => array( 'slug' => 'behind-the-scenes' ),
				)
			)
		);
	}

	/**
	 * Seed the video and document taxonomies with sensible defaults.
	 */
	public static function register_default_terms() {
		if ( get_option( 'fo_default_terms_created' ) ) {
			return;
		}
		$video_types = array(
			'match-replay'         => __( 'Match Replay', 'fan-ownership' ),
			'training'             => __( 'Training Session', 'fan-ownership' ),
			'post-match-interview' => __( 'Post-Match Interview', 'fan-ownership' ),
			'behind-the-scenes'    => __( 'Behind the Scenes', 'fan-ownership' ),
		);
		foreach ( $video_types as $slug => $name ) {
			if ( ! term_exists( $slug, 'fo_video_type' ) ) {
				wp_insert_term( $name, 'fo_video_type', array( 'slug' => $slug ) );
			}
		}
		$doc_types = array(
			'financial-statement' => __( 'Financial Statement', 'fan-ownership' ),
			'annual-report'       => __( 'Annual Report', 'fan-ownership' ),
			'board-minutes'       => __( 'Board Minutes', 'fan-ownership' ),
		);
		foreach ( $doc_types as $slug => $name ) {
			if ( ! term_exists( $slug, 'fo_document_type' ) ) {
				wp_insert_term( $name, 'fo_document_type', array( 'slug' => $slug ) );
			}
		}
		update_option( 'fo_default_terms_created', 1 );
	}

	private static function labels( $singular, $plural ) {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			/* translators: %s: singular post type label. */
			'add_new_item'  => sprintf( __( 'Add New %s', 'fan-ownership' ), $singular ),
			/* translators: %s: singular post type label. */
			'edit_item'     => sprintf( __( 'Edit %s', 'fan-ownership' ), $singular ),
			'all_items'     => $plural,
		);
	}
}
