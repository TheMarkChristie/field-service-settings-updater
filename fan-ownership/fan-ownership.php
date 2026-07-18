<?php
/**
 * Plugin Name:       Fan Ownership
 * Plugin URI:        https://github.com/TheMarkChristie/field-service-settings-updater
 * Description:       A members portal for fan-owned sports clubs: behind-the-scenes content, voting on club decisions, idea submission, meetings with RSVP, questions to the board, match/training/interview video, and financial statements — all restricted to logged-in fan owners.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mark Christie
 * License:           MIT
 * Text Domain:       fan-ownership
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'FO_VERSION', '1.0.0' );
define( 'FO_PLUGIN_FILE', __FILE__ );
define( 'FO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FO_PLUGIN_DIR . 'includes/helpers.php';
require_once FO_PLUGIN_DIR . 'includes/class-fo-roles.php';
require_once FO_PLUGIN_DIR . 'includes/class-fo-post-types.php';
require_once FO_PLUGIN_DIR . 'includes/class-fo-access.php';
require_once FO_PLUGIN_DIR . 'includes/class-fo-meta-boxes.php';
require_once FO_PLUGIN_DIR . 'includes/class-fo-voting.php';
require_once FO_PLUGIN_DIR . 'includes/class-fo-submissions.php';
require_once FO_PLUGIN_DIR . 'includes/class-fo-meetings.php';
require_once FO_PLUGIN_DIR . 'includes/class-fo-shortcodes.php';
require_once FO_PLUGIN_DIR . 'includes/class-fo-settings.php';

/**
 * Boot the plugin.
 */
function fo_init_plugin() {
	FO_Roles::init();
	FO_Post_Types::init();
	FO_Access::init();
	FO_Meta_Boxes::init();
	FO_Voting::init();
	FO_Submissions::init();
	FO_Meetings::init();
	FO_Shortcodes::init();
	FO_Settings::init();
}
add_action( 'plugins_loaded', 'fo_init_plugin' );

/**
 * Front-end assets, only loaded where the plugin renders something.
 */
function fo_register_assets() {
	wp_register_style(
		'fan-ownership',
		FO_PLUGIN_URL . 'assets/css/fan-ownership.css',
		array(),
		FO_VERSION
	);
	wp_register_script(
		'fan-ownership',
		FO_PLUGIN_URL . 'assets/js/fan-ownership.js',
		array(),
		FO_VERSION,
		true
	);
	wp_localize_script(
		'fan-ownership',
		'foConfig',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fo_ajax' ),
			'i18n'    => array(
				'working' => __( 'Working…', 'fan-ownership' ),
				'error'   => __( 'Something went wrong. Please try again.', 'fan-ownership' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'fo_register_assets' );

register_activation_hook( __FILE__, 'fo_activate' );
register_deactivation_hook( __FILE__, 'fo_deactivate' );

function fo_activate() {
	require_once FO_PLUGIN_DIR . 'includes/class-fo-roles.php';
	require_once FO_PLUGIN_DIR . 'includes/class-fo-post-types.php';
	FO_Roles::add_roles();
	FO_Post_Types::register();
	flush_rewrite_rules();
}

function fo_deactivate() {
	flush_rewrite_rules();
}
