<?php
/**
 * Plugin Name: Southland Residential Calculator
 * Plugin URI: https://github.com/christian01dev-shopify/property-tax-savings-estimator
 * Description: County-based residential property tax savings estimator for Southland Tax.
 * Version: 1.0.2
 * Author: Southland Tax
 * Update URI: https://github.com/christian01dev-shopify/property-tax-savings-estimator
 * Text Domain: southland-residential-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SRC_VERSION', '1.0.2' );
define( 'SRC_PLUGIN_FILE', __FILE__ );
define( 'SRC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SRC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SRC_PLUGIN_DIR . 'includes/class-src-settings.php';
require_once SRC_PLUGIN_DIR . 'includes/class-src-calculator.php';
require_once SRC_PLUGIN_DIR . 'includes/class-src-shortcode.php';
require_once SRC_PLUGIN_DIR . 'includes/class-src-updater.php';

register_activation_hook(
	__FILE__,
	static function () {
		if ( false === get_option( SRC_Settings::OPTION_NAME, false ) ) {
			add_option( SRC_Settings::OPTION_NAME, SRC_Settings::get_defaults() );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		$settings = new SRC_Settings();
		$settings->register_hooks();

		$calculator = new SRC_Calculator();
		$calculator->register_hooks();

		$shortcode = new SRC_Shortcode();
		$shortcode->register_hooks();

		SRC_Updater::initialize();
	}
);
