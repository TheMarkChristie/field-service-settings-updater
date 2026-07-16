<?php
/**
 * Content types: Events, Podcasts, and Videos.
 *
 * Blog posts use the built-in `post` type; these three get their own type so
 * each can have its own layout in the theme and its own archive page
 * (/events/, /podcasts/, /videos/).
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class C365_Types {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'posts_clauses', array( __CLASS__, 'events_archive_order' ), 10, 2 );
	}

	/**
	 * Order the /events/ archive: upcoming events first (soonest first),
	 * past events below (most recent first). Events without a start date
	 * sort with past events by publish date. Works under any theme.
	 *
	 * @param array    $clauses SQL clauses.
	 * @param WP_Query $query   Query.
	 * @return array
	 */
	public static function events_archive_order( $clauses, $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'c365_event' ) ) {
			return $clauses;
		}

		global $wpdb;
		$now = esc_sql( current_time( 'Y-m-d\TH:i' ) );

		$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} c365_start ON c365_start.post_id = {$wpdb->posts}.ID AND c365_start.meta_key = '_c365_event_start'";
		$clauses['orderby'] = "(c365_start.meta_value >= '{$now}') DESC, " .
			"CASE WHEN c365_start.meta_value >= '{$now}' THEN c365_start.meta_value END ASC, " .
			"COALESCE(c365_start.meta_value, {$wpdb->posts}.post_date) DESC";

		return $clauses;
	}

	/**
	 * Register the three custom post types.
	 */
	public static function register() {
		$common = array(
			'public'       => true,
			'show_in_rest' => true,
			'menu_position' => 20,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'author', 'excerpt', 'custom-fields' ),
			'has_archive'  => true,
		);

		register_post_type(
			'c365_event',
			array_merge(
				$common,
				array(
					'labels'      => array(
						'name'          => __( 'Events', 'c365-syndicator' ),
						'singular_name' => __( 'Event', 'c365-syndicator' ),
						'add_new_item'  => __( 'Add New Event', 'c365-syndicator' ),
						'edit_item'     => __( 'Edit Event', 'c365-syndicator' ),
					),
					'menu_icon'   => 'dashicons-calendar-alt',
					'rewrite'     => array( 'slug' => 'events' ),
				)
			)
		);

		register_post_type(
			'c365_podcast',
			array_merge(
				$common,
				array(
					'labels'      => array(
						'name'          => __( 'Podcasts', 'c365-syndicator' ),
						'singular_name' => __( 'Podcast Episode', 'c365-syndicator' ),
						'add_new_item'  => __( 'Add New Episode', 'c365-syndicator' ),
						'edit_item'     => __( 'Edit Episode', 'c365-syndicator' ),
					),
					'menu_icon'   => 'dashicons-microphone',
					'rewrite'     => array( 'slug' => 'podcasts' ),
				)
			)
		);

		register_post_type(
			'c365_video',
			array_merge(
				$common,
				array(
					'labels'      => array(
						'name'          => __( 'Videos', 'c365-syndicator' ),
						'singular_name' => __( 'Video', 'c365-syndicator' ),
						'add_new_item'  => __( 'Add New Video', 'c365-syndicator' ),
						'edit_item'     => __( 'Edit Video', 'c365-syndicator' ),
					),
					'menu_icon'   => 'dashicons-video-alt3',
					'rewrite'     => array( 'slug' => 'videos' ),
				)
			)
		);
	}

	/**
	 * Meta boxes for manually-added items.
	 */
	public static function add_meta_boxes() {
		add_meta_box( 'c365_event_details', __( 'Event details', 'c365-syndicator' ), array( __CLASS__, 'render_event_box' ), 'c365_event', 'side' );
		add_meta_box( 'c365_podcast_details', __( 'Episode details', 'c365-syndicator' ), array( __CLASS__, 'render_podcast_box' ), 'c365_podcast', 'side' );
		add_meta_box( 'c365_video_details', __( 'Video details', 'c365-syndicator' ), array( __CLASS__, 'render_video_box' ), 'c365_video', 'side' );
	}

	/**
	 * Event details meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_event_box( $post ) {
		wp_nonce_field( 'c365_type_meta', 'c365_type_meta_nonce' );
		$start    = get_post_meta( $post->ID, '_c365_event_start', true );
		$end      = get_post_meta( $post->ID, '_c365_event_end', true );
		$location = get_post_meta( $post->ID, '_c365_event_location', true );
		$url      = get_post_meta( $post->ID, '_c365_event_url', true );
		?>
		<p>
			<label for="c365-event-start"><strong><?php esc_html_e( 'Starts', 'c365-syndicator' ); ?></strong></label><br>
			<input type="datetime-local" id="c365-event-start" name="c365_event_start" value="<?php echo esc_attr( $start ); ?>" style="width:100%">
		</p>
		<p>
			<label for="c365-event-end"><strong><?php esc_html_e( 'Ends', 'c365-syndicator' ); ?></strong></label><br>
			<input type="datetime-local" id="c365-event-end" name="c365_event_end" value="<?php echo esc_attr( $end ); ?>" style="width:100%">
		</p>
		<p>
			<label for="c365-event-location"><strong><?php esc_html_e( 'Location', 'c365-syndicator' ); ?></strong></label><br>
			<input type="text" id="c365-event-location" name="c365_event_location" value="<?php echo esc_attr( $location ); ?>" style="width:100%" placeholder="<?php esc_attr_e( 'Online / venue name', 'c365-syndicator' ); ?>">
		</p>
		<p>
			<label for="c365-event-url"><strong><?php esc_html_e( 'Registration / info URL', 'c365-syndicator' ); ?></strong></label><br>
			<input type="url" id="c365-event-url" name="c365_event_url" value="<?php echo esc_attr( $url ); ?>" style="width:100%">
		</p>
		<?php
	}

	/**
	 * Podcast details meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_podcast_box( $post ) {
		wp_nonce_field( 'c365_type_meta', 'c365_type_meta_nonce' );
		$audio    = get_post_meta( $post->ID, '_c365_audio_url', true );
		$duration = get_post_meta( $post->ID, '_c365_duration', true );
		?>
		<p>
			<label for="c365-audio-url"><strong><?php esc_html_e( 'Audio file URL (MP3)', 'c365-syndicator' ); ?></strong></label><br>
			<input type="url" id="c365-audio-url" name="c365_audio_url" value="<?php echo esc_attr( $audio ); ?>" style="width:100%">
		</p>
		<p>
			<label for="c365-duration"><strong><?php esc_html_e( 'Duration', 'c365-syndicator' ); ?></strong></label><br>
			<input type="text" id="c365-duration" name="c365_duration" value="<?php echo esc_attr( $duration ); ?>" style="width:100%" placeholder="45:00">
		</p>
		<?php
	}

	/**
	 * Video details meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_video_box( $post ) {
		wp_nonce_field( 'c365_type_meta', 'c365_type_meta_nonce' );
		$video_id = get_post_meta( $post->ID, '_c365_video_id', true );
		?>
		<p>
			<label for="c365-video-id"><strong><?php esc_html_e( 'YouTube video ID', 'c365-syndicator' ); ?></strong></label><br>
			<input type="text" id="c365-video-id" name="c365_video_id" value="<?php echo esc_attr( $video_id ); ?>" style="width:100%" placeholder="dQw4w9WgXcQ">
		</p>
		<?php
	}

	/**
	 * Save meta box values.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['c365_type_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['c365_type_meta_nonce'] ), 'c365_type_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'c365_event_start'    => array( '_c365_event_start', 'sanitize_text_field' ),
			'c365_event_end'      => array( '_c365_event_end', 'sanitize_text_field' ),
			'c365_event_location' => array( '_c365_event_location', 'sanitize_text_field' ),
			'c365_event_url'      => array( '_c365_event_url', 'esc_url_raw' ),
			'c365_audio_url'      => array( '_c365_audio_url', 'esc_url_raw' ),
			'c365_duration'       => array( '_c365_duration', 'sanitize_text_field' ),
			'c365_video_id'       => array( '_c365_video_id', 'sanitize_text_field' ),
		);

		foreach ( $fields as $field => $spec ) {
			if ( isset( $_POST[ $field ] ) ) {
				list( $meta_key, $sanitize ) = $spec;
				$value = call_user_func( $sanitize, wp_unslash( $_POST[ $field ] ) );
				if ( '' === $value ) {
					delete_post_meta( $post_id, $meta_key );
				} else {
					update_post_meta( $post_id, $meta_key, $value );
				}
			}
		}
	}
}
