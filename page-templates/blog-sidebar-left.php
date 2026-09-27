<?php
/**
 * Template Name: Blog - Classic (Sidebar Left)
 *
 * Displays the latest posts in the classic single-column blog layout with the
 * theme sidebar on the left. Select this template from any page's editor.
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
		'posts_per_page' => 10,
		'paged'          => $zeko_paged,
	)
);

get_header();
?>

<div class="container">
	<div class="zeko-blog-layout zeko-blog-layout--sidebar-left">
		<main id="primary" class="site-main page-template-blog-sidebar-left">
			<?php if ( $zeko_posts->have_posts() ) : ?>
				<header class="page-header">
					<h1 class="page-title"><?php echo esc_html( $zeko_blog_title ); ?></h1>
					<?php if ( $zeko_blog_desc ) : ?>
						<div class="archive-description"><?php echo wp_kses_post( $zeko_blog_desc ); ?></div>
					<?php endif; ?>
				</header><!-- .page-header -->

				<?php
				while ( $zeko_posts->have_posts() ) :
					$zeko_posts->the_post();
					get_template_part( 'template-parts/content', get_post_type() );
				endwhile;

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

			else :
				get_template_part( 'template-parts/content', 'none' );
			endif;
			?>
		</main><!-- #primary -->

		<?php get_sidebar(); ?>
	</div><!-- .zeko-blog-layout -->
</div>

<?php
get_footer();
