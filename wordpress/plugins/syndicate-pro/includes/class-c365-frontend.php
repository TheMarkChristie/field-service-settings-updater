<?php
/**
 * Front-end output: source attribution, permission note, and canonical URLs.
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'C365_Frontend' ) ) :

class C365_Frontend {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'append_attribution' ), 20 );
		add_filter( 'get_canonical_url', array( __CLASS__, 'canonical_url' ), 10, 2 );
	}

	/**
	 * Source info for a post imported by the syndicator.
	 *
	 * @param int|null $post_id Post ID (defaults to current).
	 * @return array|null { url: string, name: string } or null.
	 */
	public static function get_source( $post_id = null ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		if ( ! $post_id ) {
			return null;
		}
		$url  = get_post_meta( $post_id, '_c365_source_url', true );
		$name = get_post_meta( $post_id, '_c365_source_name', true );
		if ( ! $url && ! $name ) {
			return null;
		}
		if ( ! $name && $url ) {
			$name = wp_parse_url( $url, PHP_URL_HOST );
		}
		return array(
			'url'  => $url,
			'name' => $name,
		);
	}

	/**
	 * The attribution HTML: source link + permission note.
	 *
	 * @param int|null $post_id Post ID.
	 * @return string
	 */
	public static function attribution_html( $post_id = null ) {
		// The site-wide attribution toggle is authoritative everywhere —
		// including themes that render this box themselves.
		if ( ! C365_Settings::get( 'attribution' ) ) {
			return '';
		}

		$post_id = $post_id ? $post_id : get_the_ID();
		$source  = self::get_source( $post_id );
		if ( ! $source || ! $source['url'] ) {
			return '';
		}

		$author = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );

		$html = '<div class="c365-attribution">';
		$html .= sprintf(
			/* translators: 1: original article URL, 2: source name. */
			__( '<strong>Original source:</strong> <a href="%1$s" rel="external noopener" target="_blank">%2$s</a>.', 'syndicate-pro' ),
			esc_url( $source['url'] ),
			esc_html( $source['name'] ? $source['name'] : $source['url'] )
		);
		$html .= ' ';
		$html .= sprintf(
			/* translators: %s: author display name. */
			esc_html__( 'This content is republished here with the permission of %s.', 'syndicate-pro' ),
			esc_html( $author ? $author : __( 'the original author', 'syndicate-pro' ) )
		);
		$html .= '</div>';

		/**
		 * Filter the attribution HTML, e.g. to reword the permission note.
		 *
		 * @param string $html    Attribution markup.
		 * @param int    $post_id Post ID.
		 * @param array  $source  { url, name }.
		 */
		return apply_filters( 'c365_attribution_html', $html, $post_id, $source );
	}

	/**
	 * Append the attribution below the content on single views.
	 *
	 * Skipped when the active theme declares `c365-attribution` support and
	 * renders its own attribution UI (like the Community 365 theme does).
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function append_attribution( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! C365_Settings::get( 'attribution' ) ) {
			return $content;
		}
		if ( current_theme_supports( 'c365-attribution' ) ) {
			return $content;
		}

		$attribution = self::attribution_html();
		if ( ! $attribution ) {
			return $content;
		}

		return $content . "\n" . $attribution;
	}

	/**
	 * Point rel=canonical at the original article for syndicated content.
	 *
	 * @param string  $canonical Canonical URL.
	 * @param WP_Post $post      Post object.
	 * @return string
	 */
	public static function canonical_url( $canonical, $post ) {
		if ( ! C365_Settings::get( 'canonical' ) ) {
			return $canonical;
		}
		$source = self::get_source( $post->ID );
		if ( $source && $source['url'] ) {
			return $source['url'];
		}
		return $canonical;
	}
}

endif;
