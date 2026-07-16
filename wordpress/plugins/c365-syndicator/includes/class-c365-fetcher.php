<?php
/**
 * The rotation engine.
 *
 * Every cron tick (5 minutes by default) this picks the NEXT member in the
 * rotation and checks all of their active feed records. New items are
 * imported with the original title, image, and text, assigned to the member
 * as post author, filed under the feed record's categories, and stamped with
 * source metadata so the front end can link back to the original.
 *
 * De-duplication is two-stage: feed GUID first, then a title match against
 * existing content (protecting the ~6 years of pre-plugin imports); title
 * matches are back-stamped with the GUID so future checks are fast.
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
		add_action( 'admin_post_c365_fetch_user', array( __CLASS__, 'handle_fetch_user' ) );
		add_action( 'admin_post_c365_fetch_feed', array( __CLASS__, 'handle_fetch_feed' ) );
		add_action( 'admin_post_c365_fetch_all', array( __CLASS__, 'handle_fetch_all' ) );
		add_action( 'admin_post_c365_backfill_feed', array( __CLASS__, 'handle_backfill_feed' ) );
		add_action( 'admin_post_c365_backfill_all', array( __CLASS__, 'handle_backfill_all' ) );
		add_action( 'c365_feed_result_recorded', array( __CLASS__, 'maybe_alert_admin' ), 10, 2 );
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
	 * Fetching
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

		update_user_meta( $user_id, 'c365_last_fetch', time() );
		update_user_meta(
			$user_id,
			'c365_last_result',
			sprintf( /* translators: %d: imported count. */ __( '%d new item(s) imported', 'c365-syndicator' ), $imported )
		);

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

		if ( ! function_exists( 'fetch_feed' ) ) {
			require_once ABSPATH . WPINC . '/feed.php';
		}

		// Keep the feed cache shorter than the 5-minute rotation.
		$shorten = function () {
			return 4 * MINUTE_IN_SECONDS;
		};
		add_filter( 'wp_feed_cache_transient_lifetime', $shorten );
		$feed = fetch_feed( C365_Feeds::resolved_url( $row ) );
		remove_filter( 'wp_feed_cache_transient_lifetime', $shorten );

		if ( is_wp_error( $feed ) ) {
			C365_Feeds::record_result( $row->id, $feed->get_error_message(), true );
			return 0;
		}

		// First fetch of a feed backfills everything it exposes (FR-3.10);
		// after that, the normal per-fetch cap applies. Backfilled content is
		// historic, so social sharing is suppressed for the whole run.
		$backfill = ! (int) $row->backfilled;
		$max      = $backfill ? 0 : (int) C365_Settings::get( 'max_items' );
		$items    = $feed->get_items( 0, $max );

		$post_type  = C365_Feeds::post_type_for( $row->type );
		$categories = C365_Feeds::parse_categories( $row->categories );
		$imported   = 0;

		$was_suppressed = class_exists( 'C365_Social' ) ? C365_Social::$suppressed : false;
		if ( $backfill && class_exists( 'C365_Social' ) ) {
			C365_Social::$suppressed = true;
		}

		foreach ( $items as $item ) {
			if ( self::import_item( $user, $item, $post_type, $feed, $categories, (int) $row->id, ! empty( $row->full_content ) ) ) {
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

	/**
	 * Import a single feed item, unless it already exists.
	 *
	 * @param WP_User        $user       Member.
	 * @param SimplePie_Item $item       Feed item.
	 * @param string         $post_type  Target post type.
	 * @param SimplePie      $feed       Parent feed.
	 * @param int[]          $categories Category IDs from the feed record.
	 * @param int            $feed_id    Feed record ID.
	 * @param bool           $fetch_full Scrape the source page for the full article text.
	 * @return bool Whether a post was created.
	 */
	protected static function import_item( $user, $item, $post_type, $feed, $categories, $feed_id, $fetch_full = false ) {
		$guid = $item->get_id();
		if ( ! $guid ) {
			$guid = $item->get_permalink();
		}
		if ( ! $guid || self::guid_exists( $guid ) ) {
			return false;
		}

		$title = wp_strip_all_tags( (string) $item->get_title() );
		$title = trim( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $title ) {
			return false;
		}

		// Title match against pre-existing content (legacy imports without a
		// GUID): stamp the GUID onto the match instead of duplicating it.
		$existing = self::find_by_title( $title, $post_type );
		if ( $existing ) {
			if ( ! get_post_meta( $existing, '_c365_guid', true ) ) {
				update_post_meta( $existing, '_c365_guid', $guid );
			}
			return false;
		}

		// Skip YouTube Shorts (FR-3.9).
		if ( 'c365_video' === $post_type && self::is_short( $item ) ) {
			return false;
		}

		$content = (string) $item->get_content();
		if ( '' === trim( $content ) ) {
			$content = (string) $item->get_description();
		}
		$content = wp_kses_post( $content );

		// Full-content scrape: fetch the actual article page for feeds that
		// only publish summaries. The scraped text is used when it is clearly
		// more complete than what the feed provided; otherwise fall back.
		if ( $fetch_full ) {
			$scraped = self::scrape_full_content( (string) $item->get_permalink() );
			if ( $scraped && strlen( $scraped ) > max( 300, (int) ( strlen( $content ) * 1.2 ) ) ) {
				$content = $scraped;
			}
		}

		// Apply the per-type post body template (Syndication → Templates).
		$content = self::apply_post_template(
			$post_type,
			array(
				'{content}'     => $content,
				'{title}'       => $title,
				'{author}'      => $user->display_name,
				'{excerpt}'     => wp_trim_words( wp_strip_all_tags( (string) $item->get_description() ), 40 ),
				'{source_name}' => wp_strip_all_tags( (string) $feed->get_title() ),
				'{source_url}'  => esc_url_raw( (string) $item->get_permalink() ),
				'{date}'        => (string) $item->get_date( 'j F Y' ),
			)
		);

		$postarr = array(
			'post_type'    => $post_type,
			'post_status'  => C365_Settings::get( 'post_status' ),
			'post_author'  => $user->ID,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => wp_trim_words( wp_strip_all_tags( (string) $item->get_description() ), 40 ),
			'meta_input'   => array(
				'_c365_guid'        => $guid,
				'_c365_feed_id'     => $feed_id,
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

		// Categories: the feed record's mapping, optionally plus the item's own.
		$assign = $categories;
		if ( 'post' === $post_type && C365_Settings::get( 'import_categories' ) ) {
			$assign = array_merge( $assign, self::map_item_categories( $item ) );
		}
		if ( 'post' === $post_type ) {
			if ( $assign ) {
				$postarr['post_category'] = array_unique( $assign );
			}
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		// Non-post types can also carry categories when the record maps them.
		if ( 'post' !== $post_type && $assign ) {
			wp_set_post_categories( $post_id, array_unique( $assign ) );
		}

		// Type-specific extras.
		if ( 'c365_podcast' === $post_type ) {
			self::attach_podcast_meta( $post_id, $item );
		} elseif ( 'c365_video' === $post_type ) {
			self::attach_video_meta( $post_id, $item );
		}

		// Featured image: enclosure/media image, else first image in content.
		if ( C365_Settings::get( 'set_featured_image' ) ) {
			self::attach_featured_image( $post_id, $item, $content );
		}

		return true;
	}

	/**
	 * Fetch an article page and extract its main content ("Advance Scrap").
	 *
	 * @param string $url Article URL.
	 * @return string Sanitised article HTML, or '' when unavailable.
	 */
	public static function scrape_full_content( $url ) {
		if ( ! $url || 0 !== strpos( $url, 'http' ) ) {
			return '';
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'Mozilla/5.0 (compatible; C365Syndicator/1.0; +https://365community.online)',
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		return self::extract_article_html( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Pull the main article body out of a full HTML page: tries <article>,
	 * common content containers, then <main>, picking the candidate with the
	 * most text, and strips navigation/script/share clutter from it.
	 *
	 * @param string $html Full page HTML.
	 * @return string Sanitised article HTML, or '' when nothing usable found.
	 */
	public static function extract_article_html( $html ) {
		if ( ! class_exists( 'DOMDocument' ) || '' === trim( (string) $html ) ) {
			return '';
		}

		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$loaded = $doc->loadHTML( '<?xml encoding="utf-8"?>' . $html, defined( 'LIBXML_NOWARNING' ) ? LIBXML_NOWARNING | LIBXML_NOERROR : 0 );
		libxml_clear_errors();
		if ( ! $loaded ) {
			return '';
		}

		$xpath   = new DOMXPath( $doc );
		$queries = array(
			'//*[@itemprop="articleBody"]',
			'//article',
			'//div[contains(concat(" ", normalize-space(@class), " "), " entry-content ")]',
			'//div[contains(concat(" ", normalize-space(@class), " "), " post-content ")]',
			'//div[contains(concat(" ", normalize-space(@class), " "), " article-content ")]',
			'//div[contains(concat(" ", normalize-space(@class), " "), " content-area ")]',
			'//main',
		);

		$best      = null;
		$best_size = 0;
		foreach ( $queries as $query ) {
			$nodes = $xpath->query( $query );
			if ( ! $nodes ) {
				continue;
			}
			foreach ( $nodes as $node ) {
				$size = strlen( trim( $node->textContent ) );
				if ( $size > $best_size ) {
					$best      = $node;
					$best_size = $size;
				}
			}
			// A named article container beats falling through to <main>.
			if ( $best && $best_size > 500 ) {
				break;
			}
		}

		if ( ! $best || $best_size < 200 ) {
			return '';
		}

		// Strip non-content elements from the chosen container.
		$junk = $xpath->query( './/script | .//style | .//nav | .//aside | .//form | .//footer | .//header | .//iframe', $best );
		if ( $junk ) {
			$remove = array();
			foreach ( $junk as $node ) {
				$remove[] = $node;
			}
			foreach ( $remove as $node ) {
				if ( $node->parentNode ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
					$node->parentNode->removeChild( $node ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName
				}
			}
		}

		$inner = '';
		foreach ( $best->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
			$inner .= $doc->saveHTML( $child );
		}

		return wp_kses_post( trim( $inner ) );
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
	 * Find an existing post of the same type with (effectively) the same
	 * title. Case-insensitivity comes from the DB collation.
	 *
	 * @param string $title     Normalised title.
	 * @param string $post_type Target post type.
	 * @return int Post ID or 0.
	 */
	protected static function find_by_title( $title, $post_type ) {
		global $wpdb;
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft') AND post_title = %s LIMIT 1",
				$post_type,
				$title
			)
		);
	}

	/**
	 * Whether a YouTube feed item is a Short.
	 *
	 * Checks the item link first; when ambiguous (YouTube links Shorts as
	 * ordinary watch URLs in feeds), asks youtube.com/shorts/<id> — HTTP 200
	 * means it is a Short, a redirect means it is a normal video. On any
	 * request failure the item is treated as a normal video.
	 *
	 * @param SimplePie_Item $item Feed item.
	 * @return bool
	 */
	protected static function is_short( $item ) {
		$link = (string) $item->get_permalink();
		if ( false !== strpos( $link, '/shorts/' ) ) {
			return true;
		}

		$video_id = self::video_id_from_item( $item );
		if ( ! $video_id ) {
			return false;
		}

		$response = wp_remote_head(
			'https://www.youtube.com/shorts/' . rawurlencode( $video_id ),
			array(
				'redirection' => 0,
				'timeout'     => 5,
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}

		return 200 === (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Map a feed item's categories onto WP categories, creating as needed.
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
	 * Extract the YouTube video ID from a feed item.
	 *
	 * @param SimplePie_Item $item Feed item.
	 * @return string
	 */
	protected static function video_id_from_item( $item ) {
		// YouTube feed GUIDs look like "yt:video:VIDEOID".
		$guid = (string) $item->get_id();
		if ( preg_match( '/^yt:video:([A-Za-z0-9_-]{6,})$/', $guid, $m ) ) {
			return $m[1];
		}

		$link  = (string) $item->get_permalink();
		$query = wp_parse_url( $link, PHP_URL_QUERY );
		if ( $query ) {
			parse_str( $query, $params );
			if ( ! empty( $params['v'] ) ) {
				return preg_replace( '/[^A-Za-z0-9_-]/', '', $params['v'] );
			}
		}
		return '';
	}

	/**
	 * Store the YouTube video ID for a video item.
	 *
	 * @param int            $post_id Post ID.
	 * @param SimplePie_Item $item    Feed item.
	 */
	protected static function attach_video_meta( $post_id, $item ) {
		$video_id = self::video_id_from_item( $item );
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
	 * Failure alerts (FR-7.4)
	 * -------------------------------------------------------------------- */

	/**
	 * Email the admin when a feed reaches the failure threshold exactly
	 * (so one email per streak, re-armed by a success).
	 *
	 * @param object $row        Feed row (pre-update).
	 * @param int    $fail_count New consecutive failure count.
	 */
	public static function maybe_alert_admin( $row, $fail_count ) {
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
				(string) $row->last_result,
				admin_url( 'admin.php?page=c365-syndication' )
			)
		);
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
