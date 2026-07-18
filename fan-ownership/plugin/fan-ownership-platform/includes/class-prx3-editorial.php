<?php
/**
 * Editorial workflow: routine content publishes solo; ballots and
 * financial posts need a second approver; sensitive footage needs
 * manager sign-off. FO-119, P70, T26.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enforces the editorial approval rules: a second approver for ballots
 * and financial documents, and manager sign-off for sensitive footage.
 */
class PRX3_Editorial {

	/**
	 * Hook up the publish gate, meta boxes and approval handler.
	 */
	public static function init() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'enforce_approval' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_flags' ), 10, 2 );
		add_action( 'admin_post_prx3_approve_item', array( __CLASS__, 'handle_approve' ) );
	}

	/**
	 * Types that require a second approver before publish (FO-119 AC2).
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public static function needs_second_approval( $post_type ) {
		return in_array( $post_type, array( 'prx3_ballot' ), true );
	}

	/**
	 * Financial documents also need it (detected by document type).
	 *
	 * @param int $post_id Document post ID.
	 * @return bool
	 */
	private static function is_financial_document( $post_id ) {
		return has_term( array( 'financial-statement', 'monthly-summary', 'annual-accounts' ), 'prx3_document_type', $post_id );
	}

	/**
	 * Hold un-approved governed items at pending, whoever hits publish.
	 *
	 * @param array $data    Slashed, sanitized post data about to be saved.
	 * @param array $postarr Raw post array including the post ID.
	 * @return array Filtered post data.
	 */
	public static function enforce_approval( $data, $postarr ) {
		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( 'publish' !== $data['post_status'] ) {
			return $data;
		}
		$governed = self::needs_second_approval( $data['post_type'] )
			|| ( 'prx3_document' === $data['post_type'] && $post_id && self::is_financial_document( $post_id ) );
		if ( $governed && $post_id ) {
			$approved_by = (int) get_post_meta( $post_id, '_prx3_approved_by', true );
			$author      = (int) ( $data['post_author'] ?? 0 );
			// AC2: an approval must exist and the approver cannot be the author.
			if ( ! $approved_by || $approved_by === $author ) {
				$data['post_status'] = 'pending';
			}
		}
		// Sensitive footage: manager gate (P70/P68).
		if ( $post_id && get_post_meta( $post_id, '_prx3_sensitive', true ) && ! get_post_meta( $post_id, '_prx3_manager_signoff', true ) ) {
			$data['post_status'] = 'pending';
		}
		return $data;
	}

	public static function meta_boxes() {
		foreach ( array( 'prx3_ballot', 'prx3_document', 'prx3_video', 'prx3_exclusive' ) as $type ) {
			add_meta_box( 'prx3_editorial', __( 'Editorial Controls', 'fan-ownership' ), array( __CLASS__, 'render_box' ), $type, 'side', 'high' );
		}
	}

	public static function render_box( $post ) {
		wp_nonce_field( 'prx3_editorial', 'prx3_editorial_nonce' );
		$approved_by = (int) get_post_meta( $post->ID, '_prx3_approved_by', true );
		$sensitive   = get_post_meta( $post->ID, '_prx3_sensitive', true );
		$signoff     = (int) get_post_meta( $post->ID, '_prx3_manager_signoff', true );
		$teaser      = get_post_meta( $post->ID, '_prx3_teaser', true );

		if ( self::needs_second_approval( $post->post_type ) || 'prx3_document' === $post->post_type ) {
			if ( $approved_by ) {
				$approver = get_userdata( $approved_by );
				echo '<p>' . esc_html( sprintf( /* translators: %s approver. */ __( 'Approved by %s.', 'fan-ownership' ), $approver ? $approver->display_name : '#' . $approved_by ) ) . '</p>';
			} elseif ( current_user_can( 'prx3_second_approve' ) && get_current_user_id() !== (int) $post->post_author ) {
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=prx3_approve_item&post=' . $post->ID ), 'prx3_approve_' . $post->ID );
				echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Approve for publication', 'fan-ownership' ) . '</a></p>';
			} else {
				echo '<p>' . esc_html__( 'Awaiting a second approver (the author cannot approve their own item).', 'fan-ownership' ) . '</p>';
			}
		}
		echo '<p><label><input type="checkbox" name="prx3_sensitive" value="1" ' . checked( $sensitive, '1', false ) . '> ' . esc_html__( 'Sensitive footage (needs manager sign-off)', 'fan-ownership' ) . '</label></p>';
		if ( $sensitive ) {
			echo $signoff
				? '<p>' . esc_html__( 'Manager signed off.', 'fan-ownership' ) . '</p>'
				: '<p><label><input type="checkbox" name="prx3_manager_signoff" value="1"> ' . esc_html__( 'I am the manager: sign off this footage', 'fan-ownership' ) . '</label></p>';
		}
		echo '<p><label for="prx3_teaser">' . esc_html__( 'Public teaser (optional, shown to non-owners)', 'fan-ownership' ) . '</label>';
		echo '<textarea class="widefat" rows="3" id="prx3_teaser" name="prx3_teaser">' . esc_textarea( $teaser ) . '</textarea></p>';
		echo '<p><label><input type="checkbox" name="prx3_teaser_public" value="1" ' . checked( get_post_meta( $post->ID, '_prx3_teaser_public', true ), '1', false ) . '> ' . esc_html__( 'Teaser page is publicly reachable', 'fan-ownership' ) . '</label></p>';
	}

	public static function save_flags( $post_id, $post ) {
		if ( ! isset( $_POST['prx3_editorial_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['prx3_editorial_nonce'] ), 'prx3_editorial' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_prx3_sensitive', isset( $_POST['prx3_sensitive'] ) ? '1' : '' );
		update_post_meta( $post_id, '_prx3_teaser', isset( $_POST['prx3_teaser'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prx3_teaser'] ) ) : '' );
		update_post_meta( $post_id, '_prx3_teaser_public', isset( $_POST['prx3_teaser_public'] ) ? '1' : '' );
		if ( isset( $_POST['prx3_manager_signoff'] ) && apply_filters( 'prx3_user_is_manager', current_user_can( 'prx3_admin' ), get_current_user_id() ) ) {
			update_post_meta( $post_id, '_prx3_manager_signoff', get_current_user_id() );
			PRX3_Audit::log( 'manager_signoff', sprintf( 'Sensitive item %d signed off', $post_id ) );
		}
	}

	/**
	 * Second-approver action, recorded (FO-119 AC2).
	 */
	public static function handle_approve() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'prx3_approve_' . $post_id );
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'prx3_second_approve' ) || get_current_user_id() === (int) $post->post_author ) {
			wp_die( esc_html__( 'A second person (not the author) must approve this item.', 'fan-ownership' ) );
		}
		update_post_meta( $post_id, '_prx3_approved_by', get_current_user_id() );
		update_post_meta( $post_id, '_prx3_approved_at', time() );
		PRX3_Audit::log( 'second_approval', sprintf( 'Item %d approved for publication', $post_id ) );
		wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
		exit;
	}
}
