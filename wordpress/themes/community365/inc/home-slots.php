<?php
/**
 * Home page slot system: 8 slots, each assigned one component from the
 * library (news main block, YouTube slider, popular posts, podcast slider,
 * events calendar, post blocks). Every slot carries the universal filter
 * set: category include/exclude, date window, author include/exclude, and
 * item counts.
 *
 * @package Community365
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Number of home page slots.
 */
define( 'C365_HOME_SLOTS', 8 );

/**
 * The component library.
 *
 * @return array key => [ label, template part suffix ]
 */
function community365_components() {
	return array(
		'none'    => array( __( '— Empty —', 'community365' ), '' ),
		'news'    => array( __( 'News main block', 'community365' ), 'news' ),
		'youtube' => array( __( 'YouTube slider', 'community365' ), 'slider-video' ),
		'popular' => array( __( 'Popular posts', 'community365' ), 'popular' ),
		'podcast' => array( __( 'Podcast slider', 'community365' ), 'slider-podcast' ),
		'events'  => array( __( 'Events calendar', 'community365' ), 'events' ),
		'blocks'  => array( __( 'Post blocks', 'community365' ), 'post-blocks' ),
	);
}

/**
 * Read one slot setting.
 *
 * @param int    $n       Slot number (1-8).
 * @param string $key     Setting key.
 * @param mixed  $default Default.
 * @return mixed
 */
function community365_slot( $n, $key, $default = '' ) {
	$value = get_theme_mod( 'c365_s' . (int) $n . '_' . $key, $default );
	// An emptied Customizer field means "use the component default" too.
	return ( '' === $value ) ? $default : $value;
}

/**
 * Build WP_Query args from a slot's universal filters.
 *
 * @param int    $n      Slot number.
 * @param string $suffix Optional key suffix for sub-areas (e.g. '_l' for the
 *                       news block's left column category/count overrides).
 * @return array
 */
function community365_slot_query_args( $n, $suffix = '' ) {
	$args = array(
		'post_status'         => 'publish',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	);

	$cat = (int) community365_slot( $n, 'cat' . $suffix, 0 );
	if ( ! $cat && $suffix ) {
		$cat = (int) community365_slot( $n, 'cat', 0 ); // Fall back to the slot-wide pick.
	}
	if ( $cat ) {
		$args['cat'] = $cat;
	}

	$cat_ex = (int) community365_slot( $n, 'cat_ex', 0 );
	if ( $cat_ex ) {
		$args['category__not_in'] = array( $cat_ex );
	}

	$author = (int) community365_slot( $n, 'author', 0 );
	if ( $author ) {
		$args['author'] = $author;
	}
	$author_ex = (int) community365_slot( $n, 'author_ex', 0 );
	if ( $author_ex ) {
		$args['author__not_in'] = array( $author_ex );
	}

	switch ( community365_slot( $n, 'date', 'any' ) ) {
		case 'today':
			$args['date_query'] = array( array( 'after' => 'today' ) );
			break;
		case 'week': // This week, not including today.
			$args['date_query'] = array(
				array(
					'after'     => 'monday this week',
					'before'    => 'today',
					'inclusive' => false,
				),
			);
			break;
		case 'lastweek':
			$args['date_query'] = array(
				array(
					'after'     => 'monday last week',
					'before'    => 'monday this week',
					'inclusive' => false,
				),
			);
			break;
		case 'month':
			$args['date_query'] = array( array( 'after' => 'first day of this month midnight' ) );
			break;
	}

	return $args;
}

/**
 * Convenience: run a slot query.
 *
 * @param int   $n         Slot number.
 * @param array $overrides Extra WP_Query args.
 * @param string $suffix   Sub-area suffix for cat/count.
 * @return WP_Query
 */
function community365_slot_query( $n, $overrides = array(), $suffix = '' ) {
	return new WP_Query( array_merge( community365_slot_query_args( $n, $suffix ), $overrides ) );
}

/**
 * Render all populated slots.
 */
function community365_render_home_slots() {
	$components = community365_components();
	for ( $n = 1; $n <= C365_HOME_SLOTS; $n++ ) {
		$component = community365_slot( $n, 'component', community365_slot_default( $n ) );
		if ( 'none' === $component || empty( $components[ $component ][1] ) ) {
			continue;
		}
		set_query_var( 'c365_slot', $n );
		echo '<section class="c365-slot c365-slot-' . esc_attr( $component ) . '" aria-label="' . esc_attr( $components[ $component ][0] ) . '">';
		$heading = community365_slot( $n, 'heading', '' );
		if ( $heading ) {
			echo '<h2 class="c365-home-heading c365-home-heading-accent">' . esc_html( $heading ) . '</h2>';
		}
		get_template_part( 'template-parts/home/' . $components[ $component ][1] );
		echo '</section>';
	}
}

/**
 * Out-of-the-box slot assignment (mirrors the live site's order).
 *
 * @param int $n Slot number.
 * @return string Component key.
 */
function community365_slot_default( $n ) {
	$defaults = array( 1 => 'news', 2 => 'youtube', 3 => 'popular', 4 => 'podcast', 5 => 'blocks', 6 => 'events' );
	return isset( $defaults[ $n ] ) ? $defaults[ $n ] : 'none';
}

/**
 * Customizer: one section per slot with the component picker, heading, the
 * universal filters, counts, and per-component extras.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function community365_home_slots_customize( $wp_customize ) {
	$component_choices = array();
	foreach ( community365_components() as $key => $spec ) {
		$component_choices[ $key ] = $spec[0];
	}

	$cat_choices = array( 0 => __( '— All categories —', 'community365' ) );
	foreach ( get_categories( array( 'hide_empty' => false ) ) as $category ) {
		$cat_choices[ $category->term_id ] = $category->name;
	}
	$cat_ex_choices    = array( 0 => __( '— Exclude none —', 'community365' ) ) + array_slice( $cat_choices, 1, null, true );
	$author_choices    = array( 0 => __( '— Any author —', 'community365' ) );
	$author_ex_choices = array( 0 => __( '— Exclude none —', 'community365' ) );
	foreach ( get_users( array( 'capability' => 'edit_posts', 'number' => 100, 'fields' => array( 'ID', 'display_name' ) ) ) as $author ) {
		$author_choices[ $author->ID ]    = $author->display_name;
		$author_ex_choices[ $author->ID ] = $author->display_name;
	}

	$date_choices = array(
		'any'      => __( 'Any date', 'community365' ),
		'today'    => __( 'Today', 'community365' ),
		'week'     => __( 'This week (not today)', 'community365' ),
		'lastweek' => __( 'Last week', 'community365' ),
		'month'    => __( 'This month', 'community365' ),
	);

	for ( $n = 1; $n <= C365_HOME_SLOTS; $n++ ) {
		$section = 'c365_home_slot_' . $n;
		$wp_customize->add_section(
			$section,
			array(
				/* translators: %d: slot number. */
				'title'    => sprintf( __( 'Home Slot %d', 'community365' ), $n ),
				'priority' => 40 + $n,
			)
		);

		$add = function ( $key, $label, $type, $choices = null, $default = '' ) use ( $wp_customize, $section, $n ) {
			$id       = 'c365_s' . $n . '_' . $key;
			$sanitize = 'select' === $type ? 'sanitize_text_field' : ( 'number' === $type ? 'absint' : 'sanitize_text_field' );
			$wp_customize->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => $sanitize ) );
			$control = array(
				'label'   => $label,
				'section' => $section,
				'type'    => $type,
			);
			if ( $choices ) {
				$control['choices'] = $choices;
			}
			$wp_customize->add_control( $id, $control );
		};

		$add( 'component', __( 'Component', 'community365' ), 'select', $component_choices, community365_slot_default( $n ) );
		$add( 'heading', __( 'Heading (optional)', 'community365' ), 'text' );

		// Universal filters.
		$add( 'cat', __( 'Category', 'community365' ), 'select', $cat_choices, 0 );
		$add( 'cat_ex', __( 'Exclude category', 'community365' ), 'select', $cat_ex_choices, 0 );
		$add( 'date', __( 'Date window', 'community365' ), 'select', $date_choices, 'any' );
		$add( 'author', __( 'Only this author', 'community365' ), 'select', $author_choices, 0 );
		$add( 'author_ex', __( 'Exclude author', 'community365' ), 'select', $author_ex_choices, 0 );
		// Default '' = each component's own default (sliders 5, popular 6,
		// post blocks 8) — a number here overrides all of them.
		$add( 'count', __( 'Items (main) — empty for the component default', 'community365' ), 'number', null, '' );

		// News block sub-areas.
		$add( 'cat_l', __( 'News: left column category', 'community365' ), 'select', $cat_choices, 0 );
		$add( 'count_l', __( 'News: left column items', 'community365' ), 'number', null, 3 );
		$add( 'cat_r', __( 'News: right column category', 'community365' ), 'select', $cat_choices, 0 );
		$add( 'count_r', __( 'News: right column items', 'community365' ), 'number', null, 6 );

		// Popular: content types.
		$add( 'type_post', __( 'Popular: include blogs (1/0)', 'community365' ), 'number', null, 1 );
		$add( 'type_podcast', __( 'Popular: include podcasts (1/0)', 'community365' ), 'number', null, 0 );
		$add( 'type_video', __( 'Popular: include videos (1/0)', 'community365' ), 'number', null, 0 );
		$add( 'type_event', __( 'Popular: include events (1/0)', 'community365' ), 'number', null, 0 );

		// Post blocks columns.
		for ( $col = 1; $col <= 3; $col++ ) {
			/* translators: %d: column number. */
			$add( 'col' . $col . '_cat', sprintf( __( 'Post blocks: column %d category', 'community365' ), $col ), 'select', $cat_choices, 0 );
		}
		$add( 'col_count', __( 'Post blocks: items per column', 'community365' ), 'number', null, 3 );
	}
}
add_action( 'customize_register', 'community365_home_slots_customize', 20 );
