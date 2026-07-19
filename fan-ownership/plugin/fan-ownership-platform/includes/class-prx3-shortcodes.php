<?php
/**
 * Member-facing shortcodes: the web portal surfaces. The apps use the
 * same REST API; these render the web equivalents (FO-302 parity).
 *
 * [prx3_register] [prx3_redeem_gift] [prx3_account] [prx3_ballots]
 * [prx3_ideas] [prx3_questions] [prx3_meetings] [prx3_decisions]
 * [prx3_match id] [prx3_videos] [prx3_dashboard] [prx3_board_directory]
 * [prx3_share_ladder]
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the member-facing web portal surfaces as shortcodes — join,
 * account, governance, watch and dashboard — with app parity via the
 * shared REST API (FO-302).
 */
class PRX3_Shortcodes {

	/**
	 * Register every shortcode tag against its render method.
	 */
	public static function init() {
		$codes = array(
			'prx3_register'        => 'register_form',
			'prx3_redeem_gift'     => 'redeem_form',
			'prx3_account'         => 'account',
			'prx3_ballots'         => 'ballots',
			'prx3_ideas'           => 'ideas',
			'prx3_questions'       => 'questions',
			'prx3_meetings'        => 'meetings',
			'prx3_decisions'       => 'decisions',
			'prx3_match'           => 'match',
			'prx3_videos'          => 'videos',
			'prx3_dashboard'       => 'dashboard',
			'prx3_board_directory' => 'board_directory',
			'prx3_voting_record'   => 'voting_record',
			'prx3_referrals'       => 'referrals',
			'prx3_chapters'        => 'chapters',
			'prx3_share_ladder'    => 'share_ladder',
		);
		foreach ( $codes as $tag => $method ) {
			add_shortcode( $tag, array( __CLASS__, $method ) );
		}
		add_action( 'admin_post_prx3_referral_optin', array( __CLASS__, 'handle_referral_optin' ) );
		add_action( 'admin_post_prx3_welcome_done', array( __CLASS__, 'handle_welcome_done' ) );
		// Event permalinks carry their full experience even when no
		// shortcode page exists (FO-131): ballots vote in place.
		add_filter( 'the_content', array( __CLASS__, 'single_content' ), 9 );
	}

	/**
	 * Enqueue the front-end styles and script registered at boot.
	 */
	private static function enqueue() {
		wp_enqueue_style( 'prx3' );
		wp_enqueue_script( 'prx3' );
	}

	/**
	 * Success and error notices carried on the query string after redirects.
	 *
	 * @return string Notice HTML, empty when there is nothing to show.
	 */
	private static function notices() {
		$html = '';
		if ( isset( $_GET['prx3_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$html .= '<div class="prx3-notice prx3-notice--error" role="alert">' . esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['prx3_error'] ) ) ) ) . '</div>'; // phpcs:ignore WordPress.Security.NonceVerification
		}
		if ( isset( $_GET['prx3_registered'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$html .= '<div class="prx3-notice prx3-notice--success" role="status">' . esc_html__( 'Nearly there — check your inbox and confirm your email address to activate your account.', 'fan-ownership' ) . '</div>';
		}
		if ( isset( $_GET['prx3_gift_redeemed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$html .= '<div class="prx3-notice prx3-notice--success" role="status">' . esc_html__( 'Gift redeemed — welcome aboard, owner.', 'fan-ownership' ) . '</div>';
		}
		return $html;
	}

	/**
	 * The teaser shown to non-owners in place of gated content.
	 *
	 * @return string Gate HTML, or empty for owners.
	 */
	private static function gate() {
		return prx3_is_owner() ? '' : PRX3_Access::gate_content( '' );
	}

	/* ---------------- Join ---------------- */

	/**
	 * [prx3_register] — the account creation form.
	 *
	 * @return string Form HTML.
	 */
	public static function register_form() {
		self::enqueue();
		if ( is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You are signed in already.', 'fan-ownership' ) . '</p>';
		}
		if ( ! prx3_feature_on( 'registration' ) ) {
			return PRX3_Config::unavailable_notice( 'registration' );
		}
		ob_start();
		echo self::notices(); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<form class="prx3-form" method="post">
			<?php wp_nonce_field( 'prx3_register', 'prx3_register_nonce' ); ?>
			<p><label for="prx3_name"><?php esc_html_e( 'Full name', 'fan-ownership' ); ?></label>
			<input type="text" id="prx3_name" name="prx3_name" autocomplete="name" required></p>
			<p><label for="prx3_email"><?php esc_html_e( 'Email address', 'fan-ownership' ); ?></label>
			<input type="email" id="prx3_email" name="prx3_email" autocomplete="email" required></p>
			<p><label for="prx3_password"><?php esc_html_e( 'Password (at least 8 characters)', 'fan-ownership' ); ?></label>
			<input type="password" id="prx3_password" name="prx3_password" autocomplete="new-password" minlength="8" required></p>
			<p><label><input type="checkbox" name="prx3_adult" value="1" required>
				<?php echo esc_html( sprintf( /* translators: %d age. */ __( 'I confirm I am %d or over', 'fan-ownership' ), (int) prx3_setting( 'min_age_confirm', 18 ) ) ); ?></label></p>
			<p><label><input type="checkbox" name="prx3_terms" value="1" required>
				<?php esc_html_e( 'I accept the terms of membership (shares are non-refundable and transfer only back to the club)', 'fan-ownership' ); ?></label></p>
			<p><button type="submit" class="prx3-button"><?php esc_html_e( 'Create my account', 'fan-ownership' ); ?></button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * [prx3_redeem_gift] — the gift code redemption form, with the
	 * Shareholders' Agreement acceptance when one is published.
	 *
	 * @return string Form HTML.
	 */
	public static function redeem_form() {
		self::enqueue();
		ob_start();
		echo self::notices(); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<form class="prx3-form" method="post">
			<?php wp_nonce_field( 'prx3_redeem_gift', 'prx3_gift_nonce' ); ?>
			<p><label for="prx3_gift_code"><?php esc_html_e( 'Gift code', 'fan-ownership' ); ?></label>
			<input type="text" id="prx3_gift_code" name="prx3_gift_code" required></p>
			<?php if ( PRX3_Agreements::agreement_url() ) : ?>
			<p><label><input type="checkbox" name="prx3_sha_accept" value="1" required>
				<?php
				printf(
					/* translators: 1: URL, 2: version. */
					wp_kses_post( __( 'I have read and agree to the <a href="%1$s" target="_blank" rel="noopener">Shareholders\' Agreement</a> (v%2$s).', 'fan-ownership' ) ),
					esc_url( PRX3_Agreements::agreement_url() ),
					esc_html( PRX3_Agreements::version() )
				);
				?>
			</label></p>
				<?php PRX3_Agreements::signature_field(); ?>
			<?php endif; ?>
			<p><button type="submit" class="prx3-button"><?php esc_html_e( 'Redeem my shares', 'fan-ownership' ); ?></button></p>
			<?php if ( ! is_user_logged_in() ) : ?>
				<p class="description"><?php esc_html_e( 'You will need an account first — create one, then redeem here.', 'fan-ownership' ); ?></p>
			<?php endif; ?>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * [prx3_share_ladder] — the published tiered price ladder.
	 *
	 * @return string Table HTML.
	 */
	public static function share_ladder() {
		$max  = prx3_max_shares();
		$rows = '';
		$cum  = 0.0;
		for ( $i = 1; $i <= $max; $i++ ) {
			$cum  += prx3_share_price( $i );
			$rows .= '<tr><td>' . (int) $i . '</td><td>' . esc_html( prx3_money( prx3_share_price( $i ) ) ) . '</td><td>' . esc_html( prx3_money( $cum ) ) . '</td></tr>';
		}
		return '<table class="prx3-table"><caption>' . esc_html__( 'The published share price ladder', 'fan-ownership' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Share', 'fan-ownership' ) . '</th><th scope="col">' . esc_html__( 'Price', 'fan-ownership' ) . '</th><th scope="col">' . esc_html__( 'Total holding cost', 'fan-ownership' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>';
	}

	/* ---------------- Account ---------------- */

	/**
	 * [prx3_account] — the owner's account page: shares, discounts,
	 * certificate, preferences, agreement status and account closure.
	 *
	 * @return string Account HTML, or the gate for visitors.
	 */
	public static function account() {
		self::enqueue();
		if ( ! is_user_logged_in() ) {
			return self::gate();
		}
		$user_id = get_current_user_id();
		$shares  = prx3_shares( $user_id );
		$disc    = PRX3_Shares::ticket_discounts( $user_id );
		ob_start();
		echo self::notices(); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<div class="prx3-account">
			<h2><?php echo esc_html( sprintf( /* translators: 1: club, 2: number. */ __( '%1$s Owner #%2$d', 'fan-ownership' ), prx3_club_name(), PRX3_Shares::owner_number( $user_id ) ) ); ?></h2>
			<ul>
				<li><?php echo esc_html( sprintf( /* translators: 1: shares, 2: cap. */ __( 'Shares: %1$d of %2$d — each share is one vote on every ballot.', 'fan-ownership' ), $shares, prx3_max_shares() ) ); ?></li>
				<li><?php echo esc_html( sprintf( /* translators: 1: pct, 2: pct. */ __( 'Ticket discounts: %1$d%% matchday, %2$d%% season ticket.', 'fan-ownership' ), $disc['matchday'], $disc['season'] ) ); ?></li>
				<li><a href="<?php echo esc_url( home_url( '/?prx3_certificate=latest' ) ); ?>"><?php esc_html_e( 'View my certificate', 'fan-ownership' ); ?></a></li>
				<li><a href="<?php echo esc_url( PRX3_Meetings::member_ics_url( $user_id ) ); ?>"><?php esc_html_e( 'Subscribe to the owners calendar', 'fan-ownership' ); ?></a></li>
				<li><?php echo esc_html( sprintf( /* translators: %s ticketing provider. */ __( 'Your %s ticket discount code:', 'fan-ownership' ), PRX3_Ticketing::provider() ) ); ?> <code><?php echo esc_html( PRX3_Ticketing::member_code( $user_id ) ); ?></code></li>
				<li><?php esc_html_e( 'My referral link:', 'fan-ownership' ); ?> <code><?php echo esc_html( add_query_arg( 'ref', $user_id, home_url( '/' ) ) ); ?></code></li>
			</ul>
			<?php
			$pending = PRX3_Shopify::pending_for( wp_get_current_user()->user_email );
			if ( $pending > 0 ) :
				?>
				<div class="prx3-notice" role="status"><p><?php echo esc_html( sprintf( /* translators: %d shares. */ _n( 'You have %d share waiting: sign the Shareholders\' Agreement below and it is yours.', 'You have %d shares waiting: sign the Shareholders\' Agreement below and they are yours.', $pending, 'fan-ownership' ), $pending ) ); ?></p></div>
			<?php endif; ?>
			<?php
			$buy_url = PRX3_Shopify::checkout_url( $user_id );
			?>
			<?php if ( $shares < prx3_max_shares() && prx3_feature_on( 'checkout' ) && $buy_url ) : ?>
				<p><a class="prx3-button" href="<?php echo esc_url( $buy_url ); ?>">
					<?php echo esc_html( sprintf( /* translators: %s price. */ __( 'Buy another share — next one costs %s', 'fan-ownership' ), prx3_money( prx3_share_price( $shares + 1 ) ) ) ); ?></a></p>
			<?php endif; ?>
			<h3><?php esc_html_e( 'Communication preferences', 'fan-ownership' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'prx3_save_prefs' ); ?>
				<input type="hidden" name="action" value="prx3_save_prefs">
				<?php foreach ( PRX3_Comms::CATEGORIES as $cat ) : ?>
					<p><strong><?php echo esc_html( ucfirst( $cat ) ); ?></strong>
					<label><input type="checkbox" name="email_<?php echo esc_attr( $cat ); ?>" <?php checked( PRX3_Comms::user_wants( $user_id, $cat, 'email' ) ); ?>> <?php esc_html_e( 'Email', 'fan-ownership' ); ?></label>
					<label><input type="checkbox" name="push_<?php echo esc_attr( $cat ); ?>" <?php checked( PRX3_Comms::user_wants( $user_id, $cat, 'push' ) ); ?>> <?php esc_html_e( 'Push', 'fan-ownership' ); ?></label></p>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Governance notices (ballots, AGM) always send — they are part of being an owner.', 'fan-ownership' ); ?></p>
				<p><button class="prx3-button" type="submit"><?php esc_html_e( 'Save preferences', 'fan-ownership' ); ?></button></p>
			</form>
			<?php echo wp_kses_post( PRX3_Agreements::account_block( $user_id ) ); ?>
			<h3><?php esc_html_e( 'My nominated beneficiary', 'fan-ownership' ); ?></h3>
			<p><?php esc_html_e( 'On death, your shares pass to your estate or the person you nominate here (P30). Give a name and a way to reach them.', 'fan-ownership' ); ?></p>
			<form class="prx3-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'prx3_beneficiary' ); ?>
				<input type="hidden" name="action" value="prx3_beneficiary">
				<p><label for="prx3_beneficiary"><?php esc_html_e( 'Beneficiary (name and contact)', 'fan-ownership' ); ?></label>
				<input type="text" id="prx3_beneficiary" name="prx3_beneficiary" maxlength="300" value="<?php echo esc_attr( (string) get_user_meta( $user_id, 'prx3_beneficiary', true ) ); ?>"></p>
				<p><button type="submit" class="prx3-button"><?php esc_html_e( 'Save beneficiary', 'fan-ownership' ); ?></button></p>
			</form>
			<h3><?php esc_html_e( 'My data', 'fan-ownership' ); ?></h3>
			<p><?php esc_html_e( 'You can request a full export of your data from your profile, or close your account below. Closing surrenders your shares to the club with no payout, as the terms provide.', 'fan-ownership' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Close your account and surrender your shares? This cannot be undone.', 'fan-ownership' ) ); ?>');">
				<?php wp_nonce_field( 'prx3_close_account' ); ?>
				<input type="hidden" name="action" value="prx3_close_account">
				<p><label><input type="checkbox" name="prx3_confirm_close" value="1" required> <?php esc_html_e( 'I understand my shares are surrendered without payout.', 'fan-ownership' ); ?></label></p>
				<p><button class="prx3-button prx3-button--danger" type="submit"><?php esc_html_e( 'Close my account', 'fan-ownership' ); ?></button></p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------- Governance ---------------- */

	/**
	 * [prx3_ballots] — open ballots with voting forms plus the
	 * upcoming schedule.
	 *
	 * @return string Ballots HTML, or the gate for non-owners.
	 */
	public static function ballots() {
		self::enqueue();
		if ( ! prx3_is_owner() ) {
			return self::gate();
		}
		PRX3_Onboarding::mark_complete( get_current_user_id(), 'visited_boardroom' );
		prx3_touch_activity();
		$open      = PRX3_Ballots::open_ballots();
		$scheduled = PRX3_Ballots::scheduled_ballots();
		ob_start();
		echo '<div class="prx3-ballots" data-prx3-app="ballots">';
		echo '<h2>' . esc_html__( 'Open ballots', 'fan-ownership' ) . '</h2>';
		if ( ! $open ) {
			echo '<p>' . esc_html__( 'Nothing open right now — the upcoming schedule is below.', 'fan-ownership' ) . '</p>';
		}
		foreach ( $open as $ballot ) {
			echo self::render_ballot_card( $ballot->ID, true ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<h2>' . esc_html__( 'Coming up', 'fan-ownership' ) . '</h2><ul>';
		foreach ( $scheduled as $ballot ) {
			echo '<li>' . esc_html( $ballot->post_title ) . ' — ' . esc_html( sprintf( /* translators: %s date. */ __( 'opens %s', 'fan-ownership' ), prx3_format_datetime( get_post_meta( $ballot->ID, '_prx3_opens', true ) ) ) ) . '</li>';
		}
		echo '</ul></div>';
		return ob_get_clean();
	}

	/**
	 * Render the full member experience on an event's own permalink:
	 * an open ballot shows its voting card beneath the description, a
	 * closed one its state, and a match its Match Centre. Runs for the
	 * queried singular post only — block-theme safe (no in_the_loop).
	 *
	 * @param string $content Post content.
	 * @return string Content plus the event experience.
	 */
	public static function single_content( $content ) {
		if ( is_admin() || ! function_exists( 'is_singular' ) || ! is_singular( array( 'prx3_ballot', 'prx3_match' ) ) ) {
			return $content;
		}
		$post_id = get_queried_object_id();
		if ( get_the_ID() && (int) get_the_ID() !== (int) $post_id ) {
			return $content;
		}
		if ( ! prx3_is_owner() ) {
			return $content;
		}
		self::enqueue();
		if ( 'prx3_match' === get_post_type( $post_id ) ) {
			return $content . self::match( array( 'id' => $post_id ) );
		}
		$state = PRX3_Ballots::state( $post_id );
		if ( 'open' === $state ) {
			return $content . '<div class="prx3-ballots" data-prx3-app="ballots">' . self::render_ballot_card( $post_id ) . '</div>';
		}
		if ( 'scheduled' === $state ) {
			return $content . '<p class="prx3-notice">' . esc_html( sprintf( /* translators: %s date. */ __( 'Voting opens %s.', 'fan-ownership' ), prx3_format_datetime( get_post_meta( $post_id, '_prx3_opens', true ) ) ) ) . '</p>';
		}
		if ( in_array( $state, array( 'closed', 'published', 'rerun', 'unresolved' ), true ) ) {
			return $content . '<p class="prx3-notice">' . esc_html__( 'Voting has closed — the result is announced in the decision register and on the owners dashboard.', 'fan-ownership' ) . '</p>';
		}
		if ( 'draft' === $state || '' === $state ) {
			return $content . '<p class="prx3-notice">' . esc_html__( 'Draft ballot — voting has not been scheduled yet. The voting card and discussion chat appear here the moment it opens.', 'fan-ownership' ) . '</p>';
		}
		return $content;
	}

	/**
	 * One ballot card: options, the member's current choice, weighting
	 * and the secret-ballot note.
	 *
	 * @param int  $ballot_id     Ballot post ID.
	 * @param bool $with_question Include the full question body (HTML and images) in the card.
	 * @return string Card HTML.
	 */
	private static function render_ballot_card( $ballot_id, $with_question = false ) {
		$options = (array) get_post_meta( $ballot_id, '_prx3_options', true );
		$mine    = PRX3_Ballots::member_choice( $ballot_id, get_current_user_id() );
		$weight  = prx3_shares( get_current_user_id() );
		$type    = get_post_meta( $ballot_id, '_prx3_type', true );
		$rec     = get_post_meta( $ballot_id, '_prx3_board_recommendation', true );
		$html    = '<article class="prx3-card prx3-ballot" data-ballot="' . (int) $ballot_id . '">';
		$html   .= '<h3><a href="' . esc_url( get_permalink( $ballot_id ) ) . '">' . esc_html( get_the_title( $ballot_id ) ) . '</a></h3>';
		$number  = PRX3_Ballots::number( $ballot_id );
		$html   .= '<p class="prx3-ballot__meta">' . ( $number ? '<span class="prx3-badge">' . esc_html( sprintf( /* translators: %d ballot number. */ __( 'Ballot #%d', 'fan-ownership' ), $number ) ) . '</span> ' : '' ) . ( 'constitutional' === $type
			? '<span class="prx3-badge prx3-badge--constitutional">' . esc_html( sprintf( /* translators: %d pct. */ __( 'Constitutional — %d%% to pass', 'fan-ownership' ), (int) prx3_setting( 'constitutional_pct', 75 ) ) ) . '</span>'
			: '<span class="prx3-badge">' . esc_html__( 'Standard ballot', 'fan-ownership' ) . '</span>' )
			. ' <span>' . esc_html( sprintf( /* translators: %s date. */ __( 'closes %s', 'fan-ownership' ), prx3_format_datetime( get_post_meta( $ballot_id, '_prx3_closes', true ) ) ) ) . '</span></p>';
		if ( $with_question ) {
			$question = get_post( $ballot_id );
			if ( $question && '' !== trim( (string) $question->post_content ) ) {
				$html .= '<div class="prx3-ballot__question">' . wp_kses_post( wpautop( $question->post_content ) ) . '</div>';
			}
		}
		if ( $rec ) {
			$html .= '<p class="prx3-ballot__rec"><strong>' . esc_html__( 'Board recommendation:', 'fan-ownership' ) . '</strong> ' . esc_html( $rec ) . '</p>';
		}
		$html .= '<form class="prx3-ballot__form" data-prx3-vote="' . (int) $ballot_id . '"><fieldset><legend class="screen-reader-text">' . esc_html( get_the_title( $ballot_id ) ) . '</legend>';
		foreach ( $options as $i => $option ) {
			$id    = 'prx3-b' . (int) $ballot_id . '-o' . (int) $i;
			$desc  = PRX3_Ballots::option_description( $ballot_id, $i );
			$html .= '<p class="prx3-ballot__option"><input type="radio" id="' . esc_attr( $id ) . '" name="prx3_choice" value="' . (int) $i . '" ' . checked( $mine ? (int) $mine['choice'] : -1, $i, false ) . ' required> <label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $option ) . '</strong>';
			if ( $desc ) {
				$html .= '<span class="prx3-ballot__option-desc">' . esc_html( $desc ) . '</span>';
			}
			$html .= '</label></p>';
		}
		$html .= '</fieldset><button type="submit" class="prx3-button">'
			. esc_html(
				$mine
				? __( 'Change my vote', 'fan-ownership' )
				: sprintf( /* translators: %d votes. */ _n( 'Cast my %d vote', 'Cast my %d votes', $weight, 'fan-ownership' ), $weight )
			)
			. '</button>';
		$html .= '<p class="prx3-ballot__note">' . esc_html__( 'Secret ballot: results are revealed the moment voting closes. You can change your vote until then.', 'fan-ownership' ) . '</p>';
		$html .= '<p class="prx3-feedback" role="status" aria-live="polite"></p></form></article>';
		return $html;
	}

	/**
	 * [prx3_ideas] — propose an idea and support others; ideas hitting
	 * the threshold go automatically to a ballot.
	 *
	 * @return string Ideas HTML, or the gate for non-owners.
	 */
	public static function ideas() {
		self::enqueue();
		if ( ! prx3_is_owner() ) {
			return self::gate();
		}
		ob_start();
		echo '<div data-prx3-app="ideas">';
		echo '<h2>' . esc_html__( 'Fan ideas', 'fan-ownership' ) . '</h2>';
		echo '<p>' . esc_html( sprintf( /* translators: %d threshold. */ __( 'Ideas backed by %d owners go automatically to a ballot of everyone.', 'fan-ownership' ), PRX3_Ideas::threshold_count() ) ) . '</p>';
		echo '<form class="prx3-form" data-prx3-submit="ideas"><p><label for="prx3-idea-title">' . esc_html__( 'Your idea', 'fan-ownership' ) . '</label><input id="prx3-idea-title" type="text" name="title" required maxlength="140"></p>';
		echo '<p><label for="prx3-idea-body">' . esc_html__( 'Why it would make the club better', 'fan-ownership' ) . '</label><textarea id="prx3-idea-body" name="body" rows="3"></textarea></p>';
		echo '<p><button class="prx3-button" type="submit">' . esc_html__( 'Propose it', 'fan-ownership' ) . '</button></p><p class="prx3-feedback" role="status" aria-live="polite"></p></form>';
		foreach ( get_posts(
			array(
				'post_type'      => 'prx3_idea',
				'post_status'    => 'publish',
				'posts_per_page' => 30,
				'no_found_rows'  => true,
			)
		) as $idea ) {
			$count = count( (array) get_post_meta( $idea->ID, '_prx3_supporters', true ) );
			echo '<article class="prx3-card"><h3>' . esc_html( $idea->post_title ) . '</h3>';
			echo '<p><span class="prx3-badge">' . esc_html( (string) get_post_meta( $idea->ID, '_prx3_idea_status', true ) ) . '</span> ';
			echo '<button class="prx3-button prx3-button--secondary" data-prx3-support="' . (int) $idea->ID . '">' . esc_html( sprintf( /* translators: %d supporters. */ __( 'Support (%d)', 'fan-ownership' ), $count ) ) . '</button></p></article>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * [prx3_questions] — ask the club, upvote questions and read answers.
	 *
	 * @return string Questions HTML, or the gate for non-owners.
	 */
	public static function questions() {
		self::enqueue();
		if ( ! prx3_is_owner() ) {
			return self::gate();
		}
		$categories = PRX3_Questions::categories();
		$active     = isset( $_GET['prx3_qcat'] ) ? sanitize_key( wp_unslash( $_GET['prx3_qcat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$active     = isset( $categories[ $active ] ) ? $active : '';
		ob_start();
		echo '<div data-prx3-app="questions"><h2>' . esc_html__( 'Ask the club', 'fan-ownership' ) . '</h2>';
		echo '<form class="prx3-form" data-prx3-submit="questions"><p><label for="prx3-q-title">' . esc_html__( 'Your question', 'fan-ownership' ) . '</label><input id="prx3-q-title" type="text" name="title" required maxlength="200"></p>';
		echo '<p><label for="prx3-q-category">' . esc_html__( 'Who is it for?', 'fan-ownership' ) . '</label><select id="prx3-q-category" name="category">';
		foreach ( $categories as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><button class="prx3-button" type="submit">' . esc_html__( 'Submit question', 'fan-ownership' ) . '</button></p><p class="prx3-feedback" role="status" aria-live="polite"></p></form>';
		echo '<nav class="prx3-chips" aria-label="' . esc_attr__( 'Filter questions by who they are for', 'fan-ownership' ) . '">';
		echo '<a class="prx3-chip' . ( '' === $active ? ' prx3-chip--active' : '' ) . '" href="' . esc_url( remove_query_arg( 'prx3_qcat' ) ) . '">' . esc_html__( 'All', 'fan-ownership' ) . '</a>';
		foreach ( $categories as $key => $label ) {
			echo '<a class="prx3-chip' . ( $key === $active ? ' prx3-chip--active' : '' ) . '" href="' . esc_url( add_query_arg( 'prx3_qcat', $key ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		$shown = 0;
		foreach ( get_posts(
			array(
				'post_type'      => 'prx3_question',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'no_found_rows'  => true,
			)
		) as $q ) {
			if ( ( $active && PRX3_Questions::category( $q->ID ) !== $active ) || $shown >= 30 ) {
				continue;
			}
			++$shown;
			$answer = get_post_meta( $q->ID, '_prx3_answer', true );
			$video  = get_post_meta( $q->ID, '_prx3_video_answer', true );
			echo '<article class="prx3-card"><h3>' . esc_html( $q->post_title ) . ' <span class="prx3-badge">' . esc_html( $categories[ PRX3_Questions::category( $q->ID ) ] ) . '</span></h3>';
			echo '<p><button class="prx3-button prx3-button--secondary" data-prx3-upvote="' . (int) $q->ID . '">' . esc_html( sprintf( /* translators: %d upvotes. */ __( 'Upvote (%d)', 'fan-ownership' ), count( (array) get_post_meta( $q->ID, '_prx3_upvotes', true ) ) ) ) . '</button></p>';
			if ( $answer ) {
				echo '<p><strong>' . esc_html__( 'Answer:', 'fan-ownership' ) . '</strong> ' . esc_html( $answer ) . '</p>';
			}
			if ( $video ) {
				echo '<p><a href="' . esc_url( $video ) . '">' . esc_html__( 'Watch the video answer', 'fan-ownership' ) . '</a></p>';
			}
			echo '</article>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * [prx3_meetings] — upcoming meetings with RSVP and the calendar feed.
	 *
	 * @return string Meetings HTML, or the gate for non-owners.
	 */
	public static function meetings() {
		self::enqueue();
		if ( ! prx3_is_owner() ) {
			return self::gate();
		}
		ob_start();
		echo '<div data-prx3-app="meetings"><h2>' . esc_html__( 'Meetings', 'fan-ownership' ) . '</h2>';
		echo '<p><a class="prx3-button prx3-button--secondary" href="' . esc_url( PRX3_Meetings::member_ics_url( get_current_user_id() ) ) . '">' . esc_html__( 'Subscribe in my calendar', 'fan-ownership' ) . '</a></p>';
		foreach ( PRX3_Meetings::upcoming() as $meeting ) {
			echo '<article class="prx3-card"><h3><a href="' . esc_url( get_permalink( $meeting ) ) . '">' . esc_html( $meeting->post_title ) . '</a></h3>';
			echo '<p>' . esc_html( prx3_format_datetime( get_post_meta( $meeting->ID, '_prx3_meeting_start', true ) ) ) . ( get_post_meta( $meeting->ID, '_prx3_is_agm', true ) ? ' <span class="prx3-badge">' . esc_html__( 'AGM', 'fan-ownership' ) . '</span>' : '' ) . '</p>';
			echo '<p><button class="prx3-button" data-prx3-rsvp="' . (int) $meeting->ID . '">' . esc_html( sprintf( /* translators: %d rsvps. */ __( 'RSVP (%d going)', 'fan-ownership' ), count( (array) get_post_meta( $meeting->ID, '_prx3_attendees', true ) ) ) ) . '</button></p></article>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * [prx3_decisions] — the decision register with status badges and
	 * recent updates.
	 *
	 * @return string Register HTML, or the gate for non-owners.
	 */
	public static function decisions() {
		if ( ! prx3_is_owner() ) {
			return self::gate();
		}
		self::enqueue();
		ob_start();
		echo '<div><h2>' . esc_html__( 'The decision register', 'fan-ownership' ) . '</h2><p>' . esc_html__( 'Every passed ballot and every released board decision, tracked until done.', 'fan-ownership' ) . '</p>';
		foreach ( get_posts(
			array(
				'post_type'      => 'prx3_decision',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'no_found_rows'  => true,
			)
		) as $decision ) {
			$status  = get_post_meta( $decision->ID, '_prx3_decision_status', true );
			$stalled = get_post_meta( $decision->ID, '_prx3_stalled', true );
			echo '<article class="prx3-card"><h3>' . esc_html( $decision->post_title ) . '</h3>';
			echo '<p><span class="prx3-badge prx3-badge--' . esc_attr( $status ) . '">' . esc_html( $status ) . '</span>';
			if ( $stalled ) {
				echo ' <span class="prx3-badge prx3-badge--stalled">' . esc_html__( 'stalled — no recent update', 'fan-ownership' ) . '</span>';
			}
			echo '</p>';
			$updates = array_filter( (array) get_post_meta( $decision->ID, '_prx3_updates', true ), 'is_array' );
			if ( $updates ) {
				echo '<ul>';
				foreach ( array_slice( array_reverse( $updates ), 0, 3 ) as $update ) {
					echo '<li>' . esc_html( ( $update['at'] ?? '' ) . ' — ' . ( $update['status'] ?? '' ) . ( ! empty( $update['note'] ) ? ': ' . $update['note'] : '' ) ) . '</li>';
				}
				echo '</ul>';
			}
			echo '</article>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/* ---------------- Watch ---------------- */

	/**
	 * [prx3_match id] — the match centre: player, timeline and chat.
	 *
	 * @param array $atts Shortcode attributes: id (match post ID).
	 * @return string Match centre HTML, or the gate for non-owners.
	 */
	public static function match( $atts ) {
		self::enqueue();
		if ( ! prx3_is_owner() ) {
			return self::gate();
		}
		$atts     = shortcode_atts( array( 'id' => 0 ), $atts );
		$match_id = (int) $atts['id'];
		if ( ! $match_id ) {
			return '';
		}
		return '<div class="prx3-match" data-prx3-match="' . (int) $match_id . '" data-room="match-' . (int) $match_id . '">'
			. '<div class="prx3-match__player" aria-live="off"></div>'
			. '<p class="prx3-match__state" role="status" aria-live="polite"></p>'
			. '<ol class="prx3-match__timeline" aria-label="' . esc_attr__( 'Match timeline', 'fan-ownership' ) . '"></ol>'
			. '<section class="prx3-chat" aria-label="' . esc_attr__( 'Match chat', 'fan-ownership' ) . '">'
			. '<ul class="prx3-chat__messages" aria-live="polite"></ul>'
			. '<form class="prx3-chat__form"><label class="screen-reader-text" for="prx3-chat-input-' . (int) $match_id . '">' . esc_html__( 'Chat message', 'fan-ownership' ) . '</label>'
			. '<input id="prx3-chat-input-' . (int) $match_id . '" type="text" maxlength="500" autocomplete="off">'
			. '<button type="submit" class="prx3-button">' . esc_html__( 'Send', 'fan-ownership' ) . '</button></form>'
			. '</section></div>';
	}

	/**
	 * [prx3_videos] — the video library, optionally filtered by type.
	 *
	 * @param array $atts Shortcode attributes: type (video type slug).
	 * @return string Library HTML, or the gate for non-owners.
	 */
	public static function videos( $atts ) {
		self::enqueue();
		if ( ! prx3_is_owner() ) {
			return self::gate();
		}
		$atts = shortcode_atts( array( 'type' => '' ), $atts );
		$args = array(
			'post_type'      => 'prx3_video',
			'post_status'    => 'publish',
			'posts_per_page' => 24,
			'no_found_rows'  => true,
		);
		if ( $atts['type'] ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- single-term filter on a capped library listing.
				array(
					'taxonomy' => 'prx3_video_type',
					'field'    => 'slug',
					'terms'    => sanitize_key( $atts['type'] ),
				),
			);
		}
		ob_start();
		echo '<div class="prx3-videos">';
		foreach ( get_posts( $args ) as $video ) {
			echo '<article class="prx3-card"><h3><a href="' . esc_url( get_permalink( $video ) ) . '">' . esc_html( $video->post_title ) . '</a></h3>';
			if ( has_post_thumbnail( $video ) ) {
				echo get_the_post_thumbnail( $video, 'medium' );
			}
			echo '</article>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/* ---------------- Dashboard & board ---------------- */

	/**
	 * [prx3_dashboard] — the member home: open ballots and the next meeting.
	 *
	 * @return string Dashboard HTML, or the gate for non-owners.
	 */
	public static function dashboard() {
		self::enqueue();
		$prx3_welcome = self::welcome_panel();
		if ( ! prx3_is_owner() ) {
			return self::gate();
		}
		$out  = '<div class="prx3-dashboard">';
		$out .= $prx3_welcome;
		$out .= '<h2>' . esc_html( sprintf( /* translators: 1: club, 2: name. */ __( '%1$s — welcome back, %2$s', 'fan-ownership' ), prx3_club_name(), wp_get_current_user()->display_name ) ) . '</h2>';

		$user_id = get_current_user_id();
		$shares  = prx3_shares( $user_id );
		$disc    = PRX3_Shares::ticket_discounts( $user_id );
		$open    = PRX3_Ballots::open_ballots();
		$account = prx3_setting( 'account_page_id' ) ? get_permalink( (int) prx3_setting( 'account_page_id' ) ) : '';

		$tiles = array(
			array( __( 'My shares', 'fan-ownership' ), sprintf( '%d / %d', $shares, prx3_max_shares() ), __( 'each share is one vote', 'fan-ownership' ), $account ),
			array( __( 'My voting power', 'fan-ownership' ), (string) $shares, __( 'votes on every ballot', 'fan-ownership' ), $account ),
			array( __( 'Ballots open now', 'fan-ownership' ), (string) count( $open ), $open ? __( 'your vote is needed below', 'fan-ownership' ) : __( 'nothing waiting on you', 'fan-ownership' ), $open ? '#prx3-ballots' : '' ),
			array( __( 'Ticket discount', 'fan-ownership' ), $disc['matchday'] . '%', sprintf( /* translators: %d percent. */ __( 'season tickets %d%%', 'fan-ownership' ), $disc['season'] ), $account ),
		);
		$out  .= '<div class="prx3-tiles">';
		foreach ( $tiles as $tile ) {
			$tag  = $tile[3] ? 'a' : 'div';
			$out .= '<' . $tag . ( $tile[3] ? ' href="' . esc_url( $tile[3] ) . '"' : '' ) . ' class="prx3-tile">';
			$out .= '<span class="prx3-tile-label">' . esc_html( $tile[0] ) . '</span>';
			$out .= '<strong class="prx3-tile-value">' . esc_html( $tile[1] ) . '</strong>';
			$out .= '<span class="prx3-tile-note">' . esc_html( $tile[2] ) . '</span>';
			$out .= '</' . $tag . '>';
		}
		$out .= '</div>';
		if ( $open ) {
			$out .= '<h3 id="prx3-ballots">' . esc_html( sprintf( /* translators: %d count. */ _n( '%d ballot needs your vote', '%d ballots need your vote', count( $open ), 'fan-ownership' ), count( $open ) ) ) . '</h3>';
			foreach ( $open as $ballot ) {
				$out .= self::render_ballot_card( $ballot->ID );
			}
		}
		$next = PRX3_Meetings::upcoming( 1 );
		if ( $next ) {
			$out .= '<h3>' . esc_html__( 'Next meeting', 'fan-ownership' ) . '</h3><p><a href="' . esc_url( get_permalink( $next[0] ) ) . '">' . esc_html( $next[0]->post_title ) . '</a> — ' . esc_html( prx3_format_datetime( get_post_meta( $next[0]->ID, '_prx3_meeting_start', true ) ) ) . '</p>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * [prx3_board_directory] — public list of board members with their
	 * declared interests.
	 *
	 * @return string Directory HTML.
	 */
	public static function board_directory() {
		$out = '<div class="prx3-board-directory"><h2>' . esc_html__( 'The board', 'fan-ownership' ) . '</h2>';
		foreach ( get_users( array( 'role' => 'prx3_board_member' ) ) as $director ) {
			$out      .= '<article class="prx3-card"><h3>' . esc_html( $director->display_name ) . ' <span class="prx3-badge">' . esc_html__( 'Board', 'fan-ownership' ) . '</span></h3>';
			$out      .= '<p>' . esc_html( get_user_meta( $director->ID, 'description', true ) ) . '</p>';
			$conflicts = get_user_meta( $director->ID, 'prx3_conflicts', true );
			$out      .= '<p><strong>' . esc_html__( 'Declared interests:', 'fan-ownership' ) . '</strong> ' . esc_html( $conflicts ? $conflicts : __( 'None declared', 'fan-ownership' ) ) . '</p></article>';
		}
		return $out . '</div>';
	}
	/**
	 * [prx3_voting_record] — the searchable archive of finished ballots (FO-209).
	 *
	 * @return string Archive HTML.
	 */
	public static function voting_record() {
		self::enqueue();
		if ( ! prx3_is_owner() ) {
			return self::gate();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
		$q    = isset( $_GET['prx3_vq'] ) ? sanitize_text_field( wp_unslash( $_GET['prx3_vq'] ) ) : '';
		$out  = '<div class="prx3-voting-record"><h2>' . esc_html__( 'Voting record', 'fan-ownership' ) . '</h2>';
		$out .= '<form method="get"><label class="screen-reader-text" for="prx3_vq">' . esc_html__( 'Search ballots', 'fan-ownership' ) . '</label><input type="search" id="prx3_vq" name="prx3_vq" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Search past ballots…', 'fan-ownership' ) . '"> <button class="prx3-button">' . esc_html__( 'Search', 'fan-ownership' ) . '</button></form><ul>';
		$rows = 0;
		foreach ( get_posts(
			array(
				'post_type'   => 'prx3_ballot',
				'post_status' => array( 'publish' ),
				'numberposts' => -1,
			)
		) as $ballot ) {
			$state = PRX3_Ballots::state( $ballot->ID );
			if ( ! in_array( $state, array( 'closed', 'published', 'rerun', 'unresolved' ), true ) ) {
				continue;
			}
			if ( '' !== $q && false === stripos( $ballot->post_title, $q ) ) {
				continue;
			}
			++$rows;
			$number = PRX3_Ballots::number( $ballot->ID );
			$out   .= '<li><a href="' . esc_url( get_permalink( $ballot->ID ) ) . '">' . ( $number ? '#' . (int) $number . ' — ' : '' ) . esc_html( $ballot->post_title ) . '</a> <span class="prx3-badge">' . esc_html( $state ) . '</span></li>';
		}
		if ( ! $rows ) {
			$out .= '<li>' . esc_html__( 'No finished ballots match.', 'fan-ownership' ) . '</li>';
		}
		return $out . '</ul></div>';
	}

	/**
	 * [prx3_referrals] — my referral link, opt-in, and the leaderboard (FO-223).
	 *
	 * @return string Referrals HTML.
	 */
	public static function referrals() {
		self::enqueue();
		if ( ! prx3_is_owner() ) {
			return self::gate();
		}
		$me    = get_current_user_id();
		$out   = '<div class="prx3-referrals"><h2>' . esc_html__( 'Bring a fellow fan', 'fan-ownership' ) . '</h2>';
		$out  .= '<p>' . esc_html__( 'Share your link — when they become an owner, the recruit is credited to you. Recognition only: prices never change.', 'fan-ownership' ) . '</p>';
		$out  .= '<p><code>' . esc_html( add_query_arg( 'ref', $me, home_url( '/' ) ) ) . '</code></p>';
		$optin = (bool) get_user_meta( $me, 'prx3_referral_optin', true );
		$out  .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'prx3_referral_optin', '_wpnonce', true, false );
		$out  .= '<input type="hidden" name="action" value="prx3_referral_optin"><button class="prx3-button">' . esc_html( $optin ? __( 'Leave the public leaderboard', 'fan-ownership' ) : __( 'Join the public leaderboard', 'fan-ownership' ) ) . '</button></form>';
		$out  .= '<h3>' . esc_html__( 'Leaderboard', 'fan-ownership' ) . '</h3><ol>';
		$board = array();
		foreach ( get_users( array( 'number' => -1 ) ) as $member ) {
			if ( ! get_user_meta( $member->ID, 'prx3_referral_optin', true ) ) {
				continue;
			}
			$count = (int) get_user_meta( $member->ID, 'prx3_referrals', true );
			if ( $count > 0 ) {
				$board[] = array( $member->display_name, $count );
			}
		}
		usort( $board, fn( $x, $y ) => $y[1] <=> $x[1] );
		foreach ( array_slice( $board, 0, 10 ) as $row ) {
			$out .= '<li>' . esc_html( $row[0] ) . ' — ' . (int) $row[1] . '</li>';
		}
		if ( ! $board ) {
			$out .= '<li>' . esc_html__( 'No opted-in referrers yet — be the first.', 'fan-ownership' ) . '</li>';
		}
		return $out . '</ol></div>';
	}

	/**
	 * Toggle the leaderboard opt-in.
	 */
	public static function handle_referral_optin() {
		if ( ! is_user_logged_in() || ! prx3_is_owner() ) {
			wp_die( esc_html__( 'Owners only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_referral_optin' );
		$me = get_current_user_id();
		update_user_meta( $me, 'prx3_referral_optin', get_user_meta( $me, 'prx3_referral_optin', true ) ? '' : 1 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	/**
	 * [prx3_chapters] — the chapter directory, with a map when chapters
	 * carry coordinates (_prx3_lat/_prx3_lng) (FO-222).
	 *
	 * @return string Directory HTML.
	 */
	public static function chapters() {
		self::enqueue();
		if ( ! prx3_is_owner() ) {
			return self::gate();
		}
		$chapters = get_posts(
			array(
				'post_type'   => 'prx3_chapter',
				'post_status' => array( 'publish' ),
				'numberposts' => -1,
			)
		);
		$pins     = array();
		$out      = '<div class="prx3-chapters"><h2>' . esc_html__( 'Owner chapters', 'fan-ownership' ) . '</h2><ul>';
		foreach ( $chapters as $chapter ) {
			$city = get_post_meta( $chapter->ID, '_prx3_city', true );
			$lat  = (float) get_post_meta( $chapter->ID, '_prx3_lat', true );
			$lng  = (float) get_post_meta( $chapter->ID, '_prx3_lng', true );
			if ( $lat && $lng ) {
				$pins[] = array(
					'name' => $chapter->post_title,
					'lat'  => $lat,
					'lng'  => $lng,
				);
			}
			$out .= '<li><a href="' . esc_url( get_permalink( $chapter->ID ) ) . '">' . esc_html( $chapter->post_title ) . '</a>' . ( $city ? ' — ' . esc_html( $city ) : '' ) . '</li>';
		}
		if ( ! $chapters ) {
			$out .= '<li>' . esc_html__( 'No chapters yet — owners anywhere can start one.', 'fan-ownership' ) . '</li>';
		}
		$out .= '</ul>';
		if ( $pins ) {
			wp_enqueue_style( 'prx3-leaflet' );
			wp_enqueue_script( 'prx3-leaflet' );
			$out .= '<div id="prx3-chapter-map" style="height:360px;"></div>';
			$out .= '<script>window.addEventListener("load",function(){if(!window.L){return;}var m=L.map("prx3-chapter-map");var b=[];L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png",{attribution:"© OpenStreetMap"}).addTo(m);' . wp_json_encode( $pins ) . '.forEach(function(p){L.marker([p.lat,p.lng]).addTo(m).bindPopup(p.name);b.push([p.lat,p.lng]);});m.fitBounds(b,{padding:[30,30]});});</script>';
		}
		return $out . '</div>';
	}

	/**
	 * First-run welcome: shown on the owners hub until dismissed (FO-115).
	 *
	 * @return string Panel HTML, '' once dismissed.
	 */
	public static function welcome_panel() {
		$me = get_current_user_id();
		if ( ! $me || get_user_meta( $me, 'prx3_welcome_done', true ) ) {
			return '';
		}
		$video = (string) prx3_setting( 'welcome_video_url', '' );
		$out   = '<div class="prx3-card prx3-welcome"><h3>' . esc_html( sprintf( /* translators: %s club. */ __( 'Welcome to %s — you own this', 'fan-ownership' ), prx3_club_name() ) ) . '</h3>';
		if ( $video ) {
			$out .= '<p><iframe class="prx3-welcome__video" width="560" height="315" src="' . esc_url( $video ) . '" title="' . esc_attr__( 'Welcome from the club', 'fan-ownership' ) . '" loading="lazy" allowfullscreen style="max-width:100%;"></iframe></p>';
		}
		$out .= '<p>' . esc_html__( 'Cast your first vote in the starter ballot, say hello in FanPress Chat, and set up your profile.', 'fan-ownership' ) . '</p>';
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'prx3_welcome_done', '_wpnonce', true, false );
		$out .= '<input type="hidden" name="action" value="prx3_welcome_done"><button class="prx3-button">' . esc_html__( "Got it — let's go", 'fan-ownership' ) . '</button></form></div>';
		return $out;
	}

	/**
	 * Dismiss the welcome panel.
	 */
	public static function handle_welcome_done() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Owners only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_welcome_done' );
		update_user_meta( get_current_user_id(), 'prx3_welcome_done', 1 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}
}

