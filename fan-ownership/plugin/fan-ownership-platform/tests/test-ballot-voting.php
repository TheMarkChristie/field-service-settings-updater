<?php
/**
 * Vote path: snapshot eligibility, share weighting, one-counted-vote
 * revision, secrecy, kill switch (FO-202/FO-203/FO-204, T35).
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();

$ballot = 100;
update_post_meta( $ballot, '_prx3_state', 'open' );
update_post_meta( $ballot, '_prx3_options', array( 'Yes', 'No' ) );
// Electorate snapshot at open: user 1 held 3 shares, user 2 held 1.
update_post_meta( $ballot, '_prx3_electorate', array( 1 => 3, 2 => 1 ) );

// Kill switch stops voting dead.
update_option( 'prx3_kill_switches', array( 'voting' => array( 'off' => true ) ) );
t_error_code( PRX3_Ballots::cast( $ballot, 1, 0 ), 'prx3_off', 'Kill switch blocks voting' );
update_option( 'prx3_kill_switches', array() );

// Votes are weighted from the snapshot, not the live holding.
update_user_meta( 1, 'prx3_shares', 10 ); // Live holding differs from snapshot.
$result = PRX3_Ballots::cast( $ballot, 1, 0 );
t_eq( $result['weight'], 3, 'Weight comes from the electorate snapshot, not live shares' );
t_eq( $result['revised'], false, 'First cast is not a revision' );

// Revision replaces the choice — exactly one counted vote.
$result = PRX3_Ballots::cast( $ballot, 1, 1 );
t_eq( $result['revised'], true, 'Second cast is a revision' );
$tallies = PRX3_Ballots::tallies( $ballot, true );
t_eq( $tallies['votes'], array( 0, 3 ), 'Revised vote counted once, on the new choice' );
t_eq( $tallies['members'], 1, 'One member has voted' );

// Not in the snapshot = not eligible, however many shares held now.
prx3_test_user( 3 );
update_user_meta( 3, 'prx3_shares', 10 );
t_error_code( PRX3_Ballots::cast( $ballot, 3, 0 ), 'prx3_not_eligible', 'Post-open owners are outside the electorate' );

// Invalid options refused.
t_error_code( PRX3_Ballots::cast( $ballot, 2, 9 ), 'prx3_choice', 'Unknown option refused' );

// Second voter's single share lands.
PRX3_Ballots::cast( $ballot, 2, 0 );
$tallies = PRX3_Ballots::tallies( $ballot, true );
t_eq( $tallies['votes'], array( 1, 3 ), 'Weights sum by choice' );
t_eq( $tallies['total_votes'], 4, 'Total votes = total weighted shares cast' );
t_eq( $tallies['members'], 2, 'Turnout counts members, not shares' );

// Secrecy: open-ballot tallies need the tally capability (FO-203).
$GLOBALS['prx3_t']['current'] = 2; // A plain member.
t_error_code( PRX3_Ballots::tallies( $ballot ), 'prx3_secret', 'Members cannot see live tallies on a secret ballot' );
$GLOBALS['prx3_t']['caps'][2]['prx3_view_tally'] = true;
t_eq( is_wp_error( PRX3_Ballots::tallies( $ballot ) ), false, 'Tally capability reveals live tallies' );

// Closed ballots refuse further votes.
update_post_meta( $ballot, '_prx3_state', 'closed' );
t_error_code( PRX3_Ballots::cast( $ballot, 2, 0 ), 'prx3_closed', 'Closed ballot refuses votes' );
$tallies = PRX3_Ballots::tallies( $ballot, true );
t_eq( $tallies['votes'], array( 1, 3 ), 'Closing froze the tallies' );
