<?php
/**
 * Live chat behind a transport interface: efficient short-polling via the
 * plugin API now, the self-hosted websocket service (services/chat-server)
 * swaps in at deploy time without UI changes (B4 decision honouring T41).
 *
 * FO-306: member identity, board/staff marking, word filter with hold,
 * slow mode, member reporting, in-chat mod actions. One service for
 * match and meeting rooms (FO-214/FO-306 AC5).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Chat {

	public static function init() {}

	/**
	 * Post a message to a room. Room keys: match-{id}, meeting-{id}.
	 *
	 * @return array|WP_Error ['id' => int, 'held' => bool]
	 */
	public static function post_message( $room, $user_id, $body ) {
		global $wpdb;
		if ( ! fop_feature_on( 'chat' ) ) {
			return new WP_Error( 'fop_off', __( 'Chat is temporarily unavailable.', 'fan-ownership' ) );
		}
		if ( ! fop_is_owner( $user_id ) ) {
			return new WP_Error( 'fop_owner_only', __( 'Only owners can chat.', 'fan-ownership' ) );
		}
		if ( FOP_Moderation::is_muted( $user_id ) ) {
			return new WP_Error( 'fop_muted', FOP_Moderation::mute_message( $user_id ) );
		}
		$room = sanitize_key( $room );
		$body = trim( wp_strip_all_tags( (string) $body ) );
		if ( '' === $body || strlen( $body ) > 500 ) {
			return new WP_Error( 'fop_body', __( 'Messages must be between 1 and 500 characters.', 'fan-ownership' ) );
		}
		// Slow mode / rate limit (FO-306 AC2).
		$window = self::slow_mode_seconds( $room );
		$last   = (int) get_user_meta( $user_id, 'fop_chat_last_' . $room, true );
		if ( $window && time() - $last < $window && ! user_can( $user_id, 'fop_moderate' ) ) {
			return new WP_Error( 'fop_slow', sprintf( /* translators: %d seconds. */ __( 'Slow mode is on — one message every %d seconds.', 'fan-ownership' ), $window ) );
		}
		update_user_meta( $user_id, 'fop_chat_last_' . $room, time() );

		$held = self::hits_word_filter( $body );
		$wpdb->insert(
			$wpdb->prefix . 'fop_chat_messages',
			array(
				'room'       => $room,
				'user_id'    => $user_id,
				'body'       => $body,
				'created_at' => fop_now(),
				'held'       => $held ? 1 : 0,
			),
			array( '%s', '%d', '%s', '%s', '%d' )
		);
		fop_touch_activity( $user_id );
		return array(
			'id'   => (int) $wpdb->insert_id,
			'held' => $held,
		);
	}

	/**
	 * Poll messages since an ID. Held and removed messages are hidden from
	 * members; moderators see held ones flagged for release.
	 */
	public static function fetch( $room, $since_id, $user_id ) {
		global $wpdb;
		$is_mod = user_can( $user_id, 'fop_moderate' ) || user_can( $user_id, 'fop_admin' );
		$where  = $is_mod ? 'removed = 0' : 'removed = 0 AND held = 0';
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, body, created_at, held FROM {$wpdb->prefix}fop_chat_messages WHERE room = %s AND id > %d AND $where ORDER BY id ASC LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				sanitize_key( $room ),
				(int) $since_id
			),
			ARRAY_A
		);
		return array_map(
			function ( $row ) {
				$author = get_userdata( (int) $row['user_id'] );
				$name   = $author ? $author->display_name : __( 'Former member', 'fan-ownership' );
				if ( $author && user_can( $author, 'fop_board' ) ) {
						$name .= ' ' . __( '[Board]', 'fan-ownership' );
				} elseif ( $author && ( user_can( $author, 'fop_admin' ) || user_can( $author, 'fop_edit_content' ) || user_can( $author, 'fop_governance' ) ) ) {
					$name .= ' ' . __( '[Club]', 'fan-ownership' );
				}
				$row['author'] = $name;
				return $row;
			},
			$rows
		);
	}

	/* -------- Moderation tools (FO-306 AC2, T63) -------- */

	public static function moderate( $message_id, $action, $moderator_id, $target_days = 1 ) {
		global $wpdb;
		if ( ! user_can( $moderator_id, 'fop_moderate' ) && ! user_can( $moderator_id, 'fop_admin' ) ) {
			return new WP_Error( 'fop_denied', __( 'Moderators only.', 'fan-ownership' ) );
		}
		$message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fop_chat_messages WHERE id = %d", (int) $message_id ), ARRAY_A );
		if ( ! $message ) {
			return new WP_Error( 'fop_message', __( 'Message not found.', 'fan-ownership' ) );
		}
		switch ( $action ) {
			case 'delete':
				$wpdb->update(
					$wpdb->prefix . 'fop_chat_messages',
					array(
						'removed'    => 1,
						'removed_by' => $moderator_id,
					),
					array( 'id' => (int) $message_id )
				);
				break;
			case 'release':
				$wpdb->update( $wpdb->prefix . 'fop_chat_messages', array( 'held' => 0 ), array( 'id' => (int) $message_id ) );
				break;
			case 'timeout':
				update_user_meta( (int) $message['user_id'], 'fop_chat_last_' . $message['room'], time() + 10 * MINUTE_IN_SECONDS );
				break;
			case 'mute':
				return FOP_Moderation::sanction( (int) $message['user_id'], 'mute', __( 'Chat conduct', 'fan-ownership' ), $target_days );
			default:
				return new WP_Error( 'fop_action', __( 'Unknown moderation action.', 'fan-ownership' ) );
		}
		FOP_Audit::log( 'chat_mod', sprintf( 'Message %d: %s', $message_id, $action ) );
		return true;
	}

	public static function report_message( $message_id, $reporter_id, $note ) {
		return FOP_Moderation::report( $message_id, 'chat', $reporter_id, $note );
	}

	public static function set_slow_mode( $room, $seconds, $moderator_id ) {
		if ( ! user_can( $moderator_id, 'fop_moderate' ) && ! user_can( $moderator_id, 'fop_admin' ) ) {
			return new WP_Error( 'fop_denied', __( 'Moderators only.', 'fan-ownership' ) );
		}
		$modes                          = get_option( 'fop_chat_slow', array() );
		$modes[ sanitize_key( $room ) ] = max( 0, min( 300, (int) $seconds ) );
		update_option( 'fop_chat_slow', $modes, false );
		return true;
	}

	public static function slow_mode_seconds( $room ) {
		$modes = get_option( 'fop_chat_slow', array() );
		return isset( $modes[ $room ] ) ? (int) $modes[ $room ] : 0;
	}

	private static function hits_word_filter( $body ) {
		$list = array_filter( array_map( 'trim', explode( "\n", (string) fop_setting( 'chat_blocklist', '' ) ) ) );
		foreach ( $list as $word ) {
			if ( false !== stripos( $body, $word ) ) {
				return true;
			}
		}
		return false;
	}
}
