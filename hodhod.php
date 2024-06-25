<?php

/**
 *
 * Plugin Name: Hodhod
 * Plugin URI: https://github.com/Abdoo-mayhob/hodhod
 * Description: Integrate gohodhod.com services with your wordpress website.
 * Version: 1.0.0
 * Author: Abdoo
 * Author URI: https://abdoo.me
 * License: GPLv2 or later
 * Text Domain: hodhod
 * Domain Path: /languages
 *
 * ===================================================================
 * 
 * Copyright 2024  Abdullatif Al-Mayhob, Abdoo abdoo.mayhob@gmail.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * ===================================================================
 * 
 * TODO:
 * - Readme
 * - Plugin check
 * - placers other than shortcode
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die;

// Settings link in beside the plugin name in plugins admin screen
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ($links) {
	return array_merge(
		[ '<a href="' . esc_url(
			add_query_arg(
				[ 'autofocus[section]' => 'hodhod_section' ], admin_url( 'customize.php' )
			)
		) . '">Settings</a>' ],
		$links );
} );

// Load Translation Files (Translations only needed in admin area)
add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'hodhod', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}, 0 );


add_action( 'init', function () {
	Hodhod::I();
}, 10 );


/**
 * Main Class.
 */
class Hodhod {

	public const OPTION = 'hodhod';

	// Plugin Settings and Default Values (Used when options not set yet)
	public $settings = [];
	public const SETTINGS_DEFAULT = [ 
		'iframe' => '',
		'show_profile' => false,
		'dark_mode' => true,
	];

	// Refers to a single instance of this class
	private static $instance = null;

	/**
	 * Creates or returns a single instance of this class
	 *
	 * @return Hodhod a single instance of this class.
	 */
	public static function I() {
		self::$instance = self::$instance ?? new self();
		return self::$instance;
	}

	public function __construct() {
		add_action( 'customize_register', [ $this, 'customizer' ] );
		add_shortcode( 'hodhod', [ $this, 'shortcode' ] );

		// Load Plugin Settings 
		$this->settings = get_option( self::OPTION, self::SETTINGS_DEFAULT );
	}

	// --------------------------------------------------------------------------------------
	// Admin Menu

	public function customizer( WP_Customize_Manager $wp_customize ) {

		// Add pen icon above section (Selective Refresh Partial)
		$wp_customize->selective_refresh->add_partial( self::OPTION . '[iframe]',[
			'settings' => [self::OPTION . '[iframe]' , self::OPTION . '[show_profile]' , self::OPTION . '[dark_mode]'],
			'selector' => '.hodhod',
			'container_inclusive' => true
		] );


		$wp_customize->add_section( 'hodhod_section', [ 
			'title' => __( 'Hodhod Settings', 'hodhod' ),
			'priority' => 30,
		] );

		// iframe
		$wp_customize->add_setting( self::OPTION . '[iframe]', [ 
			'type' => 'option',
			'default' => self::SETTINGS_DEFAULT['iframe'],
			'sanitize_callback' => [ $this, 'kses' ], // This allows HTML tags
		] );
		$wp_customize->add_control( new WP_Customize_Code_Editor_Control(
			$wp_customize,
			self::OPTION . '[iframe]',
			[ 
				'label' => __( 'Iframe', 'hodhod' ),
				'section' => 'hodhod_section',
				'code_type' => 'text/html',
			]
		) );

		// show_profile checkbox
		$wp_customize->add_setting( self::OPTION . '[show_profile]', [ 
			'type' => 'option',
			'default' => self::SETTINGS_DEFAULT['show_profile'],
			'sanitize_callback' => 'rest_sanitize_boolean',
		] );
		$wp_customize->add_control( self::OPTION . '[show_profile]', [ 
			'label' => __( 'Show Profile', 'hodhod' ),
			'section' => 'hodhod_section',
			'type' => 'checkbox',
		] );

		// dark_mode checkbox
		$wp_customize->add_setting( self::OPTION . '[dark_mode]', [ 
			'type' => 'option',
			'default' => self::SETTINGS_DEFAULT['dark_mode'],
			'sanitize_callback' => 'rest_sanitize_boolean',
		] );
		$wp_customize->add_control( self::OPTION . '[dark_mode]', [ 
			'label' => __( 'Dark Mode', 'hodhod' ),
			'section' => 'hodhod_section',
			'type' => 'checkbox',
		] );

		// Message under the fields
		$wp_customize->add_setting('iframe_message', [
			'sanitize_callback' => 'wp_filter_nohtml_kses',
		]);
		$wp_customize->add_control(new WP_Customize_Control(
			$wp_customize,
			'iframe_message',
			[
				'description' => '<h4 style="color: red;">' . __('Please Refresh the Customizer to see changes.', 'hodhod') . '</h4>',
				'label' => '',
				'section' => 'hodhod_section',
				'type' => 'hidden',
			]
		));

	}

	// --------------------------------------------------------------------------------------
	// Getters

	/**
	 * Our own version of wp_kses where we allow iframes
	 *
	 * @param string
	 * @return string
	 */
	public static function kses( $value ) {
		$allowed_html = wp_kses_allowed_html( 'post' );
		$allowed_html['iframe'] = [ 
			'src' => true,
			'width' => true,
			'height' => true,
			'align' => true,
			'class' => true,
			'name' => true,
			'id' => true,
			'frameborder' => true,
			'seamless' => true,
			'srcdoc' => true,
			'sandbox' => true,
			'allowfullscreen' => true
		];
		return wp_kses( $value, $allowed_html );
	}

	public function get_iframe() {

		$doc = new DOMDocument();
		$doc->loadHTML( $this->settings['iframe'] );
		$iframe = $doc->getElementsByTagName( 'iframe' );

		// Validation
		if ( $iframe->length === 0 ) {
			return false; // No iframe tag found
		}

		$iframe = $iframe->item( 0 );
		$src = $iframe->getAttribute( 'src' );

		// Parse the URL and query string
		$url_parts = parse_url( $src );

		// Validation
		if ( ! ( isset( $url_parts['host'] ) && $url_parts['host'] === 'gohodhod.com' ) ) {
			return false;
		}

		parse_str( $url_parts['query'], $params );

		// Now you can modify the query params as needed
		$show_profile = $this->settings['show_profile'] ? 'false' : 'true';
		$dark_mode = $this->settings['dark_mode'] ? 'dark' : 'light';
		$params['withoutProfile'] = $show_profile;
		$params['theme'] = $dark_mode;

		// Build the new query string and replace it in the original URL
		$url_parts['query'] = http_build_query( $params );
		$new_src = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'] . '?' . $url_parts['query'];

		// Set the new src attribute
		$iframe->setAttribute( 'src', $new_src );

		// Save the modified HTML
		$new_iframe = $doc->saveHTML( $iframe );

		$html_parts = [
			'before' => '<div class="hodhod">',
			'iframe' => $new_iframe,
			'after' => '</div>'
		];
		
		// TODO: Document
		$html_parts = apply_filters('hodhod_get_iframe', $html_parts);

		return ($html_parts['before'] ?? '') . ($html_parts['iframe'] ?? '') . ($html_parts['after'] ?? '');
	}

	// --------------------------------------------------------------------------------------
	// ShortCodes
	public function shortcode() {
		return self::kses($this->get_iframe());
	}

	// --------------------------------------------------------------------------------------
	// Diagnostics
	public function send_diagnostics( $action = 'unknown' ) {

		if ( ! ( $this->settings['send_diagnostic'] ?? false ) )
			return;

		$diagnostic = wp_json_encode( [ 
			'plugin_slug' => dirname( plugin_basename( __FILE__ ) ),
			'action' => $action,
			'name' => get_bloginfo( 'name' ),
			'url' => get_bloginfo( 'url' ),
			'admin_email' => get_option( 'admin_email' ),
			'lang' => get_locale(),
			'plugin_version' => get_plugin_data( __FILE__ )['Version'],
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version' => phpversion(),
			'theme' => wp_get_theme()->get( 'Name' ) . " | v" . wp_get_theme()->get( 'Version' ),
			'active_plugins' => get_option( 'active_plugins' ),
			'settings' => $this->settings,
			'is_multisite' => is_multisite()
		], JSON_UNESCAPED_UNICODE );

		wp_remote_post( 'https://plugins.abdoo.me/', [ 
			'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
			'blocking' => false,
			'sslverify' => false,
			'body' => $diagnostic
		] );

	}
}


// --------------------------------------------------------------------------------------
// Diagnostics

// Send Diagnostics on plugin activation
register_activation_hook( __FILE__, function () {
	EstReadTime::I()->send_diagnostics( 'activate' );
} );

// Send Diagnostics on plugin deactivation
register_deactivation_hook( __FILE__, function () {
	EstReadTime::I()->send_diagnostics( 'deactivate' );
} );
// Send Diagnostics on plugin settings update
add_action( 'update_option_' . EstReadTime::I()::ERT_SETTINGS, function () {
	EstReadTime::I()->send_diagnostics( 'settings_update' );
} );

