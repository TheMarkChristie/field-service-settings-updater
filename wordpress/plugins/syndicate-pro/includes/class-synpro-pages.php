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
		add_shortcode( 'synpro_write', array( __CLASS__, 'shortcode_write' ) );
		add_action( 'admin_post_synpro_submit_content', array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_synpro_account_save', array( __CLASS__, 'handle_account_save' ) );
		add_action( 'admin_post_synpro_write_post', array( __CLASS__, 'handle_write_post' ) );
	}

	/**
	 * The URL of one of the plugin-created pages ('' when unknown).
	 *
	 * @param string $slug Page slug key from ensure_pages().
	 * @return string
	 */
	public static function page_url( $slug ) {
		$ids = (array) get_option( 'synpro_pages_created', array() );
		if ( empty( $ids[ $slug ] ) || 'publish' !== get_post_status( (int) $ids[ $slug ] ) ) {
			return '';
		}
		$url = get_permalink( (int) $ids[ $slug ] );
		return $url ? $url : '';
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
			<?php if ( self::page_url( 'write-a-post' ) ) : ?>
				<p class="synpro-help">
					<?php esc_html_e( 'No blog, podcast, or channel of your own yet?', 'syndicate-pro' ); ?>
					<a href="<?php echo esc_url( self::page_url( 'write-a-post' ) ); ?>"><?php esc_html_e( 'Write your post right here on the site', 'syndicate-pro' ); ?></a>
				</p>
			<?php endif; ?>
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
					<?php // type=text, not url: the YouTube option takes a bare channel ID, which browser URL validation would reject. The server validates per type. ?>
				<input type="text" inputmode="url" id="synpro-submit-url" name="synpro_url" required placeholder="<?php echo esc_attr( $prompts['blog']['placeholder'] ); ?>">
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
	 * Write-a-post screen: members without a blog write their post here;
	 * it goes in as Pending and a site admin approves it.
	 *
	 * @return string
	 */
	public static function shortcode_write() {
		if ( ! is_user_logged_in() ) {
			return self::login_card();
		}
		$user_id = get_current_user_id();
		if ( ! Synpro_Profile::is_member( $user_id ) ) {
			return '<div class="synpro-page synpro-login-card"><h3>' . esc_html__( 'Almost there', 'syndicate-pro' ) . '</h3><p>'
				. esc_html__( 'Your account isn’t enabled for publishing yet. An administrator needs to upgrade you to a Contributor — get in touch and we’ll sort it.', 'syndicate-pro' ) . '</p></div>';
		}

		$categories = get_categories( array( 'hide_empty' => false ) );

		ob_start();
		?>
		<div class="synpro-page synpro-write">
			<?php if ( isset( $_GET['synpro_written'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<p class="synpro-notice synpro-notice-ok"><?php esc_html_e( 'Thanks — your post has been submitted for approval! A site admin will review it, and it goes live (and out to our social channels) once approved.', 'syndicate-pro' ); ?></p>
				<?php if ( isset( $_GET['synpro_image_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<p class="synpro-notice synpro-notice-err"><?php esc_html_e( 'Heads up: your image couldn’t be uploaded (too large, or not a JPG/PNG/WebP/GIF). The post was submitted without it — the category image will be used instead.', 'syndicate-pro' ); ?></p>
				<?php endif; ?>
			<?php elseif ( isset( $_GET['synpro_write_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<p class="synpro-notice synpro-notice-err"><?php esc_html_e( 'Your post needs at least a title and some content — please try again.', 'syndicate-pro' ); ?></p>
			<?php endif; ?>

			<p class="synpro-help">
				<?php esc_html_e( 'No blog of your own? Write your post right here. It’s reviewed by a site admin before going live, and it’s published under your name on your author page like any other post.', 'syndicate-pro' ); ?>
				<?php if ( self::page_url( 'submit-content' ) ) : ?>
					<?php esc_html_e( 'Already blogging somewhere?', 'syndicate-pro' ); ?>
					<a href="<?php echo esc_url( self::page_url( 'submit-content' ) ); ?>"><?php esc_html_e( 'Connect your feed instead', 'syndicate-pro' ); ?></a>
				<?php endif; ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="synpro_write_post">
				<?php wp_nonce_field( 'synpro_write_post' ); ?>

				<p class="synpro-field">
					<label for="synpro-write-title"><?php esc_html_e( 'Title', 'syndicate-pro' ); ?></label>
					<input type="text" id="synpro-write-title" name="synpro_title" required maxlength="200">
				</p>

				<div class="synpro-field">
					<label for="synpro-write-content"><?php esc_html_e( 'Your post', 'syndicate-pro' ); ?></label>
					<?php
					wp_editor(
						'',
						'synpro-write-content',
						array(
							'textarea_name' => 'synpro_content',
							'textarea_rows' => 14,
							'media_buttons' => false,
							'quicktags'     => false,
						)
					);
					?>
				</div>

				<fieldset class="synpro-field synpro-cats">
					<legend><?php esc_html_e( 'Category(ies)', 'syndicate-pro' ); ?></legend>
					<div class="synpro-cat-grid">
						<?php foreach ( $categories as $category ) : ?>
							<label class="synpro-chip"><input type="checkbox" name="synpro_categories[]" value="<?php echo (int) $category->term_id; ?>"> <?php echo esc_html( $category->name ); ?></label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<p class="synpro-field">
					<label for="synpro-write-image"><?php esc_html_e( 'Featured image (optional)', 'syndicate-pro' ); ?></label>
					<input type="file" id="synpro-write-image" name="synpro_image" accept="image/jpeg,image/png,image/webp,image/gif">
					<span class="synpro-help"><?php esc_html_e( 'JPG, PNG, or WebP. Without one, the category’s image is used.', 'syndicate-pro' ); ?></span>
				</p>

				<p class="synpro-actions"><button type="submit" class="synpro-btn"><?php esc_html_e( 'Submit for approval', 'syndicate-pro' ); ?></button></p>
			</form>

			<?php
			$mine = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => array( 'pending', 'draft', 'publish' ),
					'author'         => $user_id,
					'posts_per_page' => 10,
					'meta_query'     => array( array( 'key' => '_synpro_onsite', 'compare' => 'EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			?>
			<?php if ( $mine ) : ?>
				<h3 class="synpro-sources-title"><?php esc_html_e( 'Your posts on this site', 'syndicate-pro' ); ?></h3>
				<ul class="synpro-sources">
					<?php foreach ( $mine as $post ) : ?>
						<li>
							<span class="synpro-source-url">
								<?php if ( 'publish' === $post->post_status ) : ?>
									<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
								<?php else : ?>
									<?php echo esc_html( get_the_title( $post ) ); ?>
								<?php endif; ?>
							</span>
							<span class="synpro-source-status <?php echo 'publish' === $post->post_status ? 'is-ok' : 'is-warn'; ?>">
								<?php
								if ( 'publish' === $post->post_status ) {
									esc_html_e( 'Published', 'syndicate-pro' );
								} elseif ( 'pending' === $post->post_status ) {
									esc_html_e( 'Awaiting approval', 'syndicate-pro' );
								} else {
									esc_html_e( 'Draft', 'syndicate-pro' );
								}
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle a written-on-site post: insert as Pending for admin approval.
	 */
	public static function handle_write_post() {
		check_admin_referer( 'synpro_write_post' );

		$user_id = get_current_user_id();
		if ( ! $user_id || ! Synpro_Profile::is_member( $user_id ) ) {
			wp_die( esc_html__( 'You need a publishing-enabled account to write a post.', 'syndicate-pro' ) );
		}

		$back    = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$back    = remove_query_arg( array( 'synpro_written', 'synpro_write_error' ), $back );
		$title   = isset( $_POST['synpro_title'] ) ? sanitize_text_field( wp_unslash( $_POST['synpro_title'] ) ) : '';
		$content = isset( $_POST['synpro_content'] ) ? wp_kses_post( wp_unslash( $_POST['synpro_content'] ) ) : '';

		// "Empty" must catch TinyMCE's <p>&nbsp;</p> too — decode entities
		// and trim non-breaking spaces before judging.
		$plain = trim( html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES ), " \t\n\r\0\x0B\xC2\xA0" );
		if ( '' === $title || '' === $plain ) {
			wp_safe_redirect( add_query_arg( 'synpro_write_error', '1', $back ) );
			exit;
		}

		// wp_insert_post expects slashed data (it unslashes internally) —
		// without wp_slash, member-typed backslashes would be stripped.
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'     => 'post',
					'post_status'   => 'pending',
					'post_author'   => $user_id,
					'post_title'    => $title,
					'post_content'  => $content,
					'post_category' => isset( $_POST['synpro_categories'] ) ? array_map( 'absint', (array) $_POST['synpro_categories'] ) : array(),
				)
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			wp_safe_redirect( add_query_arg( 'synpro_write_error', '1', $back ) );
			exit;
		}

		// Written on this site (vs imported) — drives the status list above
		// and means no source attribution or redirect is ever applied.
		update_post_meta( $post_id, '_synpro_onsite', 1 );

		// Optional featured image upload. Restricted to images (Contributors
		// don't normally hold upload_files — this form deliberately grants a
		// narrow, image-only version of it, tied to their own pending post).
		$image_failed = false;
		if ( ! empty( $_FILES['synpro_image']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attachment_id = media_handle_upload(
				'synpro_image',
				$post_id,
				array(),
				array(
					'mimes' => array(
						'jpg|jpeg|jpe' => 'image/jpeg',
						'png'          => 'image/png',
						'webp'         => 'image/webp',
						'gif'          => 'image/gif',
					),
				)
			);
			if ( is_wp_error( $attachment_id ) ) {
				$image_failed = true;
			} else {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		// Tell the admins there's something to review.
		wp_mail(
			get_option( 'admin_email' ),
			sprintf(
				/* translators: %s: post title. */
				__( '[%1$s] New post awaiting approval: %2$s', 'syndicate-pro' ),
				get_bloginfo( 'name' ),
				$title
			),
			sprintf(
				/* translators: 1: author name, 2: post title, 3: review URL. */
				__( "%1\$s wrote a post on the site:\n\n\"%2\$s\"\n\nReview and approve it here:\n%3\$s", 'syndicate-pro' ),
				get_the_author_meta( 'display_name', $user_id ),
				$title,
				admin_url( 'post.php?post=' . $post_id . '&action=edit' )
			)
		);

		wp_safe_redirect( add_query_arg( $image_failed ? array( 'synpro_written' => '1', 'synpro_image_error' => '1' ) : array( 'synpro_written' => '1' ), $back ) );
		exit;
	}

	/**
	 * Create the member pages on activation. Each page is created at most
	 * once per slug — deleting a page won't resurrect it on the next
	 * update, but pages added in newer versions are still created.
	 */
	public static function ensure_pages() {
		$pages = array(
			'submit-content' => array( __( 'Submit Content', 'syndicate-pro' ), '[synpro_submit]' ),
			'my-account'     => array( __( 'My Account', 'syndicate-pro' ), '[synpro_account]' ),
			'write-a-post'   => array( __( 'Write a Post', 'syndicate-pro' ), '[synpro_write]' ),
		);
		$ids = (array) get_option( 'synpro_pages_created', array() );

		foreach ( $pages as $slug => $page ) {
			if ( array_key_exists( $slug, $ids ) ) {
				continue; // Already handled once (even if since deleted).
			}
			$existing = get_page_by_path( $slug );
			if ( $existing ) {
				$ids[ $slug ] = $existing->ID;
				continue;
			}
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $page[0],
					'post_name'    => $slug,
					'post_content' => $page[1],
				)
			);
			// Record only real IDs — a failed insert must retry next
			// activation, not be remembered as "handled".
			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$ids[ $slug ] = $page_id;
			}
		}
		update_option( 'synpro_pages_created', $ids );
	}
}

endif;
