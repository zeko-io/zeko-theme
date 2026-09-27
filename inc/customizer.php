<?php
/**
 * Zeko Theme Customizer
 *
 * @package Zeko
 */

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param mixed $wp_customize Wp customize.
 */
function zeko_customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport         = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
	$wp_customize->get_setting( 'header_textcolor' )->transport = 'postMessage';

	if ( isset( $wp_customize->selective_refresh ) ) {
		$wp_customize->selective_refresh->add_partial(
			'blogname',
			array(
				'selector'        => '.site-title a',
				'render_callback' => 'zeko_customize_partial_blogname',
			)
		);
		$wp_customize->selective_refresh->add_partial(
			'blogdescription',
			array(
				'selector'        => '.site-description',
				'render_callback' => 'zeko_customize_partial_blogdescription',
			)
		);
	}

	// Add theme options panel.
	$wp_customize->add_panel(
		'zeko_theme_options',
		array(
			'title'    => __( 'Zeko Theme Options', 'zeko' ),
			'priority' => 120,
		)
	);

	// Add general settings section.
	$wp_customize->add_section(
		'zeko_general_settings',
		array(
			'title'    => __( 'General Settings', 'zeko' ),
			'panel'    => 'zeko_theme_options',
			'priority' => 10,
		)
	);

	// Add color scheme setting.
	$wp_customize->add_setting(
		'zeko_color_scheme',
		array(
			'default'           => 'light',
			'sanitize_callback' => 'zeko_sanitize_color_scheme',
		)
	);

	$wp_customize->add_control(
		'zeko_color_scheme',
		array(
			'label'   => __( 'Color Scheme', 'zeko' ),
			'section' => 'zeko_general_settings',
			'type'    => 'select',
			'choices' => array(
				'light' => __( 'Light', 'zeko' ),
				'dark'  => __( 'Dark', 'zeko' ),
			),
		)
	);

	// Add container width setting.
	$wp_customize->add_setting(
		'zeko_container_width',
		array(
			'default'           => '1200',
			'sanitize_callback' => 'absint',
			'transport'         => 'postMessage',
		)
	);

	$wp_customize->add_control(
		'zeko_container_width',
		array(
			'label'       => __( 'Container Width (px)', 'zeko' ),
			'section'     => 'zeko_general_settings',
			'type'        => 'number',
			'input_attrs' => array(
				'min'  => 800,
				'max'  => 2000,
				'step' => 10,
			),
		)
	);

	// Add button style setting.
	$wp_customize->add_setting(
		'zeko_button_style',
		array(
			'default'           => 'rounded',
			'sanitize_callback' => 'zeko_sanitize_button_style',
		)
	);

	$wp_customize->add_control(
		'zeko_button_style',
		array(
			'label'   => __( 'Button Style', 'zeko' ),
			'section' => 'zeko_general_settings',
			'type'    => 'select',
			'choices' => array(
				'rounded' => __( 'Rounded', 'zeko' ),
				'square'  => __( 'Square', 'zeko' ),
				'pill'    => __( 'Pill', 'zeko' ),
			),
		)
	);

	// Footer credit text: configurable so the hardcoded developer branding is.
	// removable. An empty value hides the credit line entirely.
	$wp_customize->add_setting(
		'zeko_footer_credit',
		array(
			'default'           => __( 'Developed by <a href="https://ozconsultz.com" target="_blank" rel="noopener noreferrer">Ozconsultz.com</a>', 'zeko' ),
			'sanitize_callback' => 'wp_kses_post',
		)
	);

	$wp_customize->add_control(
		'zeko_footer_credit',
		array(
			'label'       => __( 'Footer Credit', 'zeko' ),
			'description' => __( 'Replacement text for the footer credit line. Leave empty to hide it entirely.', 'zeko' ),
			'section'     => 'zeko_general_settings',
			'type'        => 'textarea',
		)
	);

	// Footer social network links.
	$wp_customize->add_section(
		'zeko_footer_social',
		array(
			'title'    => __( 'Footer Social Links', 'zeko' ),
			'panel'    => 'zeko_theme_options',
			'priority' => 20,
		)
	);

	foreach ( array(
		'facebook'  => __( 'Facebook URL', 'zeko' ),
		'twitter'   => __( 'X / Twitter URL', 'zeko' ),
		'linkedin'  => __( 'LinkedIn URL', 'zeko' ),
		'instagram' => __( 'Instagram URL', 'zeko' ),
		'youtube'   => __( 'YouTube URL', 'zeko' ),
	) as $zeko_social_key => $zeko_social_label ) {
		$wp_customize->add_setting(
			'zeko_footer_social_' . $zeko_social_key,
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		$wp_customize->add_control(
			'zeko_footer_social_' . $zeko_social_key,
			array(
				'label'   => $zeko_social_label,
				'section' => 'zeko_footer_social',
				'type'    => 'url',
			)
		);
	}
}
add_action( 'customize_register', 'zeko_customize_register' );

/**
 * Render the site title for the selective refresh partial.
 */
function zeko_customize_partial_blogname() {
	bloginfo( 'name' );
}

/**
 * Render the site tagline for the selective refresh partial.
 */
function zeko_customize_partial_blogdescription() {
	bloginfo( 'description' );
}

/**
 * Sanitize the color scheme.
 *
 * @param mixed $input Input.
 */
function zeko_sanitize_color_scheme( $input ) {
	$valid = array( 'light', 'dark' );

	if ( in_array( $input, $valid, true ) ) {
		return $input;
	}

	return 'light';
}

/**
 * Sanitize the button style.
 *
 * @param mixed $input Input.
 */
function zeko_sanitize_button_style( $input ) {
	$valid = array( 'rounded', 'square', 'pill' );

	if ( in_array( $input, $valid, true ) ) {
		return $input;
	}

	return 'rounded';
}

/**
 * Output custom CSS based on theme options.
 */
function zeko_customizer_css() {
	$container_width = get_theme_mod( 'zeko_container_width', '1200' );
	$button_style    = get_theme_mod( 'zeko_button_style', 'rounded' );

	$css = "
    .container {
        max-width: {$container_width}px;
    }";

	if ( 'square' === $button_style ) {
		$css .= '
        .btn {
            border-radius: 0;
        }';
	} elseif ( 'pill' === $button_style ) {
		$css .= '
        .btn {
            border-radius: 50px;
        }';
	}

	wp_add_inline_style( 'zeko-style', $css );
}
add_action( 'wp_enqueue_scripts', 'zeko_customizer_css' );
