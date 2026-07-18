<?php
/**
 * Owner certificates: generated instantly on purchase, re-issued on
 * top-up, publicly verifiable. FO-113, T60.
 *
 * The certificate is rendered as a print-ready HTML document (PDF via
 * the browser's print-to-PDF or a server PDF library when available via
 * the prx3_render_certificate_pdf filter). Every version is retained.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Certificates {

	public static function init() {
		add_action( 'prx3_shares_granted', array( __CLASS__, 'issue' ), 10, 4 );
		add_action( 'init', array( __CLASS__, 'register_verify_endpoint' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	/**
	 * Issue (or re-issue) the certificate whenever the holding changes.
	 * Superseded versions remain viewable (FO-113 AC2).
	 */
	public static function issue( $user_id, $count, $total, $source ) {
		$history   = get_user_meta( $user_id, 'prx3_certificates', true );
		$history   = is_array( $history ) ? $history : array();
		$verify    = strtoupper( wp_generate_password( 10, false, false ) );
		$history[] = array(
			'issued_at'    => time(),
			'shares'       => (int) $total,
			'owner_number' => PRX3_Shares::owner_number( $user_id ),
			'club_name'    => prx3_club_name(), // Historical identity preserved (FO-104 AC4).
			'verify_code'  => $verify,
		);
		update_user_meta( $user_id, 'prx3_certificates', $history );

		$codes            = get_option( 'prx3_verify_codes', array() );
		$codes[ $verify ] = array(
			'user'  => $user_id,
			'index' => count( $history ) - 1,
		);
		update_option( 'prx3_verify_codes', $codes, false );

		$user = get_userdata( $user_id );
		if ( $user ) {
			PRX3_Comms::send(
				$user->user_email,
				sprintf( /* translators: %s club. */ __( 'Your %s owner certificate', 'fan-ownership' ), prx3_club_name() ),
				sprintf(
					/* translators: 1: name, 2: owner number, 3: shares, 4: URL. */
					__( "Congratulations %1\$s — Owner #%2\$d, now holding %3\$d share(s).\n\nView and print your certificate any time: %4\$s", 'fan-ownership' ),
					$user->display_name,
					PRX3_Shares::owner_number( $user_id ),
					$total,
					home_url( '/?prx3_certificate=latest' )
				),
				'governance'
			);
		}
		// Automatic badge issue rides the same event (T24): PRX3_Badges listens too.
	}

	public static function register_verify_endpoint() {
		add_rewrite_rule( '^verify-owner/([A-Z0-9]+)/?$', 'index.php?prx3_verify_code=$matches[1]', 'top' );
		add_rewrite_tag( '%prx3_verify_code%', '([A-Z0-9]+)' );
	}

	/**
	 * Render: the member's own certificate, or the public verification page.
	 */
	public static function maybe_render() {
		// Public verification (FO-113 AC3): confirms owner number, shares,
		// date; the name only with consent (T60).
		$code = get_query_var( 'prx3_verify_code' );
		if ( $code ) {
			self::render_verification( strtoupper( $code ) );
		}
		if ( isset( $_GET['prx3_certificate'] ) && is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification
			self::render_certificate( get_current_user_id() );
		}
	}

	private static function render_verification( $code ) {
		$codes = get_option( 'prx3_verify_codes', array() );
		status_header( 200 );
		nocache_headers();
		if ( empty( $codes[ $code ] ) ) {
			wp_die( esc_html__( 'This verification code does not match any certificate.', 'fan-ownership' ), 404 );
		}
		$user_id = (int) $codes[ $code ]['user'];
		$history = get_user_meta( $user_id, 'prx3_certificates', true );
		$cert    = is_array( $history ) && isset( $history[ $codes[ $code ]['index'] ] ) ? $history[ $codes[ $code ]['index'] ] : null;
		if ( ! $cert ) {
			wp_die( esc_html__( 'This certificate is no longer on record.', 'fan-ownership' ), 404 );
		}
		$current     = prx3_shares( $user_id );
		$surrendered = 0 === $current && ! user_can( $user_id, 'prx3_member' );
		$show_name   = (bool) get_user_meta( $user_id, 'prx3_verify_show_name', true );
		$user        = get_userdata( $user_id );

		echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
			. esc_html__( 'Certificate verification', 'fan-ownership' ) . ' — ' . esc_html( prx3_club_name() ) . '</title></head><body style="font-family:system-ui;max-width:40rem;margin:3rem auto;padding:0 1rem;">';
		echo '<h1>' . esc_html( prx3_club_name() ) . ' — ' . esc_html__( 'Owner certificate verification', 'fan-ownership' ) . '</h1>';
		if ( $surrendered ) {
			// FO-113 AC4: revoked/surrendered holdings state it clearly.
			echo '<p><strong>' . esc_html__( 'This holding has been surrendered and is no longer current.', 'fan-ownership' ) . '</strong></p>';
		} else {
			echo '<p><strong>' . esc_html__( 'Verified.', 'fan-ownership' ) . '</strong></p>';
		}
		echo '<ul>';
		echo '<li>' . esc_html__( 'Owner number:', 'fan-ownership' ) . ' #' . (int) $cert['owner_number'] . '</li>';
		echo '<li>' . esc_html__( 'Shares on this certificate:', 'fan-ownership' ) . ' ' . (int) $cert['shares'] . '</li>';
		echo '<li>' . esc_html__( 'Issued:', 'fan-ownership' ) . ' ' . esc_html( date_i18n( get_option( 'date_format' ), $cert['issued_at'] ) ) . '</li>';
		if ( $show_name && $user ) {
			echo '<li>' . esc_html__( 'Owner:', 'fan-ownership' ) . ' ' . esc_html( $user->display_name ) . '</li>';
		}
		echo '</ul></body></html>';
		exit;
	}

	private static function render_certificate( $user_id ) {
		$history = get_user_meta( $user_id, 'prx3_certificates', true );
		if ( ! is_array( $history ) || ! $history ) {
			wp_die( esc_html__( 'No certificate yet — buy your first share to become an owner.', 'fan-ownership' ) );
		}
		$cert   = end( $history );
		$user   = get_userdata( $user_id );
		$verify = home_url( '/verify-owner/' . rawurlencode( $cert['verify_code'] ) . '/' );

		$html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . esc_html( $cert['club_name'] ) . ' — '
			. esc_html__( 'Owner Certificate', 'fan-ownership' ) . '</title><style>
			body{font-family:Georgia,serif;text-align:center;margin:0;padding:4rem 2rem;background:#fdfcf7;color:#1a1a2e;}
			.frame{border:6px double #1a1a2e;max-width:52rem;margin:0 auto;padding:3rem;}
			h1{font-size:2.2rem;letter-spacing:.08em;text-transform:uppercase;margin:0 0 .5rem;}
			.no{font-size:1.1rem;} .name{font-size:1.9rem;margin:1.5rem 0 .5rem;} .shares{font-size:1.2rem;}
			.verify{margin-top:2.5rem;font-size:.85rem;color:#444;} @media print{.noprint{display:none}}
			</style></head><body><div class="frame">'
			. ( prx3_brand_asset( 'badge' ) ? '<img src="' . esc_url( prx3_brand_asset( 'badge' ) ) . '" alt="" style="height:90px;margin-bottom:1rem;">' : '' )
			. '<h1>' . esc_html( $cert['club_name'] ) . '</h1>'
			. '<p class="no">' . esc_html__( 'Certificate of Fan Ownership', 'fan-ownership' ) . ' — ' . esc_html__( 'Owner', 'fan-ownership' ) . ' #' . (int) $cert['owner_number'] . '</p>'
			. '<p class="name">' . esc_html( $user->display_name ) . '</p>'
			. '<p class="shares">' . esc_html(
				sprintf(
					/* translators: 1: shares, 2: date. */
					_n( 'holds %1$d share in the club, as of %2$s', 'holds %1$d shares in the club, as of %2$s', (int) $cert['shares'], 'fan-ownership' ),
					(int) $cert['shares'],
					date_i18n( get_option( 'date_format' ), $cert['issued_at'] )
				)
			) . '</p>'
			. '<p class="verify">' . esc_html__( 'Verify this certificate:', 'fan-ownership' ) . ' ' . esc_html( $verify ) . '</p>'
			. '<p class="noprint"><button onclick="window.print()">' . esc_html__( 'Print or save as PDF', 'fan-ownership' ) . '</button></p>'
			. '</div></body></html>';

		echo apply_filters( 'prx3_render_certificate_pdf', $html, $cert, $user ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
}
