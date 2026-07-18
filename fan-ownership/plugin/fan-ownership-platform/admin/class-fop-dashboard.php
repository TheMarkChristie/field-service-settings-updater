<?php
/**
 * The club engagement dashboard: membership & revenue, ballot health,
 * content performance, community health. FO-120, FO-315, T28.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Dashboard {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	public static function menu() {
		add_submenu_page(
			'fop-settings',
			__( 'Club Dashboard', 'fan-ownership' ),
			__( 'Dashboard', 'fan-ownership' ),
			'fop_view_tally',
			'fop-dashboard',
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		if ( ! current_user_can( 'fop_view_tally' ) && ! current_user_can( 'fop_board' ) ) {
			wp_die( esc_html__( 'Staff and board only.', 'fan-ownership' ) );
		}
		global $wpdb;

		$owners  = count( get_users( array( 'role' => 'fan_owner', 'fields' => 'ID' ) ) );
		$active  = fop_active_owner_count();
		$shares  = (int) $wpdb->get_var( "SELECT SUM(meta_value+0) FROM {$wpdb->usermeta} WHERE meta_key = 'fop_shares'" );
		$revenue = (float) $wpdb->get_var( "SELECT SUM(consideration) FROM {$wpdb->prefix}fop_share_register WHERE event = 'acquisition'" );
		$gifts   = count( array_filter( get_option( 'fop_gift_codes', array() ), fn( $g ) => empty( $g['redeemed'] ) ) );
		$surrenders = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fop_share_register WHERE event = 'surrender'" );

		echo '<div class="wrap"><h1>' . esc_html( sprintf( /* translators: %s club. */ __( '%s — Club Dashboard', 'fan-ownership' ), fop_club_name() ) ) . '</h1>';

		echo '<h2>' . esc_html__( 'Membership & revenue', 'fan-ownership' ) . '</h2><ul>';
		echo '<li>' . esc_html( sprintf( /* translators: 1: owners, 2: target. */ __( 'Owners: %1$d of the %2$d season-one target', 'fan-ownership' ), $owners, 1000 ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( /* translators: %d active. */ __( 'Active in last 12 months (quorum denominator): %d', 'fan-ownership' ), $active ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( /* translators: %d shares. */ __( 'Shares issued: %d', 'fan-ownership' ), $shares ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( /* translators: %s revenue. */ __( 'Share revenue recorded: %s', 'fan-ownership' ), fop_money( $revenue ) ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( /* translators: 1: gifts, 2: surrenders. */ __( 'Unredeemed gifts: %1$d. Surrender events: %2$d.', 'fan-ownership' ), $gifts, $surrenders ) ) . '</li></ul>';

		echo '<h2>' . esc_html__( 'Ballot health', 'fan-ownership' ) . '</h2><ul>';
		foreach ( FOP_Ballots::open_ballots() as $ballot ) {
			$tallies = FOP_Ballots::tallies( $ballot->ID, true );
			$denominator = (int) get_post_meta( $ballot->ID, '_fop_quorum_denominator', true );
			$needed  = (int) ceil( $denominator * (int) fop_setting( 'quorum_percent', 25 ) / 100 );
			echo '<li><strong>' . esc_html( $ballot->post_title ) . '</strong> — ' . esc_html( sprintf(
				/* translators: 1: voted, 2: needed, 3: closes. */
				__( '%1$d voted of %2$d needed for quorum; closes %3$s', 'fan-ownership' ),
				(int) $tallies['members'],
				$needed,
				fop_format_datetime( get_post_meta( $ballot->ID, '_fop_closes', true ) )
			) ) . '</li>';
		}
		echo '</ul>';

		$videos   = wp_count_posts( 'fop_video' )->publish ?? 0;
		$ideas    = wp_count_posts( 'fop_idea' );
		$questions = wp_count_posts( 'fop_question' );
		$queue    = count( array_filter( get_option( 'fop_mod_queue', array() ), fn( $q ) => empty( $q['resolved'] ) ) );
		$stalled  = count( get_posts( array( 'post_type' => 'fop_decision', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => '_fop_stalled', 'meta_value' => 1 ) ) );

		echo '<h2>' . esc_html__( 'Content & community', 'fan-ownership' ) . '</h2><ul>';
		echo '<li>' . esc_html( sprintf( /* translators: %d videos. */ __( 'Videos in the library: %d', 'fan-ownership' ), (int) $videos ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( /* translators: 1: published, 2: pending. */ __( 'Ideas: %1$d published, %2$d awaiting moderation', 'fan-ownership' ), (int) $ideas->publish, (int) $ideas->pending ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( /* translators: 1: published, 2: pending. */ __( 'Questions: %1$d published, %2$d awaiting moderation', 'fan-ownership' ), (int) $questions->publish, (int) $questions->pending ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( /* translators: %d queue. */ __( 'Moderation queue depth: %d', 'fan-ownership' ), $queue ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( /* translators: %d stalled. */ __( 'Stalled decisions: %d', 'fan-ownership' ), $stalled ) ) . '</li>';
		echo '</ul></div>';
	}
}
