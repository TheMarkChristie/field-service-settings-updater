<?php
/**
 * Static page template.
 *
 * @package Community365
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<div class="c365-container">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'c365-article' ); ?>>
			<h1 class="entry-title"><?php the_title(); ?></h1>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="post-thumbnail"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>

			<div class="entry-content">
				<?php
				the_content();
				wp_link_pages(
					array(
						'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'community365' ),
						'after'  => '</div>',
					)
				);
				?>
			</div>
		</article>
	</div>
	<?php
	if ( comments_open() || get_comments_number() ) {
		echo '<div class="c365-container">';
		comments_template();
		echo '</div>';
	}
endwhile;

get_footer();
