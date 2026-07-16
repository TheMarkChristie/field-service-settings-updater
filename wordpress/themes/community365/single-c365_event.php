<?php
/**
 * Single Event layout.
 *
 * @package Community365
 */

get_header();

while ( have_posts() ) :
	the_post();
	$start    = get_post_meta( get_the_ID(), '_c365_event_start', true );
	$end      = get_post_meta( get_the_ID(), '_c365_event_end', true );
	$location = get_post_meta( get_the_ID(), '_c365_event_location', true );
	$link     = get_post_meta( get_the_ID(), '_c365_event_url', true );
	?>
	<div class="c365-container">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'c365-article c365-article-event' ); ?>>
			<span class="c365-type-badge c365-type-event"><?php esc_html_e( 'Event', 'community365' ); ?></span>
			<h1 class="entry-title"><?php the_title(); ?></h1>

			<div class="c365-event-details">
				<?php if ( $start ) : ?>
					<div class="c365-event-detail">
						<span class="c365-event-label"><?php esc_html_e( 'Starts', 'community365' ); ?></span>
						<time datetime="<?php echo esc_attr( $start ); ?>"><?php echo esc_html( community365_format_event_date( $start ) ); ?></time>
					</div>
				<?php endif; ?>
				<?php if ( $end ) : ?>
					<div class="c365-event-detail">
						<span class="c365-event-label"><?php esc_html_e( 'Ends', 'community365' ); ?></span>
						<time datetime="<?php echo esc_attr( $end ); ?>"><?php echo esc_html( community365_format_event_date( $end ) ); ?></time>
					</div>
				<?php endif; ?>
				<?php if ( $location ) : ?>
					<div class="c365-event-detail">
						<span class="c365-event-label"><?php esc_html_e( 'Location', 'community365' ); ?></span>
						<span><?php echo esc_html( $location ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $link ) : ?>
					<div class="c365-event-detail c365-event-cta">
						<a class="c365-button" href="<?php echo esc_url( $link ); ?>" rel="external noopener" target="_blank"><?php esc_html_e( 'Register / more info', 'community365' ); ?></a>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="post-thumbnail"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>

			<div class="entry-content"><?php the_content(); ?></div>

			<div class="c365-entry-meta">
				<?php esc_html_e( 'Added by', 'community365' ); ?> <?php the_author_posts_link(); ?>
			</div>

			<?php community365_attribution_box(); ?>
		</article>
	</div>
	<?php
endwhile;

get_footer();
