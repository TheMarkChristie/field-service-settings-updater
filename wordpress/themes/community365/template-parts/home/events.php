<?php
/**
 * Events calendar component: monthly grid beside a list of everything
 * scheduled, each upcoming event with its tickets / call-for-speakers /
 * website buttons.
 *
 * @package Community365
 */

$n = (int) get_query_var( 'c365_slot' );

$upcoming = get_posts( array(
	'post_type'      => 'synpro_event',
	'post_status'    => 'publish',
	'posts_per_page' => 20,
	'meta_key'       => '_synpro_event_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	'meta_value'     => current_time( 'Y-m-d\TH:i' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	'meta_compare'   => '>=',
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
	'no_found_rows'  => true,
) );
?>
<div class="c365-events-wide">
	<div class="c365-side-card">
		<?php community365_events_calendar(); ?>
	</div>
	<div class="c365-upcoming">
		<?php if ( $upcoming ) : ?>
			<?php foreach ( $upcoming as $event ) : ?>
				<?php
				$start    = get_post_meta( $event->ID, '_synpro_event_start', true );
				$location = get_post_meta( $event->ID, '_synpro_event_location', true );
				$tickets  = get_post_meta( $event->ID, '_synpro_event_tickets', true );
				$cfs      = get_post_meta( $event->ID, '_synpro_event_cfs', true );
				$website  = get_post_meta( $event->ID, '_synpro_event_url', true );
				$start_ts = $start ? strtotime( $start ) : 0;
				?>
				<article class="c365-upcoming-row">
					<div class="c365-upcoming-date" aria-hidden="true">
						<span class="c365-upcoming-day"><?php echo esc_html( $start_ts ? gmdate( 'd', $start_ts ) : '—' ); ?></span>
						<span class="c365-upcoming-mon"><?php echo esc_html( $start_ts ? date_i18n( 'M', $start_ts ) : '' ); ?></span>
					</div>
					<div class="c365-upcoming-body">
						<h3 class="c365-mini-title"><a href="<?php echo esc_url( get_permalink( $event ) ); ?>"><?php echo esc_html( get_the_title( $event ) ); ?></a></h3>
						<p class="c365-mini-meta">
							<?php echo $start_ts ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $start_ts ) ) : ''; ?>
							<?php echo $location ? ' · 📍 ' . esc_html( $location ) : ''; ?>
						</p>
						<p class="c365-upcoming-actions">
							<?php if ( $tickets ) : ?><a class="c365-chip-btn" href="<?php echo esc_url( $tickets ); ?>" rel="external noopener" target="_blank">🎟 <?php esc_html_e( 'Tickets', 'community365' ); ?></a><?php endif; ?>
							<?php if ( $cfs ) : ?><a class="c365-chip-btn" href="<?php echo esc_url( $cfs ); ?>" rel="external noopener" target="_blank">🎤 <?php esc_html_e( 'Speak', 'community365' ); ?></a><?php endif; ?>
							<?php if ( $website ) : ?><a class="c365-chip-btn" href="<?php echo esc_url( $website ); ?>" rel="external noopener" target="_blank">🌐 <?php esc_html_e( 'Website', 'community365' ); ?></a><?php endif; ?>
						</p>
					</div>
				</article>
			<?php endforeach; ?>
		<?php else : ?>
			<p class="c365-next-event"><?php esc_html_e( 'No events scheduled — check back soon.', 'community365' ); ?></p>
		<?php endif; ?>
	</div>
</div>
