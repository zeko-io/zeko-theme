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
