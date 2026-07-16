<?php
/**
 * Member profile fields.
 *
 * Every website user can manage their own syndication feeds, images, links,
 * and how their author page is displayed — all from their own profile screen
 * (Users → Profile). Administrators can edit any member's fields.
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class C365_Profile {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'show_user_profile', array( __CLASS__, 'render_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_fields' ) );

		// Use the member's chosen profile image as their avatar everywhere.
		add_filter( 'get_avatar_url', array( __CLASS__, 'filter_avatar_url' ), 10, 2 );
	}

	/**
	 * The social/link fields members can fill in.
	 *
	 * @return array key => label.
	 */
	public static function link_fields() {
		return array(
			'c365_link_website'  => __( 'Website', 'c365-syndicator' ),
			'c365_link_blog'     => __( 'Blog', 'c365-syndicator' ),
			'c365_link_linkedin' => __( 'LinkedIn', 'c365-syndicator' ),
			'c365_link_twitter'  => __( 'X / Twitter', 'c365-syndicator' ),
			'c365_link_bluesky'  => __( 'Bluesky', 'c365-syndicator' ),
			'c365_link_github'   => __( 'GitHub', 'c365-syndicator' ),
			'c365_link_youtube'  => __( 'YouTube', 'c365-syndicator' ),
			'c365_link_mastodon' => __( 'Mastodon', 'c365-syndicator' ),
		);
	}

	/**
	 * The author-page section toggles.
	 *
	 * @return array key => label.
	 */
	public static function section_toggles() {
		return array(
			'c365_show_blogs'    => __( 'Show my blog posts', 'c365-syndicator' ),
			'c365_show_podcasts' => __( 'Show my podcast episodes', 'c365-syndicator' ),
			'c365_show_videos'   => __( 'Show my videos', 'c365-syndicator' ),
			'c365_show_events'   => __( 'Show my events', 'c365-syndicator' ),
			'c365_show_links'    => __( 'Show my links', 'c365-syndicator' ),
			'c365_show_bio'      => __( 'Show my bio', 'c365-syndicator' ),
		);
	}

	/**
	 * Render the profile fields.
	 *
	 * @param WP_User $user User being edited.
	 */
	public static function render_fields( $user ) {
		$blog_feed    = get_user_meta( $user->ID, 'c365_blog_feed', true );
		$blog_cat     = (int) get_user_meta( $user->ID, 'c365_blog_category', true );
		$podcast_feed = get_user_meta( $user->ID, 'c365_podcast_feed', true );
		$yt_channel   = get_user_meta( $user->ID, 'c365_youtube_channel', true );
		$events_feed  = get_user_meta( $user->ID, 'c365_events_feed', true );
		$avatar_url   = get_user_meta( $user->ID, 'c365_avatar_url', true );
		$cover_url    = get_user_meta( $user->ID, 'c365_cover_url', true );
		$tagline      = get_user_meta( $user->ID, 'c365_tagline', true );

		wp_nonce_field( 'c365_profile', 'c365_profile_nonce' );
		?>
		<h2><?php esc_html_e( 'Syndication feeds', 'c365-syndicator' ); ?></h2>
		<p class="description"><?php esc_html_e( 'The site checks these automatically and republishes new items under your name, with a link back to the original and a note that it is shared with your permission.', 'c365-syndicator' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="c365-blog-feed"><?php esc_html_e( 'Blog RSS feed URL', 'c365-syndicator' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="c365-blog-feed" name="c365_blog_feed" value="<?php echo esc_attr( $blog_feed ); ?>" placeholder="https://myblog.com/feed/">
				</td>
			</tr>
			<tr>
				<th><label for="c365-blog-cat"><?php esc_html_e( 'Post my blogs into category', 'c365-syndicator' ); ?></label></th>
				<td>
					<?php
					wp_dropdown_categories(
						array(
							'name'              => 'c365_blog_category',
							'id'                => 'c365-blog-cat',
							'selected'          => $blog_cat,
							'show_option_none'  => __( '— Default category —', 'c365-syndicator' ),
							'option_none_value' => 0,
							'hide_empty'        => false,
							'hierarchical'      => true,
						)
					);
					?>
				</td>
			</tr>
			<tr>
				<th><label for="c365-podcast-feed"><?php esc_html_e( 'Podcast RSS feed URL', 'c365-syndicator' ); ?></label></th>
				<td><input type="url" class="regular-text" id="c365-podcast-feed" name="c365_podcast_feed" value="<?php echo esc_attr( $podcast_feed ); ?>" placeholder="https://feeds.example.com/mypodcast"></td>
			</tr>
			<tr>
				<th><label for="c365-yt-channel"><?php esc_html_e( 'YouTube channel ID', 'c365-syndicator' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="c365-yt-channel" name="c365_youtube_channel" value="<?php echo esc_attr( $yt_channel ); ?>" placeholder="UCxxxxxxxxxxxxxxxxxxxxxx">
					<p class="description"><?php esc_html_e( 'Starts with “UC”. Found under YouTube Studio → Settings → Channel → Advanced settings.', 'c365-syndicator' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="c365-events-feed"><?php esc_html_e( 'Events feed URL (optional)', 'c365-syndicator' ); ?></label></th>
				<td><input type="url" class="regular-text" id="c365-events-feed" name="c365_events_feed" value="<?php echo esc_attr( $events_feed ); ?>"></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Author page', 'c365-syndicator' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Control how your public author page looks and what it shows.', 'c365-syndicator' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="c365-tagline"><?php esc_html_e( 'Tagline', 'c365-syndicator' ); ?></label></th>
				<td><input type="text" class="regular-text" id="c365-tagline" name="c365_tagline" value="<?php echo esc_attr( $tagline ); ?>" placeholder="<?php esc_attr_e( 'e.g. Dynamics 365 Field Service MVP', 'c365-syndicator' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="c365-avatar-url"><?php esc_html_e( 'Profile photo URL', 'c365-syndicator' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="c365-avatar-url" name="c365_avatar_url" value="<?php echo esc_attr( $avatar_url ); ?>">
					<p class="description"><?php esc_html_e( 'Used instead of your Gravatar across the site. Paste an image URL (upload one via Media Library first if needed).', 'c365-syndicator' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="c365-cover-url"><?php esc_html_e( 'Cover image URL', 'c365-syndicator' ); ?></label></th>
				<td><input type="url" class="regular-text" id="c365-cover-url" name="c365_cover_url" value="<?php echo esc_attr( $cover_url ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Sections to show', 'c365-syndicator' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( self::section_toggles() as $key => $label ) : ?>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( self::section_enabled( $user->ID, $key ) ); ?>>
								<?php echo esc_html( $label ); ?>
							</label><br>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Links', 'c365-syndicator' ); ?></th>
				<td>
					<?php foreach ( self::link_fields() as $key => $label ) : ?>
						<p>
							<label for="<?php echo esc_attr( $key ); ?>" style="display:inline-block;min-width:110px;"><?php echo esc_html( $label ); ?></label>
							<input type="url" class="regular-text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_user_meta( $user->ID, $key, true ) ); ?>">
						</p>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Whether an author-page section is enabled for a user. Defaults to on.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Toggle meta key.
	 * @return bool
	 */
	public static function section_enabled( $user_id, $key ) {
		$value = get_user_meta( $user_id, $key, true );
		return '' === $value ? true : (bool) (int) $value;
	}

	/**
	 * Save the profile fields.
	 *
	 * @param int $user_id User being saved.
	 */
	public static function save_fields( $user_id ) {
		if ( ! isset( $_POST['c365_profile_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['c365_profile_nonce'] ), 'c365_profile' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$url_fields = array_merge(
			array( 'c365_blog_feed', 'c365_podcast_feed', 'c365_events_feed', 'c365_avatar_url', 'c365_cover_url' ),
			array_keys( self::link_fields() )
		);
		foreach ( $url_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = esc_url_raw( wp_unslash( $_POST[ $field ] ) );
				if ( '' === $value ) {
					delete_user_meta( $user_id, $field );
				} else {
					update_user_meta( $user_id, $field, $value );
				}
			}
		}

		if ( isset( $_POST['c365_youtube_channel'] ) ) {
			$channel = sanitize_text_field( wp_unslash( $_POST['c365_youtube_channel'] ) );
			$channel = preg_replace( '/[^A-Za-z0-9_-]/', '', $channel );
			if ( '' === $channel ) {
				delete_user_meta( $user_id, 'c365_youtube_channel' );
			} else {
				update_user_meta( $user_id, 'c365_youtube_channel', $channel );
			}
		}

		if ( isset( $_POST['c365_tagline'] ) ) {
			update_user_meta( $user_id, 'c365_tagline', sanitize_text_field( wp_unslash( $_POST['c365_tagline'] ) ) );
		}

		if ( isset( $_POST['c365_blog_category'] ) ) {
			update_user_meta( $user_id, 'c365_blog_category', absint( $_POST['c365_blog_category'] ) );
		}

		// Checkboxes: unchecked boxes are absent from the POST, so store 0 explicitly.
		foreach ( array_keys( self::section_toggles() ) as $key ) {
			update_user_meta( $user_id, $key, empty( $_POST[ $key ] ) ? 0 : 1 );
		}
	}

	/**
	 * Serve the member's chosen profile photo as their avatar.
	 *
	 * @param string $url         Avatar URL.
	 * @param mixed  $id_or_email User identifier.
	 * @return string
	 */
	public static function filter_avatar_url( $url, $id_or_email ) {
		$user = false;
		if ( is_numeric( $id_or_email ) ) {
			$user = get_user_by( 'id', (int) $id_or_email );
		} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
		} elseif ( $id_or_email instanceof WP_User ) {
			$user = $id_or_email;
		} elseif ( $id_or_email instanceof WP_Post ) {
			$user = get_user_by( 'id', (int) $id_or_email->post_author );
		} elseif ( $id_or_email instanceof WP_Comment && $id_or_email->user_id ) {
			$user = get_user_by( 'id', (int) $id_or_email->user_id );
		}

		if ( $user ) {
			$custom = get_user_meta( $user->ID, 'c365_avatar_url', true );
			if ( $custom ) {
				return $custom;
			}
		}
		return $url;
	}
}
