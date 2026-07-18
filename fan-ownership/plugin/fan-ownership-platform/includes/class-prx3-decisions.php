<?php
/**
 * The public decision register. FO-218, FO-219 (report data).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * The public decision register: passed ballots and released board
 * decisions, each tracked with dated implementation updates and flagged
 * when progress stalls.
 */
class PRX3_Decisions {

	const STATUSES = array( 'planned', 'in-progress', 'done', 'blocked' );

	/**
	 * Hook the implementation meta box and its save handler.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_prx3_decision', array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	/**
	 * FO-218 AC1: every passed ballot enters the register automatically.
	 *
	 * @param int    $ballot_id      Source ballot.
	 * @param string $winning_option Label of the winning option.
	 * @param array  $extra          Optional extra meta (key => value), keys prefixed on save.
	 * @return int Decision post ID, or 0 on failure.
	 */
	public static function create_from_ballot( $ballot_id, $winning_option, $extra = array() ) {
		$decision_id = wp_insert_post(
			array(
				'post_type'    => 'prx3_decision',
				'post_status'  => 'publish',
				'post_title'   => sprintf( /* translators: 1: ballot title, 2: option. */ __( '%1$s — decided: %2$s', 'fan-ownership' ), get_the_title( $ballot_id ), $winning_option ),
				'post_content' => wp_kses_post( get_post_field( 'post_content', $ballot_id ) ),
			)
		);
		if ( ! $decision_id || is_wp_error( $decision_id ) ) {
			return 0;
		}
		update_post_meta( $decision_id, '_prx3_decision_status', 'planned' );
		update_post_meta( $decision_id, '_prx3_from_ballot', $ballot_id );
		update_post_meta( $decision_id, '_prx3_decided_at', prx3_now() );
		update_post_meta( $decision_id, '_prx3_updates', array() );
		foreach ( $extra as $key => $value ) {
			update_post_meta( $decision_id, '_prx3_' . sanitize_key( $key ), $value );
		}
		return $decision_id;
	}

	/**
	 * Board decision released to the register (P84 selective disclosure).
	 *
	 * @param string $title     Decision title.
	 * @param string $body      Decision body (post content).
	 * @param string $reasoning The board's published reasoning.
	 * @return int|WP_Error Decision post ID, or the wp_insert_post() error.
	 */
	public static function create_from_board( $title, $body, $reasoning ) {
		$decision_id = wp_insert_post(
			array(
				'post_type'    => 'prx3_decision',
				'post_status'  => 'publish',
				'post_title'   => sanitize_text_field( $title ),
				'post_content' => wp_kses_post( $body ),
			)
		);
		if ( $decision_id && ! is_wp_error( $decision_id ) ) {
			update_post_meta( $decision_id, '_prx3_decision_status', 'planned' );
			update_post_meta( $decision_id, '_prx3_board_decision', 1 );
			update_post_meta( $decision_id, '_prx3_board_reasoning', sanitize_textarea_field( $reasoning ) );
			update_post_meta( $decision_id, '_prx3_decided_at', prx3_now() );
		}
		return $decision_id;
	}

	/**
	 * Dated status updates (FO-218 AC2).
	 */
	public static function add_update( $decision_id, $status, $note ) {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return;
		}
		update_post_meta( $decision_id, '_prx3_decision_status', $status );
		$updates   = (array) get_post_meta( $decision_id, '_prx3_updates', true );
		$updates[] = array(
			'at'     => prx3_now(),
			'by'     => get_current_user_id(),
			'status' => $status,
			'note'   => sanitize_textarea_field( $note ),
		);
		update_post_meta( $decision_id, '_prx3_updates', $updates );
		update_post_meta( $decision_id, '_prx3_last_update', time() );
	}

	/**
	 * FO-218 AC3: stalled entries flagged visibly and to the board.
	 * Called from the ballot lifecycle tick.
	 */
	public static function flag_stalled() {
		$stale_days = (int) prx3_setting( 'decision_stale_days', 60 );
		$open       = get_posts(
			array(
				'post_type'      => 'prx3_decision',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_prx3_decision_status',
						'value'   => array( 'planned', 'in-progress', 'blocked' ),
						'compare' => 'IN',
					),
				),
			)
		);
		foreach ( $open as $decision ) {
			$last    = (int) get_post_meta( $decision->ID, '_prx3_last_update', true );
			$last    = $last ? $last : strtotime( get_post_meta( $decision->ID, '_prx3_decided_at', true ) );
			$stalled = $last && ( time() - $last ) > $stale_days * DAY_IN_SECONDS;
			$was     = (bool) get_post_meta( $decision->ID, '_prx3_stalled', true );
			update_post_meta( $decision->ID, '_prx3_stalled', $stalled ? 1 : '' );
			if ( $stalled && ! $was ) {
				foreach ( get_users( array( 'role' => 'prx3_board_member' ) ) as $director ) {
					PRX3_Comms::send(
						$director->user_email,
						__( 'Decision register: an entry has stalled', 'fan-ownership' ),
						sprintf( /* translators: 1: title, 2: days. */ __( '"%1$s" has had no update for over %2$d days.', 'fan-ownership' ), $decision->post_title, $stale_days ),
						'governance'
					);
				}
			}
		}
	}

	public static function meta_box() {
		add_meta_box(
			'prx3_decision_status',
			__( 'Implementation', 'fan-ownership' ),
			function ( $post ) {
				wp_nonce_field( 'prx3_decision_meta', 'prx3_decision_nonce' );
				$status = get_post_meta( $post->ID, '_prx3_decision_status', true );
				$owner  = get_post_meta( $post->ID, '_prx3_accountable', true );
				echo '<p><label>' . esc_html__( 'Status', 'fan-ownership' ) . '</label> <select name="prx3_decision_status">';
				foreach ( self::STATUSES as $s ) {
					echo '<option value="' . esc_attr( $s ) . '" ' . selected( $status, $s, false ) . '>' . esc_html( $s ) . '</option>';
				}
				echo '</select></p>';
				echo '<p><label>' . esc_html__( 'Accountable owner (name/role)', 'fan-ownership' ) . '</label> <input type="text" class="widefat" name="prx3_accountable" value="' . esc_attr( $owner ) . '"></p>';
				echo '<p><label>' . esc_html__( 'Update note', 'fan-ownership' ) . '</label><textarea class="widefat" rows="3" name="prx3_update_note"></textarea></p>';
				if ( get_post_meta( $post->ID, '_prx3_stalled', true ) ) {
					echo '<p><strong>' . esc_html__( 'Flagged as stalled.', 'fan-ownership' ) . '</strong></p>';
				}
			},
			'prx3_decision',
			'side',
			'high'
		);
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['prx3_decision_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['prx3_decision_nonce'] ), 'prx3_decision_meta' ) ) {
			return;
		}
		// Governance officers, admins, and board members can update (P79).
		if ( ! current_user_can( 'prx3_governance' ) && ! current_user_can( 'prx3_admin' ) && ! current_user_can( 'prx3_board' ) ) {
			return;
		}
		if ( isset( $_POST['prx3_accountable'] ) ) {
			update_post_meta( $post_id, '_prx3_accountable', sanitize_text_field( wp_unslash( $_POST['prx3_accountable'] ) ) );
		}
		$status = isset( $_POST['prx3_decision_status'] ) ? sanitize_key( $_POST['prx3_decision_status'] ) : '';
		$note   = isset( $_POST['prx3_update_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prx3_update_note'] ) ) : '';
		if ( $status && ( get_post_meta( $post_id, '_prx3_decision_status', true ) !== $status || $note ) ) {
			self::add_update( $post_id, $status, $note );
		}
	}
}
