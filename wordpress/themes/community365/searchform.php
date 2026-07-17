<?php
/**
 * Themed search form (used by get_search_form() and the header toggle).
 *
 * @package Community365
 */

?>
<form role="search" method="get" class="c365-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="c365-search-field"><?php esc_html_e( 'Search for:', 'community365' ); ?></label>
	<input type="search" id="c365-search-field" class="c365-search-field" placeholder="<?php esc_attr_e( 'Search the community…', 'community365' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
	<button type="submit" class="c365-search-submit">
		<span aria-hidden="true">&#128269;</span>
		<span class="screen-reader-text"><?php esc_html_e( 'Search', 'community365' ); ?></span>
	</button>
</form>
