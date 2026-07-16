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
}
add_action( 'customize_register', 'community365_customize_register' );
