<?php
/**
 * Settings screen: identity, prices, governance numbers, integrations,
 * kill switches. Owner-Admins only.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Owner-Admin settings screen: renders and saves every platform
 * setting (identity, brand pack, prices, governance numbers, integrations)
 * and the FO-103 feature kill switches.
 */
class PRX3_Admin {

	/**
	 * Hook the menu page and the settings save handler.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_prx3_save_settings', array( __CLASS__, 'save' ) );
	}

	/**
	 * Register the top-level Fan Ownership admin menu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'Fan Ownership Settings', 'fan-ownership' ),
			__( 'Settings', 'fan-ownership' ),
			'prx3_admin',
			'prx3-settings',
			array( __CLASS__, 'render' ),
			'dashicons-admin-generic',
			3.4
		);
		add_submenu_page(
			'prx3-settings',
			__( 'Fan Ownership Settings', 'fan-ownership' ),
			__( 'Settings', 'fan-ownership' ),
			'prx3_admin',
			'prx3-settings',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * The settings form definition, grouped by section.
	 *
	 * @return array<string,array<string,array>> Section => setting key => array of label and field type.
	 */
	private static function fields() {
		return array(
			'club'         => array(
				'club_name'       => array( __( 'Club name', 'fan-ownership' ), 'text' ),
				'sport'           => array( __( 'Sport (drives Match Centre events and language)', 'fan-ownership' ), 'sport' ),
				'currency_symbol' => array( __( 'Currency symbol', 'fan-ownership' ), 'text' ),
			),
			'brand pack'   => array(
				'club_mission'              => array( __( 'Mission statement (rich text — shown on the brand pack and available to the app)', 'fan-ownership' ), 'richtext' ),
				'club_primary'              => array( __( 'Primary colour (hex)', 'fan-ownership' ), 'text' ),
				'club_secondary'            => array( __( 'Secondary colour (hex)', 'fan-ownership' ), 'text' ),
				'club_tertiary'             => array( __( 'Third colour (hex)', 'fan-ownership' ), 'text' ),
				'brand_font_name'           => array( __( 'Brand font name (as used in CSS)', 'fan-ownership' ), 'text' ),
				'brand_font_file_id'        => array( __( 'Brand font file (woff2/ttf)', 'fan-ownership' ), 'media' ),
				'brand_badge_id'            => array( __( 'Badge / crest', 'fan-ownership' ), 'media' ),
				'brand_badge_inverted_id'   => array( __( 'Inverted badge (for dark backgrounds)', 'fan-ownership' ), 'media' ),
				'brand_badge_social_id'     => array( __( 'Social media badge (square)', 'fan-ownership' ), 'media' ),
				'brand_badge_svg_id'        => array( __( 'SVG badge (vector master)', 'fan-ownership' ), 'media' ),
				'brand_wordmark_id'         => array( __( 'Wordmark / logotype', 'fan-ownership' ), 'media' ),
				'brand_favicon_id'          => array( __( 'Favicon', 'fan-ownership' ), 'media' ),
				'brand_app_icon_id'         => array( __( 'App icon (1024px square)', 'fan-ownership' ), 'media' ),
				'brand_email_header_id'     => array( __( 'Email header image', 'fan-ownership' ), 'media' ),
				'brand_badge_mono_dark_id'  => array( __( 'Monochrome badge — dark (for light backgrounds)', 'fan-ownership' ), 'media' ),
				'brand_badge_mono_light_id' => array( __( 'Monochrome badge — light (for dark backgrounds)', 'fan-ownership' ), 'media' ),
				'club_primary_print'        => array( __( 'Primary print spec (CMYK / Pantone)', 'fan-ownership' ), 'text' ),
				'club_secondary_print'      => array( __( 'Secondary print spec (CMYK / Pantone)', 'fan-ownership' ), 'text' ),
				'club_tertiary_print'       => array( __( 'Third colour print spec (CMYK / Pantone)', 'fan-ownership' ), 'text' ),
				'brand_secondary_font_name' => array( __( 'Secondary / body font name', 'fan-ownership' ), 'text' ),
				'club_tagline'              => array( __( 'Tagline / motto', 'fan-ownership' ), 'text' ),
				'club_legal_name'           => array( __( 'Legal name', 'fan-ownership' ), 'text' ),
				'club_short_name'           => array( __( 'Short name', 'fan-ownership' ), 'text' ),
				'club_abbreviation'         => array( __( 'Abbreviation / initials', 'fan-ownership' ), 'text' ),
				'club_founded'              => array( __( 'Founded (year)', 'fan-ownership' ), 'text' ),
				'brand_social_handles'      => array( __( 'Official social handles (one per line)', 'fan-ownership' ), 'textarea' ),
				'brand_contact'             => array( __( 'Brand queries contact (email)', 'fan-ownership' ), 'text' ),
				'brand_usage_notes'         => array( __( 'Brand usage notes (clear space, minimum sizes, do/do-not)', 'fan-ownership' ), 'textarea' ),
				'brand_club_stamp_id'       => array( __( 'Club stamp (placed on executed documents)', 'fan-ownership' ), 'media' ),
			),
			'legal'        => array(
				'sha_page_id'                => array( __( 'Shareholders\' Agreement page ID (the supplied document)', 'fan-ownership' ), 'number' ),
				'sha_version'                => array( __( 'Shareholders\' Agreement version (bump to require re-acceptance)', 'fan-ownership' ), 'text' ),
				'terms_page_id'              => array( __( 'Terms of Membership page ID', 'fan-ownership' ), 'number' ),
				'sha_signatory_name'         => array( __( 'Board signatory name (countersigns executed copies)', 'fan-ownership' ), 'text' ),
				'sha_signatory_role'         => array( __( 'Board signatory role (e.g. Director)', 'fan-ownership' ), 'text' ),
				'sha_signatory_signature_id' => array( __( 'Board signatory signature image', 'fan-ownership' ), 'media' ),
			),
			'ticketing'    => array(
				'ticketing_provider'                 => array( __( 'Ticketing provider name', 'fan-ownership' ), 'text' ),
				'matchday_ticket_discount_per_share' => array( __( 'Matchday discount % per share', 'fan-ownership' ), 'number' ),
				'season_ticket_discount_per_share'   => array( __( 'Season ticket discount % per share', 'fan-ownership' ), 'number' ),
			),
			'shares'       => array(
				'share_base_price'  => array( __( 'Share 1 price', 'fan-ownership' ), 'number' ),
				'share_tier_growth' => array( __( 'Tier growth (0.25 = +25% per share)', 'fan-ownership' ), 'number' ),
				'max_shares'        => array( __( 'Maximum shares per member', 'fan-ownership' ), 'number' ),
				'share_product_id'  => array( __( 'WooCommerce share product ID', 'fan-ownership' ), 'number' ),
				'checkout_page_id'  => array( __( 'Checkout page ID', 'fan-ownership' ), 'number' ),
				'join_page_id'      => array( __( 'Join page ID', 'fan-ownership' ), 'number' ),
				'invoice_prefix'    => array( __( 'Invoice prefix', 'fan-ownership' ), 'text' ),
				'launch_moment'     => array( __( 'Public launch moment (Founders cutoff, e.g. 2026-09-01 12:00)', 'fan-ownership' ), 'text' ),
			),
			'governance'   => array(
				'quorum_percent'      => array( __( 'Quorum % of active owners', 'fan-ownership' ), 'number' ),
				'constitutional_pct'  => array( __( 'Constitutional supermajority %', 'fan-ownership' ), 'number' ),
				'idea_threshold_pct'  => array( __( 'Idea support threshold %', 'fan-ownership' ), 'number' ),
				'ballot_window_days'  => array( __( 'Default ballot window (days)', 'fan-ownership' ), 'number' ),
				'max_live_ballots'    => array( __( 'Max live ballots', 'fan-ownership' ), 'number' ),
				'board_chair_id'      => array( __( 'Board chair user ID (casting vote in board votes)', 'fan-ownership' ), 'number' ),
				'question_sla_days'   => array( __( 'Question answer target (days)', 'fan-ownership' ), 'number' ),
				'decision_stale_days' => array( __( 'Decision stalled after (days)', 'fan-ownership' ), 'number' ),
			),
			'integrations' => array(
				'cf_account_id'       => array( __( 'Cloudflare account ID', 'fan-ownership' ), 'text' ),
				'cf_api_token'        => array( __( 'Cloudflare API token', 'fan-ownership' ), 'password' ),
				'cf_stream_key_id'    => array( __( 'Stream signing key ID', 'fan-ownership' ), 'text' ),
				'cf_stream_key_pem'   => array( __( 'Stream signing key (PEM)', 'fan-ownership' ), 'textarea' ),
				'fcm_server_key'      => array( __( 'Firebase FCM server key', 'fan-ownership' ), 'password' ),
				'chat_blocklist'      => array( __( 'Chat word filter (one per line)', 'fan-ownership' ), 'textarea' ),
				'sync_enabled'        => array( __( 'Power Platform sync enabled (1 = on)', 'fan-ownership' ), 'number' ),
				'sync_api_key'        => array( __( 'Sync API key (Power Automate sends this as X-Prx3-Api-Key)', 'fan-ownership' ), 'password' ),
				'sync_webhook_url'    => array( __( 'Outbound webhook URL (Power Automate HTTP trigger)', 'fan-ownership' ), 'text' ),
				'sync_webhook_secret' => array( __( 'Webhook signing secret (HMAC-SHA256, X-Prx3-Signature)', 'fan-ownership' ), 'password' ),
			),
		);
	}

	/**
	 * Render the settings screen: kill switches, the settings form,
	 * register exports and the brand pack link.
	 */
	public static function render() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Fan Ownership Platform', 'fan-ownership' ) . '</h1>';

		// Kill switches (FO-103).
		echo '<h2>' . esc_html__( 'Feature switches', 'fan-ownership' ) . '</h2><p>';
		foreach ( PRX3_Config::FEATURES as $feature ) {
			$on = prx3_feature_on( $feature );
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 8px 8px 0;">';
			wp_nonce_field( 'prx3_toggle_feature' );
			echo '<input type="hidden" name="action" value="prx3_toggle_feature"><input type="hidden" name="feature" value="' . esc_attr( $feature ) . '">';
			echo '<button class="button ' . ( $on ? 'button-secondary' : 'button-primary' ) . '">' . esc_html( $feature . ': ' . ( $on ? __( 'ON — switch off', 'fan-ownership' ) : __( 'OFF — switch on', 'fan-ownership' ) ) ) . '</button></form>';
		}
		echo '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'prx3_save_settings' );
		echo '<input type="hidden" name="action" value="prx3_save_settings">';
		foreach ( self::fields() as $section => $fields ) {
			echo '<h2>' . esc_html( ucfirst( $section ) ) . '</h2><table class="form-table" role="presentation">';
			foreach ( $fields as $key => $def ) {
				$value = prx3_setting( $key, PRX3_Config::defaults()[ $key ] ?? '' );
				echo '<tr><th scope="row"><label for="prx3_' . esc_attr( $key ) . '">' . esc_html( $def[0] ) . '</label></th><td>';
				if ( 'textarea' === $def[1] ) {
					echo '<textarea class="large-text" rows="3" id="prx3_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
				} elseif ( 'sport' === $def[1] ) {
					echo '<select id="prx3_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';
					foreach ( PRX3_Config::sports() as $sport_key => $sport ) {
						echo '<option value="' . esc_attr( $sport_key ) . '" ' . selected( $value, $sport_key, false ) . '>' . esc_html( $sport['label'] ) . '</option>';
					}
					echo '</select>';
				} elseif ( 'richtext' === $def[1] ) {
					wp_editor(
						(string) $value,
						'prx3_' . $key,
						array(
							'textarea_name' => $key,
							'textarea_rows' => 6,
							'media_buttons' => false,
						)
					);
				} elseif ( 'gallery' === $def[1] ) {
					$ids = array_filter( array_map( 'absint', explode( ',', (string) $value ) ) );
					echo '<div class="prx3-media-field">';
					echo '<input type="hidden" id="prx3_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '">';
					echo '<span class="prx3-media-preview">';
					foreach ( array_slice( $ids, 0, 6 ) as $gallery_id ) {
						echo wp_kses_post( wp_get_attachment_image( $gallery_id, array( 40, 40 ) ) );
					}
					if ( $ids ) {
						echo ' <em>' . esc_html( sprintf( /* translators: %d image count. */ __( '%d selected', 'fan-ownership' ), count( $ids ) ) ) . '</em>';
					}
					echo '</span> ';
					echo '<button type="button" class="button prx3-media-pick" data-multiple="1" data-target="prx3_' . esc_attr( $key ) . '">' . esc_html__( 'Choose images', 'fan-ownership' ) . '</button> ';
					echo '<button type="button" class="button prx3-media-clear" data-target="prx3_' . esc_attr( $key ) . '">' . esc_html__( 'Clear', 'fan-ownership' ) . '</button>';
					echo '</div>';
				} elseif ( 'media' === $def[1] ) {
					$attachment_id = (int) $value;
					$preview       = $attachment_id ? wp_get_attachment_image( $attachment_id, array( 60, 60 ) ) : '';
					echo '<div class="prx3-media-field">';
					echo '<input type="hidden" id="prx3_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $attachment_id ? $attachment_id : '' ) . '">';
					echo '<span class="prx3-media-preview">' . wp_kses_post( $preview ) . '</span> ';
					echo '<button type="button" class="button prx3-media-pick" data-target="prx3_' . esc_attr( $key ) . '">' . esc_html__( 'Upload / choose', 'fan-ownership' ) . '</button> ';
					echo '<button type="button" class="button prx3-media-clear" data-target="prx3_' . esc_attr( $key ) . '">' . esc_html__( 'Clear', 'fan-ownership' ) . '</button>';
					if ( $attachment_id && ! $preview ) {
						echo ' <a href="' . esc_url( (string) wp_get_attachment_url( $attachment_id ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View file', 'fan-ownership' ) . '</a>';
					}
					echo '</div>';
				} else {
					$type = 'password' === $def[1] ? 'password' : 'text';
					echo '<input type="' . esc_attr( $type ) . '" class="regular-text" id="prx3_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">';
				}
				echo '</td></tr>';
			}
			echo '</table>';
		}
		echo '<p><button class="button button-primary">' . esc_html__( 'Save settings', 'fan-ownership' ) . '</button></p></form>';

		echo '<h2>' . esc_html__( 'Share register', 'fan-ownership' ) . '</h2>';
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=prx3_export_register' ), 'prx3_export_register' ) ) . '">' . esc_html__( 'Export register of members (CSV)', 'fan-ownership' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=prx3_export_ticketing' ), 'prx3_export_ticketing' ) ) . '">' . esc_html(
			sprintf(
				/* translators: %s ticketing provider. */
				__( 'Export %s discount codes (CSV)', 'fan-ownership' ),
				PRX3_Ticketing::provider()
			)
		) . '</a></p>';
		echo '<h2>' . esc_html__( 'Printable brand pack', 'fan-ownership' ) . '</h2>';
		echo '<p><a class="button" href="' . esc_url( home_url( '/brand-pack/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View printable brand pack (print to PDF to supply it)', 'fan-ownership' ) . '</a></p>';
		echo '</div>';

		// Media Library pickers for the brand pack fields.
		wp_enqueue_media();
		wp_add_inline_script(
			'media-editor',
			"
			document.addEventListener('click', function (event) {
				var pick = event.target.closest('.prx3-media-pick');
				var clear = event.target.closest('.prx3-media-clear');
				if (clear) {
					var target = document.getElementById(clear.getAttribute('data-target'));
					if (target) { target.value = ''; clear.closest('.prx3-media-field').querySelector('.prx3-media-preview').innerHTML = ''; }
					return;
				}
				if (!pick) { return; }
				var multiple = pick.getAttribute('data-multiple') === '1';
				var frame = wp.media({ title: 'Brand asset', multiple: multiple });
				frame.on('select', function () {
					var selection = frame.state().get('selection').toJSON();
					var attachment = selection[0];
					var target = document.getElementById(pick.getAttribute('data-target'));
					if (target) { target.value = multiple ? selection.map(function (a) { return a.id; }).join(',') : attachment.id; }
					var preview = pick.closest('.prx3-media-field').querySelector('.prx3-media-preview');
					if (preview && attachment.sizes && attachment.sizes.thumbnail) {
						preview.innerHTML = '<img src=\"' + attachment.sizes.thumbnail.url + '\" style=\"max-height:60px\" alt=\"\">';
					} else if (preview) {
						preview.textContent = attachment.filename;
					}
				});
				frame.open();
			});
		"
		);
	}

	/**
	 * Save posted settings, sanitised per field type, then audit and redirect.
	 */
	public static function save() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_save_settings' );
		foreach ( self::fields() as $fields ) {
			foreach ( $fields as $key => $def ) {
				if ( ! isset( $_POST[ $key ] ) ) {
					continue;
				}
				$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				if ( 'media' === $def[1] ) {
					prx3_update_setting( $key, absint( $raw ) );
				} elseif ( 'gallery' === $def[1] ) {
					prx3_update_setting( $key, implode( ',', array_filter( array_map( 'absint', explode( ',', (string) $raw ) ) ) ) );
				} elseif ( 'richtext' === $def[1] ) {
					prx3_update_setting( $key, wp_kses_post( $raw ) );
				} elseif ( 'sport' === $def[1] ) {
					$sport = sanitize_key( $raw );
					prx3_update_setting( $key, array_key_exists( $sport, PRX3_Config::sports() ) ? $sport : 'generic' );
				} elseif ( 'textarea' === $def[1] ) {
					prx3_update_setting( $key, sanitize_textarea_field( $raw ) );
				} else {
					prx3_update_setting( $key, sanitize_text_field( $raw ) );
				}
			}
		}
		PRX3_Audit::log( 'settings_saved', 'Platform settings updated' );
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-settings&saved=1' ) );
		exit;
	}
}
