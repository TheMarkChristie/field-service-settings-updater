<?php
/**
 * Data API: key auth, content writes restricted to platform types and
 * meta, member creation through the money path, settings allow-list.
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();

// Disabled (default) refuses even with a key.
t_error_code( PRX3_Data_API::authorize( new PRX3_Test_Request( array(), array(), array( 'X-Prx3-Data-Key' => 'k' ) ) ), 'prx3_data_disabled', 'Data API off by default' );

update_option(
	'prx3_settings',
	array(
		'data_api_enabled' => 1,
		'data_api_key'     => 'secret-key',
	)
);
t_error_code( PRX3_Data_API::authorize( new PRX3_Test_Request( array(), array(), array( 'X-Prx3-Data-Key' => 'wrong' ) ) ), 'prx3_data_auth', 'Wrong key refused' );
t_eq( PRX3_Data_API::authorize( new PRX3_Test_Request( array(), array(), array( 'X-Prx3-Data-Key' => 'secret-key' ) ) ), true, 'Correct key accepted' );

// Content: platform types only; meta restricted to the _prx3_ prefix.
$result = PRX3_Data_API::route_content(
	new PRX3_Test_Request(
		array(
			'type'  => 'prx3_player',
			'title' => 'Alexis Wight',
			'meta'  => array(
				'_prx3_number'   => 19,
				'_prx3_position' => 'Goaltender',
				'_prx3_active'   => 1,
				'wp_capabilities' => 'administrator', // Hostile key — must be ignored.
			),
		)
	)
);
$row    = $result['results'][0];
t_eq( $row['ok'], true, 'Player inserted' );
t_eq( get_post_meta( $row['id'], '_prx3_number', true ), 19, 'Platform meta written' );
t_eq( get_post_meta( $row['id'], 'wp_capabilities', true ), '', 'Non-prx3 meta refused (privilege escalation blocked)' );

$result = PRX3_Data_API::route_content( new PRX3_Test_Request( array( 'type' => 'post', 'title' => 'nope' ) ) );
t_eq( $result['results'][0]['ok'], false, 'Core post types are not writable' );

$update = PRX3_Data_API::route_content(
	new PRX3_Test_Request(
		array(
			'type'  => 'prx3_player',
			'id'    => $row['id'],
			'title' => 'Alexis Wight',
			'meta'  => array( '_prx3_active' => 0 ),
		)
	)
);
t_eq( $update['results'][0]['id'], $row['id'], 'Update targets the same post' );
t_eq( get_post_meta( $row['id'], '_prx3_active', true ), 0, 'Update applied' );

// Members: created via the money path — cap still enforced.
$result = PRX3_Data_API::route_members(
	new PRX3_Test_Request(
		array(
			'email'  => 'import@example.test',
			'name'   => 'Imported Owner',
			'shares' => 3,
		)
	)
);
$member = $result['results'][0];
t_eq( $member['ok'], true, 'Member imported' );
t_eq( $member['shares'], 3, 'Shares granted' );
t_ok( $member['owner_number'] > 0, 'Owner number allocated' );

$result = PRX3_Data_API::route_members(
	new PRX3_Test_Request(
		array(
			'email'  => 'import@example.test',
			'shares' => 8,
		)
	)
);
t_eq( $result['results'][0]['ok'], false, 'Cap enforced through the API (3 + 8 > 10)' );

// Settings: allow-listed keys only; API keys can never rotate themselves.
$result = PRX3_Data_API::route_settings(
	new PRX3_Test_Request(
		array(
			'target_owners' => 2000,
			'data_api_key'  => 'hijack',
			'made_up_key'   => 'x',
		)
	)
);
t_eq( $result['applied'], array( 'target_owners' ), 'Known setting applied' );
t_eq( in_array( 'data_api_key', $result['ignored'], true ), true, 'Key rotation over the API refused' );
t_eq( (int) prx3_setting( 'target_owners' ), 2000, 'Setting stored' );
t_eq( prx3_setting( 'data_api_key' ), 'secret-key', 'API key unchanged' );

// Read route: inspect existing content before writing.
$list = PRX3_Data_API::route_content_read( new PRX3_Test_Request( array(), array( 'type' => 'prx3_player' ) ) );
t_eq( count( $list['items'] ), 1, 'Read route lists the inserted player' );
t_eq( $list['items'][0]['title'], 'Alexis Wight', 'Read returns the title' );
t_eq( $list['items'][0]['meta']['_prx3_number'], 19, 'Read returns platform meta' );
t_eq( $list['items'][0]['meta']['_prx3_active'], 0, 'Read reflects the update' );
t_error_code( PRX3_Data_API::route_content_read( new PRX3_Test_Request( array(), array( 'type' => 'post' ) ) ), 'prx3_data_type', 'Read refuses core types' );
