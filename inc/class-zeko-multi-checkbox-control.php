<?php
/**
 * Admin restriction functionality
 *
 * @package Zeko
 */

/**
 * Restrict admin access for non-admin users.
 * Single enforcement point: honors the customizer's "Allowed Admin Roles"
 * setting and redirect URL. All privileged contexts (AJAX, REST, cron,
 * XML-RPC, WP-CLI, media handling, customizer) are always allowed through.
 */
function zeko_restrict_admin_access() {
	// Allow AJAX requests.
	if ( defined( 'DOING_AJAX' ) ) {
		return;
	}

	// Allow REST API requests.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	// Allow cron jobs.
	if ( defined( 'DOING_CRON' ) ) {
		return;
	}

	// Allow XML-RPC requests.
	if ( defined( 'XMLRPC_REQUEST' ) ) {
		return;
	}

	// Allow WP-CLI commands.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	// Administrators always keep full access.
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}

	// Allow access to admin-ajax.php for frontend AJAX.
	// and admin-post.php for frontend form handling.
	$script = isset( $_SERVER['SCRIPT_FILENAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ) ) : '';
	if ( in_array( $script, array( 'admin-ajax.php', 'admin-post.php' ), true ) ) {
		return;
	}

	if ( is_admin() ) {
		$requested_page = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		// Allow access to async-upload.php for media uploads.
		if ( strpos( $requested_page, 'async-upload.php' ) !== false ) {
			return;
		}

		// Allow access to upload.php for media management.
		if ( strpos( $requested_page, 'upload.php' ) !== false ) {
			return;
		}

		// Allow access to theme customizer for users with edit_theme_options capability.
		if ( current_user_can( 'edit_theme_options' ) && strpos( $requested_page, 'customize.php' ) !== false ) {
			return;
		}

		// Redirect everyone else to the frontend dashboard.
		if ( ! zeko_user_can_access_admin() ) {
			$redirect_url = home_url( get_theme_mod( 'zeko_admin_redirect_url', '/dashboard/' ) );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}
}
add_action( 'admin_init', 'zeko_restrict_admin_access', 1 );

/**
 * Hide admin bar for non-admin users
 */
function zeko_hide_admin_bar() {
	if ( ! current_user_can( 'manage_options' ) && ! is_admin() ) {
		show_admin_bar( false );
	}
}
add_action( 'after_setup_theme', 'zeko_hide_admin_bar' );

/**
 * Remove admin menu items for non-admin users
 */
function zeko_remove_admin_menu_items() {
	if ( ! current_user_can( 'manage_options' ) ) {
		global $menu, $submenu;

		// Remove most admin menu items.
		$restricted_menus = array(
			'index.php',          // Dashboard.
			'edit.php',           // Posts.
			'upload.php',         // Media.
			'edit.php?post_type=page', // Pages.
			'edit-comments.php',  // Comments.
			'themes.php',         // Appearance.
			'plugins.php',        // Plugins.
			'users.php',          // Users.
			'tools.php',          // Tools.
			'options-general.php', // Settings.
		);

		foreach ( $restricted_menus as $menu_slug ) {
			remove_menu_page( $menu_slug );
		}
	}
}
add_action( 'admin_menu', 'zeko_remove_admin_menu_items', 999 );

/**
 * Customizer control that renders a checkbox list and saves a
 * comma-separated string for a single setting.
 */
if ( ! class_exists( 'Zeko_Multi_Checkbox_Control' ) && class_exists( 'WP_Customize_Control' ) ) {
	/** Class Zeko_Multi_Checkbox_Control. */
	class Zeko_Multi_Checkbox_Control extends WP_Customize_Control {
		/**
		 * Type.
		 *
		 * @var mixed Type.
		 */
		public $type = 'zeko-multicheck';

		/**
		 * Render content.
		 */
		public function render_content() {
			if ( empty( $this->choices ) ) {
				return;
			}

			$value        = $this->value();
			$multi_values = is_array( $value ) ? $value : array_map( 'trim', explode( ',', (string) $value ) );
			?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php if ( ! empty( $this->description ) ) : ?>
				<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
			<?php endif; ?>
			<ul>
				<?php foreach ( $this->choices as $role => $label ) : ?>
					<li>
						<label>
							<input type="checkbox" class="zeko-multicheck" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( (string) $role, $multi_values, true ) ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
			<input type="hidden" class="zeko-multicheck-value" <?php $this->link(); ?> value="<?php echo esc_attr( implode( ',', $multi_values ) ); ?>" />
			<script>
			(function($) {
				var $container = $('#customize-control-<?php echo esc_js( $this->id ); ?>');
				var $checkboxes = $container.find('.zeko-multicheck');
				var $value = $container.find('.zeko-multicheck-value');
				$checkboxes.on('change', function() {
					var values = [];
					$checkboxes.each(function() {
						if ($(this).is(':checked')) {
							values.push($(this).val());
						}
					});
					$value.val(values.join(',')).trigger('change');
				});
			})(jQuery);
			</script>
			<?php
		}
	}
}

/**
 * Add admin restriction settings to theme customizer
 *
 * @param mixed $wp_customize Wp customize.
 */
function zeko_admin_restriction_customizer_settings( $wp_customize ) {
	// Add section.
	$wp_customize->add_section(
		'zeko_admin_restriction',
		array(
			'title'    => __( 'Admin Restriction', 'zeko' ),
			'priority' => 130,
			'panel'    => 'zeko_theme_options',
		)
	);

	// Add setting for allowed user roles.
	$wp_customize->add_setting(
		'zeko_allowed_admin_roles',
		array(
			'default'           => array( 'administrator' ),
			'sanitize_callback' => 'zeko_sanitize_admin_roles',
		)
	);

	$wp_customize->add_control(
		new Zeko_Multi_Checkbox_Control(
			$wp_customize,
			'zeko_allowed_admin_roles',
			array(
				'label'    => __( 'Allowed Admin Roles', 'zeko' ),
				'section'  => 'zeko_admin_restriction',
				'settings' => 'zeko_allowed_admin_roles',
				'choices'  => zeko_get_user_roles(),
			)
		)
	);

	// Add setting for redirect URL.
	$wp_customize->add_setting(
		'zeko_admin_redirect_url',
		array(
			'default'           => '/dashboard/',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	$wp_customize->add_control(
		'zeko_admin_redirect_url',
		array(
			'label'       => __( 'Redirect URL', 'zeko' ),
			'section'     => 'zeko_admin_restriction',
			'settings'    => 'zeko_admin_redirect_url',
			'type'        => 'text',
			'description' => __( 'URL to redirect non-admin users to (e.g., /dashboard/)', 'zeko' ),
		)
	);
}
add_action( 'customize_register', 'zeko_admin_restriction_customizer_settings' );

/**
 * Sanitize admin roles
 *
 * @param mixed $input Input.
 */
function zeko_sanitize_admin_roles( $input ) {
	if ( is_string( $input ) ) {
		$input = array_map( 'trim', explode( ',', $input ) );
	}

	if ( ! is_array( $input ) ) {
		return array( 'administrator' );
	}

	$valid_roles = array_keys( zeko_get_user_roles() );
	$sanitized   = array();

	foreach ( $input as $role ) {
		if ( in_array( $role, $valid_roles, true ) ) {
			$sanitized[] = $role;
		}
	}

	return ! empty( $sanitized ) ? array_values( array_unique( $sanitized ) ) : array( 'administrator' );
}

/**
 * Get user roles for customizer
 */
function zeko_get_user_roles() {
	global $role_map;
	$roles = array();

	if ( ! isset( $role_map ) ) {
		$role_map = new WP_Roles();
	}

	foreach ( $role_map->roles as $role => $data ) {
		$roles[ $role ] = translate_user_role( $data['name'] );
	}

	return $roles;
}

/**
 * Check if user can access admin based on settings
 *
 * @param mixed $user_id User id.
 */
function zeko_user_can_access_admin( $user_id = null ) {
	if ( is_null( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$user          = new WP_User( $user_id );
	$allowed_roles = get_theme_mod( 'zeko_allowed_admin_roles', array( 'administrator' ) );

	if ( is_string( $allowed_roles ) ) {
		$allowed_roles = array_map( 'trim', explode( ',', $allowed_roles ) );
	}

	foreach ( $user->roles as $role ) {
		if ( in_array( $role, $allowed_roles, true ) ) {
			return true;
		}
	}

	return false;
}
