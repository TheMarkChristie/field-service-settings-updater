<?php
/**
 * Single Event layout: name, date, location, organiser, description, and
 * action buttons for tickets, call for speakers, and the event website.
 *
 * @package Community365
 */

get_header();

while ( have_posts() ) :
	the_post();
	$start    = get_post_meta( get_the_ID(), '_synpro_event_start', true );
	$end      = get_post_meta( get_the_ID(), '_synpro_event_end', true );
	$location = get_post_meta( get_the_ID(), '_synpro_event_location', true );
	$website  = get_post_meta( get_the_ID(), '_synpro_event_url', true );
	$tickets  = get_post_meta( get_the_ID(), '_synpro_event_tickets', true );
	$cfs      = get_post_meta( get_the_ID(), '_synpro_event_cfs', true );
	?>
	<div class="c365-container">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'c365-article c365-article-event' ); ?>>
			<span class="c365-type-badge c365-type-event"><?php esc_html_e( 'Event', 'community365' ); ?></span>
			<h1 class="entry-title"><?php the_title(); ?></h1>

			<dl class="c365-event-details">
				<?php if ( $start ) : ?>
					<div class="c365-event-detail">
						<dt class="c365-event-label"><?php esc_html_e( 'Date', 'community365' ); ?></dt>
						<dd>
							<time datetime="<?php echo esc_attr( $start ); ?>"><?php echo esc_html( community365_format_event_date( $start ) ); ?></time>
							<?php if ( $end ) : ?>
								&ndash; <time datetime="<?php echo esc_attr( $end ); ?>"><?php echo esc_html( community365_format_event_date( $end ) ); ?></time>
							<?php endif; ?>
						</dd>
					</div>
				<?php endif; ?>
				<?php if ( $location ) : ?>
					<div class="c365-event-detail">
						<dt class="c365-event-label"><?php esc_html_e( 'Location', 'community365' ); ?></dt>
						<dd><?php echo esc_html( $location ); ?></dd>
					</div>
				<?php endif; ?>
				<div class="c365-event-detail">
					<dt class="c365-event-label"><?php esc_html_e( 'Organiser', 'community365' ); ?></dt>
					<dd><?php the_author_posts_link(); ?></dd>
				</div>
			</dl>

			<?php if ( $tickets || $cfs || $website ) : ?>
				<div class="c365-event-actions">
					<?php if ( $tickets ) : ?>
						<a class="c365-button" href="<?php echo esc_url( $tickets ); ?>" rel="external noopener" target="_blank">🎟 <?php esc_html_e( 'Get tickets', 'community365' ); ?></a>
					<?php endif; ?>
					<?php if ( $cfs ) : ?>
						<a class="c365-button c365-button-alt" href="<?php echo esc_url( $cfs ); ?>" rel="external noopener" target="_blank">🎤 <?php esc_html_e( 'Call for speakers', 'community365' ); ?></a>
					<?php endif; ?>
					<?php if ( $website ) : ?>
						<a class="c365-button c365-button-ghost" href="<?php echo esc_url( $website ); ?>" rel="external noopener" target="_blank">🌐 <?php esc_html_e( 'Event website', 'community365' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="post-thumbnail"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>

			<div class="entry-content"><?php the_content(); ?></div>

			<?php community365_attribution_box(); ?>
		</article>
	</div>
	<?php
endwhile;

get_footer();
