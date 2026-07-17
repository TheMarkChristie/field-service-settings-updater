<?php
/**
 * Uninstall cleanup for Syndicate Pro.
 *
 * Removes plugin options, the feeds table, and per-user syndication meta.
 * Imported posts, podcasts, videos, events, and their media are
 * intentionally KEPT — they are your site's content.
 *
 * @package Synpro_Syndicator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-synpro-feeds.php';
Synpro_Feeds::drop();

require_once __DIR__ . '/includes/class-synpro-subscribers.php';
Synpro_Subscribers::drop();

delete_option( 'synpro_syndicator_settings' );
delete_option( 'synpro_social_settings' );
delete_option( 'synpro_share_queue' );
delete_option( 'synpro_templates' );
delete_option( 'synpro_email_settings' );
delete_option( 'synpro_rotation_pointer' );
delete_option( 'synpro_digest_sent' );
delete_option( 'synpro_pages_created' );
delete_option( 'synpro_push_settings' );
delete_option( 'synpro_smtp_settings' );

$meta_keys = array(
	// v1.0.0 legacy feed fields (in case uninstall happens before migration).
	'synpro_blog_feed',
	'synpro_blog_category',
	'synpro_podcast_feed',
	'synpro_youtube_channel',
	'synpro_events_feed',
	// Diagnostics.
	'synpro_last_fetch',
	'synpro_last_result',
	'synpro_last_login',
	'synpro_profile_updated',
	'synpro_welcomed',
	'synpro_digest',
	'synpro_app_cats',
	// Profile / author-page fields.
	'synpro_avatar_url',
	'synpro_cover_url',
	'synpro_tagline',
	'synpro_link_website',
	'synpro_link_blog',
	'synpro_link_linkedin',
	'synpro_link_twitter',
	'synpro_link_bluesky',
	'synpro_link_github',
	'synpro_link_youtube',
	'synpro_link_mastodon',
	'synpro_show_blogs',
	'synpro_show_podcasts',
	'synpro_show_videos',
	'synpro_show_events',
	'synpro_show_links',
	'synpro_show_bio',
);

foreach ( $meta_keys as $key ) {
	delete_metadata( 'user', 0, $key, '', true );
}
