<?php
/**
 * Theme modules and plugins integration
 *
 * @package Zeko
 */

/**
 * Whether a Zeko module plugin is active.
 * Every Zeko plugin instantiates a well-known class on load, so presence
 * can be detected with a simple class_exists() check.
 *
 * @return bool
 * @param string $class Class name exposed by the module plugin.
 */
function zeko_is_module_active( $class ) {
	return class_exists( $class );
}

/**
 * Initialize the theme's module integration layer.
 * Fires once after setup so Zeko plugins can hook in to contribute nav,
 * dashboard tabs, or notification sources without depending on load order.
 */
function zeko_init_modules() {
	do_action( 'zeko_theme_modules_loaded' );
}
add_action( 'after_setup_theme', 'zeko_init_modules', 20 );
