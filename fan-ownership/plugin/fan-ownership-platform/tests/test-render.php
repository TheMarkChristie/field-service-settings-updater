<?php
/**
 * Render smoke tests (FO-132): every member surface is executed with a
 * seeded club and must produce real content for an owner and the join
 * gate for a stranger. If a page can render empty, this file fails.
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();
$GLOBALS['prx3_t_posts']    = array();
$GLOBALS['prx3_t_comments'] = array();

// A seeded club: owner, board member, an open ballot with rich content,
// a chat with replies, and a decision/video for the feed.
$viewer = prx3_test_user( 60, array( 'display_name' => 'Aileen Munro', 'user_login' => 'aileen', 'user_nicename' => 'aileen' ) );
$boardm = prx3_test_user( 61, array( 'display_name' => 'Morag Sinclair' ) );
$other  = prx3_test_user( 62, array( 'display_name' => 'Stuart McRae' ) );
$GLOBALS['prx3_t']['caps'][60]['prx3_member'] = true;
$GLOBALS['prx3_t']['caps'][61]['prx3_member'] = true;
$GLOBALS['prx3_t']['caps'][61]['prx3_board']  = true;
$GLOBALS['prx3_t']['caps'][62]['prx3_member'] = true;
$GLOBALS['prx3_t']['current']                 = 60;

$ballot = wp_insert_post( array( 'post_type' => 'prx3_ballot', 'post_status' => 'publish', 'post_title' => 'Third kit', 'post_content' => '<p>Vote on the look.</p><img src="https://example.test/kit.jpg">' ) );
update_post_meta( $ballot, '_prx3_options', array( 'Gold', 'Black' ) );
update_post_meta( $ballot, '_prx3_option_descs', array( 'The Golden Cat concept.', '' ) );
update_post_meta( $ballot, '_prx3_state', 'open' );
update_post_meta( $ballot, '_prx3_closes', '2026-07-26 20:00' );
update_post_meta( $ballot, '_prx3_ballot_no', 7 );

$chat = wp_insert_post( array( 'post_type' => 'prx3_forum_topic', 'post_status' => 'publish', 'post_title' => 'Bus to Dundee', 'post_content' => 'Who is in?', 'post_author' => 62 ) );
wp_insert_comment( array( 'comment_post_ID' => $chat, 'comment_content' => 'Count me in', 'comment_approved' => 1, 'user_id' => 61 ) );
wp_insert_comment( array( 'comment_post_ID' => $chat, 'comment_content' => 'Me too', 'comment_approved' => 1, 'user_id' => 62 ) );

wp_insert_post( array( 'post_type' => 'prx3_decision', 'post_status' => 'publish', 'post_title' => 'Rink hire agreed', 'post_content' => '' ) );
wp_insert_post( array( 'post_type' => 'prx3_video', 'post_status' => 'publish', 'post_title' => 'Highlights v Lynx', 'post_content' => '' ) );

// ---- Ballots list page renders the full voting experience.
$html = PRX3_Shortcodes::ballots();
t_ok( false !== strpos( $html, 'Open ballots' ), 'Ballots page renders its heading' );
t_ok( false !== strpos( $html, 'Third kit' ), 'Ballots page lists the open ballot' );
t_ok( false !== strpos( $html, 'Gold' ) && false !== strpos( $html, 'Black' ), 'Ballots page renders every option' );
t_ok( false !== strpos( $html, 'The Golden Cat concept.' ), 'Option descriptions render under the answers' );
t_ok( false !== strpos( $html, 'kit.jpg' ), 'The rich question (HTML + image) renders in the card' );
t_ok( false !== strpos( $html, 'Ballot #7' ), 'The ballot number badge renders' );
t_ok( false !== strpos( $html, 'prx3_choice' ), 'The vote form renders' );

// ---- The ballot's own page renders the card via single_content.
$GLOBALS['prx3_t_query'] = array( 'singular' => 'prx3_ballot', 'id' => $ballot );
$html = PRX3_Shortcodes::single_content( '<p>Vote on the look.</p>' );
t_ok( false !== strpos( $html, 'prx3_choice' ), 'Ballot permalink renders the voting card' );
t_ok( false !== strpos( $html, 'Gold' ), 'Ballot permalink renders the options' );

// ---- The ballot chat embed joins it (auto thread exists).
PRX3_Forum::thread_for_ballot( $ballot );
$html = PRX3_Forum::embed_chat( '<p>content</p>' );
t_ok( false !== strpos( $html, 'prx3-chat-thread' ), 'Ballot permalink embeds its FanPress chat' );
t_ok( false !== strpos( $html, 'prx3-event-layout' ), 'Embed uses the side-by-side event layout' );
$GLOBALS['prx3_t_query'] = array( 'singular' => '', 'id' => 0 );

// ---- FanPress chat list and thread view.
$html = PRX3_Forum::render_chat_list();
t_ok( false !== strpos( $html, 'Bus to Dundee' ), 'Chat list shows the thread' );
t_ok( false !== strpos( $html, 'prx3-chat-unread' ), 'Chat list shows the unread badge' );
$html = PRX3_Forum::render_thread_view( $chat );
t_ok( false !== strpos( $html, 'prx3-bubble--board' ), 'Board member message gets the board bubble' );
t_ok( false !== strpos( $html, 'prx3-bubble--theirs' ), 'Other owners get the left bubble' );
t_ok( false !== strpos( $html, 'Count me in' ), 'Messages render in the thread' );
t_ok( false !== strpos( $html, 'reply_body' ), 'The compose box renders' );

// ---- Social surfaces.
$html = PRX3_Social::shortcode_activity();
t_ok( false !== strpos( $html, 'Bus to Dundee' ) && false !== strpos( $html, 'Rink hire agreed' ) && false !== strpos( $html, 'Highlights v Lynx' ), 'Activity feed merges chats, decisions, and videos' );
$html = PRX3_Social::shortcode_members();
t_ok( false !== strpos( $html, 'Aileen Munro' ) && false !== strpos( $html, 'Stuart McRae' ), 'Member directory lists owners' );
t_ok( false !== strpos( $html, 'prx3_q' ), 'Directory renders the search box' );
PRX3_Social::notify( 60, 'reply', 'New reply on your topic', 'https://example.test' );
$html = PRX3_Social::shortcode_notifications();
t_ok( false !== strpos( $html, 'New reply on your topic' ), 'Notifications render' );
$html = PRX3_Social::shortcode_messages();
t_ok( false !== strpos( $html, 'prx3_dm_body' ) || false !== strpos( $html, 'Private messages' ), 'Messages page renders' );

// ---- List pages for the other member content types.
foreach ( array(
	'ideas'     => 'prx3_idea',
	'questions' => 'prx3_question',
	'meetings'  => 'prx3_meeting',
	'decisions' => 'prx3_decision',
) as $method => $type ) {
	$html = PRX3_Shortcodes::$method();
	t_ok( is_string( $html ) && '' !== trim( wp_strip_all_tags( $html ) ), "The {$method} page renders content" );
}

// ---- Strangers get the join gate everywhere, never a blank page.
$GLOBALS['prx3_t']['current'] = 0;
foreach ( array(
	array( 'PRX3_Shortcodes', 'ballots' ),
	array( 'PRX3_Forum', 'shortcode' ),
	array( 'PRX3_Social', 'shortcode_activity' ),
	array( 'PRX3_Social', 'shortcode_members' ),
	array( 'PRX3_Social', 'shortcode_messages' ),
	array( 'PRX3_Social', 'shortcode_notifications' ),
) as $surface ) {
	$html = call_user_func( $surface );
	t_ok( is_string( $html ) && '' !== trim( $html ), $surface[1] . ' gives a stranger real output, not blank' );
}
$GLOBALS['prx3_t']['current'] = 60;
