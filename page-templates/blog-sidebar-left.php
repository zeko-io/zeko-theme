<?php
/**
 * Template Name: Blog - Classic (Sidebar Left)
 *
 * Displays the latest posts in the classic single-column blog layout with the
 * theme sidebar on the left. Select this template from any page's editor.
 *
 * @package Zeko
 */

// Capture the blog page title/description before replacing the main query.
$zeko_blog_title = get_the_title();
$zeko_blog_desc  = has_excerpt() ? get_the_excerpt() : '';

query_posts(
	array(
		'post_type'      => 'post',
		'posts_per_page' => 10,
		'paged'          => get_query_var( 'paged' ),
	)
);

get_header();
?>

<div class="container">
	<div class="zeko-blog-layout zeko-blog-layout--sidebar-left">
		<main id="primary" class="site-main page-template-blog-sidebar-left">
			<?php if ( have_posts() ) : ?>
				<header class="page-header">
					<h1 class="page-title"><?php echo esc_html( $zeko_blog_title ); ?></h1>
					<?php if ( $zeko_blog_desc ) : ?>
						<div class="archive-description"><?php echo wp_kses_post( $zeko_blog_desc ); ?></div>
					<?php endif; ?>
				</header><!-- .page-header -->

				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content', get_post_type() );
				endwhile;

				the_posts_pagination();

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
