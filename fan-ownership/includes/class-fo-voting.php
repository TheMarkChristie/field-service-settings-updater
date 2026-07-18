<?php
/**
 * Voting: one vote per share owned, secret until the ballot closes.
 *
 * @package FanOwnership
 */

defined( 'ABSPATH' ) || exit;

class FO_Voting {

	public static function init() {
		add_action( 'wp_ajax_fo_cast_vote', array( __CLASS__, 'ajax_cast_vote' ) );
	}

	/**
	 * Tallies per option index (share-weighted).
	 *
	 * @param int $vote_id Vote post ID.
	 * @return array<int,int>
	 */
	public static function get_counts( $vote_id ) {
		$counts = get_post_meta( $vote_id, '_fo_vote_counts', true );
		return is_array( $counts ) ? array_map( 'intval', $counts ) : array();
	}

	/**
	 * Which option (index) did a user pick? Null if they haven't voted.
	 *
	 * @param int $vote_id Vote post ID.
	 * @param int $user_id User ID.
	 * @return int|null
	 */
	public static function get_user_choice( $vote_id, $user_id ) {
		$voters = get_post_meta( $vote_id, '_fo_vote_voters', true );
		return is_array( $voters ) && isset( $voters[ $user_id ] ) ? (int) $voters[ $user_id ]['choice'] : null;
	}

	/**
	 * Is the ballot still open?
	 *
	 * @param int $vote_id Vote post ID.
	 * @return bool
	 */
	public static function is_open( $vote_id ) {
		if ( get_post_status( $vote_id ) !== 'publish' ) {
			return false;
		}
		$closes = get_post_meta( $vote_id, '_fo_vote_closes', true );
		if ( ! $closes ) {
			return true;
		}
		return strtotime( $closes ) > strtotime( fo_now() );
	}

	/**
	 * May the current user see the tallies? Members only after close; managers always.
	 *
	 * @param int $vote_id Vote post ID.
	 * @return bool
	 */
	public static function can_see_results( $vote_id ) {
		return current_user_can( 'fo_manage' ) || ! self::is_open( $vote_id );
	}

	/**
	 * AJAX: record a member's ballot, weighted by shares owned.
	 */
	public static function ajax_cast_vote() {
		check_ajax_referer( 'fo_ajax', 'nonce' );

		if ( ! fo_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Only fan owners can vote.', 'fan-ownership' ) ), 403 );
		}

		$vote_id = isset( $_POST['vote_id'] ) ? absint( $_POST['vote_id'] ) : 0;
		$choice  = isset( $_POST['choice'] ) ? absint( $_POST['choice'] ) : -1;
		$options = get_post_meta( $vote_id, '_fo_vote_options', true );

		if ( ! $vote_id || get_post_type( $vote_id ) !== 'fo_vote' || ! is_array( $options ) || ! isset( $options[ $choice ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ballot.', 'fan-ownership' ) ), 400 );
		}
		if ( ! self::is_open( $vote_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This ballot has closed.', 'fan-ownership' ) ), 400 );
		}

		$user_id = get_current_user_id();
		$voters  = get_post_meta( $vote_id, '_fo_vote_voters', true );
		$voters  = is_array( $voters ) ? $voters : array();
		if ( isset( $voters[ $user_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'You have already voted on this ballot.', 'fan-ownership' ) ), 400 );
		}

		$weight = fo_get_shares( $user_id );
		$counts = self::get_counts( $vote_id );

		$counts[ $choice ]  = ( isset( $counts[ $choice ] ) ? $counts[ $choice ] : 0 ) + $weight;
		$voters[ $user_id ] = array(
			'choice' => $choice,
			'weight' => $weight,
			'time'   => fo_now(),
		);
		update_post_meta( $vote_id, '_fo_vote_counts', $counts );
		update_post_meta( $vote_id, '_fo_vote_voters', $voters );

		$message = sprintf(
			/* translators: %d: number of share votes cast. */
			_n( 'Thanks — your %d vote has been recorded.', 'Thanks — your %d votes have been recorded.', $weight, 'fan-ownership' ),
			$weight
		);

		wp_send_json_success(
			array(
				'message' => $message,
				'html'    => self::can_see_results( $vote_id )
					? self::render_results( $vote_id )
					: self::render_secret_notice( $vote_id ),
			)
		);
	}

	/**
	 * Notice shown in place of results while a secret ballot is open.
	 *
	 * @param int $vote_id Vote post ID.
	 * @return string HTML.
	 */
	public static function render_secret_notice( $vote_id ) {
		$closes = get_post_meta( $vote_id, '_fo_vote_closes', true );
		$when   = $closes
			? sprintf( /* translators: %s: closing date/time. */ __( 'Results will be revealed when voting closes on %s.', 'fan-ownership' ), fo_format_datetime( $closes ) )
			: __( 'Results will be revealed when the club closes the ballot.', 'fan-ownership' );
		return '<p class="fo-ballot__secret">' . esc_html( __( 'This is a secret ballot.', 'fan-ownership' ) . ' ' . $when ) . '</p>';
	}

	/**
	 * Accessible results bars for a ballot.
	 *
	 * @param int $vote_id Vote post ID.
	 * @return string HTML.
	 */
	public static function render_results( $vote_id ) {
		$options = get_post_meta( $vote_id, '_fo_vote_options', true );
		if ( ! is_array( $options ) || ! $options ) {
			return '';
		}
		$counts  = self::get_counts( $vote_id );
		$total   = array_sum( $counts );
		$voters  = get_post_meta( $vote_id, '_fo_vote_voters', true );
		$turnout = is_array( $voters ) ? count( $voters ) : 0;
		$choice  = self::get_user_choice( $vote_id, get_current_user_id() );
		$winner  = $total ? max( $counts ) : 0;

		$html = '<ul class="fo-results">';
		foreach ( $options as $i => $option ) {
			$count   = isset( $counts[ $i ] ) ? $counts[ $i ] : 0;
			$percent = $total ? round( $count / $total * 100 ) : 0;
			$is_top  = $total && $count === $winner;
			$mine    = ( $choice === $i ) ? ' <span class="fo-results__mine">' . esc_html__( '(your vote)', 'fan-ownership' ) . '</span>' : '';
			$html   .= sprintf(
				'<li class="fo-results__row%6$s"><span class="fo-results__label">%1$s%2$s</span><span class="fo-results__bar" role="img" aria-label="%3$s"><span class="fo-results__fill" style="width:%4$d%%"></span></span><span class="fo-results__value">%5$s (%4$d%%)</span></li>',
				esc_html( $option ),
				$mine,
				/* translators: 1: option label, 2: vote count, 3: percentage. */
				esc_attr( sprintf( __( '%1$s: %2$d votes, %3$d percent', 'fan-ownership' ), $option, $count, $percent ) ),
				(int) $percent,
				esc_html( sprintf( /* translators: %d: vote count. */ _n( '%d vote', '%d votes', $count, 'fan-ownership' ), $count ) ),
				$is_top ? ' fo-results__row--leading' : ''
			);
		}
		$html .= '</ul>';
		$html .= '<p class="fo-results__total">' . esc_html(
			sprintf(
				/* translators: 1: total share votes, 2: number of members who voted. */
				__( '%1$d votes cast by %2$d members (votes are weighted by shares owned).', 'fan-ownership' ),
				$total,
				$turnout
			)
		) . '</p>';
		if ( current_user_can( 'fo_manage' ) && self::is_open( $vote_id ) ) {
			$html .= '<p class="fo-results__admin-note">' . esc_html__( 'Only club administrators can see these tallies while voting is open.', 'fan-ownership' ) . '</p>';
		}
		return $html;
	}

	/**
	 * The full ballot card: form if the member can still vote, otherwise
	 * the secret-ballot notice or the results.
	 *
	 * @param int $vote_id Vote post ID.
	 * @return string HTML.
	 */
	public static function render_ballot( $vote_id ) {
		$options = get_post_meta( $vote_id, '_fo_vote_options', true );
		if ( ! is_array( $options ) || ! $options ) {
			return '<p>' . esc_html__( 'This ballot has no options yet.', 'fan-ownership' ) . '</p>';
		}

		$type   = get_post_meta( $vote_id, '_fo_vote_type', true );
		$closes = get_post_meta( $vote_id, '_fo_vote_closes', true );
		$open   = self::is_open( $vote_id );
		$voted  = null !== self::get_user_choice( $vote_id, get_current_user_id() );
		$shares = fo_get_shares( get_current_user_id() );

		$badge = 'decision' === $type
			? '<span class="fo-badge fo-badge--decision">' . esc_html__( 'Club decision', 'fan-ownership' ) . '</span>'
			: '<span class="fo-badge">' . esc_html__( 'Fan poll', 'fan-ownership' ) . '</span>';

		$status = $open
			? ( $closes ? sprintf( /* translators: %s: closing date/time. */ esc_html__( 'Voting closes %s', 'fan-ownership' ), esc_html( fo_format_datetime( $closes ) ) ) : esc_html__( 'Voting is open', 'fan-ownership' ) )
			: esc_html__( 'Voting has closed', 'fan-ownership' );

		$html  = '<div class="fo-ballot" id="fo-ballot-' . (int) $vote_id . '">';
		$html .= '<p class="fo-ballot__meta">' . $badge . ' <span class="fo-ballot__status">' . $status . '</span></p>';

		if ( $open && ! $voted ) {
			$html .= '<form class="fo-ballot__form" data-fo-vote="' . (int) $vote_id . '">';
			$html .= '<fieldset><legend class="screen-reader-text">' . esc_html( get_the_title( $vote_id ) ) . '</legend>';
			foreach ( $options as $i => $option ) {
				$field_id = 'fo-vote-' . (int) $vote_id . '-' . (int) $i;
				$html    .= sprintf(
					'<p class="fo-ballot__option"><input type="radio" id="%1$s" name="fo_choice" value="%2$d" required> <label for="%1$s">%3$s</label></p>',
					esc_attr( $field_id ),
					(int) $i,
					esc_html( $option )
				);
			}
			$html .= '</fieldset>';
			$html .= '<button type="submit" class="fo-button">' . esc_html(
				sprintf(
					/* translators: %d: number of share votes the member holds. */
					_n( 'Cast my %d vote', 'Cast my %d votes', $shares, 'fan-ownership' ),
					$shares
				)
			) . '</button>';
			$html .= '<p class="fo-ballot__shares-note">' . esc_html(
				sprintf(
					/* translators: %d: shares owned. */
					_n( 'You own %d share, so your ballot counts as 1 vote. This is a secret ballot.', 'You own %d shares, so your ballot counts as %d votes. This is a secret ballot.', $shares, 'fan-ownership' ),
					$shares,
					$shares
				)
			) . '</p>';
			$html .= '<p class="fo-feedback" role="status" aria-live="polite"></p>';
			$html .= '</form>';
			$html .= '<div class="fo-ballot__results" hidden></div>';
			if ( current_user_can( 'fo_manage' ) ) {
				$html .= '<details class="fo-ballot__admin"><summary>' . esc_html__( 'Running tally (admins only)', 'fan-ownership' ) . '</summary>' . self::render_results( $vote_id ) . '</details>';
			}
		} elseif ( $open && $voted ) {
			$html .= '<p class="fo-ballot__voted">' . esc_html__( 'Your ballot is in.', 'fan-ownership' ) . '</p>';
			$html .= self::can_see_results( $vote_id ) ? self::render_results( $vote_id ) : self::render_secret_notice( $vote_id );
		} else {
			$html .= self::render_results( $vote_id );
		}
		$html .= '</div>';
		return $html;
	}
}
