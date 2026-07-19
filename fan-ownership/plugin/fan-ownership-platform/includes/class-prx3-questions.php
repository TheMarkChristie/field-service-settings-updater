<?php
/**
 * Questions to the club: submission, upvotes, monthly video selection,
 * written answers with an SLA flag. FO-212.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Owner questions to the club: submission, upvoting, monthly video
 * selection and written answers with a daily SLA sweep.
 */
class PRX3_Questions {

	/**
	 * Hook up the answer meta box, save handler and daily SLA check.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_prx3_question', array( __CLASS__, 'save_answer' ), 10, 2 );
		add_action( 'prx3_questions_sla_check', array( __CLASS__, 'flag_overdue' ) );
		if ( ! wp_next_scheduled( 'prx3_questions_sla_check' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'prx3_questions_sla_check' );
		}
	}

	/**
	 * Who a question can be put to. Filterable so a club can add its
	 * own audiences (e.g. a youth coach) without code changes.
	 *
	 * @return array<string,string> key => label.
	 */
	public static function categories() {
		return apply_filters(
			'prx3_question_categories',
			array(
				'board'            => __( 'Board', 'fan-ownership' ),
				'manager-prematch' => __( 'Manager — pre-match', 'fan-ownership' ),
				'manager-weekly'   => __( 'Manager — weekly', 'fan-ownership' ),
				'captain'          => __( 'Captain', 'fan-ownership' ),
			)
		);
	}

	/**
	 * A question's category key, defaulting to the board for anything
	 * unset or no longer in the list.
	 *
	 * @param int $question_id Question post ID.
	 * @return string Category key.
	 */
	public static function category( $question_id ) {
		$key = (string) get_post_meta( $question_id, '_prx3_category', true );
		return isset( self::categories()[ $key ] ) ? $key : 'board';
	}

	/**
	 * Submit a question (REST + shortcode both call this). Pending until
	 * staff moderation.
	 *
	 * @param int    $user_id  Asking owner.
	 * @param string $title    The question.
	 * @param string $detail   Optional detail.
	 * @param string $category Who it's for (categories() key; default board).
	 * @return int|WP_Error Post ID.
	 */
	public static function submit( $user_id, $title, $detail, $category = 'board' ) {
		if ( ! prx3_feature_on( 'questions' ) ) {
			return new WP_Error( 'prx3_off', __( 'Question submission is temporarily unavailable.', 'fan-ownership' ) );
		}
		if ( ! prx3_is_owner( $user_id ) ) {
			return new WP_Error( 'prx3_owner_only', __( 'Only owners can put questions to the club.', 'fan-ownership' ) );
		}
		if ( ! $title ) {
			return new WP_Error( 'prx3_title', __( 'Please write your question.', 'fan-ownership' ) );
		}
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'prx3_question',
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
		update_post_meta( $post_id, '_prx3_upvotes', array( $user_id ) );
		update_post_meta( $post_id, '_prx3_category', isset( self::categories()[ $category ] ) ? $category : 'board' );
		update_post_meta( $post_id, '_prx3_accepted_at', '' );
		PRX3_Moderation::enqueue( $post_id, 'question' );
		prx3_touch_activity( $user_id );
		return $post_id;
	}

	/**
	 * Toggle an owner's upvote on a published question (FO-212 AC2).
	 *
	 * @param int $question_id Question post ID.
	 * @param int $user_id     Voting owner.
	 * @return array|WP_Error ['count' => int, 'upvoted' => bool]
	 */
	public static function toggle_upvote( $question_id, $user_id ) {
		if ( 'publish' !== get_post_status( $question_id ) ) {
			return new WP_Error( 'prx3_question', __( 'This question is not open for upvotes.', 'fan-ownership' ) );
		}
		$votes = array_map( 'intval', array_filter( (array) get_post_meta( $question_id, '_prx3_upvotes', true ) ) );
		if ( in_array( $user_id, $votes, true ) ) {
			$votes = array_values( array_diff( $votes, array( $user_id ) ) );
			$on    = false;
		} else {
			$votes[] = $user_id;
			$on      = true;
		}
		update_post_meta( $question_id, '_prx3_upvotes', $votes );
		prx3_touch_activity( $user_id );
		return array(
			'count'   => count( $votes ),
			'upvoted' => $on,
		);
	}

	/**
	 * Written answer saved -> asker notified, answered state set (FO-212 AC4).
	 *
	 * @param int     $post_id Question post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_answer( $post_id, $post ) {
		if ( ! isset( $_POST['prx3_question_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['prx3_question_nonce'] ), 'prx3_question_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'prx3_governance' ) && ! current_user_can( 'prx3_admin' ) ) {
			return;
		}
		$answer   = isset( $_POST['prx3_answer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prx3_answer'] ) ) : '';
		$video    = isset( $_POST['prx3_video_answer'] ) ? esc_url_raw( wp_unslash( $_POST['prx3_video_answer'] ) ) : '';
		$selected = isset( $_POST['prx3_selected_for_video'] ) ? '1' : '';
		$category = isset( $_POST['prx3_q_category'] ) ? sanitize_key( wp_unslash( $_POST['prx3_q_category'] ) ) : '';
		if ( isset( self::categories()[ $category ] ) ) {
			update_post_meta( $post_id, '_prx3_category', $category );
		}
		$had = get_post_meta( $post_id, '_prx3_answer', true );
		update_post_meta( $post_id, '_prx3_answer', $answer );
		update_post_meta( $post_id, '_prx3_video_answer', $video );
		update_post_meta( $post_id, '_prx3_selected_for_video', $selected );
		if ( $answer && ! $had ) {
			update_post_meta( $post_id, '_prx3_answered_at', time() );
			$asker = get_userdata( (int) $post->post_author );
			if ( $asker ) {
				PRX3_Comms::send(
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
		$target_days = (int) prx3_setting( 'question_sla_days', 14 );
		$overdue     = get_posts(
			array(
				'post_type'      => 'prx3_question',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'date_query'     => array( array( 'before' => $target_days . ' days ago' ) ),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded daily SLA sweep, capped at 50 posts.
					array(
						'key'     => '_prx3_answer',
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
		foreach ( get_users( array( 'role__in' => array( 'prx3_governance_officer', 'prx3_owner_admin', 'administrator' ) ) ) as $staff ) {
			PRX3_Comms::send(
				$staff->user_email,
				sprintf( /* translators: %d count. */ _n( '%d owner question is past the answer target', '%d owner questions are past the answer target', count( $overdue ), 'fan-ownership' ), count( $overdue ) ),
				implode( "\n", $lines ),
				'governance'
			);
		}
	}

	/**
	 * Register the answer meta box for governance staff.
	 */
	public static function meta_box() {
		add_meta_box(
			'prx3_question_answer',
			__( "The Club's Answer", 'fan-ownership' ),
			function ( $post ) {
				wp_nonce_field( 'prx3_question_meta', 'prx3_question_nonce' );
				echo '<p><label>' . esc_html__( 'Who is this question for?', 'fan-ownership' ) . '</label> <select name="prx3_q_category">';
				foreach ( self::categories() as $key => $label ) {
					echo '<option value="' . esc_attr( $key ) . '" ' . selected( self::category( $post->ID ), $key, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select></p>';
				echo '<p><label>' . esc_html__( 'Written answer', 'fan-ownership' ) . '</label>';
				echo '<textarea class="widefat" rows="5" name="prx3_answer">' . esc_textarea( get_post_meta( $post->ID, '_prx3_answer', true ) ) . '</textarea></p>';
				echo '<p><label><input type="checkbox" name="prx3_selected_for_video" ' . checked( get_post_meta( $post->ID, '_prx3_selected_for_video', true ), '1', false ) . '> ' . esc_html__( 'Selected for the monthly Q&A video', 'fan-ownership' ) . '</label></p>';
				echo '<p><label>' . esc_html__( 'Link to the video answer', 'fan-ownership' ) . '</label>';
				echo '<input type="url" class="widefat" name="prx3_video_answer" value="' . esc_attr( get_post_meta( $post->ID, '_prx3_video_answer', true ) ) . '"></p>';
				echo '<p>' . esc_html( sprintf( /* translators: %d upvotes. */ __( 'Upvotes: %d', 'fan-ownership' ), count( (array) get_post_meta( $post->ID, '_prx3_upvotes', true ) ) ) ) . '</p>';
			},
			'prx3_question',
			'normal',
			'high'
		);
	}
}
