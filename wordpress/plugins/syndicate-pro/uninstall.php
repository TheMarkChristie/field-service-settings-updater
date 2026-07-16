<?php
/**
 * Uninstall cleanup for Syndicate Pro.
 *
 * Removes plugin options, the feeds table, and per-user syndication meta.
 * Imported posts, podcasts, videos, events, and their media are
 * intentionally KEPT — they are your site's content.
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-c365-feeds.php';
C365_Feeds::drop();

delete_option( 'c365_syndicator_settings' );
delete_option( 'c365_social_settings' );
delete_option( 'c365_share_queue' );
delete_option( 'c365_templates' );
delete_option( 'c365_email_settings' );
delete_option( 'c365_rotation_pointer' );

$meta_keys = array(
	// v1.0.0 legacy feed fields (in case uninstall happens before migration).
	'c365_blog_feed',
	'c365_blog_category',
	'c365_podcast_feed',
	'c365_youtube_channel',
	'c365_events_feed',
	// Diagnostics.
	'c365_last_fetch',
	'c365_last_result',
	'c365_last_login',
	'c365_profile_updated',
	'c365_welcomed',
	'c365_digest',
	// Profile / author-page fields.
	'c365_avatar_url',
	'c365_cover_url',
	'c365_tagline',
	'c365_link_website',
	'c365_link_blog',
	'c365_link_linkedin',
	'c365_link_twitter',
	'c365_link_bluesky',
	'c365_link_github',
	'c365_link_youtube',
	'c365_link_mastodon',
	'c365_show_blogs',
	'c365_show_podcasts',
	'c365_show_videos',
	'c365_show_events',
	'c365_show_links',
	'c365_show_bio',
);

foreach ( $meta_keys as $key ) {
	delete_metadata( 'user', 0, $key, '', true );
}
