<?php
/**
 * Post blocks: a full-width blog slider (featured images) with three
 * configurable blog columns beneath it. Plain headings, no badges.
 *
 * @package Community365
 */

$n = (int) get_query_var( 'c365_slot' );

$slider = community365_slot_query( $n, array( 'posts_per_page' => max( 3, (int) community365_slot( $n, 'count', 8 ) ) ) );
?>
<?php if ( $slider->have_posts() ) : ?>
	<div class="c365-strip-wrap" data-c365-strip>
		<button type="button" class="c365-strip-arrow c365-strip-prev" aria-label="<?php esc_attr_e( 'Previous', 'community365' ); ?>">&larr;</button>
		<div class="c365-strip">
			<?php
			while ( $slider->have_posts() ) :
				$slider->the_post();
				?>
				<article <?php post_class( 'c365-strip-card' ); ?>>
					<a class="c365-strip-thumb" href="<?php the_permalink(); ?>">
						<?php the_post_thumbnail( 'c365-card', array( 'loading' => 'lazy' ) ); ?>
						<span class="c365-hero-feature-overlay"><strong><?php the_title(); ?></strong></span>
					</a>
				</article>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>
		<button type="button" class="c365-strip-arrow c365-strip-next" aria-label="<?php esc_attr_e( 'Next', 'community365' ); ?>">&rarr;</button>
	</div>
<?php endif; ?>

<div class="c365-block-cols">
	<?php for ( $col = 1; $col <= 3; $col++ ) : ?>
		<?php
		$col_cat  = (int) community365_slot( $n, 'col' . $col . '_cat', 0 );
		$col_args = array( 'posts_per_page' => max( 1, (int) community365_slot( $n, 'col_count', 3 ) ) );
		if ( $col_cat ) {
			$col_args['cat'] = $col_cat;
		}
		$col_query = community365_slot_query( $n, $col_args );
		if ( ! $col_query->have_posts() ) {
			continue;
		}
		$col_term = $col_cat ? get_term( $col_cat, 'category' ) : null;
		?>
		<div class="c365-block-col">
			<?php if ( $col_term && ! is_wp_error( $col_term ) ) : ?>
				<h3 class="c365-block-col-heading"><?php echo esc_html( strtoupper( $col_term->name ) ); ?></h3>
			<?php endif; ?>
			<?php
			while ( $col_query->have_posts() ) {
				$col_query->the_post();
				get_template_part( 'template-parts/content', 'mini' );
			}
			wp_reset_postdata();
			?>
		</div>
	<?php endfor; ?>
</div>
