<?php
/**
 * Native forum (P109/FO-230): automated threads, idempotency, chat
 * transcript digests, and thread-to-ballot conversion.
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();

// Automated threads are idempotent per source.
$first  = PRX3_Forum::auto_thread( 'ballot-77', 'Discussion: New kit', '<p>Talk here.</p>', 'club-business' );
$second = PRX3_Forum::auto_thread( 'ballot-77', 'Discussion: New kit', '<p>Talk here.</p>', 'club-business' );
t_ok( $first > 0, 'Automated thread created' );
t_eq( $second, $first, 'Re-firing the event never duplicates the thread' );

// A ballot opening creates its discussion thread.
$ballot = wp_insert_post( array( 'post_type' => 'prx3_ballot', 'post_status' => 'publish', 'post_title' => 'Third kit colour', 'post_content' => '' ) );
PRX3_Forum::thread_for_ballot( $ballot );
$threads = get_posts( array( 'post_type' => 'prx3_forum_topic', 'post_status' => array( 'publish' ), 'meta_key' => '_prx3_source_key', 'meta_value' => 'ballot-' . $ballot ) );
t_eq( count( $threads ), 1, 'Ballot open spawns exactly one thread' );

// Chat digest formatting.
prx3_test_user( 30, array( 'display_name' => 'Shona' ) );
$digest = PRX3_Forum::digest_from_rows(
	array(
		array( 'user_id' => 30, 'body' => 'GOAL!!!', 'created_at' => '2026-07-18 19:04:00' ),
		array( 'user_id' => 999, 'body' => 'What a save', 'created_at' => '2026-07-18 19:06:00' ),
	)
);
t_ok( false !== strpos( $digest, '19:04 Shona: GOAL!!!' ), 'Digest lines carry time, name, and message' );
t_ok( false !== strpos( $digest, 'Former member' ), 'Erased members are anonymised in the transcript' );

// Thread converts to a draft ballot with provenance both ways.
$topic = wp_insert_post( array( 'post_type' => 'prx3_forum_topic', 'post_status' => 'publish', 'post_title' => 'Safe standing section', 'post_content' => '<p>We should trial it.</p>' ) );
$draft = PRX3_Forum::convert_to_ballot( $topic, 5 );
t_ok( ! is_wp_error( $draft ) && $draft > 0, 'Thread converts to a draft ballot' );
t_eq( get_post_type( $draft ), 'prx3_ballot', 'Converted post is a ballot' );
t_eq( (int) get_post_meta( $draft, '_prx3_from_topic', true ), $topic, 'Ballot records its origin thread' );
t_eq( (int) get_post_meta( $topic, '_prx3_ballot_id', true ), $draft, 'Thread records its ballot' );
t_eq( PRX3_Forum::convert_to_ballot( $topic, 5 ), $draft, 'Converting twice returns the same ballot' );
$non_topic = PRX3_Forum::convert_to_ballot( $ballot, 5 );
t_error_code( $non_topic, 'prx3_not_topic', 'Only topics convert' );

// FanPress Chat overview stats count topics, replies, and provenance.
$before = PRX3_Forum::stats();
$plain  = wp_insert_post( array( 'post_type' => 'prx3_forum_topic', 'post_status' => 'publish', 'post_title' => 'Away travel', 'post_content' => '' ) );
wp_insert_comment( array( 'comment_post_ID' => $plain, 'comment_content' => 'Bus from Perth?', 'user_id' => 30 ) );
wp_insert_comment( array( 'comment_post_ID' => $plain, 'comment_content' => 'Count me in.', 'user_id' => 30 ) );
PRX3_Forum::auto_thread( 'ballot-stats-1', 'Discussion: Stats', '<p>Talk.</p>', 'club-business' );
$after = PRX3_Forum::stats();
t_eq( $after['topics'] - $before['topics'], 2, 'Stats count new topics' );
t_eq( $after['replies'] - $before['replies'], 2, 'Stats count replies' );
t_eq( $after['automated'] - $before['automated'], 1, 'Stats count automated threads' );

// WhatsApp-style chat (FO-234): bubble roles, unread badges, colours,
// and the automated-chat lookup used by match/ballot embeds.
prx3_test_reset();
prx3_test_user( 40, array( 'display_name' => 'Viewer' ) );
prx3_test_user( 41, array( 'display_name' => 'Another Owner' ) );
prx3_test_user( 42, array( 'display_name' => 'Board Betty' ) );
$GLOBALS['prx3_t']['caps'][42]['prx3_board'] = true;
t_eq( PRX3_Forum::bubble_role( 40, 40 ), 'mine', 'Own messages sit in the right-hand bubble' );
t_eq( PRX3_Forum::bubble_role( 41, 40 ), 'theirs', "Other owners' messages sit on the left" );
t_eq( PRX3_Forum::bubble_role( 42, 40 ), 'board', 'Board members get the board bubble' );
t_eq( PRX3_Forum::bubble_role( 42, 42 ), 'mine', 'A board member sees their own messages as theirs-on-the-right' );

// Colours: defaults, then configured overrides, invalid hex ignored.
$colors = PRX3_Forum::chat_colors();
t_eq( $colors['mine'], '#dcf8c6', 'My bubble defaults to WhatsApp green' );
update_option( 'prx3_settings', array( 'chat_color_mine' => '#123456', 'chat_color_board' => 'not-a-colour' ) );
$colors = PRX3_Forum::chat_colors();
t_eq( $colors['mine'], '#123456', 'My bubble colour is configurable in Settings' );
t_eq( $colors['board'], '#fff3cd', 'An invalid board colour falls back to the default' );

// Unread badges: new replies count until the thread is opened.
$chat = wp_insert_post( array( 'post_type' => 'prx3_forum_topic', 'post_status' => 'publish', 'post_title' => 'Unread test', 'post_content' => 'Hello' ) );
wp_insert_comment( array( 'comment_post_ID' => $chat, 'comment_content' => 'One', 'comment_approved' => 1, 'user_id' => 41 ) );
wp_insert_comment( array( 'comment_post_ID' => $chat, 'comment_content' => 'Two', 'comment_approved' => 1, 'user_id' => 42 ) );
t_eq( PRX3_Forum::unread_replies( 40, $chat ), 2, 'Unopened chat shows every reply as unread' );
PRX3_Forum::mark_thread_read( 40, $chat );
t_eq( PRX3_Forum::unread_replies( 40, $chat ), 0, 'Opening the chat clears the badge' );
wp_insert_comment( array( 'comment_post_ID' => $chat, 'comment_content' => 'Three', 'comment_approved' => 1, 'user_id' => 41 ) );
t_eq( PRX3_Forum::unread_replies( 40, $chat ), 1, 'New messages bring the badge back' );

// Held replies never appear in the visible thread.
wp_insert_comment( array( 'comment_post_ID' => $chat, 'comment_content' => 'held one', 'comment_approved' => 0, 'user_id' => 41 ) );
t_eq( count( PRX3_Forum::replies_for( $chat ) ), 3, 'Held messages stay out of the conversation' );

// Automated chats are findable for the match/ballot page embed.
$auto = PRX3_Forum::auto_thread( 'match-77', 'Match day: Embed test', '<p>Chat here.</p>', 'match-days' );
t_eq( PRX3_Forum::topic_for_source( 'match-77' ), $auto, 'A match finds its own chat by source key' );
t_eq( PRX3_Forum::topic_for_source( 'match-9999' ), 0, 'No chat means no embed' );
t_eq( PRX3_Forum::unread_replies( 40, $chat ), 1, 'Held messages never inflate the unread badge' );
