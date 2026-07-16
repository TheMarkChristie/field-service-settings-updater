<?php
/**
 * Single Video layout: title, embedded video, description. Nothing else
 * between the reader and the content — just a slim byline underneath.
 *
 * @package Community365
 */

get_header();

while ( have_posts() ) :
	the_post();
	$video_id = get_post_meta( get_the_ID(), '_synpro_video_id', true );
	$source   = community365_get_source();
	?>
	<div class="c365-container">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'c365-article c365-article-video' ); ?>>
			<h1 class="entry-title"><?php the_title(); ?></h1>

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

			<p class="c365-slim-byline">
				<?php esc_html_e( 'By', 'community365' ); ?> <?php the_author_posts_link(); ?>
				<span aria-hidden="true">&middot;</span> <?php echo esc_html( get_the_date() ); ?>
				<?php if ( $source && $source['url'] ) : ?>
					<span aria-hidden="true">&middot;</span>
					<a href="<?php echo esc_url( $source['url'] ); ?>" rel="external noopener" target="_blank"><?php esc_html_e( 'Watch on YouTube', 'community365' ); ?></a>
				<?php endif; ?>
			</p>
		</article>
	</div>
	<?php
endwhile;

get_footer();
