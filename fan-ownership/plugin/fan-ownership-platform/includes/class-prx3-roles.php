<?php
/**
 * The six platform roles and the permissions matrix.
 *
 * FO-102. Roles: Owner (fan_owner), Owner-Admin, Content Editor,
 * Governance Officer, Moderator, Board Member — plus the per-item
 * Board Observer (granted as user meta, not a standing role, per P87).
 *
 * Capability map (the documented matrix, FO-102 AC1):
 * - prx3_member          — access owner-only areas, vote, submit, RSVP
 * - prx3_admin           — everything incl. members, checkout config, kill switches
 * - prx3_edit_content    — author/publish routine content
 * - prx3_governance      — author ballots, run meetings, publish financial drafts, edit decision register
 * - prx3_moderate        — moderation queue, sanctions up to mute
 * - prx3_board           — board oversight (tallies, register edit, drafts, mod visibility) + board workspace
 * - prx3_view_tally      — see running tallies while a ballot is open
 * - prx3_second_approve  — act as second approver for ballots/financials
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installs the six platform roles and their capability matrix (FO-102),
 * audits role changes, and enforces the staff 2FA requirement (T7).
 */
class PRX3_Roles {

	/**
	 * Hook login recording, role-change auditing and the 2FA gate.
	 */
	public static function init() {
		add_action( 'wp_login', array( __CLASS__, 'record_login' ), 10, 2 );
		add_action( 'set_user_role', array( __CLASS__, 'audit_role_change' ), 10, 3 );
		add_filter( 'authenticate', array( __CLASS__, 'enforce_staff_2fa_flag' ), 99 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ), 5 );
	}

	/**
	 * Self-heal roles and capabilities on installs where the activation
	 * hook never (re)ran — without the prx3_* capabilities the Settings,
	 * Board, and Dashboard menus are invisible even to administrators.
	 * Version-stamped so it costs one option read per admin request.
	 */
	public static function maybe_install() {
		if ( PRX3_VERSION === get_option( 'prx3_roles_installed' ) ) {
			return;
		}
		self::install();
		PRX3_Register::install_tables();
		PRX3_Ballot_Lifecycle::schedule_cron();
		update_option( 'prx3_roles_installed', PRX3_VERSION, false );
	}

	/**
	 * Create roles on activation.
	 */
	public static function install() {
		add_role(
			'fan_owner',
			__( 'Fan Owner', 'fan-ownership' ),
			array(
				'read'        => true,
				'prx3_member' => true,
			)
		);

		add_role(
			'prx3_content_editor',
			__( 'Content Editor', 'fan-ownership' ),
			array(
				'read'                 => true,
				'prx3_member'          => true,
				'prx3_edit_content'    => true,
				'upload_files'         => true,
				'edit_posts'           => true,
				'edit_others_posts'    => true,
				'publish_posts'        => true,
				'edit_published_posts' => true,
				'delete_posts'         => true,
			)
		);

		add_role(
			'prx3_governance_officer',
			__( 'Governance Officer', 'fan-ownership' ),
			array(
				'read'                 => true,
				'prx3_member'          => true,
				'prx3_governance'      => true,
				'prx3_view_tally'      => true,
				'edit_posts'           => true,
				'publish_posts'        => true,
				'edit_published_posts' => true,
			)
		);

		add_role(
			'prx3_moderator',
			__( 'Moderator', 'fan-ownership' ),
			array(
				'read'          => true,
				'prx3_member'   => true,
				'prx3_moderate' => true,
			)
		);

		add_role(
			'prx3_board_member',
			__( 'Board Member', 'fan-ownership' ),
			array(
				'read'            => true,
				'prx3_member'     => true,
				'prx3_board'      => true,
				'prx3_view_tally' => true,
			)
		);

		// Owner-Admin capabilities ride on administrator plus a dedicated role
		// for club staff who administer without full WP admin.
		add_role(
			'prx3_owner_admin',
			__( 'Owner-Admin', 'fan-ownership' ),
			array(
				'read'                => true,
				'prx3_member'         => true,
				'prx3_admin'          => true,
				'prx3_governance'     => true,
				'prx3_edit_content'   => true,
				'prx3_moderate'       => true,
				'prx3_view_tally'     => true,
				'prx3_second_approve' => true,
				'list_users'          => true,
				'edit_users'          => true,
				'upload_files'        => true,
			)
		);

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array( 'prx3_member', 'prx3_admin', 'prx3_governance', 'prx3_edit_content', 'prx3_moderate', 'prx3_view_tally', 'prx3_second_approve' ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
		// Editors and Governance Officers can act as second approver too (T26):
		// approval logic additionally enforces author !== approver.
		foreach ( array( 'prx3_content_editor', 'prx3_governance_officer' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( 'prx3_second_approve' );
			}
		}

		// FanPress Chat ties into WordPress roles (FO-233): Moderators run
		// topics and the held-replies queue, Content Editors and Owner-Admins
		// additionally manage the boards taxonomy. add_cap (not add_role) so
		// existing installs pick these up through the versioned self-heal.
		$fanpress = array(
			'prx3_moderator'      => array( 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'moderate_comments' ),
			'prx3_content_editor' => array( 'moderate_comments', 'manage_categories' ),
			'prx3_owner_admin'    => array( 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'moderate_comments', 'manage_categories' ),
		);
		foreach ( $fanpress as $role_name => $caps ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $caps as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Staff and board roles require 2FA (T7). The platform does not ship its
	 * own 2FA implementation; it enforces that a 2FA provider has marked the
	 * user enrolled (filterable so any 2FA plugin can integrate).
	 *
	 * @param int|WP_User $user User ID or object to check.
	 * @return bool Whether the user holds a staff capability that requires 2FA.
	 */
	public static function staff_needs_2fa( $user ) {
		$staff_caps = array( 'prx3_admin', 'prx3_governance', 'prx3_edit_content', 'prx3_moderate', 'prx3_board' );
		foreach ( $staff_caps as $cap ) {
			if ( user_can( $user, $cap ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Block staff logins without 2FA enrolment when an enforcing provider is
	 * connected. Members are never blocked (2FA optional for them).
	 *
	 * @param WP_User|WP_Error|null $user Authenticated user so far.
	 * @return WP_User|WP_Error|null
	 */
	public static function enforce_staff_2fa_flag( $user ) {
		if ( ! $user instanceof WP_User || ! self::staff_needs_2fa( $user ) ) {
			return $user;
		}
		$provider_active = apply_filters( 'prx3_2fa_provider_active', false );
		$enrolled        = apply_filters( 'prx3_user_2fa_enrolled', (bool) get_user_meta( $user->ID, 'prx3_2fa_enrolled', true ), $user );
		if ( $provider_active && ! $enrolled ) {
			return new WP_Error(
				'prx3_2fa_required',
				__( 'Staff and board accounts must have two-factor authentication enabled. Please contact an administrator.', 'fan-ownership' )
			);
		}
		return $user;
	}

	/**
	 * Record login as member activity (feeds the P76 active denominator).
	 *
	 * @param string  $user_login Login name.
	 * @param WP_User $user       User.
	 */
	public static function record_login( $user_login, $user ) {
		prx3_touch_activity( $user->ID );
	}

	/**
	 * Audit every role change (FO-102 AC4).
	 *
	 * @param int    $user_id   User changed.
	 * @param string $role      New role.
	 * @param array  $old_roles Previous roles.
	 */
	public static function audit_role_change( $user_id, $role, $old_roles ) {
		PRX3_Audit::log(
			'role_change',
			sprintf( 'User %d: %s -> %s', $user_id, implode( ',', (array) $old_roles ), $role ),
			array( 'user' => $user_id )
		);
		// Instant revoke on board departure (FO-229 AC3): destroy sessions
		// so workspace access ends immediately.
		if ( in_array( 'prx3_board_member', (array) $old_roles, true ) && 'prx3_board_member' !== $role ) {
			$sessions = WP_Session_Tokens::get_instance( $user_id );
			$sessions->destroy_all();
		}
	}
}
