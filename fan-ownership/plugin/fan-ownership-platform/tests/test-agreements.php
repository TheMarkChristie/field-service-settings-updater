<?php
/**
 * Legal path: signature validation and versioned acceptance
 * (FO-121/FO-122, P97/P98).
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();

// A structurally valid PNG data URL (magic bytes + padding past the minimum).
$png       = "\x89PNG\r\n\x1a\n" . str_repeat( 'x', 300 );
$valid_sig = 'data:image/png;base64,' . base64_encode( $png );

$_POST['prx3_sha_signature'] = $valid_sig;
t_eq( PRX3_Agreements::posted_signature(), $valid_sig, 'Valid PNG signature accepted' );

$_POST['prx3_sha_signature'] = 'data:image/jpeg;base64,' . base64_encode( $png );
t_eq( PRX3_Agreements::posted_signature(), '', 'Non-PNG data URL rejected' );

$_POST['prx3_sha_signature'] = 'data:image/png;base64,' . base64_encode( 'JUNK' . str_repeat( 'x', 300 ) );
t_eq( PRX3_Agreements::posted_signature(), '', 'PNG prefix with wrong magic bytes rejected' );

$_POST['prx3_sha_signature'] = 'data:image/png;base64,!!!not-base64!!!';
t_eq( PRX3_Agreements::posted_signature(), '', 'Invalid base64 rejected' );

$_POST['prx3_sha_signature'] = 'data:image/png;base64,' . base64_encode( "\x89PNG" );
t_eq( PRX3_Agreements::posted_signature(), '', 'Sub-minimum payload rejected (blank scribble guard)' );

$_POST['prx3_sha_signature'] = 'data:image/png;base64,' . str_repeat( 'A', 300001 );
t_eq( PRX3_Agreements::posted_signature(), '', 'Oversized payload rejected' );

$_POST['prx3_sha_signature'] = '';
t_eq( PRX3_Agreements::posted_signature(), '', 'Missing signature rejected' );

// Versioned acceptance: append-only log, current-version tracking.
prx3_test_user( 7 );
update_option( 'prx3_settings', array( 'sha_version' => '1.0' ) );
t_eq( PRX3_Agreements::is_current( 7 ), false, 'No acceptance yet' );

PRX3_Agreements::record_acceptance( 7, 'checkout', 555, $valid_sig );
t_eq( PRX3_Agreements::is_current( 7 ), true, 'Acceptance makes the member current' );
t_eq( get_user_meta( 7, 'prx3_sha_signature', true ), $valid_sig, 'Signature stored for the executed copy' );

$log = get_user_meta( 7, 'prx3_sha_acceptances', true );
t_eq( count( $log ), 1, 'One acceptance logged' );
t_eq( $log[0]['version'], '1.0', 'Version recorded' );
t_eq( $log[0]['context'], 'checkout', 'Context recorded' );
t_eq( $log[0]['order'], 555, 'Order reference recorded' );
t_eq( $log[0]['signed'], true, 'Signed flag recorded' );

// Version bump: member goes stale; re-acceptance appends, never replaces.
update_option( 'prx3_settings', array( 'sha_version' => '1.1' ) );
t_eq( PRX3_Agreements::is_current( 7 ), false, 'Version bump makes the member out of date' );
PRX3_Agreements::record_acceptance( 7, 'reaccept', 0, $valid_sig );
t_eq( PRX3_Agreements::is_current( 7 ), true, 'Re-acceptance restores currency' );
$log = get_user_meta( 7, 'prx3_sha_acceptances', true );
t_eq( count( $log ), 2, 'Log is append-only — both acceptances kept' );
t_eq( $log[0]['version'], '1.0', 'Original acceptance untouched' );
t_eq( $log[1]['version'], '1.1', 'New acceptance appended' );

// The acceptance fires the sync signal (FO-124 wiring).
$fired = array_filter(
	$GLOBALS['prx3_t']['actions'],
	function ( $a ) {
		return 'prx3_sha_accepted' === $a[0];
	}
);
t_eq( count( $fired ), 2, 'Acceptance events fired for sync' );
