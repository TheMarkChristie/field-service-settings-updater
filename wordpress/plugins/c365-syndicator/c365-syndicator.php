<?php
/**
 * Plugin Name: 365 Community Syndicator
 * Plugin URI:  https://365community.online
 * Description: Community content engine. Every 5 minutes it rotates to the next member and checks their feed records — blog RSS, podcast RSS, YouTube channels, events feeds — creating posts, podcasts, videos, and events with the original title, image, and text, credited to that member with a link to the original source and a "republished with permission" note. New content is auto-shared to the community's LinkedIn, Bluesky, Mastodon, and X accounts. Members manage their own feeds and author-page profile. Built entirely on WordPress core — no other plugins required.
 * Version:     1.6.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      365 Community
 * Author URI:  https://365community.online
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: c365-syndicator
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If another copy of this plugin is already loaded (e.g. two installs in
// different folders), bail out instead of fataling on redeclarations.
if ( defined( 'C365_SYN_VERSION' ) ) {
	return;
}

define( 'C365_SYN_VERSION', '1.6.0' );
define( 'C365_SYN_FILE', __FILE__ );
define( 'C365_SYN_DIR', plugin_dir_path( __FILE__ ) );
define( 'C365_SYN_CRON_HOOK', 'c365_syndicator_rotate' );

require_once C365_SYN_DIR . 'includes/class-c365-feeds.php';
require_once C365_SYN_DIR . 'includes/class-c365-social.php';
require_once C365_SYN_DIR . 'includes/class-c365-settings.php';
require_once C365_SYN_DIR . 'includes/class-c365-types.php';
require_once C365_SYN_DIR . 'includes/class-c365-profile.php';
require_once C365_SYN_DIR . 'includes/class-c365-fetcher.php';
require_once C365_SYN_DIR . 'includes/class-c365-frontend.php';
require_once C365_SYN_DIR . 'includes/class-c365-dashboard.php';

C365_Settings::init();
C365_Types::init();
C365_Profile::init();
C365_Fetcher::init();
C365_Frontend::init();
C365_Social::init();
C365_Dashboard::init();

// Create/upgrade the feeds table on updates too (not just activation).
add_action( 'init', array( 'C365_Feeds', 'install' ), 5 );

/**
 * On activation: create the feeds table (migrating any v1.0.0 profile-field
 * feeds), register content types, and schedule the 5-minute rotation.
 */
if ( ! function_exists( 'c365_syn_activate' ) ) :
function c365_syn_activate() {
	C365_Feeds::install();
	C365_Types::register();
	flush_rewrite_rules();

	if ( ! wp_next_scheduled( C365_SYN_CRON_HOOK ) ) {
		wp_schedule_event( time() + 60, C365_Settings::get( 'interval' ), C365_SYN_CRON_HOOK );
	}
}
endif;
register_activation_hook( __FILE__, 'c365_syn_activate' );

/**
 * On deactivation: clear the schedules.
 */
if ( ! function_exists( 'c365_syn_deactivate' ) ) :
function c365_syn_deactivate() {
	wp_clear_scheduled_hook( C365_SYN_CRON_HOOK );
	wp_clear_scheduled_hook( 'c365_process_share_queue' );
	flush_rewrite_rules();
}
endif;
register_deactivation_hook( __FILE__, 'c365_syn_deactivate' );
