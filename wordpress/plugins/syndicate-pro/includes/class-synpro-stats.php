<?php
/**
 * Lightweight view counting and member-facing stats.
 *
 * Every single view of a post/event/podcast/video increments an all-time
 * counter and a per-month counter in post meta. Members see their numbers
 * on their own profile screen (Users → Profile): views this month, all-time
 * views, and their top posts.
 *
 * Note: on sites behind full-page caching, cached hits do not execute PHP,
 * so counts are a floor, not an exact analytics figure.
 *
 * @package Synpro_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Synpro_Stats' ) ) :

class Synpro_Stats {

	/**
	 * Post types whose views are counted.
	 *
	 * @var string[]
	 */
	const COUNTED = array( 'post', 'synpro_event', 'synpro_podcast', 'synpro_video' );

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'count_view' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'render_profile_stats' ), 5 );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_stats' ), 5 );
	}

	/**
	 * The current month's meta key, e.g. _synpro_views_202607.
	 *
	 * @return string
	 */
	public static function month_key() {
		return '_synpro_views_' . gmdate( 'Ym' );
	}

	/**
	 * Count a single view on singular content (admins and previews skipped).
	 */
	public static function count_view() {
		if ( is_admin() || is_preview() || ! is_singular( self::COUNTED ) ) {
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return; // Admins browsing their own site aren't readers.
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		update_post_meta( $post_id, '_synpro_views', (int) get_post_meta( $post_id, '_synpro_views', true ) + 1 );
		update_post_meta( $post_id, self::month_key(), (int) get_post_meta( $post_id, self::month_key(), true ) + 1 );
	}

	/**
	 * Sum a view meta key across a member's published content.
	 *
	 * @param int    $user_id  Member ID.
	 * @param string $meta_key _synpro_views or a monthly key.
	 * @return int
	 */
	public static function author_views( $user_id, $meta_key ) {
		global $wpdb;
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT SUM( pm.meta_value )
				 FROM {$wpdb->posts} p
				 JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				 WHERE p.post_author = %d AND p.post_status = 'publish'
				   AND p.post_type IN ('post','synpro_event','synpro_podcast','synpro_video')",
				$meta_key,
				$user_id
			)
		);
	}

	/**
	 * A member's most-viewed posts.
	 *
	 * @param int $user_id Member ID.
	 * @param int $limit   How many.
	 * @return array[] { ID, post_title, views }
	 */
	public static function author_top_posts( $user_id, $limit = 3 ) {
		global $wpdb;
		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, CAST( pm.meta_value AS UNSIGNED ) AS views
				 FROM {$wpdb->posts} p
				 JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_synpro_views'
				 WHERE p.post_author = %d AND p.post_status = 'publish'
				   AND p.post_type IN ('post','synpro_event','synpro_podcast','synpro_video')
				 ORDER BY views DESC
				 LIMIT %d",
				$user_id,
				$limit
			)
		);
	}

	/**
	 * "Your content stats" panel on the profile screen.
	 *
	 * @param WP_User $user User being viewed.
	 */
	public static function render_profile_stats( $user ) {
		if ( ! user_can( $user->ID, 'edit_posts' ) ) {
			return;
		}

		$month_views = self::author_views( $user->ID, self::month_key() );
		$total_views = self::author_views( $user->ID, '_synpro_views' );
		$post_count  = count_user_posts( $user->ID, self::COUNTED, true );
		$top         = self::author_top_posts( $user->ID );
		?>
		<h2><?php esc_html_e( 'Your content stats', 'syndicate-pro' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Views this month', 'syndicate-pro' ); ?></th>
				<td><strong style="font-size:1.4em;"><?php echo esc_html( number_format_i18n( $month_views ) ); ?></strong></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'All-time views', 'syndicate-pro' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $total_views ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Published items', 'syndicate-pro' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( (int) $post_count ) ); ?></td>
			</tr>
			<?php if ( $top ) : ?>
				<tr>
					<th><?php esc_html_e( 'Your most-read posts', 'syndicate-pro' ); ?></th>
					<td>
						<ol style="margin:0 0 0 1.2em;">
							<?php foreach ( $top as $row ) : ?>
								<li>
									<a href="<?php echo esc_url( get_permalink( (int) $row->ID ) ); ?>"><?php echo esc_html( $row->post_title ); ?></a>
									— <?php echo esc_html( number_format_i18n( (int) $row->views ) ); ?> <?php esc_html_e( 'views', 'syndicate-pro' ); ?>
								</li>
							<?php endforeach; ?>
						</ol>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<p class="description"><?php esc_html_e( 'Counts start from when this feature was installed, and cached page views may not be included.', 'syndicate-pro' ); ?></p>
		<?php
	}
}

endif;
