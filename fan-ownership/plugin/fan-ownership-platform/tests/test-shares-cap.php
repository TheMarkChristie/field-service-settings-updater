<?php
/**
 * Money path: grants, the 10-share cap across all sources, age gate,
 * owner numbers, surrender (FO-106 AC3/AC5, FO-107, FO-112).
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();
PRX3_Register::$records = array();
prx3_test_user( 1 );

// Age gate before anything else.
t_error_code( PRX3_Shares::grant_shares( 1, 1, 'purchase' ), 'prx3_age', 'No shares without the 18+ confirmation' );
update_user_meta( 1, 'prx3_adult_confirmed', time() );

// Invalid counts refused.
t_error_code( PRX3_Shares::grant_shares( 1, 0, 'purchase' ), 'prx3_invalid_count', 'Zero shares refused' );
t_error_code( PRX3_Shares::grant_shares( 1, -3, 'purchase' ), 'prx3_invalid_count', 'Negative shares refused' );

// First grant promotes to owner with a sequential owner number.
t_eq( PRX3_Shares::grant_shares( 1, 3, 'purchase' ), true, 'First grant succeeds' );
t_eq( prx3_shares( 1 ), 3, 'Holding is 3' );
t_eq( PRX3_Shares::owner_number( 1 ), 1, 'Owner number 1 allocated' );

// Cap enforced combining all sources (purchase + gift).
t_eq( PRX3_Shares::grant_shares( 1, 7, 'gift' ), true, 'Top-up to the cap succeeds' );
t_eq( prx3_shares( 1 ), 10, 'Holding is the cap' );
t_error_code( PRX3_Shares::grant_shares( 1, 1, 'purchase' ), 'prx3_cap', 'Cap cannot be exceeded by purchase' );
t_error_code( PRX3_Shares::grant_shares( 1, 1, 'gift' ), 'prx3_cap', 'Cap cannot be exceeded by gift' );

// Second member gets the next owner number, never a reused one.
prx3_test_user( 2 );
update_user_meta( 2, 'prx3_adult_confirmed', time() );
PRX3_Shares::grant_shares( 2, 1, 'purchase' );
t_eq( PRX3_Shares::owner_number( 2 ), 2, 'Owner numbers are sequential' );

// Every movement lands in the register (append-only evidence).
$acquisitions = array_filter(
	PRX3_Register::$records,
	function ( $r ) {
		return 'acquisition' === $r['event'];
	}
);
t_eq( count( $acquisitions ), 3, 'Three acquisitions recorded in the register' );

// Surrender zeroes the holding, records it, keeps the owner number.
t_eq( PRX3_Shares::surrender_all( 1, 'left' ), 10, 'Surrender returns the shares given up' );
t_eq( prx3_shares( 1 ), 0, 'Holding is zero after surrender' );
t_eq( PRX3_Shares::owner_number( 1 ), 1, 'Owner number survives surrender (never reused)' );
$surrenders = array_filter(
	PRX3_Register::$records,
	function ( $r ) {
		return 'surrender' === $r['event'];
	}
);
t_eq( count( $surrenders ), 1, 'Surrender recorded in the register' );

// Re-joining keeps the original owner number.
PRX3_Shares::grant_shares( 1, 1, 'purchase' );
t_eq( PRX3_Shares::owner_number( 1 ), 1, 'Returning owner keeps their number' );
