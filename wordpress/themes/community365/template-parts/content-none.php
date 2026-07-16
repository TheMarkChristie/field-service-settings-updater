<?php
/**
 * Empty state.
 *
 * @package Community365
 */
?>
<section class="c365-404">
	<h2><?php esc_html_e( 'Nothing here yet', 'community365' ); ?></h2>
	<p><?php esc_html_e( 'No posts matched. Try a different search.', 'community365' ); ?></p>
	<div style="display:flex;justify-content:center;">
		<?php get_search_form(); ?>
	</div>
</section>
