<?php
/**
 * Legal documents supplied to members, with versioned acceptance.
 *
 * The Shareholders' Agreement (with its morality/conduct clauses) and
 * the Terms of Membership are WordPress pages the club controls. This
 * module:
 *  - requires explicit acceptance of the current agreement version at
 *    the share checkout and at gift redemption (becoming a shareholder
 *    is what triggers it);
 *  - records every acceptance permanently (version, timestamp, IP,
 *    order) — the evidential record that a member agreed;
 *  - links the documents from the confirmation email and the member's
 *    account page (printable pages; print-to-PDF for personal copies);
 *  - when the club publishes a new version, banners existing owners
 *    until they re-accept, and records that too.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Agreements {

	public static function init() {
		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'checkout_checkbox' ) );
			add_action( 'woocommerce_checkout_process', array( __CLASS__, 'checkout_validate' ) );
			add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'record_from_order' ), 10, 1 );
		}
		add_action( 'admin_post_prx3_accept_agreement', array( __CLASS__, 'handle_reaccept' ) );
		add_action( 'admin_post_nopriv_prx3_accept_agreement', '__return_false' );
		add_action( 'init', array( __CLASS__, 'register_endpoint' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_executed' ) );
		add_action( 'admin_menu', array( __CLASS__, 'board_menu' ), 20 );
	}

	public static function register_endpoint() {
		add_rewrite_rule( '^my-agreement/?$', 'index.php?prx3_agreement=1', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'prx3_agreement';
		return $vars;
	}

	public static function version() {
		return (string) prx3_setting( 'sha_version', '1.0' );
	}

	public static function agreement_url() {
		$page_id = (int) prx3_setting( 'sha_page_id', 0 );
		return $page_id && 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : '';
	}

	/**
	 * Does the cart contain shares (own purchase or gift)?
	 */
	private static function cart_has_shares() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( PRX3_WooCommerce::share_product_id() && PRX3_WooCommerce::share_product_id() === (int) $item['product_id'] ) {
				return true;
			}
		}
		return false;
	}

	public static function checkout_checkbox() {
		if ( ! self::cart_has_shares() ) {
			return;
		}
		$url = self::agreement_url();
		woocommerce_form_field(
			'prx3_sha_accept',
			array(
				'type'     => 'checkbox',
				'class'    => array( 'form-row', 'prx3-sha' ),
				'required' => true,
				'label'    => $url
					? sprintf(
						/* translators: 1: URL, 2: version. */
						__( 'I have read and agree to the <a href="%1$s" target="_blank" rel="noopener">Shareholders\' Agreement</a> (v%2$s), including its conduct and morality provisions.', 'fan-ownership' ),
						esc_url( $url ),
						esc_html( self::version() )
					)
					: sprintf(
						/* translators: %s version. */
						__( 'I agree to the Shareholders\' Agreement (v%s), including its conduct and morality provisions.', 'fan-ownership' ),
						esc_html( self::version() )
					),
			)
		);
		self::signature_field();
	}

	/**
	 * The signature capture field (canvas pad rendered by JS, PNG data
	 * URL posted in the hidden input). Shared by checkout, gift
	 * redemption, and the re-accept form.
	 */
	public static function signature_field() {
		wp_enqueue_script( 'prx3-signature' );
		echo '<div class="prx3-sigpad" data-label="' . esc_attr__( 'Draw your signature', 'fan-ownership' ) . '">';
		echo '<input type="hidden" class="prx3-sig-value" name="prx3_sha_signature" value="">';
		echo '<p class="description">' . esc_html__( 'Sign in the box above with your finger, stylus, or mouse — your signature is placed on your executed copy of the agreement.', 'fan-ownership' ) . '</p>';
		echo '<p><button type="button" class="prx3-sig-clear button">' . esc_html__( 'Clear signature', 'fan-ownership' ) . '</button></p>';
		echo '<noscript><p>' . esc_html__( 'JavaScript is required to sign the agreement.', 'fan-ownership' ) . '</p></noscript>';
		echo '</div>';
	}

	/**
	 * Validate and return the posted signature as a safe PNG data URL,
	 * or '' when missing/invalid. Callers verify their own nonce
	 * (WooCommerce checkout, gift-redeem, and re-accept forms all do).
	 */
	public static function posted_signature() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- every calling form verifies its own nonce before acting.
		if ( empty( $_POST['prx3_sha_signature'] ) || ! is_string( $_POST['prx3_sha_signature'] ) ) {
			return '';
		}
		$raw = wp_unslash( $_POST['prx3_sha_signature'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated byte-for-byte below.
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$prefix = 'data:image/png;base64,';
		if ( 0 !== strpos( $raw, $prefix ) || strlen( $raw ) > 300000 ) {
			return '';
		}
		$decoded = base64_decode( substr( $raw, strlen( $prefix ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding a canvas PNG for validation.
		if ( false === $decoded || strlen( $decoded ) < 200 || "\x89PNG" !== substr( $decoded, 0, 4 ) ) {
			return '';
		}
		return $prefix . base64_encode( $decoded ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- re-encoding the validated signature PNG only.
	}

	public static function checkout_validate() {
		if ( ! self::cart_has_shares() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout carries its own nonce.
		if ( empty( $_POST['prx3_sha_accept'] ) ) {
			wc_add_notice( __( 'Please read and accept the Shareholders\' Agreement to buy shares.', 'fan-ownership' ), 'error' );
		}
		if ( ! self::posted_signature() ) {
			wc_add_notice( __( 'Please sign in the signature box — your signature goes on your executed copy of the Shareholders\' Agreement.', 'fan-ownership' ), 'error' );
		}
	}

	public static function record_from_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_user_id() ) {
			return;
		}
		// Only record when shares are in the order.
		$has_shares = false;
		foreach ( $order->get_items() as $item ) {
			if ( PRX3_WooCommerce::share_product_id() && (int) $item->get_product_id() === PRX3_WooCommerce::share_product_id() ) {
				$has_shares = true;
			}
		}
		if ( $has_shares ) {
			self::record_acceptance( $order->get_user_id(), 'checkout', $order_id, self::posted_signature() );
		}
	}

	/**
	 * The permanent acceptance record: append-only user meta + audit.
	 * The member's drawn signature (validated PNG data URL) is kept so
	 * it can be placed on their executed copy and shown in the board
	 * signatures register.
	 *
	 * @param int    $user_id Member.
	 * @param string $context 'checkout'|'gift_redemption'|'reaccept'.
	 * @param int    $order_id Optional order reference.
	 * @param string $signature Validated PNG data URL from posted_signature().
	 */
	public static function record_acceptance( $user_id, $context, $order_id = 0, $signature = '' ) {
		if ( $signature ) {
			update_user_meta( $user_id, 'prx3_sha_signature', $signature );
		}
		$log   = (array) get_user_meta( $user_id, 'prx3_sha_acceptances', true );
		$log[] = array(
			'version' => self::version(),
			'at'      => prx3_now(),
			'ip'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'context' => sanitize_key( $context ),
			'order'   => (int) $order_id,
			'signed'  => (bool) $signature,
		);
		update_user_meta( $user_id, 'prx3_sha_acceptances', $log );
		update_user_meta( $user_id, 'prx3_sha_version', self::version() );
		PRX3_Audit::log( 'sha_accepted', sprintf( 'User %d accepted Shareholders\' Agreement v%s (%s)', $user_id, self::version(), $context ) );
		do_action( 'prx3_sha_accepted', $user_id, $context );
	}

	/**
	 * Has this owner accepted the current version?
	 */
	public static function is_current( $user_id ) {
		return get_user_meta( $user_id, 'prx3_sha_version', true ) === self::version();
	}

	/**
	 * Owner-facing status block for the account page: accepted version,
	 * document links, and a re-accept button when a new version ships.
	 */
	public static function account_block( $user_id ) {
		$url     = self::agreement_url();
		$version = get_user_meta( $user_id, 'prx3_sha_version', true );
		$html    = '<h3>' . esc_html__( 'My documents', 'fan-ownership' ) . '</h3><ul>';
		if ( $url ) {
			$html .= '<li><a href="' . esc_url( $url ) . '">' . esc_html__( 'Shareholders\' Agreement', 'fan-ownership' ) . '</a> — '
				. esc_html(
					$version
						? sprintf( /* translators: %s version. */ __( 'you accepted v%s', 'fan-ownership' ), $version )
						: __( 'not yet accepted', 'fan-ownership' )
				) . '</li>';
			if ( $version ) {
				$html .= '<li><a href="' . esc_url( home_url( '/my-agreement/' ) ) . '">' . esc_html__( 'View / print my executed copy (with signatures and the club stamp)', 'fan-ownership' ) . '</a></li>';
			}
		}
		$terms = (int) prx3_setting( 'terms_page_id', 0 );
		if ( $terms && 'publish' === get_post_status( $terms ) ) {
			$html .= '<li><a href="' . esc_url( get_permalink( $terms ) ) . '">' . esc_html__( 'Terms of Membership', 'fan-ownership' ) . '</a></li>';
		}
		$html .= '<li>' . esc_html__( 'Open a document and use your browser\'s print / save-as-PDF for your personal copy.', 'fan-ownership' ) . '</li></ul>';

		if ( prx3_is_owner( $user_id ) && $url && ! self::is_current( $user_id ) ) {
			$html .= '<div class="prx3-notice" role="status"><p>' . esc_html(
				sprintf(
					/* translators: %s version. */
					__( 'The Shareholders\' Agreement has been updated to v%s. Please read and re-accept it.', 'fan-ownership' ),
					self::version()
				)
			) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$html .= wp_nonce_field( 'prx3_accept_agreement', '_wpnonce', true, false );
			$html .= '<input type="hidden" name="action" value="prx3_accept_agreement">';
			$html .= '<p><label><input type="checkbox" name="prx3_sha_accept" value="1" required> ' . esc_html__( 'I have read and accept the updated agreement.', 'fan-ownership' ) . '</label></p>';
			ob_start();
			self::signature_field();
			$html .= ob_get_clean();
			$html .= '<p><button class="prx3-button" type="submit">' . esc_html__( 'Accept', 'fan-ownership' ) . '</button></p></form></div>';
		}
		return $html;
	}

	public static function handle_reaccept() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please sign in.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_accept_agreement' );
		if ( empty( $_POST['prx3_sha_accept'] ) ) {
			wp_die( esc_html__( 'Please tick the acceptance box.', 'fan-ownership' ) );
		}
		$signature = self::posted_signature();
		if ( ! $signature ) {
			wp_die( esc_html__( 'Please sign in the signature box before accepting.', 'fan-ownership' ) );
		}
		self::record_acceptance( get_current_user_id(), 'reaccept', 0, $signature );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	/* ---------------- Executed copy: /my-agreement/ ---------------- */

	/**
	 * The member's personalised executed copy: the full agreement text
	 * with an execution block — member name, owner number, acceptance
	 * date, the member's drawn signature, the club stamp, and the board
	 * signatory's countersignature. Print-to-PDF for the personal copy.
	 */
	public static function maybe_render_executed() {
		if ( ! get_query_var( 'prx3_agreement' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		$member_id = get_current_user_id();
		// Board members and admins may open a specific member's copy from the signatures register.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view; access is capability-gated.
		$requested = isset( $_GET['member'] ) ? (int) $_GET['member'] : 0;
		if ( $requested && $requested !== $member_id ) {
			if ( ! current_user_can( 'prx3_board' ) && ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You can only view your own agreement.', 'fan-ownership' ) );
			}
			$member_id = $requested;
		}
		$member = get_userdata( $member_id );
		$log    = array_filter( (array) get_user_meta( $member_id, 'prx3_sha_acceptances', true ) );
		if ( ! $member || ! $log ) {
			wp_die( esc_html__( 'No agreement acceptance is recorded for this account yet.', 'fan-ownership' ) );
		}
		$latest    = end( $log );
		$page_id   = (int) prx3_setting( 'sha_page_id', 0 );
		$page      = $page_id ? get_post( $page_id ) : null;
		$body      = $page && 'publish' === $page->post_status ? apply_filters( 'the_content', $page->post_content ) : '<p>' . esc_html__( 'The agreement text has not been published yet.', 'fan-ownership' ) . '</p>'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- applying core's the_content filter to render the page, not defining a hook.
		$signature = (string) get_user_meta( $member_id, 'prx3_sha_signature', true );
		$stamp     = prx3_brand_asset( 'club_stamp' );
		$badge     = prx3_brand_asset( 'badge' );
		$signatory = array(
			'name' => (string) prx3_setting( 'sha_signatory_name', '' ),
			'role' => (string) prx3_setting( 'sha_signatory_role', __( 'Director', 'fan-ownership' ) ),
			'sig'  => wp_get_attachment_url( (int) prx3_setting( 'sha_signatory_signature_id', 0 ) ),
		);

		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( sprintf( /* translators: %s club name. */ __( 'Shareholders\' Agreement — %s', 'fan-ownership' ), prx3_club_name() ) ); ?></title>
<style>
	body { font-family: Georgia, 'Times New Roman', serif; color: #111; max-width: 760px; margin: 2rem auto; padding: 0 1.25rem; line-height: 1.55; }
	header.prx3-doc { text-align: center; border-bottom: 3px double #333; padding-bottom: 1rem; margin-bottom: 1.5rem; }
	header.prx3-doc img { max-height: 90px; }
	.prx3-exec { border: 2px solid #333; padding: 1.25rem 1.5rem; margin-top: 2rem; page-break-inside: avoid; position: relative; }
	.prx3-exec table { width: 100%; border-collapse: collapse; }
	.prx3-exec td { padding: .4rem .5rem; vertical-align: bottom; }
	.prx3-sig-img { max-height: 80px; display: block; }
	.prx3-stamp { position: absolute; right: 1.5rem; top: -40px; max-height: 130px; opacity: .9; transform: rotate(-8deg); }
	.prx3-sigline { border-bottom: 1px solid #333; min-height: 90px; }
	.prx3-note { font-size: .85rem; color: #444; margin-top: 1.25rem; }
	.prx3-print { text-align: center; margin: 1.5rem 0; }
	@media print { .prx3-print { display: none; } body { margin: 0 auto; } }
</style>
</head>
<body>
<header class="prx3-doc">
		<?php if ( $badge ) : ?>
		<img src="<?php echo esc_url( $badge ); ?>" alt="<?php echo esc_attr( prx3_club_name() ); ?>">
	<?php endif; ?>
	<h1><?php echo esc_html( sprintf( /* translators: %s club name. */ __( '%s — Shareholders\' Agreement', 'fan-ownership' ), prx3_club_name() ) ); ?></h1>
	<p><?php echo esc_html( sprintf( /* translators: 1: version, 2: owner number, 3: name. */ __( 'Executed copy — v%1$s — Owner #%2$d — %3$s', 'fan-ownership' ), $latest['version'], PRX3_Shares::owner_number( $member_id ), $member->display_name ) ); ?></p>
</header>
<div class="prx3-print"><button onclick="window.print()"><?php esc_html_e( 'Print / save as PDF', 'fan-ownership' ); ?></button></div>
<main><?php echo wp_kses_post( $body ); ?></main>
<section class="prx3-exec">
		<?php if ( $stamp ) : ?>
		<img class="prx3-stamp" src="<?php echo esc_url( $stamp ); ?>" alt="<?php esc_attr_e( 'Club stamp', 'fan-ownership' ); ?>">
	<?php endif; ?>
	<h2><?php esc_html_e( 'Execution', 'fan-ownership' ); ?></h2>
	<table>
		<tr>
			<td style="width:50%">
				<div class="prx3-sigline">
					<?php if ( $signature ) : ?>
						<img class="prx3-sig-img" src="<?php echo esc_attr( $signature ); ?>" alt="<?php esc_attr_e( 'Member signature', 'fan-ownership' ); ?>">
					<?php endif; ?>
				</div>
				<strong><?php echo esc_html( $member->display_name ); ?></strong><br>
				<?php echo esc_html( sprintf( /* translators: %d owner number. */ __( 'Member — Owner #%d', 'fan-ownership' ), PRX3_Shares::owner_number( $member_id ) ) ); ?><br>
				<?php echo esc_html( sprintf( /* translators: %s date. */ __( 'Signed: %s', 'fan-ownership' ), $latest['at'] ) ); ?>
			</td>
			<td style="width:50%">
				<div class="prx3-sigline">
					<?php if ( $signatory['sig'] ) : ?>
						<img class="prx3-sig-img" src="<?php echo esc_url( $signatory['sig'] ); ?>" alt="<?php esc_attr_e( 'Club signatory signature', 'fan-ownership' ); ?>">
					<?php endif; ?>
				</div>
				<strong><?php echo esc_html( $signatory['name'] ? $signatory['name'] : __( '(signatory not configured)', 'fan-ownership' ) ); ?></strong><br>
				<?php echo esc_html( sprintf( /* translators: 1: role, 2: club. */ __( '%1$s, for and on behalf of %2$s', 'fan-ownership' ), $signatory['role'], prx3_club_name() ) ); ?>
			</td>
		</tr>
	</table>
	<p class="prx3-note">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: version, 2: date, 3: context. */
				__( 'Executed by electronic signature. Acceptance of v%1$s recorded %2$s (%3$s); the club retains a permanent log of every acceptance (version, date, IP address, order reference) and the member\'s signature.', 'fan-ownership' ),
				$latest['version'],
				$latest['at'],
				$latest['context']
			)
		);
		if ( self::version() !== $latest['version'] ) {
			echo ' ' . esc_html( sprintf( /* translators: %s version. */ __( 'Note: the current published version is v%s — please re-accept from your account page.', 'fan-ownership' ), self::version() ) );
		}
		?>
	</p>
</section>
</body>
</html>
		<?php
		exit;
	}

	/* ---------------- Board area: owner signatures register ---------------- */

	public static function board_menu() {
		add_submenu_page(
			'prx3-board',
			__( 'Owner Signatures', 'fan-ownership' ),
			__( 'Owner Signatures', 'fan-ownership' ),
			'prx3_board',
			'prx3-signatures',
			array( __CLASS__, 'render_signatures_register' )
		);
	}

	public static function render_signatures_register() {
		if ( ! current_user_can( 'prx3_board' ) ) {
			wp_die( esc_html__( 'Board access required.', 'fan-ownership' ) );
		}
		$owners = get_users(
			array(
				'meta_key'     => 'prx3_sha_version', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- board register, admin-only screen.
				'meta_compare' => 'EXISTS',
				'orderby'      => 'display_name',
				'number'       => 500,
			)
		);
		echo '<div class="wrap"><h1>' . esc_html__( 'Owner Signatures', 'fan-ownership' ) . '</h1>';
		echo '<p>' . esc_html__( 'Every member\'s Shareholders\' Agreement signature and acceptance history. Personal data — board eyes only; do not export or share outside the board.', 'fan-ownership' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Owner #', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Member', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Accepted version', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Acceptances', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Last accepted', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Signature', 'fan-ownership' ) . '</th><th>' . esc_html__( 'Executed copy', 'fan-ownership' ) . '</th></tr></thead><tbody>';
		foreach ( $owners as $owner ) {
			$log       = array_filter( (array) get_user_meta( $owner->ID, 'prx3_sha_acceptances', true ) );
			$latest    = $log ? end( $log ) : array();
			$signature = (string) get_user_meta( $owner->ID, 'prx3_sha_signature', true );
			echo '<tr>';
			echo '<td>' . (int) PRX3_Shares::owner_number( $owner->ID ) . '</td>';
			echo '<td>' . esc_html( $owner->display_name ) . '<br><span class="description">' . esc_html( $owner->user_email ) . '</span></td>';
			echo '<td>' . esc_html( get_user_meta( $owner->ID, 'prx3_sha_version', true ) ) . ( self::is_current( $owner->ID ) ? '' : ' <strong>' . esc_html__( '(out of date)', 'fan-ownership' ) . '</strong>' ) . '</td>';
			echo '<td>' . count( $log ) . '</td>';
			echo '<td>' . esc_html( isset( $latest['at'] ) ? $latest['at'] . ' (' . $latest['context'] . ')' : '—' ) . '</td>';
			echo '<td>' . ( $signature ? '<img src="' . esc_attr( $signature ) . '" alt="" style="max-height:40px;background:#fff;border:1px solid #ccc">' : esc_html__( 'none on file', 'fan-ownership' ) ) . '</td>';
			echo '<td><a href="' . esc_url( add_query_arg( 'member', $owner->ID, home_url( '/my-agreement/' ) ) ) . '">' . esc_html__( 'Open', 'fan-ownership' ) . '</a></td>';
			echo '</tr>';
		}
		if ( ! $owners ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No acceptances recorded yet.', 'fan-ownership' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
