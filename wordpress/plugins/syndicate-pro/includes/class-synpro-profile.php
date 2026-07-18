<?php
/**
 * Member profile fields.
 *
 * Members (Contributor role and above) manage their own feed records,
 * images, links, and author-page display from their profile screen
 * (Users → Profile). Administrators can edit any member's fields.
 *
 * @package Synpro_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Synpro_Profile' ) ) :

class Synpro_Profile {

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
	 * Whether a user is eligible for syndication (Contributor+, FR-1.5).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_member( $user_id ) {
		return user_can( $user_id, 'edit_posts' );
	}

	/**
	 * The social/link fields members can fill in.
	 *
	 * @return array key => label.
	 */
	public static function link_fields() {
		return array(
			'synpro_link_website'  => __( 'Website', 'syndicate-pro' ),
			'synpro_link_blog'     => __( 'Blog', 'syndicate-pro' ),
			'synpro_link_mvp'      => __( 'Microsoft MVP profile', 'syndicate-pro' ),
			'synpro_link_linkedin' => __( 'LinkedIn', 'syndicate-pro' ),
			'synpro_link_twitter'  => __( 'X / Twitter', 'syndicate-pro' ),
			'synpro_link_bluesky'  => __( 'Bluesky', 'syndicate-pro' ),
			'synpro_link_github'   => __( 'GitHub', 'syndicate-pro' ),
			'synpro_link_youtube'  => __( 'YouTube', 'syndicate-pro' ),
			'synpro_link_mastodon' => __( 'Mastodon', 'syndicate-pro' ),
		);
	}

	/**
	 * The author-page section toggles.
	 *
	 * @return array key => label.
	 */
	public static function section_toggles() {
		return array(
			'synpro_show_blogs'    => __( 'Show my blog posts', 'syndicate-pro' ),
			'synpro_show_podcasts' => __( 'Show my podcast episodes', 'syndicate-pro' ),
			'synpro_show_videos'   => __( 'Show my videos', 'syndicate-pro' ),
			'synpro_show_events'   => __( 'Show my events', 'syndicate-pro' ),
			'synpro_show_links'    => __( 'Show my links', 'syndicate-pro' ),
			'synpro_show_bio'      => __( 'Show my bio', 'syndicate-pro' ),
		);
	}

	/**
	 * Render the profile fields.
	 *
	 * @param WP_User $user User being edited.
	 */
	public static function render_fields( $user ) {
		if ( ! self::is_member( $user->ID ) ) {
			return;
		}

		wp_nonce_field( 'synpro_profile', 'synpro_profile_nonce' );

		self::render_feeds_section( $user );
		self::render_author_page_section( $user );
	}

	/**
	 * Feed records manager.
	 *
	 * @param WP_User $user User being edited.
	 */
	protected static function render_feeds_section( $user ) {
		$rows       = Synpro_Feeds::for_user( $user->ID );
		$types      = Synpro_Feeds::types();
		$categories = get_categories( array( 'hide_empty' => false ) );

		$category_select = function ( $name, $selected ) use ( $categories ) {
			$html = '<select name="' . esc_attr( $name ) . '[]" multiple size="3" style="min-width:180px;">';
			foreach ( $categories as $category ) {
				$html .= sprintf(
					'<option value="%d" %s>%s</option>',
					(int) $category->term_id,
					in_array( (int) $category->term_id, $selected, true ) ? 'selected' : '',
					esc_html( $category->name )
				);
			}
			return $html . '</select>';
		};

		$type_select = function ( $name, $selected ) use ( $types ) {
			$html = '<select name="' . esc_attr( $name ) . '">';
			foreach ( $types as $value => $label ) {
				$html .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
			}
			return $html . '</select>';
		};
		?>
		<h2><?php esc_html_e( 'Syndication feeds', 'syndicate-pro' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'The site checks these automatically and republishes new items under your name, with a link back to the original and a note that it is shared with your permission. You can add several feeds and point each at its own category. For YouTube, enter your channel ID (starts with “UC”). No RSS feed? Choose “Web page (no RSS)” and enter your blog’s listing page URL — the site will discover new articles on it automatically.', 'syndicate-pro' ); ?>
		</p>
		<table class="widefat striped" style="max-width:900px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'syndicate-pro' ); ?></th>
					<th><?php esc_html_e( 'Feed URL / channel ID', 'syndicate-pro' ); ?></th>
					<th><?php esc_html_e( 'Post into category(ies)', 'syndicate-pro' ); ?></th>
					<th title="<?php esc_attr_e( 'If your feed only contains summaries, tick this so the site fetches the full article text from your blog.', 'syndicate-pro' ); ?>"><?php esc_html_e( 'Full text', 'syndicate-pro' ); ?></th>
					<th><?php esc_html_e( 'Active', 'syndicate-pro' ); ?></th>
					<th><?php esc_html_e( 'Delete', 'syndicate-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php $prefix = 'synpro_feed_rows[' . (int) $row->id . ']'; ?>
					<tr>
						<td><?php echo $type_select( $prefix . '[type]', $row->type ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[feed_url]" value="<?php echo esc_attr( $row->feed_url ); ?>" class="regular-text"></td>
						<td><?php echo $category_select( $prefix . '[categories]', Synpro_Feeds::parse_categories( $row->categories ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[full_content]" value="1" <?php checked( ! empty( $row->full_content ) ); ?>></td>
						<td><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[active]" value="1" <?php checked( (int) $row->active ); ?>></td>
						<td><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[delete]" value="1"></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<td><?php echo $type_select( 'synpro_feed_rows[new][type]', 'blog' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td><input type="text" name="synpro_feed_rows[new][feed_url]" value="" class="regular-text" placeholder="https://myblog.com/feed/"></td>
					<td><?php echo $category_select( 'synpro_feed_rows[new][categories]', array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td><input type="checkbox" name="synpro_feed_rows[new][full_content]" value="1"></td>
					<td><input type="checkbox" name="synpro_feed_rows[new][active]" value="1" checked></td>
					<td>—</td>
				</tr>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Fill in the last row to add a feed; it is saved when you update the profile. The first fetch of a new feed imports its full history (existing posts on this site are detected and never duplicated).', 'syndicate-pro' ); ?></p>
		<?php
	}

	/**
	 * Author page presentation fields.
	 *
	 * @param WP_User $user User being edited.
	 */
	protected static function render_author_page_section( $user ) {
		$avatar_url = get_user_meta( $user->ID, 'synpro_avatar_url', true );
		$cover_url  = get_user_meta( $user->ID, 'synpro_cover_url', true );
		$tagline    = get_user_meta( $user->ID, 'synpro_tagline', true );
		?>
		<h2><?php esc_html_e( 'Author page', 'syndicate-pro' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Control how your public author page looks and what it shows. Your social links are also used to credit you by @handle when the site shares your posts to its social accounts.', 'syndicate-pro' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="synpro-tagline"><?php esc_html_e( 'Tagline', 'syndicate-pro' ); ?></label></th>
				<td><input type="text" class="regular-text" id="synpro-tagline" name="synpro_tagline" value="<?php echo esc_attr( $tagline ); ?>" placeholder="<?php esc_attr_e( 'e.g. Dynamics 365 Field Service MVP', 'syndicate-pro' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="synpro-avatar-url"><?php esc_html_e( 'Profile photo URL', 'syndicate-pro' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="synpro-avatar-url" name="synpro_avatar_url" value="<?php echo esc_attr( $avatar_url ); ?>">
					<p class="description"><?php esc_html_e( 'Used instead of your Gravatar across the site. Paste an image URL (upload one via the Media Library first if needed).', 'syndicate-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="synpro-cover-url"><?php esc_html_e( 'Cover image URL', 'syndicate-pro' ); ?></label></th>
				<td><input type="url" class="regular-text" id="synpro-cover-url" name="synpro_cover_url" value="<?php echo esc_attr( $cover_url ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Emails', 'syndicate-pro' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="synpro_digest" value="1" <?php checked( '0' !== (string) get_user_meta( $user->ID, 'synpro_digest', true ) ); ?>>
						<?php esc_html_e( 'Send me the weekly community digest email', 'syndicate-pro' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Sections to show', 'syndicate-pro' ); ?></th>
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
				<th><?php esc_html_e( 'Links', 'syndicate-pro' ); ?></th>
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
		if ( ! isset( $_POST['synpro_profile_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['synpro_profile_nonce'] ), 'synpro_profile' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) || ! self::is_member( $user_id ) ) {
			return;
		}

		self::save_feed_rows( $user_id );

		$url_fields = array_merge(
			array( 'synpro_avatar_url', 'synpro_cover_url' ),
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

		if ( isset( $_POST['synpro_tagline'] ) ) {
			update_user_meta( $user_id, 'synpro_tagline', sanitize_text_field( wp_unslash( $_POST['synpro_tagline'] ) ) );
		}

		// Checkboxes: unchecked boxes are absent from the POST, so store 0 explicitly.
		foreach ( array_keys( self::section_toggles() ) as $key ) {
			update_user_meta( $user_id, $key, empty( $_POST[ $key ] ) ? 0 : 1 );
		}
		update_user_meta( $user_id, 'synpro_digest', empty( $_POST['synpro_digest'] ) ? 0 : 1 );

		// Marks the member as verified on the Dashboard's unverified list.
		update_user_meta( $user_id, 'synpro_profile_updated', time() );
	}

	/**
	 * Persist the feed-records table edits.
	 *
	 * @param int $user_id User being saved.
	 */
	protected static function save_feed_rows( $user_id ) {
		if ( ! isset( $_POST['synpro_feed_rows'] ) || ! is_array( $_POST['synpro_feed_rows'] ) ) {
			return;
		}
		$rows = wp_unslash( $_POST['synpro_feed_rows'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- fields sanitised below.

		foreach ( $rows as $id => $row ) {
			$data = array(
				'type'         => isset( $row['type'] ) ? sanitize_key( $row['type'] ) : 'blog',
				'feed_url'     => isset( $row['feed_url'] ) ? trim( (string) $row['feed_url'] ) : '',
				'categories'   => isset( $row['categories'] ) ? array_map( 'absint', (array) $row['categories'] ) : array(),
				'active'       => ! empty( $row['active'] ),
				'full_content' => ! empty( $row['full_content'] ),
			);

			if ( 'new' === $id ) {
				if ( '' !== $data['feed_url'] ) {
					$data['user_id'] = $user_id;
					Synpro_Feeds::add( $data );
				}
				continue;
			}

			$existing = Synpro_Feeds::get( (int) $id );
			if ( ! $existing || (int) $existing->user_id !== (int) $user_id ) {
				continue; // Not this member's feed.
			}

			if ( ! empty( $row['delete'] ) || '' === $data['feed_url'] ) {
				Synpro_Feeds::delete( (int) $id );
			} else {
				Synpro_Feeds::update( (int) $id, $data );
			}
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
			$custom = get_user_meta( $user->ID, 'synpro_avatar_url', true );
			if ( $custom ) {
				return $custom;
			}
		}
		return $url;
	}
}

endif;
