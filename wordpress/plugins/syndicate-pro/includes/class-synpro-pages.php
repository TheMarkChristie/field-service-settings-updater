<?php
/**
 * Front-end member pages.
 *
 * - [synpro_submit]  Submit Content page: logged-in members pick a post
 *   type, category(ies), and a URL — the URL field's label, placeholder,
 *   and help text change with the chosen type (RSS feed vs listing page
 *   vs channel ID vs events feed). Submissions become feed records.
 * - [synpro_account] My Account page: a front-end version of the member's
 *   profile — display name, bio, and all the syndication/author-page
 *   fields from the wp-admin profile screen, reusing the same save logic.
 *
 * Both pages are created automatically on activation ("Submit Content"
 * and "My Account") if they don't already exist.
 *
 * @package Syndicate_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Synpro_Pages' ) ) :

class Synpro_Pages {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_shortcode( 'synpro_submit', array( __CLASS__, 'shortcode_submit' ) );
		add_shortcode( 'synpro_account', array( __CLASS__, 'shortcode_account' ) );
		add_action( 'admin_post_synpro_submit_content', array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_synpro_account_save', array( __CLASS__, 'handle_account_save' ) );
	}

	/**
	 * Per-type URL prompts: what to ask for changes with the post type.
	 *
	 * @return array type => [label, placeholder, help].
	 */
	public static function type_prompts() {
		return array(
			'blog'    => array(
				'label'       => __( 'Your blog’s RSS feed URL', 'syndicate-pro' ),
				'placeholder' => 'https://yourblog.com/feed/',
				'help'        => __( 'Most blogs have one at /feed/ or /rss. New posts on this feed are republished here under your name.', 'syndicate-pro' ),
			),
			'scrape'  => array(
				'label'       => __( 'Your blog’s listing page URL', 'syndicate-pro' ),
				'placeholder' => 'https://yourblog.com/articles/',
				'help'        => __( 'No RSS feed? Point us at the page that lists your articles — we’ll discover new ones automatically.', 'syndicate-pro' ),
			),
			'podcast' => array(
				'label'       => __( 'Your podcast’s RSS feed URL', 'syndicate-pro' ),
				'placeholder' => 'https://feeds.buzzsprout.com/123456.rss',
				'help'        => __( 'The RSS feed from your podcast host (Buzzsprout, Transistor, Anchor…). Episodes appear with a built-in player.', 'syndicate-pro' ),
			),
			'youtube' => array(
				'label'       => __( 'Your YouTube channel ID or URL', 'syndicate-pro' ),
				'placeholder' => 'UCxxxxxxxxxxxxxxxxxxxxxx',
				'help'        => __( 'Your channel ID starts with “UC” (YouTube → Settings → Advanced), or paste your channel URL. Shorts are skipped.', 'syndicate-pro' ),
			),
			'event'   => array(
				'label'       => __( 'Your events feed URL', 'syndicate-pro' ),
				'placeholder' => 'https://sessionize.com/your-event/rss',
				'help'        => __( 'An RSS/iCal-style feed of your events (e.g. from Sessionize or Meetup). New events land on the community calendar.', 'syndicate-pro' ),
			),
		);
	}

	/**
	 * A styled "please log in" card, sending the member back here after.
	 *
	 * @return string
	 */
	protected static function login_card() {
		$back     = home_url( add_query_arg( array() ) );
		$out      = '<div class="synpro-page synpro-login-card">';
		$out     .= '<h3>' . esc_html__( 'Members only', 'syndicate-pro' ) . '</h3>';
		$out     .= '<p>' . esc_html__( 'Log in to your community account to continue.', 'syndicate-pro' ) . '</p>';
		$out     .= '<p class="synpro-actions"><a class="synpro-btn" href="' . esc_url( wp_login_url( $back ) ) . '">' . esc_html__( 'Log in', 'syndicate-pro' ) . '</a>';
		if ( get_option( 'users_can_register' ) ) {
			$out .= ' <a class="synpro-btn synpro-btn-ghost" href="' . esc_url( wp_registration_url() ) . '">' . esc_html__( 'Create an account', 'syndicate-pro' ) . '</a>';
		}
		$out     .= '</p></div>';
		return $out;
	}

	/**
	 * Submit Content page.
	 *
	 * @return string
	 */
	public static function shortcode_submit() {
		if ( ! is_user_logged_in() ) {
			return self::login_card();
		}

		$user_id = get_current_user_id();
		if ( ! Synpro_Profile::is_member( $user_id ) ) {
			return '<div class="synpro-page synpro-login-card"><h3>' . esc_html__( 'Almost there', 'syndicate-pro' ) . '</h3><p>'
				. esc_html__( 'Your account isn’t enabled for publishing yet. An administrator needs to upgrade you to a Contributor — get in touch and we’ll sort it.', 'syndicate-pro' ) . '</p></div>';
		}

		$prompts    = self::type_prompts();
		$types      = Synpro_Feeds::types();
		$categories = get_categories( array( 'hide_empty' => false ) );

		ob_start();
		?>
		<div class="synpro-page synpro-submit">
			<?php if ( isset( $_GET['synpro_submitted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<p class="synpro-notice synpro-notice-ok"><?php esc_html_e( 'Thanks — your source has been added! Its full history is imported on the next rotation, and new items will appear automatically from now on.', 'syndicate-pro' ); ?></p>
			<?php elseif ( isset( $_GET['synpro_submit_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<p class="synpro-notice synpro-notice-err"><?php esc_html_e( 'That URL didn’t look right for the type you picked — please check it and try again.', 'syndicate-pro' ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="synpro_submit_content">
				<?php wp_nonce_field( 'synpro_submit_content' ); ?>

				<p class="synpro-field">
					<label for="synpro-submit-type"><?php esc_html_e( 'What are you sharing?', 'syndicate-pro' ); ?></label>
					<select id="synpro-submit-type" name="synpro_type">
						<?php foreach ( $types as $value => $label ) : ?>
							<?php $p = isset( $prompts[ $value ] ) ? $prompts[ $value ] : $prompts['blog']; ?>
							<option value="<?php echo esc_attr( $value ); ?>"
								data-label="<?php echo esc_attr( $p['label'] ); ?>"
								data-placeholder="<?php echo esc_attr( $p['placeholder'] ); ?>"
								data-help="<?php echo esc_attr( $p['help'] ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<p class="synpro-field">
					<label for="synpro-submit-url" id="synpro-submit-url-label"><?php echo esc_html( $prompts['blog']['label'] ); ?></label>
					<input type="url" id="synpro-submit-url" name="synpro_url" required placeholder="<?php echo esc_attr( $prompts['blog']['placeholder'] ); ?>">
					<span class="synpro-help" id="synpro-submit-url-help"><?php echo esc_html( $prompts['blog']['help'] ); ?></span>
				</p>

				<fieldset class="synpro-field synpro-cats">
					<legend><?php esc_html_e( 'Post into category(ies)', 'syndicate-pro' ); ?></legend>
					<div class="synpro-cat-grid">
						<?php foreach ( $categories as $category ) : ?>
							<label class="synpro-chip"><input type="checkbox" name="synpro_categories[]" value="<?php echo (int) $category->term_id; ?>"> <?php echo esc_html( $category->name ); ?></label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<p class="synpro-field synpro-fulltext" id="synpro-submit-fulltext">
					<label class="synpro-chip"><input type="checkbox" name="synpro_full_content" value="1"> <?php esc_html_e( 'My feed only contains summaries — fetch the full article text', 'syndicate-pro' ); ?></label>
				</p>

				<p class="synpro-actions"><button type="submit" class="synpro-btn"><?php esc_html_e( 'Submit content source', 'syndicate-pro' ); ?></button></p>
			</form>

			<?php $rows = Synpro_Feeds::for_user( $user_id ); ?>
			<?php if ( $rows ) : ?>
				<h3 class="synpro-sources-title"><?php esc_html_e( 'Your current sources', 'syndicate-pro' ); ?></h3>
				<ul class="synpro-sources">
					<?php foreach ( $rows as $row ) : ?>
						<li>
							<span class="synpro-source-type"><?php echo esc_html( isset( $types[ $row->type ] ) ? $types[ $row->type ] : $row->type ); ?></span>
							<span class="synpro-source-url"><?php echo esc_html( $row->feed_url ); ?></span>
							<span class="synpro-source-status <?php echo $row->active ? ( $row->fail_count >= 3 ? 'is-warn' : 'is-ok' ) : 'is-off'; ?>">
								<?php
								if ( ! $row->active ) {
									esc_html_e( 'Paused', 'syndicate-pro' );
								} elseif ( $row->fail_count >= 3 ) {
									esc_html_e( 'Having trouble', 'syndicate-pro' );
								} else {
									esc_html_e( 'Active', 'syndicate-pro' );
								}
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="synpro-help"><?php esc_html_e( 'Manage or remove sources from your account page.', 'syndicate-pro' ); ?></p>
			<?php endif; ?>
		</div>
		<script>
		( function () {
			var type = document.getElementById( 'synpro-submit-type' );
			if ( ! type ) { return; }
			var swap = function () {
				var o = type.options[ type.selectedIndex ];
				document.getElementById( 'synpro-submit-url-label' ).textContent = o.getAttribute( 'data-label' );
				document.getElementById( 'synpro-submit-url' ).placeholder = o.getAttribute( 'data-placeholder' );
				document.getElementById( 'synpro-submit-url-help' ).textContent = o.getAttribute( 'data-help' );
				document.getElementById( 'synpro-submit-fulltext' ).style.display =
					( 'blog' === o.value || 'scrape' === o.value ) ? '' : 'none';
			};
			type.addEventListener( 'change', swap );
			swap();
		} )();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle a Submit Content submission.
	 */
	public static function handle_submit() {
		check_admin_referer( 'synpro_submit_content' );

		$user_id = get_current_user_id();
		if ( ! $user_id || ! Synpro_Profile::is_member( $user_id ) ) {
			wp_die( esc_html__( 'You need a publishing-enabled account to submit content.', 'syndicate-pro' ) );
		}

		$id = Synpro_Feeds::add(
			array(
				'user_id'      => $user_id,
				'type'         => isset( $_POST['synpro_type'] ) ? sanitize_key( $_POST['synpro_type'] ) : 'blog',
				'feed_url'     => isset( $_POST['synpro_url'] ) ? trim( (string) wp_unslash( $_POST['synpro_url'] ) ) : '',
				'categories'   => isset( $_POST['synpro_categories'] ) ? array_map( 'absint', (array) $_POST['synpro_categories'] ) : array(),
				'full_content' => ! empty( $_POST['synpro_full_content'] ),
				'active'       => true,
			)
		);

		$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$back = remove_query_arg( array( 'synpro_submitted', 'synpro_submit_error' ), $back );
		wp_safe_redirect( add_query_arg( $id ? 'synpro_submitted' : 'synpro_submit_error', '1', $back ) );
		exit;
	}

	/**
	 * My Account page.
	 *
	 * @return string
	 */
	public static function shortcode_account() {
		if ( ! is_user_logged_in() ) {
			return self::login_card();
		}

		$user = wp_get_current_user();

		ob_start();
		?>
		<div class="synpro-page synpro-account">
			<?php if ( isset( $_GET['synpro_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<p class="synpro-notice synpro-notice-ok"><?php esc_html_e( 'Your profile has been saved.', 'syndicate-pro' ); ?></p>
			<?php endif; ?>

			<div class="synpro-account-head">
				<?php echo get_avatar( $user->ID, 96 ); ?>
				<div>
					<h3><?php echo esc_html( $user->display_name ); ?></h3>
					<?php $tagline = get_user_meta( $user->ID, 'synpro_tagline', true ); ?>
					<?php if ( $tagline ) : ?>
						<p class="synpro-tagline"><?php echo esc_html( $tagline ); ?></p>
					<?php endif; ?>
					<p class="synpro-actions">
						<a class="synpro-btn synpro-btn-ghost" href="<?php echo esc_url( get_author_posts_url( $user->ID ) ); ?>"><?php esc_html_e( 'View my author page', 'syndicate-pro' ); ?></a>
						<a class="synpro-btn synpro-btn-ghost" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'syndicate-pro' ); ?></a>
					</p>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="synpro_account_save">
				<?php wp_nonce_field( 'synpro_account_save', 'synpro_account_nonce' ); ?>

				<h2><?php esc_html_e( 'About you', 'syndicate-pro' ); ?></h2>
				<p class="synpro-field">
					<label for="synpro-display-name"><?php esc_html_e( 'Display name', 'syndicate-pro' ); ?></label>
					<input type="text" id="synpro-display-name" name="synpro_display_name" value="<?php echo esc_attr( $user->display_name ); ?>">
				</p>
				<p class="synpro-field">
					<label for="synpro-bio"><?php esc_html_e( 'Bio', 'syndicate-pro' ); ?></label>
					<textarea id="synpro-bio" name="synpro_bio" rows="4"><?php echo esc_textarea( get_user_meta( $user->ID, 'description', true ) ); ?></textarea>
				</p>

				<?php
				// The same feeds + author-page fields as the wp-admin profile
				// screen — one set of fields, one save path.
				Synpro_Profile::render_fields( $user );
				?>

				<p class="synpro-actions"><button type="submit" class="synpro-btn"><?php esc_html_e( 'Save my profile', 'syndicate-pro' ); ?></button></p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle a My Account save.
	 */
	public static function handle_account_save() {
		if ( ! isset( $_POST['synpro_account_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['synpro_account_nonce'] ), 'synpro_account_save' ) ) {
			wp_die( esc_html__( 'Session expired — please go back and try again.', 'syndicate-pro' ) );
		}
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_die( esc_html__( 'Please log in first.', 'syndicate-pro' ) );
		}

		$userdata = array( 'ID' => $user_id );
		if ( isset( $_POST['synpro_display_name'] ) ) {
			$name = sanitize_text_field( wp_unslash( $_POST['synpro_display_name'] ) );
			if ( '' !== $name ) {
				$userdata['display_name'] = $name;
			}
		}
		if ( isset( $_POST['synpro_bio'] ) ) {
			$userdata['description'] = sanitize_textarea_field( wp_unslash( $_POST['synpro_bio'] ) );
		}
		if ( count( $userdata ) > 1 ) {
			wp_update_user( $userdata );
		}

		// Feeds + author-page fields: same nonce-checked save as wp-admin.
		Synpro_Profile::save_fields( $user_id );

		$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'synpro_saved', '1', remove_query_arg( 'synpro_saved', $back ) ) );
		exit;
	}

	/**
	 * Create the Submit Content and My Account pages on activation
	 * (once — deleting a page won't resurrect it on the next update).
	 */
	public static function ensure_pages() {
		if ( get_option( 'synpro_pages_created' ) ) {
			return;
		}
		$pages = array(
			'submit-content' => array( __( 'Submit Content', 'syndicate-pro' ), '[synpro_submit]' ),
			'my-account'     => array( __( 'My Account', 'syndicate-pro' ), '[synpro_account]' ),
		);
		$ids = array();
		foreach ( $pages as $slug => $page ) {
			$existing = get_page_by_path( $slug );
			if ( $existing ) {
				$ids[ $slug ] = $existing->ID;
				continue;
			}
			$ids[ $slug ] = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $page[0],
					'post_name'    => $slug,
					'post_content' => $page[1],
				)
			);
		}
		update_option( 'synpro_pages_created', $ids );
	}
}

endif;
