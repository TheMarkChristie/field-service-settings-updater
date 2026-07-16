<?php
/**
 * Plugin Name: 365 Community Syndicator
 * Plugin URI:  https://365community.online
 * Description: Community content engine. Every 5 minutes it rotates to the next community member and checks their feeds — blog RSS, podcast RSS, and YouTube channel — creating posts, podcasts, and videos with the original title, image, and text, credited to that member with a link to the original source and a "shared with permission" note. Also provides an Events content type, and lets members manage their own author-page profile (images, links, and which sections are shown). Built entirely on WordPress core — no other plugins required.
 * Version:     1.0.0
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

define( 'C365_SYN_VERSION', '1.0.0' );
define( 'C365_SYN_FILE', __FILE__ );
define( 'C365_SYN_DIR', plugin_dir_path( __FILE__ ) );
define( 'C365_SYN_CRON_HOOK', 'c365_syndicator_rotate' );

require_once C365_SYN_DIR . 'includes/class-c365-settings.php';
require_once C365_SYN_DIR . 'includes/class-c365-types.php';
require_once C365_SYN_DIR . 'includes/class-c365-profile.php';
require_once C365_SYN_DIR . 'includes/class-c365-fetcher.php';
require_once C365_SYN_DIR . 'includes/class-c365-frontend.php';

C365_Settings::init();
C365_Types::init();
C365_Profile::init();
C365_Fetcher::init();
C365_Frontend::init();

/**
 * On activation: register content types and schedule the 5-minute rotation.
 */
function c365_syn_activate() {
	C365_Types::register();
	flush_rewrite_rules();

	if ( ! wp_next_scheduled( C365_SYN_CRON_HOOK ) ) {
		wp_schedule_event( time() + 60, C365_Settings::get( 'interval' ), C365_SYN_CRON_HOOK );
	}
}
register_activation_hook( __FILE__, 'c365_syn_activate' );

/**
 * On deactivation: clear the rotation schedule.
 */
function c365_syn_deactivate() {
	wp_clear_scheduled_hook( C365_SYN_CRON_HOOK );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'c365_syn_deactivate' );
