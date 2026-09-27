<?php
/**
 * Profile builder functionality
 *
 * @package Zeko
 */

/**
 * Create custom profile database tables
 */
function zeko_create_profile_tables() {
	// Table creation moved to Zeko Core (app schema module 'profile').
	if ( class_exists( 'Zeko_Core_DB' ) ) {
		Zeko_Core_DB::get_instance()->ensure_module( 'profile' );
		return;
	}

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Profile fields table.
	$table_name = $wpdb->prefix . 'zeko_profile_fields';
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
		$sql = "CREATE TABLE $table_name (
            field_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            field_name varchar(100) NOT NULL,
            field_value longtext DEFAULT NULL,
            field_visibility varchar(20) DEFAULT 'public',
            field_order int(11) DEFAULT 0,
            PRIMARY KEY (field_id),
            KEY user_id (user_id),
            KEY field_name (field_name),
        ) $charset_collate";
		zeko_safe_db_delta( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// Profile settings table.
	$settings_table = $wpdb->prefix . 'zeko_profile_settings';
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$settings_table'" ) !== $settings_table ) {
		$sql = "CREATE TABLE $settings_table (
            setting_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            setting_name varchar(100) NOT NULL,
            setting_value longtext DEFAULT NULL,
            PRIMARY KEY (setting_id),
            KEY user_id (user_id),
            KEY setting_name (setting_name)
        ) $charset_collate;";
		zeko_safe_db_delta( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}
}

/**
 * Check if the current page is a user profile page.
 * This is a placeholder and should be implemented based on your theme's structure.
 * For example, it might check if it's an author archive or a custom profile page.
 */
function zeko_is_profile_page() {
	// Example implementation: check if it's an author archive page.
	if ( is_author() ) {
		return true;
	}

	// Example implementation: check for a custom profile page template.
	// if (is_page_template('template-profile.php')) {.
	// return true;.
	// }.

	// More complex logic might be needed depending on your URL structure.
	// For instance, if profiles are at /profile/{username}.
	$current_url = home_url( add_query_arg( null, null ) );
	if ( strpos( $current_url, '/profile/' ) !== false ) {
		return true;
	}

	return false;
}

/**
 * Safe dbDelta function with existence check
 *
 * @param mixed $sql Sql.
 */
function zeko_safe_db_delta( $sql ) {
	if ( ! function_exists( 'dbDelta' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}
	dbDelta( $sql );
}

/**
 * Get profile fields for a user
 *
 * @param mixed $user_id User id.
 */
function zeko_get_profile_fields( $user_id ) {
	// Routes to Zeko Core data layer (#46); fallback for Core-less installs.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		return Zeko_Core_App_Data::get_profile_fields( (int) $user_id );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_profile_fields';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	$fields = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT field_name, field_value FROM $table_name WHERE user_id = %d",
			$user_id
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	$profile_fields = array();
	foreach ( $fields as $field ) {
		$profile_fields[ $field['field_name'] ] = $field['field_value'];
	}

	return $profile_fields;
}

/**
 * Save profile field to custom table
 *
 * @param mixed $user_id User id.
 * @param mixed $field_name Field name.
 * @param mixed $field_value Field value.
 */
function zeko_save_profile_field( $user_id, $field_name, $field_value ) {
	// Routes to Zeko Core data layer (#46); fallback for Core-less installs.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		Zeko_Core_App_Data::save_profile_field( (int) $user_id, (string) $field_name, (string) $field_value );
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_profile_fields';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	// Check if field already exists.
	$existing = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d AND field_name = %s",
			$user_id,
			$field_name
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	if ( $existing ) {
		// Update existing field.
		$wpdb->update(
			$table_name,
			array(
				'field_value'      => $field_value,
				'field_visibility' => 'private', // Default visibility.
			),
			array(
				'user_id'    => $user_id,
				'field_name' => $field_name,
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
	} else {
		// Insert new field.
		$wpdb->insert(
			$table_name,
			array(
				'user_id'          => $user_id,
				'field_name'       => $field_name,
				'field_value'      => $field_value,
				'field_visibility' => 'private',
				'field_order'      => 0,
			),
			array( '%d', '%s', '%s', '%s', '%d' )
		);
	}
}

/**
 * Get profile completion percentage for a user
 *
 * @param mixed $user_id User id.
 */
function zeko_get_profile_completion_percentage( $user_id ) {
	// Define a list of essential profile fields.
	$essential_fields = array(
		'first_name',
		'last_name',
		'description', // WordPress 'About Me'.
		// Add custom fields if applicable.
		'zeko_profile_avatar',
		'zeko_profile_cover',
		'zeko_social_facebook',
		'zeko_social_twitter',
		'zeko_social_linkedin',
	);

	$completed_fields = 0;
	$total_fields     = count( $essential_fields );

	foreach ( $essential_fields as $field ) {
		$value = '';
		// Check standard WordPress user meta.
		if ( in_array( $field, array( 'first_name', 'last_name', 'description' ), true ) ) {
			$value = get_user_meta( $user_id, $field, true );
		} else {
			// Check custom Zeko profile fields (assuming they are stored similarly).
			// You might need a specific function to get a single custom field value.
			$profile_data = zeko_get_profile_fields( $user_id ); // Get all custom profile fields.
			if ( isset( $profile_data[ $field ] ) ) {
				$value = $profile_data[ $field ];
			}
		}

		if ( ! empty( $value ) ) {
			++$completed_fields;
		}
	}

	if ( 0 === $total_fields ) {
		return 100; // No essential fields defined, consider 100% complete.
	}

	$percentage = ( $completed_fields / $total_fields ) * 100;
	return round( $percentage );
}

/**
 * Resolve the profile target user from pretty slug / legacy query vars.
 * Handles: zeko_profile_slug (meta, login, nicename), zeko_profile_username
 * (login), zeko_profile_id and ?user_id=.
 *
 * @return int Resolved user ID (0 when unresolvable).
 * @param bool $fallback_current Whether to fall back to the current user.
 */
function zeko_resolve_profile_user_id( $fallback_current = true ) {
	$slug = (string) get_query_var( 'zeko_profile_slug', '' );
	if ( '' !== $slug ) {
		$slug = sanitize_title( $slug );

		$by_meta = get_users(
			array(
				'meta_key'   => 'zeko_profile_slug',
				'meta_value' => $slug,
				'number'     => 1,
				'fields'     => 'ID',
			)
		);
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( ! empty( $by_meta ) ) {
			return (int) $by_meta[0];
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$user = get_user_by( 'login', $slug );
		if ( ! $user ) {
			$user = get_user_by( 'slug', $slug );
		}
		if ( ! $user ) {
			$user = get_user_by( 'nicename', $slug );
		}
		if ( $user ) {
			return (int) $user->ID;
		}
	}

	$username = (string) get_query_var( 'zeko_profile_username', '' );
	if ( '' !== $username ) {
		$user = get_user_by( 'login', $username );
		if ( $user ) {
			return (int) $user->ID;
		}
	}

	$user_id = (int) get_query_var( 'zeko_profile_id', 0 );
	if ( $user_id > 0 ) {
		return $user_id;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['user_id'] ) && absint( $_GET['user_id'] ) > 0 ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return absint( $_GET['user_id'] );
	}

	if ( ! $fallback_current ) {
		return 0;
	}

	return get_current_user_id();
}

/**
 * Map e-mail address or user object to a user ID for avatar resolution.
 *
 * @param 0|int|string|\WP_User|\WP_Comment $id_or_email Avatar identifier.
 * @return int User ID (0 when unresolvable).
 */
function zeko_profile_avatar_user_id( $id_or_email ) {
	if ( $id_or_email instanceof \WP_User ) {
		return (int) $id_or_email->ID;
	}

	if ( $id_or_email instanceof \WP_Comment ) {
		return (int) $id_or_email->user_id;
	}

	if ( is_numeric( $id_or_email ) ) {
		$user = get_userdata( (int) $id_or_email );
		return $user ? (int) $user->ID : 0;
	}

	if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
		return $user ? (int) $user->ID : 0;
	}

	return 0;
}

/**
 * Honor custom Zeko avatars site-wide.
 * The zeko_profile_avatar (uploaded in the profile editor) takes precedence
 * over Gravatar so get_avatar()/get_avatar_url() everywhere show the uploaded
 * one (fixes Zeko_Core_Helpers::get_user_avatar and the public profile).
 *
 * @return array
 * @param array $args Avatar args.
 * @param mixed $id_or_email Identifier.
 */
function zeko_profile_pre_get_avatar_data( $args, $id_or_email ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $args;
	}

	$user_id = zeko_profile_avatar_user_id( $id_or_email );
	if ( ! $user_id ) {
		return $args;
	}

	$avatar = get_user_meta( $user_id, 'zeko_profile_avatar', true );
	if ( is_string( $avatar ) && '' !== $avatar ) {
		$args['url']          = $avatar;
		$args['found_avatar'] = true;
	}

	return $args;
}
add_filter( 'pre_get_avatar_data', 'zeko_profile_pre_get_avatar_data', 10, 2 );

/**
 * Register the profile query vars (theme fallback for Core-less / AI-less
 * installs; harmless when zeko-ai owns routing).
 *
 * @return array
 * @param array $vars Query vars.
 */
function zeko_profile_query_vars( $vars ) {
	$vars[] = 'zeko_profile_username';
	$vars[] = 'zeko_profile_id';
	$vars[] = 'zeko_edit_profile';
	$vars[] = 'zeko_profile_slug';
	return $vars;
}
add_filter( 'query_vars', 'zeko_profile_query_vars' );

/**
 * Register pretty /profile/{slug}/ routing when zeko-ai is absent.
 * zeko-ai owns this rewrite when active, so we stay out of its way.
 */
function zeko_profile_register_rewrite() {
	if ( shortcode_exists( 'zeko_public_profile' ) ) {
		return;
	}

	add_rewrite_rule( '^profile/([^/]+)/?$', 'index.php?zeko_profile_slug=$matches[1]', 'top' );

	if ( ! get_option( 'zeko_profile_rewrite_flushed' ) ) {
		flush_rewrite_rules();
		update_option( 'zeko_profile_rewrite_flushed', 1 );
	}
}
add_action( 'init', 'zeko_profile_register_rewrite', 30 );

/**
 * Route pretty profile URLs to the profile template when zeko-ai is absent.
 *
 * @return string
 * @param string $template Template path.
 */
function zeko_profile_route_template( $template ) {
	if ( shortcode_exists( 'zeko_public_profile' ) ) {
		return $template;
	}

	$slug     = (string) get_query_var( 'zeko_profile_slug', '' );
	$username = (string) get_query_var( 'zeko_profile_username', '' );
	$user_id  = (int) get_query_var( 'zeko_profile_id', 0 );

	if ( '' === $slug && '' === $username && ! $user_id ) {
		return $template;
	}

	$profile_template = locate_template( 'zeko-profile-template.php' );
	if ( $profile_template ) {
		return $profile_template;
	}

	return $template;
}
add_filter( 'template_include', 'zeko_profile_route_template', 99 );

/**
 * Save an uploaded avatar/cover image for a profile.
 * Validates through Zeko_Core_Upload when present, else a hard-coded image
 * allow-list via wp_handle_upload.
 *
 * @return true|\WP_Error
 * @param string $field $_FILES key.
 * @param int    $user_id Target user ID.
 * @param string $meta_key Profile field / user meta key.
 */
function zeko_profile_handle_upload( $field, $user_id, $meta_key ) {
	if ( empty( $_FILES[ $field ]['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked by caller.
		return true;
	}

	$file = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- delegated to upload policy; nonce checked by caller.

	if ( class_exists( 'Zeko_Core_Upload' ) ) {
		$result = Zeko_Core_Upload::handle_upload( $file, 'image' );
	} else {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$mimes  = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
		);
		$result = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $mimes,
			)
		);
	}

	if ( is_wp_error( $result ) || empty( $result['url'] ) ) {
		return is_wp_error( $result ) ? $result : new WP_Error( 'zeko_upload_failed', __( 'The image could not be saved.', 'zeko' ) );
	}

	$url = esc_url_raw( $result['url'] );
	update_user_meta( $user_id, $meta_key, $url );
	if ( function_exists( 'zeko_save_profile_field' ) ) {
		zeko_save_profile_field( $user_id, $meta_key, $url );
	}

	return true;
}

/**
 * [zeko_profile_editor] — full member profile editor.
 * Editable fields: display name, first/last name, bio, avatar, cover, socials.
 * Saves to WP user meta AND the profile-fields table (via zeko_save_profile_field)
 * so completion % and ecosystem reads stay consistent, then logs activity.
 *
 * @return string
 * @param array  $atts Atts.
 * @param string $content Content.
 */
function zeko_profile_editor_shortcode( $atts = array(), $content = '' ) {
	unset( $content );
	if ( ! is_user_logged_in() ) {
		return '<div class="zeko-profile-editor"><p>' . esc_html__( 'You must be logged in to edit your profile.', 'zeko' ) . '</p></div>';
	}

	$atts    = shortcode_atts( array( 'user_id' => 0 ), $atts, 'zeko_profile_editor' );
	$user_id = (int) $atts['user_id'];
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
		return '<div class="zeko-profile-editor"><p>' . esc_html__( 'You are not allowed to edit this profile.', 'zeko' ) . '</p></div>';
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return '<div class="zeko-profile-editor"><p>' . esc_html__( 'Profile not found.', 'zeko' ) . '</p></div>';
	}

	$saved = false;
	if ( isset( $_POST['zeko_profile_edit_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['zeko_profile_edit_nonce'] ) ), 'zeko_profile_edit_' . $user_id ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslashAlreadySanitized
		$saved = zeko_profile_editor_save( $user_id );
	}

	$profile_fields = zeko_get_profile_fields( $user_id );
	$social_keys    = array( 'facebook', 'twitter', 'linkedin', 'instagram' );

	ob_start();
	?>
	<div class="zeko-profile-editor">
		<?php if ( $saved ) : ?>
			<div class="zeko-completion-message success"><?php esc_html_e( 'Your profile has been updated.', 'zeko' ); ?></div>
		<?php endif; ?>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( get_permalink() ); ?>">
			<?php wp_nonce_field( 'zeko_profile_edit_' . $user_id, 'zeko_profile_edit_nonce' ); ?>

			<div class="zeko-form-row">
				<div class="zeko-form-group">
					<label for="zeko_display_name"><?php esc_html_e( 'Display Name', 'zeko' ); ?></label>
					<input type="text" id="zeko_display_name" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>" />
				</div>
			</div>

			<div class="zeko-form-row">
				<div class="zeko-form-group">
					<label for="zeko_first_name"><?php esc_html_e( 'First Name', 'zeko' ); ?></label>
					<input type="text" id="zeko_first_name" name="first_name" value="<?php echo esc_attr( get_user_meta( $user_id, 'first_name', true ) ); ?>" />
				</div>
				<div class="zeko-form-group">
					<label for="zeko_last_name"><?php esc_html_e( 'Last Name', 'zeko' ); ?></label>
					<input type="text" id="zeko_last_name" name="last_name" value="<?php echo esc_attr( get_user_meta( $user_id, 'last_name', true ) ); ?>" />
				</div>
			</div>

			<div class="zeko-form-group">
				<label for="zeko_description"><?php esc_html_e( 'About Me', 'zeko' ); ?></label>
				<textarea id="zeko_description" name="description" rows="5"><?php echo esc_textarea( get_user_meta( $user_id, 'description', true ) ); ?></textarea>
			</div>

			<div class="zeko-form-row">
				<div class="zeko-form-group">
					<label for="zeko_avatar_upload"><?php esc_html_e( 'Profile Picture', 'zeko' ); ?></label>
					<input type="file" id="zeko_avatar_upload" name="zeko_avatar_upload" accept="image/jpeg,image/png,image/gif,image/webp" />
					<?php if ( ! empty( $profile_fields['zeko_profile_avatar'] ) ) : ?>
						<div class="zeko-avatar-container" style="margin-top:10px;"><img class="zeko-avatar-img" src="<?php echo esc_url( $profile_fields['zeko_profile_avatar'] ); ?>" alt="" /></div>
					<?php endif; ?>
				</div>
				<div class="zeko-form-group">
					<label for="zeko_cover_upload"><?php esc_html_e( 'Cover Photo', 'zeko' ); ?></label>
					<input type="file" id="zeko_cover_upload" name="zeko_cover_upload" accept="image/jpeg,image/png,image/gif,image/webp" />
					<?php if ( ! empty( $profile_fields['zeko_profile_cover'] ) ) : ?>
						<div class="zeko-cover-container" style="height:120px;margin-top:10px;"><img class="zeko-cover-img" src="<?php echo esc_url( $profile_fields['zeko_profile_cover'] ); ?>" alt="" /></div>
					<?php endif; ?>
				</div>
			</div>

			<div class="zeko-profile-details-grid" style="margin-top:10px;">
				<?php foreach ( $social_keys as $social_key ) : ?>
					<div class="zeko-form-group">
						<label for="zeko_social_<?php echo esc_attr( $social_key ); ?>"><?php echo esc_html( ucfirst( $social_key ) ); ?></label>
						<input type="url" id="zeko_social_<?php echo esc_attr( $social_key ); ?>" name="zeko_social_<?php echo esc_attr( $social_key ); ?>" value="<?php echo esc_url( (string) ( $profile_fields[ 'zeko_social_' . $social_key ] ?? '' ) ); ?>" />
					</div>
				<?php endforeach; ?>
			</div>

			<p><button type="submit" class="zeko-save-settings-btn"><?php esc_html_e( 'Save Profile', 'zeko' ); ?></button></p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'zeko_profile_editor', 'zeko_profile_editor_shortcode' );

/**
 * Process the [zeko_profile_editor] form submission.
 *
 * @return bool True on success.
 * @param int $user_id Target user ID.
 */
function zeko_profile_editor_save( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- this save routine is only invoked after the caller verifies the nonce.
	$display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
	$first_name   = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
	$last_name    = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
	$description  = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$user_fields = array( 'ID' => $user_id );
	if ( '' !== $display_name ) {
		$user_fields['display_name'] = $display_name;
	}
	if ( '' !== $first_name ) {
		$user_fields['first_name'] = $first_name;
	}
	if ( '' !== $last_name ) {
		$user_fields['last_name'] = $last_name;
	}
	if ( '' !== $description ) {
		$user_fields['description'] = $description;
	}
	wp_update_user( $user_fields );

	foreach ( array( 'facebook', 'twitter', 'linkedin', 'instagram' ) as $social_key ) {
		$post_key = 'zeko_social_' . $social_key;
		$url      = isset( $_POST[ $post_key ] ) ? esc_url_raw( wp_unslash( $_POST[ $post_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by caller.
		update_user_meta( $user_id, $post_key, $url );
		if ( function_exists( 'zeko_save_profile_field' ) ) {
			zeko_save_profile_field( $user_id, $post_key, $url );
		}
	}

	$avatar_result = zeko_profile_handle_upload( 'zeko_avatar_upload', $user_id, 'zeko_profile_avatar' );
	if ( is_wp_error( $avatar_result ) ) {
		return false;
	}
	$cover_result = zeko_profile_handle_upload( 'zeko_cover_upload', $user_id, 'zeko_profile_cover' );
	if ( is_wp_error( $cover_result ) ) {
		return false;
	}

	if ( function_exists( 'zeko_log_user_activity' ) ) {
		zeko_log_user_activity(
			$user_id,
			'profile_updated',
			array( 'content' => __( 'Updated their profile', 'zeko' ) )
		);
	}

	return true;
}

/**
 * [zeko_public_profile] — theme fallback public profile view.
 * Only registered when zeko-ai (the owning hub) is NOT active.
 *
 * @return string
 * @param array  $atts Atts.
 * @param string $content Content.
 */
function zeko_public_profile_shortcode( $atts = array(), $content = '' ) {
	unset( $content );
	$atts    = shortcode_atts( array( 'user_id' => 0 ), $atts, 'zeko_public_profile' );
	$user_id = (int) $atts['user_id'];
	if ( ! $user_id ) {
		$user_id = zeko_resolve_profile_user_id();
	}

	$user = $user_id ? get_userdata( $user_id ) : null;
	if ( ! $user ) {
		return '<div class="zeko-error-page"><p>' . esc_html__( 'Profile not found.', 'zeko' ) . '</p></div>';
	}

	$fields       = zeko_get_profile_fields( $user_id );
	$avatar       = zeko_get_user_avatar( $user_id, 150 );
	$completion   = zeko_get_profile_completion_percentage( $user_id );
	$registered   = $user->user_registered ? mysql2date( get_option( 'date_format' ), $user->user_registered ) : '';
	$social_links = array();
	foreach ( array( 'facebook', 'twitter', 'linkedin', 'instagram' ) as $social_key ) {
		$url = (string) ( $fields[ 'zeko_social_' . $social_key ] ?? '' );
		if ( '' !== $url ) {
			$social_links[ $social_key ] = $url;
		}
	}

	ob_start();
	?>
	<div class="zeko-profile-header">
		<div class="zeko-profile-avatar">
			<div class="zeko-avatar-container"><?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() output is self-escaped. ?></div>
		</div>
		<div class="zeko-profile-info">
			<h1 class="zeko-profile-name"><?php echo esc_html( $user->display_name ); ?></h1>
			<p class="zeko-profile-username">@<?php echo esc_html( $user->user_nicename ); ?></p>
			<?php if ( $user->description ) : ?>
				<p><?php echo esc_html( $user->description ); ?></p>
			<?php endif; ?>
			<?php if ( $registered ) : ?>
				<p class="zeko-profile-username">
				<?php
					/* translators: %s: member registration date. */
					echo esc_html( sprintf( __( 'Member since %s', 'zeko' ), $registered ) );
				?>
				</p>
			<?php endif; ?>

			<div class="zeko-profile-completion-bar">
				<div class="zeko-completion-label">
					<span><?php esc_html_e( 'Profile Completion', 'zeko' ); ?></span>
					<span class="zeko-completion-percentage"><?php echo esc_html( $completion ); ?>%</span>
				</div>
				<div class="zeko-completion-progress"><div class="zeko-progress-bar" style="width:<?php echo esc_attr( $completion ); ?>%"></div></div>
			</div>

			<?php if ( get_current_user_id() === $user_id ) : ?>
				<p class="zeko-profile-actions">
					<a class="btn btn-secondary btn-sm" href="<?php echo esc_url( home_url( '/edit-profile/' ) ); ?>"><?php esc_html_e( 'Edit Profile', 'zeko' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $social_links ) ) : ?>
		<div class="zeko-profile-social">
			<h3><?php esc_html_e( 'Connect', 'zeko' ); ?></h3>
			<div class="zeko-profile-social-links">
				<?php foreach ( $social_links as $social_key => $social_url ) : ?>
					<a class="zeko-social-link" href="<?php echo esc_url( $social_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( ucfirst( $social_key ) ); ?>">
						<?php echo esc_html( ucfirst( $social_key[0] ) ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}

if ( ! shortcode_exists( 'zeko_public_profile' ) ) {
	add_shortcode( 'zeko_public_profile', 'zeko_public_profile_shortcode' );
}
