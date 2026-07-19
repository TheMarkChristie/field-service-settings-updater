<?php
/**
 * Configurable squad source (P131): the player features can point at
 * the built-in players table or an existing one from another plugin,
 * so a club never runs two squad lists.
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();

// ---------------- Default: the built-in table ----------------
t_eq( prx3_player_post_type(), 'prx3_player', 'The squad source defaults to the built-in players table' );

$q = PRX3_Players::candidate_query();
t_eq( $q['post_type'], 'prx3_player', 'Candidates come from the built-in type by default' );
t_eq( $q['meta_value'], '1', 'The built-in roster is filtered to the active flag' );

$own_active = wp_insert_post( array( 'post_type' => 'prx3_player', 'post_status' => 'publish', 'post_title' => 'Active Ace' ) );
update_post_meta( $own_active, '_prx3_active', '1' );
$own_bench = wp_insert_post( array( 'post_type' => 'prx3_player', 'post_status' => 'publish', 'post_title' => 'Benched Ben' ) );
t_ok( PRX3_Players::is_votable_player( $own_active ), 'An active built-in player is votable' );
t_ok( ! PRX3_Players::is_votable_player( $own_bench ), 'An inactive built-in player is not votable' );

// ---------------- Pointed at an external squad table ----------------
update_option( 'prx3_settings', array( 'player_post_type' => 'sp_player' ) );
t_eq( prx3_player_post_type(), 'sp_player', 'The squad source follows the configured post type' );

$q2 = PRX3_Players::candidate_query();
t_eq( $q2['post_type'], 'sp_player', 'Candidates now come from the external squad table' );
t_ok( ! isset( $q2['meta_value'] ), 'The external table is not filtered on our active flag (its plugin manages the roster)' );

$ext = wp_insert_post( array( 'post_type' => 'sp_player', 'post_status' => 'publish', 'post_title' => 'League Legend' ) );
t_ok( PRX3_Players::is_votable_player( $ext ), 'An external player is votable without our active meta' );
t_ok( ! PRX3_Players::is_votable_player( $own_active ), 'A player of the wrong (built-in) type is not votable when an external source is set' );

// ---------------- Blank falls back to the built-in ----------------
update_option( 'prx3_settings', array( 'player_post_type' => '' ) );
t_eq( prx3_player_post_type(), 'prx3_player', 'A blank setting falls back to the built-in players table' );

// ---------------- Match source (P134) ----------------
prx3_test_reset();
t_eq( prx3_match_post_type(), 'prx3_match', 'The match source defaults to the built-in Match Centre' );

// Built-in matches: POTM validation rejects the wrong type.
$own_match = wp_insert_post( array( 'post_type' => 'prx3_match', 'post_status' => 'publish', 'post_title' => 'v Fife' ) );
t_eq( get_post_type( $own_match ), 'prx3_match', 'Built-in match created' );

// External fixtures table: POTM opens while the fixture is published,
// with no dependence on our live-state meta.
update_option( 'prx3_settings', array( 'match_post_type' => 'hockey_match', 'player_post_type' => 'hockey_player' ) );
t_eq( prx3_match_post_type(), 'hockey_match', 'The match source follows the configured post type' );
$ext_match = wp_insert_post( array( 'post_type' => 'hockey_match', 'post_status' => 'publish', 'post_title' => 'v Dundee Stars' ) );
t_ok( PRX3_Players::potm_open( $ext_match ), 'POTM is open for a published external fixture' );
$draft_match = wp_insert_post( array( 'post_type' => 'hockey_match', 'post_status' => 'draft', 'post_title' => 'v Glasgow (draft)' ) );
t_ok( ! PRX3_Players::potm_open( $draft_match ), 'POTM is closed for an unpublished external fixture' );

// A vote on the external fixture for an external player is accepted end to end.
prx3_test_user( 55, array( 'display_name' => 'Voter' ) );
$GLOBALS['prx3_t']['caps'][55]['prx3_member'] = true;
$ext_player = wp_insert_post( array( 'post_type' => 'hockey_player', 'post_status' => 'publish', 'post_title' => 'Star Forward' ) );
$res = PRX3_Players::potm_vote( $ext_match, 55, $ext_player );
t_ok( ! is_wp_error( $res ), 'A POTM vote on an external fixture + external player is accepted' );

// A match of the wrong type is rejected.
$wrong = PRX3_Players::potm_vote( $own_match, 55, $ext_player );
t_error_code( $wrong, 'prx3_match', 'A match of the wrong type is rejected when an external source is set' );

update_option( 'prx3_settings', array( 'match_post_type' => '' ) );
t_eq( prx3_match_post_type(), 'prx3_match', 'A blank match setting falls back to the built-in Match Centre' );
