<?php
/**
 * Template Name: Blog - Grid (No Sidebar)
 *
 * Displays the latest posts as a responsive card grid, full width, with no
 * sidebar. Select this template from any page's editor to build a grid blog.
 *
 * @package Zeko
 */

// Capture the blog page title/description. The main query (the page itself)
// is left untouched; posts are fetched through a secondary WP_Query below.
$zeko_blog_title = get_the_title();
$zeko_blog_desc  = has_excerpt() ? get_the_excerpt() : '';

$zeko_paged = max( 1, (int) get_query_var( 'paged' ) );
$zeko_posts = new WP_Query(
	array(
		'post_type'      => 'post',
		'posts_per_page' => 12,
		'paged'          => $zeko_paged,
	)
);

get_header();
?>

<main id="primary" class="site-main page-template-blog-grid">
	<div class="container">
		<?php if ( $zeko_posts->have_posts() ) : ?>
			<header class="page-header">
				<h1 class="page-title"><?php echo esc_html( $zeko_blog_title ); ?></h1>
				<?php if ( $zeko_blog_desc ) : ?>
					<div class="archive-description"><?php echo wp_kses_post( $zeko_blog_desc ); ?></div>
				<?php endif; ?>
			</header><!-- .page-header -->

			<div class="zeko-blog-grid">
				<?php
				while ( $zeko_posts->have_posts() ) :
					$zeko_posts->the_post();
					?>
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'zeko-blog-card' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="post-thumbnail zeko-blog-card__thumb" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
								<?php the_post_thumbnail( 'post-thumbnail' ); ?>
							</a>
						<?php endif; ?>
						<div class="zeko-blog-card__body">
							<header class="entry-header">
								<?php
								the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
								?>
								<div class="entry-meta">
									<?php
									zeko_posted_on();
									zeko_posted_by();
									?>
								</div><!-- .entry-meta -->
							</header><!-- .entry-header -->
							<div class="entry-excerpt">
								<?php the_excerpt(); ?>
							</div>
							<footer class="entry-footer">
								<a class="more-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read More', 'zeko' ); ?></a>
							</footer>
						</div>
					</article>
					<?php
				endwhile;
				?>
			</div><!-- .zeko-blog-grid -->

			<?php
			if ( $zeko_posts->max_num_pages > 1 ) :
				?>
				<nav class="navigation pagination" aria-label="<?php esc_attr_e( 'Posts', 'zeko' ); ?>">
					<div class="nav-links">
						<?php
						echo wp_kses_post(
							(string) paginate_links(
								array(
									'total'   => $zeko_posts->max_num_pages,
									'current' => $zeko_paged,
								)
							)
						);
						?>
					</div>
				</nav>
				<?php
			endif;

			wp_reset_postdata();
			?>

		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>
	</div>
</main><!-- #primary -->

<?php
get_footer();
