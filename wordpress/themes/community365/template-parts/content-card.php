<?php
/**
 * Content card used in all grids. Adapts its layout to the content type:
 * blog post, event (date/location line), podcast (play hint), or video
 * (play overlay).
 *
 * @package Community365
 */

$c365_type       = get_post_type();
$c365_type_label = community365_type_label( $c365_type );
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'c365-card c365-card-' . esc_attr( $c365_type ) ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="c365-card-thumb" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
			<?php the_post_thumbnail( 'c365-card', array( 'loading' => 'lazy' ) ); ?>
			<?php if ( 'c365_video' === $c365_type || 'c365_podcast' === $c365_type ) : ?>
				<span class="c365-play-overlay" aria-hidden="true">&#9654;</span>
			<?php endif; ?>
		</a>
	<?php endif; ?>

	<div class="c365-card-body">
		<?php if ( $c365_type_label ) : ?>
			<span class="c365-type-badge c365-type-<?php echo esc_attr( str_replace( 'c365_', '', $c365_type ) ); ?>">
				<?php echo esc_html( $c365_type_label ); ?>
			</span>
		<?php else : ?>
			<?php
			$c365_category = get_the_category();
			if ( ! empty( $c365_category ) ) {
				printf(
					'<a class="c365-cat-chip" href="%1$s">%2$s</a>',
					esc_url( get_category_link( $c365_category[0] ) ),
					esc_html( $c365_category[0]->name )
				);
			}
			?>
		<?php endif; ?>

		<h2 class="c365-card-title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h2>

		<?php if ( 'c365_event' === $c365_type ) : ?>
			<?php
			$c365_start    = get_post_meta( get_the_ID(), '_c365_event_start', true );
			$c365_location = get_post_meta( get_the_ID(), '_c365_event_location', true );
			?>
			<p class="c365-card-event-meta">
				<?php if ( $c365_start ) : ?>
					<time datetime="<?php echo esc_attr( $c365_start ); ?>">📅 <?php echo esc_html( community365_format_event_date( $c365_start ) ); ?></time>
				<?php endif; ?>
				<?php if ( $c365_location ) : ?>
					<span>📍 <?php echo esc_html( $c365_location ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<p class="c365-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24 ) ); ?></p>

		<div class="c365-card-footer">
			<span>
				<?php community365_entry_meta( false ); ?>
				&middot; <?php the_author_posts_link(); ?>
			</span>
			<?php community365_source_badge(); ?>
		</div>
	</div>
</article>
