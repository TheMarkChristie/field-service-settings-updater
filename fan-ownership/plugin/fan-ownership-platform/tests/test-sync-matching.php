<?php
/**
 * Sync path: inbound matching rules — ID, then email, then owner
 * number; review queue for ambiguity; field-level ownership
 * (FO-125, P100/P102).
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();
update_option(
	'prx3_settings',
	array(
		'sync_enabled' => 1,
		'sync_api_key' => 'k',
	)
);

prx3_test_user( 5, array( 'user_email' => 'alice@example.test' ) );
update_user_meta( 5, 'prx3_owner_number', 7 );
prx3_test_user( 6, array( 'user_email' => 'bob@example.test' ) );
update_user_meta( 6, 'prx3_owner_number', 9 );

function prx3_t_upsert( $body ) {
	return PRX3_Sync::route_upsert( new PRX3_Test_Request( $body ) );
}

// Rule 2: email match links and applies enrichment.
$result = prx3_t_upsert(
	array(
		'dataverse_id' => 'G1',
		'email'        => 'ALICE@example.test',
		'fields'       => array(
			'phone'  => '01738 123456',
			'shares' => 99,
		),
	)
);
t_eq( $result['status'], 'linked', 'Email match links' );
t_eq( $result['matched_by'], 'email', 'Matched by email (case-insensitive)' );
t_eq( get_user_meta( 5, 'prx3_dataverse_id', true ), 'G1', 'Cross-reference link stored' );
t_eq( get_user_meta( 5, 'prx3_crm_phone', true ), '01738 123456', 'Enrichment field applied' );
t_eq( $result['ignored'], array( 'shares' ), 'Ownership field rejected — field-level ownership holds' );
t_eq( get_user_meta( 5, 'prx3_shares', true ), '', 'Shares untouched by inbound write' );

// Rule 1: once linked, the ID wins even without an email.
$result = prx3_t_upsert(
	array(
		'dataverse_id' => 'G1',
		'fields'       => array( 'city' => 'Perth' ),
	)
);
t_eq( $result['matched_by'], 'dataverse_id', 'Linked records match by cross-reference ID' );
t_eq( get_user_meta( 5, 'prx3_crm_city', true ), 'Perth', 'Update applied via ID match' );

// Rule 3: owner number when there is no link and no email match.
$result = prx3_t_upsert(
	array(
		'dataverse_id' => 'G2',
		'owner_number' => 9,
	)
);
t_eq( $result['matched_by'], 'owner_number', 'Owner number is the third rule' );
t_eq( get_user_meta( 6, 'prx3_dataverse_id', true ), 'G2', 'Owner-number match stores the link' );

// Conflict: matched member already linked to a different record → review, no writes.
$result = prx3_t_upsert(
	array(
		'dataverse_id' => 'G9',
		'email'        => 'alice@example.test',
		'fields'       => array( 'phone' => 'SHOULD NOT LAND' ),
	)
);
t_eq( $result['status'], 'review', 'Link conflict goes to review' );
t_eq( $result['reason'], 'link_conflict', 'Conflict reason recorded' );
t_eq( get_user_meta( 5, 'prx3_crm_phone', true ), '01738 123456', 'Conflicting update wrote nothing' );
t_eq( get_user_meta( 5, 'prx3_dataverse_id', true ), 'G1', 'Existing link preserved' );

// No match at all → review, never auto-created.
$user_count = count( $GLOBALS['prx3_t']['users'] );
$result     = prx3_t_upsert(
	array(
		'dataverse_id' => 'G5',
		'email'        => 'nobody@example.test',
	)
);
t_eq( $result['status'], 'review', 'No match goes to review' );
t_eq( $result['reason'], 'no_match', 'No-match reason recorded' );
t_eq( count( $GLOBALS['prx3_t']['users'] ), $user_count, 'No member auto-created' );

// The queue holds both entries with payloads for the human.
$queue = get_option( 'prx3_sync_review', array() );
t_eq( count( $queue ), 2, 'Both ambiguous records queued for review' );

// dataverse_id is mandatory.
$result = prx3_t_upsert( array( 'email' => 'alice@example.test' ) );
t_error_code( $result, 'prx3_sync_bad_request', 'Missing dataverse_id refused' );

// Auth: wrong key and disabled sync both refuse.
t_error_code( PRX3_Sync::authorize( new PRX3_Test_Request( array(), array(), array( 'X-Prx3-Api-Key' => 'wrong' ) ) ), 'prx3_sync_auth', 'Wrong API key refused' );
t_eq( PRX3_Sync::authorize( new PRX3_Test_Request( array(), array(), array( 'X-Prx3-Api-Key' => 'k' ) ) ), true, 'Correct API key accepted' );
update_option( 'prx3_settings', array( 'sync_enabled' => 0 ) );
t_error_code( PRX3_Sync::authorize( new PRX3_Test_Request( array(), array(), array( 'X-Prx3-Api-Key' => 'k' ) ) ), 'prx3_sync_disabled', 'Disabled sync refuses all calls' );
