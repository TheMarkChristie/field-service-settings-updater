<?php
/**
 * Footer template.
 *
 * @package Community365
 */
?>
</main>

<footer class="site-footer">
	<div class="c365-container">
		<?php if ( is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' ) ) : ?>
			<div class="c365-footer-widgets">
				<?php
				for ( $i = 1; $i <= 3; $i++ ) {
					if ( is_active_sidebar( 'footer-' . $i ) ) {
						dynamic_sidebar( 'footer-' . $i );
					}
				}
				?>
			</div>
		<?php endif; ?>

		<div class="site-info">
			<span>
				<?php
				$footer_text = get_theme_mod( 'c365_footer_text', '' );
				if ( $footer_text ) {
					echo wp_kses_post( $footer_text );
				} else {
					printf(
						/* translators: 1: year, 2: site name. */
						esc_html__( '© %1$s %2$s. Syndicated posts remain the property of their original authors.', 'community365' ),
						esc_html( gmdate( 'Y' ) ),
						esc_html( get_bloginfo( 'name' ) )
					);
				}
				?>
			</span>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'footer-menu',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
