<?php
/**
 * Mini card: small thumbnail, type/category label, time-ago, title.
 * Used in the home page hero columns.
 *
 * @package Community365
 */

$c365_type = get_post_type();
$c365_kind = community365_type_label( $c365_type );
if ( ! $c365_kind ) {
	$c365_cats = get_the_category();
	$c365_kind = ! empty( $c365_cats ) ? $c365_cats[0]->name : __( 'Blog', 'community365' );
}
?>
<article <?php post_class( 'c365-mini' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="c365-mini-thumb" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
			<?php the_post_thumbnail( 'thumbnail', array( 'loading' => 'lazy' ) ); ?>
		</a>
	<?php endif; ?>
	<div class="c365-mini-body">
		<p class="c365-mini-meta">
			<span class="c365-mini-kind"><?php echo esc_html( $c365_kind ); ?></span>
			<span aria-hidden="true">⇸</span>
			<?php
			printf(
				/* translators: %s: human time diff. */
				esc_html__( '%s ago', 'community365' ),
				esc_html( human_time_diff( get_post_time( 'U', true ), time() ) )
			);
			?>
		</p>
		<h3 class="c365-mini-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
	</div>
</article>
