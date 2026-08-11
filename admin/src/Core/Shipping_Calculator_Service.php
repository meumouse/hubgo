<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Core\Address\Address_Provider;
use MeuMouse\Hubgo\Core\Address\Address_Service;
use MeuMouse\Hubgo\Core\Delivery_Estimate;
use MeuMouse\Hubgo\Core\Free_Shipping_Context;
use MeuMouse\Hubgo\Core\Postcode_Locator;

use WC_Cache_Helper;
use WC_Shipping;
use WC_Shipping_Rate;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WC_Validation;

defined('ABSPATH') || exit;

/**
 * Shipping calculation service.
 *
 * Computes shipping rates for a single product without rendering HTML, so the
 * REST endpoint (and any other caller) can quote a postcode from the product
 * page. Returns normalized rows carrying the WooCommerce rate id, the cost and
 * the delivery forecast — see {@see self::normalize_rows()} for the shape.
 *
 * Zone resolution notes (WooCommerce >= 6.0):
 * WooCommerce matches a package to a single zone using country + state +
 * postcode and picks the first one by `zone_order`. The postcode can only
 * exclude zones that declare postcode rules, so the package *must* carry the
 * state that belongs to the informed postcode — otherwise a state zone with a
 * lower order wins over the zone the shopper actually falls into. The state is
 * therefore derived from the postcode (see Postcode_Locator), never read from
 * the customer session, which is stale on the product page and entirely absent
 * on REST requests.
 *
 * The service is side-effect free: it never writes to the cart, the customer
 * session or the checkout rate cache.
 *
 * @since 3.0.0
 * @version 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Shipping_Calculator_Service {

    /**
     * Transient prefix for calculated quotes.
     *
     * The suffix is bumped whenever the cached payload shape changes, so an
     * upgrade never serves a row set the new storefront cannot read.
     *
     * @since 3.0.1
     * @version 3.0.0
     * @var string
     */
    const CACHE_PREFIX = 'hubgo_ship_v2_';

    /**
     * Quote cache lifetime, in seconds.
     *
     * @since 3.0.1
     * @var int
     */
    const CACHE_TTL = 300;

    /**
     * Zone matched on the last calculation: [ 'id' => int, 'name' => string ].
     *
     * @since 3.0.1
     * @var array
     */
    private $matched_zone = array();

    /**
     * Zone object matched on the last calculation, when there was one.
     *
     * Kept alongside the id/name pair because the free-shipping context has to
     * read the zone's method instances, not just identify it.
     *
     * @since 3.0.0
     * @var WC_Shipping_Zone|null
     */
    private $matched_zone_object = null;

    /**
     * Free-shipping context produced by the last calculation.
     *
     * @since 3.0.0
     * @var array
     */
    private $free_shipping = array();

    /**
     * Destination context resolved on the last calculation.
     *
     * @since 3.0.0
     * @var array
     */
    private $context = array();

    /**
     * Error code produced by the last calculation, empty when it succeeded.
     *
     * @since 3.0.1
     * @var string
     */
    private $last_error = '';


    /**
     * Calculate normalized shipping rows for a product/postcode.
     *
     * @since 3.0.0
     * @version 3.0.0
     * @param int $product_id Product ID.
     * @param int $variation_id Variation ID (optional).
     * @param string $postcode Destination postcode.
     * @param int $quantity Quantity.
     * @param string $country Destination country code (optional).
     * @return array<int,array<string,mixed>>
     */
    public function calculate( $product_id, $variation_id, $postcode, $quantity, $country = '' ) {
        // Seeded with fully-shaped empties so an early return still answers the
        // storefront with the object it expects — an empty PHP array would
        // serialize as a JSON array and break every property read on it.
        $this->matched_zone        = array();
        $this->matched_zone_object = null;
        $this->free_shipping       = Free_Shipping_Context::get_empty();
        $this->context             = self::get_empty_context();
        $this->last_error          = '';

        $product_id   = absint( $product_id );
        $variation_id = absint( $variation_id );
        $quantity     = max( 1, absint( $quantity ) );
        $postcode     = $this->normalize_postcode( $postcode );

        if ( ! $product_id && $variation_id ) {
            $product_id = $this->get_parent_product_id( $variation_id );
        }

        if ( ! $product_id ) {
            $this->last_error = 'invalid_product';

            return array();
        }

        if ( '' === $postcode ) {
            $this->last_error = 'invalid_postcode';

            return array();
        }

        if ( ! function_exists( 'WC' ) || ! WC() ) {
            $this->last_error = 'woocommerce_unavailable';

            return array();
        }

        $country       = $this->resolve_country( $country );
        $this->context = $this->build_context( $postcode, $country );

        $cache_key = $this->get_cache_key( $product_id, $variation_id, $quantity, $postcode, $country );
        $cached    = get_transient( $cache_key );

        if ( is_array( $cached ) && isset( $cached['rows'] ) ) {
            $this->matched_zone  = isset( $cached['zone'] ) ? (array) $cached['zone'] : array();
            $this->free_shipping = isset( $cached['free_shipping'] ) ? (array) $cached['free_shipping'] : array();

            return $cached['rows'];
        }

        $rows = $this->normalize_rows(
            $this->calculate_rates( $product_id, $variation_id, $postcode, $quantity, $country )
        );

        // Only cache settled results. Errors depend on stock and configuration
        // that can change at any moment, so they must be re-evaluated.
        if ( '' === $this->last_error ) {
            set_transient( $cache_key, array(
                'rows'          => $rows,
                'zone'          => $this->matched_zone,
                'free_shipping' => $this->free_shipping,
            ), self::CACHE_TTL );
        }

        return $rows;
    }


    /**
     * Get the free-shipping context of the last calculation.
     *
     * @since 3.0.0
     * @return array Empty array when no calculation ran.
     */
    public function get_free_shipping_context() {
        return $this->free_shipping;
    }


    /**
     * Get the destination context of the last calculation.
     *
     * @since 3.0.0
     * @return array
     */
    public function get_context() {
        return $this->context;
    }


    /**
     * Build the destination context echoed back to the storefront.
     *
     * The formatted postcode and the resolved state let the client render the
     * destination without re-deriving anything the server already knows. Since
     * 3.0.0 it also carries the street the postcode belongs to, which is what
     * lets the card name the destination instead of repeating the digits.
     *
     * The address is resolved outside the quote cache on purpose: it is keyed on
     * the postcode alone, so one lookup serves every product, quantity and
     * shopper, and it survives the far shorter quote TTL. {@see Address_Service}
     * answers with empty strings whenever the feature is off, over budget or
     * simply slow, so nothing here has to guard against it.
     *
     * @since 3.0.0
     * @version 3.0.0
     * @param string $postcode Normalized postcode.
     * @param string $country Resolved country code.
     * @return array<string,mixed>
     */
    private function build_context( $postcode, $country ) {
        return array(
            'postcode'           => $postcode,
            'formatted_postcode' => wc_format_postcode( $postcode, $country ),
            'state'              => Postcode_Locator::get_state( $postcode, $country ),
            'country'            => $country,
            'currency'           => get_woocommerce_currency(),
            'address'            => Address_Service::lookup_postcode( $postcode, $country ),
        );
    }


    /**
     * Destination context shape used before (or instead of) a calculation.
     *
     * @since 3.0.0
     * @version 3.0.0
     * @return array<string,mixed>
     */
    private static function get_empty_context() {
        return array(
            'postcode'           => '',
            'formatted_postcode' => '',
            'state'              => '',
            'country'            => '',
            'currency'           => '',
            'address'            => Address_Provider::get_empty_address(),
        );
    }


    /**
     * Get the zone matched by the last calculation.
     *
     * @since 3.0.1
     * @return array Empty array when no calculation ran, otherwise [ 'id', 'name' ].
     */
    public function get_matched_zone() {
        return $this->matched_zone;
    }


    /**
     * Get the error code produced by the last calculation.
     *
     * @since 3.0.1
     * @return string Empty string when the last calculation succeeded.
     */
    public function get_last_error() {
        return $this->last_error;
    }


    /**
     * Normalize WC rate objects to plain rows.
     *
     * `label`, `cost` and `cost_formatted` are the original 3.0.0 contract and
     * must stay: they are what every published consumer of this service reads.
     * Everything else is additive — the rate id is what makes a row selectable
     * as the shopper's preferred method (see {@see Shipping_Preference}).
     *
     * @since 3.0.0
     * @version 3.1.0
     * @param array $rates Rate objects.
     * @return array<int,array<string,mixed>>
     */
    private function normalize_rows( $rates ) {
        $rows = array();

        foreach ( $rates as $rate ) {
            if ( ! $rate instanceof WC_Shipping_Rate ) {
                continue;
            }

            $cost  = (float) $rate->get_cost();
            $taxes = array_sum( (array) $rate->get_taxes() );
            $total = $cost + (float) $taxes;

            $rows[] = array(
                'id'             => (string) $rate->get_id(),
                'method_id'      => (string) $rate->get_method_id(),
                'instance_id'    => (int) $rate->get_instance_id(),
                'label'          => $rate->get_label(),
                'cost'           => $total,
                'cost_formatted' => self::format_price( $total ),
                'is_free'        => 0.0 === $total,
                'delivery'       => Delivery_Estimate::for_rate( $rate ),
                'meta'           => $this->normalize_rate_meta( $rate ),
            );
        }

        return $this->sort_rows( $rows );
    }


    /**
     * Order the rows the way the storefront reads them: cheapest first.
     *
     * WooCommerce hands the rates back in the order their shipping methods were
     * registered, which puts whatever the store happened to add first at the top
     * of the list. The compact card already headlines the cheapest option
     * (see `resolvePreferredRate` on the JS side), so an unordered modal
     * contradicts the very card that opened it — and a free rate, which is the
     * one the shopper is looking for, could sit anywhere in the list.
     *
     * Ties fall back to the label so two identical quotes never reshuffle:
     * `usort` is not stable before PHP 8.0, and this plugin still supports 7.4.
     *
     * @since 3.1.0
     * @param array<int,array<string,mixed>> $rows Normalized rows.
     * @return array<int,array<string,mixed>>
     */
    private function sort_rows( $rows ) {
        usort( $rows, function( $a, $b ) {
            $cost_a = (float) $a['cost'];
            $cost_b = (float) $b['cost'];

            if ( $cost_a === $cost_b ) {
                return strcmp( (string) $a['label'], (string) $b['label'] );
            }

            return ( $cost_a < $cost_b ) ? -1 : 1;
        } );

        /**
         * Filters the ordered rate rows before they reach the storefront.
         *
         * Runs after the default cheapest-first ordering, so a store that
         * promises a carrier the top slot can restore its own order here
         * without having to re-normalize the rows.
         *
         * @since 3.1.0
         * @param array<int,array<string,mixed>> $rows Rows, cheapest first.
         */
        return array_values( (array) apply_filters( 'Hubgo/Shipping_Calculator/Rates_Order', $rows ) );
    }


    /**
     * Flatten a rate's meta data into scalar strings.
     *
     * Carriers occasionally store objects or arrays in there; passing those
     * straight to `wp_json_encode()` leaks internals into a public response, so
     * only scalars survive the trip to the storefront.
     *
     * @since 3.0.0
     * @param WC_Shipping_Rate $rate Rate to read.
     * @return array<string,string>
     */
    private function normalize_rate_meta( $rate ) {
        $meta = array();

        foreach ( (array) $rate->get_meta_data() as $key => $value ) {
            if ( ! is_scalar( $value ) ) {
                continue;
            }

            $meta[ sanitize_key( (string) $key ) ] = wp_strip_all_tags( (string) $value );
        }

        return $meta;
    }


    /**
     * Format an amount as plain text, safe to render as a JSON string.
     *
     * `wc_price()` returns markup whose currency symbol is HTML-encoded
     * (`&#82;&#36;`). Stripping the tags leaves those entities behind, and a
     * client that escapes on output — the Vue storefront does, correctly —
     * prints them literally: "&#82;&#36;&nbsp;100,00" instead of "R$ 100,00".
     * Decoding is what makes the string safe to hand to a non-HTML consumer.
     *
     * @since 3.0.0
     * @param float $amount Amount in store currency.
     * @return string
     */
    public static function format_price( $amount ) {
        return html_entity_decode(
            wp_strip_all_tags( wc_price( (float) $amount ) ),
            ENT_QUOTES,
            'UTF-8'
        );
    }


    /**
     * Normalize postcode (keep alphanumerics/dash/space).
     *
     * @since 3.0.0
     * @param string $postcode Raw postcode.
     * @return string
     */
    private function normalize_postcode( $postcode ) {
        $postcode = sanitize_text_field( (string) $postcode );

        return (string) preg_replace( '/[^0-9A-Za-z\- ]/', '', $postcode );
    }


    /**
     * Resolve parent product ID from a variation.
     *
     * @since 3.0.0
     * @param int $variation_id Variation ID.
     * @return int
     */
    private function get_parent_product_id( $variation_id ) {
        if ( ! $variation_id ) {
            return 0;
        }

        $variation = wc_get_product( $variation_id );

        if ( $variation instanceof \WC_Product_Variation ) {
            return absint( $variation->get_parent_id() );
        }

        return absint( wp_get_post_parent_id( $variation_id ) );
    }


    /**
     * Resolve the destination country for the quote.
     *
     * The calculator input is a domestic postcode field, so the store base
     * country is the sane target. WooCommerce's own fallback
     * (`wc_get_customer_default_location()`) may return a geolocated country,
     * which makes a shopper behind a VPN fail postcode validation and get an
     * empty result with no explanation.
     *
     * @since 3.0.1
     * @param string $requested Country code sent by the client, if any.
     * @return string
     */
    private function resolve_country( $requested ) {
        $requested = strtoupper( sanitize_text_field( (string) $requested ) );
        $allowed   = array_keys( WC()->countries->get_shipping_countries() );
        $country   = '';

        if ( '' !== $requested && in_array( $requested, $allowed, true ) ) {
            $country = $requested;
        }

        if ( '' === $country ) {
            $country = (string) WC()->countries->get_base_country();
        }

        if ( '' === $country ) {
            $default = wc_get_customer_default_location();
            $country = isset( $default['country'] ) ? (string) $default['country'] : '';
        }

        /**
         * Filters the destination country used by the shipping calculator.
         *
         * @since 3.0.1
         * @param string $country Resolved country code.
         * @param string $requested Country code requested by the client.
         */
        return apply_filters( 'Hubgo/Shipping_Calculator/Country', $country, $requested );
    }


    /**
     * Build the package destination.
     *
     * The state comes from the postcode and the address fields are left empty
     * on purpose: sending the session's city/address for a postcode the shopper
     * has just typed feeds carriers (Correios, Melhor Envio) an address that
     * does not exist, and sending the session's state breaks zone priority.
     *
     * @since 3.0.0
     * @version 3.0.1
     * @param string $postcode Postcode.
     * @param string $country Country.
     * @return array
     */
    private function get_destination_array( $postcode, $country ) {
        $destination = array(
            'country'   => $country,
            'state'     => Postcode_Locator::get_state( $postcode, $country ),
            'postcode'  => wc_format_postcode( $postcode, $country ),
            'city'      => '',
            'address'   => '',
            'address_2' => '',
        );

        /**
         * Filters the destination used to match a shipping zone.
         *
         * @since 3.0.1
         * @param array $destination Destination address parts.
         * @param string $postcode Postcode informed by the shopper.
         * @param string $country Resolved country code.
         */
        return apply_filters( 'Hubgo/Shipping_Calculator/Destination', $destination, $postcode, $country );
    }


    /**
     * Build the cache key for a quote.
     *
     * Includes WooCommerce's shipping transient version so the cache is dropped
     * automatically whenever zones or methods change.
     *
     * @since 3.0.1
     * @param int $product_id Product ID.
     * @param int $variation_id Variation ID.
     * @param int $quantity Quantity.
     * @param string $postcode Postcode.
     * @param string $country Country.
     * @return string
     */
    private function get_cache_key( $product_id, $variation_id, $quantity, $postcode, $country ) {
        $parts = implode( '|', array(
            $product_id,
            $variation_id,
            $quantity,
            wc_normalize_postcode( $postcode ),
            $country,
            get_current_user_id(),
            // Rows carry formatted prices, so a multi-currency switch must miss.
            get_woocommerce_currency(),
            WC_Cache_Helper::get_transient_version( 'shipping' ),
        ) );

        return self::CACHE_PREFIX . md5( $parts );
    }


    /**
     * Make sure the WooCommerce session and cart objects exist.
     *
     * WooCommerce only calls `wc_load_cart()` for frontend requests, and
     * `WooCommerce::is_request( 'frontend' )` is false for anything under the
     * REST prefix. Without a cart, `WC_Shipping_Free_Shipping::is_available()`
     * fatals on `WC()->cart->get_coupons()`. The objects are only read here,
     * never written to.
     *
     * @since 3.0.1
     * @return void
     */
    private function ensure_wc_session() {
        if ( ! is_null( WC()->cart ) && ! is_null( WC()->session ) ) {
            return;
        }

        if ( function_exists( 'wc_load_cart' ) && did_action( 'woocommerce_init' ) ) {
            wc_load_cart();
        }
    }


    /**
     * Calculate shipping rates for a single product package.
     *
     * @since 3.0.0
     * @version 3.0.1
     * @param int $product_id Product ID.
     * @param int $variation_id Variation ID.
     * @param string $postcode Postcode.
     * @param int $quantity Quantity.
     * @param string $country Destination country.
     * @return array<int,WC_Shipping_Rate>
     */
    private function calculate_rates( $product_id, $variation_id, $postcode, $quantity, $country ) {
        if ( ! wc_shipping_enabled() ) {
            $this->last_error = 'shipping_disabled';

            return array();
        }

        $base_product = wc_get_product( $product_id );

        if ( ! $base_product || ! $base_product->needs_shipping() ) {
            $this->last_error = 'not_shippable';

            return array();
        }

        if ( ! $base_product->is_in_stock() ) {
            $this->last_error = 'out_of_stock';

            return array();
        }

        if ( '' === $country || ! WC_Validation::is_postcode( $postcode, $country ) ) {
            $this->last_error = 'invalid_postcode';

            return array();
        }

        $product = $variation_id ? wc_get_product( $variation_id ) : $base_product;

        if ( ! $product ) {
            $this->last_error = 'invalid_product';

            return array();
        }

        $this->ensure_wc_session();

        $package = $this->build_package( $product, $product_id, $variation_id, $quantity, $postcode, $country );

        $declared_value_filter = $this->add_correios_declared_value_filter( $package );

        $rates = $this->get_zone_rates( $package );

        if ( $declared_value_filter ) {
            remove_filter( 'woocommerce_correios_shipping_args', $declared_value_filter, 10 );
        }

        return $this->decorate_rates( $rates, $package );
    }


    /**
     * Build the shipping package for a single product line.
     *
     * @since 3.0.1
     * @param \WC_Product $product Product (or variation) being quoted.
     * @param int $product_id Parent product ID.
     * @param int $variation_id Variation ID.
     * @param int $quantity Quantity.
     * @param string $postcode Postcode.
     * @param string $country Country.
     * @return array
     */
    private function build_package( $product, $product_id, $variation_id, $quantity, $postcode, $country ) {
        $price      = (float) wc_get_price_excluding_tax( $product );
        $price_incl = (float) wc_get_price_including_tax( $product );
        $tax        = max( 0, $price_incl - $price );

        $line_total = $price * $quantity;
        $line_tax   = $tax * $quantity;

        $cart_id = ( WC()->cart )
            ? WC()->cart->generate_cart_id( $product_id, $variation_id )
            : md5( $product_id . ':' . $variation_id );

        $package = array(
            // Marks the package as ours so scoped filters can identify it.
            'hubgo_calculator' => true,
            'destination'      => $this->get_destination_array( $postcode, $country ),
            'applied_coupons'  => ( WC()->cart ) ? WC()->cart->get_applied_coupons() : array(),
            'user'             => array( 'ID' => get_current_user_id() ),
            'contents'         => array(),
            'contents_cost'    => $line_total,
        );

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

        /**
         * Filters the package sent to the shipping methods.
         *
         * @since 2.0.0
         * @param array $package Shipping package.
         * @param int $product_id Parent product ID.
         * @param string $postcode Postcode informed by the shopper.
         * @param int $quantity Quantity.
         */
        return apply_filters( 'Hubgo/Shipping_Calculator/Package', $package, $product_id, $postcode, $quantity );
    }


    /**
     * Resolve the matching zone and collect its rates.
     *
     * Mirrors WC_Shipping::calculate_shipping_for_package() minus the session
     * cache, which lives in the same slot the checkout uses and would both
     * pollute and be polluted by it.
     *
     * @since 3.0.1
     * @param array $package Shipping package.
     * @return array<string,WC_Shipping_Rate>
     */
    private function get_zone_rates( $package ) {
        $zone = WC_Shipping_Zones::get_zone_matching_package( $package );

        /**
         * Filters the shipping zone matched for a calculator package.
         *
         * @since 3.0.1
         * @param \WC_Shipping_Zone $zone Matched zone.
         * @param array $package Shipping package.
         */
        $zone = apply_filters( 'Hubgo/Shipping_Calculator/Zone', $zone, $package );

        $this->matched_zone_object = $zone;

        $this->matched_zone = array(
            'id'   => (int) $zone->get_id(),
            'name' => (string) $zone->get_zone_name(),
        );

        $is_shippable = WC_Shipping::instance()->is_package_shippable( $package );
        $rates        = array();

        // Evaluate free shipping against this package instead of the shopper's
        // real cart, which is empty on a product page.
        $free_shipping_filter = array( $this, 'filter_free_shipping_availability' );
        add_filter( 'woocommerce_shipping_free_shipping_is_available', $free_shipping_filter, 20, 3 );

        foreach ( $zone->get_shipping_methods( true ) as $method ) {
            // Legacy methods without zone support are handled globally by
            // WooCommerce, not per zone.
            if ( ! $method->supports( 'shipping-zones' ) && ! $method->get_instance_id() ) {
                continue;
            }

            // Non-shippable destinations may still offer local pickup.
            if ( ! $is_shippable && 'local_pickup' !== $method->id && ! $method->supports( 'local-pickup' ) ) {
                continue;
            }

            do_action( 'woocommerce_before_get_rates_for_package', $package, $method );

            // Use + instead of array_merge so rate IDs stay as keys (dedup).
            $rates = $rates + $method->get_rates_for_package( $package );

            do_action( 'woocommerce_after_get_rates_for_package', $package, $method );
        }

        remove_filter( 'woocommerce_shipping_free_shipping_is_available', $free_shipping_filter, 20 );

        $rates = apply_filters( 'woocommerce_package_rates', $rates, $package );

        return is_array( $rates ) ? $rates : array();
    }


    /**
     * Re-evaluate free shipping availability against the calculator package.
     *
     * WC_Shipping_Free_Shipping::is_available() reads the shopper's cart, which
     * is empty while browsing a product, so `min_amount` never matches. This
     * repeats WooCommerce's own logic using the quoted line instead. Scoped to
     * packages built by this service.
     *
     * @since 3.0.1
     * @param bool $is_available Availability decided by WooCommerce.
     * @param array $package Shipping package.
     * @param \WC_Shipping_Free_Shipping $method Method instance.
     * @return bool
     */
    public function filter_free_shipping_availability( $is_available, $package, $method ) {
        if ( empty( $package['hubgo_calculator'] ) || $is_available ) {
            return $is_available;
        }

        $requires = isset( $method->requires ) ? $method->requires : '';

        if ( ! in_array( $requires, array( 'min_amount', 'coupon', 'both', 'either' ), true ) ) {
            return $is_available;
        }

        $has_coupon = false;

        if ( in_array( $requires, array( 'coupon', 'both', 'either' ), true ) ) {
            $coupons = ( WC()->cart ) ? WC()->cart->get_coupons() : array();

            foreach ( $coupons as $coupon ) {
                if ( $coupon->is_valid() && $coupon->get_free_shipping() ) {
                    $has_coupon = true;
                    break;
                }
            }
        }

        $has_min_amount = false;

        if ( in_array( $requires, array( 'min_amount', 'both', 'either' ), true ) ) {
            // Same helper the storefront badge uses, so the threshold shown and
            // the threshold enforced can never drift apart.
            $total = Free_Shipping_Context::get_package_subtotal( $package );

            $has_min_amount = ( $total >= (float) $method->min_amount );
        }

        switch ( $requires ) {
            case 'min_amount':
                return $has_min_amount;
            case 'coupon':
                return $has_coupon;
            case 'both':
                return ( $has_min_amount && $has_coupon );
            case 'either':
                return ( $has_min_amount || $has_coupon );
        }

        return $is_available;
    }


    /**
     * Resolve the free-shipping context and expose the rate filter.
     *
     * Up to 3.0.1 this also appended "(Delivery in N business days)" to every rate
     * label. The forecast is now structured data on each row (see
     * {@see Delivery_Estimate}), so appending it to the label as well would
     * print it twice on the storefront.
     *
     * @since 3.0.1
     * @version 3.0.0
     * @param array $rates Rate objects keyed by rate ID.
     * @param array $package Shipping package the rates came from.
     * @return array<int,WC_Shipping_Rate>
     */
    private function decorate_rates( $rates, $package ) {
        $this->free_shipping = Free_Shipping_Context::build( $this->matched_zone_object, $package, $rates );

        /**
         * Filters the rates returned by the shipping calculator.
         *
         * @since 2.0.0
         * @version 3.0.1 Added the $matched_zone argument.
         * @param array $rates Rate objects.
         * @param array $package Shipping package.
         * @param array $matched_zone Zone matched for the package.
         */
        $rates = apply_filters( 'Hubgo/Shipping_Calculator/Rates', array_values( $rates ), $package, $this->matched_zone );

        return is_array( $rates ) ? $rates : array();
    }


    /**
     * Forward the product price to Correios as the declared value.
     *
     * Returns the callback so the caller can unhook it, keeping the filter from
     * leaking into unrelated shipping calculations later in the request.
     *
     * @since 3.0.1
     * @param array $package Shipping package.
     * @return callable|null
     */
    private function add_correios_declared_value_filter( $package ) {
        if ( ! class_exists( 'WC_Correios_Webservice' ) ) {
            return null;
        }

        $declared_value = (float) $package['contents_cost'];

        $callback = function( $args, $method_id, $instance_id, $method_package ) use ( $declared_value ) {
            $settings = get_option( 'woocommerce_' . $method_id . '_' . $instance_id . '_settings' );

            if ( isset( $settings['declare_value'] ) && 'yes' === $settings['declare_value'] ) {
                $args['nVlValorDeclarado'] = $declared_value;
            }

            return $args;
        };

        add_filter( 'woocommerce_correios_shipping_args', $callback, 10, 4 );

        return $callback;
    }
}
