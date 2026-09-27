<?php
/**
 * Zeko functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Zeko
 */

if ( ! defined( '_S_VERSION' ) ) {
	// Replace the version number of the theme on each release.
	define( '_S_VERSION', '1.0.0' );
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 */
function zeko_setup() {
	/*
	 * Make theme available for translation.
	 * Translations can be filed in the /languages/ directory.
	 */
	load_theme_textdomain( 'zeko', get_template_directory() . '/languages' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	/*
	 * Let WordPress manage the document title.
	 * By adding theme support, we declare that this theme does not use a
	 * hard-coded <title> tag in the document head, and expect WordPress to
	 * provide it for us.
	 */
	add_theme_support( 'title-tag' );

	/*
	 * Enable support for Post Thumbnails on posts and pages.
	 */
	add_theme_support( 'post-thumbnails' );

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus(
		array(
			'primary' => esc_html__( 'Primary Menu', 'zeko' ),
			'footer'  => esc_html__( 'Footer Menu', 'zeko' ),
			'account' => esc_html__( 'Account Menu', 'zeko' ),
		)
	);

	/*
	 * Switch default core markup for search form, comment form, and comments
	 * to output valid HTML5.
	 */
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	// Set up the WordPress core custom background feature.
	add_theme_support(
		'custom-background',
		apply_filters(
			'zeko_custom_background_args',
			array(
				'default-color' => 'ffffff',
				'default-image' => '',
			)
		)
	);

	// Add theme support for selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );

	/**
	 * Add support for core custom logo.
	 */
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);
}
add_action( 'after_setup_theme', 'zeko_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 */
function zeko_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'zeko_content_width', 640 );
}
add_action( 'after_setup_theme', 'zeko_content_width', 0 );

/**
 * Register widget area.
 */
function zeko_widgets_init() {
	register_sidebar(
		array(
			'name'          => esc_html__( 'Sidebar', 'zeko' ),
			'id'            => 'sidebar-1',
			'description'   => esc_html__( 'Add widgets here.', 'zeko' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);

	register_sidebar(
		array(
			'name'          => esc_html__( 'Footer Widgets', 'zeko' ),
			'id'            => 'footer-widgets',
			'description'   => esc_html__( 'Add footer widgets here.', 'zeko' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'zeko_widgets_init' );

/**
 * Enqueue scripts and styles.
 */
function zeko_scripts() {
	$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	$version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? _S_VERSION : '1.0.0';

	// Load Zeko Core design tokens if plugin is active.
	if ( class_exists( 'Zeko_Core' ) ) {
		$core_css = WP_PLUGIN_DIR . '/zeko-core/assets/css/zeko-core.css';
		wp_enqueue_style( 'zeko-core', plugins_url( 'zeko-core/assets/css/zeko-core.css' ), array(), file_exists( $core_css ) ? filemtime( $core_css ) : '1.0.0' );
		wp_enqueue_style( 'zeko-style', get_stylesheet_uri(), array( 'zeko-core' ), $version );
	} else {
		wp_enqueue_style( 'zeko-style', get_stylesheet_uri(), array(), $version );
	}

	$theme_dir = get_template_directory();
	if ( $suffix && file_exists( $theme_dir . '/assets/css/zeko.min.css' ) ) {
		wp_enqueue_style( 'zeko-main', get_template_directory_uri() . '/assets/css/zeko.min.css', array(), filemtime( $theme_dir . '/assets/css/zeko.min.css' ) );
	} else {
		wp_enqueue_style( 'zeko-main', get_template_directory_uri() . '/assets/css/main.css', array(), file_exists( $theme_dir . '/assets/css/main.css' ) ? filemtime( $theme_dir . '/assets/css/main.css' ) : $version );
		if ( file_exists( $theme_dir . '/assets/css/profile.css' ) ) {
			wp_enqueue_style( 'zeko-profile', get_template_directory_uri() . '/assets/css/profile.css', array(), filemtime( $theme_dir . '/assets/css/profile.css' ) );
		}
	}

	// Dashicons for dashboard/messaging/notification glyphs.
	wp_enqueue_style( 'dashicons' );

	// Load JavaScript.
	if ( $suffix && file_exists( $theme_dir . '/assets/js/zeko.min.js' ) ) {
		wp_enqueue_script( 'zeko-navigation', get_template_directory_uri() . '/assets/js/zeko.min.js', array( 'jquery' ), filemtime( $theme_dir . '/assets/js/zeko.min.js' ), true );
		$main_handle = 'zeko-navigation';
	} else {
		wp_enqueue_script( 'zeko-navigation', get_template_directory_uri() . '/assets/js/navigation.js', array(), file_exists( $theme_dir . '/assets/js/navigation.js' ) ? filemtime( $theme_dir . '/assets/js/navigation.js' ) : $version, true );
		wp_enqueue_script( 'zeko-main', get_template_directory_uri() . '/assets/js/main.js', array( 'jquery' ), file_exists( $theme_dir . '/assets/js/main.js' ) ? filemtime( $theme_dir . '/assets/js/main.js' ) : $version, true );
		$main_handle = 'zeko-main';
	}

	// Localize main script (target the handle actually enqueued).
	wp_localize_script(
		$main_handle,
		'zekoData',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'zeko_main_nonce' ),
			'i18n'     => array(
				'backToTopText'      => __( 'Back to Top', 'zeko' ),
				'commentPlaceholder' => __( 'Enter your comment here...', 'zeko' ),
			),
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'zeko_scripts' );

/**
 * Implement the Custom Header feature.
 */
require_once get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require_once get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require_once get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions.
 */
require_once get_template_directory() . '/inc/customizer.php';

/**
 * Resolve a logical ecosystem page through the Core page registry.
 * Central page-URL gateway for the theme. When Zeko Core is active this
 * routes through Zeko_Core_Helpers::get_page_url(), which resolves the
 * "zeko_{module}_{slug}_page_id" stored option, registered page IDs, custom
 * slugs and translated page slugs. When Core is absent the theme falls back
 * to the legacy hardcoded slug so the front end keeps working.
 *
 * @return string Page URL.
 * @param string $module Page-owner module key (e.g. 'zeko', 'auth').
 * @param string $slug Logical page slug (e.g. 'login', 'dashboard').
 * @param array  $config Optional resolution config (see Core).
 */
function zeko_get_page_url( $module, $slug, $config = array() ) {
	if ( class_exists( 'Zeko_Core_Helpers' ) ) {
		return Zeko_Core_Helpers::get_instance()->get_page_url(
			sanitize_key( $module ),
			sanitize_title( $slug ),
			is_array( $config ) ? $config : array()
		);
	}

	return home_url( '/' . sanitize_title( $slug ) . '/' );
}

/**
 * Get a user's public profile URL.
 *
 * @return string Profile URL or home URL fallback.
 * @param int|float $user_id User ID. Defaults to current user.
 */
function zeko_get_user_profile_url( $user_id = 0 ) {
	if ( class_exists( 'Zeko_Core_Helpers' ) ) {
		return Zeko_Core_Helpers::get_instance()->get_user_profile_url( (int) $user_id );
	}

	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id ) {
		return home_url( '/profile/' );
	}
	$profile_slug = get_user_meta( $user_id, 'zeko_profile_slug', true );
	if ( $profile_slug ) {
		return home_url( '/profile/' . $profile_slug . '/' );
	}
	return home_url( '/profile/?user_id=' . $user_id );
}

/**
 * Get a user's avatar HTML.
 *
 * @return string Avatar HTML.
 * @param int|float $user_id User ID.
 * @param int|float $size Avatar size in pixels.
 */
function zeko_get_user_avatar( $user_id = 0, $size = 40 ) {
	if ( class_exists( 'Zeko_Core_Helpers' ) ) {
		return Zeko_Core_Helpers::get_instance()->get_user_avatar( (int) $user_id, (int) $size );
	}

	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id ) {
		return get_avatar( 0, $size );
	}
	return get_avatar( $user_id, $size );
}

/**
 * Whether a periodic theme page-setup routine should run now.
 * These setups were historically executed on every request via init,
 * which added seconds of overhead to each page load. They now run at
 * most once per TTL (default weekly) per setup key while still
 * self-healing over time. Force runs (e.g. the admin recreate tool)
 * should bypass this helper.
 *
 * @return bool True when the setup should run now.
 * @param string $key Setup identifier (e.g. 'essential', 'activity', 'friends').
 * @param int    $ttl How often the setup may run, in seconds.
 */
function zeko_page_setup_due( string $key, int $ttl = WEEK_IN_SECONDS ): bool {
	if ( get_transient( 'zeko_pages_setup_' . $key ) ) {
		return false;
	}
	set_transient( 'zeko_pages_setup_' . $key, 1, $ttl );
	return true;
}

/**
 * Load theme modules and plugins integration
 */
require_once get_template_directory() . '/inc/modules.php';

/**
 * Load custom login and registration system
 */
require_once get_template_directory() . '/inc/auth.php';

/**
 * Load profile builder functionality
 */
require_once get_template_directory() . '/inc/profile-builder.php';

/**
 * Load activity feed functionality
 */
require_once get_template_directory() . '/inc/activity-feed.php';

/**
 * Load messaging system
 */
require_once get_template_directory() . '/inc/messaging.php';

/**
 * Load friendship connections
 */
require_once get_template_directory() . '/inc/friendships.php';

/**
 * Load theme notifications (own source for the shared notification bell)
 */
require_once get_template_directory() . '/inc/notifications.php';

/**
 * Load admin restriction functionality
 */
require_once get_template_directory() . '/inc/class-zeko-multi-checkbox-control.php';

/**
 * Load frontend dashboard functionality
 */
require_once get_template_directory() . '/inc/dashboard.php';

/**
 * Auto-arrange primary and footer menus on theme activation.
 */
function zeko_setup_default_menus() {
	if ( class_exists( 'Zeko_Core_Nav' ) ) {
		$primary_items = Zeko_Core_Nav::get_instance()->get_items( 'primary' );
		$footer_items  = Zeko_Core_Nav::get_instance()->get_items( 'footer' );

		// Prepend theme-owned items that modules don't register.
		array_unshift(
			$primary_items,
			array(
				'title'    => __( 'Home', 'zeko' ),
				'url'      => home_url( '/' ),
				'order'    => 1,
				'children' => array(),
			)
		);
		array_unshift(
			$footer_items,
			array(
				'title' => __( 'Home', 'zeko' ),
				'url'   => home_url( '/' ),
				'order' => 1,
			)
		);
		$footer_items[] = array(
			'title' => __( 'Privacy Policy', 'zeko' ),
			'url'   => home_url( '/privacy/' ),
			'order' => 5,
		);
		$footer_items[] = array(
			'title' => __( 'Terms', 'zeko' ),
			'url'   => home_url( '/terms/' ),
			'order' => 6,
		);
		$footer_items[] = array(
			'title' => __( 'Contact', 'zeko' ),
			'url'   => home_url( '/contact/' ),
			'order' => 7,
		);

		zeko_create_menu( 'primary', __( 'Primary Menu', 'zeko' ), $primary_items );
		zeko_create_menu( 'footer', __( 'Footer Menu', 'zeko' ), $footer_items );

		// Account Menu (top bar, logged-in users).
		$account_items = array(
			array(
				'title' => __( 'Dashboard', 'zeko' ),
				'url'   => zeko_get_page_url( 'zeko', 'dashboard', array( 'fallback' => home_url( '/dashboard/' ) ) ),
				'order' => 1,
			),
			array(
				'title' => __( 'Profile', 'zeko' ),
				'url'   => zeko_get_page_url( 'zeko', 'profile', array( 'fallback' => home_url( '/profile/' ) ) ),
				'order' => 2,
			),
			array(
				'title' => __( 'Settings', 'zeko' ),
				'url'   => home_url( '/settings/' ),
				'order' => 3,
			),
			array(
				'title' => __( 'Logout', 'zeko' ),
				'url'   => wp_logout_url( home_url() ),
				'order' => 4,
			),
		);
		zeko_create_menu( 'account', __( 'Account Menu', 'zeko' ), $account_items );
		return;
	}

	// ── Fallback: hardcoded module URLs (no zeko-core). ──────────────.
	// ── Primary Menu (4 items — mega dropdowns via CSS grid) ────.
	$primary_items = array(
		array(
			'title'    => __( 'Learn', 'zeko' ),
			'url'      => home_url( '/courses/' ),
			'order'    => 1,
			'children' => array(
				array(
					'title' => __( 'Browse Courses', 'zeko' ),
					'url'   => home_url( '/courses/' ),
				),
				array(
					'title' => __( 'My Learning', 'zeko' ),
					'url'   => home_url( '/course-dashboard/' ),
				),
				array(
					'title' => __( 'Certificates', 'zeko' ),
					'url'   => home_url( '/certificates/' ),
				),
				array(
					'title' => __( 'Teach on Zeko', 'zeko' ),
					'url'   => home_url( '/instructor-dashboard/' ),
				),
				array(
					'title' => __( 'Mentorship', 'zeko' ),
					'url'   => home_url( '/mentorship/' ),
				),
			),
		),
		array(
			'title'    => __( 'Work', 'zeko' ),
			'url'      => home_url( '/jobs/' ),
			'order'    => 2,
			'children' => array(
				array(
					'title' => __( 'Browse Jobs', 'zeko' ),
					'url'   => home_url( '/jobs/' ),
				),
				array(
					'title' => __( 'Post a Job', 'zeko' ),
					'url'   => home_url( '/post-a-job/' ),
				),
				array(
					'title' => __( 'Freelance', 'zeko' ),
					'url'   => home_url( '/freelance/' ),
				),
				array(
					'title' => __( 'My Dashboard', 'zeko' ),
					'url'   => home_url( '/job-dashboard/' ),
				),
			),
		),
		array(
			'title'    => __( 'Community', 'zeko' ),
			'url'      => home_url( '/activity/' ),
			'order'    => 3,
			'children' => array(
				array(
					'title' => __( 'Activity Feed', 'zeko' ),
					'url'   => home_url( '/activity/' ),
				),
				array(
					'title' => __( 'Friends', 'zeko' ),
					'url'   => home_url( '/friends/' ),
				),
				array(
					'title' => __( 'Questions', 'zeko' ),
					'url'   => home_url( '/questions/' ),
				),
				array(
					'title' => __( 'Ask a Question', 'zeko' ),
					'url'   => home_url( '/ask-a-question/' ),
				),
				array(
					'title' => __( 'Topics', 'zeko' ),
					'url'   => home_url( '/topics/' ),
				),
			),
		),
	);

	// ── Footer Menu ─────────────────────────────────────.
	$footer_items = array(
		array(
			'title' => __( 'Home', 'zeko' ),
			'url'   => home_url( '/' ),
			'order' => 1,
		),
		array(
			'title' => __( 'Courses', 'zeko' ),
			'url'   => home_url( '/courses/' ),
			'order' => 2,
		),
		array(
			'title' => __( 'Jobs', 'zeko' ),
			'url'   => home_url( '/jobs/' ),
			'order' => 3,
		),
		array(
			'title' => __( 'Q&A', 'zeko' ),
			'url'   => home_url( '/questions/' ),
			'order' => 4,
		),
		array(
			'title' => __( 'Privacy Policy', 'zeko' ),
			'url'   => home_url( '/privacy/' ),
			'order' => 5,
		),
		array(
			'title' => __( 'Terms', 'zeko' ),
			'url'   => home_url( '/terms/' ),
			'order' => 6,
		),
		array(
			'title' => __( 'Contact', 'zeko' ),
			'url'   => home_url( '/contact/' ),
			'order' => 7,
		),
	);

	zeko_create_menu( 'primary', __( 'Primary Menu', 'zeko' ), $primary_items );
	zeko_create_menu( 'footer', __( 'Footer Menu', 'zeko' ), $footer_items );

	// Account Menu (top bar, logged-in users).
	$account_items = array(
		array(
			'title' => __( 'Dashboard', 'zeko' ),
			'url'   => zeko_get_page_url( 'zeko', 'dashboard', array( 'fallback' => home_url( '/dashboard/' ) ) ),
			'order' => 1,
		),
		array(
			'title' => __( 'Profile', 'zeko' ),
			'url'   => zeko_get_page_url( 'zeko', 'profile', array( 'fallback' => home_url( '/profile/' ) ) ),
			'order' => 2,
		),
		array(
			'title' => __( 'Settings', 'zeko' ),
			'url'   => home_url( '/settings/' ),
			'order' => 3,
		),
		array(
			'title' => __( 'Logout', 'zeko' ),
			'url'   => wp_logout_url( home_url() ),
			'order' => 4,
		),
	);
	zeko_create_menu( 'account', __( 'Account Menu', 'zeko' ), $account_items );
}

/**
 * Zeko create menu.
 *
 * @param mixed $location Location.
 * @param mixed $menu_name Menu name.
 * @param mixed $items Items.
 */
function zeko_create_menu( $location, $menu_name, $items ) {
	// Check if menu already exists for this location.
	$locations = get_nav_menu_locations();
	if ( isset( $locations[ $location ] ) && $locations[ $location ] ) {
		return; // Already set, don't overwrite.
	}

	// Find or create the menu.
	$menu = wp_get_nav_menu_object( $menu_name );
	if ( ! $menu ) {
		$menu_id = wp_create_nav_menu( $menu_name );
	} else {
		$menu_id = $menu->term_id;
	}

	// Add items (supports children via 'children' key).
	foreach ( $items as $item ) {
		$parent_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'   => $item['title'],
				'menu-item-url'     => $item['url'],
				'menu-item-status'  => 'publish',
				'menu-item-type'    => 'custom',
				'menu-item-target'  => '',
				'menu-item-classes' => '',
			)
		);

		// Add child items.
		if ( ! empty( $item['children'] ) ) {
			foreach ( $item['children'] as $child ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'     => $child['title'],
						'menu-item-url'       => $child['url'],
						'menu-item-status'    => 'publish',
						'menu-item-type'      => 'custom',
						'menu-item-target'    => '',
						'menu-item-classes'   => '',
						'menu-item-parent-id' => $parent_id,
					)
				);
			}
		}
	}

	// Assign location.
	set_theme_mod( 'nav_menu_locations', array_merge( get_theme_mod( 'nav_menu_locations', array() ), array( $location => $menu_id ) ) );
}
// Finance menu items now owned exclusively by zeko-pay's register_nav_items() on zeko_nav_items.
add_action(
	'admin_init',
	function () {
		if ( get_option( 'zeko_menus_setup_done' ) ) {
			return;
		}
		$locations = get_nav_menu_locations();
		if ( empty( $locations['primary'] ) ) {
			zeko_setup_default_menus();
		}
		update_option( 'zeko_menus_setup_done', true );
	}
);

/**
 * Signature of the currently active Zeko modules plus the nav-registry
 * contents. Used to detect when module menu items must be rebuilt.
 *
 * @return string
 */
function zeko_module_menus_signature() {
	$plugins = (array) get_option( 'active_plugins', array() );
	$zeko    = array_values( preg_grep( '/^zeko-/', $plugins ) );

	$registry = array();
	if ( class_exists( 'Zeko_Core_Nav' ) ) {
		foreach ( array( 'primary', 'footer' ) as $location ) {
			foreach ( Zeko_Core_Nav::get_instance()->get_items( $location ) as $item ) {
				$registry[] = array( $location, $item['title'] ?? '', count( $item['children'] ?? array() ) );
			}
		}
	}

	return md5( (string) wp_json_encode( array( $zeko, $registry ) ) );
}

/**
 * Delete all auto-managed module menu items in a menu.
 *
 * @return void
 * @param int $menu_id Menu term ID.
 */
function zeko_delete_module_menu_items( $menu_id ) {
	$items = wp_get_nav_menu_items( $menu_id );
	if ( ! $items ) {
		return;
	}

	// Children first so parents are removed with their subtree intact.
	usort(
		$items,
		static function ( $a, $b ) {
			return strcmp( (string) $b->menu_item_parent, (string) $a->menu_item_parent );
		}
	);

	foreach ( $items as $item ) {
		if ( get_post_meta( $item->ID, '_zeko_module_item', true ) ) {
			wp_delete_post( $item->ID, true );
		}
	}
}

/**
 * One-time cleanup: remove "Businesses" items injected by the old
 * hardcoded zeko-business-profile code (deleted long ago).
 *
 * @return void
 */
function zeko_cleanup_legacy_business_menu_items() {
	$legacy_urls = array( '/business-directory/', '/create-business/', '/business-portal/' );
	$legacy      = array( 'Businesses', 'Directory', 'Add New', 'My Portal' );

	foreach ( wp_get_nav_menus() as $menu ) {
		$items = wp_get_nav_menu_items( $menu->term_id );
		if ( ! $items ) {
			continue;
		}

		foreach ( $items as $item ) {
			if ( get_post_meta( $item->ID, '_zeko_module_item', true ) ) {
				continue; // Already managed by the new sync system.
			}

			$url       = (string) $item->url;
			$is_legacy = false;
			foreach ( $legacy_urls as $slug ) {
				if ( false !== strpos( $url, $slug ) ) {
					$is_legacy = true;
					break;
				}
			}

			if ( $is_legacy && in_array( $item->title, $legacy, true ) ) {
				wp_delete_post( $item->ID, true );
			}
		}
	}
}

/**
 * Keep module navigation in sync with active plugins.
 * Module menu entries come exclusively from the `zeko_nav_items`
 * registry (Zeko_Core_Nav). Items are rebuilt whenever the set of
 * active zeko-* plugins changes, so deactivated or deleted modules
 * disappear from primary/footer menus automatically. Auto-managed
 * items carry the `_zeko_module_item` post meta flag.
 *
 * @return void
 */
function zeko_sync_module_menus() {
	if ( ! class_exists( 'Zeko_Core_Nav' ) ) {
		return;
	}

	if ( ! get_option( 'zeko_legacy_business_menu_cleaned' ) ) {
		zeko_cleanup_legacy_business_menu_items();
		update_option( 'zeko_legacy_business_menu_cleaned', true );
	}

	// One-time removal of hardcoded Finance/Shop/AI/Rewards items (now owned by zeko_nav_items registry).
	if ( ! get_option( 'zeko_legacy_nav_menu_cleaned' ) ) {
		$legacy_titles = array( 'Finance', 'Shop', 'AI', 'Rewards' );
		foreach ( wp_get_nav_menus() as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );
			if ( ! $items ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( get_post_meta( $item->ID, '_zeko_module_item', true ) ) {
					continue;
				}
				if ( in_array( (string) $item->title, $legacy_titles, true ) && ! (int) $item->menu_item_parent ) {
					wp_delete_post( $item->ID, true );
				}
			}
		}
		update_option( 'zeko_legacy_nav_menu_cleaned', true );
	}

	$signature = zeko_module_menus_signature();
	if ( get_option( 'zeko_module_menus_signature' ) === $signature ) {
		return;
	}

	$locations = get_nav_menu_locations();
	if ( empty( $locations['primary'] ) ) {
		return; // Nothing assigned yet — retry on next request.
	}

	foreach ( array( 'primary', 'footer' ) as $location ) {
		if ( empty( $locations[ $location ] ) ) {
			continue;
		}

		zeko_delete_module_menu_items( (int) $locations[ $location ] );

		$registry_items = Zeko_Core_Nav::get_instance()->get_items( $location );
		if ( ! $registry_items ) {
			continue;
		}

		$menu_id   = (int) $locations[ $location ];
		$max_order = 0;
		foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $existing ) {
			$max_order = max( $max_order, (int) $existing->menu_order );
		}

		foreach ( $registry_items as $index => $item ) {
			$item_id = wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'  => $item['title'],
					'menu-item-url'    => $item['url'],
					'menu-item-status' => 'publish',
					'menu-item-type'   => 'custom',
					'menu-item-order'  => $max_order + (int) $index + 1,
				)
			);

			if ( ! $item_id || is_wp_error( $item_id ) ) {
				continue;
			}

			update_post_meta( $item_id, '_zeko_module_item', 1 );

			foreach ( (array) ( $item['children'] ?? array() ) as $child ) {
				$child_id = wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'     => $child['title'],
						'menu-item-url'       => $child['url'],
						'menu-item-status'    => 'publish',
						'menu-item-type'      => 'custom',
						'menu-item-parent-id' => $item_id,
					)
				);

				if ( $child_id && ! is_wp_error( $child_id ) ) {
					update_post_meta( $child_id, '_zeko_module_item', 1 );
				}
			}
		}
	}

	update_option( 'zeko_module_menus_signature', $signature );
}
	add_action( 'admin_init', 'zeko_sync_module_menus', 20 );

/**
 * One-time sweep: remove every stale legacy (non-`_zeko_module_item`) item
 * from the `primary` menu, keeping only the theme-owned "Home" item and the
 * `_zeko_module_item`-tagged entries managed by zeko_sync_module_menus().
 * Legacy items were injected by deprecated `maybe_create_nav_menu_items()`
 * methods and old hardcoded code. They were never tagged, so the sync system
 * left them in place next to the rebuilt module items — producing the
 * "appears twice, in the dropdown and separately" duplicates.
 *
 * @return void
 */
function zeko_sweep_legacy_primary_items() {
	$locations = get_nav_menu_locations();
	if ( empty( $locations['primary'] ) ) {
		update_option( 'zeko_legacy_primary_swept', 1 );
		return;
	}

	$items = wp_get_nav_menu_items( (int) $locations['primary'] );
	if ( ! $items ) {
		update_option( 'zeko_legacy_primary_swept', 1 );
		return;
	}

	// Remove children before their parents so each subtree stays intact.
	usort(
		$items,
		static function ( $a, $b ) {
			return strcmp( (string) $b->menu_item_parent, (string) $a->menu_item_parent );
		}
	);

	foreach ( $items as $item ) {
		if ( get_post_meta( $item->ID, '_zeko_module_item', true ) ) {
			continue; // Managed by the sync system — keep.
		}
		if ( 0 === (int) $item->menu_item_parent && 'Home' === $item->title ) {
			continue; // Theme-owned home link — keep.
		}
		wp_delete_post( $item->ID, true );
	}

	update_option( 'zeko_legacy_primary_swept', 1 );
}
	add_action(
		'admin_init',
		function () {
			if ( ! get_option( 'zeko_legacy_primary_swept' ) ) {
				zeko_sweep_legacy_primary_items();
			}
		},
		25
	);

	/**
	 * The slim primary navigation header.
	 * The main bar keeps only a small set of high-traffic hubs (Home + a few
	 * module pillars) while the remaining module hubs are folded into the
	 * header-corner gear menu. This filter lists which top-level *titles* stay
	 * in the bar; everything else registered in the primary nav goes to the gear.
	 *
	 * @return string[] Top-level item titles to keep visible in the main bar.
	 */
	function zeko_primary_bar_titles() {
		return apply_filters(
			'zeko_primary_bar_titles',
			array(
				__( 'Home', 'zeko' ),
				__( 'Learn', 'zeko' ),
				__( 'Jobs', 'zeko' ),
				__( 'Businesses', 'zeko' ),
			)
		);
	}

	/**
	 * Return the full, sorted list of primary nav items from the authoritative
	 * Zeko_Core_Nav registry, prefixed with the theme-owned "Home" item.
	 *
	 * @return array<int,array{title:string,url:string,order:int,children:array}>
	 */
	function zeko_primary_nav_items() {
		$items = array(
			array(
				'title'    => __( 'Home', 'zeko' ),
				'url'      => home_url( '/' ),
				'order'    => 1,
				'children' => array(),
			),
		);

		if ( class_exists( 'Zeko_Core_Nav' ) ) {
			$items = array_merge( $items, Zeko_Core_Nav::get_instance()->get_items( 'primary' ) );
		}

		usort(
			$items,
			function ( $a, $b ) {
				return ( (int) ( $a['order'] ?? 20 ) ) <=> ( (int) ( $b['order'] ?? 20 ) );
			}
		);

		return $items;
	}

	/**
	 * Split the primary nav items into the slim top bar and the gear menu.
	 *
	 * @return array{bar:array,gear:array}
	 */
	function zeko_primary_nav_split() {
		$bar_titles = zeko_primary_bar_titles();
		$bar        = array();
		$gear       = array();

		foreach ( zeko_primary_nav_items() as $item ) {
			if ( in_array( $item['title'], $bar_titles, true ) ) {
				$bar[] = $item;
			} else {
				$gear[] = $item;
			}
		}

		return array(
			'bar'  => $bar,
			'gear' => $gear,
		);
	}

	/**
	 * Render a primary nav item's children as an inline expanded list (used
	 * inside the bar dropdowns and the gear hub panels).
	 *
	 * @return string HTML.
	 * @param array $children Flat list of {title,url} child items.
	 */
	function zeko_render_nav_children( $children ) {
		if ( empty( $children ) ) {
			return '';
		}
		$html = '<ul class="sub-menu">';
		foreach ( $children as $child ) {
			$html .= '<li><a href="' . esc_url( $child['url'] ) . '">' . esc_html( $child['title'] ) . '</a></li>';
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * Auto-create ecosystem pages (Wallet, Subscriptions, Invoices, etc.) if missing.
	 */
	add_action(
		'admin_init',
		function () {
			if ( get_option( 'zeko_ecosystem_pages_done' ) ) {
				return;
			}

			$pages = array(
				'wallet'        => array(
					'title'     => __( 'My Wallet', 'zeko' ),
					'shortcode' => '[zeko_pay_wallet]',
				),
				'subscriptions' => array(
					'title'     => __( 'Subscriptions', 'zeko' ),
					'shortcode' => '[zeko_pay_subscriptions]',
				),
				'invoices'      => array(
					'title'     => __( 'Invoices', 'zeko' ),
					'shortcode' => '[zeko_pay_invoices]',
				),
			);

			foreach ( $pages as $slug => $page ) {
				// Check if a published page with this slug already exists.
				$existing = get_page_by_path( $slug );
				if ( $existing && 'publish' === $existing->post_status ) {
					continue;
				}

				$page_id = wp_insert_post(
					array(
						'post_title'   => $page['title'],
						'post_name'    => $slug,
						'post_content' => $page['shortcode'],
						'post_status'  => 'publish',
						'post_type'    => 'page',
					)
				);

				if ( $page_id && ! is_wp_error( $page_id ) ) {
					update_option( 'zeko_pay_' . $slug . '_page_id', $page_id );
				}
			}

			update_option( 'zeko_ecosystem_pages_done', true );
		}
	);

	/**
	 * Redirect wp-login.php to clean /login/ URL.
	 *
	 * @param string $login_url The login URL.
	 * @param string $redirect  Where to redirect after login.
	 * @return string
	 */
	add_filter(
		'login_url',
		function ( $login_url, $redirect ) {
			$url = zeko_get_page_url( 'auth', 'login' );
			if ( $redirect ) {
				$url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
			}
			return $url;
		},
		10,
		2
	);
