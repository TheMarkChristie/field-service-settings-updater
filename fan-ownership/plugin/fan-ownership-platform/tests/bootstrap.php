<?php
/**
 * Test bootstrap: a dependency-free WordPress shim layer so the money
 * and vote paths (T35) run under test without a WordPress install.
 * Run via: php tests/run-tests.php
 *
 * @package FanOwnershipPlatform
 */

error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

/* ---------------- Test state ---------------- */

function prx3_test_reset() {
	$GLOBALS['prx3_t'] = array(
		'options'   => array(),
		'user_meta' => array(),
		'post_meta' => array(),
		'users'     => array(),
		'caps'      => array(),
		'actions'   => array(),
		'hooks'     => array(),
		'audit'     => array(),
		'current'   => 0,
	);
	$GLOBALS['wpdb'] = new PRX3_Fake_WPDB();
	$_POST           = array();
}

class PRX3_Test_User {
	public $ID;
	public $user_email;
	public $user_login;
	public $user_nicename;
	public $user_registered = '2026-01-01 00:00:00';
	public $first_name      = 'Test';
	public $last_name;
	public $display_name;
	public $roles = array();
	public function __construct( $id, $fields ) {
		$this->ID           = $id;
		$this->user_email   = "user{$id}@example.test";
		$this->last_name    = "User{$id}";
		$this->display_name = "Test User{$id}";
		foreach ( $fields as $key => $value ) {
			$this->$key = $value;
		}
	}
	public function add_role( $role ) {
		$this->roles[] = $role;
	}
	public function remove_role( $role ) {
		$this->roles = array_values( array_diff( $this->roles, array( $role ) ) );
	}
	public function set_role( $role ) {
		$this->roles = $role ? array( $role ) : array();
	}
}

function prx3_test_user( $id, $fields = array() ) {
	$user                              = new PRX3_Test_User( $id, $fields );
	$GLOBALS['prx3_t']['users'][ $id ] = $user;
	return $user;
}

/* ---------------- WP function shims ---------------- */

function get_option( $key, $default_value = false ) {
	return array_key_exists( $key, $GLOBALS['prx3_t']['options'] ) ? $GLOBALS['prx3_t']['options'][ $key ] : $default_value;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['prx3_t']['options'][ $key ] = $value;
	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['prx3_t']['options'][ $key ] );
	return true;
}

function get_user_meta( $user_id, $key, $single = false ) {
	$store = isset( $GLOBALS['prx3_t']['user_meta'][ $user_id ][ $key ] ) ? $GLOBALS['prx3_t']['user_meta'][ $user_id ][ $key ] : null;
	if ( null === $store ) {
		return $single ? '' : array();
	}
	return $single ? $store : array( $store );
}
function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['prx3_t']['user_meta'][ $user_id ][ $key ] = $value;
	return true;
}
function delete_user_meta( $user_id, $key ) {
	unset( $GLOBALS['prx3_t']['user_meta'][ $user_id ][ $key ] );
	return true;
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	if ( '' === $key ) {
		$all = isset( $GLOBALS['prx3_t']['post_meta'][ $post_id ] ) ? $GLOBALS['prx3_t']['post_meta'][ $post_id ] : array();
		$out = array();
		foreach ( $all as $meta_key => $value ) {
			$out[ $meta_key ] = array( $value );
		}
		return $out;
	}
	$store = isset( $GLOBALS['prx3_t']['post_meta'][ $post_id ][ $key ] ) ? $GLOBALS['prx3_t']['post_meta'][ $post_id ][ $key ] : null;
	if ( null === $store ) {
		return $single ? '' : array();
	}
	return $single ? $store : array( $store );
}
function maybe_unserialize( $value ) {
	return $value;
}
function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . $path;
}
function get_posts( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['prx3_t_posts'] as $id => $post ) {
		if ( isset( $args['post_type'] ) && $post['post_type'] !== $args['post_type'] ) {
			continue;
		}
		if ( isset( $args['post_status'] ) && is_array( $args['post_status'] ) && ! in_array( $post['post_status'] ?? 'draft', $args['post_status'], true ) ) {
			continue;
		}
		if ( isset( $args['meta_key'], $args['meta_value'] ) && (string) get_post_meta( $id, $args['meta_key'], true ) !== (string) $args['meta_value'] ) {
			continue;
		}
		$out[] = (object) array(
			'ID'           => $id,
			'post_title'   => $post['post_title'] ?? '',
			'post_status'  => $post['post_status'] ?? 'draft',
			'post_content' => $post['post_content'] ?? '',
			'post_type'    => $post['post_type'],
		);
	}
	$per  = isset( $args['posts_per_page'] ) && $args['posts_per_page'] > 0 ? (int) $args['posts_per_page'] : count( $out );
	$page = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
	return array_slice( $out, ( $page - 1 ) * $per, $per );
}
function get_post( $post_id ) {
	if ( ! isset( $GLOBALS['prx3_t_posts'][ (int) $post_id ] ) ) {
		return null;
	}
	$post = $GLOBALS['prx3_t_posts'][ (int) $post_id ];
	return (object) array(
		'ID'           => (int) $post_id,
		'post_title'   => $post['post_title'] ?? '',
		'post_content' => $post['post_content'] ?? '',
		'post_type'    => $post['post_type'] ?? 'post',
		'post_status'  => $post['post_status'] ?? 'draft',
	);
}
function get_post_status( $post_id ) {
	$post = get_post( $post_id );
	return $post ? $post->post_status : false;
}
function get_the_title( $post_id ) {
	$post = get_post( $post_id );
	return $post ? $post->post_title : '';
}
function term_exists( $term, $taxonomy = '' ) {
	return 0;
}
function wp_insert_term( $name, $taxonomy, $args = array() ) {
	return array( 'term_id' => 1 );
}
function get_terms( $args = array() ) {
	return array();
}
$GLOBALS['prx3_t_comments'] = array();
function wp_insert_comment( $data ) {
	static $next = 9000;
	$id                                = ++$next;
	$data['comment_ID']                = $id;
	$GLOBALS['prx3_t_comments'][ $id ] = $data;
	return $id;
}
function sanitize_hex_color( $color ) {
	return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', (string) $color ) ? $color : null;
}
function get_comments( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['prx3_t_comments'] as $comment ) {
		if ( isset( $args['post_id'] ) && (int) ( $comment['comment_post_ID'] ?? 0 ) !== (int) $args['post_id'] ) {
			continue;
		}
		if ( isset( $args['status'] ) && 'approve' === $args['status'] && isset( $comment['comment_approved'] ) && 1 !== (int) $comment['comment_approved'] ) {
			continue;
		}
		$out[] = $comment;
	}
	return $out;
}
function wp_html_excerpt( $text, $length, $more = '' ) {
	return strlen( $text ) > $length ? substr( $text, 0, $length ) . $more : $text;
}
function get_comments_number( $post_id ) {
	$n = 0;
	foreach ( $GLOBALS['prx3_t_comments'] as $comment ) {
		if ( (int) ( $comment['comment_post_ID'] ?? 0 ) === (int) $post_id ) {
			++$n;
		}
	}
	return $n;
}
function get_edit_post_link( $post_id, $context = 'display' ) {
	return 'https://example.test/wp-admin/post.php?post=' . (int) $post_id . '&action=edit';
}
function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['prx3_t']['post_meta'][ $post_id ][ $key ] = $value;
	return true;
}

function get_userdata( $user_id ) {
	return isset( $GLOBALS['prx3_t']['users'][ $user_id ] ) ? $GLOBALS['prx3_t']['users'][ $user_id ] : false;
}
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['prx3_t']['users'] as $user ) {
		if ( 'email' === $field && 0 === strcasecmp( $user->user_email, $value ) ) {
			return $user;
		}
		if ( ( 'id' === $field || 'ID' === $field ) && (int) $value === (int) $user->ID ) {
			return $user;
		}
		if ( 'login' === $field && isset( $user->user_login ) && 0 === strcasecmp( $user->user_login, $value ) ) {
			return $user;
		}
		if ( 'slug' === $field && isset( $user->user_nicename ) && 0 === strcasecmp( $user->user_nicename, $value ) ) {
			return $user;
		}
	}
	return false;
}
function get_users( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['prx3_t']['users'] as $user ) {
		if ( isset( $args['meta_key'], $args['meta_value'] ) ) {
			$meta = get_user_meta( $user->ID, $args['meta_key'], true );
			if ( (string) $meta !== (string) $args['meta_value'] ) {
				continue;
			}
		}
		$out[] = ( isset( $args['fields'] ) && 'ID' === $args['fields'] ) ? $user->ID : $user;
		if ( isset( $args['number'] ) && $args['number'] > 0 && count( $out ) >= $args['number'] ) {
			break;
		}
	}
	return $out;
}
function user_can( $user, $cap ) {
	$id = is_object( $user ) ? $user->ID : (int) $user;
	return ! empty( $GLOBALS['prx3_t']['caps'][ $id ][ $cap ] );
}
function current_user_can( $cap ) {
	return user_can( $GLOBALS['prx3_t']['current'], $cap );
}
function get_current_user_id() {
	return $GLOBALS['prx3_t']['current'];
}
function is_user_logged_in() {
	return (bool) $GLOBALS['prx3_t']['current'];
}

function add_action( $hook, $cb = null, $priority = 10, $args = 1 ) {
	$GLOBALS['prx3_t']['hooks'][ $hook ][] = $cb;
}
function remove_action( $hook, $callback = null, $priority = 10 ) {
	return true;
}
function add_filter( $hook, $cb = null, $priority = 10, $args = 1 ) {
	$GLOBALS['prx3_t']['hooks'][ $hook ][] = $cb;
}
function do_action( $hook, ...$args ) {
	$GLOBALS['prx3_t']['actions'][] = array( $hook, $args );
	foreach ( (array) ( isset( $GLOBALS['prx3_t']['hooks'][ $hook ] ) ? $GLOBALS['prx3_t']['hooks'][ $hook ] : array() ) as $cb ) {
		if ( is_callable( $cb ) ) {
			call_user_func_array( $cb, $args );
		}
	}
}
function apply_filters( $hook, $value, ...$args ) {
	foreach ( (array) ( isset( $GLOBALS['prx3_t']['hooks'][ $hook ] ) ? $GLOBALS['prx3_t']['hooks'][ $hook ] : array() ) as $cb ) {
		if ( is_callable( $cb ) ) {
			$value = call_user_func_array( $cb, array_merge( array( $value ), $args ) );
		}
	}
	return $value;
}

// phpcs-style i18n/escaping shims: identity is fine under test.
function _n( $single, $plural, $number, $domain = null ) {
	return 1 === (int) $number ? $single : $plural;
}
function get_term_link( $term ) {
	return 'https://example.test/board/' . ( is_object( $term ) ? $term->slug : $term ) . '/';
}
function __( $text, $domain = null ) {
	return $text;
}
function esc_html__( $text, $domain = null ) {
	return $text;
}
function esc_attr__( $text, $domain = null ) {
	return $text;
}
function esc_html( $text ) {
	return $text;
}
function esc_attr( $text ) {
	return $text;
}
function esc_url( $url ) {
	return $url;
}
function esc_url_raw( $url ) {
	return $url;
}
function wp_kses_post( $text ) {
	return $text;
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_text_field( $text ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $text ) ) );
}
function sanitize_email( $email ) {
	return filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? (string) $email : '';
}
function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}
function wp_unslash( $value ) {
	return $value;
}
function absint( $n ) {
	return abs( (int) $n );
}
function wp_json_encode( $value ) {
	return json_encode( $value );
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, $decimals );
}
function current_time( $type ) {
	return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time();
}
function wp_cache_delete( $key, $group = '' ) {
	return true;
}
function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	return substr( str_shuffle( str_repeat( 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 3 ) ), 0, $length );
}
function rest_ensure_response( $value ) {
	return $value;
}
function register_rest_route( ...$args ) {
	return true;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	$field = '<input type="hidden" name="' . $name . '" value="test-nonce">';
	if ( $display ) {
		echo $field; // phpcs:ignore
	}
	return $field;
}
function add_query_arg( ...$args ) {
	return 'https://example.test/page/?args';
}
function remove_query_arg( $keys, $url = false ) {
	return 'https://example.test/page/';
}
function checked( $checked, $current = true, $display = true ) {
	$out = (string) $checked === (string) $current ? " checked='checked'" : '';
	if ( $display ) {
		echo $out; // phpcs:ignore
	}
	return $out;
}
function selected( $selected, $current = true, $display = true ) {
	$out = (string) $selected === (string) $current ? " selected='selected'" : '';
	if ( $display ) {
		echo $out; // phpcs:ignore
	}
	return $out;
}
function wpautop( $text ) {
	return '<p>' . str_replace( "\n\n", '</p><p>', (string) $text ) . '</p>';
}
$GLOBALS['prx3_t_query'] = array( 'singular' => '', 'id' => 0 );
function is_admin() {
	return false;
}
function is_singular( $types = '' ) {
	$current = $GLOBALS['prx3_t_query']['singular'];
	if ( ! $current ) {
		return false;
	}
	return ! $types || in_array( $current, (array) $types, true );
}
function get_queried_object_id() {
	return (int) $GLOBALS['prx3_t_query']['id'];
}
function get_the_ID() {
	return (int) $GLOBALS['prx3_t_query']['id'];
}
function date_i18n( $format, $timestamp = null ) {
	return gmdate( $format, $timestamp ? $timestamp : time() );
}
function wp_date( $format, $timestamp = null ) {
	return gmdate( $format, $timestamp ? $timestamp : time() );
}
function wp_nonce_url( $url, $action = -1 ) {
	return $url . '&_wpnonce=test';
}
function wp_login_url( $redirect = '' ) {
	return 'https://example.test/wp-login.php';
}
function shortcode_atts( $defaults, $atts ) {
	return array_merge( $defaults, array_intersect_key( (array) $atts, $defaults ) );
}
function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_js( $text ) {
	return addslashes( (string) $text );
}
function esc_html_e( $text, $domain = null ) {
	echo esc_html( $text ); // phpcs:ignore
}
function wp_enqueue_style( $handle ) {}
function wp_enqueue_script( $handle ) {}
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['prx3_t']['http'][] = array( $url, $args );
	$code = isset( $GLOBALS['prx3_t']['http_response_code'] ) ? $GLOBALS['prx3_t']['http_response_code'] : 200;
	return array( 'response' => array( 'code' => $code ) );
}
function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
}

$GLOBALS['prx3_t_posts'] = array();
function wp_insert_post( $args, $wp_error = false ) {
	static $next = 1000;
	$id                            = ++$next;
	$args['ID']                    = $id;
	$GLOBALS['prx3_t_posts'][ $id ] = $args;
	return $id;
}
function wp_delete_post( $post_id, $force = false ) {
	unset( $GLOBALS['prx3_t_posts'][ (int) $post_id ], $GLOBALS['prx3_t']['post_meta'][ (int) $post_id ] );
	return true;
}
function wp_delete_user( $user_id ) {
	unset( $GLOBALS['prx3_t']['users'][ (int) $user_id ], $GLOBALS['prx3_t']['user_meta'][ (int) $user_id ] );
	return true;
}
function delete_transient( $key ) {
	return delete_option( $key );
}
function set_transient( $key, $value, $ttl = 0 ) {
	return update_option( $key, $value );
}
function get_transient( $key ) {
	return get_option( $key );
}
function wp_update_post( $args, $wp_error = false ) {
	$id = (int) ( $args['ID'] ?? 0 );
	if ( ! isset( $GLOBALS['prx3_t_posts'][ $id ] ) ) {
		return new WP_Error( 'invalid_post', 'Invalid post ID.' );
	}
	$GLOBALS['prx3_t_posts'][ $id ] = array_merge( $GLOBALS['prx3_t_posts'][ $id ], $args );
	return $id;
}
function get_post_type( $id ) {
	return $GLOBALS['prx3_t_posts'][ (int) $id ]['post_type'] ?? false;
}
function get_permalink( $id ) {
	return 'https://example.test/?p=' . (int) $id;
}
function taxonomy_exists( $taxonomy ) {
	return false;
}
function wp_set_object_terms( $post_id, $terms, $taxonomy ) {
	return array();
}
function sanitize_user( $username, $strict = false ) {
	return preg_replace( '/[^a-z0-9._@-]/i', '', (string) $username );
}
function wp_insert_user( $args ) {
	static $next = 500;
	$id   = ++$next;
	$user = prx3_test_user(
		$id,
		array(
			'user_email'   => $args['user_email'],
			'display_name' => $args['display_name'] ?? $args['user_email'],
		)
	);
	$user->roles = array( $args['role'] ?? 'subscriber' );
	return $id;
}

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/** Minimal request double for REST route callbacks. */
class PRX3_Test_Request {
	private $json;
	private $params;
	private $headers;
	public function __construct( $json = array(), $params = array(), $headers = array() ) {
		$this->json    = $json;
		$this->params  = $params;
		$this->headers = $headers;
	}
	public function get_json_params() {
		return $this->json;
	}
	public function get_param( $key ) {
		return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
	}
	public function get_header( $key ) {
		return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : '';
	}
	public function get_body() {
		return json_encode( $this->json );
	}
}

/* ---------------- Fake wpdb (ballot votes + register + options seq) ---------------- */

class PRX3_Fake_WPDB {
	public $prefix  = 'wp_';
	public $options = 'wp_options';
	public $rows    = array(); // table => rows.
	private $auto   = array();

	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		foreach ( $args as $arg ) {
			$replacement = is_numeric( $arg ) ? (string) $arg : "'" . $arg . "'";
			$sql         = preg_replace( '/%[dsf]/', $replacement, $sql, 1 );
		}
		return $sql;
	}

	public function insert( $table, $data, $formats = null ) {
		if ( false !== strpos( $table, 'prx3_ballot_votes' ) ) {
			foreach ( $this->table( $table ) as $row ) {
				if ( (int) $row['ballot_id'] === (int) $data['ballot_id'] && (int) $row['user_id'] === (int) $data['user_id'] ) {
					return false; // Unique key (ballot, user).
				}
			}
		}
		$this->auto[ $table ]    = isset( $this->auto[ $table ] ) ? $this->auto[ $table ] + 1 : 1;
		$data['id']              = $this->auto[ $table ];
		$this->rows[ $table ][]  = $data;
		return 1;
	}

	public function update( $table, $data, $where, $formats = null, $where_formats = null ) {
		foreach ( $this->table( $table ) as $i => $row ) {
			$hit = true;
			foreach ( $where as $key => $value ) {
				if ( (string) $row[ $key ] !== (string) $value ) {
					$hit = false;
				}
			}
			if ( $hit ) {
				$this->rows[ $table ][ $i ] = array_merge( $row, $data );
			}
		}
		return 1;
	}

	public function query( $sql ) {
		if ( false !== strpos( $sql, 'prx3_owner_seq' ) ) {
			$seq = (int) get_option( 'prx3_owner_seq_test', 0 ) + 1;
			update_option( 'prx3_owner_seq_test', $seq );
		}
		return 1;
	}

	public function get_var( $sql ) {
		if ( false !== strpos( $sql, 'prx3_owner_seq' ) ) {
			return (string) get_option( 'prx3_owner_seq_test', 0 );
		}
		return null;
	}

	public function get_row( $sql, $output = OBJECT ) {
		if ( preg_match( '/prx3_ballot_votes WHERE ballot_id = (\d+) AND user_id = (\d+)/', $sql, $m ) ) {
			foreach ( $this->table( $this->prefix . 'prx3_ballot_votes' ) as $row ) {
				if ( (int) $row['ballot_id'] === (int) $m[1] && (int) $row['user_id'] === (int) $m[2] ) {
					return ARRAY_A === $output ? $row : (object) $row;
				}
			}
		}
		return null;
	}

	public function get_results( $sql, $output = OBJECT ) {
		if ( preg_match( '/SUM\(weight\).*prx3_ballot_votes WHERE ballot_id = (\d+) GROUP BY choice/s', $sql, $m ) ) {
			$groups = array();
			foreach ( $this->table( $this->prefix . 'prx3_ballot_votes' ) as $row ) {
				if ( (int) $row['ballot_id'] !== (int) $m[1] ) {
					continue;
				}
				$choice = (int) $row['choice'];
				if ( ! isset( $groups[ $choice ] ) ) {
					$groups[ $choice ] = array(
						'choice'  => $choice,
						'votes'   => 0,
						'members' => 0,
					);
				}
				$groups[ $choice ]['votes']   += (int) $row['weight'];
				$groups[ $choice ]['members'] += 1;
			}
			return array_values( $groups );
		}
		if ( preg_match( '/prx3_ballot_votes WHERE user_id = (\d+)/', $sql, $m ) ) {
			$out = array();
			foreach ( $this->table( $this->prefix . 'prx3_ballot_votes' ) as $row ) {
				if ( (int) $row['user_id'] === (int) $m[1] ) {
					$out[] = ARRAY_A === $output ? $row : (object) $row;
				}
			}
			return $out;
		}
		return array();
	}

	private function table( $table ) {
		return isset( $this->rows[ $table ] ) ? $this->rows[ $table ] : array();
	}
}
define( 'OBJECT', 'OBJECT' );
define( 'ARRAY_A', 'ARRAY_A' );

/* ---------------- Module shims (collaborators not under test) ---------------- */

class PRX3_Audit {
	public static function log( $event, $message ) {
		$GLOBALS['prx3_t']['audit'][] = array( $event, $message );
	}
}
class PRX3_Register {
	public static $records = array();
	public static function record( $user_id, $event, $shares, $source, $context = array() ) {
		self::$records[] = compact( 'user_id', 'event', 'shares', 'source', 'context' );
	}
}
class PRX3_Comms {
	public static $sent = array();
	public static function send( $to, $subject, $body, $category = '' ) {
		self::$sent[] = compact( 'to', 'subject', 'body', 'category' );
		return true;
	}
}
class PRX3_Moderation {
	public static function is_muted( $user_id ) {
		return ! empty( $GLOBALS['prx3_t']['user_meta'][ $user_id ]['prx3_muted'] );
	}
}
class PRX3_Access {
	public static function gate_content( $content ) {
		return '<div class="prx3-notice prx3-notice--join">JOIN-GATE</div>';
	}
}
class PRX3_Ideas {
	public static function threshold_count() {
		return 5;
	}
}
class PRX3_Meetings {
	public static function upcoming( $limit = 0 ) {
		return array();
	}
	public static function member_ics_url( $user_id ) {
		return 'https://example.test/calendar.ics';
	}
	public static function rsvp_count( $meeting_id ) {
		return 0;
	}
}
class PRX3_Onboarding {
	public static function mark_complete( $user_id, $step ) {}
}
class PRX3_Badges {
	public static function member_badges( $user_id ) {
		return (array) get_user_meta( $user_id, 'prx3_badges', true );
	}
}

// Patch WP_User double methods used by grant/surrender.
function prx3_test_add_user_role_methods() {}

/* ---------------- Load the code under test ---------------- */

prx3_test_reset();

$prx3_base = dirname( __DIR__ );
require $prx3_base . '/includes/helpers.php';
require $prx3_base . '/includes/class-prx3-config.php';
require $prx3_base . '/includes/class-prx3-shares.php';
require $prx3_base . '/includes/class-prx3-ballots.php';
require $prx3_base . '/includes/class-prx3-agreements.php';
require $prx3_base . '/includes/class-prx3-sync.php';
require $prx3_base . '/includes/class-prx3-gifts.php';
require $prx3_base . '/includes/class-prx3-shopify.php';
require $prx3_base . '/includes/api/class-prx3-data-api.php';
require $prx3_base . '/includes/class-prx3-forum.php';
require $prx3_base . '/includes/class-prx3-social.php';
require $prx3_base . '/includes/class-prx3-pages.php';
require $prx3_base . '/includes/class-prx3-shortcodes.php';

/* ---------------- Assertions ---------------- */

$GLOBALS['prx3_test_results'] = array();

function t_ok( $cond, $label ) {
	$GLOBALS['prx3_test_results'][] = array( (bool) $cond, $label );
	if ( ! $cond ) {
		fwrite( STDERR, "  FAIL: {$label}\n" );
	}
}
function t_eq( $actual, $expected, $label ) {
	$pass = $actual === $expected;
	t_ok( $pass, $pass ? $label : $label . ' — got ' . var_export( $actual, true ) . ', expected ' . var_export( $expected, true ) );
}
function t_error_code( $thing, $code, $label ) {
	t_ok( is_wp_error( $thing ) && $thing->get_error_code() === $code, $label . ( is_wp_error( $thing ) ? '' : ' — no error returned' ) );
}
