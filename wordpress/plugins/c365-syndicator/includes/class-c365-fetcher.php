<?php
/**
 * The rotation engine.
 *
 * Every cron tick (5 minutes by default) this picks the NEXT member in the
 * rotation and checks all of their feeds: blog RSS, podcast RSS, YouTube
 * channel, and events feed. New items are imported with the original title,
 * image, and text, assigned to that member as post author, and stamped with
 * source metadata so the front end can link back to the original.
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class C365_Fetcher {

	/**
	 * User meta keys that put a member into the rotation.
	 *
	 * @var string[]
	 */
	const FEED_KEYS = array( 'c365_blog_feed', 'c365_podcast_feed', 'c365_youtube_channel', 'c365_events_feed' );

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( C365_SYN_CRON_HOOK, array( __CLASS__, 'rotate' ) );
		add_action( 'admin_post_c365_fetch_user', array( __CLASS__, 'handle_fetch_user' ) );
		add_action( 'admin_post_c365_fetch_all', array( __CLASS__, 'handle_fetch_all' ) );
	}

	/* -----------------------------------------------------------------------
	 * Rotation
	 * -------------------------------------------------------------------- */

	/**
	 * All members that have at least one feed configured, ordered by ID.
	 *
	 * @return WP_User[]
	 */
	public static function get_members() {
		$meta_query = array( 'relation' => 'OR' );
		foreach ( self::FEED_KEYS as $key ) {
			$meta_query[] = array(
				'key'     => $key,
				'value'   => '',
				'compare' => '!=',
			);
		}

		return get_users(
			array(
				'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'orderby'    => 'ID',
				'order'      => 'ASC',
			)
		);
	}

	/**
	 * Given the rotation pointer (the last user checked), find who is next.
	 *
	 * @param WP_User[] $members Members in rotation.
	 * @param int       $pointer Last-checked user ID.
	 * @return int Next user ID, or 0 when there are no members.
	 */
	public static function next_member_id( $members, $pointer ) {
		if ( empty( $members ) ) {
			return 0;
		}
		foreach ( $members as $member ) {
			if ( $member->ID > $pointer ) {
				return $member->ID;
			}
		}
		return $members[0]->ID; // Wrap around.
	}

	/**
	 * Cron tick: check the next member's feeds and advance the pointer.
	 */
	public static function rotate() {
		$members = self::get_members();
		if ( empty( $members ) ) {
			return;
		}

		$pointer = (int) get_option( 'c365_rotation_pointer', 0 );
		$next_id = self::next_member_id( $members, $pointer );
		if ( ! $next_id ) {
			return;
		}

		update_option( 'c365_rotation_pointer', $next_id, false );
		self::fetch_user( $next_id );
	}

	/* -----------------------------------------------------------------------
	 * Per-member fetching
	 * -------------------------------------------------------------------- */

	/**
	 * Check every feed a member has configured. Returns imported item count.
	 *
	 * @param int $user_id Member ID.
	 * @return int
	 */
	public static function fetch_user( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return 0;
		}

		$imported = 0;
		$errors   = array();

		$feeds = array();

		$blog_feed = get_user_meta( $user_id, 'c365_blog_feed', true );
		if ( $blog_feed ) {
			$feeds[] = array( $blog_feed, 'post' );
		}

		$podcast_feed = get_user_meta( $user_id, 'c365_podcast_feed', true );
		if ( $podcast_feed ) {
			$feeds[] = array( $podcast_feed, 'c365_podcast' );
		}

		$channel = get_user_meta( $user_id, 'c365_youtube_channel', true );
		if ( $channel ) {
			$feeds[] = array( 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode( $channel ), 'c365_video' );
		}

		$events_feed = get_user_meta( $user_id, 'c365_events_feed', true );
		if ( $events_feed ) {
			$feeds[] = array( $events_feed, 'c365_event' );
		}

		foreach ( $feeds as $feed ) {
			list( $feed_url, $post_type ) = $feed;
			$result = self::import_feed( $user, $feed_url, $post_type );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} else {
				$imported += $result;
			}
		}

		update_user_meta( $user_id, 'c365_last_fetch', time() );
		if ( $errors ) {
			update_user_meta(
				$user_id,
				'c365_last_result',
				sprintf(
					/* translators: 1: imported count, 2: error messages. */
					__( '%1$d imported; errors: %2$s', 'c365-syndicator' ),
					$imported,
					implode( ' / ', array_slice( $errors, 0, 3 ) )
				)
			);
		} else {
			update_user_meta(
				$user_id,
				'c365_last_result',
				sprintf(
					/* translators: %d: imported count. */
					__( '%d new item(s) imported', 'c365-syndicator' ),
					$imported
				)
			);
		}

		return $imported;
	}

	/**
	 * Import new items from one feed into one post type.
	 *
	 * @param WP_User $user      Member the feed belongs to.
	 * @param string  $feed_url  Feed URL.
	 * @param string  $post_type Target post type.
	 * @return int|WP_Error Number of imported items.
	 */
	public static function import_feed( $user, $feed_url, $post_type ) {
		if ( ! function_exists( 'fetch_feed' ) ) {
			require_once ABSPATH . WPINC . '/feed.php';
		}

		// Keep the feed cache short so a 5-minute rotation sees new items.
		$shorten = function () {
			return 4 * MINUTE_IN_SECONDS;
		};
		add_filter( 'wp_feed_cache_transient_lifetime', $shorten );
		$feed = fetch_feed( esc_url_raw( $feed_url ) );
		remove_filter( 'wp_feed_cache_transient_lifetime', $shorten );

		if ( is_wp_error( $feed ) ) {
			return $feed;
		}

		$max      = (int) C365_Settings::get( 'max_items' );
		$items    = $feed->get_items( 0, $max );
		$imported = 0;

		foreach ( $items as $item ) {
			if ( self::import_item( $user, $item, $post_type, $feed ) ) {
				$imported++;
			}
		}

		return $imported;
	}

	/**
	 * Import a single feed item, unless it already exists.
	 *
	 * @param WP_User        $user      Member.
	 * @param SimplePie_Item $item      Feed item.
	 * @param string         $post_type Target post type.
	 * @param SimplePie      $feed      Parent feed.
	 * @return bool Whether a post was created.
	 */
	protected static function import_item( $user, $item, $post_type, $feed ) {
		$guid = $item->get_id();
		if ( ! $guid ) {
			$guid = $item->get_permalink();
		}
		if ( ! $guid || self::guid_exists( $guid ) ) {
			return false;
		}

		$title = wp_strip_all_tags( (string) $item->get_title() );
		if ( '' === trim( $title ) ) {
			return false;
		}

		$content = (string) $item->get_content();
		if ( '' === trim( $content ) ) {
			$content = (string) $item->get_description();
		}
		$content = wp_kses_post( $content );

		$postarr = array(
			'post_type'    => $post_type,
			'post_status'  => C365_Settings::get( 'post_status' ),
			'post_author'  => $user->ID,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => wp_trim_words( wp_strip_all_tags( (string) $item->get_description() ), 40 ),
			'meta_input'   => array(
				'_c365_guid'        => $guid,
				'_c365_source_url'  => esc_url_raw( (string) $item->get_permalink() ),
				'_c365_source_name' => wp_strip_all_tags( (string) $feed->get_title() ),
			),
		);

		// Keep the original publish date.
		if ( C365_Settings::get( 'use_original_date' ) ) {
			$timestamp = $item->get_date( 'U' );
			if ( $timestamp ) {
				$postarr['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', (int) $timestamp );
				$postarr['post_date']     = get_date_from_gmt( $postarr['post_date_gmt'] );
			}
		}

		// Blog posts go to the member's chosen category.
		if ( 'post' === $post_type ) {
			$categories = array();
			$member_cat = (int) get_user_meta( $user->ID, 'c365_blog_category', true );
			if ( $member_cat ) {
				$categories[] = $member_cat;
			}
			if ( C365_Settings::get( 'import_categories' ) ) {
				$categories = array_merge( $categories, self::map_item_categories( $item ) );
			}
			if ( $categories ) {
				$postarr['post_category'] = array_unique( $categories );
			}
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		// Type-specific extras.
		if ( 'c365_podcast' === $post_type ) {
			self::attach_podcast_meta( $post_id, $item );
		} elseif ( 'c365_video' === $post_type ) {
			self::attach_video_meta( $post_id, $item );
		}

		// Featured image: enclosure/media image, else first image in the content.
		if ( C365_Settings::get( 'set_featured_image' ) ) {
			self::attach_featured_image( $post_id, $item, $content );
		}

		return true;
	}

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
				'post_status'    => 'any',
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
	 * Map a feed item's categories onto WP categories, creating them if needed.
	 *
	 * @param SimplePie_Item $item Feed item.
	 * @return int[] Category IDs.
	 */
	protected static function map_item_categories( $item ) {
		$ids        = array();
		$categories = $item->get_categories();
		if ( ! $categories ) {
			return $ids;
		}
		foreach ( array_slice( $categories, 0, 5 ) as $category ) {
			$label = wp_strip_all_tags( (string) $category->get_label() );
			if ( '' === $label ) {
				continue;
			}
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

	/**
	 * Store the audio enclosure and duration for a podcast episode.
	 *
	 * @param int            $post_id Post ID.
	 * @param SimplePie_Item $item    Feed item.
	 */
	protected static function attach_podcast_meta( $post_id, $item ) {
		$enclosure = $item->get_enclosure();
		if ( $enclosure && $enclosure->get_link() ) {
			$type = (string) $enclosure->get_type();
			if ( '' === $type || 0 === strpos( $type, 'audio' ) ) {
				update_post_meta( $post_id, '_c365_audio_url', esc_url_raw( $enclosure->get_link() ) );
			}
			$duration = $enclosure->get_duration( true );
			if ( $duration ) {
				update_post_meta( $post_id, '_c365_duration', sanitize_text_field( $duration ) );
			}
		}
	}

	/**
	 * Extract and store the YouTube video ID for a video item.
	 *
	 * @param int            $post_id Post ID.
	 * @param SimplePie_Item $item    Feed item.
	 */
	protected static function attach_video_meta( $post_id, $item ) {
		$video_id = '';

		// YouTube feed GUIDs look like "yt:video:VIDEOID".
		$guid = (string) $item->get_id();
		if ( preg_match( '/^yt:video:([A-Za-z0-9_-]{6,})$/', $guid, $m ) ) {
			$video_id = $m[1];
		}

		// Fallback: ?v= parameter on the permalink.
		if ( ! $video_id ) {
			$link  = (string) $item->get_permalink();
			$query = wp_parse_url( $link, PHP_URL_QUERY );
			if ( $query ) {
				parse_str( $query, $params );
				if ( ! empty( $params['v'] ) ) {
					$video_id = preg_replace( '/[^A-Za-z0-9_-]/', '', $params['v'] );
				}
			}
		}

		if ( $video_id ) {
			update_post_meta( $post_id, '_c365_video_id', $video_id );
		}
	}

	/**
	 * Sideload the item's image and set it as the featured image.
	 *
	 * @param int            $post_id Post ID.
	 * @param SimplePie_Item $item    Feed item.
	 * @param string         $content Sanitised item content.
	 */
	protected static function attach_featured_image( $post_id, $item, $content ) {
		$image_url = '';

		// 1. YouTube: use the video thumbnail.
		$video_id = get_post_meta( $post_id, '_c365_video_id', true );
		if ( $video_id ) {
			$image_url = 'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/hqdefault.jpg';
		}

		// 2. Enclosure / media:content image or thumbnail.
		if ( ! $image_url ) {
			$enclosure = $item->get_enclosure();
			if ( $enclosure ) {
				$thumb = $enclosure->get_thumbnail();
				if ( $thumb ) {
					$image_url = $thumb;
				} elseif ( $enclosure->get_link() && 0 === strpos( (string) $enclosure->get_type(), 'image' ) ) {
					$image_url = $enclosure->get_link();
				}
			}
		}

		// 3. First <img> in the content.
		if ( ! $image_url && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m ) ) {
			$image_url = $m[1];
		}

		if ( ! $image_url || 0 !== strpos( $image_url, 'http' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( esc_url_raw( $image_url ), $post_id, get_the_title( $post_id ), 'id' );
		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/* -----------------------------------------------------------------------
	 * Manual fetch handlers (admin buttons)
	 * -------------------------------------------------------------------- */

	/**
	 * "Fetch now" for a single member.
	 */
	public static function handle_fetch_user() {
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $user_id ) {
			wp_die( esc_html__( 'Not allowed.', 'c365-syndicator' ) );
		}
		check_admin_referer( 'c365_fetch_user_' . $user_id );

		$count = self::fetch_user( $user_id );
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
		foreach ( self::get_members() as $member ) {
			$count += self::fetch_user( $member->ID );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=c365-syndication&c365_fetched=' . $count ) );
		exit;
	}
}
