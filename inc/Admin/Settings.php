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
    const REQUIRED_CAPABILITY = 'manage_options';

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
    }


    /**
     * Add admin menu page
     *
     * @since 2.0.0
     * @return void
     */
    public function add_menu() {
        $capability = $this->get_required_capability();

        add_submenu_page(
            $this->get_parent_menu_slug(),
            esc_html__( self::PAGE_TITLE, 'hubgo' ),
            esc_html__( self::MENU_TITLE, 'hubgo' ),
            $capability,
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
        if ( ! current_user_can( $this->get_required_capability() ) ) {
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
     * Get required capability for accessing settings page
     * 
     * @since 2.0.0
     * @return string
     */
    private function get_parent_menu_slug() {
        global $menu;

        if ( empty( $menu ) || ! is_array( $menu ) ) {
            return 'options-general.php';
        }

        foreach ( $menu as $menu_item ) {
            if ( isset( $menu_item[2] ) && $menu_item[2] === self::PARENT_MENU_SLUG ) {
                return self::PARENT_MENU_SLUG;
            }
        }

        return 'options-general.php';
    }


    /**
     * Get required capability (filterable)
     *
     * @since 2.0.0
     * @return string
     */
    private function get_required_capability() {
        return apply_filters( 'Hubgo/Admin/Settings_Capability', self::REQUIRED_CAPABILITY );
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