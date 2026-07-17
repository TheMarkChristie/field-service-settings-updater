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
			'default'           => '#f97316',
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

	/* ---------------- Home page sections ------------------------------- */
	$wp_customize->add_section(
		'c365_home_options',
		array(
			'title'    => __( 'Community 365 Home Page', 'community365' ),
			'priority' => 32,
		)
	);

	// Category choices shared by all pickers (0 = all categories).
	$c365_cat_choices = array( 0 => __( '— All categories —', 'community365' ) );
	foreach ( get_categories( array( 'hide_empty' => false ) ) as $c365_cat ) {
		$c365_cat_choices[ $c365_cat->term_id ] = $c365_cat->name . ' (' . $c365_cat->count . ')';
	}

	$c365_home_sections = array(
		'ticker'    => __( 'Category ticker bar', 'community365' ),
		'hero'      => __( 'Magazine hero (3 columns)', 'community365' ),
		'spotlight' => __( 'Featured banner', 'community365' ),
		'videos'    => __( 'Video spotlight', 'community365' ),
		'latest'    => __( 'Latest articles', 'community365' ),
		'blocks'    => __( 'Category blocks', 'community365' ),
		'headlines' => __( 'Top headlines', 'community365' ),
		'join'      => __( 'Join us (social bars)', 'community365' ),
		'events'    => __( 'Events calendar', 'community365' ),
	);
	foreach ( $c365_home_sections as $c365_key => $c365_label ) {
		$wp_customize->add_setting( 'c365_home_show_' . $c365_key, array( 'default' => true, 'sanitize_callback' => 'wp_validate_boolean' ) );
		$wp_customize->add_control(
			'c365_home_show_' . $c365_key,
			array(
				/* translators: %s: section name. */
				'label'   => sprintf( __( 'Show: %s', 'community365' ), $c365_label ),
				'section' => 'c365_home_options',
				'type'    => 'checkbox',
			)
		);
	}

	// Per-element category pickers.
	$c365_home_cats = array(
		'c365_home_left_cat'      => __( 'Hero left column — category', 'community365' ),
		'c365_home_right_cat'     => __( 'Hero right column — category', 'community365' ),
		'c365_home_spotlight_cat' => __( 'Featured banner — category', 'community365' ),
		'c365_home_latest_cat'    => __( 'Latest articles — category', 'community365' ),
		'c365_home_headlines_cat' => __( 'Top headlines — category', 'community365' ),
		'c365_home_block_cat_1'   => __( 'Category block 1', 'community365' ),
		'c365_home_block_cat_2'   => __( 'Category block 2', 'community365' ),
		'c365_home_block_cat_3'   => __( 'Category block 3', 'community365' ),
	);
	foreach ( $c365_home_cats as $c365_key => $c365_label ) {
		$wp_customize->add_setting( $c365_key, array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control(
			$c365_key,
			array(
				'label'   => $c365_label,
				'section' => 'c365_home_options',
				'type'    => 'select',
				'choices' => $c365_cat_choices,
			)
		);
	}

	// Video spotlight heading.
	$wp_customize->add_setting( 'c365_home_video_heading', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control(
		'c365_home_video_heading',
		array(
			'label'       => __( 'Video spotlight heading', 'community365' ),
			'description' => __( 'e.g. "How about a session from Scottish Summit?" Leave empty for "Latest videos".', 'community365' ),
			'section'     => 'c365_home_options',
			'type'        => 'text',
		)
	);

	// Follower-count labels for the Join us bars (URLs come from the sidebar settings).
	for ( $i = 1; $i <= 3; $i++ ) {
		$wp_customize->add_setting( 'c365_social_count_' . $i, array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control(
			'c365_social_count_' . $i,
			array(
				/* translators: %d: button number. */
				'label'       => sprintf( __( 'Join us — follower count for social %d', 'community365' ), $i ),
				'description' => 1 === $i ? __( 'Shown on the Join us bars, e.g. "2.1K". Uses the sidebar social URLs.', 'community365' ) : '',
				'section'     => 'c365_home_options',
				'type'        => 'text',
			)
		);
	}

	// Header sponsor: "Sponsored by" label + sponsor logo, next to the site logo.
	$wp_customize->add_setting( 'c365_sponsor_label', array( 'default' => __( 'Sponsored by', 'community365' ), 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control(
		'c365_sponsor_label',
		array(
			'label'       => __( 'Header sponsor — label', 'community365' ),
			'description' => __( 'The words shown before the sponsor logo (default "Sponsored by").', 'community365' ),
			'section'     => 'c365_home_options',
			'type'        => 'text',
		)
	);
	$wp_customize->add_setting( 'c365_sponsor_logo', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control(
		new WP_Customize_Image_Control(
			$wp_customize,
			'c365_sponsor_logo',
			array(
				'label'       => __( 'Header sponsor — logo', 'community365' ),
				'description' => __( 'The sponsor’s logo image. Leave empty to hide the sponsor slot.', 'community365' ),
				'section'     => 'c365_home_options',
			)
		)
	);
	$wp_customize->add_setting( 'c365_sponsor_link', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control(
		'c365_sponsor_link',
		array(
			'label'       => __( 'Header sponsor — link', 'community365' ),
			'description' => __( 'Where clicking the sponsor logo goes (optional).', 'community365' ),
			'section'     => 'c365_home_options',
			'type'        => 'url',
		)
	);
	// Advanced override: free-form HTML replaces the label + logo fields entirely.
	$wp_customize->add_setting( 'c365_sponsor_html', array( 'default' => '', 'sanitize_callback' => 'wp_kses_post' ) );
	$wp_customize->add_control(
		'c365_sponsor_html',
		array(
			'label'       => __( 'Header sponsor — custom HTML (advanced)', 'community365' ),
			'description' => __( 'If filled in, this HTML replaces the label + logo fields above.', 'community365' ),
			'section'     => 'c365_home_options',
			'type'        => 'textarea',
		)
	);
}
add_action( 'customize_register', 'community365_customize_register' );
