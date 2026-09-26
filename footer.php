<?php
/**
 * The footer for our theme
 *
 * This is the template that displays all of the <footer> section and everything after <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Zeko
 */

?>

		</div><!-- #content -->

		<footer id="colophon" class="site-footer">
			<div class="container">
				<div class="footer-widgets">
					<?php if ( is_active_sidebar( 'footer-widgets' ) ) : ?>
						<div class="footer-widget-area">
							<?php dynamic_sidebar( 'footer-widgets' ); ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="site-info">
					<div class="copyright">
						&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'zeko' ); ?>
					</div>
<?php $zeko_footer_credit = get_theme_mod( 'zeko_footer_credit', __( 'Developed by <a href="https://ozconsultz.com" target="_blank" rel="noopener noreferrer">Ozconsultz.com</a>', 'zeko' ) ); ?>
					<?php if ( '' !== trim( wp_strip_all_tags( (string) $zeko_footer_credit ) ) ) : ?>
						<div class="credits">
							<?php echo wp_kses_post( (string) $zeko_footer_credit ); ?>
						</div>
					<?php endif; ?>
					<div class="footer-menu">
						<?php
						wp_nav_menu(
							array(
								'theme_location' => 'footer',
								'menu_id'        => 'footer-menu',
								'depth'          => 1,
							)
						);
						?>
					</div>
				</div><!-- .site-info -->
			</div>
		</footer><!-- #colophon -->
	</div><!-- #page -->

	<?php wp_footer(); ?>

</body>
</html>
