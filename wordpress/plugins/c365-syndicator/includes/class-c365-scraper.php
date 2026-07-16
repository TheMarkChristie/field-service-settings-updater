<?php
/**
 * HTML/HTTP scraping utilities shared by the RSS source (full-content
 * enrichment) and the web-page source (article discovery): page fetching,
 * article-body extraction, listing-page link discovery, and article
 * metadata parsing.
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'C365_Scraper' ) ) :

class C365_Scraper {

	/**
	 * Fetch a URL with the syndicator's user agent.
	 *
	 * @param string $url URL.
	 * @return string|WP_Error Response body.
	 */
	public static function http_get( $url ) {
		if ( ! $url || 0 !== strpos( $url, 'http' ) ) {
			return new WP_Error( 'c365_bad_url', __( 'Not a fetchable URL.', 'c365-syndicator' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'Mozilla/5.0 (compatible; C365Syndicator/1.0; +https://365community.online)',
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'c365_http_' . $code, 'HTTP ' . $code );
		}
		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Parse HTML into a DOMXPath, or null when unavailable/unparseable.
	 *
	 * @param string $html Page HTML.
	 * @return DOMXPath|null
	 */
	protected static function load_xpath( $html ) {
		if ( ! class_exists( 'DOMDocument' ) || '' === trim( (string) $html ) ) {
			return null;
		}

		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$loaded = $doc->loadHTML( '<?xml encoding="utf-8"?>' . $html, defined( 'LIBXML_NOWARNING' ) ? LIBXML_NOWARNING | LIBXML_NOERROR : 0 );
		libxml_clear_errors();

		return $loaded ? new DOMXPath( $doc ) : null;
	}

	/**
	 * Fetch an article page and extract its main content ("Advance Scrap").
	 *
	 * @param string $url Article URL.
	 * @return string Sanitised article HTML, or '' when unavailable.
	 */
	public static function scrape_full_content( $url ) {
		$body = self::http_get( $url );
		if ( is_wp_error( $body ) ) {
			return '';
		}
		return self::extract_article_html( $body );
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
		$xpath = self::load_xpath( $html );
		if ( ! $xpath ) {
			return '';
		}

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

		$doc   = $best->ownerDocument; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$inner = '';
		foreach ( $best->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
			$inner .= $doc->saveHTML( $child );
		}

		return wp_kses_post( trim( $inner ) );
	}

	/**
	 * Find likely article URLs on a listing page: links inside <article>
	 * elements, heading links, and entry-title links — same host only,
	 * archive/nav URLs filtered out, document order preserved.
	 *
	 * @param string $html Listing page HTML.
	 * @param string $base Listing page URL (for resolving relative links).
	 * @return string[] Absolute URLs.
	 */
	public static function discover_article_links( $html, $base ) {
		$xpath = self::load_xpath( $html );
		if ( ! $xpath ) {
			return array();
		}

		$queries = array(
			'//article//a[@href]',
			'//h2//a[@href] | //h3//a[@href]',
			'//a[contains(concat(" ", normalize-space(@class), " "), " entry-title ")][@href]',
			'//*[contains(concat(" ", normalize-space(@class), " "), " entry-title ")]//a[@href]',
		);

		$host = wp_parse_url( $base, PHP_URL_HOST );
		$seen = array();
		$urls = array();

		foreach ( $queries as $query ) {
			$nodes = $xpath->query( $query );
			if ( ! $nodes ) {
				continue;
			}
			foreach ( $nodes as $node ) {
				$href = trim( (string) $node->getAttribute( 'href' ) );
				$url  = self::resolve_url( $href, $base );
				if ( ! $url || isset( $seen[ $url ] ) ) {
					continue;
				}
				$seen[ $url ] = true;

				$link_host = wp_parse_url( $url, PHP_URL_HOST );
				if ( ! $link_host || strcasecmp( $link_host, (string) $host ) !== 0 ) {
					continue; // Off-site link.
				}
				if ( preg_match( '~/(category|tag|author|page|feed|wp-login|wp-admin|shop|cart|search)([/?]|$)|[?&]s=|#|\.(jpg|jpeg|png|gif|pdf|zip)$~i', $url ) ) {
					continue; // Archive/nav/asset link.
				}
				if ( untrailingslashit( $url ) === untrailingslashit( $base ) ) {
					continue; // The listing page itself.
				}
				$urls[] = $url;
			}
		}

		return $urls;
	}

	/**
	 * Resolve a possibly-relative href against a base URL.
	 *
	 * @param string $href Href value.
	 * @param string $base Base URL.
	 * @return string Absolute URL or ''.
	 */
	public static function resolve_url( $href, $base ) {
		if ( '' === $href || '#' === $href[0] || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'javascript:' ) ) {
			return '';
		}
		if ( preg_match( '~^https?://~i', $href ) ) {
			return esc_url_raw( $href );
		}

		// Core's resolver handles ports, ../ segments, and scheme-relative
		// URLs correctly — don't reimplement it.
		if ( ! class_exists( 'WP_Http' ) ) {
			require_once ABSPATH . WPINC . '/class-http.php';
		}
		if ( class_exists( 'WP_Http' ) && method_exists( 'WP_Http', 'make_absolute_url' ) ) {
			return esc_url_raw( WP_Http::make_absolute_url( $href, $base ) );
		}

		// Minimal fallback (should not be reached on real WordPress).
		$parts = wp_parse_url( $base );
		if ( empty( $parts['host'] ) ) {
			return '';
		}
		$origin = ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		if ( 0 === strpos( $href, '//' ) ) {
			return esc_url_raw( ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . ':' . $href );
		}
		if ( 0 === strpos( $href, '/' ) ) {
			return esc_url_raw( $origin . $href );
		}
		$path = isset( $parts['path'] ) ? preg_replace( '~/[^/]*$~', '/', $parts['path'] ) : '/';
		return esc_url_raw( $origin . $path . $href );
	}

	/**
	 * Pull title / date / image / site name out of an article page's markup
	 * (Open Graph and standard meta first, visible elements as fallback).
	 *
	 * @param string $html Article page HTML.
	 * @param string $url  Article URL (host used as last-resort site name).
	 * @return array { title, timestamp, image, site_name }
	 */
	public static function parse_article_meta( $html, $url ) {
		$meta = array(
			'title'     => '',
			'timestamp' => 0,
			'image'     => '',
			'site_name' => (string) wp_parse_url( $url, PHP_URL_HOST ),
		);

		$grab = function ( $pattern ) use ( $html ) {
			return preg_match( $pattern, $html, $m ) ? html_entity_decode( trim( $m[1] ), ENT_QUOTES, 'UTF-8' ) : '';
		};

		$meta['title'] = $grab( '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i' );
		if ( ! $meta['title'] ) {
			$meta['title'] = $grab( '/<h1[^>]*>(.*?)<\/h1>/is' );
		}
		if ( ! $meta['title'] ) {
			$meta['title'] = $grab( '/<title[^>]*>(.*?)<\/title>/is' );
		}
		$meta['title'] = wp_strip_all_tags( $meta['title'] );

		$published = $grab( '/<meta[^>]+property=["\']article:published_time["\'][^>]+content=["\']([^"\']+)["\']/i' );
		if ( ! $published ) {
			$published = $grab( '/<time[^>]+datetime=["\']([^"\']+)["\']/i' );
		}
		if ( $published ) {
			$timestamp = strtotime( $published );
			if ( $timestamp ) {
				$meta['timestamp'] = $timestamp;
			}
		}

		$meta['image'] = $grab( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i' );

		$site_name = $grab( '/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\']/i' );
		if ( $site_name ) {
			$meta['site_name'] = $site_name;
		}

		return $meta;
	}
}

endif;
