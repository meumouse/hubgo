<?php

namespace MeuMouse\Hubgo\Core;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use ReflectionClass;
use Exception;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Plugin core class.
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
final class Plugin {

    /**
     * Plugin version.
     *
     * @since 2.0.0
     * @var string
     */
    private $plugin_version;

    /**
     * Plugin instance.
     *
     * @since 2.0.0
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Cache for instantiated classes.
     *
     * @since 2.0.0
     * @var array
     */
    private $instances = array();


    /**
     * Get plugin instance.
     *
     * @since 2.0.0
     * @return Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * Initialize the plugin.
     *
     * @since 2.0.0
     * @param string $plugin_version Plugin version.
     * @return void
     */
    public function init( $plugin_version ) {
        $this->plugin_version = $plugin_version;

        // Hook before plugin init.
        do_action('Hubgo/Before_Init');

        $this->define_constants();

        // Load text domain.
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Check dependencies early.
        add_action( 'plugins_loaded', array( $this, 'check_dependencies' ), 5 );

        // Set compatibility with WooCommerce HPOS.
        add_action( 'before_woocommerce_init', array( $this, 'setup_hpos_compatibility' ) );

        // Add plugin action links.
        add_filter( 'plugin_action_links_' . $this->get_constant( 'HUBGO_BASENAME' ), array( $this, 'plugin_action_links' ) );

        // Register all class hooks for lazy instantiation.
        $this->register_class_hooks();

        // Hook after plugin init.
        do_action('Hubgo/After_Init');
    }


    /**
     * Define plugin constants used across modules.
     *
     * @since 2.0.0
     * @return void
     */
    private function define_constants() {
        $base_file = dirname( __DIR__, 2 ) . '/hubgo.php';
        $base_dir = plugin_dir_path( $base_file );
        $base_url = plugin_dir_url( $base_file );

        $constants = array(
            'HUBGO_BASENAME'   => plugin_basename( $base_file ),
            'HUBGO_FILE'       => $base_file,
            'HUBGO_PATH'       => $base_dir,
            'HUBGO_INC_PATH'   => $base_dir . 'inc/',
            'HUBGO_URL'        => $base_url,
            'HUBGO_ASSETS'     => $base_url . 'assets/',
            'HUBGO_ABSPATH'    => dirname( $base_file ) . '/',
            'HUBGO_SLUG'       => 'hubgo',
            'HUBGO_VERSION'    => $this->plugin_version,
            'HUBGO_DEBUG_MODE' => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'HUBGO_DEV_MODE'   => true,
        );

        foreach ( $constants as $key => $value ) {
            if ( ! defined( $key ) ) {
                define( $key, $value );
            }
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
     * Register all class hooks for lazy instantiation.
     *
     * This will only instantiate the classes at the hooks defined in the map.
     *
     * @since 2.0.0
     * @return void
     */
    private function register_class_hooks() {
        $map = $this->get_hook_class_map();

        foreach ( $map as $hook => $classes ) {
            if ( empty( $hook ) || empty( $classes ) || ! is_array( $classes ) ) {
                continue;
            }

            foreach ( $classes as $class ) {
                if ( empty( $class ) || ! is_string( $class ) ) {
                    continue;
                }

                add_action( $hook, function() use ( $class ) {
                    $this->safe_instance_class( $class );
                }, 1 );
            }
        }
    }


    /**
     * Get hook => classes map used to lazy-load plugin components.
     *
     * @since 2.0.0
     * @return array
     */
    private function get_hook_class_map() {
        return array(
            'init' => array(
                'MeuMouse\\Hubgo\\Core\\Assets',
                'MeuMouse\\Hubgo\\Core\\Ajax',
                'MeuMouse\\Hubgo\\Admin\\Settings',
            ),
            'wp_loaded' => array(
                'MeuMouse\\Hubgo\\Views\\Shipping_Calculator',
                'MeuMouse\\Hubgo\\Views\\Custom_Colors',
                'MeuMouse\\Hubgo\\API\\Updater',
            ),
        );
    }


    /**
     * Check plugin dependencies.
     *
     * @since 2.0.0
     * @return bool
     */
    public function check_dependencies() {
        $dependencies_met = true;

        // Check PHP version.
        if ( version_compare( phpversion(), '7.4', '<' ) ) {
            add_action( 'admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php echo esc_html__( 'HubGo', 'hubgo' ); ?></strong> 
                        <?php echo esc_html__( 'requer PHP 7.4 ou superior.', 'hubgo' ); ?>
                    </p>
                </div>
                <?php
            } );

            $dependencies_met = false;
        }

        // Check if WooCommerce is active.
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php echo esc_html__( 'HubGo', 'hubgo' ); ?></strong> 
                        <?php echo esc_html__( 'requer que o', 'hubgo' ); ?> 
                        <strong><?php echo esc_html__( 'WooCommerce', 'hubgo' ); ?></strong> 
                        <?php echo esc_html__( 'esteja instalado e ativado.', 'hubgo' ); ?>
                    </p>
                </div>
                <?php
            } );

            $dependencies_met = false;
        }

        // Check WC version.
        if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '6.0', '<' ) ) {
            add_action( 'admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php echo esc_html__( 'HubGo', 'hubgo' ); ?></strong> 
                        <?php echo esc_html__( 'requer WooCommerce 6.0 ou superior.', 'hubgo' ); ?>
                    </p>
                </div>
                <?php
            } );

            $dependencies_met = false;
        }

        return $dependencies_met;
    }


    /**
     * Load plugin text domain.
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
     * Safely instantiate a class.
     *
     * - Skips if dependencies are not met.
     * - Skips if the class does not exist.
     * - Skips if it's not instantiable.
     * - Skips if it has required constructor parameters.
     * - Prevents duplicate instantiation.
     * - Calls init() if available.
     *
     * @since 2.0.0
     * @param string $class Class name.
     * @return void
     */
    private function safe_instance_class( $class ) {
        // Run dependency checks before loading any mapped class.
        if ( ! $this->check_dependencies() ) {
            return;
        }

        // Avoid double instantiation.
        if ( isset( $this->instances[ $class ] ) ) {
            return;
        }

        // Ensure class exists.
        if ( ! class_exists( $class ) ) {
            return;
        }

        try {
            $reflection = new ReflectionClass( $class );

            if ( ! $reflection->isInstantiable() ) {
                return;
            }

            $constructor = $reflection->getConstructor();

            // Only instantiate classes without required constructor parameters.
            if ( $constructor && $constructor->getNumberOfRequiredParameters() > 0 ) {
                return;
            }

            $instance = new $class();

            $this->instances[ $class ] = $instance;

            // Call init method if exists.
            if ( method_exists( $instance, 'init' ) ) {
                $instance->init();
            }
        } catch ( Exception $e ) {
            if ( defined( 'HUBGO_DEBUG_MODE' ) && HUBGO_DEBUG_MODE ) {
                error_log( 'HubGo: Error instancing class ' . $class . ' - ' . $e->getMessage() );
            }
        }
    }


    /**
     * Setup HPOS compatibility.
     *
     * @since 2.0.0
     * @return void
     */
    public function setup_hpos_compatibility() {
        if ( class_exists( FeaturesUtil::class ) ) {
            FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                $this->get_constant( 'HUBGO_FILE' ),
                true
            );
        }
    }


    /**
     * Plugin activation hook
     *
     * @since 2.0.0
     */
    public static function activate() {
        self::get_instance()->maybe_remove_legacy_shipping_plugin();
    }


    /**
     * Deactivate and delete legacy Hubgo Shipping Management plugin
     *
     * @since 2.0.0
     * @return void
     */
    private function maybe_remove_legacy_shipping_plugin() {
        $plugin_file = 'hubgo-shipping-management-wc/hubgo-shipping-management-wc.php';

        // Ensure required functions are available
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Check if plugin exists
        if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {

            // Deactivate if active
            if ( is_plugin_active( $plugin_file ) ) {
                deactivate_plugins( $plugin_file );
            }

            // Delete plugin
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/misc.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

            delete_plugins( array( $plugin_file ) );
        }
    }


    /**
     * Add custom plugin action links.
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
            '<a href="https://ajuda.meumouse.com/docs/hubgo/overview" target="_blank" rel="noopener noreferrer">' . 
                esc_html__( 'Central de ajuda', 'hubgo' ) . 
            '</a>',
        );

        return array_merge( $custom_links, $links );
    }


    /**
     * Cloning is forbidden.
     *
     * @since 2.0.0
     * @return void
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Trapaceando?', 'hubgo' ), '2.0.0' );
    }


    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 2.0.0
     * @return void
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Trapaceando?', 'hubgo' ), '2.0.0' );
    }
}