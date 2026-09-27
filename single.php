<?php
/**
 * The template for displaying all single posts
 *
 * @package Zeko
 */

get_header(); ?>

<div class="container">
	<div class="zeko-blog-layout">
		<main id="primary" class="site-main">
			<?php
			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/content', 'single' );

				the_post_navigation();

				if ( comments_open() || get_comments_number() ) :
					comments_template();
				endif;
			endwhile;
			?>
		</main><!-- #main -->

		<?php get_sidebar(); ?>
	</div><!-- .zeko-blog-layout -->
</div>

<?php
get_footer();
