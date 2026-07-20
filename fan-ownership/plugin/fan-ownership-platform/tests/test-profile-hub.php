<?php
/**
 * Owner profile hub (FO-320): an owner reads and edits their own profile
 * and KYC/identity record, and lists the documents they can access. KYC
 * edits are audited; PEP is whitelisted.
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();
$GLOBALS['prx3_t']['audit'] = array();

$uid = 70;
prx3_test_user( $uid, array( 'display_name' => 'Owner Ola' ) );

// ---------------- Profile edit ----------------
PRX3_REST_API::apply_profile(
	$uid,
	array(
		'bio'           => str_repeat( 'a', 400 ),
		'socials'       => array( 'x' => 'https://x.com/ola', 'instagram' => '', 'evil' => 'https://spam.example' ),
		'shares_public' => true,
		'photo_consent' => false,
	)
);
$p = PRX3_REST_API::profile_payload( $uid );
t_eq( strlen( $p['bio'] ), 300, 'Bio is capped at 300 characters' );
t_eq( $p['socials']['x'], 'https://x.com/ola', 'A known social link is saved' );
t_ok( ! isset( $p['socials']['evil'] ), 'An unknown social key is dropped' );
t_ok( $p['shares_public'], 'A truthy flag is stored as on' );
t_ok( ! $p['photo_consent'], 'A falsey flag is stored as off' );

// ---------------- Identity edit (audited) ----------------
PRX3_REST_API::apply_identity(
	$uid,
	array(
		'birth_name'  => 'Ola Original',
		'nationality' => 'Scottish',
		'dob'         => '1990-01-02',
		'gov_id'      => 'AB123456C',
		'pep'         => 'bogus',
	)
);
$id = PRX3_REST_API::identity_payload( $uid );
t_eq( $id['birth_name'], 'Ola Original', 'Identity birth name saved' );
t_eq( $id['nationality'], 'Scottish', 'Identity nationality saved' );
t_eq( $id['pep'], '', 'An invalid PEP value is rejected to empty' );
t_ok( ! empty( $GLOBALS['prx3_t']['audit'] ) && 'identity_updated' === $GLOBALS['prx3_t']['audit'][0][0], 'Identity edits are audited' );

PRX3_REST_API::apply_identity( $uid, array( 'pep' => 'yes' ) );
t_eq( PRX3_REST_API::identity_payload( $uid )['pep'], 'yes', 'A valid PEP value is accepted' );
t_eq( PRX3_REST_API::identity_payload( $uid )['birth_name'], 'Ola Original', 'A partial identity edit leaves other fields intact' );

// ---------------- Gallery removal ----------------
update_user_meta( $uid, 'prx3_gallery', array( 11, 22, 33 ) );
PRX3_REST_API::remove_gallery_photo( $uid, 22 );
$gallery = array_map( 'intval', (array) get_user_meta( $uid, 'prx3_gallery', true ) );
t_eq( count( $gallery ), 2, 'Removing a gallery photo drops exactly one entry' );
t_ok( ! in_array( 22, $gallery, true ) && in_array( 11, $gallery, true ) && in_array( 33, $gallery, true ), 'Only the requested photo is removed' );

// ---------------- Documents ----------------
update_user_meta(
	$uid,
	'prx3_certificates',
	array( array( 'issued_at' => 1, 'shares' => 3, 'verify_code' => 'ABCD1234' ) )
);
$d1 = wp_insert_post( array( 'post_type' => 'prx3_document', 'post_status' => 'publish', 'post_title' => 'Annual Report 2026' ) );
$docs  = PRX3_REST_API::documents_for( $uid );
$types = array_map(
	function ( $doc ) {
		return $doc['type'];
	},
	$docs
);
t_ok( in_array( 'certificate', $types, true ), 'The ownership certificate is listed' );
t_ok( in_array( 'document', $types, true ), 'A published club document is listed' );
foreach ( $docs as $doc ) {
	if ( 'certificate' === $doc['type'] ) {
		t_ok( false !== strpos( $doc['verify_url'], 'ABCD1234' ), 'The certificate carries its verify link' );
	}
	if ( 'document' === $doc['type'] ) {
		t_eq( $doc['title'], 'Annual Report 2026', 'The document title is carried' );
	}
}
