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

if ( ! class_exists( 'C365_Types' ) ) :

class C365_Types {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'posts_clauses', array( __CLASS__, 'events_archive_order' ), 10, 2 );

		// Category images: used as the fallback featured image for imports.
		add_action( 'category_add_form_fields', array( __CLASS__, 'render_category_image_add' ) );
		add_action( 'category_edit_form_fields', array( __CLASS__, 'render_category_image_edit' ) );
		add_action( 'created_category', array( __CLASS__, 'save_category_image' ) );
		add_action( 'edited_category', array( __CLASS__, 'save_category_image' ) );
	}

	/**
	 * Category image field on the Add Category form.
	 */
	public static function render_category_image_add() {
		?>
		<div class="form-field">
			<label for="c365-category-image"><?php esc_html_e( 'Category image URL', 'syndicate-pro' ); ?></label>
			<input type="url" id="c365-category-image" name="c365_category_image" value="">
			<p><?php esc_html_e( 'Used as the featured image for imported posts in this category that have no image of their own.', 'syndicate-pro' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Category image field on the Edit Category form.
	 *
	 * @param WP_Term $term Term being edited.
	 */
	public static function render_category_image_edit( $term ) {
		$url = get_term_meta( $term->term_id, 'c365_category_image', true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="c365-category-image"><?php esc_html_e( 'Category image URL', 'syndicate-pro' ); ?></label></th>
			<td>
				<input type="url" id="c365-category-image" name="c365_category_image" value="<?php echo esc_attr( $url ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'Used as the featured image for imported posts in this category that have no image of their own. Paste an image URL (upload one via the Media Library first if needed).', 'syndicate-pro' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the category image URL; changing it clears the cached attachment.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_category_image( $term_id ) {
		if ( ! isset( $_POST['c365_category_image'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- core taxonomy forms carry their own nonces, verified before these hooks fire.
			return;
		}
		$url = esc_url_raw( wp_unslash( $_POST['c365_category_image'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$old = get_term_meta( $term_id, 'c365_category_image', true );

		if ( $url !== $old ) {
			delete_term_meta( $term_id, 'c365_category_image_id' );
		}
		if ( '' === $url ) {
			delete_term_meta( $term_id, 'c365_category_image' );
		} else {
			update_term_meta( $term_id, 'c365_category_image', $url );
		}
	}

	/**
	 * The attachment ID for a category's image, sideloading the URL into the
	 * Media Library once and reusing the same attachment thereafter.
	 *
	 * @param int $term_id Category term ID.
	 * @return int Attachment ID, or 0 when the category has no usable image.
	 */
	public static function category_image_attachment_id( $term_id ) {
		$cached = (int) get_term_meta( $term_id, 'c365_category_image_id', true );
		if ( $cached && get_post( $cached ) ) {
			return $cached;
		}

		$url = get_term_meta( $term_id, 'c365_category_image', true );
		if ( ! $url || 0 !== strpos( $url, 'http' ) ) {
			return 0;
		}

		$attachment_id = C365_Fetcher::sideload_image( $url );
		if ( ! $attachment_id ) {
			return 0;
		}

		update_term_meta( $term_id, 'c365_category_image_id', $attachment_id );
		return $attachment_id;
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
						'name'          => __( 'Events', 'syndicate-pro' ),
						'singular_name' => __( 'Event', 'syndicate-pro' ),
						'add_new_item'  => __( 'Add New Event', 'syndicate-pro' ),
						'edit_item'     => __( 'Edit Event', 'syndicate-pro' ),
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
						'name'          => __( 'Podcasts', 'syndicate-pro' ),
						'singular_name' => __( 'Podcast Episode', 'syndicate-pro' ),
						'add_new_item'  => __( 'Add New Episode', 'syndicate-pro' ),
						'edit_item'     => __( 'Edit Episode', 'syndicate-pro' ),
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
						'name'          => __( 'Videos', 'syndicate-pro' ),
						'singular_name' => __( 'Video', 'syndicate-pro' ),
						'add_new_item'  => __( 'Add New Video', 'syndicate-pro' ),
						'edit_item'     => __( 'Edit Video', 'syndicate-pro' ),
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
		add_meta_box( 'c365_event_details', __( 'Event details', 'syndicate-pro' ), array( __CLASS__, 'render_event_box' ), 'c365_event', 'side' );
		add_meta_box( 'c365_podcast_details', __( 'Episode details', 'syndicate-pro' ), array( __CLASS__, 'render_podcast_box' ), 'c365_podcast', 'side' );
		add_meta_box( 'c365_video_details', __( 'Video details', 'syndicate-pro' ), array( __CLASS__, 'render_video_box' ), 'c365_video', 'side' );
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
			<label for="c365-event-start"><strong><?php esc_html_e( 'Starts', 'syndicate-pro' ); ?></strong></label><br>
			<input type="datetime-local" id="c365-event-start" name="c365_event_start" value="<?php echo esc_attr( $start ); ?>" style="width:100%">
		</p>
		<p>
			<label for="c365-event-end"><strong><?php esc_html_e( 'Ends', 'syndicate-pro' ); ?></strong></label><br>
			<input type="datetime-local" id="c365-event-end" name="c365_event_end" value="<?php echo esc_attr( $end ); ?>" style="width:100%">
		</p>
		<p>
			<label for="c365-event-location"><strong><?php esc_html_e( 'Location', 'syndicate-pro' ); ?></strong></label><br>
			<input type="text" id="c365-event-location" name="c365_event_location" value="<?php echo esc_attr( $location ); ?>" style="width:100%" placeholder="<?php esc_attr_e( 'Online / venue name', 'syndicate-pro' ); ?>">
		</p>
		<p>
			<label for="c365-event-url"><strong><?php esc_html_e( 'Registration / info URL', 'syndicate-pro' ); ?></strong></label><br>
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
			<label for="c365-audio-url"><strong><?php esc_html_e( 'Audio file URL (MP3)', 'syndicate-pro' ); ?></strong></label><br>
			<input type="url" id="c365-audio-url" name="c365_audio_url" value="<?php echo esc_attr( $audio ); ?>" style="width:100%">
		</p>
		<p>
			<label for="c365-duration"><strong><?php esc_html_e( 'Duration', 'syndicate-pro' ); ?></strong></label><br>
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
			<label for="c365-video-id"><strong><?php esc_html_e( 'YouTube video ID', 'syndicate-pro' ); ?></strong></label><br>
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

endif;
