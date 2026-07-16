<?php
/**
 * Main index template — home page and default fallback.
 *
 * @package Community365
 */

get_header();
?>

<?php if ( is_home() && ! is_paged() && get_theme_mod( 'c365_show_hero', true ) ) : ?>
	<section class="c365-hero">
		<div class="c365-container">
			<h1>
				<?php
				$heading = get_theme_mod( 'c365_hero_heading', '' );
				echo esc_html( $heading ? $heading : get_bloginfo( 'name' ) );
				?>
			</h1>
			<p>
				<?php
				$intro = get_theme_mod( 'c365_hero_text', '' );
				echo esc_html( $intro ? $intro : get_bloginfo( 'description' ) );
				?>
			</p>
		</div>
	</section>
<?php endif; ?>

<div class="c365-container">
	<?php if ( have_posts() ) : ?>
		<div class="c365-grid">
			<?php
			while ( have_posts() ) {
				the_post();
				get_template_part( 'template-parts/content', 'card' );
			}
			?>
		</div>

		<nav class="c365-pagination" aria-label="<?php esc_attr_e( 'Posts', 'community365' ); ?>">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'prev_text' => __( '&larr; Newer', 'community365' ),
						'next_text' => __( 'Older &rarr;', 'community365' ),
					)
				)
			);
			?>
		</nav>
	<?php else : ?>
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>
</div>

<?php
get_footer();
