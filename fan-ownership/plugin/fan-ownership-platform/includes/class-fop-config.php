<?php
/**
 * Identity-as-configuration and feature kill switches.
 *
 * FO-103 (kill switches), FO-104 (club identity as configuration).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class FOP_Config {

	const FEATURES = array( 'registration', 'checkout', 'voting', 'forum', 'chat', 'streams', 'meetings', 'ideas', 'questions' );

	public static function init() {
		add_action( 'admin_post_fop_toggle_feature', array( __CLASS__, 'handle_toggle' ) );
	}

	/**
	 * Default settings, used on first run.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'club_name'                          => 'Perth Panthers',
			'club_crest_id'                      => 0,
			'club_primary'                       => '#1a1a2e',
			'club_accent'                        => '#e2b007',
			'currency_symbol'                    => '£',
			'share_base_price'                   => 50.0,
			'share_tier_growth'                  => 0.25,
			'max_shares'                         => 10,
			'launch_moment'                      => '', // Founders cutoff (P56): ISO datetime of public launch.
			'quorum_percent'                     => 25,
			'constitutional_pct'                 => 75,
			'idea_threshold_pct'                 => 5,
			'ballot_window_days'                 => 7,
			'max_live_ballots'                   => 2,
			'min_age_confirm'                    => 18,
			'active_window_months'               => 12,
			'matchday_ticket_discount_per_share' => 5,
			'season_ticket_discount_per_share'   => 10,
		);
	}

	/**
	 * Toggle a feature kill switch (Owner-Admins only), audited.
	 */
	public static function handle_toggle() {
		if ( ! current_user_can( 'fop_admin' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'fan-ownership' ) );
		}
		check_admin_referer( 'fop_toggle_feature' );

		$feature = isset( $_POST['feature'] ) ? sanitize_key( $_POST['feature'] ) : '';
		$reason  = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		if ( ! in_array( $feature, self::FEATURES, true ) ) {
			wp_die( esc_html__( 'Unknown feature.', 'fan-ownership' ) );
		}

		$switches             = get_option( 'fop_kill_switches', array() );
		$turning_off          = empty( $switches[ $feature ]['off'] );
		$switches[ $feature ] = array(
			'off'    => $turning_off,
			'by'     => get_current_user_id(),
			'at'     => time(),
			'reason' => $reason,
		);
		update_option( 'fop_kill_switches', $switches );

		FOP_Audit::log(
			'kill_switch',
			sprintf( '%s turned %s', $feature, $turning_off ? 'OFF' : 'ON' ),
			array( 'reason' => $reason )
		);

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Member-facing notice when a feature is switched off (FO-103 AC2).
	 *
	 * @param string $feature Feature key.
	 * @return string HTML.
	 */
	public static function unavailable_notice( $feature ) {
		return '<div class="fop-notice fop-notice--unavailable" role="status">'
			. esc_html(
				sprintf(
					/* translators: 1: feature label, 2: club name. */
					__( 'This part of the %2$s portal is temporarily unavailable. Please check back shortly.', 'fan-ownership' ),
					$feature,
					fop_club_name()
				)
			)
			. '</div>';
	}
}
