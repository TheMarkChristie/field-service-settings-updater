<?php
/**
 * Author profile page.
 *
 * Dark profile card (photo, tagline, bio, link chips, stats), filter pills
 * (content types + the member's top categories), author-scoped search, and
 * a paginated list of their content. Members control the photo, tagline,
 * bio, links, and which type pills exist from their own profile screen.
 *
 * @package Community365
 */

get_header();

$author_id = get_queried_object_id();
$cover     = get_user_meta( $author_id, 'synpro_cover_url', true );
$tagline   = get_user_meta( $author_id, 'synpro_tagline', true );
$types     = community365_author_types( $author_id );
$topics    = community365_author_top_categories( $author_id );

// Active filters (plain links — no JS required).
$active_type  = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$active_topic = isset( $_GET['topic'] ) ? sanitize_title( wp_unslash( $_GET['topic'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( 'all' !== $active_type && ! isset( $types[ $active_type ] ) ) {
	$active_type = 'all';
}

$post_types = array();
foreach ( $types as $slug => $spec ) {
	if ( 'all' === $active_type || $active_type === $slug ) {
		$post_types[] = $spec['post_type'];
	}
}

$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$query = new WP_Query(
	array(
		'post_type'      => $post_types ? $post_types : array( 'post' ),
		'author'         => $author_id,
		'posts_per_page' => 10,
		'paged'          => $paged,
		'category_name'  => $active_topic,
	)
);

$total_posts = 0;
foreach ( $types as $spec ) {
	$total_posts += (int) count_user_posts( $author_id, $spec['post_type'], true );
}
$total_views = community365_author_total_views( $author_id );

$author_url = get_author_posts_url( $author_id );
$pill_url   = function ( $type, $topic ) use ( $author_url ) {
	$args = array();
	if ( 'all' !== $type ) {
		$args['type'] = $type;
	}
	if ( $topic ) {
		$args['topic'] = $topic;
	}
	return $args ? add_query_arg( $args, $author_url ) : $author_url;
};
?>

<div class="c365-container c365-author-wrap">

	<section class="c365-profile-card <?php echo $cover ? 'has-cover' : ''; ?>"
		<?php if ( $cover ) : ?>style="background-image: linear-gradient(135deg, rgba(15,17,24,.88), rgba(24,27,40,.88)), url('<?php echo esc_url( $cover ); ?>');"<?php endif; ?>>
		<div class="c365-profile-avatar"><?php echo get_avatar( $author_id, 120 ); ?></div>
		<div class="c365-profile-main">
			<?php if ( $tagline ) : ?>
				<p class="c365-profile-tagline"><?php echo esc_html( $tagline ); ?></p>
			<?php endif; ?>
			<h1 class="c365-profile-name"><?php echo esc_html( get_the_author_meta( 'display_name', $author_id ) ); ?></h1>
			<?php if ( community365_author_section_enabled( $author_id, 'synpro_show_bio' ) ) : ?>
				<?php $bio = get_the_author_meta( 'description', $author_id ); ?>
				<?php if ( $bio ) : ?>
					<p class="c365-profile-bio"><?php echo esc_html( $bio ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( community365_author_section_enabled( $author_id, 'synpro_show_links' ) ) : ?>
				<?php community365_author_links( $author_id ); ?>
			<?php endif; ?>
		</div>
		<div class="c365-profile-stats">
			<div>
				<span class="c365-profile-num"><?php echo esc_html( number_format_i18n( $total_posts ) ); ?></span>
				<span class="c365-profile-lbl"><?php esc_html_e( 'Posts', 'community365' ); ?></span>
			</div>
			<?php if ( $total_views ) : ?>
				<div>
					<span class="c365-profile-num"><?php echo esc_html( number_format_i18n( $total_views ) ); ?></span>
					<span class="c365-profile-lbl"><?php esc_html_e( 'Views', 'community365' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<nav class="c365-pills" aria-label="<?php esc_attr_e( 'Filter content', 'community365' ); ?>">
		<a class="c365-pill <?php echo 'all' === $active_type && ! $active_topic ? 'is-active' : ''; ?>" href="<?php echo esc_url( $author_url ); ?>"><?php esc_html_e( 'All posts', 'community365' ); ?></a>
		<?php foreach ( $types as $slug => $spec ) : ?>
			<a class="c365-pill <?php echo $active_type === $slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( $pill_url( $slug, '' ) ); ?>"><?php echo esc_html( $spec['label'] ); ?></a>
		<?php endforeach; ?>
		<?php foreach ( $topics as $topic ) : ?>
			<a class="c365-pill <?php echo $active_topic === $topic->slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( $pill_url( 'all', $topic->slug ) ); ?>"><?php echo esc_html( $topic->name ); ?></a>
		<?php endforeach; ?>
	</nav>

	<form class="c365-author-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<?php $c365_author_search_label = sprintf( /* translators: %s: author display name. */ __( 'Search %s’s content…', 'community365' ), get_the_author_meta( 'display_name', $author_id ) ); ?>
		<label class="screen-reader-text" for="c365-author-search-field"><?php echo esc_html( $c365_author_search_label ); ?></label>
		<input type="search" id="c365-author-search-field" name="s" placeholder="<?php echo esc_attr( $c365_author_search_label ); ?>">
		<input type="hidden" name="author" value="<?php echo esc_attr( $author_id ); ?>">
	</form>

	<?php if ( $query->have_posts() ) : ?>
		<div class="c365-list">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				get_template_part( 'template-parts/content', 'list' );
			}
			wp_reset_postdata();
			?>
		</div>

		<nav class="c365-pagination" aria-label="<?php esc_attr_e( 'Posts', 'community365' ); ?>">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'total'     => (int) $query->max_num_pages,
						'current'   => $paged,
						'add_args'  => array_filter(
							array(
								'type'  => 'all' === $active_type ? '' : $active_type,
								'topic' => $active_topic,
							)
						),
						'prev_text' => __( '&larr; Newer', 'community365' ),
						'next_text' => __( 'Older &rarr;', 'community365' ),
					)
				)
			);
			?>
		</nav>
	<?php else : ?>
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>
</div>

<?php
get_footer();
