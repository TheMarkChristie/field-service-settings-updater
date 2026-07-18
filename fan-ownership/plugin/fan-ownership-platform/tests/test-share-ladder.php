<?php
/**
 * Money path: the published price ladder (FO-106/FO-107, P46).
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();

// Defaults: £50 base, +25% per tier, rounded to the penny.
t_eq( prx3_share_price( 1 ), 50.00, 'Share 1 costs the base price' );
t_eq( prx3_share_price( 2 ), 62.50, 'Share 2 is +25%' );
t_eq( prx3_share_price( 3 ), 78.13, 'Share 3 rounds to the penny' );
t_eq( prx3_share_price( 10 ), round( 50 * pow( 1.25, 9 ), 2 ), 'Share 10 follows the curve' );
t_eq( prx3_share_price( 0 ), 50.00, 'Tier floor: share 0 clamps to base' );

// Ladder totals continue from the holder's position (FO-107 AC1).
t_eq( prx3_ladder_total( 0, 1 ), 50.00, 'First share from zero' );
t_eq( prx3_ladder_total( 0, 2 ), 112.50, 'Two from zero sums tiers 1+2' );
t_eq( prx3_ladder_total( 2, 1 ), prx3_share_price( 3 ), 'Top-up continues at tier 3' );
t_eq( prx3_ladder_total( 9, 1 ), prx3_share_price( 10 ), 'Final share priced at tier 10' );
t_eq( prx3_ladder_total( 3, 0 ), 0.00, 'Buying zero costs zero' );

// The charged amount must always match the published ladder: totals
// are order-independent (0→2 then 2→1 equals 0→3 in one go).
t_eq(
	round( prx3_ladder_total( 0, 2 ) + prx3_ladder_total( 2, 1 ), 2 ),
	prx3_ladder_total( 0, 3 ),
	'Split purchases cost the same as one purchase'
);

// Config-driven: a different base and growth reprice the ladder.
update_option( 'prx3_settings', array( 'share_base_price' => 100, 'share_tier_growth' => 0.5 ) );
t_eq( prx3_share_price( 1 ), 100.00, 'Configured base price honoured' );
t_eq( prx3_share_price( 2 ), 150.00, 'Configured growth honoured' );
