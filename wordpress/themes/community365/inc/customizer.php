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

	// ------------------------------------------------------------------
	// Branding: logos, colour palette, fonts — one section for it all.
	// ------------------------------------------------------------------
	$wp_customize->add_section(
		'c365_branding',
		array(
			'title'       => __( 'Branding — logos, colours, fonts', 'community365' ),
			'description' => __( 'Everything that makes the site yours. Tip: keep text colours at a contrast ratio of at least 4.5:1 against the background so the site stays readable (and WCAG AA compliant).', 'community365' ),
			'priority'    => 25,
		)
	);

	// Website logo: reuse the core custom-logo uploader, but surface it here.
	$core_logo = $wp_customize->get_control( 'custom_logo' );
	if ( $core_logo ) {
		$core_logo->section  = 'c365_branding';
		$core_logo->priority = 1;
		$core_logo->label    = __( 'Website logo', 'community365' );
	}

	// The six palette colours. Defaults are the shipped black/orange/white
	// design — every default pairing passes WCAG AA contrast.
	$palette = array(
		'c365_color_primary'      => array( '#f97316', __( 'Primary colour', 'community365' ), __( 'Buttons, highlights, badges, the ticker. Button text automatically switches between white and black to stay readable on whatever you pick.', 'community365' ) ),
		'c365_color_secondary'    => array( '#fdba74', __( 'Secondary colour', 'community365' ), __( 'Secondary accents: tags, hover states, small flourishes.', 'community365' ) ),
		'c365_color_tertiary'     => array( '#9aa1b2', __( 'Tertiary colour', 'community365' ), __( 'Muted text: dates, bylines, help text, captions.', 'community365' ) ),
		'c365_color_link'         => array( '#f97316', __( 'Hyperlink colour', 'community365' ), '' ),
		'c365_color_link_visited' => array( '#d97706', __( 'Hyperlink colour — clicked (visited)', 'community365' ), '' ),
		'c365_color_background'   => array( '#0c0d12', __( 'Background colour', 'community365' ), __( 'The page background. Card/panel surfaces are derived from it automatically.', 'community365' ) ),
	);
	$palette_priority = 10;
	foreach ( $palette as $key => $spec ) {
		$wp_customize->add_setting(
			$key,
			array(
				'default'           => $spec[0],
				'sanitize_callback' => 'sanitize_hex_color',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				$key,
				array(
					'label'       => $spec[1],
					'description' => $spec[2],
					'section'     => 'c365_branding',
					'priority'    => $palette_priority++,
				)
			)
		);
	}

	// Fonts: bundled system stacks only (no external font requests — fast and
	// private). Sizes are a sensible readable range.
	$wp_customize->add_setting( 'c365_font_family', array( 'default' => 'system', 'sanitize_callback' => 'sanitize_key' ) );
	$wp_customize->add_control(
		'c365_font_family',
		array(
			'label'       => __( 'Font', 'community365' ),
			'description' => __( 'System font stacks — no external font downloads, so the site stays fast.', 'community365' ),
			'section'     => 'c365_branding',
			'type'        => 'select',
			'priority'    => 20,
			'choices'     => array(
				'system'    => __( 'System sans-serif (default)', 'community365' ),
				'helvetica' => __( 'Helvetica / Arial', 'community365' ),
				'verdana'   => __( 'Verdana — wide and clear', 'community365' ),
				'trebuchet' => __( 'Trebuchet MS — rounded', 'community365' ),
				'georgia'   => __( 'Georgia — serif', 'community365' ),
				'palatino'  => __( 'Palatino — bookish serif', 'community365' ),
			),
		)
	);
	$wp_customize->add_setting( 'c365_font_size', array( 'default' => 17, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control(
		'c365_font_size',
		array(
			'label'       => __( 'Base font size (px)', 'community365' ),
			'description' => __( 'Body text size, 14–20px. Headings scale with it.', 'community365' ),
			'section'     => 'c365_branding',
			'type'        => 'number',
			'priority'    => 21,
			'input_attrs' => array( 'min' => 14, 'max' => 20, 'step' => 1 ),
		)
	);

	// Back-compat: the old single accent setting still exists on upgraded
	// sites; the primary colour above supersedes it (and falls back to it).
	$wp_customize->add_setting(
		'c365_accent_color',
		array(
			'default'           => '#f97316',
			'sanitize_callback' => 'sanitize_hex_color',
			'transport'         => 'refresh',
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
			'description' => __( 'Shown on the blog-posts page (when your reading settings use a separate posts page). The front page itself is built from the Home Slots. Leave empty to use the site title.', 'community365' ),
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
			'label'   => __( 'Show hero section on the blog-posts page', 'community365' ),
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

	// Only the ticker and Join us strip live outside the 8-slot system —
	// everything else on the home page is configured per slot in the
	// "Home Slot 1–8" sections. (The pre-slot section toggles and category
	// pickers were removed: they no longer drove anything.)
	foreach ( array(
		'ticker' => __( 'Category ticker bar', 'community365' ),
		'join'   => __( 'Join us (social bars + digest signup)', 'community365' ),
	) as $c365_key => $c365_label ) {
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

	// Header sponsor: "Sponsored by" label + sponsor logo + link, shown next
	// to the website logo. Lives in the Branding section with the other logos.
	$wp_customize->add_setting( 'c365_sponsor_label', array( 'default' => __( 'Sponsored by', 'community365' ), 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control(
		'c365_sponsor_label',
		array(
			'label'       => __( 'Header sponsor — label', 'community365' ),
			'description' => __( 'The words shown before the sponsor logo (default "Sponsored by").', 'community365' ),
			'section'     => 'c365_branding',
			'type'        => 'text',
			'priority'    => 30,
		)
	);
	$wp_customize->add_setting( 'c365_sponsor_logo', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control(
		new WP_Customize_Image_Control(
			$wp_customize,
			'c365_sponsor_logo',
			array(
				'label'       => __( 'Header sponsor — logo', 'community365' ),
				'description' => __( 'Upload the sponsor’s logo. Leave empty to hide the sponsor slot.', 'community365' ),
				'section'     => 'c365_branding',
				'priority'    => 31,
			)
		)
	);
	$wp_customize->add_setting( 'c365_sponsor_link', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control(
		'c365_sponsor_link',
		array(
			'label'       => __( 'Header sponsor — link', 'community365' ),
			'description' => __( 'Where clicking the sponsor logo goes (optional).', 'community365' ),
			'section'     => 'c365_branding',
			'type'        => 'url',
			'priority'    => 32,
		)
	);
	// Advanced override: free-form HTML replaces the label + logo fields entirely.
	$wp_customize->add_setting( 'c365_sponsor_html', array( 'default' => '', 'sanitize_callback' => 'wp_kses_post' ) );
	$wp_customize->add_control(
		'c365_sponsor_html',
		array(
			'label'       => __( 'Header sponsor — custom HTML (advanced)', 'community365' ),
			'description' => __( 'If filled in, this HTML replaces the label + logo fields above.', 'community365' ),
			'section'     => 'c365_branding',
			'type'        => 'textarea',
			'priority'    => 33,
		)
	);
}
add_action( 'customize_register', 'community365_customize_register' );
