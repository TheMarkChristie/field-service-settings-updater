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
 * Syndicate Pro plugin (or any plugin using the same meta keys).
 *
 * @param int|null $post_id Post ID. Defaults to current post.
 * @return array|null { name: string, url: string } or null when not syndicated.
 */
function community365_get_source( $post_id = null ) {
	// The syndicator plugin owns the definition of "source" — delegate so the
	// theme's badges and the plugin's attribution never disagree.
	if ( class_exists( 'Synpro_Frontend' ) ) {
		return Synpro_Frontend::get_source( $post_id );
	}

	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id ) {
		return null;
	}

	$url  = get_post_meta( $post_id, '_synpro_source_url', true );
	$name = get_post_meta( $post_id, '_synpro_source_name', true );

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
	if ( class_exists( 'Synpro_Frontend' ) ) {
		echo wp_kses_post( Synpro_Frontend::attribution_html() );
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
	if ( class_exists( 'Synpro_Profile' ) ) {
		return Synpro_Profile::section_enabled( $user_id, $key );
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
	$fields = class_exists( 'Synpro_Profile' ) ? Synpro_Profile::link_fields() : array(
		'synpro_link_website'  => __( 'Website', 'community365' ),
		'synpro_link_blog'     => __( 'Blog', 'community365' ),
		'synpro_link_linkedin' => __( 'LinkedIn', 'community365' ),
		'synpro_link_twitter'  => __( 'X / Twitter', 'community365' ),
		'synpro_link_bluesky'  => __( 'Bluesky', 'community365' ),
		'synpro_link_github'   => __( 'GitHub', 'community365' ),
		'synpro_link_youtube'  => __( 'YouTube', 'community365' ),
		'synpro_link_mastodon' => __( 'Mastodon', 'community365' ),
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
		'synpro_event'   => __( 'Event', 'community365' ),
		'synpro_podcast' => __( 'Podcast', 'community365' ),
		'synpro_video'   => __( 'Video', 'community365' ),
	);
	return isset( $labels[ $post_type ] ) ? $labels[ $post_type ] : '';
}

/**
 * The content-type filters available on a member's author page, honouring
 * their section toggles. Keyed by the ?type= slug.
 *
 * @param int $author_id Member ID.
 * @return array slug => { post_type, label }
 */
function community365_author_types( $author_id ) {
	$all = array(
		'blogs'    => array( 'post', __( 'Blogs', 'community365' ), 'synpro_show_blogs' ),
		'podcasts' => array( 'synpro_podcast', __( 'Podcasts', 'community365' ), 'synpro_show_podcasts' ),
		'videos'   => array( 'synpro_video', __( 'Videos', 'community365' ), 'synpro_show_videos' ),
		'events'   => array( 'synpro_event', __( 'Events', 'community365' ), 'synpro_show_events' ),
	);

	$types = array();
	foreach ( $all as $slug => $spec ) {
		if ( community365_author_section_enabled( $author_id, $spec[2] ) ) {
			$types[ $slug ] = array(
				'post_type' => $spec[0],
				'label'     => $spec[1],
			);
		}
	}
	return $types;
}

/**
 * A member's most-used categories (from their recent content), for the
 * author-page topic pills.
 *
 * @param int $author_id Member ID.
 * @param int $limit     How many.
 * @return WP_Term[]
 */
function community365_author_top_categories( $author_id, $limit = 4 ) {
	$recent = get_posts(
		array(
			'post_type'      => array( 'post', 'synpro_event', 'synpro_podcast', 'synpro_video' ),
			'author'         => $author_id,
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	if ( ! $recent ) {
		return array();
	}

	$counts = array();
	$terms  = array();
	foreach ( wp_get_object_terms( $recent, 'category' ) as $term ) {
		if ( 'uncategorized' === $term->slug ) {
			continue;
		}
		$counts[ $term->slug ] = isset( $counts[ $term->slug ] ) ? $counts[ $term->slug ] + 1 : 1;
		$terms[ $term->slug ]  = $term;
	}
	arsort( $counts );

	$top = array();
	foreach ( array_slice( array_keys( $counts ), 0, $limit ) as $slug ) {
		$top[] = $terms[ $slug ];
	}
	return $top;
}

/**
 * Total views across a member's published content, when the plugin's view
 * counter is available.
 *
 * @param int $author_id Member ID.
 * @return int
 */
function community365_author_total_views( $author_id ) {
	if ( class_exists( 'Synpro_Stats' ) ) {
		return Synpro_Stats::author_views( $author_id, '_synpro_views' );
	}
	return 0;
}
