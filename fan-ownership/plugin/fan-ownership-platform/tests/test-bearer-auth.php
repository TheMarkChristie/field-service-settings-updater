<?php
/**
 * Bearer-token authentication (FO-301): the app's JWT must be honoured
 * even when the host strips the Authorization header from
 * HTTP_AUTHORIZATION — the usual reason app sign-in "works" but every
 * authenticated call (e.g. /me) then fails on production.
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();

$access = PRX3_JWT::issue_pair( 88 )['access_token'];

// A configurable getallheaders() double for the CLI SAPI.
$GLOBALS['prx3_test_headers'] = array();
if ( ! function_exists( 'getallheaders' ) ) {
	function getallheaders() {
		return $GLOBALS['prx3_test_headers'];
	}
}

$reset = function () {
	unset( $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
	$GLOBALS['prx3_test_headers'] = array();
};

// Already-authenticated requests pass straight through untouched.
$reset();
t_eq( PRX3_REST_API::bearer_auth( 5 ), 5, 'An already-resolved user is returned unchanged' );

// The standard location.
$reset();
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $access;
t_eq( PRX3_REST_API::bearer_auth( false ), 88, 'Token read from HTTP_AUTHORIZATION' );

// Apache rewrite fallback (header moved by mod_rewrite).
$reset();
$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer ' . $access;
t_eq( PRX3_REST_API::bearer_auth( false ), 88, 'Token read from REDIRECT_HTTP_AUTHORIZATION' );

// Only exposed via getallheaders() (some CGI/FastCGI setups).
$reset();
$GLOBALS['prx3_test_headers'] = array( 'Authorization' => 'Bearer ' . $access );
t_eq( PRX3_REST_API::bearer_auth( false ), 88, 'Token read from getallheaders()' );

// No header at all: the request stays unauthenticated.
$reset();
t_eq( PRX3_REST_API::bearer_auth( false ), false, 'No Authorization header leaves the request unauthenticated' );

// A malformed / non-Bearer header is ignored.
$reset();
$_SERVER['HTTP_AUTHORIZATION'] = 'Basic abc123';
t_eq( PRX3_REST_API::bearer_auth( false ), false, 'A non-Bearer header is ignored' );

$reset();
