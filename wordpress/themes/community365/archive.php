<?php
/**
 * Archive template (categories, tags, dates, authors).
 *
 * @package Community365
 */

get_header();
?>

<div class="c365-container">
	<header class="c365-page-header">
		<?php
		the_archive_title( '<h1>', '</h1>' );
		the_archive_description( '<div class="archive-description">', '</div>' );
		?>
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
