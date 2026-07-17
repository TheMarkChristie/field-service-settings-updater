<?php
/**
 * YouTube slider: one large in-place player left, small thumbnail+title
 * cards right; clicking a card loads that video into the player.
 *
 * @package Community365
 */

$n = (int) get_query_var( 'c365_slot' );

$videos = community365_slot_query( $n, array(
	'post_type'      => 'synpro_video',
	'posts_per_page' => max( 2, (int) community365_slot( $n, 'count', 5 ) ),
) );
if ( ! $videos->have_posts() ) {
	return;
}

$items = array();
while ( $videos->have_posts() ) {
	$videos->the_post();
	$vid = get_post_meta( get_the_ID(), '_synpro_video_id', true );
	if ( ! $vid ) {
		continue;
	}
	$items[] = array(
		'embed' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $vid ),
		'title' => get_the_title(),
		'thumb' => get_the_post_thumbnail( null, 'c365-card', array( 'loading' => 'lazy' ) ),
		'link'  => get_permalink(),
	);
}
wp_reset_postdata();
if ( ! $items ) {
	return;
}
$first = $items[0];
?>
<div class="c365-media" data-c365-media>
	<div class="c365-media-main">
		<div class="c365-video-embed">
			<iframe data-c365-player src="<?php echo esc_url( $first['embed'] ); ?>" title="<?php echo esc_attr( $first['title'] ); ?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
		</div>
		<h3 class="c365-media-title" data-c365-media-title><a href="<?php echo esc_url( $first['link'] ); ?>"><?php echo esc_html( $first['title'] ); ?></a></h3>
	</div>
	<div class="c365-media-list" role="list">
		<?php foreach ( $items as $i => $item ) : ?>
			<button type="button" role="listitem" class="c365-media-item <?php echo 0 === $i ? 'is-active' : ''; ?>" data-embed="<?php echo esc_url( $item['embed'] ); ?>" data-title="<?php echo esc_attr( $item['title'] ); ?>" data-link="<?php echo esc_url( $item['link'] ); ?>">
				<span class="c365-media-item-num"><?php echo (int) ( $i + 1 ); ?></span>
				<span class="c365-media-item-thumb"><?php echo $item['thumb']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<span class="c365-media-item-title"><?php echo esc_html( $item['title'] ); ?></span>
			</button>
		<?php endforeach; ?>
	</div>
</div>
