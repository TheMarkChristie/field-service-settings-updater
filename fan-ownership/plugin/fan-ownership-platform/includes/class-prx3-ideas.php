<?php
/**
 * Ideas: submission, staff pre-moderation, support, the 5% threshold
 * auto-drafting a ballot, and the status lifecycle.
 *
 * FO-210, FO-211.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Ideas {

	const STATUSES = array( 'new', 'under-review', 'ballot-scheduled', 'planned', 'delivered', 'declined' );

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_prx3_idea', array( __CLASS__, 'save_status' ), 10, 2 );
	}

	/**
	 * Submit an idea (REST + shortcode both call this). Pending until
	 * staff moderation (FO-210 AC1).
	 *
	 * @return int|WP_Error Post ID.
	 */
	public static function submit( $user_id, $title, $rationale ) {
		if ( ! prx3_feature_on( 'ideas' ) ) {
			return new WP_Error( 'prx3_off', __( 'Idea submission is temporarily unavailable.', 'fan-ownership' ) );
		}
		if ( ! prx3_is_owner( $user_id ) ) {
			return new WP_Error( 'prx3_owner_only', __( 'Only owners can propose ideas.', 'fan-ownership' ) );
		}
		if ( ! $title ) {
			return new WP_Error( 'prx3_title', __( 'Give your idea a title.', 'fan-ownership' ) );
		}
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'prx3_idea',
				'post_status'  => 'pending',
				'post_title'   => sanitize_text_field( $title ),
				'post_content' => sanitize_textarea_field( $rationale ),
				'post_author'  => $user_id,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		update_post_meta( $post_id, '_prx3_idea_status', 'new' );
		update_post_meta( $post_id, '_prx3_supporters', array( $user_id ) );
		PRX3_Moderation::enqueue( $post_id, 'idea' );
		prx3_touch_activity( $user_id );
		return $post_id;
	}

	/**
	 * Toggle support (FO-210 AC2). Once per owner; withdrawable.
	 *
	 * @return array|WP_Error ['count' => int, 'supporting' => bool, 'threshold' => int]
	 */
	public static function toggle_support( $idea_id, $user_id ) {
		if ( 'publish' !== get_post_status( $idea_id ) || 'prx3_idea' !== get_post_type( $idea_id ) ) {
			return new WP_Error( 'prx3_idea', __( 'This idea is not open for support.', 'fan-ownership' ) );
		}
		if ( ! prx3_is_owner( $user_id ) ) {
			return new WP_Error( 'prx3_owner_only', __( 'Only owners can support ideas.', 'fan-ownership' ) );
		}
		$supporters = (array) get_post_meta( $idea_id, '_prx3_supporters', true );
		$supporters = array_map( 'intval', array_filter( $supporters ) );
		if ( in_array( $user_id, $supporters, true ) ) {
			$supporters = array_values( array_diff( $supporters, array( $user_id ) ) );
			$supporting = false;
		} else {
			$supporters[] = $user_id;
			$supporting   = true;
		}
		update_post_meta( $idea_id, '_prx3_supporters', $supporters );
		prx3_touch_activity( $user_id );

		$threshold = self::threshold_count();
		if ( $supporting && count( $supporters ) >= $threshold && ! get_post_meta( $idea_id, '_prx3_ballot_drafted', true ) ) {
			self::auto_draft_ballot( $idea_id );
		}
		return array(
			'count'      => count( $supporters ),
			'supporting' => $supporting,
			'threshold'  => $threshold,
		);
	}

	/**
	 * 5% of active owners (FO-210 AC3).
	 */
	public static function threshold_count() {
		return max( 2, (int) ceil( prx3_active_owner_count() * (int) prx3_setting( 'idea_threshold_pct', 5 ) / 100 ) );
	}

	/**
	 * Threshold reached: auto-create the draft ballot, notify staff and
	 * supporters, set status. Staff legality-check then schedule (FO-210 AC4).
	 */
	private static function auto_draft_ballot( $idea_id ) {
		$idea      = get_post( $idea_id );
		$ballot_id = wp_insert_post(
			array(
				'post_type'    => 'prx3_ballot',
				'post_status'  => 'draft',
				'post_title'   => sprintf( /* translators: %s idea title. */ __( 'Fan proposal: %s', 'fan-ownership' ), $idea->post_title ),
				'post_content' => $idea->post_content,
				'post_author'  => $idea->post_author,
			)
		);
		if ( ! $ballot_id || is_wp_error( $ballot_id ) ) {
			return;
		}
		update_post_meta( $ballot_id, '_prx3_options', array( __( 'Adopt this proposal', 'fan-ownership' ), __( 'Do not adopt', 'fan-ownership' ) ) );
		update_post_meta( $ballot_id, '_prx3_type', 'standard' );
		update_post_meta( $ballot_id, '_prx3_state', 'draft' );
		update_post_meta( $ballot_id, '_prx3_from_idea', $idea_id );
		update_post_meta( $idea_id, '_prx3_ballot_drafted', $ballot_id );
		self::set_status( $idea_id, 'ballot-scheduled' );

		do_action( 'prx3_milestone_event', 'idea_reached_ballot', (int) $idea->post_author );
		PRX3_Audit::log( 'idea_threshold', sprintf( 'Idea %d reached threshold; ballot %d auto-drafted', $idea_id, $ballot_id ) );

		// Notify governance staff.
		foreach ( get_users(
			array(
				'role__in' => array( 'prx3_governance_officer', 'prx3_owner_admin', 'administrator' ),
				'fields'   => 'all',
			)
		) as $staff ) {
			PRX3_Comms::send(
				$staff->user_email,
				__( 'Fan idea reached the ballot threshold', 'fan-ownership' ),
				sprintf(
					/* translators: 1: idea title, 2: edit link. */
					__( '"%1$s" has reached the support threshold. A draft ballot has been created — legality-check it and schedule it (declining to schedule requires a published reason): %2$s', 'fan-ownership' ),
					$idea->post_title,
					admin_url( 'post.php?post=' . $ballot_id . '&action=edit' )
				),
				'governance'
			);
		}
		// Notify proposer + supporters (FO-210 AC3).
		foreach ( (array) get_post_meta( $idea_id, '_prx3_supporters', true ) as $uid ) {
			$user = get_userdata( (int) $uid );
			if ( $user ) {
				PRX3_Comms::send(
					$user->user_email,
					__( 'An idea you support is heading to a ballot', 'fan-ownership' ),
					sprintf( /* translators: %s idea title. */ __( '"%s" reached the support threshold and will be put to a vote of all owners.', 'fan-ownership' ), $idea->post_title ),
					'governance'
				);
			}
		}
	}

	/**
	 * Status transitions with history + notifications (FO-211).
	 */
	public static function set_status( $idea_id, $status, $reason = '' ) {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return;
		}
		$old = get_post_meta( $idea_id, '_prx3_idea_status', true );
		if ( $old === $status ) {
			return;
		}
		update_post_meta( $idea_id, '_prx3_idea_status', $status );
		if ( 'declined' === $status && $reason ) {
			update_post_meta( $idea_id, '_prx3_declined_reason', sanitize_textarea_field( $reason ) );
		}
		$history   = (array) get_post_meta( $idea_id, '_prx3_status_history', true );
		$history[] = array(
			'from'   => $old,
			'to'     => $status,
			'at'     => time(),
			'by'     => get_current_user_id(),
			'reason' => $reason,
		);
		update_post_meta( $idea_id, '_prx3_status_history', $history );

		foreach ( array_unique( array_merge( array( (int) get_post_field( 'post_author', $idea_id ) ), array_map( 'intval', (array) get_post_meta( $idea_id, '_prx3_supporters', true ) ) ) ) as $uid ) {
			$user = get_userdata( $uid );
			if ( $user ) {
				PRX3_Comms::send(
					$user->user_email,
					sprintf( /* translators: %s idea title. */ __( 'Idea update: %s', 'fan-ownership' ), get_the_title( $idea_id ) ),
					sprintf( /* translators: 1: title, 2: status. */ __( '"%1$s" is now: %2$s.%3$s', 'fan-ownership' ), get_the_title( $idea_id ), $status, $reason ? "\n\n" . $reason : '' ),
					'governance'
				);
			}
		}
	}

	public static function meta_box() {
		add_meta_box(
			'prx3_idea_status',
			__( 'Idea Status', 'fan-ownership' ),
			function ( $post ) {
				wp_nonce_field( 'prx3_idea_status', 'prx3_idea_nonce' );
				$status = get_post_meta( $post->ID, '_prx3_idea_status', true );
				echo '<select name="prx3_idea_status" class="widefat">';
				foreach ( self::STATUSES as $s ) {
					echo '<option value="' . esc_attr( $s ) . '" ' . selected( $status, $s, false ) . '>' . esc_html( $s ) . '</option>';
				}
				echo '</select>';
				echo '<p><label>' . esc_html__( 'Reason (required when declining)', 'fan-ownership' ) . '</label><textarea class="widefat" name="prx3_idea_reason"></textarea></p>';
				echo '<p>' . esc_html(
					sprintf(
					/* translators: 1: supporters, 2: threshold. */
						__( 'Support: %1$d of %2$d needed for an automatic ballot.', 'fan-ownership' ),
						count( (array) get_post_meta( $post->ID, '_prx3_supporters', true ) ),
						self::threshold_count()
					)
				) . '</p>';
			},
			'prx3_idea',
			'side',
			'high'
		);
	}

	public static function save_status( $post_id, $post ) {
		if ( ! isset( $_POST['prx3_idea_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['prx3_idea_nonce'] ), 'prx3_idea_status' ) ) {
			return;
		}
		if ( ! current_user_can( 'prx3_governance' ) && ! current_user_can( 'prx3_moderate' ) && ! current_user_can( 'prx3_admin' ) ) {
			return;
		}
		$status = isset( $_POST['prx3_idea_status'] ) ? sanitize_key( $_POST['prx3_idea_status'] ) : '';
		$reason = isset( $_POST['prx3_idea_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prx3_idea_reason'] ) ) : '';
		if ( 'declined' === $status && ! $reason && ! get_post_meta( $post_id, '_prx3_declined_reason', true ) ) {
			return; // Declining requires a reason (FO-211 AC3).
		}
		self::set_status( $post_id, $status, $reason );
	}
}
