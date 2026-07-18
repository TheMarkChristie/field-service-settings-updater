<?php
/**
 * Moderation queue and the sanctions ladder: warn -> mute -> expel.
 *
 * FO-221, P38. Expulsion surrenders shares and needs Owner-Admin
 * confirmation. All actions audited and visible to board oversight.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs the moderation queue and applies the warn -> mute -> expel
 * sanctions ladder, with auditing on every action.
 */
class PRX3_Moderation {

	/**
	 * Hook up the moderation action handler.
	 */
	public static function init() {
		add_action( 'admin_post_prx3_moderate', array( __CLASS__, 'handle_action' ) );
	}

	/**
	 * Add an item to the queue (pending posts register on submission;
	 * reports register here too).
	 *
	 * @param int    $object_id Post or message ID being queued.
	 * @param string $kind      Object kind, e.g. 'idea', 'question', 'chat'.
	 * @param int    $reporter  Reporting member ID (0 for system entries).
	 * @param string $note      Optional reporter note.
	 */
	public static function enqueue( $object_id, $kind, $reporter = 0, $note = '' ) {
		$queue   = get_option( 'prx3_mod_queue', array() );
		$queue[] = array(
			'object'   => (int) $object_id,
			'kind'     => sanitize_key( $kind ),
			'reporter' => (int) $reporter,
			'note'     => sanitize_text_field( $note ),
			'at'       => time(),
			'resolved' => 0,
		);
		update_option( 'prx3_mod_queue', $queue, false );
	}

	/**
	 * Member report from any surface (FO-220 AC4).
	 *
	 * @param int    $object_id Post or message ID being reported.
	 * @param string $kind      Object kind, e.g. 'idea', 'question', 'chat'.
	 * @param int    $reporter  Reporting member ID.
	 * @param string $note      Reporter note.
	 * @return true|WP_Error
	 */
	public static function report( $object_id, $kind, $reporter, $note ) {
		if ( ! prx3_is_owner( $reporter ) ) {
			return new WP_Error( 'prx3_owner_only', __( 'Only owners can report content.', 'fan-ownership' ) );
		}
		self::enqueue( $object_id, $kind, $reporter, $note );
		return true;
	}

	/* ---------------- Sanctions ---------------- */

	/**
	 * Whether a member's posting rights are currently suspended.
	 *
	 * @param int $user_id Member ID.
	 * @return bool
	 */
	public static function is_muted( $user_id ) {
		$until = (int) get_user_meta( $user_id, 'prx3_muted_until', true );
		return $until > time();
	}

	/**
	 * Message shown to a muted member wherever posting is blocked.
	 *
	 * @param int $user_id Member ID.
	 * @return string
	 */
	public static function mute_message( $user_id ) {
		return sprintf(
			/* translators: %s date. */
			__( 'Your posting rights are suspended until %s under the code of conduct. Your voting and viewing rights are unaffected.', 'fan-ownership' ),
			date_i18n( get_option( 'date_format' ), (int) get_user_meta( $user_id, 'prx3_muted_until', true ) )
		);
	}

	/**
	 * Apply a sanction step. Warn and mute: moderators. Expel: requires
	 * prx3_admin confirmation (FO-221 AC2).
	 *
	 * @param int    $target  Member.
	 * @param string $step    'warn'|'mute'|'expel'.
	 * @param string $reason  Stated reason (sent to the member).
	 * @param int    $days    Mute length.
	 * @return true|WP_Error
	 */
	public static function sanction( $target, $step, $reason, $days = 7 ) {
		$actor_can = 'expel' === $step ? current_user_can( 'prx3_admin' ) : ( current_user_can( 'prx3_moderate' ) || current_user_can( 'prx3_admin' ) );
		if ( ! $actor_can ) {
			return new WP_Error( 'prx3_denied', __( 'You are not allowed to apply that sanction.', 'fan-ownership' ) );
		}
		if ( ! $reason ) {
			return new WP_Error( 'prx3_reason', __( 'A stated reason is required for every sanction.', 'fan-ownership' ) );
		}
		$user = get_userdata( $target );
		if ( ! $user ) {
			return new WP_Error( 'prx3_user', __( 'Member not found.', 'fan-ownership' ) );
		}
		$history   = (array) get_user_meta( $target, 'prx3_sanctions', true );
		$history[] = array(
			'step'   => $step,
			'reason' => $reason,
			'by'     => get_current_user_id(),
			'at'     => time(),
			'days'   => $days,
		);
		update_user_meta( $target, 'prx3_sanctions', $history );

		switch ( $step ) {
			case 'warn':
				PRX3_Comms::send(
					$user->user_email,
					__( 'Code of conduct warning', 'fan-ownership' ),
					sprintf(
					/* translators: %s reason. */
						__( "This is a formal warning under the club's code of conduct.\n\nReason: %s\n\nA further breach may suspend your posting rights.", 'fan-ownership' ),
						$reason
					),
					'governance'
				);
				break;
			case 'mute':
				update_user_meta( $target, 'prx3_muted_until', time() + $days * DAY_IN_SECONDS );
				PRX3_Comms::send(
					$user->user_email,
					__( 'Posting rights suspended', 'fan-ownership' ),
					sprintf(
					/* translators: 1: days, 2: reason. */
						__( "Your posting and submission rights are suspended for %1\$d days under the code of conduct.\n\nReason: %2\$s\n\nYour voting and viewing rights are unaffected.", 'fan-ownership' ),
						$days,
						$reason
					),
					'governance'
				);
				break;
			case 'expel':
				$held     = PRX3_Shares::surrender_all( $target, 'expelled', array( 'reason' => $reason ) );
				$sessions = WP_Session_Tokens::get_instance( $target );
				$sessions->destroy_all();
				$user->set_role( '' );
				PRX3_Comms::send(
					$user->user_email,
					__( 'Membership ended', 'fan-ownership' ),
					sprintf(
					/* translators: 1: reason, 2: shares. */
						__( "Your membership has been ended under the code of conduct and your %2\$d share(s) surrendered to the club, as the terms of membership provide.\n\nReason: %1\$s", 'fan-ownership' ),
						$reason,
						$held
					),
					'governance'
				);
				break;
			default:
				return new WP_Error( 'prx3_step', __( 'Unknown sanction.', 'fan-ownership' ) );
		}
		PRX3_Audit::log( 'sanction_' . $step, sprintf( 'Member %d: %s', $target, $reason ) );
		return true;
	}

	/**
	 * Admin-post handler for the moderation screen's sanction form.
	 */
	public static function handle_action() {
		check_admin_referer( 'prx3_moderate' );
		$target = isset( $_POST['target'] ) ? absint( $_POST['target'] ) : 0;
		$step   = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : '';
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		$days   = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 7;
		$result = self::sanction( $target, $step, $reason, $days );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
