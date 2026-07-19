<?php
/**
 * Meeting video (FO-228) and 8x8 JaaS room tokens (P124): the video
 * window, stable private room names, moderator mapping, RS256 JWT
 * structure and signature, and video_content routing (8x8 when
 * configured, plain Jitsi fallback otherwise, matches never).
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();
$GLOBALS['prx3_t_query'] = array( 'singular' => '', 'id' => 0 );

// ---------------- Video window ----------------
$start = gmdate( 'Y-m-d H:i:s', 1770000000 );
t_eq( PRX3_Meetings::video_window( $start, 1770000000 - 2 * HOUR_IN_SECONDS ), 'before', 'Room is closed more than an hour before the start' );
t_eq( PRX3_Meetings::video_window( $start, 1770000000 - HOUR_IN_SECONDS + 60 ), 'open', 'Room opens an hour before the start' );
t_eq( PRX3_Meetings::video_window( $start, 1770000000 + 5 * HOUR_IN_SECONDS ), 'open', 'Room stays open through the meeting' );
t_eq( PRX3_Meetings::video_window( $start, 1770000000 + 7 * HOUR_IN_SECONDS ), 'after', 'Room closes six hours after the start' );
t_eq( PRX3_Meetings::video_window( '', 1770000000 ), '', 'No start time means no window' );

// ---------------- Room names ----------------
$meeting = wp_insert_post( array( 'post_type' => 'prx3_meeting', 'post_status' => 'publish', 'post_title' => 'Members forum', 'post_content' => '' ) );
$room    = PRX3_Meetings::room_name( $meeting );
t_ok( 0 === strpos( $room, 'prx3-' . $meeting . '-' ), 'Room name carries the meeting ID' );
t_eq( PRX3_Meetings::room_name( $meeting ), $room, 'Room name is generated once and stays stable' );

// ---------------- Moderator mapping ----------------
prx3_test_user( 60, array( 'display_name' => 'Ordinary Owner', 'user_email' => 'owner@example.test' ) );
prx3_test_user( 61, array( 'display_name' => 'Board Bella', 'user_email' => 'bella@example.test' ) );
$GLOBALS['prx3_t']['current'] = 60;
t_ok( ! PRX3_Meetings::is_moderator(), 'Ordinary owners are not moderators' );
$GLOBALS['prx3_t']['caps'][61]['prx3_board'] = true;
$GLOBALS['prx3_t']['current']                = 61;
t_ok( PRX3_Meetings::is_moderator(), 'Board members moderate meeting rooms' );

// ---------------- JaaS configuration + JWT ----------------
t_ok( ! PRX3_Meetings::jaas_configured(), 'JaaS is off until all three settings are present' );

$pair = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
openssl_pkey_export( $pair, $pem );
$pub = openssl_pkey_get_details( $pair )['key'];

update_option(
	'prx3_settings',
	array(
		'jaas_app_id'      => 'vpaas-magic-cookie-testapp',
		'jaas_api_key_id'  => 'vpaas-magic-cookie-testapp/abc123',
		'jaas_private_key' => $pem,
	)
);
t_ok( PRX3_Meetings::jaas_configured(), 'JaaS turns on when App ID, key ID, and private key are set' );

$user = get_userdata( 61 );
$jwt  = PRX3_Meetings::jaas_jwt( $room, $user, true );
$bits = explode( '.', $jwt );
t_eq( count( $bits ), 3, 'JaaS token has header, payload, and signature' );

$b64d    = function ( $s ) {
	return base64_decode( strtr( $s, '-_', '+/' ) );
};
$header  = json_decode( $b64d( $bits[0] ), true );
$payload = json_decode( $b64d( $bits[1] ), true );
t_eq( $header['alg'], 'RS256', 'Token is RS256 signed' );
t_eq( $header['kid'], 'vpaas-magic-cookie-testapp/abc123', 'Token header carries the API key ID' );
t_eq( $payload['aud'], 'jitsi', 'Audience is jitsi' );
t_eq( $payload['iss'], 'chat', 'Issuer is chat (JaaS requirement)' );
t_eq( $payload['sub'], 'vpaas-magic-cookie-testapp', 'Subject is the JaaS App ID' );
t_eq( $payload['room'], $room, 'Token is scoped to the meeting room' );
t_eq( $payload['context']['user']['moderator'], 'true', 'Moderator claim set for board members' );
t_eq( $payload['context']['user']['name'], 'Board Bella', 'Token carries the display name' );
t_ok( $payload['exp'] > time(), 'Token expiry is in the future' );
t_eq( 1, openssl_verify( $bits[0] . '.' . $bits[1], $b64d( $bits[2] ), $pub, OPENSSL_ALGO_SHA256 ), 'Signature verifies against the key pair' );

$member_jwt     = PRX3_Meetings::jaas_jwt( $room, get_userdata( 60 ), false );
$member_payload = json_decode( $b64d( explode( '.', $member_jwt )[1] ), true );
t_eq( $member_payload['context']['user']['moderator'], 'false', 'Ordinary owners join without moderator rights' );

// kid normalisation: a bare key fragment is prefixed with the App ID
// (8x8 requires kid = "<AppID>/<KeyID>").
update_option(
	'prx3_settings',
	array(
		'jaas_app_id'      => 'vpaas-magic-cookie-testapp',
		'jaas_api_key_id'  => 'abc123',
		'jaas_private_key' => $pem,
	)
);
$bare_header = json_decode( $b64d( explode( '.', PRX3_Meetings::jaas_jwt( $room, $user, true ) )[0] ), true );
t_eq( $bare_header['kid'], 'vpaas-magic-cookie-testapp/abc123', 'A bare key ID is normalised to AppID/KeyID for the kid header' );

// ---------------- video_content routing ----------------
update_post_meta( $meeting, '_prx3_meeting_start', gmdate( 'Y-m-d H:i:s', time() ) );
$GLOBALS['prx3_t']['current']                 = 60;
$GLOBALS['prx3_t']['caps'][60]['prx3_member'] = true;
$GLOBALS['prx3_t_query']                          = array( 'singular' => 'prx3_meeting', 'id' => $meeting );
$out = PRX3_Meetings::video_content( 'AGENDA' );
t_ok( false !== strpos( $out, 'https://8x8.vc/vpaas-magic-cookie-testapp/' ), 'Configured installs join through 8x8 JaaS' );
t_ok( false !== strpos( $out, '?jwt=' ), 'The 8x8 room URL carries the signed token' );

// Without JaaS settings the room falls back to the open Jitsi domain.
update_option( 'prx3_settings', array() );
$fallback = PRX3_Meetings::video_content( 'AGENDA' );
t_ok( false !== strpos( $fallback, 'https://meet.jit.si/' ), 'Without JaaS the room falls back to meet.jit.si' );
t_ok( false === strpos( $fallback, '8x8.vc' ), 'Fallback never touches 8x8' );

// Matches never get a meeting room.
$match                   = wp_insert_post( array( 'post_type' => 'prx3_match', 'post_status' => 'publish', 'post_title' => 'v Dundee', 'post_content' => '' ) );
$GLOBALS['prx3_t_query'] = array( 'singular' => 'prx3_match', 'id' => $match );
t_eq( PRX3_Meetings::video_content( 'MATCH' ), 'MATCH', 'Matches never embed the meeting room' );
$GLOBALS['prx3_t_query'] = array( 'singular' => '', 'id' => 0 );
