<?php
/**
 * Uninstall cleanup for 365 Community Syndicator.
 *
 * Removes plugin options and per-user syndication meta. Imported posts,
 * podcasts, videos, and events are intentionally KEPT — they are your
 * site's content.
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'c365_syndicator_settings' );
delete_option( 'c365_rotation_pointer' );

$meta_keys = array(
	'c365_blog_feed',
	'c365_blog_category',
	'c365_podcast_feed',
	'c365_youtube_channel',
	'c365_events_feed',
	'c365_last_fetch',
	'c365_last_result',
);

foreach ( $meta_keys as $key ) {
	delete_metadata( 'user', 0, $key, '', true );
}
