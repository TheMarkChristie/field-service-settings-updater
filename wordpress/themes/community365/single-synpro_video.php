<?php
/**
 * Single Video layout.
 *
 * @package Community365
 */

get_header();

while ( have_posts() ) :
	the_post();
	$video_id = get_post_meta( get_the_ID(), '_synpro_video_id', true );
	?>
	<div class="c365-container">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'c365-article c365-article-video' ); ?>>
			<span class="c365-type-badge c365-type-video"><?php esc_html_e( 'Video', 'community365' ); ?></span>
			<h1 class="entry-title"><?php the_title(); ?></h1>
			<div class="c365-entry-meta">
				<?php community365_entry_meta(); ?>
				<?php community365_source_badge(); ?>
			</div>

			<?php if ( $video_id ) : ?>
				<div class="c365-video-embed">
					<iframe
						src="<?php echo esc_url( 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $video_id ) ); ?>"
						title="<?php the_title_attribute(); ?>"
						loading="lazy"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
						allowfullscreen></iframe>
				</div>
			<?php elseif ( has_post_thumbnail() ) : ?>
				<div class="post-thumbnail"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>

			<div class="entry-content"><?php the_content(); ?></div>

			<?php community365_attribution_box(); ?>
		</article>
	</div>
	<?php
endwhile;

get_footer();
