<?php
/**
 * The template for displaying comments.
 *
 * The area of the page that contains both current comments
 * and the comment form.
 *
 * @package Zeko
 */

/*
 * If the current post is protected by a password and the visitor has not yet
 * entered the password, return early without loading the comments.
 */
if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="comments-area">

	<?php
	// You can start editing here -- including this comment!
	if ( have_comments() ) :
		?>
		<h2 class="comments-title">
			<?php
			$zeko_comment_count = get_comments_number();
			printf(
				/* translators: %s: Comment count number. */
				esc_html( _n( '%s Comment', '%s Comments', $zeko_comment_count, 'zeko' ) ),
				esc_html( number_format_i18n( $zeko_comment_count ) )
			);
			?>
		</h2>

		<?php the_comments_navigation(); ?>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>

		<?php
		the_comments_navigation();

		// If comments are closed and there are comments, leave a message.
		if ( ! comments_open() ) :
			?>
			<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'zeko' ); ?></p>
		<?php endif; ?>

	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title">',
			'title_reply_after'  => '</h3>',
		)
	);
	?>

</div><!-- #comments -->