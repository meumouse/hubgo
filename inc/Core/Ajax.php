<?php

namespace MeuMouse\Hubgo\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Ajax
 *
 * Manages AJAX endpoints for settings and shipping calculations
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Ajax {

    /**
     * Settings option name
     *
     * @since 2.0.0
     * @var string
     */
    const SETTINGS_OPTION_NAME = 'hubgo-shipping-management-wc-setting';

    /**
     * Nonce action for admin
     *
     * @since 2.0.0
     * @var string
     */
    const ADMIN_NONCE_ACTION = 'hubgo_admin_nonce';

    /**
     * Nonce action for shipping calculator
     *
     * @since 2.0.0
     * @var string
     */
    const SHIPPING_NONCE_ACTION = 'hubgo-shipping-calc-nonce';


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
        $ajax_actions = array(
            'hubgo_save_settings' => 'save_settings',
            'hubgo_ajax_postcode' => 'ajax_calculate_shipping',
        );

        foreach ( $ajax_actions as $action => $method ) {
            // Logged-in users
            add_action( 'wp_ajax_' . $action, array( $this, $method ) );
            
            // Non-logged users for public endpoints
            if ( 'ajax_calculate_shipping' === $method ) {
                add_action( 'wp_ajax_nopriv_' . $action, array( $this, $method ) );
            }
        }
    }


    /**
     * Save plugin settings via AJAX
     *
     * @since 2.0.0
     * @return void
     */
    public function save_settings() {
        try {
            $this->verify_admin_request();

            // Get and sanitize form data
            $form_data = $this->get_sanitized_form_data();
            
            if ( empty( $form_data ) ) {
                wp_send_json_error( array(
                    'message' => esc_html__( 'Dados inválidos.', 'hubgo' ),
                ));
            }

            // Process and save settings
            $updated_options = $this->process_settings_data( $form_data );
            
            $update_result = update_option( self::SETTINGS_OPTION_NAME, $updated_options );

            if ( ! $update_result ) {
                wp_send_json_error( array(
                    'message' => esc_html__( 'Erro ao salvar as configurações.', 'hubgo' ),
                ));
            }

            wp_send_json_success( array(
                'message' => esc_html__( 'Configurações salvas com sucesso!', 'hubgo' ),
                'options' => $updated_options,
            ));

        } catch ( \Exception $e ) {
            error_log( 'HubGo Ajax Error: ' . $e->getMessage() );
            
            wp_send_json_error( array(
                'message' => esc_html__( 'Erro ao processar a requisição.', 'hubgo' ),
            ));
        }
    }


    /**
     * Verify admin AJAX request
     *
     * @since 2.0.0
     * @throws \Exception If verification fails
     * @return void
     */
    private function verify_admin_request() {
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) 
            ? sanitize_text_field( $_POST['nonce'] ) 
            : '';

        if ( ! wp_verify_nonce( $nonce, self::ADMIN_NONCE_ACTION ) ) {
            throw new \Exception( 'Invalid nonce verification' );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            throw new \Exception( 'Insufficient user permissions' );
        }
    }


    /**
     * Get and sanitize form data from AJAX request
     *
     * @since 2.0.0
     * @return array
     */
    private function get_sanitized_form_data() {
        if ( ! isset( $_POST['form_data'] ) ) {
            return array();
        }

        $form_data_string = sanitize_text_field( $_POST['form_data'] );
        
        if ( empty( $form_data_string ) ) {
            return array();
        }

        $parsed_data = array();
        parse_str( $form_data_string, $parsed_data );

        return $this->sanitize_array_recursive( $parsed_data );
    }


    /**
     * Recursively sanitize array data
     *
     * @since 2.0.0
     * @param mixed $data Data to sanitize
     * @return mixed
     */
    private function sanitize_array_recursive( $data ) {
        if ( is_array( $data ) ) {
            return array_map( array( $this, 'sanitize_array_recursive' ), $data );
        }

        if ( is_string( $data ) ) {
            return sanitize_text_field( $data );
        }

        return $data;
    }


    /**
     * Process and validate settings data
     *
     * @since 2.0.0
     * @param array $form_data Raw form data
     * @return array
     */
    private function process_settings_data( $form_data ) {
        // Get existing options
        $existing_options = get_option( self::SETTINGS_OPTION_NAME, array() );

        // Define checkbox fields
        $checkbox_fields = array(
            'enable_shipping_calculator',
            'enable_auto_shipping_calculator',
        );

        // Process checkboxes
        foreach ( $checkbox_fields as $field ) {
            $form_data[ $field ] = isset( $form_data[ $field ] ) ? 'yes' : 'no';
        }

        // Sanitize text fields
        $text_fields = array(
            'text_header_ship',
            'text_header_value',
            'note_text_bottom_shipping_calc',
        );

        foreach ( $text_fields as $field ) {
            if ( isset( $form_data[ $field ] ) ) {
                $form_data[ $field ] = sanitize_text_field( $form_data[ $field ] );
            }
        }

        // Merge with existing options
        return wp_parse_args( $form_data, $existing_options );
    }


    /**
     * Calculate shipping via AJAX
     *
     * @since 2.0.0
     * @return void
     */
    public function ajax_calculate_shipping() {
        try {
            $this->verify_shipping_request();

            $product_id = $this->get_sanitized_product_id();
            $postcode = $this->get_sanitized_postcode();
            $quantity = $this->get_sanitized_quantity();

            if ( ! $product_id || ! $postcode ) {
                wp_send_json_error( array(
                    'message' => esc_html__( 'Dados inválidos.', 'hubgo' ),
                ));
            }

            $rates = $this->get_shipping_rates( $product_id, $postcode, $quantity );

            if ( ! empty( $rates ) ) {
                $this->render_shipping_table( $rates );
            } else {
                $this->render_no_shipping_message();
            }

        } catch ( \Exception $e ) {
            error_log( 'HubGo Shipping Error: ' . $e->getMessage() );
            
            $this->render_error_message();
        }

        wp_die();
    }


    /**
     * Verify shipping calculation request
     *
     * @since 2.0.0
     * @throws \Exception If verification fails
     * @return void
     */
    private function verify_shipping_request() {
        $nonce = isset( $_POST['nonce'] ) 
            ? sanitize_text_field( $_POST['nonce'] ) 
            : '';

        if ( ! wp_verify_nonce( $nonce, self::SHIPPING_NONCE_ACTION ) ) {
            throw new \Exception( 'Invalid shipping nonce' );
        }
    }


    /**
     * Get sanitized product ID from request
     *
     * @since 2.0.0
     * @return int
     */
    private function get_sanitized_product_id() {
        return isset( $_POST['product'] ) 
            ? absint( $_POST['product'] ) 
            : 0;
    }


    /**
     * Get sanitized postcode from request
     *
     * @since 2.0.0
     * @return string
     */
    private function get_sanitized_postcode() {
        return isset( $_POST['postcode'] ) 
            ? sanitize_text_field( $_POST['postcode'] ) 
            : '';
    }


    /**
     * Get sanitized quantity from request
     *
     * @since 2.0.0
     * @return int
     */
    private function get_sanitized_quantity() {
        return isset( $_POST['qty'] ) 
            ? absint( $_POST['qty'] ) 
            : 1;
    }


    /**
     * Get shipping rates
     *
     * @since 2.0.0
     * @param int $product_id Product ID
     * @param string $postcode Shipping postcode
     * @param int $quantity Product quantity
     * @return array
     */
    private function get_shipping_rates( $product_id, $postcode, $quantity ) {
        static $cached_rates = array();
        
        $cache_key = $product_id . '_' . $postcode . '_' . $quantity;

        if ( isset( $cached_rates[ $cache_key ] ) ) {
            return $cached_rates[ $cache_key ];
        }

        $rates = $this->calculate_rates( $product_id, $postcode, $quantity );
        $cached_rates[ $cache_key ] = $rates;

        return $rates;
    }


    /**
     * Calculate shipping rates
     *
     * @since 2.0.0
     * @param int $product_id Product ID
     * @param string $postcode Shipping postcode
     * @param int $quantity Product quantity
     * @return array
     */
    private function calculate_rates( $product_id, $postcode, $quantity ) {
        // This method should contain the actual shipping calculation logic
        // Placeholder for now - implement according to your business logic
        return array();
    }


    /**
     * Render shipping methods table
     *
     * @since 2.0.0
     * @param array $rates Shipping rates
     * @return void
     */
    private function render_shipping_table( $rates ) {
        $header_shipping = Settings::get_setting( 'text_header_ship' );
        $header_value = Settings::get_setting( 'text_header_value' );
        $bottom_note = Settings::get_setting( 'note_text_bottom_shipping_calc' );

        ?>
        <table cellspacing="0" class="hubgo-table-shipping-methods">
            <tbody>
                <?php if ( $header_shipping || $header_value ) : ?>
                    <tr class="hubgo-shipping-header">
                        <th><?php echo esc_html( $header_shipping ); ?></th>
                        <th><?php echo esc_html( $header_value ); ?></th>
                    </tr>
                <?php endif; ?>

                <?php foreach ( $rates as $rate ) : ?>
                    <tr class="hubgo-shipping-method">
                        <td><?php echo esc_html( $rate->label ); ?></td>
                        <td><?php echo wc_price( $rate->cost ); ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ( $bottom_note ) : ?>
                    <tr class="hubgo-shipping-bottom">
                        <td colspan="2">
                            <span><?php echo esc_html( $bottom_note ); ?></span>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }


    /**
     * Render no shipping available message
     *
     * @since 2.0.0
     * @return void
     */
    private function render_no_shipping_message() {
        echo '<div class="woocommerce-message woocommerce-error">' 
            . esc_html__( 'Nenhuma forma de entrega disponível.', 'hubgo' ) 
            . '</div>';
    }


    /**
     * Render error message
     *
     * @since 2.0.0
     * @return void
     */
    private function render_error_message() {
        echo '<div class="woocommerce-message woocommerce-error">' 
            . esc_html__( 'Erro ao calcular o frete. Tente novamente.', 'hubgo' ) 
            . '</div>';
    }
}