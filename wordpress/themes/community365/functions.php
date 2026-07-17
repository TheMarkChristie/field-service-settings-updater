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

require get_template_directory() . '/inc/template-tags.php';
require get_template_directory() . '/inc/customizer.php';
