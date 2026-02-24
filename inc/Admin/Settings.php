<?php

namespace MeuMouse\Hubgo\Admin;

use Exception;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Settings
 *
 * Manages plugin settings in WordPress admin
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Admin
 * @author MeuMouse.com
 */
class Settings {

    /**
     * Settings option name
     *
     * @since 2.0.0
     * @var string
     */
    const OPTION_NAME = 'hubgo-shipping-management-wc-setting';

    /**
     * Settings page slug
     *
     * @since 2.0.0
     * @var string
     */
    const PAGE_SLUG = 'hubgo-settings';

    /**
     * Parent menu slug
     *
     * @since 2.0.0
     * @var string
     */
    const PARENT_MENU_SLUG = 'woocommerce';

    /**
     * Required capability
     *
     * @since 2.0.0
     * @var string
     */
    const REQUIRED_CAPABILITY = 'manage_woocommerce';

    /**
     * Settings page title
     *
     * @since 2.0.0
     * @var string
     */
    const PAGE_TITLE = 'HubGo - Gerenciamento de Frete';

    /**
     * Menu title
     *
     * @since 2.0.0
     * @var string
     */
    const MENU_TITLE = 'HubGo';

    /**
     * Cache for settings options
     *
     * @since 2.0.0
     * @var array|null
     */
    private static $options_cache = null;


    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->init_hooks();
    }


    /**
     * Initialize WordPress hooks
     *
     * @since 2.0.0
     * @return void
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'plugins_loaded', array( $this, 'init_defaults' ), 20 );
    }


    /**
     * Add admin menu page
     *
     * @since 2.0.0
     * @return void
     */
    public function add_menu() {
        add_submenu_page(
            self::PARENT_MENU_SLUG,
            esc_html__( self::PAGE_TITLE, 'hubgo' ),
            esc_html__( self::MENU_TITLE, 'hubgo' ),
            self::REQUIRED_CAPABILITY,
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' )
        );
    }


    /**
     * Render settings page
     *
     * @since 2.0.0
     * @return void
     */
    public function render_settings_page() {
        // Verify user capabilities
        if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
            wp_die(
                esc_html__( 'Você não tem permissão para acessar esta página.', 'hubgo' )
            );
        }

        $settings_file = $this->get_settings_file_path();
        
        if ( file_exists( $settings_file ) ) {
            include_once $settings_file;
        } else {
            $this->render_file_not_found_error();
        }
    }


    /**
     * Get settings file path
     *
     * @since 2.0.0
     * @return string
     */
    private function get_settings_file_path() {
        $base_path = defined( 'HUBGO_PATH' ) ? HUBGO_PATH : '';
        
        if ( empty( $base_path ) ) {
            return '';
        }

        return $base_path . 'inc/Admin/views/settings-page.php';
    }


    /**
     * Render file not found error
     *
     * @since 2.0.0
     * @return void
     */
    private function render_file_not_found_error() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php echo esc_html__(
                    'Erro: Arquivo de configurações não encontrado.',
                    'hubgo'
                ); ?>
            </p>
        </div>
        <?php
    }


    /**
     * Initialize default options
     *
     * @since 2.0.0
     * @return void
     */
    public function init_defaults() {
        try {
            $default_options = $this->get_default_options();
            $current_options = $this->get_current_options();

            if ( $this->should_update_options( $current_options, $default_options ) ) {
                $this->update_options( $default_options, $current_options );
            }
        } catch ( Exception $e ) {
            error_log( 'HubGo Settings Error: Failed to initialize defaults - ' . $e->getMessage() );
        }
    }


    /**
     * Get default options
     *
     * @since 2.0.0
     * @return array
     */
    private function get_default_options() {
        if ( ! class_exists( 'Default_Options' ) ) {
            return array();
        }

        return Default_Options::get_defaults();
    }


    /**
     * Get current options
     *
     * @since 2.0.0
     * @return array
     */
    private function get_current_options() {
        $options = get_option( self::OPTION_NAME, array() );
        
        return is_array( $options ) ? $options : array();
    }


    /**
     * Check if options need update
     *
     * @since 2.0.0
     * @param array $current_options
     * @param array $default_options
     * @return bool
     */
    private function should_update_options( $current_options, $default_options ) {
        // If no options exist, we need to create defaults
        if ( empty( $current_options ) ) {
            return true;
        }

        // Check if we need to merge with new defaults
        $merged = wp_parse_args( $current_options, $default_options );
        
        return $merged !== $current_options;
    }


    /**
     * Update options
     *
     * @since 2.0.0
     * @param array $default_options
     * @param array $current_options
     * @return void
     */
    private function update_options( $default_options, $current_options ) {
        if ( empty( $current_options ) ) {
            $new_options = $default_options;
        } else {
            $new_options = wp_parse_args( $current_options, $default_options );
        }

        update_option( self::OPTION_NAME, $new_options );
        
        // Clear cache after update
        self::$options_cache = null;
    }


    /**
     * Get a specific setting value
     *
     * @since 2.0.0
     * @param string $key Setting key
     * @param mixed $default Default value if setting not found
     * @return mixed
     */
    public static function get_setting( $key, $default = false ) {
        $options = self::get_all_settings();
        
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }


    /**
     * Get all settings with caching
     *
     * @since 2.0.0
     * @return array
     */
    private static function get_all_settings() {
        if ( null === self::$options_cache ) {
            $options = get_option( self::OPTION_NAME, array() );
            self::$options_cache = is_array( $options ) ? $options : array();
        }

        return self::$options_cache;
    }


    /**
     * Update a specific setting
     *
     * @since 2.0.0
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool
     */
    public static function update_setting( $key, $value ) {
        $options = self::get_all_settings();
        $options[ $key ] = $value;

        $updated = update_option( self::OPTION_NAME, $options );
        
        if ( $updated ) {
            // Clear cache after update
            self::$options_cache = null;
        }

        return $updated;
    }


    /**
     * Delete a specific setting
     *
     * @since 2.0.0
     * @param string $key Setting key
     * @return bool
     */
    public static function delete_setting( $key ) {
        $options = self::get_all_settings();
        
        if ( isset( $options[ $key ] ) ) {
            unset( $options[ $key ] );
            
            $updated = update_option( self::OPTION_NAME, $options );
            
            if ( $updated ) {
                self::$options_cache = null;
            }

            return $updated;
        }

        return false;
    }


    /**
     * Reset all settings to defaults
     *
     * @since 2.0.0
     * @return bool
     */
    public static function reset_to_defaults() {
        if ( ! class_exists( 'Default_Options' ) ) {
            return false;
        }

        $default_options = Default_Options::get_defaults();
        
        $updated = update_option( self::OPTION_NAME, $default_options );
        
        if ( $updated ) {
            self::$options_cache = null;
        }

        return $updated;
    }
}