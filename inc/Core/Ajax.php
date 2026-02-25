<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Admin\Settings;

use Exception;
use WC_Shipping;
use WC_Shipping_Rate;
use WC_Validation;

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
    const SETTINGS_OPTION_NAME = 'hubgo_settings';

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
            add_action( 'wp_ajax_' . $action, array( $this, $method ) );

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

            $form_data = $this->get_sanitized_form_data();

            if ( empty( $form_data ) ) {
                wp_send_json_error( array(
                    'message' => esc_html__( 'Dados inválidos.', 'hubgo' ),
                ) );
            }

            $updated_options = $this->process_settings_data( $form_data );

            $update_result = update_option( self::SETTINGS_OPTION_NAME, $updated_options );

            if ( ! $update_result ) {
                wp_send_json_error( array(
                    'message' => esc_html__( 'Erro ao salvar as configurações.', 'hubgo' ),
                ) );
            }

            wp_send_json_success( array(
                'message' => esc_html__( 'Configurações salvas com sucesso!', 'hubgo' ),
                'options' => $updated_options,
            ) );

        } catch ( Exception $e ) {
            error_log( 'HubGo Ajax Error: ' . $e->getMessage() );

            wp_send_json_error( array(
                'message' => esc_html__( 'Erro ao processar a requisição.', 'hubgo' ),
            ) );
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
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

        if ( ! wp_verify_nonce( $nonce, self::ADMIN_NONCE_ACTION ) ) {
            throw new Exception( 'Invalid nonce verification' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            throw new Exception( 'Insufficient user permissions' );
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

        $form_data_string = wp_unslash( $_POST['form_data'] );

        if ( ! is_string( $form_data_string ) || empty( $form_data_string ) ) {
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
        $existing_options = get_option( self::SETTINGS_OPTION_NAME, array() );

        $checkbox_fields = array(
            'enable_shipping_calculator',
            'enable_auto_shipping_calculator',
        );

        foreach ( $checkbox_fields as $field ) {
            $form_data[ $field ] = isset( $form_data[ $field ] ) ? 'yes' : 'no';
        }

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

            $product_id   = $this->get_sanitized_product_id();
            $variation_id = $this->get_sanitized_variation_id();
            $postcode     = $this->get_sanitized_postcode();
            $quantity     = $this->get_sanitized_quantity();

            if ( ! $product_id || empty( $postcode ) ) {
                $this->render_no_shipping_message();
                wp_die();
            }

            $rates = $this->get_shipping_rates( $product_id, $variation_id, $postcode, $quantity );

            if ( ! empty( $rates ) ) {
                $this->render_shipping_table( $rates );
            } else {
                $this->render_no_shipping_message();
            }

        } catch ( Exception $e ) {
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
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

        if ( ! wp_verify_nonce( $nonce, self::SHIPPING_NONCE_ACTION ) ) {
            throw new Exception( 'Invalid shipping nonce' );
        }
    }


    /**
     * Get sanitized product ID from request
     *
     * @since 2.0.0
     * @return int
     */
    private function get_sanitized_product_id() {
        return isset( $_POST['product'] ) ? absint( $_POST['product'] ) : 0;
    }


    /**
     * Get sanitized variation ID from request
     *
     * @since 2.0.0
     * @return int
     */
    private function get_sanitized_variation_id() {
        return isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
    }


    /**
     * Get sanitized postcode from request
     *
     * @since 2.0.0
     * @return string
     */
    private function get_sanitized_postcode() {
        $postcode = isset( $_POST['postcode'] ) ? sanitize_text_field( $_POST['postcode'] ) : '';

        // Normalize: keep digits only (BR CEP) if user typed mask.
        $postcode = preg_replace( '/[^0-9A-Za-z\- ]/', '', (string) $postcode );

        return (string) $postcode;
    }


    /**
     * Get sanitized quantity from request
     *
     * @since 2.0.0
     * @return int
     */
    private function get_sanitized_quantity() {
        $qty = isset( $_POST['qty'] ) ? absint( $_POST['qty'] ) : 1;

        return max( 1, $qty );
    }


    /**
     * Get shipping rates with in-request cache
     *
     * @since 2.0.0
     * @param int $product_id
     * @param int $variation_id
     * @param string $postcode
     * @param int $quantity
     * @return array
     */
    private function get_shipping_rates( $product_id, $variation_id, $postcode, $quantity ) {
        static $cached_rates = array();

        $cache_key = $product_id . '|' . $variation_id . '|' . $postcode . '|' . $quantity;

        if ( isset( $cached_rates[ $cache_key ] ) ) {
            return $cached_rates[ $cache_key ];
        }

        $rates = $this->calculate_rates( $product_id, $variation_id, $postcode, $quantity );

        $cached_rates[ $cache_key ] = $rates;

        return $rates;
    }


    /**
     * Calculate shipping rates (WooCommerce package calculation)
     *
     * @since 1.0.0
     * @version 2.0.0
     * @param int $product_id
     * @param int $variation_id
     * @param string $postcode
     * @param int $quantity
     * @return array
     */
    private function calculate_rates( $product_id, $variation_id, $postcode, $quantity ) {
        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return array();
        }

        $base_product = wc_get_product( $product_id );

        if ( ! $base_product || ! $base_product->needs_shipping() ) {
            return array();
        }

        if ( 'no' === get_option( 'woocommerce_calc_shipping' ) ) {
            return array();
        }

        if ( ! $base_product->is_in_stock() ) {
            return array();
        }

        // Ensure a country exists for postcode validation.
        $country = WC()->customer ? WC()->customer->get_shipping_country() : '';

        if ( empty( $country ) ) {
            $default_location = wc_get_customer_default_location();
            $country = isset( $default_location['country'] ) ? $default_location['country'] : '';
        }

        if ( empty( $postcode ) || ( $country && ! WC_Validation::is_postcode( $postcode, $country ) ) ) {
            return array();
        }

        // Use variation if provided.
        $product = $variation_id ? wc_get_product( $variation_id ) : $base_product;

        if ( ! $product ) {
            return array();
        }

        $destination = $this->get_destination_array( $postcode, $country );

        /**
         * Price + tax (avoid deprecated WC_Product methods).
         */
        $price      = (float) wc_get_price_excluding_tax( $product );
        $price_incl = (float) wc_get_price_including_tax( $product );
        $tax        = max( 0, $price_incl - $price );

        $package = array(
            'destination'     => $destination,
            'applied_coupons' => ( WC()->cart ) ? WC()->cart->get_applied_coupons() : array(),
            'user'            => array( 'ID' => get_current_user_id() ),
            'contents'        => array(),
            'contents_cost'   => 0,
        );

        $cart_id = ( WC()->cart )
            ? WC()->cart->generate_cart_id( $product_id, $variation_id )
            : md5( $product_id . ':' . $variation_id );

        $line_total = $price * $quantity;
        $line_tax   = $tax * $quantity;

        $package['contents'][ $cart_id ] = array(
            'product_id'        => $product_id,
            'variation_id'      => $variation_id,
            'data'              => $product,
            'quantity'          => $quantity,
            'line_total'        => $line_total,
            'line_tax'          => $line_tax,
            'line_subtotal'     => $line_total,
            'line_subtotal_tax' => $line_tax,
            'contents_cost'     => $line_total,
        );

        $package['contents_cost'] = $line_total;

        /**
         * Correios: declare value when enabled on method settings.
         */
        if ( class_exists( 'WC_Correios_Webservice' ) ) {
            add_filter( 'woocommerce_correios_shipping_args', function( $array, $this_id, $this_instance_id, $this_package ) use ( $price ) {
                $option_id = 'woocommerce_' . $this_id . '_' . $this_instance_id . '_settings';
                $settings  = get_option( $option_id );

                if ( isset( $settings['declare_value'] ) && 'yes' === $settings['declare_value'] ) {
                    $array['nVlValorDeclarado'] = $price;
                }

                return $array;
            }, 10, 4 );
        }

        /**
         * Check free shipping availability (for manual fallback if needed).
         */
        $is_free_available = false;
        $method_free       = null;

        $methods = WC_Shipping::instance()->load_shipping_methods( $package );

        foreach ( $methods as $method ) {
            if ( 'free_shipping' === $method->id && 'yes' === $method->enabled ) {
                $method_free = $method;

                $has_coupon         = false;
                $has_met_min_amount = false;

                if ( in_array( $method->requires, array( 'coupon', 'either', 'both' ), true ) ) {
                    if ( WC()->cart && ( $coupons = WC()->cart->get_coupons() ) ) {
                        foreach ( $coupons as $coupon ) {
                            if ( $coupon->is_valid() && $coupon->get_free_shipping() ) {
                                $has_coupon = true;
                                break;
                            }
                        }
                    }
                }

                if ( in_array( $method->requires, array( 'min_amount', 'either', 'both' ), true ) ) {
                    $_total = $price * $quantity;

                    if ( $_total >= (float) $method->min_amount ) {
                        $has_met_min_amount = true;
                    }
                }

                switch ( $method->requires ) {
                    case 'min_amount':
                        $is_free_available = $has_met_min_amount;
                        break;
                    case 'coupon':
                        $is_free_available = $has_coupon;
                        break;
                    case 'both':
                        $is_free_available = ( $has_met_min_amount && $has_coupon );
                        break;
                    case 'either':
                        $is_free_available = ( $has_met_min_amount || $has_coupon );
                        break;
                    default:
                        $is_free_available = false;
                        break;
                }

                break;
            }
        }

        /**
         * Some shipping plugins depend on cart contents.
         * If cart is empty, add a temporary item.
         */
        $temporary_cart_item_key = false;

        if ( WC()->cart && WC()->cart->is_empty() ) {
            $temporary_cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
            WC()->cart->calculate_totals();
        }

        /**
         * Calculate package rates.
         */
        $package_rates = WC_Shipping::instance()->calculate_shipping_for_package( $package );

        if ( $temporary_cart_item_key ) {
            WC()->cart->remove_cart_item( $temporary_cart_item_key );
            WC()->cart->calculate_totals();
        }

        $rates = array();

        /**
         * Detect if free_shipping already came in Woo results.
         */
        $has_free_rate = false;

        if ( isset( $package_rates['rates'] ) && is_array( $package_rates['rates'] ) ) {
            foreach ( $package_rates['rates'] as $r ) {
                if ( $r instanceof WC_Shipping_Rate && 'free_shipping' === $r->get_method_id() ) {
                    $has_free_rate = true;
                    break;
                }
            }
        }

        /**
         * Manual fallback: add free shipping only if available and not returned.
         */
        if ( $is_free_available && ! $has_free_rate ) {
            $rates[] = (object) array(
                'cost'  => 0,
                'label' => ( $method_free && ! empty( $method_free->method_title ) )
                    ? $method_free->method_title
                    : __( 'Frete grátis', 'hubgo' ),
            );
        }

        /**
         * Add real calculated rates and append delivery forecast if present.
         */
        if ( isset( $package_rates['rates'] ) && is_array( $package_rates['rates'] ) ) {
            foreach ( $package_rates['rates'] as $rate ) {
                if ( $rate instanceof WC_Shipping_Rate ) {
                    $meta = $rate->get_meta_data();

                    if ( isset( $meta['_delivery_forecast'] ) ) {
                        $delivery_forecast = $meta['_delivery_forecast'];

                        $translated_delivery_forecast = sprintf(
                            __( '(Entrega em %s dias úteis)', 'hubgo' ),
                            $delivery_forecast
                        );

                        $rate->set_label( $rate->get_label() . ' ' . $translated_delivery_forecast );
                    }
                }

                $rates[] = $rate;
            }
        }

        /**
         * Update customer postcode for consistency.
         */
        if ( WC()->customer ) {
            WC()->customer->set_shipping_postcode( $postcode );
            WC()->customer->set_billing_postcode( $postcode );
        }

        /**
         * Final de-duplication:
         * - WC_Shipping_Rate: dedupe by get_id()
         * - Fallback object: dedupe by label|cost
         */
        $unique = array();

        foreach ( $rates as $rate ) {
            if ( $rate instanceof WC_Shipping_Rate ) {
                $key = $rate->get_id();
            } else {
                $label = isset( $rate->label ) ? (string) $rate->label : '';
                $cost  = isset( $rate->cost ) ? (string) $rate->cost : '0';
                $key   = md5( $label . '|' . $cost );
            }

            if ( isset( $unique[ $key ] ) ) {
                continue;
            }

            $unique[ $key ] = $rate;
        }

        return array_values( $unique );
    }


    /**
     * Get destination array with fallbacks
     *
     * @since 2.0.0
     * @param string $postcode
     * @param string $country
     * @return array
     */
    private function get_destination_array( $postcode, $country ) {
        if ( WC()->customer ) {
            return array(
                'country'   => $country,
                'state'     => WC()->customer->get_shipping_state(),
                'postcode'  => $postcode,
                'city'      => WC()->customer->get_shipping_city(),
                'address'   => WC()->customer->get_shipping_address(),
                'address_2' => WC()->customer->get_shipping_address_2(),
            );
        }

        $default = wc_get_customer_default_location();

        return array(
            'country'   => isset( $default['country'] ) ? $default['country'] : $country,
            'state'     => isset( $default['state'] ) ? $default['state'] : '',
            'postcode'  => $postcode,
            'city'      => '',
            'address'   => '',
            'address_2' => '',
        );
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
                <?php if ( ! empty( $header_shipping ) || ! empty( $header_value ) ) : ?>
                    <tr class="hubgo-shipping-header">
                        <th><?php echo esc_html( $header_shipping ); ?></th>
                        <th><?php echo esc_html( $header_value ); ?></th>
                    </tr>
                <?php endif; ?>

                <?php foreach ( $rates as $rate ) : ?>
                    <?php
                    if ( $rate instanceof WC_Shipping_Rate ) {
                        $label = $rate->get_label();

                        $cost  = (float) $rate->get_cost();
                        $taxes = array_sum( (array) $rate->get_taxes() );
                        $total = $cost + (float) $taxes;

                        $price_html = wc_price( $total );
                    } else {
                        $label = isset( $rate->label ) ? (string) $rate->label : '';
                        $cost  = isset( $rate->cost ) ? (float) $rate->cost : 0;

                        $price_html = wc_price( $cost );
                    }
                    ?>
                    <tr class="hubgo-shipping-method">
                        <td><?php echo esc_html( $label ); ?></td>
                        <td><?php echo wp_kses_post( $price_html ); ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ( ! empty( $bottom_note ) ) : ?>
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