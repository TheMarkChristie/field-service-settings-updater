<?php
/**
 * Social auto-sharing (spec §2.9).
 *
 * When a post/event/podcast/video is published for the first time, an
 * announcement is queued for each connected network — LinkedIn, Bluesky,
 * Mastodon, X — in the format:
 *
 *   New Post: {title} by {author}
 *   {excerpt}
 *   {link}
 *   {hashtags}
 *
 * {author} is the member's @handle on that network when it can be derived
 * from their profile links, otherwise their WordPress display name. The
 * featured image is attached where the network supports it. Templates are
 * editable per network x post type. Shares are queued and processed by
 * cron so a slow social API never blocks publishing or importing.
 *
 * @package Synpro_Syndicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Synpro_Social' ) ) :

class Synpro_Social {

	const OPTION       = 'synpro_social_settings';
	const QUEUE_OPTION = 'synpro_share_queue';
	const MAX_ATTEMPTS = 3;

	/**
	 * Post types that get shared.
	 *
	 * @var string[]
	 */
	const SHAREABLE = array( 'post', 'synpro_event', 'synpro_podcast', 'synpro_video' );

	/**
	 * When true, publishing does not queue social shares. The fetcher sets
	 * this during historic backfills so old content is never announced.
	 *
	 * @var bool
	 */
	public static $suppressed = false;

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 10, 3 );
		add_action( 'synpro_process_share_queue', array( __CLASS__, 'process_queue' ) );
		add_action( 'admin_post_synpro_social_test', array( __CLASS__, 'handle_test_post' ) );
	}

	/**
	 * Supported networks.
	 *
	 * @return array key => label.
	 */
	public static function networks() {
		return array(
			'mastodon' => 'Mastodon',
			'bluesky'  => 'Bluesky',
			'twitter'  => 'X / Twitter',
			'linkedin' => 'LinkedIn',
		);
	}

	/**
	 * Character limits per network.
	 *
	 * @param string $network Network key.
	 * @return int
	 */
	public static function char_limit( $network ) {
		$limits = array(
			'mastodon' => 500,
			'bluesky'  => 300,
			'twitter'  => 280,
			'linkedin' => 3000,
		);
		return isset( $limits[ $network ] ) ? $limits[ $network ] : 280;
	}

	/**
	 * Default first-line template per post type.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	public static function default_template( $post_type ) {
		$lines = array(
			'post'         => __( 'New Post: {title} by {author}', 'syndicate-pro' ),
			'synpro_event'   => __( 'New Event: {title} by {author}', 'syndicate-pro' ),
			'synpro_podcast' => __( 'New Episode: {title} by {author}', 'syndicate-pro' ),
			'synpro_video'   => __( 'New Video: {title} by {author}', 'syndicate-pro' ),
		);
		$first = isset( $lines[ $post_type ] ) ? $lines[ $post_type ] : $lines['post'];
		return $first . "\n{excerpt}\n{link}\n{hashtags}";
	}

	/**
	 * Settings with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'mastodon_instance'      => '',
				'mastodon_token'         => '',
				'bluesky_handle'         => '',
				'bluesky_app_password'   => '',
				'twitter_api_key'        => '',
				'twitter_api_secret'     => '',
				'twitter_access_token'   => '',
				'twitter_access_secret'  => '',
				'linkedin_token'         => '',
				'linkedin_org_urn'       => '',
				'enabled'                => array(), // "network:post_type" => 1
				'templates'              => array(), // "network:post_type" => template
			)
		);
	}

	/**
	 * Whether a network is configured with credentials.
	 *
	 * @param string $network Network key.
	 * @return bool
	 */
	public static function is_connected( $network ) {
		$s = self::settings();
		switch ( $network ) {
			case 'mastodon':
				return $s['mastodon_instance'] && $s['mastodon_token'];
			case 'bluesky':
				return $s['bluesky_handle'] && $s['bluesky_app_password'];
			case 'twitter':
				return $s['twitter_api_key'] && $s['twitter_api_secret'] && $s['twitter_access_token'] && $s['twitter_access_secret'];
			case 'linkedin':
				return $s['linkedin_token'] && $s['linkedin_org_urn'];
		}
		return false;
	}

	/**
	 * Whether sharing is enabled for network x post type (default: on when
	 * the network is connected).
	 *
	 * @param string $network   Network key.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function is_enabled( $network, $post_type ) {
		if ( ! self::is_connected( $network ) ) {
			return false;
		}
		$s   = self::settings();
		$key = $network . ':' . $post_type;
		return ! isset( $s['enabled'][ $key ] ) || (int) $s['enabled'][ $key ];
	}

	/* -----------------------------------------------------------------------
	 * Queueing
	 * -------------------------------------------------------------------- */

	/**
	 * Queue shares when a shareable post is published for the first time.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function on_publish( $new_status, $old_status, $post ) {
		if ( self::$suppressed ) {
			return; // Historic backfill in progress — never announce old content.
		}
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( ! in_array( $post->post_type, self::SHAREABLE, true ) ) {
			return;
		}

		// Durable backfill guard: a post imported by a feed whose backfilled
		// flag is still 0 was created by that feed's historic run (the flag is
		// set only after the run completes) — never announce it, even if the
		// in-process flag above was lost to an error mid-run.
		if ( class_exists( 'Synpro_Feeds' ) ) {
			$feed_id = (int) get_post_meta( $post->ID, '_synpro_feed_id', true );
			if ( $feed_id ) {
				$feed_row = Synpro_Feeds::get( $feed_id );
				if ( $feed_row && ! (int) $feed_row->backfilled ) {
					return;
				}
			}
		}

		$queued = false;
		foreach ( array_keys( self::networks() ) as $network ) {
			if ( ! self::is_enabled( $network, $post->post_type ) ) {
				continue;
			}
			if ( get_post_meta( $post->ID, '_synpro_shared_' . $network, true ) ) {
				continue; // Already announced on this network.
			}
			self::enqueue( $post->ID, $network );
			$queued = true;
		}

		// Process soon rather than waiting for the next 5-minute tick.
		if ( $queued && ! wp_next_scheduled( 'synpro_process_share_queue' ) ) {
			wp_schedule_single_event( time() + 15, 'synpro_process_share_queue' );
		}
	}

	/**
	 * Add one share job to the queue.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $network Network key.
	 */
	protected static function enqueue( $post_id, $network ) {
		$queue   = (array) get_option( self::QUEUE_OPTION, array() );
		$queue[] = array(
			'post_id'  => (int) $post_id,
			'network'  => $network,
			'attempts' => 0,
		);
		update_option( self::QUEUE_OPTION, $queue, false );
	}

	/**
	 * Process the queue: attempt every job, requeue failures with attempt
	 * counts, drop jobs that exhausted their retries (recording the error).
	 */
	public static function process_queue() {
		// Only one processor at a time: overlapping cron runs would otherwise
		// snapshot the same jobs and double-post.
		if ( get_transient( 'synpro_share_queue_lock' ) ) {
			if ( ! wp_next_scheduled( 'synpro_process_share_queue' ) ) {
				wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'synpro_process_share_queue' );
			}
			return;
		}
		set_transient( 'synpro_share_queue_lock', 1, 2 * MINUTE_IN_SECONDS );

		$queue = (array) get_option( self::QUEUE_OPTION, array() );
		if ( empty( $queue ) ) {
			delete_transient( 'synpro_share_queue_lock' );
			return;
		}
		update_option( self::QUEUE_OPTION, array(), false );

		$retry = array();
		foreach ( $queue as $job ) {
			$post = get_post( (int) $job['post_id'] );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}

			// Claim before sending: add_post_meta with $unique=true fails if
			// the key exists, so a post can never be announced twice on the
			// same network even by racing processes. A failed send releases
			// the claim for the retry.
			if ( ! add_post_meta( $post->ID, '_synpro_shared_' . $job['network'], time(), true ) ) {
				continue; // Already shared or claimed elsewhere.
			}

			$result = self::share( $post, $job['network'] );
			if ( is_wp_error( $result ) ) {
				delete_post_meta( $post->ID, '_synpro_shared_' . $job['network'] );
				$job['attempts']++;
				if ( $job['attempts'] < self::MAX_ATTEMPTS ) {
					$retry[] = $job;
				} else {
					update_post_meta( $post->ID, '_synpro_share_error_' . $job['network'], $result->get_error_message() );
				}
			} else {
				delete_post_meta( $post->ID, '_synpro_share_error_' . $job['network'] );
			}
		}

		if ( $retry ) {
			$existing = (array) get_option( self::QUEUE_OPTION, array() );
			update_option( self::QUEUE_OPTION, array_merge( $existing, $retry ), false );
			if ( ! wp_next_scheduled( 'synpro_process_share_queue' ) ) {
				wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'synpro_process_share_queue' );
			}
		}

		delete_transient( 'synpro_share_queue_lock' );
	}

	/* -----------------------------------------------------------------------
	 * Message building
	 * -------------------------------------------------------------------- */

	/**
	 * Build the share message for a post on a network.
	 *
	 * @param WP_Post $post    Post.
	 * @param string  $network Network key.
	 * @return string
	 */
	public static function build_message( $post, $network ) {
		$s = self::settings();
		// Priority: per-network developer override, then the per-type template
		// from the Templates tab (explicit stored value, not compared against
		// the rendered default), then legacy "all:" keys, then the default.
		$network_key = $network . ':' . $post->post_type;
		$all_key     = 'all:' . $post->post_type;
		$tab_stored  = (array) get_option( 'synpro_templates', array() );
		if ( ! empty( $s['templates'][ $network_key ] ) ) {
			$template = $s['templates'][ $network_key ];
		} elseif ( ! empty( $tab_stored[ $post->post_type ]['social'] ) ) {
			$template = $tab_stored[ $post->post_type ]['social'];
		} elseif ( ! empty( $s['templates'][ $all_key ] ) ) {
			$template = $s['templates'][ $all_key ];
		} else {
			$template = self::default_template( $post->post_type );
		}

		$title    = wp_strip_all_tags( get_the_title( $post ) );
		$author   = self::author_handle( (int) $post->post_author, $network );
		$excerpt  = self::first_sentence( $post );
		$link     = get_permalink( $post );
		$hashtags = self::hashtags( $post );

		$source_url  = get_post_meta( $post->ID, '_synpro_source_url', true );
		$source_name = get_post_meta( $post->ID, '_synpro_source_name', true );

		$message = strtr(
			$template,
			array(
				'{title}'       => $title,
				'{author}'      => $author,
				'{excerpt}'     => $excerpt,
				'{link}'        => $link,
				'{hashtags}'    => $hashtags,
				'{source_name}' => $source_name,
				'{source_url}'  => $source_url,
				'{date}'        => get_the_date( '', $post ),
			)
		);
		$message = trim( preg_replace( "/\n{3,}/", "\n\n", $message ) );

		// Truncate to the network limit: shrink the excerpt first, then drop
		// hashtags — never the title or link. Links on X count as 23 chars.
		$limit  = self::char_limit( $network );
		$length = function ( $text ) use ( $network, $link ) {
			if ( 'twitter' === $network && $link ) {
				return mb_strlen( str_replace( $link, str_repeat( 'x', 23 ), $text ) );
			}
			return mb_strlen( $text );
		};

		if ( $length( $message ) > $limit && $excerpt ) {
			$excess    = $length( $message ) - $limit;
			$keep      = max( 0, mb_strlen( $excerpt ) - $excess - 1 );
			$short     = $keep > 20 ? rtrim( mb_substr( $excerpt, 0, $keep ) ) . '…' : '';
			$message   = str_replace( $excerpt, $short, $message );
			$message   = trim( preg_replace( "/\n{3,}/", "\n\n", $message ) );
		}
		if ( $length( $message ) > $limit && $hashtags ) {
			$message = trim( str_replace( $hashtags, '', $message ) );
		}

		// Last resort: shorten the title itself, otherwise a very long title
		// plus the link would exceed the limit on every attempt and the share
		// could never succeed.
		if ( $length( $message ) > $limit && $title && mb_strlen( $title ) > 40 ) {
			$excess      = $length( $message ) - $limit;
			$keep        = max( 30, mb_strlen( $title ) - $excess - 1 );
			$short_title = rtrim( mb_substr( $title, 0, $keep ) ) . '…';
			$message     = str_replace( $title, $short_title, $message );
		}
		if ( $length( $message ) > $limit ) {
			$message = rtrim( mb_substr( $message, 0, $limit - 1 ) ) . '…';
		}

		return $message;
	}

	/**
	 * The member's @handle on a network, derived from their profile links;
	 * falls back to their display name. LinkedIn always uses the name.
	 *
	 * @param int    $user_id User ID.
	 * @param string $network Network key.
	 * @return string
	 */
	public static function author_handle( $user_id, $network ) {
		$name = get_the_author_meta( 'display_name', $user_id );

		if ( 'bluesky' === $network ) {
			$url = get_user_meta( $user_id, 'synpro_link_bluesky', true );
			if ( $url && preg_match( '~/profile/([^/?#]+)~', $url, $m ) ) {
				return '@' . $m[1];
			}
		} elseif ( 'mastodon' === $network ) {
			$url = get_user_meta( $user_id, 'synpro_link_mastodon', true );
			if ( $url ) {
				$host = wp_parse_url( $url, PHP_URL_HOST );
				$path = (string) wp_parse_url( $url, PHP_URL_PATH );
				if ( $host && preg_match( '~/@([^/?#]+)~', $path, $m ) ) {
					return '@' . $m[1] . '@' . $host;
				}
			}
		} elseif ( 'twitter' === $network ) {
			$url = get_user_meta( $user_id, 'synpro_link_twitter', true );
			if ( $url && preg_match( '~(?:twitter|x)\.com/@?([A-Za-z0-9_]+)~', $url, $m ) ) {
				return '@' . $m[1];
			}
		}

		return $name ? $name : __( 'a community member', 'syndicate-pro' );
	}

	/**
	 * First sentence of the post's excerpt/content.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function first_sentence( $post ) {
		$text = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
		$text = trim( wp_strip_all_tags( strip_shortcodes( $text ) ) );
		$text = preg_replace( '/\s+/', ' ', $text );
		if ( '' === $text ) {
			return '';
		}
		if ( preg_match( '/^.*?[.!?](?=\s|$)/u', $text, $m ) ) {
			return trim( $m[0] );
		}
		return wp_trim_words( $text, 25 );
	}

	/**
	 * The post's categories as hashtags: "Field Service" -> #FieldService.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function hashtags( $post ) {
		$terms = get_the_terms( $post, 'category' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$tags = array();
		foreach ( array_slice( $terms, 0, 5 ) as $term ) {
			$tag = preg_replace( '/[^A-Za-z0-9]/', '', ucwords( $term->name ) );
			if ( $tag && 'Uncategorized' !== $tag ) {
				$tags[] = '#' . $tag;
			}
		}
		return implode( ' ', array_unique( $tags ) );
	}

	/**
	 * The featured image path + MIME type, when there is one.
	 *
	 * @param WP_Post $post Post.
	 * @return array|null { path, mime, alt } or null.
	 */
	protected static function featured_image( $post ) {
		$thumb_id = get_post_thumbnail_id( $post );
		if ( ! $thumb_id ) {
			return null;
		}
		$path = get_attached_file( $thumb_id );
		if ( ! $path || ! file_exists( $path ) || filesize( $path ) > 5 * MB_IN_BYTES ) {
			return null;
		}
		return array(
			'path' => $path,
			'mime' => get_post_mime_type( $thumb_id ),
			'alt'  => wp_strip_all_tags( get_the_title( $post ) ),
		);
	}

	/* -----------------------------------------------------------------------
	 * Dispatch
	 * -------------------------------------------------------------------- */

	/**
	 * Share a post on one network.
	 *
	 * @param WP_Post $post    Post.
	 * @param string  $network Network key.
	 * @return true|WP_Error
	 */
	public static function share( $post, $network ) {
		$message = self::build_message( $post, $network );
		$image   = self::featured_image( $post );

		switch ( $network ) {
			case 'mastodon':
				return self::post_mastodon( $message, $image );
			case 'bluesky':
				return self::post_bluesky( $message, $image );
			case 'twitter':
				return self::post_twitter( $message, $image );
			case 'linkedin':
				return self::post_linkedin( $message, $image );
		}
		return new WP_Error( 'synpro_unknown_network', $network );
	}

	/* ----------------------------- Mastodon ----------------------------- */

	/**
	 * Post to Mastodon.
	 *
	 * @param string     $message Status text.
	 * @param array|null $image   Featured image.
	 * @return true|WP_Error
	 */
	protected static function post_mastodon( $message, $image ) {
		$s        = self::settings();
		$instance = untrailingslashit( $s['mastodon_instance'] );
		$token    = $s['mastodon_token'];

		$media_id = null;
		if ( $image ) {
			$upload = self::multipart_request(
				$instance . '/api/v2/media',
				array( 'Authorization' => 'Bearer ' . $token ),
				array(),
				'file',
				$image
			);
			if ( ! is_wp_error( $upload ) && ! empty( $upload['id'] ) ) {
				$media_id = $upload['id'];
			}
		}

		$body = array( 'status' => $message );
		if ( $media_id ) {
			$body['media_ids'] = array( $media_id );
		}

		$response = wp_remote_post(
			$instance . '/api/v1/statuses',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		return self::check_response( $response, array( 200 ) );
	}

	/* ----------------------------- Bluesky ------------------------------ */

	/**
	 * Post to Bluesky via the AT Protocol.
	 *
	 * @param string     $message Post text.
	 * @param array|null $image   Featured image.
	 * @return true|WP_Error
	 */
	protected static function post_bluesky( $message, $image ) {
		$s = self::settings();

		// 1. Create a session with the app password.
		$session = wp_remote_post(
			'https://bsky.social/xrpc/com.atproto.server.createSession',
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'identifier' => ltrim( $s['bluesky_handle'], '@' ),
						'password'   => $s['bluesky_app_password'],
					)
				),
			)
		);
		$check = self::check_response( $session, array( 200 ) );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$auth = json_decode( wp_remote_retrieve_body( $session ), true );
		if ( empty( $auth['accessJwt'] ) || empty( $auth['did'] ) ) {
			return new WP_Error( 'synpro_bluesky_auth', __( 'Bluesky login failed.', 'syndicate-pro' ) );
		}

		$record = array(
			'$type'     => 'app.bsky.feed.post',
			'text'      => $message,
			'createdAt' => gmdate( 'Y-m-d\TH:i:s\Z' ),
		);

		// 2. Upload the image blob.
		if ( $image ) {
			$blob_response = wp_remote_post(
				'https://bsky.social/xrpc/com.atproto.repo.uploadBlob',
				array(
					'timeout' => 30,
					'headers' => array(
						'Authorization' => 'Bearer ' . $auth['accessJwt'],
						'Content-Type'  => $image['mime'],
					),
					'body'    => file_get_contents( $image['path'] ), // phpcs:ignore WordPress.WP.AlternativeFunctions
				)
			);
			if ( ! is_wp_error( self::check_response( $blob_response, array( 200 ) ) ) ) {
				$blob = json_decode( wp_remote_retrieve_body( $blob_response ), true );
				if ( ! empty( $blob['blob'] ) ) {
					$record['embed'] = array(
						'$type'  => 'app.bsky.embed.images',
						'images' => array(
							array(
								'alt'   => $image['alt'],
								'image' => $blob['blob'],
							),
						),
					);
				}
			}
		}

		// 3. Create the post record.
		$response = wp_remote_post(
			'https://bsky.social/xrpc/com.atproto.repo.createRecord',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $auth['accessJwt'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'repo'       => $auth['did'],
						'collection' => 'app.bsky.feed.post',
						'record'     => $record,
					)
				),
			)
		);
		return self::check_response( $response, array( 200 ) );
	}

	/* ------------------------------- X ---------------------------------- */

	/**
	 * Post to X/Twitter (OAuth 1.0a user context).
	 *
	 * @param string     $message Tweet text.
	 * @param array|null $image   Featured image.
	 * @return true|WP_Error
	 */
	protected static function post_twitter( $message, $image ) {
		$media_id = null;
		if ( $image ) {
			$upload = self::multipart_request(
				'https://upload.twitter.com/1.1/media/upload.json',
				array( 'Authorization' => self::twitter_oauth_header( 'POST', 'https://upload.twitter.com/1.1/media/upload.json' ) ),
				array(),
				'media',
				$image
			);
			if ( ! is_wp_error( $upload ) && ! empty( $upload['media_id_string'] ) ) {
				$media_id = $upload['media_id_string'];
			}
		}

		$body = array( 'text' => $message );
		if ( $media_id ) {
			$body['media'] = array( 'media_ids' => array( $media_id ) );
		}

		$url      = 'https://api.twitter.com/2/tweets';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => self::twitter_oauth_header( 'POST', $url ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		return self::check_response( $response, array( 200, 201 ) );
	}

	/**
	 * Build an OAuth 1.0a Authorization header for X.
	 *
	 * @param string $method HTTP method.
	 * @param string $url    Request URL (no query string).
	 * @param array  $params Query/body params included in the signature.
	 * @return string
	 */
	protected static function twitter_oauth_header( $method, $url, $params = array() ) {
		$s     = self::settings();
		$oauth = array(
			'oauth_consumer_key'     => $s['twitter_api_key'],
			'oauth_nonce'            => wp_generate_password( 32, false ),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => (string) time(),
			'oauth_token'            => $s['twitter_access_token'],
			'oauth_version'          => '1.0',
		);

		$sign_params = array_merge( $oauth, $params );
		ksort( $sign_params );
		$pairs = array();
		foreach ( $sign_params as $key => $value ) {
			$pairs[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
		}

		$base = strtoupper( $method ) . '&' . rawurlencode( $url ) . '&' . rawurlencode( implode( '&', $pairs ) );
		$key  = rawurlencode( $s['twitter_api_secret'] ) . '&' . rawurlencode( $s['twitter_access_secret'] );

		$oauth['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $base, $key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

		$header = array();
		foreach ( $oauth as $k => $v ) {
			$header[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
		}
		return 'OAuth ' . implode( ', ', $header );
	}

	/* ----------------------------- LinkedIn ----------------------------- */

	/**
	 * Post to the community's LinkedIn organisation page.
	 *
	 * @param string     $message Post text.
	 * @param array|null $image   Featured image.
	 * @return true|WP_Error
	 */
	protected static function post_linkedin( $message, $image ) {
		$s     = self::settings();
		$token = $s['linkedin_token'];
		$urn   = $s['linkedin_org_urn'];

		$asset = null;
		if ( $image ) {
			// 1. Register the upload.
			$register = wp_remote_post(
				'https://api.linkedin.com/v2/assets?action=registerUpload',
				array(
					'timeout' => 20,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'registerUploadRequest' => array(
								'recipes'              => array( 'urn:li:digitalmediaRecipe:feedshare-image' ),
								'owner'                => $urn,
								'serviceRelationships' => array(
									array(
										'relationshipType' => 'OWNER',
										'identifier'       => 'urn:li:userGeneratedContent',
									),
								),
							),
						)
					),
				)
			);
			if ( ! is_wp_error( self::check_response( $register, array( 200 ) ) ) ) {
				$reg = json_decode( wp_remote_retrieve_body( $register ), true );
				$upload_url = $reg['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? '';
				$asset      = $reg['value']['asset'] ?? null;

				// 2. Upload the binary.
				if ( $upload_url && $asset ) {
					$put = wp_remote_request(
						$upload_url,
						array(
							'method'  => 'PUT',
							'timeout' => 30,
							'headers' => array(
								'Authorization' => 'Bearer ' . $token,
								'Content-Type'  => $image['mime'],
							),
							'body'    => file_get_contents( $image['path'] ), // phpcs:ignore WordPress.WP.AlternativeFunctions
						)
					);
					if ( is_wp_error( self::check_response( $put, array( 200, 201 ) ) ) ) {
						$asset = null;
					}
				}
			}
		}

		$share_content = array(
			'shareCommentary'    => array( 'text' => $message ),
			'shareMediaCategory' => $asset ? 'IMAGE' : 'NONE',
		);
		if ( $asset ) {
			$share_content['media'] = array(
				array(
					'status' => 'READY',
					'media'  => $asset,
				),
			);
		}

		$response = wp_remote_post(
			'https://api.linkedin.com/v2/ugcPosts',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization'             => 'Bearer ' . $token,
					'Content-Type'              => 'application/json',
					'X-Restli-Protocol-Version' => '2.0.0',
				),
				'body'    => wp_json_encode(
					array(
						'author'          => $urn,
						'lifecycleState'  => 'PUBLISHED',
						'specificContent' => array( 'com.linkedin.ugc.ShareContent' => $share_content ),
						'visibility'      => array( 'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC' ),
					)
				),
			)
		);
		return self::check_response( $response, array( 200, 201 ) );
	}

	/* ------------------------------ Helpers ----------------------------- */

	/**
	 * Send a multipart/form-data request with one file field.
	 *
	 * @param string $url        Endpoint.
	 * @param array  $headers    Extra headers (Authorization etc.).
	 * @param array  $fields     Plain form fields.
	 * @param string $file_field Name of the file field.
	 * @param array  $image      { path, mime }.
	 * @return array|WP_Error Decoded JSON body.
	 */
	protected static function multipart_request( $url, $headers, $fields, $file_field, $image ) {
		$boundary = wp_generate_password( 24, false );
		$body     = '';

		foreach ( $fields as $name => $value ) {
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
		}

		$filename = basename( $image['path'] );
		$body    .= "--{$boundary}\r\n";
		$body    .= "Content-Disposition: form-data; name=\"{$file_field}\"; filename=\"{$filename}\"\r\n";
		$body    .= "Content-Type: {$image['mime']}\r\n\r\n";
		$body    .= file_get_contents( $image['path'] ) . "\r\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$body    .= "--{$boundary}--\r\n";

		$headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => $headers,
				'body'    => $body,
			)
		);
		$check = self::check_response( $response, array( 200, 201, 202 ) );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		return (array) json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Turn an HTTP response into true or a WP_Error.
	 *
	 * @param array|WP_Error $response Response.
	 * @param int[]          $ok       Acceptable status codes.
	 * @return true|WP_Error
	 */
	protected static function check_response( $response, $ok ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $code, $ok, true ) ) {
			return true;
		}
		$body = wp_remote_retrieve_body( $response );
		return new WP_Error(
			'synpro_social_http_' . $code,
			sprintf( 'HTTP %d: %s', $code, mb_substr( wp_strip_all_tags( (string) $body ), 0, 200 ) )
		);
	}

	/**
	 * "Send test post" button handler.
	 */
	public static function handle_test_post() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'syndicate-pro' ) );
		}
		$network = isset( $_GET['network'] ) ? sanitize_key( $_GET['network'] ) : '';
		check_admin_referer( 'synpro_social_test_' . $network );

		if ( ! isset( self::networks()[ $network ] ) ) {
			wp_die( esc_html__( 'Unknown network.', 'syndicate-pro' ) );
		}

		$message = sprintf(
			/* translators: 1: site name, 2: site URL. */
			__( 'Test post from %1$s — connection working. %2$s', 'syndicate-pro' ),
			get_bloginfo( 'name' ),
			home_url( '/' )
		);

		$result = true;
		switch ( $network ) {
			case 'mastodon':
				$result = self::post_mastodon( $message, null );
				break;
			case 'bluesky':
				$result = self::post_bluesky( $message, null );
				break;
			case 'twitter':
				$result = self::post_twitter( $message, null );
				break;
			case 'linkedin':
				$result = self::post_linkedin( $message, null );
				break;
		}

		$arg = is_wp_error( $result ) ? array( 'synpro_test_error' => rawurlencode( $result->get_error_message() ) ) : array( 'synpro_test_ok' => $network );
		wp_safe_redirect( add_query_arg( $arg, admin_url( 'admin.php?page=synpro-syndication&tab=social' ) ) );
		exit;
	}
}

endif;
