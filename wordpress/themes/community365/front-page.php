<?php
/**
 * Home page: the magazine layout, section by section, with every element's
 * category and visibility configurable in Customize → Community 365 Home
 * Page. Sections render only when enabled AND they have content.
 *
 * @package Community365
 */

get_header();

/**
 * Small query helper for home sections.
 *
 * @param array $args Overrides.
 * @return WP_Query
 */
if ( ! function_exists( 'c365_home_query' ) ) {
function c365_home_query( $args ) {
	return new WP_Query(
		wp_parse_args(
			$args,
			array(
				'post_status'         => 'publish',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		)
	);
}
}

/**
 * Category filter argument from a customizer setting (0 = all).
 *
 * @param string $mod Theme mod name.
 * @return array
 */
if ( ! function_exists( 'c365_home_cat_arg' ) ) {
function c365_home_cat_arg( $mod ) {
	$cat = (int) get_theme_mod( $mod, 0 );
	return $cat ? array( 'cat' => $cat ) : array();
}
}
?>

<?php if ( get_theme_mod( 'c365_home_show_ticker', true ) ) : ?>
	<?php $ticker_cats = get_categories( array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 14 ) ); ?>
	<?php if ( $ticker_cats ) : ?>
		<nav class="c365-ticker" aria-label="<?php esc_attr_e( 'Browse categories', 'community365' ); ?>">
			<div class="c365-ticker-inner">
				<?php foreach ( $ticker_cats as $ticker_cat ) : ?>
					<a href="<?php echo esc_url( get_category_link( $ticker_cat ) ); ?>">#<?php echo esc_html( str_replace( ' ', '', $ticker_cat->name ) ); ?></a>
				<?php endforeach; ?>
			</div>
		</nav>
	<?php endif; ?>
<?php endif; ?>

<div class="c365-container">

	<?php if ( get_theme_mod( 'c365_home_show_hero', true ) ) : ?>
		<?php
		$left    = c365_home_query( array( 'posts_per_page' => 4 ) + c365_home_cat_arg( 'c365_home_left_cat' ) );
		$popular = c365_home_query(
			array(
				'posts_per_page' => 2,
				'meta_key'       => '_synpro_views', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'date_query'     => array( array( 'after' => '60 days ago' ) ),
			)
		);
		if ( ! $popular->have_posts() ) {
			$popular = c365_home_query( array( 'posts_per_page' => 2 ) );
		}
		$right = c365_home_query(
			array(
				'post_type'      => array( 'post', 'synpro_event', 'synpro_podcast', 'synpro_video' ),
				'posts_per_page' => 8,
			) + c365_home_cat_arg( 'c365_home_right_cat' )
		);
		?>
		<section class="c365-hero-grid" aria-label="<?php esc_attr_e( 'Featured content', 'community365' ); ?>">
			<div class="c365-hero-col">
				<?php
				while ( $left->have_posts() ) {
					$left->the_post();
					get_template_part( 'template-parts/content', 'mini' );
				}
				wp_reset_postdata();
				?>
			</div>
			<div class="c365-hero-main">
				<h2 class="c365-home-heading"><span><?php esc_html_e( 'Popular', 'community365' ); ?></span></h2>
				<?php
				while ( $popular->have_posts() ) {
					$popular->the_post();
					?>
					<article <?php post_class( 'c365-hero-feature' ); ?>>
						<a class="c365-hero-feature-thumb" href="<?php the_permalink(); ?>">
							<?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
							<span class="c365-hero-feature-overlay">
								<span class="c365-mini-meta">
									<?php
									$feature_cats = get_the_category();
									echo esc_html( community365_type_label( get_post_type() ) ? community365_type_label( get_post_type() ) : ( $feature_cats ? $feature_cats[0]->name : '' ) );
									?>
									<span aria-hidden="true">⇸</span>
									<?php
									printf( /* translators: %s: human time diff. */ esc_html__( '%s ago', 'community365' ), esc_html( human_time_diff( get_post_time( 'U', true ), time() ) ) );
									?>
								</span>
								<strong><?php the_title(); ?></strong>
							</span>
						</a>
					</article>
					<?php
				}
				wp_reset_postdata();
				?>
			</div>
			<div class="c365-hero-col">
				<?php
				while ( $right->have_posts() ) {
					$right->the_post();
					get_template_part( 'template-parts/content', 'mini' );
				}
				wp_reset_postdata();
				?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( get_theme_mod( 'c365_home_show_spotlight', true ) ) : ?>
		<?php $spot = c365_home_query( array( 'posts_per_page' => 1 ) + c365_home_cat_arg( 'c365_home_spotlight_cat' ) ); ?>
		<?php if ( $spot->have_posts() ) : ?>
			<?php
			while ( $spot->have_posts() ) :
				$spot->the_post();
				?>
				<section class="c365-spotlight" <?php if ( has_post_thumbnail() ) : ?>style="background-image: linear-gradient(rgba(8,9,13,.55), rgba(8,9,13,.8)), url('<?php echo esc_url( get_the_post_thumbnail_url( null, 'large' ) ); ?>');"<?php endif; ?> aria-label="<?php esc_attr_e( 'Featured post', 'community365' ); ?>">
					<div class="c365-spotlight-card">
						<p class="c365-mini-meta">
							<?php
							$spot_cats = get_the_category();
							echo esc_html( $spot_cats ? $spot_cats[0]->name : '' );
							?>
							<span aria-hidden="true">⇸</span>
							<?php printf( /* translators: %s: human time diff. */ esc_html__( '%s ago', 'community365' ), esc_html( human_time_diff( get_post_time( 'U', true ), time() ) ) ); ?>
						</p>
						<h2><?php the_title(); ?></h2>
						<p class="c365-spotlight-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 28 ) ); ?></p>
						<a class="c365-button" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read more', 'community365' ); ?> ↗</a>
					</div>
				</section>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( get_theme_mod( 'c365_home_show_videos', true ) && post_type_exists( 'synpro_video' ) ) : ?>
		<?php $videos = c365_home_query( array( 'post_type' => 'synpro_video', 'posts_per_page' => 3 ) ); ?>
		<?php if ( $videos->have_posts() ) : ?>
			<section aria-label="<?php esc_attr_e( 'Videos', 'community365' ); ?>">
				<h2 class="c365-home-heading c365-home-heading-accent">
					<?php
					$video_heading = get_theme_mod( 'c365_home_video_heading', '' );
					echo esc_html( $video_heading ? $video_heading : __( 'Latest videos', 'community365' ) );
					?>
				</h2>
				<div class="c365-grid">
					<?php
					while ( $videos->have_posts() ) {
						$videos->the_post();
						get_template_part( 'template-parts/content', 'card' );
					}
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( get_theme_mod( 'c365_home_show_latest', true ) ) : ?>
		<?php $latest = c365_home_query( array( 'posts_per_page' => 6 ) + c365_home_cat_arg( 'c365_home_latest_cat' ) ); ?>
		<?php if ( $latest->have_posts() ) : ?>
			<section aria-label="<?php esc_attr_e( 'Latest articles', 'community365' ); ?>">
				<h2 class="c365-home-heading"><span><?php esc_html_e( 'Latest articles', 'community365' ); ?></span></h2>
				<div class="c365-list">
					<?php
					while ( $latest->have_posts() ) {
						$latest->the_post();
						get_template_part( 'template-parts/content', 'list' );
					}
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( get_theme_mod( 'c365_home_show_blocks', true ) ) : ?>
		<section class="c365-cat-blocks" aria-label="<?php esc_attr_e( 'Browse by category', 'community365' ); ?>">
			<?php
			for ( $block = 1; $block <= 3; $block++ ) {
				$block_cat = (int) get_theme_mod( 'c365_home_block_cat_' . $block, 0 );
				$term      = $block_cat ? get_term( $block_cat, 'category' ) : null;
				if ( $term && ! is_wp_error( $term ) ) {
					printf(
						'<a class="c365-cat-block" href="%1$s"><span>%2$s</span><span class="c365-cat-count">%3$d</span></a>',
						esc_url( get_category_link( $term ) ),
						esc_html( strtoupper( $term->name ) ),
						(int) $term->count
					);
				}
			}
			?>
		</section>
	<?php endif; ?>

	<?php if ( get_theme_mod( 'c365_home_show_headlines', true ) ) : ?>
		<?php
		$headlines = c365_home_query(
			array(
				'post_type'      => array( 'post', 'synpro_podcast', 'synpro_video' ),
				'posts_per_page' => 6,
				'meta_key'       => '_synpro_views', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
			) + c365_home_cat_arg( 'c365_home_headlines_cat' )
		);
		if ( ! $headlines->have_posts() ) {
			$headlines = c365_home_query( array( 'posts_per_page' => 6 ) + c365_home_cat_arg( 'c365_home_headlines_cat' ) );
		}
		?>
		<?php if ( $headlines->have_posts() ) : ?>
			<section aria-label="<?php esc_attr_e( 'Top headlines', 'community365' ); ?>">
				<h2 class="c365-home-heading"><span><?php esc_html_e( 'Top headlines', 'community365' ); ?></span></h2>
				<ol class="c365-headlines">
					<?php
					$rank = 1;
					while ( $headlines->have_posts() ) :
						$headlines->the_post();
						?>
						<li class="c365-headline">
							<a class="c365-headline-thumb" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
								<?php the_post_thumbnail( 'thumbnail', array( 'loading' => 'lazy' ) ); ?>
								<span class="c365-headline-rank"><?php echo esc_html( str_pad( (string) $rank, 2, '0', STR_PAD_LEFT ) ); ?></span>
							</a>
							<div>
								<p class="c365-mini-meta">
									<?php
									$headline_cats = get_the_category();
									echo esc_html( community365_type_label( get_post_type() ) ? community365_type_label( get_post_type() ) : ( $headline_cats ? $headline_cats[0]->name : '' ) );
									?>
									<span aria-hidden="true">⇸</span>
									<?php printf( /* translators: %s: human time diff. */ esc_html__( '%s ago', 'community365' ), esc_html( human_time_diff( get_post_time( 'U', true ), time() ) ) ); ?>
								</p>
								<h3 class="c365-mini-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							</div>
						</li>
						<?php
						$rank++;
					endwhile;
					wp_reset_postdata();
					?>
				</ol>
			</section>
		<?php endif; ?>
	<?php endif; ?>

	<div class="c365-home-bottom">
		<?php if ( get_theme_mod( 'c365_home_show_join', true ) ) : ?>
			<section aria-label="<?php esc_attr_e( 'Join us', 'community365' ); ?>">
				<h2 class="c365-home-heading"><span><?php esc_html_e( 'Join us', 'community365' ); ?></span></h2>
				<div class="c365-join-bars">
					<?php
					for ( $join = 1; $join <= 3; $join++ ) {
						$join_url = get_theme_mod( 'c365_social_' . $join, '' );
						if ( ! $join_url ) {
							continue;
						}
						printf(
							'<a class="c365-join-bar" href="%1$s" rel="external noopener" target="_blank"><span>%2$s</span><span class="c365-join-count">%3$s</span></a>',
							esc_url( $join_url ),
							esc_html( community365_social_label( $join_url ) ),
							esc_html( get_theme_mod( 'c365_social_count_' . $join, '' ) )
						);
					}
					?>
				</div>
				<?php $coffee_url = get_theme_mod( 'c365_coffee_url', '' ); ?>
				<?php if ( $coffee_url ) : ?>
					<a class="c365-coffee-btn" href="<?php echo esc_url( $coffee_url ); ?>" rel="external noopener" target="_blank">☕ <?php esc_html_e( 'Buy me a coffee', 'community365' ); ?></a>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( get_theme_mod( 'c365_home_show_events', true ) && function_exists( 'community365_events_calendar' ) ) : ?>
			<section aria-label="<?php esc_attr_e( 'Events', 'community365' ); ?>">
				<h2 class="c365-home-heading"><span><?php esc_html_e( 'Events', 'community365' ); ?></span></h2>
				<div class="c365-side-card">
					<?php community365_events_calendar(); ?>
				</div>
				<?php
				$next_event = get_posts(
					array(
						'post_type'      => 'synpro_event',
						'posts_per_page' => 1,
						'meta_key'       => '_synpro_event_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value'     => current_time( 'Y-m-d\TH:i' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						'meta_compare'   => '>=',
						'orderby'        => 'meta_value',
						'order'          => 'ASC',
						'no_found_rows'  => true,
					)
				);
				?>
				<p class="c365-next-event">
					<strong><?php esc_html_e( 'Next event:', 'community365' ); ?></strong>
					<?php if ( $next_event ) : ?>
						<a href="<?php echo esc_url( get_permalink( $next_event[0] ) ); ?>"><?php echo esc_html( get_the_title( $next_event[0] ) ); ?></a>
					<?php else : ?>
						<?php esc_html_e( 'No events scheduled.', 'community365' ); ?>
					<?php endif; ?>
				</p>
			</section>
		<?php endif; ?>
	</div>

</div>

<?php
get_footer();
