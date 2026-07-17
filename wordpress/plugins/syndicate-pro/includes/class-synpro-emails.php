<?php
/**
 * Member emails: the welcome email (sent when a member's first piece of
 * content goes live) and the weekly digest (top blogs, video, podcast, and newest event).
 *
 * Templates are editable under Syndication → Emails. Members can opt out
 * of the digest on their profile.
 *
 * @package Synpro_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Synpro_Emails' ) ) :

class Synpro_Emails {

	const OPTION    = 'synpro_email_settings';
	const CRON_HOOK = 'synpro_weekly_digest';

	/**
	 * Hook everything up.
	 */
	const SMTP_OPTION = 'synpro_smtp_settings';

	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'maybe_send_welcome' ), 20, 3 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_digest' ) );

		// Route all wp_mail through SMTP when configured (better deliverability
		// than PHP mail() for the digest's volume).
		add_action( 'phpmailer_init', array( __CLASS__, 'configure_smtp' ) );
		$smtp = self::smtp_settings();
		if ( ! empty( $smtp['enabled'] ) && $smtp['from_email'] ) {
			add_filter( 'wp_mail_from', array( __CLASS__, 'smtp_from_email' ), 20 );
			add_filter( 'wp_mail_from_name', array( __CLASS__, 'smtp_from_name' ), 20 );
		}
	}

	/**
	 * SMTP settings, with defaults.
	 *
	 * @return array
	 */
	public static function smtp_settings() {
		return wp_parse_args(
			(array) get_option( self::SMTP_OPTION, array() ),
			array(
				'enabled'    => 0,
				'host'       => '',
				'port'       => 587,
				'encryption' => 'tls', // '', 'ssl', 'tls'
				'auth'       => 1,
				'username'   => '',
				'password'   => '',
				'from_email' => '',
				'from_name'  => '',
			)
		);
	}

	/**
	 * Sanitise SMTP settings. A blank password keeps the stored one.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_smtp( $input ) {
		$input  = (array) $input;
		$stored = self::smtp_settings();
		$pass   = isset( $input['password'] ) ? (string) $input['password'] : '';
		if ( '' === $pass ) {
			$pass = $stored['password'];
		}
		$enc = isset( $input['encryption'] ) && in_array( $input['encryption'], array( '', 'ssl', 'tls' ), true ) ? $input['encryption'] : 'tls';
		return array(
			'enabled'    => empty( $input['enabled'] ) ? 0 : 1,
			'host'       => isset( $input['host'] ) ? sanitize_text_field( $input['host'] ) : '',
			'port'       => min( 65535, max( 1, absint( $input['port'] ?? 587 ) ) ),
			'encryption' => $enc,
			'auth'       => empty( $input['auth'] ) ? 0 : 1,
			'username'   => isset( $input['username'] ) ? sanitize_text_field( $input['username'] ) : '',
			'password'   => $pass,
			'from_email' => isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : '',
			'from_name'  => isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : '',
		);
	}

	/**
	 * Configure PHPMailer to use SMTP when enabled.
	 *
	 * @param object $phpmailer PHPMailer instance (by reference).
	 */
	public static function configure_smtp( $phpmailer ) {
		$s = self::smtp_settings();
		if ( empty( $s['enabled'] ) || empty( $s['host'] ) ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = $s['host'];
		$phpmailer->Port       = (int) $s['port'];
		$phpmailer->SMTPAuth   = ! empty( $s['auth'] );
		$phpmailer->SMTPSecure = $s['encryption']; // '', 'ssl', or 'tls'
		if ( ! empty( $s['auth'] ) ) {
			$phpmailer->Username = $s['username'];
			$phpmailer->Password = $s['password'];
		}
		if ( $s['from_email'] ) {
			$phpmailer->setFrom( $s['from_email'], $s['from_name'] ? $s['from_name'] : '', false );
		}
	}

	/**
	 * SMTP "from" address filter.
	 *
	 * @param string $email Default from email.
	 * @return string
	 */
	public static function smtp_from_email( $email ) {
		$s = self::smtp_settings();
		return $s['from_email'] ? $s['from_email'] : $email;
	}

	/**
	 * SMTP "from" name filter.
	 *
	 * @param string $name Default from name.
	 * @return string
	 */
	public static function smtp_from_name( $name ) {
		$s = self::smtp_settings();
		return $s['from_name'] ? $s['from_name'] : $name;
	}

	/**
	 * Default settings and templates.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'welcome_enabled' => 1,
			'welcome_subject' => __( 'Welcome to {site_name} — your content is live!', 'syndicate-pro' ),
			'welcome_body'    => __(
				"Hi {name},\n\nGreat news — your first piece of content is now live on {site_name}:\n\n{title}\n{link}\n\nFrom here, everything is automatic: we check your feeds every few minutes and republish new posts under your name, always linking back to the original and noting it is shared with your permission. New posts are also announced on our community social accounts.\n\nTake a minute to polish your author page — photo, bio, and links — here:\n{profile_url}\n\nThanks for being part of the community!\n{site_name}",
				'syndicate-pro'
			),
			'digest_enabled'  => 1,
			'digest_subject'  => __( 'This week on {site_name}: the top posts', 'syndicate-pro' ),
			'digest_intro'    => __( "Here are the most-read posts from the community this week:", 'syndicate-pro' ),
			'digest_html'     => '',
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
	 * Sanitise submitted settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = (array) $input;
		$defaults = self::defaults();
		return array(
			'welcome_enabled' => empty( $input['welcome_enabled'] ) ? 0 : 1,
			'welcome_subject' => isset( $input['welcome_subject'] ) && '' !== trim( $input['welcome_subject'] ) ? sanitize_text_field( $input['welcome_subject'] ) : $defaults['welcome_subject'],
			'welcome_body'    => isset( $input['welcome_body'] ) && '' !== trim( $input['welcome_body'] ) ? sanitize_textarea_field( $input['welcome_body'] ) : $defaults['welcome_body'],
			'digest_enabled'  => empty( $input['digest_enabled'] ) ? 0 : 1,
			'digest_subject'  => isset( $input['digest_subject'] ) && '' !== trim( $input['digest_subject'] ) ? sanitize_text_field( $input['digest_subject'] ) : $defaults['digest_subject'],
			'digest_intro'    => isset( $input['digest_intro'] ) ? sanitize_textarea_field( $input['digest_intro'] ) : $defaults['digest_intro'],
			'digest_html'     => isset( $input['digest_html'] ) ? wp_kses_post( $input['digest_html'] ) : '',
		);
	}

	/* -----------------------------------------------------------------------
	 * Welcome email (first published post)
	 * -------------------------------------------------------------------- */

	/**
	 * When a member's first piece of content goes live, send them the
	 * welcome email — once, ever.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function maybe_send_welcome( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( ! in_array( $post->post_type, array( 'post', 'synpro_event', 'synpro_podcast', 'synpro_video' ), true ) ) {
			return;
		}
		if ( ! self::get( 'welcome_enabled' ) ) {
			return;
		}

		$author_id = (int) $post->post_author;
		if ( ! $author_id || get_user_meta( $author_id, 'synpro_welcomed', true ) ) {
			return;
		}

		$user = get_user_by( 'id', $author_id );
		if ( ! $user || ! $user->user_email ) {
			return;
		}

		// Mark first so a mail failure can never cause repeat sends.
		update_user_meta( $author_id, 'synpro_welcomed', time() );

		$replacements = array(
			'{name}'        => $user->display_name,
			'{title}'       => wp_strip_all_tags( get_the_title( $post ) ),
			'{link}'        => get_permalink( $post ),
			'{profile_url}' => admin_url( 'profile.php' ),
			'{site_name}'   => get_bloginfo( 'name' ),
		);

		wp_mail(
			$user->user_email,
			strtr( self::get( 'welcome_subject' ), $replacements ),
			strtr( self::get( 'welcome_body' ), $replacements )
		);
	}

	/* -----------------------------------------------------------------------
	 * Weekly digest (top 4 blogs + top video + top podcast + newest event)
	 * -------------------------------------------------------------------- */

	/**
	 * Pick top not-previously-sent items of one post type, ranked by views
	 * (date as tiebreak), topping up with newest unsent when views are thin.
	 *
	 * @param string $post_type Post type.
	 * @param int    $limit     How many.
	 * @param int[]  $exclude   Post IDs never to repeat.
	 * @return WP_Post[]
	 */
	public static function pick_unsent( $post_type, $limit, $exclude ) {
		$base = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'post__not_in'   => $exclude,
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
		);

		$by_views = get_posts(
			$base + array(
				'meta_key' => '_synpro_views', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'  => 'meta_value_num',
				'order'    => 'DESC',
			)
		);
		if ( count( $by_views ) >= $limit ) {
			return $by_views;
		}

		$have = wp_list_pluck( $by_views, 'ID' );
		// array_merge, not the + union: + would keep $base's original
		// post__not_in and posts_per_page and re-select the same posts.
		$fill = get_posts(
			array_merge(
				$base,
				array(
					'post__not_in'   => array_merge( $exclude, $have ),
					'posts_per_page' => $limit - count( $by_views ),
				)
			)
		);
		return array_merge( $by_views, $fill );
	}

	/**
	 * One item as a digest HTML row.
	 *
	 * @param WP_Post $post  Post.
	 * @param string  $label Section label.
	 * @return string
	 */
	protected static function item_html( $post, $label ) {
		$image = get_the_post_thumbnail_url( $post, 'medium' );
		$row   = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px"><tr>';
		if ( $image ) {
			$row .= '<td width="110" valign="top" style="padding-right:12px"><a href="' . esc_url( get_permalink( $post ) ) . '"><img src="' . esc_url( $image ) . '" width="110" alt="" style="border-radius:8px;display:block"></a></td>';
		}
		$row .= '<td valign="top">'
			. '<div style="font-size:11px;font-weight:bold;letter-spacing:.05em;text-transform:uppercase;color:#f97316">' . esc_html( $label ) . '</div>'
			. '<a href="' . esc_url( get_permalink( $post ) ) . '" style="font-size:16px;font-weight:bold;color:#111;text-decoration:none">' . esc_html( get_the_title( $post ) ) . '</a>'
			. '<div style="font-size:12px;color:#666">' . esc_html( get_the_author_meta( 'display_name', (int) $post->post_author ) ) . '</div>'
			. '</td></tr></table>';
		return $row;
	}

	/**
	 * Build this week's digest: top 4 blogs, top video, top podcast, and the
	 * newest event — none of which has appeared in a previous digest.
	 *
	 * @return array|null { subject, html, sent_ids } or null when empty.
	 */
	public static function build_digest() {
		$sent = array_map( 'intval', (array) get_option( 'synpro_digest_sent', array() ) );
		$site = get_bloginfo( 'name' );

		$blogs   = self::pick_unsent( 'post', 4, $sent );
		$video   = self::pick_unsent( 'synpro_video', 1, $sent );
		$podcast = self::pick_unsent( 'synpro_podcast', 1, $sent );
		$event   = get_posts(
			array(
				'post_type'      => 'synpro_event',
				'post_status'    => 'publish',
				'post__not_in'   => $sent,
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);

		$chosen = array_merge( $blogs, $video, $podcast, $event );
		if ( ! $chosen ) {
			return null;
		}

		$sections = '';
		foreach ( $blogs as $post ) {
			$sections .= self::item_html( $post, __( 'Top blog', 'syndicate-pro' ) );
		}
		foreach ( $video as $post ) {
			$sections .= self::item_html( $post, __( 'Top video', 'syndicate-pro' ) );
		}
		foreach ( $podcast as $post ) {
			$sections .= self::item_html( $post, __( 'Top podcast', 'syndicate-pro' ) );
		}
		foreach ( $event as $post ) {
			$sections .= self::item_html( $post, __( 'New event', 'syndicate-pro' ) );
		}

		$intro    = strtr( self::get( 'digest_intro' ), array( '{site_name}' => $site ) );
		$template = self::get( 'digest_html' );
		if ( ! $template ) {
			$template = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;padding:20px">'
				. '<h1 style="color:#f97316;font-size:20px">{site_name}</h1>'
				. '<p style="color:#333">{intro}</p>{items}'
				. '<p style="font-size:12px;color:#999">{unsubscribe}</p></div>';
		}

		$html = strtr(
			$template,
			array(
				'{site_name}' => esc_html( $site ),
				'{intro}'     => esc_html( $intro ),
				'{items}'     => $sections,
				'{link}'      => esc_url( home_url( '/' ) ),
			)
		);

		return array(
			'subject'  => strtr( self::get( 'digest_subject' ), array( '{site_name}' => $site ) ),
			'html'     => $html,
			'sent_ids' => wp_list_pluck( $chosen, 'ID' ),
		);
	}

	/**
	 * Send the weekly digest to members (unless opted out) and website
	 * subscribers, then record the featured post IDs so no item is ever
	 * sent twice.
	 */
	public static function send_digest() {
		if ( ! self::get( 'digest_enabled' ) ) {
			return;
		}

		$digest = self::build_digest();
		if ( ! $digest ) {
			return; // Nothing new — no email this week.
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		// WP users who have not opted out (unsubscribe = profile checkbox).
		foreach ( get_users( array( 'fields' => array( 'ID', 'user_email' ) ) ) as $user ) {
			if ( '0' === (string) get_user_meta( $user->ID, 'synpro_digest', true ) ) {
				continue;
			}
			$html = str_replace(
				'{unsubscribe}',
				esc_html__( 'To stop these emails, untick the weekly digest box on your profile.', 'syndicate-pro' ),
				$digest['html']
			);
			wp_mail( $user->user_email, $digest['subject'], $html, $headers );
		}

		// Website subscribers (tokenised unsubscribe link).
		if ( class_exists( 'Synpro_Subscribers' ) ) {
			foreach ( Synpro_Subscribers::all() as $subscriber ) {
				$unsub = '<a href="' . esc_url( Synpro_Subscribers::unsubscribe_url( $subscriber ) ) . '">' . esc_html__( 'Unsubscribe', 'syndicate-pro' ) . '</a>';
				$html  = str_replace( '{unsubscribe}', $unsub, $digest['html'] );
				wp_mail( $subscriber->email, $digest['subject'], $html, $headers );
			}
		}

		$sent = array_map( 'intval', (array) get_option( 'synpro_digest_sent', array() ) );
		$sent = array_slice( array_merge( $sent, array_map( 'intval', $digest['sent_ids'] ) ), -5000 );
		update_option( 'synpro_digest_sent', $sent, false );
	}
}

endif;
