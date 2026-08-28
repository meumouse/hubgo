<?php

namespace MeuMouse\Hubgo\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Resolve a destination state (UF) from a Brazilian postcode.
 *
 * WooCommerce matches shipping zones on country + state + postcode only
 * (see WC_Shipping_Zone_Data_Store::get_zone_id_from_package), and the first
 * zone by `zone_order` wins. A postcode can only *exclude* zones that declare
 * postcode rules — it can never stop a state zone from winning. So a package
 * carrying a state that does not belong to the informed postcode makes every
 * state zone a false candidate.
 *
 * The calculator only receives a postcode, so the state has to be derived from
 * it. The ranges below are the official Correios CEP bands, which break at
 * five-digit granularity and have been stable for decades.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Postcode_Locator {

    /**
     * Country this locator knows how to resolve.
     *
     * @since 3.0.0
     * @var string
     */
    const COUNTRY = 'BR';

    /**
     * Minimum number of digits required to resolve a state.
     *
     * Every range boundary falls on a five-digit prefix, so five digits are
     * enough to pick a band unambiguously.
     *
     * @since 3.0.0
     * @var int
     */
    const MIN_DIGITS = 5;


    /**
     * Get the CEP ranges mapped to their state (UF).
     *
     * Each entry is `array( <start>, <end>, <uf> )` with the bounds written as
     * zero-padded eight digit strings so they can be compared as strings.
     *
     * @since 3.0.0
     * @return array<int,array<int,string>>
     */
    public static function get_ranges() {
        $ranges = array(
            array( '01000000', '19999999', 'SP' ),
            array( '20000000', '28999999', 'RJ' ),
            array( '29000000', '29999999', 'ES' ),
            array( '30000000', '39999999', 'MG' ),
            array( '40000000', '48999999', 'BA' ),
            array( '49000000', '49999999', 'SE' ),
            array( '50000000', '56999999', 'PE' ),
            array( '57000000', '57999999', 'AL' ),
            array( '58000000', '58999999', 'PB' ),
            array( '59000000', '59999999', 'RN' ),
            array( '60000000', '63999999', 'CE' ),
            array( '64000000', '64999999', 'PI' ),
            array( '65000000', '65999999', 'MA' ),
            array( '66000000', '68899999', 'PA' ),
            array( '68900000', '68999999', 'AP' ),
            array( '69000000', '69299999', 'AM' ),
            array( '69300000', '69389999', 'RR' ),
            array( '69390000', '69399999', 'AM' ),
            array( '69400000', '69899999', 'AM' ),
            array( '69900000', '69999999', 'AC' ),
            array( '70000000', '72799999', 'DF' ),
            array( '72800000', '72999999', 'GO' ),
            array( '73000000', '73699999', 'DF' ),
            array( '73700000', '76799999', 'GO' ),
            array( '76800000', '76999999', 'RO' ),
            array( '77000000', '77999999', 'TO' ),
            array( '78000000', '78899999', 'MT' ),
            array( '78900000', '78999999', 'RO' ),
            array( '79000000', '79999999', 'MS' ),
            array( '80000000', '87999999', 'PR' ),
            array( '88000000', '89999999', 'SC' ),
            array( '90000000', '99999999', 'RS' ),
        );

        /**
         * Filters the CEP -> UF range table.
         *
         * @since 3.0.0
         * @param array $ranges List of `array( start, end, uf )` entries.
         */
        return apply_filters( 'Hubgo/Shipping_Calculator/Postcode_State_Map', $ranges );
    }


    /**
     * Resolve the state (UF) a postcode belongs to.
     *
     * Returns an empty string whenever the state cannot be determined. That is
     * deliberate and safer than guessing: with an empty state the `BR:` state
     * criterion never matches, so state zones simply drop out of the dispute
     * instead of hijacking a package that belongs to another region.
     *
     * @since 3.0.0
     * @param string $postcode Raw or formatted postcode.
     * @param string $country Destination country code.
     * @return string Two letter state code, or an empty string.
     */
    public static function get_state( $postcode, $country = self::COUNTRY ) {
        $country = strtoupper( (string) $country );
        $state   = '';

        if ( self::COUNTRY === $country ) {
            $digits = preg_replace( '/\D/', '', (string) $postcode );

            if ( strlen( $digits ) >= self::MIN_DIGITS ) {
                // Pad partial postcodes to the start of their band. Safe because
                // every boundary sits on a five-digit prefix.
                $compare = str_pad( substr( $digits, 0, 8 ), 8, '0', STR_PAD_RIGHT );

                foreach ( self::get_ranges() as $range ) {
                    if ( ! isset( $range[0], $range[1], $range[2] ) ) {
                        continue;
                    }

                    if ( $compare >= $range[0] && $compare <= $range[1] ) {
                        $state = $range[2];
                        break;
                    }
                }
            }
        }

        /**
         * Filters the state resolved for a postcode.
         *
         * Useful to plug an address lookup service (ViaCEP, BrasilAPI) or to
         * support countries this locator does not know about.
         *
         * @since 3.0.0
         * @param string $state Resolved state code, empty when unknown.
         * @param string $postcode Postcode being resolved.
         * @param string $country Destination country code.
         */
        return apply_filters( 'Hubgo/Shipping_Calculator/Resolved_State', $state, $postcode, $country );
    }
}
