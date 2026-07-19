<?php
/**
 * Question categories (P126): the category list, submission with a
 * valid/invalid category, the default, and admin profile moderation
 * (content editable, identity untouchable).
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();

// ---------------- Categories ----------------
$cats = PRX3_Questions::categories();
t_eq( array_keys( $cats ), array( 'board', 'manager-prematch', 'manager-weekly', 'captain' ), 'Four categories ship: board, manager pre-match, manager weekly, captain' );

prx3_test_user( 70, array( 'display_name' => 'Asker' ) );
$GLOBALS['prx3_t']['caps'][70]['prx3_member'] = true;

$q1 = PRX3_Questions::submit( 70, 'When is the next signing?', '', 'manager-weekly' );
t_ok( $q1 > 0 && ! is_wp_error( $q1 ), 'Question submits with a category' );
t_eq( PRX3_Questions::category( $q1 ), 'manager-weekly', 'The chosen category is stored' );

$q2 = PRX3_Questions::submit( 70, 'What is the budget?', '' );
t_eq( PRX3_Questions::category( $q2 ), 'board', 'No category defaults to the board' );

$q3 = PRX3_Questions::submit( 70, 'Bad category?', '', 'tea-lady' );
t_eq( PRX3_Questions::category( $q3 ), 'board', 'An unknown category falls back to the board' );

// ---------------- Profile moderation (P126) ----------------
prx3_test_reset();
prx3_test_user( 80, array( 'display_name' => 'Owner Olive' ) );
prx3_test_user( 81, array( 'display_name' => 'Admin Ally' ) );
$GLOBALS['prx3_t']['caps'][80]['prx3_member'] = true;
$GLOBALS['prx3_t']['caps'][81]['prx3_admin']  = true;

update_user_meta( 80, 'prx3_bio', 'Something inappropriate' );
update_user_meta( 80, 'prx3_socials', array( 'x' => 'https://x.com/bad' ) );
update_user_meta( 80, 'prx3_photo', 501 );
update_user_meta( 80, 'prx3_gallery', array( 502, 503, 504 ) );
update_user_meta(
	80,
	'prx3_identity',
	array(
		'birth_name'  => 'Olive Example',
		'nationality' => 'Scottish',
		'residence'   => 'UK',
		'dob'         => '1990-01-01',
		'gov_id'      => 'AB123456C',
		'pep'         => 'no',
	)
);

// Non-admins are refused.
$denied = PRX3_Social::moderate_profile( 80, 80, array( 'bio' => '' ) );
t_error_code( $denied, 'prx3_admin_only', 'Only Owner-Admins can moderate a profile' );

// Admin cleans the content.
$applied = PRX3_Social::moderate_profile(
	81,
	80,
	array(
		'bio'            => '',
		'clear_socials'  => true,
		'remove_photo'   => true,
		'remove_gallery' => array( 503 ),
	)
);
t_eq( $applied, array( 'bio', 'socials', 'photo', 'gallery' ), 'Bio, socials, photo, and a gallery picture can all be moderated' );
t_eq( (string) get_user_meta( 80, 'prx3_bio', true ), '', 'The inappropriate bio is cleared' );
t_eq( array_filter( (array) get_user_meta( 80, 'prx3_socials', true ) ), array(), 'Social links are removed' );
t_eq( (int) get_user_meta( 80, 'prx3_photo', true ), 0, 'The profile picture is removed' );
t_eq( array_values( (array) get_user_meta( 80, 'prx3_gallery', true ) ), array( 502, 504 ), 'Only the flagged gallery photo is removed' );

// The identity record is untouched — moderation cannot reach it.
$identity = (array) get_user_meta( 80, 'prx3_identity', true );
t_eq( $identity['birth_name'], 'Olive Example', 'Name is never editable through moderation' );
t_eq( $identity['gov_id'], 'AB123456C', 'Government ID is never editable through moderation' );
t_eq( $identity['pep'], 'no', 'The PEP declaration is never editable through moderation' );

// No-op calls apply nothing and are not audited as changes.
$noop = PRX3_Social::moderate_profile( 81, 80, array( 'remove_photo' => true ) );
t_eq( $noop, array(), 'Removing an already-removed photo applies nothing' );
