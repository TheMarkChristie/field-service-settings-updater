<?php
/**
 * Home page: the category ticker, then eight configurable slots, each
 * rendering a component chosen in Customize → Home Slot N, then the Join
 * us / coffee strip. Slots with no component or no content are skipped.
 *
 * @package Community365
 */

get_header();
?>

<?php if ( get_theme_mod( 'c365_home_show_ticker', true ) ) : ?>
	<?php $ticker_cats = get_categories( array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 14 ) ); ?>
	<?php if ( $ticker_cats ) : ?>
		<nav class="c365-ticker" aria-label="<?php esc_attr_e( 'Browse categories', 'community365' ); ?>">
			<div class="c365-ticker-inner">
				<?php foreach ( $ticker_cats as $ticker_cat ) : ?>
					<a href="<?php echo esc_url( get_category_link( $ticker_cat ) ); ?>">#<?php echo esc_html( str_replace( ' ', '', $ticker_cat->name ) ); ?></a>
				<?php endforeach; ?>
			</div>
		</nav>
	<?php endif; ?>
<?php endif; ?>

<div class="c365-container">
	<?php community365_render_home_slots(); ?>

	<?php if ( get_theme_mod( 'c365_home_show_join', true ) ) : ?>
		<div class="c365-home-bottom">
			<section aria-label="<?php esc_attr_e( 'Join us', 'community365' ); ?>">
				<h2 class="c365-home-heading"><span><?php esc_html_e( 'Join us', 'community365' ); ?></span></h2>
				<div class="c365-join-bars">
					<?php
					for ( $join = 1; $join <= 3; $join++ ) {
						$join_url = get_theme_mod( 'c365_social_' . $join, '' );
						if ( ! $join_url ) {
							continue;
						}
						printf(
							'<a class="c365-join-bar" href="%1$s" rel="external noopener" target="_blank"><span>%2$s</span><span class="c365-join-count">%3$s</span></a>',
							esc_url( $join_url ),
							esc_html( community365_social_label( $join_url ) ),
							esc_html( get_theme_mod( 'c365_social_count_' . $join, '' ) )
						);
					}
					?>
				</div>
				<?php $coffee_url = get_theme_mod( 'c365_coffee_url', '' ); ?>
				<?php if ( $coffee_url ) : ?>
					<a class="c365-coffee-btn" href="<?php echo esc_url( $coffee_url ); ?>" rel="external noopener" target="_blank">☕ <?php esc_html_e( 'Buy me a coffee', 'community365' ); ?></a>
				<?php endif; ?>
				<?php if ( shortcode_exists( 'synpro_subscribe' ) ) : ?>
					<div class="c365-subscribe">
						<h3 class="c365-subscribe-title"><?php esc_html_e( 'Weekly digest', 'community365' ); ?></h3>
						<p class="c365-subscribe-text"><?php esc_html_e( 'The best of the community in your inbox, once a week.', 'community365' ); ?></p>
						<?php echo do_shortcode( '[synpro_subscribe]' ); ?>
					</div>
				<?php endif; ?>
			</section>
			<div></div>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
