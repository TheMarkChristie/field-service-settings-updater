<?php
/**
 * Themed search form (used by get_search_form() and the header toggle).
 *
 * @package Community365
 */

$c365_sf_id = wp_unique_id( 'c365-search-field-' ); // get_search_form() can render more than once per page.
?>
<form role="search" method="get" class="c365-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $c365_sf_id ); ?>"><?php esc_html_e( 'Search for:', 'community365' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $c365_sf_id ); ?>" class="c365-search-field" placeholder="<?php esc_attr_e( 'Search the community…', 'community365' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
	<button type="submit" class="c365-search-submit">
		<span aria-hidden="true">&#128269;</span>
		<span class="screen-reader-text"><?php esc_html_e( 'Search', 'community365' ); ?></span>
	</button>
</form>
