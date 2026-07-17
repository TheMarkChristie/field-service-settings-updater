<?php
/**
 * Community 365 theme functions.
 *
 * @package Community365
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COMMUNITY365_VERSION', '1.0.0' );

/**
 * Theme setup.
 */
function community365_setup() {
	load_theme_textdomain( 'community365', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 320,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	/*
	 * Tells the Syndicate Pro plugin that this theme renders its own
	 * source-attribution UI, so the plugin should not append its fallback box.
	 */
	add_theme_support( 'synpro-attribution' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'community365' ),
			'footer'  => __( 'Footer Menu', 'community365' ),
		)
	);

	add_image_size( 'c365-card', 640, 360, true );
}
add_action( 'after_setup_theme', 'community365_setup' );

/**
 * Enqueue styles and scripts.
 */
function community365_scripts() {
	wp_enqueue_style( 'community365-style', get_stylesheet_uri(), array(), COMMUNITY365_VERSION );
	wp_enqueue_script( 'community365-theme', get_template_directory_uri() . '/assets/js/theme.js', array(), COMMUNITY365_VERSION, true );

	if ( is_front_page() ) {
		wp_enqueue_script( 'community365-home', get_template_directory_uri() . '/assets/js/home.js', array(), COMMUNITY365_VERSION, true );
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	$accent = get_theme_mod( 'c365_accent_color', '#f97316' );
	if ( $accent && preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $accent ) ) {
		list( $r, $g, $b ) = community365_hex_to_rgb( $accent );
		$css = sprintf(
			':root { --c365-accent: %1$s; --c365-accent-soft: rgba(%2$d, %3$d, %4$d, 0.12); }',
			$accent,
			$r,
			$g,
			$b
		);
		wp_add_inline_style( 'community365-style', $css );
	}
}
add_action( 'wp_enqueue_scripts', 'community365_scripts' );

/**
 * Convert a hex colour to an [r, g, b] array.
 *
 * @param string $hex Hex colour.
 * @return int[]
 */
function community365_hex_to_rgb( $hex ) {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	return array(
		hexdec( substr( $hex, 0, 2 ) ),
		hexdec( substr( $hex, 2, 2 ) ),
		hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * Register footer widget areas.
 */
function community365_widgets_init() {
	for ( $i = 1; $i <= 3; $i++ ) {
		register_sidebar(
			array(
				'name'          => sprintf( /* translators: %d: widget area number. */ __( 'Footer %d', 'community365' ), $i ),
				'id'            => 'footer-' . $i,
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);
	}
}
add_action( 'widgets_init', 'community365_widgets_init' );

/**
 * Excerpt length and suffix.
 */
function community365_excerpt_length() {
	return 28;
}
add_filter( 'excerpt_length', 'community365_excerpt_length' );

function community365_excerpt_more() {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'community365_excerpt_more' );

/**
 * Branded login screen (also styles registration and lost-password —
 * they're all wp-login.php): black background, orange accent, site logo.
 */
function community365_login_styles() {
	$accent = get_theme_mod( 'c365_accent_color', '#f97316' );
	if ( ! preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $accent ) ) {
		$accent = '#f97316';
	}
	$logo = '';
	if ( has_custom_logo() ) {
		$logo = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'medium' );
	}
	?>
	<style>
		body.login { background: #0c0d12; }
		body.login #login h1 a {
			<?php if ( $logo ) : ?>
			background-image: url('<?php echo esc_url( $logo ); ?>');
			background-size: contain;
			width: 220px; height: 70px;
			<?php else : ?>
			background: none; text-indent: 0; width: auto; height: auto;
			font-size: 1.6rem; font-weight: 800; color: #f2f3f7; text-decoration: none;
			<?php endif; ?>
		}
		body.login form {
			background: #16181f; border: 1px solid #262a35; border-radius: 12px;
			box-shadow: 0 12px 40px rgba(0,0,0,.5);
		}
		body.login label { color: #f2f3f7; }
		body.login form .input, body.login input[type="text"], body.login input[type="password"], body.login input[type="email"] {
			background: #0c0d12; border: 1px solid #262a35; color: #f2f3f7; border-radius: 8px;
		}
		body.login form .input:focus, body.login input[type="text"]:focus, body.login input[type="password"]:focus, body.login input[type="email"]:focus {
			border-color: <?php echo esc_html( $accent ); ?>; box-shadow: 0 0 0 1px <?php echo esc_html( $accent ); ?>;
		}
		body.login .button-primary {
			background: <?php echo esc_html( $accent ); ?>; border-color: <?php echo esc_html( $accent ); ?>;
			border-radius: 8px; text-shadow: none; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
		}
		body.login .button-primary:hover, body.login .button-primary:focus { background: <?php echo esc_html( $accent ); ?>; filter: brightness(1.12); border-color: <?php echo esc_html( $accent ); ?>; }
		body.login .wp-login-lost-password, body.login #nav a, body.login #backtoblog a { color: #9aa1b0; }
		body.login #nav a:hover, body.login #backtoblog a:hover { color: <?php echo esc_html( $accent ); ?>; }
		body.login .message, body.login .notice, body.login #login_error {
			background: #16181f; border-left-color: <?php echo esc_html( $accent ); ?>; color: #f2f3f7; border-radius: 0 8px 8px 0;
		}
		body.login .privacy-policy-page-link a { color: #9aa1b0; }
		body.login input[type="checkbox"] { background: #0c0d12; border-color: #262a35; }
	</style>
	<?php
}
add_action( 'login_enqueue_scripts', 'community365_login_styles' );

function community365_login_headerurl() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'community365_login_headerurl' );

function community365_login_headertext() {
	return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'community365_login_headertext' );

require get_template_directory() . '/inc/template-tags.php';
require get_template_directory() . '/inc/customizer.php';
require get_template_directory() . '/inc/home-slots.php';
