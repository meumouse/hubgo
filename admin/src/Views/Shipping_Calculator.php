<?php

namespace MeuMouse\Hubgo\Views;

use MeuMouse\Hubgo\Admin\Settings;
use MeuMouse\Hubgo\Core\Shipping_Calculator_Service;
use MeuMouse\Hubgo\Core\Shipping_Preference;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Shipping_Calculator
 *
 * Renders the storefront shipping calculator mount node. Since 3.1.0 the
 * calculator itself is a Vue application (app/src/storefront): this class only
 * prints the placeholder and the per-instance config the app reads from it.
 *
 * Rate calculation lives in Core\Shipping_Calculator_Service and is reached over
 * the `hubgo/v1/shipping/calculate` endpoint.
 *
 * {@see self::get_config()} is deliberately public and static: the product-page
 * hook, the shortcode and the Elementor widget all render the same node, and
 * having one place build that config is what keeps the three in step.
 *
 * @since 2.0.0
 * @version 3.1.0
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
            add_shortcode( self::SHORTCODE_TAG, array( $this, 'shortcode_render_form' ) );
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
     * Render shipping calculator mount node
     *
     * @since 2.0.0
     * @version 3.1.0
     * @return void
     */
    public function render_form() {
        if ( ! self::is_enabled() ) {
            return;
        }

        self::render_mount_node();
    }


    /**
     * Whether the calculator module is switched on.
     *
     * @since 3.1.0
     * @return bool
     */
    public static function is_enabled() {
        return 'yes' === Settings::get_setting('enable_shipping_calculator');
    }


    /**
     * Print the mount node the Vue app attaches to.
     *
     * @since 3.1.0
     * @param array $overrides Config overrides (used by the Elementor widget).
     * @return void
     */
    public static function render_mount_node( $overrides = array() ) {
        $config = self::get_config( $overrides );

        wc_get_template(
            'shipping-calculator.php',
            array( 'config' => $config ),
            '',
            trailingslashit( HUBGO_PATH ) . 'templates/'
        );
    }


    /**
     * Build the per-instance config the storefront app reads.
     *
     * Empty text values are meaningful: the Vue side hides the element a text
     * belongs to when it is blank, which is how a store owner removes the
     * title, the note or the address finder without a dedicated toggle.
     *
     * @since 3.1.0
     * @param array $overrides Values replacing the settings-derived defaults.
     * @return array<string,mixed>
     */
    public static function get_config( $overrides = array() ) {
        $overrides = is_array( $overrides ) ? $overrides : array();

        $config = array(
            'product'          => self::get_current_product_id(),
            'quantity'         => 1,
            'display'          => (string) Settings::get_setting( 'shipping_methods_display', 'list' ),
            'freeShippingHint' => self::get_free_shipping_hint(),
            'features'         => array(
                'badge'      => true,
                'finder'     => true,
                'options'    => true,
                'preference' => Shipping_Preference::is_enabled(),
                'auto'       => 'yes' === Settings::get_setting( 'enable_auto_shipping_calculator', 'no' ),
            ),
            'texts'            => array(
                'title'               => (string) Settings::get_setting( 'text_calculator_title', '' ),
                'info'                => (string) Settings::get_setting( 'text_info_before_input_shipping_calc', '' ),
                'placeholder'         => (string) Settings::get_setting( 'text_placeholder_input_shipping_calc', '' ),
                'button'              => (string) Settings::get_setting( 'text_button_shipping_calc', '' ),
                'finderLink'          => (string) Settings::get_setting( 'text_cep_finder_link', '' ),
                'moreOptions'         => (string) Settings::get_setting( 'text_more_options', '' ),
                'note'                => (string) Settings::get_setting( 'note_text_bottom_shipping_calc', '' ),
                'headerShip'          => (string) Settings::get_setting( 'text_header_ship', '' ),
                'headerValue'         => (string) Settings::get_setting( 'text_header_value', '' ),
                'freeShippingBadge'   => (string) Settings::get_setting( 'text_free_shipping_badge', '' ),
                'freeShippingActive'  => (string) Settings::get_setting( 'text_free_shipping_active', '' ),
                'preferenceHint'      => (string) Settings::get_setting( 'text_preference_hint', '' ),
                'preferenceSaved'     => (string) Settings::get_setting( 'text_preference_saved', '' ),
                'clearPreference'     => (string) Settings::get_setting( 'text_clear_preference', '' ),
            ),
        );

        // Nested arrays are merged one level deep so an override may replace a
        // single feature flag or a single string without restating the rest.
        foreach ( array( 'features', 'texts' ) as $group ) {
            if ( ! empty( $overrides[ $group ] ) && is_array( $overrides[ $group ] ) ) {
                $config[ $group ] = array_merge( $config[ $group ], $overrides[ $group ] );
                unset( $overrides[ $group ] );
            }
        }

        $config = array_merge( $config, $overrides );

        /**
         * Filters the config handed to a storefront calculator instance.
         *
         * @since 3.1.0
         * @param array<string,mixed> $config Instance config.
         * @param array<string,mixed> $overrides Overrides that were applied.
         */
        return apply_filters( 'Hubgo/Shipping_Calculator/Config', $config, $overrides );
    }


    /**
     * Formatted free-shipping threshold shown before the first quote.
     *
     * Only the manually configured value can be known here: the zone-derived
     * one depends on a destination the shopper has not typed yet.
     *
     * @since 3.1.0
     * @return string
     */
    private static function get_free_shipping_hint() {
        $threshold = Settings::get_setting( 'free_shipping_threshold', '' );

        if ( ! is_scalar( $threshold ) || '' === trim( (string) $threshold ) || ! is_numeric( $threshold ) ) {
            return '';
        }

        return Shipping_Calculator_Service::format_price( (float) $threshold );
    }


    /**
     * Resolve the product the calculator should quote.
     *
     * @since 3.1.0
     * @return int
     */
    private static function get_current_product_id() {
        if ( function_exists( 'is_product' ) && is_product() ) {
            return absint( get_queried_object_id() );
        }

        if ( isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof \WC_Product ) {
            return absint( $GLOBALS['product']->get_id() );
        }

        return 0;
    }


    /**
     * Render via shortcode
     *
     * @since 2.0.0
     * @version 3.1.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function shortcode_render_form( $atts ) {
        if ( ! self::is_enabled() ) {
            return '';
        }

        $atts = shortcode_atts( array(
            'product'  => 0,
            'quantity' => 1,
        ), (array) $atts, self::SHORTCODE_TAG );

        $overrides = array();

        if ( absint( $atts['product'] ) ) {
            $overrides['product'] = absint( $atts['product'] );
        }

        if ( absint( $atts['quantity'] ) ) {
            $overrides['quantity'] = absint( $atts['quantity'] );
        }

        // A shortcode dropped outside a product context has nothing to quote,
        // so it renders nothing rather than an input that can never succeed.
        if ( empty( $overrides['product'] ) && ! self::get_current_product_id() ) {
            return '';
        }

        ob_start();

        self::render_mount_node( $overrides );

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
