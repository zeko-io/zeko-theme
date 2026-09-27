<?php
/**
 * Functions which enhance the theme by hooking into WordPress
 *
 * @package Zeko
 */

/**
 * Adds custom classes to the array of body classes.
 *
 * @param mixed $classes Classes.
 */
function zeko_body_classes( $classes ) {
	// Adds a class of hfeed to non-singular pages.
	if ( ! is_singular() ) {
		$classes[] = 'hfeed';
	}

	// Add class if sidebar is active.
	if ( is_active_sidebar( 'sidebar-1' ) ) {
		$classes[] = 'has-sidebar';
	} else {
		$classes[] = 'no-sidebar';
	}

	return $classes;
}
add_filter( 'body_class', 'zeko_body_classes' );

/**
 * Add a pingback url auto-discovery header for single posts, pages, or attachments.
 */
function zeko_pingback_header() {
	if ( is_singular() && pings_open() ) {
		printf( '<link rel="pingback" href="%s">', esc_url( get_bloginfo( 'pingback_url' ) ) );
	}
}
add_action( 'wp_head', 'zeko_pingback_header' );

/**
 * Change the excerpt more text
 *
 * @param _ $_more more.
 */
function zeko_excerpt_more( $_more ) {
	return '...';
}
add_filter( 'excerpt_more', 'zeko_excerpt_more' );

/**
 * Change the excerpt length
 *
 * @param _ $_length length.
 */
function zeko_excerpt_length( $_length ) {
	return 20;
}
add_filter( 'excerpt_length', 'zeko_excerpt_length' );

/**
 * Modify the read more link
 */
function zeko_modify_read_more_link() {
	return '<a class="more-link" href="' . get_permalink() . '">' . __( 'Read More', 'zeko' ) . '</a>';
}
add_filter( 'the_content_more_link', 'zeko_modify_read_more_link' );

/**
 * Add custom classes to pagination links
 *
 * @param mixed $args Args.
 */
function zeko_pagination_classes( $args ) {
	$args['prev_text']          = __( 'Previous', 'zeko' );
	$args['next_text']          = __( 'Next', 'zeko' );
	$args['before_page_number'] = '<span class="page-number">';
	$args['after_page_number']  = '</span>';
	return $args;
}
add_filter( 'navigation_markup_template', 'zeko_pagination_classes' );

/**
 * Custom comment form fields
 *
 * @param mixed $fields Fields.
 */
function zeko_comment_form_fields( $fields ) {
	$commenter = wp_get_current_commenter();
	$req       = get_option( 'require_name_email' );
	$aria_req  = ( $req ? " aria-required='true'" : '' );
	$html5     = 'html5';

	$fields['author'] = '<p class="comment-form-author"><label for="author">' . __( 'Name', 'zeko' ) . '</label> ' .
		( $req ? '<span class="required">*</span>' : '' ) .
		'<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) .
		'" size="30"' . $aria_req . ' /></p>';

	$fields['email'] = '<p class="comment-form-email"><label for="email">' . __( 'Email', 'zeko' ) . '</label> ' .
		( $req ? '<span class="required">*</span>' : '' ) .
		'<input id="email" name="email" ' . ( $html5 ? 'type="email"' : 'type="text"' ) . ' value="' . esc_attr( $commenter['comment_author_email'] ) .
		'" size="30"' . $aria_req . ' /></p>';

	$fields['url'] = '<p class="comment-form-url"><label for="url">' . __( 'Website', 'zeko' ) . '</label>' .
		'<input id="url" name="url" ' . ( $html5 ? 'type="url"' : 'type="text"' ) . ' value="' . esc_attr( $commenter['comment_author_url'] ) .
		'" size="30" /></p>';

	return $fields;
}
add_filter( 'comment_form_default_fields', 'zeko_comment_form_fields' );

/**
 * Custom comment form submit button
 *
 * @param mixed $submit_button Submit button.
 */
function zeko_comment_form_submit_button( $submit_button ) {
	$submit_button = '<button name="%1$s" type="submit" id="%2$s" class="%3$s btn btn-primary">%4$s</button>';
	return $submit_button;
}
add_filter( 'comment_form_submit_button', 'zeko_comment_form_submit_button', 10, 4 );

/**
 * Custom search form
 *
 * @param mixed $form Form.
 */
function zeko_search_form( $form ) {
	$form = '<form role="search" method="get" class="search-form" action="' . esc_url( home_url( '/' ) ) . '">
        <label>
            <span class="screen-reader-text">' . _x( 'Search for:', 'label', 'zeko' ) . '</span>
            <input type="search" class="search-field" placeholder="' . esc_attr_x( 'Search &hellip;', 'placeholder', 'zeko' ) . '" value="' . get_search_query() . '" name="s" />
        </label>
        <button type="submit" class="search-submit btn btn-secondary">' . esc_attr_x( 'Search', 'submit button', 'zeko' ) . '</button>
    </form>';

	return $form;
}
add_filter( 'get_search_form', 'zeko_search_form' );

/**
 * Custom password form
 *
 * @param int|float $post Post.
 */
function zeko_password_form( $post = 0 ) {
	$post   = get_post( $post );
	$label  = 'pwbox-' . ( empty( $post->ID ) ? wp_rand() : $post->ID );
	$output = '<form class="protected-post-form" action="' . esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ) . '" method="post">
        <p>' . __( 'This content is password protected. To view it please enter your password below:', 'zeko' ) . '</p>
        <div class="form-group">
            <label for="' . $label . '">' . __( 'Password:', 'zeko' ) . '</label>
            <input name="post_password" id="' . $label . '" type="password" class="form-control" />
        </div>
        <button type="submit" class="btn btn-primary" name="Submit">' . esc_attr__( 'Enter', 'zeko' ) . '</button>
    </form>';

	return $output;
}
add_filter( 'the_password_form', 'zeko_password_form' );

/**
 * Footer newsletter subscription handler (admin-post).
 *
 * Delegates to Zeko Core's provider layer (self-hosted list, Sendinblue/Brevo
 * API, etc.) when available; falls back to a plain local option store only if
 * Zeko Core is inactive.
 */
function zeko_footer_newsletter_handle() {
	if ( ! isset( $_POST['zeko_newsletter_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['zeko_newsletter_nonce'] ) ), 'zeko_newsletter_subscribe' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslashAlreadySanitized
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) . '?newsletter=denied' );
		exit;
	}

	$email = isset( $_POST['zeko_newsletter_email'] ) ? sanitize_email( wp_unslash( $_POST['zeko_newsletter_email'] ) ) : '';
	$base  = wp_get_referer() ? wp_get_referer() : home_url( '/' );

	if ( ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'newsletter', 'invalid', $base ) );
		exit;
	}

	if ( class_exists( 'Zeko_Core_Newsletter' ) && method_exists( 'Zeko_Core_Newsletter', 'get_instance' ) ) {
		$result = Zeko_Core_Newsletter::get_instance()->subscribe( $email );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'newsletter', 'error', $base ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'newsletter', 'subscribed', $base ) );
		exit;
	}

	// Fallback: local-only storage (no Zeko Core).
	$subscribers = get_option( 'zeko_newsletter_subscribers', array() );
	if ( ! is_array( $subscribers ) ) {
		$subscribers = array();
	}
	if ( ! in_array( $email, $subscribers, true ) ) {
		$subscribers[] = $email;
		update_option( 'zeko_newsletter_subscribers', $subscribers );
	}
	wp_safe_redirect( add_query_arg( 'newsletter', 'subscribed', $base ) );
	exit;
}
add_action( 'admin_post_zeko_newsletter_subscribe', 'zeko_footer_newsletter_handle' );
add_action( 'admin_post_nopriv_zeko_newsletter_subscribe', 'zeko_footer_newsletter_handle' );

/**
 * Footer social links. Filtered so modules can inject their own.
 *
 * @return array<string,string> handle => url.
 */
function zeko_footer_social_links() {
	$social = array(
		'facebook'  => get_theme_mod( 'zeko_footer_social_facebook', '' ),
		'twitter'   => get_theme_mod( 'zeko_footer_social_twitter', '' ),
		'linkedin'  => get_theme_mod( 'zeko_footer_social_linkedin', '' ),
		'instagram' => get_theme_mod( 'zeko_footer_social_instagram', '' ),
		'youtube'   => get_theme_mod( 'zeko_footer_social_youtube', '' ),
	);

	return (array) apply_filters( 'zeko_footer_social_links', array_filter( $social, 'strlen' ) );
}

/**
 * Footer social icon markup (inline SVG, branded colors).
 *
 * @return string
 * @param string $handle Social handle (facebook/twitter/etc).
 */
function zeko_footer_social_icon( $handle ) {
	$icons = array(
		'facebook'  => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>',
		'twitter'   => '<path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path>',
		'linkedin'  => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle>',
		'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>',
		'youtube'   => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon>',
	);

	if ( ! isset( $icons[ $handle ] ) ) {
		return '';
	}

	return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[ $handle ] . '</svg>';
}

/**
 * Render the footer social row.
 */
function zeko_footer_social_render() {
	$links = zeko_footer_social_links();
	if ( empty( $links ) ) {
		return;
	}
	echo '<div class="footer-social">';
	foreach ( $links as $handle => $url ) {
		echo '<a class="footer-social__link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( ucfirst( $handle ) ) . '">';
		echo zeko_footer_social_icon( $handle ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG, static strings.
		echo '</a>';
	}
	echo '</div>';
}
