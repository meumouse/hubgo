<?php
/**
 * Plugin Name:             HubGo - Gerenciamento de Frete para WooCommerce
 * Description:             Extensão que permite gerenciar opções de frete para lojas WooCommerce.
 * Plugin URI:              https://meumouse.com/plugins/hubgo/
 * Author:                  MeuMouse.com
 * Author URI:              https://meumouse.com/
 * Version:                 2.0.0
 * WC requires at least:    6.0.0
 * WC tested up to:         10.5.2
 * Requires PHP:            7.4
 * Tested up to:            6.9.1
 * Text Domain:             hubgo
 * Domain Path:             /languages
 * License:                 GPL2
 */

use MeuMouse\Hubgo\Core\Plugin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

$autoload = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

if ( file_exists( $autoload ) ) {
    require_once $autoload;
}

$plugin_version = '2.0.0';

Plugin::get_instance()->init( $plugin_version );

// Activation hook must use the main plugin file path.
register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );