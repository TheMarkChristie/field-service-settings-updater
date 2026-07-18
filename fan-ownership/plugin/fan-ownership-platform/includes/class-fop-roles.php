<?php
/**
 * The six platform roles and the permissions matrix.
 *
 * FO-102. Roles: Owner (fan_owner), Owner-Admin, Content Editor,
 * Governance Officer, Moderator, Board Member — plus the per-item
 * Board Observer (granted as user meta, not a standing role, per P87).
 *
 * Capability map (the documented matrix, FO-102 AC1):
 * - fop_member          — access owner-only areas, vote, submit, RSVP
 * - fop_admin           — everything incl. members, checkout config, kill switches
 * - fop_edit_content    — author/publish routine content
 * - fop_governance      — author ballots, run meetings, publish financial drafts, edit decision register
 * - fop_moderate        — moderation queue, sanctions up to mute
 * - fop_board           — board oversight (tallies, register edit, drafts, mod visibility) + board workspace
 * - fop_view_tally      — see running tallies while a ballot is open
 * - fop_second_approve  — act as second approver for ballots/financials
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Roles {

	public static function init() {
		add_action( 'wp_login', array( __CLASS__, 'record_login' ), 10, 2 );
		add_action( 'set_user_role', array( __CLASS__, 'audit_role_change' ), 10, 3 );
		add_filter( 'authenticate', array( __CLASS__, 'enforce_staff_2fa_flag' ), 99 );
	}

	/**
	 * Create roles on activation.
	 */
	public static function install() {
		add_role( 'fan_owner', __( 'Fan Owner', 'fan-ownership' ), array(
			'read'       => true,
			'fop_member' => true,
		) );

		add_role( 'fop_content_editor', __( 'Content Editor', 'fan-ownership' ), array(
			'read'             => true,
			'fop_member'       => true,
			'fop_edit_content' => true,
			'upload_files'     => true,
			'edit_posts'       => true,
			'edit_others_posts' => true,
			'publish_posts'    => true,
			'edit_published_posts' => true,
			'delete_posts'     => true,
		) );

		add_role( 'fop_governance_officer', __( 'Governance Officer', 'fan-ownership' ), array(
			'read'            => true,
			'fop_member'      => true,
			'fop_governance'  => true,
			'fop_view_tally'  => true,
			'edit_posts'      => true,
			'publish_posts'   => true,
			'edit_published_posts' => true,
		) );

		add_role( 'fop_moderator', __( 'Moderator', 'fan-ownership' ), array(
			'read'         => true,
			'fop_member'   => true,
			'fop_moderate' => true,
		) );

		add_role( 'fop_board_member', __( 'Board Member', 'fan-ownership' ), array(
			'read'           => true,
			'fop_member'     => true,
			'fop_board'      => true,
			'fop_view_tally' => true,
		) );

		// Owner-Admin capabilities ride on administrator plus a dedicated role
		// for club staff who administer without full WP admin.
		add_role( 'fop_owner_admin', __( 'Owner-Admin', 'fan-ownership' ), array(
			'read'               => true,
			'fop_member'         => true,
			'fop_admin'          => true,
			'fop_governance'     => true,
			'fop_edit_content'   => true,
			'fop_moderate'       => true,
			'fop_view_tally'     => true,
			'fop_second_approve' => true,
			'list_users'         => true,
			'edit_users'         => true,
			'upload_files'       => true,
		) );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array( 'fop_member', 'fop_admin', 'fop_governance', 'fop_edit_content', 'fop_moderate', 'fop_view_tally', 'fop_second_approve' ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
		// Editors and Governance Officers can act as second approver too (T26):
		// approval logic additionally enforces author !== approver.
		foreach ( array( 'fop_content_editor', 'fop_governance_officer' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( 'fop_second_approve' );
			}
		}
	}

	/**
	 * Staff and board roles require 2FA (T7). The platform does not ship its
	 * own 2FA implementation; it enforces that a 2FA provider has marked the
	 * user enrolled (filterable so any 2FA plugin can integrate).
	 */
	public static function staff_needs_2fa( $user ) {
		$staff_caps = array( 'fop_admin', 'fop_governance', 'fop_edit_content', 'fop_moderate', 'fop_board' );
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
		$provider_active = apply_filters( 'fop_2fa_provider_active', false );
		$enrolled        = apply_filters( 'fop_user_2fa_enrolled', (bool) get_user_meta( $user->ID, 'fop_2fa_enrolled', true ), $user );
		if ( $provider_active && ! $enrolled ) {
			return new WP_Error(
				'fop_2fa_required',
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
		fop_touch_activity( $user->ID );
	}

	/**
	 * Audit every role change (FO-102 AC4).
	 *
	 * @param int    $user_id   User changed.
	 * @param string $role      New role.
	 * @param array  $old_roles Previous roles.
	 */
	public static function audit_role_change( $user_id, $role, $old_roles ) {
		FOP_Audit::log(
			'role_change',
			sprintf( 'User %d: %s -> %s', $user_id, implode( ',', (array) $old_roles ), $role ),
			array( 'user' => $user_id )
		);
		// Instant revoke on board departure (FO-229 AC3): destroy sessions
		// so workspace access ends immediately.
		if ( in_array( 'fop_board_member', (array) $old_roles, true ) && 'fop_board_member' !== $role ) {
			$sessions = WP_Session_Tokens::get_instance( $user_id );
			$sessions->destroy_all();
		}
	}
}
