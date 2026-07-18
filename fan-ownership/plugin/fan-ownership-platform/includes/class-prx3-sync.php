<?php
/**
 * Power Platform sync: bidirectional data exchange with Dataverse.
 *
 * Contract (FO-124/FO-125, P99–P102):
 *  - Outbound: member/share/agreement/badge changes are queued and
 *    delivered to a Power Automate HTTP trigger as signed webhooks
 *    (HMAC-SHA256 over the body); a paged delta API lets flows pull
 *    everything modified since a timestamp, and the share register
 *    streams as an append-only feed by row id.
 *  - Inbound: Dataverse upserts CRM-owned enrichment fields through
 *    /sync/upsert with matching rules — cross-reference ID first, then
 *    verified email, then owner number. No fuzzy matching, no
 *    auto-create, no auto-merge: anything ambiguous queues for human
 *    review in the admin.
 *  - Field-level ownership: WordPress owns what it mints (shares,
 *    votes, acceptances, owner numbers); Dataverse owns CRM enrichment
 *    (phone, address, marketing consents, notes). Inbound writes are
 *    limited to the enrichment allow-list; nothing can write shares.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Sync {

	const OUTBOX_OPTION = 'prx3_sync_outbox';
	const REVIEW_OPTION = 'prx3_sync_review';
	const MAX_TRIES     = 8;
	const OUTBOX_CAP    = 500;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );

		// Outbound change signals → touch + queue.
		add_action( 'user_register', array( __CLASS__, 'on_member_changed' ) );
		add_action( 'profile_update', array( __CLASS__, 'on_member_changed' ) );
		add_action( 'prx3_shares_granted', array( __CLASS__, 'on_shares_changed' ) );
		add_action( 'prx3_shares_surrendered', array( __CLASS__, 'on_shares_changed' ) );
		add_action( 'prx3_sha_accepted', array( __CLASS__, 'on_agreement_accepted' ) );
		add_action( 'prx3_award_badge', array( __CLASS__, 'on_member_changed' ), 20 );

		add_action( 'prx3_ballot_tick', array( __CLASS__, 'deliver_outbox' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_post_prx3_sync_resolve', array( __CLASS__, 'handle_resolve' ) );
	}

	public static function enabled() {
		return (int) prx3_setting( 'sync_enabled', 0 ) && '' !== (string) prx3_setting( 'sync_api_key', '' );
	}

	/* ---------------- Change tracking + outbox ---------------- */

	public static function on_member_changed( $user_id ) {
		self::touch_and_queue( (int) $user_id, 'member_updated' );
	}

	public static function on_shares_changed( $user_id ) {
		self::touch_and_queue( (int) $user_id, 'shares_changed' );
	}

	public static function on_agreement_accepted( $user_id ) {
		self::touch_and_queue( (int) $user_id, 'agreement_accepted' );
	}

	private static function touch_and_queue( $user_id, $event ) {
		if ( ! $user_id ) {
			return;
		}
		update_user_meta( $user_id, 'prx3_sync_touched', time() );
		if ( ! self::enabled() || '' === (string) prx3_setting( 'sync_webhook_url', '' ) ) {
			return;
		}
		$outbox   = (array) get_option( self::OUTBOX_OPTION, array() );
		$outbox[] = array(
			'event'   => $event,
			'user_id' => $user_id,
			'at'      => prx3_now(),
			'tries'   => 0,
		);
		if ( count( $outbox ) > self::OUTBOX_CAP ) {
			$outbox = array_slice( $outbox, -self::OUTBOX_CAP );
			PRX3_Audit::log( 'sync_outbox_capped', 'Sync outbox exceeded cap; oldest events dropped' );
		}
		update_option( self::OUTBOX_OPTION, $outbox, false );
	}

	/**
	 * Deliver queued events to the Power Automate HTTP trigger, signed.
	 * Runs on the 5-minute tick; failed deliveries retry next tick up
	 * to MAX_TRIES, then drop with an audit entry.
	 */
	public static function deliver_outbox() {
		if ( ! self::enabled() ) {
			return;
		}
		$url = (string) prx3_setting( 'sync_webhook_url', '' );
		if ( '' === $url ) {
			return;
		}
		$outbox = (array) get_option( self::OUTBOX_OPTION, array() );
		if ( ! $outbox ) {
			return;
		}
		$secret = (string) prx3_setting( 'sync_webhook_secret', '' );
		$keep   = array();
		$batch  = 0;
		foreach ( $outbox as $item ) {
			if ( $batch >= 20 ) {
				$keep[] = $item;
				continue;
			}
			++$batch;
			$body    = wp_json_encode(
				array(
					'event'  => $item['event'],
					'at'     => $item['at'],
					'member' => self::member_payload( (int) $item['user_id'] ),
				)
			);
			$headers = array( 'Content-Type' => 'application/json' );
			if ( $secret ) {
				$headers['X-Prx3-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
			}
			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 5,
					'headers' => $headers,
					'body'    => $body,
				)
			);
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code > 299 ) {
				++$item['tries'];
				if ( $item['tries'] >= self::MAX_TRIES ) {
					PRX3_Audit::log( 'sync_delivery_dropped', sprintf( 'Sync event %s for user %d dropped after %d attempts', $item['event'], $item['user_id'], $item['tries'] ) );
				} else {
					$keep[] = $item;
				}
			}
		}
		update_option( self::OUTBOX_OPTION, array_values( $keep ), false );
	}

	/* ---------------- The member payload (outbound shape) ---------------- */

	public static function member_payload( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array(
				'id'      => $user_id,
				'deleted' => true,
			);
		}
		$log = array_filter( (array) get_user_meta( $user_id, 'prx3_sha_acceptances', true ) );
		$crm = array();
		foreach ( self::inbound_fields() as $field ) {
			$value = get_user_meta( $user_id, 'prx3_crm_' . $field, true );
			if ( '' !== $value ) {
				$crm[ $field ] = $value;
			}
		}
		return array(
			'id'           => $user_id,
			'dataverse_id' => (string) get_user_meta( $user_id, 'prx3_dataverse_id', true ),
			'email'        => $user->user_email,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'display_name' => $user->display_name,
			'owner_number' => PRX3_Shares::owner_number( $user_id ),
			'shares'       => prx3_shares( $user_id ),
			'is_owner'     => prx3_is_owner( $user_id ),
			'agreement'    => array(
				'accepted_version' => (string) get_user_meta( $user_id, 'prx3_sha_version', true ),
				'current'          => class_exists( 'PRX3_Agreements' ) ? PRX3_Agreements::is_current( $user_id ) : false,
				'acceptances'      => count( $log ),
			),
			'badges'       => (array) PRX3_Badges::member_badges( $user_id ),
			'engagement'   => array(
				'ballots_voted' => count( (array) PRX3_Ballots::member_vote_history( $user_id ) ),
			),
			'crm'          => $crm,
			'registered'   => $user->user_registered,
			'modified'     => (int) get_user_meta( $user_id, 'prx3_sync_touched', true ),
		);
	}

	/**
	 * The Dataverse-owned enrichment fields inbound writes may touch.
	 * Everything else — shares, votes, acceptances, owner numbers — is
	 * WordPress-owned and rejected.
	 */
	public static function inbound_fields() {
		return apply_filters(
			'prx3_sync_inbound_fields',
			array( 'phone', 'mobile', 'address_line1', 'address_line2', 'city', 'postcode', 'country', 'marketing_email_optin', 'marketing_sms_optin', 'crm_notes' )
		);
	}

	/* ---------------- REST API ---------------- */

	public static function routes() {
		register_rest_route(
			'prx3/v1',
			'/sync/members',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'authorize' ),
				'callback'            => array( __CLASS__, 'route_members' ),
			)
		);
		register_rest_route(
			'prx3/v1',
			'/sync/register',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'authorize' ),
				'callback'            => array( __CLASS__, 'route_register' ),
			)
		);
		register_rest_route(
			'prx3/v1',
			'/sync/upsert',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'authorize' ),
				'callback'            => array( __CLASS__, 'route_upsert' ),
			)
		);
		register_rest_route(
			'prx3/v1',
			'/sync/review',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'authorize' ),
				'callback'            => array( __CLASS__, 'route_review' ),
			)
		);
	}

	/**
	 * API-key auth for service-to-service calls (Power Automate).
	 */
	public static function authorize( $request ) {
		if ( ! self::enabled() ) {
			return new WP_Error( 'prx3_sync_disabled', __( 'Sync is not enabled.', 'fan-ownership' ), array( 'status' => 403 ) );
		}
		$key = (string) $request->get_header( 'X-Prx3-Api-Key' );
		if ( ! $key || ! hash_equals( (string) prx3_setting( 'sync_api_key', '' ), $key ) ) {
			return new WP_Error( 'prx3_sync_auth', __( 'Invalid API key.', 'fan-ownership' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * GET /sync/members — paged; ?modified_since= (ISO 8601 or epoch)
	 * returns only members touched since then.
	 */
	public static function route_members( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 200, max( 1, (int) ( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 100 ) ) );
		$since    = (string) $request->get_param( 'modified_since' );
		$args     = array(
			'number'  => $per_page,
			'paged'   => $page,
			'orderby' => 'ID',
			'order'   => 'ASC',
		);
		if ( $since ) {
			$ts = is_numeric( $since ) ? (int) $since : strtotime( $since );
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- service-to-service delta endpoint, paged and capped.
			$args['meta_query'] = array(
				array(
					'key'     => 'prx3_sync_touched',
					'value'   => (int) $ts,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			);
		}
		$query = new WP_User_Query( $args );
		return rest_ensure_response(
			array(
				'page'    => $page,
				'total'   => (int) $query->get_total(),
				'members' => array_map(
					function ( $user ) {
						return self::member_payload( $user->ID );
					},
					(array) $query->get_results()
				),
			)
		);
	}

	/**
	 * GET /sync/register — append-only share register feed; ?since_id=
	 * streams rows after that id (natural, gap-free delta).
	 */
	public static function route_register( $request ) {
		global $wpdb;
		$since_id = max( 0, (int) $request->get_param( 'since_id' ) );
		$rows     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- append-only feed from the custom register table.
			$wpdb->prepare( "SELECT id, recorded_at, user_id, event, shares, holding_after, source, consideration FROM {$wpdb->prefix}prx3_share_register WHERE id > %d ORDER BY id ASC LIMIT 500", $since_id ),
			ARRAY_A
		);
		$last     = $rows ? (int) end( $rows )['id'] : $since_id;
		return rest_ensure_response(
			array(
				'rows'    => $rows,
				'last_id' => $last,
				'more'    => count( $rows ) === 500,
			)
		);
	}

	/**
	 * POST /sync/upsert — inbound from Dataverse. Matching rules:
	 * 1. prx3_dataverse_id cross-reference; 2. email (case-insensitive);
	 * 3. owner number. Ambiguity or conflict queues for human review —
	 * never auto-merge, never auto-create.
	 */
	public static function route_upsert( $request ) {
		$body         = $request->get_json_params();
		$dataverse_id = isset( $body['dataverse_id'] ) ? sanitize_text_field( $body['dataverse_id'] ) : '';
		$email        = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
		$owner_number = isset( $body['owner_number'] ) ? (int) $body['owner_number'] : 0;
		$fields       = isset( $body['fields'] ) && is_array( $body['fields'] ) ? $body['fields'] : array();
		if ( ! $dataverse_id ) {
			return new WP_Error( 'prx3_sync_bad_request', __( 'dataverse_id is required.', 'fan-ownership' ), array( 'status' => 400 ) );
		}

		// Rule 1 — cross-reference ID.
		$linked = get_users(
			array(
				'meta_key'   => 'prx3_dataverse_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- exact-match lookup on the cross-reference key.
				'meta_value' => $dataverse_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 2,
				'fields'     => 'ID',
			)
		);
		if ( count( $linked ) > 1 ) {
			return self::to_review( 'duplicate_link', $body, __( 'More than one member holds this Dataverse ID.', 'fan-ownership' ) );
		}
		$user_id    = $linked ? (int) $linked[0] : 0;
		$matched_by = $user_id ? 'dataverse_id' : '';

		// Rule 2 — email.
		if ( ! $user_id && $email ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$user_id    = $user->ID;
				$matched_by = 'email';
			}
		}

		// Rule 3 — owner number.
		if ( ! $user_id && $owner_number ) {
			$by_number = get_users(
				array(
					'meta_key'   => 'prx3_owner_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- exact-match lookup on the owner number.
					'meta_value' => $owner_number, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'number'     => 2,
					'fields'     => 'ID',
				)
			);
			if ( 1 === count( $by_number ) ) {
				$user_id    = (int) $by_number[0];
				$matched_by = 'owner_number';
			}
		}

		if ( ! $user_id ) {
			return self::to_review( 'no_match', $body, __( 'No member matched by ID, email, or owner number.', 'fan-ownership' ) );
		}

		// Conflict: matched member already linked to a different Dataverse record.
		$existing_link = (string) get_user_meta( $user_id, 'prx3_dataverse_id', true );
		if ( $existing_link && $existing_link !== $dataverse_id ) {
			return self::to_review( 'link_conflict', $body, __( 'Matched member is already linked to a different Dataverse record.', 'fan-ownership' ) );
		}

		$ignored = self::apply_inbound( $user_id, $dataverse_id, $fields );
		PRX3_Audit::log( 'sync_upsert', sprintf( 'Dataverse upsert applied to user %d (matched by %s)', $user_id, $matched_by ) );
		return rest_ensure_response(
			array(
				'status'     => 'linked',
				'user_id'    => $user_id,
				'matched_by' => $matched_by,
				'ignored'    => $ignored,
			)
		);
	}

	public static function route_review( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- REST signature.
		return rest_ensure_response( array( 'review' => array_values( (array) get_option( self::REVIEW_OPTION, array() ) ) ) );
	}

	/**
	 * Link + write the enrichment fields. Returns the rejected field
	 * names (anything outside the allow-list — field-level ownership).
	 */
	private static function apply_inbound( $user_id, $dataverse_id, $fields ) {
		update_user_meta( $user_id, 'prx3_dataverse_id', $dataverse_id );
		$allowed = self::inbound_fields();
		$ignored = array();
		foreach ( $fields as $field => $value ) {
			$field = sanitize_key( $field );
			if ( ! in_array( $field, $allowed, true ) ) {
				$ignored[] = $field;
				continue;
			}
			update_user_meta( $user_id, 'prx3_crm_' . $field, sanitize_text_field( (string) $value ) );
		}
		// Touch only — no outbound echo, so inbound writes cannot loop.
		update_user_meta( $user_id, 'prx3_sync_touched', time() );
		return $ignored;
	}

	private static function to_review( $reason, $payload, $message ) {
		$review         = (array) get_option( self::REVIEW_OPTION, array() );
		$key            = substr( md5( wp_json_encode( $payload ) ), 0, 12 );
		$review[ $key ] = array(
			'key'     => $key,
			'reason'  => $reason,
			'message' => $message,
			'payload' => $payload,
			'at'      => prx3_now(),
		);
		update_option( self::REVIEW_OPTION, $review, false );
		PRX3_Audit::log( 'sync_review_queued', sprintf( 'Inbound sync queued for review (%s)', $reason ) );
		return rest_ensure_response(
			array(
				'status' => 'review',
				'reason' => $reason,
				'key'    => $key,
			)
		);
	}

	/* ---------------- Admin: sync health + review queue ---------------- */

	public static function menu() {
		add_submenu_page(
			'prx3-settings',
			__( 'CRM Sync', 'fan-ownership' ),
			__( 'CRM Sync', 'fan-ownership' ),
			'prx3_admin',
			'prx3-sync',
			array( __CLASS__, 'render_admin' )
		);
	}

	public static function render_admin() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Admin access required.', 'fan-ownership' ) );
		}
		$outbox = (array) get_option( self::OUTBOX_OPTION, array() );
		$review = (array) get_option( self::REVIEW_OPTION, array() );
		echo '<div class="wrap"><h1>' . esc_html__( 'CRM Sync (Power Platform)', 'fan-ownership' ) . '</h1>';
		echo '<p>' . esc_html(
			self::enabled()
				? sprintf( /* translators: %d queue depth. */ __( 'Sync is enabled. Outbound queue: %d event(s) awaiting delivery.', 'fan-ownership' ), count( $outbox ) )
				: __( 'Sync is disabled — set the API key and enable it under Settings → Integrations.', 'fan-ownership' )
		) . '</p>';
		echo '<h2>' . esc_html__( 'Matching review queue', 'fan-ownership' ) . '</h2>';
		if ( ! $review ) {
			echo '<p>' . esc_html__( 'Nothing awaiting review — every inbound record matched cleanly.', 'fan-ownership' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Received', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Reason', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Inbound record', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Resolve', 'fan-ownership' ) . '</th></tr></thead><tbody>';
		foreach ( $review as $entry ) {
			echo '<tr><td>' . esc_html( $entry['at'] ) . '</td>';
			echo '<td><strong>' . esc_html( $entry['reason'] ) . '</strong><br>' . esc_html( $entry['message'] ) . '</td>';
			echo '<td><code style="font-size:11px">' . esc_html( wp_json_encode( $entry['payload'] ) ) . '</code></td>';
			echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'prx3_sync_resolve' );
			echo '<input type="hidden" name="action" value="prx3_sync_resolve">';
			echo '<input type="hidden" name="entry" value="' . esc_attr( $entry['key'] ) . '">';
			echo '<p><label>' . esc_html__( 'Link to member (user ID or email):', 'fan-ownership' ) . ' <input type="text" name="member" size="20"></label></p>';
			echo '<p><button class="button button-primary" name="do" value="link">' . esc_html__( 'Link & apply', 'fan-ownership' ) . '</button> ';
			echo '<button class="button" name="do" value="discard">' . esc_html__( 'Discard', 'fan-ownership' ) . '</button></p>';
			echo '</form></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function handle_resolve() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Admin access required.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_sync_resolve' );
		$key    = isset( $_POST['entry'] ) ? sanitize_key( $_POST['entry'] ) : '';
		$do     = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		$review = (array) get_option( self::REVIEW_OPTION, array() );
		if ( ! $key || empty( $review[ $key ] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=prx3-sync' ) );
			exit;
		}
		if ( 'link' === $do ) {
			$member = isset( $_POST['member'] ) ? sanitize_text_field( wp_unslash( $_POST['member'] ) ) : '';
			$user   = is_numeric( $member ) ? get_userdata( (int) $member ) : get_user_by( 'email', $member );
			if ( ! $user ) {
				wp_die( esc_html__( 'No member found for that ID or email — go back and check.', 'fan-ownership' ) );
			}
			$payload = $review[ $key ]['payload'];
			self::apply_inbound( $user->ID, sanitize_text_field( $payload['dataverse_id'] ), isset( $payload['fields'] ) ? (array) $payload['fields'] : array() );
			PRX3_Audit::log( 'sync_review_resolved', sprintf( 'Review entry %s manually linked to user %d', $key, $user->ID ) );
		} else {
			PRX3_Audit::log( 'sync_review_discarded', sprintf( 'Review entry %s discarded', $key ) );
		}
		unset( $review[ $key ] );
		update_option( self::REVIEW_OPTION, $review, false );
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-sync' ) );
		exit;
	}
}
