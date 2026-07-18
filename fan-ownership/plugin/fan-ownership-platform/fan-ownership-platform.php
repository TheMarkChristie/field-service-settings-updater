<?php
/**
 * Plugin Name:       Fan Ownership Platform
 * Description:       The fan-owned club platform: shares on a tiered ladder, weighted secret ballots, ideas, questions, meetings, financial transparency, decision register, board workspace, match centre, and the app API. Built to the Perth Panthers specification (spec v1.1, 139 decisions).
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Perth Panthers
 * License:           MIT
 * Text Domain:       fan-ownership
 */

defined( 'ABSPATH' ) || exit;

define( 'FOP_VERSION', '0.1.0' );
define( 'FOP_FILE', __FILE__ );
define( 'FOP_DIR', plugin_dir_path( __FILE__ ) );
define( 'FOP_URL', plugin_dir_url( __FILE__ ) );

// Foundations.
require_once FOP_DIR . 'includes/helpers.php';
require_once FOP_DIR . 'includes/class-fop-config.php';
require_once FOP_DIR . 'includes/class-fop-roles.php';
require_once FOP_DIR . 'includes/class-fop-post-types.php';
require_once FOP_DIR . 'includes/class-fop-access.php';
require_once FOP_DIR . 'includes/class-fop-audit.php';

// Phase 1 — Own.
require_once FOP_DIR . 'includes/class-fop-membership.php';
require_once FOP_DIR . 'includes/class-fop-shares.php';
require_once FOP_DIR . 'includes/class-fop-woocommerce.php';
require_once FOP_DIR . 'includes/class-fop-gifts.php';
require_once FOP_DIR . 'includes/class-fop-register.php';
require_once FOP_DIR . 'includes/class-fop-certificates.php';
require_once FOP_DIR . 'includes/class-fop-badges.php';
require_once FOP_DIR . 'includes/class-fop-onboarding.php';
require_once FOP_DIR . 'includes/class-fop-comms.php';
require_once FOP_DIR . 'includes/class-fop-privacy.php';
require_once FOP_DIR . 'includes/class-fop-editorial.php';

// Phase 2 — Decide.
require_once FOP_DIR . 'includes/class-fop-ballots.php';
require_once FOP_DIR . 'includes/class-fop-ballot-lifecycle.php';
require_once FOP_DIR . 'includes/class-fop-ideas.php';
require_once FOP_DIR . 'includes/class-fop-questions.php';
require_once FOP_DIR . 'includes/class-fop-meetings.php';
require_once FOP_DIR . 'includes/class-fop-decisions.php';
require_once FOP_DIR . 'includes/class-fop-financials.php';
require_once FOP_DIR . 'includes/class-fop-community.php';
require_once FOP_DIR . 'includes/class-fop-moderation.php';
require_once FOP_DIR . 'includes/class-fop-chapters.php';
require_once FOP_DIR . 'includes/class-fop-board.php';

// Phase 3 — Watch.
require_once FOP_DIR . 'includes/class-fop-match-centre.php';
require_once FOP_DIR . 'includes/class-fop-chat.php';
require_once FOP_DIR . 'includes/class-fop-media.php';
require_once FOP_DIR . 'includes/api/class-fop-jwt.php';
require_once FOP_DIR . 'includes/api/class-fop-rest-api.php';

// Admin.
require_once FOP_DIR . 'admin/class-fop-admin.php';
require_once FOP_DIR . 'admin/class-fop-dashboard.php';
require_once FOP_DIR . 'includes/class-fop-shortcodes.php';

/**
 * Boot all modules.
 */
function fop_boot() {
	$modules = array(
		'FOP_Config',
		'FOP_Roles',
		'FOP_Post_Types',
		'FOP_Access',
		'FOP_Audit',
		'FOP_Membership',
		'FOP_Shares',
		'FOP_WooCommerce',
		'FOP_Gifts',
		'FOP_Register',
		'FOP_Certificates',
		'FOP_Badges',
		'FOP_Onboarding',
		'FOP_Comms',
		'FOP_Privacy',
		'FOP_Editorial',
		'FOP_Ballots',
		'FOP_Ballot_Lifecycle',
		'FOP_Ideas',
		'FOP_Questions',
		'FOP_Meetings',
		'FOP_Decisions',
		'FOP_Financials',
		'FOP_Community',
		'FOP_Moderation',
		'FOP_Chapters',
		'FOP_Board',
		'FOP_Match_Centre',
		'FOP_Chat',
		'FOP_Media',
		'FOP_REST_API',
		'FOP_Admin',
		'FOP_Dashboard',
		'FOP_Shortcodes',
	);
	foreach ( $modules as $module ) {
		if ( class_exists( $module ) && method_exists( $module, 'init' ) ) {
			$module::init();
		}
	}
}
add_action( 'plugins_loaded', 'fop_boot' );

/**
 * Front-end assets.
 */
function fop_assets() {
	wp_register_style( 'fop', FOP_URL . 'assets/css/fop.css', array(), FOP_VERSION );
	wp_register_script( 'fop', FOP_URL . 'assets/js/fop.js', array(), FOP_VERSION, true );
	wp_localize_script(
		'fop',
		'fopConfig',
		array(
			'restUrl' => esc_url_raw( rest_url( 'fop/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'working' => __( 'Working…', 'fan-ownership' ),
				'error'   => __( 'Something went wrong. Please try again.', 'fan-ownership' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'fop_assets' );

register_activation_hook( __FILE__, 'fop_activate' );
register_deactivation_hook( __FILE__, 'fop_deactivate' );

function fop_activate() {
	FOP_Roles::install();
	FOP_Post_Types::register_all();
	FOP_Register::install_tables();
	FOP_Ballot_Lifecycle::schedule_cron();
	flush_rewrite_rules();
}

function fop_deactivate() {
	FOP_Ballot_Lifecycle::unschedule_cron();
	flush_rewrite_rules();
}

/**
 * Companion-plugin notices: integrate when present, degrade clearly when not.
 */
function fop_dependency_notices() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$missing = array();
	if ( ! class_exists( 'WooCommerce' ) ) {
		$missing[] = __( 'WooCommerce (share checkout is disabled until it is active)', 'fan-ownership' );
	}
	if ( ! class_exists( 'bbPress' ) ) {
		$missing[] = __( 'bbPress (the member forum is disabled until it is active)', 'fan-ownership' );
	}
	if ( ! has_action( 'fop_award_badge' ) && ! apply_filters( 'fop_badge_provider_present', false ) ) {
		$missing[] = __( 'Badge plugin integration (no handler found for the fop_award_badge action — badges will queue until one is connected)', 'fan-ownership' );
	}
	if ( $missing ) {
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Fan Ownership Platform — companion plugins:', 'fan-ownership' ) . '</strong> ' . esc_html( implode( '; ', $missing ) ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'fop_dependency_notices' );
