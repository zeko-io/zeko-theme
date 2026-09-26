<?php
/**
 * The template for displaying all single posts
 *
 * @package Zeko
 */

get_header(); ?>

<main id="primary" class="site-main">
	<div class="container">
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
	</div>
</main><!-- #main -->

<?php
get_sidebar();
get_footer();
