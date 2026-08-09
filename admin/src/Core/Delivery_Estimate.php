<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Admin\Settings;

use WC_Shipping_Rate;
use DateTimeImmutable;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Resolve a delivery forecast (business days + calendar date) for a rate.
 *
 * WooCommerce has no native notion of a delivery window: carriers publish it as
 * rate meta under keys they each picked on their own. This class reads the keys
 * shipped by the integrations HubGo knows about, adds the store's own handling
 * time, and turns the result into a calendar date the storefront can promise.
 *
 * Ranges ("3 a 5 dias") always resolve to the LATER bound. Under-promising a
 * delivery window is the only side of that trade that does not create a support
 * ticket.
 *
 * Weekends and the holidays published through
 * `Hubgo/Shipping_Calculator/Holidays` are skipped, and the resulting date is
 * pushed forward until it lands on a business day — a carrier that says "5 dias
 * úteis" is not promising a Sunday.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Delivery_Estimate {

    /**
     * Upper bound for a forecast, in days.
     *
     * Guards the business-day walk against a carrier returning nonsense (or a
     * filter returning a huge number), which would otherwise spin the loop.
     *
     * @since 3.0.0
     * @var int
     */
    const MAX_DAYS = 180;


    /**
     * Rate meta keys that may carry a delivery forecast, in priority order.
     *
     * `_delivery_forecast` comes from WooCommerce Correios, `delivery_time` from
     * Melhor Envio, the remaining ones from smaller gateways that copied one of
     * those two conventions.
     *
     * @since 3.0.0
     * @return array<int,string>
     */
    public static function get_meta_keys() {
        $keys = array(
            '_delivery_forecast',
            'delivery_forecast',
            '_delivery_time',
            'delivery_time',
            '_delivery_days',
            'delivery_days',
            '_prazo',
            'prazo',
        );

        /**
         * Filters the rate meta keys scanned for a delivery forecast.
         *
         * @since 3.0.0
         * @param array<int,string> $keys Meta keys in priority order.
         */
        return apply_filters( 'Hubgo/Shipping_Calculator/Delivery_Meta_Keys', $keys );
    }


    /**
     * Build the delivery estimate for a shipping rate.
     *
     * @since 3.0.0
     * @param WC_Shipping_Rate $rate Rate to inspect.
     * @return array<string,mixed>
     */
    public static function for_rate( $rate ) {
        $days = self::get_days_from_rate( $rate );

        return self::build( $days, $rate );
    }


    /**
     * Build the estimate payload from a number of business days.
     *
     * @since 3.0.0
     * @param int|null $days Carrier forecast in business days, null when unknown.
     * @param WC_Shipping_Rate|null $rate Rate the estimate belongs to, for filters.
     * @return array<string,mixed>
     */
    public static function build( $days, $rate = null ) {
        $estimate = array(
            'days'       => null,
            'date'       => '',
            'date_label' => '',
            'timestamp'  => 0,
            'label'      => '',
            'headline'   => '',
        );

        if ( null !== $days ) {
            $total = min( self::MAX_DAYS, max( 0, (int) $days + self::get_handling_days() ) );
            $date  = self::add_business_days( self::get_today(), $total );

            $estimate['days']       = $total;
            $estimate['timestamp']  = $date->getTimestamp();
            $estimate['date']       = $date->format('Y-m-d');
            // The localized date on its own, so the storefront can compose a
            // different sentence (a free delivery reads "Chegará grátis até…")
            // without doing string surgery on an already-translated headline —
            // which only ever works in the language it was written for.
            $estimate['date_label'] = self::format_date_label( $date );
            $estimate['label']      = self::format_label( $total );
            $estimate['headline']   = self::format_headline( $date );
        }

        /**
         * Filters the delivery estimate computed for a rate.
         *
         * @since 3.0.0
         * @param array<string,mixed> $estimate Estimate payload.
         * @param int|null $days Raw carrier forecast, before handling time.
         * @param WC_Shipping_Rate|null $rate Rate the estimate belongs to.
         */
        return apply_filters( 'Hubgo/Shipping_Calculator/Delivery_Estimate', $estimate, $days, $rate );
    }


    /**
     * Extra business days the store needs before handing the parcel over.
     *
     * @since 3.0.0
     * @return int
     */
    public static function get_handling_days() {
        return max( 0, absint( Settings::get_setting( 'shipping_handling_days', 0 ) ) );
    }


    /**
     * Read the carrier forecast out of a rate's meta data.
     *
     * @since 3.0.0
     * @param WC_Shipping_Rate $rate Rate to inspect.
     * @return int|null Business days, or null when no key carried one.
     */
    private static function get_days_from_rate( $rate ) {
        if ( ! $rate instanceof WC_Shipping_Rate ) {
            return null;
        }

        $meta = (array) $rate->get_meta_data();
        $days = self::get_days_from_meta( $meta );

        /**
         * Filters the raw carrier forecast read from a rate.
         *
         * Lets an integration teach HubGo about a forecast stored somewhere
         * other than the rate meta (an option, the method instance settings).
         *
         * @since 3.0.0
         * @param int|null $days Business days read from the meta, null when absent.
         * @param WC_Shipping_Rate $rate Rate being inspected.
         * @param array $meta Rate meta data.
         */
        return apply_filters( 'Hubgo/Shipping_Calculator/Delivery_Days', $days, $rate, $meta );
    }


    /**
     * Scan a meta map for the first key carrying a delivery forecast.
     *
     * Public because a forecast outlives the rate it came from: WooCommerce
     * copies rate meta onto the order's shipping line item, which is where
     * {@see Delivery_Promise} reads it back after the checkout. Unlike
     * {@see self::get_days_from_rate()} this runs no filter — there is no rate
     * to hand to one.
     *
     * @since 3.1.0
     * @param array $meta Meta map keyed by meta key.
     * @return int|null Business days, or null when no key carried one.
     */
    public static function get_days_from_meta( $meta ) {
        if ( ! is_array( $meta ) ) {
            return null;
        }

        foreach ( self::get_meta_keys() as $key ) {
            if ( ! isset( $meta[ $key ] ) ) {
                continue;
            }

            $parsed = self::parse_days( $meta[ $key ] );

            if ( null !== $parsed ) {
                return $parsed;
            }
        }

        return null;
    }


    /**
     * Coerce a meta value into a number of business days.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @param mixed $value Raw meta value.
     * @return int|null
     */
    public static function parse_days( $value ) {
        if ( is_bool( $value ) || null === $value ) {
            return null;
        }

        if ( is_numeric( $value ) ) {
            return max( 0, (int) $value );
        }

        if ( ! is_string( $value ) ) {
            return null;
        }

        // Ranges arrive as "3 a 5", "3-5" or "3 até 5 dias úteis". Take the
        // upper bound so the promised date is never earlier than the carrier's.
        if ( preg_match_all( '/\d+/', $value, $matches ) && ! empty( $matches[0] ) ) {
            return max( 0, max( array_map( 'intval', $matches[0] ) ) );
        }

        return null;
    }


    /**
     * Today, in the site timezone.
     *
     * @since 3.0.0
     * @return DateTimeImmutable
     */
    private static function get_today() {
        $now = current_datetime();

        return $now->setTime( 0, 0, 0 );
    }


    /**
     * Walk forward N business days, then land on the next business day.
     *
     * @since 3.0.0
     * @param DateTimeImmutable $start Starting date.
     * @param int $days Business days to add.
     * @return DateTimeImmutable
     */
    private static function add_business_days( DateTimeImmutable $start, $days ) {
        $holidays = self::get_holidays();
        $date     = $start;
        $added    = 0;

        while ( $added < $days ) {
            $date = $date->modify('+1 day');

            if ( self::is_business_day( $date, $holidays ) ) {
                $added++;
            }
        }

        // A zero-day forecast (or a walk that ended on a holiday because the
        // start date already was one) must still resolve to a working day.
        $guard = 0;

        while ( ! self::is_business_day( $date, $holidays ) && $guard < 30 ) {
            $date = $date->modify('+1 day');
            $guard++;
        }

        return $date;
    }


    /**
     * Whether a date is a working day for delivery purposes.
     *
     * @since 3.0.0
     * @param DateTimeImmutable $date Date to test.
     * @param array<int,string> $holidays Holidays as Y-m-d strings.
     * @return bool
     */
    private static function is_business_day( DateTimeImmutable $date, $holidays ) {
        if ( in_array( (int) $date->format('N'), self::get_non_business_weekdays(), true ) ) {
            return false;
        }

        return ! in_array( $date->format('Y-m-d'), $holidays, true );
    }


    /**
     * ISO weekday numbers that are not working days (1 = Monday, 7 = Sunday).
     *
     * @since 3.0.0
     * @return array<int,int>
     */
    private static function get_non_business_weekdays() {
        /**
         * Filters the weekdays treated as non-working days.
         *
         * @since 3.0.0
         * @param array<int,int> $weekdays ISO weekday numbers (1 = Monday).
         */
        $weekdays = apply_filters( 'Hubgo/Shipping_Calculator/Non_Business_Days', array( 6, 7 ) );

        return array_map( 'intval', (array) $weekdays );
    }


    /**
     * Holidays excluded from the business-day walk.
     *
     * Empty by default: guessing a national holiday calendar and getting it
     * wrong shifts every promised date on the storefront, so the store owner
     * (or a locale plugin) supplies it.
     *
     * @since 3.0.0
     * @return array<int,string> Dates as Y-m-d.
     */
    private static function get_holidays() {
        /**
         * Filters the holidays skipped when computing a delivery date.
         *
         * @since 3.0.0
         * @param array<int,string> $holidays Dates as Y-m-d strings.
         */
        $holidays = apply_filters( 'Hubgo/Shipping_Calculator/Holidays', array() );

        return array_values( array_filter( array_map( 'strval', (array) $holidays ) ) );
    }


    /**
     * "3 dias úteis" label for a forecast.
     *
     * @since 3.0.0
     * @param int $days Business days.
     * @return string
     */
    private static function format_label( $days ) {
        if ( $days <= 0 ) {
            return __( 'Delivered today', 'hubgo' );
        }

        return sprintf(
            /* translators: %s: number of business days. */
            _n( '%s business day', '%s business days', $days, 'hubgo' ),
            number_format_i18n( $days )
        );
    }


    /**
     * "dia 14 de agosto" — the date fragment reused by every headline.
     *
     * @since 3.0.0
     * @param DateTimeImmutable $date Promised delivery date.
     * @return string
     */
    private static function format_date_label( DateTimeImmutable $date ) {
        return sprintf(
            /* translators: %s: localized day and month, e.g. "14 de agosto". */
            __( 'day %s', 'hubgo' ),
            wp_date( 'j \d\e F', $date->getTimestamp() )
        );
    }


    /**
     * "Receba até dia 14 de agosto" headline for a delivery date.
     *
     * @since 3.0.0
     * @param DateTimeImmutable $date Promised delivery date.
     * @return string
     */
    private static function format_headline( DateTimeImmutable $date ) {
        return sprintf(
            /* translators: %s: localized delivery date, e.g. "dia 14 de agosto". */
            __( 'Get it by %s', 'hubgo' ),
            self::format_date_label( $date )
        );
    }
}
