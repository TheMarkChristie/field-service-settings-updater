<?php
/**
 * Author profile page.
 *
 * Shows the member's cover image, photo, bio, and links, then the content
 * sections they have chosen to display (blogs, podcasts, videos, events).
 * Members control all of this from their own profile screen.
 *
 * @package Community365
 */

get_header();

$author_id = get_queried_object_id();
$cover     = get_user_meta( $author_id, 'c365_cover_url', true );
$tagline   = get_user_meta( $author_id, 'c365_tagline', true );
?>

<section class="c365-author-hero <?php echo $cover ? 'has-cover' : ''; ?>"
	<?php if ( $cover ) : ?>style="background-image:url('<?php echo esc_url( $cover ); ?>');"<?php endif; ?>>
</section>

<div class="c365-container">
	<header class="c365-author-header">
		<div class="c365-author-avatar"><?php echo get_avatar( $author_id, 140 ); ?></div>
		<div class="c365-author-heading">
			<h1><?php echo esc_html( get_the_author_meta( 'display_name', $author_id ) ); ?></h1>
			<?php if ( $tagline ) : ?>
				<p class="c365-author-tagline"><?php echo esc_html( $tagline ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( community365_author_section_enabled( $author_id, 'c365_show_bio' ) ) : ?>
		<?php $bio = get_the_author_meta( 'description', $author_id ); ?>
		<?php if ( $bio ) : ?>
			<div class="c365-author-bio"><?php echo wp_kses_post( wpautop( $bio ) ); ?></div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( community365_author_section_enabled( $author_id, 'c365_show_links' ) ) : ?>
		<?php community365_author_links( $author_id ); ?>
	<?php endif; ?>

	<?php
	$sections = array(
		'c365_show_blogs'    => array( 'post', __( 'Blog posts', 'community365' ) ),
		'c365_show_podcasts' => array( 'c365_podcast', __( 'Podcast episodes', 'community365' ) ),
		'c365_show_videos'   => array( 'c365_video', __( 'Videos', 'community365' ) ),
		'c365_show_events'   => array( 'c365_event', __( 'Events', 'community365' ) ),
	);

	foreach ( $sections as $toggle => $section ) :
		list( $post_type, $label ) = $section;

		if ( ! community365_author_section_enabled( $author_id, $toggle ) ) {
			continue;
		}
		if ( 'post' !== $post_type && ! post_type_exists( $post_type ) ) {
			continue;
		}

		$section_query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'author'         => $author_id,
				'posts_per_page' => 6,
				'no_found_rows'  => true,
			)
		);
		if ( ! $section_query->have_posts() ) {
			continue;
		}
		?>
		<section class="c365-author-section">
			<h2><?php echo esc_html( $label ); ?></h2>
			<div class="c365-grid">
				<?php
				while ( $section_query->have_posts() ) {
					$section_query->the_post();
					get_template_part( 'template-parts/content', 'card' );
				}
				wp_reset_postdata();
				?>
			</div>
		</section>
	<?php endforeach; ?>
</div>

<?php
get_footer();
