<?php
/**
 * Application-password sign-in (FO-301): the app authenticates with a
 * WordPress Application Password and receives a JWT pair, so it can sign
 * in even where a security/2FA plugin blocks password-only REST logins.
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();

// A stand-in for WordPress core's application-password authenticator:
// accepts one known credential, rejects everything else.
if ( ! function_exists( 'wp_authenticate_application_password' ) ) {
	function wp_authenticate_application_password( $input_user, $username, $password ) {
		if ( ( 'mark' === $username || 'mark@example.test' === $username ) && 'abcd EFGH ijkl MNOP qrst UVWX' === $password ) {
			return new WP_User( 88 );
		}
		return new WP_Error( 'incorrect_password', 'The provided password is incorrect.' );
	}
}

// Success: a valid application password yields a usable token pair.
$ok = PRX3_REST_API::app_password_login( 'mark', 'abcd EFGH ijkl MNOP qrst UVWX' );
t_ok( is_array( $ok ) && ! empty( $ok['access_token'] ), 'A valid application password returns an access token' );
t_ok( is_array( $ok ) && ! empty( $ok['refresh_token'] ), 'A valid application password returns a refresh token' );

// The account email works as the username too.
$byemail = PRX3_REST_API::app_password_login( 'mark@example.test', 'abcd EFGH ijkl MNOP qrst UVWX' );
t_ok( is_array( $byemail ) && ! empty( $byemail['access_token'] ), 'The account email is accepted as the username' );

// Failure: a wrong application password is rejected with the auth error.
$bad = PRX3_REST_API::app_password_login( 'mark', 'wrong-password' );
t_error_code( $bad, 'prx3_login', 'A wrong application password is rejected' );
