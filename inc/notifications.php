<?php
/**
 * Theme-owned notification source for the Zeko notification bell.
 *
 * The bell aggregator (Zeko Core) picks up every registered source as a tab in
 * the header/footer widget. Until now only active *modules* registered sources,
 * so the theme had no tab of its own. This registers a `theme` source backed by
 * a dedicated `wp_zeko_theme_notifications` table and writes rows for theme
 * events (friend request sent/received, friend request accepted, etc.), so the
 * bell lists the theme under its own tab exactly like modules.
 *
 * @package Zeko
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create the theme notifications table.
 */
function zeko_create_theme_notifications_table() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'zeko_theme_notifications';
	$charset    = $wpdb->get_charset_collate();

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return;
	}

	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		action varchar(50) NOT NULL DEFAULT '',
		object_id bigint(20) unsigned NOT NULL DEFAULT 0,
		object_type varchar(50) NOT NULL DEFAULT '',
		actor_id bigint(20) unsigned DEFAULT NULL,
		is_read tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		title varchar(200) NOT NULL DEFAULT '',
		message text,
		link varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY user_unread (user_id, is_read),
		KEY actor_id (actor_id),
		KEY created_at (created_at)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/**
 * Register the theme as a notification source (bell tab).
 *
 * @return array<string,array> Merged sources.
 * @param array<string,array> $sources Registered sources.
 */
function zeko_register_theme_notification_source( $sources ) {
	global $wpdb;

	$table = $wpdb->prefix . 'zeko_theme_notifications';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		zeko_create_theme_notifications_table();
	}

	$sources['theme'] = array(
		'table'         => $table,
		'has_title'     => true,
		'has_message'   => true,
		'has_link'      => true,
		'has_object'    => true,
		'has_user_id'   => true,
		'label'         => __( 'Theme', 'zeko' ),
		'icon'          => 'dashicons-admin-appearance',
		'link_fallback' => home_url( '/directory/' ),
	);

	return $sources;
}
add_filter( 'zeko_register_notification_sources', 'zeko_register_theme_notification_source', 50 );

/**
 * Insert a theme notification row.
 *
 * @return int|false Inserted ID or false on failure.
 * @param int    $user_id Recipient user ID.
 * @param string $action  Notification action key (e.g. 'friend_request_received').
 * @param array  $args    Optional: title, message, link, object_id, object_type, actor_id.
 */
function zeko_theme_notify( $user_id, $action, array $args = array() ) {
	global $wpdb;

	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return false;
	}

	$table = $wpdb->prefix . 'zeko_theme_notifications';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		zeko_create_theme_notifications_table();
	}

	$result = $wpdb->insert(
		$table,
		array(
			'user_id'     => $user_id,
			'action'      => sanitize_key( $action ),
			'object_id'   => isset( $args['object_id'] ) ? absint( $args['object_id'] ) : 0,
			'object_type' => isset( $args['object_type'] ) ? sanitize_key( $args['object_type'] ) : '',
			'actor_id'    => isset( $args['actor_id'] ) ? absint( $args['actor_id'] ) : 0,
			'is_read'     => 0,
			'created_at'  => current_time( 'mysql' ),
			'title'       => sanitize_text_field( $args['title'] ?? '' ),
			'message'     => sanitize_text_field( $args['message'] ?? '' ),
			'link'        => esc_url_raw( $args['link'] ?? '' ),
		),
		array( '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
	);

	return $result ? (int) $wpdb->insert_id : false;
}

/**
 * Notify the friend when a request is received.
 *
 * @return mixed
 * @param int $initiator_id Initiator user id.
 * @param int $friend_id Friend (recipient) user id.
 */
function zeko_theme_notify_friend_request_sent( $initiator_id, $friend_id ) {
	if ( ! function_exists( 'zeko_get_user_profile_url' ) ) {
		return;
	}

	$actor = get_userdata( (int) $initiator_id );
	$name  = $actor ? $actor->display_name : __( 'Someone', 'zeko' );

	zeko_theme_notify(
		(int) $friend_id,
		'friend_request_received',
		array(
			/* translators: %s: requesting user's display name. */
			'title'       => sprintf( __( '%s sent you a friend request', 'zeko' ), $name ),
			'message'     => __( 'Accept or decline to connect.', 'zeko' ),
			'link'        => zeko_get_user_profile_url( (int) $friend_id ),
			'object_id'   => (int) $initiator_id,
			'object_type' => 'user',
			'actor_id'    => (int) $initiator_id,
		)
	);
}

/**
 * Notify the initiator when their request is accepted.
 *
 * @return mixed
 * @param int $initiator_id Initiator user id.
 * @param int $friend_id Friend (acceptor) user id.
 */
function zeko_theme_notify_friend_accepted( $initiator_id, $friend_id ) {
	$actor = get_userdata( (int) $friend_id );
	$name  = $actor ? $actor->display_name : __( 'Someone', 'zeko' );

	zeko_theme_notify(
		(int) $initiator_id,
		'friend_accepted',
		array(
			/* translators: %s: accepting user's display name. */
			'title'       => sprintf( __( '%s accepted your friend request', 'zeko' ), $name ),
			'message'     => __( 'You are now friends.', 'zeko' ),
			'link'        => function_exists( 'zeko_get_user_profile_url' ) ? zeko_get_user_profile_url( (int) $friend_id ) : home_url( '/friends/' ),
			'object_id'   => (int) $friend_id,
			'object_type' => 'user',
			'actor_id'    => (int) $friend_id,
		)
	);
}

add_action(
	'after_setup_theme',
	function () {
		if ( ! get_option( 'zeko_theme_notifications_table' ) ) {
			if ( ! get_transient( 'zeko_theme_notifications_boot' ) ) {
				set_transient( 'zeko_theme_notifications_boot', 1, DAY_IN_SECONDS );
				zeko_create_theme_notifications_table();
			}
		}

		// Wire event hooks only when the friendship persistence is the one the
		// theme actually drives (Core App_Data when present, DB otherwise).
		add_action( 'zeko_friendship_sent', 'zeko_theme_notify_friend_request_sent', 10, 2 );
		add_action( 'zeko_friendship_accepted', 'zeko_theme_notify_friend_accepted', 10, 2 );
	}
);

/**
 * Changelog:
 * 2026-09 — new: theme notifications table + source registration + event hooks.
 */

/**
 * Create the table during theme migration.
 */
add_action(
	'after_switch_theme',
	function () {
		zeko_create_theme_notifications_table();
	}
);
add_action(
	'admin_init',
	function () {
		if ( ! get_option( 'zeko_theme_notifications_table' ) ) {
			zeko_create_theme_notifications_table();
			update_option( 'zeko_theme_notifications_table', 1 );
		}
	},
	5
);
