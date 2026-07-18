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

/**
 * Live chat service: message posting and polling with word-filter holds,
 * slow mode and in-chat moderation, shared by match and meeting rooms.
 */
class PRX3_Chat {

	/**
	 * No hooks needed yet; chat is driven through the plugin REST API.
	 */
	public static function init() {}

	/**
	 * Post a message to a room. Room keys: match-{id}, meeting-{id}.
	 *
	 * @param string $room    Room key.
	 * @param int    $user_id Posting member's user ID.
	 * @param string $body    Message text.
	 * @return array|WP_Error ['id' => int, 'held' => bool]
	 */
	public static function post_message( $room, $user_id, $body ) {
		global $wpdb;
		if ( ! prx3_feature_on( 'chat' ) ) {
			return new WP_Error( 'prx3_off', __( 'Chat is temporarily unavailable.', 'fan-ownership' ) );
		}
		if ( ! prx3_is_owner( $user_id ) ) {
			return new WP_Error( 'prx3_owner_only', __( 'Only owners can chat.', 'fan-ownership' ) );
		}
		if ( PRX3_Moderation::is_muted( $user_id ) ) {
			return new WP_Error( 'prx3_muted', PRX3_Moderation::mute_message( $user_id ) );
		}
		$room = sanitize_key( $room );
		$body = trim( wp_strip_all_tags( (string) $body ) );
		if ( '' === $body || strlen( $body ) > 500 ) {
			return new WP_Error( 'prx3_body', __( 'Messages must be between 1 and 500 characters.', 'fan-ownership' ) );
		}
		// Slow mode / rate limit (FO-306 AC2).
		$window = self::slow_mode_seconds( $room );
		$last   = (int) get_user_meta( $user_id, 'prx3_chat_last_' . $room, true );
		if ( $window && time() - $last < $window && ! user_can( $user_id, 'prx3_moderate' ) ) {
			return new WP_Error( 'prx3_slow', sprintf( /* translators: %d seconds. */ __( 'Slow mode is on — one message every %d seconds.', 'fan-ownership' ), $window ) );
		}
		update_user_meta( $user_id, 'prx3_chat_last_' . $room, time() );

		$held = self::hits_word_filter( $body );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- write to the plugin's own chat messages table (moderation state lives there).
			$wpdb->prefix . 'prx3_chat_messages',
			array(
				'room'       => $room,
				'user_id'    => $user_id,
				'body'       => $body,
				'created_at' => prx3_now(),
				'held'       => $held ? 1 : 0,
			),
			array( '%s', '%d', '%s', '%s', '%d' )
		);
		prx3_touch_activity( $user_id );
		return array(
			'id'   => (int) $wpdb->insert_id,
			'held' => $held,
		);
	}

	/**
	 * Poll messages since an ID. Held and removed messages are hidden from
	 * members; moderators see held ones flagged for release.
	 *
	 * @param string $room     Room key.
	 * @param int    $since_id Return messages with IDs greater than this.
	 * @param int    $user_id  Requesting user ID.
	 * @return array[] Message rows with an author label added.
	 */
	public static function fetch( $room, $since_id, $user_id ) {
		global $wpdb;
		$is_mod = user_can( $user_id, 'prx3_moderate' ) || user_can( $user_id, 'prx3_admin' );
		$where  = $is_mod ? 'removed = 0' : 'removed = 0 AND held = 0';
		$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- short-poll read of the plugin's own chat table; caching deliberately avoided so new messages appear immediately.
			$wpdb->prepare(
				"SELECT id, user_id, body, created_at, held FROM {$wpdb->prefix}prx3_chat_messages WHERE room = %s AND id > %d AND $where ORDER BY id ASC LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				sanitize_key( $room ),
				(int) $since_id
			),
			ARRAY_A
		);
		return array_map(
			function ( $row ) {
				$author = get_userdata( (int) $row['user_id'] );
				$name   = $author ? $author->display_name : __( 'Former member', 'fan-ownership' );
				if ( $author && user_can( $author, 'prx3_board' ) ) {
						$name .= ' ' . __( '[Board]', 'fan-ownership' );
				} elseif ( $author && ( user_can( $author, 'prx3_admin' ) || user_can( $author, 'prx3_edit_content' ) || user_can( $author, 'prx3_governance' ) ) ) {
					$name .= ' ' . __( '[Club]', 'fan-ownership' );
				}
				$row['author'] = $name;
				return $row;
			},
			$rows
		);
	}

	/* -------- Moderation tools (FO-306 AC2, T63) -------- */

	/**
	 * Apply a moderation action to a message: delete it, release a held
	 * one, time the author out, or mute them (FO-306 AC2, T63).
	 *
	 * @param int    $message_id   Message ID.
	 * @param string $action       One of 'delete', 'release', 'timeout', 'mute'.
	 * @param int    $moderator_id Acting moderator's user ID.
	 * @param int    $target_days  Mute length in days ('mute' action only).
	 * @return true|WP_Error
	 */
	public static function moderate( $message_id, $action, $moderator_id, $target_days = 1 ) {
		global $wpdb;
		if ( ! user_can( $moderator_id, 'prx3_moderate' ) && ! user_can( $moderator_id, 'prx3_admin' ) ) {
			return new WP_Error( 'prx3_denied', __( 'Moderators only.', 'fan-ownership' ) );
		}
		$message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}prx3_chat_messages WHERE id = %d", (int) $message_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- moderation lookup on the plugin's own chat table; must see the current row.
		if ( ! $message ) {
			return new WP_Error( 'prx3_message', __( 'Message not found.', 'fan-ownership' ) );
		}
		switch ( $action ) {
			case 'delete':
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- moderation state lives on the plugin's own chat table.
					$wpdb->prefix . 'prx3_chat_messages',
					array(
						'removed'    => 1,
						'removed_by' => $moderator_id,
					),
					array( 'id' => (int) $message_id )
				);
				break;
			case 'release':
				$wpdb->update( $wpdb->prefix . 'prx3_chat_messages', array( 'held' => 0 ), array( 'id' => (int) $message_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- moderation state lives on the plugin's own chat table.
				break;
			case 'timeout':
				update_user_meta( (int) $message['user_id'], 'prx3_chat_last_' . $message['room'], time() + 10 * MINUTE_IN_SECONDS );
				break;
			case 'mute':
				return PRX3_Moderation::sanction( (int) $message['user_id'], 'mute', __( 'Chat conduct', 'fan-ownership' ), $target_days );
			default:
				return new WP_Error( 'prx3_action', __( 'Unknown moderation action.', 'fan-ownership' ) );
		}
		PRX3_Audit::log( 'chat_mod', sprintf( 'Message %d: %s', $message_id, $action ) );
		return true;
	}

	/**
	 * Report a chat message to the moderation queue.
	 *
	 * @param int    $message_id  Message ID.
	 * @param int    $reporter_id Reporting member's user ID.
	 * @param string $note        Reporter's note.
	 * @return true|WP_Error
	 */
	public static function report_message( $message_id, $reporter_id, $note ) {
		return PRX3_Moderation::report( $message_id, 'chat', $reporter_id, $note );
	}

	/**
	 * Set a room's slow-mode window (moderators only).
	 *
	 * @param string $room         Room key.
	 * @param int    $seconds      Seconds between messages, capped at 300; 0 turns slow mode off.
	 * @param int    $moderator_id Acting moderator's user ID.
	 * @return true|WP_Error
	 */
	public static function set_slow_mode( $room, $seconds, $moderator_id ) {
		if ( ! user_can( $moderator_id, 'prx3_moderate' ) && ! user_can( $moderator_id, 'prx3_admin' ) ) {
			return new WP_Error( 'prx3_denied', __( 'Moderators only.', 'fan-ownership' ) );
		}
		$modes                          = get_option( 'prx3_chat_slow', array() );
		$modes[ sanitize_key( $room ) ] = max( 0, min( 300, (int) $seconds ) );
		update_option( 'prx3_chat_slow', $modes, false );
		return true;
	}

	/**
	 * Current slow-mode window for a room.
	 *
	 * @param string $room Room key.
	 * @return int Seconds between messages; 0 when slow mode is off.
	 */
	public static function slow_mode_seconds( $room ) {
		$modes = get_option( 'prx3_chat_slow', array() );
		return isset( $modes[ $room ] ) ? (int) $modes[ $room ] : 0;
	}

	/**
	 * Does the message hit the configured word filter (held for review)?
	 *
	 * @param string $body Message text.
	 * @return bool
	 */
	private static function hits_word_filter( $body ) {
		$list = array_filter( array_map( 'trim', explode( "\n", (string) prx3_setting( 'chat_blocklist', '' ) ) ) );
		foreach ( $list as $word ) {
			if ( false !== stripos( $body, $word ) ) {
				return true;
			}
		}
		return false;
	}
}
