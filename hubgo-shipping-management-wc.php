<?php

/**
 * Plugin Name: 			HubGo - Gerenciamento de Frete para WooCommerce
 * Description: 			Extensão que permite gerenciar opções de frete para lojas WooCommerce.
 * Plugin URI: 				https://meumouse.com/plugins/hubgo/
 * Author: 					MeuMouse.com
 * Author URI: 				https://meumouse.com/
 * Version: 				1.3.0
 * WC requires at least: 	5.0.0
 * WC tested up to: 		8.6.0
 * Requires PHP: 			7.2
 * Tested up to:      		6.4.3
 * Text Domain: 			hubgo-shipping-management-wc
 * Domain Path: 			/languages
 * License: 				GPL2
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Hubgo_Shipping_Management' ) ) {
  
/**
 * Main Hubgo_Shipping_Management Class
 *
 * @class Hubgo_Shipping_Management
 * @version 1.0.0
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Hubgo_Shipping_Management {

		/**
		 * Hubgo_Shipping_Management The single instance of Hubgo_Shipping_Management.
		 *
		 * @var object
		 * @since 1.0.0
		 */
		private static $instance = null;

		/**
		 * The slug
		 *
		 * @var string
		 * @since 1.0.0
		 */
		public static $slug = 'hubgo-shipping-management-wc';

		/**
		 * The version number
		 *
		 * @var string
		 * @since 1.0.0
		 */
		public static $version = '1.3.0';

		/**
		 * Constructor function.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function __construct() {
			$this->setup_constants();

			add_action( 'init', array( $this, 'load_plugin_textdomain' ), -1 );
			add_action( 'plugins_loaded', array( $this, 'hubgo_shipping_management_wc_load_checker' ), 5 );
		}
		

		/**
		 * Check requeriments on load plugin
		 * 
		 * @since 1.0.0
		 * @return void
		 */
		public function hubgo_shipping_management_wc_load_checker() {
			if ( !function_exists( 'is_plugin_active' ) ) {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}

			// check if WooCommerce is active
			if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				add_action( 'before_woocommerce_init', array( $this, 'setup_hpos_compatibility' ) );
				add_action( 'plugins_loaded', array( $this, 'setup_includes' ), 20 );
				add_filter( 'plugin_action_links_' . HUBGO_SHIPPING_MANAGEMENT_BASE, array( $this, 'hubgo_shipping_management_wc_plugin_links' ), 10, 4 );
			} else {
				deactivate_plugins( 'hubgo-shipping-management-wc/hubgo-shipping-management-wc.php' );
				add_action( 'admin_notices', array( $this, 'hubgo_shipping_management_wc_deactivate_notice' ) );
			}

			// Display notice if PHP version is bottom 7.2
			if ( version_compare( phpversion(), '7.2', '<' ) ) {
				add_action( 'admin_notices', array( $this, 'hubgo_shipping_management_wc_php_version_notice' ) );
				return;
			}

			// display notice if WooCommerce version is bottom 6.0
			if ( is_plugin_active( 'woocommerce/woocommerce.php' ) && version_compare( WC_VERSION, '6.0', '<' ) ) {
				add_action( 'admin_notices', array( $this, 'hubgo_shipping_management_wc_version_notice' ) );
				return;
			}
		}


		/**
		 * Setup WooCommerce High-Performance Order Storage (HPOS) compatibility
		 * 
		 * @since 1.2.5
		 * @return void
		 */
		public function setup_hpos_compatibility() {
			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '7.1', '<' ) ) {
				return;
			}

			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'custom_order_tables',
					HUBGO_SHIPPING_MANAGEMENT_FILE,
					true
				);
			}
		}


		/**
		 * Main Hubgo_Shipping_Management Instance
		 *
		 * Ensures only one instance of Hubgo_Shipping_Management is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 * @see Hubgo_Shipping_Management()
		 * @return Main Hubgo_Shipping_Management instance
		 */
		public static function run() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Setup plugin constants
		 *
		 * @since  1.0.0
		 * @return void
		 */
		public function setup_constants() {
			$this->define( 'HUBGO_SHIPPING_MANAGEMENT_BASE', plugin_basename( __FILE__ ) );
			$this->define( 'HUBGO_SHIPPING_MANAGEMENT_DIR', plugin_dir_path( __FILE__ ) );
			$this->define( 'HUBGO_SHIPPING_MANAGEMENT_INC_DIR', HUBGO_SHIPPING_MANAGEMENT_DIR . 'includes/' );
			$this->define( 'HUBGO_SHIPPING_MANAGEMENT_URL', plugin_dir_url( __FILE__ ) );
			$this->define( 'HUBGO_SHIPPING_MANAGEMENT_ASSETS', HUBGO_SHIPPING_MANAGEMENT_URL . 'assets/' );
			$this->define( 'HUBGO_SHIPPING_MANAGEMENT_FILE', __FILE__ );
			$this->define( 'HUBGO_SHIPPING_MANAGEMENT_ABSPATH', dirname( HUBGO_SHIPPING_MANAGEMENT_FILE ) . '/' );
			$this->define( 'HUBGO_SHIPPING_MANAGEMENT_SLUG', self::$slug );
			$this->define( 'HUBGO_SHIPPING_MANAGEMENT_VERSION', self::$version );
		}


		/**
		 * Define constant if not already set.
		 *
		 * @param string $name  Constant name.
		 * @param string|bool $value Constant value.
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}


		/**
		 * Include required files
		 *
		 * @since  1.0.0
		 * @return void
		 */
		public function setup_includes() {

			/**
			 * Class init plugin
			 * 
			 * @since 1.0.0
			 */
			include_once HUBGO_SHIPPING_MANAGEMENT_INC_DIR . 'class-hubgo-shipping-management-wc-init.php';

			/**
			 * Admin options
			 * 
			 * @since 1.0.0
			 */
			include_once HUBGO_SHIPPING_MANAGEMENT_INC_DIR . 'admin/class-hubgo-shipping-management-wc-admin-options.php';

			/**
			 * Custom colors
			 * 
			 * @since 1.0.0
			 */
			include_once HUBGO_SHIPPING_MANAGEMENT_INC_DIR . 'classes/class-hubgo-shipping-management-custom-colors.php';

			/**
			 * Update checker
			 * 
			 * @since 1.2.0
			 */
			include_once HUBGO_SHIPPING_MANAGEMENT_INC_DIR . 'classes/class-hubgo-shipping-management-updater.php';
		}


		/**
		 * Notice if WooCommerce is deactivate
		 */
		public function hubgo_shipping_management_wc_deactivate_notice() {
			if ( !current_user_can( 'install_plugins' ) ) { return; }

			echo '<div class="notice is-dismissible error">
					<p>' . __( '<strong>HubGo - Gerenciamento de Frete para WooCommerce</strong> requer que <strong>WooCommerce</strong> esteja instalado e ativado.', 'hubgo-shipping-management-wc' ) . '</p>
				</div>';
		}

		/**
		 * WooCommerce version notice.
		 */
		public function hubgo_shipping_management_wc_version_notice() {
			echo '<div class="notice is-dismissible error">
					<p>' . __( '<strong>HubGo - Gerenciamento de Frete para WooCommerce</strong> requer a versão do WooCommerce 5.0 ou maior. Faça a atualização do plugin WooCommerce.', 'hubgo-shipping-management-wc' ) . '</p>
				</div>';
		}


		/**
		 * PHP version notice
		 * 
		 * @since 1.0.0
		 */
		public function hubgo_shipping_management_wc_php_version_notice() {
			echo '<div class="notice is-dismissible error">
					<p>' . __( '<strong>HubGo - Gerenciamento de Frete para WooCommerce</strong> requer a versão do PHP 7.2 ou maior. Contate o suporte da sua hospedagem para realizar a atualização.', 'hubgo-shipping-management-wc' ) . '</p>
				</div>';
		}

		/**
		 * Plugin action links
		 * 
		 * @since 1.0.0
		 * @return array
		 */
		public function hubgo_shipping_management_wc_plugin_links( $action_links ) {
			$plugins_links = array(
				'<a href="' . admin_url( 'admin.php?page=hubgo-shipping-management-wc' ) . '">'. __( 'Configurar', 'hubgo-shipping-management-wc' ) .'</a>',
				'<a href="https://meumouse.com/docs-category/hubgo-gerenciamento-de-frete-para-woocommerce/" target="_blank">'. __( 'Ajuda', 'hubgo-shipping-management-wc' ) .'</a>'
			);

			return array_merge( $plugins_links, $action_links );
		}

		/**
		 * Load the plugin text domain for translation.
		 */
		public static function load_plugin_textdomain() {
			load_plugin_textdomain( 'hubgo-shipping-management-wc', false, dirname( HUBGO_SHIPPING_MANAGEMENT_BASE ) . '/languages/' );
		}

		/**
		 * Get the plugin url.
		 *
		 * @return string
		 */
		public function plugin_url() {
			return untrailingslashit( plugins_url( '/', HUBGO_SHIPPING_MANAGEMENT_FILE ) );
		}

		/**
		 * Cloning is forbidden.
		 *
		 * @since 1.0.0
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Trapaceando?', 'hubgo-shipping-management-wc' ), '1.0.0' );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 *
		 * @since 1.0.0
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Trapaceando?', 'hubgo-shipping-management-wc' ), '1.0.0' );
		}

	}
}

/**
 * Initialise the plugin
 */
Hubgo_Shipping_Management::run();