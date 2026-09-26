<?php
/**
 * Custom login and registration system
 *
 * Application-logic consolidation (audit row 2, Unit A): when zeko-core is
 * active, member roles/capabilities, the login + registration handlers, and
 * the wp-login/wp-signup/lostpassword redirects are owned by Zeko_Core_Auth —
 * each bails early below so Core installs run the plugin twin while Core-less
 * installs keep the original bodies. Form renderers (the two shortcodes),
 * zeko_theme_migrate, and enqueue stay theme-owned.
 *
 * @package Zeko
 */

/**
 * Create custom user tables on theme activation
 */
function zeko_create_custom_user_tables() {
	// Table creation moved to Zeko Core (app schema module 'auth').
	if ( class_exists( 'Zeko_Core_DB' ) ) {
		Zeko_Core_DB::get_instance()->ensure_module( 'auth' );
		return;
	}

	// Check if dbDelta function exists.
	if ( ! function_exists( 'dbDelta' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Custom user meta table.
	$table_name = $wpdb->prefix . 'zeko_user_meta';
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
		$sql = "CREATE TABLE $table_name (
            meta_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext DEFAULT NULL,
            PRIMARY KEY (meta_id),
            KEY user_id (user_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";
		dbDelta( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// User activity log table.
	if ( ! class_exists( 'Zeko_Core_DB' ) ) {
		$activity_table = $wpdb->prefix . 'zeko_user_activity';
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$activity_table'" ) !== $activity_table ) {
			$sql = "CREATE TABLE $activity_table (
                activity_id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                activity_type varchar(50) NOT NULL,
                activity_module varchar(50) DEFAULT NULL,
                activity_item_id bigint(20) DEFAULT NULL,
                activity_content longtext DEFAULT NULL,
                activity_meta longtext DEFAULT NULL,
                activity_date datetime NOT NULL,
                activity_ip varchar(45) DEFAULT NULL,
                activity_status varchar(20) DEFAULT 'published',
                PRIMARY KEY (activity_id),
                KEY user_id (user_id),
                KEY activity_type (activity_type),
                KEY activity_module (activity_module),
                KEY activity_date (activity_date)
            ) $charset_collate;";
			dbDelta( $sql );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
	}
}

/**
 * Register the theme-owned member role and the cross-role capabilities the
 * ecosystem relies on. Idempotent, safe to call from admin and init.
 */
function zeko_register_member_roles() {
	if ( class_exists( 'Zeko_Core_Auth' ) ) {
		return;
	}
	// Add custom user roles.
	if ( ! get_role( 'zeko_member' ) ) {
		add_role(
			'zeko_member',
			__( 'Zeko Member', 'zeko' ),
			array(
				'read'                  => true,
				'edit_posts'            => true,
				'upload_files'          => true,
				'zeko_access_dashboard' => true,
			)
		);
	}

	// Add custom capabilities.
	$roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber', 'zeko_member' );
	foreach ( $roles as $role ) {
		$role_obj = get_role( $role );
		if ( $role_obj ) {
			$role_obj->add_cap( 'zeko_access_dashboard' );
			$role_obj->add_cap( 'zeko_view_profiles' );
			$role_obj->add_cap( 'zeko_send_messages' );
		}
	}
}

/**
 * Versioned theme schema migration.
 * Themes have no plugin-style activation hook; register_activation_hook() is
 * plugin-only and never reliably fires in a theme context. Table and page
 * creation therefore runs from a versioned admin-init routine plus the proper
 * after_switch_theme hook, so DDL never happens on ordinary front-end
 * requests. Bump the version constant whenever a table or page definition
 * changes.
 */
function zeko_theme_migrate() {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$version = '1.1';
	if ( get_option( 'zeko_theme_db_version' ) === $version ) {
		return;
	}

	// Custom user tables.
	zeko_create_custom_user_tables();

	// Other theme-owned tables (all guarded by SHOW TABLES existence checks).
	if ( function_exists( 'zeko_create_dashboard_tables' ) ) {
		zeko_create_dashboard_tables();
	}
	if ( function_exists( 'zeko_create_friendship_tables' ) ) {
		zeko_create_friendship_tables();
	}
	if ( function_exists( 'zeko_create_activity_tables' ) ) {
		zeko_create_activity_tables();
	}
	if ( function_exists( 'zeko_create_profile_tables' ) ) {
		zeko_create_profile_tables();
	}

	// Essential pages + roles.
	zeko_create_essential_pages();
	zeko_register_member_roles();

	update_option( 'zeko_theme_db_version', $version );
}
add_action( 'after_switch_theme', 'zeko_theme_migrate' );
add_action( 'admin_init', 'zeko_theme_migrate', 4 );

/**
 * Front-end safety net for installs that have never visited wp-admin.
 * The versioned admin-init routine (above) normally owns schema creation.
 * This only rescues a freshly deployed site whose visitors have not reached
 * the admin once yet, and is throttled so it can never run on every request.
 */
function zeko_theme_init_schema() {
	if ( get_option( 'zeko_theme_db_version' ) ) {
		return;
	}
	if ( get_transient( 'zeko_theme_schema_boot' ) ) {
		return;
	}
	set_transient( 'zeko_theme_schema_boot', 1, DAY_IN_SECONDS );
	zeko_theme_migrate();
}

/**
 * Custom login form shortcode
 *
 * @param mixed $atts Atts.
 */
function zeko_custom_login_form_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'redirect'           => '',
			'show_register'      => 'true',
			'show_lost_password' => 'true',
		),
		$atts
	);

	ob_start();
	?>
	<div class="zeko-login-form">
		<?php if ( is_user_logged_in() ) : ?>
			<p class="zeko-logged-in-message">
				<?php esc_html_e( 'You are already logged in.', 'zeko' ); ?>
				<a href="<?php echo esc_url( zeko_get_page_url( 'zeko', 'dashboard' ) ); ?>" class="btn btn-secondary">
					<?php esc_html_e( 'Go to Dashboard', 'zeko' ); ?>
				</a>
			</p>
		<?php else : ?>
			<form id="zeko-login-form" class="zeko-form" method="post">
				<div class="form-group">
					<label for="zeko-username"><?php esc_html_e( 'Username or Email', 'zeko' ); ?></label>
					<input type="text" name="zeko_username" id="zeko-username" class="form-control" required>
				</div>

				<div class="form-group">
					<label for="zeko-password"><?php esc_html_e( 'Password', 'zeko' ); ?></label>
					<input type="password" name="zeko_password" id="zeko-password" class="form-control" required>
				</div>

				<div class="form-group form-check">
					<input type="checkbox" name="zeko_remember" id="zeko-remember" class="form-check-input">
					<label class="form-check-label" for="zeko-remember">
						<?php esc_html_e( 'Remember Me', 'zeko' ); ?>
					</label>
				</div>

				<?php wp_nonce_field( 'zeko_login_action', 'zeko_login_nonce' ); ?>
				<input type="hidden" name="zeko_login_redirect" value="<?php echo esc_url( $atts['redirect'] ); ?>">

				<button type="submit" name="zeko_login_submit" class="btn btn-primary btn-block">
					<?php esc_html_e( 'Log In', 'zeko' ); ?>
				</button>

				<?php if ( 'true' === $atts['show_lost_password'] ) : ?>
					<div class="zeko-form-footer">
						<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="zeko-lost-password">
							<?php esc_html_e( 'Lost your password?', 'zeko' ); ?>
						</a>
					</div>
				<?php endif; ?>

				<?php if ( 'true' === $atts['show_register'] ) : ?>
					<div class="zeko-form-footer">
						<?php esc_html_e( "Don't have an account?", 'zeko' ); ?>
						<a href="<?php echo esc_url( zeko_get_page_url( 'auth', 'register' ) ); ?>" class="zeko-register-link">
							<?php esc_html_e( 'Register', 'zeko' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'zeko_login_form', 'zeko_custom_login_form_shortcode' );

/**
 * Handle custom login form submission
 */
function zeko_handle_custom_login() {
	if ( class_exists( 'Zeko_Core_Auth' ) ) {
		return;
	}
	if ( isset( $_POST['zeko_login_submit'] ) && wp_verify_nonce( (string) wp_unslash( $_POST['zeko_login_nonce'] ?? '' ), 'zeko_login_action' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce must stay verbatim (unslashed only) for wp_verify_nonce(); it is compared, never echoed or stored.
		$username = sanitize_user( wp_unslash( $_POST['zeko_username'] ?? '' ) );
		$password = (string) wp_unslash( $_POST['zeko_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password must stay verbatim for wp_signon(); never sanitized or echoed.
		$remember = isset( $_POST['zeko_remember'] ) ? true : false;
		$redirect = ! empty( $_POST['zeko_login_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['zeko_login_redirect'] ) ) : zeko_get_page_url( 'zeko', 'dashboard' );

		// Log login attempt.
		zeko_log_user_activity(
			get_current_user_id(),
			'login_attempt',
			array(
				'username'   => $username,
				'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			)
		);

		$creds = array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => $remember,
		);

		$user = wp_signon( $creds, is_ssl() );

		if ( ! is_wp_error( $user ) ) {
			// Successful login.
			zeko_log_user_activity(
				$user->ID,
				'login_success',
				array(
					'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
					'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				)
			);

			// Redirect to dashboard or specified URL.
			wp_safe_redirect( $redirect );
			exit;
		} else {
			// Failed login.
			zeko_log_user_activity(
				0,
				'login_failed',
				array(
					'username'   => $username,
					'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
					'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
					'error'      => $user->get_error_message(),
				)
			);

			// Set error message.
			add_filter(
				'zeko_login_errors',
				function () use ( $user ) {
					return $user->get_error_message();
				}
			);
		}
	}
}
add_action( 'init', 'zeko_handle_custom_login' );

/**
 * Custom registration form shortcode
 *
 * @param mixed $atts Atts.
 */
function zeko_custom_registration_form_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'redirect'   => zeko_get_page_url( 'auth', 'dashboard' ),
			'show_login' => 'true',
		),
		$atts
	);

	ob_start();
	?>
	<div class="zeko-register-form">
		<?php if ( is_user_logged_in() ) : ?>
			<p class="zeko-logged-in-message">
				<?php esc_html_e( 'You are already logged in.', 'zeko' ); ?>
				<a href="<?php echo esc_url( zeko_get_page_url( 'auth', 'dashboard' ) ); ?>" class="btn btn-secondary">
					<?php esc_html_e( 'Go to Dashboard', 'zeko' ); ?>
				</a>
			</p>
		<?php else : ?>
			<form id="zeko-register-form" class="zeko-form" method="post">
				<div class="form-group">
					<label for="zeko-reg-username"><?php esc_html_e( 'Username', 'zeko' ); ?></label>
					<input type="text" name="zeko_username" id="zeko-reg-username" class="form-control" required>
				</div>

				<div class="form-group">
					<label for="zeko-reg-email"><?php esc_html_e( 'Email Address', 'zeko' ); ?></label>
					<input type="email" name="zeko_email" id="zeko-reg-email" class="form-control" required>
				</div>

				<div class="form-group">
					<label for="zeko-reg-password"><?php esc_html_e( 'Password', 'zeko' ); ?></label>
					<input type="password" name="zeko_password" id="zeko-reg-password" class="form-control" required>
					<small class="form-text text-muted">
						<?php esc_html_e( 'Password should be at least 8 characters long.', 'zeko' ); ?>
					</small>
				</div>

				<div class="form-group">
					<label for="zeko-reg-password-confirm"><?php esc_html_e( 'Confirm Password', 'zeko' ); ?></label>
					<input type="password" name="zeko_password_confirm" id="zeko-reg-password-confirm" class="form-control" required>
				</div>

				<?php do_action( 'zeko_registration_form_fields' ); ?>

				<?php wp_nonce_field( 'zeko_register_action', 'zeko_register_nonce' ); ?>
				<input type="hidden" name="zeko_register_redirect" value="<?php echo esc_url( $atts['redirect'] ); ?>">

				<button type="submit" name="zeko_register_submit" class="btn btn-primary btn-block">
					<?php esc_html_e( 'Register', 'zeko' ); ?>
				</button>

				<?php if ( 'true' === $atts['show_login'] ) : ?>
					<div class="zeko-form-footer">
						<?php esc_html_e( 'Already have an account?', 'zeko' ); ?>
						<a href="<?php echo esc_url( zeko_get_page_url( 'auth', 'login' ) ); ?>" class="zeko-login-link">
							<?php esc_html_e( 'Log In', 'zeko' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'zeko_register_form', 'zeko_custom_registration_form_shortcode' );

/**
 * Handle custom registration form submission
 */
function zeko_handle_custom_registration() {
	if ( class_exists( 'Zeko_Core_Auth' ) ) {
		return;
	}
	if ( isset( $_POST['zeko_register_submit'] ) && wp_verify_nonce( (string) wp_unslash( $_POST['zeko_register_nonce'] ?? '' ), 'zeko_register_action' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce must stay verbatim (unslashed only) for wp_verify_nonce(); it is compared, never echoed or stored.
		$username         = sanitize_user( wp_unslash( $_POST['zeko_username'] ?? '' ) );
		$email            = sanitize_email( wp_unslash( $_POST['zeko_email'] ?? '' ) );
		$password         = (string) wp_unslash( $_POST['zeko_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password must stay verbatim for wp_create_user()/wp_signon(); never sanitized or echoed.
		$password_confirm = (string) wp_unslash( $_POST['zeko_password_confirm'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password must stay verbatim; compared against $password, never sanitized or echoed.
		$password_confirm = (string) wp_unslash( $_POST['zeko_password_confirm'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password must stay verbatim; compared against $password, never sanitized or echoed.
		$redirect         = ! empty( $_POST['zeko_register_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['zeko_register_redirect'] ) ) : zeko_get_page_url( 'zeko', 'dashboard' );

		// Validate username.
		if ( username_exists( $username ) ) {
			wp_die( esc_html__( 'Username already exists. Please choose another one.', 'zeko' ) );
		}

		// Validate email.
		if ( email_exists( $email ) ) {
			wp_die( esc_html__( 'Email address already exists. Please choose another one.', 'zeko' ) );
		}

		// Validate password match.
		if ( $password !== $password_confirm ) {
			wp_die( esc_html__( 'Passwords do not match.', 'zeko' ) );
		}

		// Validate password strength.
		if ( strlen( $password ) < 8 ) {
			wp_die( esc_html__( 'Password must be at least 8 characters long.', 'zeko' ) );
		}

		// Create user.
		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			wp_die( esc_html( $user_id->get_error_message() ) );
		}

		// Set user role.
		$user = new WP_User( $user_id );
		$user->set_role( 'subscriber' );

		// Log registration.
		zeko_log_user_activity(
			$user_id,
			'registration',
			array(
				'username'   => $username,
				'email'      => $email,
				'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			)
		);

		// Auto-login the user.
		$creds = array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => true,
		);

		$signon = wp_signon( $creds, is_ssl() );

		if ( ! is_wp_error( $signon ) ) {
			// Redirect to dashboard or specified URL.
			wp_safe_redirect( $redirect );
			exit;
		} else {
			wp_die( esc_html__( 'Registration successful, but automatic login failed. Please log in manually.', 'zeko' ) );
		}
	}
}
add_action( 'init', 'zeko_handle_custom_registration' );

/**
 * Sanitize activity metadata recursively before serialization.
 * Fallback for when Zeko Core is absent; mirrors Zeko_Core_Activity::
 * sanitize_activity_meta(). Keys are normalized with sanitize_key and
 * only scalars/nested arrays of scalars are kept.
 *
 * @return array<string,mixed> Sanitized metadata.
 * @param mixed $meta Raw metadata value.
 */
function zeko_sanitize_activity_meta( $meta ) {
	if ( ! is_array( $meta ) ) {
		return array();
	}

	$clean = array();
	foreach ( $meta as $raw_key => $value ) {
		$key = sanitize_key( (string) $raw_key );
		if ( '' === $key ) {
			continue;
		}

		if ( is_array( $value ) ) {
			$clean[ $key ] = zeko_sanitize_activity_meta( $value );
		} elseif ( is_string( $value ) ) {
			$clean[ $key ] = ( 'content' === $key ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		} elseif ( is_int( $value ) || is_float( $value ) ) {
			$clean[ $key ] = $value;
		} elseif ( is_bool( $value ) ) {
			$clean[ $key ] = $value;
		}
	}

	return $clean;
}

/**
 * Log user activity
 *
 * @param mixed $user_id User id.
 * @param mixed $activity_type Activity type.
 * @param array $activity_data Activity data.
 */
function zeko_log_user_activity( $user_id, $activity_type, $activity_data = array() ) {
	if ( class_exists( 'Zeko_Core_Activity' ) ) {
		$message = isset( $activity_data['content'] ) ? $activity_data['content'] : '';
		Zeko_Core_Activity::get_instance()->log( (int) $user_id, sanitize_text_field( $activity_type ), $message, 0, $activity_data );
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_user_activity';
	$clean_meta = zeko_sanitize_activity_meta( $activity_data );

	$wpdb->insert(
		$table_name,
		array(
			'user_id'          => (int) $user_id,
			'activity_type'    => sanitize_text_field( $activity_type ),
			'activity_content' => isset( $activity_data['content'] ) ? sanitize_textarea_field( $activity_data['content'] ) : '',
			'activity_meta'    => maybe_serialize( $clean_meta ),
			'activity_date'    => current_time( 'mysql' ),
			'activity_ip'      => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
			'activity_status'  => 'published',
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
}

/**
 * Redirect default WordPress login to custom login page
 */
function zeko_redirect_login_page() {
	if ( class_exists( 'Zeko_Core_Auth' ) ) {
		return;
	}
	$login_page     = zeko_get_page_url( 'auth', 'login' );
	$page_viewed    = basename( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
	$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

	if ( 'wp-login.php' === $page_viewed && 'GET' === $request_method ) {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( zeko_get_page_url( 'zeko', 'dashboard' ) );
		} else {
			wp_safe_redirect( $login_page );
		}
		exit;
	}
}
add_action( 'init', 'zeko_redirect_login_page' );

/**
 * Redirect default WordPress registration to custom registration page
 */
function zeko_redirect_registration_page() {
	if ( class_exists( 'Zeko_Core_Auth' ) ) {
		return;
	}
	$page_requested = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( strpos( $page_requested, 'wp-signup.php' ) !== false ||
		strpos( $page_requested, 'wp-register.php' ) !== false ) {
		wp_safe_redirect( zeko_get_page_url( 'auth', 'register' ) );
		exit;
	}
}
add_action( 'init', 'zeko_redirect_registration_page' );

/**
 * Redirect default WordPress lost password to custom lost password page
 */
function zeko_redirect_lost_password_page() {
	if ( class_exists( 'Zeko_Core_Auth' ) ) {
		return;
	}
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( strpos( $request_uri, 'wp-login.php?action=lostpassword' ) !== false ) {
		wp_safe_redirect( zeko_get_page_url( 'auth', 'lost-password' ) );
		exit;
	}
}
add_action( 'init', 'zeko_redirect_lost_password_page' );

/**
 * Create essential pages on theme activation
 */
function zeko_create_essential_pages() {
	$pages = array(
		'login'         => array(
			'title'   => __( 'Login', 'zeko' ),
			'content' => '[zeko_login_form show_register="true" show_lost_password="true"]',
			'slug'    => 'login',
		),
		'register'      => array(
			'title'   => __( 'Register', 'zeko' ),
			'content' => '[zeko_register_form show_login="true"]',
			'slug'    => 'register',
		),
		'dashboard'     => array(
			'title'   => __( 'Dashboard', 'zeko' ),
			'content' => '[zeko_dashboard]',
			'slug'    => 'dashboard',
		),
		'lost-password' => array(
			'title'   => __( 'Lost Password', 'zeko' ),
			'content' => '<!-- Lost Password Form -->',
			'slug'    => 'lost-password',
		),
		'profile'       => array(
			'title'   => __( 'Profile', 'zeko' ),
			'content' => '[zeko_public_profile]',
			'slug'    => 'profile',
		),
		'edit-profile'  => array(
			'title'   => __( 'Edit Profile', 'zeko' ),
			'content' => '[zeko_profile_editor]',
			'slug'    => 'edit-profile',
		),
		'settings'      => array(
			'title'   => __( 'Settings', 'zeko' ),
			'content' => '[zeko_settings_redirect]',
			'slug'    => 'settings',
		),
		'activity'      => array(
			'title'   => __( 'Activity', 'zeko' ),
			'content' => '<!-- Activity Feed -->',
			'slug'    => 'activity',
		),
		'messages'      => array(
			'title'   => __( 'Messages', 'zeko' ),
			'content' => '[zeko_messaging]',
			'slug'    => 'messages',
		),
		'friends'       => array(
			'title'   => __( 'Friends', 'zeko' ),
			'content' => '<!-- Friends List -->',
			'slug'    => 'friends',
		),
	);

	foreach ( $pages as $key => $page ) {
		// Check if page already exists.
		$existing_page = get_page_by_path( $page['slug'] );

		if ( ! $existing_page ) {
			// Create new page.
			$page_id = wp_insert_post(
				array(
					'post_title'     => $page['title'],
					'post_content'   => $page['content'],
					'post_name'      => $page['slug'],
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'post_author'    => 1,
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			);

			// Store page ID in theme options.
			update_option( 'zeko_' . $key . '_page_id', $page_id );

			// Log page creation.
			zeko_log_user_activity(
				get_current_user_id(),
				'page_created',
				array(
					'page_type'  => $key,
					'page_id'    => $page_id,
					'page_title' => $page['title'],
				)
			);
		} else {
			// Update existing page if content is different.
			if ( $existing_page->post_content !== $page['content'] ) {
				wp_update_post(
					array(
						'ID'           => $existing_page->ID,
						'post_content' => $page['content'],
					)
				);
			}

			// Ensure page ID is stored.
			update_option( 'zeko_' . $key . '_page_id', $existing_page->ID );
		}
	}
}

/**
 * Initialize auth system
 */
function zeko_init_auth() {
	// Ensure tables exist (throttled safety net; admin migration owns this).
	zeko_theme_init_schema();

	// Create essential pages (throttled — not on every request).
	if ( zeko_page_setup_due( 'essential' ) ) {
		zeko_create_essential_pages();
	}

	// Load textdomain for translations.
	load_theme_textdomain( 'zeko', get_template_directory() . '/languages' );

	// Add custom user roles and capabilities.
	zeko_register_member_roles();
}
add_action( 'init', 'zeko_init_auth' );

/**
 * Add page creation tool to admin
 */
function zeko_add_page_creation_tool() {
	add_submenu_page(
		'tools.php',
		__( 'Zeko Pages', 'zeko' ),
		__( 'Zeko Pages', 'zeko' ),
		'manage_options',
		'zeko-pages',
		'zeko_page_creation_tool_page'
	);
}
add_action( 'admin_menu', 'zeko_add_page_creation_tool' );

/**
 * Page creation tool interface
 */
function zeko_page_creation_tool_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Zeko Essential Pages', 'zeko' ); ?></h1>

		<div class="card" style="max-width: 800px; margin: 20px 0; padding: 20px; background: white; border: 1px solid #ddd; border-radius: 4px;">
			<h2><?php esc_html_e( 'Page Status', 'zeko' ); ?></h2>

			<?php
			$pages = array(
				'login'         => __( 'Login', 'zeko' ),
				'register'      => __( 'Register', 'zeko' ),
				'dashboard'     => __( 'Dashboard', 'zeko' ),
				'lost-password' => __( 'Lost Password', 'zeko' ),
				'profile'       => __( 'Profile', 'zeko' ),
				'settings'      => __( 'Settings', 'zeko' ),
				'activity'      => __( 'Activity', 'zeko' ),
				'messages'      => __( 'Messages', 'zeko' ),
				'friends'       => __( 'Friends', 'zeko' ),
			);

			echo '<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">';
			echo '<thead><tr><th>' . esc_html__( 'Page', 'zeko' ) . '</th><th>' . esc_html__( 'Status', 'zeko' ) . '</th><th>' . esc_html__( 'Shortcode', 'zeko' ) . '</th><th>' . esc_html__( 'Actions', 'zeko' ) . '</th></tr></thead>';
			echo '<tbody>';

			foreach ( $pages as $key => $title ) {
				$page_id = get_option( 'zeko_' . $key . '_page_id' );
				$page    = $page_id ? get_post( $page_id ) : false;

				echo '<tr>';
				echo '<td><strong>' . esc_html( $title ) . '</strong></td>';

				if ( $page ) {
					$edit_url = get_edit_post_link( $page_id );
					$view_url = get_permalink( $page_id );
					echo '<td><span style="color: green;">✓ ' . esc_html__( 'Created', 'zeko' ) . '</span></td>';
					echo '<td><code>[zeko_' . esc_html( $key ) . '_form]</code></td>';
					echo '<td>
                        <a href="' . esc_url( $edit_url ) . '" class="button button-primary">' . esc_html__( 'Edit', 'zeko' ) . '</a>
                        <a href="' . esc_url( $view_url ) . '" class="button" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', 'zeko' ) . '</a>
                    </td>';
				} else {
					echo '<td><span style="color: orange;">⚠ ' . esc_html__( 'Not Created', 'zeko' ) . '</span></td>';
					echo '<td><code>[zeko_' . esc_html( $key ) . '_form]</code></td>';
					echo '<td><button class="button button-secondary zeko-recreate-page" data-page="' . esc_attr( $key ) . '">' . esc_html__( 'Create', 'zeko' ) . '</button></td>';
				}

				echo '</tr>';
			}

			echo '</tbody></table>';
			?>

			<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
				<h3><?php esc_html_e( 'Actions', 'zeko' ); ?></h3>
				<button id="zeko-recreate-all-pages" class="button button-primary">
					<?php esc_html_e( 'Recreate All Pages', 'zeko' ); ?>
				</button>
				<button id="zeko-check-pages" class="button">
					<?php esc_html_e( 'Check Page Status', 'zeko' ); ?>
				</button>
			</div>

			<div id="zeko-page-message" style="margin-top: 20px; padding: 10px; border-radius: 4px; display: none;"></div>
		</div>

		<div class="card" style="max-width: 800px; margin: 20px 0; padding: 20px; background: white; border: 1px solid #ddd; border-radius: 4px;">
			<h2><?php esc_html_e( 'Manual Setup', 'zeko' ); ?></h2>
			<p><?php esc_html_e( 'If you prefer to create pages manually, use these shortcodes:', 'zeko' ); ?></p>

			<ul style="list-style: disc; margin-left: 20px;">
				<li><code>[zeko_login_form]</code> - <?php esc_html_e( 'Login form with optional registration link', 'zeko' ); ?></li>
				<li><code>[zeko_register_form]</code> - <?php esc_html_e( 'Registration form with optional login link', 'zeko' ); ?></li>
			</ul>

			<p><?php esc_html_e( 'For other pages, you can create them manually and add the appropriate content.', 'zeko' ); ?></p>
		</div>
	</div>

	<script>
	jQuery(document).ready(function($) {
		// Handle recreate single page
		$('.zeko-recreate-page').on('click', function() {
			var pageType = $(this).data('page');
			var messageDiv = $('#zeko-page-message');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'zeko_recreate_page',
					page_type: pageType,
					nonce: '<?php echo esc_attr( wp_create_nonce( 'zeko_recreate_page' ) ); ?>'
				},
				beforeSend: function() {
					messageDiv.removeClass('error').addClass('updated').text('<?php esc_html_e( 'Creating page...', 'zeko' ); ?>').show();
				},
				success: function(response) {
					if (response.success) {
						messageDiv.removeClass('error').addClass('updated').html('✓ ' + response.data.message).show();
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						messageDiv.removeClass('updated').addClass('error').html('✗ ' + response.data.message).show();
					}
				}
			});
		});

		// Handle recreate all pages
		$('#zeko-recreate-all-pages').on('click', function() {
			if (!confirm('<?php esc_html_e( 'Are you sure you want to recreate all pages? This will update existing pages.', 'zeko' ); ?>')) {
				return;
			}

			var messageDiv = $('#zeko-page-message');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'zeko_recreate_all_pages',
					nonce: '<?php echo esc_attr( wp_create_nonce( 'zeko_recreate_all_pages' ) ); ?>'
				},
				beforeSend: function() {
					messageDiv.removeClass('error').addClass('updated').text('<?php esc_html_e( 'Creating all pages...', 'zeko' ); ?>').show();
				},
				success: function(response) {
					if (response.success) {
						messageDiv.removeClass('error').addClass('updated').html('✓ ' + response.data.message).show();
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						messageDiv.removeClass('updated').addClass('error').html('✗ ' + response.data.message).show();
					}
				}
			});
		});

		// Handle check pages
		$('#zeko-check-pages').on('click', function() {
			var messageDiv = $('#zeko-page-message');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'zeko_check_pages',
					nonce: '<?php echo esc_attr( wp_create_nonce( 'zeko_check_pages' ) ); ?>'
				},
				beforeSend: function() {
					messageDiv.removeClass('error').addClass('updated').text('<?php esc_html_e( 'Checking pages...', 'zeko' ); ?>').show();
				},
				success: function(response) {
					if (response.success) {
						messageDiv.removeClass('error').addClass('updated').html('✓ ' + response.data.message).show();
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						messageDiv.removeClass('updated').addClass('error').html('✗ ' + response.data.message).show();
					}
				}
			});
		});
	});
	</script>
	<?php
}

/**
 * AJAX handler for recreating a single page
 */
function zeko_ajax_recreate_page() {
	check_ajax_referer( 'zeko_recreate_page', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'zeko' ) ) );
	}

	$page_type = isset( $_POST['page_type'] ) ? sanitize_text_field( wp_unslash( $_POST['page_type'] ) ) : '';

	if ( empty( $page_type ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid page type.', 'zeko' ) ) );
	}

	$pages = array(
		'login'         => array(
			'title'   => __( 'Login', 'zeko' ),
			'content' => '[zeko_login_form show_register="true" show_lost_password="true"]',
			'slug'    => 'login',
		),
		'register'      => array(
			'title'   => __( 'Register', 'zeko' ),
			'content' => '[zeko_register_form show_login="true"]',
			'slug'    => 'register',
		),
		'dashboard'     => array(
			'title'   => __( 'Dashboard', 'zeko' ),
			'content' => '<!-- Zeko Dashboard Content -->',
			'slug'    => 'dashboard',
		),
		'lost-password' => array(
			'title'   => __( 'Lost Password', 'zeko' ),
			'content' => '<!-- Lost Password Form -->',
			'slug'    => 'lost-password',
		),
		'profile'       => array(
			'title'   => __( 'Profile', 'zeko' ),
			'content' => '<!-- Profile Content -->',
			'slug'    => 'profile',
		),
		'settings'      => array(
			'title'   => __( 'Settings', 'zeko' ),
			'content' => '[zeko_settings_redirect]',
			'slug'    => 'settings',
		),
		'activity'      => array(
			'title'   => __( 'Activity', 'zeko' ),
			'content' => '<!-- Activity Feed -->',
			'slug'    => 'activity',
		),
		'messages'      => array(
			'title'   => __( 'Messages', 'zeko' ),
			'content' => '[zeko_messaging]',
			'slug'    => 'messages',
		),
		'friends'       => array(
			'title'   => __( 'Friends', 'zeko' ),
			'content' => '<!-- Friends List -->',
			'slug'    => 'friends',
		),
	);

	if ( ! isset( $pages[ $page_type ] ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid page type.', 'zeko' ) ) );
	}

	$page_data = $pages[ $page_type ];

	// Check if page already exists.
	$existing_page = get_page_by_path( $page_data['slug'] );

	if ( $existing_page ) {
		// Update existing page.
		wp_update_post(
			array(
				'ID'           => $existing_page->ID,
				'post_content' => $page_data['content'],
			)
		);
		$page_id = $existing_page->ID;
		/* translators: %s: page title */
		$message = sprintf( __( 'Updated existing %s page.', 'zeko' ), $page_data['title'] );
	} else {
		// Create new page.
		$page_id = wp_insert_post(
			array(
				'post_title'     => $page_data['title'],
				'post_content'   => $page_data['content'],
				'post_name'      => $page_data['slug'],
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_author'    => get_current_user_id(),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);
		/* translators: %s: page title */
		$message = sprintf( __( 'Created new %s page.', 'zeko' ), $page_data['title'] );
	}

	// Update page ID option.
	update_option( 'zeko_' . $page_type . '_page_id', $page_id );

	wp_send_json_success( array( 'message' => $message ) );
}

/**
 * AJAX handler for recreating all pages
 */
function zeko_ajax_recreate_all_pages() {
	check_ajax_referer( 'zeko_recreate_all_pages', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'zeko' ) ) );
	}

	zeko_create_essential_pages();

	wp_send_json_success( array( 'message' => __( 'All essential pages have been recreated.', 'zeko' ) ) );
}

/**
 * AJAX handler for checking pages
 */
function zeko_ajax_check_pages() {
	check_ajax_referer( 'zeko_check_pages', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'zeko' ) ) );
	}

	// Just return success - the page will reload and show current status.
	wp_send_json_success( array( 'message' => __( 'Page status checked. Refreshing...', 'zeko' ) ) );
}

/**
 * Settings redirect shortcode
 */
function zeko_settings_redirect_shortcode() {
	// Redirect to edit profile page where settings are managed.
	wp_safe_redirect( home_url( '/edit-profile/' ) );
	exit;
}
add_shortcode( 'zeko_settings_redirect', 'zeko_settings_redirect_shortcode' );

// Register AJAX handlers.
add_action( 'wp_ajax_zeko_recreate_page', 'zeko_ajax_recreate_page' );
add_action( 'wp_ajax_zeko_recreate_all_pages', 'zeko_ajax_recreate_all_pages' );
add_action( 'wp_ajax_zeko_check_pages', 'zeko_ajax_check_pages' );
