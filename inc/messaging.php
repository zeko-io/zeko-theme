<?php
/**
 * Messaging system functionality
 *
 * @package Zeko
 */

// If the zeko-jobs plugin provides messaging, bail — let the plugin handle everything.
if ( defined( 'ZEKO_JOBS_VERSION' ) && class_exists( 'Zeko_Jobs_Messaging' ) ) {
	return;
}

/**
 * Create messaging database tables
 * Canonical ownership: zeko-core "messaging" module
 * (class-zeko-app-schema.php). When zeko-core is absent, the tables are
 * owned by zeko-jobs (class-zeko-jobs-db.php) and this stays a no-op;
 * messaging is then only functional while zeko-jobs is active.
 */
function zeko_create_messaging_tables() {
	if ( class_exists( 'Zeko_Core_DB' ) ) {
		Zeko_Core_DB::get_instance()->ensure_module( 'messaging' );
	}
}

/**
 * Initialize messaging system
 */
function zeko_init_messaging() {

	zeko_create_messaging_tables();

	// Enqueue messaging assets.
	add_action( 'wp_enqueue_scripts', 'zeko_enqueue_messaging_assets' );

	// Add messaging menu to admin bar.
	add_action( 'admin_bar_menu', 'zeko_add_messaging_menu_to_admin_bar', 90 );

	// Add messaging endpoints.
	add_action( 'init', 'zeko_add_messaging_endpoints' );

	// Create messages page if it doesn't exist.
	add_action( 'init', 'zeko_create_messages_page' );
}

/**
 * Create messages page if it doesn't exist
 */
function zeko_create_messages_page() {
	// Force update on every init to ensure shortcode is correct.
	$page    = get_page_by_path( 'messages' );
	$updated = false;

	if ( $page ) {
		$content = $page->post_content;

		// Check for old shortcode and update.
		if ( strpos( $content, '[zeko_messages_form]' ) !== false ) {
			wp_update_post(
				array(
					'ID'           => $page->ID,
					'post_content' => '[zeko_messaging]',
				)
			);
			$updated = true;
		}

		// Also check if page is empty or has wrong content.
		if ( empty( $content ) || strpos( $content, '[zeko_messaging]' ) === false ) {
			wp_update_post(
				array(
					'ID'           => $page->ID,
					'post_content' => '[zeko_messaging]',
				)
			);
			$updated = true;
		}

		update_option( 'zeko_messages_page_id', $page->ID );
	} else {
		// Create new page if doesn't exist.
		$page_id = wp_insert_post(
			array(
				'post_title'     => __( 'Messages', 'zeko' ),
				'post_name'      => 'messages',
				'post_content'   => '[zeko_messaging]',
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);

		if ( $page_id ) {
			update_option( 'zeko_messages_page_id', $page_id );
			$updated = true;
		}
	}

	// Force flush rewrite rules to ensure page is accessible.
	if ( $updated ) {
		flush_rewrite_rules();
	}
}

/**
 * Manual function to fix messages page - can be called via AJAX or directly
 */
function zeko_fix_messages_page_manually() {
	global $wpdb;

	// First try WordPress method.
	$page = get_page_by_path( 'messages' );

	if ( $page ) {
		// Force update the page content.
		$result = wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => '[zeko_messaging]',
			)
		);

		if ( $result ) {
			update_option( 'zeko_messages_page_id', $page->ID );
			flush_rewrite_rules();
			return true;
		}
	}

	// If WordPress method fails, try direct database update.
	$page_id = $wpdb->get_var(
		"SELECT ID FROM {$wpdb->posts} WHERE post_name = 'messages' AND post_type = 'page' AND post_status = 'publish' LIMIT 1"
	);

	if ( $page_id ) {
		$result = $wpdb->update(
			$wpdb->posts,
			array( 'post_content' => '[zeko_messaging]' ),
			array( 'ID' => $page_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			update_option( 'zeko_messages_page_id', $page_id );
			flush_rewrite_rules();
			return true;
		}
	}

	return false;
}

/**
 * AJAX handler for manual fix
 * Stays theme-side (row 2 / B.3): page provisioning is a theme responsibility.
 * Different nonce ('zeko_fix_nonce') and manage_options gate — no Core twin.
 */
function zeko_ajax_fix_messages_page() {
	check_ajax_referer( 'zeko_fix_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'zeko' ) ) );
	}

	$result = zeko_fix_messages_page_manually();

	if ( $result ) {
		wp_send_json_success(
			array(
				'message' => __( 'Messages page has been fixed! Please refresh the page.', 'zeko' ),
			)
		);
	} else {
		wp_send_json_error(
			array(
				'message' => __( 'Failed to fix messages page. Please check if the page exists.', 'zeko' ),
			)
		);
	}
}
add_action( 'wp_ajax_zeko_fix_messages_page', 'zeko_ajax_fix_messages_page' );
add_action( 'init', 'zeko_init_messaging' );

/**
 * Add messaging endpoints
 */
function zeko_add_messaging_endpoints() {
	add_rewrite_endpoint( 'messages', EP_PERMALINK | EP_PAGES );
	add_rewrite_endpoint( 'conversation', EP_PERMALINK | EP_PAGES );

	// Removed the unconditional flush_rewrite_rules() here. This is hooked to.
	// 'init' and previously ran on EVERY page load, regenerating WordPress's.
	// stored rewrite_rules and dropping other plugins' custom rewrite rules.
	// (e.g. zeko-business /businesses/.../, /business-portal/, /business-create/,.
	// and /businesses/{slug}/services/{id}/). WordPress already flushes rewrite.
	// rules automatically on theme switch/activation, so no flush is needed here.
	// Any backend-triggered flushes live in the page-creation helpers instead.
}

/**
 * Enqueue messaging assets
 */
function zeko_enqueue_messaging_assets() {
	if ( zeko_is_messaging_page() ) {
		$theme_dir = get_template_directory();
		wp_enqueue_script( 'zeko-messaging', get_template_directory_uri() . '/assets/js/messaging.js', array( 'jquery' ), file_exists( $theme_dir . '/assets/js/messaging.js' ) ? filemtime( $theme_dir . '/assets/js/messaging.js' ) : '1.0.0', true );
		wp_enqueue_style( 'zeko-messaging', get_template_directory_uri() . '/assets/css/messaging.css', array(), file_exists( $theme_dir . '/assets/css/messaging.css' ) ? filemtime( $theme_dir . '/assets/css/messaging.css' ) : '1.0.0' );

		wp_localize_script(
			'zeko-messaging',
			'zekoMessagingData',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'zeko_messaging_nonce' ),
				'i18n'     => array(
					'sending'            => __( 'Sending...', 'zeko' ),
					'sent'               => __( 'Message sent!', 'zeko' ),
					'error'              => __( 'Error: ', 'zeko' ),
					'no_messages'        => __( 'No messages yet.', 'zeko' ),
					'load_more'          => __( 'Load More Messages', 'zeko' ),
					'loading'            => __( 'Loading...', 'zeko' ),
					'new_message'        => __( 'New Message', 'zeko' ),
					'search_placeholder' => __( 'Search messages...', 'zeko' ),
				),
			)
		);
	}
}

/**
 * Check if current page is a messaging page
 */
function zeko_is_messaging_page() {
	$messaging_page_id = get_option( 'zeko_messages_page_id' );

	if ( $messaging_page_id ) {
		if ( is_page( $messaging_page_id ) ) {
			return true;
		}
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return strpos( $request_uri, 'messages' ) !== false;
}

/**
 * Messaging shortcode
 *
 * @param mixed $atts Atts.
 */
function zeko_messaging_shortcode( $atts ) {

	$atts = shortcode_atts(
		array(
			'user_id'            => get_current_user_id(),
			'show_conversations' => 'true',
			'show_compose'       => 'true',
		),
		$atts
	);

	if ( ! is_user_logged_in() ) {
		return '<p class="zeko-not-logged-in">' . __( 'You must be logged in to view your messages.', 'zeko' ) . '</p>';
	}

	$user_id         = $atts['user_id'];
	$current_user_id = get_current_user_id();

	if ( $user_id !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
		return '<p class="zeko-error">' . __( 'You do not have permission to view these messages.', 'zeko' ) . '</p>';
	}

	ob_start();
	?>
	<div class="zeko-messaging" data-user-id="<?php echo esc_attr( $user_id ); ?>">
		<div class="zeko-messaging-container">
			<?php if ( 'true' === $atts['show_conversations'] ) : ?>
				<div class="zeko-conversations-list">
					<div class="zeko-conversations-header">
						<h2 class="zeko-conversations-title"><?php esc_html_e( 'Messages', 'zeko' ); ?></h2>
						<?php if ( 'true' === $atts['show_compose'] ) : ?>
							<button class="btn btn-primary zeko-new-message-btn">
								<?php esc_html_e( 'New Message', 'zeko' ); ?>
							</button>
						<?php endif; ?>
					</div>

					<div class="zeko-search-messages">
						<label class="screen-reader-text" for="zeko-message-search"><?php esc_html_e( 'Search messages', 'zeko' ); ?></label>
						<input type="text" id="zeko-message-search" class="zeko-message-search" placeholder="<?php esc_attr_e( 'Search messages...', 'zeko' ); ?>">
					</div>

					<div class="zeko-conversations">
						<?php echo zeko_get_conversations_list( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_conversations_list() escapes values internally (esc_html/esc_url); previews use zeko_get_last_message_preview() which escapes. ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="zeko-message-area">
				<div class="zeko-no-conversation-selected">
					<div class="zeko-no-conversation-icon">
						<span class="dashicons dashicons-email-alt"></span>
					</div>
					<h3><?php esc_html_e( 'Select a conversation', 'zeko' ); ?></h3>
					<p><?php esc_html_e( 'Choose a conversation from the left to view messages.', 'zeko' ); ?></p>
				</div>

				<div class="zeko-message-content" style="display: none;">
					<div class="zeko-message-header">
						<button class="zeko-back-to-conversations" aria-label="<?php esc_attr_e( 'Back to conversations', 'zeko' ); ?>">
							<span class="dashicons dashicons-arrow-left-alt2"></span>
						</button>
						<h3 class="zeko-conversation-title"></h3>
						<div class="zeko-conversation-actions">
							<button class="zeko-conversation-settings" aria-label="<?php esc_attr_e( 'Conversation settings', 'zeko' ); ?>">
								<span class="dashicons dashicons-admin-generic"></span>
							</button>
						</div>
					</div>

					<div class="zeko-messages-container">
						<div class="zeko-messages-list">
							<!-- Messages will be loaded here via AJAX -->
						</div>
						<div class="zeko-load-more-messages" style="display: none;">
							<button class="btn btn-secondary zeko-load-more-btn"><?php esc_html_e( 'Load More Messages', 'zeko' ); ?></button>
						</div>
					</div>

					<div class="zeko-message-compose">
						<textarea class="zeko-message-input" placeholder="<?php esc_attr_e( 'Type your message...', 'zeko' ); ?>" aria-label="<?php esc_attr_e( 'Type your message', 'zeko' ); ?>"></textarea>
						<div class="zeko-compose-actions">
							<button class="zeko-add-attachment" aria-label="<?php esc_attr_e( 'Attach file', 'zeko' ); ?>">
								<span class="dashicons dashicons-paperclip"></span>
							</button>
							<button class="btn btn-primary zeko-send-message-btn"><?php esc_html_e( 'Send', 'zeko' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- New Message Modal -->
		<div class="zeko-new-message-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="zeko-new-message-title">
			<div class="zeko-new-message-content">
				<div class="zeko-new-message-header">
					<h3 id="zeko-new-message-title"><?php esc_html_e( 'New Message', 'zeko' ); ?></h3>
					<button class="zeko-close-new-message" aria-label="<?php esc_attr_e( 'Close new message dialog', 'zeko' ); ?>">&times;</button>
				</div>
				<div class="zeko-new-message-body">
					<div class="form-group">
						<label for="zeko-recipient"><?php esc_html_e( 'To:', 'zeko' ); ?></label>
						<input type="text" id="zeko-recipient" class="zeko-recipient-search" placeholder="<?php esc_attr_e( 'Search users...', 'zeko' ); ?>">
						<div class="zeko-user-search-results"></div>
					</div>
					<div class="form-group">
						<label for="zeko-new-message-content"><?php esc_html_e( 'Message:', 'zeko' ); ?></label>
						<textarea id="zeko-new-message-content" class="zeko-new-message-text" placeholder="<?php esc_html_e( 'Write your message...', 'zeko' ); ?>"></textarea>
					</div>
				</div>
				<div class="zeko-new-message-footer">
					<button class="btn btn-secondary zeko-cancel-new-message"><?php esc_html_e( 'Cancel', 'zeko' ); ?></button>
					<button class="btn btn-primary zeko-send-new-message"><?php esc_html_e( 'Send Message', 'zeko' ); ?></button>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

// Single registrations for both shortcode tags (previously also registered.
// inside zeko_init_messaging() — deduplicated). The zeko-jobs plugin bails this.
// file out entirely when it owns messaging, so no conflict with its copy.
add_shortcode( 'zeko_messaging', 'zeko_messaging_shortcode' );

// Add legacy shortcode support for [zeko_messages_form].
add_shortcode( 'zeko_messages_form', 'zeko_messaging_shortcode' );

/**
 * Get conversations list
 *
 * @param mixed $user_id User id.
 */
function zeko_get_conversations_list( $user_id ) {
	// Gate-and-delegate: zeko-core owns the conversations data read.
	if ( class_exists( 'Zeko_Core_Messaging' ) ) {
		$conversations = Zeko_Core_Messaging::get_instance()->get_conversations( (int) $user_id );
	} else {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zeko_conversations';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name
			WHERE (user1_id = %d OR user2_id = %d)
			AND status = 'active'
			ORDER BY last_message_date DESC",
				$user_id,
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	ob_start();
	?>
	<?php if ( ! empty( $conversations ) ) : ?>
		<?php foreach ( $conversations as $conversation ) : ?>
			<?php
			// Determine the other user in the conversation.
			$other_user_id = ( (int) $conversation->user1_id === $user_id ) ? $conversation->user2_id : $conversation->user1_id;
			$other_user    = get_userdata( $other_user_id );
			if ( ! $other_user ) {
				continue;
			}

			// Get unread count for current user.
			$unread_count = ( (int) $conversation->user1_id === $user_id ) ? $conversation->unread_count_user1 : $conversation->unread_count_user2;
			?>
			<div class="zeko-conversation-item" data-conversation-id="<?php echo esc_attr( $conversation->conversation_id ); ?>">
				<div class="zeko-conversation-avatar">
					<?php echo get_avatar( $other_user_id, 50 ); ?>
				</div>
				<div class="zeko-conversation-info">
					<div class="zeko-conversation-user"><?php echo esc_html( $other_user->display_name ); ?></div>
					<div class="zeko-conversation-preview">
						<?php echo zeko_get_last_message_preview( $conversation->last_message_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Preview HTML escaped internally by zeko_get_last_message_preview() (esc_html on name and content). ?>
					</div>
				</div>
				<div class="zeko-conversation-meta">
					<?php if ( $unread_count > 0 ) : ?>
						<span class="zeko-unread-count"><?php echo esc_html( $unread_count ); ?></span>
					<?php endif; ?>
					<span class="zeko-conversation-time">
						<?php echo esc_html( zeko_get_conversation_time_ago( $conversation->last_message_date ) ); ?>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php else : ?>
		<div class="zeko-no-conversations">
			<p><?php esc_html_e( 'No conversations yet.', 'zeko' ); ?></p>
			<p><?php esc_html_e( 'Start a new conversation to connect with others!', 'zeko' ); ?></p>
		</div>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}

/**
 * Get last message preview
 *
 * @param mixed $message_id Message id.
 */
function zeko_get_last_message_preview( $message_id ) {
	if ( ! $message_id ) {
		return __( 'No messages', 'zeko' );
	}

	// Gate-and-delegate: zeko-core owns the message preview data read.
	if ( class_exists( 'Zeko_Core_Messaging' ) ) {
		$message = Zeko_Core_Messaging::get_instance()->get_message_preview( (int) $message_id );
	} else {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zeko_messages';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$message = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT message_content, sender_id FROM $table_name WHERE message_id = %d",
				$message_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	if ( $message ) {
		$preview = wp_trim_words( $message->message_content, 10, '...' );
		$sender  = get_userdata( $message->sender_id );
		$name    = $sender ? $sender->display_name : __( 'Unknown', 'zeko' );
		/* translators: 1: sender display name. 2: message preview */
		return sprintf( __( '%1$s: %2$s', 'zeko' ), esc_html( $name ), esc_html( $preview ) );
	}

	return __( 'No messages', 'zeko' );
}

/**
 * Get conversation time ago
 * Delegates to Zeko_Core_Helpers when zeko-core is active.
 *
 * @param mixed $date Date.
 */
function zeko_get_conversation_time_ago( $date ) {
	if ( class_exists( 'Zeko_Core_Helpers' ) ) {
		return Zeko_Core_Helpers::time_ago( (string) $date, '', true );
	}
	// Fallback. zeko_time_ago() exists only when zeko-core is loaded (it is.
	// defined in class-zeko-helpers.php), so guard against Core-less installs.
	// where calling it directly would fatal.
	if ( function_exists( 'zeko_time_ago' ) ) {
		return zeko_time_ago( $date, '', true );
	}
	// Minimal local formatter (Core-less standalone installs).
	if ( empty( $date ) ) {
		return '';
	}
	$diff = time() - strtotime( $date );
	if ( $diff < 60 ) {
		return __( 'just now', 'zeko' );
	}
	if ( $diff < 3600 ) {
		/* translators: %s: number of minutes */
		return sprintf( _n( '%s min ago', '%s mins ago', floor( $diff / 60 ), 'zeko' ), floor( $diff / 60 ) );
	}
	if ( $diff < 86400 ) {
		/* translators: %s: number of hours */
		return sprintf( _n( '%s hour ago', '%s hours ago', floor( $diff / 3600 ), 'zeko' ), floor( $diff / 3600 ) );
	}
	/* translators: %s: number of days */
	return sprintf( _n( '%s day ago', '%s days ago', floor( $diff / 86400 ), 'zeko' ), floor( $diff / 86400 ) );
}

/**
 * Get message inbox for dashboard widget
 */
function zeko_get_message_inbox() {
	$user_id      = get_current_user_id();
	$unread_count = zeko_get_unread_message_count( $user_id );

	ob_start();
	?>
	<div class="zeko-message-inbox-widget">
		<div class="zeko-widget-header">
			<h3 class="zeko-widget-title"><?php esc_html_e( 'Messages', 'zeko' ); ?></h3>
			<a href="<?php echo esc_url( home_url( '/messages/' ) ); ?>" class="zeko-view-all">
				<?php esc_html_e( 'View All', 'zeko' ); ?>
			</a>
		</div>
		<div class="zeko-widget-content">
			<?php if ( $unread_count > 0 ) : ?>
				<div class="zeko-unread-messages">
					<a href="<?php echo esc_url( home_url( '/messages/' ) ); ?>">
						<strong><?php echo esc_html( $unread_count ); ?></strong>
						<?php echo esc_html( _n( 'unread message', 'unread messages', $unread_count, 'zeko' ) ); ?>
					</a>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'No unread messages.', 'zeko' ); ?></p>
			<?php endif; ?>

			<div class="zeko-recent-conversations">
				<?php echo zeko_get_recent_conversations( $user_id, 3 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_recent_conversations() escapes values internally; previews escaped by zeko_get_last_message_preview(). ?>
			</div>

			<a href="<?php echo esc_url( home_url( '/messages/' ) ); ?>" class="btn btn-primary zeko-new-message-dashboard">
				<?php esc_html_e( 'New Message', 'zeko' ); ?>
			</a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Get recent conversations for dashboard widget
 *
 * @param mixed     $user_id User id.
 * @param int|float $limit Limit.
 */
function zeko_get_recent_conversations( $user_id, $limit = 3 ) {
	// Gate-and-delegate: zeko-core owns the conversations data read.
	if ( class_exists( 'Zeko_Core_Messaging' ) ) {
		$conversations = Zeko_Core_Messaging::get_instance()->get_conversations( (int) $user_id, (int) $limit );
	} else {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zeko_conversations';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name
			WHERE (user1_id = %d OR user2_id = %d)
			AND status = 'active'
			ORDER BY last_message_date DESC
			LIMIT %d",
				$user_id,
				$user_id,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	ob_start();
	?>
	<div class="zeko-recent-conversations-list">
		<?php if ( ! empty( $conversations ) ) : ?>
			<?php foreach ( $conversations as $conversation ) : ?>
				<?php
				$other_user_id = ( (int) $conversation->user1_id === $user_id ) ? $conversation->user2_id : $conversation->user1_id;
				$other_user    = get_userdata( $other_user_id );
				if ( ! $other_user ) {
					continue;
				}
				$unread_count = ( (int) $conversation->user1_id === $user_id ) ? $conversation->unread_count_user1 : $conversation->unread_count_user2;
				?>
				<div class="zeko-recent-conversation">
					<div class="zeko-conversation-avatar">
						<?php echo get_avatar( $other_user_id, 32 ); ?>
					</div>
					<div class="zeko-conversation-info">
						<div class="zeko-conversation-user"><?php echo esc_html( $other_user->display_name ); ?></div>
						<div class="zeko-conversation-preview">
							<?php echo zeko_get_last_message_preview( $conversation->last_message_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Preview HTML escaped internally by zeko_get_last_message_preview() (esc_html on name and content). ?>
						</div>
					</div>
					<?php if ( $unread_count > 0 ) : ?>
						<span class="zeko-unread-count"><?php echo esc_html( $unread_count ); ?></span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No recent conversations.', 'zeko' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Add messaging menu to admin bar
 *
 * @param mixed $admin_bar Admin bar.
 */
function zeko_add_messaging_menu_to_admin_bar( $admin_bar ) {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$unread_count = zeko_get_unread_message_count( get_current_user_id() );

	$admin_bar->add_menu(
		array(
			'id'    => 'zeko-messaging',
			'title' => __( 'Messages', 'zeko' ) . ( $unread_count > 0 ? ' <span class="zeko-adminbar-unread">' . $unread_count . '</span>' : '' ),
			'href'  => home_url( '/messages/' ),
			'meta'  => array(
				'title' => __( 'Messages', 'zeko' ),
			),
		)
	);
}

/**
 * Get unread message count for a user
 * Delegates to Zeko_Core_Messaging when zeko-core is active.
 */
if ( ! function_exists( 'zeko_get_unread_message_count' ) ) {
	/**
	 * Zeko get unread message count.
	 *
	 * @param mixed $user_id User id.
	 */
	function zeko_get_unread_message_count( $user_id ) {
		if ( class_exists( 'Zeko_Core_Messaging' ) ) {
			return Zeko_Core_Messaging::get_instance()->get_unread_count( (int) $user_id );
		}
		// Fallback: direct DB query.
		global $wpdb;
		$table_name = $wpdb->prefix . 'zeko_conversations';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CASE
            WHEN user1_id = %d THEN unread_count_user1
            WHEN user2_id = %d THEN unread_count_user2
            ELSE 0
        END) as unread_count
        FROM $table_name
        WHERE (user1_id = %d OR user2_id = %d)
        AND status = 'active'",
				$user_id,
				$user_id,
				$user_id,
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return $count ? (int) $count : 0;
	}
} // End if function_exists.

/**
 * Create or get conversation between two users
 * Delegates to Zeko_Core_Messaging when zeko-core is active.
 */
if ( ! function_exists( 'zeko_get_conversation' ) ) {
	/**
	 * Zeko get conversation.
	 *
	 * @param mixed $user_id1 User id1.
	 * @param mixed $user_id2 User id2.
	 */
	function zeko_get_conversation( $user_id1, $user_id2 ) {
		if ( class_exists( 'Zeko_Core_Messaging' ) ) {
			return Zeko_Core_Messaging::get_instance()->get_conversation( (int) $user_id1, (int) $user_id2 );
		}
		// Fallback: direct DB query.
		global $wpdb;
		$table_name = $wpdb->prefix . 'zeko_conversations';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		// Check if conversation already exists.
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name
        WHERE (user1_id = %d AND user2_id = %d)
        OR (user1_id = %d AND user2_id = %d)",
				$user_id1,
				$user_id2,
				$user_id2,
				$user_id1
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( $conversation ) {
			return $conversation->conversation_id;
		}

		// Create new conversation.
		$wpdb->insert(
			$table_name,
			array(
				'user1_id' => $user_id1,
				'user2_id' => $user_id2,
				'status'   => 'active',
			),
			array( '%d', '%d', '%s' )
		);

		return $wpdb->insert_id;
	}
} // End if function_exists.

/**
 * Send a message
 */
if ( ! function_exists( 'zeko_send_message' ) ) {
	/**
	 * Send a message
	 * Delegates to Zeko_Core_Messaging when zeko-core is active.
	 *
	 * @param mixed $sender_id Sender id.
	 * @param mixed $recipient_id Recipient id.
	 * @param mixed $message_content Message content.
	 */
	function zeko_send_message( $sender_id, $recipient_id, $message_content ) {
		if ( class_exists( 'Zeko_Core_Messaging' ) ) {
			return Zeko_Core_Messaging::get_instance()->send_message( (int) $sender_id, (int) $recipient_id, (string) $message_content );
		}
		// Fallback: direct DB insert.
		global $wpdb;

		// Get or create conversation.
		$conversation_id = zeko_get_conversation( $sender_id, $recipient_id );

		// Insert message.
		$wpdb->insert(
			$wpdb->prefix . 'zeko_messages',
			array(
				'conversation_id' => $conversation_id,
				'sender_id'       => $sender_id,
				'recipient_id'    => $recipient_id,
				'message_content' => $message_content,
				'message_date'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		$message_id = $wpdb->insert_id;

		// Update conversation.
		$wpdb->update(
			$wpdb->prefix . 'zeko_conversations',
			array(
				'last_message_id'    => $message_id,
				'last_message_date'  => current_time( 'mysql' ),
				'unread_count_user1' => ( $sender_id === $recipient_id ) ? 0 : 1,
				'unread_count_user2' => ( $recipient_id === $sender_id ) ? 0 : 1,
			),
			array( 'conversation_id' => $conversation_id ),
			array( '%d', '%s', '%d', '%d' ),
			array( '%d' )
		);

		// Log activity.
		zeko_log_user_activity(
			$sender_id,
			'message_sent',
			array(
				'recipient_id' => $recipient_id,
				'message_id'   => $message_id,
			)
		);

		return $message_id;
	}
}

/**
 * Get messages for a conversation
 *
 * @param mixed     $conversation_id Conversation id.
 * @param mixed     $user_id User id.
 * @param int|float $limit Limit.
 * @param int|float $offset Offset.
 */
function zeko_get_messages( $conversation_id, $user_id, $limit = 20, $offset = 0 ) {
	// Gate-and-delegate: zeko-core owns the messages data read + read-marking.
	if ( class_exists( 'Zeko_Core_Messaging' ) ) {
		return Zeko_Core_Messaging::get_instance()->get_messages( (int) $conversation_id, (int) $user_id, (int) $limit, (int) $offset );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_messages';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	$messages = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $table_name
        WHERE conversation_id = %d
        ORDER BY message_date DESC
        LIMIT %d OFFSET %d",
			$conversation_id,
			$limit,
			$offset
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	// Mark messages as read if they're for the current user.
	if ( ! empty( $messages ) ) {
		$wpdb->update(
			$table_name,
			array( 'is_read' => 1 ),
			array(
				'conversation_id' => $conversation_id,
				'recipient_id'    => $user_id,
				'is_read'         => 0,
			),
			array( '%d' ),
			array( '%d', '%d', '%d' )
		);

		// Update unread count.
		$conversation_table = $wpdb->prefix . 'zeko_conversations';
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $conversation_table WHERE conversation_id = %d",
				$conversation_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( $conversation ) {
			$unread_field = ( (int) $conversation->user1_id === $user_id ) ? 'unread_count_user1' : 'unread_count_user2';
			$wpdb->update(
				$conversation_table,
				array( $unread_field => 0 ),
				array( 'conversation_id' => $conversation_id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	return array_reverse( $messages ); // Return in chronological order.
}

/**
 * Get message data
 *
 * @param mixed $message_id Message id.
 */
function zeko_get_message_data( $message_id ) {
	// Gate-and-delegate: zeko-core owns the message data read.
	if ( class_exists( 'Zeko_Core_Messaging' ) ) {
		return Zeko_Core_Messaging::get_instance()->get_message( (int) $message_id );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_messages';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $table_name WHERE message_id = %d",
			$message_id
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
}

/**
 * Get message HTML
 *
 * @param mixed $message Message.
 * @param mixed $current_user_id Current user id.
 */
function zeko_get_message_html( $message, $current_user_id ) {
	$sender          = get_userdata( $message->sender_id );
	$sender_name     = $sender ? $sender->display_name : __( 'Unknown', 'zeko' );
	$is_current_user = ( (int) $message->sender_id === $current_user_id );

	ob_start();
	?>
	<div class="zeko-message <?php echo $is_current_user ? 'zeko-message-sent' : 'zeko-message-received'; ?>" data-message-id="<?php echo esc_attr( $message->message_id ); ?>">
		<?php if ( ! $is_current_user ) : ?>
			<div class="zeko-message-avatar">
				<?php echo get_avatar( $message->sender_id, 32 ); ?>
			</div>
		<?php endif; ?>

		<div class="zeko-message-content">
			<?php if ( ! $is_current_user ) : ?>
				<div class="zeko-message-sender"><?php echo esc_html( $sender_name ); ?></div>
			<?php endif; ?>

			<div class="zeko-message-text">
				<?php echo wp_kses_post( wpautop( esc_textarea( $message->message_content ) ) ); ?>
			</div>

			<div class="zeko-message-meta">
				<span class="zeko-message-time"><?php echo esc_html( zeko_get_message_time( $message->message_date ) ); ?></span>
				<?php if ( $is_current_user && $message->is_read ) : ?>
					<span class="zeko-message-status"><?php esc_html_e( 'Read', 'zeko' ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $is_current_user ) : ?>
			<div class="zeko-message-avatar">
				<?php echo get_avatar( $message->sender_id, 32 ); ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Get message time
 *
 * @param mixed $date Date.
 */
function zeko_get_message_time( $date ) {
	$timestamp = strtotime( $date );
	return gmdate( 'g:i a', $timestamp );
}

/**
 * AJAX handler for sending a message
 */
function zeko_ajax_send_message() {
	check_ajax_referer( 'zeko_messaging_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$sender_id       = get_current_user_id();
	$recipient_id    = isset( $_POST['recipient_id'] ) ? intval( $_POST['recipient_id'] ) : 0;
	$message_content = isset( $_POST['message_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message_content'] ) ) : '';

	if ( empty( $recipient_id ) || empty( $message_content ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid message data.', 'zeko' ) ) );
	}

	// Check if recipient exists.
	$recipient = get_userdata( $recipient_id );
	if ( ! $recipient ) {
		wp_send_json_error( array( 'message' => __( 'Recipient not found.', 'zeko' ) ) );
	}

	// Send message.
	$message_id = zeko_send_message( $sender_id, $recipient_id, $message_content );

	if ( $message_id ) {
		// Get the message data.
		$message = zeko_get_message_data( $message_id );

		wp_send_json_success(
			array(
				'message'      => __( 'Message sent successfully!', 'zeko' ),
				'message_id'   => $message_id,
				'message_html' => zeko_get_message_html( $message, $sender_id ),
			)
		);
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to send message.', 'zeko' ) ) );
	}
}
// When zeko-core is present, Zeko_Core_Messaging owns wp_ajax_zeko_send_message;.
// this fallback shell is kept for Core-less installs (row 2 gate-and-delegate).
if ( ! class_exists( 'Zeko_Core_Messaging' ) ) {
	add_action( 'wp_ajax_zeko_send_message', 'zeko_ajax_send_message' );
}

/**
 * AJAX handler for getting messages
 */
function zeko_ajax_get_messages() {
	check_ajax_referer( 'zeko_messaging_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$user_id         = get_current_user_id();
	$conversation_id = isset( $_POST['conversation_id'] ) ? intval( $_POST['conversation_id'] ) : 0;
	$limit           = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 20;
	$offset          = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;

	if ( empty( $conversation_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid conversation.', 'zeko' ) ) );
	}

	// Verify user has access to this conversation.
	$conversation = zeko_get_conversation_data( $conversation_id );
	if ( ! $conversation || ( (int) $conversation->user1_id !== $user_id && (int) $conversation->user2_id !== $user_id ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to view this conversation.', 'zeko' ) ) );
	}

	// Get messages.
	$messages = zeko_get_messages( $conversation_id, $user_id, $limit, $offset );

	ob_start();
	?>
	<div class="zeko-messages">
		<?php if ( ! empty( $messages ) ) : ?>
			<?php foreach ( $messages as $message ) : ?>
				<?php echo zeko_get_message_html( $message, $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_message_html() escapes values internally (esc_html/esc_url/esc_textarea). ?>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="zeko-no-messages">
				<?php esc_html_e( 'No messages yet. Start the conversation!', 'zeko' ); ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	$messages_html = ob_get_clean();

	wp_send_json_success(
		array(
			'messages_html' => $messages_html,
			'has_more'      => ( count( $messages ) >= $limit ),
			'next_offset'   => $offset + $limit,
		)
	);
}
// Owned by Zeko_Core_Messaging when present; fallback for Core-less installs.
if ( ! class_exists( 'Zeko_Core_Messaging' ) ) {
	add_action( 'wp_ajax_zeko_get_messages', 'zeko_ajax_get_messages' );
}

/**
 * Get conversation data
 *
 * @param mixed $conversation_id Conversation id.
 */
function zeko_get_conversation_data( $conversation_id ) {
	// Gate-and-delegate: zeko-core owns the conversation data read.
	if ( class_exists( 'Zeko_Core_Messaging' ) ) {
		return Zeko_Core_Messaging::get_instance()->get_conversation_data( (int) $conversation_id );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_conversations';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $table_name WHERE conversation_id = %d",
			$conversation_id
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
}

/**
 * AJAX handler for searching users
 */
function zeko_ajax_search_users() {
	check_ajax_referer( 'zeko_messaging_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$search_term     = isset( $_POST['search_term'] ) ? sanitize_text_field( wp_unslash( $_POST['search_term'] ) ) : '';
	$current_user_id = get_current_user_id();

	if ( empty( $search_term ) ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a search term.', 'zeko' ) ) );
	}

	// Search users.
	$users = get_users(
		array(
			'search'         => '*' . $search_term . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email', 'display_name' ),
			'exclude'        => array( $current_user_id ),
			'number'         => 10,
		)
	);

	ob_start();
	?>
	<div class="zeko-user-search-results">
		<?php if ( ! empty( $users ) ) : ?>
			<?php foreach ( $users as $user ) : ?>
				<div class="zeko-user-result" data-user-id="<?php echo esc_attr( $user->ID ); ?>">
					<div class="zeko-user-avatar">
						<?php echo get_avatar( $user->ID, 40 ); ?>
					</div>
					<div class="zeko-user-info">
						<div class="zeko-user-name"><?php echo esc_html( $user->display_name ); ?></div>
						<div class="zeko-user-username">@<?php echo esc_html( $user->user_login ); ?></div>
					</div>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="zeko-no-results">
				<?php esc_html_e( 'No users found.', 'zeko' ); ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	$results_html = ob_get_clean();

	wp_send_json_success(
		array(
			'results_html' => $results_html,
		)
	);
}
// Owned by Zeko_Core_Messaging when present; fallback for Core-less installs.
if ( ! class_exists( 'Zeko_Core_Messaging' ) ) {
	add_action( 'wp_ajax_zeko_search_users', 'zeko_ajax_search_users' );
}

/**
 * AJAX handler for starting new conversation
 */
function zeko_ajax_start_conversation() {
	check_ajax_referer( 'zeko_messaging_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$sender_id       = get_current_user_id();
	$recipient_id    = isset( $_POST['recipient_id'] ) ? intval( $_POST['recipient_id'] ) : 0;
	$message_content = isset( $_POST['message_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message_content'] ) ) : '';

	if ( empty( $recipient_id ) || empty( $message_content ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid message data.', 'zeko' ) ) );
	}

	// Check if recipient exists.
	$recipient = get_userdata( $recipient_id );
	if ( ! $recipient ) {
		wp_send_json_error( array( 'message' => __( 'Recipient not found.', 'zeko' ) ) );
	}

	// Send message (this will create conversation if needed).
	$message_id = zeko_send_message( $sender_id, $recipient_id, $message_content );

	if ( $message_id ) {
		// Get conversation ID.
		$conversation_id = zeko_get_conversation( $sender_id, $recipient_id );

		wp_send_json_success(
			array(
				'message'         => __( 'Message sent successfully!', 'zeko' ),
				'conversation_id' => $conversation_id,
			)
		);
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to send message.', 'zeko' ) ) );
	}
}
// Owned by Zeko_Core_Messaging when present; fallback for Core-less installs.
if ( ! class_exists( 'Zeko_Core_Messaging' ) ) {
	add_action( 'wp_ajax_zeko_start_conversation', 'zeko_ajax_start_conversation' );
}

/**
 * AJAX handler for checking new messages
 */
function zeko_ajax_check_new_messages() {
	check_ajax_referer( 'zeko_messaging_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$conversation_id = isset( $_POST['conversation_id'] ) ? intval( $_POST['conversation_id'] ) : 0;
	$user_id         = get_current_user_id();
	$last_message_id = isset( $_POST['last_message_id'] ) ? intval( $_POST['last_message_id'] ) : 0;

	if ( empty( $conversation_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid conversation.', 'zeko' ) ) );
	}

	// Verify user has access to this conversation.
	$conversation = zeko_get_conversation_data( $conversation_id );
	if ( ! $conversation || ( (int) $conversation->user1_id !== $user_id && (int) $conversation->user2_id !== $user_id ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to view this conversation.', 'zeko' ) ) );
	}

	// Get new messages since last_message_id.
	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_messages';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	$new_messages = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $table_name
        WHERE conversation_id = %d
        AND message_id > %d
        ORDER BY message_date ASC",
			$conversation_id,
			$last_message_id
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	if ( ! empty( $new_messages ) ) {
		// Mark new messages as read.
		$message_ids = wp_list_pluck( $new_messages, 'message_id' );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table_name SET is_read = 1
            WHERE message_id IN (" . implode( ',', array_fill( 0, count( $message_ids ), '%d' ) ) . ')',
				$message_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		// Update unread count.
		$unread_field       = ( (int) $conversation->user1_id === $user_id ) ? 'unread_count_user1' : 'unread_count_user2';
		$conversation_table = $wpdb->prefix . 'zeko_conversations';
		$wpdb->update(
			$conversation_table,
			array( $unread_field => 0 ),
			array( 'conversation_id' => $conversation_id ),
			array( '%d' ),
			array( '%d' )
		);

		// Generate HTML for new messages.
		ob_start();
		foreach ( $new_messages as $message ) {
			echo zeko_get_message_html( $message, $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_message_html() escapes values internally (esc_html/esc_url/esc_textarea).
		}
		$messages_html = ob_get_clean();

		wp_send_json_success(
			array(
				'new_messages'  => true,
				'messages_html' => $messages_html,
				'new_count'     => count( $new_messages ),
			)
		);
	} else {
		wp_send_json_success(
			array(
				'new_messages' => false,
				'new_count'    => 0,
			)
		);
	}
}
// Owned by Zeko_Core_Messaging when present; fallback for Core-less installs.
if ( ! class_exists( 'Zeko_Core_Messaging' ) ) {
	add_action( 'wp_ajax_zeko_check_new_messages', 'zeko_ajax_check_new_messages' );
}

/**
 * AJAX handler for getting conversation data
 */
function zeko_ajax_get_conversation_data() {
	check_ajax_referer( 'zeko_messaging_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$conversation_id = isset( $_POST['conversation_id'] ) ? intval( $_POST['conversation_id'] ) : 0;
	$user_id         = get_current_user_id();

	if ( empty( $conversation_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid conversation.', 'zeko' ) ) );
	}

	// Get conversation data.
	$conversation = zeko_get_conversation_data( $conversation_id );

	if ( ! $conversation ) {
		wp_send_json_error( array( 'message' => __( 'Conversation not found.', 'zeko' ) ) );
	}

	// Verify user has access to this conversation.
	if ( (int) $conversation->user1_id !== $user_id && (int) $conversation->user2_id !== $user_id ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to view this conversation.', 'zeko' ) ) );
	}

	// Determine the other user.
	$other_user_id = ( (int) $conversation->user1_id === $user_id ) ? $conversation->user2_id : $conversation->user1_id;
	$other_user    = get_userdata( $other_user_id );

	if ( ! $other_user ) {
		wp_send_json_error( array( 'message' => __( 'User not found.', 'zeko' ) ) );
	}

	wp_send_json_success(
		array(
			'conversation' => $conversation,
			'other_user'   => array(
				'ID'           => $other_user->ID,
				'display_name' => $other_user->display_name,
				'user_login'   => $other_user->user_login,
				'user_email'   => $other_user->user_email,
			),
		)
	);
}
// Owned by Zeko_Core_Messaging when present; fallback for Core-less installs.
if ( ! class_exists( 'Zeko_Core_Messaging' ) ) {
	add_action( 'wp_ajax_zeko_get_conversation_data', 'zeko_ajax_get_conversation_data' );
}

/**
 * AJAX handler for getting unread count
 */
function zeko_ajax_get_unread_count() {
	check_ajax_referer( 'zeko_messaging_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$user_id      = get_current_user_id();
	$unread_count = zeko_get_unread_message_count( $user_id );

	wp_send_json_success(
		array(
			'unread_count' => $unread_count,
		)
	);
}
// Owned by Zeko_Core_Messaging when present; fallback for Core-less installs.
if ( ! class_exists( 'Zeko_Core_Messaging' ) ) {
	add_action( 'wp_ajax_zeko_get_unread_count', 'zeko_ajax_get_unread_count' );
}

/**
 * AJAX handler for logging user activity
 */
function zeko_ajax_log_user_activity() {
	check_ajax_referer( 'zeko_messaging_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$current_user_id = get_current_user_id();
	$user_id         = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
	$activity_type   = isset( $_POST['activity_type'] ) ? sanitize_text_field( wp_unslash( $_POST['activity_type'] ) ) : '';
	$activity_data   = isset( $_POST['activity_data'] ) && is_array( $_POST['activity_data'] ) ? wp_unslash( $_POST['activity_data'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nested array; sanitized wholesale below by zeko_sanitize_activity_meta().

	// Only the logged-in user may log their own activity.
	if ( ! $user_id || $user_id !== $current_user_id || empty( $activity_type ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid activity data.', 'zeko' ) ) );
	}

	$clean_meta = function_exists( 'zeko_sanitize_activity_meta' ) ? zeko_sanitize_activity_meta( $activity_data ) : array();

	zeko_log_user_activity( $user_id, $activity_type, $clean_meta );

	wp_send_json_success(
		array(
			'message' => __( 'Activity logged successfully!', 'zeko' ),
		)
	);
}
// Owned by Zeko_Core_Activity when present; fallback for Core-less installs.
if ( ! class_exists( 'Zeko_Core_Activity' ) ) {
	add_action( 'wp_ajax_zeko_log_user_activity', 'zeko_ajax_log_user_activity' );
}

/**
 * AJAX handler for getting conversations list
 */
function zeko_ajax_get_conversations() {
	check_ajax_referer( 'zeko_messaging_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$user_id = get_current_user_id();

	ob_start();
	echo zeko_get_conversations_list( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_conversations_list() escapes values internally (esc_html/esc_url); previews escaped by zeko_get_last_message_preview().
	$conversations_html = ob_get_clean();

	wp_send_json_success(
		array(
			'conversations_html' => $conversations_html,
		)
	);
}
// Owned by Zeko_Core_Messaging when present; fallback for Core-less installs.
if ( ! class_exists( 'Zeko_Core_Messaging' ) ) {
	add_action( 'wp_ajax_zeko_get_conversations', 'zeko_ajax_get_conversations' );
}
