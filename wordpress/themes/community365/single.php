<?php
/**
 * Single post template.
 *
 * @package Community365
 */

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/content', 'single' );

	$prev = get_previous_post();
	$next = get_next_post();
	if ( $prev || $next ) :
		?>
		<div class="c365-container">
			<nav class="c365-post-nav" aria-label="<?php esc_attr_e( 'Post navigation', 'community365' ); ?>">
				<div>
					<?php if ( $prev ) : ?>
						<a href="<?php echo esc_url( get_permalink( $prev ) ); ?>">
							<span class="nav-label"><?php esc_html_e( 'Previous', 'community365' ); ?></span>
							<?php echo esc_html( get_the_title( $prev ) ); ?>
						</a>
					<?php endif; ?>
				</div>
				<div>
					<?php if ( $next ) : ?>
						<a class="nav-next" href="<?php echo esc_url( get_permalink( $next ) ); ?>">
							<span class="nav-label"><?php esc_html_e( 'Next', 'community365' ); ?></span>
							<?php echo esc_html( get_the_title( $next ) ); ?>
						</a>
					<?php endif; ?>
				</div>
			</nav>
		</div>
		<?php
	endif;

	if ( comments_open() || get_comments_number() ) {
		echo '<div class="c365-container">';
		comments_template();
		echo '</div>';
	}
endwhile;

get_footer();
