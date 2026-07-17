<?php
/**
 * Paid (sponsored) content.
 *
 * A post can be marked as paid-for on its edit screen, at one of two
 * tiers: standard (£25 default) or featured (£100 default). Featured
 * posts are placed in the site's designated featured category and drop
 * out of it automatically after 7 days (configurable), returning to the
 * normal content cycle.
 *
 * Disclosure is non-negotiable and enforced by the plugin, not the
 * theme: every paid post carries a "Paid content" badge over its
 * featured image wherever the image renders, and a disclosure banner at
 * the top of the post when opened.
 *
 * Payments themselves happen outside WordPress (invoicing); the plugin
 * records the tier and shows the price for reference.
 *
 * @package Syndicate_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Synpro_Paid' ) ) :

class Synpro_Paid {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_post', array( __CLASS__, 'save' ), 10, 2 );

		// Disclosure: banner on the post, badge on the image — everywhere.
		add_filter( 'the_content', array( __CLASS__, 'prepend_banner' ), 5 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'badge_thumbnail' ), 10, 2 );
		add_action( 'wp_head', array( __CLASS__, 'fallback_styles' ) );

		// Featured expiry rides the existing daily cron.
		add_action( 'synpro_daily_prune', array( __CLASS__, 'expire_featured' ) );

		// Posts-list column so paid content is visible at a glance.
		add_filter( 'manage_post_posts_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_post_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
	}

	/**
	 * Whether a post is paid content.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_paid( $post_id ) {
		return (bool) get_post_meta( (int) $post_id, '_synpro_paid', true );
	}

	/**
	 * A post's paid tier.
	 *
	 * @param int $post_id Post ID.
	 * @return string 'standard' or 'featured' ('' when not paid).
	 */
	public static function tier( $post_id ) {
		if ( ! self::is_paid( $post_id ) ) {
			return '';
		}
		$tier = get_post_meta( (int) $post_id, '_synpro_paid_tier', true );
		return 'featured' === $tier ? 'featured' : 'standard';
	}

	/**
	 * The PayPal payment link for a post's tier: a Website Payments
	 * Standard "Buy Now" URL for the right amount in GBP, tagged with the
	 * post ID (item_number) so it's traceable in PayPal activity.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $tier    'standard' or 'featured'.
	 * @return string Empty when no PayPal email is configured.
	 */
	public static function payment_url( $post_id, $tier = '' ) {
		$business = Synpro_Settings::get( 'paypal_email' );
		if ( ! $business || ! is_email( $business ) ) {
			return '';
		}
		$tier   = $tier ? $tier : self::tier( $post_id );
		$amount = 'featured' === $tier ? (int) Synpro_Settings::get( 'featured_price' ) : (int) Synpro_Settings::get( 'paid_price' );
		$label  = 'featured' === $tier ? __( 'Featured post', 'syndicate-pro' ) : __( 'Paid post', 'syndicate-pro' );

		return add_query_arg(
			array(
				'cmd'           => '_xclick',
				'business'      => rawurlencode( $business ),
				'item_name'     => rawurlencode( sprintf( '%s — %s — #%d', $label, get_bloginfo( 'name' ), (int) $post_id ) ),
				'item_number'   => (int) $post_id,
				'amount'        => number_format( $amount, 2, '.', '' ),
				'currency_code' => 'GBP',
				'no_shipping'   => 1,
				'no_note'       => 1,
			),
			'https://www.paypal.com/cgi-bin/webscr'
		);
	}

	/**
	 * Whether payment has been marked received for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function payment_received( $post_id ) {
		return (bool) get_post_meta( (int) $post_id, '_synpro_paid_received', true );
	}

	/**
	 * The paid-content meta box on the post editor.
	 */
	public static function add_meta_box() {
		add_meta_box(
			'synpro-paid',
			__( 'Syndicate Pro — Paid content', 'syndicate-pro' ),
			array( __CLASS__, 'render_meta_box' ),
			'post',
			'side',
			'high'
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'synpro_paid', 'synpro_paid_nonce' );
		$paid  = self::is_paid( $post->ID );
		$tier  = self::tier( $post->ID );
		$until = (int) get_post_meta( $post->ID, '_synpro_featured_until', true );
		?>
		<p>
			<label>
				<input type="checkbox" name="synpro_paid" value="1" <?php checked( $paid ); ?>>
				<strong><?php esc_html_e( 'This is paid-for content', 'syndicate-pro' ); ?></strong>
			</label>
		</p>
		<p>
			<label>
				<input type="radio" name="synpro_paid_tier" value="standard" <?php checked( 'featured' !== $tier ); ?>>
				<?php
				printf(
					/* translators: %d: price. */
					esc_html__( 'Standard — £%d', 'syndicate-pro' ),
					(int) Synpro_Settings::get( 'paid_price' )
				);
				?>
			</label><br>
			<label>
				<input type="radio" name="synpro_paid_tier" value="featured" <?php checked( 'featured', $tier ); ?>>
				<?php
				printf(
					/* translators: 1: price, 2: number of days. */
					esc_html__( 'Featured — £%1$d (featured for %2$d days, then back to the normal cycle)', 'syndicate-pro' ),
					(int) Synpro_Settings::get( 'featured_price' ),
					(int) Synpro_Settings::get( 'featured_days' )
				);
				?>
			</label>
		</p>
		<?php if ( $until && $until > time() ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: date. */
					esc_html__( 'Featured until %s.', 'syndicate-pro' ),
					esc_html( date_i18n( get_option( 'date_format', 'j F Y' ), $until ) )
				);
				?>
			</p>
		<?php endif; ?>
		<?php if ( ! (int) Synpro_Settings::get( 'featured_category' ) ) : ?>
			<p class="description"><?php esc_html_e( 'Tip: set the featured category under Syndicate Pro → Settings so featured posts get their placement.', 'syndicate-pro' ); ?></p>
		<?php endif; ?>

		<?php if ( $paid ) : ?>
			<hr>
			<p>
				<label>
					<input type="checkbox" name="synpro_paid_received" value="1" <?php checked( self::payment_received( $post->ID ) ); ?>>
					<strong><?php esc_html_e( 'Payment received', 'syndicate-pro' ); ?></strong>
					<?php
					$received_on = (int) get_post_meta( $post->ID, '_synpro_paid_received', true );
					if ( $received_on > 1 ) {
						echo ' <span class="description">(' . esc_html( date_i18n( get_option( 'date_format', 'j M Y' ), $received_on ) ) . ')</span>';
					}
					?>
				</label>
			</p>
			<?php $pay_url = self::payment_url( $post->ID ); ?>
			<?php if ( $pay_url ) : ?>
				<p>
					<a href="<?php echo esc_url( $pay_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open PayPal payment link', 'syndicate-pro' ); ?></a>
				</p>
				<p>
					<input type="text" class="widefat" readonly value="<?php echo esc_attr( $pay_url ); ?>" onclick="this.select()">
					<span class="description"><?php esc_html_e( 'Send this link to the sponsor — it charges the right amount for the selected tier and tags the payment with this post’s ID.', 'syndicate-pro' ); ?></span>
				</p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Add your PayPal email under Syndicate Pro → Settings → Paid content to get a ready-made payment link here.', 'syndicate-pro' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Paid posts always show a "Paid content" badge on their image and a disclosure banner when opened.', 'syndicate-pro' ); ?></p>
		<?php
	}

	/**
	 * Save the meta box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['synpro_paid_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['synpro_paid_nonce'] ), 'synpro_paid' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		if ( empty( $_POST['synpro_paid'] ) ) {
			// Unmarking removes the flags but leaves the category placement to
			// the editor — removing content someone paid for is their call.
			delete_post_meta( $post_id, '_synpro_paid' );
			delete_post_meta( $post_id, '_synpro_paid_tier' );
			delete_post_meta( $post_id, '_synpro_featured_until' );
			return;
		}

		$tier = ( isset( $_POST['synpro_paid_tier'] ) && 'featured' === $_POST['synpro_paid_tier'] ) ? 'featured' : 'standard';
		update_post_meta( $post_id, '_synpro_paid', 1 );
		update_post_meta( $post_id, '_synpro_paid_tier', $tier );

		// Payment tracking: store the timestamp the box was first ticked.
		if ( empty( $_POST['synpro_paid_received'] ) ) {
			delete_post_meta( $post_id, '_synpro_paid_received' );
		} elseif ( ! self::payment_received( $post_id ) ) {
			update_post_meta( $post_id, '_synpro_paid_received', time() );
		}

		if ( 'featured' === $tier ) {
			// Stamp the expiry once (re-saving must not extend the window).
			if ( ! (int) get_post_meta( $post_id, '_synpro_featured_until', true ) ) {
				update_post_meta( $post_id, '_synpro_featured_until', time() + (int) Synpro_Settings::get( 'featured_days' ) * DAY_IN_SECONDS );
			}
			$featured_cat = (int) Synpro_Settings::get( 'featured_category' );
			if ( $featured_cat ) {
				wp_set_post_categories( $post_id, array( $featured_cat ), true );
			}
		} else {
			delete_post_meta( $post_id, '_synpro_featured_until' );
		}
	}

	/**
	 * The disclosure banner markup.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function banner_html( $post_id ) {
		$html = '<div class="synpro-paid-banner" role="note"><span class="synpro-paid-flag">' . esc_html__( 'Paid content', 'syndicate-pro' ) . '</span> '
			. esc_html__( 'This post is paid-for content: the author or their organisation paid for its placement on this site.', 'syndicate-pro' )
			. '</div>';

		/**
		 * Filter the paid-content disclosure banner. Whatever this returns is
		 * still shown — returning an empty string restores the default, so the
		 * disclosure cannot be filtered away entirely.
		 *
		 * @param string $html    Banner HTML.
		 * @param int    $post_id Post ID.
		 */
		$filtered = apply_filters( 'synpro_paid_banner_html', $html, $post_id );
		return is_string( $filtered ) && '' !== trim( wp_strip_all_tags( $filtered ) ) ? $filtered : $html;
	}

	/**
	 * Prepend the disclosure banner to a paid post's content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function prepend_banner( $content ) {
		$post_id = get_the_ID();
		if ( ! $post_id || ! self::is_paid( $post_id ) ) {
			return $content;
		}
		return self::banner_html( $post_id ) . $content;
	}

	/**
	 * Overlay the "Paid content" badge on a paid post's featured image,
	 * wherever the thumbnail is rendered (cards, sliders, singles).
	 *
	 * @param string $html    Thumbnail HTML.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public static function badge_thumbnail( $html, $post_id ) {
		if ( '' === $html || ! self::is_paid( $post_id ) ) {
			return $html;
		}
		return '<span class="synpro-paid-wrap">' . $html
			. '<span class="synpro-paid-badge">' . esc_html__( 'Paid content', 'syndicate-pro' ) . '</span></span>';
	}

	/**
	 * Minimal disclosure styling for themes without their own. The
	 * Community 365 theme declares `synpro-paid` support and styles it
	 * properly; this fallback keeps the disclosure visible on any theme.
	 */
	public static function fallback_styles() {
		if ( current_theme_supports( 'synpro-paid' ) ) {
			return;
		}
		echo '<style id="synpro-paid-css">'
			. '.synpro-paid-wrap{position:relative;display:block}'
			. '.synpro-paid-badge{position:absolute;top:10px;left:10px;background:#111;color:#fff;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:3px 9px;border-radius:999px;z-index:2}'
			. '.synpro-paid-banner{border:1px solid #b45309;background:#fef3c7;color:#78350f;padding:10px 14px;border-radius:8px;margin:0 0 16px;font-size:14px}'
			. '.synpro-paid-banner .synpro-paid-flag{font-weight:800;text-transform:uppercase;letter-spacing:.04em;margin-right:6px}'
			. '</style>';
	}

	/**
	 * Daily: featured posts whose window has passed drop out of the
	 * featured category and back into the normal cycle. The paid flag and
	 * disclosure remain — only the placement expires.
	 */
	public static function expire_featured() {
		$expired = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => '_synpro_featured_until',
						'value'   => time(),
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		$featured_cat = (int) Synpro_Settings::get( 'featured_category' );

		foreach ( $expired as $post_id ) {
			if ( $featured_cat ) {
				$cats = array_diff( wp_get_post_categories( $post_id ), array( $featured_cat ) );
				// A post must keep at least one category; fall back to default.
				wp_set_post_categories( $post_id, $cats ? array_values( $cats ) : array() );
			}
			delete_post_meta( $post_id, '_synpro_featured_until' );

			/**
			 * Fires when a featured paid post's window ends.
			 *
			 * @param int $post_id Post ID.
			 */
			do_action( 'synpro_featured_expired', $post_id );
		}
	}

	/**
	 * "Paid" column on the posts list.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function add_column( $columns ) {
		$columns['synpro_paid'] = __( 'Paid', 'syndicate-pro' );
		return $columns;
	}

	/**
	 * Render the "Paid" column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function render_column( $column, $post_id ) {
		if ( 'synpro_paid' !== $column || ! self::is_paid( $post_id ) ) {
			return;
		}
		if ( 'featured' === self::tier( $post_id ) ) {
			$until = (int) get_post_meta( $post_id, '_synpro_featured_until', true );
			if ( $until && $until > time() ) {
				printf(
					/* translators: %s: date. */
					esc_html__( 'Featured until %s', 'syndicate-pro' ),
					esc_html( date_i18n( get_option( 'date_format', 'j M Y' ), $until ) )
				);
			} else {
				esc_html_e( 'Featured (ended)', 'syndicate-pro' );
			}
		} else {
			esc_html_e( 'Paid', 'syndicate-pro' );
		}
		if ( ! self::payment_received( $post_id ) ) {
			echo '<br><span style="color:#b45309;font-weight:600">' . esc_html__( 'awaiting payment', 'syndicate-pro' ) . '</span>';
		}
	}
}

endif;
