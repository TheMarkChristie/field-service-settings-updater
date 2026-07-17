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
		if ( ! $name ) {
			$name = $url; // Schemeless/odd source URL: show it rather than an empty badge.
		}
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

/**
 * Monthly events calendar: a compact grid with event days highlighted and
 * linked. Supports ?cal=YYYY-MM prev/next navigation on the same page.
 */
function community365_events_calendar() {
	$requested = isset( $_GET['cal'] ) ? sanitize_text_field( wp_unslash( $_GET['cal'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$base      = preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $requested ) ? strtotime( $requested . '-01' ) : (int) current_time( 'timestamp' );
	$first     = mktime( 0, 0, 0, (int) gmdate( 'n', $base ), 1, (int) gmdate( 'Y', $base ) );
	$days      = (int) gmdate( 't', $first );
	$start_dow = (int) gmdate( 'N', $first ); // 1 = Monday.
	$prefix    = gmdate( 'Y-m', $first );
	$today     = current_time( 'Y-m-d' );

	// Events with a start date in this month.
	$events = get_posts(
		array(
			'post_type'      => 'synpro_event',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_synpro_event_start',
					'value'   => $prefix,
					'compare' => 'LIKE',
				),
			),
		)
	);
	$map = array();
	foreach ( $events as $event ) {
		$start = get_post_meta( $event->ID, '_synpro_event_start', true );
		$day   = (int) substr( (string) $start, 8, 2 );
		if ( $day && ! isset( $map[ $day ] ) ) {
			$map[ $day ] = array(
				'url'   => get_permalink( $event ),
				'title' => get_the_title( $event ),
			);
		}
	}

	// On archives/the front page get_permalink() would return whatever post
	// the last loop left in the global — month arrows must stay on the page.
	$page_url = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
	$page_url = remove_query_arg( 'cal', $page_url );
	$prev     = add_query_arg( 'cal', gmdate( 'Y-m', strtotime( '-1 month', $first ) ), $page_url );
	$next     = add_query_arg( 'cal', gmdate( 'Y-m', strtotime( '+1 month', $first ) ), $page_url );
	?>
	<div class="c365-cal-nav">
		<a href="<?php echo esc_url( $prev ); ?>" aria-label="<?php esc_attr_e( 'Previous month', 'community365' ); ?>">&larr;</a>
		<strong><?php echo esc_html( date_i18n( 'F Y', $first ) ); ?></strong>
		<a href="<?php echo esc_url( $next ); ?>" aria-label="<?php esc_attr_e( 'Next month', 'community365' ); ?>">&rarr;</a>
	</div>
	<table class="c365-cal">
		<caption class="screen-reader-text">
			<?php
			printf(
				/* translators: %s: month name. */
				esc_html__( 'Events in %s', 'community365' ),
				esc_html( date_i18n( 'F Y', $first ) )
			);
			?>
		</caption>
		<thead>
			<tr>
				<?php foreach ( array( __( 'M', 'community365' ), __( 'T', 'community365' ), __( 'W', 'community365' ), __( 'T', 'community365' ), __( 'F', 'community365' ), __( 'S', 'community365' ), __( 'S', 'community365' ) ) as $dow ) : ?>
					<th scope="col"><?php echo esc_html( $dow ); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<tr>
				<?php
				$cell = 1;
				for ( $blank = 1; $blank < $start_dow; $blank++, $cell++ ) {
					echo '<td></td>';
				}
				for ( $day = 1; $day <= $days; $day++, $cell++ ) {
					$classes = array();
					if ( $prefix . '-' . str_pad( (string) $day, 2, '0', STR_PAD_LEFT ) === $today ) {
						$classes[] = 'is-today';
					}
					if ( isset( $map[ $day ] ) ) {
						printf(
							'<td class="has-event %1$s"><a href="%2$s" title="%3$s">%4$d</a></td>',
							esc_attr( implode( ' ', $classes ) ),
							esc_url( $map[ $day ]['url'] ),
							esc_attr( $map[ $day ]['title'] ),
							(int) $day
						);
					} else {
						printf( '<td class="%1$s">%2$d</td>', esc_attr( implode( ' ', $classes ) ), (int) $day );
					}
					if ( 0 === $cell % 7 && $day < $days ) {
						echo '</tr><tr>';
					}
				}
				while ( 0 !== ( $cell - 1 ) % 7 ) {
					echo '<td></td>';
					$cell++;
				}
				?>
			</tr>
		</tbody>
	</table>
	<?php
}

/**
 * Human label for a social URL, worked out from its host.
 *
 * @param string $url Social profile URL.
 * @return string
 */
function community365_social_label( $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$map  = array(
		'linkedin.com'  => 'LinkedIn',
		'twitter.com'   => 'X / Twitter',
		'x.com'         => 'X / Twitter',
		'bsky.app'      => 'Bluesky',
		'youtube.com'   => 'YouTube',
		'facebook.com'  => 'Facebook',
		'instagram.com' => 'Instagram',
		'github.com'    => 'GitHub',
	);
	foreach ( $map as $needle => $label ) {
		if ( false !== strpos( $host, $needle ) ) {
			return $label;
		}
	}
	if ( false !== strpos( $host, 'mastodon' ) || false !== strpos( $host, 'social' ) ) {
		return 'Mastodon';
	}
	return __( 'Follow us', 'community365' );
}
