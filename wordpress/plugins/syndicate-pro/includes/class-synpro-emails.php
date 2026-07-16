<?php
/**
 * Member emails: the welcome email (sent when a member's first piece of
 * content goes live) and the weekly digest (top 10 blog posts of the week).
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
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'maybe_send_welcome' ), 20, 3 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_digest' ) );
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
	 * Weekly digest (top 10 blogs of the week)
	 * -------------------------------------------------------------------- */

	/**
	 * The week's top 10 blog posts: by views this month when view data
	 * exists, newest first otherwise.
	 *
	 * @return WP_Post[]
	 */
	public static function top_posts_of_week() {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'date_query'     => array( array( 'after' => '1 week ago' ) ),
				'posts_per_page' => 50,
				'no_found_rows'  => true,
			)
		);

		usort(
			$posts,
			function ( $a, $b ) {
				$views_a = (int) get_post_meta( $a->ID, '_synpro_views', true );
				$views_b = (int) get_post_meta( $b->ID, '_synpro_views', true );
				if ( $views_a === $views_b ) {
					return strcmp( $b->post_date, $a->post_date );
				}
				return $views_b <=> $views_a;
			}
		);

		return array_slice( $posts, 0, 10 );
	}

	/**
	 * Send the weekly digest to every user who has not opted out.
	 */
	public static function send_digest() {
		if ( ! self::get( 'digest_enabled' ) ) {
			return;
		}

		$posts = self::top_posts_of_week();
		if ( ! $posts ) {
			return; // Quiet week — no email.
		}

		$site    = get_bloginfo( 'name' );
		$subject = strtr( self::get( 'digest_subject' ), array( '{site_name}' => $site ) );

		$lines   = array();
		$lines[] = strtr( self::get( 'digest_intro' ), array( '{site_name}' => $site ) );
		$lines[] = '';
		$rank    = 1;
		foreach ( $posts as $post ) {
			$views   = (int) get_post_meta( $post->ID, '_synpro_views', true );
			$author  = get_the_author_meta( 'display_name', (int) $post->post_author );
			$lines[] = sprintf(
				'%d. %s — %s%s',
				$rank++,
				wp_strip_all_tags( get_the_title( $post ) ),
				$author,
				$views ? sprintf( /* translators: %d: view count. */ __( ' (%d views)', 'syndicate-pro' ), $views ) : ''
			);
			$lines[] = get_permalink( $post );
			$lines[] = '';
		}
		$lines[] = sprintf( /* translators: %s: site URL. */ __( 'Read everything at %s', 'syndicate-pro' ), home_url( '/' ) );
		$body    = implode( "\n", $lines );

		foreach ( self::digest_recipients() as $recipient ) {
			wp_mail( $recipient->user_email, $subject, $body );
		}
	}

	/**
	 * Users receiving the digest: everyone except explicit opt-outs.
	 *
	 * @return object[] With user_email.
	 */
	public static function digest_recipients() {
		$users = get_users( array( 'fields' => array( 'ID', 'user_email', 'display_name' ) ) );
		return array_values(
			array_filter(
				$users,
				function ( $user ) {
					return '0' !== (string) get_user_meta( $user->ID, 'synpro_digest', true );
				}
			)
		);
	}
}

endif;
