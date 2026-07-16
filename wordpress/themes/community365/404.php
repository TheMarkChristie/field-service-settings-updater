<?php
/**
 * 404 template.
 *
 * @package Community365
 */

get_header();
?>

<div class="c365-container">
	<section class="c365-404">
		<h1>404</h1>
		<p><?php esc_html_e( 'That page could not be found. Try a search instead:', 'community365' ); ?></p>
		<div style="display:flex;justify-content:center;">
			<?php get_search_form(); ?>
		</div>
	</section>
</div>

<?php
get_footer();
