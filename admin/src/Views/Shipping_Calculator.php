<?php

namespace MeuMouse\Hubgo\Views;

use MeuMouse\Hubgo\Admin\Settings;
use MeuMouse\Hubgo\Core\Shipping_Calculator_Service;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Shipping_Calculator
 *
 * Renders the storefront shipping calculator form. Rate calculation itself
 * lives in Core\Shipping_Calculator_Service and is reached over the
 * `hubgo/v1/shipping/calculate` endpoint.
 *
 * @since 2.0.0
 * @version 3.0.1
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
     * Constructor
     *
     * @since 2.0.0
     * @version 3.0.1
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
     * Kept for backwards compatibility. Calculation moved to
     * Core\Shipping_Calculator_Service in 3.0.1, so this now returns
     * normalized rows instead of WC_Shipping_Rate objects.
     *
     * @since 2.0.0
     * @version 3.0.1
     * @param int $product_id Product ID
     * @param string $postcode Shipping postcode
     * @param int $quantity Product quantity
     * @return array<int,array<string,mixed>>
     */
    public function get_rates( $product_id, $postcode, $quantity ) {
        $service = new Shipping_Calculator_Service();

        return $service->calculate( $product_id, 0, $postcode, $quantity );
    }
}