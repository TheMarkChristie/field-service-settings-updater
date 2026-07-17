<?php
/**
 * Header template.
 *
 * @package Community365
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="screen-reader-text" href="#c365-content"><?php esc_html_e( 'Skip to content', 'community365' ); ?></a>

<header class="site-header">
	<div class="c365-container">
		<div class="site-branding">
			<?php
			if ( has_custom_logo() ) {
				the_custom_logo();
			}
			?>
			<p class="site-title">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?><span class="c365-dot">.</span></a>
			</p>
			<?php
			$c365_sponsor_html = get_theme_mod( 'c365_sponsor_html', '' );
			$c365_sponsor_logo = get_theme_mod( 'c365_sponsor_logo', '' );
			if ( $c365_sponsor_html ) :
				?>
				<div class="c365-sponsor"><?php echo wp_kses_post( $c365_sponsor_html ); ?></div>
			<?php elseif ( $c365_sponsor_logo ) : ?>
				<div class="c365-sponsor">
					<span class="c365-sponsor-label"><?php echo esc_html( get_theme_mod( 'c365_sponsor_label', __( 'Sponsored by', 'community365' ) ) ); ?></span>
					<?php $c365_sponsor_link = get_theme_mod( 'c365_sponsor_link', '' ); ?>
					<?php if ( $c365_sponsor_link ) : ?>
						<a href="<?php echo esc_url( $c365_sponsor_link ); ?>" rel="external noopener sponsored" target="_blank"><img src="<?php echo esc_url( $c365_sponsor_logo ); ?>" alt="<?php esc_attr_e( 'Sponsor logo', 'community365' ); ?>"></a>
					<?php else : ?>
						<img src="<?php echo esc_url( $c365_sponsor_logo ); ?>" alt="<?php esc_attr_e( 'Sponsor logo', 'community365' ); ?>">
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<button class="menu-toggle" aria-controls="site-navigation" aria-expanded="false">
			<span aria-hidden="true">&#9776;</span> <?php esc_html_e( 'Menu', 'community365' ); ?>
		</button>

		<nav id="site-navigation" class="main-navigation" aria-label="<?php esc_attr_e( 'Primary', 'community365' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'menu_id'        => 'primary-menu',
					'container'      => false,
					'fallback_cb'    => 'wp_page_menu',
				)
			);
			?>
		</nav>
	</div>
</header>

<main id="c365-content" class="site-main">
