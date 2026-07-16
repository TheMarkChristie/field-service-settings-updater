<?php
/**
 * Search results template.
 *
 * @package Community365
 */

get_header();
?>

<div class="c365-container">
	<header class="c365-page-header">
		<h1>
			<?php
			printf(
				/* translators: %s: search query. */
				esc_html__( 'Search results for “%s”', 'community365' ),
				esc_html( get_search_query() )
			);
			?>
		</h1>
	</header>

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
