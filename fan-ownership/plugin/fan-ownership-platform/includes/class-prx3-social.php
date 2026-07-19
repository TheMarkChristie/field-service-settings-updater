<?php
/**
 * The social layer (P110): BuddyPress-parity community features built
 * natively on the platform — no BuddyPress dependency:
 *
 *  - Activity stream: one feed of club life (topics, replies, ballots
 *    opening, decisions, videos, new owners), filterable to the
 *    members you follow.
 *  - Member directory & profiles: every owner discoverable to other
 *    owners, with owner number, badges, join date, and follow button.
 *  - Private messages: member-to-member conversations riding the
 *    moderated chat infrastructure (word filter, mutes, retention).
 *  - Notifications: on-site notification list — replies to your
 *    topics, @mentions, new private messages.
 *  - Follows: one-way follows powering the personalised feed.
 *
 * Groups are the platform's chapters (FO-222); forum is PRX3_Forum.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Native community layer: activity feed, member directory, private
 * messages over chat rooms, notifications, mentions, and follows
 * (FO-231).
 */
class PRX3_Social {

	const NOTIFICATIONS_CAP = 50;

	/**
	 * Wire shortcodes, notification generators, and the member API.
	 */
	public static function init() {
		add_shortcode( 'prx3_activity', array( __CLASS__, 'shortcode_activity' ) );
		add_shortcode( 'prx3_members', array( __CLASS__, 'shortcode_members' ) );
		add_shortcode( 'prx3_messages', array( __CLASS__, 'shortcode_messages' ) );
		add_shortcode( 'prx3_notifications', array( __CLASS__, 'shortcode_notifications' ) );

		// Notification generators.
		add_action( 'wp_insert_comment', array( __CLASS__, 'on_reply' ), 10, 2 );
		add_action( 'publish_prx3_forum_topic', array( __CLASS__, 'on_topic' ), 10, 2 );

		add_action( 'admin_post_prx3_follow', array( __CLASS__, 'handle_follow' ) );
		add_action( 'admin_post_prx3_send_dm', array( __CLASS__, 'handle_send_dm' ) );
		add_action( 'admin_post_prx3_cheer', array( __CLASS__, 'handle_cheer' ) );
		add_shortcode( 'prx3_profile', array( __CLASS__, 'shortcode_profile' ) );
		add_action( 'admin_post_prx3_save_profile', array( __CLASS__, 'handle_save_profile' ) );
		add_action( 'admin_post_prx3_notify_email', array( __CLASS__, 'handle_notify_email' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/* ---------------- Notifications ---------------- */

	/**
	 * Add a notification to a member's on-site list (capped, unread).
	 *
	 * @param int    $user_id Recipient.
	 * @param string $type    Type key: reply|mention|dm.
	 * @param string $text    Human text.
	 * @param string $link     Destination URL.
	 * @param int    $topic_id Related chat (respects per-chat mute), 0 for none.
	 */
	public static function notify( $user_id, $type, $text, $link, $topic_id = 0 ) {
		if ( ! $user_id ) {
			return;
		}
		$list   = array_values( array_filter( (array) get_user_meta( $user_id, 'prx3_notifications', true ) ) );
		$list[] = array(
			'type' => sanitize_key( $type ),
			'text' => sanitize_text_field( $text ),
			'link' => esc_url_raw( $link ),
			'at'   => prx3_now(),
			'read' => false,
		);
		if ( count( $list ) > self::NOTIFICATIONS_CAP ) {
			$list = array_slice( $list, -self::NOTIFICATIONS_CAP );
		}
		update_user_meta( $user_id, 'prx3_notifications', $list );

		// Email too (FO-236), unless the member switched chat emails off
		// or muted this chat.
		if ( '' !== (string) get_user_meta( $user_id, 'prx3_notify_email_off', true ) ) {
			return;
		}
		if ( $topic_id && in_array( (int) $topic_id, array_map( 'intval', (array) get_user_meta( $user_id, 'prx3_muted_chats', true ) ), true ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( $user && class_exists( 'PRX3_Comms' ) ) {
			PRX3_Comms::send( $user->user_email, sanitize_text_field( $text ), sanitize_text_field( $text ) . "\n" . esc_url_raw( $link ), 'community' );
		}
	}

	/**
	 * A member's notifications, newest first; optionally mark all read.
	 *
	 * @param int  $user_id   Member.
	 * @param bool $mark_read Mark everything read after fetching.
	 * @return array[] Notification entries.
	 */
	public static function notifications( $user_id, $mark_read = false ) {
		$list = array_values( array_filter( (array) get_user_meta( $user_id, 'prx3_notifications', true ) ) );
		if ( $mark_read ) {
			$updated = array();
			foreach ( $list as $entry ) {
				$entry['read'] = true;
				$updated[]     = $entry;
			}
			update_user_meta( $user_id, 'prx3_notifications', $updated );
		}
		return array_reverse( $list );
	}

	/**
	 * Unread count for the bell.
	 *
	 * @param int $user_id Member.
	 * @return int Unread notifications.
	 */
	public static function unread_count( $user_id ) {
		return count( array_filter( self::notifications( $user_id ), fn( $entry ) => empty( $entry['read'] ) ) );
	}

	/**
	 * Users @mentioned in a text, by login/nicename, excluding self.
	 *
	 * @param string $text      The content.
	 * @param int    $author_id The writer (never notified of self-mention).
	 * @return int[] Mentioned user IDs.
	 */
	public static function mention_targets( $text, $author_id = 0 ) {
		if ( ! preg_match_all( '/@([a-z0-9_.\-]+)/i', wp_strip_all_tags( (string) $text ), $matches ) ) {
			return array();
		}
		$targets = array();
		foreach ( array_unique( $matches[1] ) as $handle ) {
			$user = get_user_by( 'login', strtolower( $handle ) );
			if ( ! $user ) {
				$user = get_user_by( 'slug', strtolower( $handle ) );
			}
			if ( $user && (int) $user->ID !== (int) $author_id ) {
				$targets[] = (int) $user->ID;
			}
		}
		return $targets;
	}

	/**
	 * A forum reply landed: notify the topic author and any mentions.
	 *
	 * @param int        $comment_id Comment ID.
	 * @param WP_Comment $comment    Comment object.
	 */
	public static function on_reply( $comment_id, $comment ) {
		$post_id = (int) ( is_object( $comment ) ? $comment->comment_post_ID : ( $comment['comment_post_ID'] ?? 0 ) );
		if ( 'prx3_forum_topic' !== get_post_type( $post_id ) ) {
			return;
		}
		$content   = is_object( $comment ) ? $comment->comment_content : (string) ( $comment['comment_content'] ?? '' );
		$author_id = (int) ( is_object( $comment ) ? $comment->user_id : ( $comment['user_id'] ?? 0 ) );
		$topic     = get_post( $post_id );
		$owner     = $topic ? (int) $topic->post_author : 0;
		if ( $owner && $owner !== $author_id ) {
			self::notify( $owner, 'reply', sprintf( /* translators: %s topic. */ __( 'New reply on your topic "%s"', 'fan-ownership' ), $topic->post_title ), get_permalink( $post_id ), $post_id );
		}
		foreach ( self::mention_targets( $content, $author_id ) as $target ) {
			self::notify( $target, 'mention', sprintf( /* translators: %s topic. */ __( 'You were mentioned in "%s"', 'fan-ownership' ), $topic ? $topic->post_title : '' ), get_permalink( $post_id ), $post_id );
		}
	}

	/**
	 * A topic published: notify anyone @mentioned in it.
	 *
	 * @param int     $post_id Topic ID.
	 * @param WP_Post $post    Topic post.
	 */
	public static function on_topic( $post_id, $post = null ) {
		$content = $post && isset( $post->post_content ) ? $post->post_content : '';
		$author  = $post && isset( $post->post_author ) ? (int) $post->post_author : 0;
		foreach ( self::mention_targets( $content, $author ) as $target ) {
			self::notify( $target, 'mention', sprintf( /* translators: %s topic. */ __( 'You were mentioned in "%s"', 'fan-ownership' ), get_the_title( $post_id ) ), get_permalink( $post_id ) );
		}
	}

	/* ---------------- Follows ---------------- */

	/**
	 * Toggle following another member.
	 *
	 * @param int $user_id   The follower.
	 * @param int $target_id The member being (un)followed.
	 * @return bool True when now following.
	 */
	public static function toggle_follow( $user_id, $target_id ) {
		$target_id = (int) $target_id;
		if ( ! $target_id || $target_id === (int) $user_id ) {
			return false;
		}
		$following = array_map( 'intval', array_filter( (array) get_user_meta( $user_id, 'prx3_following', true ) ) );
		if ( in_array( $target_id, $following, true ) ) {
			$following = array_values( array_diff( $following, array( $target_id ) ) );
			$now       = false;
		} else {
			$following[] = $target_id;
			$now         = true;
		}
		update_user_meta( $user_id, 'prx3_following', $following );
		return $now;
	}

	/**
	 * Who a member follows.
	 *
	 * @param int $user_id Member.
	 * @return int[] Followed user IDs.
	 */
	public static function following( $user_id ) {
		return array_map( 'intval', array_filter( (array) get_user_meta( $user_id, 'prx3_following', true ) ) );
	}

	/**
	 * Follow/unfollow from the directory.
	 */
	public static function handle_follow() {
		if ( ! is_user_logged_in() || ! prx3_is_owner() ) {
			wp_die( esc_html__( 'Owners only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_follow' );
		self::toggle_follow( get_current_user_id(), isset( $_POST['target'] ) ? absint( $_POST['target'] ) : 0 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	/* ---------------- Private messages (over chat rooms) ---------------- */

	/**
	 * The deterministic room key for a pair of members.
	 *
	 * @param int $a One member.
	 * @param int $b The other.
	 * @return string Room key, identical whichever way round.
	 */
	public static function dm_room_key( $a, $b ) {
		$low  = min( (int) $a, (int) $b );
		$high = max( (int) $a, (int) $b );
		return 'dm-' . $low . '-' . $high;
	}

	/**
	 * Send a private message: same moderation rails as public chat,
	 * plus a notification and a conversation index for both inboxes.
	 *
	 * @param int    $from_id Sender.
	 * @param int    $to_id   Recipient.
	 * @param string $body    Message text.
	 * @return true|WP_Error
	 */
	public static function send_dm( $from_id, $to_id, $body ) {
		$to = get_userdata( (int) $to_id );
		if ( ! $to || ! prx3_is_owner( $to_id ) ) {
			return new WP_Error( 'prx3_dm_target', __( 'You can only message fellow owners.', 'fan-ownership' ) );
		}
		if ( (int) $from_id === (int) $to_id ) {
			return new WP_Error( 'prx3_dm_self', __( 'That would be talking to yourself.', 'fan-ownership' ) );
		}
		$room   = self::dm_room_key( $from_id, $to_id );
		$result = PRX3_Chat::post_message( $room, $from_id, $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		foreach ( array( (int) $from_id, (int) $to_id ) as $participant ) {
			$threads          = (array) get_user_meta( $participant, 'prx3_dm_threads', true );
			$other            = $participant === (int) $from_id ? (int) $to_id : (int) $from_id;
			$threads[ $room ] = array(
				'with' => $other,
				'at'   => prx3_now(),
			);
			update_user_meta( $participant, 'prx3_dm_threads', $threads );
		}
		$from = get_userdata( $from_id );
		self::notify( $to_id, 'dm', sprintf( /* translators: %s sender. */ __( 'New private message from %s', 'fan-ownership' ), $from ? $from->display_name : '' ), home_url( '/' ) );
		return true;
	}

	/**
	 * Guard: only the two participants may read a DM room.
	 *
	 * @param string $room    Room key.
	 * @param int    $user_id Reader.
	 * @return bool True when the reader belongs to the conversation.
	 */
	public static function can_read_dm( $room, $user_id ) {
		if ( ! preg_match( '/^dm-(\d+)-(\d+)$/', (string) $room, $matches ) ) {
			return false;
		}
		return in_array( (int) $user_id, array( (int) $matches[1], (int) $matches[2] ), true );
	}

	/**
	 * Front-end DM send.
	 */
	public static function handle_send_dm() {
		if ( ! is_user_logged_in() || ! prx3_is_owner() ) {
			wp_die( esc_html__( 'Owners only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_send_dm' );
		$result = self::send_dm(
			get_current_user_id(),
			isset( $_POST['to'] ) ? absint( $_POST['to'] ) : 0,
			isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : ''
		);
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	/* ---------------- Owner activity & profile (FO-235) ---------------- */

	/**
	 * An owner's activity percentage: the average of three engagement
	 * components — voting (ballots voted of those closed this year),
	 * community (FanPress posts in the last 90 days, 10 = full marks),
	 * and watching (matches watched/listened of those in the last 90
	 * days, tracked when an owner opens a match page or stream).
	 *
	 * @param int $user_id The owner.
	 * @return array{percent:int,voting:int,community:int,watching:int}
	 */
	public static function activity_score( $user_id ) {
		$user_id = (int) $user_id;
		$voted   = 0;
		$closed  = 0;
		foreach ( get_posts(
			array(
				'post_type'   => 'prx3_ballot',
				'post_status' => array( 'publish' ),
				'numberposts' => -1,
			)
		) as $ballot ) {
			if ( ! in_array( PRX3_Ballots::state( $ballot->ID ), array( 'closed', 'published', 'open' ), true ) ) {
				continue;
			}
			++$closed;
			if ( PRX3_Ballots::member_choice( $ballot->ID, $user_id ) ) {
				++$voted;
			}
		}
		$voting = $closed ? (int) round( 100 * $voted / $closed ) : 0;

		$posts  = 0;
		$recent = strtotime( '-90 days' );
		foreach ( get_comments( array( 'status' => 'approve' ) ) as $comment ) {
			$uid = (int) ( is_object( $comment ) ? $comment->user_id : ( $comment['user_id'] ?? 0 ) );
			$at  = strtotime( (string) ( is_object( $comment ) ? $comment->comment_date : ( $comment['comment_date'] ?? '' ) ) );
			if ( $uid === $user_id && ( ! $at || $at >= $recent ) ) {
				++$posts;
			}
		}
		$community = (int) min( 100, round( 100 * $posts / 10 ) );

		$watched = count( array_filter( (array) get_user_meta( $user_id, 'prx3_matches_watched', true ) ) );
		$held    = 0;
		foreach ( get_posts(
			array(
				'post_type'   => 'prx3_match',
				'post_status' => array( 'publish' ),
				'numberposts' => -1,
			)
		) as $match ) {
			++$held;
		}
		$watching = $held ? (int) min( 100, round( 100 * $watched / $held ) ) : 0;

		return array(
			'percent'   => (int) round( ( $voting + $community + $watching ) / 3 ),
			'voting'    => $voting,
			'community' => $community,
			'watching'  => $watching,
		);
	}

	/**
	 * Record that an owner watched or listened to a match (once each).
	 *
	 * @param int $user_id  The owner.
	 * @param int $match_id The match.
	 */
	public static function record_match_watch( $user_id, $match_id ) {
		if ( ! $user_id || ! $match_id ) {
			return;
		}
		$watched = array_map( 'intval', array_filter( (array) get_user_meta( $user_id, 'prx3_matches_watched', true ) ) );
		if ( ! in_array( (int) $match_id, $watched, true ) ) {
			$watched[] = (int) $match_id;
			update_user_meta( $user_id, 'prx3_matches_watched', $watched );
		}
	}

	/**
	 * [prx3_profile] — the owner profile: personal details, socials,
	 * owner since, shares, badges, and the activity meter. Own profile
	 * is editable; other owners see the public card (?prx3_member=ID).
	 *
	 * @return string Profile HTML.
	 */
	public static function shortcode_profile() {
		if ( ! prx3_is_owner() ) {
			return PRX3_Access::gate_content( '' );
		}
		wp_enqueue_style( 'prx3' );
		$me = get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only profile selector.
		$who    = isset( $_GET['prx3_member'] ) ? absint( $_GET['prx3_member'] ) : $me;
		$member = get_userdata( $who );
		if ( ! $member || ! prx3_is_owner( $who ) ) {
			return '<p>' . esc_html__( 'Owner not found.', 'fan-ownership' ) . '</p>';
		}
		$own     = $who === $me;
		$score   = self::activity_score( $who );
		$socials = (array) get_user_meta( $who, 'prx3_socials', true );
		$bio     = (string) get_user_meta( $who, 'prx3_bio', true );
		$badges  = class_exists( 'PRX3_Badges' ) ? (array) PRX3_Badges::member_badges( $who ) : array();

		$out  = '<div class="prx3-profile"><div class="prx3-profile__head">';
		$out .= '<h2>' . esc_html( $member->display_name ) . '</h2>';
		$out .= '<p class="prx3-profile__meta">' . esc_html( sprintf( /* translators: %d owner number. */ __( 'Owner #%d', 'fan-ownership' ), PRX3_Shares::owner_number( $who ) ) );
		$out .= ' · ' . esc_html( sprintf( /* translators: %s date. */ __( 'Owner since %s', 'fan-ownership' ), prx3_format_datetime( (string) $member->user_registered ) ) ) . '</p></div>';
		if ( $bio ) {
			$out .= '<p class="prx3-profile__bio">' . esc_html( $bio ) . '</p>';
		}
		$out .= '<div class="prx3-tiles">';
		$out .= '<div class="prx3-tile"><span class="prx3-tile-label">' . esc_html__( 'Activity', 'fan-ownership' ) . '</span><strong class="prx3-tile-value">' . (int) $score['percent'] . '%</strong><span class="prx3-tile-note">' . esc_html( sprintf( /* translators: 1-3 percents. */ __( 'Voting %1$d%% · Community %2$d%% · Watching %3$d%%', 'fan-ownership' ), $score['voting'], $score['community'], $score['watching'] ) ) . '</span></div>';
		if ( $own || get_user_meta( $who, 'prx3_shares_public', true ) ) {
			$out .= '<div class="prx3-tile"><span class="prx3-tile-label">' . esc_html__( 'Shares', 'fan-ownership' ) . '</span><strong class="prx3-tile-value">' . (int) prx3_shares( $who ) . '</strong><span class="prx3-tile-note">' . esc_html( sprintf( /* translators: %d votes. */ __( '%d votes in every ballot', 'fan-ownership' ), prx3_shares( $who ) ) ) . '</span></div>';
		}
		$out  .= '<div class="prx3-tile"><span class="prx3-tile-label">' . esc_html__( 'Badges', 'fan-ownership' ) . '</span><strong class="prx3-tile-value">' . (int) count( $badges ) . '</strong><span class="prx3-tile-note">' . esc_html( $badges ? implode( ', ', array_slice( $badges, 0, 4 ) ) : __( 'Owner', 'fan-ownership' ) ) . '</span></div>';
		$out  .= '</div>';
		$known = array(
			'x'         => 'X',
			'instagram' => 'Instagram',
			'facebook'  => 'Facebook',
			'bluesky'   => 'Bluesky',
		);
		$links = '';
		foreach ( $known as $key => $label ) {
			if ( ! empty( $socials[ $key ] ) ) {
				$links .= '<a class="prx3-badge" rel="nofollow noopener" href="' . esc_url( $socials[ $key ] ) . '">' . esc_html( $label ) . '</a> ';
			}
		}
		if ( $links ) {
			$out .= '<p class="prx3-profile__socials">' . $links . '</p>';
		}
		if ( ! $own ) {
			$following = self::following( $me );
			$out      .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'prx3_follow', '_wpnonce', true, false );
			$out      .= '<input type="hidden" name="action" value="prx3_follow"><input type="hidden" name="target" value="' . esc_attr( (string) $who ) . '">';
			$out      .= '<button class="prx3-button">' . ( in_array( $who, $following, true ) ? esc_html__( 'Unfollow', 'fan-ownership' ) : esc_html__( 'Follow', 'fan-ownership' ) ) . '</button></form>';
			return $out . '</div>';
		}

		// Own profile: personal details + edit form.
		$out .= '<h3>' . esc_html__( 'My details', 'fan-ownership' ) . '</h3>';
		$out .= '<p>' . esc_html( $member->user_email ) . '</p>';
		$out .= '<form class="prx3-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'prx3_save_profile', '_wpnonce', true, false );
		$out .= '<input type="hidden" name="action" value="prx3_save_profile">';
		$out .= '<p><label for="prx3_bio">' . esc_html__( 'Bio (public to fellow owners)', 'fan-ownership' ) . '</label><textarea id="prx3_bio" name="bio" rows="2" maxlength="300">' . esc_textarea( $bio ) . '</textarea></p>';
		foreach ( $known as $key => $label ) {
			$out .= '<p><label for="prx3_social_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><input type="url" id="prx3_social_' . esc_attr( $key ) . '" name="socials[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) ( $socials[ $key ] ?? '' ) ) . '" placeholder="https://"></p>';
		}
		$out .= '<p><label><input type="checkbox" name="shares_public" value="1" ' . checked( (string) get_user_meta( $who, 'prx3_shares_public', true ), '1', false ) . '> ' . esc_html__( 'Show my share count to fellow owners', 'fan-ownership' ) . '</label></p>';
		$out .= '<p><button class="prx3-button">' . esc_html__( 'Save profile', 'fan-ownership' ) . '</button></p></form>';
		$off  = (string) get_user_meta( $me, 'prx3_notify_email_off', true );
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'prx3_notify_email', '_wpnonce', true, false );
		$out .= '<input type="hidden" name="action" value="prx3_notify_email"><button class="prx3-button prx3-button--secondary">' . esc_html( $off ? __( 'Turn chat emails on', 'fan-ownership' ) : __( 'Turn chat emails off', 'fan-ownership' ) ) . '</button></form>';
		return $out . '</div>';
	}

	/**
	 * Save bio, socials, and the share-visibility choice.
	 */
	public static function handle_save_profile() {
		if ( ! is_user_logged_in() || ! prx3_is_owner() ) {
			wp_die( esc_html__( 'Owners only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_save_profile' );
		$me = get_current_user_id();
		update_user_meta( $me, 'prx3_bio', isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : '' );
		$socials     = array();
		$raw_socials = isset( $_POST['socials'] ) ? (array) wp_unslash( $_POST['socials'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per entry below.
		foreach ( $raw_socials as $key => $url ) {
			$url = esc_url_raw( $url );
			if ( $url ) {
				$socials[ sanitize_key( $key ) ] = $url;
			}
		}
		update_user_meta( $me, 'prx3_socials', $socials );
		update_user_meta( $me, 'prx3_shares_public', isset( $_POST['shares_public'] ) ? '1' : '' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	/**
	 * Toggle chat email notifications.
	 */
	public static function handle_notify_email() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Owners only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_notify_email' );
		$me = get_current_user_id();
		update_user_meta( $me, 'prx3_notify_email_off', get_user_meta( $me, 'prx3_notify_email_off', true ) ? '' : '1' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	/* ---------------- Cheers (activity likes) ---------------- */

	/**
	 * Toggle a member's cheer on a piece of club content.
	 *
	 * @param int $user_id The member.
	 * @param int $post_id The topic/ballot/decision/video being cheered.
	 * @return bool True when now cheered.
	 */
	public static function toggle_cheer( $user_id, $post_id ) {
		$user_id = (int) $user_id;
		$post_id = (int) $post_id;
		if ( ! $user_id || ! $post_id ) {
			return false;
		}
		$cheers = array_map( 'intval', array_filter( (array) get_post_meta( $post_id, '_prx3_cheers', true ) ) );
		if ( in_array( $user_id, $cheers, true ) ) {
			$cheers = array_values( array_diff( $cheers, array( $user_id ) ) );
			$now    = false;
		} else {
			$cheers[] = $user_id;
			$now      = true;
		}
		update_post_meta( $post_id, '_prx3_cheers', $cheers );
		return $now;
	}

	/**
	 * How many members have cheered a piece of content.
	 *
	 * @param int $post_id The content.
	 * @return int Cheer count.
	 */
	public static function cheer_count( $post_id ) {
		return count( array_filter( (array) get_post_meta( (int) $post_id, '_prx3_cheers', true ) ) );
	}

	/**
	 * Whether a member has cheered a piece of content.
	 *
	 * @param int $user_id The member.
	 * @param int $post_id The content.
	 * @return bool
	 */
	public static function has_cheered( $user_id, $post_id ) {
		$cheers = array_map( 'intval', array_filter( (array) get_post_meta( (int) $post_id, '_prx3_cheers', true ) ) );
		return in_array( (int) $user_id, $cheers, true );
	}

	/**
	 * Cheer/uncheer from the activity feed.
	 */
	public static function handle_cheer() {
		if ( ! is_user_logged_in() || ! prx3_is_owner() ) {
			wp_die( esc_html__( 'Owners only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_cheer' );
		self::toggle_cheer( get_current_user_id(), isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	/* ---------------- The activity stream ---------------- */

	/**
	 * The club activity feed: recent life across the platform, merged
	 * and sorted, optionally restricted to followed members.
	 *
	 * @param int   $limit       Entries to return.
	 * @param int[] $only_actors Restrict to these author IDs ('' = everyone).
	 * @return array[] Entries: at, text, link, actor.
	 */
	public static function feed( $limit = 30, $only_actors = array() ) {
		$entries = array();
		foreach ( get_posts(
			array(
				'post_type'      => 'prx3_forum_topic',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
			)
		) as $topic ) {
			$entries[] = array(
				'id'    => (int) $topic->ID,
				'at'    => $topic->post_date ?? prx3_now(),
				'actor' => (int) ( $topic->post_author ?? 0 ),
				'text'  => sprintf( /* translators: %s title. */ __( 'New topic: %s', 'fan-ownership' ), $topic->post_title ),
				'link'  => get_permalink( $topic->ID ),
			);
		}
		foreach ( get_posts(
			array(
				'post_type'      => 'prx3_ballot',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'no_found_rows'  => true,
				'meta_key'       => '_prx3_state', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded feed of open ballots.
				'meta_value'     => 'open', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		) as $ballot ) {
			$entries[] = array(
				'id'    => (int) $ballot->ID,
				'at'    => $ballot->post_date ?? prx3_now(),
				'actor' => 0,
				'text'  => sprintf( /* translators: %s title. */ __( 'Voting open: %s', 'fan-ownership' ), $ballot->post_title ),
				'link'  => get_permalink( $ballot->ID ),
			);
		}
		foreach ( array( 'prx3_decision', 'prx3_video' ) as $type ) {
			foreach ( get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => 'publish',
					'posts_per_page' => 10,
					'no_found_rows'  => true,
				)
			) as $post ) {
				$entries[] = array(
					'id'    => (int) $post->ID,
					'at'    => $post->post_date ?? prx3_now(),
					'actor' => 0,
					'text'  => ( 'prx3_decision' === $type ? __( 'Decision recorded: ', 'fan-ownership' ) : __( 'New video: ', 'fan-ownership' ) ) . $post->post_title,
					'link'  => get_permalink( $post->ID ),
				);
			}
		}
		if ( $only_actors ) {
			$entries = array_values( array_filter( $entries, fn( $entry ) => in_array( (int) $entry['actor'], $only_actors, true ) ) );
		}
		usort( $entries, fn( $x, $y ) => strcmp( (string) $y['at'], (string) $x['at'] ) );
		return array_slice( $entries, 0, $limit );
	}

	/* ---------------- Member surfaces ---------------- */

	/**
	 * [prx3_activity] — the club activity stream.
	 *
	 * @return string Feed HTML.
	 */
	public static function shortcode_activity() {
		if ( ! prx3_is_owner() ) {
			return PRX3_Access::gate_content( '' );
		}
		wp_enqueue_style( 'prx3' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter.
		$mine = isset( $_GET['prx3_following'] );
		$feed = self::feed( 30, $mine ? self::following( get_current_user_id() ) : array() );
		$out  = '<div class="prx3-activity"><h2>' . esc_html__( 'Club activity', 'fan-ownership' ) . '</h2>';
		$out .= '<p><a href="' . esc_url( remove_query_arg( 'prx3_following' ) ) . '">' . esc_html__( 'Everyone', 'fan-ownership' ) . '</a> · <a href="' . esc_url( add_query_arg( 'prx3_following', 1 ) ) . '">' . esc_html__( 'People I follow', 'fan-ownership' ) . '</a></p><ul>';
		foreach ( $feed as $entry ) {
			$actor  = $entry['actor'] ? get_userdata( $entry['actor'] ) : null;
			$cheers = self::cheer_count( $entry['id'] );
			$out   .= '<li>' . esc_html( prx3_format_datetime( $entry['at'] ) ) . ' — ' . ( $actor ? esc_html( $actor->display_name ) . ': ' : '' ) . '<a href="' . esc_url( $entry['link'] ) . '">' . esc_html( $entry['text'] ) . '</a> ';
			$out   .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="prx3-cheer">';
			$out   .= wp_nonce_field( 'prx3_cheer', '_wpnonce', true, false );
			$out   .= '<input type="hidden" name="action" value="prx3_cheer"><input type="hidden" name="post" value="' . esc_attr( (string) $entry['id'] ) . '">';
			$out   .= '<button type="submit" class="prx3-button prx3-cheer-button' . ( self::has_cheered( get_current_user_id(), $entry['id'] ) ? ' is-cheered' : '' ) . '">&#127881; ' . (int) $cheers . '</button></form></li>';
		}
		return $out . '</ul></div>';
	}

	/**
	 * The owner directory, searchable and paginated.
	 *
	 * @param string $search   Free text matched against name, login, slug, and owner number.
	 * @param int    $page     1-based page.
	 * @param int    $per_page Members per page.
	 * @return array{members:array,total:int,pages:int,page:int}
	 */
	public static function directory( $search = '', $page = 1, $per_page = 24 ) {
		$search  = strtolower( trim( (string) $search ) );
		$members = array();
		foreach ( get_users(
			array(
				'role'   => 'fan_owner',
				'number' => -1,
			)
		) as $member ) {
			if ( '' !== $search ) {
				$owner_no = (string) PRX3_Shares::owner_number( $member->ID );
				$haystack = strtolower( implode( ' ', array( (string) $member->display_name, (string) ( $member->user_login ?? '' ), (string) ( $member->user_nicename ?? '' ), $owner_no, '#' . $owner_no ) ) );
				if ( false === strpos( $haystack, $search ) ) {
					continue;
				}
			}
			$members[] = $member;
		}
		$total = count( $members );
		$pages = max( 1, (int) ceil( $total / max( 1, (int) $per_page ) ) );
		$page  = min( max( 1, (int) $page ), $pages );
		return array(
			'members' => array_slice( $members, ( $page - 1 ) * $per_page, $per_page ),
			'total'   => $total,
			'pages'   => $pages,
			'page'    => $page,
		);
	}

	/**
	 * Owners matching a typed @handle prefix, for the mention picker.
	 *
	 * @param string $prefix What the member has typed after the @.
	 * @param int    $limit  Maximum suggestions.
	 * @return array[] Entries: id, handle, name.
	 */
	public static function members_suggest( $prefix, $limit = 8 ) {
		$prefix = strtolower( trim( (string) $prefix ) );
		$out    = array();
		foreach ( get_users(
			array(
				'role'   => 'fan_owner',
				'number' => -1,
			)
		) as $member ) {
			$login = strtolower( (string) ( $member->user_login ?? '' ) );
			$slug  = strtolower( (string) ( $member->user_nicename ?? '' ) );
			$name  = strtolower( (string) $member->display_name );
			if ( '' !== $prefix && 0 !== strpos( $login, $prefix ) && 0 !== strpos( $slug, $prefix ) && 0 !== strpos( $name, $prefix ) ) {
				continue;
			}
			$out[] = array(
				'id'     => (int) $member->ID,
				'handle' => $slug ? $slug : $login,
				'name'   => (string) $member->display_name,
			);
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * [prx3_members] — the searchable, paginated owner directory.
	 *
	 * @return string Directory HTML.
	 */
	public static function shortcode_members() {
		if ( ! prx3_is_owner() ) {
			return PRX3_Access::gate_content( '' );
		}
		wp_enqueue_style( 'prx3' );
		$following = self::following( get_current_user_id() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search and paging.
		$search = isset( $_GET['prx3_q'] ) ? sanitize_text_field( wp_unslash( $_GET['prx3_q'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search and paging.
		$page = isset( $_GET['prx3_pg'] ) ? absint( $_GET['prx3_pg'] ) : 1;
		$dir  = self::directory( $search, $page );
		$out  = '<div class="prx3-members"><h2>' . esc_html__( 'The owners', 'fan-ownership' ) . '</h2>';
		$out .= '<form method="get" class="prx3-members-search"><label class="screen-reader-text" for="prx3_q">' . esc_html__( 'Search owners', 'fan-ownership' ) . '</label>';
		$out .= '<input type="search" id="prx3_q" name="prx3_q" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search by name or owner number…', 'fan-ownership' ) . '"> ';
		$out .= '<button type="submit" class="prx3-button">' . esc_html__( 'Search', 'fan-ownership' ) . '</button></form>';
		$out .= '<p>' . esc_html( sprintf( /* translators: %d owners found. */ _n( '%d owner', '%d owners', $dir['total'], 'fan-ownership' ), $dir['total'] ) ) . '</p><div class="prx3-tiles">';
		foreach ( $dir['members'] as $member ) {
			$badges = class_exists( 'PRX3_Badges' ) ? (array) PRX3_Badges::member_badges( $member->ID ) : array();
			$out   .= '<div class="prx3-tile">';
			$out   .= '<span class="prx3-tile-label">' . esc_html( sprintf( /* translators: %d owner number. */ __( 'Owner #%d', 'fan-ownership' ), PRX3_Shares::owner_number( $member->ID ) ) ) . '</span>';
			$out   .= '<strong class="prx3-tile-value"><a href="' . esc_url( add_query_arg( 'prx3_member', $member->ID, home_url( '/profile/' ) ) ) . '">' . esc_html( $member->display_name ) . '</a></strong>';
			$out   .= '<span class="prx3-tile-note">' . esc_html( $badges ? implode( ', ', array_slice( $badges, 0, 3 ) ) : __( 'Owner', 'fan-ownership' ) ) . '</span>';
			if ( get_current_user_id() !== (int) $member->ID ) {
				$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				$out .= wp_nonce_field( 'prx3_follow', '_wpnonce', true, false );
				$out .= '<input type="hidden" name="action" value="prx3_follow"><input type="hidden" name="target" value="' . esc_attr( (string) $member->ID ) . '">';
				$out .= '<button type="submit" class="prx3-button">' . ( in_array( (int) $member->ID, $following, true ) ? esc_html__( 'Unfollow', 'fan-ownership' ) : esc_html__( 'Follow', 'fan-ownership' ) ) . '</button></form>';
			}
			$out .= '</div>';
		}
		$out .= '</div>';
		if ( $dir['pages'] > 1 ) {
			$out .= '<p class="prx3-members-pages">';
			for ( $i = 1; $i <= $dir['pages']; $i++ ) {
				$out .= $i === $dir['page']
					? '<strong>' . (int) $i . '</strong> '
					: '<a href="' . esc_url(
						add_query_arg(
							array(
								'prx3_q'  => $search,
								'prx3_pg' => $i,
							)
						)
					) . '">' . (int) $i . '</a> ';
			}
			$out .= '</p>';
		}
		return $out . '</div>';
	}

	/**
	 * [prx3_messages] — conversations and the send form.
	 *
	 * @return string Inbox HTML.
	 */
	public static function shortcode_messages() {
		if ( ! prx3_is_owner() ) {
			return PRX3_Access::gate_content( '' );
		}
		wp_enqueue_style( 'prx3' );
		wp_enqueue_script( 'prx3-mentions' );
		$user_id = get_current_user_id();
		$threads = (array) get_user_meta( $user_id, 'prx3_dm_threads', true );
		$out     = '<div class="prx3-messages"><h2>' . esc_html__( 'Private messages', 'fan-ownership' ) . '</h2>';
		if ( $threads ) {
			$out .= '<ul>';
			foreach ( $threads as $room => $thread ) {
				$other = get_userdata( (int) ( $thread['with'] ?? 0 ) );
				if ( ! $other || ! self::can_read_dm( $room, $user_id ) ) {
					continue;
				}
				$messages = PRX3_Chat::fetch( $room, 0, $user_id );
				$last     = is_array( $messages ) && $messages ? end( $messages ) : null;
				$out     .= '<li><strong>' . esc_html( $other->display_name ) . '</strong>' . ( $last ? ' — ' . esc_html( wp_trim_words( (string) $last->body, 12 ) ) : '' ) . '</li>';
			}
			$out .= '</ul>';
		} else {
			$out .= '<p>' . esc_html__( 'No conversations yet.', 'fan-ownership' ) . '</p>';
		}
		$out .= '<h3>' . esc_html__( 'New message', 'fan-ownership' ) . '</h3>';
		$out .= '<form class="prx3-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= wp_nonce_field( 'prx3_send_dm', '_wpnonce', true, false );
		$out .= '<input type="hidden" name="action" value="prx3_send_dm">';
		$out .= '<p><label for="prx3_dm_to">' . esc_html__( 'To (owner number or user ID)', 'fan-ownership' ) . '</label><input type="number" id="prx3_dm_to" name="to" required></p>';
		$out .= '<p><label for="prx3_dm_body">' . esc_html__( 'Message', 'fan-ownership' ) . '</label><textarea id="prx3_dm_body" name="body" rows="3" required></textarea></p>';
		$out .= '<p><button type="submit" class="prx3-button">' . esc_html__( 'Send', 'fan-ownership' ) . '</button></p></form></div>';
		return $out;
	}

	/**
	 * [prx3_notifications] — the on-site notification list.
	 *
	 * @return string Notification HTML.
	 */
	public static function shortcode_notifications() {
		if ( ! prx3_is_owner() ) {
			return PRX3_Access::gate_content( '' );
		}
		wp_enqueue_style( 'prx3' );
		$list = self::notifications( get_current_user_id(), true );
		$out  = '<div class="prx3-notifications"><h2>' . esc_html__( 'Notifications', 'fan-ownership' ) . '</h2>';
		if ( ! $list ) {
			return $out . '<p>' . esc_html__( 'Nothing yet — replies, mentions, and messages land here.', 'fan-ownership' ) . '</p></div>';
		}
		$out .= '<ul>';
		foreach ( $list as $entry ) {
			$out .= '<li' . ( empty( $entry['read'] ) ? ' class="prx3-unread"' : '' ) . '>' . esc_html( prx3_format_datetime( $entry['at'] ) ) . ' — <a href="' . esc_url( $entry['link'] ) . '">' . esc_html( $entry['text'] ) . '</a></li>';
		}
		return $out . '</ul></div>';
	}

	/* ---------------- App API ---------------- */

	/**
	 * Register the social REST routes.
	 */
	public static function routes() {
		$member = array( 'PRX3_Forum', 'member_permission' );
		register_rest_route(
			'prx3/v1',
			'/activity',
			array(
				'methods'             => 'GET',
				'permission_callback' => $member,
				'callback'            => fn( $request ) => rest_ensure_response( array( 'feed' => self::feed( 30, $request->get_param( 'following' ) ? self::following( get_current_user_id() ) : array() ) ) ),
			)
		);
		register_rest_route(
			'prx3/v1',
			'/notifications',
			array(
				'methods'             => 'GET',
				'permission_callback' => $member,
				'callback'            => fn() => rest_ensure_response( array( 'notifications' => self::notifications( get_current_user_id(), true ) ) ),
			)
		);
		register_rest_route(
			'prx3/v1',
			'/members/suggest',
			array(
				'methods'             => 'GET',
				'permission_callback' => $member,
				'callback'            => fn( $request ) => rest_ensure_response( array( 'members' => self::members_suggest( (string) $request->get_param( 'q' ) ) ) ),
			)
		);
		register_rest_route(
			'prx3/v1',
			'/messages/(?P<with>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $member,
					'callback'            => array( __CLASS__, 'route_dm_fetch' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $member,
					'callback'            => array( __CLASS__, 'route_dm_send' ),
				),
			)
		);
	}

	/**
	 * GET /messages/{with} — the conversation with another member.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_dm_fetch( $request ) {
		$user_id = get_current_user_id();
		$room    = self::dm_room_key( $user_id, (int) $request->get_param( 'with' ) );
		if ( ! self::can_read_dm( $room, $user_id ) ) {
			return new WP_Error( 'prx3_dm_private', __( 'Not your conversation.', 'fan-ownership' ), array( 'status' => 403 ) );
		}
		return rest_ensure_response( array( 'messages' => PRX3_Chat::fetch( $room, (int) $request->get_param( 'since' ), $user_id ) ) );
	}

	/**
	 * POST /messages/{with} — send a private message.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_dm_send( $request ) {
		$body   = (array) $request->get_json_params();
		$result = self::send_dm( get_current_user_id(), (int) $request->get_param( 'with' ), sanitize_textarea_field( (string) ( $body['body'] ?? '' ) ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'sent' => true ) );
	}
}
