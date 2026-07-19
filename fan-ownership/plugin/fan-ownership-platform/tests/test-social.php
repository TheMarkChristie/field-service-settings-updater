<?php
/**
 * Social layer (P110/FO-231): notifications, @mentions, follows, and
 * the private-message room guard.
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();

// DM room keys are deterministic whichever way round the pair arrives.
t_eq( PRX3_Social::dm_room_key( 9, 4 ), 'dm-4-9', 'Room key orders the pair low-high' );
t_eq( PRX3_Social::dm_room_key( 4, 9 ), PRX3_Social::dm_room_key( 9, 4 ), 'Room key is identical for both directions' );

// Only the two participants may read a DM room.
t_ok( PRX3_Social::can_read_dm( 'dm-4-9', 4 ), 'First participant can read the room' );
t_ok( PRX3_Social::can_read_dm( 'dm-4-9', 9 ), 'Second participant can read the room' );
t_ok( ! PRX3_Social::can_read_dm( 'dm-4-9', 7 ), 'An outsider cannot read the room' );
t_ok( ! PRX3_Social::can_read_dm( 'match-12', 4 ), 'Non-DM rooms never pass the DM guard' );

// @mentions resolve by login or nicename, dedupe, and never notify the author.
prx3_test_user( 21, array( 'user_login' => 'alice', 'user_nicename' => 'alice' ) );
prx3_test_user( 22, array( 'user_login' => 'bob.smith', 'user_nicename' => 'bob-smith' ) );
$targets = PRX3_Social::mention_targets( 'Great point @alice — @bob-smith and @alice should see this. @nobody too.', 22 );
t_eq( $targets, array( 21 ), 'Mentions resolve real members, dedupe, skip the author and unknowns' );
t_eq( PRX3_Social::mention_targets( 'No handles here.' ), array(), 'Text without mentions returns nothing' );

// Notifications: newest first, unread counting, mark-read.
PRX3_Social::notify( 21, 'reply', 'First', 'https://example.test/a' );
PRX3_Social::notify( 21, 'mention', 'Second', 'https://example.test/b' );
$list = PRX3_Social::notifications( 21 );
t_eq( count( $list ), 2, 'Both notifications stored' );
t_eq( $list[0]['text'], 'Second', 'Newest notification first' );
t_eq( PRX3_Social::unread_count( 21 ), 2, 'Both start unread' );
PRX3_Social::notifications( 21, true );
t_eq( PRX3_Social::unread_count( 21 ), 0, 'Fetching with mark-read clears the unread count' );

// The store is capped so meta never grows unbounded.
for ( $i = 0; $i < 60; $i++ ) {
	PRX3_Social::notify( 22, 'reply', 'Note ' . $i, 'https://example.test' );
}
t_eq( count( PRX3_Social::notifications( 22 ) ), 50, 'Notification store caps at fifty, oldest dropped' );
t_eq( PRX3_Social::notifications( 22 )[0]['text'], 'Note 59', 'Cap keeps the newest entries' );

// Follows toggle on and off; self-follow is refused.
t_ok( PRX3_Social::toggle_follow( 21, 22 ), 'First toggle follows' );
t_eq( PRX3_Social::following( 21 ), array( 22 ), 'Directory records the follow' );
t_ok( ! PRX3_Social::toggle_follow( 21, 22 ), 'Second toggle unfollows' );
t_eq( PRX3_Social::following( 21 ), array(), 'Unfollow clears the list' );
t_ok( ! PRX3_Social::toggle_follow( 21, 21 ), 'Members cannot follow themselves' );
t_eq( PRX3_Social::following( 21 ), array(), 'Self-follow leaves the list untouched' );

// Directory search and pagination (FO-233).
prx3_test_reset();
for ( $i = 1; $i <= 30; $i++ ) {
	prx3_test_user( 100 + $i, array( 'display_name' => 'Owner Number' . $i, 'user_login' => 'owner' . $i, 'user_nicename' => 'owner' . $i ) );
}
prx3_test_user( 200, array( 'display_name' => 'Aileen Munro', 'user_login' => 'aileen', 'user_nicename' => 'aileen' ) );
$page1 = PRX3_Social::directory( '', 1, 24 );
t_eq( $page1['total'], 31, 'Directory counts every owner' );
t_eq( count( $page1['members'] ), 24, 'First page holds a full page of owners' );
t_eq( $page1['pages'], 2, 'Pagination computes total pages' );
$page2 = PRX3_Social::directory( '', 2, 24 );
t_eq( count( $page2['members'] ), 7, 'Last page holds the remainder' );
$found = PRX3_Social::directory( 'aileen' );
t_eq( $found['total'], 1, 'Search narrows to matching owners' );
t_eq( $found['members'][0]->display_name, 'Aileen Munro', 'Search matches by name or handle' );
t_eq( PRX3_Social::directory( 'zzz-nobody' )['total'], 0, 'No-match search returns empty' );

// Mention autosuggest matches on login, slug, or display name prefixes.
$suggest = PRX3_Social::members_suggest( 'ail' );
t_eq( count( $suggest ), 1, 'Suggest matches the typed prefix' );
t_eq( $suggest[0]['handle'], 'aileen', 'Suggest returns the @handle to insert' );
t_eq( count( PRX3_Social::members_suggest( 'owner' ) ), 8, 'Suggest caps the list at eight' );

// Cheers toggle per member and count across members.
$topic = wp_insert_post( array( 'post_type' => 'prx3_forum_topic', 'post_status' => 'publish', 'post_title' => 'Cheer me', 'post_content' => '' ) );
t_ok( PRX3_Social::toggle_cheer( 101, $topic ), 'First toggle cheers' );
t_ok( PRX3_Social::toggle_cheer( 102, $topic ), 'A second member cheers too' );
t_eq( PRX3_Social::cheer_count( $topic ), 2, 'Cheer count spans members' );
t_ok( PRX3_Social::has_cheered( 101, $topic ), 'Cheer state is per member' );
t_ok( ! PRX3_Social::toggle_cheer( 101, $topic ), 'Second toggle withdraws the cheer' );
t_eq( PRX3_Social::cheer_count( $topic ), 1, 'Withdrawn cheer leaves the rest' );
t_ok( ! PRX3_Social::has_cheered( 101, $topic ), 'Withdrawn member no longer shows cheered' );
