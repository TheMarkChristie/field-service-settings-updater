<?php
/**
 * Right sidebar for single posts: monthly events calendar, configurable
 * advert, three social buttons, and a Buy Me a Coffee button — all set in
 * Customizer → Community 365 Post Sidebar.
 *
 * @package Community365
 */

if ( ! get_theme_mod( 'c365_show_sidebar', true ) ) {
	return;
}

$ad_html  = get_theme_mod( 'c365_ad_html', '' );
$ad_image = get_theme_mod( 'c365_ad_image', '' );
$ad_link  = get_theme_mod( 'c365_ad_link', '' );
$coffee   = get_theme_mod( 'c365_coffee_url', '' );

$socials = array();
for ( $i = 1; $i <= 3; $i++ ) {
	$url = get_theme_mod( 'c365_social_' . $i, '' );
	if ( $url ) {
		$socials[] = $url;
	}
}
?>
<aside class="c365-side" aria-label="<?php esc_attr_e( 'Sidebar', 'community365' ); ?>">

	<div class="c365-side-card">
		<h2 class="c365-side-title"><?php esc_html_e( 'Community events', 'community365' ); ?></h2>
		<?php community365_events_calendar(); ?>
		<p class="c365-side-more"><a href="<?php echo esc_url( get_post_type_archive_link( 'synpro_event' ) ); ?>"><?php esc_html_e( 'All events →', 'community365' ); ?></a></p>
	</div>

	<?php if ( $ad_html || ( $ad_image && $ad_link ) ) : ?>
		<div class="c365-side-card c365-side-ad">
			<span class="c365-ad-label"><?php esc_html_e( 'Sponsored', 'community365' ); ?></span>
			<?php if ( $ad_html ) : ?>
				<?php echo wp_kses_post( $ad_html ); ?>
			<?php else : ?>
				<a href="<?php echo esc_url( $ad_link ); ?>" rel="external noopener sponsored" target="_blank">
					<img src="<?php echo esc_url( $ad_image ); ?>" alt="<?php esc_attr_e( 'Advertisement', 'community365' ); ?>" loading="lazy">
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $socials ) : ?>
		<div class="c365-side-card">
			<h2 class="c365-side-title"><?php esc_html_e( 'Follow the community', 'community365' ); ?></h2>
			<div class="c365-side-socials">
				<?php foreach ( $socials as $url ) : ?>
					<a class="c365-social-btn" href="<?php echo esc_url( $url ); ?>" rel="external noopener" target="_blank">
						<?php echo esc_html( community365_social_label( $url ) ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $coffee ) : ?>
		<a class="c365-coffee-btn" href="<?php echo esc_url( $coffee ); ?>" rel="external noopener" target="_blank">
			☕ <?php esc_html_e( 'Buy me a coffee', 'community365' ); ?>
		</a>
	<?php endif; ?>

</aside>
