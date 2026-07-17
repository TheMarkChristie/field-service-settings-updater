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

<?php
if ( get_theme_mod( 'c365_cookie_enabled', true ) ) :
	$c365_cookie_msg = get_theme_mod( 'c365_cookie_text', __( 'We use cookies to improve your experience on this site. By continuing to browse, you agree to our use of cookies.', 'community365' ) );
	$c365_policy     = (int) get_theme_mod( 'c365_cookie_policy_page', 0 );
	?>
	<div class="c365-cookie" id="c365-cookie" role="dialog" aria-live="polite" aria-label="<?php esc_attr_e( 'Cookie notice', 'community365' ); ?>" hidden>
		<p class="c365-cookie-text">
			<?php echo esc_html( $c365_cookie_msg ); ?>
			<?php if ( $c365_policy && get_permalink( $c365_policy ) ) : ?>
				<a href="<?php echo esc_url( get_permalink( $c365_policy ) ); ?>"><?php esc_html_e( 'Learn more', 'community365' ); ?></a>
			<?php endif; ?>
		</p>
		<button type="button" class="c365-cookie-accept"><?php esc_html_e( 'Got it', 'community365' ); ?></button>
	</div>
	<script>
	( function () {
		var KEY = 'c365_cookie_ok';
		var bar = document.getElementById( 'c365-cookie' );
		if ( ! bar ) { return; }
		try { if ( localStorage.getItem( KEY ) === '1' ) { return; } } catch ( e ) {}
		bar.hidden = false;
		bar.querySelector( '.c365-cookie-accept' ).addEventListener( 'click', function () {
			try { localStorage.setItem( KEY, '1' ); } catch ( e ) {}
			bar.hidden = true;
		} );
	} )();
	</script>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
