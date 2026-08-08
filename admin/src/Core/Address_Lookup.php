<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Admin\Settings;
use MeuMouse\Hubgo\Core\Address\Address_Provider;
use MeuMouse\Hubgo\Core\Address\Google_Places_Provider;
use MeuMouse\Hubgo\Core\Address\ViaCep_Provider;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Facade over the address lookup providers used by "I do not know my postcode".
 *
 * Resolves the configured provider, exposes the bootstrap the storefront modal
 * needs to render the right form, and enforces the per-visitor rate limit.
 *
 * The rate limit is not optional. Both endpoints are public by necessity —
 * guests calculate shipping — and one of the providers spends the store owner's
 * Google quota on every call. Without a ceiling, a single script can turn the
 * storefront into a billing incident.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Address_Lookup {

    /**
     * Setting holding the active provider id.
     *
     * @since 3.0.0
     * @var string
     */
    const PROVIDER_SETTING = 'address_lookup_provider';

    /**
     * Value disabling the feature entirely.
     *
     * @since 3.0.0
     * @var string
     */
    const PROVIDER_OFF = 'off';

    /**
     * Transient prefix for the per-visitor request counter.
     *
     * @since 3.0.0
     * @var string
     */
    const RATE_LIMIT_PREFIX = 'hubgo_addr_rl_';

    /**
     * Rate limit window, in seconds.
     *
     * @since 3.0.0
     * @var int
     */
    const RATE_LIMIT_WINDOW = 60;

    /**
     * Requests allowed per visitor per window.
     *
     * Sized for a shopper typing an address with a debounced field, not for a
     * script: roughly one search per two seconds.
     *
     * @since 3.0.0
     * @var int
     */
    const RATE_LIMIT_MAX = 30;


    /**
     * Instantiate every known provider, keyed by id.
     *
     * @since 3.0.0
     * @return array<string,Address_Provider>
     */
    public static function get_providers() {
        static $providers = null;

        if ( null !== $providers ) {
            return $providers;
        }

        $instances = array();

        /**
         * Filters the address lookup provider classes.
         *
         * @since 3.0.0
         * @param array<int,string> $classes Provider class names.
         */
        $classes = apply_filters( 'Hubgo/Core/Address_Lookup/Providers', array(
            ViaCep_Provider::class,
            Google_Places_Provider::class,
        ) );

        foreach ( (array) $classes as $class ) {
            if ( ! is_string( $class ) || ! class_exists( $class ) ) {
                continue;
            }

            $provider = new $class();

            if ( $provider instanceof Address_Provider ) {
                $instances[ $provider->get_id() ] = $provider;
            }
        }

        $providers = $instances;

        return $providers;
    }


    /**
     * The provider currently in use, or null when the feature is off.
     *
     * Falls back to ViaCEP when the configured provider is missing a
     * credential: losing the feature silently because a key was never pasted in
     * is worse than serving the free provider.
     *
     * @since 3.0.0
     * @return Address_Provider|null
     */
    public static function get_active_provider() {
        $configured = sanitize_key( (string) Settings::get_setting( self::PROVIDER_SETTING, ViaCep_Provider::ID ) );

        if ( self::PROVIDER_OFF === $configured ) {
            return null;
        }

        $providers = self::get_providers();
        $provider  = $providers[ $configured ] ?? null;

        if ( $provider instanceof Address_Provider && $provider->is_configured() ) {
            return $provider;
        }

        $fallback = $providers[ ViaCep_Provider::ID ] ?? null;

        return ( $fallback instanceof Address_Provider ) ? $fallback : null;
    }


    /**
     * Whether the address lookup is available to the storefront.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_enabled() {
        return null !== self::get_active_provider();
    }


    /**
     * Payload the storefront needs to render the lookup modal.
     *
     * @since 3.0.0
     * @return array<string,mixed>
     */
    public static function get_bootstrap() {
        $provider = self::get_active_provider();

        if ( ! $provider ) {
            return array( 'enabled' => false, 'provider' => '', 'mode' => '', 'states' => array() );
        }

        $mode = $provider->get_mode();

        return array(
            'enabled'  => true,
            'provider' => $provider->get_id(),
            'mode'     => $mode,
            // Only the structured form needs the state list; sending it always
            // would add ~1 KB to every product page for nothing.
            'states'   => ( Address_Provider::MODE_STRUCTURED === $mode ) ? self::get_states() : array(),
        );
    }


    /**
     * Options for the state select of the structured lookup form.
     *
     * @since 3.0.0
     * @return array<int,array<string,string>>
     */
    public static function get_states() {
        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return array();
        }

        $country = (string) WC()->countries->get_base_country();
        $states  = WC()->countries->get_states( $country );
        $options = array();

        foreach ( (array) $states as $code => $label ) {
            $options[] = array(
                'value' => (string) $code,
                'label' => (string) $label,
            );
        }

        return $options;
    }


    /**
     * Consume one unit of the caller's rate-limit budget.
     *
     * @since 3.0.0
     * @return bool False once the visitor is over the ceiling.
     */
    public static function consume_rate_limit() {
        $key   = self::RATE_LIMIT_PREFIX . md5( self::get_client_ip() );
        $count = (int) get_transient( $key );

        /**
         * Filters how many address lookups a visitor may perform per window.
         *
         * @since 3.0.0
         * @param int $max Requests allowed per {@see self::RATE_LIMIT_WINDOW} seconds.
         */
        $max = (int) apply_filters( 'Hubgo/Core/Address_Lookup/Rate_Limit', self::RATE_LIMIT_MAX );

        if ( $max > 0 && $count >= $max ) {
            return false;
        }

        // set_transient() restarts the window on every write. That is deliberate:
        // a caller who keeps hammering stays locked out until they stop.
        set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );

        return true;
    }


    /**
     * Best-effort client IP used to bucket the rate limit.
     *
     * REMOTE_ADDR only. Proxy headers are attacker-controlled, so trusting them
     * would let one caller mint an unlimited number of buckets — the exact
     * thing the limit exists to prevent. Sites behind a reverse proxy should
     * filter this to read whatever header their proxy actually sets.
     *
     * @since 3.0.0
     * @return string
     */
    private static function get_client_ip() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        /**
         * Filters the client IP used to bucket address lookup rate limiting.
         *
         * @since 3.0.0
         * @param string $ip Address read from REMOTE_ADDR.
         */
        return (string) apply_filters( 'Hubgo/Core/Address_Lookup/Client_Ip', $ip );
    }
}
