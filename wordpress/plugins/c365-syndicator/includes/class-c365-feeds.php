<?php
/**
 * Feed records: the {prefix}c365_feeds table, CRUD helpers, and the
 * migration from the v1.0.0 per-user profile fields.
 *
 * Each row = one feed belonging to one member: type (blog / podcast /
 * youtube / event), the feed URL (or YouTube channel ID), and the
 * category(ies) its imports are filed under. One member can have any
 * number of feeds, including several of the same type mapped to
 * different categories.
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'C365_Feeds' ) ) :

class C365_Feeds {

	const DB_VERSION = '2';

	/**
	 * Valid feed types.
	 *
	 * @return array type => label.
	 */
	public static function types() {
		return array(
			'blog'    => __( 'Blog RSS', 'c365-syndicator' ),
			'scrape'  => __( 'Web page (no RSS)', 'c365-syndicator' ),
			'podcast' => __( 'Podcast RSS', 'c365-syndicator' ),
			'youtube' => __( 'YouTube channel', 'c365-syndicator' ),
			'event'   => __( 'Events feed', 'c365-syndicator' ),
		);
	}

	/**
	 * Post type each feed type imports into.
	 *
	 * @param string $type Feed type.
	 * @return string
	 */
	public static function post_type_for( $type ) {
		$map = array(
			'blog'    => 'post',
			'scrape'  => 'post',
			'podcast' => 'c365_podcast',
			'youtube' => 'c365_video',
			'event'   => 'c365_event',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : 'post';
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'c365_feeds';
	}

	/**
	 * Create or upgrade the table, then migrate v1.0.0 profile-field feeds.
	 */
	public static function install() {
		global $wpdb;

		if ( get_option( 'c365_feeds_db_version' ) === self::DB_VERSION ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$table   = self::table();

		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT(20) UNSIGNED NOT NULL,
				type VARCHAR(20) NOT NULL DEFAULT 'blog',
				feed_url TEXT NOT NULL,
				categories TEXT,
				active TINYINT(1) NOT NULL DEFAULT 1,
				backfilled TINYINT(1) NOT NULL DEFAULT 0,
				full_content TINYINT(1) NOT NULL DEFAULT 0,
				last_fetch DATETIME DEFAULT NULL,
				last_result TEXT,
				fail_count INT NOT NULL DEFAULT 0,
				created_at DATETIME DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY active (active)
			) {$charset};"
		);

		self::migrate_user_meta();
		update_option( 'c365_feeds_db_version', self::DB_VERSION );
	}

	/**
	 * Migrate v1.0.0 per-user feed meta into feed records, then remove it.
	 */
	protected static function migrate_user_meta() {
		$legacy = array(
			'c365_blog_feed'       => 'blog',
			'c365_podcast_feed'    => 'podcast',
			'c365_youtube_channel' => 'youtube',
			'c365_events_feed'     => 'event',
		);

		foreach ( $legacy as $meta_key => $type ) {
			$users = get_users(
				array(
					'meta_key'     => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_compare' => 'EXISTS',
					'fields'       => 'ID',
				)
			);
			foreach ( $users as $user_id ) {
				$url = get_user_meta( $user_id, $meta_key, true );
				if ( $url ) {
					$categories = array();
					if ( 'blog' === $type ) {
						$cat = (int) get_user_meta( $user_id, 'c365_blog_category', true );
						if ( $cat ) {
							$categories[] = $cat;
						}
					}
					self::add(
						array(
							'user_id'    => (int) $user_id,
							'type'       => $type,
							'feed_url'   => $url,
							'categories' => $categories,
							// v1.0.0 feeds were already being fetched; don't re-backfill.
							'backfilled' => 1,
						)
					);
				}
				delete_user_meta( $user_id, $meta_key );
			}
		}
		delete_metadata( 'user', 0, 'c365_blog_category', '', true );
	}

	/* -----------------------------------------------------------------------
	 * CRUD
	 * -------------------------------------------------------------------- */

	/**
	 * Insert a feed record.
	 *
	 * @param array $data user_id, type, feed_url, categories (int[]), active, backfilled.
	 * @return int New row ID, or 0 on failure.
	 */
	public static function add( $data ) {
		global $wpdb;

		$type = isset( $data['type'] ) && isset( self::types()[ $data['type'] ] ) ? $data['type'] : 'blog';
		$url  = self::sanitize_url_for_type( isset( $data['feed_url'] ) ? $data['feed_url'] : '', $type );
		if ( ! $url || empty( $data['user_id'] ) ) {
			return 0;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'user_id'      => (int) $data['user_id'],
				'type'         => $type,
				'feed_url'     => $url,
				'categories'   => self::serialize_categories( isset( $data['categories'] ) ? $data['categories'] : array() ),
				'active'       => isset( $data['active'] ) ? (int) (bool) $data['active'] : 1,
				'backfilled'   => isset( $data['backfilled'] ) ? (int) (bool) $data['backfilled'] : 0,
				'full_content' => isset( $data['full_content'] ) ? (int) (bool) $data['full_content'] : 0,
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a feed record's editable fields.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data type, feed_url, categories, active.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$row = self::get( $id );
		if ( ! $row ) {
			return false;
		}

		$type = isset( $data['type'] ) && isset( self::types()[ $data['type'] ] ) ? $data['type'] : $row->type;
		$url  = self::sanitize_url_for_type( isset( $data['feed_url'] ) ? $data['feed_url'] : $row->feed_url, $type );
		if ( ! $url ) {
			return false;
		}

		$fields = array(
			'type'         => $type,
			'feed_url'     => $url,
			'categories'   => self::serialize_categories( isset( $data['categories'] ) ? $data['categories'] : self::parse_categories( $row->categories ) ),
			'active'       => isset( $data['active'] ) ? (int) (bool) $data['active'] : (int) $row->active,
			'full_content' => isset( $data['full_content'] ) ? (int) (bool) $data['full_content'] : (int) $row->full_content,
		);

		// Changing the URL makes it a different feed: allow a fresh backfill.
		if ( $url !== $row->feed_url ) {
			$fields['backfilled'] = 0;
			$fields['fail_count'] = 0;
		}

		return false !== $wpdb->update( self::table(), $fields, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Delete a feed record.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Fetch one record.
	 *
	 * @param int $id Row ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * All records for one member.
	 *
	 * @param int $user_id User ID.
	 * @return object[]
	 */
	public static function for_user( $user_id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id', (int) $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * All records (dashboard listing).
	 *
	 * @return object[]
	 */
	public static function all() {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY user_id, id' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Distinct user IDs that have at least one active feed, ascending —
	 * the rotation order. Only members who can still edit posts
	 * (Contributor+) are included.
	 *
	 * @return int[]
	 */
	public static function member_ids() {
		global $wpdb;
		$ids = $wpdb->get_col( 'SELECT DISTINCT user_id FROM ' . self::table() . ' WHERE active = 1 ORDER BY user_id ASC' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = array_map( 'intval', $ids );

		return array_values(
			array_filter(
				$ids,
				function ( $id ) {
					return user_can( $id, 'edit_posts' );
				}
			)
		);
	}

	/**
	 * Record a fetch outcome.
	 *
	 * @param int    $id      Row ID.
	 * @param string $result  Human-readable result.
	 * @param bool   $failed  Whether the fetch errored.
	 * @param bool   $mark_backfilled Set the backfilled flag.
	 */
	public static function record_result( $id, $result, $failed = false, $mark_backfilled = false ) {
		global $wpdb;

		$row = self::get( $id );
		if ( ! $row ) {
			return;
		}

		$fields = array(
			'last_fetch'  => current_time( 'mysql', true ),
			'last_result' => $result,
			'fail_count'  => $failed ? ( (int) $row->fail_count + 1 ) : 0,
		);
		if ( $mark_backfilled ) {
			$fields['backfilled'] = 1;
		}

		$wpdb->update( self::table(), $fields, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		/**
		 * Fires after a fetch outcome is recorded; used for failure alerts.
		 *
		 * @param object $row        Feed row (pre-update).
		 * @param int    $fail_count New consecutive failure count.
		 */
		do_action( 'c365_feed_result_recorded', $row, (int) $fields['fail_count'] );
	}

	/* -----------------------------------------------------------------------
	 * Helpers
	 * -------------------------------------------------------------------- */

	/**
	 * Sanitise the feed_url column value for a type.
	 *
	 * @param string $value Raw value.
	 * @param string $type  Feed type.
	 * @return string Empty string when invalid.
	 */
	public static function sanitize_url_for_type( $value, $type ) {
		$value = trim( (string) $value );
		if ( 'youtube' === $type ) {
			// Accept a channel ID, or a channel URL we can extract it from.
			if ( preg_match( '~youtube\.com/channel/([A-Za-z0-9_-]+)~', $value, $m ) ) {
				$value = $m[1];
			}
			return preg_replace( '/[^A-Za-z0-9_-]/', '', $value );
		}
		return esc_url_raw( $value );
	}

	/**
	 * The resolvable feed URL for a record (expands YouTube channel IDs).
	 *
	 * @param object $row Feed row.
	 * @return string
	 */
	public static function resolved_url( $row ) {
		if ( 'youtube' === $row->type ) {
			return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode( $row->feed_url );
		}
		return $row->feed_url;
	}

	/**
	 * Categories column (comma-separated IDs) to int[].
	 *
	 * @param string $stored Stored value.
	 * @return int[]
	 */
	public static function parse_categories( $stored ) {
		return array_values( array_filter( array_map( 'absint', explode( ',', (string) $stored ) ) ) );
	}

	/**
	 * int[]|mixed to the stored comma-separated form.
	 *
	 * @param mixed $categories Array of IDs (or CSV string).
	 * @return string
	 */
	public static function serialize_categories( $categories ) {
		if ( is_string( $categories ) ) {
			$categories = explode( ',', $categories );
		}
		return implode( ',', array_values( array_filter( array_map( 'absint', (array) $categories ) ) ) );
	}

	/**
	 * Clear a feed's backfilled flag so its next fetch re-imports history.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function reset_backfill( $id ) {
		global $wpdb;
		return false !== $wpdb->update( self::table(), array( 'backfilled' => 0 ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Drop the table (uninstall).
	 */
	public static function drop() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		delete_option( 'c365_feeds_db_version' );
	}
}

endif;
