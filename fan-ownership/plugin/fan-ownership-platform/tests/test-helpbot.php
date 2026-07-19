<?php
/**
 * FanPress Bot (P135): FAQ answering from the knowledge base, the
 * open-a-ticket fallback, ticket creation + staff notification, replies
 * with ownership rules, and the public config surface.
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();
PRX3_Comms::$sent = array();

// ---------------- Knowledge base + answering ----------------
$f1 = wp_insert_post( array( 'post_type' => 'prx3_faq', 'post_status' => 'publish', 'post_title' => 'How do I buy shares?', 'post_content' => 'Shares are bought through the Shopify checkout on the join page.' ) );
$f2 = wp_insert_post( array( 'post_type' => 'prx3_faq', 'post_status' => 'publish', 'post_title' => 'When is the next match?', 'post_content' => 'Fixtures appear in the Match Centre and your calendar feed.' ) );

$hit = PRX3_Helpbot::answer( 'how can I buy some shares?' );
t_ok( is_array( $hit ) && $hit['faq_id'] === $f1, 'A close FAQ match is returned' );
t_ok( false !== strpos( $hit['answer'], 'Shopify' ), 'The answer carries the FAQ body' );

$hit2 = PRX3_Helpbot::answer( 'when is the next match on' );
t_eq( $hit2['faq_id'], $f2, 'The best-scoring FAQ wins' );

$miss = PRX3_Helpbot::answer( 'can I bring my dog to the rink' );
t_eq( $miss, null, 'An unrelated question returns no answer (bot will offer a ticket)' );

t_eq( PRX3_Helpbot::answer( '' ), null, 'An empty question is not answered' );

// ---------------- Tickets ----------------
prx3_test_user( 60, array( 'display_name' => 'Member Mo', 'user_email' => 'mo@example.test' ) );
prx3_test_user( 61, array( 'display_name' => 'Admin Ann', 'user_email' => 'ann@example.test' ) );
$GLOBALS['prx3_t']['caps'][61]['prx3_admin'] = true;
// A staff member to receive the notification.
$staff = prx3_test_user( 62, array( 'display_name' => 'Gov Gail', 'user_email' => 'gail@example.test' ) );
$staff->roles = array( 'administrator' );

$tid = PRX3_Helpbot::create_ticket( 60, 'Cannot sign in', 'It says my password is wrong.' );
t_ok( is_int( $tid ) && $tid > 0, 'A ticket is created' );
t_eq( (string) get_post_meta( $tid, '_prx3_ticket_status', true ), 'open', 'New tickets start open' );
t_ok( count( PRX3_Comms::$sent ) >= 1, 'Staff are notified of a new ticket' );

$bad = PRX3_Helpbot::create_ticket( 60, '', '' );
t_error_code( $bad, 'prx3_ticket_empty', 'A ticket needs a subject and message' );

$mine = PRX3_Helpbot::member_tickets( 60 );
t_eq( count( $mine ), 1, 'The member sees their own ticket' );
t_eq( count( PRX3_Helpbot::member_tickets( 61 ) ), 0, 'Other members do not see it' );

// ---------------- Replies + ownership ----------------
$r1 = PRX3_Helpbot::add_reply( $tid, 61, 'Try resetting via the app.', true );
t_ok( ! is_wp_error( $r1 ), 'Staff can reply to a ticket' );
t_eq( (string) get_post_meta( $tid, '_prx3_ticket_status', true ), 'answered', 'A staff reply marks the ticket answered' );

$r2 = PRX3_Helpbot::add_reply( $tid, 60, 'That worked, thanks!', false );
t_ok( ! is_wp_error( $r2 ), 'The owning member can reply' );

$r3 = PRX3_Helpbot::add_reply( $tid, 99, 'Nosey reply', false );
t_error_code( $r3, 'prx3_ticket_owner', 'A non-owner member cannot reply to someone else\'s ticket' );

// ---------------- Config + enable ----------------
update_option( 'prx3_settings', array( 'bot_enabled' => '1', 'bot_name' => 'Panthers Helper', 'bot_color' => '#008080' ) );
$cfg = PRX3_Helpbot::config();
t_ok( PRX3_Helpbot::enabled(), 'The bot is enabled by setting' );
t_eq( $cfg['name'], 'Panthers Helper', 'Config carries the configured bot name' );
t_eq( $cfg['color'], '#008080', 'Config carries the configured colour' );

update_option( 'prx3_settings', array( 'bot_enabled' => '0' ) );
t_ok( ! PRX3_Helpbot::enabled(), 'The bot can be switched off' );
