<?php
/**
 * Owner avatars (P130): the platform's own picture where an owner has
 * uploaded one, a clean local grey placeholder where they haven't, and
 * no interference with non-owner accounts (which keep the WP default).
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();

prx3_test_user( 90, array( 'display_name' => 'Photo Owner', 'user_email' => 'photo@example.test' ) );
prx3_test_user( 91, array( 'display_name' => 'Bare Owner', 'user_email' => 'bare@example.test' ) );
prx3_test_user( 92, array( 'display_name' => 'Random Visitor', 'user_email' => 'rand@example.test' ) );
$GLOBALS['prx3_t']['caps'][90]['prx3_member'] = true;
$GLOBALS['prx3_t']['caps'][91]['prx3_member'] = true;
// 92 is not an owner.

update_user_meta( 90, 'prx3_photo', 777 );

$default = '<img src="https://gravatar" class="avatar" />';

// Owner with a photo -> their uploaded picture.
$a90 = PRX3_Social::filter_avatar( $default, 90, 96, 'mystery', 'Photo Owner' );
t_ok( false !== strpos( $a90, 'photo-777.jpg' ), 'An owner with an uploaded picture shows it as their avatar' );
t_ok( false === strpos( $a90, 'gravatar' ), 'The Gravatar is not used for an owner with a photo' );
t_ok( false !== strpos( $a90, 'width="96"' ), 'The avatar is rendered at the requested size' );

// Owner without a photo -> local grey placeholder, no Gravatar.
$a91 = PRX3_Social::filter_avatar( $default, 91, 96, 'mystery', 'Bare Owner' );
t_ok( false !== strpos( $a91, 'data:image/svg+xml' ), 'An owner with no picture gets a local SVG placeholder' );
t_ok( false === strpos( $a91, 'gravatar' ), 'No Gravatar request is made for an owner without a photo' );

// Non-owner -> untouched WP default.
$a92 = PRX3_Social::filter_avatar( $default, 92, 96, 'mystery', 'Random Visitor' );
t_eq( $a92, $default, 'Non-owner accounts keep the WordPress default avatar' );

// Resolving by email works (comment/author lookups).
$byemail = PRX3_Social::filter_avatar( $default, 'photo@example.test', 48, 'mystery', '' );
t_ok( false !== strpos( $byemail, 'photo-777.jpg' ), 'Avatars resolve by email as well as by ID' );

// The placeholder is a self-contained data URI at the right size.
$ph = PRX3_Social::placeholder_avatar( 120 );
t_ok( 0 === strpos( $ph, 'data:image/svg+xml' ), 'The placeholder is an inline data URI' );
t_ok( false !== strpos( rawurldecode( $ph ), "width='120'" ), 'The placeholder honours the requested size' );
