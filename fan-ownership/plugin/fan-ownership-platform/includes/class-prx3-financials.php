<?php
/**
 * Financial publishing: monthly summaries + annual accounts, in-portal
 * view only, missed-month flagging, annual report assembly.
 *
 * FO-217, FO-219. Financial documents are prx3_document posts in the
 * financial document types; the second-approval rule comes from
 * PRX3_Editorial. View-only (no downloads) is enforced by never exposing
 * the attachment URL: the document body is portal content, and any
 * attached PDF renders through a viewer route that streams inline with
 * download disposition disabled.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Financials {

	public static function init() {
		add_action( 'prx3_financial_month_check', array( __CLASS__, 'flag_missing_month' ) );
		if ( ! wp_next_scheduled( 'prx3_financial_month_check' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'prx3_financial_month_check' );
		}
		add_action( 'init', array( __CLASS__, 'viewer_endpoint' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_stream_inline' ) );
	}

	/**
	 * FO-217 AC4: a missed monthly publication flags staff and board.
	 */
	public static function flag_missing_month() {
		$day = (int) gmdate( 'j' );
		if ( $day < (int) prx3_setting( 'financial_due_day', 14 ) || get_option( 'prx3_fin_flag_' . gmdate( 'Y-m' ) ) ) {
			return;
		}
		$published = get_posts(
			array(
				'post_type'      => 'prx3_document',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'date_query'     => array( array( 'after' => gmdate( 'Y-m-01' ) ) ),
				'tax_query'      => array(
					array(
						'taxonomy' => 'prx3_document_type',
						'field'    => 'slug',
						'terms'    => array( 'monthly-summary' ),
					),
				),
			)
		);
		if ( $published ) {
			return;
		}
		update_option( 'prx3_fin_flag_' . gmdate( 'Y-m' ), time(), false );
		foreach ( get_users( array( 'role__in' => array( 'prx3_governance_officer', 'prx3_owner_admin', 'prx3_board_member', 'administrator' ) ) ) as $person ) {
			PRX3_Comms::send(
				$person->user_email,
				__( 'Monthly financial summary not yet published', 'fan-ownership' ),
				sprintf( /* translators: %s month. */ __( 'The %s income and spend summary has not been published to owners yet. The transparency commitment is monthly.', 'fan-ownership' ), date_i18n( 'F Y' ) ),
				'governance'
			);
		}
	}

	/**
	 * Inline viewer for attached statement PDFs: streams with inline
	 * disposition inside the member gate; no download route (T23).
	 */
	public static function viewer_endpoint() {
		add_rewrite_rule( '^owners/view-document/([0-9]+)/?$', 'index.php?prx3_view_doc=$matches[1]', 'top' );
		add_rewrite_tag( '%prx3_view_doc%', '([0-9]+)' );
	}

	public static function maybe_stream_inline() {
		$doc_id = absint( get_query_var( 'prx3_view_doc' ) );
		if ( ! $doc_id ) {
			return;
		}
		if ( ! prx3_is_owner() ) {
			wp_safe_redirect( PRX3_Access::join_url() );
			exit;
		}
		$attachment_id = (int) get_post_meta( $doc_id, '_prx3_attachment', true );
		$path          = $attachment_id ? get_attached_file( $attachment_id ) : '';
		if ( ! $path || ! file_exists( $path ) || 'prx3_document' !== get_post_type( $doc_id ) ) {
			wp_die( esc_html__( 'Document not found.', 'fan-ownership' ), 404 );
		}
		prx3_touch_activity();
		header( 'Content-Type: ' . get_post_mime_type( $attachment_id ) );
		header( 'Content-Disposition: inline; filename="statement"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Security-Policy: sandbox' );
		readfile( $path ); // phpcs:ignore
		exit;
	}

	/**
	 * FO-219: assemble the annual report draft from platform records.
	 *
	 * @param int $year Calendar year.
	 * @return array Report data for the drafting screen.
	 */
	public static function assemble_annual_report( $year ) {
		$from = $year . '-01-01 00:00:00';
		$to   = $year . '-12-31 23:59:59';

		$ballots   = get_posts(
			array(
				'post_type'      => 'prx3_ballot',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_prx3_state',
						'value'   => array( 'published', 'unresolved' ),
						'compare' => 'IN',
					),
				),
				'date_query'     => array(
					array(
						'after'  => $from,
						'before' => $to,
					),
				),
			)
		);
		$decisions = get_posts(
			array(
				'post_type'      => 'prx3_decision',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);
		$by_status = array();
		foreach ( $decisions as $d ) {
			$s               = get_post_meta( $d->ID, '_prx3_decision_status', true );
			$by_status[ $s ] = ( $by_status[ $s ] ?? 0 ) + 1;
		}
		global $wpdb;
		$new_owners = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}prx3_share_register WHERE event = 'acquisition' AND recorded_at BETWEEN %s AND %s",
				$from,
				$to
			)
		);
		return array(
			'year'         => $year,
			'ballots'      => array_map(
				function ( $b ) {
					return array(
						'title'  => $b->post_title,
						'result' => get_post_meta( $b->ID, '_prx3_result', true ),
					);
				},
				$ballots
			),
			'decisions'    => $by_status,
			'new_owners'   => $new_owners,
			'total_owners' => count(
				get_users(
					array(
						'role'   => 'fan_owner',
						'fields' => 'ID',
					)
				)
			),
			'target'       => 1000,
		);
	}
}
