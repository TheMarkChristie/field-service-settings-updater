<?php
/**
 * Dependency-free test runner for the money/vote paths (T35).
 * Usage: php tests/run-tests.php
 *
 * @package FanOwnershipPlatform
 */

require __DIR__ . '/bootstrap.php';

$prx3_files = glob( __DIR__ . '/test-*.php' );
sort( $prx3_files );
foreach ( $prx3_files as $prx3_file ) {
	echo basename( $prx3_file ) . "\n";
	require $prx3_file;
}

$prx3_total  = count( $GLOBALS['prx3_test_results'] );
$prx3_failed = count(
	array_filter(
		$GLOBALS['prx3_test_results'],
		function ( $r ) {
			return ! $r[0];
		}
	)
);

echo "\n" . ( $prx3_total - $prx3_failed ) . "/{$prx3_total} assertions passed";
if ( $prx3_failed ) {
	echo " — {$prx3_failed} FAILED\n";
	exit( 1 );
}
echo "\n";
exit( 0 );
