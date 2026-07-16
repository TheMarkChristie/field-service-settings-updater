<?php
/**
 * Web-page source strategy ("Web page (no RSS)"): discovers article links
 * on a listing page, then scrapes each new article during enrichment.
 *
 * fetch() returns minimal items (the article URL doubles as the GUID) so
 * de-duplication happens before any per-article HTTP; enrich() fetches the
 * article page and fills in title, content, date, image, and site name.
 *
 * @package Synpro_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Synpro_Source_Scrape' ) ) :

class Synpro_Source_Scrape {

	/**
	 * Discover article links on the listing page.
	 *
	 * @param object $row Feed record.
	 * @param int    $max Maximum items (0 = everything discovered).
	 * @return array|WP_Error Normalized (minimal) items.
	 */
	public function fetch( $row, $max ) {
		$body = Synpro_Scraper::http_get( $row->feed_url );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$links = Synpro_Scraper::discover_article_links( $body, $row->feed_url );
		if ( ! $links ) {
			return new WP_Error( 'synpro_no_links', __( 'No article links found on the page', 'syndicate-pro' ) );
		}

		if ( $max > 0 ) {
			$links = array_slice( $links, 0, (int) $max );
		}

		$items = array();
		foreach ( $links as $link ) {
			$items[] = array(
				'guid'           => $link,
				'title'          => '', // Filled by enrich() after dedup.
				'content'        => '',
				'excerpt'        => '',
				'source_name'    => '',
				'source_url'     => $link,
				'timestamp'      => 0,
				'image_url'      => '',
				'category_names' => array(),
			);
		}
		return $items;
	}

	/**
	 * Fetch and parse the article page for an item that passed dedup.
	 *
	 * @param array  $item Normalized (minimal) item.
	 * @param object $row  Feed record.
	 * @return array|null Enriched item, or null when the page is unusable.
	 */
	public function enrich( $item, $row ) {
		$body = Synpro_Scraper::http_get( $item['source_url'] );
		if ( is_wp_error( $body ) ) {
			return null;
		}

		$meta    = Synpro_Scraper::parse_article_meta( $body, $item['source_url'] );
		$content = Synpro_Scraper::extract_article_html( $body );
		if ( '' === $meta['title'] || '' === $content ) {
			return null;
		}

		$item['title']       = $meta['title'];
		$item['content']     = $content;
		$item['excerpt']     = wp_trim_words( wp_strip_all_tags( $content ), 40 );
		$item['source_name'] = $meta['site_name'];
		$item['timestamp']   = (int) $meta['timestamp'];
		$item['image_url']   = $meta['image'];

		return $item;
	}
}

endif;
