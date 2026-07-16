<?php
/**
 * Site-wide settings, the Syndication admin menu, and the status dashboard.
 *
 * @package C365_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class C365_Settings {

	const OPTION = 'c365_syndicator_settings';

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
			'interval'           => 'c365_5min',  // Rotate to the next member every 5 minutes.
			'post_status'        => 'publish',    // Status for imported content.
			'max_items'          => 10,           // Per feed per fetch.
			'set_featured_image' => 1,            // Sideload the item image as featured image.
			'import_categories'  => 0,            // Also map feed item categories to WP categories.
			'use_original_date'  => 1,            // Keep the original publish date.
			'attribution'        => 1,            // Append source link + permission note to content.
			'canonical'          => 1,            // Point rel=canonical at the original article.
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
		$schedules['c365_5min'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes', 'c365-syndicator' ),
		);
		$schedules['c365_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes', 'c365-syndicator' ),
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
		$old_interval = isset( $old['interval'] ) ? $old['interval'] : 'c365_5min';
		$new_interval = isset( $new['interval'] ) ? $new['interval'] : 'c365_5min';
		if ( $old_interval !== $new_interval || ! wp_next_scheduled( C365_SYN_CRON_HOOK ) ) {
			wp_clear_scheduled_hook( C365_SYN_CRON_HOOK );
			wp_schedule_event( time() + 60, $new_interval, C365_SYN_CRON_HOOK );
		}
	}

	/**
	 * Add the top-level Syndication menu.
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'Syndication', 'c365-syndicator' ),
			__( 'Syndication', 'c365-syndicator' ),
			'manage_options',
			'c365-syndication',
			array( __CLASS__, 'render_page' ),
			'dashicons-rss',
			58
		);
	}

	/**
	 * Register the setting with sanitisation.
	 */
	public static function register() {
		register_setting(
			'c365_syndicator',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	/**
	 * Sanitise submitted settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input     = (array) $input;
		$defaults  = self::defaults();
		$intervals = array( 'c365_5min', 'c365_15min', 'hourly', 'twicedaily', 'daily' );
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
		);
	}

	/**
	 * Render the Syndication dashboard: rotation status, member table, settings.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s       = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		$next    = wp_next_scheduled( C365_SYN_CRON_HOOK );
		$members = C365_Fetcher::get_members();
		$pointer = (int) get_option( 'c365_rotation_pointer', 0 );
		$next_up = C365_Fetcher::next_member_id( $members, $pointer );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Syndication', 'c365-syndicator' ); ?></h1>

			<?php if ( isset( $_GET['c365_fetched'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %d: number of imported items. */
							esc_html__( 'Fetch complete — %d new item(s) imported.', 'c365-syndicator' ),
							absint( $_GET['c365_fetched'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Rotation', 'c365-syndicator' ); ?></h2>
			<p>
				<?php
				if ( $next ) {
					printf(
						/* translators: %s: human-readable time difference. */
						esc_html__( 'Next rotation tick: in %s.', 'c365-syndicator' ),
						esc_html( human_time_diff( time(), $next ) )
					);
				} else {
					esc_html_e( 'No rotation is scheduled — save the settings below to schedule one.', 'c365-syndicator' );
				}
				echo ' ';
				printf(
					/* translators: %d: number of members with feeds. */
					esc_html__( '%d member(s) in the rotation.', 'c365-syndicator' ),
					count( $members )
				);
				?>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=c365_fetch_all' ), 'c365_fetch_all' ) ); ?>">
					<?php esc_html_e( 'Fetch all members now', 'c365-syndicator' ); ?>
				</a>
			</p>
			<p class="description">
				<?php esc_html_e( 'WP-Cron only runs when the site gets visits. For a reliable 5-minute rotation, add a real cron job that requests wp-cron.php every 5 minutes (see the plugin readme).', 'c365-syndicator' ); ?>
			</p>

			<h2><?php esc_html_e( 'Members in rotation', 'c365-syndicator' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Member', 'c365-syndicator' ); ?></th>
						<th><?php esc_html_e( 'Feeds', 'c365-syndicator' ); ?></th>
						<th><?php esc_html_e( 'Last checked', 'c365-syndicator' ); ?></th>
						<th><?php esc_html_e( 'Last result', 'c365-syndicator' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $members ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No members have feeds configured yet. Feeds are set on each user’s profile screen.', 'c365-syndicator' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $members as $member ) : ?>
							<?php
							$feeds = array();
							if ( get_user_meta( $member->ID, 'c365_blog_feed', true ) ) {
								$feeds[] = __( 'Blog', 'c365-syndicator' );
							}
							if ( get_user_meta( $member->ID, 'c365_podcast_feed', true ) ) {
								$feeds[] = __( 'Podcast', 'c365-syndicator' );
							}
							if ( get_user_meta( $member->ID, 'c365_youtube_channel', true ) ) {
								$feeds[] = __( 'YouTube', 'c365-syndicator' );
							}
							if ( get_user_meta( $member->ID, 'c365_events_feed', true ) ) {
								$feeds[] = __( 'Events', 'c365-syndicator' );
							}
							$last_time   = (int) get_user_meta( $member->ID, 'c365_last_fetch', true );
							$last_result = get_user_meta( $member->ID, 'c365_last_result', true );
							$fetch_url   = wp_nonce_url(
								admin_url( 'admin-post.php?action=c365_fetch_user&user_id=' . $member->ID ),
								'c365_fetch_user_' . $member->ID
							);
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( get_edit_user_link( $member->ID ) ); ?>"><strong><?php echo esc_html( $member->display_name ); ?></strong></a>
									<?php if ( $member->ID === $next_up ) : ?>
										<span class="dashicons dashicons-controls-play" title="<?php esc_attr_e( 'Next in rotation', 'c365-syndicator' ); ?>"></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( implode( ', ', $feeds ) ); ?></td>
								<td><?php echo $last_time ? esc_html( human_time_diff( $last_time, time() ) . ' ' . __( 'ago', 'c365-syndicator' ) ) : '—'; ?></td>
								<td><?php echo $last_result ? esc_html( $last_result ) : '—'; ?></td>
								<td><a class="button button-small" href="<?php echo esc_url( $fetch_url ); ?>"><?php esc_html_e( 'Fetch now', 'c365-syndicator' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Settings', 'c365-syndicator' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'c365_syndicator' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="c365-interval"><?php esc_html_e( 'Rotation interval', 'c365-syndicator' ); ?></label></th>
						<td>
							<select id="c365-interval" name="<?php echo esc_attr( self::OPTION ); ?>[interval]">
								<?php
								$options = array(
									'c365_5min'  => __( 'Every 5 minutes (one member per tick)', 'c365-syndicator' ),
									'c365_15min' => __( 'Every 15 minutes', 'c365-syndicator' ),
									'hourly'     => __( 'Hourly', 'c365-syndicator' ),
									'twicedaily' => __( 'Twice daily', 'c365-syndicator' ),
									'daily'      => __( 'Daily', 'c365-syndicator' ),
								);
								foreach ( $options as $value => $label ) {
									printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $s['interval'], $value, false ), esc_html( $label ) );
								}
								?>
							</select>
							<p class="description"><?php esc_html_e( 'Each tick checks the next member in the rotation, then moves the pointer on.', 'c365-syndicator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="c365-status"><?php esc_html_e( 'Imported content status', 'c365-syndicator' ); ?></label></th>
						<td>
							<select id="c365-status" name="<?php echo esc_attr( self::OPTION ); ?>[post_status]">
								<?php
								$statuses = array(
									'publish' => __( 'Published', 'c365-syndicator' ),
									'draft'   => __( 'Draft', 'c365-syndicator' ),
									'pending' => __( 'Pending review', 'c365-syndicator' ),
									'private' => __( 'Private', 'c365-syndicator' ),
								);
								foreach ( $statuses as $value => $label ) {
									printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $s['post_status'], $value, false ), esc_html( $label ) );
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="c365-max"><?php esc_html_e( 'Max items per feed per fetch', 'c365-syndicator' ); ?></label></th>
						<td><input id="c365-max" type="number" min="1" max="50" name="<?php echo esc_attr( self::OPTION ); ?>[max_items]" value="<?php echo esc_attr( $s['max_items'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'c365-syndicator' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[set_featured_image]" value="1" <?php checked( $s['set_featured_image'] ); ?>>
									<?php esc_html_e( 'Import the item image as the featured image', 'c365-syndicator' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[import_categories]" value="1" <?php checked( $s['import_categories'] ); ?>>
									<?php esc_html_e( 'Also create/assign categories from the feed item’s own categories (blog posts only)', 'c365-syndicator' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[use_original_date]" value="1" <?php checked( $s['use_original_date'] ); ?>>
									<?php esc_html_e( 'Use the original publish date on imported content', 'c365-syndicator' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[attribution]" value="1" <?php checked( $s['attribution'] ); ?>>
									<?php esc_html_e( 'Append the source link and “shared with permission” note below the content (skipped automatically when the active theme renders its own)', 'c365-syndicator' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[canonical]" value="1" <?php checked( $s['canonical'] ); ?>>
									<?php esc_html_e( 'Point rel="canonical" at the original article (recommended for SEO on syndicated content)', 'c365-syndicator' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
