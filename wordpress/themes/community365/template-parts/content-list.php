<?php
/**
 * List-style card used on author pages: kind label, title, two-line
 * excerpt, and a meta row with date, views/duration/location, and the
 * source chip linking to the original.
 *
 * @package Community365
 */

$c365_type  = get_post_type();
$c365_views = (int) get_post_meta( get_the_ID(), '_synpro_views', true );
$c365_kind  = community365_type_label( $c365_type );
$c365_class = strtolower( str_replace( 'synpro_', '', $c365_type ) );

if ( ! $c365_kind ) {
	// Blog posts: the first category is the kind label.
	$c365_categories = get_the_category();
	$c365_kind       = ! empty( $c365_categories ) ? $c365_categories[0]->name : __( 'Blog', 'community365' );
	$c365_class      = 'blog';
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'c365-list-card' ); ?>>
	<span class="c365-list-kind c365-list-kind-<?php echo esc_attr( $c365_class ); ?>"><?php echo esc_html( $c365_kind ); ?></span>

	<h3 class="c365-list-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

	<p class="c365-list-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 32 ) ); ?></p>

	<div class="c365-list-meta">
		<?php if ( 'synpro_event' === $c365_type ) : ?>
			<?php
			$c365_start    = get_post_meta( get_the_ID(), '_synpro_event_start', true );
			$c365_location = get_post_meta( get_the_ID(), '_synpro_event_location', true );
			?>
			<span>📅 <?php echo esc_html( $c365_start ? community365_format_event_date( $c365_start ) : get_the_date() ); ?></span>
			<?php if ( $c365_location ) : ?>
				<span>📍 <?php echo esc_html( $c365_location ); ?></span>
			<?php endif; ?>
		<?php else : ?>
			<span>📅 <?php echo esc_html( get_the_date() ); ?></span>
			<?php if ( 'synpro_podcast' === $c365_type && get_post_meta( get_the_ID(), '_synpro_duration', true ) ) : ?>
				<span>🎧 <?php echo esc_html( get_post_meta( get_the_ID(), '_synpro_duration', true ) ); ?></span>
			<?php elseif ( $c365_views ) : ?>
				<span>👁 <?php echo esc_html( number_format_i18n( $c365_views ) ); ?> <?php esc_html_e( 'views', 'community365' ); ?></span>
			<?php endif; ?>
		<?php endif; ?>

		<?php
		$c365_source = community365_get_source();
		if ( $c365_source && $c365_source['url'] ) {
			printf(
				'<a class="c365-list-source" href="%1$s" rel="external noopener" target="_blank">↗ %2$s</a>',
				esc_url( $c365_source['url'] ),
				esc_html( $c365_source['name'] )
			);
		}
		?>
	</div>
</article>
