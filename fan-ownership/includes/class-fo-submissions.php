<?php
/**
 * Front-end submissions: fan ideas (with upvotes) and questions to the board.
 *
 * @package FanOwnership
 */

defined( 'ABSPATH' ) || exit;

class FO_Submissions {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_handle_forms' ) );
		add_action( 'wp_ajax_fo_support_idea', array( __CLASS__, 'ajax_support_idea' ) );
	}

	/**
	 * Handle idea and question form posts.
	 */
	public static function maybe_handle_forms() {
		if ( isset( $_POST['fo_idea_nonce'] ) ) {
			self::handle_submission( 'fo_idea', 'fo_idea_nonce', 'fo_idea' );
		}
		if ( isset( $_POST['fo_question_nonce'] ) ) {
			self::handle_submission( 'fo_question', 'fo_question_nonce', 'fo_question' );
		}
	}

	/**
	 * Shared handler for both submission forms.
	 *
	 * @param string $post_type    Target post type.
	 * @param string $nonce_field  POST field holding the nonce.
	 * @param string $nonce_action Nonce action name.
	 */
	private static function handle_submission( $post_type, $nonce_field, $nonce_action ) {
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
			self::redirect_with( array( 'fo_error' => rawurlencode( __( 'Your session expired. Please try again.', 'fan-ownership' ) ) ) );
		}
		if ( ! fo_user_can_access() ) {
			self::redirect_with( array( 'fo_error' => rawurlencode( __( 'Only fan owners can submit.', 'fan-ownership' ) ) ) );
		}

		$title   = isset( $_POST['fo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['fo_title'] ) ) : '';
		$details = isset( $_POST['fo_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fo_details'] ) ) : '';

		if ( ! $title ) {
			self::redirect_with( array( 'fo_error' => rawurlencode( __( 'Please give your submission a title.', 'fan-ownership' ) ) ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_status'  => 'pending', // Club moderates before it appears to other members.
				'post_title'   => $title,
				'post_content' => $details,
				'post_author'  => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			self::redirect_with( array( 'fo_error' => rawurlencode( __( 'Could not save your submission. Please try again.', 'fan-ownership' ) ) ) );
		}

		if ( 'fo_idea' === $post_type ) {
			update_post_meta( $post_id, '_fo_idea_status', 'new' );
			update_post_meta( $post_id, '_fo_idea_supporters', array( get_current_user_id() ) );
		}

		wp_mail(
			get_option( 'admin_email' ),
			sprintf(
				/* translators: 1: submission type, 2: title. */
				__( 'New fan %1$s: %2$s', 'fan-ownership' ),
				'fo_idea' === $post_type ? __( 'idea', 'fan-ownership' ) : __( 'question', 'fan-ownership' ),
				$title
			),
			sprintf(
				/* translators: 1: member name, 2: details, 3: edit URL. */
				__( "Submitted by %1\$s:\n\n%2\$s\n\nReview and publish: %3\$s", 'fan-ownership' ),
				wp_get_current_user()->display_name,
				$details,
				admin_url( 'post.php?post=' . $post_id . '&action=edit' )
			)
		);

		self::redirect_with( array( 'fo_submitted' => 'fo_idea' === $post_type ? 'idea' : 'question' ) );
	}

	private static function redirect_with( $args ) {
		$url = wp_get_referer() ? wp_get_referer() : home_url();
		$url = remove_query_arg( array( 'fo_error', 'fo_submitted' ), $url );
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	/**
	 * AJAX: toggle support for an idea.
	 */
	public static function ajax_support_idea() {
		check_ajax_referer( 'fo_ajax', 'nonce' );

		if ( ! fo_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Only fan owners can support ideas.', 'fan-ownership' ) ), 403 );
		}

		$idea_id = isset( $_POST['idea_id'] ) ? absint( $_POST['idea_id'] ) : 0;
		if ( ! $idea_id || get_post_type( $idea_id ) !== 'fo_idea' || get_post_status( $idea_id ) !== 'publish' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid idea.', 'fan-ownership' ) ), 400 );
		}

		$user_id    = get_current_user_id();
		$supporters = get_post_meta( $idea_id, '_fo_idea_supporters', true );
		$supporters = is_array( $supporters ) ? $supporters : array();

		if ( in_array( $user_id, $supporters, true ) ) {
			$supporters = array_values( array_diff( $supporters, array( $user_id ) ) );
			$supporting = false;
		} else {
			$supporters[] = $user_id;
			$supporting   = true;
		}
		update_post_meta( $idea_id, '_fo_idea_supporters', $supporters );

		$count = count( $supporters );
		wp_send_json_success(
			array(
				'supporting' => $supporting,
				'count'      => $count,
				'label'      => $supporting ? __( 'Supported', 'fan-ownership' ) : __( 'Support this idea', 'fan-ownership' ),
				'message'    => sprintf(
					/* translators: %d: supporter count. */
					_n( '%d member supports this idea.', '%d members support this idea.', $count, 'fan-ownership' ),
					$count
				),
			)
		);
	}

	/**
	 * Supporter count for an idea.
	 *
	 * @param int $idea_id Idea post ID.
	 * @return int
	 */
	public static function supporter_count( $idea_id ) {
		$supporters = get_post_meta( $idea_id, '_fo_idea_supporters', true );
		return is_array( $supporters ) ? count( $supporters ) : 0;
	}

	/**
	 * Is the given user currently supporting an idea?
	 *
	 * @param int $idea_id Idea post ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_supporting( $idea_id, $user_id ) {
		$supporters = get_post_meta( $idea_id, '_fo_idea_supporters', true );
		return is_array( $supporters ) && in_array( $user_id, $supporters, true );
	}
}
