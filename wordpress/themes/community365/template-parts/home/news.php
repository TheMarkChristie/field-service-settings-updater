<?php
/**
 * News main block: 3 feature cards left, tabbed Popular/Recent large
 * feature centre, title list right. Each side column has its own
 * category/count; all columns share the slot's other filters.
 *
 * @package Community365
 */

$n = (int) get_query_var( 'c365_slot' );

$left = community365_slot_query( $n, array( 'posts_per_page' => max( 1, (int) community365_slot( $n, 'count_l', 3 ) ) ), '_l' );

$popular = community365_slot_query(
	$n,
	array(
		'posts_per_page' => 1,
		'meta_key'       => '_synpro_views', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
	)
);
$recent  = community365_slot_query( $n, array( 'posts_per_page' => 1 ) );

$right = community365_slot_query( $n, array(
	'post_type'      => array( 'post', 'synpro_event', 'synpro_podcast', 'synpro_video' ),
	'posts_per_page' => max( 1, (int) community365_slot( $n, 'count_r', 6 ) ),
), '_r' );
?>
<div class="c365-news">
	<div class="c365-news-left">
		<?php
		while ( $left->have_posts() ) :
			$left->the_post();
			?>
			<article <?php post_class( 'c365-news-card' ); ?>>
				<a class="c365-news-thumb" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1"><?php the_post_thumbnail( 'c365-card', array( 'loading' => 'lazy' ) ); ?></a>
				<p class="c365-mini-meta">
					<span class="c365-mini-kind"><?php echo esc_html( community365_type_label( get_post_type() ) ? community365_type_label( get_post_type() ) : ( ( $c = get_the_category() ) ? $c[0]->name : '' ) ); ?></span>
					<span aria-hidden="true">⇸</span>
					<?php printf( /* translators: %s: time diff. */ esc_html__( '%s ago', 'community365' ), esc_html( human_time_diff( get_post_time( 'U', true ), time() ) ) ); ?>
				</p>
				<h3 class="c365-mini-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
			</article>
			<?php
		endwhile;
		wp_reset_postdata();
		?>
	</div>

	<div class="c365-news-main" data-c365-tabs>
		<div class="c365-tabs" role="tablist">
			<button class="c365-tab is-active" role="tab" aria-selected="true" data-tab="popular"><?php esc_html_e( 'Popular', 'community365' ); ?></button>
			<button class="c365-tab" role="tab" aria-selected="false" data-tab="recent"><?php esc_html_e( 'Recent', 'community365' ); ?></button>
		</div>
		<?php
		foreach ( array( 'popular' => $popular, 'recent' => $recent ) as $tab => $query ) :
			?>
			<div class="c365-tab-panel <?php echo 'popular' === $tab ? 'is-active' : ''; ?>" data-panel="<?php echo esc_attr( $tab ); ?>">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					?>
					<article <?php post_class( 'c365-hero-feature' ); ?>>
						<a class="c365-hero-feature-thumb" href="<?php the_permalink(); ?>">
							<?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
							<span class="c365-hero-feature-overlay">
								<span class="c365-mini-meta"><?php printf( /* translators: %s: time diff. */ esc_html__( '%s ago', 'community365' ), esc_html( human_time_diff( get_post_time( 'U', true ), time() ) ) ); ?></span>
								<strong><?php the_title(); ?></strong>
							</span>
						</a>
					</article>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="c365-news-right">
		<?php
		while ( $right->have_posts() ) {
			$right->the_post();
			get_template_part( 'template-parts/content', 'mini' );
		}
		wp_reset_postdata();
		?>
	</div>
</div>
