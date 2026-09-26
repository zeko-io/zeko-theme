<?php
/**
 * Activity feed functionality
 *
 * @package Zeko
 */

/**
 * Create custom activity feed database tables
 */
function zeko_create_activity_tables() {
	if ( class_exists( 'Zeko_Core_DB' ) ) {
		Zeko_Core_DB::get_instance()->create_tables();
		return;
	}

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$table_name = $wpdb->prefix . 'zeko_user_activity';
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
		$sql = "CREATE TABLE $table_name (
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

/**
 * Initialize activity feed system
 */
function zeko_init_activity_feed() {

	// Ensure tables exist (throttled safety net; admin migration owns this).
	zeko_theme_init_schema();

	// Add activity feed shortcode (core is the single owner when present;.
	// Core-less standalone theme registers its own copy).
	if ( ! class_exists( 'Zeko_Core_Activity' ) ) {
		add_shortcode( 'zeko_activity_feed', 'zeko_activity_feed_shortcode' );
	}

	// Enqueue activity feed scripts.
	add_action( 'wp_enqueue_scripts', 'zeko_enqueue_activity_feed_scripts' );
	add_action( 'admin_enqueue_scripts', 'zeko_enqueue_activity_feed_scripts' ); // For admin/dashboard.

	// Create activity page if it doesn't exist - call directly instead of adding to init (throttled).
	if ( zeko_page_setup_due( 'activity' ) ) {
		zeko_create_activity_page();
	}
}
add_action( 'init', 'zeko_init_activity_feed' );

/**
 * Enqueue activity feed scripts and styles
 */
function zeko_enqueue_activity_feed_scripts() {
	// Only load on activity page or dashboard for now.
	if ( zeko_is_activity_page() || zeko_is_dashboard_page() ) {
		$theme_dir = get_template_directory();
		wp_enqueue_script( 'zeko-activity-feed', get_template_directory_uri() . '/assets/js/activity-feed.js', array( 'jquery' ), file_exists( $theme_dir . '/assets/js/activity-feed.js' ) ? filemtime( $theme_dir . '/assets/js/activity-feed.js' ) : '1.0.0', true );
		wp_enqueue_style( 'zeko-activity-feed', get_template_directory_uri() . '/assets/css/activity-feed.css', array(), file_exists( $theme_dir . '/assets/css/activity-feed.css' ) ? filemtime( $theme_dir . '/assets/css/activity-feed.css' ) : '1.0.0' );

		wp_localize_script(
			'zeko-activity-feed',
			'zekoActivityData',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'zeko_activity_nonce' ),
				'i18n'     => array(
					'loading' => __( 'Loading more activities...', 'zeko' ),
					'no_more' => __( 'No more activities found.', 'zeko' ),
					'error'   => __( 'Error loading activities.', 'zeko' ),
				),
			)
		);
	}
}

/**
 * Check if current page is an activity page
 */
function zeko_is_activity_page() {
	$activity_page_id = get_option( 'zeko_activity_page_id' );
	$request_uri      = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return is_page( $activity_page_id ) || strpos( $request_uri, '/activity/' ) !== false;
}

/**
 * Check if current page is the dashboard page (assuming a function exists in dashboard.php)
 */
function zeko_is_dashboard_page() {
	$dashboard_page_id = get_option( 'zeko_dashboard_page_id' );
	$request_uri       = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return ( $dashboard_page_id && is_page( $dashboard_page_id ) ) || strpos( $request_uri, '/dashboard/' ) !== false;
}

/**
 * Create activity page if it doesn't exist
 */
function zeko_create_activity_page() {

	// Check if activity page already exists.
	$page = get_page_by_path( 'activity' );

	if ( $page ) {

		// Update page content if needed.
		$content = $page->post_content;
		if ( empty( $content ) || strpos( $content, '[zeko_activity_feed]' ) === false ) {
			wp_update_post(
				array(
					'ID'           => $page->ID,
					'post_content' => '[zeko_activity_feed]',
				)
			);
		}
		update_option( 'zeko_activity_page_id', $page->ID );
	} else {
		// Create new activity page.
		$page_id = wp_insert_post(
			array(
				'post_title'     => __( 'Activity Feed', 'zeko' ),
				'post_name'      => 'activity',
				'post_content'   => '[zeko_activity_feed]',
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);

		if ( $page_id ) {
			update_option( 'zeko_activity_page_id', $page_id );
			// Flush rewrite rules to ensure page is accessible.
			flush_rewrite_rules();
		}
	}
}

/**
 * Activity feed shortcode
 *
 * @param mixed $atts Atts.
 */
function zeko_activity_feed_shortcode( $atts ) {
	// Core-present: delegate to the single owner so this function (used by the.
	// legacy [zeko_activity_form] alias and any direct callers) can never.
	// diverge from core's markup (rows 46/47 gate-and-delegate pattern).
	if ( class_exists( 'Zeko_Core_Activity' ) ) {
		return Zeko_Core_Activity::get_instance()->activity_feed_shortcode( $atts );
	}

	$atts = shortcode_atts(
		array(
			'per_page' => 10,
			'user_id'  => 0, // 0 for all users, or specific user ID.
			'context'  => 'public', // 'public', 'profile', 'dashboard'.
		),
		$atts
	);

	if ( ! is_user_logged_in() ) {
		return '<div class="zeko-activity-feed-login-required"><p>' .
			esc_html__( 'Please log in to view the activity feed.', 'zeko' ) .
			'</p></div>';
	}

	ob_start();
	?>
	<div class="zeko-activity-feed-container">
		<div class="zeko-activity-feed-header">
			<h3><?php esc_html_e( 'Activity Feed', 'zeko' ); ?></h3>
			<div class="zeko-activity-filters">
				<div class="zeko-filter-group">
					<select class="zeko-activity-type-filter">
						<option value="all"><?php esc_html_e( 'All Activities', 'zeko' ); ?></option>
						<option value="asked_question"><?php esc_html_e( 'Questions Asked', 'zeko' ); ?></option>
						<option value="answered_question"><?php esc_html_e( 'Answers Given', 'zeko' ); ?></option>
						<option value="message_sent"><?php esc_html_e( 'Messages', 'zeko' ); ?></option>
						<option value="profile_updated"><?php esc_html_e( 'Profile Updates', 'zeko' ); ?></option>
						<option value="avatar_updated"><?php esc_html_e( 'Avatar Changes', 'zeko' ); ?></option>
						<option value="cover_updated"><?php esc_html_e( 'Cover Photo Changes', 'zeko' ); ?></option>
						<option value="login"><?php esc_html_e( 'Logins', 'zeko' ); ?></option>
						<option value="registration"><?php esc_html_e( 'Registrations', 'zeko' ); ?></option>
					</select>
				</div>
				<div class="zeko-filter-group">
					<select class="zeko-date-range-filter">
						<option value="all"><?php esc_html_e( 'All Time', 'zeko' ); ?></option>
						<option value="today"><?php esc_html_e( 'Today', 'zeko' ); ?></option>
						<option value="week"><?php esc_html_e( 'Last 7 Days', 'zeko' ); ?></option>
						<option value="month"><?php esc_html_e( 'Last 30 Days', 'zeko' ); ?></option>
						<option value="year"><?php esc_html_e( 'Last Year', 'zeko' ); ?></option>
					</select>
				</div>
				<div class="zeko-filter-group">
					<input type="text" class="zeko-activity-search" placeholder="<?php esc_attr_e( 'Search activities...', 'zeko' ); ?>">
					<button class="zeko-activity-search-btn"><?php esc_html_e( 'Search', 'zeko' ); ?></button>
				</div>
				<div class="zeko-filter-group">
					<button class="zeko-clear-filters-btn"><?php esc_html_e( 'Clear Filters', 'zeko' ); ?></button>
				</div>
			</div>
		</div>
		<div class="zeko-activity-feed" data-per-page="<?php echo esc_attr( $atts['per_page'] ); ?>" data-user-id="<?php echo esc_attr( $atts['user_id'] ); ?>" data-context="<?php echo esc_attr( $atts['context'] ); ?>">
			<!-- Activities will be loaded here via AJAX -->
			<p class="zeko-loading-activities"><?php esc_html_e( 'Loading activities...', 'zeko' ); ?></p>
		</div>
		<button class="btn btn-secondary zeko-load-more-activities" style="display: none;">
			<?php esc_html_e( 'Load More', 'zeko' ); ?>
		</button>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * AJAX endpoint + message formatting for the activity feed moved to their single
 * owner, Zeko_Core_Activity (plugins/zeko-core/includes/class-zeko-core-activity.php):
 * the theme's copy registered the same wp_ajax_zeko_load_activity_feed action and
 * the same zeko_format_activity_message() logic byte-for-byte. De-registered here
 * so the feed's data endpoint has one owner (row 2 pilot extraction). The theme
 * keeps presentation only: enqueue + localize in this file, and the template
 * markup it renders. No logic is lost: whenever zeko-core is present (the
 * ecosystem's required module) the core class answers the AJAX request and
 * formats the same messages using Zeko_Core_Helpers for avatar/profile URLs.
 *
 * The [zeko_activity_feed] shortcode tag is consolidated the same way: core's
 * Zeko_Core_Activity::activity_feed_shortcode() now renders the full UI (login
 * gate, header, filters, inner .zeko-activity-feed carrying the data attributes,
 * load-more) and is the sole registrant when core is present; this file only
 * registers the tag in a Core-less install, and the theme function below
 * delegates to core (first lines) with the original body kept as fallback.
 *
 * Legacy shortcode support for [zeko_activity_form] — theme-owned alias,
 * always registered here (core does not grow a second tag); its callback
 * delegates to core when present so both tags render identical markup.
 */
add_shortcode( 'zeko_activity_form', 'zeko_activity_feed_shortcode' );
?>
