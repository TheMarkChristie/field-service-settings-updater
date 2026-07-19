<?php
/**
 * Registration (18+, verified email) and duplicate-account flagging.
 *
 * FO-105, FO-110.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Membership sign-up: handles the registration form, 18+ and terms
 * confirmation, email verification, and near-duplicate account flagging.
 */
class PRX3_Membership {

	/**
	 * Hook the registration and verification handlers.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_handle_registration' ) );
		add_action( 'init', array( __CLASS__, 'maybe_handle_verification' ) );
	}

	/**
	 * Handle the registration form (shortcode [prx3_register]).
	 */
	public static function maybe_handle_registration() {
		if ( ! isset( $_POST['prx3_register_nonce'] ) ) {
			return;
		}
		if ( ! prx3_feature_on( 'registration' ) ) {
			self::back_with( array( 'prx3_error' => rawurlencode( __( 'Registration is temporarily unavailable.', 'fan-ownership' ) ) ) );
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['prx3_register_nonce'] ), 'prx3_register' ) ) {
			self::back_with( array( 'prx3_error' => rawurlencode( __( 'Your session expired. Please try again.', 'fan-ownership' ) ) ) );
		}
		if ( is_user_logged_in() ) {
			self::back_with( array( 'prx3_error' => rawurlencode( __( 'You are already signed in.', 'fan-ownership' ) ) ) );
		}

		$name     = isset( $_POST['prx3_name'] ) ? sanitize_text_field( wp_unslash( $_POST['prx3_name'] ) ) : '';
		$email    = isset( $_POST['prx3_email'] ) ? sanitize_email( wp_unslash( $_POST['prx3_email'] ) ) : '';
		$password = isset( $_POST['prx3_password'] ) ? (string) wp_unslash( $_POST['prx3_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords must not be altered; hashed by wp_insert_user().
		$is_adult = ! empty( $_POST['prx3_adult'] );
		$terms_ok = ! empty( $_POST['prx3_terms'] );

		// FO-105 AC1: name, email, password, 18+ confirmation, terms.
		if ( ! $is_adult ) {
			self::back_with(
				array(
					'prx3_error' => rawurlencode(
						sprintf(
						/* translators: %d minimum age. */
							__( 'You must be %d or over to own shares in the club.', 'fan-ownership' ),
							(int) prx3_setting( 'min_age_confirm', 18 )
						)
					),
				)
			);
		}
		if ( ! $terms_ok ) {
			self::back_with( array( 'prx3_error' => rawurlencode( __( 'Please accept the terms of membership.', 'fan-ownership' ) ) ) );
		}
		if ( ! $name || ! is_email( $email ) || strlen( $password ) < 8 ) {
			self::back_with( array( 'prx3_error' => rawurlencode( __( 'Please provide your name, a valid email address, and a password of at least 8 characters.', 'fan-ownership' ) ) ) );
		}
		// FO-105 AC4: existing email routes to sign-in.
		if ( email_exists( $email ) ) {
			self::back_with( array( 'prx3_error' => rawurlencode( __( 'An account already exists for that email address. Try signing in or recovering your password.', 'fan-ownership' ) ) ) );
		}

		$username = self::unique_username( $email );
		$user_id  = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $name,
				'role'         => 'subscriber', // Promoted to fan_owner on email verification + first share.
			)
		);
		if ( is_wp_error( $user_id ) ) {
			self::back_with( array( 'prx3_error' => rawurlencode( $user_id->get_error_message() ) ) );
		}

		update_user_meta( $user_id, 'prx3_adult_confirmed', time() );
		update_user_meta( $user_id, 'prx3_terms_accepted', time() );

		self::flag_possible_duplicates( $user_id, $email, $name );
		self::send_verification( $user_id );

		self::back_with( array( 'prx3_registered' => 'verify' ) );
	}

	/**
	 * FO-105 AC2: activation only after email verification.
	 *
	 * @param int $user_id User to send the verification email to.
	 */
	public static function send_verification( $user_id ) {
		$token = wp_generate_password( 32, false );
		update_user_meta( $user_id, 'prx3_email_token', wp_hash_password( $token ) );
		update_user_meta( $user_id, 'prx3_email_verified', '' );
		$user = get_userdata( $user_id );
		$link = add_query_arg(
			array(
				'prx3_verify' => $user_id,
				'token'       => $token,
			),
			home_url( '/' )
		);
		PRX3_Comms::send(
			$user->user_email,
			sprintf( /* translators: %s club name. */ __( 'Confirm your email — %s', 'fan-ownership' ), prx3_club_name() ),
			sprintf(
				/* translators: 1: name, 2: link. */
				__( "Hi %1\$s,\n\nConfirm your email address to activate your account:\n%2\$s\n\nOnce confirmed you can buy your shares and become an owner.", 'fan-ownership' ),
				$user->display_name,
				$link
			),
			'governance'
		);
	}

	/**
	 * Handle the email verification link: check the token, mark verified,
	 * sign the member in and send them to checkout.
	 */
	public static function maybe_handle_verification() {
		// Email-link flow: a nonce cannot exist in a link sent by email.
		// Authentication is the single-use hashed token checked below.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['prx3_verify'], $_GET['token'] ) ) {
			return;
		}
		$user_id = absint( $_GET['prx3_verify'] );
		$token   = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$hash = get_user_meta( $user_id, 'prx3_email_token', true );
		if ( ! $user_id || ! $hash || ! wp_check_password( $token, $hash ) ) {
			wp_die( esc_html__( 'This verification link is not valid. Please request a new one.', 'fan-ownership' ) );
		}
		update_user_meta( $user_id, 'prx3_email_verified', time() );
		delete_user_meta( $user_id, 'prx3_email_token' );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );
		prx3_touch_activity( $user_id );

		$checkout = PRX3_Shopify::checkout_url( $user_id );
		wp_safe_redirect( $checkout ? $checkout : home_url( '/?prx3_verified=1' ) );
		exit;
	}

	/**
	 * Whether a member has verified their email address.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function is_verified( $user_id ) {
		return (bool) get_user_meta( $user_id, 'prx3_email_verified', true );
	}

	/**
	 * FO-110 AC1: flag (never auto-block) near-duplicate registrations.
	 *
	 * @param int    $user_id Newly registered user.
	 * @param string $email   Registration email address.
	 * @param string $name    Display name given at registration.
	 */
	private static function flag_possible_duplicates( $user_id, $email, $name ) {
		$local    = strtolower( preg_replace( '/\+.*$/', '', strstr( $email, '@', true ) ) );
		$domain   = strtolower( substr( strrchr( $email, '@' ), 1 ) );
		$suspects = array();
		foreach ( get_users(
			array(
				'fields' => array( 'ID', 'user_email', 'display_name' ),
				'number' => 2000,
			)
		) as $u ) {
			if ( (int) $u->ID === (int) $user_id ) {
				continue;
			}
			$u_local  = strtolower( preg_replace( '/\+.*$/', '', strstr( $u->user_email, '@', true ) ) );
			$u_domain = strtolower( substr( strrchr( $u->user_email, '@' ), 1 ) );
			if ( ( $u_local === $local && $u_domain === $domain )
				|| ( 0 === strcasecmp( $u->display_name, $name ) && $u_domain === $domain ) ) {
				$suspects[] = (int) $u->ID;
			}
		}
		// Payment-identity matching joins at checkout (the commerce webhook adds
		// card fingerprint comparison via the prx3_payment_identity hook).
		if ( $suspects ) {
			update_user_meta( $user_id, 'prx3_duplicate_suspects', $suspects );
			PRX3_Audit::log( 'duplicate_flag', sprintf( 'User %d flagged as possible duplicate of %s', $user_id, implode( ',', $suspects ) ) );
		}
	}

	/**
	 * Derive a unique username from the local part of an email address.
	 *
	 * @param string $email Email address.
	 * @return string Unique username.
	 */
	private static function unique_username( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		$base = $base ? $base : 'owner';
		$name = $base;
		$i    = 1;
		while ( username_exists( $name ) ) {
			$name = $base . ( ++$i );
		}
		return $name;
	}

	/**
	 * Redirect back to the referring page with the given query args and exit.
	 *
	 * @param array $args Query args to append (e.g. prx3_error).
	 */
	private static function back_with( $args ) {
		$url = wp_get_referer() ? wp_get_referer() : home_url();
		wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'prx3_error', 'prx3_registered' ), $url ) ) );
		exit;
	}
}
