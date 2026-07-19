<?php
/**
 * Member pages installer (FO-131): one click builds every member-facing
 * page the platform's shortcodes need — join, account, the owners hub,
 * ballots, FanPress Chat, match centre, and the rest — and wires the
 * join/account page settings. Fresh installs stop rendering "nothing".
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and tracks the member-facing shortcode pages.
 */
class PRX3_Pages {

	/**
	 * Wire the admin button.
	 */
	public static function init() {
		add_action( 'admin_post_prx3_create_pages', array( __CLASS__, 'handle_install' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/**
	 * Every member-facing page the platform ships: slug => title, content.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function pages() {
		return array(
			'join'          => array( __( 'Become an Owner', 'fan-ownership' ), "[prx3_share_ladder]\n\n[prx3_register]" ),
			'account'       => array( __( 'My Ownership', 'fan-ownership' ), '[prx3_account]' ),
			'owners-hub'    => array( __( 'Owners Hub', 'fan-ownership' ), '[prx3_dashboard]' ),
			'ballots'       => array( __( 'Ballots', 'fan-ownership' ), '[prx3_ballots]' ),
			'ideas'         => array( __( 'Ideas', 'fan-ownership' ), '[prx3_ideas]' ),
			'questions'     => array( __( 'Questions', 'fan-ownership' ), '[prx3_questions]' ),
			'meetings'      => array( __( 'Meetings', 'fan-ownership' ), '[prx3_meetings]' ),
			'decisions'     => array( __( 'Decision Register', 'fan-ownership' ), '[prx3_decisions]' ),
			'videos'        => array( __( 'Video', 'fan-ownership' ), '[prx3_videos]' ),
			'match-centre'  => array( __( 'Match Centre', 'fan-ownership' ), '[prx3_match]' ),
			'fanpress'      => array( __( 'FanPress Chat', 'fan-ownership' ), '[prx3_forum]' ),
			'activity'      => array( __( 'Club Activity', 'fan-ownership' ), '[prx3_activity]' ),
			'owners'        => array( __( 'The Owners', 'fan-ownership' ), '[prx3_members]' ),
			'messages'      => array( __( 'Private Messages', 'fan-ownership' ), '[prx3_messages]' ),
			'notifications' => array( __( 'Notifications', 'fan-ownership' ), '[prx3_notifications]' ),
			'board'         => array( __( 'The Board', 'fan-ownership' ), '[prx3_board_directory]' ),
			'redeem'        => array( __( 'Redeem a Gift', 'fan-ownership' ), '[prx3_redeem_gift]' ),
			'voting-record' => array( __( 'Voting Record', 'fan-ownership' ), '[prx3_voting_record]' ),
			'referrals'     => array( __( 'Bring a Fellow Fan', 'fan-ownership' ), '[prx3_referrals]' ),
			'chapters'      => array( __( 'Owner Chapters', 'fan-ownership' ), '[prx3_chapters]' ),
			'profile'       => array( __( 'My Profile', 'fan-ownership' ), '[prx3_profile]' ),
		);
	}

	/**
	 * Create any missing pages; existing ones (tracked by the installed
	 * map) are left untouched, so the installer is safe to re-run.
	 *
	 * @return array{created:int,existing:int}
	 */
	public static function install() {
		$map = (array) get_option( 'prx3_member_pages', array() );
		$out = array(
			'created'  => 0,
			'existing' => 0,
		);
		foreach ( self::pages() as $slug => $def ) {
			$existing = isset( $map[ $slug ] ) ? (int) $map[ $slug ] : 0;
			if ( $existing && get_post( $existing ) && 'publish' === get_post_status( $existing ) ) {
				++$out['existing'];
				continue;
			}
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $def[0],
					'post_content' => $def[1],
				)
			);
			if ( is_wp_error( $page_id ) || ! $page_id ) {
				continue;
			}
			$map[ $slug ] = (int) $page_id;
			++$out['created'];
		}
		update_option( 'prx3_member_pages', $map, false );
		// Wire the gate destinations so logged-out visitors land on the
		// join pitch and members find their account page.
		if ( isset( $map['join'] ) && ! (int) prx3_setting( 'join_page_id', 0 ) ) {
			prx3_update_setting( 'join_page_id', (int) $map['join'] );
		}
		if ( isset( $map['account'] ) && ! (int) prx3_setting( 'account_page_id', 0 ) ) {
			prx3_update_setting( 'account_page_id', (int) $map['account'] );
		}
		PRX3_Audit::log( 'pages_installed', sprintf( 'Member pages installer: %d created, %d already present', $out['created'], $out['existing'] ) );
		return $out;
	}

	/**
	 * The admin panel: current state plus the install button.
	 */
	public static function panel() {
		$map = (array) get_option( 'prx3_member_pages', array() );
		echo '<h2>' . esc_html__( 'Member pages', 'fan-ownership' ) . '</h2>';
		echo '<p>' . esc_html__( 'The member experience lives on shortcode pages — join, account, owners hub, ballots, FanPress Chat, match centre, and friends. One click creates any that are missing and wires the join and account destinations. Existing pages are never touched.', 'fan-ownership' ) . '</p>';
		if ( $map ) {
			echo '<p>';
			foreach ( $map as $slug => $page_id ) {
				if ( get_post( $page_id ) ) {
					echo '<a class="prx3-badge" href="' . esc_url( get_permalink( $page_id ) ) . '">' . esc_html( get_the_title( $page_id ) ) . '</a> ';
				}
			}
			echo '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'prx3_create_pages' );
		echo '<input type="hidden" name="action" value="prx3_create_pages">';
		echo '<p><button class="button button-primary">' . esc_html__( 'Create member pages', 'fan-ownership' ) . '</button></p></form>';
	}

	/**
	 * Install from the button.
	 */
	public static function handle_install() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_create_pages' );
		$result = self::install();
		set_transient( 'prx3_pages_result_' . get_current_user_id(), $result, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-settings&prx3_pages=1' ) );
		exit;
	}

	/**
	 * Result notice after installing.
	 */
	public static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of a one-time result.
		if ( ! isset( $_GET['prx3_pages'] ) ) {
			return;
		}
		$result = get_transient( 'prx3_pages_result_' . get_current_user_id() );
		if ( ! is_array( $result ) ) {
			return;
		}
		delete_transient( 'prx3_pages_result_' . get_current_user_id() );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( /* translators: 1 created, 2 existing. */ __( 'Member pages ready: %1$d created, %2$d already in place. The join and account destinations are wired.', 'fan-ownership' ), (int) $result['created'], (int) $result['existing'] ) ) . '</p></div>';
	}
}
