<?php
/**
 * Profile Template
 * Displays individual user profiles
 *
 * @package Zeko
 */

get_header();
?>

<main id="primary" class="site-main">
	<div class="zeko-profile-page-container">
		<?php
		// Determine which user profile to show.
		$profile_username = get_query_var( 'zeko_profile_username' );
		$profile_id       = get_query_var( 'zeko_profile_id' );

		$user = null;

		if ( $profile_username ) {
			$user = get_user_by( 'login', $profile_username );
		} elseif ( $profile_id ) {
			$user = get_userdata( $profile_id );
		}

		if ( ! $user ) {
			?>
			<div class="zeko-error-page">
				<h1><?php esc_html_e( 'Profile Not Found', 'zeko' ); ?></h1>
				<p><?php esc_html_e( 'The user profile you are looking for does not exist.', 'zeko' ); ?></p>
				<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'Return Home', 'zeko' ); ?></a></p>
			</div>
			<?php
		} else {
			// Check if edit mode.
			$is_edit_mode = get_query_var( 'zeko_edit_profile' );

			if ( $is_edit_mode ) {
				echo do_shortcode( '[zeko_profile_editor user_id="' . intval( $user->ID ) . '"]' );
			} else {
				echo do_shortcode( '[zeko_public_profile user_id="' . intval( $user->ID ) . '"]' );

				// Allow plugins to inject profile sections (e.g. jobs, learning).
				?>
				<div class="zeko-profile-extended-sections" style="margin-top:32px;">
					<?php do_action( 'zeko_profile_view_sections', $user->ID ); ?>
				</div>
				<?php

				// Allow plugins to inject profile stats (e.g. jobs applied, courses completed).
				?>
				<div class="zeko-profile-stats" style="margin-top:24px;">
					<?php do_action( 'zeko_theme_profile_stats', $user->ID ); ?>
				</div>
				<?php
			}
		}
		?>
	</div>
</main>

<?php
get_footer();
?>
