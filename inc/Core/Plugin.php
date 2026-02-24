<?php

namespace MeuMouse\Hubgo\Core;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use ReflectionClass;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Plugin
 *
 * Main plugin class that handles initialization, requirements checking, and core setup
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
final class Plugin {

    /**
     * Plugin version
     *
     * @since 2.0.0
     * @var string
     */
    public $version;

    /**
     * Instance of this class
     *
     * @since 2.0.0
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Default plugin constants
     *
     * @since 2.0.0
     * @var array
     */
    private $default_constants = array(
        'HUBGO_VERSION' => '',
        'HUBGO_FILE' => '',
        'HUBGO_PATH' => '',
        'HUBGO_URL' => '',
        'HUBGO_ASSETS' => '',
        'HUBGO_BASENAME' => '',
    );

    /**
     * Track instanced classes
     *
     * @since 2.0.0
     * @var array
     */
    private static $instanced_classes = array();


    /**
     * Get instance of this class
     *
     * @since 2.0.0
     * @return Plugin
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * Initialize plugin
     *
     * @since 2.0.0
     * @param string $plugin_version Plugin version
     * @return void
     */
    public function init( $plugin_version ) {
        $this->version = $plugin_version;
        
        $this->define_constants();
        $this->init_hooks();
        $this->load_components();
    }


    /**
     * Define plugin constants
     *
     * @since 2.0.0
     * @return void
     */
    private function define_constants() {
        $constants = array(
            'HUBGO_VERSION' => $this->version,
            'HUBGO_FILE' => dirname( dirname( __DIR__ ) ) . '/hubgo.php',
            'HUBGO_BASENAME' => plugin_basename( $this->get_constant( 'HUBGO_FILE' ) ),
        );

        // Calculate dependent constants
        $constants['HUBGO_PATH'] = plugin_dir_path( $constants['HUBGO_FILE'] );
        $constants['HUBGO_URL'] = plugin_dir_url( $constants['HUBGO_FILE'] );
        $constants['HUBGO_ASSETS'] = $constants['HUBGO_URL'] . 'assets/';

        foreach ( $constants as $name => $value ) {
            $this->define_constant( $name, $value );
        }
    }


    /**
     * Define constant if not already set
     *
     * @since 2.0.0
     * @param string $name Constant name
     * @param mixed $value Constant value
     * @return void
     */
    private function define_constant( $name, $value ) {
        if ( ! defined( $name ) ) {
            define( $name, $value );
        }
    }


    /**
     * Get constant value with fallback
     *
     * @since 2.0.0
     * @param string $name Constant name
     * @param mixed $default Default value
     * @return mixed
     */
    private function get_constant( $name, $default = '' ) {
        return defined( $name ) ? constant( $name ) : $default;
    }


    /**
     * Initialize WordPress hooks
     *
     * @since 2.0.0
     * @return void
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'load_textdomain' ), -1 );
        add_action( 'plugins_loaded', array( $this, 'check_requirements' ), 5 );
    }


    /**
     * Load plugin textdomain
     *
     * @since 2.0.0
     * @return void
     */
    public function load_textdomain() {
        $basename = $this->get_constant( 'HUBGO_BASENAME' );
        
        if ( empty( $basename ) ) {
            return;
        }

        load_plugin_textdomain(
            'hubgo',
            false,
            dirname( $basename ) . '/languages/'
        );
    }


    /**
     * Check plugin requirements
     *
     * @since 2.0.0
     * @return void
     */
    public function check_requirements() {
        if ( ! $this->verify_requirements() ) {
            return;
        }

        $this->setup_compatibility();
        $this->add_plugin_links_filter();
    }


    /**
     * Verify all plugin requirements
     *
     * @since 2.0.0
     * @return bool
     */
    private function verify_requirements() {
        // Check WooCommerce
        if ( ! $this->is_woocommerce_active() ) {
            deactivate_plugins( $this->get_constant( 'HUBGO_BASENAME' ) );
            add_action( 'admin_notices', array( $this, 'notice_woocommerce_missing' ) );
            
            return false;
        }

        // Check PHP version
        if ( version_compare( phpversion(), '7.4', '<' ) ) {
            add_action( 'admin_notices', array( $this, 'notice_php_version' ) );
            
            return false;
        }

        // Check WC version
        if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '6.0', '<' ) ) {
            add_action( 'admin_notices', array( $this, 'notice_wc_version' ) );
            
            return false;
        }

        return true;
    }


    /**
     * Check if WooCommerce is active
     *
     * @since 2.0.0
     * @return bool
     */
    private function is_woocommerce_active() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( 'woocommerce/woocommerce.php' );
    }


    /**
     * Setup plugin compatibility features
     *
     * @since 2.0.0
     * @return void
     */
    private function setup_compatibility() {
        add_action( 'before_woocommerce_init', array( $this, 'setup_hpos_compatibility' ) );
    }


    /**
     * Setup HPOS compatibility
     *
     * @since 2.0.0
     * @return void
     */
    public function setup_hpos_compatibility() {
        if ( ! class_exists( FeaturesUtil::class ) ) {
            return;
        }

        FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            $this->get_constant( 'HUBGO_FILE' ),
            true
        );
    }


    /**
     * Add plugin action links filter
     *
     * @since 2.0.0
     * @return void
     */
    private function add_plugin_links_filter() {
        add_filter(
            'plugin_action_links_' . $this->get_constant( 'HUBGO_BASENAME' ),
            array( $this, 'plugin_action_links' )
        );
    }


    /**
     * Add custom plugin action links
     *
     * @since 2.0.0
     * @param array $links Existing plugin links
     * @return array
     */
    public function plugin_action_links( $links ) {
        $custom_links = array(
            '<a href="' . esc_url( admin_url( 'admin.php?page=hubgo-settings' ) ) . '">' . 
                esc_html__( 'Configurar', 'hubgo' ) . 
            '</a>',
            '<a href="https://meumouse.com/docs-category/hubgo-gerenciamento-de-frete-para-woocommerce/" target="_blank" rel="noopener noreferrer">' . 
                esc_html__( 'Ajuda', 'hubgo' ) . 
            '</a>',
        );

        return array_merge( $custom_links, $links );
    }


    /**
     * Display WooCommerce missing notice
     *
     * @since 2.0.0
     * @return void
     */
    public function notice_woocommerce_missing() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php echo esc_html__( 'HubGo', 'hubgo' ); ?></strong> 
                <?php echo esc_html__( 'requer que o', 'hubgo' ); ?> 
                <strong><?php echo esc_html__( 'WooCommerce', 'hubgo' ); ?></strong> 
                <?php echo esc_html__( 'esteja instalado e ativado.', 'hubgo' ); ?>
            </p>
        </div>
        <?php
    }


    /**
     * Display PHP version notice
     *
     * @since 2.0.0
     * @return void
     */
    public function notice_php_version() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php echo esc_html__( 'HubGo', 'hubgo' ); ?></strong> 
                <?php echo esc_html__( 'requer PHP 7.4 ou superior.', 'hubgo' ); ?>
            </p>
        </div>
        <?php
    }


    /**
     * Display WC version notice
     *
     * @since 2.0.0
     * @return void
     */
    public function notice_wc_version() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php echo esc_html__( 'HubGo', 'hubgo' ); ?></strong> 
                <?php echo esc_html__( 'requer WooCommerce 6.0 ou superior.', 'hubgo' ); ?>
            </p>
        </div>
        <?php
    }


    /**
     * Load plugin components
     *
     * @since 2.0.0
     * @return void
     */
    private function load_components() {
        $component_map = array(
            'MeuMouse\Hubgo\Core\Assets' => array(),
            'MeuMouse\Hubgo\Core\Ajax' => array(),
            'MeuMouse\Hubgo\Admin\Settings' => array(
                'condition' => function() {
                    return is_admin();
                },
            ),
            'MeuMouse\Hubgo\API\Updater' => array(),
            'MeuMouse\Hubgo\Views\ShippingCalculator' => array(),
            'MeuMouse\Hubgo\Views\Custom_Colors' => array(),
        );

        foreach ( $component_map as $class_name => $config ) {
            $this->safe_instance_class( $class_name, $config );
        }
    }


    /**
     * Safely instantiate a class
     *
     * @since 2.0.0
     * @param string $class Fully qualified class name
     * @param array $config Configuration array with optional condition
     * @return void
     */
    private function safe_instance_class( $class, $config = array() ) {
        // Check if already instanced
        if ( isset( self::$instanced_classes[ $class ] ) ) {
            return;
        }

        // Check condition if provided
        if ( isset( $config['condition'] ) && is_callable( $config['condition'] ) ) {
            if ( ! call_user_func( $config['condition'] ) ) {
                return;
            }
        }

        try {
            $reflection = new ReflectionClass( $class );

            if ( ! $reflection->isInstantiable() ) {
                return;
            }

            $instance = new $class();
            self::$instanced_classes[ $class ] = $instance;

            // Call init method if exists
            if ( method_exists( $instance, 'init' ) ) {
                $instance->init();
            }
        } catch ( \Exception $e ) {
            error_log( 'HubGo Plugin Error: Failed to instantiate class ' . $class . ' - ' . $e->getMessage() );
        }
    }
}