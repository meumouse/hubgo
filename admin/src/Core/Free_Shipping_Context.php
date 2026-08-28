<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Admin\Settings;
use MeuMouse\Hubgo\Core\Shipping_Calculator_Service;

use WC_Shipping_Zone;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Free-shipping threshold context for a quoted package.
 *
 * The storefront card advertises "free shipping over X" and how much is
 * missing to get there. Hardcoding X on the frontend (as the reference
 * implementation did) makes the badge lie the moment the store owner edits the
 * zone, so the threshold is read from the very `free_shipping` instances that
 * were considered for this package.
 *
 * Only instances whose `requires` actually depends on the cart total count:
 * a free shipping method gated on a coupon has no threshold to advertise.
 * When several qualify, the lowest one wins — that is the number the shopper
 * can realistically reach.
 *
 * A store owner who wants to advertise a different figure (a campaign, a value
 * including products the calculator cannot see) sets `free_shipping_threshold`
 * and that wins outright.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Free_Shipping_Context {

    /**
     * `requires` values that make a free shipping instance depend on the total.
     *
     * @since 3.0.0
     * @var array<int,string>
     */
    const AMOUNT_REQUIREMENTS = array( 'min_amount', 'either', 'both' );


    /**
     * The "nothing to advertise" context.
     *
     * Returned whenever a calculation bails before a zone was matched. It has
     * to carry every key the populated version does: an empty PHP array
     * serializes to a JSON array, and the storefront reads this as an object.
     *
     * @since 3.0.0
     * @return array<string,mixed>
     */
    public static function get_empty() {
        return array(
            'enabled'             => false,
            'threshold'           => 0.0,
            'threshold_formatted' => '',
            'subtotal'            => 0.0,
            'subtotal_formatted'  => '',
            'remaining'           => 0.0,
            'remaining_formatted' => '',
            'qualifies'           => false,
            'has_free_rate'       => false,
        );
    }


    /**
     * Build the free-shipping context for a package.
     *
     * @since 3.0.0
     * @param WC_Shipping_Zone|null $zone Zone matched for the package.
     * @param array $package Shipping package.
     * @param array $rates Rate objects returned for the package.
     * @return array<string,mixed>
     */
    public static function build( $zone, $package, $rates = array() ) {
        $threshold = self::get_threshold( $zone );
        $subtotal  = self::get_package_subtotal( $package );
        $remaining = max( 0, $threshold - $subtotal );

        $context = array(
            'enabled'             => $threshold > 0,
            'threshold'           => $threshold,
            'threshold_formatted' => $threshold > 0 ? self::price( $threshold ) : '',
            'subtotal'            => $subtotal,
            'subtotal_formatted'  => self::price( $subtotal ),
            'remaining'           => $remaining,
            'remaining_formatted' => self::price( $remaining ),
            // Reaching the threshold and actually being offered a free rate are
            // different things: the zone may gate it on a coupon as well.
            'qualifies'           => $threshold > 0 && $subtotal >= $threshold,
            'has_free_rate'       => self::has_free_rate( $rates ),
        );

        /**
         * Filters the free-shipping context advertised by the calculator.
         *
         * @since 3.0.0
         * @param array<string,mixed> $context Context payload.
         * @param WC_Shipping_Zone|null $zone Zone matched for the package.
         * @param array $package Shipping package.
         */
        return apply_filters( 'Hubgo/Shipping_Calculator/Free_Shipping', $context, $zone, $package );
    }


    /**
     * Resolve the threshold to advertise, in store currency.
     *
     * @since 3.0.0
     * @param WC_Shipping_Zone|null $zone Zone matched for the package.
     * @return float Zero when there is nothing to advertise.
     */
    public static function get_threshold( $zone ) {
        $override = Settings::get_setting( 'free_shipping_threshold', '' );
        $override = is_scalar( $override ) ? trim( (string) $override ) : '';

        if ( '' !== $override && is_numeric( $override ) ) {
            return max( 0, (float) $override );
        }

        return self::get_zone_threshold( $zone );
    }


    /**
     * Lowest amount-based free shipping threshold declared by a zone.
     *
     * @since 3.0.0
     * @param WC_Shipping_Zone|null $zone Zone to inspect.
     * @return float
     */
    private static function get_zone_threshold( $zone ) {
        if ( ! $zone instanceof WC_Shipping_Zone ) {
            return 0;
        }

        $threshold = 0;

        foreach ( $zone->get_shipping_methods( true ) as $method ) {
            if ( 'free_shipping' !== $method->id ) {
                continue;
            }

            $requires = isset( $method->requires ) ? (string) $method->requires : '';

            if ( ! in_array( $requires, self::AMOUNT_REQUIREMENTS, true ) ) {
                continue;
            }

            $min_amount = isset( $method->min_amount ) ? (float) $method->min_amount : 0;

            if ( $min_amount <= 0 ) {
                continue;
            }

            if ( 0 === $threshold || $min_amount < $threshold ) {
                $threshold = $min_amount;
            }
        }

        return $threshold;
    }


    /**
     * Sum a package's line totals the way WooCommerce evaluates free shipping.
     *
     * Mirrors WC_Cart::get_displayed_subtotal() for the single line the
     * calculator quotes, so the number shown on the storefront and the number
     * WC_Shipping_Free_Shipping::is_available() compares against never diverge.
     *
     * @since 3.0.0
     * @param array $package Shipping package.
     * @return float
     */
    public static function get_package_subtotal( $package ) {
        if ( empty( $package['contents'] ) || ! is_array( $package['contents'] ) ) {
            return 0;
        }

        $include_tax = ( WC()->cart ) ? WC()->cart->display_prices_including_tax() : false;
        $total       = 0;

        foreach ( $package['contents'] as $item ) {
            $total += isset( $item['line_total'] ) ? (float) $item['line_total'] : 0;

            if ( $include_tax && isset( $item['line_tax'] ) ) {
                $total += (float) $item['line_tax'];
            }
        }

        return round( $total, wc_get_price_decimals() );
    }


    /**
     * Whether any of the returned rates is actually free.
     *
     * @since 3.0.0
     * @param array $rates Rate objects.
     * @return bool
     */
    private static function has_free_rate( $rates ) {
        foreach ( (array) $rates as $rate ) {
            if ( ! is_object( $rate ) || ! method_exists( $rate, 'get_cost' ) ) {
                continue;
            }

            if ( 0.0 === (float) $rate->get_cost() ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Format an amount as a plain-text price.
     *
     * Shares the service's formatter so the badge and the rate rows can never
     * present the same currency differently.
     *
     * @since 3.0.0
     * @param float $amount Amount in store currency.
     * @return string
     */
    private static function price( $amount ) {
        return Shipping_Calculator_Service::format_price( $amount );
    }
}
