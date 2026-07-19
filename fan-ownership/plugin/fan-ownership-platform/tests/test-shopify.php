<?php
/**
 * Shopify commerce path: HMAC, ladder verification, sign-to-claim,
 * gifts, refund clawback, cart permalinks (P105).
 *
 * @package FanOwnershipPlatform
 */

prx3_test_reset();
PRX3_Register::$records = array();
update_option(
	'prx3_settings',
	array(
		'commerce_provider'      => 'shopify',
		'shopify_domain'         => 'club.myshopify.com',
		'shopify_webhook_secret' => 'shhh',
		'shopify_share_variants' => 'v1,v2,v3,v4,v5,v6,v7,v8,v9,v10',
		'sha_version'            => '1.0',
	)
);
PRX3_Shopify::init(); // Registers the sign-to-claim listener.

// HMAC verification.
$body = '{"id":1}';
$good = base64_encode( hash_hmac( 'sha256', $body, 'shhh', true ) );
t_eq( PRX3_Shopify::verify_hmac( $body, $good, 'shhh' ), true, 'Genuine Shopify HMAC accepted' );
t_eq( PRX3_Shopify::verify_hmac( $body, $good, 'wrong' ), false, 'Wrong secret refused' );
t_eq( PRX3_Shopify::verify_hmac( $body, '', 'shhh' ), false, 'Missing header refused' );

// Cart permalink walks the buyer's next tiers.
prx3_test_user( 20, array( 'user_email' => 'buyer@example.test' ) );
update_user_meta( 20, 'prx3_shares', 2 );
t_eq( PRX3_Shopify::checkout_url( 20, 2 ), 'https://club.myshopify.com/cart/v3:1,v4:1', 'Cart permalink uses tiers 3 and 4' );
update_user_meta( 20, 'prx3_shares', 10 );
t_eq( PRX3_Shopify::checkout_url( 20, 1 ), '', 'No checkout link at the cap' );
update_user_meta( 20, 'prx3_shares', 0 );

// Order for a signed member grants immediately at the ladder price.
update_user_meta( 20, 'prx3_adult_confirmed', time() );
$sig = 'data:image/png;base64,' . base64_encode( "\x89PNG\r\n\x1a\n" . str_repeat( 'x', 300 ) );
PRX3_Agreements::record_acceptance( 20, 'checkout', 0, $sig );
$order  = array(
	'id'         => '9001',
	'email'      => 'Buyer@example.test',
	'line_items' => array(
		array( 'variant_id' => 'v1', 'quantity' => 1, 'price' => '50.00' ),
		array( 'variant_id' => 'v2', 'quantity' => 1, 'price' => '62.50' ),
	),
);
$result = PRX3_Shopify::process_order( $order );
t_eq( $result['status'], 'granted', 'Signed member granted on webhook' );
t_eq( prx3_shares( 20 ), 2, 'Two shares granted' );
t_eq( PRX3_Shopify::process_order( $order )['status'], 'duplicate', 'Webhook retries are idempotent' );

// Price mismatch is held, never granted.
$bad    = array(
	'id'         => '9002',
	'email'      => 'buyer@example.test',
	'line_items' => array( array( 'variant_id' => 'v3', 'quantity' => 1, 'price' => '1.00' ) ),
);
$result = PRX3_Shopify::process_order( $bad );
t_eq( $result['status'], 'price_mismatch', 'Underpaid order held for review' );
t_eq( prx3_shares( 20 ), 2, 'No shares granted on mismatch' );

// Unknown buyer waits for signature, then claims on signing.
$order2 = array(
	'id'         => '9003',
	'email'      => 'new@example.test',
	'line_items' => array( array( 'variant_id' => 'v1', 'quantity' => 1, 'price' => '50.00' ) ),
);
t_eq( PRX3_Shopify::process_order( $order2 )['status'], 'pending_signature', 'Unknown buyer goes to pending' );
t_eq( PRX3_Shopify::pending_for( 'new@example.test' ), 1, 'Pending shares visible' );
prx3_test_user( 21, array( 'user_email' => 'new@example.test' ) );
update_user_meta( 21, 'prx3_adult_confirmed', time() );
PRX3_Agreements::record_acceptance( 21, 'reaccept', 0, $sig ); // Fires prx3_sha_accepted -> claim.
t_eq( prx3_shares( 21 ), 1, 'Signing the agreement claims the purchase' );
t_eq( PRX3_Shopify::pending_for( 'new@example.test' ), 0, 'Pending cleared after claim' );

// Gift line issues a code instead of granting the buyer.
$order3 = array(
	'id'         => '9004',
	'email'      => 'buyer@example.test',
	'line_items' => array(
		array(
			'variant_id' => 'v1',
			'quantity'   => 1,
			'price'      => '50.00',
			'properties' => array(
				array( 'name' => 'gift', 'value' => '1' ),
				array( 'name' => 'recipient_email', 'value' => 'kid@example.test' ),
			),
		),
	),
);
t_eq( PRX3_Shopify::process_order( $order3 )['status'], 'gift_issued', 'Gift order issues a code' );
$gifts = get_option( 'prx3_gift_codes', array() );
t_eq( count( $gifts ), 1, 'One gift code minted' );
t_eq( prx3_shares( 20 ), 2, 'Gift does not change the buyer holding' );

// Refund claws back the granted shares and voids the gift code.
t_eq( PRX3_Shopify::process_refund( array( 'order_id' => '9001' ) )['status'], 'clawed_back', 'Refund processed' );
t_eq( prx3_shares( 20 ), 0, 'Refunded shares surrendered' );
t_eq( PRX3_Shopify::process_refund( array( 'order_id' => '9001' ) )['status'], 'duplicate', 'Refund idempotent' );
PRX3_Shopify::process_refund( array( 'order_id' => '9004' ) );
$gifts = get_option( 'prx3_gift_codes', array() );
t_eq( empty( array_filter( $gifts, fn( $g ) => empty( $g['voided'] ) ) ), true, 'Gift code voided on refund' );
$surrenders = array_filter( PRX3_Register::$records, fn( $r ) => 'surrender' === $r['event'] );
t_eq( count( $surrenders ), 1, 'Surrender recorded in the register' );

// Commerce seam ops (P107): counts, chasing, reassign, release, drop.
$held = PRX3_Shopify::counts();
t_eq( $held['held'] >= 1, true, 'Held count includes the mismatched order' );

// Backdate the unclaimed gift-era pending? Create a fresh unclaimed purchase and backdate it.
$order4 = array(
	'id'         => '9005',
	'email'      => 'slow@example.test',
	'line_items' => array( array( 'variant_id' => 'v1', 'quantity' => 1, 'price' => '50.00' ) ),
);
PRX3_Shopify::process_order( $order4 );
$pending = get_option( 'prx3_shopify_pending', array() );
$pending['slow@example.test'][0]['at'] = gmdate( 'Y-m-d H:i:s', time() - 11 * DAY_IN_SECONDS );
update_option( 'prx3_shopify_pending', $pending, false );
PRX3_Shopify::daily_ops();
$pending = get_option( 'prx3_shopify_pending', array() );
t_eq( $pending['slow@example.test'][0]['reminded'], array( 3, 10 ), 'Both reminders recorded for an 11-day-old purchase' );

// Reassign to a signed member claims immediately.
t_eq( PRX3_Shopify::reassign_pending( 'slow@example.test', '9005', 'new@example.test' ), true, 'Reassign moves the purchase' );
t_eq( prx3_shares( 21 ), 2, 'Reassigned purchase claimed by the signed member' );

// Release a held order after review.
$release = PRX3_Shopify::release_pending( 'buyer@example.test', '9002' );
t_eq( $release, true, 'Held order released after review' );
t_eq( prx3_shares( 20 ), 1, 'Release granted through the money path' );
t_eq( PRX3_Shopify::counts()['held'], 0, 'No held orders after release' );

// Drop removes a pending entry.
$order5 = array(
	'id'         => '9006',
	'email'      => 'gone@example.test',
	'line_items' => array( array( 'variant_id' => 'v1', 'quantity' => 1, 'price' => '50.00' ) ),
);
PRX3_Shopify::process_order( $order5 );
t_eq( PRX3_Shopify::drop_pending( 'gone@example.test', '9006' ), true, 'Refunded pending dropped' );
t_eq( PRX3_Shopify::pending_for( 'gone@example.test' ), 0, 'Dropped purchase no longer pending' );
