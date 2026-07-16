<?php
/**
 * Template helpers for Community 365.
 *
 * @package Community365
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get syndication source info for a post, if it was imported by the
 * 365 Community Syndicator plugin (or any plugin using the same meta keys).
 *
 * @param int|null $post_id Post ID. Defaults to current post.
 * @return array|null { name: string, url: string } or null when not syndicated.
 */
function community365_get_source( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id ) {
		return null;
	}

	$url  = get_post_meta( $post_id, '_c365_source_url', true );
	$name = get_post_meta( $post_id, '_c365_source_name', true );

	if ( ! $url && ! $name ) {
		return null;
	}

	if ( ! $name ) {
		$name = wp_parse_url( $url, PHP_URL_HOST );
	}

	return array(
		'name' => $name,
		'url'  => $url,
	);
}

/**
 * Output a source badge linking to the original article.
 *
 * @param int|null $post_id Post ID.
 */
function community365_source_badge( $post_id = null ) {
	if ( ! get_theme_mod( 'c365_show_source_badges', true ) ) {
		return;
	}

	$source = community365_get_source( $post_id );
	if ( ! $source ) {
		return;
	}

	if ( $source['url'] ) {
		printf(
			'<a class="c365-source-badge" href="%1$s" rel="external noopener" target="_blank" title="%2$s">%3$s</a>',
			esc_url( $source['url'] ),
			esc_attr__( 'Read the original article', 'community365' ),
			esc_html( $source['name'] )
		);
	} else {
		printf( '<span class="c365-source-badge">%s</span>', esc_html( $source['name'] ) );
	}
}

/**
 * Output the attribution box on single syndicated posts.
 */
function community365_attribution_box() {
	// Prefer the syndicator plugin's attribution (source link + permission note).
	if ( class_exists( 'C365_Frontend' ) ) {
		echo wp_kses_post( C365_Frontend::attribution_html() );
		return;
	}

	$source = community365_get_source();
	if ( ! $source || ! $source['url'] ) {
		return;
	}
	?>
	<aside class="c365-attribution">
		<span>
			<?php
			printf(
				/* translators: 1: source URL, 2: source name, 3: author name. */
				wp_kses_post( __( '<strong>Original source:</strong> <a href="%1$s" rel="external noopener" target="_blank">%2$s</a>. This content is republished here with the permission of %3$s.', 'community365' ) ),
				esc_url( $source['url'] ),
				esc_html( $source['name'] ),
				esc_html( get_the_author() )
			);
			?>
		</span>
	</aside>
	<?php
}

/**
 * Output entry meta (date, author, categories) for cards and single posts.
 *
 * @param bool $show_author Whether to show the author.
 */
function community365_entry_meta( $show_author = true ) {
	echo '<time datetime="' . esc_attr( get_the_date( DATE_W3C ) ) . '">' . esc_html( get_the_date() ) . '</time>';

	if ( $show_author ) {
		echo ' <span aria-hidden="true">&middot;</span> ';
		the_author_posts_link();
	}
}

/**
 * Whether an author-page section is enabled for a member. Defaults to on.
 * Uses the syndicator plugin's helper when available.
 *
 * @param int    $user_id User ID.
 * @param string $key     Toggle meta key (e.g. c365_show_blogs).
 * @return bool
 */
function community365_author_section_enabled( $user_id, $key ) {
	if ( class_exists( 'C365_Profile' ) ) {
		return C365_Profile::section_enabled( $user_id, $key );
	}
	$value = get_user_meta( $user_id, $key, true );
	return '' === $value ? true : (bool) (int) $value;
}

/**
 * Output the member's link chips (website, socials) on the author page.
 *
 * @param int $user_id User ID.
 */
function community365_author_links( $user_id ) {
	$fields = class_exists( 'C365_Profile' ) ? C365_Profile::link_fields() : array(
		'c365_link_website'  => __( 'Website', 'community365' ),
		'c365_link_blog'     => __( 'Blog', 'community365' ),
		'c365_link_linkedin' => __( 'LinkedIn', 'community365' ),
		'c365_link_twitter'  => __( 'X / Twitter', 'community365' ),
		'c365_link_bluesky'  => __( 'Bluesky', 'community365' ),
		'c365_link_github'   => __( 'GitHub', 'community365' ),
		'c365_link_youtube'  => __( 'YouTube', 'community365' ),
		'c365_link_mastodon' => __( 'Mastodon', 'community365' ),
	);

	$links = array();
	foreach ( $fields as $key => $label ) {
		$url = get_user_meta( $user_id, $key, true );
		if ( $url ) {
			$links[ $label ] = $url;
		}
	}
	if ( ! $links ) {
		return;
	}

	echo '<div class="c365-author-links">';
	foreach ( $links as $label => $url ) {
		printf(
			'<a class="c365-link-chip" href="%1$s" rel="external noopener me" target="_blank">%2$s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}
	echo '</div>';
}

/**
 * Format an event date stored as a datetime-local string.
 *
 * @param string $datetime e.g. "2026-07-16T18:00".
 * @return string
 */
function community365_format_event_date( $datetime ) {
	$timestamp = strtotime( $datetime );
	if ( ! $timestamp ) {
		return $datetime;
	}
	return date_i18n(
		get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
		$timestamp
	);
}

/**
 * The label shown on cards for each content type.
 *
 * @param string $post_type Post type.
 * @return string Empty for regular posts.
 */
function community365_type_label( $post_type ) {
	$labels = array(
		'c365_event'   => __( 'Event', 'community365' ),
		'c365_podcast' => __( 'Podcast', 'community365' ),
		'c365_video'   => __( 'Video', 'community365' ),
	);
	return isset( $labels[ $post_type ] ) ? $labels[ $post_type ] : '';
}
