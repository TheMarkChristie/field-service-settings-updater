<?php
/**
 * Communications: one branded master template, category preferences,
 * push registration for the apps.
 *
 * FO-116, T65, T72, FO-303 (server side). Actual SMTP/campaign delivery
 * is Brevo's job (site-level SMTP plugin); this class shapes and gates
 * what the platform itself sends.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Comms {

	const CATEGORIES = array( 'governance', 'match', 'content', 'meetings', 'news' );

	public static function init() {
		add_action( 'admin_post_prx3_save_prefs', array( __CLASS__, 'save_prefs' ) );
		add_action( 'admin_post_nopriv_prx3_save_prefs', '__return_false' );
	}

	/**
	 * Send an email through the branded master template, honouring
	 * category preferences. Governance-critical notices always send
	 * (FO-116 AC3).
	 *
	 * @param string $to       Recipient email.
	 * @param string $subject  Subject.
	 * @param string $body     Plain-text body (auto-wrapped).
	 * @param string $category One of CATEGORIES.
	 * @return bool
	 */
	public static function send( $to, $subject, $body, $category = 'news' ) {
		$user = get_user_by( 'email', $to );
		if ( $user && 'governance' !== $category && ! self::user_wants( $user->ID, $category, 'email' ) ) {
			return false;
		}
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		return wp_mail( $to, $subject, self::wrap( $subject, $body, $category ), $headers );
	}

	/**
	 * The single branded template (FO-116 AC1 / T72), identity from config.
	 */
	private static function wrap( $subject, $body, $category ) {
		$club    = prx3_club_name();
		$primary = prx3_setting( 'club_primary', '#1a1a2e' );
		$prefs   = esc_url( home_url( '/?prx3_prefs=1' ) );
		$unsub   = 'governance' === $category ? '' :
			'<p style="font-size:12px;color:#777;">' . esc_html__( 'Choose which emails you receive:', 'fan-ownership' )
			. ' <a href="' . $prefs . '">' . esc_html__( 'preferences', 'fan-ownership' ) . '</a></p>';
		// Brand pack: uploaded email header wins; else inverted badge beside
		// the club name on the primary colour; else name alone.
		$header_image = prx3_brand_asset( 'email_header' );
		$badge        = prx3_brand_asset( 'badge_inverted' );
		if ( $header_image ) {
			$header = '<tr><td style="background:' . esc_attr( $primary ) . ';"><img src="' . esc_url( $header_image ) . '" width="600" style="display:block;width:100%;height:auto;" alt="' . esc_attr( $club ) . '"></td></tr>';
		} else {
			$badge_html = $badge ? '<img src="' . esc_url( $badge ) . '" height="36" style="vertical-align:middle;margin-right:12px;" alt="">' : '';
			$header     = '<tr><td style="background:' . esc_attr( $primary ) . ';color:#fff;padding:20px 28px;font-size:20px;font-weight:700;">' . $badge_html . esc_html( $club ) . '</td></tr>';
		}
		return '<!doctype html><html><body style="margin:0;background:#f4f4f6;font-family:system-ui,Arial,sans-serif;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px;">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;">'
			. $header
			. '<tr><td style="padding:28px;font-size:15px;line-height:1.6;color:#1a1a2e;">' . wp_kses_post( nl2br( esc_html( $body ) ) ) . '</td></tr>'
			. '<tr><td style="padding:0 28px 24px;">' . $unsub . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	/**
	 * Preference check for one channel. Quorum/governance reminders default
	 * on (T65); everything else defaults on until switched off.
	 *
	 * @param int    $user_id  Member.
	 * @param string $category Category.
	 * @param string $channel  'email'|'push'.
	 * @return bool
	 */
	public static function user_wants( $user_id, $category, $channel ) {
		$prefs = get_user_meta( $user_id, 'prx3_comms_prefs', true );
		if ( ! is_array( $prefs ) || ! isset( $prefs[ $channel ][ $category ] ) ) {
			return true;
		}
		return (bool) $prefs[ $channel ][ $category ];
	}

	/**
	 * Save preferences (one centre for email + push, FO-303 AC1).
	 */
	public static function save_prefs() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please sign in.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_save_prefs' );
		$prefs = array(
			'email' => array(),
			'push'  => array(),
		);
		foreach ( self::CATEGORIES as $cat ) {
			$prefs['email'][ $cat ] = ! empty( $_POST[ 'email_' . $cat ] );
			$prefs['push'][ $cat ]  = ! empty( $_POST[ 'push_' . $cat ] );
		}
		update_user_meta( get_current_user_id(), 'prx3_comms_prefs', $prefs );
		update_user_meta(
			get_current_user_id(),
			'prx3_prefs_consent_log',
			array_merge(
				(array) get_user_meta( get_current_user_id(), 'prx3_prefs_consent_log', true ),
				array(
					array(
						'at'    => time(),
						'prefs' => $prefs,
					),
				)
			)
		);
		wp_safe_redirect( add_query_arg( 'prx3_saved', 1, wp_get_referer() ? wp_get_referer() : home_url() ) );
		exit;
	}

	/**
	 * Push fan-out to registered app devices via FCM. Device tokens are
	 * registered through the app API; delivery uses the configured FCM
	 * credentials (T14).
	 *
	 * @param int[]  $user_ids Recipients (empty = all owners).
	 * @param string $title    Title.
	 * @param string $body     Body.
	 * @param string $category Category (preference-gated except governance).
	 * @param string $deeplink Universal link target (T66).
	 */
	public static function push( $user_ids, $title, $body, $category, $deeplink = '' ) {
		$server_key = prx3_setting( 'fcm_server_key', '' );
		$user_ids   = $user_ids ? $user_ids : get_users(
			array(
				'role'   => 'fan_owner',
				'fields' => 'ID',
				'number' => -1,
			)
		);
		$queued     = 0;
		foreach ( $user_ids as $uid ) {
			if ( 'governance' !== $category && ! self::user_wants( $uid, $category, 'push' ) ) {
				continue;
			}
			$tokens = (array) get_user_meta( $uid, 'prx3_push_tokens', true );
			foreach ( array_filter( $tokens ) as $token ) {
				self::fcm_send( $server_key, $token, $title, $body, $deeplink );
				++$queued;
			}
		}
		return $queued;
	}

	private static function fcm_send( $server_key, $token, $title, $body, $deeplink ) {
		if ( ! $server_key ) {
			return; // Not configured yet; pushes silently no-op in dev.
		}
		wp_remote_post(
			'https://fcm.googleapis.com/fcm/send',
			array(
				'timeout' => 5,
				'headers' => array(
					'Authorization' => 'key=' . $server_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'to'           => $token,
						'notification' => array(
							'title' => $title,
							'body'  => $body,
						),
						'data'         => array( 'deeplink' => $deeplink ),
					)
				),
			)
		);
	}
}
