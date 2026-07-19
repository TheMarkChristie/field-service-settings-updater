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
