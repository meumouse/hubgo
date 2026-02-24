<?php

namespace MeuMouse\Hubgo\API;

use MeuMouse\Hubgo\Admin\Settings;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Updater
 *
 * Manages plugin updates, version checking, and auto-update functionality
 *
 * @since 1.0.0
 * @package MeuMouse\Hubgo\API
 * @author MeuMouse.com
 */
class Updater {

    /**
     * URL to the update checker JSON file
     *
     * @since 1.4.0
     * @var string
     */
    public $update_checker_file = 'https://packages.meumouse.com/v1/updates/hubgo?path=updater&file=update-checker.json';

    /**
     * Plugin slug
     *
     * @since 1.4.0
     * @var string
     */
    public $plugin_slug;

    /**
     * Current plugin version
     *
     * @since 1.4.0
     * @var string
     */
    public $version;

    /**
     * Cache key for update checks
     *
     * @since 1.4.0
     * @var string
     */
    public $cache_key;

    /**
     * Cache key for remote data
     *
     * @since 1.4.0
     * @var string
     */
    public $cache_data_base_key;

    /**
     * Whether caching is allowed
     *
     * @since 1.4.0
     * @var bool
     */
    public $cache_allowed = true;

    /**
     * Cache time in seconds
     *
     * @since 1.4.0
     * @var int
     */
    public $time_cache = DAY_IN_SECONDS;

    /**
     * Update available data
     *
     * @since 1.4.0
     * @var object|null
     */
    public $update_available;

    /**
     * Download URL for the update
     *
     * @since 1.4.0
     * @var string
     */
    public $download_url;

    /**
     * Instance of this class
     *
     * @since 1.4.0
     * @var Updater|null
     */
    private static $instance = null;


    /**
     * Constructor
     *
     * @since 1.4.0
     */
    public function __construct() {
        $this->setup_constants();
        $this->setup_debug_mode();
        $this->init_hooks();
    }


    /**
     * Setup plugin constants
     *
     * @since 1.3.0
     * @return void
     */
    private function setup_constants() {
        $this->plugin_slug = defined( 'HUBGO_SHIPPING_MANAGEMENT_SLUG' ) 
            ? HUBGO_SHIPPING_MANAGEMENT_SLUG 
            : 'hubgo-shipping-management-wc';
            
        $this->version = defined( 'HUBGO_SHIPPING_MANAGEMENT_VERSION' ) 
            ? HUBGO_SHIPPING_MANAGEMENT_VERSION 
            : '1.0.0';
            
        $this->cache_key = 'hubgo_shipping_management_check_updates';
        $this->cache_data_base_key = 'hubgo_shipping_management_remote_data';
    }


    /**
     * Setup debug mode hooks
     *
     * @since 1.3.0
     * @return void
     */
    private function setup_debug_mode() {
        if ( defined( 'HUBGO_SHIPPING_MANAGEMENT_DEV_MODE' ) && HUBGO_SHIPPING_MANAGEMENT_DEV_MODE ) {
            add_filter( 'https_ssl_verify', '__return_false' );
            add_filter( 'https_local_ssl_verify', '__return_false' );
            add_filter( 'http_request_host_is_external', '__return_true' );
        }
    }


    /**
     * Initialize WordPress hooks
     *
     * @since 1.4.0
     * @return void
     */
    private function init_hooks() {
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'site_transient_update_plugins', array( $this, 'update_plugin' ) );
        add_action( 'upgrader_process_complete', array( $this, 'purge_cache' ), 10, 2 );
        add_filter( 'plugin_row_meta', array( $this, 'add_check_updates_link' ), 10, 2 );
        add_action( 'admin_notices', array( $this, 'check_manual_update_query_arg' ) );

        // Auto-update functionality if enabled in settings
        if ( $this->is_auto_update_enabled() ) {
            add_filter( 'auto_update_plugin', array( $this, 'enable_auto_update' ), 10, 2 );
        }
    }


    /**
     * Check if auto-update is enabled in settings
     *
     * @since 1.3.0
     * @return bool
     */
    private function is_auto_update_enabled() {
        return Settings::get_setting( 'enable_auto_updates' ) === 'yes';
    }


    /**
     * Get instance of this class
     *
     * @since 1.4.0
     * @return Updater
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * Request data from remote server
     *
     * @since 1.4.0
     * @return object|false
     */
    public function request() {
        $cached_data = wp_cache_get( $this->cache_key );
    
        if ( false === $cached_data ) {
            $remote = get_transient( $this->cache_data_base_key );
    
            if ( false === $remote ) {
                $params = array(
                    'timeout' => 10,
                    'headers' => array(
                        'Accept' => 'application/json',
                    ),
                );

                $remote = wp_remote_get( $this->update_checker_file, $params );
    
                if ( ! is_wp_error( $remote ) && 200 === wp_remote_retrieve_response_code( $remote ) ) {
                    $remote_data = json_decode( wp_remote_retrieve_body( $remote ) );
    
                    // Set cache remote data for 1 day
                    set_transient( $this->cache_data_base_key, $remote_data, $this->time_cache );
                } else {
                    return false;
                }
            } else {
                $remote_data = $remote;
            }
    
            // Set cache remote data for 1 day
            wp_cache_set( $this->cache_key, $remote_data, $this->time_cache );
        } else {
            $remote_data = $cached_data;
        }
    
        return $remote_data;
    }


    /**
     * Get plugin information for WordPress plugin API
     *
     * @since 1.4.0
     * @param mixed  $response | Existing response object
     * @param string $action   | API action being performed
     * @param object $args     | Arguments for the API call
     * @return mixed
     */
    public function plugin_info( $response, $action, $args ) {
        // Do nothing if not getting plugin information
        if ( 'plugin_information' !== $action ) {
            return $response;
        }
    
        // Do nothing if it's not our plugin
        if ( empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
            return $response;
        }
    
        // Get updates
        $remote = $this->request();
    
        if ( ! $remote ) {
            return $response;
        }
    
        $response = new \stdClass();
    
        $response->name = $remote->name ?? '';
        $response->slug = $remote->slug ?? $this->plugin_slug;
        $response->version = $remote->version ?? $this->version;
        $response->tested = $remote->tested ?? '';
        $response->requires = $remote->requires ?? '';
        $response->author = $remote->author ?? '';
        $response->author_profile = $remote->author_profile ?? '';
        $response->donate_link = $remote->donate_link ?? '';
        $response->homepage = $remote->homepage ?? '';
        $response->download_link = $remote->download_url ?? '';
        $response->trunk = $remote->download_url ?? '';
        $response->requires_php = $remote->requires_php ?? '';
        $response->last_updated = $remote->last_updated ?? '';
    
        $response->sections = array(
            'description' => $remote->sections->description ?? '',
            'installation' => $remote->sections->installation ?? '',
            'changelog' => $remote->sections->changelog ?? '',
        );
    
        if ( ! empty( $remote->banners ) ) {
            $response->banners = array(
                'low' => $remote->banners->low ?? '',
                'high' => $remote->banners->high ?? '',
            );
        }
    
        return $response;
    }


    /**
     * Update plugin information in WordPress update transient
     *
     * @since 1.4.0
     * @param object $transient | Update transient
     * @return object
     */
    public function update_plugin( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }
    
        $cached_data = $this->request();
    
        if ( $cached_data && isset( $cached_data->version ) && version_compare( $this->version, $cached_data->version, '<' ) ) {
            $this->update_available = $cached_data;

            $response = new \stdClass();
            $response->slug = $this->plugin_slug;
            $response->plugin = "{$this->plugin_slug}/{$this->plugin_slug}.php";
            $response->new_version = $cached_data->version;
            $response->tested = $cached_data->tested ?? '';
            $response->package = $cached_data->download_url ?? '';
            
            $transient->response[ $response->plugin ] = $response;
        }
    
        return $transient;
    }


    /**
     * Purge cache after plugin update
     *
     * @since 1.4.0
     * @param object $upgrader | WP_Upgrader instance
     * @param array  $options  | Update options
     * @return void
     */
    public function purge_cache( $upgrader, $options ) {
        if ( $this->cache_allowed && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
            delete_transient( $this->cache_key );
            delete_transient( $this->cache_data_base_key );
            wp_cache_delete( $this->cache_key );
        }
    }


    /**
     * Add check updates link to plugin row meta
     *
     * @since 1.4.0
     * @param array  $plugin_meta | Plugin meta links
     * @param string $plugin_file | Plugin file path
     * @return array
     */
    public function add_check_updates_link( $plugin_meta, $plugin_file ) {
        if ( $plugin_file === $this->plugin_slug . '/' . $this->plugin_slug . '.php' ) {
            $check_updates_link = '<a href="' . esc_url( add_query_arg( 'hubgo_check_updates', '1' ) ) . '">' . 
                esc_html__( 'Verificar atualizações', 'hubgo-shipping-management-wc' ) . 
            '</a>';
            
            $plugin_meta['hubgo_check_updates'] = $check_updates_link;
        }
        
        return $plugin_meta;
    }


    /**
     * Check for manual update query parameter and display notices
     *
     * @since 1.4.0
     * @return void
     */
    public function check_manual_update_query_arg() {
        if ( ! isset( $_GET['hubgo_check_updates'] ) || '1' !== $_GET['hubgo_check_updates'] ) {
            return;
        }

        // Purge cache before request
        $this->purge_all_caches();

        $remote_data = $this->request();
        $message = '';
        $class = '';

        if ( $remote_data ) {
            $current_version = $this->version;
            $latest_version = $remote_data->version;
    
            // If current version is lower than remote version
            if ( version_compare( $current_version, $latest_version, '<' ) ) {
                $message = sprintf(
                    /* translators: %s: latest version number */
                    __( 'Uma nova versão do plugin <strong>HubGo</strong> (%s) está disponível.', 'hubgo-shipping-management-wc' ),
                    esc_html( $latest_version )
                );
                $class = 'notice-success';
                
                // Trigger page reload to show update
                $this->trigger_page_reload();
            } elseif ( version_compare( $current_version, $latest_version, '==' ) ) {
                $message = __( 'A versão do plugin <strong>HubGo</strong> é a mais recente.', 'hubgo-shipping-management-wc' );
                $class = 'notice-success';
            }
        } else {
            $message = __( 'Não foi possível verificar atualizações para o plugin <strong>HubGo</strong>.', 'hubgo-shipping-management-wc' );
            $class = 'notice-error';
        }

        // Display notice
        if ( ! empty( $message ) ) {
            printf(
                '<div class="notice is-dismissible %s"><p>%s</p></div>',
                esc_attr( $class ),
                wp_kses_post( $message )
            );
        }
    }


    /**
     * Purge all cache related to updates
     *
     * @since 1.3.0
     * @return void
     */
    private function purge_all_caches() {
        delete_transient( $this->cache_key );
        delete_transient( $this->cache_data_base_key );
        wp_cache_delete( $this->cache_key );
    }


    /**
     * Trigger page reload for update display
     *
     * @since 1.3.0
     * @return void
     */
    private function trigger_page_reload() {
        ?>
        <script type="text/javascript">
            if ( ! sessionStorage.getItem( 'reload_hubgo_update' ) ) {
                sessionStorage.setItem( 'reload_hubgo_update', 'true' );
                window.location.reload();
            }
        </script>
        <?php
    }


    /**
     * Enable auto-update for this plugin only
     *
     * @since 1.3.0
     * @param bool   $update | Whether to enable auto-update
     * @param object $item   | Plugin object
     * @return bool
     */
    public function enable_auto_update( $update, $item ) {
        if ( isset( $item->plugin ) && $item->plugin === $this->plugin_slug . '/' . $this->plugin_slug . '.php' ) {
            return true;
        }

        return $update;
    }


    /**
     * Check for updates daily and store in option
     *
     * @since 1.3.0
     * @return void
     */
    public static function check_daily_updates() {
        $updater = self::get_instance();
        
        // Purge cache for fresh check
        $updater->purge_all_caches();
        
        $remote_data = $updater->request();

        if ( ! $remote_data ) {
            return;
        }

        // Compare versions
        $current_version = $updater->version;
        $latest_version = $remote_data->version;

        if ( version_compare( $current_version, $latest_version, '<' ) ) {
            // Store update information for later display
            update_option( 'hubgo_update_available', $latest_version );
        } else {
            // Remove option if already updated
            delete_option( 'hubgo_update_available' );
        }
    }
}