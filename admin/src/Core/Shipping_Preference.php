<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Admin\Settings;
use MeuMouse\Hubgo\Core\Postcode_Locator;

use Automattic\WooCommerce\Utilities\LocalPickupUtils;
use WC_Shipping_Rate;
use WC_Validation;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Carry the shopper's preferred shipping method from the product page into the
 * cart and the checkout.
 *
 * The storefront calculator writes the choice to a cookie; this class reads it
 * back when WooCommerce is deciding which method to pre-select, and seeds the
 * customer's shipping postcode so the checkout opens on the destination the
 * shopper already quoted.
 *
 * Why a cookie and not the WooCommerce session: writing
 * `chosen_shipping_methods` from the product page forces a session — a row in
 * `wp_woocommerce_sessions` plus the session cookie — for every visitor who
 * merely calculates shipping, and that cookie makes most page caches bail out.
 * The cookie defers all of that to the cart, where a session exists anyway. It
 * also keeps {@see Shipping_Calculator_Service} side-effect free, which is what
 * lets its REST endpoint stay public and cacheable.
 *
 * The preference is a DEFAULT, never an override. `woocommerce_shipping_chosen_method`
 * only fires when the session holds no valid choice (or the available rates
 * changed), so a method the shopper picked at checkout always wins. Three extra
 * guards protect cases where overriding would actively harm the shopper — see
 * {@see self::filter_chosen_method()}.
 *
 * @since 3.1.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Shipping_Preference {

    /**
     * Cookie holding the preference.
     *
     * Read server-side only on cart/checkout, which are never page-cached.
     * Reverse proxies that hash on the full cookie jar should be told to ignore
     * it.
     *
     * @since 3.1.0
     * @var string
     */
    const COOKIE_NAME = 'hubgo_ship_pref';

    /**
     * Shape a WooCommerce rate id must have: `method_id:instance_id`.
     *
     * The cookie is written by the shopper's own browser, so it is untrusted
     * input. A value is only ever used to SELECT an entry already present in
     * the package rates — never to build one — and this pattern rejects the
     * obvious garbage before it gets that far.
     *
     * @since 3.1.0
     * @var string
     */
    const RATE_ID_PATTERN = '/^[a-z0-9_\-]+:[0-9]+$/i';

    /**
     * Default cookie lifetime, in days.
     *
     * @since 3.1.0
     * @var int
     */
    const DEFAULT_TTL_DAYS = 30;

    /**
     * Whether the postcode seed already ran this request.
     *
     * @since 3.1.0
     * @var bool
     */
    private static $seeded = false;


    /**
     * Constructor: wire the cart/checkout hooks.
     *
     * @since 3.1.0
     */
    public function __construct() {
        if ( ! self::is_enabled() ) {
            return;
        }

        // Priority 20 so WooCommerce's own default (including its local pickup
        // and free-shipping-coupon handling) is fully resolved before we look.
        add_filter( 'woocommerce_shipping_chosen_method', array( $this, 'filter_chosen_method' ), 20, 3 );

        // Before the packages are assembled from the customer address.
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'maybe_seed_customer_postcode' ), 5 );
    }


    /**
     * Whether the feature is switched on.
     *
     * @since 3.1.0
     * @return bool
     */
    public static function is_enabled() {
        return 'yes' === Settings::get_setting( 'enable_shipping_preference', 'yes' );
    }


    /**
     * Pre-select the shopper's preferred method for a package.
     *
     * Bails, leaving WooCommerce's own default in place, when:
     *
     * 1. The shopper has local pickup selected. WooCommerce deliberately keeps
     *    pickup sticky so the block checkout's shipping/pickup toggle does not
     *    flip under the customer.
     * 2. The default is free shipping granted by a coupon. Applying a paid
     *    preference on top would charge someone who had earned free delivery.
     * 3. Nothing in this package matches the preference.
     *
     * @since 3.1.0
     * @param string $default Default chosen by WooCommerce.
     * @param array $rates Available rates, keyed by rate id.
     * @param string $chosen_method Method currently held in the session.
     * @return string
     */
    public function filter_chosen_method( $default, $rates, $chosen_method ) {
        $rates = is_array( $rates ) ? $rates : array();

        if ( empty( $rates ) ) {
            return $default;
        }

        if ( $this->is_local_pickup_locked( $rates, (string) $chosen_method ) ) {
            return $default;
        }

        if ( $this->is_coupon_free_shipping( (string) $default ) ) {
            return $default;
        }

        $preferred = self::resolve_from_rates( $rates );

        /**
         * Filters the shipping method pre-selected from the shopper's preference.
         *
         * Returning an empty string falls back to WooCommerce's own default.
         *
         * @since 3.1.0
         * @param string $preferred Rate id resolved from the preference, or ''.
         * @param array $rates Available rates, keyed by rate id.
         * @param string $default Default chosen by WooCommerce.
         * @param string $chosen_method Method currently held in the session.
         */
        $preferred = (string) apply_filters( 'Hubgo/Shipping_Preference/Chosen_Method', $preferred, $rates, $default, $chosen_method );

        return ( '' !== $preferred && isset( $rates[ $preferred ] ) ) ? $preferred : $default;
    }


    /**
     * Seed the customer's shipping postcode from the calculator, once.
     *
     * Only fills an EMPTY postcode: a shopper who typed a real address must
     * never have it replaced by a number they punched into a product page.
     *
     * The state is derived from the postcode as well, because WooCommerce
     * matches zones on country + state + postcode — seeding the postcode alone
     * can hand the cart to a different zone than the one that was quoted.
     *
     * @since 3.1.0
     * @return void
     */
    public function maybe_seed_customer_postcode() {
        if ( self::$seeded ) {
            return;
        }

        self::$seeded = true;

        if ( 'yes' !== Settings::get_setting( 'shipping_preference_apply_postcode', 'yes' ) ) {
            return;
        }

        if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->customer ) {
            return;
        }

        if ( '' !== (string) WC()->customer->get_shipping_postcode() ) {
            return;
        }

        $preference = self::get_preference();

        if ( '' === $preference['postcode'] ) {
            return;
        }

        $country = (string) WC()->customer->get_shipping_country();

        if ( '' === $country ) {
            $country = (string) WC()->countries->get_base_country();
        }

        if ( '' === $country || ! WC_Validation::is_postcode( $preference['postcode'], $country ) ) {
            return;
        }

        WC()->customer->set_shipping_country( $country );
        WC()->customer->set_shipping_postcode( wc_format_postcode( $preference['postcode'], $country ) );

        $state = Postcode_Locator::get_state( $preference['postcode'], $country );

        if ( '' !== $state ) {
            WC()->customer->set_shipping_state( $state );
        }

        WC()->customer->save();

        /**
         * Fires after the calculator postcode was applied to the customer.
         *
         * @since 3.1.0
         * @param string $postcode Postcode that was applied.
         * @param string $country Country it was applied under.
         */
        do_action( 'Hubgo/Shipping_Preference/Postcode_Applied', $preference['postcode'], $country );
    }


    /**
     * Read and validate the preference cookie.
     *
     * @since 3.1.0
     * @return array{rate_id:string,postcode:string}
     */
    public static function get_preference() {
        $empty = array( 'rate_id' => '', 'postcode' => '' );

        if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return $empty;
        }

        $raw  = wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $data = json_decode( (string) $raw, true );

        if ( ! is_array( $data ) ) {
            return $empty;
        }

        $rate_id = isset( $data['r'] ) && is_scalar( $data['r'] ) ? wc_clean( (string) $data['r'] ) : '';

        if ( ! preg_match( self::RATE_ID_PATTERN, $rate_id ) ) {
            $rate_id = '';
        }

        $postcode = isset( $data['p'] ) && is_scalar( $data['p'] )
            ? preg_replace( '/\D/', '', (string) $data['p'] )
            : '';

        if ( 8 !== strlen( (string) $postcode ) ) {
            $postcode = '';
        }

        return array(
            'rate_id'  => $rate_id,
            'postcode' => (string) $postcode,
        );
    }


    /**
     * Resolve the preference against a set of WC rate objects.
     *
     * @since 3.1.0
     * @param array<string,WC_Shipping_Rate> $rates Rates keyed by rate id.
     * @return string Rate id, or an empty string when nothing matches.
     */
    public static function resolve_from_rates( $rates ) {
        $costs = array();

        foreach ( (array) $rates as $id => $rate ) {
            $costs[ (string) $id ] = ( $rate instanceof WC_Shipping_Rate ) ? (float) $rate->get_cost() : 0.0;
        }

        return self::pick( $costs );
    }


    /**
     * Resolve the preference against the normalized rows the REST route returns.
     *
     * @since 3.1.0
     * @param array<int,array<string,mixed>> $rows Normalized rate rows.
     * @return string Rate id, or an empty string when nothing matches.
     */
    public static function match_preferred_rate( $rows ) {
        $costs = array();

        foreach ( (array) $rows as $row ) {
            if ( empty( $row['id'] ) ) {
                continue;
            }

            $costs[ (string) $row['id'] ] = isset( $row['cost'] ) ? (float) $row['cost'] : 0.0;
        }

        return self::pick( $costs );
    }


    /**
     * Apply the fallback ladder against a rate id => cost map.
     *
     * 1. The exact rate id, when it is on offer.
     * 2. In `same_method` mode, the cheapest instance of the same method — a
     *    shopper who chose PAC in one zone still means PAC in another, even
     *    though the instance id differs.
     * 3. Nothing, letting WooCommerce decide.
     *
     * @since 3.1.0
     * @param array<string,float> $costs Available rate ids mapped to their cost.
     * @return string
     */
    private static function pick( $costs ) {
        $preference = self::get_preference();

        if ( '' === $preference['rate_id'] || empty( $costs ) ) {
            return '';
        }

        if ( isset( $costs[ $preference['rate_id'] ] ) ) {
            return $preference['rate_id'];
        }

        if ( 'same_method' !== self::get_fallback_mode() ) {
            return '';
        }

        $method_id = self::get_method_id( $preference['rate_id'] );
        $best      = '';
        $best_cost = 0.0;

        foreach ( $costs as $id => $cost ) {
            if ( self::get_method_id( $id ) !== $method_id ) {
                continue;
            }

            if ( '' === $best || $cost < $best_cost ) {
                $best      = (string) $id;
                $best_cost = (float) $cost;
            }
        }

        return $best;
    }


    /**
     * Method id portion of a rate id (`flat_rate:3` -> `flat_rate`).
     *
     * @since 3.1.0
     * @param string $rate_id Rate id.
     * @return string
     */
    private static function get_method_id( $rate_id ) {
        $parts = explode( ':', (string) $rate_id );

        return (string) reset( $parts );
    }


    /**
     * Configured fallback strategy.
     *
     * @since 3.1.0
     * @return string One of `exact` or `same_method`.
     */
    private static function get_fallback_mode() {
        $mode = sanitize_key( (string) Settings::get_setting( 'shipping_preference_fallback', 'same_method' ) );

        return in_array( $mode, array( 'exact', 'same_method' ), true ) ? $mode : 'same_method';
    }


    /**
     * Whether the shopper has a still-available local pickup selected.
     *
     * @since 3.1.0
     * @param array $rates Available rates, keyed by rate id.
     * @param string $chosen_method Method held in the session.
     * @return bool
     */
    private function is_local_pickup_locked( $rates, $chosen_method ) {
        if ( '' === $chosen_method || ! isset( $rates[ $chosen_method ] ) ) {
            return false;
        }

        return in_array( self::get_method_id( $chosen_method ), self::get_local_pickup_ids(), true );
    }


    /**
     * Method ids WooCommerce treats as local pickup.
     *
     * @since 3.1.0
     * @return array<int,string>
     */
    private static function get_local_pickup_ids() {
        // LocalPickupUtils only exists on newer WooCommerce releases; the core
        // method id has been stable since long before HubGo's 6.0 floor.
        if ( class_exists( LocalPickupUtils::class ) && method_exists( LocalPickupUtils::class, 'get_local_pickup_method_ids' ) ) {
            return array_map( 'strval', (array) LocalPickupUtils::get_local_pickup_method_ids() );
        }

        return array( 'local_pickup', 'pickup_location' );
    }


    /**
     * Whether the default rate is free shipping granted by a coupon.
     *
     * @since 3.1.0
     * @param string $default Default rate id chosen by WooCommerce.
     * @return bool
     */
    private function is_coupon_free_shipping( $default ) {
        if ( 'free_shipping' !== self::get_method_id( $default ) ) {
            return false;
        }

        if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
            return false;
        }

        foreach ( WC()->cart->get_coupons() as $coupon ) {
            if ( $coupon->get_free_shipping() ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Cookie descriptor handed to the storefront so JS and PHP agree on it.
     *
     * @since 3.1.0
     * @return array<string,mixed>
     */
    public static function get_cookie_config() {
        $days = absint( Settings::get_setting( 'shipping_preference_ttl', self::DEFAULT_TTL_DAYS ) );

        return array(
            'name'    => self::COOKIE_NAME,
            'days'    => $days > 0 ? $days : self::DEFAULT_TTL_DAYS,
            'secure'  => is_ssl(),
            'enabled' => self::is_enabled(),
        );
    }
}
