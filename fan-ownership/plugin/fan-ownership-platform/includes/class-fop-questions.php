<?php
/**
 * Questions to the club: submission, upvotes, monthly video selection,
 * written answers with an SLA flag. FO-212.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Questions {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_fop_question', array( __CLASS__, 'save_answer' ), 10, 2 );
		add_action( 'fop_questions_sla_check', array( __CLASS__, 'flag_overdue' ) );
		if ( ! wp_next_scheduled( 'fop_questions_sla_check' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'fop_questions_sla_check' );
		}
	}

	public static function submit( $user_id, $title, $detail ) {
		if ( ! fop_feature_on( 'questions' ) ) {
			return new WP_Error( 'fop_off', __( 'Question submission is temporarily unavailable.', 'fan-ownership' ) );
		}
		if ( ! fop_is_owner( $user_id ) ) {
			return new WP_Error( 'fop_owner_only', __( 'Only owners can put questions to the club.', 'fan-ownership' ) );
		}
		if ( ! $title ) {
			return new WP_Error( 'fop_title', __( 'Please write your question.', 'fan-ownership' ) );
		}
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'fop_question',
				'post_status'  => 'pending',
				'post_title'   => sanitize_text_field( $title ),
				'post_content' => sanitize_textarea_field( $detail ),
				'post_author'  => $user_id,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		update_post_meta( $post_id, '_fop_upvotes', array( $user_id ) );
		update_post_meta( $post_id, '_fop_accepted_at', '' );
		FOP_Moderation::enqueue( $post_id, 'question' );
		fop_touch_activity( $user_id );
		return $post_id;
	}

	public static function toggle_upvote( $question_id, $user_id ) {
		if ( 'publish' !== get_post_status( $question_id ) ) {
			return new WP_Error( 'fop_question', __( 'This question is not open for upvotes.', 'fan-ownership' ) );
		}
		$votes = array_map( 'intval', array_filter( (array) get_post_meta( $question_id, '_fop_upvotes', true ) ) );
		if ( in_array( $user_id, $votes, true ) ) {
			$votes = array_values( array_diff( $votes, array( $user_id ) ) );
			$on    = false;
		} else {
			$votes[] = $user_id;
			$on      = true;
		}
		update_post_meta( $question_id, '_fop_upvotes', $votes );
		fop_touch_activity( $user_id );
		return array(
			'count'   => count( $votes ),
			'upvoted' => $on,
		);
	}

	/**
	 * Written answer saved -> asker notified, answered state set (FO-212 AC4).
	 */
	public static function save_answer( $post_id, $post ) {
		if ( ! isset( $_POST['fop_question_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fop_question_nonce'] ), 'fop_question_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'fop_governance' ) && ! current_user_can( 'fop_admin' ) ) {
			return;
		}
		$answer   = isset( $_POST['fop_answer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fop_answer'] ) ) : '';
		$video    = isset( $_POST['fop_video_answer'] ) ? esc_url_raw( wp_unslash( $_POST['fop_video_answer'] ) ) : '';
		$selected = isset( $_POST['fop_selected_for_video'] ) ? '1' : '';
		$had      = get_post_meta( $post_id, '_fop_answer', true );
		update_post_meta( $post_id, '_fop_answer', $answer );
		update_post_meta( $post_id, '_fop_video_answer', $video );
		update_post_meta( $post_id, '_fop_selected_for_video', $selected );
		if ( $answer && ! $had ) {
			update_post_meta( $post_id, '_fop_answered_at', time() );
			$asker = get_userdata( (int) $post->post_author );
			if ( $asker ) {
				FOP_Comms::send(
					$asker->user_email,
					__( 'The club has answered your question', 'fan-ownership' ),
					sprintf( /* translators: 1: question, 2: answer. */ __( "Q: %1\$s\n\nA: %2\$s", 'fan-ownership' ), $post->post_title, $answer ),
					'governance'
				);
			}
		}
	}

	/**
	 * FO-212 AC3: accepted questions past the written-answer target get
	 * flagged to staff daily.
	 */
	public static function flag_overdue() {
		$target_days = (int) fop_setting( 'question_sla_days', 14 );
		$overdue     = get_posts(
			array(
				'post_type'      => 'fop_question',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'date_query'     => array( array( 'before' => $target_days . ' days ago' ) ),
				'meta_query'     => array(
					array(
						'key'     => '_fop_answer',
						'compare' => 'NOT EXISTS',
					),
				),
				'no_found_rows'  => true,
			)
		);
		if ( ! $overdue ) {
			return;
		}
		$lines = array_map(
			function ( $q ) {
				return '- ' . $q->post_title . ' (' . admin_url( 'post.php?post=' . $q->ID . '&action=edit' ) . ')';
			},
			$overdue
		);
		foreach ( get_users( array( 'role__in' => array( 'fop_governance_officer', 'fop_owner_admin', 'administrator' ) ) ) as $staff ) {
			FOP_Comms::send(
				$staff->user_email,
				sprintf( /* translators: %d count. */ _n( '%d owner question is past the answer target', '%d owner questions are past the answer target', count( $overdue ), 'fan-ownership' ), count( $overdue ) ),
				implode( "\n", $lines ),
				'governance'
			);
		}
	}

	public static function meta_box() {
		add_meta_box(
			'fop_question_answer',
			__( "The Club's Answer", 'fan-ownership' ),
			function ( $post ) {
				wp_nonce_field( 'fop_question_meta', 'fop_question_nonce' );
				echo '<p><label>' . esc_html__( 'Written answer', 'fan-ownership' ) . '</label>';
				echo '<textarea class="widefat" rows="5" name="fop_answer">' . esc_textarea( get_post_meta( $post->ID, '_fop_answer', true ) ) . '</textarea></p>';
				echo '<p><label><input type="checkbox" name="fop_selected_for_video" ' . checked( get_post_meta( $post->ID, '_fop_selected_for_video', true ), '1', false ) . '> ' . esc_html__( 'Selected for the monthly Q&A video', 'fan-ownership' ) . '</label></p>';
				echo '<p><label>' . esc_html__( 'Link to the video answer', 'fan-ownership' ) . '</label>';
				echo '<input type="url" class="widefat" name="fop_video_answer" value="' . esc_attr( get_post_meta( $post->ID, '_fop_video_answer', true ) ) . '"></p>';
				echo '<p>' . esc_html( sprintf( /* translators: %d upvotes. */ __( 'Upvotes: %d', 'fan-ownership' ), count( (array) get_post_meta( $post->ID, '_fop_upvotes', true ) ) ) ) . '</p>';
			},
			'fop_question',
			'normal',
			'high'
		);
	}
}
