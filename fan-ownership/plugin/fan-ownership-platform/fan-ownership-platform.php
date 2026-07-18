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

define( 'PRX3_VERSION', '0.1.0' );
define( 'PRX3_FILE', __FILE__ );
define( 'PRX3_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRX3_URL', plugin_dir_url( __FILE__ ) );

// Foundations.
require_once PRX3_DIR . 'includes/helpers.php';
require_once PRX3_DIR . 'includes/class-prx3-config.php';
require_once PRX3_DIR . 'includes/class-prx3-roles.php';
require_once PRX3_DIR . 'includes/class-prx3-post-types.php';
require_once PRX3_DIR . 'includes/class-prx3-access.php';
require_once PRX3_DIR . 'includes/class-prx3-audit.php';

// Phase 1 — Own.
require_once PRX3_DIR . 'includes/class-prx3-membership.php';
require_once PRX3_DIR . 'includes/class-prx3-shares.php';
require_once PRX3_DIR . 'includes/class-prx3-woocommerce.php';
require_once PRX3_DIR . 'includes/class-prx3-gifts.php';
require_once PRX3_DIR . 'includes/class-prx3-register.php';
require_once PRX3_DIR . 'includes/class-prx3-certificates.php';
require_once PRX3_DIR . 'includes/class-prx3-badges.php';
require_once PRX3_DIR . 'includes/class-prx3-onboarding.php';
require_once PRX3_DIR . 'includes/class-prx3-comms.php';
require_once PRX3_DIR . 'includes/class-prx3-privacy.php';
require_once PRX3_DIR . 'includes/class-prx3-editorial.php';

// Phase 2 — Decide.
require_once PRX3_DIR . 'includes/class-prx3-ballots.php';
require_once PRX3_DIR . 'includes/class-prx3-ballot-lifecycle.php';
require_once PRX3_DIR . 'includes/class-prx3-ideas.php';
require_once PRX3_DIR . 'includes/class-prx3-questions.php';
require_once PRX3_DIR . 'includes/class-prx3-meetings.php';
require_once PRX3_DIR . 'includes/class-prx3-decisions.php';
require_once PRX3_DIR . 'includes/class-prx3-financials.php';
require_once PRX3_DIR . 'includes/class-prx3-community.php';
require_once PRX3_DIR . 'includes/class-prx3-moderation.php';
require_once PRX3_DIR . 'includes/class-prx3-chapters.php';
require_once PRX3_DIR . 'includes/class-prx3-board.php';

// Phase 3 — Watch.
require_once PRX3_DIR . 'includes/class-prx3-match-centre.php';
require_once PRX3_DIR . 'includes/class-prx3-chat.php';
require_once PRX3_DIR . 'includes/class-prx3-media.php';
require_once PRX3_DIR . 'includes/class-prx3-ticketing.php';
require_once PRX3_DIR . 'includes/class-prx3-brand.php';
require_once PRX3_DIR . 'includes/class-prx3-disputes.php';
require_once PRX3_DIR . 'includes/class-prx3-commitments.php';
require_once PRX3_DIR . 'includes/class-prx3-players.php';
require_once PRX3_DIR . 'includes/class-prx3-agreements.php';
require_once PRX3_DIR . 'includes/api/class-prx3-jwt.php';
require_once PRX3_DIR . 'includes/api/class-prx3-rest-api.php';

// Admin.
require_once PRX3_DIR . 'admin/class-prx3-admin.php';
require_once PRX3_DIR . 'admin/class-prx3-dashboard.php';
require_once PRX3_DIR . 'includes/class-prx3-shortcodes.php';

/**
 * Boot all modules.
 */
function prx3_boot() {
	$modules = array(
		'PRX3_Config',
		'PRX3_Roles',
		'PRX3_Post_Types',
		'PRX3_Access',
		'PRX3_Audit',
		'PRX3_Membership',
		'PRX3_Shares',
		'PRX3_WooCommerce',
		'PRX3_Gifts',
		'PRX3_Register',
		'PRX3_Certificates',
		'PRX3_Badges',
		'PRX3_Onboarding',
		'PRX3_Comms',
		'PRX3_Privacy',
		'PRX3_Editorial',
		'PRX3_Ballots',
		'PRX3_Ballot_Lifecycle',
		'PRX3_Ideas',
		'PRX3_Questions',
		'PRX3_Meetings',
		'PRX3_Decisions',
		'PRX3_Financials',
		'PRX3_Community',
		'PRX3_Moderation',
		'PRX3_Chapters',
		'PRX3_Board',
		'PRX3_Match_Centre',
		'PRX3_Chat',
		'PRX3_Media',
		'PRX3_Ticketing',
		'PRX3_Brand',
		'PRX3_Disputes',
		'PRX3_Commitments',
		'PRX3_Players',
		'PRX3_Agreements',
		'PRX3_REST_API',
		'PRX3_Admin',
		'PRX3_Dashboard',
		'PRX3_Shortcodes',
	);
	foreach ( $modules as $module ) {
		if ( class_exists( $module ) && method_exists( $module, 'init' ) ) {
			$module::init();
		}
	}
}
add_action( 'plugins_loaded', 'prx3_boot' );

/**
 * Front-end assets.
 */
function prx3_assets() {
	wp_register_style( 'prx3', PRX3_URL . 'assets/css/prx3.css', array(), PRX3_VERSION );
	wp_register_script( 'prx3', PRX3_URL . 'assets/js/prx3.js', array(), PRX3_VERSION, true );
	wp_localize_script(
		'prx3',
		'prx3Config',
		array(
			'restUrl' => esc_url_raw( rest_url( 'prx3/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'working' => __( 'Working…', 'fan-ownership' ),
				'error'   => __( 'Something went wrong. Please try again.', 'fan-ownership' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'prx3_assets' );

/**
 * Brand pack on the front end: CSS custom properties, @font-face for an
 * uploaded brand font, and the uploaded favicon (T46 — all configuration).
 */
function prx3_brand_head() {
	$brand = prx3_brand_pack();
	$css   = sprintf(
		':root{--prx3-primary:%s;--prx3-secondary:%s;--prx3-tertiary:%s;--prx3-accent:%s;}',
		sanitize_hex_color( $brand['primary'] ) ? $brand['primary'] : '#1a1a2e',
		sanitize_hex_color( $brand['secondary'] ) ? $brand['secondary'] : '#ffffff',
		sanitize_hex_color( $brand['tertiary'] ) ? $brand['tertiary'] : '#e2b007',
		sanitize_hex_color( $brand['tertiary'] ) ? $brand['tertiary'] : '#e2b007'
	);
	if ( $brand['font_file'] && $brand['font_name'] ) {
		$css .= sprintf(
			'@font-face{font-family:"%s";src:url("%s");font-display:swap;}',
			esc_attr( $brand['font_name'] ),
			esc_url( $brand['font_file'] )
		);
	}
	echo '<style id="prx3-brand">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from sanitised values above.
	if ( $brand['favicon'] ) {
		echo '<link rel="icon" href="' . esc_url( $brand['favicon'] ) . '">';
	}
}
add_action( 'wp_head', 'prx3_brand_head', 5 );

register_activation_hook( __FILE__, 'prx3_activate' );
register_deactivation_hook( __FILE__, 'prx3_deactivate' );

function prx3_activate() {
	PRX3_Roles::install();
	PRX3_Post_Types::register_all();
	PRX3_Register::install_tables();
	PRX3_Ballot_Lifecycle::schedule_cron();
	flush_rewrite_rules();
}

function prx3_deactivate() {
	PRX3_Ballot_Lifecycle::unschedule_cron();
	flush_rewrite_rules();
}

/**
 * Companion-plugin notices: integrate when present, degrade clearly when not.
 */
function prx3_dependency_notices() {
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
	if ( ! has_action( 'prx3_award_badge' ) && ! apply_filters( 'prx3_badge_provider_present', false ) ) {
		$missing[] = __( 'Badge plugin integration (no handler found for the prx3_award_badge action — badges will queue until one is connected)', 'fan-ownership' );
	}
	if ( $missing ) {
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Fan Ownership Platform — companion plugins:', 'fan-ownership' ) . '</strong> ' . esc_html( implode( '; ', $missing ) ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'prx3_dependency_notices' );
