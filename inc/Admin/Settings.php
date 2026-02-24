<?php

namespace MeuMouse\Hubgo\Admin;

use Exception;
use MeuMouse\Hubgo\Admin\Views\Settings\Components\Toggle;
use MeuMouse\Hubgo\Admin\Views\Settings\Components\Select;
use MeuMouse\Hubgo\Admin\Views\Settings\Components\Text;
use MeuMouse\Hubgo\Admin\Views\Settings\Components\Color;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Settings
 *
 * Manages plugin settings with tabbed interface and reusable components
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
     * Get /inc directory
     * 
     * @since 2.0.0
     * @var string 
     */
    private $inc_directory = HUBGO_INC_PATH;

    /**
     * Cache for settings options
     *
     * @since 2.0.0
     * @var array|null
     */
    private static $options_cache = null;

    /**
     * Plugin instance.
     *
     * @since 2.0.0
     * @var Settings
     */
    private static $instance = null;


    /**
     * Get class instance
     *
     * @since 2.0.0
     * @return Settings
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }


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
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }


    /**
     * Enqueue admin assets
     *
     * @since 2.0.0
     * @param string $hook Current admin page
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
            return;
        }

        // Enqueue admin CSS
        wp_enqueue_style(
            'hubgo-admin-style',
            HUBGO_ASSETS . 'admin/css/hubgo-admin.css',
            array(),
            HUBGO_VERSION
        );

        // Enqueue admin JavaScript
        wp_enqueue_script(
            'hubgo-admin-script',
            HUBGO_ASSETS . 'admin/js/hubgo-admin.js',
            array( 'jquery' ),
            HUBGO_VERSION,
            true
        );

        // Localize script with parameters
        wp_localize_script( 'hubgo-admin-script', 'hubgo_admin_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'hubgo_admin_nonce' ),
            'unsaved_changes_warning' => __( 'Existem alterações não salvas. Deseja realmente sair?', 'hubgo' ),
        ));
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

        // Process form submission
        $this->process_settings_submission();

        // Render the page
        $this->render_page_html();
    }


    /**
     * Process settings form submission
     *
     * @since 2.0.0
     * @return void
     */
    private function process_settings_submission() {
        if ( ! isset( $_POST['hubgo-shipping-management-wc'] ) ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'hubgo_save_settings' ) ) {
            return;
        }

        // Get and sanitize form data
        $form_data = $this->sanitize_form_data( $_POST );
        
        // Update options
        update_option( self::OPTION_NAME, $form_data );
        
        // Clear cache
        self::$options_cache = null;

        // Add success message
        add_settings_error(
            'hubgo_settings',
            'settings_updated',
            __( 'Configurações salvas com sucesso!', 'hubgo' ),
            'success'
        );
    }


    /**
     * Sanitize form data
     *
     * @since 2.0.0
     * @param array $data Raw form data
     * @return array
     */
    private function sanitize_form_data( $data ) {
        $sanitized = array();

        // Define checkbox fields
        $checkbox_fields = array(
            'enable_shipping_calculator',
            'enable_auto_shipping_calculator',
        );

        // Process checkboxes
        foreach ( $checkbox_fields as $field ) {
            $sanitized[ $field ] = isset( $data[ $field ] ) ? 'yes' : 'no';
        }

        // Process text fields
        $text_fields = array(
            'hook_display_shipping_calculator',
            'primary_main_color',
            'text_info_before_input_shipping_calc',
            'text_button_shipping_calc',
            'text_header_ship',
            'text_header_value',
            'text_placeholder_input_shipping_calc',
            'note_text_bottom_shipping_calc',
        );

        foreach ( $text_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
            }
        }

        return $sanitized;
    }


    /**
     * Render the complete settings page HTML
     *
     * @since 2.0.0
     * @return void
     */
    private function render_page_html() {
        ?>
        <div class="wrap hubgo-settings-wrap">
            <?php $this->render_header(); ?>
            <?php $this->render_success_toast(); ?>
            <?php settings_errors( 'hubgo_settings' ); ?>
            
            <div class="hubgo-settings-content">
                <nav class="hubgo-settings-nav-tabs hubgo-shipping-management-wc-tab-wrapper">
                    <?php $this->render_settings_tabs(); ?>
                </nav>

                <form method="post" action="" class="hubgo-settings-form hubgo-shipping-management-wc-wrapper" name="hubgo-shipping-management-wc">
                    <?php wp_nonce_field( 'hubgo_save_settings' ); ?>
                    <input type="hidden" name="hubgo-shipping-management-wc" value="1" />
                    
                    <?php $this->include_tabs_content(); ?>

                    <div class="hubgo-settings-actions">
                        <button type="submit" class="hubgo-button hubgo-button-primary" name="save_settings">
                            <?php esc_html_e( 'Salvar configurações', 'hubgo' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }


    /**
     * Render settings header
     *
     * @since 2.0.0
     * @return void
     */
    private function render_header() {
        ?>
        <div class="hubgo-settings-header">
            <h1 class="hubgo-shipping-management-wc-admin-section-tile"><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div class="hubgo-shipping-management-wc-admin-title-description">
                <p>
                    <?php echo esc_html__( 'Extensão que permite gerenciar opções de frete para lojas WooCommerce. Se precisar de ajuda para configurar, acesse nossa ', 'hubgo' ); ?>
                    <a class="fancy-link" href="https://meumouse.com/docs-category/hubgo-gerenciamento-de-frete-para-woocommerce/" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html__( 'Central de ajuda', 'hubgo' ); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }


    /**
     * Render success toast notification
     *
     * @since 2.0.0
     * @return void
     */
    private function render_success_toast() {
        ?>
        <div class="toast update-notice-spm-wp">
            <div class="toast-header bg-success text-white">
                <svg class="hubgo-toast-check-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#ffffff">
                    <g stroke-width="0"/>
                    <g stroke-linecap="round" stroke-linejoin="round"/>
                    <g>
                        <path d="M10.5 15.25C10.307 15.2353 10.1276 15.1455 9.99998 15L6.99998 12C6.93314 11.8601 6.91133 11.7029 6.93756 11.55C6.96379 11.3971 7.03676 11.2562 7.14643 11.1465C7.2561 11.0368 7.39707 10.9638 7.54993 10.9376C7.70279 10.9114 7.86003 10.9332 7.99998 11L10.47 13.47L19 5.00004C19.1399 4.9332 19.2972 4.91139 19.45 4.93762C19.6029 4.96385 19.7439 5.03682 19.8535 5.14649C19.9632 5.25616 20.0362 5.39713 20.0624 5.54999C20.0886 5.70286 20.0668 5.86009 20 6.00004L11 15C10.8724 15.1455 10.6929 15.2353 10.5 15.25Z" fill="#ffffff"/>
                        <path d="M12 21C10.3915 20.9974 8.813 20.5638 7.42891 19.7443C6.04481 18.9247 4.90566 17.7492 4.12999 16.34C3.54037 15.29 3.17596 14.1287 3.05999 12.93C2.87697 11.1721 3.2156 9.39921 4.03363 7.83249C4.85167 6.26578 6.1129 4.9746 7.65999 4.12003C8.71001 3.53041 9.87134 3.166 11.07 3.05003C12.2641 2.92157 13.4719 3.03725 14.62 3.39003C14.7224 3.4105 14.8195 3.45215 14.9049 3.51232C14.9903 3.57248 15.0622 3.64983 15.116 3.73941C15.1698 3.82898 15.2043 3.92881 15.2173 4.03249C15.2302 4.13616 15.2214 4.2414 15.1913 4.34146C15.1612 4.44152 15.1105 4.53419 15.0425 4.61352C14.9745 4.69286 14.8907 4.75712 14.7965 4.80217C14.7022 4.84723 14.5995 4.87209 14.4951 4.87516C14.3907 4.87824 14.2867 4.85946 14.19 4.82003C13.2186 4.52795 12.1987 4.43275 11.19 4.54003C10.193 4.64212 9.22694 4.94485 8.34999 5.43003C7.50512 5.89613 6.75813 6.52088 6.14999 7.27003C5.52385 8.03319 5.05628 8.91361 4.77467 9.85974C4.49307 10.8059 4.40308 11.7987 4.50999 12.78C4.61208 13.777 4.91482 14.7431 5.39999 15.62C5.86609 16.4649 6.49084 17.2119 7.23999 17.82C8.00315 18.4462 8.88357 18.9137 9.8297 19.1953C10.7758 19.4769 11.7686 19.5669 12.75 19.46C13.747 19.3579 14.713 19.0552 15.59 18.57C16.4349 18.1039 17.1818 17.4792 17.79 16.73C18.4161 15.9669 18.8837 15.0864 19.1653 14.1403C19.4469 13.1942 19.5369 12.2014 19.43 11.22C19.4201 11.1169 19.4307 11.0129 19.461 10.9139C19.4914 10.8149 19.5409 10.7228 19.6069 10.643C19.6728 10.5631 19.7538 10.497 19.8453 10.4485C19.9368 10.3999 20.0369 10.3699 20.14 10.36C20.2431 10.3502 20.3471 10.3607 20.4461 10.3911C20.5451 10.4214 20.6372 10.471 20.717 10.5369C20.7969 10.6028 20.863 10.6839 20.9115 10.7753C20.9601 10.8668 20.9901 10.9669 21 11.07C21.1821 12.829 20.842 14.6026 20.0221 16.1695C19.2022 17.7363 17.9389 19.0269 16.39 19.88C15.3288 20.4938 14.1495 20.8755 12.93 21C12.62 21 12.3 21 12 21Z" fill="#ffffff"/>
                    </g>
                </svg>
                <span class="me-auto"><?php esc_html_e( 'Salvo com sucesso', 'hubgo' ); ?></span>
                <button class="btn-close btn-close-white ms-2 hide-toast" type="button" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"><?php esc_html_e( 'As configurações foram atualizadas!', 'hubgo' ); ?></div>
        </div>
        <?php
    }


    /**
     * Render settings nav tabs
     *
     * @since 2.0.0
     * @return void
     */
    public function render_settings_tabs() {
        $tabs = $this->register_settings_tabs();
        $first = true;

        foreach ( $tabs as $tab ) {
            $active_class = $first ? 'nav-tab-active' : '';
            printf(
                '<a href="#%1$s" class="nav-tab %4$s" data-tab="%1$s">%2$s %3$s</a>',
                esc_attr( $tab['id'] ),
                $tab['icon'],
                esc_html( $tab['label'] ),
                $active_class
            );
            $first = false;
        }
    }


    /**
     * Register settings tabs through a filter
     *
     * @since 2.0.0
     * @return array
     */
    public function register_settings_tabs() {
        return apply_filters( 'Hubgo/Admin/Register_Settings_Tabs', array(
            'general' => array(
                'id' => 'general',
                'label' => esc_html__( 'Geral', 'hubgo' ),
                'icon' => '',
            ),
            'appearance' => array(
                'id' => 'appearance',
                'label' => esc_html__( 'Aparência', 'hubgo' ),
                'icon' => '',
            ),
            'advanced' => array(
                'id' => 'advanced',
                'label' => esc_html__( 'Avançado', 'hubgo' ),
                'icon' => '',
            ),
        ));
    }


    /**
     * Include tabs content from dedicated files
     *
     * @since 2.0.0
     * @return void
     */
    private function include_tabs_content() {
        $tabs_path = HUBGO_INC_PATH . 'admin/views/settings/tabs/';
        
        // Include general tab
        if ( file_exists( $tabs_path . 'General.php' ) ) {
            include $tabs_path . 'General.php';
        }
        
        // Include appearance tab
        if ( file_exists( $tabs_path . 'Appearance.php' ) ) {
            include $tabs_path . 'Appearance.php';
        }
        
        // Include advanced tab
        if ( file_exists( $tabs_path . 'Advanced.php' ) ) {
            include $tabs_path . 'Advanced.php';
        }
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
        if ( ! class_exists( 'MeuMouse\Hubgo\Admin\Default_Options' ) ) {
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
        if ( empty( $current_options ) ) {
            return true;
        }

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
        if ( ! class_exists( 'MeuMouse\Hubgo\Admin\Default_Options' ) ) {
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