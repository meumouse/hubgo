<?php

namespace MeuMouse\Hubgo\Views;

use MeuMouse\Hubgo\Admin\Settings;

use WC_Shipping;
use WC_Validation;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Shipping_Calculator
 *
 * Manages shipping calculator form rendering and rate calculation
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Views
 * @author MeuMouse.com
 */
class Shipping_Calculator {

    /**
     * Default postcode helper URL
     *
     * @since 2.0.0
     * @var string
     */
    const DEFAULT_POSTCODE_HELPER = 'https://buscacepinter.correios.com.br/app/endereco/';

    /**
     * Shortcode tag
     *
     * @since 2.0.0
     * @var string
     */
    const SHORTCODE_TAG = 'hubgo_shipping_calculator';

    /**
     * Cache for shipping rates
     *
     * @since 2.0.0
     * @var array
     */
    private static $rates_cache = array();


    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->init_hooks();
        $this->ensure_geolocation();
    }


    /**
     * Initialize WordPress hooks
     *
     * @since 2.0.0
     * @return void
     */
    private function init_hooks() {
        $hook_position = $this->get_hook_position();

        if ( $this->is_shortcode_only( $hook_position ) ) {
            add_shortcode( self::SHORTCODE_TAG, array( $this, 'shortcode_render_form' ) );
        } else {
            add_action( $hook_position, array( $this, 'render_form' ), 10 );
        }
    }


    /**
     * Get hook position from settings
     *
     * @since 2.0.0
     * @return string
     */
    private function get_hook_position() {
        $selected = Settings::get_setting('hook_display_shipping_calculator');

        $positions = apply_filters( 'Hubgo/Shipping_Calculator/Positions', array(
            'after_cart'    => 'woocommerce_after_add_to_cart_form',
            'before_cart'   => 'woocommerce_before_add_to_cart_form',
            'meta_end'      => 'woocommerce_product_meta_end',
        ));
        
        // Return mapped hook if exists
        if ( ! empty( $selected ) && isset( $positions[ $selected ] ) ) {
            return $positions[ $selected ];
        }

        // Fallback to shortcode if nothing matches
        return 'shortcode';
    }


    /**
     * Check if should use shortcode only
     *
     * @since 2.0.0
     * @param string $hook_position
     * @return bool
     */
    private function is_shortcode_only( $hook_position ) {
        $valid_hooks = array(
            'woocommerce_after_add_to_cart_form',
            'woocommerce_before_add_to_cart_form',
            'woocommerce_product_meta_end',
        );

        return ! in_array( $hook_position, $valid_hooks, true );
    }


    /**
     * Ensure geolocation is enabled
     *
     * @since 2.0.0
     * @return void
     */
    private function ensure_geolocation() {
        $current_address = get_option( 'woocommerce_default_customer_address', '' );
        
        if ( empty( $current_address ) ) {
            update_option( 'woocommerce_default_customer_address', 'geolocation' );
        }
    }


    /**
     * Render shipping calculator form
     *
     * @since 2.0.0
     * @return void
     */
    public function render_form() {
        $is_enabled = Settings::get_setting('enable_shipping_calculator');

        if ( 'yes' !== $is_enabled ) {
            return;
        }

        $this->render_form_html();
    }


    /**
     * Render form HTML
     *
     * @since 2.0.0
     * @return void
     */
    private function render_form_html() {
        $info_text = $this->get_setting_text('text_info_before_input_shipping_calc');
        $placeholder = $this->get_setting_text('text_placeholder_input_shipping_calc');
        $button_text = $this->get_setting_text('text_button_shipping_calc');
        $postcode_helper_url = $this->get_postcode_helper_url(); ?>

        <div id="hubgo-shipping-calc">
            <?php if ( ! empty( $info_text ) ) : ?>
                <span class="hubgo-info-shipping-calc">
                    <?php echo esc_html( $info_text ); ?>
                </span>
            <?php endif; ?>
            
            <div class="hubgo-form-group">
                <input 
                    type="text"
                    id="hubgo-postcode" 
                    name="hubgo-postcode"
                    placeholder="<?php echo esc_attr( $placeholder ); ?>"
                    class="hubgo-postcode-input"
                    autocomplete="postal-code"
                >

                <button 
                    type="button"
                    id="hubgo-shipping-calc-button"
                    class="hubgo-shipping-calc-button"
                    aria-label="<?php echo esc_attr( $button_text ); ?>"
                >
                    <?php echo esc_html( $button_text ); ?>
                </button>
            </div>
            
            <a 
                class="hubgo-postcode-search" 
                href="<?php echo esc_url( $postcode_helper_url ); ?>" 
                target="_blank" 
                rel="noopener noreferrer"
            >
                <?php echo esc_html__( 'Não sei meu CEP', 'hubgo' ); ?>
            </a>
            
            <div id="hubgo-response" aria-live="polite"></div>
        </div>
        <?php
    }


    /**
     * Get setting text with fallback
     *
     * @since 2.0.0
     * @param string $setting_key
     * @return string
     */
    private function get_setting_text( $setting_key ) {
        $value = Settings::get_setting( $setting_key );
        
        return ! empty( $value ) ? $value : '';
    }


    /**
     * Get postcode helper URL with filter
     *
     * @since 2.0.0
     * @return string
     */
    private function get_postcode_helper_url() {
        $url = apply_filters( 'Hubgo/Shipping_Calculator/Postcode_Helper', self::DEFAULT_POSTCODE_HELPER );

        return $url;
    }


    /**
     * Render form via shortcode
     *
     * @since 2.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function shortcode_render_form( $atts ) {
        if ( ! is_product() ) {
            return '';
        }

        ob_start();

        $this->render_form();
        
        return ob_get_clean();
    }


    /**
     * Get shipping rates for product
     *
     * @since 2.0.0
     * @param int $product_id Product ID
     * @param string $postcode Shipping postcode
     * @param int $quantity Product quantity
     * @return array
     */
    public function get_rates( $product_id, $postcode, $quantity ) {
        // Check cache first
        $cache_key = $this->get_rates_cache_key( $product_id, $postcode, $quantity );
        
        if ( isset( self::$rates_cache[ $cache_key ] ) ) {
            return self::$rates_cache[ $cache_key ];
        }

        // Validate and calculate rates
        $rates = $this->calculate_rates( $product_id, $postcode, $quantity );
        
        // Cache the results
        self::$rates_cache[ $cache_key ] = $rates;

        return $rates;
    }


    /**
     * Generate cache key for rates
     *
     * @since 2.0.0
     * @param int $product_id
     * @param string $postcode
     * @param int $quantity
     * @return string
     */
    private function get_rates_cache_key( $product_id, $postcode, $quantity ) {
        return md5( $product_id . '|' . $postcode . '|' . $quantity );
    }


    /**
     * Calculate shipping rates
     *
     * @since 2.0.0
     * @param int $product_id
     * @param string $postcode
     * @param int $quantity
     * @return array
     */
    private function calculate_rates( $product_id, $postcode, $quantity ) {
        // Validate before calculation
        if ( ! $this->can_calculate_rates( $product_id, $postcode ) ) {
            return array();
        }

        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            return array();
        }

        // Prepare package for shipping calculation
        $package = $this->prepare_shipping_package( $product, $postcode, $quantity );
        
        // Apply filters for external integrations
        $package = apply_filters( 'Hubgo/Shipping_Calculator/Package', $package, $product_id, $postcode, $quantity );

        // Calculate shipping for package
        $package_rates = WC_Shipping::instance()->calculate_shipping_for_package( $package );
        
        // Extract rates
        $rates = $this->extract_rates_from_package( $package_rates );

        // Apply final filters
        return apply_filters( 'Hubgo/Shipping_Calculator/Rates', $rates, $package );
    }


    /**
     * Check if rates can be calculated
     *
     * @since 2.0.0
     * @param int $product_id
     * @param string $postcode
     * @return bool
     */
    private function can_calculate_rates( $product_id, $postcode ) {
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return false;
        }

        // Check if product needs shipping
        if ( ! $product->needs_shipping() ) {
            return false;
        }

        // Check if shipping is enabled
        if ( 'no' === get_option( 'woocommerce_calc_shipping' ) ) {
            return false;
        }

        // Check if product is in stock
        if ( ! $product->is_in_stock() ) {
            return false;
        }

        // Validate postcode format
        $country = WC()->customer->get_shipping_country();
        
        if ( ! WC_Validation::is_postcode( $postcode, $country ) ) {
            return false;
        }

        return true;
    }


    /**
     * Prepare shipping package for calculation
     *
     * @since 2.0.0
     * @param object $product Product object
     * @param string $postcode
     * @param int $quantity
     * @return array
     */
    private function prepare_shipping_package( $product, $postcode, $quantity ) {
        $destination = $this->get_destination_array( $postcode );
        
        $price = $product->get_price_excluding_tax();
        $tax = $product->get_price_including_tax() - $price;
        
        $cart_id = $this->generate_cart_id( $product );
        $total_cost = $price * $quantity;

        $package = array(
            'destination'     => $destination,
            'applied_coupons' => WC()->cart->get_applied_coupons(),
            'user'            => array( 'ID' => get_current_user_id() ),
            'contents'        => array(),
            'contents_cost'   => 0,
        );

        $package['contents'][ $cart_id ] = array(
            'product_id'        => $product->get_id(),
            'variation_id'      => $this->get_variation_id( $product ),
            'data'              => $product,
            'quantity'          => $quantity,
            'line_total'        => $total_cost,
            'line_tax'          => $tax * $quantity,
            'line_subtotal'     => $total_cost,
            'line_subtotal_tax' => $tax * $quantity,
            'contents_cost'     => $total_cost,
        );
        
        $package['contents_cost'] = $total_cost;

        return $package;
    }


    /**
     * Get destination array
     *
     * @since 2.0.0
     * @param string $postcode
     * @return array
     */
    private function get_destination_array( $postcode ) {
        return array(
            'country'   => WC()->customer->get_shipping_country(),
            'state'     => WC()->customer->get_shipping_state(),
            'postcode'  => $postcode,
            'city'      => WC()->customer->get_shipping_city(),
            'address'   => WC()->customer->get_shipping_address(),
            'address_2' => WC()->customer->get_shipping_address_2(),
        );
    }


    /**
     * Generate cart ID for package
     *
     * @since 2.0.0
     * @param object $product
     * @return string
     */
    private function generate_cart_id( $product ) {
        $variation_id = $this->get_variation_id( $product );
        
        return WC()->cart->generate_cart_id( $product->get_id(), $variation_id );
    }


    /**
     * Get variation ID if product is variable
     *
     * @since 2.0.0
     * @param object $product
     * @return int
     */
    private function get_variation_id( $product ) {
        return $product->is_type( 'variable' ) ? $product->get_id() : 0;
    }


    /**
     * Extract rates from package calculation
     *
     * @since 2.0.0
     * @param array $package_rates
     * @return array
     */
    private function extract_rates_from_package( $package_rates ) {
        $rates = array();

        if ( isset( $package_rates['rates'] ) && is_array( $package_rates['rates'] ) ) {
            foreach ( $package_rates['rates'] as $rate ) {
                $rates[] = $rate;
            }
        }

        return $rates;
    }
}