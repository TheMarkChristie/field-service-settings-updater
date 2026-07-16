<?php
/**
 * wp-admin Dashboard widgets:
 *  - Top 10 posters this month (published content across all four types)
 *  - Failing feeds (consecutive fetch failures)
 *  - Unverified members (never logged in AND never updated their profile)
 *
 * Login times and profile updates are tracked from plugin activation
 * onward via the c365_last_login / c365_profile_updated user meta.
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'C365_Dashboard' ) ) :

class C365_Dashboard {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widgets' ) );
		add_action( 'wp_login', array( __CLASS__, 'record_login' ), 10, 2 );
	}

	/**
	 * Record a member's login time (powers the unverified-members widget).
	 *
	 * @param string  $login Username.
	 * @param WP_User $user  User.
	 */
	public static function record_login( $login, $user ) {
		update_user_meta( $user->ID, 'c365_last_login', time() );
	}

	/**
	 * Register the three widgets (admins only).
	 */
	public static function register_widgets() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'c365_top_posters', __( '365 Community — Top posters this month', 'c365-syndicator' ), array( __CLASS__, 'render_top_posters' ) );
		wp_add_dashboard_widget( 'c365_failing_feeds', __( '365 Community — Failing feeds', 'c365-syndicator' ), array( __CLASS__, 'render_failing_feeds' ) );
		wp_add_dashboard_widget( 'c365_unverified', __( '365 Community — Unverified members', 'c365-syndicator' ), array( __CLASS__, 'render_unverified' ) );
	}

	/**
	 * Top 10 posters this calendar month across posts, events, podcasts,
	 * and videos.
	 */
	public static function render_top_posters() {
		global $wpdb;

		$month_start = gmdate( 'Y-m-01 00:00:00', strtotime( current_time( 'mysql' ) ) );
		$rows        = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_author, COUNT(*) AS total
				 FROM {$wpdb->posts}
				 WHERE post_status = 'publish'
				   AND post_type IN ('post','c365_event','c365_podcast','c365_video')
				   AND post_date >= %s
				 GROUP BY post_author
				 ORDER BY total DESC
				 LIMIT 10",
				$month_start
			)
		);

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No content published yet this month.', 'c365-syndicator' ) . '</p>';
			return;
		}

		echo '<ol style="margin-left:1.2em;">';
		foreach ( $rows as $row ) {
			$user = get_user_by( 'id', (int) $row->post_author );
			$name = $user ? $user->display_name : '#' . (int) $row->post_author;
			$url  = $user ? get_author_posts_url( $user->ID ) : '';
			printf(
				'<li>%s — <strong>%d</strong> %s</li>',
				$url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ),
				(int) $row->total,
				esc_html( _n( 'item', 'items', (int) $row->total, 'c365-syndicator' ) )
			);
		}
		echo '</ol>';
	}

	/**
	 * Feeds with consecutive failures, worst first.
	 */
	public static function render_failing_feeds() {
		$failing = array_filter(
			C365_Feeds::all(),
			function ( $row ) {
				return (int) $row->fail_count > 0 && (int) $row->active;
			}
		);

		if ( ! $failing ) {
			echo '<p>' . esc_html__( 'All feeds are healthy. 🎉', 'c365-syndicator' ) . '</p>';
			return;
		}

		usort(
			$failing,
			function ( $a, $b ) {
				return (int) $b->fail_count <=> (int) $a->fail_count;
			}
		);

		$types = C365_Feeds::types();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Member', 'c365-syndicator' ) . '</th><th>' . esc_html__( 'Feed', 'c365-syndicator' ) . '</th><th>' . esc_html__( 'Fails', 'c365-syndicator' ) . '</th></tr></thead><tbody>';
		foreach ( array_slice( $failing, 0, 10 ) as $row ) {
			$user = get_user_by( 'id', (int) $row->user_id );
			printf(
				'<tr><td>%s</td><td title="%s">%s</td><td>%d</td></tr>',
				esc_html( $user ? $user->display_name : '#' . $row->user_id ),
				esc_attr( $row->last_result . ' — ' . $row->feed_url ),
				esc_html( isset( $types[ $row->type ] ) ? $types[ $row->type ] : $row->type ),
				(int) $row->fail_count
			);
		}
		echo '</tbody></table>';
		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=c365-syndication' ) ),
			esc_html__( 'Open the Syndication dashboard', 'c365-syndicator' )
		);
	}

	/**
	 * Members who have never logged in AND never updated their profile
	 * (tracking starts when this version of the plugin is activated).
	 */
	public static function render_unverified() {
		// Bounded query: fetch only the 15 rows we render plus the total
		// count — hydrating every matching user would blow up on large sites.
		$query = new WP_User_Query(
			array(
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => 'c365_last_login',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => 'c365_profile_updated',
						'compare' => 'NOT EXISTS',
					),
				),
				'orderby'     => 'registered',
				'order'       => 'ASC',
				'number'      => 15,
				'count_total' => true,
			)
		);
		$users = $query->get_results();
		$total = (int) $query->get_total();

		if ( ! $users ) {
			echo '<p>' . esc_html__( 'Every member has logged in or updated their profile. 🎉', 'c365-syndicator' ) . '</p>';
			return;
		}

		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: number of unverified members. */
					_n( '%d member has never logged in or updated their profile:', '%d members have never logged in or updated their profile:', $total, 'c365-syndicator' ),
					$total
				)
			)
		);

		echo '<ul style="margin-left:1.2em;list-style:disc;">';
		foreach ( $users as $user ) {
			printf(
				'<li><a href="%s">%s</a> <span style="color:#888;">(%s)</span></li>',
				esc_url( get_edit_user_link( $user->ID ) ),
				esc_html( $user->display_name ),
				esc_html( $user->user_email )
			);
		}
		echo '</ul>';

		if ( $total > 15 ) {
			printf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'users.php' ) ),
				esc_html( sprintf( /* translators: %d: remaining count. */ __( '…and %d more — see all users', 'c365-syndicator' ), $total - 15 ) )
			);
		}

		echo '<p class="description">' . esc_html__( 'Tracking starts from plugin activation — a member clears this list the first time they log in or save their profile.', 'c365-syndicator' ) . '</p>';
	}
}

endif;
