<?php
/**
 * Popular posts: card grid ranked by views (last 30 days by default; the
 * slot's date window overrides). Content types selectable per slot; view
 * counts intentionally not displayed.
 *
 * @package Community365
 */

$n = (int) get_query_var( 'c365_slot' );

$types = array();
foreach ( array( 'type_post' => 'post', 'type_podcast' => 'synpro_podcast', 'type_video' => 'synpro_video', 'type_event' => 'synpro_event' ) as $key => $type ) {
	if ( (int) community365_slot( $n, $key, 'type_post' === $key ? 1 : 0 ) ) {
		$types[] = $type;
	}
}
if ( ! $types ) {
	$types = array( 'post' );
}

$args = array(
	'post_type'      => $types,
	'posts_per_page' => max( 1, (int) community365_slot( $n, 'count', 6 ) ),
	'meta_key'       => '_synpro_views', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	'orderby'        => 'meta_value_num',
	'order'          => 'DESC',
);
if ( 'any' === community365_slot( $n, 'date', 'any' ) ) {
	$args['date_query'] = array( array( 'after' => '30 days ago' ) );
}
$popular = community365_slot_query( $n, $args );
if ( ! $popular->have_posts() ) {
	$popular = community365_slot_query( $n, array( 'post_type' => $types, 'posts_per_page' => max( 1, (int) community365_slot( $n, 'count', 6 ) ) ) );
}
if ( ! $popular->have_posts() ) {
	return;
}
?>
<div class="c365-grid">
	<?php
	while ( $popular->have_posts() ) {
		$popular->the_post();
		get_template_part( 'template-parts/content', 'card' );
	}
	wp_reset_postdata();
	?>
</div>
