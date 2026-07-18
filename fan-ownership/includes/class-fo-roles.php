<?php
/**
 * Roles, membership registration, and the shares register.
 *
 * Members self-register, choose how many shares to buy (1–10), and get
 * portal access straight away. Payment is collected off-platform (or via a
 * gateway hooked onto `fo_member_registered`); admins track who has paid
 * on the user profile.
 *
 * @package FanOwnership
 */

defined( 'ABSPATH' ) || exit;

class FO_Roles {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_handle_registration' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'profile_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile_fields' ) );
		add_filter( 'manage_users_columns', array( __CLASS__, 'users_columns' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'users_column_value' ), 10, 3 );
	}

	/**
	 * Create roles and grant admins/editors portal capabilities.
	 * Runs on activation.
	 */
	public static function add_roles() {
		add_role(
			'fan_owner',
			__( 'Fan Owner', 'fan-ownership' ),
			array(
				'read'      => true,
				'fo_access' => true,
			)
		);
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( 'fo_access' );
				$role->add_cap( 'fo_manage' );
			}
		}
	}

	/**
	 * Handle the front-end registration form.
	 */
	public static function maybe_handle_registration() {
		if ( ! isset( $_POST['fo_register_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['fo_register_nonce'] ), 'fo_register' ) ) {
			self::redirect_with( array( 'fo_error' => rawurlencode( __( 'Your session expired. Please try again.', 'fan-ownership' ) ) ) );
		}
		if ( is_user_logged_in() ) {
			self::redirect_with( array( 'fo_error' => rawurlencode( __( 'You are already logged in.', 'fan-ownership' ) ) ) );
		}

		$name     = isset( $_POST['fo_name'] ) ? sanitize_text_field( wp_unslash( $_POST['fo_name'] ) ) : '';
		$email    = isset( $_POST['fo_email'] ) ? sanitize_email( wp_unslash( $_POST['fo_email'] ) ) : '';
		$password = isset( $_POST['fo_password'] ) ? (string) wp_unslash( $_POST['fo_password'] ) : '';
		$shares   = isset( $_POST['fo_shares'] ) ? absint( $_POST['fo_shares'] ) : 1;
		$shares   = max( 1, min( fo_max_shares(), $shares ) );

		if ( ! $name || ! is_email( $email ) || strlen( $password ) < 8 ) {
			self::redirect_with( array( 'fo_error' => rawurlencode( __( 'Please fill in your name, a valid email address, and a password of at least 8 characters.', 'fan-ownership' ) ) ) );
		}
		if ( email_exists( $email ) ) {
			self::redirect_with( array( 'fo_error' => rawurlencode( __( 'An account already exists for that email address. Try logging in instead.', 'fan-ownership' ) ) ) );
		}

		$username = sanitize_user( current( explode( '@', $email ) ), true );
		$suffix   = 1;
		$base     = $username ? $username : 'fanowner';
		$username = $base;
		while ( username_exists( $username ) ) {
			$username = $base . ( ++$suffix );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $name,
				'first_name'   => $name,
				'role'         => 'fan_owner',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			self::redirect_with( array( 'fo_error' => rawurlencode( $user_id->get_error_message() ) ) );
		}

		fo_set_shares( $user_id, $shares );
		update_user_meta( $user_id, 'fo_paid', '' ); // Awaiting payment.

		/**
		 * Fires after a fan owner registers. Payment gateways can hook here.
		 *
		 * @param int $user_id New member's user ID.
		 * @param int $shares  Shares requested.
		 */
		do_action( 'fo_member_registered', $user_id, $shares );

		self::email_new_member( $user_id, $shares );
		self::email_admin( $user_id, $shares );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		self::redirect_with( array( 'fo_registered' => 1 ) );
	}

	private static function email_new_member( $user_id, $shares ) {
		$user         = get_userdata( $user_id );
		$instructions = fo_get_setting( 'payment_instructions', __( 'The club will be in touch with payment details for your shares.', 'fan-ownership' ) );
		wp_mail(
			$user->user_email,
			sprintf( /* translators: %s: club name. */ __( 'Welcome to %s fan ownership', 'fan-ownership' ), fo_club_name() ),
			sprintf(
				/* translators: 1: name, 2: club name, 3: share count, 4: share price, 5: payment instructions, 6: login URL. */
				__( "Hi %1\$s,\n\nWelcome aboard — you are now a %2\$s fan owner.\n\nShares reserved: %3\$d at %4\$s per share.\n\nPayment: %5\$s\n\nYour membership gives you one vote per share on club ballots, plus access to exclusive video, meetings, financial statements and more.\n\nLog in any time: %6\$s", 'fan-ownership' ),
				$user->display_name,
				fo_club_name(),
				$shares,
				fo_share_price(),
				$instructions,
				wp_login_url()
			)
		);
	}

	private static function email_admin( $user_id, $shares ) {
		$user = get_userdata( $user_id );
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( /* translators: %s: member name. */ __( 'New fan owner: %s', 'fan-ownership' ), $user->display_name ),
			sprintf(
				/* translators: 1: name, 2: email, 3: share count, 4: profile URL. */
				__( "%1\$s (%2\$s) has registered as a fan owner and reserved %3\$d share(s).\n\nOnce payment is received, mark them as paid on their profile: %4\$s", 'fan-ownership' ),
				$user->display_name,
				$user->user_email,
				$shares,
				admin_url( 'user-edit.php?user_id=' . $user_id )
			)
		);
	}

	private static function redirect_with( $args ) {
		$url = wp_get_referer() ? wp_get_referer() : home_url();
		$url = remove_query_arg( array( 'fo_error', 'fo_registered' ), $url );
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	/**
	 * Shares + payment fields on the user profile.
	 *
	 * @param WP_User $user User being viewed.
	 */
	public static function profile_fields( $user ) {
		if ( ! in_array( 'fan_owner', (array) $user->roles, true ) && ! user_can( $user, 'fo_access' ) ) {
			return;
		}
		$can_edit = current_user_can( 'fo_manage' );
		$shares   = fo_get_shares( $user->ID );
		$paid     = get_user_meta( $user->ID, 'fo_paid', true );
		?>
		<h2><?php echo esc_html( sprintf( /* translators: %s: club name. */ __( '%s Fan Ownership', 'fan-ownership' ), fo_club_name() ) ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="fo_shares"><?php esc_html_e( 'Shares owned', 'fan-ownership' ); ?></label></th>
				<td>
					<?php if ( $can_edit ) : ?>
						<input type="number" id="fo_shares" name="fo_shares" value="<?php echo esc_attr( $shares ); ?>" min="1" max="<?php echo esc_attr( fo_max_shares() ); ?>">
						<?php wp_nonce_field( 'fo_profile', 'fo_profile_nonce' ); ?>
					<?php else : ?>
						<strong><?php echo esc_html( $shares ); ?></strong>
					<?php endif; ?>
					<p class="description"><?php echo esc_html( sprintf( /* translators: %d: maximum shares. */ __( 'One vote per share on club ballots. Maximum %d shares per member.', 'fan-ownership' ), fo_max_shares() ) ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Payment received', 'fan-ownership' ); ?></th>
				<td>
					<?php if ( $can_edit ) : ?>
						<label><input type="checkbox" name="fo_paid" value="1" <?php checked( $paid, '1' ); ?>> <?php esc_html_e( 'Member has paid for their shares', 'fan-ownership' ); ?></label>
					<?php else : ?>
						<?php echo $paid ? esc_html__( 'Yes', 'fan-ownership' ) : esc_html__( 'Pending', 'fan-ownership' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save profile fields (club admins only).
	 *
	 * @param int $user_id User being saved.
	 */
	public static function save_profile_fields( $user_id ) {
		if ( ! current_user_can( 'fo_manage' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! isset( $_POST['fo_profile_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fo_profile_nonce'] ), 'fo_profile' ) ) {
			return;
		}
		if ( isset( $_POST['fo_shares'] ) ) {
			fo_set_shares( $user_id, absint( $_POST['fo_shares'] ) );
		}
		update_user_meta( $user_id, 'fo_paid', isset( $_POST['fo_paid'] ) ? '1' : '' );
	}

	/**
	 * Shares and payment columns on the Users screen.
	 *
	 * @param string[] $columns Column headings.
	 * @return string[]
	 */
	public static function users_columns( $columns ) {
		$columns['fo_shares'] = __( 'Shares', 'fan-ownership' );
		$columns['fo_paid']   = __( 'Paid', 'fan-ownership' );
		return $columns;
	}

	/**
	 * @param string $output      Current output.
	 * @param string $column_name Column key.
	 * @param int    $user_id     Row user.
	 * @return string
	 */
	public static function users_column_value( $output, $column_name, $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( 'fan_owner', (array) $user->roles, true ) ) {
			return 'fo_shares' === $column_name || 'fo_paid' === $column_name ? '—' : $output;
		}
		if ( 'fo_shares' === $column_name ) {
			return (string) fo_get_shares( $user_id );
		}
		if ( 'fo_paid' === $column_name ) {
			return get_user_meta( $user_id, 'fo_paid', true )
				? '<span style="color:#00701a;">' . esc_html__( 'Paid', 'fan-ownership' ) . '</span>'
				: '<span style="color:#8a6d00;">' . esc_html__( 'Pending', 'fan-ownership' ) . '</span>';
		}
		return $output;
	}
}
