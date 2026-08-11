<?php

namespace MeuMouse\Hubgo\Core\Address;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Facade over the active address provider.
 *
 * Owns everything that is true regardless of which service is answering: which
 * provider is active, what the storefront needs to know about it, and the three
 * ceilings that keep a public endpoint from turning a metered API into a
 * billing incident.
 *
 * Core deliberately knows nothing about Google. The provider arrives through
 * the `Hubgo/Core/Address/Provider` filter — {@see \MeuMouse\Hubgo\Integrations\Google_Maps}
 * is what fills it in — so the whole feature is absent, and free, until a store
 * opts into it, and a third party can plug a different service in without this
 * class changing.
 *
 * **Why the ceilings exist.** Both `hubgo/v1/address/*` and
 * `hubgo/v1/shipping/calculate` are public by necessity: guests calculate
 * shipping. Since 3.0.0 the quote also resolves the destination address, so a
 * script walking postcodes spends the store owner's Google quota. Four things
 * stand between that script and the bill:
 *
 * 1. The per-postcode cache below, which is what makes the steady-state cost of
 *    the address line roughly one call per postcode per month for the whole
 *    store — the street a postcode sits on does not change.
 * 2. A per-visitor rate limit, shared with the finder endpoints.
 * 3. A store-wide daily ceiling on *new* postcodes.
 * 4. A short negative cache, so a rejected key does not mean one failed Google
 *    round-trip on every product page view until someone notices.
 *
 * None of them may ever fail a quote: the address is decoration, and
 * {@see self::lookup_postcode()} answers with an empty address rather than an
 * error whatever goes wrong.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core\Address
 * @author MeuMouse.com
 */
class Address_Service {

    /**
     * Transient prefix for resolved postcode addresses.
     *
     * @since 3.0.0
     * @var string
     */
    const CACHE_PREFIX = 'hubgo_addr_cep_';

    /**
     * Cache lifetime for a resolved postcode, in seconds.
     *
     * @since 3.0.0
     * @var int
     */
    const CACHE_TTL = MONTH_IN_SECONDS;

    /**
     * Cache lifetime for a failed lookup, in seconds.
     *
     * @since 3.0.0
     * @var int
     */
    const ERROR_CACHE_TTL = 5 * MINUTE_IN_SECONDS;

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
     * Transient prefix for the store-wide daily counter.
     *
     * @since 3.0.0
     * @var string
     */
    const BUDGET_PREFIX = 'hubgo_addr_budget_';

    /**
     * New postcodes the store will resolve per day.
     *
     * Only cache misses count, so a busy store serving the same regions never
     * approaches it. It is a backstop against enumeration, not a quota to plan
     * around — raise it with the filter if a legitimate store hits it.
     *
     * @since 3.0.0
     * @var int
     */
    const DAILY_BUDGET = 500;


    /**
     * The provider currently in use, or null when the feature is off.
     *
     * @since 3.0.0
     * @return Address_Provider|null
     */
    public static function get_provider() {
        /**
         * Filters the active address provider.
         *
         * Null — the default — means the feature is off: nothing in Core
         * registers a provider, so an install with no address integration
         * enabled never reaches an external service.
         *
         * @since 3.0.0
         * @param Address_Provider|null $provider Active provider.
         */
        $provider = apply_filters( 'Hubgo/Core/Address/Provider', null );

        if ( ! $provider instanceof Address_Provider || ! $provider->is_configured() ) {
            return null;
        }

        return $provider;
    }


    /**
     * Whether an address provider is available at all.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_enabled() {
        return null !== self::get_provider();
    }


    /**
     * Whether the "I do not know my postcode" finder should be offered.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_finder_enabled() {
        $provider = self::get_provider();

        if ( ! $provider ) {
            return false;
        }

        /**
         * Filters whether the address finder is offered to shoppers.
         *
         * @since 3.0.0
         * @param bool $enabled Whether the finder is available.
         * @param Address_Provider $provider Active provider.
         */
        return (bool) apply_filters( 'Hubgo/Core/Address/Finder_Enabled', true, $provider );
    }


    /**
     * Whether a quote should name the destination address.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_lookup_enabled() {
        $provider = self::get_provider();

        if ( ! $provider ) {
            return false;
        }

        /**
         * Filters whether the calculator resolves the destination address.
         *
         * Separate from the finder because the two directions are billed
         * separately: a store may want the free-text search without paying to
         * name the street on every quote, or the other way around.
         *
         * @since 3.0.0
         * @param bool $enabled Whether the lookup runs.
         * @param Address_Provider $provider Active provider.
         */
        return (bool) apply_filters( 'Hubgo/Core/Address/Lookup_Enabled', true, $provider );
    }


    /**
     * Payload the storefront needs to render the finder.
     *
     * @since 3.0.0
     * @return array<string,mixed>
     */
    public static function get_bootstrap() {
        $provider = self::get_provider();

        if ( ! $provider || ! self::is_finder_enabled() ) {
            return array( 'enabled' => false, 'provider' => '', 'mode' => '' );
        }

        return array(
            'enabled'  => true,
            'provider' => $provider->get_id(),
            'mode'     => $provider->get_mode(),
        );
    }


    /**
     * Resolve the address a postcode belongs to.
     *
     * Never fails and never blocks for long: every guard answers with the empty
     * address, so a caller can drop the result straight into a payload without
     * checking anything.
     *
     * @since 3.0.0
     * @param string $postcode Raw or formatted postcode.
     * @param string $country Destination country code.
     * @return array<string,string> Address parts, all empty when unresolved.
     */
    public static function lookup_postcode( $postcode, $country = '' ) {
        $empty    = Address_Provider::get_empty_address();
        $postcode = Address_Provider::normalize_postcode( $postcode );

        if ( '' === $postcode || ! self::is_lookup_enabled() ) {
            return $empty;
        }

        $cache_key = self::CACHE_PREFIX . md5( strtoupper( (string) $country ) . '|' . $postcode );
        $cached    = get_transient( $cache_key );

        if ( is_array( $cached ) ) {
            // Failures are cached under a sentinel so they expire in minutes
            // while a real answer is kept for a month.
            return empty( $cached['error'] ) ? wp_parse_args( $cached, $empty ) : $empty;
        }

        // Both ceilings are consumed only on a cache miss, which is the only
        // case that can actually reach the network.
        if ( ! self::consume_rate_limit() || ! self::consume_daily_budget() ) {
            return $empty;
        }

        $provider = self::get_provider();
        $address  = $provider ? $provider->lookup_postcode( $postcode, $country ) : $empty;

        if ( is_wp_error( $address ) || ! is_array( $address ) ) {
            set_transient( $cache_key, array( 'error' => true ), self::ERROR_CACHE_TTL );

            return $empty;
        }

        $address = wp_parse_args( $address, $empty );

        set_transient( $cache_key, $address, self::CACHE_TTL );

        /**
         * Filters the address resolved for a postcode.
         *
         * Runs after the cache is written, so a site amending the result here
         * does not have its changes stored and served to everyone.
         *
         * @since 3.0.0
         * @param array<string,string> $address Resolved address parts.
         * @param string $postcode Eight-digit postcode.
         * @param string $country Destination country code.
         */
        return (array) apply_filters( 'Hubgo/Core/Address/Postcode_Address', $address, $postcode, $country );
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
        $max = (int) apply_filters( 'Hubgo/Core/Address/Rate_Limit', self::RATE_LIMIT_MAX );

        if ( $max > 0 && $count >= $max ) {
            return false;
        }

        // set_transient() restarts the window on every write. That is deliberate:
        // a caller who keeps hammering stays locked out until they stop.
        set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );

        return true;
    }


    /**
     * Consume one unit of the store's daily lookup budget.
     *
     * @since 3.0.0
     * @return bool False once the store is over the ceiling for the day.
     */
    public static function consume_daily_budget() {
        /**
         * Filters how many uncached postcodes the store resolves per day.
         *
         * Zero or less removes the ceiling entirely.
         *
         * @since 3.0.0
         * @param int $budget Uncached lookups allowed per day.
         */
        $budget = (int) apply_filters( 'Hubgo/Core/Address/Daily_Budget', self::DAILY_BUDGET );

        if ( $budget <= 0 ) {
            return true;
        }

        // Keyed on the site's own day, so the window a store owner sees in the
        // reports is the window that was counted.
        $key   = self::BUDGET_PREFIX . gmdate( 'Ymd', (int) current_time( 'timestamp' ) );
        $count = (int) get_transient( $key );

        if ( $count >= $budget ) {
            return false;
        }

        set_transient( $key, $count + 1, DAY_IN_SECONDS );

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
    public static function get_client_ip() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        /**
         * Filters the client IP used to bucket address lookup rate limiting.
         *
         * @since 3.0.0
         * @param string $ip Address read from REMOTE_ADDR.
         */
        return (string) apply_filters( 'Hubgo/Core/Address/Client_Ip', $ip );
    }
}
