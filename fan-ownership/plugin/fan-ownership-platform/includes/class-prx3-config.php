<?php
/**
 * Identity-as-configuration and feature kill switches.
 *
 * FO-103 (kill switches), FO-104 (club identity as configuration).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Platform configuration: default settings, the sport catalogue, and the
 * FO-103 feature kill switches (identity-as-configuration, FO-104).
 */
class PRX3_Config {

	const FEATURES = array( 'registration', 'checkout', 'voting', 'forum', 'chat', 'streams', 'meetings', 'ideas', 'questions' );

	/**
	 * Hook the kill-switch toggle handler.
	 */
	public static function init() {
		add_action( 'admin_post_prx3_toggle_feature', array( __CLASS__, 'handle_toggle' ) );
	}

	/**
	 * Default settings, used on first run.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'club_name'                          => 'Perth Panthers',
			'sport'                              => 'ice_hockey',
			// Brand pack (uploadable via the Media Library; values are attachment IDs).
			'brand_badge_id'                     => 0,
			'brand_badge_inverted_id'            => 0,
			'brand_badge_social_id'              => 0,
			'brand_badge_svg_id'                 => 0,
			'brand_wordmark_id'                  => 0,
			'brand_favicon_id'                   => 0,
			'brand_app_icon_id'                  => 0,
			'brand_email_header_id'              => 0,
			'brand_font_file_id'                 => 0,
			'brand_font_name'                    => '',
			'brand_usage_notes'                  => '',
			'brand_badge_mono_dark_id'           => 0,
			'brand_badge_mono_light_id'          => 0,
			'brand_secondary_font_name'          => '',
			'brand_social_handles'               => '',
			'brand_contact'                      => '',
			'club_tagline'                       => '',
			'club_mission'                       => '',
			'club_legal_name'                    => '',
			'club_short_name'                    => '',
			'club_abbreviation'                  => '',
			'club_founded'                       => '',
			'club_primary_print'                 => '',
			'club_secondary_print'               => '',
			'club_tertiary_print'                => '',
			'brand_kit_home_id'                  => 0,
			'brand_kit_away_id'                  => 0,
			'brand_kit_third_id'                 => 0,
			'brand_kit_notes'                    => '',
			'brand_kit_history_home_ids'         => '',
			'brand_kit_history_away_ids'         => '',
			'brand_photography_ids'              => '',
			'brand_photography_notes'            => '',
			'brand_dont_ids'                     => '',
			'brand_dont_notes'                   => '',
			'club_primary'                       => '#1a1a2e',
			'club_secondary'                     => '#ffffff',
			'club_tertiary'                      => '#e2b007',
			'club_accent'                        => '#e2b007',
			'currency_symbol'                    => '£',
			'ticketing_provider'                 => 'Fanbase',
			'sha_page_id'                        => 0,
			'sha_version'                        => '1.0',
			'terms_page_id'                      => 0,
			'commerce_provider'                  => 'shopify',
			'shopify_domain'                     => '',
			'shopify_webhook_secret'             => '',
			'shopify_share_variants'             => '',
			'data_api_enabled'                   => 0,
			'data_api_key'                       => '',
			'sync_enabled'                       => 0,
			'sync_api_key'                       => '',
			'sync_webhook_url'                   => '',
			'sync_webhook_secret'                => '',
			'sha_signatory_name'                 => '',
			'sha_signatory_role'                 => 'Director',
			'sha_signatory_signature_id'         => 0,
			'brand_club_stamp_id'                => 0,
			'account_page_id'                    => 0,
			'target_owners'                      => 1000,
			'target_revenue'                     => 0,
			'gift_cap_per_buyer'                 => 10,
			'gift_bulk_flag_at'                  => 5,
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
	 * Sport presets: the club picks a sport and the Match Centre speaks its
	 * language — event types, period structure, notification titles.
	 * Filterable (prx3_sports) so any sport can be added without code here.
	 *
	 * Event definition: key => [label, scoring(bool), push_title|null].
	 *
	 * @return array<string,array>
	 */
	public static function sports() {
		$sports = array(
			'ice_hockey' => array(
				'label'   => __( 'Ice hockey', 'fan-ownership' ),
				'start'   => __( 'Face-off', 'fan-ownership' ),
				'periods' => array( __( 'Period 1', 'fan-ownership' ), __( 'Period 2', 'fan-ownership' ), __( 'Period 3', 'fan-ownership' ), __( 'Overtime', 'fan-ownership' ), __( 'Shootout', 'fan-ownership' ) ),
				'events'  => array(
					'faceoff'          => array( __( 'Face-off', 'fan-ownership' ), false, __( 'Face-off — we are underway', 'fan-ownership' ) ),
					'goal'             => array( __( 'Goal', 'fan-ownership' ), true, __( 'GOAL!', 'fan-ownership' ) ),
					'assist'           => array( __( 'Assist', 'fan-ownership' ), false, null ),
					'penalty'          => array( __( 'Penalty (2 min)', 'fan-ownership' ), false, __( 'Penalty', 'fan-ownership' ) ),
					'major_penalty'    => array( __( 'Major penalty (5 min)', 'fan-ownership' ), false, __( 'Major penalty', 'fan-ownership' ) ),
					'powerplay_goal'   => array( __( 'Powerplay goal', 'fan-ownership' ), true, __( 'POWERPLAY GOAL!', 'fan-ownership' ) ),
					'shorthanded_goal' => array( __( 'Shorthanded goal', 'fan-ownership' ), true, __( 'SHORTHANDED GOAL!', 'fan-ownership' ) ),
					'period_end'       => array( __( 'End of period', 'fan-ownership' ), false, __( 'End of the period', 'fan-ownership' ) ),
					'overtime'         => array( __( 'Overtime', 'fan-ownership' ), false, __( 'Overtime!', 'fan-ownership' ) ),
					'shootout'         => array( __( 'Shootout', 'fan-ownership' ), false, __( 'Shootout!', 'fan-ownership' ) ),
					'timeout'          => array( __( 'Timeout', 'fan-ownership' ), false, null ),
					'full_time'        => array( __( 'Final buzzer', 'fan-ownership' ), false, __( 'Final score', 'fan-ownership' ) ),
					'note'             => array( __( 'Note', 'fan-ownership' ), false, null ),
				),
			),
			'football'   => array(
				'label'   => __( 'Football', 'fan-ownership' ),
				'start'   => __( 'Kick-off', 'fan-ownership' ),
				'periods' => array( __( 'First half', 'fan-ownership' ), __( 'Second half', 'fan-ownership' ), __( 'Extra time', 'fan-ownership' ), __( 'Penalties', 'fan-ownership' ) ),
				'events'  => array(
					'kickoff'     => array( __( 'Kick-off', 'fan-ownership' ), false, __( 'Kick-off', 'fan-ownership' ) ),
					'goal'        => array( __( 'Goal', 'fan-ownership' ), true, __( 'GOAL!', 'fan-ownership' ) ),
					'own_goal'    => array( __( 'Own goal', 'fan-ownership' ), true, __( 'Goal (OG)', 'fan-ownership' ) ),
					'card_yellow' => array( __( 'Yellow card', 'fan-ownership' ), false, null ),
					'card_red'    => array( __( 'Red card', 'fan-ownership' ), false, __( 'Red card', 'fan-ownership' ) ),
					'sub'         => array( __( 'Substitution', 'fan-ownership' ), false, null ),
					'half_time'   => array( __( 'Half-time', 'fan-ownership' ), false, __( 'Half-time', 'fan-ownership' ) ),
					'full_time'   => array( __( 'Full-time', 'fan-ownership' ), false, __( 'Full-time', 'fan-ownership' ) ),
					'note'        => array( __( 'Note', 'fan-ownership' ), false, null ),
				),
			),
			'rugby'      => array(
				'label'   => __( 'Rugby', 'fan-ownership' ),
				'start'   => __( 'Kick-off', 'fan-ownership' ),
				'periods' => array( __( 'First half', 'fan-ownership' ), __( 'Second half', 'fan-ownership' ) ),
				'events'  => array(
					'kickoff'      => array( __( 'Kick-off', 'fan-ownership' ), false, __( 'Kick-off', 'fan-ownership' ) ),
					'try'          => array( __( 'Try', 'fan-ownership' ), true, __( 'TRY!', 'fan-ownership' ) ),
					'conversion'   => array( __( 'Conversion', 'fan-ownership' ), true, null ),
					'penalty_kick' => array( __( 'Penalty kick', 'fan-ownership' ), true, null ),
					'drop_goal'    => array( __( 'Drop goal', 'fan-ownership' ), true, __( 'Drop goal!', 'fan-ownership' ) ),
					'card_yellow'  => array( __( 'Yellow card', 'fan-ownership' ), false, null ),
					'card_red'     => array( __( 'Red card', 'fan-ownership' ), false, __( 'Red card', 'fan-ownership' ) ),
					'half_time'    => array( __( 'Half-time', 'fan-ownership' ), false, __( 'Half-time', 'fan-ownership' ) ),
					'full_time'    => array( __( 'Full-time', 'fan-ownership' ), false, __( 'Full-time', 'fan-ownership' ) ),
					'note'         => array( __( 'Note', 'fan-ownership' ), false, null ),
				),
			),
			'basketball' => array(
				'label'   => __( 'Basketball', 'fan-ownership' ),
				'start'   => __( 'Tip-off', 'fan-ownership' ),
				'periods' => array( __( 'Q1', 'fan-ownership' ), __( 'Q2', 'fan-ownership' ), __( 'Q3', 'fan-ownership' ), __( 'Q4', 'fan-ownership' ), __( 'Overtime', 'fan-ownership' ) ),
				'events'  => array(
					'tipoff'     => array( __( 'Tip-off', 'fan-ownership' ), false, __( 'Tip-off', 'fan-ownership' ) ),
					'score'      => array( __( 'Score update', 'fan-ownership' ), true, null ),
					'three'      => array( __( 'Three-pointer', 'fan-ownership' ), true, null ),
					'foul'       => array( __( 'Foul', 'fan-ownership' ), false, null ),
					'timeout'    => array( __( 'Timeout', 'fan-ownership' ), false, null ),
					'period_end' => array( __( 'End of quarter', 'fan-ownership' ), false, null ),
					'full_time'  => array( __( 'Final buzzer', 'fan-ownership' ), false, __( 'Final score', 'fan-ownership' ) ),
					'note'       => array( __( 'Note', 'fan-ownership' ), false, null ),
				),
			),
			'generic'    => array(
				'label'   => __( 'Other sport (generic)', 'fan-ownership' ),
				'start'   => __( 'Start', 'fan-ownership' ),
				'periods' => array( __( 'Period 1', 'fan-ownership' ), __( 'Period 2', 'fan-ownership' ) ),
				'events'  => array(
					'start'      => array( __( 'Start', 'fan-ownership' ), false, __( 'We are underway', 'fan-ownership' ) ),
					'score'      => array( __( 'Score', 'fan-ownership' ), true, __( 'Score!', 'fan-ownership' ) ),
					'incident'   => array( __( 'Incident', 'fan-ownership' ), false, null ),
					'period_end' => array( __( 'End of period', 'fan-ownership' ), false, null ),
					'full_time'  => array( __( 'Full time', 'fan-ownership' ), false, __( 'Final score', 'fan-ownership' ) ),
					'note'       => array( __( 'Note', 'fan-ownership' ), false, null ),
				),
			),
		);
		return apply_filters( 'prx3_sports', $sports );
	}

	/**
	 * The active sport's preset.
	 *
	 * @return array
	 */
	public static function sport() {
		$sports = self::sports();
		$key    = prx3_setting( 'sport', 'ice_hockey' );
		return isset( $sports[ $key ] ) ? $sports[ $key ] : $sports['generic'];
	}

	/**
	 * Toggle a feature kill switch (Owner-Admins only), audited.
	 */
	public static function handle_toggle() {
		if ( ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_toggle_feature' );

		$feature = isset( $_POST['feature'] ) ? sanitize_key( $_POST['feature'] ) : '';
		$reason  = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		if ( ! in_array( $feature, self::FEATURES, true ) ) {
			wp_die( esc_html__( 'Unknown feature.', 'fan-ownership' ) );
		}

		$switches             = get_option( 'prx3_kill_switches', array() );
		$turning_off          = empty( $switches[ $feature ]['off'] );
		$switches[ $feature ] = array(
			'off'    => $turning_off,
			'by'     => get_current_user_id(),
			'at'     => time(),
			'reason' => $reason,
		);
		update_option( 'prx3_kill_switches', $switches );

		PRX3_Audit::log(
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
		return '<div class="prx3-notice prx3-notice--unavailable" role="status">'
			. esc_html(
				sprintf(
					/* translators: 1: feature label, 2: club name. */
					__( 'This part of the %2$s portal is temporarily unavailable. Please check back shortly.', 'fan-ownership' ),
					$feature,
					prx3_club_name()
				)
			)
			. '</div>';
	}
}
