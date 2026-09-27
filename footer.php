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
				<?php $zeko_email = get_bloginfo( 'admin_email' ); ?>
				<?php $zeko_newsletter_ok = isset( $_GET['newsletter'] ) && 'subscribed' === sanitize_key( wp_unslash( $_GET['newsletter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="footer-widgets">
					<?php if ( is_active_sidebar( 'footer-widgets' ) ) : ?>
						<div class="footer-widget-area footer-widget-area--primary">
							<?php dynamic_sidebar( 'footer-widgets' ); ?>
						</div>
					<?php endif; ?>

					<div class="footer-widget-area footer-widget-area--newsletter">
						<h3 class="footer-widgets-title"><?php esc_html_e( 'Stay in the loop', 'zeko' ); ?></h3>
						<?php if ( $zeko_newsletter_ok ) : ?>
							<p class="footer-newsletter-done"><?php esc_html_e( 'Thanks for subscribing!', 'zeko' ); ?></p>
						<?php else : ?>
							<form class="footer-newsletter" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
								<input type="hidden" name="action" value="zeko_newsletter_subscribe" />
								<?php wp_nonce_field( 'zeko_newsletter_subscribe', 'zeko_newsletter_nonce' ); ?>
								<label class="screen-reader-text" for="zeko-newsletter-email"><?php esc_html_e( 'Email address', 'zeko' ); ?></label>
								<input type="email" id="zeko-newsletter-email" name="zeko_newsletter_email" placeholder="<?php esc_attr_e( 'you@example.com', 'zeko' ); ?>" required />
								<button type="submit" class="btn btn-primary btn-sm"><?php esc_html_e( 'Subscribe', 'zeko' ); ?></button>
							</form>
						<?php endif; ?>
						<?php zeko_footer_social_render(); ?>
					</div>
				</div>

				<div class="site-info">
					<div class="copyright">
						&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'zeko' ); ?>
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