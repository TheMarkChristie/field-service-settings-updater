<?php
/**
 * Site-wide settings, the Syndication admin menu, the feeds dashboard,
 * and the Social sharing tab.
 *
 * @package Synpro_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Synpro_Settings' ) ) :

class Synpro_Settings {

	const OPTION           = 'synpro_syndicator_settings';
	const TEMPLATES_OPTION = 'synpro_templates';

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'maybe_reschedule' ), 10, 2 );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'interval'           => 'synpro_5min', // Rotate to the next member every 5 minutes.
			'post_status'        => 'publish',   // Q2: publish immediately.
			'max_items'          => 5,           // Q16: 5 items per feed per fetch (backfill exempt).
			'set_featured_image' => 1,           // Q5: download to Media Library.
			'import_categories'  => 0,           // Optionally also map the item's own categories.
			'use_original_date'  => 1,           // Keep the original publish date.
			'attribution'        => 1,           // Source link + permission note below content.
			'canonical'          => 1,           // Q3: rel=canonical to the original.
			'redirect_original'  => 1,           // Send blog-post visitors to the author's site.
			'prune_enabled'      => 1,           // Auto-trash old low-interest imports.
			'prune_years'        => 3,           // ...older than this many years...
			'prune_views'        => 50,          // ...with fewer than this many views.
		);
	}

	/**
	 * Get one setting (with defaults applied).
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Register extra cron intervals.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		$schedules['synpro_5min'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes', 'syndicate-pro' ),
		);
		$schedules['synpro_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes', 'syndicate-pro' ),
		);
		return $schedules;
	}

	/**
	 * Reschedule the rotation when the interval changes.
	 *
	 * @param array $old Old settings.
	 * @param array $new New settings.
	 */
	public static function maybe_reschedule( $old, $new ) {
		$old_interval = isset( $old['interval'] ) ? $old['interval'] : 'synpro_5min';
		$new_interval = isset( $new['interval'] ) ? $new['interval'] : 'synpro_5min';
		if ( $old_interval !== $new_interval || ! wp_next_scheduled( SYNPRO_CRON_HOOK ) ) {
			wp_clear_scheduled_hook( SYNPRO_CRON_HOOK );
			wp_schedule_event( time() + 60, $new_interval, SYNPRO_CRON_HOOK );
		}
	}

	/**
	 * Add the top-level Syndication menu.
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'Syndicate Pro', 'syndicate-pro' ),
			__( 'Syndicate Pro', 'syndicate-pro' ),
			'manage_options',
			'synpro-syndication',
			array( __CLASS__, 'render_page' ),
			'dashicons-rss',
			58
		);
	}

	/**
	 * Register the settings.
	 */
	public static function register() {
		register_setting( 'synpro_syndicator', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
		register_setting( 'synpro_social', Synpro_Social::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_social' ) ) );
		register_setting( 'synpro_templates', self::TEMPLATES_OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_templates' ) ) );
		register_setting( 'synpro_emails', Synpro_Emails::OPTION, array( 'sanitize_callback' => array( 'Synpro_Emails', 'sanitize' ) ) );
	}

	/**
	 * The post types that have editable templates.
	 *
	 * @return array post_type => label.
	 */
	public static function template_types() {
		return array(
			'post'         => __( 'Blog posts', 'syndicate-pro' ),
			'synpro_event'   => __( 'Events', 'syndicate-pro' ),
			'synpro_podcast' => __( 'Podcast episodes', 'syndicate-pro' ),
			'synpro_video'   => __( 'Videos', 'syndicate-pro' ),
		);
	}

	/**
	 * Get the effective template for a post type.
	 *
	 * @param string $post_type Post type.
	 * @param string $kind      'post' (imported post body) or 'social'.
	 * @return string
	 */
	public static function get_template( $post_type, $kind ) {
		$stored = (array) get_option( self::TEMPLATES_OPTION, array() );
		if ( ! empty( $stored[ $post_type ][ $kind ] ) ) {
			return $stored[ $post_type ][ $kind ];
		}
		if ( 'social' === $kind && class_exists( 'Synpro_Social' ) ) {
			return Synpro_Social::default_template( $post_type );
		}
		return '{content}';
	}

	/**
	 * Sanitise the templates option.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_templates( $input ) {
		$input = (array) $input;
		$clean = array();
		foreach ( array_keys( self::template_types() ) as $post_type ) {
			$post_template   = isset( $input[ $post_type ]['post'] ) ? trim( wp_kses_post( (string) $input[ $post_type ]['post'] ) ) : '';
			$social_template = isset( $input[ $post_type ]['social'] ) ? trim( sanitize_textarea_field( (string) $input[ $post_type ]['social'] ) ) : '';
			if ( '' !== $post_template || '' !== $social_template ) {
				$clean[ $post_type ] = array(
					'post'   => $post_template,
					'social' => $social_template,
				);
			}
		}
		return $clean;
	}

	/**
	 * Sanitise submitted core settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input     = (array) $input;
		$defaults  = self::defaults();
		$intervals = array( 'synpro_5min', 'synpro_15min', 'hourly', 'twicedaily', 'daily' );
		$statuses  = array( 'publish', 'draft', 'pending', 'private' );

		return array(
			'interval'           => in_array( $input['interval'] ?? '', $intervals, true ) ? $input['interval'] : $defaults['interval'],
			'post_status'        => in_array( $input['post_status'] ?? '', $statuses, true ) ? $input['post_status'] : $defaults['post_status'],
			'max_items'          => min( 50, max( 1, absint( $input['max_items'] ?? $defaults['max_items'] ) ) ),
			'set_featured_image' => empty( $input['set_featured_image'] ) ? 0 : 1,
			'import_categories'  => empty( $input['import_categories'] ) ? 0 : 1,
			'use_original_date'  => empty( $input['use_original_date'] ) ? 0 : 1,
			'attribution'        => empty( $input['attribution'] ) ? 0 : 1,
			'canonical'          => empty( $input['canonical'] ) ? 0 : 1,
			'redirect_original'  => empty( $input['redirect_original'] ) ? 0 : 1,
			'prune_enabled'      => empty( $input['prune_enabled'] ) ? 0 : 1,
			'prune_years'        => min( 20, max( 1, absint( $input['prune_years'] ?? $defaults['prune_years'] ) ) ),
			'prune_views'        => min( 100000, max( 0, absint( $input['prune_views'] ?? $defaults['prune_views'] ) ) ),
		);
	}

	/**
	 * Sanitise submitted social settings. Empty credential fields keep the
	 * stored value so saving the form never wipes masked secrets.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_social( $input ) {
		$input  = (array) $input;
		$stored = Synpro_Social::settings();

		$secrets = array(
			'mastodon_token',
			'bluesky_app_password',
			'twitter_api_key',
			'twitter_api_secret',
			'twitter_access_token',
			'twitter_access_secret',
			'linkedin_token',
		);
		$clean = array();
		foreach ( $secrets as $key ) {
			$value = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
			$clean[ $key ] = '' === $value ? $stored[ $key ] : sanitize_text_field( $value );
		}

		$clean['mastodon_instance'] = isset( $input['mastodon_instance'] ) ? esc_url_raw( trim( (string) $input['mastodon_instance'] ) ) : '';
		$clean['bluesky_handle']    = isset( $input['bluesky_handle'] ) ? sanitize_text_field( $input['bluesky_handle'] ) : '';
		$clean['linkedin_org_urn']  = isset( $input['linkedin_org_urn'] ) ? sanitize_text_field( $input['linkedin_org_urn'] ) : '';

		$clean['enabled'] = array();
		foreach ( array_keys( Synpro_Social::networks() ) as $network ) {
			foreach ( Synpro_Social::SHAREABLE as $post_type ) {
				$key = $network . ':' . $post_type;
				$clean['enabled'][ $key ] = empty( $input['enabled'][ $key ] ) ? 0 : 1;
			}
		}

		// The Social tab form has no template fields (they live on the
		// Templates tab), so keep whatever is stored — otherwise every save
		// of this tab would silently wipe legacy/per-network overrides.
		if ( isset( $input['templates'] ) && is_array( $input['templates'] ) ) {
			$clean['templates'] = array();
			foreach ( $input['templates'] as $key => $template ) {
				$template = trim( sanitize_textarea_field( $template ) );
				if ( $template && preg_match( '/^[a-z]+:[a-z0-9_]+$/', $key ) ) {
					$clean['templates'][ $key ] = $template;
				}
			}
		} else {
			$clean['templates'] = isset( $stored['templates'] ) ? (array) $stored['templates'] : array();
		}

		return $clean;
	}

	/**
	 * Render the Syndication page (Dashboard / Settings / Social tabs).
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Self-heal: if the rotation event vanished (cron cleanup plugin,
		// failed reschedule), restore it when an admin opens this page —
		// saving unchanged settings would not fire the update hook.
		if ( ! wp_next_scheduled( SYNPRO_CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, self::get( 'interval' ), SYNPRO_CRON_HOOK );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Syndicate Pro', 'syndicate-pro' ); ?></h1>

			<?php self::render_notices(); ?>

			<nav class="nav-tab-wrapper">
				<?php
				$tabs = array(
					'dashboard' => __( 'Dashboard', 'syndicate-pro' ),
					'settings'  => __( 'Settings', 'syndicate-pro' ),
					'templates' => __( 'Templates', 'syndicate-pro' ),
					'social'    => __( 'Social sharing', 'syndicate-pro' ),
					'emails'    => __( 'Emails', 'syndicate-pro' ),
				);
				foreach ( $tabs as $key => $label ) {
					printf(
						'<a class="nav-tab %s" href="%s">%s</a>',
						$tab === $key ? 'nav-tab-active' : '',
						esc_url( admin_url( 'admin.php?page=synpro-syndication&tab=' . $key ) ),
						esc_html( $label )
					);
				}
				?>
			</nav>

			<?php
			if ( 'settings' === $tab ) {
				self::render_settings_tab();
			} elseif ( 'templates' === $tab ) {
				self::render_templates_tab();
			} elseif ( 'social' === $tab ) {
				self::render_social_tab();
			} elseif ( 'emails' === $tab ) {
				self::render_emails_tab();
			} else {
				self::render_dashboard_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Admin notices for fetch/test results.
	 */
	protected static function render_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['synpro_fetched'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( /* translators: %d: imported count. */ __( 'Fetch complete — %d new item(s) imported.', 'syndicate-pro' ), absint( $_GET['synpro_fetched'] ) ) )
			);
		}
		if ( isset( $_GET['synpro_test_ok'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( /* translators: %s: network name. */ __( 'Test post sent successfully via %s.', 'syndicate-pro' ), sanitize_key( $_GET['synpro_test_ok'] ) ) )
			);
		}
		if ( isset( $_GET['synpro_test_error'] ) ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s %s</p></div>',
				esc_html__( 'Test post failed:', 'syndicate-pro' ),
				esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['synpro_test_error'] ) ) ) )
			);
		}
		// phpcs:enable
	}

	/**
	 * Dashboard tab: rotation status + feed records table.
	 */
	protected static function render_dashboard_tab() {
		$next    = wp_next_scheduled( SYNPRO_CRON_HOOK );
		$members = Synpro_Fetcher::get_member_ids();
		$pointer = (int) get_option( 'synpro_rotation_pointer', 0 );
		$next_up = Synpro_Fetcher::next_member_id( $members, $pointer );
		$feeds   = Synpro_Feeds::all();
		?>
		<h2><?php esc_html_e( 'Rotation', 'syndicate-pro' ); ?></h2>
		<p>
			<?php
			if ( $next ) {
				printf(
					/* translators: %s: human-readable time difference. */
					esc_html__( 'Next rotation tick: in %s.', 'syndicate-pro' ),
					esc_html( human_time_diff( time(), $next ) )
				);
			} else {
				esc_html_e( 'No rotation is scheduled — save the Settings tab to schedule one.', 'syndicate-pro' );
			}
			echo ' ';
			printf(
				/* translators: %d: number of members. */
				esc_html__( '%d member(s) in the rotation.', 'syndicate-pro' ),
				count( $members )
			);
			?>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=synpro_fetch_all' ), 'synpro_fetch_all' ) ); ?>">
				<?php esc_html_e( 'Fetch all members now', 'syndicate-pro' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=synpro_backfill_all' ), 'synpro_backfill_all' ) ); ?>"
				onclick="return confirm('<?php echo esc_js( __( 'Re-import the full history of every feed? Nothing will be posted to social media, and existing posts are never duplicated.', 'syndicate-pro' ) ); ?>');">
				<?php esc_html_e( 'Run all historic (no social posting)', 'syndicate-pro' ); ?>
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'WP-Cron only runs when the site gets visits. For a reliable 5-minute rotation, add a hosting cron job requesting wp-cron.php every 5 minutes.', 'syndicate-pro' ); ?>
		</p>

		<h2><?php esc_html_e( 'Feeds', 'syndicate-pro' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Feeds are added on each member’s profile screen (Users → Profile).', 'syndicate-pro' ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Member', 'syndicate-pro' ); ?></th>
					<th><?php esc_html_e( 'Type', 'syndicate-pro' ); ?></th>
					<th><?php esc_html_e( 'Feed', 'syndicate-pro' ); ?></th>
					<th><?php esc_html_e( 'Categories', 'syndicate-pro' ); ?></th>
					<th><?php esc_html_e( 'Last checked', 'syndicate-pro' ); ?></th>
					<th><?php esc_html_e( 'Last result', 'syndicate-pro' ); ?></th>
					<th><?php esc_html_e( 'Fails', 'syndicate-pro' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $feeds ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No feeds configured yet.', 'syndicate-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $feeds as $row ) : ?>
						<?php
						$user  = get_user_by( 'id', (int) $row->user_id );
						$types = Synpro_Feeds::types();
						$cats  = array();
						foreach ( Synpro_Feeds::parse_categories( $row->categories ) as $cat_id ) {
							$term = get_term( $cat_id, 'category' );
							if ( $term && ! is_wp_error( $term ) ) {
								$cats[] = $term->name;
							}
						}
						$fetch_url = wp_nonce_url(
							admin_url( 'admin-post.php?action=synpro_fetch_feed&feed_id=' . (int) $row->id ),
							'synpro_fetch_feed_' . (int) $row->id
						);
						$backfill_url = wp_nonce_url(
							admin_url( 'admin-post.php?action=synpro_backfill_feed&feed_id=' . (int) $row->id ),
							'synpro_backfill_feed_' . (int) $row->id
						);
						$last = $row->last_fetch ? human_time_diff( strtotime( $row->last_fetch . ' UTC' ), time() ) . ' ' . __( 'ago', 'syndicate-pro' ) : '—';
						?>
						<tr>
							<td>
								<?php if ( $user ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><strong><?php echo esc_html( $user->display_name ); ?></strong></a>
									<?php if ( (int) $user->ID === (int) $next_up ) : ?>
										<span class="dashicons dashicons-controls-play" title="<?php esc_attr_e( 'Next in rotation', 'syndicate-pro' ); ?>"></span>
									<?php endif; ?>
								<?php else : ?>
									#<?php echo (int) $row->user_id; ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( isset( $types[ $row->type ] ) ? $types[ $row->type ] : $row->type ); ?></td>
							<td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
								<?php echo esc_html( $row->feed_url ); ?>
								<?php if ( ! empty( $row->full_content ) ) : ?>
									<em title="<?php esc_attr_e( 'Fetches the full article text from the source page', 'syndicate-pro' ); ?>">(<?php esc_html_e( 'full text', 'syndicate-pro' ); ?>)</em>
								<?php endif; ?>
								<?php if ( ! (int) $row->active ) : ?>
									<em>(<?php esc_html_e( 'inactive', 'syndicate-pro' ); ?>)</em>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( implode( ', ', $cats ) ); ?></td>
							<td><?php echo esc_html( $last ); ?></td>
							<td><?php echo esc_html( $row->last_result ? $row->last_result : '—' ); ?></td>
							<td><?php echo (int) $row->fail_count; ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( $fetch_url ); ?>"><?php esc_html_e( 'Fetch now', 'syndicate-pro' ); ?></a>
								<a class="button button-small" href="<?php echo esc_url( $backfill_url ); ?>" title="<?php esc_attr_e( 'Re-import this feed’s full history. No social posts are sent; existing posts are never duplicated.', 'syndicate-pro' ); ?>"><?php esc_html_e( 'Run historic', 'syndicate-pro' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Settings tab.
	 */
	protected static function render_settings_tab() {
		$s = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'synpro_syndicator' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="synpro-interval"><?php esc_html_e( 'Rotation interval', 'syndicate-pro' ); ?></label></th>
					<td>
						<select id="synpro-interval" name="<?php echo esc_attr( self::OPTION ); ?>[interval]">
							<?php
							$options = array(
								'synpro_5min'  => __( 'Every 5 minutes (one member per tick)', 'syndicate-pro' ),
								'synpro_15min' => __( 'Every 15 minutes', 'syndicate-pro' ),
								'hourly'     => __( 'Hourly', 'syndicate-pro' ),
								'twicedaily' => __( 'Twice daily', 'syndicate-pro' ),
								'daily'      => __( 'Daily', 'syndicate-pro' ),
							);
							foreach ( $options as $value => $label ) {
								printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $s['interval'], $value, false ), esc_html( $label ) );
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="synpro-status"><?php esc_html_e( 'Imported content status', 'syndicate-pro' ); ?></label></th>
					<td>
						<select id="synpro-status" name="<?php echo esc_attr( self::OPTION ); ?>[post_status]">
							<?php
							$statuses = array(
								'publish' => __( 'Published', 'syndicate-pro' ),
								'draft'   => __( 'Draft', 'syndicate-pro' ),
								'pending' => __( 'Pending review', 'syndicate-pro' ),
								'private' => __( 'Private', 'syndicate-pro' ),
							);
							foreach ( $statuses as $value => $label ) {
								printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $s['post_status'], $value, false ), esc_html( $label ) );
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="synpro-max"><?php esc_html_e( 'Max items per feed per fetch', 'syndicate-pro' ); ?></label></th>
					<td>
						<input id="synpro-max" type="number" min="1" max="50" name="<?php echo esc_attr( self::OPTION ); ?>[max_items]" value="<?php echo esc_attr( $s['max_items'] ); ?>">
						<p class="description"><?php esc_html_e( 'The first fetch of a new feed ignores this cap and imports the feed’s full history (duplicates are detected by GUID and title).', 'syndicate-pro' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Options', 'syndicate-pro' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[set_featured_image]" value="1" <?php checked( $s['set_featured_image'] ); ?>>
								<?php esc_html_e( 'Import the item image as the featured image', 'syndicate-pro' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[import_categories]" value="1" <?php checked( $s['import_categories'] ); ?>>
								<?php esc_html_e( 'Also create/assign categories from the feed item’s own categories (blog posts only)', 'syndicate-pro' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[use_original_date]" value="1" <?php checked( $s['use_original_date'] ); ?>>
								<?php esc_html_e( 'Use the original publish date on imported content', 'syndicate-pro' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[attribution]" value="1" <?php checked( $s['attribution'] ); ?>>
								<?php esc_html_e( 'Append the source link and “shared with permission” note below the content (skipped automatically when the active theme renders its own)', 'syndicate-pro' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[canonical]" value="1" <?php checked( $s['canonical'] ); ?>>
								<?php esc_html_e( 'Point rel="canonical" at the original article (recommended for SEO on syndicated content)', 'syndicate-pro' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[redirect_original]" value="1" <?php checked( $s['redirect_original'] ); ?>>
								<?php esc_html_e( 'Redirect visitors opening a syndicated blog post to the original article on the author\'s site (the view is counted first; editors and previews are never redirected)', 'syndicate-pro' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Content pruning', 'syndicate-pro' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[prune_enabled]" value="1" <?php checked( $s['prune_enabled'] ); ?>>
								<?php esc_html_e( 'Automatically move old, low-interest imported content to the bin (runs daily)', 'syndicate-pro' ); ?>
							</label>
							<p>
								<?php esc_html_e( 'Older than', 'syndicate-pro' ); ?>
								<input type="number" min="1" max="20" style="width:70px" name="<?php echo esc_attr( self::OPTION ); ?>[prune_years]" value="<?php echo esc_attr( $s['prune_years'] ); ?>">
								<?php esc_html_e( 'years with fewer than', 'syndicate-pro' ); ?>
								<input type="number" min="0" max="100000" style="width:90px" name="<?php echo esc_attr( self::OPTION ); ?>[prune_views]" value="<?php echo esc_attr( $s['prune_views'] ); ?>">
								<?php esc_html_e( 'views. Only imported content is pruned; manually written posts are never touched. Binned posts are recoverable for 30 days.', 'syndicate-pro' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Templates tab: per content type, the imported-post body template and
	 * the social announcement template.
	 */
	protected static function render_templates_tab() {
		$stored = (array) get_option( self::TEMPLATES_OPTION, array() );
		?>
		<p class="description" style="max-width:760px;">
			<?php esc_html_e( 'Customise how imported content and social announcements are built, per content type. Leave a box empty to use the default. Placeholders:', 'syndicate-pro' ); ?>
		</p>
		<p>
			<code>{content}</code> <?php esc_html_e( 'full original text', 'syndicate-pro' ); ?> &nbsp;
			<code>{title}</code> &nbsp;
			<code>{author}</code> <?php esc_html_e( '(member name; @handle in social posts where available)', 'syndicate-pro' ); ?> &nbsp;
			<code>{excerpt}</code> <?php esc_html_e( '(one sentence in social posts)', 'syndicate-pro' ); ?> &nbsp;
			<code>{link}</code> <?php esc_html_e( '(this site’s post URL — social only)', 'syndicate-pro' ); ?> &nbsp;
			<code>{source_name}</code> &nbsp; <code>{source_url}</code> &nbsp; <code>{date}</code> <?php esc_html_e( '(original publish date)', 'syndicate-pro' ); ?> &nbsp;
			<code>{hashtags}</code> <?php esc_html_e( '(categories as #hashtags — social only)', 'syndicate-pro' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'synpro_templates' ); ?>

			<?php foreach ( self::template_types() as $post_type => $label ) : ?>
				<h2><?php echo esc_html( $label ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="synpro-tpl-post-<?php echo esc_attr( $post_type ); ?>"><?php esc_html_e( 'Post body template', 'syndicate-pro' ); ?></label>
						</th>
						<td>
							<textarea class="large-text code" rows="4"
								id="synpro-tpl-post-<?php echo esc_attr( $post_type ); ?>"
								name="<?php echo esc_attr( self::TEMPLATES_OPTION ); ?>[<?php echo esc_attr( $post_type ); ?>][post]"
								placeholder="{content}"><?php echo esc_textarea( isset( $stored[ $post_type ]['post'] ) ? $stored[ $post_type ]['post'] : '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'HTML allowed. Default {content} imports the original text unchanged. Example: <p><em>From {source_name}, {date}:</em></p>{content}', 'syndicate-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="synpro-tpl-social-<?php echo esc_attr( $post_type ); ?>"><?php esc_html_e( 'Social post template', 'syndicate-pro' ); ?></label>
						</th>
						<td>
							<textarea class="large-text code" rows="4"
								id="synpro-tpl-social-<?php echo esc_attr( $post_type ); ?>"
								name="<?php echo esc_attr( self::TEMPLATES_OPTION ); ?>[<?php echo esc_attr( $post_type ); ?>][social]"
								placeholder="<?php echo esc_attr( class_exists( 'Synpro_Social' ) ? Synpro_Social::default_template( $post_type ) : '' ); ?>"><?php echo esc_textarea( isset( $stored[ $post_type ]['social'] ) ? $stored[ $post_type ]['social'] : '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Plain text. Applied to every connected network, then truncated to each network’s length limit (excerpt shrinks first, then hashtags drop — never the title or link).', 'syndicate-pro' ); ?></p>
						</td>
					</tr>
				</table>
			<?php endforeach; ?>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Emails tab: welcome email and weekly digest.
	 */
	protected static function render_emails_tab() {
		$s      = wp_parse_args( (array) get_option( Synpro_Emails::OPTION, array() ), Synpro_Emails::defaults() );
		$option = Synpro_Emails::OPTION;
		$next   = wp_next_scheduled( Synpro_Emails::CRON_HOOK );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'synpro_emails' ); ?>

			<h2><?php esc_html_e( 'Welcome email', 'syndicate-pro' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Sent to a member once, when their first piece of content goes live on the site. Placeholders: {name} {title} {link} {profile_url} {site_name}.', 'syndicate-pro' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Enabled', 'syndicate-pro' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[welcome_enabled]" value="1" <?php checked( $s['welcome_enabled'] ); ?>> <?php esc_html_e( 'Send the welcome email', 'syndicate-pro' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="synpro-welcome-subject"><?php esc_html_e( 'Subject', 'syndicate-pro' ); ?></label></th>
					<td><input type="text" class="large-text" id="synpro-welcome-subject" name="<?php echo esc_attr( $option ); ?>[welcome_subject]" value="<?php echo esc_attr( $s['welcome_subject'] ); ?>"></td>
				</tr>
				<tr>
					<th><label for="synpro-welcome-body"><?php esc_html_e( 'Body', 'syndicate-pro' ); ?></label></th>
					<td><textarea class="large-text" rows="12" id="synpro-welcome-body" name="<?php echo esc_attr( $option ); ?>[welcome_body]"><?php echo esc_textarea( $s['welcome_body'] ); ?></textarea></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Weekly digest', 'syndicate-pro' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'The week’s top 10 blog posts (by views, newest first as a tiebreak), emailed weekly to everyone who hasn’t opted out on their profile.', 'syndicate-pro' ); ?>
				<?php
				if ( $next ) {
					echo ' ';
					printf(
						/* translators: %s: human-readable time difference. */
						esc_html__( 'Next send: in %s.', 'syndicate-pro' ),
						esc_html( human_time_diff( time(), $next ) )
					);
				}
				?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Enabled', 'syndicate-pro' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[digest_enabled]" value="1" <?php checked( $s['digest_enabled'] ); ?>> <?php esc_html_e( 'Send the weekly digest', 'syndicate-pro' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="synpro-digest-subject"><?php esc_html_e( 'Subject', 'syndicate-pro' ); ?></label></th>
					<td><input type="text" class="large-text" id="synpro-digest-subject" name="<?php echo esc_attr( $option ); ?>[digest_subject]" value="<?php echo esc_attr( $s['digest_subject'] ); ?>"></td>
				</tr>
				<tr>
					<th><label for="synpro-digest-intro"><?php esc_html_e( 'Intro text', 'syndicate-pro' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="3" id="synpro-digest-intro" name="<?php echo esc_attr( $option ); ?>[digest_intro]"><?php echo esc_textarea( $s['digest_intro'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'The numbered top-10 list is appended automatically below this text.', 'syndicate-pro' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Social sharing tab.
	 */
	protected static function render_social_tab() {
		$s          = Synpro_Social::settings();
		$networks   = Synpro_Social::networks();
		$post_types = array(
			'post'         => __( 'Blog posts', 'syndicate-pro' ),
			'synpro_event'   => __( 'Events', 'syndicate-pro' ),
			'synpro_podcast' => __( 'Podcasts', 'syndicate-pro' ),
			'synpro_video'   => __( 'Videos', 'syndicate-pro' ),
		);
		$option = Synpro_Social::OPTION;

		$secret_field = function ( $name, $has_value, $placeholder = '' ) use ( $option ) {
			printf(
				'<input type="password" class="regular-text" name="%s[%s]" value="" placeholder="%s" autocomplete="new-password">',
				esc_attr( $option ),
				esc_attr( $name ),
				esc_attr( $has_value ? __( '•••••• (saved — leave blank to keep)', 'syndicate-pro' ) : $placeholder )
			);
		};
		?>
		<p class="description" style="max-width:720px;">
			<?php esc_html_e( 'When new content is published, it is announced on the community’s own accounts below, e.g. “New Post: {title} by {author}” with a one-sentence excerpt, the link, category hashtags, and the featured image. Members are credited by their @handle when their profile links provide one. Credentials are stored server-side and never shown in full.', 'syndicate-pro' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'synpro_social' ); ?>

			<h2>Mastodon</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label><?php esc_html_e( 'Instance URL', 'syndicate-pro' ); ?></label></th>
					<td><input type="url" class="regular-text" name="<?php echo esc_attr( $option ); ?>[mastodon_instance]" value="<?php echo esc_attr( $s['mastodon_instance'] ); ?>" placeholder="https://mastodon.social"></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Access token', 'syndicate-pro' ); ?></label></th>
					<td><?php $secret_field( 'mastodon_token', (bool) $s['mastodon_token'] ); ?>
					<p class="description"><?php esc_html_e( 'Mastodon → Preferences → Development → New application (write:statuses, write:media).', 'syndicate-pro' ); ?></p></td>
				</tr>
			</table>

			<h2>Bluesky</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label><?php esc_html_e( 'Handle', 'syndicate-pro' ); ?></label></th>
					<td><input type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[bluesky_handle]" value="<?php echo esc_attr( $s['bluesky_handle'] ); ?>" placeholder="365community.bsky.social"></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'App password', 'syndicate-pro' ); ?></label></th>
					<td><?php $secret_field( 'bluesky_app_password', (bool) $s['bluesky_app_password'] ); ?>
					<p class="description"><?php esc_html_e( 'Bluesky → Settings → Privacy and security → App passwords.', 'syndicate-pro' ); ?></p></td>
				</tr>
			</table>

			<h2>X / Twitter</h2>
			<table class="form-table" role="presentation">
				<tr><th><label><?php esc_html_e( 'API key', 'syndicate-pro' ); ?></label></th><td><?php $secret_field( 'twitter_api_key', (bool) $s['twitter_api_key'] ); ?></td></tr>
				<tr><th><label><?php esc_html_e( 'API secret', 'syndicate-pro' ); ?></label></th><td><?php $secret_field( 'twitter_api_secret', (bool) $s['twitter_api_secret'] ); ?></td></tr>
				<tr><th><label><?php esc_html_e( 'Access token', 'syndicate-pro' ); ?></label></th><td><?php $secret_field( 'twitter_access_token', (bool) $s['twitter_access_token'] ); ?></td></tr>
				<tr><th><label><?php esc_html_e( 'Access token secret', 'syndicate-pro' ); ?></label></th><td><?php $secret_field( 'twitter_access_secret', (bool) $s['twitter_access_secret'] ); ?>
				<p class="description"><?php esc_html_e( 'From an app at developer.x.com with Read and Write permissions. Note: the free API tier has low monthly posting caps.', 'syndicate-pro' ); ?></p></td></tr>
			</table>

			<h2>LinkedIn</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label><?php esc_html_e( 'Organisation URN', 'syndicate-pro' ); ?></label></th>
					<td><input type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[linkedin_org_urn]" value="<?php echo esc_attr( $s['linkedin_org_urn'] ); ?>" placeholder="urn:li:organization:12345678"></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Access token', 'syndicate-pro' ); ?></label></th>
					<td><?php $secret_field( 'linkedin_token', (bool) $s['linkedin_token'] ); ?>
					<p class="description"><?php esc_html_e( 'OAuth token with the w_organization_social scope for your company page; LinkedIn tokens expire and need periodic renewal.', 'syndicate-pro' ); ?></p></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'What gets shared where', 'syndicate-pro' ); ?></h2>
			<table class="widefat striped" style="max-width:640px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Content type', 'syndicate-pro' ); ?></th>
						<?php foreach ( $networks as $network_label ) : ?>
							<th><?php echo esc_html( $network_label ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $post_types as $post_type => $type_label ) : ?>
						<tr>
							<td><?php echo esc_html( $type_label ); ?></td>
							<?php foreach ( array_keys( $networks ) as $network ) : ?>
								<?php
								$key     = $network . ':' . $post_type;
								$checked = ! isset( $s['enabled'][ $key ] ) || (int) $s['enabled'][ $key ];
								?>
								<td><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[enabled][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $checked ); ?>></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<?php
				printf(
					/* translators: %s: Templates tab URL. */
					wp_kses_post( __( 'Message wording is edited per content type on the <a href="%s">Templates</a> tab.', 'syndicate-pro' ) ),
					esc_url( admin_url( 'admin.php?page=synpro-syndication&tab=templates' ) )
				);
				?>
			</p>

			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Test connections', 'syndicate-pro' ); ?></h2>
		<p>
			<?php foreach ( $networks as $network => $network_label ) : ?>
				<?php if ( Synpro_Social::is_connected( $network ) ) : ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=synpro_social_test&network=' . $network ), 'synpro_social_test_' . $network ) ); ?>">
						<?php
						printf(
							/* translators: %s: network name. */
							esc_html__( 'Send test post to %s', 'syndicate-pro' ),
							esc_html( $network_label )
						);
						?>
					</a>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( ! array_filter( array_map( array( 'Synpro_Social', 'is_connected' ), array_keys( $networks ) ) ) ) : ?>
				<em><?php esc_html_e( 'Save credentials above to enable test buttons.', 'syndicate-pro' ); ?></em>
			<?php endif; ?>
		</p>
		<?php
	}
}

endif;
