<?php
/**
 * The header for our theme
 *
 * Two-row layout: slim top bar (account + utilities) + main header (logo + nav + search).
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Zeko
 */

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">

	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>

	<div id="page" class="site">
		<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'zeko' ); ?></a>

		<header id="masthead" class="site-header">

			<!-- ═══ TOP BAR ═══ -->
			<div class="site-top-bar">
				<div class="container">
					<nav class="top-bar-left" aria-label="<?php esc_attr_e( 'Account', 'zeko' ); ?>">
						<?php if ( is_user_logged_in() ) : ?>
							<?php
							$zeko_chip_user_id     = get_current_user_id();
							$zeko_chip_display     = wp_get_current_user()->display_name;
							$zeko_chip_profile_url = zeko_get_user_profile_url( $zeko_chip_user_id );
							$zeko_chip_avatar      = zeko_get_user_avatar( $zeko_chip_user_id, 26 );
							?>
							<?php
							/* translators: %1$s: user display name. */
							$zeko_chip_aria = esc_attr( sprintf( __( 'View %1$s\'s profile', 'zeko' ), $zeko_chip_display ) );
							?>
							<a class="zeko-account-chip" href="<?php echo esc_url( $zeko_chip_profile_url ); ?>" aria-label="<?php echo $zeko_chip_aria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above. ?>">
								<span class="zeko-account-chip__avatar"><?php echo $zeko_chip_avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() output is self-escaped. ?></span>
								<span class="zeko-account-chip__name"><?php echo esc_html( $zeko_chip_display ); ?></span>
							</a>
							<?php
							wp_nav_menu(
								array(
									'theme_location' => 'account',
									'menu_class'     => 'top-bar-menu',
									'container'      => false,
									'depth'          => 1,
									'fallback_cb'    => false,
								)
							);
							?>
						<?php endif; ?>
					</nav>
					<div class="top-bar-right">
						<button type="button" class="header-search-toggle" aria-expanded="false" aria-controls="header-search-panel" aria-label="<?php esc_attr_e( 'Toggle search', 'zeko' ); ?>">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
						</button>
						<?php do_action( 'zeko_header_top_bar' ); ?>
						<?php if ( shortcode_exists( 'zeko_shop_mini_cart' ) ) : ?>
							<div class="zeko-header-mini-cart">
								<button type="button" class="zeko-mini-cart-toggle" aria-expanded="false" aria-controls="zeko-mini-cart-panel" aria-label="<?php esc_attr_e( 'Toggle shopping cart', 'zeko' ); ?>">
									<svg class="zeko-mini-cart-icon" aria-hidden="true" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
									<span class="zeko-mini-cart-count" data-zeko-cart-count><?php echo (int) do_shortcode( '[zeko_shop_cart_count]' ); ?></span>
								</button>
								<div class="zeko-mini-cart-panel" id="zeko-mini-cart-panel">
									<?php echo do_shortcode( '[zeko_shop_mini_cart]' ); ?>
								</div>
							</div>
						<?php endif; ?>
						<?php if ( ! is_user_logged_in() ) : ?>
	<a href="<?php echo esc_url( zeko_get_page_url( 'auth', 'login' ) ); ?>" class="btn btn-primary btn-sm"><?php esc_html_e( 'Login', 'zeko' ); ?></a>
							<?php if ( ! is_user_logged_in() ) : ?>
	<a href="<?php echo esc_url( zeko_get_page_url( 'auth', 'register' ) ); ?>" class="btn btn-secondary btn-sm"><?php esc_html_e( 'Register', 'zeko' ); ?></a>
	<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- ═══ MAIN HEADER ═══ -->
			<div class="site-main-header">
				<div class="container">
					<div class="site-branding">
						<?php
						the_custom_logo();
						if ( is_front_page() && is_home() ) :
							?>
							<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
							<?php
						else :
							?>
							<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></p>
							<?php
						endif;
						$zeko_description = get_bloginfo( 'description', 'display' );
						if ( $zeko_description || is_customize_preview() ) :
							?>
							<p class="site-description"><?php echo esc_html( $zeko_description ); ?></p>
						<?php endif; ?>
					</div><!-- .site-branding -->

					<nav id="site-navigation" class="main-navigation">
						<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle navigation menu', 'zeko' ); ?>">
							<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
						</button>
						<?php
						$zeko_nav_split = zeko_primary_nav_split();
						if ( ! empty( $zeko_nav_split['bar'] ) ) {
							echo '<ul class="primary-menu" id="primary-menu">';
							foreach ( $zeko_nav_split['bar'] as $zeko_item ) {
								$zeko_item_children = empty( $zeko_item['children'] ) ? '' : ' menu-item-has-children';
								echo '<li class="menu-item' . esc_attr( $zeko_item_children ) . '">';
								echo '<a href="' . esc_url( $zeko_item['url'] ) . '">' . esc_html( $zeko_item['title'] ) . '</a>';
								echo zeko_render_nav_children( $zeko_item['children'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_render_nav_children() returns <ul> markup escaped internally (esc_url/esc_html per child).
								echo '</li>';
							}
							echo '</ul>';
						}
						?>
					</nav><!-- #site-navigation -->

					<?php if ( ! empty( $zeko_nav_split['gear'] ) ) : ?>
						<div class="zeko-nav-gear">
							<button type="button" class="zeko-nav-gear__toggle" aria-expanded="false" aria-controls="zeko-nav-gear-panel" aria-label="<?php esc_attr_e( 'More menu', 'zeko' ); ?>">
								<svg class="zeko-nav-gear__icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
							</button>
							<div class="zeko-nav-gear__panel" id="zeko-nav-gear-panel" aria-hidden="true">
								<div class="zeko-nav-gear__grid">
									<?php foreach ( $zeko_nav_split['gear'] as $zeko_item ) : ?>
										<div class="zeko-nav-gear__hub">
											<a class="zeko-nav-gear__hub-title" href="<?php echo esc_url( $zeko_item['url'] ); ?>"><?php echo esc_html( $zeko_item['title'] ); ?></a>
											<?php echo zeko_render_nav_children( $zeko_item['children'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_render_nav_children() returns <ul> markup escaped internally (esc_url/esc_html per child). ?>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					<?php endif; ?>

				</div>
			</div>

			<!-- Search panel (slides down below main header) -->
			<div id="header-search-panel" class="header-search-panel" aria-hidden="true">
				<div class="container">
					<?php get_search_form(); ?>
				</div>
			</div>

		</header><!-- #masthead -->

		<div id="content" class="site-content">
