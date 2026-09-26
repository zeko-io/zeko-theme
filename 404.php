<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @package Zeko
 */

get_header(); ?>

<main id="primary" class="site-main">
	<div class="container">
		<section class="error-404 not-found">
			<header class="page-header">
				<h1 class="page-title"><?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'zeko' ); ?></h1>
			</header><!-- .page-header -->

			<div class="page-content">
				<p><?php esc_html_e( 'It looks like nothing was found at this location. Maybe try a search?', 'zeko' ); ?></p>

				<?php get_search_form(); ?>
			</div><!-- .page-content -->
		</section><!-- .error-404 -->
	</div>
</main><!-- #main -->

<?php
get_footer();
