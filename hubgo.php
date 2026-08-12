<?php

/**
 * Plugin Name:             HubGo - Shipping Management for WooCommerce
 * Description:             Extension that manages shipping options for WooCommerce stores.
 * Plugin URI:              https://meumouse.com/plugins/hubgo/?utm_source=wordpress&utm_medium=hubgo&utm_campaign=plugins_list
 * Author:                  MeuMouse.com
 * Author URI:              https://meumouse.com/?utm_source=wordpress&utm_medium=hubgo&utm_campaign=plugins_list
 * Version:                 3.0.0
 * WC requires at least:    6.0.0
 * WC tested up to:         11.0.0
 * Requires PHP:            7.4
 * Tested up to:            7.0.3
 * Text Domain:             hubgo
 * Domain Path:             /languages
 * License:                 GPLv2 or later
 * License URI:             https://www.gnu.org/licenses/gpl-2.0.html
 */

use MeuMouse\Hubgo\Core\Delivery_Watcher;
use MeuMouse\Hubgo\Core\License;
use MeuMouse\Hubgo\Core\Plugin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

$autoload = plugin_dir_path( __FILE__ ) . 'admin/vendor/autoload.php';

if ( file_exists( $autoload ) ) {
    require_once $autoload;
}

// MDS SDK bootstrap (licensing, signed updates and rollback). It declares no
// class at include time; the newest embedded copy across all plugins is elected
// on plugins_loaded and fires `mds_sdk_loaded`.
$mds_sdk = plugin_dir_path( __FILE__ ) . 'admin/vendor/meumouse/mds-php-sdk/mds-sdk.php';

if ( file_exists( $mds_sdk ) ) {
    require_once $mds_sdk;
}

$plugin_version = '3.0.0';

Plugin::get_instance()->init( $plugin_version, __FILE__ );

// Activation hook must use the main plugin file path.
register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );

// Drop the license heartbeat cron when the plugin is deactivated.
register_deactivation_hook( __FILE__, array( License::class, 'deactivate' ) );

// Same for the daily late-delivery pass, which would otherwise stay scheduled
// against a hook nothing answers.
register_deactivation_hook( __FILE__, array( Delivery_Watcher::class, 'unschedule' ) );