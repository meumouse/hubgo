<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Admin\Settings;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Assets
 *
 * Manages all CSS and JavaScript enqueuing for the plugin
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Assets {

    /**
     * Frontend script handle
     *
     * @since 2.0.0
     * @var string
     */
    const FRONT_SCRIPT_HANDLE = 'hubgo-front';

    /**
     * Frontend style handle
     *
     * @since 2.0.0
     * @var string
     */
    const FRONT_STYLE_HANDLE = 'hubgo-front-style';

    /**
     * Admin script handle
     *
     * @since 2.0.0
     * @var string
     */
    const ADMIN_SCRIPT_HANDLE = 'hubgo-admin-script';

    /**
     * Admin style handle
     *
     * @since 2.0.0
     * @var string
     */
    const ADMIN_STYLE_HANDLE = 'hubgo-admin-style';

    /**
     * Settings page identifier
     *
     * @since 2.0.0
     * @var string
     */
    const SETTINGS_PAGE_ID = 'hubgo-settings';


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
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }


    /**
     * Enqueue frontend assets
     *
     * @since 2.0.0
     * @return void
     */
    public function enqueue_frontend_assets() {
        $this->register_frontend_styles();
        $this->register_frontend_scripts();
        $this->localize_frontend_scripts();
    }


    /**
     * Register frontend styles
     *
     * @since 2.0.0
     * @return void
     */
    private function register_frontend_styles() {
        $style_url = $this->get_asset_url( 'front/css/hubgo-front.css' );
        $version = $this->get_asset_version();

        wp_enqueue_style(
            self::FRONT_STYLE_HANDLE,
            $style_url,
            array(),
            $version
        );
    }


    /**
     * Register frontend scripts
     *
     * @since 2.0.0
     * @return void
     */
    private function register_frontend_scripts() {
        $script_url = $this->get_asset_url( 'front/js/hubgo-front.js' );
        $version = $this->get_asset_version();

        wp_enqueue_script(
            self::FRONT_SCRIPT_HANDLE,
            $script_url,
            array( 'jquery' ),
            $version,
            true
        );
    }


    /**
     * Localize frontend scripts with data
     *
     * @since 2.0.0
     * @return void
     */
    private function localize_frontend_scripts() {
        $front_params = $this->get_frontend_params();
        $hubgo_params = $this->get_hubgo_params();

        wp_localize_script(
            self::FRONT_SCRIPT_HANDLE,
            'hubgo_front_params',
            apply_filters( 'Hubgo/Core/Assets/FrontParams', $front_params )
        );

        wp_localize_script(
            self::FRONT_SCRIPT_HANDLE,
            'hubgo_params',
            apply_filters( 'Hubgo/Core/Assets/HubgoParams', $hubgo_params )
        );
    }


    /**
     * Get frontend parameters
     *
     * @since 2.0.0
     * @return array
     */
    private function get_frontend_params() {
        return array(
            'ajax_url' => esc_url( admin_url( 'admin-ajax.php' ) ),
        );
    }


    /**
     * Get hubgo specific parameters
     *
     * @since 2.0.0
     * @return array
     */
    private function get_hubgo_params() {
        return array(
            'nonce' => wp_create_nonce( 'hubgo-shipping-calc-nonce' ),
            'auto_shipping' => $this->get_auto_shipping_setting(),
        );
    }


    /**
     * Get auto shipping calculator setting
     *
     * @since 2.0.0
     * @return string
     */
    private function get_auto_shipping_setting() {
        $setting_value = Settings::get_setting( 'enable_auto_shipping_calculator' );
        
        return ! empty( $setting_value ) ? $setting_value : 'no';
    }


    /**
     * Enqueue admin assets
     *
     * @since 2.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        if ( ! $this->is_settings_page( $hook ) ) {
            return;
        }

        $this->register_admin_styles();
        $this->register_admin_scripts();
        $this->localize_admin_scripts();
    }


    /**
     * Check if current page is settings page
     *
     * @since 2.0.0
     * @param string $hook Current admin page hook
     * @return bool
     */
    private function is_settings_page( $hook ) {
        return strpos( $hook, self::SETTINGS_PAGE_ID ) !== false;
    }


    /**
     * Register admin styles
     *
     * @since 2.0.0
     * @return void
     */
    private function register_admin_styles() {
        $style_url = $this->get_asset_url( 'admin/css/hubgo-admin.css' );
        $version = $this->get_asset_version();

        wp_enqueue_style(
            self::ADMIN_STYLE_HANDLE,
            $style_url,
            array(),
            $version
        );
    }


    /**
     * Register admin scripts
     *
     * @since 2.0.0
     * @return void
     */
    private function register_admin_scripts() {
        $script_url = $this->get_asset_url( 'admin/js/hubgo-admin.js' );
        $version = $this->get_asset_version();

        wp_enqueue_script(
            self::ADMIN_SCRIPT_HANDLE,
            $script_url,
            array( 'jquery' ),
            $version,
            true
        );
    }


    /**
     * Localize admin scripts with data
     *
     * @since 2.0.0
     * @return void
     */
    private function localize_admin_scripts() {
        $admin_params = $this->get_admin_params();

        wp_localize_script(
            self::ADMIN_SCRIPT_HANDLE,
            'hubgo_admin_params',
            apply_filters( 'Hubgo/Core/Assets/AdminParams', $admin_params )
        );
    }


    /**
     * Get admin parameters
     *
     * @since 2.0.0
     * @return array
     */
    private function get_admin_params() {
        return array(
            'ajax_url' => esc_url( admin_url( 'admin-ajax.php' ) ),
            'nonce'    => wp_create_nonce( 'hubgo_admin_nonce' ),
        );
    }


    /**
     * Get asset URL with fallback
     *
     * @since 2.0.0
     * @param string $path Asset path relative to assets directory
     * @return string
     */
    private function get_asset_url( $path ) {
        $assets_base = defined( 'HUBGO_ASSETS' ) ? HUBGO_ASSETS : '';
        
        if ( empty( $assets_base ) ) {
            return '';
        }

        return $assets_base . $path;
    }


    /**
     * Get asset version with fallback
     *
     * @since 2.0.0
     * @return string
     */
    private function get_asset_version() {
        return defined( 'HUBGO_VERSION' ) ? HUBGO_VERSION : '1.0.0';
    }
}