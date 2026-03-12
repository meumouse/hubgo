<?php

/**
 * Plugin Name:             HubGo - Gerenciamento de Frete para WooCommerce
 * Description:             Extensão que permite gerenciar opções de frete para lojas WooCommerce.
 * Plugin URI:              https://meumouse.com/plugins/hubgo/?utm_source=wordpress&utm_medium=hubgo&utm_campaign=plugins_list
 * Author:                  MeuMouse.com
 * Author URI:              https://meumouse.com/?utm_source=wordpress&utm_medium=hubgo&utm_campaign=plugins_list
 * Version:                 2.2.0
 * WC requires at least:    6.0.0
 * WC tested up to:         10.6.0
 * Requires PHP:            7.4
 * Tested up to:            6.9.4
 * Text Domain:             hubgo
 * Domain Path:             /languages
 * License:                 GPLv2 or later
 * License URI:             https://www.gnu.org/licenses/gpl-2.0.html
 */

use MeuMouse\Hubgo\Core\Plugin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

$autoload = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

if ( file_exists( $autoload ) ) {
    require_once $autoload;
}

$plugin_version = '2.2.0';

Plugin::get_instance()->init( $plugin_version );

// Activation hook must use the main plugin file path.
register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );