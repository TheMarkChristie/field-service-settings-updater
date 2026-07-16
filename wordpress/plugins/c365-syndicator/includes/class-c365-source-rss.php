<?php
/**
 * RSS/Atom source strategy: turns SimplePie feed items (blogs, podcasts,
 * YouTube channels, event feeds) into the fetcher's normalized item shape.
 *
 * Normalized item keys: guid, title, content, excerpt, source_name,
 * source_url, timestamp, image_url, category_names, and the type extras
 * (audio_url, duration, video_id, event_start, event_end, event_location,
 * event_url).
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'C365_Source_Rss' ) ) :

class C365_Source_Rss {

	/**
	 * Fetch and normalize up to $max items for a feed record.
	 *
	 * @param object $row Feed record.
	 * @param int    $max Maximum items (0 = everything the feed exposes).
	 * @return array|WP_Error Normalized items.
	 */
	public function fetch( $row, $max ) {
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
			return $feed;
		}

		$items = array();
		foreach ( $feed->get_items( 0, (int) $max ) as $sp_item ) {
			$items[] = $this->normalize( $sp_item, $feed, $row );
		}
		return $items;
	}

	/**
	 * Post-dedup enrichment: skip YouTube Shorts, and fetch the full article
	 * text for summary-only blog feeds when the record asks for it. Runs only
	 * for items that passed de-duplication, so no HTTP is wasted on repeats.
	 *
	 * @param array  $item Normalized item.
	 * @param object $row  Feed record.
	 * @return array|null Enriched item, or null to skip it.
	 */
	public function enrich( $item, $row ) {
		if ( 'youtube' === $row->type && ! empty( $item['video_id'] ) && $this->is_short( $item['video_id'] ) ) {
			return null;
		}

		if ( ! empty( $row->full_content ) && $item['source_url'] ) {
			$scraped = C365_Scraper::scrape_full_content( $item['source_url'] );
			if ( $scraped && strlen( $scraped ) > max( 300, (int) ( strlen( $item['content'] ) * 1.2 ) ) ) {
				$item['content'] = $scraped;
			}
		}

		return $item;
	}

	/**
	 * SimplePie item → normalized array.
	 *
	 * @param SimplePie_Item $sp_item Feed item.
	 * @param SimplePie      $feed    Parent feed.
	 * @param object         $row     Feed record.
	 * @return array
	 */
	protected function normalize( $sp_item, $feed, $row ) {
		$guid = $sp_item->get_id();
		if ( ! $guid ) {
			$guid = $sp_item->get_permalink();
		}

		$title = wp_strip_all_tags( (string) $sp_item->get_title() );
		$title = trim( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) );

		$content = (string) $sp_item->get_content();
		if ( '' === trim( $content ) ) {
			$content = (string) $sp_item->get_description();
		}

		$item = array(
			'guid'           => (string) $guid,
			'title'          => $title,
			'content'        => wp_kses_post( $content ),
			'excerpt'        => wp_trim_words( wp_strip_all_tags( (string) $sp_item->get_description() ), 40 ),
			'source_name'    => wp_strip_all_tags( (string) $feed->get_title() ),
			'source_url'     => esc_url_raw( (string) $sp_item->get_permalink() ),
			'timestamp'      => (int) $sp_item->get_date( 'U' ),
			'image_url'      => '',
			'category_names' => array(),
		);

		foreach ( (array) $sp_item->get_categories() as $category ) {
			$label = wp_strip_all_tags( (string) $category->get_label() );
			if ( '' !== $label ) {
				$item['category_names'][] = $label;
			}
		}

		// Enclosure image (media:content / media:thumbnail).
		$enclosure = $sp_item->get_enclosure();
		if ( $enclosure ) {
			$thumb = $enclosure->get_thumbnail();
			if ( $thumb ) {
				$item['image_url'] = $thumb;
			} elseif ( $enclosure->get_link() && 0 === strpos( (string) $enclosure->get_type(), 'image' ) ) {
				$item['image_url'] = $enclosure->get_link();
			}
		}

		// Type extras.
		switch ( $row->type ) {
			case 'podcast':
				if ( $enclosure && $enclosure->get_link() ) {
					$type = (string) $enclosure->get_type();
					if ( '' === $type || 0 === strpos( $type, 'audio' ) ) {
						$item['audio_url'] = esc_url_raw( $enclosure->get_link() );
					}
					$duration = $enclosure->get_duration( true );
					if ( $duration ) {
						$item['duration'] = sanitize_text_field( $duration );
					}
				}
				break;

			case 'youtube':
				$item['video_id'] = $this->video_id( $sp_item );
				if ( $item['video_id'] ) {
					$item['image_url'] = 'https://i.ytimg.com/vi/' . rawurlencode( $item['video_id'] ) . '/hqdefault.jpg';
				}
				break;

			case 'event':
				$this->normalize_event( $sp_item, $item );
				break;
		}

		return $item;
	}

	/**
	 * Extract event details from RSS event/xCal modules where present.
	 *
	 * @param SimplePie_Item $sp_item Feed item.
	 * @param array          $item    Normalized item (by reference).
	 */
	protected function normalize_event( $sp_item, &$item ) {
		$namespaces = array(
			'http://purl.org/rss/1.0/modules/event/' => array(
				'start'    => 'startdate',
				'end'      => 'enddate',
				'location' => 'location',
			),
			'urn:ietf:params:xml:ns:xcal'             => array(
				'start'    => 'dtstart',
				'end'      => 'dtend',
				'location' => 'location',
			),
		);

		$get_tag = function ( $ns, $tag ) use ( $sp_item ) {
			$tags = $sp_item->get_item_tags( $ns, $tag );
			return isset( $tags[0]['data'] ) ? trim( (string) $tags[0]['data'] ) : '';
		};

		foreach ( $namespaces as $ns => $tags ) {
			$start = $get_tag( $ns, $tags['start'] );
			if ( ! $start ) {
				continue;
			}
			$start_ts = strtotime( $start );
			if ( $start_ts ) {
				$item['event_start'] = gmdate( 'Y-m-d\TH:i', $start_ts );
			}
			$end    = $get_tag( $ns, $tags['end'] );
			$end_ts = $end ? strtotime( $end ) : 0;
			if ( $end_ts ) {
				$item['event_end'] = gmdate( 'Y-m-d\TH:i', $end_ts );
			}
			$location = $get_tag( $ns, $tags['location'] );
			if ( $location ) {
				$item['event_location'] = sanitize_text_field( $location );
			}
			break;
		}

		// The original page is the natural "more info" link for the event.
		if ( $item['source_url'] ) {
			$item['event_url'] = $item['source_url'];
		}
	}

	/**
	 * Extract the YouTube video ID from a feed item.
	 *
	 * @param SimplePie_Item $sp_item Feed item.
	 * @return string
	 */
	protected function video_id( $sp_item ) {
		// YouTube feed GUIDs look like "yt:video:VIDEOID".
		$guid = (string) $sp_item->get_id();
		if ( preg_match( '/^yt:video:([A-Za-z0-9_-]{6,})$/', $guid, $m ) ) {
			return $m[1];
		}

		$link  = (string) $sp_item->get_permalink();
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
	 * Whether a YouTube video is a Short. Verdicts are cached so interrupted
	 * backfills never re-ask, and the timeout is short so a slow response
	 * degrades to "not a Short" rather than stalling the cron tick.
	 *
	 * @param string $video_id Video ID.
	 * @return bool
	 */
	protected function is_short( $video_id ) {
		$cache_key = 'c365_short_' . $video_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return 'yes' === $cached;
		}

		$response = wp_remote_head(
			'https://www.youtube.com/shorts/' . rawurlencode( $video_id ),
			array(
				'redirection' => 0,
				'timeout'     => 3,
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$is_short = 200 === (int) wp_remote_retrieve_response_code( $response );
		set_transient( $cache_key, $is_short ? 'yes' : 'no', WEEK_IN_SECONDS );

		return $is_short;
	}
}

endif;
