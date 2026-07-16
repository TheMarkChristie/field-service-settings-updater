<?php
/**
 * Single post content.
 *
 * @package Community365
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'c365-article' ); ?>>
		<header class="entry-header">
			<h1 class="entry-title"><?php the_title(); ?></h1>
			<div class="c365-entry-meta">
				<?php community365_entry_meta(); ?>
				<?php community365_source_badge(); ?>
				<?php
				$categories = get_the_category_list( ', ' );
				if ( $categories ) {
					echo '<span aria-hidden="true">&middot;</span> ' . wp_kses_post( $categories );
				}
				?>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="post-thumbnail"><?php the_post_thumbnail( 'large' ); ?></div>
		<?php endif; ?>

		<div class="entry-content">
			<?php
			the_content();
			wp_link_pages(
				array(
					'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'community365' ),
					'after'  => '</div>',
				)
			);
			?>
		</div>

		<?php community365_attribution_box(); ?>

		<?php
		$tags = get_the_tag_list();
		if ( $tags ) {
			echo '<div class="c365-tags">' . wp_kses_post( $tags ) . '</div>';
		}
		?>
</article>
