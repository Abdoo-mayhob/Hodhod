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
 * -
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die;

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

	public const SETTINGS = 'hodhod';

	// Plugin Settings and Default Values (Used when options not set yet)
	public $settings = [];
	public const SETTINGS_DEFAULT = [
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
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_shortcode( 'hodhod', [ $this, 'shortcode' ] );

		// Load Plugin Settings 
		$this->settings = get_option( self::SETTINGS, self::SETTINGS_DEFAULT );
	}

	// --------------------------------------------------------------------------------------
	// Admin Menu
	public function admin_menu() {
		add_options_page(
			__( 'Hodhod Settings', 'hodhod' ),
			__( 'Hodhod', 'hodhod' ),
			'manage_options',
			'hodhod',
			[ $this, 'view_admin' ]
		);
	}

	public function view_admin( $post ) {
		require_once __DIR__ . '/admin.php';
	}

	public function shortcode() {
		return wp_kses_post( '' );
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

