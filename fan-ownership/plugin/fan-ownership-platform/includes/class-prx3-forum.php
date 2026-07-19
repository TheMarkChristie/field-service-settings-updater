<?php
/**
 * The native club forum (P109): no BuddyPress/bbPress dependency —
 * topics are a platform post type behind the owner gate, replies are
 * WordPress comments, boards are a taxonomy, and the whole thing is
 * wired into the club's machinery:
 *
 *  - Auto-generated threads: every ballot that opens and every match
 *    that is published gets its discussion thread automatically.
 *  - Match chat archived: when a match ends, the live chat transcript
 *    is posted into the match-day thread so the conversation survives.
 *  - Threads convert to ballots: governance staff can turn a thread
 *    into a draft ballot in one action, with provenance both ways.
 *  - Platform rules apply everywhere: owner gate, feature kill switch,
 *    word-filter holds, mute sanctions, audit trail.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Owner forum: gated topics with comment replies, board taxonomy,
 * automated thread generation, chat archiving, and thread-to-ballot
 * conversion (FO-230).
 */
class PRX3_Forum {

	/**
	 * Wire the post type, boards, automation listeners, forms, and API.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_type' ) );
		add_action( 'init', array( __CLASS__, 'seed_boards' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_shortcode( 'prx3_forum', array( __CLASS__, 'shortcode' ) );

		// Automation: club events open their own threads.
		add_action( 'prx3_ballot_opened', array( __CLASS__, 'thread_for_ballot' ) );
		add_action( 'publish_prx3_match', array( __CLASS__, 'thread_for_match' ), 10, 2 );
		add_action( 'prx3_ballot_tick', array( __CLASS__, 'archive_ended_match_chats' ) );

		// Posting rules: kill switch, mutes, word filter.
		add_filter( 'comments_open', array( __CLASS__, 'replies_open' ), 10, 2 );
		add_filter( 'pre_comment_approved', array( __CLASS__, 'moderate_reply' ), 10, 2 );

		add_action( 'admin_post_prx3_forum_new_topic', array( __CLASS__, 'handle_new_topic' ) );
		add_action( 'admin_post_prx3_forum_to_ballot', array( __CLASS__, 'handle_to_ballot' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * The topic post type and the boards taxonomy.
	 */
	public static function register_type() {
		register_post_type(
			'prx3_forum_topic',
			array(
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => 'prx3-fanpress',
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-format-chat',
				'has_archive'     => true,
				'rewrite'         => array( 'slug' => 'owners/forum' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'editor', 'author', 'comments' ),
				'labels'          => array(
					'name'          => __( 'FanPress Chat', 'fan-ownership' ),
					'singular_name' => __( 'Topic', 'fan-ownership' ),
					'all_items'     => __( 'Topics', 'fan-ownership' ),
				),
			)
		);
		register_taxonomy(
			'prx3_forum_board',
			'prx3_forum_topic',
			array(
				'hierarchical' => true,
				'public'       => true,
				'show_in_rest' => false,
				'rewrite'      => array( 'slug' => 'owners/board' ),
				'labels'       => array(
					'name'          => __( 'Boards', 'fan-ownership' ),
					'singular_name' => __( 'Board', 'fan-ownership' ),
				),
			)
		);
	}

	/**
	 * Seed the default boards once.
	 */
	public static function seed_boards() {
		if ( get_option( 'prx3_forum_boards_seeded' ) ) {
			return;
		}
		foreach ( array(
			'general'       => __( 'General', 'fan-ownership' ),
			'match-days'    => __( 'Match Days', 'fan-ownership' ),
			'club-business' => __( 'Club Business & Ballots', 'fan-ownership' ),
			'ideas'         => __( 'Ideas & Suggestions', 'fan-ownership' ),
		) as $slug => $name ) {
			if ( ! term_exists( $slug, 'prx3_forum_board' ) ) {
				wp_insert_term( $name, 'prx3_forum_board', array( 'slug' => $slug ) );
			}
		}
		update_option( 'prx3_forum_boards_seeded', 1, false );
	}

	/**
	 * The FanPress Chat top-level menu: the community home in one place —
	 * topics attach beneath it via show_in_menu, plus boards and the
	 * held-replies moderation queue.
	 */
	public static function menu() {
		add_menu_page(
			__( 'FanPress Chat', 'fan-ownership' ),
			__( 'FanPress Chat', 'fan-ownership' ),
			'edit_posts',
			'prx3-fanpress',
			array( __CLASS__, 'render_home' ),
			'dashicons-format-chat',
			3.1
		);
		add_submenu_page(
			'prx3-fanpress',
			__( 'FanPress Chat', 'fan-ownership' ),
			__( 'Overview', 'fan-ownership' ),
			'edit_posts',
			'prx3-fanpress',
			array( __CLASS__, 'render_home' )
		);
		add_submenu_page(
			'prx3-fanpress',
			__( 'Boards', 'fan-ownership' ),
			__( 'Boards', 'fan-ownership' ),
			'manage_categories',
			'edit-tags.php?taxonomy=prx3_forum_board&post_type=prx3_forum_topic'
		);
		add_submenu_page(
			'prx3-fanpress',
			__( 'Held Replies', 'fan-ownership' ),
			__( 'Held Replies', 'fan-ownership' ),
			'moderate_comments',
			'edit-comments.php?comment_status=moderated'
		);
	}

	/**
	 * Community counts for the FanPress overview.
	 *
	 * @return array{topics:int,replies:int,automated:int,converted:int}
	 */
	public static function stats() {
		$topics = get_posts(
			array(
				'post_type'   => 'prx3_forum_topic',
				'post_status' => array( 'publish' ),
				'numberposts' => -1,
			)
		);
		$stats  = array(
			'topics'    => count( $topics ),
			'replies'   => 0,
			'automated' => 0,
			'converted' => 0,
		);
		foreach ( $topics as $topic ) {
			$stats['replies'] += (int) get_comments_number( $topic->ID );
			if ( get_post_meta( $topic->ID, '_prx3_source_key', true ) ) {
				++$stats['automated'];
			}
			if ( get_post_meta( $topic->ID, '_prx3_ballot_id', true ) ) {
				++$stats['converted'];
			}
		}
		return $stats;
	}

	/**
	 * The FanPress Chat overview screen.
	 */
	public static function render_home() {
		$stats = self::stats();
		$tiles = array(
			__( 'Topics', 'fan-ownership' )               => (string) $stats['topics'],
			__( 'Replies', 'fan-ownership' )              => (string) $stats['replies'],
			__( 'Automated threads', 'fan-ownership' )    => (string) $stats['automated'],
			__( 'Converted to ballots', 'fan-ownership' ) => (string) $stats['converted'],
		);
		echo '<div class="wrap"><h1>' . esc_html__( 'FanPress Chat', 'fan-ownership' ) . '</h1>';
		echo '<p>' . esc_html__( 'The club community in one place: forum topics and boards, the activity feed, follows, private messages, notifications, and @mentions — built into the platform, no third-party forum plugin.', 'fan-ownership' ) . '</p>';
		echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0;">';
		foreach ( $tiles as $label => $value ) {
			echo '<div style="border:1px solid #ccd0d4;background:#fff;padding:16px 24px;min-width:140px;"><div style="font-size:28px;font-weight:600;">' . esc_html( $value ) . '</div><div>' . esc_html( $label ) . '</div></div>';
		}
		echo '</div>';
		echo '<h2>' . esc_html__( 'Latest topics', 'fan-ownership' ) . '</h2><ul>';
		$latest = get_posts(
			array(
				'post_type'   => 'prx3_forum_topic',
				'post_status' => array( 'publish' ),
				'numberposts' => 10,
			)
		);
		if ( ! $latest ) {
			echo '<li>' . esc_html__( 'No topics yet — ballots and matches will open their own threads automatically.', 'fan-ownership' ) . '</li>';
		}
		foreach ( $latest as $topic ) {
			echo '<li><a href="' . esc_url( get_edit_post_link( $topic->ID ) ) . '">' . esc_html( get_the_title( $topic->ID ) ) . '</a> — ' . (int) get_comments_number( $topic->ID ) . ' ' . esc_html__( 'replies', 'fan-ownership' ) . '</li>';
		}
		echo '</ul>';
		echo '<p>' . esc_html__( 'Member-facing screens: put the shortcodes prx3_forum, prx3_activity, prx3_members, prx3_messages, and prx3_notifications on owner-gated pages. Word-filter holds land in Held Replies; mutes and the forum kill switch are in Settings.', 'fan-ownership' ) . '</p></div>';
	}

	/* ---------------- Automation: threads from club events ---------------- */

	/**
	 * Create (or return) an automated thread keyed to a source object,
	 * so retries and re-fires never duplicate it.
	 *
	 * @param string $source_key Unique key, e.g. 'ballot-12'.
	 * @param string $title      Thread title.
	 * @param string $content    Thread body HTML.
	 * @param string $board_slug Board term slug.
	 * @return int Topic post ID.
	 */
	public static function auto_thread( $source_key, $title, $content, $board_slug ) {
		$existing = get_posts(
			array(
				'post_type'      => 'prx3_forum_topic',
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_prx3_source_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- idempotency lookup for automated threads.
				'meta_value'     => $source_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $existing ) {
			return (int) ( is_object( $existing[0] ) ? $existing[0]->ID : $existing[0] );
		}
		$topic_id = wp_insert_post(
			array(
				'post_type'    => 'prx3_forum_topic',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => wp_kses_post( $content ),
			)
		);
		if ( is_wp_error( $topic_id ) || ! $topic_id ) {
			return 0;
		}
		update_post_meta( $topic_id, '_prx3_source_key', $source_key );
		if ( taxonomy_exists( 'prx3_forum_board' ) ) {
			wp_set_object_terms( $topic_id, $board_slug, 'prx3_forum_board' );
		}
		PRX3_Audit::log( 'forum_auto_thread', sprintf( 'Automated thread #%d created (%s)', $topic_id, $source_key ) );
		return (int) $topic_id;
	}

	/**
	 * A ballot opened — open its discussion thread.
	 *
	 * @param int $ballot_id Ballot post ID.
	 */
	public static function thread_for_ballot( $ballot_id ) {
		$title = get_the_title( $ballot_id );
		self::auto_thread(
			'ballot-' . (int) $ballot_id,
			sprintf( /* translators: %s ballot title. */ __( 'Discussion: %s', 'fan-ownership' ), $title ),
			sprintf(
				/* translators: 1: URL, 2: title. */
				'<p>' . __( 'Voting is open on <a href="%1$s">%2$s</a>. Make the case for your side here — then cast your vote. Your ballot stays secret; the debate does not have to be.', 'fan-ownership' ) . '</p>',
				esc_url( get_permalink( $ballot_id ) ),
				esc_html( $title )
			),
			'club-business'
		);
	}

	/**
	 * A match was published — open its match-day thread.
	 *
	 * @param int     $post_id Match post ID.
	 * @param WP_Post $post    Match post (unused).
	 */
	public static function thread_for_match( $post_id, $post = null ) {
		$opponent = (string) get_post_meta( $post_id, '_prx3_opponent', true );
		$title    = get_the_title( $post_id );
		$topic_id = self::auto_thread(
			'match-' . (int) $post_id,
			sprintf( /* translators: %s match title. */ __( 'Match day: %s', 'fan-ownership' ), $opponent ? $title . ' — ' . $opponent : $title ),
			sprintf(
				/* translators: %s URL. */
				'<p>' . __( 'The match-day thread. Build-up, line-up reactions, and the post-mortem all live here. Watch live in the <a href="%s">Match Centre</a> — the live chat transcript is archived into this thread after the final buzzer.', 'fan-ownership' ) . '</p>',
				esc_url( get_permalink( $post_id ) )
			),
			'match-days'
		);
		if ( $topic_id ) {
			update_post_meta( $post_id, '_prx3_forum_topic', $topic_id );
		}
	}

	/**
	 * Sweep for ended matches whose chat has not been archived yet and
	 * post the transcript into the match-day thread.
	 */
	public static function archive_ended_match_chats() {
		$matches = get_posts(
			array(
				'post_type'      => 'prx3_match',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded sweep (10 matches) on the 5-minute tick.
					array(
						'key'   => '_prx3_ended',
						'value' => '1',
					),
					array(
						'key'     => '_prx3_chat_archived',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		foreach ( $matches as $match ) {
			$match_id = is_object( $match ) ? $match->ID : (int) $match;
			self::archive_match_chat( $match_id );
		}
	}

	/**
	 * Archive one match's chat transcript into its thread.
	 *
	 * @param int $match_id Match post ID.
	 */
	public static function archive_match_chat( $match_id ) {
		update_post_meta( $match_id, '_prx3_chat_archived', time() );
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off transcript read from the plugin's own chat table.
			$wpdb->prepare( "SELECT user_id, body, created_at FROM {$wpdb->prefix}prx3_chat_messages WHERE room = %s AND held = 0 AND removed = 0 ORDER BY id ASC LIMIT 2000", 'match-' . $match_id ),
			ARRAY_A
		);
		if ( ! $rows ) {
			return;
		}
		$topic_id = (int) get_post_meta( $match_id, '_prx3_forum_topic', true );
		if ( ! $topic_id ) {
			$topic_id = self::auto_thread(
				'match-' . $match_id,
				sprintf( /* translators: %s match. */ __( 'Match day: %s', 'fan-ownership' ), get_the_title( $match_id ) ),
				'',
				'match-days'
			);
			update_post_meta( $match_id, '_prx3_forum_topic', $topic_id );
		}
		wp_insert_comment(
			array(
				'comment_post_ID'      => $topic_id,
				'comment_author'       => __( 'Match Centre', 'fan-ownership' ),
				'comment_author_email' => '',
				'comment_content'      => self::digest_from_rows( $rows ),
				'comment_approved'     => 1,
				'user_id'              => 0,
			)
		);
		PRX3_Audit::log( 'forum_chat_archived', sprintf( 'Match %d chat archived to thread %d (%d messages)', $match_id, $topic_id, count( $rows ) ) );
	}

	/**
	 * Build the archived transcript text from chat rows.
	 *
	 * @param array[] $rows Chat rows: user_id, body, created_at.
	 * @return string Transcript digest.
	 */
	public static function digest_from_rows( $rows ) {
		$lines = array();
		foreach ( $rows as $row ) {
			$user    = get_userdata( (int) $row['user_id'] );
			$name    = $user ? $user->display_name : __( 'Former member', 'fan-ownership' );
			$stamp   = gmdate( 'H:i', strtotime( (string) $row['created_at'] ) );
			$lines[] = $stamp . ' ' . $name . ': ' . wp_strip_all_tags( (string) $row['body'] );
		}
		return sprintf( /* translators: %d message count. */ __( "Live chat transcript (%d messages):\n\n", 'fan-ownership' ), count( $lines ) ) . implode( "\n", $lines );
	}

	/* ---------------- Threads become ballots ---------------- */

	/**
	 * Convert a thread into a draft ballot with provenance both ways.
	 * Governance staff review, add options, and schedule it as normal —
	 * the second-approval and lifecycle rules are untouched.
	 *
	 * @param int $topic_id Topic post ID.
	 * @param int $user_id  Acting user.
	 * @return int|WP_Error Draft ballot ID.
	 */
	public static function convert_to_ballot( $topic_id, $user_id ) {
		if ( 'prx3_forum_topic' !== get_post_type( $topic_id ) ) {
			return new WP_Error( 'prx3_not_topic', __( 'Only forum topics can convert to ballots.', 'fan-ownership' ) );
		}
		$existing = (int) get_post_meta( $topic_id, '_prx3_ballot_id', true );
		if ( $existing ) {
			return $existing;
		}
		$topic     = get_post( $topic_id );
		$ballot_id = wp_insert_post(
			array(
				'post_type'    => 'prx3_ballot',
				'post_status'  => 'draft',
				'post_title'   => $topic ? $topic->post_title : '',
				'post_content' => ( $topic ? $topic->post_content : '' ) . sprintf(
					/* translators: %s URL. */
					'<p><em>' . __( 'Raised by the owners on the forum: <a href="%s">read the discussion</a>.', 'fan-ownership' ) . '</em></p>',
					esc_url( get_permalink( $topic_id ) )
				),
			)
		);
		if ( is_wp_error( $ballot_id ) || ! $ballot_id ) {
			return is_wp_error( $ballot_id ) ? $ballot_id : new WP_Error( 'prx3_convert_failed', __( 'Could not create the draft ballot.', 'fan-ownership' ) );
		}
		update_post_meta( $ballot_id, '_prx3_from_topic', $topic_id );
		update_post_meta( $topic_id, '_prx3_ballot_id', $ballot_id );
		PRX3_Audit::log( 'forum_to_ballot', sprintf( 'Topic %d converted to draft ballot %d by user %d', $topic_id, $ballot_id, $user_id ) );
		return (int) $ballot_id;
	}

	/**
	 * Governance action from the thread page.
	 */
	public static function handle_to_ballot() {
		if ( ! current_user_can( 'prx3_governance' ) && ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Governance staff only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_forum_to_ballot' );
		$topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
		$result   = self::convert_to_ballot( $topic_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		wp_safe_redirect( get_edit_post_link( $result, 'raw' ) );
		exit;
	}

	/* ---------------- Posting rules ---------------- */

	/**
	 * Replies obey the kill switch and mute sanctions.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post being commented on.
	 * @return bool Filtered openness.
	 */
	public static function replies_open( $open, $post_id ) {
		if ( 'prx3_forum_topic' !== get_post_type( $post_id ) ) {
			return $open;
		}
		if ( ! prx3_feature_on( 'forum' ) || ! prx3_is_owner() ) {
			return false;
		}
		if ( class_exists( 'PRX3_Moderation' ) && PRX3_Moderation::is_muted( get_current_user_id() ) ) {
			return false;
		}
		return $open;
	}

	/**
	 * Word-filter hits on forum replies are held for moderation.
	 *
	 * @param int|string $approved    Current approval status.
	 * @param array      $commentdata Comment payload.
	 * @return int|string Filtered status.
	 */
	public static function moderate_reply( $approved, $commentdata ) {
		$post_id = (int) ( $commentdata['comment_post_ID'] ?? 0 );
		if ( 'prx3_forum_topic' !== get_post_type( $post_id ) ) {
			return $approved;
		}
		if ( class_exists( 'PRX3_Chat' ) && PRX3_Chat::hits_word_filter( (string) ( $commentdata['comment_content'] ?? '' ) ) ) {
			return 0;
		}
		return $approved;
	}

	/* ---------------- Member surfaces ---------------- */

	/**
	 * [prx3_forum] — boards, recent topics, and the new-topic form.
	 *
	 * @return string Forum HTML.
	 */
	public static function shortcode() {
		if ( ! prx3_feature_on( 'forum' ) ) {
			return PRX3_Config::unavailable_notice( 'forum' );
		}
		if ( ! prx3_is_owner() ) {
			return PRX3_Access::gate_content( '' );
		}
		wp_enqueue_style( 'prx3' );
		$out    = '<div class="prx3-forum"><h2>' . esc_html__( 'FanPress Chat', 'fan-ownership' ) . '</h2>';
		$boards = get_terms(
			array(
				'taxonomy'   => 'prx3_forum_board',
				'hide_empty' => false,
			)
		);
		if ( $boards && ! is_wp_error( $boards ) ) {
			$out .= '<p>';
			foreach ( $boards as $board ) {
				$out .= '<a class="prx3-badge" href="' . esc_url( get_term_link( $board ) ) . '">' . esc_html( $board->name ) . ' (' . (int) $board->count . ')</a> ';
			}
			$out .= '</p>';
		}
		$topics = get_posts(
			array(
				'post_type'      => 'prx3_forum_topic',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'no_found_rows'  => true,
			)
		);
		$out   .= '<ul class="prx3-forum-topics">';
		foreach ( $topics as $topic ) {
			$replies = (int) get_comments_number( $topic->ID );
			$out    .= '<li><a href="' . esc_url( get_permalink( $topic->ID ) ) . '">' . esc_html( $topic->post_title ) . '</a> — ' . esc_html( sprintf( /* translators: %d replies. */ _n( '%d reply', '%d replies', $replies, 'fan-ownership' ), $replies ) ) . '</li>';
		}
		$out .= '</ul>';

		$muted = class_exists( 'PRX3_Moderation' ) && PRX3_Moderation::is_muted( get_current_user_id() );
		if ( $muted ) {
			$out .= '<p>' . esc_html__( 'Your posting rights are currently suspended.', 'fan-ownership' ) . '</p>';
		} else {
			$out .= '<h3>' . esc_html__( 'Start a topic', 'fan-ownership' ) . '</h3>';
			$out .= '<form class="prx3-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$out .= wp_nonce_field( 'prx3_forum_new_topic', '_wpnonce', true, false );
			$out .= '<input type="hidden" name="action" value="prx3_forum_new_topic">';
			$out .= '<p><label for="prx3_topic_title">' . esc_html__( 'Title', 'fan-ownership' ) . '</label><input type="text" id="prx3_topic_title" name="topic_title" maxlength="140" required></p>';
			$out .= '<p><label for="prx3_topic_body">' . esc_html__( 'Your post', 'fan-ownership' ) . '</label><textarea id="prx3_topic_body" name="topic_body" rows="5" required></textarea></p>';
			if ( $boards && ! is_wp_error( $boards ) ) {
				$out .= '<p><label for="prx3_topic_board">' . esc_html__( 'Board', 'fan-ownership' ) . '</label><select id="prx3_topic_board" name="topic_board">';
				foreach ( $boards as $board ) {
					$out .= '<option value="' . esc_attr( $board->slug ) . '">' . esc_html( $board->name ) . '</option>';
				}
				$out .= '</select></p>';
			}
			$out .= '<p><button type="submit" class="prx3-button">' . esc_html__( 'Post topic', 'fan-ownership' ) . '</button></p></form>';
		}

		if ( current_user_can( 'prx3_governance' ) || current_user_can( 'prx3_admin' ) ) {
			$out .= '<p class="description">' . esc_html__( 'Governance staff: open any topic in the admin to convert it into a draft ballot.', 'fan-ownership' ) . '</p>';
		}
		return $out . '</div>';
	}

	/**
	 * Create a topic from the front-end form: gated, filtered, audited.
	 */
	public static function handle_new_topic() {
		if ( ! is_user_logged_in() || ! prx3_is_owner() ) {
			wp_die( esc_html__( 'Owners only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_forum_new_topic' );
		if ( ! prx3_feature_on( 'forum' ) ) {
			wp_die( esc_html__( 'The forum is temporarily unavailable.', 'fan-ownership' ) );
		}
		if ( class_exists( 'PRX3_Moderation' ) && PRX3_Moderation::is_muted( get_current_user_id() ) ) {
			wp_die( esc_html__( 'Your posting rights are currently suspended.', 'fan-ownership' ) );
		}
		$title = isset( $_POST['topic_title'] ) ? sanitize_text_field( wp_unslash( $_POST['topic_title'] ) ) : '';
		$body  = isset( $_POST['topic_body'] ) ? wp_kses_post( wp_unslash( $_POST['topic_body'] ) ) : '';
		$board = isset( $_POST['topic_board'] ) ? sanitize_key( $_POST['topic_board'] ) : 'general';
		if ( '' === $title || '' === $body ) {
			wp_die( esc_html__( 'A title and a post are both required.', 'fan-ownership' ) );
		}
		$held     = class_exists( 'PRX3_Chat' ) && PRX3_Chat::hits_word_filter( $title . ' ' . $body );
		$topic_id = wp_insert_post(
			array(
				'post_type'    => 'prx3_forum_topic',
				'post_status'  => $held ? 'pending' : 'publish',
				'post_title'   => $title,
				'post_content' => $body,
				'post_author'  => get_current_user_id(),
			)
		);
		if ( $topic_id && ! is_wp_error( $topic_id ) && taxonomy_exists( 'prx3_forum_board' ) ) {
			wp_set_object_terms( $topic_id, $board, 'prx3_forum_board' );
		}
		prx3_touch_activity( get_current_user_id() );
		$destination = $held ? add_query_arg( 'prx3_error', rawurlencode( __( 'Your topic is held for moderation and will appear once reviewed.', 'fan-ownership' ) ), wp_get_referer() ) : get_permalink( $topic_id );
		wp_safe_redirect( $destination ? $destination : home_url() );
		exit;
	}

	/* ---------------- App API (FO-302 parity) ---------------- */

	/**
	 * Register the forum REST routes.
	 */
	public static function routes() {
		register_rest_route(
			'prx3/v1',
			'/forum/topics',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( __CLASS__, 'member_permission' ),
					'callback'            => array( __CLASS__, 'route_topics' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( __CLASS__, 'member_permission' ),
					'callback'            => array( __CLASS__, 'route_create_topic' ),
				),
			)
		);
		register_rest_route(
			'prx3/v1',
			'/forum/topics/(?P<id>\d+)/replies',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( __CLASS__, 'member_permission' ),
					'callback'            => array( __CLASS__, 'route_replies' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( __CLASS__, 'member_permission' ),
					'callback'            => array( __CLASS__, 'route_create_reply' ),
				),
			)
		);
	}

	/**
	 * Members only, forum switched on.
	 *
	 * @return true|WP_Error
	 */
	public static function member_permission() {
		if ( ! prx3_feature_on( 'forum' ) ) {
			return new WP_Error( 'prx3_off', __( 'The forum is temporarily unavailable.', 'fan-ownership' ), array( 'status' => 503 ) );
		}
		if ( ! prx3_is_owner() ) {
			return new WP_Error( 'prx3_members', __( 'Owners only.', 'fan-ownership' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * GET /forum/topics — recent topics, optionally by board.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|array
	 */
	public static function route_topics( $request ) {
		$args  = array(
			'post_type'      => 'prx3_forum_topic',
			'post_status'    => 'publish',
			'posts_per_page' => min( 50, max( 1, (int) ( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 20 ) ) ),
			'paged'          => max( 1, (int) $request->get_param( 'page' ) ),
			'no_found_rows'  => true,
		);
		$board = sanitize_key( (string) $request->get_param( 'board' ) );
		if ( $board ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded member feed by board.
				array(
					'taxonomy' => 'prx3_forum_board',
					'field'    => 'slug',
					'terms'    => $board,
				),
			);
		}
		$topics = array();
		foreach ( get_posts( $args ) as $topic ) {
			$topics[] = array(
				'id'      => $topic->ID,
				'title'   => $topic->post_title,
				'replies' => (int) get_comments_number( $topic->ID ),
				'link'    => get_permalink( $topic->ID ),
				'ballot'  => (int) get_post_meta( $topic->ID, '_prx3_ballot_id', true ),
			);
		}
		return rest_ensure_response( array( 'topics' => $topics ) );
	}

	/**
	 * POST /forum/topics — create a topic from the app.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_create_topic( $request ) {
		if ( class_exists( 'PRX3_Moderation' ) && PRX3_Moderation::is_muted( get_current_user_id() ) ) {
			return new WP_Error( 'prx3_muted', __( 'Your posting rights are currently suspended.', 'fan-ownership' ), array( 'status' => 403 ) );
		}
		$body  = (array) $request->get_json_params();
		$title = sanitize_text_field( (string) ( $body['title'] ?? '' ) );
		$text  = wp_kses_post( (string) ( $body['body'] ?? '' ) );
		if ( '' === $title || '' === $text ) {
			return new WP_Error( 'prx3_forum_empty', __( 'A title and a post are both required.', 'fan-ownership' ), array( 'status' => 400 ) );
		}
		$held     = class_exists( 'PRX3_Chat' ) && PRX3_Chat::hits_word_filter( $title . ' ' . $text );
		$topic_id = wp_insert_post(
			array(
				'post_type'    => 'prx3_forum_topic',
				'post_status'  => $held ? 'pending' : 'publish',
				'post_title'   => $title,
				'post_content' => $text,
				'post_author'  => get_current_user_id(),
			)
		);
		if ( $topic_id && ! is_wp_error( $topic_id ) && taxonomy_exists( 'prx3_forum_board' ) ) {
			wp_set_object_terms( $topic_id, sanitize_key( (string) ( $body['board'] ?? 'general' ) ), 'prx3_forum_board' );
		}
		return rest_ensure_response(
			array(
				'id'   => (int) $topic_id,
				'held' => $held,
			)
		);
	}

	/**
	 * GET /forum/topics/{id}/replies — approved replies, oldest first.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|array
	 */
	public static function route_replies( $request ) {
		$topic_id = (int) $request->get_param( 'id' );
		$replies  = array();
		foreach ( get_comments(
			array(
				'post_id' => $topic_id,
				'status'  => 'approve',
				'order'   => 'ASC',
			)
		) as $comment ) {
			$replies[] = array(
				'id'     => (int) $comment->comment_ID,
				'author' => $comment->comment_author,
				'body'   => $comment->comment_content,
				'at'     => $comment->comment_date_gmt,
			);
		}
		return rest_ensure_response( array( 'replies' => $replies ) );
	}

	/**
	 * POST /forum/topics/{id}/replies — reply from the app.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_create_reply( $request ) {
		if ( class_exists( 'PRX3_Moderation' ) && PRX3_Moderation::is_muted( get_current_user_id() ) ) {
			return new WP_Error( 'prx3_muted', __( 'Your posting rights are currently suspended.', 'fan-ownership' ), array( 'status' => 403 ) );
		}
		$topic_id = (int) $request->get_param( 'id' );
		if ( 'prx3_forum_topic' !== get_post_type( $topic_id ) ) {
			return new WP_Error( 'prx3_not_topic', __( 'Topic not found.', 'fan-ownership' ), array( 'status' => 404 ) );
		}
		$body = (array) $request->get_json_params();
		$text = wp_kses_post( (string) ( $body['body'] ?? '' ) );
		if ( '' === $text ) {
			return new WP_Error( 'prx3_forum_empty', __( 'A reply is required.', 'fan-ownership' ), array( 'status' => 400 ) );
		}
		$user       = wp_get_current_user();
		$held       = class_exists( 'PRX3_Chat' ) && PRX3_Chat::hits_word_filter( $text );
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => $topic_id,
				'comment_author'   => $user->display_name,
				'comment_content'  => $text,
				'comment_approved' => $held ? 0 : 1,
				'user_id'          => $user->ID,
			)
		);
		return rest_ensure_response(
			array(
				'id'   => (int) $comment_id,
				'held' => $held,
			)
		);
	}
}
