<?php
/**
 * Frontend Dashboard functionality
 *
 * @package Zeko
 */

/**
 * Create dashboard database tables
 */
function zeko_create_dashboard_tables() {
	// Widget/prefs table creation moved to Zeko Core (app schema module 'dashboard').
	if ( class_exists( 'Zeko_Core_DB' ) ) {
		Zeko_Core_DB::get_instance()->ensure_module( 'dashboard' );
		return;
	}

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Dashboard widgets table.
	$widgets_table = $wpdb->prefix . 'zeko_dashboard_widgets';
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$widgets_table'" ) !== $widgets_table ) {
		$sql = "CREATE TABLE $widgets_table (
            widget_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            widget_type varchar(50) NOT NULL,
            widget_title varchar(255) DEFAULT NULL,
            widget_content longtext DEFAULT NULL,
            widget_settings longtext DEFAULT NULL,
            widget_position int(11) DEFAULT 0,
            widget_status varchar(20) DEFAULT 'active',
            widget_column varchar(20) DEFAULT 'main',
            PRIMARY KEY (widget_id),
            KEY user_id (user_id),
            KEY widget_type (widget_type),
            KEY widget_position (widget_position)
        ) $charset_collate;";
		zeko_safe_db_delta( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// Dashboard preferences table.
	$prefs_table = $wpdb->prefix . 'zeko_dashboard_prefs';
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$prefs_table'" ) !== $prefs_table ) {
		$sql = "CREATE TABLE $prefs_table (
            pref_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            pref_name varchar(100) NOT NULL,
            pref_value longtext DEFAULT NULL,
            PRIMARY KEY (pref_id),
            KEY user_id (user_id),
            KEY pref_name (pref_name)
        ) $charset_collate";
		zeko_safe_db_delta( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// Activity feed table (owned by Zeko Core; theme-only fallback when Core is absent).
	if ( class_exists( 'Zeko_Core_DB' ) ) {
		Zeko_Core_DB::get_instance()->create_tables();
	} else {
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
			zeko_safe_db_delta( $sql );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
	}
}

/**
 * Initialize dashboard system
 */
function zeko_init_dashboard() {
	// Ensure tables exist (throttled safety net; admin migration owns this).
	zeko_theme_init_schema();

	// Add dashboard shortcode.
	add_shortcode( 'zeko_dashboard', 'zeko_dashboard_shortcode' );

	// Add dashboard widgets.
	add_action( 'zeko_dashboard_widgets', 'zeko_add_default_dashboard_widgets' );

	// Enqueue dashboard assets.
	add_action( 'wp_enqueue_scripts', 'zeko_enqueue_dashboard_assets' );

	// Add dashboard menu to admin bar.
	add_action( 'admin_bar_menu', 'zeko_add_dashboard_menu_to_admin_bar', 90 );

	// Set up default widgets for new users.
	add_action( 'user_register', 'zeko_setup_default_dashboard_widgets' );

	// Ensure shortcodes are processed in text widgets.
	add_filter( 'widget_text', 'do_shortcode' );
}
add_action( 'init', 'zeko_init_dashboard' );

/**
 * Enqueue dashboard assets
 */
function zeko_enqueue_dashboard_assets() {
	if ( zeko_is_dashboard_page() ) {
		$theme_dir = get_template_directory();
		wp_enqueue_script( 'zeko-dashboard', get_template_directory_uri() . '/assets/js/dashboard.js', array( 'jquery', 'jquery-ui-sortable' ), file_exists( $theme_dir . '/assets/js/dashboard.js' ) ? filemtime( $theme_dir . '/assets/js/dashboard.js' ) : '1.0.0', true );
		wp_enqueue_style( 'zeko-dashboard', get_template_directory_uri() . '/assets/css/dashboard.css', array(), file_exists( $theme_dir . '/assets/css/dashboard.css' ) ? filemtime( $theme_dir . '/assets/css/dashboard.css' ) : '1.0.0' );

		wp_localize_script(
			'zeko-dashboard',
			'zekoDashboardData',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'zeko_dashboard_nonce' ),
				'i18n'     => array(
					'saving'        => __( 'Saving...', 'zeko' ),
					'saved'         => __( 'Dashboard saved!', 'zeko' ),
					'error'         => __( 'Error: ', 'zeko' ),
					'confirm_reset' => __( 'Are you sure you want to reset your dashboard layout?', 'zeko' ),
					'loading'       => __( 'Loading...', 'zeko' ),
					'no_activity'   => __( 'No recent activity found.', 'zeko' ),
				),
			)
		);
	}
}

/**
 * Check if current page is dashboard
 */
/* Duplicate zeko_is_dashboard_page() removed – defined in activity-feed.php */

/**
 * Dashboard shortcode
 *
 * @param mixed $atts Atts.
 */
function zeko_dashboard_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'user_id'       => get_current_user_id(),
			'show_welcome'  => 'true',
			'show_stats'    => 'true',
			'show_activity' => 'true',
			'show_modules'  => 'true',
		),
		$atts
	);

	if ( ! is_user_logged_in() ) {
		return '<p class="zeko-not-logged-in">' . __( 'You must be logged in to view your dashboard.', 'zeko' ) . '</p>';
	}

	$user_id         = $atts['user_id'];
	$current_user_id = get_current_user_id();

	// Check if user can view this dashboard.
	if ( $user_id !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
		return '<p class="zeko-error">' . __( 'You do not have permission to view this dashboard.', 'zeko' ) . '</p>';
	}

	ob_start();
	?>
	<div class="zeko-dashboard" data-user-id="<?php echo esc_attr( $user_id ); ?>">
		<?php if ( 'true' === $atts['show_welcome'] ) : ?>
			<div class="zeko-dashboard-header">
				<h1 class="zeko-dashboard-title">
					<?php esc_html_e( 'Welcome to Your Dashboard', 'zeko' ); ?>
					<?php $dash_user = get_userdata( $user_id ); ?>
					<span class="zeko-dashboard-user"><?php echo esc_html( $dash_user ? $dash_user->display_name : __( 'User', 'zeko' ) ); ?></span>
				</h1>

				<?php if ( $user_id === $current_user_id ) : ?>
					<div class="zeko-dashboard-actions">
						<button class="btn btn-secondary zeko-edit-dashboard-btn">
							<?php esc_html_e( 'Customize Dashboard', 'zeko' ); ?>
						</button>
						<button class="btn btn-outline zeko-reset-dashboard-btn">
							<?php esc_html_e( 'Reset Layout', 'zeko' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php
		if ( class_exists( 'Zeko_Core_Dashboard' ) ) {
			echo Zeko_Core_Dashboard::get_instance()->render_tabs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Zeko_Core_Dashboard::render_tabs() returns pre-escaped tab markup.
		} else {
			$dashboard_tabs = apply_filters( 'zeko_dashboard_tabs', array() );
			if ( ! empty( $dashboard_tabs ) ) :
				$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : array_key_first( $dashboard_tabs ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET tab selector; only switches which dashboard tab is displayed, performs no state change.
				?>
			<div class="zeko-dashboard-tabs" style="display:flex;gap:0;border-bottom:2px solid var(--color-border, #e2e8f0);margin-bottom:24px;">
				<?php
				foreach ( $dashboard_tabs as $tab_key => $tab_label ) :
					$tab_label = is_array( $tab_label ) ? ( $tab_label['label'] ?? '' ) : $tab_label;
					$is_active = ( $tab_key === $active_tab );
					?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key, get_permalink() ) ); ?>"
						class="zeko-dashboard-tab-link"
						style="padding:10px 20px;font-size:14px;font-weight:600;text-decoration:none;border-bottom:2px solid <?php echo $is_active ? 'var(--color-primary, #2563eb)' : 'transparent'; ?>;margin-bottom:-2px;color:<?php echo $is_active ? 'var(--color-primary, #2563eb)' : 'var(--color-text-secondary, #64748b)'; ?>;">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</div>
			<div class="zeko-dashboard-tab-content" id="zeko-dashboard-tab-content">
				<?php do_action( 'zeko_dashboard_tab_content_' . sanitize_key( $active_tab ) ); ?>
			</div>
				<?php
			endif;
		}
		?>

		<?php if ( 'true' === $atts['show_stats'] ) : ?>
			<div class="zeko-dashboard-stats">
				<?php echo zeko_get_dashboard_stats( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_dashboard_stats() escapes all values internally (esc_html). ?>
			</div>
		<?php endif; ?>

		<div class="zeko-dashboard-main">
			<div class="zeko-dashboard-column zeko-dashboard-main-column">
				<?php echo zeko_get_dashboard_widgets( $user_id, 'main' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_dashboard_widgets() escapes widget titles (esc_html) and runs content through do_shortcode(). ?>
			</div>

			<div class="zeko-dashboard-column zeko-dashboard-sidebar">
				<?php if ( 'true' === $atts['show_activity'] ) : ?>
					<div class="zeko-dashboard-widget zeko-activity-feed-widget">
						<div class="zeko-widget-header">
							<h2 class="zeko-widget-title"><?php esc_html_e( 'Recent Activity', 'zeko' ); ?></h2>
							<?php if ( $user_id === $current_user_id ) : ?>
								<button class="zeko-widget-edit-btn"><?php esc_html_e( 'Edit', 'zeko' ); ?></button>
							<?php endif; ?>
						</div>
						<div class="zeko-widget-content">
							<?php echo zeko_get_activity_feed( $user_id, 5, 0, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_activity_feed() escapes all dynamic values internally (esc_html/esc_url). ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( 'true' === $atts['show_modules'] ) : ?>
					<div class="zeko-dashboard-widget zeko-modules-widget">
						<div class="zeko-widget-header">
							<h2 class="zeko-widget-title"><?php esc_html_e( 'Quick Access', 'zeko' ); ?></h2>
						</div>
						<div class="zeko-widget-content">
							<?php echo zeko_get_module_access_buttons( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_module_access_buttons() escapes all module fields internally (esc_url/esc_attr/esc_html). ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Messages Widget -->
				<div class="zeko-dashboard-widget zeko-messages-widget">
					<?php echo zeko_get_message_inbox(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_message_inbox() escapes all values internally (esc_html/esc_url). ?>
				</div>

				<?php echo zeko_get_dashboard_widgets( $user_id, 'sidebar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_dashboard_widgets() escapes widget titles (esc_html) and runs content through do_shortcode(). ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Get dashboard statistics
 *
 * @param mixed $user_id User id.
 */
function zeko_get_dashboard_stats( $user_id ) {
	$stats = array(
		'profile_completion'   => zeko_get_profile_completion_percentage( $user_id ),
		'friends_count'        => zeko_get_friends_count( $user_id ),
		'messages_unread'      => zeko_get_unread_messages_count( $user_id ),
		'notifications_unread' => zeko_get_unread_notifications_count( $user_id ),
		'activity_count'       => zeko_get_recent_activity_count( $user_id ),
		'last_login'           => zeko_get_last_login_date( $user_id ),
		'jobs_posted_count'    => zeko_get_jobs_posted_count( $user_id ),
		'jobs_applied_count'   => zeko_get_jobs_applied_count( $user_id ),
	);

	ob_start();
	?>
	<div class="zeko-stats-grid">
		<div class="zeko-stat-item">
			<div class="zeko-stat-value"><?php echo esc_html( $stats['profile_completion'] ); ?>%</div>
			<div class="zeko-stat-label"><?php esc_html_e( 'Profile Complete', 'zeko' ); ?></div>
			<div class="zeko-stat-progress">
				<div class="zeko-progress-bar" style="width: <?php echo esc_attr( $stats['profile_completion'] ); ?>%"></div>
			</div>
		</div>

		<div class="zeko-stat-item">
			<div class="zeko-stat-value"><?php echo esc_html( $stats['friends_count'] ); ?></div>
			<div class="zeko-stat-label"><?php esc_html_e( 'Friends', 'zeko' ); ?></div>
		</div>

		<div class="zeko-stat-item">
			<div class="zeko-stat-value"><?php echo esc_html( $stats['messages_unread'] ); ?></div>
			<div class="zeko-stat-label"><?php esc_html_e( 'New Messages', 'zeko' ); ?></div>
		</div>

		<div class="zeko-stat-item">
			<div class="zeko-stat-value"><?php echo esc_html( $stats['notifications_unread'] ); ?></div>
			<div class="zeko-stat-label"><?php esc_html_e( 'Notifications', 'zeko' ); ?></div>
		</div>

		<div class="zeko-stat-item">
			<div class="zeko-stat-value"><?php echo esc_html( $stats['activity_count'] ); ?></div>
			<div class="zeko-stat-label"><?php esc_html_e( 'Recent Activity', 'zeko' ); ?></div>
		</div>

		<div class="zeko-stat-item">
			<div class="zeko-stat-value"><?php echo esc_html( $stats['last_login'] ); ?></div>
			<div class="zeko-stat-label"><?php esc_html_e( 'Last Login', 'zeko' ); ?></div>
		</div>

		<?php if ( class_exists( 'Zeko_Jobs_Public' ) ) : ?>
			<div class="zeko-stat-item">
				<div class="zeko-stat-value"><?php echo esc_html( $stats['jobs_posted_count'] ); ?></div>
				<div class="zeko-stat-label"><?php esc_html_e( 'Jobs Posted', 'zeko-jobs' ); ?></div>
			</div>

			<div class="zeko-stat-item">
				<div class="zeko-stat-value"><?php echo esc_html( $stats['jobs_applied_count'] ); ?></div>
				<div class="zeko-stat-label"><?php esc_html_e( 'Jobs Applied', 'zeko-jobs' ); ?></div>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Get friends count
 *
 * @param mixed $user_id User id.
 */
function zeko_get_friends_count( $user_id ) {
	if ( function_exists( 'zeko_get_friends' ) ) {
		$friends = zeko_get_friends( $user_id );
		return count( $friends );
	}
	return 0;
}

/**
 * Get jobs posted count
 *
 * @param mixed $user_id User id.
 */
function zeko_get_jobs_posted_count( $user_id ) {
	if ( class_exists( 'Zeko_Jobs_DB' ) && method_exists( 'Zeko_Jobs_DB', 'count_posted_jobs' ) ) {
		return Zeko_Jobs_DB::get_instance()->count_posted_jobs( (int) $user_id );
	}
	if ( ! class_exists( 'Zeko_Jobs_DB' ) ) {
		return 0;
	}
	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_jobs';
	return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE employer_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
}

/**
 * Get jobs applied count
 *
 * @param mixed $user_id User id.
 */
function zeko_get_jobs_applied_count( $user_id ) {
	if ( class_exists( 'Zeko_Jobs_DB' ) && method_exists( 'Zeko_Jobs_DB', 'count_applications_by_seeker' ) ) {
		return Zeko_Jobs_DB::get_instance()->count_applications_by_seeker( (int) $user_id );
	}
	if ( ! class_exists( 'Zeko_Jobs_DB' ) ) {
		return 0;
	}
	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_job_applications';
	return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE seeker_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
}

/**
 * Get unread messages count
 *
 * @param mixed $user_id User id.
 */
function zeko_get_unread_messages_count( $user_id ) {
	if ( function_exists( 'zeko_get_unread_message_count' ) ) {
		return zeko_get_unread_message_count( $user_id );
	}
	return 0;
}

/**
 * Get unread notifications count (placeholder)
 *
 * @param mixed $user_id User id.
 */
function zeko_get_unread_notifications_count( $user_id ) {
	if ( ! is_user_logged_in() || ! $user_id ) {
		return 0;
	}
	if ( class_exists( 'Zeko_Core_Notifications' ) ) {
		return Zeko_Core_Notifications::get_instance()->get_unread_count( (int) $user_id );
	}
	global $wpdb;
	$table = $wpdb->prefix . 'zeko_notifications';
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
		return 0;
	}
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE user_id = %d AND is_read = 0",
			$user_id
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
}

/**
 * Get recent activity count
 *
 * @param mixed $user_id User id.
 */
function zeko_get_recent_activity_count( $user_id ) {
	if ( class_exists( 'Zeko_Core_Activity' ) ) {
		return Zeko_Core_Activity::get_instance()->count_recent( (int) $user_id );
	}
	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_user_activity';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	return $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND activity_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
			$user_id
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
}

/**
 * Get last login date
 *
 * @param mixed $user_id User id.
 */
function zeko_get_last_login_date( $user_id ) {
	$last_login = get_user_meta( $user_id, 'zeko_last_login', true );
	if ( $last_login ) {
		return gmdate( 'M j, Y \a\t g:i a', strtotime( $last_login ) );
	}
	return __( 'Never', 'zeko' );
}

/**
 * Get dashboard widgets
 *
 * @param mixed  $user_id User id.
 * @param string $column Column.
 */
function zeko_get_dashboard_widgets( $user_id, $column = 'main' ) {
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		$widgets = Zeko_Core_App_Data::get_dashboard_widgets( (int) $user_id, (string) $column );
	} else {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zeko_dashboard_widgets';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$widgets = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d AND widget_column = %s AND widget_status = 'active' ORDER BY widget_position ASC",
				$user_id,
				$column
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	ob_start();
	?>
	<div class="zeko-widgets-container" data-column="<?php echo esc_attr( $column ); ?>">
		<?php foreach ( $widgets as $widget ) : ?>
			<div class="zeko-dashboard-widget zeko-widget-<?php echo esc_attr( $widget->widget_type ); ?>" data-widget-id="<?php echo esc_attr( $widget->widget_id ); ?>">
				<div class="zeko-widget-header">
					<h2 class="zeko-widget-title"><?php echo esc_html( $widget->widget_title ); ?></h2>
					<?php if ( get_current_user_id() === $user_id ) : ?>
						<div class="zeko-widget-actions">
							<button class="zeko-widget-edit-btn"><?php esc_html_e( 'Edit', 'zeko' ); ?></button>
							<button class="zeko-widget-remove-btn"><?php esc_html_e( 'Remove', 'zeko' ); ?></button>
						</div>
					<?php endif; ?>
				</div>
				<div class="zeko-widget-content">
					<?php echo do_shortcode( $widget->widget_content ); ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Welcome widget shortcode
 */
function zeko_welcome_widget_shortcode() {
	ob_start();
	?>
	<p><?php esc_html_e( 'Welcome to your Zeko dashboard! This is your personal hub where you can manage your profile, connect with friends, and access all the amazing features of the Zeko ecosystem.', 'zeko' ); ?></p>
	<p><?php esc_html_e( 'Get started by completing your profile, exploring the modules, and connecting with other members.', 'zeko' ); ?></p>
	<a href="<?php echo esc_url( home_url( '/edit-profile/' ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'Complete Your Profile', 'zeko' ); ?></a>
	<?php
	return ob_get_clean();
}
add_shortcode( 'zeko_welcome_widget', 'zeko_welcome_widget_shortcode' );

/**
 * Quick links widget shortcode
 */
function zeko_quick_links_widget_shortcode() {
	ob_start();
	?>
	<div class="zeko-quick-links-grid">
		<a href="<?php echo esc_url( zeko_get_user_profile_url() ); ?>" class="zeko-quick-link">
			<span class="dashicons dashicons-admin-users"></span>
			<?php esc_html_e( 'My Profile', 'zeko' ); ?>
		</a>
		<a href="<?php echo esc_url( home_url( '/edit-profile/' ) ); ?>" class="zeko-quick-link">
			<span class="dashicons dashicons-edit"></span>
			<?php esc_html_e( 'Edit Profile', 'zeko' ); ?>
		</a>
		<a href="<?php echo esc_url( home_url( '/messages/' ) ); ?>" class="zeko-quick-link">
			<span class="dashicons dashicons-email"></span>
			<?php esc_html_e( 'Messages', 'zeko' ); ?>
		</a>
		<a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>" class="zeko-quick-link">
			<span class="dashicons dashicons-groups"></span>
			<?php esc_html_e( 'Friends', 'zeko' ); ?>
		</a>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'zeko_quick_links_widget', 'zeko_quick_links_widget_shortcode' );

/**
 * Posted Jobs widget shortcode.
 */
function zeko_posted_jobs_widget_shortcode() {
	if ( class_exists( 'Zeko_Jobs_Public' ) ) {
		return Zeko_Jobs_Public::get_instance()->get_my_posted_jobs_widget( get_current_user_id() );
	}
	return '';
}
add_shortcode( 'zeko_posted_jobs_widget', 'zeko_posted_jobs_widget_shortcode' );

/**
 * Applications widget shortcode.
 */
function zeko_applications_widget_shortcode() {
	if ( class_exists( 'Zeko_Jobs_Public' ) ) {
		return Zeko_Jobs_Public::get_instance()->get_my_applications_widget( get_current_user_id() );
	}
	return '';
}
add_shortcode( 'zeko_applications_widget', 'zeko_applications_widget_shortcode' );

/**
 * Stats widget shortcode
 */
function zeko_stats_widget_shortcode() {
	$user_id = get_current_user_id();
	$stats   = array(
		'profile_completion' => zeko_get_profile_completion_percentage( $user_id ),
		'friends_count'      => zeko_get_friends_count( $user_id ),
		'messages_unread'    => zeko_get_unread_messages_count( $user_id ),
		'activity_count'     => zeko_get_recent_activity_count( $user_id ),
	);

	ob_start();
	?>
	<div class="zeko-stats-widget">
		<div class="zeko-stat-item">
			<div class="zeko-stat-value"><?php echo esc_html( $stats['profile_completion'] ); ?>%</div>
			<div class="zeko-stat-label"><?php esc_html_e( 'Profile Complete', 'zeko' ); ?></div>
			<div class="zeko-stat-progress">
				<div class="zeko-progress-bar" style="width: <?php echo esc_attr( $stats['profile_completion'] ); ?>%"></div>
			</div>
		</div>

		<div class="zeko-stat-item">
			<div class="zeko-stat-value"><?php echo esc_html( $stats['friends_count'] ); ?></div>
			<div class="zeko-stat-label"><?php esc_html_e( 'Friends', 'zeko' ); ?></div>
		</div>

		<div class="zeko-stat-item">
			<div class="zeko-stat-value"><?php echo esc_html( $stats['messages_unread'] ); ?></div>
			<div class="zeko-stat-label"><?php esc_html_e( 'New Messages', 'zeko' ); ?></div>
		</div>

		<div class="zeko-stat-item">
			<div class="zeko-stat-value"><?php echo esc_html( $stats['activity_count'] ); ?></div>
			<div class="zeko-stat-label"><?php esc_html_e( 'Recent Activity', 'zeko' ); ?></div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'zeko_stats_widget', 'zeko_stats_widget_shortcode' );

/**
 * Friends widget shortcode
 */
function zeko_friends_widget_shortcode() {
	if ( ! function_exists( 'zeko_get_friends' ) ) {
		return '<p>' . __( 'Friendship module not available.', 'zeko' ) . '</p>';
	}

	$user_id          = get_current_user_id();
	$friends          = zeko_get_friends( $user_id );
	$pending_requests = zeko_get_pending_friend_requests( $user_id );

	ob_start();
	?>
	<div class="zeko-friends-widget">
		<div class="zeko-friends-header">
			<h4><?php esc_html_e( 'Your Friends', 'zeko' ); ?></h4>
			<a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>" class="zeko-view-all">
				<?php esc_html_e( 'View All', 'zeko' ); ?>
			</a>
		</div>

		<?php if ( ! empty( $friends ) ) : ?>
			<div class="zeko-friends-list">
				<?php
				// Show up to 4 friends.
				$friends_to_show = array_slice( $friends, 0, 4 );
				foreach ( $friends_to_show as $friend_id ) :
					$friend_data = get_userdata( $friend_id );
					if ( $friend_data ) :
						?>
					<a href="<?php echo esc_url( zeko_get_user_profile_url( $friend_id ) ); ?>" class="zeko-friend-item" title="<?php echo esc_attr( $friend_data->display_name ); ?>">
						<?php echo zeko_get_user_avatar( $friend_id, 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_user_avatar() returns pre-escaped get_avatar() markup. ?>
					</a>
						<?php
					endif;
				endforeach;
				?>
			</div>
		<?php else : ?>
			<p class="zeko-no-friends"><?php esc_html_e( 'You haven\'t added any friends yet.', 'zeko' ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $pending_requests ) ) : ?>
			<div class="zeko-friend-requests-notice">
				<span class="zeko-request-count"><?php echo count( $pending_requests ); ?></span>
				<?php echo esc_html( _n( 'New friend request', 'New friend requests', count( $pending_requests ), 'zeko' ) ); ?>
				<a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>" class="btn btn-primary btn-sm">
					<?php esc_html_e( 'Find Friends', 'zeko' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'zeko_friends_widget', 'zeko_friends_widget_shortcode' );

/**
 * Add default dashboard widgets
 */
function zeko_add_default_dashboard_widgets() {
	// Welcome widget.
	echo '<div class="zeko-dashboard-widget zeko-widget-welcome" data-widget-type="welcome">
        <div class="zeko-widget-header">
            <h2 class="zeko-widget-title">' . esc_html__( 'Welcome to Zeko', 'zeko' ) . '</h2>
        </div>
        <div class="zeko-widget-content">';
		echo do_shortcode( '[zeko_welcome_widget]' );
	echo '</div></div>';

	// Quick links widget.
	echo '<div class="zeko-dashboard-widget zeko-widget-quick-links" data-widget-type="quick_links">
        <div class="zeko-widget-header">
            <h2 class="zeko-widget-title">' . esc_html__( 'Quick Links', 'zeko' ) . '</h2>
        </div>
        <div class="zeko-widget-content">';
		echo do_shortcode( '[zeko_quick_links_widget]' );
	echo '</div></div>';

	// My Posted Jobs widget.
	echo '<div class="zeko-dashboard-widget zeko-widget-my-posted-jobs" data-widget-type="my_posted_jobs">
        <div class="zeko-widget-header">
            <h2 class="zeko-widget-title">' . esc_html__( 'My Posted Jobs', 'zeko' ) . '</h2>
        </div>
        <div class="zeko-widget-content">';
		echo zeko_posted_jobs_widget_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode returns Zeko_Jobs_Public widget markup (escaped internally).
	echo '</div></div>';

	// My Job Applications widget.
	echo '<div class="zeko-dashboard-widget zeko-widget-my-applications" data-widget-type="my_applications">
        <div class="zeko-widget-header">
            <h2 class="zeko-widget-title">' . esc_html__( 'My Job Applications', 'zeko' ) . '</h2>
        </div>
        <div class="zeko-widget-content">';
		echo zeko_applications_widget_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode returns Zeko_Jobs_Public widget markup (escaped internally).
	echo '</div></div>';

	// Friends widget - add to sidebar.
	echo '<div class="zeko-dashboard-widget zeko-widget-friends" data-widget-type="friends">
        <div class="zeko-widget-header">
            <h2 class="zeko-widget-title">' . esc_html__( 'Your Friends', 'zeko' ) . '</h2>
        </div>
        <div class="zeko-widget-content">';
		echo do_shortcode( '[zeko_friends_widget]' );
	echo '</div></div>';

	// Social connections widget - add to sidebar.
	echo '<div class="zeko-dashboard-widget zeko-widget-social-connections" data-widget-type="social_connections">
        <div class="zeko-widget-header">
            <h2 class="zeko-widget-title">' . esc_html__( 'Social Connections', 'zeko' ) . '</h2>
        </div>
        <div class="zeko-widget-content">';
		echo do_shortcode( '[zeko_social_connections_widget]' );
	echo '</div></div>';
}

/**
 * Social connections widget shortcode
 */
function zeko_social_connections_widget_shortcode() {
	if ( ! is_user_logged_in() || ! function_exists( 'zeko_get_friends' ) ) {
		return '<p>' . __( 'Social features not available.', 'zeko' ) . '</p>';
	}

	$user_id       = get_current_user_id();
	$friends       = zeko_get_friends( $user_id );
	$friends_count = count( $friends );

	ob_start();
	?>
	<div class="zeko-social-connections-widget">
		<div class="zeko-connection-stats">
			<div class="zeko-stat-item">
				<div class="zeko-stat-value"><?php echo esc_html( $friends_count ); ?></div>
				<div class="zeko-stat-label"><?php esc_html_e( 'Friends', 'zeko' ); ?></div>
			</div>

			<?php if ( $friends_count > 0 ) : ?>
				<?php
				// Calculate average mutual friends.
				$total_mutual = 0;
				foreach ( $friends as $friend_id ) {
					$mutual_friends = zeko_get_mutual_friends( $user_id, $friend_id );
					$total_mutual  += count( $mutual_friends );
				}
				$avg_mutual = $friends_count > 0 ? round( $total_mutual / $friends_count ) : 0;
				?>
				<div class="zeko-stat-item">
					<div class="zeko-stat-value"><?php echo esc_html( $avg_mutual ); ?></div>
					<div class="zeko-stat-label"><?php esc_html_e( 'Avg Mutual', 'zeko' ); ?></div>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $friends_count > 0 ) : ?>
			<div class="zeko-recent-interactions">
				<h4><?php esc_html_e( 'Recent Interactions', 'zeko' ); ?></h4>
				<div class="zeko-interaction-list">
					<?php
					// Show up to 3 recent friends.
					$recent_friends = array_slice( $friends, 0, 3 );
					foreach ( $recent_friends as $friend_id ) :
						$friend_data = get_userdata( $friend_id );
						if ( $friend_data ) :
							$mutual_friends = zeko_get_mutual_friends( $user_id, $friend_id );
							$mutual_count   = count( $mutual_friends );
							?>
						<div class="zeko-interaction-item">
							<a href="<?php echo esc_url( zeko_get_user_profile_url( $friend_id ) ); ?>" class="zeko-interaction-avatar">
								<?php echo zeko_get_user_avatar( $friend_id, 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_user_avatar() returns pre-escaped get_avatar() markup. ?>
							</a>
							<div class="zeko-interaction-info">
								<a href="<?php echo esc_url( zeko_get_user_profile_url( $friend_id ) ); ?>" class="zeko-interaction-name">
									<?php echo esc_html( $friend_data->display_name ); ?>
								</a>
								<?php if ( $mutual_count > 0 ) : ?>
									<span class="zeko-interaction-mutual">
										<?php echo esc_html( $mutual_count ); ?> <?php echo esc_html( _n( 'mutual', 'mutuals', $mutual_count, 'zeko' ) ); ?>
									</span>
								<?php endif; ?>
							</div>
						</div>
							<?php
						endif;
					endforeach;
					?>
				</div>
			</div>
		<?php else : ?>
			<div class="zeko-no-connections">
				<p><?php esc_html_e( 'You haven\'t connected with anyone yet.', 'zeko' ); ?></p>
				<a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>" class="btn btn-primary btn-sm">
					<?php esc_html_e( 'Find Friends', 'zeko' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'zeko_social_connections_widget', 'zeko_social_connections_widget_shortcode' );

/**
 * Track user login for last login date
 *
 * @param mixed $user_login User login.
 * @param mixed $user User.
 */
function zeko_track_user_login( $user_login, $user ) {
	if ( class_exists( 'Zeko_Core_Auth' ) ) {
		return;
	}
	update_user_meta( $user->ID, 'zeko_last_login', current_time( 'mysql' ) );
}

/**
 * Track user login for last login date when zeko-core is active.
 *
 * @param mixed $user_login User login.
 * @param mixed $user User.
 */
function zeko_core_track_user_login( $user_login, $user ) {
	if ( ! class_exists( 'Zeko_Core_Auth' ) ) {
		return;
	}
	update_user_meta( $user->ID, 'zeko_last_login', current_time( 'mysql' ) );
}
add_action( 'wp_login', 'zeko_track_user_login', 10, 2 );
add_action( 'wp_login', 'zeko_core_track_user_login', 11, 2 );

/**
 * Get activity feed
 *
 * @param mixed     $user_id User id.
 * @param int|float $limit Limit.
 * @param int|float $offset Offset.
 * @param bool      $is_dashboard Is dashboard.
 */
function zeko_get_activity_feed( $user_id, $limit = 5, $offset = 0, $is_dashboard = false ) {
	global $wpdb;

	// Prefer the transient-cached core reader when present. The core flushes.
	// cache keys for limits 10/20/50 on write, so only use the cache for.
	// those limits (arbitrary limits read fresh).
	$activities = array();
	if ( class_exists( 'Zeko_Core_Activity' ) ) {
		$use_cache = in_array( (int) $limit, array( 10, 20, 50 ), true );
		$rows      = Zeko_Core_Activity::get_instance()->get_activities( (int) $user_id, (int) $limit, (int) $offset, $use_cache );
		foreach ( $rows as $row ) {
			$activities[] = (object) $row;
		}
	}
	if ( empty( $activities ) ) {
		$table_name = $wpdb->prefix . 'zeko_user_activity';
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$activities = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d ORDER BY activity_date DESC LIMIT %d OFFSET %d",
				$user_id,
				$limit,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// Allow plugins (zeko-jobs, zeko-learn, etc.) to inject additional activities.
	$extra_items = apply_filters( 'zeko_activity_feed_items', array() );
	if ( ! empty( $extra_items ) && is_array( $extra_items ) ) {
		foreach ( $extra_items as $item ) {
			$obj                   = new stdClass();
			$obj->activity_id      = 0;
			$obj->user_id          = $user_id;
			$obj->activity_type    = $item['action'] ?? '';
			$obj->activity_module  = $item['module'] ?? '';
			$obj->activity_item_id = $item['item_id'] ?? 0;
			$obj->activity_content = $item['message'] ?? '';
			$obj->activity_date    = $item['timestamp'] ?? current_time( 'mysql' );
			$obj->activity_meta    = '';
			$obj->activity_status  = 'published';
			$obj->activity_ip      = '';
			$activities[]          = $obj;
		}
		// Re-sort by date descending.
		usort(
			$activities,
			function ( $a, $b ) {
				return strtotime( $b->activity_date ) - strtotime( $a->activity_date );
			}
		);
		// Re-limit after merging.
		$activities = array_slice( $activities, $offset, $limit );
	}

	ob_start();
	?>
	<div class="zeko-activity-feed" data-user-id="<?php echo esc_attr( $user_id ); ?>" data-offset="<?php echo esc_attr( $offset ); ?>">
		<?php if ( ! empty( $activities ) ) : ?>
			<div class="zeko-activities-list">
				<?php foreach ( $activities as $activity ) : ?>
					<div class="zeko-activity-item" data-activity-id="<?php echo esc_attr( $activity->activity_id ); ?>">
						<div class="zeko-activity-avatar">
							<?php echo get_avatar( get_avatar_url( $activity->user_id ), 40 ); ?>
						</div>
						<div class="zeko-activity-content">
							<div class="zeko-activity-header">
								<?php $act_user = get_userdata( $activity->user_id ); ?>
								<span class="zeko-activity-user"><?php echo esc_html( $act_user ? $act_user->display_name : __( 'Unknown', 'zeko' ) ); ?></span>
								<span class="zeko-activity-type"><?php echo esc_html( zeko_get_activity_type_label( $activity->activity_type ) ); ?></span>
								<span class="zeko-activity-time"><?php echo esc_html( zeko_get_activity_time_ago( $activity->activity_date ) ); ?></span>
							</div>
							<?php if ( ! empty( $activity->activity_content ) ) : ?>
								<div class="zeko-activity-text">
									<?php echo wp_kses_post( wpautop( esc_textarea( $activity->activity_content ) ) ); ?>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $activity->activity_module ) ) : ?>
								<div class="zeko-activity-module">
									<?php esc_html_e( 'Module:', 'zeko' ); ?> <?php echo esc_html( ucfirst( $activity->activity_module ) ); ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $is_dashboard && count( $activities ) >= $limit ) : ?>
				<div class="zeko-activity-footer">
					<a href="<?php echo esc_url( home_url( '/activity/' ) ); ?>" class="zeko-view-all-activities">
						<?php esc_html_e( 'View All Activities', 'zeko' ); ?>
					</a>
				</div>
			<?php elseif ( ! $is_dashboard && count( $activities ) >= $limit ) : ?>
				<button class="btn btn-secondary zeko-load-more-activities" data-offset="<?php echo esc_attr( $offset + $limit ); ?>">
					<?php esc_html_e( 'Load More Activities', 'zeko' ); ?>
				</button>
			<?php endif; ?>
		<?php else : ?>
			<p class="zeko-no-activities"><?php esc_html_e( 'No recent activity found.', 'zeko' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Get activity type label
 * Delegates to Zeko_Core_Helpers when zeko-core is active.
 */
if ( ! function_exists( 'zeko_get_activity_type_label' ) ) {
	/**
	 * Zeko get activity type label.
	 *
	 * @param mixed $type Type.
	 */
	function zeko_get_activity_type_label( $type ) {
		if ( class_exists( 'Zeko_Core_Helpers' ) ) {
			return Zeko_Core_Helpers::get_activity_type_label( (string) $type );
		}
		$labels = array(
			'profile_updated'    => __( 'updated their profile', 'zeko' ),
			'avatar_updated'     => __( 'updated their avatar', 'zeko' ),
			'cover_updated'      => __( 'updated their cover photo', 'zeko' ),
			'registration'       => __( 'joined Zeko', 'zeko' ),
			'login'              => __( 'logged in', 'zeko' ),
			'friendship_created' => __( 'made a new friend', 'zeko' ),
			'message_sent'       => __( 'sent a message', 'zeko' ),
			'post_created'       => __( 'created a post', 'zeko' ),
			'comment_created'    => __( 'posted a comment', 'zeko' ),
			'like_created'       => __( 'liked something', 'zeko' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( str_replace( '_', ' ', $type ) );
	}
} // End if function_exists.

/**
 * Get activity time ago
 * Delegates to Zeko_Core_Helpers when zeko-core is active.
 *
 * @param mixed $date Date.
 */
function zeko_get_activity_time_ago( $date ) {
	if ( class_exists( 'Zeko_Core_Helpers' ) ) {
		return Zeko_Core_Helpers::time_ago( (string) $date, ' ago' );
	}
	if ( function_exists( 'zeko_time_ago' ) ) {
		return zeko_time_ago( $date, ' ago' );
	}
	$time = strtotime( (string) $date );
	if ( ! $time ) {
		return __( 'Unknown', 'zeko' );
	}
	$diff = time() - $time;
	if ( $diff < 60 ) {
		return __( 'just now', 'zeko' );
	}
	$min = (int) floor( $diff / 60 );
	if ( $min < 60 ) {
		/* translators: %d: number of minutes */
		return sprintf( __( '%1$d minute(s) ago', 'zeko' ), $min );
	}
	$hr = (int) floor( $min / 60 );
	if ( $hr < 24 ) {
		/* translators: %d: number of hours */
		return sprintf( __( '%1$d hour(s) ago', 'zeko' ), $hr );
	}
	$day = (int) floor( $hr / 24 );
	/* translators: %d: number of days */
	return sprintf( __( '%1$d day(s) ago', 'zeko' ), $day );
}


/**
 * Get module access buttons
 *
 * @param _ $_user_id user id.
 */
function zeko_get_module_access_buttons( $_user_id ) {
	$modules = array(
		'jobs'      => array(
			'label'       => __( 'Jobs', 'zeko' ),
			'icon'        => 'portfolio',
			'url'         => home_url( '/jobs/' ),
			'description' => __( 'Find and post jobs', 'zeko' ),
		),
		'qa'        => array(
			'label'       => __( 'Q&A', 'zeko' ),
			'icon'        => 'editor-help',
			'url'         => home_url( '/qa/' ),
			'description' => __( 'Ask and answer questions', 'zeko' ),
		),
		'learn'     => array(
			'label'       => __( 'Learn', 'zeko' ),
			'icon'        => 'welcome-learn-more',
			'url'         => home_url( '/learn/' ),
			'description' => __( 'Online courses and learning', 'zeko' ),
		),
		'mentor'    => array(
			'label'       => __( 'Mentorship', 'zeko' ),
			'icon'        => 'groups',
			'url'         => home_url( '/mentor/' ),
			'description' => __( 'Find mentors and mentees', 'zeko' ),
		),
		'freelance' => array(
			'label'       => __( 'Freelance', 'zeko' ),
			'icon'        => 'admin-tools',
			'url'         => home_url( '/freelance/' ),
			'description' => __( 'Freelance projects', 'zeko' ),
		),
		'shop'      => array(
			'label'       => __( 'Shop', 'zeko' ),
			'icon'        => 'cart',
			'url'         => home_url( '/shop/' ),
			'description' => __( 'Buy and sell products', 'zeko' ),
		),
	);

	ob_start();
	?>
	<div class="zeko-modules-grid">
		<?php foreach ( $modules as $module => $data ) : ?>
			<a href="<?php echo esc_url( $data['url'] ); ?>" class="zeko-module-card">
				<div class="zeko-module-icon">
					<span class="dashicons dashicons-<?php echo esc_attr( $data['icon'] ); ?>"></span>
				</div>
				<div class="zeko-module-info">
					<h4 class="zeko-module-title"><?php echo esc_html( $data['label'] ); ?></h4>
					<p class="zeko-module-desc"><?php echo esc_html( $data['description'] ); ?></p>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Log profile update activity
 *
 * @param mixed $user_id User id.
 * @param mixed $fields_updated Fields updated.
 */
function zeko_log_profile_activity( $user_id, $fields_updated ) {
	zeko_add_activity_item(
		$user_id,
		'profile_updated',
		array(
			'fields' => $fields_updated,
		)
	);
}

/**
 * Log avatar update activity
 *
 * @param mixed $user_id User id.
 */
function zeko_log_avatar_activity( $user_id ) {
	zeko_add_activity_item( $user_id, 'avatar_updated' );
}

/**
 * Log cover update activity
 *
 * @param mixed $user_id User id.
 */
function zeko_log_cover_activity( $user_id ) {
	zeko_add_activity_item( $user_id, 'cover_updated' );
}

/**
 * Add activity item
 *
 * @param mixed $user_id User id.
 * @param mixed $activity_type Activity type.
 * @param array $activity_data Activity data.
 */
function zeko_add_activity_item( $user_id, $activity_type, $activity_data = array() ) {
	if ( class_exists( 'Zeko_Core_Activity' ) ) {
		$message = isset( $activity_data['content'] ) ? $activity_data['content'] : '';
		Zeko_Core_Activity::get_instance()->log( (int) $user_id, sanitize_text_field( $activity_type ), $message, 0, $activity_data );
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_user_activity';
	$clean_meta = function_exists( 'zeko_sanitize_activity_meta' ) ? zeko_sanitize_activity_meta( $activity_data ) : array();

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
 * Setup default dashboard widgets for new users
 *
 * @param mixed $user_id User id.
 */
function zeko_setup_default_dashboard_widgets( $user_id ) {
	$default_widgets = array(
		array(
			'widget_type'     => 'welcome',
			'widget_title'    => __( 'Welcome to Zeko', 'zeko' ),
			'widget_content'  => '[zeko_welcome_widget]',
			'widget_column'   => 'main',
			'widget_position' => 10,
		),
		array(
			'widget_type'     => 'quick_links',
			'widget_title'    => __( 'Quick Links', 'zeko' ),
			'widget_content'  => '[zeko_quick_links_widget]',
			'widget_column'   => 'main',
			'widget_position' => 20,
		),
		array(
			'widget_type'     => 'stats',
			'widget_title'    => __( 'Statistics', 'zeko' ),
			'widget_content'  => '[zeko_stats_widget]',
			'widget_column'   => 'sidebar',
			'widget_position' => 10,
		),
	);

	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		Zeko_Core_App_Data::insert_default_dashboard_widgets( (int) $user_id, $default_widgets );
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_dashboard_widgets';

	foreach ( $default_widgets as $widget ) {
		$wpdb->insert(
			$table_name,
			array(
				'user_id'         => $user_id,
				'widget_type'     => $widget['widget_type'],
				'widget_title'    => $widget['widget_title'],
				'widget_content'  => $widget['widget_content'],
				'widget_column'   => $widget['widget_column'],
				'widget_position' => $widget['widget_position'],
				'widget_status'   => 'active',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}
}

/**
 * Add dashboard menu to admin bar
 *
 * @param mixed $admin_bar Admin bar.
 */
function zeko_add_dashboard_menu_to_admin_bar( $admin_bar ) {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$admin_bar->add_menu(
		array(
			'id'    => 'zeko-dashboard',
			'title' => __( 'Dashboard', 'zeko' ),
			'href'  => home_url( '/dashboard/' ),
			'meta'  => array(
				'title' => __( 'Dashboard', 'zeko' ),
			),
		)
	);
}

/**
 * AJAX handler for saving widget positions
 */
function zeko_ajax_save_widget_positions() {
	check_ajax_referer( 'zeko_dashboard_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$user_id   = get_current_user_id();
	$positions = isset( $_POST['positions'] ) && is_array( $_POST['positions'] ) ? wp_unslash( $_POST['positions'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nested array; each widget_id (absint), position (intval/bounds-checked) and column (sanitize_key + allow-list) is validated per-field below.

	if ( empty( $positions ) ) {
		wp_send_json_error( array( 'message' => __( 'No widget positions provided.', 'zeko' ) ) );
	}

	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		Zeko_Core_App_Data::save_widget_positions( (int) $user_id, $positions );
	} else {
		$allowed_columns = array( 'main', 'sidebar' );
		$max_position    = 1000;

		global $wpdb;
		$table_name = $wpdb->prefix . 'zeko_dashboard_widgets';

		foreach ( $positions as $widget_id => $position_data ) {
			$widget_id = absint( $widget_id );

			// Validate the structure of each entry; skip malformed rows.
			if ( ! $widget_id || ! is_array( $position_data ) ) {
				continue;
			}

			// Validate integer bounds; out-of-range values are clamped.
			$position = isset( $position_data['position'] ) ? intval( $position_data['position'] ) : 0;
			if ( $position < 0 ) {
				$position = 0;
			}
			$position = min( $position, $max_position );

			// Allow only known column names.
			$column = isset( $position_data['column'] ) ? sanitize_key( $position_data['column'] ) : '';
			if ( ! in_array( $column, $allowed_columns, true ) ) {
				$column = 'main';
			}

			$wpdb->update(
				$table_name,
				array(
					'widget_position' => $position,
					'widget_column'   => $column,
				),
				array(
					'widget_id' => $widget_id,
					'user_id'   => $user_id,
				),
				array( '%d', '%s' ),
				array( '%d', '%d' )
			);
		}
	}

	wp_send_json_success( array( 'message' => __( 'Dashboard layout saved!', 'zeko' ) ) );
}
add_action( 'wp_ajax_zeko_save_widget_positions', 'zeko_ajax_save_widget_positions' );

/**
 * AJAX handler for adding widget
 */
function zeko_ajax_add_widget() {
	check_ajax_referer( 'zeko_dashboard_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$user_id     = get_current_user_id();
	$widget_type = isset( $_POST['widget_type'] ) ? sanitize_key( $_POST['widget_type'] ) : '';
	$column      = isset( $_POST['column'] ) ? sanitize_key( $_POST['column'] ) : 'main';

	if ( ! in_array( $widget_type, array_keys( zeko_get_widget_defaults() ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid widget type.', 'zeko' ) ) );
	}

	if ( ! in_array( $column, array( 'main', 'sidebar' ), true ) ) {
		$column = 'main';
	}

	// Get widget position (next available position in column).
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		$widget_id = Zeko_Core_App_Data::add_dashboard_widget( $user_id, $widget_type, $column, zeko_get_widget_default_title( $widget_type ), zeko_get_widget_default_content( $widget_type ) );
		$widget    = $widget_id ? Zeko_Core_App_Data::get_dashboard_widget( $widget_id ) : null;
	} else {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zeko_dashboard_widgets';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$position = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(widget_position) + 10 FROM $table_name WHERE user_id = %d AND widget_column = %s",
				$user_id,
				$column
			)
		) ?: 10;
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		// Insert new widget.
		$widget_id = $wpdb->insert(
			$table_name,
			array(
				'user_id'         => $user_id,
				'widget_type'     => $widget_type,
				'widget_title'    => zeko_get_widget_default_title( $widget_type ),
				'widget_content'  => zeko_get_widget_default_content( $widget_type ),
				'widget_column'   => $column,
				'widget_position' => $position,
				'widget_status'   => 'active',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$widget = $widget_id ? $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE widget_id = %d",
				$widget_id
			)
		) : null;
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	if ( $widget_id ) {
		ob_start();
		?>
		<div class="zeko-dashboard-widget zeko-widget-<?php echo esc_attr( $widget->widget_type ); ?>" data-widget-id="<?php echo esc_attr( $widget->widget_id ); ?>">
			<div class="zeko-widget-header">
				<h2 class="zeko-widget-title"><?php echo esc_html( $widget->widget_title ); ?></h2>
				<div class="zeko-widget-actions">
					<button class="zeko-widget-edit-btn"><?php esc_html_e( 'Edit', 'zeko' ); ?></button>
					<button class="zeko-widget-remove-btn"><?php esc_html_e( 'Remove', 'zeko' ); ?></button>
				</div>
			</div>
			<div class="zeko-widget-content">
				<?php echo do_shortcode( $widget->widget_content ); ?>
			</div>
		</div>
		<?php
		$widget_html = ob_get_clean();

		wp_send_json_success(
			array(
				'message'     => __( 'Widget added successfully!', 'zeko' ),
				'widget_id'   => $widget_id,
				'widget_html' => $widget_html,
			)
		);
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to add widget.', 'zeko' ) ) );
	}
}
add_action( 'wp_ajax_zeko_add_widget', 'zeko_ajax_add_widget' );

/**
 * Get the canonical widget type definitions (title + shortcode content).
 * Single source of truth for dashboard widget types; used by the "add
 * widget" allow-list and the default title/content resolvers.
 *
 * @return array{titles:array,content:array}
 */
function zeko_get_widget_defaults() {
	return array(
		'titles'  => array(
			'welcome'         => __( 'Welcome', 'zeko' ),
			'quick_links'     => __( 'Quick Links', 'zeko' ),
			'stats'           => __( 'Statistics', 'zeko' ),
			'activity'        => __( 'Recent Activity', 'zeko' ),
			'friends'         => __( 'Friends', 'zeko' ),
			'messages'        => __( 'Messages', 'zeko' ),
			'notifications'   => __( 'Notifications', 'zeko' ),
			'my_posted_jobs'  => __( 'My Posted Jobs', 'zeko' ),
			'my_applications' => __( 'My Job Applications', 'zeko' ),
		),
		'content' => array(
			'welcome'         => '[zeko_welcome_widget]',
			'quick_links'     => '[zeko_quick_links_widget]',
			'stats'           => '[zeko_stats_widget]',
			'activity'        => '[zeko_activity_widget]',
			'friends'         => '[zeko_friends_widget]',
			'messages'        => '[zeko_messages_widget]',
			'notifications'   => '[zeko_notifications_widget]',
			'my_posted_jobs'  => '[zeko_posted_jobs_widget]',
			'my_applications' => '[zeko_applications_widget]',
		),
	);
}

/**
 * Get widget default title
 *
 * @param mixed $widget_type Widget type.
 */
function zeko_get_widget_default_title( $widget_type ) {
	$defaults = zeko_get_widget_defaults();

	return isset( $defaults['titles'][ $widget_type ] ) ? $defaults['titles'][ $widget_type ] : ucfirst( str_replace( '_', ' ', $widget_type ) );
}

/**
 * Get widget default content
 *
 * @param mixed $widget_type Widget type.
 */
function zeko_get_widget_default_content( $widget_type ) {
	$defaults = zeko_get_widget_defaults();

	return isset( $defaults['content'][ $widget_type ] ) ? $defaults['content'][ $widget_type ] : '';
}

/**
 * AJAX handler for removing widget
 */
function zeko_ajax_remove_widget() {
	check_ajax_referer( 'zeko_dashboard_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$user_id   = get_current_user_id();
	$widget_id = isset( $_POST['widget_id'] ) ? intval( $_POST['widget_id'] ) : 0;

	if ( empty( $widget_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid widget ID.', 'zeko' ) ) );
	}

	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		$result = Zeko_Core_App_Data::remove_dashboard_widget( $widget_id, $user_id );
	} else {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zeko_dashboard_widgets';

		$result = $wpdb->delete(
			$table_name,
			array(
				'widget_id' => $widget_id,
				'user_id'   => $user_id,
			),
			array( '%d', '%d' )
		);
	}

	if ( $result ) {
		wp_send_json_success( array( 'message' => __( 'Widget removed successfully!', 'zeko' ) ) );
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to remove widget.', 'zeko' ) ) );
	}
}
add_action( 'wp_ajax_zeko_remove_widget', 'zeko_ajax_remove_widget' );

/**
 * AJAX handler for resetting dashboard layout
 */
function zeko_ajax_reset_dashboard_layout() {
	check_ajax_referer( 'zeko_dashboard_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$user_id = get_current_user_id();

	// Remove all existing widgets.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		Zeko_Core_App_Data::delete_dashboard_widgets( $user_id );
	} else {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zeko_dashboard_widgets';

		$wpdb->delete( $table_name, array( 'user_id' => $user_id ), array( '%d' ) );
	}

	// Set up default widgets.
	zeko_setup_default_dashboard_widgets( $user_id );

	wp_send_json_success( array( 'message' => __( 'Dashboard layout reset to default!', 'zeko' ) ) );
}
add_action( 'wp_ajax_zeko_reset_dashboard_layout', 'zeko_ajax_reset_dashboard_layout' );

/**
 * Get current user's posted jobs for dashboard widget.
 *
 * @param mixed $user_id User id.
 */
function zeko_get_my_posted_jobs_widget( $user_id ) {
	if ( class_exists( 'Zeko_Jobs_Public' ) && method_exists( 'Zeko_Jobs_Public', 'get_my_posted_jobs_widget' ) ) {
		return Zeko_Jobs_Public::get_instance()->get_my_posted_jobs_widget( (int) $user_id );
	}
	if ( ! class_exists( 'Zeko_Jobs_DB' ) ) {
		return '';
	}
	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_jobs';
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	$jobs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, title, slug, status, created_at FROM $table_name WHERE employer_id = %d ORDER BY created_at DESC LIMIT 5",
			$user_id
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	ob_start();
	?>
	<div class="zeko-my-jobs-widget">
		<h4><?php esc_html_e( 'My Posted Jobs', 'zeko' ); ?></h4>
		<?php if ( ! empty( $jobs ) ) : ?>
			<ul>
				<?php foreach ( $jobs as $job ) : ?>
					<li>
						<a href="<?php echo esc_url( home_url( '/jobs/' . $job['slug'] ) ); ?>">
							<?php echo esc_html( $job['title'] ); ?>
						</a>
						<span class="zeko-job-status status-<?php echo esc_attr( $job['status'] ); ?>">
							(<?php echo esc_html( ucfirst( $job['status'] ) ); ?>)
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p><a href="<?php echo esc_url( home_url( '/job-dashboard/' ) ); ?>"><?php esc_html_e( 'View All My Jobs', 'zeko' ); ?></a></p>
		<?php else : ?>
			<p><?php esc_html_e( 'You haven\'t posted any jobs yet.', 'zeko' ); ?></p>
			<p><a href="<?php echo esc_url( home_url( '/post-a-job/' ) ); ?>" class="button button-primary button-small"><?php esc_html_e( 'Post a New Job', 'zeko' ); ?></a></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Get current user's job applications for dashboard widget.
 *
 * @param mixed $user_id User id.
 */
function zeko_get_my_applications_widget( $user_id ) {
	if ( class_exists( 'Zeko_Jobs_Public' ) && method_exists( 'Zeko_Jobs_Public', 'get_my_applications_widget' ) ) {
		return Zeko_Jobs_Public::get_instance()->get_my_applications_widget( (int) $user_id );
	}
	if ( ! class_exists( 'Zeko_Jobs_DB' ) ) {
		return '';
	}
	global $wpdb;
	$applications_table = $wpdb->prefix . 'zeko_job_applications';
	$jobs_table         = $wpdb->prefix . 'zeko_jobs';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	$applications = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT app.id, app.status, app.applied_at, job.title, job.slug FROM $applications_table app JOIN $jobs_table job ON app.job_id = job.id WHERE app.seeker_id = %d ORDER BY app.applied_at DESC LIMIT 5",
			$user_id
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	ob_start();
	?>
	<div class="zeko-my-applications-widget">
		<h4><?php esc_html_e( 'My Job Applications', 'zeko' ); ?></h4>
		<?php if ( ! empty( $applications ) ) : ?>
			<ul>
				<?php foreach ( $applications as $app ) : ?>
					<li>
						<a href="<?php echo esc_url( home_url( '/jobs/' . $app['slug'] ) ); ?>">
							<?php echo esc_html( $app['title'] ); ?>
						</a>
						<span class="zeko-application-status status-<?php echo esc_attr( $app['status'] ); ?>">
							(<?php echo esc_html( ucfirst( $app['status'] ) ); ?>)
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p><a href="<?php echo esc_url( home_url( '/job-dashboard/' ) ); ?>"><?php esc_html_e( 'View All My Applications', 'zeko' ); ?></a></p>
		<?php else : ?>
			<p><?php esc_html_e( 'You haven\'t applied for any jobs yet.', 'zeko' ); ?></p>
			<p><a href="<?php echo esc_url( home_url( '/jobs/' ) ); ?>" class="button button-primary button-small"><?php esc_html_e( 'Browse Jobs', 'zeko' ); ?></a></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
