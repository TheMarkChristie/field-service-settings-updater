<?php
/**
 * Community 365 theme functions.
 *
 * @package Community365
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COMMUNITY365_VERSION', '2.4.0' );

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

	// The theme styles the plugin's paid-content badge + disclosure banner
	// itself (the plugin would otherwise inject fallback styles).
	add_theme_support( 'synpro-paid' );

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

	wp_add_inline_style( 'community365-style', community365_brand_css() );
}
add_action( 'wp_enqueue_scripts', 'community365_scripts' );

/**
 * A theme-mod colour, validated as hex, with a fallback.
 *
 * @param string $key      Theme mod key.
 * @param string $fallback Default hex.
 * @return string
 */
function community365_color( $key, $fallback ) {
	$value = get_theme_mod( $key, $fallback );
	return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $value ) ? $value : $fallback;
}

/**
 * WCAG relative luminance of a hex colour (0 = black, 1 = white).
 *
 * @param string $hex Hex colour.
 * @return float
 */
function community365_luminance( $hex ) {
	$channels = array();
	foreach ( community365_hex_to_rgb( $hex ) as $channel ) {
		$c          = $channel / 255;
		$channels[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/**
 * Shift a hex colour towards white (positive $amount) or black (negative),
 * 0–1 scale. Used to derive card surfaces and borders from the background.
 *
 * @param string $hex    Hex colour.
 * @param float  $amount Mix amount.
 * @return string
 */
function community365_shift( $hex, $amount ) {
	$rgb    = community365_hex_to_rgb( $hex );
	$target = $amount >= 0 ? 255 : 0;
	$mix    = abs( $amount );
	foreach ( $rgb as $i => $channel ) {
		$rgb[ $i ] = (int) round( $channel + ( $target - $channel ) * $mix );
	}
	return sprintf( '#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2] );
}

/**
 * Build the CSS custom-property overrides from the Customizer branding:
 * six palette colours + font family/size, with readability safeguards —
 * button/badge text flips between white and black based on the primary
 * colour's luminance, and surfaces/body text follow the background so a
 * light background gets dark text automatically.
 *
 * @return string
 */
function community365_brand_css() {
	// Primary supersedes the legacy single accent setting but honours it.
	$legacy    = community365_color( 'c365_accent_color', '#f97316' );
	$primary   = community365_color( 'c365_color_primary', $legacy );
	$secondary = community365_color( 'c365_color_secondary', '#fdba74' );
	$tertiary  = community365_color( 'c365_color_tertiary', '#9aa1b2' );
	$link      = community365_color( 'c365_color_link', $primary );
	$visited   = community365_color( 'c365_color_link_visited', '#d97706' );
	$bg        = community365_color( 'c365_color_background', '#0c0d12' );

	list( $pr, $pg, $pb ) = community365_hex_to_rgb( $primary );

	// Text on primary-coloured elements: white or black, whichever contrasts
	// more (contrast = (L1 + 0.05) / (L2 + 0.05); the 0.179 cutover is where
	// white and black tie).
	$on_primary = community365_luminance( $primary ) > 0.179 ? '#111111' : '#ffffff';

	// Surfaces, borders, and body text derive from the background so any
	// background — dark or light — stays readable.
	$dark_bg = community365_luminance( $bg ) < 0.5;
	$surface = community365_shift( $bg, $dark_bg ? 0.05 : -0.04 );
	$border  = community365_shift( $bg, $dark_bg ? 0.13 : -0.13 );
	$text    = $dark_bg ? '#f2f3f7' : '#16181f';

	$stacks = array(
		'system'    => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", Arial, sans-serif',
		'helvetica' => '"Helvetica Neue", Helvetica, Arial, sans-serif',
		'verdana'   => 'Verdana, Geneva, "DejaVu Sans", sans-serif',
		'trebuchet' => '"Trebuchet MS", "Segoe UI", Tahoma, sans-serif',
		'georgia'   => 'Georgia, "Times New Roman", serif',
		'palatino'  => '"Palatino Linotype", Palatino, "Book Antiqua", Georgia, serif',
	);
	$family = get_theme_mod( 'c365_font_family', 'system' );
	$stack  = isset( $stacks[ $family ] ) ? $stacks[ $family ] : $stacks['system'];
	$size   = min( 20, max( 14, absint( get_theme_mod( 'c365_font_size', 17 ) ) ) );

	return sprintf(
		':root { --c365-accent: %1$s; --c365-accent-soft: rgba(%2$d, %3$d, %4$d, 0.14); --c365-accent-2: %5$s; --c365-on-accent: %6$s; --c365-link: %7$s; --c365-link-visited: %8$s; --c365-bg: %9$s; --c365-surface: %10$s; --c365-border: %11$s; --c365-text: %12$s; --c365-text-soft: %13$s; --c365-font: %14$s; --c365-font-size: %15$dpx; }',
		$primary,
		$pr,
		$pg,
		$pb,
		$secondary,
		$on_primary,
		$link,
		$visited,
		$bg,
		$surface,
		$border,
		$text,
		$tertiary,
		$stack,
		$size
	);
}

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
	$accent  = community365_color( 'c365_color_primary', community365_color( 'c365_accent_color', '#f97316' ) );
	$bg      = community365_color( 'c365_color_background', '#0c0d12' );
	$dark    = community365_luminance( $bg ) < 0.5;
	$surface = community365_shift( $bg, $dark ? 0.05 : -0.04 );
	$border  = community365_shift( $bg, $dark ? 0.13 : -0.13 );
	$text    = $dark ? '#f2f3f7' : '#16181f';
	$logo    = '';
	if ( has_custom_logo() ) {
		$logo = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'medium' );
	}
	?>
	<style>
		body.login { background: <?php echo esc_html( $bg ); ?>; }
		body.login #login h1 a {
			<?php if ( $logo ) : ?>
			background-image: url('<?php echo esc_url( $logo ); ?>');
			background-size: contain;
			width: 220px; height: 70px;
			<?php else : ?>
			background: none; text-indent: 0; width: auto; height: auto;
			font-size: 1.6rem; font-weight: 800; color: <?php echo esc_html( $text ); ?>; text-decoration: none;
			<?php endif; ?>
		}
		body.login form {
			background: <?php echo esc_html( $surface ); ?>; border: 1px solid <?php echo esc_html( $border ); ?>; border-radius: 12px;
			box-shadow: 0 12px 40px rgba(0,0,0,.5);
		}
		body.login label { color: <?php echo esc_html( $text ); ?>; }
		body.login form .input, body.login input[type="text"], body.login input[type="password"], body.login input[type="email"] {
			background: <?php echo esc_html( $bg ); ?>; border: 1px solid <?php echo esc_html( $border ); ?>; color: <?php echo esc_html( $text ); ?>; border-radius: 8px;
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
			background: <?php echo esc_html( $surface ); ?>; border-left-color: <?php echo esc_html( $accent ); ?>; color: <?php echo esc_html( $text ); ?>; border-radius: 0 8px 8px 0;
		}
		body.login .privacy-policy-page-link a { color: #9aa1b0; }
		body.login input[type="checkbox"] { background: <?php echo esc_html( $bg ); ?>; border-color: <?php echo esc_html( $border ); ?>; }
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
