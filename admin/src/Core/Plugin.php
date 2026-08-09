<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Core\License;
use MeuMouse\Hubgo\Core\Tracking_Manager;
use MeuMouse\Hubgo\Admin\Order_Tracking_Meta_Box;
use MeuMouse\Hubgo\Views\Order_Tracking_View;
use MeuMouse\Hubgo\Emails\Email_Shipped_Order;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use ReflectionClass;
use Exception;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Plugin core class.
 *
 * @since 2.0.0
 * @version 2.2.0
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
     * Absolute path to the main plugin file (hubgo.php).
     *
     * @since 3.0.0
     * @var string
     */
    private $base_file = '';

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
     * Collected dependency error codes.
     *
     * @since 3.0.0
     * @var array<int,string>
     */
    private $dependency_errors = array();


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
     * @version 3.0.0
     * @param string $plugin_version Plugin version.
     * @param string $base_file Absolute path to the main plugin file (hubgo.php).
     * @return void
     */
    public function init( $plugin_version, $base_file = '' ) {
        $this->plugin_version = $plugin_version;
        $this->base_file = $base_file;

        // Hook before plugin init.
        do_action('Hubgo/Before_Init');

        $this->define_constants();

        // Register with the MDS SDK (licensing + signed updates). Must be wired
        // before `plugins_loaded`, where the SDK loader fires `mds_sdk_loaded`,
        // and stays outside the dependency gate so licensing keeps working even
        // when WooCommerce is missing.
        License::boot();

        // Load the text domain at the very start of `init`, before any component
        // boots. Nothing in the plugin may call a translation function earlier:
        // since WordPress 6.7 that triggers the just-in-time loader and emits
        // "Translation loading for the hubgo domain was triggered too early".
        add_action( 'init', array( $this, 'load_textdomain' ), 0 );

        // Check dependencies early. The check itself is translation-free — it
        // yields error codes that are turned into copy at render time, which is
        // an admin_notices callback and therefore safely past `init`.
        add_action( 'plugins_loaded', array( $this, 'check_dependencies' ), 5 );

        // Render dependency notices once (deduplicated).
        add_action( 'admin_notices', array( $this, 'render_dependency_notices' ) );

        // Set compatibility with WooCommerce HPOS.
        add_action( 'before_woocommerce_init', array( $this, 'setup_hpos_compatibility' ) );

        // Add plugin action links.
        add_filter( 'plugin_action_links_' . $this->get_constant('HUBGO_BASENAME'), array( $this, 'plugin_action_links' ) );

        // Register all class hooks for lazy instantiation.
        $this->register_class_hooks();

        // Tracking components read settings in their constructors, and the
        // storefront defaults are translatable, so they cannot be built while
        // plugin files are still loading — boot them right after the text
        // domain. Every hook they register (add_meta_boxes, the order save
        // hooks, woocommerce_email_classes) fires well after `init`.
        add_action( 'init', array( $this, 'init_tracking' ), 5 );

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
        // Prefer the file passed from the bootstrap; fall back to the path
        // relative to this class (admin/src/Core/Plugin.php -> plugin root).
        $base_file = $this->base_file ?: ( dirname( __DIR__, 3 ) . '/hubgo.php' );
        $base_dir = plugin_dir_path( $base_file );
        $base_url = plugin_dir_url( $base_file );

        $constants = array(
            'HUBGO_BASENAME'   => plugin_basename( $base_file ),
            'HUBGO_FILE'       => $base_file,
            'HUBGO_PATH'       => $base_dir,
            'HUBGO_INC_PATH'   => $base_dir . 'admin/src/',
            'HUBGO_URL'        => $base_url,
            'HUBGO_ASSETS'     => $base_url . 'assets/',
            'HUBGO_ABSPATH'    => dirname( $base_file ) . '/',
            'HUBGO_SLUG'       => 'hubgo',
            'HUBGO_VERSION'    => $this->plugin_version,
            'HUBGO_DEBUG_MODE' => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'HUBGO_DEV_MODE'   => false,
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
     * @version 3.0.0
     * @return void
     */
    private function register_class_hooks() {
        $map = $this->get_hook_class_map();
        $priorities = $this->get_hook_priorities();

        foreach ( $map as $hook => $classes ) {
            if ( empty( $hook ) || empty( $classes ) || ! is_array( $classes ) ) {
                continue;
            }

            $priority = isset( $priorities[ $hook ] ) ? (int) $priorities[ $hook ] : 10;

            foreach ( $classes as $class ) {
                if ( empty( $class ) || ! is_string( $class ) ) {
                    continue;
                }

                add_action( $hook, function() use ( $class ) {
                    $this->safe_instance_class( $class );
                }, $priority );
            }
        }
    }


    /**
     * Get hook => classes map used to lazy-load plugin components.
     *
     * @since 2.0.0
     * @version 3.0.0
     * @return array
     */
    private function get_hook_class_map() {
        return array(
            // Integrations register themselves through the host plugin's filters
            // (Joinotify builds its trigger/placeholder/integration catalogs from
            // `Joinotify/...` filters), so they must boot before those catalogs
            // are assembled. Joinotify's developer guide asks third parties to
            // register on plugins_loaded/init; booting on wp_loaded left the
            // registration racing the catalog that reads it.
            //
            // `init` (not plugins_loaded) because the registration payloads are
            // translated: on plugins_loaded the text domain is not loaded yet and
            // WordPress 6.7+ warns about a just-in-time translation. The catalogs
            // are only *read* when an admin screen or REST route renders, so the
            // early priority below still lands comfortably ahead of every reader.
            'init' => array(
                'MeuMouse\Hubgo\Integrations\Integration_Registry',
                'MeuMouse\Hubgo\Core\Assets',
                'MeuMouse\Hubgo\Admin\Menu',
                'MeuMouse\Hubgo\Core\Order_Status',
                'MeuMouse\Hubgo\API\Rest_Controller',
                // Adds the "Check for updates" link to the plugins list. Sits
                // with the other admin wiring: its own REST route lives in the
                // controller above, so both appear (or not) together.
                'MeuMouse\Hubgo\Core\Update_Checker',
                // Hooks into the cart/checkout rate selection, which runs well
                // after init but must be wired before the first calculation.
                'MeuMouse\Hubgo\Core\Shipping_Preference',
                // Captures the promised delivery date while the order is being
                // created, so it must be listening before any checkout runs.
                'MeuMouse\Hubgo\Core\Delivery_Promise',
                'MeuMouse\Hubgo\Core\Delivery_Watcher',
            ),
            'wp_loaded' => array(
                'MeuMouse\Hubgo\Views\Shipping_Calculator',
                'MeuMouse\Hubgo\Views\Custom_Colors',
                'MeuMouse\Hubgo\Views\Calculator_Styles',
            ),
        );
    }


    /**
     * Get hook => priority map used when registering the lazy-loading callbacks.
     *
     * Hooks absent from this map are registered with the default priority (10).
     *
     * @since 3.0.0
     * @return array<string,int>
     */
    private function get_hook_priorities() {
        return array(
            // Right after the text domain (priority 0) and ahead of the default
            // priority, so the integration catalogs are populated before any
            // host plugin reads them.
            'init' => 5,
        );
    }

    /**
     * Initialize tracking management components.
     *
     * Wired to `init` (priority 5) rather than called inline: the constructors
     * read settings, whose storefront defaults are translatable.
     *
     * @since 2.1.0
     * @version 3.0.0
     * @return void
     */
    public function init_tracking() {
        $tracking = new Tracking_Manager();

        new Order_Tracking_Meta_Box( $tracking );
        new Order_Tracking_View( $tracking );

        add_filter( 'woocommerce_email_classes', function( $emails ) {
            $emails['Hubgo_Shipped_Order'] = new Email_Shipped_Order();

            return $emails;
        });
    }


    /**
     * Check plugin dependencies.
     *
     * Collects the failing dependency *codes* (without rendering) and returns
     * whether all dependencies are met. Rendering happens once via
     * {@see Plugin::render_dependency_notices()} to avoid duplicated notices.
     *
     * This runs on `plugins_loaded` and again from `safe_instance_class()`, so
     * it must never call a translation function: the text domain only loads on
     * `init`, and translating here makes WordPress 6.7+ report that "Translation
     * loading for the hubgo domain was triggered too early". The copy lives in
     * {@see Plugin::get_dependency_message()} instead.
     *
     * @since 2.0.0
     * @version 3.0.0
     * @return bool
     */
    public function check_dependencies() {
        $errors = array();

        // Check PHP version.
        if ( version_compare( phpversion(), '7.4', '<' ) ) {
            $errors[] = 'php';
        }

        // Check if WooCommerce is active.
        if ( ! class_exists( 'WooCommerce' ) ) {
            $errors[] = 'woocommerce';
        } elseif ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '6.0', '<' ) ) {
            // Check WC version (only when WooCommerce is active).
            $errors[] = 'wc_version';
        }

        $this->dependency_errors = $errors;

        return empty( $errors );
    }


    /**
     * Human-readable copy for a dependency error code.
     *
     * Only ever called from `admin_notices`, which is past `init`.
     *
     * @since 3.0.0
     * @param string $code Dependency error code.
     * @return string
     */
    private function get_dependency_message( $code ) {
        $messages = array(
            'php'         => esc_html__( 'requires PHP 7.4 or higher.', 'hubgo' ),
            'woocommerce' => esc_html__( 'requires WooCommerce to be installed and active.', 'hubgo' ),
            'wc_version'  => esc_html__( 'requires WooCommerce 6.0 or higher.', 'hubgo' ),
        );

        return $messages[ $code ] ?? '';
    }


    /**
     * Render dependency error notices once (deduplicated).
     *
     * @since 3.0.0
     * @version 3.0.0
     * @return void
     */
    public function render_dependency_notices() {
        // Ensure errors are up to date at render time.
        $this->check_dependencies();

        foreach ( $this->dependency_errors as $code ) {
            $action = ( 'woocommerce' === $code ) ? $this->get_woocommerce_action_button() : '';

            printf(
                '<div class="notice notice-error"><p><strong>%1$s</strong> %2$s%3$s</p></div>',
                esc_html__( 'HubGo', 'hubgo' ),
                esc_html( $this->get_dependency_message( $code ) ),
                $action
            );
        }
    }


    /**
     * Build the WooCommerce install/activate action button for the notice.
     *
     * Returns an install button when WooCommerce is not installed, an activate
     * button when it is installed but inactive, or an empty string when the
     * current user lacks the required capability.
     *
     * @since 3.0.0
     * @return string Escaped anchor HTML, or empty string.
     */
    private function get_woocommerce_action_button() {
        $plugin_file = 'woocommerce/woocommerce.php';
        $is_installed = file_exists( WP_PLUGIN_DIR . '/' . $plugin_file );

        if ( $is_installed ) {
            if ( ! current_user_can( 'activate_plugins' ) ) {
                return '';
            }

            $label = esc_html__( 'Activate WooCommerce', 'hubgo' );
            $url = wp_nonce_url(
                self_admin_url( 'plugins.php?action=activate&plugin=' . $plugin_file ),
                'activate-plugin_' . $plugin_file
            );
        } else {
            if ( ! current_user_can( 'install_plugins' ) ) {
                return '';
            }

            $label = esc_html__( 'Install WooCommerce', 'hubgo' );
            $url = wp_nonce_url(
                self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ),
                'install-plugin_woocommerce'
            );
        }

        return sprintf(
            ' <a href="%1$s" class="button button-primary" style="margin-left:8px;">%2$s</a>',
            esc_url( $url ),
            $label
        );
    }


    /**
     * Load plugin text domain.
     *
     * @since 2.0.0
     * @return void
     */
    public function load_textdomain() {
        $basename = $this->get_constant('HUBGO_BASENAME');
        
        if ( empty( $basename ) ) {
            return;
        }

        load_plugin_textdomain( 'hubgo', false, dirname( $basename ) . '/languages/' );
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
            FeaturesUtil::declare_compatibility( 'custom_order_tables', $this->get_constant('HUBGO_FILE'), true );
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
        if ( ! function_exists('is_plugin_active') ) {
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
                esc_html__( 'Settings', 'hubgo' ) .
            '</a>',
            '<a href="https://ajuda.meumouse.com/docs/hubgo/overview" target="_blank" rel="noopener noreferrer">' .
                esc_html__( 'Help center', 'hubgo' ) .
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cheating, huh?', 'hubgo' ), '2.0.0' );
    }


    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 2.0.0
     * @return void
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cheating, huh?', 'hubgo' ), '2.0.0' );
    }
}