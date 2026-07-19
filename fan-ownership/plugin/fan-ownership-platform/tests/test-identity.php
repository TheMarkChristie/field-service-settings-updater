<?php
/**
 * Identity-as-config check (FO-104 AC2): no hard-coded club name in
 * member-facing code — only the config default may carry it.
 *
 * @package FanOwnershipPlatform
 */

$prx3_root = dirname( __DIR__ );
$prx3_hits = array();
foreach ( array( 'includes', 'admin', 'assets' ) as $prx3_dir ) {
	$prx3_iter = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $prx3_root . '/' . $prx3_dir ) );
	foreach ( $prx3_iter as $prx3_file ) {
		if ( ! in_array( $prx3_file->getExtension(), array( 'php', 'js', 'css' ), true ) ) {
			continue;
		}
		if ( 'class-prx3-config.php' === $prx3_file->getFilename() ) {
			continue; // The configurable default lives here by design.
		}
		if ( false !== strpos( (string) file_get_contents( $prx3_file->getPathname() ), 'Perth Panthers' ) ) {
			$prx3_hits[] = substr( $prx3_file->getPathname(), strlen( $prx3_root ) + 1 );
		}
	}
}
t_eq( $prx3_hits, array(), 'No hard-coded club name outside the config default (' . implode( ', ', $prx3_hits ) . ')' );
