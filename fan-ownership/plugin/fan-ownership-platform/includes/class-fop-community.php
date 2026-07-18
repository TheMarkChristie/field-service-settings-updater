<?php
/**
 * Community: bbPress forum integration, comments on club content,
 * board/staff post identifiers, referrals.
 *
 * FO-220, FO-223. Forum access rides the owner gate; bbPress supplies
 * the forum itself (B3 decision) and this class binds it to the
 * platform's identity and access rules.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Community {

	public static function init() {
		// Comments open on owner content, owners only.
		add_filter( 'comments_open', array( __CLASS__, 'owners_comment' ), 10, 2 );
		add_filter( 'pre_comment_approved', array( __CLASS__, 'gate_comment' ), 10, 2 );
		// Board/staff identifier on every surface (FO-220 AC2 / P80).
		add_filter( 'get_comment_author', array( __CLASS__, 'tag_author' ), 10, 3 );
		add_filter( 'bbp_get_reply_author_display_name', array( __CLASS__, 'tag_bbp_author' ), 10, 2 );
		// bbPress behind the owner gate.
		add_action( 'template_redirect', array( __CLASS__, 'gate_forum' ), 5 );
		// Referrals (FO-223).
		add_action( 'init', array( __CLASS__, 'capture_referral' ) );
		add_action( 'fop_member_became_owner', array( __CLASS__, 'credit_referral' ) );
	}

	public static function owners_comment( $open, $post_id ) {
		if ( in_array( get_post_type( $post_id ), fop_gated_post_types(), true ) ) {
			return fop_is_owner() && fop_feature_on( 'forum' );
		}
		return $open;
	}

	/**
	 * Non-owner comments on owner content never post.
	 */
	public static function gate_comment( $approved, $commentdata ) {
		$post_id = isset( $commentdata['comment_post_ID'] ) ? (int) $commentdata['comment_post_ID'] : 0;
		if ( $post_id && in_array( get_post_type( $post_id ), fop_gated_post_types(), true ) && ! fop_is_owner() ) {
			return new WP_Error( 'fop_owner_only', __( 'Only owners can join this discussion.', 'fan-ownership' ), 403 );
		}
		// Muted members lose expression rights but keep voting (FO-221 AC3).
		if ( FOP_Moderation::is_muted( get_current_user_id() ) ) {
			return new WP_Error( 'fop_muted', FOP_Moderation::mute_message( get_current_user_id() ), 403 );
		}
		return $approved;
	}

	public static function tag_author( $author, $comment_id, $comment ) {
		$user_id = $comment ? (int) $comment->user_id : 0;
		return self::apply_tag( $author, $user_id );
	}

	public static function tag_bbp_author( $author, $reply_id ) {
		$user_id = function_exists( 'bbp_get_reply_author_id' ) ? (int) bbp_get_reply_author_id( $reply_id ) : 0;
		return self::apply_tag( $author, $user_id );
	}

	private static function apply_tag( $author, $user_id ) {
		if ( ! $user_id ) {
			return $author;
		}
		if ( user_can( $user_id, 'fop_board' ) ) {
			return $author . ' ' . __( '[Board]', 'fan-ownership' );
		}
		if ( user_can( $user_id, 'fop_admin' ) || user_can( $user_id, 'fop_governance' ) || user_can( $user_id, 'fop_edit_content' ) ) {
			return $author . ' ' . __( '[Club]', 'fan-ownership' );
		}
		return $author;
	}

	/**
	 * The whole forum sits behind the owner gate (FO-220 AC3).
	 */
	public static function gate_forum() {
		if ( ! function_exists( 'is_bbpress' ) || ! is_bbpress() ) {
			return;
		}
		if ( ! fop_feature_on( 'forum' ) ) {
			wp_die( wp_kses_post( FOP_Config::unavailable_notice( 'forum' ) ), 200 );
		}
		if ( ! fop_is_owner() ) {
			wp_safe_redirect( FOP_Access::join_url() );
			exit;
		}
		fop_touch_activity();
	}

	/**
	 * FO-223 AC1: referral links credit the referrer on the recruit's
	 * first purchase.
	 */
	public static function capture_referral() {
		if ( isset( $_GET['ref'] ) && ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification
			$ref = absint( $_GET['ref'] ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( $ref && get_userdata( $ref ) ) {
				setcookie( 'fop_ref', (string) $ref, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			}
		}
	}

	public static function credit_referral( $new_owner_id ) {
		$ref = isset( $_COOKIE['fop_ref'] ) ? absint( $_COOKIE['fop_ref'] ) : 0;
		if ( ! $ref || $ref === $new_owner_id || ! fop_is_owner( $ref ) ) {
			return;
		}
		$count = (int) get_user_meta( $ref, 'fop_referrals', true );
		update_user_meta( $ref, 'fop_referrals', $count + 1 );
		do_action( 'fop_milestone_event', 'referral', $ref );
		// FO-223 AC3: recognition only — no pricing effect anywhere.
	}
}
