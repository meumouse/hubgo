<?php

namespace MeuMouse\Hubgo\Core;

use WC_Shipping;
use WC_Shipping_Rate;
use WC_Validation;

defined('ABSPATH') || exit;

/**
 * Shipping calculation service.
 *
 * Extracted from the legacy AJAX handler so the REST endpoint (and any other
 * caller) can compute shipping rates for a single product without rendering HTML.
 * Returns normalized rows: [ [ 'label' => string, 'cost' => float, 'cost_formatted' => string ], ... ].
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Shipping_Calculator_Service {

    /**
     * Calculate normalized shipping rows for a product/postcode.
     *
     * @since 3.0.0
     * @param int $product_id Product ID.
     * @param int $variation_id Variation ID (optional).
     * @param string $postcode Destination postcode.
     * @param int $quantity Quantity.
     * @return array<int,array<string,mixed>>
     */
    public function calculate( $product_id, $variation_id, $postcode, $quantity ) {
        $product_id   = absint( $product_id );
        $variation_id = absint( $variation_id );
        $quantity     = max( 1, absint( $quantity ) );
        $postcode     = $this->normalize_postcode( $postcode );

        if ( ! $product_id && $variation_id ) {
            $product_id = $this->get_parent_product_id( $variation_id );
        }

        if ( ! $product_id || '' === $postcode ) {
            return array();
        }

        $rates = $this->calculate_rates( $product_id, $variation_id, $postcode, $quantity );

        return $this->normalize_rows( $rates );
    }


    /**
     * Normalize WC rate objects to plain rows.
     *
     * @since 3.0.0
     * @param array $rates Rate objects.
     * @return array<int,array<string,mixed>>
     */
    private function normalize_rows( $rates ) {
        $rows = array();

        foreach ( $rates as $rate ) {
            if ( $rate instanceof WC_Shipping_Rate ) {
                $label = $rate->get_label();
                $cost  = (float) $rate->get_cost();
                $taxes = array_sum( (array) $rate->get_taxes() );
                $total = $cost + (float) $taxes;
            } else {
                $label = isset( $rate->label ) ? (string) $rate->label : '';
                $total = isset( $rate->cost ) ? (float) $rate->cost : 0;
            }

            $rows[] = array(
                'label'          => $label,
                'cost'           => $total,
                'cost_formatted' => wp_strip_all_tags( wc_price( $total ) ),
            );
        }

        return $rows;
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
     * Calculate shipping rates (WooCommerce package calculation).
     *
     * Ported from the legacy AJAX handler.
     *
     * @since 3.0.0
     * @param int $product_id Product ID.
     * @param int $variation_id Variation ID.
     * @param string $postcode Postcode.
     * @param int $quantity Quantity.
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

        $country = WC()->customer ? WC()->customer->get_shipping_country() : '';

        if ( empty( $country ) ) {
            $default_location = wc_get_customer_default_location();
            $country = isset( $default_location['country'] ) ? $default_location['country'] : '';
        }

        if ( empty( $postcode ) || ( $country && ! WC_Validation::is_postcode( $postcode, $country ) ) ) {
            return array();
        }

        $product = $variation_id ? wc_get_product( $variation_id ) : $base_product;

        if ( ! $product ) {
            return array();
        }

        $destination = $this->get_destination_array( $postcode, $country );

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
                    if ( ( $price * $quantity ) >= (float) $method->min_amount ) {
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

        $temporary_cart_item_key = false;

        if ( WC()->cart && WC()->cart->is_empty() ) {
            $temporary_cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
            WC()->cart->calculate_totals();
        }

        $package_rates = WC_Shipping::instance()->calculate_shipping_for_package( $package );

        if ( $temporary_cart_item_key ) {
            WC()->cart->remove_cart_item( $temporary_cart_item_key );
            WC()->cart->calculate_totals();
        }

        $rates = array();
        $has_free_rate = false;

        if ( isset( $package_rates['rates'] ) && is_array( $package_rates['rates'] ) ) {
            foreach ( $package_rates['rates'] as $r ) {
                if ( $r instanceof WC_Shipping_Rate && 'free_shipping' === $r->get_method_id() ) {
                    $has_free_rate = true;
                    break;
                }
            }
        }

        if ( $is_free_available && ! $has_free_rate ) {
            $rates[] = (object) array(
                'cost'  => 0,
                'label' => ( $method_free && ! empty( $method_free->method_title ) )
                    ? $method_free->method_title
                    : __( 'Frete grátis', 'hubgo' ),
            );
        }

        if ( isset( $package_rates['rates'] ) && is_array( $package_rates['rates'] ) ) {
            foreach ( $package_rates['rates'] as $rate ) {
                if ( $rate instanceof WC_Shipping_Rate ) {
                    $meta = $rate->get_meta_data();

                    if ( isset( $meta['_delivery_forecast'] ) ) {
                        $translated_delivery_forecast = sprintf(
                            __( '(Entrega em %s dias úteis)', 'hubgo' ),
                            $meta['_delivery_forecast']
                        );

                        $rate->set_label( $rate->get_label() . ' ' . $translated_delivery_forecast );
                    }
                }

                $rates[] = $rate;
            }
        }

        if ( WC()->customer ) {
            WC()->customer->set_shipping_postcode( $postcode );
            WC()->customer->set_billing_postcode( $postcode );
        }

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
     * Get destination array with fallbacks.
     *
     * @since 3.0.0
     * @param string $postcode Postcode.
     * @param string $country Country.
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
}
