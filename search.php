<?php
/**
 * The template for displaying search results pages
 *
 * @package Zeko
 */

get_header(); ?>

<div class="container">
	<div class="zeko-blog-layout">
		<main id="primary" class="site-main">
			<?php if ( have_posts() ) : ?>

				<header class="page-header">
					<h1 class="page-title">
						<?php
						/* translators: %s: search query. */
						printf( esc_html__( 'Search Results for: %s', 'zeko' ), '<span>' . get_search_query() . '</span>' );
						?>
					</h1>
				</header><!-- .page-header -->

				<?php
				while ( have_posts() ) :
					the_post();

					get_template_part( 'template-parts/content', get_post_type() );

				endwhile;

				the_posts_navigation();

			else :
				?>

				<p><?php esc_html_e( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'zeko' ); ?></p>

				<?php get_search_form(); ?>

			<?php endif; ?>
		</main><!-- #main -->

		<?php get_sidebar(); ?>
	</div><!-- .zeko-blog-layout -->
</div>

<?php
get_footer();
