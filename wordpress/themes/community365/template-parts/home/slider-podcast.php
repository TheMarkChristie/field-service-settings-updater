<?php
/**
 * Podcast slider: newest episode auto-featured with an inline audio
 * player; episode cards right load into the featured spot on click.
 *
 * @package Community365
 */

$n = (int) get_query_var( 'c365_slot' );

$episodes = community365_slot_query( $n, array(
	'post_type'      => 'synpro_podcast',
	'posts_per_page' => max( 2, (int) community365_slot( $n, 'count', 5 ) ),
) );
if ( ! $episodes->have_posts() ) {
	return;
}

$items = array();
while ( $episodes->have_posts() ) {
	$episodes->the_post();
	$items[] = array(
		'audio'    => get_post_meta( get_the_ID(), '_synpro_audio_url', true ),
		'title'    => get_the_title(),
		'duration' => get_post_meta( get_the_ID(), '_synpro_duration', true ),
		'thumb'    => get_the_post_thumbnail( null, 'c365-card', array( 'loading' => 'lazy' ) ),
		'art'      => get_the_post_thumbnail_url( null, 'medium' ),
		'link'     => get_permalink(),
		'is_new'   => ( time() - get_post_time( 'U', true ) ) < WEEK_IN_SECONDS,
	);
}
wp_reset_postdata();
if ( ! $items ) {
	return;
}
$first = $items[0]; // Newest episode auto-features.
?>
<div class="c365-media c365-media-podcast" data-c365-media>
	<div class="c365-media-main">
		<div class="c365-podcast-feature">
			<?php if ( $first['art'] ) : ?>
				<img data-c365-art src="<?php echo esc_url( $first['art'] ); ?>" alt="" loading="lazy">
			<?php endif; ?>
			<div class="c365-podcast-feature-body">
				<?php if ( $first['is_new'] ) : ?>
					<span class="c365-new-badge" data-c365-new><?php esc_html_e( 'NEW', 'community365' ); ?></span>
				<?php else : ?>
					<span class="c365-new-badge" data-c365-new hidden><?php esc_html_e( 'NEW', 'community365' ); ?></span>
				<?php endif; ?>
				<h3 class="c365-media-title" data-c365-media-title><a href="<?php echo esc_url( $first['link'] ); ?>"><?php echo esc_html( $first['title'] ); ?></a></h3>
				<audio controls preload="none" data-c365-player src="<?php echo esc_url( $first['audio'] ); ?>"></audio>
			</div>
		</div>
	</div>
	<div class="c365-media-list" role="list">
		<?php foreach ( $items as $i => $item ) : ?>
			<button type="button" role="listitem" class="c365-media-item <?php echo 0 === $i ? 'is-active' : ''; ?>" data-audio="<?php echo esc_url( $item['audio'] ); ?>" data-title="<?php echo esc_attr( $item['title'] ); ?>" data-link="<?php echo esc_url( $item['link'] ); ?>" data-art="<?php echo esc_url( (string) $item['art'] ); ?>" data-new="<?php echo $item['is_new'] ? '1' : '0'; ?>">
				<span class="c365-media-item-thumb"><?php echo $item['thumb']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<span class="c365-media-item-title">
					<?php echo esc_html( $item['title'] ); ?>
					<?php if ( $item['duration'] ) : ?>
						<em><?php echo esc_html( $item['duration'] ); ?></em>
					<?php endif; ?>
				</span>
			</button>
		<?php endforeach; ?>
	</div>
</div>
