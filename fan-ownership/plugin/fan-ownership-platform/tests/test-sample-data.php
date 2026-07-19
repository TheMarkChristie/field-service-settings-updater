<?php
/**
 * Sample data pack (FO-130): every payload in integrations/sample-data
 * is pushed through the real Data API handlers, so the pack the club
 * runs against a live site is proven against the same code and rules.
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();
$GLOBALS['prx3_t_posts']    = array();
$GLOBALS['prx3_t_comments'] = array();

$prx3_pack = dirname( __DIR__, 3 ) . '/integrations/sample-data';
$prx3_members  = json_decode( (string) file_get_contents( $prx3_pack . '/members.json' ), true );
$prx3_content  = json_decode( (string) file_get_contents( $prx3_pack . '/content.json' ), true );
$prx3_settings = json_decode( (string) file_get_contents( $prx3_pack . '/settings.json' ), true );

t_ok( is_array( $prx3_members ) && is_array( $prx3_content ) && is_array( $prx3_settings ), 'All three pack files are valid JSON' );

update_option(
	'prx3_settings',
	array(
		'data_api_enabled' => 1,
		'data_api_key'     => 'sample-key',
		'max_shares'       => 10,
	)
);

// Members through the money path.
$result = PRX3_Data_API::route_members( new PRX3_Test_Request( $prx3_members ) );
$rows   = $result['results'];
t_eq( count( $rows ), count( $prx3_members ), 'All twenty sample members processed' );
$prx3_failed = array_filter( $rows, fn( $r ) => empty( $r['ok'] ) );
t_eq( count( $prx3_failed ), 0, 'Every member import succeeds (' . implode( '; ', array_map( fn( $r ) => $r['error'] ?? '?', $prx3_failed ) ) . ')' );
t_ok( $rows[0]['owner_number'] > 0, 'Owner numbers issue through the register' );
t_eq( $rows[1]['shares'], 10, 'The ten-share member lands on the cap exactly' );

// Content through the platform-type guard rails.
$result = PRX3_Data_API::route_content( new PRX3_Test_Request( $prx3_content ) );
$rows   = $result['results'];
t_eq( count( $rows ), count( $prx3_content ), 'Every content item processed' );
$prx3_failed = array_filter( $rows, fn( $r ) => empty( $r['ok'] ) );
t_eq( count( $prx3_failed ), 0, 'Every content item succeeds (' . implode( '; ', array_map( fn( $r ) => $r['error'] ?? '?', $prx3_failed ) ) . ')' );

// Spot-check the seeded club: squad, matches, the open ballot, FanPress chats.
t_eq( count( get_posts( array( 'post_type' => 'prx3_player', 'post_status' => array( 'publish' ) ) ) ), 8, 'Eight squad players seeded' );
t_eq( count( get_posts( array( 'post_type' => 'prx3_match', 'post_status' => array( 'publish' ) ) ) ), 3, 'Three matches seeded' );
$prx3_open = get_posts( array( 'post_type' => 'prx3_ballot', 'post_status' => array( 'publish' ), 'meta_key' => '_prx3_state', 'meta_value' => 'open' ) );
t_eq( count( $prx3_open ), 1, 'One ballot arrives open' );
t_eq( count( (array) get_post_meta( $prx3_open[0]->ID, '_prx3_options', true ) ), 3, 'The open ballot carries its three options' );
t_eq( count( get_posts( array( 'post_type' => 'prx3_forum_topic', 'post_status' => array( 'publish' ) ) ) ), 3, 'Three FanPress chats seeded' );

// Settings through the allow-list.
$result = PRX3_Data_API::route_settings( new PRX3_Test_Request( $prx3_settings ) );
t_ok( in_array( 'target_revenue', $result['applied'], true ), 'Financial target applied' );
t_ok( in_array( 'chat_color_board', $result['applied'], true ), 'Board bubble colour applied' );
t_eq( prx3_setting( 'chat_color_mine' ), '#dcf8c6', 'Chat colours readable after seeding' );
t_eq( (int) prx3_setting( 'target_revenue' ), 250000, 'Season target readable after seeding' );

// The pack never smuggles anything past the rules.
$prx3_types = PRX3_Data_API::allowed_types();
foreach ( $prx3_content as $prx3_item ) {
	if ( ! in_array( $prx3_item['type'], $prx3_types, true ) ) {
		t_ok( false, 'Pack uses disallowed type ' . $prx3_item['type'] );
	}
	foreach ( array_keys( (array) ( $prx3_item['meta'] ?? array() ) ) as $prx3_key ) {
		if ( 0 !== strpos( $prx3_key, '_prx3_' ) ) {
			t_ok( false, 'Pack uses non-platform meta ' . $prx3_key );
		}
	}
}
t_ok( true, 'Pack stays inside platform types and _prx3_ meta throughout' );

// The bundled one-click loader (Load demo club) runs the same pack.
prx3_test_reset();
$GLOBALS['prx3_t_posts']    = array();
$GLOBALS['prx3_t_comments'] = array();
if ( ! defined( 'PRX3_DIR' ) ) {
	define( 'PRX3_DIR', dirname( __DIR__ ) . '/' );
}
update_option( 'prx3_settings', array( 'max_shares' => 10 ) );
$seeded = PRX3_Data_API::seed_sample();
t_eq( $seeded['members'], 20, 'One-click loader imports all twenty members' );
t_eq( $seeded['content'], 32, 'One-click loader inserts all thirty-two content items' );
t_ok( $seeded['settings'] >= 5, 'One-click loader applies the targets and chat colours' );
t_eq( count( $seeded['failed'] ), 0, 'One-click loader has zero failures (' . implode( '; ', $seeded['failed'] ) . ')' );
t_ok( (int) get_option( 'prx3_sample_loaded' ) > 0, 'Loader stamps the loaded marker' );

// The bundled copies can never drift from the integrations pack.
foreach ( array( 'members', 'content', 'settings' ) as $prx3_part ) {
	t_eq(
		json_decode( (string) file_get_contents( PRX3_DIR . 'data/sample-' . $prx3_part . '.json' ), true ),
		json_decode( (string) file_get_contents( $prx3_pack . '/' . $prx3_part . '.json' ), true ),
		'Bundled ' . $prx3_part . ' matches the integrations pack byte-for-byte'
	);
}
