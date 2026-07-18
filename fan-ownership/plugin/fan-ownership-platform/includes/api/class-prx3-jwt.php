<?php
/**
 * JWT for the native apps: HS256 access + refresh tokens, secure
 * storage app-side, revocation via a per-user token version.
 *
 * FO-301, T6. Password change / role removal / account closure bumps the
 * version, ending app access at the next request (FO-301 AC3).
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * HS256 JWT issue/verify for the native apps, with revocation via a
 * per-user token version (FO-301, T6).
 */
class PRX3_JWT {

	const ACCESS_TTL  = 3600;        // 1 hour.
	const REFRESH_TTL = 2592000;     // 30 days.

	/**
	 * The signing secret: a stored random value plus the site auth salt.
	 *
	 * @return string
	 */
	private static function secret() {
		$secret = get_option( 'prx3_jwt_secret' );
		if ( ! $secret ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( 'prx3_jwt_secret', $secret, false );
		}
		return $secret . wp_salt( 'auth' );
	}

	/**
	 * The user's current token version; tokens minted for older versions
	 * are rejected.
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	public static function token_version( $user_id ) {
		return (int) get_user_meta( $user_id, 'prx3_token_version', true );
	}

	public static function revoke_all( $user_id ) {
		update_user_meta( $user_id, 'prx3_token_version', self::token_version( $user_id ) + 1 );
	}

	public static function issue_pair( $user_id ) {
		return array(
			'access_token'  => self::encode(
				array(
					'sub' => $user_id,
					'typ' => 'access',
					'exp' => time() + self::ACCESS_TTL,
					'ver' => self::token_version( $user_id ),
				)
			),
			'refresh_token' => self::encode(
				array(
					'sub' => $user_id,
					'typ' => 'refresh',
					'exp' => time() + self::REFRESH_TTL,
					'ver' => self::token_version( $user_id ),
				)
			),
			'expires_in'    => self::ACCESS_TTL,
		);
	}

	public static function encode( $claims ) {
		$header  = self::b64(
			wp_json_encode(
				array(
					'alg' => 'HS256',
					'typ' => 'JWT',
				)
			)
		);
		$payload = self::b64( wp_json_encode( $claims ) );
		$sig     = self::b64( hash_hmac( 'sha256', $header . '.' . $payload, self::secret(), true ) );
		return $header . '.' . $payload . '.' . $sig;
	}

	/**
	 * @return array|WP_Error Claims.
	 */
	public static function decode( $token, $expected_type = 'access' ) {
		$parts = explode( '.', (string) $token );
		if ( 3 !== count( $parts ) ) {
			return new WP_Error( 'prx3_jwt', __( 'Malformed token.', 'fan-ownership' ), array( 'status' => 401 ) );
		}
		list( $header, $payload, $sig ) = $parts;
		$expected                       = self::b64( hash_hmac( 'sha256', $header . '.' . $payload, self::secret(), true ) );
		if ( ! hash_equals( $expected, $sig ) ) {
			return new WP_Error( 'prx3_jwt', __( 'Invalid token signature.', 'fan-ownership' ), array( 'status' => 401 ) );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- JWT payload decode (RFC 7515).
		$claims = json_decode( base64_decode( strtr( $payload, '-_', '+/' ) ), true );
		if ( ! is_array( $claims ) || ( $claims['exp'] ?? 0 ) < time() || ( $claims['typ'] ?? '' ) !== $expected_type ) {
			return new WP_Error( 'prx3_jwt', __( 'Token expired or wrong type.', 'fan-ownership' ), array( 'status' => 401 ) );
		}
		$user_id = (int) ( $claims['sub'] ?? 0 );
		if ( ! $user_id || (int) ( $claims['ver'] ?? -1 ) !== self::token_version( $user_id ) ) {
			return new WP_Error( 'prx3_jwt', __( 'Session revoked — please sign in again.', 'fan-ownership' ), array( 'status' => 401 ) );
		}
		return $claims;
	}

	private static function b64( $data ) {
		// Base64url is the JWT wire format (RFC 7515), not obfuscation.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
