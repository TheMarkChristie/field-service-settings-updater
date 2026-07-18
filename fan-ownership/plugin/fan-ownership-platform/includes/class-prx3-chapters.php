<?php
/**
 * Owner chapters: light charter, directory, annual re-affirmation.
 *
 * FO-222, P71.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Owner chapters: founding applications, open join/leave membership,
 * annual re-affirmation with lapse flagging, and de-recognition.
 */
class PRX3_Chapters {

	/**
	 * Wire the chapter meta box, save handler, and the daily
	 * re-affirmation check.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_prx3_chapter', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_action( 'prx3_chapter_reaffirm_check', array( __CLASS__, 'flag_lapsed' ) );
		if ( ! wp_next_scheduled( 'prx3_chapter_reaffirm_check' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'prx3_chapter_reaffirm_check' );
		}
	}

	/**
	 * Apply to found a chapter: 5+ owners, standard naming (FO-222 AC1).
	 *
	 * @param int    $lead_id  Founding lead.
	 * @param string $city     City/region name.
	 * @param int[]  $founders Founding member IDs (including lead).
	 * @return int|WP_Error Chapter post ID (pending staff approval).
	 */
	public static function apply( $lead_id, $city, $founders ) {
		$founders = array_unique( array_map( 'intval', $founders ) );
		$founders = array_filter( $founders, 'prx3_is_owner' );
		if ( ! prx3_is_owner( $lead_id ) ) {
			return new WP_Error( 'prx3_owner_only', __( 'Only owners can found a chapter.', 'fan-ownership' ) );
		}
		if ( count( $founders ) < 5 ) {
			return new WP_Error( 'prx3_five', __( 'A chapter needs at least five founding owners.', 'fan-ownership' ) );
		}
		$city = sanitize_text_field( $city );
		if ( ! $city ) {
			return new WP_Error( 'prx3_city', __( 'Tell us where the chapter is.', 'fan-ownership' ) );
		}
		$title      = sprintf( '%s — %s', prx3_club_name(), $city );
		$chapter_id = wp_insert_post(
			array(
				'post_type'   => 'prx3_chapter',
				'post_status' => 'pending',
				'post_title'  => $title,
				'post_author' => $lead_id,
			),
			true
		);
		if ( is_wp_error( $chapter_id ) ) {
			return $chapter_id;
		}
		update_post_meta( $chapter_id, '_prx3_chapter_lead', $lead_id );
		update_post_meta( $chapter_id, '_prx3_chapter_members', $founders );
		update_post_meta( $chapter_id, '_prx3_chapter_city', $city );
		update_post_meta( $chapter_id, '_prx3_affirmed_at', time() );
		PRX3_Moderation::enqueue( $chapter_id, 'chapter' );
		return $chapter_id;
	}

	/**
	 * Join / leave freely (FO-222 AC2).
	 *
	 * @param int $chapter_id Chapter post ID.
	 * @param int $user_id    Owner joining or leaving.
	 * @return array|WP_Error Member count and membership state, or error.
	 */
	public static function toggle_membership( $chapter_id, $user_id ) {
		if ( 'publish' !== get_post_status( $chapter_id ) || ! prx3_is_owner( $user_id ) ) {
			return new WP_Error( 'prx3_chapter', __( 'This chapter is not open to join.', 'fan-ownership' ) );
		}
		$members = array_map( 'intval', array_filter( (array) get_post_meta( $chapter_id, '_prx3_chapter_members', true ) ) );
		if ( in_array( $user_id, $members, true ) ) {
			$members = array_values( array_diff( $members, array( $user_id ) ) );
			$in      = false;
		} else {
			$members[] = $user_id;
			$in        = true;
		}
		update_post_meta( $chapter_id, '_prx3_chapter_members', $members );
		return array(
			'count'  => count( $members ),
			'member' => $in,
		);
	}

	/**
	 * FO-222 AC3: annual re-affirmation; lapsed chapters flagged, then
	 * archivable; de-recognition recorded.
	 */
	public static function flag_lapsed() {
		$chapters = get_posts(
			array(
				'post_type'      => 'prx3_chapter',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);
		foreach ( $chapters as $chapter ) {
			$affirmed = (int) get_post_meta( $chapter->ID, '_prx3_affirmed_at', true );
			if ( $affirmed && time() - $affirmed > YEAR_IN_SECONDS ) {
				update_post_meta( $chapter->ID, '_prx3_lapsed', 1 );
				$lead = get_userdata( (int) get_post_meta( $chapter->ID, '_prx3_chapter_lead', true ) );
				if ( $lead && ! get_post_meta( $chapter->ID, '_prx3_lapse_notified', true ) ) {
					update_post_meta( $chapter->ID, '_prx3_lapse_notified', 1 );
					PRX3_Comms::send(
						$lead->user_email,
						__( 'Chapter re-affirmation due', 'fan-ownership' ),
						sprintf(
						/* translators: %s chapter. */
							__( '"%s" is due its annual re-affirmation. Confirm the chapter is still active from your chapter page.', 'fan-ownership' ),
							$chapter->post_title
						),
						'governance'
					);
				}
			}
		}
	}

	/**
	 * De-recognise a chapter: moved out of the directory with the
	 * decision recorded (FO-222 AC3).
	 *
	 * @param int    $chapter_id Chapter post ID.
	 * @param string $reason     Reason recorded against the chapter.
	 * @return true|WP_Error True on success.
	 */
	public static function derecognise( $chapter_id, $reason ) {
		if ( ! current_user_can( 'prx3_admin' ) && ! current_user_can( 'prx3_governance' ) ) {
			return new WP_Error( 'prx3_denied', __( 'Not allowed.', 'fan-ownership' ) );
		}
		wp_update_post(
			array(
				'ID'          => $chapter_id,
				'post_status' => 'private',
			)
		);
		update_post_meta(
			$chapter_id,
			'_prx3_derecognised',
			array(
				'at'     => time(),
				'by'     => get_current_user_id(),
				'reason' => sanitize_text_field( $reason ),
			)
		);
		PRX3_Audit::log( 'chapter_derecognised', sprintf( 'Chapter %d de-recognised: %s', $chapter_id, $reason ) );
		return true;
	}

	/**
	 * Chapter side meta box: city, lead, member count, affirmation
	 * state, and the re-affirmation checkbox.
	 */
	public static function meta_box() {
		add_meta_box(
			'prx3_chapter_details',
			__( 'Chapter', 'fan-ownership' ),
			function ( $post ) {
				wp_nonce_field( 'prx3_chapter_meta', 'prx3_chapter_nonce' );
				$lead = get_userdata( (int) get_post_meta( $post->ID, '_prx3_chapter_lead', true ) );
				echo '<p>' . esc_html__( 'City/region:', 'fan-ownership' ) . ' <strong>' . esc_html( get_post_meta( $post->ID, '_prx3_chapter_city', true ) ) . '</strong></p>';
				echo '<p>' . esc_html__( 'Lead:', 'fan-ownership' ) . ' ' . esc_html( $lead ? $lead->display_name : '—' ) . '</p>';
				echo '<p>' . esc_html( sprintf( /* translators: %d members. */ __( 'Members: %d', 'fan-ownership' ), count( (array) get_post_meta( $post->ID, '_prx3_chapter_members', true ) ) ) ) . '</p>';
				echo '<p>' . esc_html__( 'Last affirmed:', 'fan-ownership' ) . ' ' . esc_html( date_i18n( get_option( 'date_format' ), (int) get_post_meta( $post->ID, '_prx3_affirmed_at', true ) ) ) . '</p>';
				if ( get_post_meta( $post->ID, '_prx3_lapsed', true ) ) {
					echo '<p><strong>' . esc_html__( 'Lapsed — awaiting re-affirmation.', 'fan-ownership' ) . '</strong></p>';
				}
				echo '<p><label><input type="checkbox" name="prx3_affirm" value="1"> ' . esc_html__( 'Record re-affirmation now', 'fan-ownership' ) . '</label></p>';
			},
			'prx3_chapter',
			'side',
			'high'
		);
	}

	/**
	 * Record a re-affirmation from the meta box, clearing the lapsed
	 * flags.
	 *
	 * @param int     $post_id Chapter post ID.
	 * @param WP_Post $post    Chapter post object.
	 */
	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['prx3_chapter_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['prx3_chapter_nonce'] ), 'prx3_chapter_meta' ) ) {
			return;
		}
		if ( isset( $_POST['prx3_affirm'] ) && ( current_user_can( 'prx3_governance' ) || current_user_can( 'prx3_admin' ) ) ) {
			update_post_meta( $post_id, '_prx3_affirmed_at', time() );
			delete_post_meta( $post_id, '_prx3_lapsed' );
			delete_post_meta( $post_id, '_prx3_lapse_notified' );
		}
	}
}
