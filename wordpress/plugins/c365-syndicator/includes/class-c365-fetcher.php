<?php
/**
 * The rotation engine and the shared import pipeline.
 *
 * Every cron tick (5 minutes by default) this picks the NEXT member in the
 * rotation and checks all of their active feed records. WHERE items come
 * from is delegated to a source strategy per record type (C365_Source_Rss,
 * C365_Source_Scrape); everything else — backfill handling, social
 * suppression, de-duplication (GUID + title with legacy back-stamping),
 * template application, inserting, type meta, featured images with category
 * fallback, result recording, and failure alerts — lives here, once.
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'C365_Fetcher' ) ) :

class C365_Fetcher {

	/**
	 * Consecutive failures before the admin is emailed.
	 */
	const ALERT_THRESHOLD = 5;

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( C365_SYN_CRON_HOOK, array( __CLASS__, 'rotate' ) );
		add_action( 'admin_post_c365_fetch_feed', array( __CLASS__, 'handle_fetch_feed' ) );
		add_action( 'admin_post_c365_fetch_all', array( __CLASS__, 'handle_fetch_all' ) );
		add_action( 'admin_post_c365_backfill_feed', array( __CLASS__, 'handle_backfill_feed' ) );
		add_action( 'admin_post_c365_backfill_all', array( __CLASS__, 'handle_backfill_all' ) );
		add_action( 'c365_feed_result_recorded', array( __CLASS__, 'maybe_alert_admin' ), 10, 3 );
	}

	/**
	 * The source strategy for a feed type.
	 *
	 * @param string $type Feed type.
	 * @return object With fetch( $row, $max ) and enrich( $item, $row ).
	 */
	public static function source_for( $type ) {
		if ( 'scrape' === $type ) {
			return new C365_Source_Scrape();
		}
		return new C365_Source_Rss();
	}

	/* -----------------------------------------------------------------------
	 * Rotation
	 * -------------------------------------------------------------------- */

	/**
	 * Member IDs in rotation order.
	 *
	 * @return int[]
	 */
	public static function get_member_ids() {
		return C365_Feeds::member_ids();
	}

	/**
	 * Given the rotation pointer (last user checked), find who is next.
	 *
	 * @param int[] $member_ids Members in rotation.
	 * @param int   $pointer    Last-checked user ID.
	 * @return int Next user ID, or 0 when there are no members.
	 */
	public static function next_member_id( $member_ids, $pointer ) {
		if ( empty( $member_ids ) ) {
			return 0;
		}
		foreach ( $member_ids as $id ) {
			if ( $id > $pointer ) {
				return $id;
			}
		}
		return $member_ids[0]; // Wrap around.
	}

	/**
	 * Cron tick: check the next member's feeds, advance the pointer, then
	 * let the social sharer flush its queue.
	 */
	public static function rotate() {
		$members = self::get_member_ids();
		if ( ! empty( $members ) ) {
			$pointer = (int) get_option( 'c365_rotation_pointer', 0 );
			$next_id = self::next_member_id( $members, $pointer );
			if ( $next_id ) {
				update_option( 'c365_rotation_pointer', $next_id, false );
				self::fetch_user( $next_id );
			}
		}

		if ( class_exists( 'C365_Social' ) ) {
			C365_Social::process_queue();
		}
	}

	/* -----------------------------------------------------------------------
	 * Fetch orchestration (shared by all source types)
	 * -------------------------------------------------------------------- */

	/**
	 * Check every active feed record a member has. Returns imported count.
	 *
	 * @param int $user_id Member ID.
	 * @return int
	 */
	public static function fetch_user( $user_id ) {
		$imported = 0;
		foreach ( C365_Feeds::for_user( $user_id ) as $row ) {
			if ( (int) $row->active ) {
				$imported += self::fetch_feed_record( $row );
			}
		}
		return $imported;
	}

	/**
	 * Fetch one feed record and import its new items.
	 *
	 * @param object $row Feed record.
	 * @return int Imported count.
	 */
	public static function fetch_feed_record( $row ) {
		$user = get_user_by( 'id', (int) $row->user_id );
		if ( ! $user || ! user_can( $user, 'edit_posts' ) ) {
			return 0;
		}

		// First fetch backfills everything the source exposes; after that,
		// the per-fetch cap applies. Backfilled content is historic, so
		// social sharing is suppressed for the whole run.
		$backfill = ! (int) $row->backfilled;
		$max      = $backfill ? 0 : (int) C365_Settings::get( 'max_items' );

		$source = self::source_for( $row->type );
		$items  = $source->fetch( $row, $max );
		if ( is_wp_error( $items ) ) {
			C365_Feeds::record_result( $row->id, $items->get_error_message(), true );
			return 0;
		}

		$post_type  = C365_Feeds::post_type_for( $row->type );
		$categories = C365_Feeds::parse_categories( $row->categories );
		$imported   = 0;

		$was_suppressed = class_exists( 'C365_Social' ) ? C365_Social::$suppressed : false;
		if ( $backfill && class_exists( 'C365_Social' ) ) {
			C365_Social::$suppressed = true;
		}

		foreach ( $items as $item ) {
			if ( self::import_item( $user, $row, $source, $item, $post_type, $categories ) ) {
				$imported++;
			}
		}

		if ( class_exists( 'C365_Social' ) ) {
			C365_Social::$suppressed = $was_suppressed;
		}

		C365_Feeds::record_result(
			$row->id,
			sprintf(
				/* translators: %d: imported count. */
				$backfill ? __( 'Backfill complete — %d item(s) imported', 'c365-syndicator' ) : __( '%d new item(s) imported', 'c365-syndicator' ),
				$imported
			),
			false,
			$backfill
		);

		return $imported;
	}

	/* -----------------------------------------------------------------------
	 * The shared import pipeline
	 * -------------------------------------------------------------------- */

	/**
	 * Import one normalized item, unless it already exists.
	 *
	 * Pipeline: GUID dedup → title dedup (when the title is known before
	 * enrichment) → enrich (may skip: Shorts, unreadable pages) → title
	 * dedup (when the title only arrived with enrichment) → template →
	 * insert → type meta → featured image.
	 *
	 * @param WP_User $user       Member.
	 * @param object  $row        Feed record.
	 * @param object  $source     Source strategy.
	 * @param array   $item       Normalized item.
	 * @param string  $post_type  Target post type.
	 * @param int[]   $categories Category IDs from the feed record.
	 * @return bool Whether a post was created.
	 */
	protected static function import_item( $user, $row, $source, $item, $post_type, $categories ) {
		if ( empty( $item['guid'] ) || self::guid_exists( $item['guid'] ) ) {
			return false;
		}

		$had_title = '' !== $item['title'];
		if ( $had_title && self::title_matches_existing( $item['title'], $post_type, $item['guid'] ) ) {
			return false;
		}

		$item = $source->enrich( $item, $row );
		if ( ! $item || '' === $item['title'] ) {
			return false;
		}
		if ( ! $had_title && self::title_matches_existing( $item['title'], $post_type, $item['guid'] ) ) {
			return false;
		}

		// Apply the per-type post body template (Syndication → Templates).
		$content = self::apply_post_template(
			$post_type,
			array(
				'{content}'     => $item['content'],
				'{title}'       => $item['title'],
				'{author}'      => $user->display_name,
				'{excerpt}'     => $item['excerpt'],
				'{source_name}' => $item['source_name'],
				'{source_url}'  => $item['source_url'],
				'{date}'        => $item['timestamp'] ? gmdate( 'j F Y', $item['timestamp'] ) : '',
			)
		);

		$postarr = array(
			'post_type'    => $post_type,
			'post_status'  => C365_Settings::get( 'post_status' ),
			'post_author'  => $user->ID,
			'post_title'   => $item['title'],
			'post_content' => $content,
			'post_excerpt' => $item['excerpt'],
			'meta_input'   => array(
				'_c365_guid'        => $item['guid'],
				'_c365_feed_id'     => (int) $row->id,
				'_c365_source_url'  => $item['source_url'],
				'_c365_source_name' => $item['source_name'],
			),
		);

		// Keep the original publish date.
		if ( $item['timestamp'] && C365_Settings::get( 'use_original_date' ) ) {
			$postarr['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $item['timestamp'] );
			$postarr['post_date']     = get_date_from_gmt( $postarr['post_date_gmt'] );
		}

		// Categories: the record's mapping, optionally plus the item's own.
		$assign = $categories;
		if ( 'post' === $post_type && C365_Settings::get( 'import_categories' ) && ! empty( $item['category_names'] ) ) {
			$assign = array_merge( $assign, self::map_category_names( $item['category_names'] ) );
		}
		if ( 'post' === $post_type && $assign ) {
			$postarr['post_category'] = array_unique( $assign );
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		// Non-post types can also carry categories when the record maps them.
		if ( 'post' !== $post_type && $assign ) {
			wp_set_post_categories( $post_id, array_unique( $assign ) );
		}

		// Type extras from the normalized item.
		$extra_meta = array(
			'_c365_audio_url'      => 'audio_url',
			'_c365_duration'       => 'duration',
			'_c365_video_id'       => 'video_id',
			'_c365_event_start'    => 'event_start',
			'_c365_event_end'      => 'event_end',
			'_c365_event_location' => 'event_location',
			'_c365_event_url'      => 'event_url',
		);
		foreach ( $extra_meta as $meta_key => $item_key ) {
			if ( ! empty( $item[ $item_key ] ) ) {
				update_post_meta( $post_id, $meta_key, $item[ $item_key ] );
			}
		}

		if ( C365_Settings::get( 'set_featured_image' ) ) {
			self::attach_featured_image( $post_id, $item['image_url'], $item['content'] );
		}

		return true;
	}

	/**
	 * Build the post body from the per-type template (default: '{content}',
	 * i.e. the original text unchanged).
	 *
	 * @param string $post_type Post type.
	 * @param array  $vars      Placeholder replacements including {content}.
	 * @return string
	 */
	public static function apply_post_template( $post_type, $vars ) {
		$template = class_exists( 'C365_Settings' ) ? C365_Settings::get_template( $post_type, 'post' ) : '{content}';
		if ( '{content}' === trim( $template ) ) {
			return $vars['{content}'];
		}
		return wp_kses_post( strtr( $template, $vars ) );
	}

	/* -----------------------------------------------------------------------
	 * De-duplication
	 * -------------------------------------------------------------------- */

	/**
	 * Whether an item with this GUID was already imported.
	 *
	 * @param string $guid Item GUID.
	 * @return bool
	 */
	protected static function guid_exists( $guid ) {
		$existing = get_posts(
			array(
				'post_type'      => array( 'post', 'c365_event', 'c365_podcast', 'c365_video' ),
				// Explicit list including trash: a trashed import must stay
				// deleted, not resurrect on the next fetch ('any' skips trash).
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
				'meta_key'       => '_c365_guid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $guid,        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		return ! empty( $existing );
	}

	/**
	 * Title match against pre-existing content (legacy imports without a
	 * GUID): when found, stamp the GUID onto the match so future fetches
	 * take the fast GUID path, and report the item as a duplicate.
	 *
	 * @param string $title     Normalised title.
	 * @param string $post_type Target post type.
	 * @param string $guid      Item GUID to back-stamp.
	 * @return bool Whether an existing post matched.
	 */
	protected static function title_matches_existing( $title, $post_type, $guid ) {
		global $wpdb;
		$existing = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('auto-draft') AND post_title = %s LIMIT 1",
				$post_type,
				$title
			)
		);
		if ( ! $existing ) {
			return false;
		}
		if ( ! get_post_meta( $existing, '_c365_guid', true ) ) {
			update_post_meta( $existing, '_c365_guid', $guid );
		}
		return true;
	}

	/**
	 * Map feed category names onto WP categories, creating as needed.
	 *
	 * @param string[] $names Category labels from the feed item.
	 * @return int[] Category IDs.
	 */
	protected static function map_category_names( $names ) {
		$ids = array();
		foreach ( array_slice( $names, 0, 5 ) as $label ) {
			$term = get_term_by( 'name', $label, 'category' );
			if ( ! $term ) {
				$new = wp_insert_term( $label, 'category' );
				if ( is_wp_error( $new ) ) {
					continue;
				}
				$ids[] = (int) $new['term_id'];
			} else {
				$ids[] = (int) $term->term_id;
			}
		}
		return $ids;
	}

	/* -----------------------------------------------------------------------
	 * Featured images
	 * -------------------------------------------------------------------- */

	/**
	 * Set the featured image: the item's preferred image, else the first
	 * image in the content, else the category's fallback image.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $preferred Preferred image URL (may be '').
	 * @param string $content   Sanitised item content.
	 */
	protected static function attach_featured_image( $post_id, $preferred, $content ) {
		$image_url = $preferred;

		if ( ! $image_url && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m ) ) {
			$image_url = $m[1];
		}

		if ( $image_url && 0 === strpos( $image_url, 'http' ) ) {
			$attachment_id = self::sideload_image( $image_url, $post_id, get_the_title( $post_id ) );
			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
				return;
			}
		}

		self::set_category_fallback_image( $post_id );
	}

	/**
	 * Download an image into the Media Library.
	 *
	 * @param string $url     Image URL.
	 * @param int    $post_id Parent post (0 for unattached).
	 * @param string $desc    Description.
	 * @return int Attachment ID, or 0 on failure.
	 */
	public static function sideload_image( $url, $post_id = 0, $desc = null ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( esc_url_raw( $url ), $post_id, $desc, 'id' );
		return is_wp_error( $attachment_id ) ? 0 : (int) $attachment_id;
	}

	/**
	 * Last-resort featured image: the image of the first of the post's
	 * categories that has one (set on the category edit screen).
	 *
	 * @param int $post_id Post ID.
	 */
	protected static function set_category_fallback_image( $post_id ) {
		foreach ( wp_get_post_categories( $post_id ) as $term_id ) {
			$attachment_id = C365_Types::category_image_attachment_id( (int) $term_id );
			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
				return;
			}
		}
	}

	/* -----------------------------------------------------------------------
	 * Failure alerts (FR-7.4)
	 * -------------------------------------------------------------------- */

	/**
	 * Email the admin when a feed reaches the failure threshold exactly
	 * (so one email per streak, re-armed by a success).
	 *
	 * @param object $row        Feed row (pre-update).
	 * @param int    $fail_count New consecutive failure count.
	 * @param string $message    The result message just recorded.
	 */
	public static function maybe_alert_admin( $row, $fail_count, $message = '' ) {
		/**
		 * Filter the consecutive-failure threshold for admin alerts.
		 *
		 * @param int $threshold Default 5.
		 */
		$threshold = (int) apply_filters( 'c365_alert_threshold', self::ALERT_THRESHOLD );
		if ( $fail_count !== $threshold ) {
			return;
		}

		$user = get_user_by( 'id', (int) $row->user_id );
		$name = $user ? $user->display_name : ( '#' . $row->user_id );

		wp_mail(
			get_option( 'admin_email' ),
			sprintf(
				/* translators: %s: member name. */
				__( '[365 Community] Feed failing for %s', 'c365-syndicator' ),
				$name
			),
			sprintf(
				/* translators: 1: member name, 2: feed type, 3: feed URL, 4: failure count, 5: last error, 6: admin URL. */
				__( "The %2\$s feed for %1\$s has failed %4\$d fetches in a row.\n\nFeed: %3\$s\nLast error: %5\$s\n\nManage feeds: %6\$s", 'c365-syndicator' ),
				$name,
				$row->type,
				C365_Feeds::resolved_url( $row ),
				$fail_count,
				'' !== $message ? $message : (string) $row->last_result,
				admin_url( 'admin.php?page=c365-syndication' )
			)
		);
	}

	/* -----------------------------------------------------------------------
	 * Manual fetch handlers (admin buttons)
	 * -------------------------------------------------------------------- */

	/**
	 * "Fetch now" for a single feed record.
	 */
	public static function handle_fetch_feed() {
		$feed_id = isset( $_GET['feed_id'] ) ? absint( $_GET['feed_id'] ) : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $feed_id ) {
			wp_die( esc_html__( 'Not allowed.', 'c365-syndicator' ) );
		}
		check_admin_referer( 'c365_fetch_feed_' . $feed_id );

		$row   = C365_Feeds::get( $feed_id );
		$count = $row ? self::fetch_feed_record( $row ) : 0;
		wp_safe_redirect( admin_url( 'admin.php?page=c365-syndication&c365_fetched=' . $count ) );
		exit;
	}

	/**
	 * "Run historic" for a single feed: re-import everything the feed
	 * exposes, with social posting suppressed.
	 */
	public static function handle_backfill_feed() {
		$feed_id = isset( $_GET['feed_id'] ) ? absint( $_GET['feed_id'] ) : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $feed_id ) {
			wp_die( esc_html__( 'Not allowed.', 'c365-syndicator' ) );
		}
		check_admin_referer( 'c365_backfill_feed_' . $feed_id );

		$count = 0;
		if ( C365_Feeds::reset_backfill( $feed_id ) ) {
			$row   = C365_Feeds::get( $feed_id );
			$count = $row ? self::fetch_feed_record( $row ) : 0;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=c365-syndication&c365_fetched=' . $count ) );
		exit;
	}

	/**
	 * "Run all historic": re-import every feed's full history, with social
	 * posting suppressed.
	 */
	public static function handle_backfill_all() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'c365-syndicator' ) );
		}
		check_admin_referer( 'c365_backfill_all' );

		$count = 0;
		foreach ( C365_Feeds::all() as $row ) {
			if ( ! (int) $row->active ) {
				continue;
			}
			C365_Feeds::reset_backfill( (int) $row->id );
			$row->backfilled = 0;
			$count          += self::fetch_feed_record( $row );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=c365-syndication&c365_fetched=' . $count ) );
		exit;
	}

	/**
	 * "Fetch all members now".
	 */
	public static function handle_fetch_all() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'c365-syndicator' ) );
		}
		check_admin_referer( 'c365_fetch_all' );

		$count = 0;
		foreach ( self::get_member_ids() as $member_id ) {
			$count += self::fetch_user( $member_id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=c365-syndication&c365_fetched=' . $count ) );
		exit;
	}
}

endif;
