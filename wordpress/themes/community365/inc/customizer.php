<?php
/**
 * Customizer settings for Community 365.
 *
 * @package Community365
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register customizer settings.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 */
function community365_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'c365_theme_options',
		array(
			'title'    => __( 'Community 365 Options', 'community365' ),
			'priority' => 30,
		)
	);

	// Accent colour.
	$wp_customize->add_setting(
		'c365_accent_color',
		array(
			'default'           => '#4f46e5',
			'sanitize_callback' => 'sanitize_hex_color',
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			'c365_accent_color',
			array(
				'label'   => __( 'Accent colour', 'community365' ),
				'section' => 'c365_theme_options',
			)
		)
	);

	// Hero heading + intro.
	$wp_customize->add_setting(
		'c365_hero_heading',
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control(
		'c365_hero_heading',
		array(
			'label'       => __( 'Hero heading', 'community365' ),
			'description' => __( 'Shown on the home page. Leave empty to use the site title.', 'community365' ),
			'section'     => 'c365_theme_options',
			'type'        => 'text',
		)
	);

	$wp_customize->add_setting(
		'c365_hero_text',
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_textarea_field',
		)
	);
	$wp_customize->add_control(
		'c365_hero_text',
		array(
			'label'       => __( 'Hero intro text', 'community365' ),
			'description' => __( 'Shown under the hero heading. Leave empty to use the site tagline.', 'community365' ),
			'section'     => 'c365_theme_options',
			'type'        => 'textarea',
		)
	);

	// Show hero.
	$wp_customize->add_setting(
		'c365_show_hero',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'c365_show_hero',
		array(
			'label'   => __( 'Show hero section on the home page', 'community365' ),
			'section' => 'c365_theme_options',
			'type'    => 'checkbox',
		)
	);

	// Source badges.
	$wp_customize->add_setting(
		'c365_show_source_badges',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'c365_show_source_badges',
		array(
			'label'       => __( 'Show source badges on syndicated posts', 'community365' ),
			'description' => __( 'Badges link back to the blog each post was syndicated from.', 'community365' ),
			'section'     => 'c365_theme_options',
			'type'        => 'checkbox',
		)
	);

	// Footer text.
	$wp_customize->add_setting(
		'c365_footer_text',
		array(
			'default'           => '',
			'sanitize_callback' => 'wp_kses_post',
		)
	);
	$wp_customize->add_control(
		'c365_footer_text',
		array(
			'label'       => __( 'Footer credit text', 'community365' ),
			'description' => __( 'Leave empty for the default copyright line.', 'community365' ),
			'section'     => 'c365_theme_options',
			'type'        => 'textarea',
		)
	);

	/* ---------------- Post sidebar (calendar, advert, social, coffee) --- */
	$wp_customize->add_section(
		'c365_sidebar_options',
		array(
			'title'    => __( 'Community 365 Post Sidebar', 'community365' ),
			'priority' => 31,
		)
	);

	$wp_customize->add_setting(
		'c365_show_sidebar',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'c365_show_sidebar',
		array(
			'label'   => __( 'Show the sidebar on single posts', 'community365' ),
			'section' => 'c365_sidebar_options',
			'type'    => 'checkbox',
		)
	);

	// Advert: image + link, or raw HTML (HTML wins when both are set).
	$wp_customize->add_setting( 'c365_ad_image', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control(
		'c365_ad_image',
		array(
			'label'   => __( 'Advert image URL', 'community365' ),
			'section' => 'c365_sidebar_options',
			'type'    => 'url',
		)
	);
	$wp_customize->add_setting( 'c365_ad_link', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control(
		'c365_ad_link',
		array(
			'label'   => __( 'Advert click-through URL', 'community365' ),
			'section' => 'c365_sidebar_options',
			'type'    => 'url',
		)
	);
	$wp_customize->add_setting( 'c365_ad_html', array( 'default' => '', 'sanitize_callback' => 'wp_kses_post' ) );
	$wp_customize->add_control(
		'c365_ad_html',
		array(
			'label'       => __( 'Advert custom HTML (optional)', 'community365' ),
			'description' => __( 'Overrides the image advert when set. Script tags are stripped.', 'community365' ),
			'section'     => 'c365_sidebar_options',
			'type'        => 'textarea',
		)
	);

	// Three social buttons.
	for ( $i = 1; $i <= 3; $i++ ) {
		$wp_customize->add_setting( 'c365_social_' . $i, array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control(
			'c365_social_' . $i,
			array(
				/* translators: %d: button number. */
				'label'       => sprintf( __( 'Social button %d URL', 'community365' ), $i ),
				'description' => 1 === $i ? __( 'The button label is worked out from the URL (LinkedIn, X, Bluesky, Mastodon, YouTube, Facebook, Instagram, GitHub).', 'community365' ) : '',
				'section'     => 'c365_sidebar_options',
				'type'        => 'url',
			)
		);
	}

	// Buy Me a Coffee.
	$wp_customize->add_setting( 'c365_coffee_url', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control(
		'c365_coffee_url',
		array(
			'label'       => __( 'Buy Me a Coffee URL', 'community365' ),
			'description' => __( 'e.g. https://buymeacoffee.com/yourname — the button only shows when set.', 'community365' ),
			'section'     => 'c365_sidebar_options',
			'type'        => 'url',
		)
	);
}
add_action( 'customize_register', 'community365_customize_register' );
