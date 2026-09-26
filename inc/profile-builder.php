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
		if ( in_array( $field, array( 'first_name', 'last_name', 'description' ) ) ) {
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
