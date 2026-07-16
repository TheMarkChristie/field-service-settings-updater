<?php
/**
 * Single Podcast Episode layout.
 *
 * @package Community365
 */

get_header();

while ( have_posts() ) :
	the_post();
	$audio    = get_post_meta( get_the_ID(), '_c365_audio_url', true );
	$duration = get_post_meta( get_the_ID(), '_c365_duration', true );
	?>
	<div class="c365-container">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'c365-article c365-article-podcast' ); ?>>
			<span class="c365-type-badge c365-type-podcast"><?php esc_html_e( 'Podcast', 'community365' ); ?></span>
			<h1 class="entry-title"><?php the_title(); ?></h1>
			<div class="c365-entry-meta">
				<?php community365_entry_meta(); ?>
				<?php if ( $duration ) : ?>
					<span aria-hidden="true">&middot;</span>
					<span><?php echo esc_html( $duration ); ?></span>
				<?php endif; ?>
				<?php community365_source_badge(); ?>
			</div>

			<div class="c365-podcast-player">
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="c365-podcast-art"><?php the_post_thumbnail( 'medium' ); ?></div>
				<?php endif; ?>
				<?php if ( $audio ) : ?>
					<audio controls preload="none" src="<?php echo esc_url( $audio ); ?>">
						<?php esc_html_e( 'Your browser does not support the audio player.', 'community365' ); ?>
					</audio>
				<?php endif; ?>
			</div>

			<div class="entry-content"><?php the_content(); ?></div>

			<?php community365_attribution_box(); ?>
		</article>
	</div>
	<?php
endwhile;

get_footer();
