<?php
/**
 * The board layer: directory, structured actions (casting votes,
 * recommendations, reserved matters), the private workspace, internal
 * votes with conflicts and recusal, observers, the vault, departures.
 *
 * FO-224 to FO-229; P78–P89.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * The board layer: workspace menu, structured actions, internal votes
 * with conflicts and recusal, selective release to the register,
 * observers, and the watermarked vault.
 */
class PRX3_Board {

	/**
	 * Wire the workspace menu, action handlers, conflicts profile
	 * fields, and vault rendering.
	 */
	public static function init() {
		add_action( 'admin_post_prx3_annual_draft', array( __CLASS__, 'handle_annual_draft' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_prx3_board_action', array( __CLASS__, 'handle_structured_action' ) );
		add_action( 'admin_post_prx3_board_vote', array( __CLASS__, 'handle_internal_vote' ) );
		add_action( 'admin_post_prx3_board_release', array( __CLASS__, 'handle_release' ) );
		add_action( 'admin_post_prx3_board_observer', array( __CLASS__, 'handle_observer_grant' ) );
		add_action( 'admin_post_prx3_board_moderate', array( __CLASS__, 'handle_owner_moderate' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'conflicts_profile' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'conflicts_profile' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_conflicts' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_conflicts' ) );
		add_action( 'template_redirect', array( __CLASS__, 'watermark_vault_view' ) );
	}

	/**
	 * Register the Board Workspace admin menu (board members only).
	 */
	public static function menu() {
		add_menu_page(
			__( 'FanPress Board', 'fan-ownership' ),
			__( 'FanPress Board', 'fan-ownership' ),
			'prx3_view_tally',
			'prx3-board',
			array( __CLASS__, 'render_workspace_home' ),
			'dashicons-shield',
			3.2
		);
		// The board's working registers that live on standard screens.
		add_submenu_page(
			'prx3-board',
			__( 'Decision Register', 'fan-ownership' ),
			__( 'Decision Register', 'fan-ownership' ),
			'edit_posts',
			'edit.php?post_type=prx3_decision'
		);
		add_submenu_page(
			'prx3-board',
			__( 'Live Q&A Presenter', 'fan-ownership' ),
			__( 'Live Q&A', 'fan-ownership' ),
			'prx3_view_tally',
			'prx3-presenter',
			array( __CLASS__, 'render_presenter' )
		);
		add_submenu_page(
			'prx3-board',
			__( 'Owner Management', 'fan-ownership' ),
			__( 'Owner Management', 'fan-ownership' ),
			current_user_can( 'prx3_board' ) ? 'prx3_board' : 'prx3_admin',
			'prx3-board-owners',
			array( __CLASS__, 'render_owner_management' )
		);
		add_submenu_page(
			'prx3-board',
			__( 'Identity Lookup', 'fan-ownership' ),
			__( 'Identity Lookup', 'fan-ownership' ),
			current_user_can( 'prx3_board' ) ? 'prx3_board' : 'prx3_admin',
			'prx3-board-identity',
			array( __CLASS__, 'render_identity_lookup' )
		);
		add_submenu_page(
			'prx3-board',
			__( 'Annual Report', 'fan-ownership' ),
			__( 'Annual Report', 'fan-ownership' ),
			'prx3_governance',
			'prx3-annual-report',
			array( __CLASS__, 'render_annual_report' )
		);
	}

	/* ---------------- Structured board actions (FO-225) ---------------- */

	/**
	 * Open a pending board action (casting vote, failed quorum, reserved
	 * matter). Called by the ballot lifecycle and admin screens.
	 *
	 * @param string $type      Action type key (e.g. 'casting_vote').
	 * @param int    $ballot_id Related ballot post ID.
	 * @param string $summary   Plain-text summary shown to directors.
	 */
	public static function open_action( $type, $ballot_id, $summary ) {
		$actions   = get_option( 'prx3_board_actions', array() );
		$actions[] = array(
			'type'    => sanitize_key( $type ),
			'ballot'  => (int) $ballot_id,
			'summary' => sanitize_text_field( $summary ),
			'opened'  => time(),
			'done'    => 0,
		);
		update_option( 'prx3_board_actions', $actions, false );
		foreach ( get_users( array( 'role' => 'prx3_board_member' ) ) as $director ) {
			PRX3_Comms::send( $director->user_email, __( 'Board action required', 'fan-ownership' ), $summary, 'governance' );
		}
		PRX3_Audit::log( 'board_action_open', $summary );
	}

	/**
	 * Record a structured action's outcome. Casting votes resolve ties;
	 * failed-quorum and reserved decisions publish to the register with
	 * reasoning (P81 — every board act leaves a trail).
	 */
	public static function handle_structured_action() {
		if ( ! current_user_can( 'prx3_board' ) ) {
			wp_die( esc_html__( 'Board members only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_board_action' );
		$index     = isset( $_POST['action_index'] ) ? absint( $_POST['action_index'] ) : -1;
		$choice    = isset( $_POST['choice'] ) ? absint( $_POST['choice'] ) : 0;
		$reasoning = isset( $_POST['reasoning'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reasoning'] ) ) : '';
		if ( ! $reasoning ) {
			wp_die( esc_html__( 'Published reasoning is required for every board action.', 'fan-ownership' ) );
		}
		$actions = get_option( 'prx3_board_actions', array() );
		if ( ! isset( $actions[ $index ] ) || $actions[ $index ]['done'] ) {
			wp_die( esc_html__( 'Action not found or already completed.', 'fan-ownership' ) );
		}
		$action = $actions[ $index ];
		if ( 'casting_vote' === $action['type'] ) {
			$result = PRX3_Ballot_Lifecycle::resolve_tie( $action['ballot'], $choice, $reasoning );
			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ) );
			}
		} else {
			// Failed quorum / reserved matter: decision + reasoning to register.
			PRX3_Decisions::create_from_board(
				sprintf( /* translators: %s summary. */ __( 'Board decision: %s', 'fan-ownership' ), $action['summary'] ),
				$reasoning,
				$reasoning
			);
		}
		$actions[ $index ]['done']      = time();
		$actions[ $index ]['by']        = get_current_user_id();
		$actions[ $index ]['reasoning'] = $reasoning;
		update_option( 'prx3_board_actions', $actions, false );
		PRX3_Audit::log( 'board_action_done', $action['summary'], array( 'reasoning' => $reasoning ) );
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-board' ) );
		exit;
	}

	/* ---------------- Internal board votes (FO-226/FO-227) ---------------- */

	/**
	 * One vote per director, open within the board, chair casting vote,
	 * recusal lockout, auto-minuted.
	 */
	public static function handle_internal_vote() {
		if ( ! current_user_can( 'prx3_board' ) ) {
			wp_die( esc_html__( 'Board members only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_board_vote' );
		$vote_id  = isset( $_POST['vote_id'] ) ? absint( $_POST['vote_id'] ) : 0;
		$position = isset( $_POST['position'] ) ? sanitize_key( $_POST['position'] ) : '';
		$declare  = isset( $_POST['conflict_declared'] ) ? sanitize_key( $_POST['conflict_declared'] ) : '';
		$user_id  = get_current_user_id();
		if ( ! $vote_id || 'prx3_board_vote' !== get_post_type( $vote_id ) || ! in_array( $position, array( 'for', 'against', 'abstain', 'recuse' ), true ) ) {
			wp_die( esc_html__( 'Invalid board vote.', 'fan-ownership' ) );
		}
		// FO-227 AC2: declare-or-confirm-none before voting.
		if ( ! in_array( $declare, array( 'none', 'declared' ), true ) ) {
			wp_die( esc_html__( 'Confirm you have no conflict, or declare one, before voting.', 'fan-ownership' ) );
		}
		if ( ! PRX3_Access::can_access_board_item( $vote_id, $user_id ) ) {
			wp_die( esc_html__( 'You are recused from this item.', 'fan-ownership' ) );
		}
		if ( 'declared' === $declare || 'recuse' === $position ) {
			self::recuse( $vote_id, $user_id, 'self-declared' );
			wp_safe_redirect( admin_url( 'admin.php?page=prx3-board&recused=1' ) );
			exit;
		}
		$votes             = (array) get_post_meta( $vote_id, '_prx3_board_votes', true );
		$votes[ $user_id ] = array(
			'position' => $position,
			'at'       => time(),
		); // Open within the board (P83).
		update_post_meta( $vote_id, '_prx3_board_votes', $votes );
		self::maybe_minute_outcome( $vote_id );
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-board' ) );
		exit;
	}

	/**
	 * FO-227 AC3/AC4: recusal locks papers, threads, and the vote; chair
	 * can apply it too; always minuted.
	 *
	 * @param int    $item_id Board item (paper, vote, or thread) post ID.
	 * @param int    $user_id Director being recused.
	 * @param string $how     How the recusal arose (e.g. 'self-declared').
	 */
	public static function recuse( $item_id, $user_id, $how ) {
		$recused   = array_map( 'intval', (array) get_post_meta( $item_id, '_prx3_recused_users', true ) );
		$recused[] = (int) $user_id;
		update_post_meta( $item_id, '_prx3_recused_users', array_unique( $recused ) );
		PRX3_Audit::log( 'board_recusal', sprintf( 'Director %d recused from item %d (%s)', $user_id, $item_id, $how ) );
	}

	/**
	 * When every non-recused director has voted, minute the outcome with
	 * the vote breakdown; a level vote falls to the chair (P83).
	 *
	 * @param int $vote_id Board vote post ID.
	 */
	private static function maybe_minute_outcome( $vote_id ) {
		$directors = get_users(
			array(
				'role'   => 'prx3_board_member',
				'fields' => 'ID',
			)
		);
		$recused   = array_map( 'intval', (array) get_post_meta( $vote_id, '_prx3_recused_users', true ) );
		$eligible  = array_diff( array_map( 'intval', $directors ), $recused );
		$votes     = (array) get_post_meta( $vote_id, '_prx3_board_votes', true );
		if ( count( array_intersect( array_keys( $votes ), $eligible ) ) < count( $eligible ) ) {
			return;
		}
		$for     = count( array_filter( $votes, fn( $v ) => 'for' === $v['position'] ) );
		$against = count( array_filter( $votes, fn( $v ) => 'against' === $v['position'] ) );
		$chair   = (int) prx3_setting( 'board_chair_id', 0 );
		$outcome = $for > $against ? 'carried' : ( $against > $for ? 'defeated' : 'level' );
		if ( 'level' === $outcome && $chair && isset( $votes[ $chair ] ) ) {
			$outcome = 'for' === $votes[ $chair ]['position'] ? 'carried-on-chair-casting-vote' : 'defeated-on-chair-casting-vote';
		}
		update_post_meta(
			$vote_id,
			'_prx3_board_outcome',
			array(
				'outcome' => $outcome,
				'for'     => $for,
				'against' => $against,
				'abstain' => count( $votes ) - $for - $against,
				'minuted' => prx3_now(),
			)
		);
		PRX3_Audit::log( 'board_vote_minuted', sprintf( 'Board vote %d: %s (%d for, %d against)', $vote_id, $outcome, $for, $against ) );
	}

	/* ---------------- Disclosure (P84) ---------------- */

	/**
	 * Selective release: a board item's outcome publishes to the decision
	 * register only when the board pushes it out.
	 */
	public static function handle_release() {
		if ( ! current_user_can( 'prx3_board' ) ) {
			wp_die( esc_html__( 'Board members only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_board_release' );
		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$summary = isset( $_POST['summary'] ) ? sanitize_text_field( wp_unslash( $_POST['summary'] ) ) : '';
		$body    = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		if ( ! $item_id || ! $summary ) {
			wp_die( esc_html__( 'A release needs a summary for the register.', 'fan-ownership' ) );
		}
		$decision_id = PRX3_Decisions::create_from_board( $summary, $body, $body );
		update_post_meta( $item_id, '_prx3_released_as', $decision_id );
		PRX3_Audit::log( 'board_release', sprintf( 'Board item %d released to register as decision %d', $item_id, $decision_id ) );
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-board' ) );
		exit;
	}

	/* ---------------- Observers (FO-228) ---------------- */

	/**
	 * Whether a user holds a current observer grant on a board item
	 * (FO-228: time-boxed, read-only access).
	 *
	 * @param int $post_id Board item post ID.
	 * @param int $user_id User to check.
	 * @return bool True when an unexpired grant exists.
	 */
	public static function observer_has_access( $post_id, $user_id ) {
		$grants = (array) get_post_meta( $post_id, '_prx3_observers', true );
		foreach ( $grants as $grant ) {
			if ( (int) $grant['user'] === (int) $user_id && (int) $grant['expires'] > time() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Grant time-boxed observer access to a board item (capped at 90
	 * days), always audited.
	 */
	public static function handle_observer_grant() {
		if ( ! current_user_can( 'prx3_board' ) ) {
			wp_die( esc_html__( 'Board members only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_board_observer' );
		$item_id  = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$observer = isset( $_POST['observer'] ) ? absint( $_POST['observer'] ) : 0;
		$days     = isset( $_POST['days'] ) ? min( 90, absint( $_POST['days'] ) ) : 7;
		if ( ! $item_id || ! get_userdata( $observer ) ) {
			wp_die( esc_html__( 'Choose the item and the observer.', 'fan-ownership' ) );
		}
		$grants   = (array) get_post_meta( $item_id, '_prx3_observers', true );
		$grants[] = array(
			'user'    => $observer,
			'by'      => get_current_user_id(),
			'expires' => time() + $days * DAY_IN_SECONDS,
		);
		update_post_meta( $item_id, '_prx3_observers', $grants );
		PRX3_Audit::log( 'observer_grant', sprintf( 'Observer %d granted item %d for %d days', $observer, $item_id, $days ) );
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-board' ) );
		exit;
	}

	/* ---------------- Conflicts register (FO-227 AC1) ---------------- */

	/**
	 * Render the conflicts-of-interest field on a director's profile
	 * (FO-227 AC1: published on the board directory).
	 *
	 * @param WP_User $user Profile being viewed or edited.
	 */
	public static function conflicts_profile( $user ) {
		if ( ! in_array( 'prx3_board_member', (array) $user->roles, true ) ) {
			return;
		}
		$conflicts = get_user_meta( $user->ID, 'prx3_conflicts', true );
		echo '<h2>' . esc_html__( 'Conflicts of interest (public on the board directory)', 'fan-ownership' ) . '</h2>';
		wp_nonce_field( 'prx3_conflicts', 'prx3_conflicts_nonce' );
		echo '<textarea class="widefat" rows="4" name="prx3_conflicts">' . esc_textarea( $conflicts ) . '</textarea>';
	}

	/**
	 * Save a director's conflicts register entry from the profile
	 * screen.
	 *
	 * @param int $user_id Profile user ID being saved.
	 */
	public static function save_conflicts( $user_id ) {
		if ( ! isset( $_POST['prx3_conflicts_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['prx3_conflicts_nonce'] ), 'prx3_conflicts' ) ) {
			return;
		}
		if ( get_current_user_id() !== $user_id && ! current_user_can( 'prx3_admin' ) ) {
			return;
		}
		update_user_meta( $user_id, 'prx3_conflicts', isset( $_POST['prx3_conflicts'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prx3_conflicts'] ) ) : '' );
	}

	/* ---------------- Vault (FO-229) ---------------- */

	/**
	 * Vault documents render in-workspace only, watermarked per view,
	 * with a chair-visible access log.
	 */
	public static function watermark_vault_view() {
		if ( ! is_singular( 'prx3_vault_doc' ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		$user    = wp_get_current_user();
		if ( ! PRX3_Access::can_access_board_item( $post_id, $user->ID ) ) {
			wp_die( esc_html__( 'This area is restricted.', 'fan-ownership' ), 403 );
		}
		PRX3_Audit::log( 'vault_view', sprintf( 'Vault document %d viewed', $post_id ), array( 'document' => $post_id ) );
		add_filter(
			'the_content',
			function ( $content ) use ( $user ) {
				$stamp = sprintf( '%s — %s', $user->display_name, date_i18n( get_option( 'date_format' ) . ' H:i' ) );
				return '<div class="prx3-vault" style="position:relative;">'
				. '<div aria-hidden="true" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;opacity:.12;transform:rotate(-24deg);font-size:2rem;">' . esc_html( $stamp ) . '</div>'
				. $content . '<p><em>' . esc_html__( 'View-only. This document cannot be downloaded; every view is logged.', 'fan-ownership' ) . '</em></p></div>';
			},
			99
		);
	}

	/* ---------------- Workspace home (admin screen) ---------------- */

	/**
	 * Render the workspace home screen: outstanding structured actions
	 * with the required published-reasoning form.
	 */
	public static function render_workspace_home() {
		if ( ! current_user_can( 'prx3_board' ) ) {
			// Staff with tally view see the menu for the Club Dashboard, but
			// the workspace itself stays behind the board hard wall (FO-226).
			echo '<div class="wrap"><h1>' . esc_html__( 'FanPress Board', 'fan-ownership' ) . '</h1><p>' . esc_html__( 'The Board Workspace is restricted to board members. The Club Dashboard is available from this menu.', 'fan-ownership' ) . '</p></div>';
			return;
		}
		$actions = array_filter( get_option( 'prx3_board_actions', array() ), fn( $a ) => empty( $a['done'] ) );
		echo '<div class="wrap"><h1>' . esc_html( sprintf( /* translators: %s club. */ __( '%s Board Workspace', 'fan-ownership' ), prx3_club_name() ) ) . '</h1>';
		echo '<h2>' . esc_html__( 'Actions awaiting the board', 'fan-ownership' ) . '</h2>';
		if ( ! $actions ) {
			echo '<p>' . esc_html__( 'Nothing outstanding.', 'fan-ownership' ) . '</p>';
		}
		foreach ( $actions as $i => $action ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="border:1px solid #ccd0d4;padding:12px;margin:8px 0;max-width:640px;">';
			wp_nonce_field( 'prx3_board_action' );
			echo '<input type="hidden" name="action" value="prx3_board_action"><input type="hidden" name="action_index" value="' . (int) $i . '">';
			echo '<p><strong>' . esc_html( $action['summary'] ) . '</strong></p>';
			if ( 'casting_vote' === $action['type'] ) {
				$options = (array) get_post_meta( $action['ballot'], '_prx3_options', true );
				foreach ( $options as $idx => $label ) {
					echo '<label style="display:block;"><input type="radio" name="choice" value="' . (int) $idx . '" required> ' . esc_html( $label ) . '</label>';
				}
			}
			echo '<p><label>' . esc_html__( 'Published reasoning (required)', 'fan-ownership' ) . '</label><textarea class="widefat" name="reasoning" required></textarea></p>';
			echo '<p><button class="button button-primary">' . esc_html__( 'Record board action', 'fan-ownership' ) . '</button></p></form>';
		}
		echo '<p>' . esc_html__( 'Papers, internal votes, threads, and the vault live in the Board menu items. Meetings run by video within the workspace (embedded conferencing per the specification).', 'fan-ownership' ) . '</p></div>';
	}
	/**
	 * Live Q&A presenter (FO-214): published questions ranked by member
	 * upvotes, auto-refreshing every 20 seconds for the person on stage.
	 */
	public static function render_presenter() {
		if ( ! current_user_can( 'prx3_view_tally' ) ) {
			wp_die( esc_html__( 'Board and staff only.', 'fan-ownership' ) );
		}
		echo '<meta http-equiv="refresh" content="20">';
		echo '<div class="wrap"><h1>' . esc_html__( 'Live Q&A — ranked by owner upvotes', 'fan-ownership' ) . '</h1>';
		echo '<p>' . esc_html__( 'Refreshes every 20 seconds. Answered questions drop off when marked answered on the Questions screen. Filter to the person on stage.', 'fan-ownership' ) . '</p>';
		$categories = PRX3_Questions::categories();
		$active     = isset( $_GET['qcat'] ) ? sanitize_key( wp_unslash( $_GET['qcat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only presenter filter.
		$active     = isset( $categories[ $active ] ) ? $active : '';
		echo '<p>';
		echo '<a class="button' . ( '' === $active ? ' button-primary' : '' ) . '" href="' . esc_url( remove_query_arg( 'qcat' ) ) . '">' . esc_html__( 'All', 'fan-ownership' ) . '</a> ';
		foreach ( $categories as $key => $label ) {
			echo '<a class="button' . ( $key === $active ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( 'qcat', $key ) ) . '">' . esc_html( $label ) . '</a> ';
		}
		echo '</p>';
		$ranked = array();
		foreach ( get_posts(
			array(
				'post_type'   => 'prx3_question',
				'post_status' => array( 'publish' ),
				'numberposts' => -1,
			)
		) as $question ) {
			if ( '' !== trim( (string) get_post_meta( $question->ID, '_prx3_answer', true ) ) ) {
				continue;
			}
			if ( $active && PRX3_Questions::category( $question->ID ) !== $active ) {
				continue;
			}
			$ranked[] = array( $question, count( array_filter( (array) get_post_meta( $question->ID, '_prx3_upvotes', true ) ) ) );
		}
		usort( $ranked, fn( $x, $y ) => $y[1] <=> $x[1] );
		if ( ! $ranked ) {
			echo '<p>' . esc_html__( 'No open questions.', 'fan-ownership' ) . '</p>';
		}
		echo '<ol style="font-size:1.35em;max-width:820px;">';
		foreach ( $ranked as $row ) {
			echo '<li style="margin-bottom:14px;"><strong>' . esc_html( $row[0]->post_title ) . '</strong> <span style="color:#666;">[' . esc_html( $categories[ PRX3_Questions::category( $row[0]->ID ) ] ) . '] (' . (int) $row[1] . ' ' . esc_html__( 'upvotes', 'fan-ownership' ) . ')</span> <a href="' . esc_url( get_edit_post_link( $row[0]->ID ) ) . '">' . esc_html__( 'open', 'fan-ownership' ) . '</a></li>';
		}
		echo '</ol></div>';
	}

	/**
	 * Annual report workspace (FO-219): the year\'s report drafts and a
	 * one-click starting point, published into the owner document vault.
	 */
	public static function render_annual_report() {
		if ( ! current_user_can( 'prx3_governance' ) && ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Governance staff only.', 'fan-ownership' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Annual Report', 'fan-ownership' ) . '</h1>';
		echo '<p>' . esc_html__( 'Draft here, publish to the owner vault when the board signs it off. Reports are documents of type annual-report.', 'fan-ownership' ) . '</p><ul>';
		$found = false;
		foreach ( get_posts(
			array(
				'post_type'   => 'prx3_document',
				'post_status' => array( 'publish', 'draft', 'pending' ),
				'numberposts' => -1,
				'meta_key'    => '_prx3_dtype', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small bounded list.
				'meta_value'  => 'annual-report', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		) as $report ) {
			$found = true;
			echo '<li><a href="' . esc_url( get_edit_post_link( $report->ID ) ) . '">' . esc_html( $report->post_title ) . '</a> — ' . esc_html( $report->post_status ) . '</li>';
		}
		if ( ! $found ) {
			echo '<li>' . esc_html__( 'No annual reports yet.', 'fan-ownership' ) . '</li>';
		}
		echo '</ul><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'prx3_annual_draft' );
		echo '<input type="hidden" name="action" value="prx3_annual_draft">';
		echo '<p><button class="button button-primary">' . esc_html__( "Start this year's draft", 'fan-ownership' ) . '</button></p></form></div>';
	}

	/**
	 * Create the current year\'s annual report draft.
	 */
	public static function handle_annual_draft() {
		if ( ! current_user_can( 'prx3_governance' ) && ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Governance staff only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_annual_draft' );
		$report = wp_insert_post(
			array(
				'post_type'    => 'prx3_document',
				'post_status'  => 'draft',
				'post_title'   => sprintf( /* translators: 1 club, 2 year. */ __( '%1$s Annual Report %2$s', 'fan-ownership' ), prx3_club_name(), gmdate( 'Y' ) ),
				'post_content' => '<h2>' . esc_html__( 'Season review', 'fan-ownership' ) . '</h2><p></p><h2>' . esc_html__( 'Accounts summary', 'fan-ownership' ) . '</h2><p></p><h2>' . esc_html__( 'Governance and ballots', 'fan-ownership' ) . '</h2><p></p><h2>' . esc_html__( 'The year ahead', 'fan-ownership' ) . '</h2><p></p>',
			)
		);
		if ( $report && ! is_wp_error( $report ) ) {
			update_post_meta( $report, '_prx3_dtype', 'annual-report' );
			PRX3_Audit::log( 'annual_report_draft', sprintf( 'Annual report draft #%d created', $report ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-annual-report' ) );
		exit;
	}
	/**
	 * Board access to the audited identity lookup (P122 amendment:
	 * board members see the record; the government ID stays masked).
	 */
	public static function render_identity_lookup() {
		if ( ! current_user_can( 'prx3_board' ) && ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Board members only.', 'fan-ownership' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Identity Lookup', 'fan-ownership' ) . '</h1>';
		PRX3_Admin::identity_lookup_panel( 'prx3-board-identity' );
		echo '</div>';
	}

	/**
	 * Board → Owner Management (P128): find an owner, see their
	 * personal information (audited, ID masked), and moderate their
	 * community-facing profile content — picture, bio, socials,
	 * gallery — in one place. Identity and contact details are
	 * read-only here by design (P127).
	 */
	public static function render_owner_management() {
		if ( ! current_user_can( 'prx3_board' ) && ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Board members and Owner-Admins only.', 'fan-ownership' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Owner Management', 'fan-ownership' ) . '</h1>';
		if ( isset( $_GET['moderated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- confirmation notice only.
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Profile updated. The change is in the audit log.', 'fan-ownership' ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Find an owner to see their personal information and fix inappropriate profile content. Name, address, ID, PEP, and contact details are read-only here — they belong to the member.', 'fan-ownership' ) . '</p>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search + selection.
		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$who = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		echo '<form method="get"><input type="hidden" name="page" value="prx3-board-owners">';
		echo '<p><label for="prx3_om_q">' . esc_html__( 'Search owners (name, login, or email)', 'fan-ownership' ) . '</label> <input type="search" id="prx3_om_q" name="q" value="' . esc_attr( $search ) . '"> <button class="button">' . esc_html__( 'Search', 'fan-ownership' ) . '</button></p></form>';
		if ( $search && ! $who ) {
			$found = get_users(
				array(
					'search'         => '*' . $search . '*',
					'search_columns' => array( 'user_login', 'user_email', 'display_name', 'user_nicename' ),
					'number'         => 20,
				)
			);
			echo $found ? '<ul>' : '<p>' . esc_html__( 'No matching members.', 'fan-ownership' ) . '</p>';
			foreach ( $found as $member ) {
				echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=prx3-board-owners&user=' . (int) $member->ID ) ) . '">' . esc_html( $member->display_name ) . '</a> — ' . esc_html( $member->user_email ) . ( prx3_is_owner( $member->ID ) ? ' <span class="prx3-badge">#' . (int) PRX3_Shares::owner_number( $member->ID ) . '</span>' : '' ) . '</li>';
			}
			echo $found ? '</ul>' : '';
		}
		if ( ! $who || ! get_userdata( $who ) ) {
			echo '</div>';
			return;
		}
		$member = get_userdata( $who );
		echo '<h2>' . esc_html( $member->display_name ) . '</h2>';
		echo '<p><a class="button" href="' . esc_url( get_edit_user_link( $who ) ) . '">' . esc_html__( 'Open WP user account', 'fan-ownership' ) . '</a> ';
		$live = class_exists( 'PRX3_Social' ) ? PRX3_Social::profile_url( $who ) : '';
		if ( $live ) {
			echo '<a class="button" href="' . esc_url( $live ) . '">' . esc_html__( 'View live owner profile', 'fan-ownership' ) . '</a>';
		}
		echo '</p>';
		// Personal information — read-only, audited, ID masked (P122/P127).
		$identity = PRX3_Social::identity( $who );
		PRX3_Audit::log( 'identity_viewed', sprintf( 'Identity record for member %1$d viewed by user %2$d on Owner Management', $who, get_current_user_id() ) );
		echo '<h3>' . esc_html__( 'Personal information (read-only)', 'fan-ownership' ) . '</h3>';
		echo '<table class="widefat striped" style="max-width:640px;"><tbody>';
		$rows = array(
			__( 'Full birth name', 'fan-ownership' )      => $identity['birth_name'],
			__( 'Nationality', 'fan-ownership' )          => $identity['nationality'],
			__( 'Country of residence', 'fan-ownership' ) => $identity['residence'],
			__( 'Date of birth', 'fan-ownership' )        => $identity['dob'],
			__( 'Government ID', 'fan-ownership' )        => PRX3_Social::mask_gov_id( $identity['gov_id'] ),
			__( 'Politically exposed', 'fan-ownership' )  => $identity['pep'] ? $identity['pep'] : __( 'not declared', 'fan-ownership' ),
			__( 'Email', 'fan-ownership' )                => $member->user_email,
			__( 'Shares', 'fan-ownership' )               => (string) prx3_shares( $who ),
		);
		foreach ( $rows as $label => $value ) {
			echo '<tr><th style="text-align:left;width:220px;">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
		// Profile content — the part the board can fix.
		echo '<h3>' . esc_html__( 'Profile content (editable — for inappropriate pictures or text)', 'fan-ownership' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'prx3_board_moderate' );
		echo '<input type="hidden" name="action" value="prx3_board_moderate"><input type="hidden" name="user" value="' . (int) $who . '">';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="prx3_om_bio">' . esc_html__( 'Bio', 'fan-ownership' ) . '</label></th><td><textarea id="prx3_om_bio" name="bio" class="regular-text" rows="2" maxlength="300">' . esc_textarea( (string) get_user_meta( $who, 'prx3_bio', true ) ) . '</textarea></td></tr>';
		$socials = array_filter( (array) get_user_meta( $who, 'prx3_socials', true ) );
		echo '<tr><th>' . esc_html__( 'Social links', 'fan-ownership' ) . '</th><td>' . ( $socials ? esc_html( implode( '  ', array_map( 'strval', $socials ) ) ) . '<br><label><input type="checkbox" name="clear_socials" value="1"> ' . esc_html__( 'Remove all social links', 'fan-ownership' ) . '</label>' : esc_html__( 'None', 'fan-ownership' ) ) . '</td></tr>';
		$photo = (int) get_user_meta( $who, 'prx3_photo', true );
		echo '<tr><th>' . esc_html__( 'Profile picture', 'fan-ownership' ) . '</th><td>' . ( $photo ? wp_kses_post( wp_get_attachment_image( $photo, 'thumbnail' ) ) . '<br><label><input type="checkbox" name="remove_photo" value="1"> ' . esc_html__( 'Remove the profile picture', 'fan-ownership' ) . '</label>' : esc_html__( 'None', 'fan-ownership' ) ) . '</td></tr>';
		$gallery = array_map( 'intval', array_filter( (array) get_user_meta( $who, 'prx3_gallery', true ) ) );
		echo '<tr><th>' . esc_html__( 'Gallery photos', 'fan-ownership' ) . '</th><td>';
		if ( $gallery ) {
			foreach ( $gallery as $pic ) {
				echo '<label style="display:inline-block;margin:0 12px 8px 0;text-align:center;">' . wp_kses_post( wp_get_attachment_image( $pic, 'thumbnail' ) ) . '<br><input type="checkbox" name="remove_gallery[]" value="' . (int) $pic . '"> ' . esc_html__( 'Remove', 'fan-ownership' ) . '</label>';
			}
		} else {
			echo esc_html__( 'None', 'fan-ownership' );
		}
		echo '</td></tr></tbody></table>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Apply changes', 'fan-ownership' ) . '</button></p></form></div>';
	}

	/**
	 * Apply Owner Management moderation and return to the member's page.
	 */
	public static function handle_owner_moderate() {
		if ( ! current_user_can( 'prx3_board' ) && ! current_user_can( 'prx3_admin' ) ) {
			wp_die( esc_html__( 'Board members and Owner-Admins only.', 'fan-ownership' ) );
		}
		check_admin_referer( 'prx3_board_moderate' );
		$who = isset( $_POST['user'] ) ? absint( $_POST['user'] ) : 0;
		PRX3_Social::moderate_profile(
			get_current_user_id(),
			$who,
			array(
				'bio'            => isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : null,
				'clear_socials'  => ! empty( $_POST['clear_socials'] ),
				'remove_photo'   => ! empty( $_POST['remove_photo'] ),
				'remove_gallery' => isset( $_POST['remove_gallery'] ) ? array_map( 'intval', (array) $_POST['remove_gallery'] ) : array(),
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=prx3-board-owners&user=' . $who . '&moderated=1' ) );
		exit;
	}
}
