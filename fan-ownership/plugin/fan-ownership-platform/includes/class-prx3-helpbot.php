<?php
/**
 * FanPress Bot: an on-site help bot that answers questions from a
 * staff-curated FAQ knowledge base and, when it can't, opens a support
 * ticket routed to the club. Configurable name/avatar/colour/background
 * (FanPress Technical Setup → FanPress Bot). Presented WhatsApp-style,
 * consistent with FanPress Chat, on the member site and the app (shared
 * REST). Answers stay on the server (no third-party AI) — an LLM answer
 * mode can be layered on later behind a setting.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * The help bot + support-ticket engine.
 */
class PRX3_Helpbot {

	/**
	 * Wire post types, REST, the shortcodes, admin screens, and the
	 * floating launcher.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_types' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_shortcode( 'prx3_helpbot', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'prx3_tickets', array( __CLASS__, 'tickets_shortcode' ) );
		add_action( 'admin_post_prx3_ticket_reply', array( __CLASS__, 'handle_ticket_reply' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'ticket_meta_box' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'floating_launcher' ) );
	}

	/**
	 * The FAQ knowledge base and the support-ticket store. FAQs are
	 * public content (they can also render on a help page); tickets are
	 * private to the member and staff.
	 */
	public static function register_types() {
		register_post_type(
			'prx3_faq',
			array(
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'prx3-setup',
				'menu_icon'       => 'dashicons-editor-help',
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'editor', 'page-attributes' ),
				'labels'          => self::labels( __( 'FAQ', 'fan-ownership' ), __( 'FAQs', 'fan-ownership' ) ),
			)
		);
		register_post_type(
			'prx3_ticket',
			array(
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'prx3-setup',
				'menu_icon'       => 'dashicons-sos',
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'editor', 'author', 'comments' ),
				'labels'          => self::labels( __( 'Support ticket', 'fan-ownership' ), __( 'Support tickets', 'fan-ownership' ) ),
			)
		);
	}

	/**
	 * Minimal post-type label set.
	 *
	 * @param string $singular Singular label.
	 * @param string $plural   Plural label.
	 * @return array
	 */
	protected static function labels( $singular, $plural ) {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'add_new_item'  => sprintf( /* translators: %s singular. */ __( 'Add %s', 'fan-ownership' ), $singular ),
			'edit_item'     => sprintf( /* translators: %s singular. */ __( 'Edit %s', 'fan-ownership' ), $singular ),
		);
	}

	/* ---------------- Configuration ---------------- */

	/**
	 * Whether the bot is switched on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return '1' === (string) prx3_setting( 'bot_enabled', '1' );
	}

	/**
	 * The public bot configuration (name, greeting, avatar, colours,
	 * background) for the web widget and the app.
	 *
	 * @return array
	 */
	public static function config() {
		$avatar = (int) prx3_setting( 'bot_avatar_id', 0 );
		$bg     = (int) prx3_setting( 'bot_bg_image_id', 0 );
		return array(
			'enabled'    => self::enabled(),
			'name'       => (string) prx3_setting( 'bot_name', 'FanPress Bot' ),
			'greeting'   => (string) prx3_setting( 'bot_greeting', '' ),
			'avatar'     => $avatar ? (string) wp_get_attachment_image_url( $avatar, array( 96, 96 ) ) : '',
			'color'      => (string) prx3_setting( 'bot_color', '#1a1a2e' ),
			'background' => $bg ? (string) wp_get_attachment_image_url( $bg, 'large' ) : '',
			'bg_color'   => (string) prx3_setting( 'bot_bg_color', '' ),
		);
	}

	/* ---------------- Answering ---------------- */

	/**
	 * Published FAQ entries, ordered by menu order then title.
	 *
	 * @return WP_Post[]
	 */
	public static function faqs() {
		return get_posts(
			array(
				'post_type'      => 'prx3_faq',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'  => true,
			)
		);
	}

	/**
	 * Reduce a string to a set of lower-case word tokens (3+ chars),
	 * with a few noise words removed.
	 *
	 * @param string $text Input.
	 * @return string[] Tokens.
	 */
	public static function tokens( $text ) {
		$text = strtolower( wp_strip_all_tags( (string) $text ) );
		$stop = array( 'the', 'and', 'for', 'you', 'your', 'how', 'what', 'when', 'where', 'why', 'can', 'does', 'with', 'this', 'that', 'from', 'are', 'was' );
		$out  = array();
		foreach ( preg_split( '/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
			if ( strlen( $word ) >= 3 && ! in_array( $word, $stop, true ) ) {
				$out[ $word ] = true;
			}
		}
		return array_keys( $out );
	}

	/**
	 * Answer a question from the FAQ knowledge base. Scores each FAQ by
	 * how many of the question's words appear in its title (weighted) and
	 * body, and returns the best match above a confidence floor.
	 *
	 * @param string $query The member's question.
	 * @return array|null ['answer','title','faq_id','score'] or null when unsure.
	 */
	public static function answer( $query ) {
		$q = self::tokens( $query );
		if ( ! $q ) {
			return null;
		}
		$best  = null;
		$score = 0.0;
		foreach ( self::faqs() as $faq ) {
			$title = self::tokens( $faq->post_title );
			$body  = self::tokens( $faq->post_content );
			$hit   = 0.0;
			foreach ( $q as $word ) {
				if ( in_array( $word, $title, true ) ) {
					$hit += 2.0;
				} elseif ( in_array( $word, $body, true ) ) {
					$hit += 1.0;
				}
			}
			$this_score = $hit / ( count( $q ) * 2.0 );
			if ( $this_score > $score ) {
				$score = $this_score;
				$best  = $faq;
			}
		}
		if ( ! $best || $score < 0.34 ) {
			return null;
		}
		return array(
			'faq_id' => $best->ID,
			'title'  => $best->post_title,
			'answer' => wp_kses_post( wpautop( $best->post_content ) ),
			'score'  => round( $score, 2 ),
		);
	}

	/* ---------------- Tickets ---------------- */

	/**
	 * Open a support ticket for a member and notify the club.
	 *
	 * @param int    $user_id Member.
	 * @param string $subject Subject line.
	 * @param string $body    Message.
	 * @return int|WP_Error Ticket ID.
	 */
	public static function create_ticket( $user_id, $subject, $body ) {
		$user_id = (int) $user_id;
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return new WP_Error( 'prx3_ticket_user', __( 'You must be signed in to open a ticket.', 'fan-ownership' ) );
		}
		$subject = trim( sanitize_text_field( $subject ) );
		$body    = trim( sanitize_textarea_field( $body ) );
		if ( '' === $subject || '' === $body ) {
			return new WP_Error( 'prx3_ticket_empty', __( 'Please add a subject and a message.', 'fan-ownership' ) );
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'prx3_ticket',
				'post_status'  => 'publish',
				'post_title'   => $subject,
				'post_content' => $body,
				'post_author'  => $user_id,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_prx3_ticket_status', 'open' );
		PRX3_Audit::log( 'ticket_opened', sprintf( 'Support ticket %1$d opened by member %2$d', $id, $user_id ) );
		self::notify_staff( $id, $subject, $body );
		return $id;
	}

	/**
	 * Email the support inbox / admins that a ticket was opened.
	 *
	 * @param int    $ticket_id Ticket ID.
	 * @param string $subject   Subject.
	 * @param string $body      Body.
	 */
	protected static function notify_staff( $ticket_id, $subject, $body ) {
		if ( ! class_exists( 'PRX3_Comms' ) ) {
			return;
		}
		$link = admin_url( 'post.php?post=' . (int) $ticket_id . '&action=edit' );
		foreach ( get_users( array( 'role__in' => array( 'prx3_owner_admin', 'prx3_governance_officer', 'administrator' ) ) ) as $staff ) {
			PRX3_Comms::send(
				$staff->user_email,
				sprintf( /* translators: %s subject. */ __( 'New support ticket: %s', 'fan-ownership' ), $subject ),
				$body . "\n\n" . $link,
				'governance'
			);
		}
	}

	/**
	 * The tickets belonging to one member, newest first.
	 *
	 * @param int $user_id Member.
	 * @return WP_Post[]
	 */
	public static function member_tickets( $user_id ) {
		return get_posts(
			array(
				'post_type'      => 'prx3_ticket',
				'post_status'    => 'publish',
				'author'         => (int) $user_id,
				'posts_per_page' => 50,
				'no_found_rows'  => true,
			)
		);
	}

	/**
	 * Add a reply to a ticket (member or staff) and notify the other
	 * party. Staff replies may also change the status.
	 *
	 * @param int    $ticket_id Ticket.
	 * @param int    $user_id   Author of the reply.
	 * @param string $body      Reply text.
	 * @param bool   $is_staff  Whether this is a staff reply.
	 * @return int|WP_Error Comment ID.
	 */
	public static function add_reply( $ticket_id, $user_id, $body, $is_staff ) {
		$ticket = get_post( $ticket_id );
		if ( ! $ticket || 'prx3_ticket' !== $ticket->post_type ) {
			return new WP_Error( 'prx3_ticket_missing', __( 'Ticket not found.', 'fan-ownership' ) );
		}
		if ( ! $is_staff && (int) $ticket->post_author !== (int) $user_id ) {
			return new WP_Error( 'prx3_ticket_owner', __( 'You can only reply to your own tickets.', 'fan-ownership' ) );
		}
		$body = trim( sanitize_textarea_field( $body ) );
		if ( '' === $body ) {
			return new WP_Error( 'prx3_ticket_empty', __( 'Please write a reply.', 'fan-ownership' ) );
		}
		$user = get_userdata( $user_id );
		$cid  = wp_insert_comment(
			array(
				'comment_post_ID'  => (int) $ticket_id,
				'comment_content'  => $body,
				'user_id'          => (int) $user_id,
				'comment_author'   => $user ? $user->display_name : '',
				'comment_approved' => 1,
				'comment_meta'     => array( '_prx3_staff' => $is_staff ? '1' : '' ),
			)
		);
		if ( ! $cid ) {
			return new WP_Error( 'prx3_ticket_reply', __( 'Could not save the reply.', 'fan-ownership' ) );
		}
		update_post_meta( $ticket_id, '_prx3_ticket_status', $is_staff ? 'answered' : 'open' );
		// Notify the other party.
		if ( class_exists( 'PRX3_Comms' ) ) {
			if ( $is_staff ) {
				$member = get_userdata( (int) $ticket->post_author );
				if ( $member ) {
					PRX3_Comms::send( $member->user_email, sprintf( /* translators: %s subject. */ __( 'Reply to your ticket: %s', 'fan-ownership' ), $ticket->post_title ), $body, 'governance' );
				}
			} else {
				self::notify_staff( $ticket_id, $ticket->post_title, $body );
			}
		}
		return $cid;
	}

	/* ---------------- REST (shared by web widget + app) ---------------- */

	/**
	 * Register the help + ticket routes under prx3/v1.
	 */
	public static function routes() {
		register_rest_route(
			'prx3/v1',
			'/help/config',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'route_config' ),
			)
		);
		register_rest_route(
			'prx3/v1',
			'/help/ask',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'route_ask' ),
			)
		);
		register_rest_route(
			'prx3/v1',
			'/tickets',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( __CLASS__, 'member_permission' ),
					'callback'            => array( __CLASS__, 'route_my_tickets' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( __CLASS__, 'member_permission' ),
					'callback'            => array( __CLASS__, 'route_create_ticket' ),
				),
			)
		);
		register_rest_route(
			'prx3/v1',
			'/tickets/(?P<id>\d+)/reply',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'member_permission' ),
				'callback'            => array( __CLASS__, 'route_reply' ),
			)
		);
	}

	/**
	 * REST permission: a signed-in member.
	 *
	 * @return bool
	 */
	public static function member_permission() {
		return is_user_logged_in();
	}

	/**
	 * GET /help/config.
	 *
	 * @return array
	 */
	public static function route_config() {
		return self::config();
	}

	/**
	 * POST /help/ask { q }.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function route_ask( $request ) {
		$hit = self::answer( (string) $request['q'] );
		if ( $hit ) {
			return array(
				'answered' => true,
				'answer'   => $hit['answer'],
				'title'    => $hit['title'],
			);
		}
		return array(
			'answered'       => false,
			'message'        => __( "I couldn't find an answer to that. Would you like to open a support ticket so the club can help?", 'fan-ownership' ),
			'suggest_ticket' => true,
		);
	}

	/**
	 * GET /tickets — the caller's tickets.
	 *
	 * @return array
	 */
	public static function route_my_tickets() {
		$out = array();
		foreach ( self::member_tickets( get_current_user_id() ) as $t ) {
			$out[] = array(
				'id'      => $t->ID,
				'subject' => $t->post_title,
				'status'  => (string) get_post_meta( $t->ID, '_prx3_ticket_status', true ),
				'opened'  => $t->post_date_gmt,
			);
		}
		return $out;
	}

	/**
	 * POST /tickets { subject, body }.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function route_create_ticket( $request ) {
		$id = self::create_ticket( get_current_user_id(), (string) $request['subject'], (string) $request['body'] );
		return is_wp_error( $id ) ? $id : array(
			'id'     => $id,
			'status' => 'open',
		);
	}

	/**
	 * POST /tickets/{id}/reply { body }.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function route_reply( $request ) {
		$cid = self::add_reply( (int) $request['id'], get_current_user_id(), (string) $request['body'], current_user_can( 'prx3_admin' ) );
		return is_wp_error( $cid ) ? $cid : array( 'ok' => true );
	}

	/* ---------------- Front-end widget ---------------- */

	/**
	 * Register the widget assets.
	 */
	public static function assets() {
		wp_register_script( 'prx3-helpbot', PRX3_URL . 'assets/js/prx3-helpbot.js', array( 'prx3' ), PRX3_VERSION, true );
	}

	/**
	 * The chat widget markup (WhatsApp-style), shared by the shortcode
	 * and the floating launcher.
	 *
	 * @return string
	 */
	public static function widget_html() {
		$c     = self::config();
		$style = '--prx3-bot:' . esc_attr( $c['color'] );
		if ( '' !== $c['bg_color'] ) {
			$style .= ';--prx3-bot-bg:' . esc_attr( $c['bg_color'] );
		}
		$bg = '' !== $c['background'] ? ' style="background-image:url(' . esc_url( $c['background'] ) . ')"' : '';
		ob_start();
		echo '<div class="prx3-bot" data-prx3-bot style="' . esc_attr( $style ) . '">';
		echo '<div class="prx3-bot__head">';
		if ( '' !== $c['avatar'] ) {
			echo '<img class="prx3-bot__avatar" src="' . esc_url( $c['avatar'] ) . '" alt="" width="28" height="28">';
		}
		echo '<strong>' . esc_html( $c['name'] ) . '</strong></div>';
		echo '<div class="prx3-bot__scroll"' . $bg . ' data-prx3-bot-log>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $bg is an escaped inline style.
		echo '<div class="prx3-bubble-row"><div class="prx3-bubble prx3-bubble--theirs">' . esc_html( $c['greeting'] ) . '</div></div>';
		echo '</div>';
		echo '<form class="prx3-bot__form" data-prx3-bot-form>';
		echo '<input type="text" data-prx3-bot-input placeholder="' . esc_attr__( 'Ask a question…', 'fan-ownership' ) . '" aria-label="' . esc_attr__( 'Ask the help bot', 'fan-ownership' ) . '">';
		echo '<button type="submit" class="prx3-button">' . esc_html__( 'Send', 'fan-ownership' ) . '</button>';
		echo '</form></div>';
		return ob_get_clean();
	}

	/**
	 * [prx3_helpbot] — the inline help chat.
	 *
	 * @return string
	 */
	public static function shortcode() {
		if ( ! self::enabled() ) {
			return '';
		}
		wp_enqueue_script( 'prx3-helpbot' );
		wp_enqueue_style( 'prx3' );
		return self::widget_html();
	}

	/**
	 * A floating launcher on member-facing pages, so the bot is one tap
	 * away everywhere without adding the shortcode to each page.
	 */
	public static function floating_launcher() {
		if ( is_admin() || ! self::enabled() || ! prx3_is_owner() ) {
			return;
		}
		wp_enqueue_script( 'prx3-helpbot' );
		wp_enqueue_style( 'prx3' );
		echo '<div class="prx3-bot-launch" data-prx3-bot-launch>';
		echo '<button type="button" class="prx3-bot-launch__btn" aria-label="' . esc_attr__( 'Open help', 'fan-ownership' ) . '" style="background:' . esc_attr( (string) prx3_setting( 'bot_color', '#1a1a2e' ) ) . '">?</button>';
		echo '<div class="prx3-bot-launch__panel" hidden>' . self::widget_html() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- widget_html() escapes its own output.
		echo '</div>';
	}

	/**
	 * [prx3_tickets] — a member's own support tickets and their status.
	 *
	 * @return string
	 */
	public static function tickets_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$tickets = self::member_tickets( get_current_user_id() );
		ob_start();
		echo '<div class="prx3-tickets"><h2>' . esc_html__( 'My support tickets', 'fan-ownership' ) . '</h2>';
		if ( ! $tickets ) {
			echo '<p>' . esc_html__( 'You have no tickets. Ask the help bot if you need a hand.', 'fan-ownership' ) . '</p>';
		}
		foreach ( $tickets as $t ) {
			$status = (string) get_post_meta( $t->ID, '_prx3_ticket_status', true );
			echo '<article class="prx3-card"><h3>' . esc_html( $t->post_title ) . ' <span class="prx3-badge">' . esc_html( $status ? $status : 'open' ) . '</span></h3>';
			echo '<p>' . esc_html( wp_trim_words( $t->post_content, 40 ) ) . '</p>';
			foreach ( get_comments(
				array(
					'post_id' => $t->ID,
					'orderby' => 'comment_date_gmt',
					'order'   => 'ASC',
				)
			) as $reply ) {
				$staff = get_comment_meta( $reply->comment_ID, '_prx3_staff', true );
				echo '<div class="prx3-bubble-row' . ( $staff ? '' : ' prx3-bubble-row--mine' ) . '"><div class="prx3-bubble ' . ( $staff ? 'prx3-bubble--theirs' : 'prx3-bubble--mine' ) . '">' . esc_html( $reply->comment_content ) . '</div></div>';
			}
			echo '</article>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/* ---------------- Admin: ticket replies ---------------- */

	/**
	 * A reply box + status control on the ticket edit screen.
	 */
	public static function ticket_meta_box() {
		add_meta_box(
			'prx3_ticket_thread',
			__( 'Conversation & reply', 'fan-ownership' ),
			function ( $post ) {
				$status = (string) get_post_meta( $post->ID, '_prx3_ticket_status', true );
				$member = get_userdata( (int) $post->post_author );
				echo '<p>' . esc_html( sprintf( /* translators: 1 member, 2 status. */ __( 'From: %1$s · Status: %2$s', 'fan-ownership' ), $member ? $member->display_name : '#' . (int) $post->post_author, $status ? $status : 'open' ) ) . '</p>';
				foreach ( get_comments(
					array(
						'post_id' => $post->ID,
						'orderby' => 'comment_date_gmt',
						'order'   => 'ASC',
					)
				) as $reply ) {
					$staff = get_comment_meta( $reply->comment_ID, '_prx3_staff', true );
					echo '<p style="padding:6px 10px;border-radius:8px;background:' . ( $staff ? '#e7f0ff' : '#f0f0f1' ) . ';"><strong>' . esc_html( $reply->comment_author ) . ':</strong> ' . esc_html( $reply->comment_content ) . '</p>';
				}
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( 'prx3_ticket_reply_' . $post->ID );
				echo '<input type="hidden" name="action" value="prx3_ticket_reply"><input type="hidden" name="ticket" value="' . (int) $post->ID . '">';
				echo '<p><textarea name="reply" class="widefat" rows="3" placeholder="' . esc_attr__( 'Reply to the member…', 'fan-ownership' ) . '"></textarea></p>';
				echo '<p><label><input type="checkbox" name="close" value="1"> ' . esc_html__( 'Mark resolved after sending', 'fan-ownership' ) . '</label></p>';
				echo '<p><button class="button button-primary">' . esc_html__( 'Send reply', 'fan-ownership' ) . '</button></p></form>';
			},
			'prx3_ticket',
			'normal',
			'high'
		);
	}

	/**
	 * Handle a staff reply from the ticket screen.
	 */
	public static function handle_ticket_reply() {
		$ticket = isset( $_POST['ticket'] ) ? absint( $_POST['ticket'] ) : 0;
		if ( ! current_user_can( 'prx3_admin' ) && ! current_user_can( 'prx3_governance' ) ) {
			wp_die( esc_html__( 'Staff only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_ticket_reply_' . $ticket );
		$body = isset( $_POST['reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reply'] ) ) : '';
		if ( '' !== $body ) {
			self::add_reply( $ticket, get_current_user_id(), $body, true );
		}
		if ( ! empty( $_POST['close'] ) ) {
			update_post_meta( $ticket, '_prx3_ticket_status', 'resolved' );
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . $ticket . '&action=edit' ) );
		exit;
	}
}
