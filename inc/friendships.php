<?php
/**
 * Friendship connections functionality
 *
 * @package Zeko
 */

/**
 * Create custom friendship database tables
 */
function zeko_create_friendship_tables() {
	// Table creation moved to Zeko Core (app schema module 'friendships').
	if ( class_exists( 'Zeko_Core_DB' ) ) {
		Zeko_Core_DB::get_instance()->ensure_module( 'friendships' );
		return;
	}

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Friendship connections table.
	$table_name = $wpdb->prefix . 'zeko_friendships';
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
		$sql = "CREATE TABLE $table_name (
            friendship_id bigint(20) NOT NULL AUTO_INCREMENT,
            initiator_id bigint(20) NOT NULL,
            friend_id bigint(20) NOT NULL,
            status varchar(20) DEFAULT 'pending' COMMENT 'pending, accepted, rejected, blocked',
            date_created datetime NOT NULL,
            date_updated datetime DEFAULT NULL,
            PRIMARY KEY (friendship_id),
            UNIQUE KEY initiator_friend (initiator_id, friend_id),
            KEY initiator_id (initiator_id),
            KEY friend_id (friend_id)
        ) $charset_collate;";
		zeko_safe_db_delta( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}
}

/**
 * Initialize friendship system
 */
function zeko_init_friendships() {

	// Ensure tables exist (throttled safety net; admin migration owns this).
	zeko_theme_init_schema();

	// Add friendship related shortcodes.
	add_shortcode( 'zeko_friend_list', 'zeko_friend_list_shortcode' );
	add_shortcode( 'zeko_friend_requests', 'zeko_friend_requests_shortcode' );
	add_shortcode( 'zeko_add_friend_button', 'zeko_add_friend_button_shortcode' );
	add_shortcode( 'zeko_mutual_friends', 'zeko_mutual_friends_shortcode' );
	add_shortcode( 'zeko_friend_suggestions', 'zeko_friend_suggestions_shortcode' );
	add_shortcode( 'zeko_friends_form', 'zeko_friend_list_shortcode' );

	// Enqueue friendship scripts and styles.
	add_action( 'wp_enqueue_scripts', 'zeko_enqueue_friendship_scripts' );
	add_action( 'admin_enqueue_scripts', 'zeko_enqueue_friendship_scripts' ); // For admin/dashboard.

	// Create friends page if it doesn't exist - call directly like messaging system (throttled).
	if ( zeko_page_setup_due( 'friends' ) ) {
		zeko_create_friends_page();
	}
}
add_action( 'init', 'zeko_init_friendships' );

/**
 * Enqueue friendship scripts and styles
 */
function zeko_enqueue_friendship_scripts() {
	// Load on relevant pages (profile, dashboard, friends page).
	// Also load when friend request shortcode is present.
	$load_scripts = zeko_is_profile_page() || zeko_is_dashboard_page() || zeko_is_friends_page();

	if ( $load_scripts || has_shortcode( get_the_content(), 'zeko_friend_requests' ) ) {

		$theme_dir = get_template_directory();
		wp_enqueue_script( 'zeko-friendships', get_template_directory_uri() . '/assets/js/friendships.js', array( 'jquery' ), file_exists( $theme_dir . '/assets/js/friendships.js' ) ? filemtime( $theme_dir . '/assets/js/friendships.js' ) : '1.0.0', true );
		wp_enqueue_style( 'zeko-friendships', get_template_directory_uri() . '/assets/css/friendships.css', array(), file_exists( $theme_dir . '/assets/css/friendships.css' ) ? filemtime( $theme_dir . '/assets/css/friendships.css' ) : '1.0.0' );

		$localized_data = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'zeko_friendship_nonce' ),
			'i18n'     => array(
				'saving'           => __( 'Processing...', 'zeko' ),
				'request_sent'     => __( 'Request Sent', 'zeko' ),
				'request_accepted' => __( 'Friend request accepted!', 'zeko' ),
				'request_rejected' => __( 'Friend request rejected!', 'zeko' ),
				'friend_removed'   => __( 'Friend removed.', 'zeko' ),
				'error'            => __( 'Error: ', 'zeko' ),
				'confirm_remove'   => __( 'Are you sure you want to remove this friend?', 'zeko' ),
			),
		);

		wp_localize_script( 'zeko-friendships', 'zekoFriendshipData', $localized_data );
	}
}

/**
 * Check if current page is the friends page
 */
function zeko_is_friends_page() {
	$friends_page_id = get_option( 'zeko_friends_page_id' );
	$request_uri     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return is_page( $friends_page_id ) || strpos( $request_uri, '/friends/' ) !== false;
}

/**
 * Create friends page if it doesn't exist
 */
function zeko_create_friends_page() {

	// Check if friends page already exists.
	$page = get_page_by_path( 'friends' );

	if ( $page ) {
		$content     = $page->post_content;
		$new_content = '[zeko_friend_list][zeko_friend_suggestions][zeko_friend_requests]';
		if ( empty( $content ) || strpos( $content, '[zeko_friend_list]' ) === false || strpos( $content, '[zeko_friend_suggestions]' ) === false || strpos( $content, '[zeko_friend_requests]' ) === false ) {
			wp_update_post(
				array(
					'ID'           => $page->ID,
					'post_content' => $new_content,
				)
			);
		}
		update_option( 'zeko_friends_page_id', $page->ID );
	} else {
		// Create new friends page.
		$page_id = wp_insert_post(
			array(
				'post_title'     => __( 'My Friends', 'zeko' ),
				'post_name'      => 'friends',
				'post_content'   => '[zeko_friend_list]',
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);

		if ( $page_id ) {
			update_option( 'zeko_friends_page_id', $page_id );
			// Flush rewrite rules to ensure page is accessible.
			flush_rewrite_rules();
		}
	}
}

/**
 * Add friend button shortcode.
 *
 * @param mixed $atts Atts.
 */
function zeko_add_friend_button_shortcode( $atts ) {
	if ( ! is_user_logged_in() ) {
		return ''; // Only show for logged in users.
	}

	$atts = shortcode_atts(
		array(
			'user_id' => 0, // The ID of the user to add as a friend.
		),
		$atts
	);

	$target_user_id  = absint( $atts['user_id'] );
	$current_user_id = get_current_user_id();

	if ( 0 === $target_user_id || $target_user_id === $current_user_id ) {
		return ''; // Don't show button for self or invalid user.
	}

	// Check friendship status.
	$status = zeko_get_friendship_status( $current_user_id, $target_user_id );

	ob_start();
	?>
	<div class="zeko-friendship-actions" data-user-id="<?php echo esc_attr( $target_user_id ); ?>">
		<?php if ( 'not_friends' === $status ) : ?>
			<button class="btn btn-primary zeko-add-friend-btn">
				<?php esc_html_e( 'Add Friend', 'zeko' ); ?>
			</button>
		<?php elseif ( 'pending_sent' === $status ) : ?>
			<button class="btn btn-secondary" disabled>
				<?php esc_html_e( 'Request Sent', 'zeko' ); ?>
			</button>
		<?php elseif ( 'pending_received' === $status ) : ?>
			<button class="btn btn-primary zeko-accept-friend-btn">
				<?php esc_html_e( 'Accept Request', 'zeko' ); ?>
			</button>
			<button class="btn btn-outline zeko-reject-friend-btn">
				<?php esc_html_e( 'Reject Request', 'zeko' ); ?>
			</button>
		<?php elseif ( 'accepted' === $status ) : ?>
			<button class="btn btn-outline zeko-remove-friend-btn">
				<?php esc_html_e( 'Remove Friend', 'zeko' ); ?>
			</button>
		<?php elseif ( 'blocked' === $status ) : ?>
			<button class="btn btn-danger" disabled>
				<?php esc_html_e( 'Blocked', 'zeko' ); ?>
			</button>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Get friendship status between two users
 * Returns 'not_friends', 'pending_sent', 'pending_received', 'accepted', 'blocked'
 *
 * @param mixed $user_id1 User id1.
 * @param mixed $user_id2 User id2.
 */
function zeko_get_friendship_status( $user_id1, $user_id2 ) {
	// Routes to Zeko Core data layer (#46); fallback for Core-less installs.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		return Zeko_Core_App_Data::get_friendship_status( (int) $user_id1, (int) $user_id2 );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_friendships';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	// Check if user1 sent request to user2.
	$sent_request = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT status FROM $table_name WHERE initiator_id = %d AND friend_id = %d",
			$user_id1,
			$user_id2
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( $sent_request ) {
		if ( 'pending' === $sent_request->status ) {
			return 'pending_sent';
		}
		return $sent_request->status; // 'accepted' or 'blocked'.
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	// Check if user2 sent request to user1.
	$received_request = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT status FROM $table_name WHERE initiator_id = %d AND friend_id = %d",
			$user_id2,
			$user_id1
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( $received_request ) {
		if ( 'pending' === $received_request->status ) {
			return 'pending_received';
		}
		return $received_request->status; // 'accepted' or 'blocked'.
	}

	return 'not_friends';
}

/**
 * Send a friend request
 *
 * @param mixed $initiator_id Initiator id.
 * @param mixed $friend_id Friend id.
 */
function zeko_send_friend_request( $initiator_id, $friend_id ) {
	// Routes to Zeko Core data layer (#46); fallback for Core-less installs.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		return Zeko_Core_App_Data::send_friend_request( (int) $initiator_id, (int) $friend_id );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_friendships';

	// Prevent self-friending.
	if ( $initiator_id === $friend_id ) {
		return new WP_Error( 'zeko_friendship_error', __( 'You cannot send a friend request to yourself.', 'zeko' ) );
	}

	// Check if friendship already exists or is pending.
	$existing = zeko_get_friendship_status( $initiator_id, $friend_id );
	if ( 'not_friends' !== $existing ) {
		return new WP_Error( 'zeko_friendship_error', __( 'Friendship request already exists or users are already friends/blocked.', 'zeko' ) );
	}

	$result = $wpdb->insert(
		$table_name,
		array(
			'initiator_id' => $initiator_id,
			'friend_id'    => $friend_id,
			'status'       => 'pending',
			'date_created' => current_time( 'mysql' ),
			'date_updated' => current_time( 'mysql' ),
		),
		array( '%d', '%d', '%s', '%s', '%s' )
	);

	if ( $result ) {
		zeko_log_user_activity( $initiator_id, 'friendship_request_sent', array( 'friend_id' => $friend_id ) );
		do_action( 'zeko_friendship_sent', (int) $initiator_id, (int) $friend_id );
		return true;
	}
	return new WP_Error( 'db_error', __( 'Failed to send friend request.', 'zeko' ) );
}

/**
 * Accept a friend request
 *
 * @param mixed $initiator_id Initiator id.
 * @param mixed $friend_id Friend id.
 */
function zeko_accept_friend_request( $initiator_id, $friend_id ) {
	// Routes to Zeko Core data layer (#46); fallback for Core-less installs.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		return Zeko_Core_App_Data::accept_friend_request( (int) $initiator_id, (int) $friend_id );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_friendships';

	// Find the pending request (friend_id is the initiator, initiator_id is the receiver).
	$result = $wpdb->update(
		$table_name,
		array(
			'status'       => 'accepted',
			'date_updated' => current_time( 'mysql' ),
		),
		array(
			'initiator_id' => $friend_id, // The one who sent the request.
			'friend_id'    => $initiator_id, // The one accepting.
			'status'       => 'pending',
		),
		array( '%s', '%s' ),
		array( '%d', '%d', '%s' )
	);

	if ( $result ) {
		zeko_log_user_activity( $initiator_id, 'friendship_accepted', array( 'friend_id' => $friend_id ) );
		do_action( 'zeko_friendship_accepted', (int) $initiator_id, (int) $friend_id );
		return true;
	}
	return new WP_Error( 'db_error', __( 'Failed to accept friend request.', 'zeko' ) );
}

/**
 * Reject a friend request
 *
 * @param mixed $initiator_id Initiator id.
 * @param mixed $friend_id Friend id.
 */
function zeko_reject_friend_request( $initiator_id, $friend_id ) {
	// Routes to Zeko Core data layer (#46); fallback for Core-less installs.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		return Zeko_Core_App_Data::reject_friend_request( (int) $initiator_id, (int) $friend_id );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_friendships';

	// Delete the pending request.
	$result = $wpdb->delete(
		$table_name,
		array(
			'initiator_id' => $friend_id, // The one who sent the request.
			'friend_id'    => $initiator_id, // The one rejecting.
			'status'       => 'pending',
		),
		array( '%d', '%d', '%s' )
	);

	if ( $result ) {
		zeko_log_user_activity( $initiator_id, 'friendship_rejected', array( 'friend_id' => $friend_id ) );
		return true;
	}
	return new WP_Error( 'db_error', __( 'Failed to reject friend request.', 'zeko' ) );
}

/**
 * Remove a friend
 *
 * @param mixed $user_id1 User id1.
 * @param mixed $user_id2 User id2.
 */
function zeko_remove_friend( $user_id1, $user_id2 ) {
	// Routes to Zeko Core data layer (#46); fallback for Core-less installs.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		return Zeko_Core_App_Data::remove_friend( (int) $user_id1, (int) $user_id2 );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_friendships';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	// Delete any entry where they are friends (either direction).
	$result = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $table_name WHERE 
        (initiator_id = %d AND friend_id = %d AND status = 'accepted') OR 
        (initiator_id = %d AND friend_id = %d AND status = 'accepted')",
			$user_id1,
			$user_id2,
			$user_id2,
			$user_id1
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	if ( $result ) {
		zeko_log_user_activity( $user_id1, 'friend_removed', array( 'friend_id' => $user_id2 ) );
		return true;
	}
	return new WP_Error( 'db_error', __( 'Failed to remove friend.', 'zeko' ) );
}

/**
 * Check if two users are friends
 *
 * @param mixed $user_id1 User id1.
 * @param mixed $user_id2 User id2.
 */
function zeko_are_friends( $user_id1, $user_id2 ) {
	// Routes to Zeko Core data layer (#46); fallback for Core-less installs.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		return Zeko_Core_App_Data::are_friends( (int) $user_id1, (int) $user_id2 );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_friendships';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	$is_friend = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE 
        ((initiator_id = %d AND friend_id = %d) OR (initiator_id = %d AND friend_id = %d)) 
        AND status = 'accepted'",
			$user_id1,
			$user_id2,
			$user_id2,
			$user_id1
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	return (bool) $is_friend;
}

/**
 * Get a user's friends
 *
 * @param mixed $user_id User id.
 */
function zeko_get_friends( $user_id ) {
	// Routes to Zeko Core data layer (#46); fallback for Core-less installs.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		return Zeko_Core_App_Data::get_friends( (int) $user_id );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_friendships';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	$friends = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT initiator_id, friend_id FROM $table_name WHERE
        (initiator_id = %d OR friend_id = %d) AND status = 'accepted'",
			$user_id,
			$user_id
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	$friend_ids = array();
	foreach ( $friends as $friendship ) {
		if ( (int) $friendship->initiator_id === $user_id ) {
			$friend_ids[] = $friendship->friend_id;
		} else {
			$friend_ids[] = $friendship->initiator_id;
		}
	}
	return array_unique( $friend_ids );
}

/**
 * Get mutual friends between two users
 *
 * @param mixed $user_id1 User id1.
 * @param mixed $user_id2 User id2.
 */
function zeko_get_mutual_friends( $user_id1, $user_id2 ) {
	// Routes to Zeko Core data layer (#46); fallback for Core-less installs.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		return Zeko_Core_App_Data::get_mutual_friends( (int) $user_id1, (int) $user_id2 );
	}

	$friends1 = zeko_get_friends( $user_id1 );
	$friends2 = zeko_get_friends( $user_id2 );

	return array_intersect( $friends1, $friends2 );
}

/**
 * Get friend suggestions for a user (friends of friends)
 *
 * @param mixed     $user_id User id.
 * @param int|float $limit Limit.
 */
function zeko_get_friend_suggestions( $user_id, $limit = 5 ) {
	// Routes to Zeko Core data layer (#46); fallback for Core-less installs.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		return Zeko_Core_App_Data::get_friend_suggestions( (int) $user_id, (int) $limit );
	}

	$friends = zeko_get_friends( $user_id );

	// Get friends of friends.
	$suggestions = array();

	if ( ! empty( $friends ) ) {
		foreach ( $friends as $friend_id ) {
			$friends_of_friend = zeko_get_friends( $friend_id );
			foreach ( $friends_of_friend as $suggestion ) {
				// Don't suggest the user themselves or their existing friends.
				if ( $suggestion !== $user_id && ! in_array( $suggestion, $friends ) ) {
					$suggestions[ $suggestion ] = ( $suggestions[ $suggestion ] ?? 0 ) + 1;
				}
			}
		}
	}

	// If no friends, suggest other users (excluding self).
	if ( empty( $suggestions ) ) {
		$all_users = get_users(
			array(
				'exclude' => array( $user_id ),
				'fields'  => 'IDs',
				'number'  => $limit * 2, // Get more to filter.
			)
		);

		foreach ( $all_users as $potential_suggestion ) {
			if ( ! in_array( $potential_suggestion, $friends ) && $potential_suggestion !== $user_id ) {
				$suggestions[ $potential_suggestion ] = 0; // No mutual friends.
			}
		}
	}

	// Sort by number of mutual friends (descending).
	arsort( $suggestions );

	// Return top suggestions.
	return array_slice( array_keys( $suggestions ), 0, $limit );
}

/**
 * Get friendship status display for profile pages
 *
 * @param mixed $profile_user_id Profile user id.
 */
function zeko_get_profile_friendship_status( $profile_user_id ) {
	if ( ! is_user_logged_in() || get_current_user_id() === $profile_user_id ) {
		return '';
	}

	$current_user_id = get_current_user_id();
	$status          = zeko_get_friendship_status( $current_user_id, $profile_user_id );
	$mutual_friends  = zeko_get_mutual_friends( $current_user_id, $profile_user_id );
	$mutual_count    = count( $mutual_friends );

	ob_start();
	?>
	<div class="zeko-profile-friendship-status">
		<?php if ( 'accepted' === $status ) : ?>
			<div class="zeko-friend-indicator">
				<span class="zeko-friend-badge"><?php esc_html_e( '✓ Friend', 'zeko' ); ?></span>
				<?php if ( $mutual_count > 0 ) : ?>
<span class="zeko-mutual-friends-badge">
						<?php echo esc_html( $mutual_count ); ?> <?php echo esc_html( _n( 'mutual friend', 'mutual friends', $mutual_count, 'zeko' ) ); ?>
					</span>
				<?php endif; ?>
			</div>
			<div class="zeko-friend-actions">
				<?php echo zeko_add_friend_button_shortcode( array( 'user_id' => $profile_user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode returns pre-escaped button markup (esc_html_e/esc_attr). ?>
			</div>
		<?php elseif ( 'pending_sent' === $status ) : ?>
			<div class="zeko-friend-indicator">
				<span class="zeko-pending-badge"><?php esc_html_e( '⏳ Friend Request Sent', 'zeko' ); ?></span>
			</div>
		<?php elseif ( 'pending_received' === $status ) : ?>
			<div class="zeko-friend-indicator">
				<span class="zeko-request-badge"><?php esc_html_e( '📩 Friend Request Received', 'zeko' ); ?></span>
			</div>
			<div class="zeko-friend-actions">
				<?php echo zeko_add_friend_button_shortcode( array( 'user_id' => $profile_user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode returns pre-escaped button markup (esc_html_e/esc_attr). ?>
			</div>
		<?php elseif ( $mutual_count > 0 ) : ?>
			<div class="zeko-friend-indicator">
<span class="zeko-mutual-badge">
					<?php echo esc_html( $mutual_count ); ?> <?php echo esc_html( _n( 'mutual friend', 'mutual friends', $mutual_count, 'zeko' ) ); ?>
				</span>
			</div>
			<div class="zeko-friend-actions">
				<?php echo zeko_add_friend_button_shortcode( array( 'user_id' => $profile_user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode returns pre-escaped button markup (esc_html_e/esc_attr). ?>
			</div>
		<?php else : ?>
			<div class="zeko-friend-actions">
				<?php echo zeko_add_friend_button_shortcode( array( 'user_id' => $profile_user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode returns pre-escaped button markup (esc_html_e/esc_attr). ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Shortcode for profile friendship status
 *
 * @param mixed $atts Atts.
 */
function zeko_profile_friendship_status_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'user_id' => 0,
		),
		$atts
	);

	$user_id = absint( $atts['user_id'] );
	if ( 0 === $user_id ) {
		return '';
	}

	return zeko_get_profile_friendship_status( $user_id );
}
add_shortcode( 'zeko_profile_friendship_status', 'zeko_profile_friendship_status_shortcode' );

/**
 * Get pending friend requests for a user
 *
 * @param mixed $user_id User id.
 */
function zeko_get_pending_friend_requests( $user_id ) {
	// Routes to Zeko Core data layer (#46); fallback for Core-less installs.
	if ( class_exists( 'Zeko_Core_App_Data' ) ) {
		return Zeko_Core_App_Data::get_pending_friend_requests( (int) $user_id );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'zeko_friendships';

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	$requests = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT initiator_id FROM $table_name WHERE friend_id = %d AND status = 'pending'",
			$user_id
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	return array_map(
		function ( $req ) {
			return $req->initiator_id;
		},
		$requests
	);
}

/**
 * Friend list shortcode
 *
 * @param mixed $atts Atts.
 */
function zeko_friend_list_shortcode( $atts ) {

	if ( ! is_user_logged_in() ) {
		return '<p class="zeko-not-logged-in">' . __( 'You must be logged in to view your friends.', 'zeko' ) . '</p>';
	}

	$atts = shortcode_atts(
		array(
			'user_id'  => get_current_user_id(),
			'per_page' => 10,
		),
		$atts
	);

	$target_user_id = absint( $atts['user_id'] );

	$friends = zeko_get_friends( $target_user_id );

	if ( empty( $friends ) ) {
		return '<p class="zeko-no-friends">' . __( 'No friends found.', 'zeko' ) . '</p>';
	}

	ob_start();
	?>
	<div class="zeko-friend-list-container">
		<h3><?php esc_html_e( 'My Friends', 'zeko' ); ?></h3>
		<div class="zeko-friends-stats">
			<span class="zeko-friend-count"><?php echo count( $friends ); ?> <?php esc_html_e( 'friends', 'zeko' ); ?></span>
		</div>
		<ul class="zeko-friend-list">
			<?php
			foreach ( $friends as $friend_id ) :
				$friend_data = get_userdata( $friend_id );
				if ( $friend_data ) :
					// Get mutual friends count.
					$mutual_friends = zeko_get_mutual_friends( $target_user_id, $friend_id );
					$mutual_count   = count( $mutual_friends );
					?>
				<li class="zeko-friend-item">
					<a href="<?php echo esc_url( zeko_get_user_profile_url( $friend_id ) ); ?>" class="zeko-friend-avatar">
						<?php echo zeko_get_user_avatar( $friend_id, 50 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_user_avatar() returns pre-escaped get_avatar() markup. ?>
					</a>
					<div class="zeko-friend-info">
						<a href="<?php echo esc_url( zeko_get_user_profile_url( $friend_id ) ); ?>" class="zeko-friend-name">
							<?php echo esc_html( $friend_data->display_name ); ?>
						</a>
						<span class="zeko-friend-username">@<?php echo esc_html( $friend_data->user_login ); ?></span>
						<?php if ( $mutual_count > 0 ) : ?>
<span class="zeko-mutual-friends">
								<?php echo esc_html( $mutual_count ); ?> <?php echo esc_html( _n( 'mutual friend', 'mutual friends', $mutual_count, 'zeko' ) ); ?>
							</span>
						<?php endif; ?>
					</div>
					<?php if ( get_current_user_id() === $target_user_id ) : // Only show remove button for current user's own list. ?>
						<div class="zeko-friend-actions">
							<?php echo zeko_add_friend_button_shortcode( array( 'user_id' => $friend_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode returns pre-escaped button markup (esc_html_e/esc_attr). ?>
						</div>
					<?php endif; ?>
				</li>
					<?php
				endif;
			endforeach;
			?>
		</ul>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Mutual friends shortcode
 *
 * @param mixed $atts Atts.
 */
function zeko_mutual_friends_shortcode( $atts ) {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'user_id' => 0,
		),
		$atts
	);

	$target_user_id = absint( $atts['user_id'] );
	if ( 0 === $target_user_id ) {
		return '';
	}

	$current_user_id = get_current_user_id();
	$mutual_friends  = zeko_get_mutual_friends( $current_user_id, $target_user_id );

	if ( empty( $mutual_friends ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="zeko-mutual-friends-container">
		<h4><?php echo esc_html( count( $mutual_friends ) ); ?> <?php echo esc_html( _n( 'Mutual Friend', 'Mutual Friends', count( $mutual_friends ), 'zeko' ) ); ?></h4>
		<ul class="zeko-mutual-friends-list">
			<?php
			foreach ( $mutual_friends as $friend_id ) :
				$friend_data = get_userdata( $friend_id );
				if ( $friend_data ) :
					?>
				<li class="zeko-mutual-friend-item">
					<a href="<?php echo esc_url( zeko_get_user_profile_url( $friend_id ) ); ?>" class="zeko-friend-avatar">
						<?php echo zeko_get_user_avatar( $friend_id, 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_get_user_avatar() returns pre-escaped get_avatar() markup. ?>
					</a>
					<div class="zeko-friend-info">
						<a href="<?php echo esc_url( zeko_get_user_profile_url( $friend_id ) ); ?>" class="zeko-friend-name">
							<?php echo esc_html( $friend_data->display_name ); ?>
						</a>
					</div>
				</li>
					<?php
				endif;
			endforeach;
			?>
		</ul>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Friend suggestions shortcode
 *
 * @param mixed $atts Atts.
 */
function zeko_friend_suggestions_shortcode( $atts ) {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'limit' => 5,
		),
		$atts
	);

	$current_user_id = get_current_user_id();
	$suggestions     = zeko_get_friend_suggestions( $current_user_id, absint( $atts['limit'] ) );

	if ( empty( $suggestions ) ) {
		return '<p class="zeko-no-suggestions">' . __( 'No friend suggestions available.', 'zeko' ) . '</p>';
	}

	ob_start();
	?>
	<div class="zeko-friend-suggestions-container">
		<h4><?php esc_html_e( 'People You May Know', 'zeko' ); ?></h4>
		<ul class="zeko-friend-suggestions-list">
			<?php
			foreach ( $suggestions as $suggestion_id ) :
				$suggestion_data = get_userdata( $suggestion_id );
				if ( $suggestion_data ) :
					// Get mutual friends count for suggestion.
					$mutual_friends = zeko_get_mutual_friends( $current_user_id, $suggestion_id );
					$mutual_count   = count( $mutual_friends );
					?>
				<li class="zeko-suggestion-item">
					<a href="<?php echo esc_url( zeko_get_user_profile_url( $suggestion_id ) ); ?>" class="zeko-friend-avatar">
						<?php echo get_avatar( $suggestion_id, 40 ); ?>
					</a>
					<div class="zeko-suggestion-info">
						<a href="<?php echo esc_url( zeko_get_user_profile_url( $suggestion_id ) ); ?>" class="zeko-suggestion-name">
							<?php echo esc_html( $suggestion_data->display_name ); ?>
						</a>
						<?php if ( $mutual_count > 0 ) : ?>
							<span class="zeko-mutual-count">
								<?php echo esc_html( $mutual_count ); ?> <?php echo esc_html( _n( 'mutual friend', 'mutual friends', $mutual_count, 'zeko' ) ); ?>
							</span>
						<?php endif; ?>
					</div>
					<div class="zeko-suggestion-actions">
						<?php echo zeko_add_friend_button_shortcode( array( 'user_id' => $suggestion_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode returns pre-escaped button markup (esc_html_e/esc_attr). ?>
					</div>
				</li>
					<?php
				endif;
			endforeach;
			?>
		</ul>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Friend requests shortcode
 *
 * @param _ $_atts atts.
 */
function zeko_friend_requests_shortcode( $_atts ) {
	if ( ! is_user_logged_in() ) {
		return '<p class="zeko-not-logged-in">' . __( 'You must be logged in to view friend requests.', 'zeko' ) . '</p>';
	}

	$current_user_id  = get_current_user_id();
	$pending_requests = zeko_get_pending_friend_requests( $current_user_id );

	if ( empty( $pending_requests ) ) {
		return '<p class="zeko-no-requests">' . __( 'No pending friend requests.', 'zeko' ) . '</p>';
	}

	ob_start();
	?>
	<div class="zeko-friend-requests-container">
		<h3><?php esc_html_e( 'Friend Requests', 'zeko' ); ?></h3>
		<ul class="zeko-friend-requests">
			<?php
			foreach ( $pending_requests as $initiator_id ) :
				$initiator_data = get_userdata( $initiator_id );
				if ( $initiator_data ) :
					?>
				<li class="zeko-request-item">
					<a href="<?php echo esc_url( zeko_get_user_profile_url( $initiator_id ) ); ?>" class="zeko-request-avatar">
						<?php echo get_avatar( $initiator_id, 50 ); ?>
					</a>
					<div class="zeko-request-info">
						<a href="<?php echo esc_url( zeko_get_user_profile_url( $initiator_id ) ); ?>" class="zeko-request-name">
							<?php echo esc_html( $initiator_data->display_name ); ?>
						</a>
						<span class="zeko-request-username">@<?php echo esc_html( $initiator_data->user_login ); ?></span>
					</div>
					<div class="zeko-request-actions" data-initiator-id="<?php echo esc_attr( $initiator_id ); ?>">
						<button class="btn btn-primary zeko-accept-friend-btn">
							<?php esc_html_e( 'Accept', 'zeko' ); ?>
						</button>
						<button class="btn btn-outline zeko-reject-friend-btn">
							<?php esc_html_e( 'Reject', 'zeko' ); ?>
						</button>
					</div>
				</li>
					<?php
				endif;
			endforeach;
			?>
		</ul>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * AJAX handler for friendship actions
 */
function zeko_ajax_friendship_action() {

	check_ajax_referer( 'zeko_friendship_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'zeko' ) ) );
	}

	$current_user_id = get_current_user_id();
	$target_user_id  = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	$action          = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';

	if ( 0 === $target_user_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid user ID.', 'zeko' ) ) );
	}

	$result  = false;
	$message = __( 'An unknown error occurred.', 'zeko' );

	switch ( $action ) {
		case 'add':
			$result = zeko_send_friend_request( $current_user_id, $target_user_id );
			if ( ! is_wp_error( $result ) ) {
				$message = __( 'Friend request sent!', 'zeko' );
			} else {
				$message = $result->get_error_message();
			}
			break;
		case 'accept':
			$result = zeko_accept_friend_request( $current_user_id, $target_user_id );
			if ( ! is_wp_error( $result ) ) {
				$message = __( 'Friend request accepted!', 'zeko' );
			} else {
				$message = $result->get_error_message();
			}
			break;
		case 'reject':
			$result = zeko_reject_friend_request( $current_user_id, $target_user_id );
			if ( ! is_wp_error( $result ) ) {
				$message = __( 'Friend request rejected!', 'zeko' );
			} else {
				$message = $result->get_error_message();
			}
			break;
		case 'remove':
			$result = zeko_remove_friend( $current_user_id, $target_user_id );
			if ( ! is_wp_error( $result ) ) {
				$message = __( 'Friend removed.', 'zeko' );
			} else {
				$message = $result->get_error_message();
			}
			break;
		default:
			wp_send_json_error( array( 'message' => __( 'Invalid action type.', 'zeko' ) ) );
			break;
	}

	if ( ! is_wp_error( $result ) && $result ) {
		wp_send_json_success( array( 'message' => $message ) );
	} else {
		wp_send_json_error( array( 'message' => $message ) );
	}
}
add_action( 'wp_ajax_zeko_friendship_action', 'zeko_ajax_friendship_action' );

?>
