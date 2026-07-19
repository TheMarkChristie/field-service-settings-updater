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
		add_action( 'admin_post_prx3_merge_members', array( __CLASS__, 'handle_merge' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_prx3_save_settings', array( __CLASS__, 'save' ) );
	}

	/**
	 * Register the top-level Fan Ownership admin menu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'Fan App Settings', 'fan-ownership' ),
			__( 'Fan App Settings', 'fan-ownership' ),
			'prx3_admin',
			'prx3-settings',
			array( __CLASS__, 'render' ),
			'dashicons-admin-generic',
			3.4
		);
		add_submenu_page(
			'prx3-settings',
			__( 'Member Tools', 'fan-ownership' ),
			__( 'Member Tools', 'fan-ownership' ),
			'prx3_admin',
			'prx3-member-tools',
			array( __CLASS__, 'render_member_tools' )
		);
		add_submenu_page(
			'prx3-settings',
			__( 'Feature Switches', 'fan-ownership' ),
			__( 'Features', 'fan-ownership' ),
			'prx3_admin',
			'prx3-settings',
			array( __CLASS__, 'render' )
		);
		foreach ( self::sections() as $section => $def ) {
			// Board-level sections (Legal, Targets, Governance) live in the
			// Board menu: readable by tally-view holders, edited by admins.
			add_submenu_page(
				$def[2],
				$def[0],
				$def[0],
				'prx3-board' === $def[2] ? 'prx3_view_tally' : 'prx3_admin',
				$def[1],
				function () use ( $section ) {
					self::render_section( $section );
				}
			);
		}
	}

	/**
	 * Section registry: fields() key => [submenu label, page slug].
	 *
	 * @return array
	 */
	private static function sections() {
		return array(
			'club'         => array( __( 'Club', 'fan-ownership' ), 'prx3-settings-club', 'prx3-settings' ),
			'brand pack'   => array( __( 'Brand Pack', 'fan-ownership' ), 'prx3-settings-brand', 'prx3-settings' ),
			'legal'        => array( __( 'Legal', 'fan-ownership' ), 'prx3-settings-legal', 'prx3-board' ),
			'ticketing'    => array( __( 'Ticketing', 'fan-ownership' ), 'prx3-settings-ticketing', 'prx3-settings' ),
			'shares'       => array( __( 'Shares & Checkout', 'fan-ownership' ), 'prx3-settings-shares', 'prx3-settings' ),
			'targets'      => array( __( 'Targets', 'fan-ownership' ), 'prx3-settings-targets', 'prx3-board' ),
			'governance'   => array( __( 'Governance', 'fan-ownership' ), 'prx3-settings-governance', 'prx3-board' ),
			'fanpress'     => array( __( 'FanPress Chat', 'fan-ownership' ), 'prx3-settings-fanpress', 'prx3-settings' ),
			'integrations' => array( __( 'API & Integrations', 'fan-ownership' ), 'prx3-settings-api', 'prx3-settings' ),
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
				'club_name'         => array( __( 'Club name', 'fan-ownership' ), 'text' ),
				'sport'             => array( __( 'Sport (drives Match Centre events and language)', 'fan-ownership' ), 'sport' ),
				'currency_symbol'   => array( __( 'Currency symbol', 'fan-ownership' ), 'text' ),
				'welcome_video_url' => array( __( 'Welcome video URL (embed URL, shown to new owners on the hub)', 'fan-ownership' ), 'text' ),
				'weekly_show_day'   => array( __( 'Weekly show day (0 = Sunday … 6 = Saturday; blank = no standing slot)', 'fan-ownership' ), 'text' ),
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
				'shopify_domain'         => array( __( 'Shopify store domain (e.g. club.myshopify.com)', 'fan-ownership' ), 'text' ),
				'shopify_webhook_secret' => array( __( 'Shopify webhook signing secret', 'fan-ownership' ), 'password' ),
				'shopify_share_variants' => array( __( 'Shopify variant IDs per tier, comma-separated, tier 1 first', 'fan-ownership' ), 'textarea' ),
				'share_base_price'       => array( __( 'Share 1 price', 'fan-ownership' ), 'number' ),
				'share_tier_growth'      => array( __( 'Tier growth (0.25 = +25% per share)', 'fan-ownership' ), 'number' ),
				'max_shares'             => array( __( 'Maximum shares per member', 'fan-ownership' ), 'number' ),
				'join_page_id'           => array( __( 'Join page ID', 'fan-ownership' ), 'number' ),
				'account_page_id'        => array( __( 'Account page ID (the [prx3_account] page)', 'fan-ownership' ), 'number' ),
				'launch_moment'          => array( __( 'Public launch moment (Founders cutoff, e.g. 2026-09-01 12:00)', 'fan-ownership' ), 'text' ),
			),
			'targets'      => array(
				'target_owners'  => array( __( 'Owner target (drives the dashboard meter)', 'fan-ownership' ), 'number' ),
				'target_revenue' => array( __( 'Financial target for the season (club currency; 0 hides the meter)', 'fan-ownership' ), 'number' ),
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
			'fanpress'     => array(
				'chat_color_mine'   => array( __( 'My chat bubble colour (hex, e.g. #dcf8c6)', 'fan-ownership' ), 'text' ),
				'chat_color_theirs' => array( __( "Other owners' bubble colour (hex)", 'fan-ownership' ), 'text' ),
				'chat_color_board'  => array( __( "Board members' bubble colour (hex)", 'fan-ownership' ), 'text' ),
			),
			'integrations' => array(
				'cf_account_id'       => array( __( 'Cloudflare account ID', 'fan-ownership' ), 'text' ),
				'cf_api_token'        => array( __( 'Cloudflare API token', 'fan-ownership' ), 'password' ),
				'cf_stream_key_id'    => array( __( 'Stream signing key ID', 'fan-ownership' ), 'text' ),
				'cf_stream_key_pem'   => array( __( 'Stream signing key (PEM)', 'fan-ownership' ), 'textarea' ),
				'fcm_server_key'      => array( __( 'Firebase FCM server key', 'fan-ownership' ), 'password' ),
				'chat_blocklist'      => array( __( 'Chat word filter (one per line)', 'fan-ownership' ), 'textarea' ),
				'data_api_enabled'    => array( __( 'Data API enabled (1 = on; write access for trusted automation)', 'fan-ownership' ), 'number' ),
				'data_api_key'        => array( __( 'Data API key (sent as X-Prx3-Data-Key)', 'fan-ownership' ), 'password' ),
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

		if ( class_exists( 'PRX3_Pages' ) ) {
			PRX3_Pages::panel();
		}

		echo '<h2>' . esc_html__( 'Settings sections', 'fan-ownership' ) . '</h2><ul class="ul-disc">';
		foreach ( self::sections() as $def ) {
			echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=' . $def[1] ) ) . '">' . esc_html( $def[0] ) . '</a>' . ( 'prx3-board' === $def[2] ? ' <em>' . esc_html__( '(in the Board menu)', 'fan-ownership' ) . '</em>' : '' ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Read-only view of a board-level section for board members without
	 * the admin capability: values only, no form, secrets masked.
	 *
	 * @param string $section Section key from fields().
	 */
	private static function render_section_readonly( $section ) {
		$label = self::sections()[ $section ][0] ?? ucfirst( $section );
		echo '<div class="wrap"><h1>' . esc_html( $label ) . '</h1>';
		echo '<p>' . esc_html__( 'Read-only view — changes are made by an Owner-Admin.', 'fan-ownership' ) . '</p>';
		echo '<table class="widefat striped"><tbody>';
		foreach ( self::fields()[ $section ] ?? array() as $key => $def ) {
			$value = prx3_setting( $key, PRX3_Config::defaults()[ $key ] ?? '' );
			if ( 'password' === $def[1] ) {
				$display = '' !== (string) $value ? '••••••••' : '—';
			} elseif ( 'media' === $def[1] ) {
				$display = $value ? sprintf( /* translators: %d attachment id. */ __( 'uploaded (attachment #%d)', 'fan-ownership' ), (int) $value ) : __( 'not set', 'fan-ownership' );
			} elseif ( 'sport' === $def[1] ) {
				$display = PRX3_Config::sports()[ $value ]['label'] ?? (string) $value;
			} else {
				$display = '' !== (string) $value ? wp_strip_all_tags( (string) $value ) : '—';
			}
			echo '<tr><th scope="row">' . esc_html( $def[0] ) . '</th><td>' . esc_html( $display ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render one settings section as its own page, with the section's
	 * exports and tools beneath the form.
	 *
	 * @param string $section Section key from fields().
	 */
	public static function render_section( $section ) {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			$parent = self::sections()[ $section ][2] ?? 'prx3-settings';
			if ( 'prx3-board' === $parent && ( current_user_can( 'prx3_view_tally' ) || current_user_can( 'prx3_board' ) ) ) {
				self::render_section_readonly( $section );
				return;
			}
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		$label = self::sections()[ $section ][0] ?? ucfirst( $section );
		echo '<div class="wrap"><h1>' . esc_html( sprintf( /* translators: %s section. */ __( 'Settings — %s', 'fan-ownership' ), $label ) ) . '</h1>';
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice.
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'fan-ownership' ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'prx3_save_settings' );
		echo '<input type="hidden" name="action" value="prx3_save_settings">';
		echo '<input type="hidden" name="prx3_redirect" value="' . esc_attr( self::sections()[ $section ][1] ?? 'prx3-settings' ) . '">';
		$fields = self::fields()[ $section ] ?? array();
		echo '<table class="form-table" role="presentation">';
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
		echo '<p><button class="button button-primary">' . esc_html__( 'Save settings', 'fan-ownership' ) . '</button></p></form>';

		if ( 'shares' === $section ) {
			echo '<h2>' . esc_html__( 'Share register', 'fan-ownership' ) . '</h2>';
			echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=prx3_export_register' ), 'prx3_export_register' ) ) . '">' . esc_html__( 'Export register of members (CSV)', 'fan-ownership' ) . '</a></p>';
		}
		if ( 'ticketing' === $section ) {
			echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=prx3_export_ticketing' ), 'prx3_export_ticketing' ) ) . '">' . esc_html( sprintf( /* translators: %s ticketing provider. */ __( 'Export %s discount codes (CSV)', 'fan-ownership' ), PRX3_Ticketing::provider() ) ) . '</a></p>';
		}
		if ( 'legal' === $section ) {
			echo '<p class="description"><strong>' . esc_html__( 'Before bumping the agreement version:', 'fan-ownership' ) . '</strong> ' . esc_html__( 'a material adverse change to the Shareholders\' Agreement must pass a member ballot first (agreement clause 7). Bumping the version re-prompts every owner to re-sign.', 'fan-ownership' ) . '</p>';
		}
		if ( 'integrations' === $section ) {
			PRX3_Data_API::connection_panel();
		}
		if ( 'brand pack' === $section ) {
			echo '<p><a class="button" href="' . esc_url( home_url( '/brand-pack/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View printable brand pack (print to PDF to supply it)', 'fan-ownership' ) . '</a></p>';
		}
		echo '</div>';
		foreach ( self::fields()[ $section ] ?? array() as $def ) {
			if ( in_array( $def[1], array( 'media', 'gallery' ), true ) ) {
				self::media_picker_js();
				break;
			}
		}
	}

	/**
	 * The Media Library picker script for media and gallery fields.
	 */
	private static function media_picker_js() {
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
		$slug  = isset( $_POST['prx3_redirect'] ) ? sanitize_key( wp_unslash( $_POST['prx3_redirect'] ) ) : 'prx3-settings';
		$valid = array_merge( array( 'prx3-settings' ), wp_list_pluck( self::sections(), 1 ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . ( in_array( $slug, $valid, true ) ? $slug : 'prx3-settings' ) . '&saved=1' ) );
		exit;
	}
	/**
	 * Member Tools: merge duplicate accounts (FO-110). Shares move from
	 * the duplicate to the kept account through the register, the SHA
	 * acceptance is preserved, and the duplicate loses its owner role.
	 */
	public static function render_member_tools() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Member Tools', 'fan-ownership' ) . '</h1>';
		echo '<h2>' . esc_html__( 'Merge duplicate accounts', 'fan-ownership' ) . '</h2>';
		echo '<p>' . esc_html__( 'Moves every share from the duplicate to the kept account (both movements recorded in the register), keeps the earlier agreement acceptance, removes the duplicate\'s owner role, and audits the merge. The duplicate account itself is not deleted — close it from the Users screen when you are satisfied.', 'fan-ownership' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Merge the duplicate into the kept account?', 'fan-ownership' ) ) . '\');">';
		wp_nonce_field( 'prx3_merge_members' );
		echo '<input type="hidden" name="action" value="prx3_merge_members">';
		echo '<p><label for="prx3_merge_from">' . esc_html__( 'Duplicate account (user ID)', 'fan-ownership' ) . '</label> <input type="number" id="prx3_merge_from" name="from" required min="1"></p>';
		echo '<p><label for="prx3_merge_into">' . esc_html__( 'Kept account (user ID)', 'fan-ownership' ) . '</label> <input type="number" id="prx3_merge_into" name="into" required min="1"></p>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Merge accounts', 'fan-ownership' ) . '</button></p></form></div>';
	}

	/**
	 * Perform the merge: register-recorded share movement, cap enforced.
	 *
	 * @param int $from Duplicate account.
	 * @param int $into Kept account.
	 * @return true|WP_Error
	 */
	public static function merge_members( $from, $into ) {
		$from = (int) $from;
		$into = (int) $into;
		if ( ! $from || ! $into || $from === $into || ! get_userdata( $from ) || ! get_userdata( $into ) ) {
			return new WP_Error( 'prx3_merge_bad', __( 'Both accounts must exist and differ.', 'fan-ownership' ) );
		}
		$moving = prx3_shares( $from );
		if ( $moving > 0 ) {
			$granted = PRX3_Shares::grant_shares( $into, $moving, 'merge_in', array( 'from' => $from ) );
			if ( is_wp_error( $granted ) ) {
				return $granted;
			}
			PRX3_Shares::surrender_all( $from, 'merge_out', array( 'into' => $into ) );
		}
		$sha = get_user_meta( $from, 'prx3_sha_accepted', true );
		if ( $sha && ! get_user_meta( $into, 'prx3_sha_accepted', true ) ) {
			update_user_meta( $into, 'prx3_sha_accepted', $sha );
		}
		$user = get_userdata( $from );
		if ( $user && method_exists( $user, 'remove_role' ) ) {
			$user->remove_role( 'fan_owner' );
		}
		PRX3_Audit::log( 'members_merged', sprintf( 'Member %1$d merged into %2$d (%3$d shares moved)', $from, $into, $moving ) );
		return true;
	}

	/**
	 * Merge from the Member Tools form.
	 */
	public static function handle_merge() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Owner-Admins only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_merge_members' );
		$result = self::merge_members( isset( $_POST['from'] ) ? absint( $_POST['from'] ) : 0, isset( $_POST['into'] ) ? absint( $_POST['into'] ) : 0 );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-member-tools&merged=1' ) );
		exit;
	}
}
