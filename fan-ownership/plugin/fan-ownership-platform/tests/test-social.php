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
