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
