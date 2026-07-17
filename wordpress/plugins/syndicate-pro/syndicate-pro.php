<?php
/**
 * Plugin Name: Syndicate Pro
 * Plugin URI:  https://365community.online
 * Description: Community content engine. Every 5 minutes it rotates to the next member and checks their feed records — blog RSS, podcast RSS, YouTube channels, events feeds — creating posts, podcasts, videos, and events with the original title, image, and text, credited to that member with a link to the original source and a "republished with permission" note. New content is auto-shared to the community's LinkedIn, Bluesky, Mastodon, and X accounts. Members manage their own feeds and author-page profile. Built entirely on WordPress core — no other plugins required.
 * Version:     2.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      365 Community
 * Author URI:  https://365community.online
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: syndicate-pro
 *
 * @package Synpro_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If another copy of this plugin is already loaded (e.g. two installs in
// different folders), bail out instead of fataling on redeclarations.
if ( defined( 'SYNPRO_VERSION' ) ) {
	return;
}

define( 'SYNPRO_VERSION', '2.2.0' );
define( 'SYNPRO_FILE', __FILE__ );
define( 'SYNPRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'SYNPRO_CRON_HOOK', 'synpro_syndicator_rotate' );

require_once SYNPRO_DIR . 'includes/class-synpro-feeds.php';
require_once SYNPRO_DIR . 'includes/class-synpro-scraper.php';
require_once SYNPRO_DIR . 'includes/class-synpro-source-rss.php';
require_once SYNPRO_DIR . 'includes/class-synpro-source-scrape.php';
require_once SYNPRO_DIR . 'includes/class-synpro-social.php';
require_once SYNPRO_DIR . 'includes/class-synpro-settings.php';
require_once SYNPRO_DIR . 'includes/class-synpro-types.php';
require_once SYNPRO_DIR . 'includes/class-synpro-profile.php';
require_once SYNPRO_DIR . 'includes/class-synpro-fetcher.php';
require_once SYNPRO_DIR . 'includes/class-synpro-frontend.php';
require_once SYNPRO_DIR . 'includes/class-synpro-dashboard.php';
require_once SYNPRO_DIR . 'includes/class-synpro-emails.php';
require_once SYNPRO_DIR . 'includes/class-synpro-stats.php';

Synpro_Settings::init();
Synpro_Types::init();
Synpro_Profile::init();
Synpro_Fetcher::init();
Synpro_Frontend::init();
Synpro_Social::init();
Synpro_Dashboard::init();
Synpro_Emails::init();
Synpro_Stats::init();

// Create/upgrade the feeds table on updates too (not just activation).
add_action( 'init', array( 'Synpro_Feeds', 'install' ), 5 );

/**
 * On activation: create the feeds table (migrating any v1.0.0 profile-field
 * feeds), register content types, and schedule the 5-minute rotation.
 */
if ( ! function_exists( 'synpro_syn_activate' ) ) :
function synpro_syn_activate() {
	Synpro_Feeds::install();
	Synpro_Types::register();
	flush_rewrite_rules();

	// Pre-create the large/secret-bearing options with autoload off — they
	// are only read at fetch/share/settings time, and credentials should not
	// ride along in alloptions on every front-end request.
	add_option( 'synpro_social_settings', array(), '', 'no' );
	add_option( 'synpro_templates', array(), '', 'no' );

	if ( ! wp_next_scheduled( SYNPRO_CRON_HOOK ) ) {
		wp_schedule_event( time() + 60, Synpro_Settings::get( 'interval' ), SYNPRO_CRON_HOOK );
	}
	if ( ! wp_next_scheduled( Synpro_Emails::CRON_HOOK ) ) {
		wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', Synpro_Emails::CRON_HOOK );
	}
	if ( ! wp_next_scheduled( 'synpro_daily_prune' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'synpro_daily_prune' );
	}
}
endif;
register_activation_hook( __FILE__, 'synpro_syn_activate' );

/**
 * On deactivation: clear the schedules.
 */
if ( ! function_exists( 'synpro_syn_deactivate' ) ) :
function synpro_syn_deactivate() {
	wp_clear_scheduled_hook( SYNPRO_CRON_HOOK );
	wp_clear_scheduled_hook( 'synpro_process_share_queue' );
	wp_clear_scheduled_hook( Synpro_Emails::CRON_HOOK );
	wp_clear_scheduled_hook( 'synpro_daily_prune' );
	flush_rewrite_rules();
}
endif;
register_deactivation_hook( __FILE__, 'synpro_syn_deactivate' );
