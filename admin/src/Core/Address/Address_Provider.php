<?php

namespace MeuMouse\Hubgo\Core\Address;

use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Base class for the address providers behind the storefront calculator.
 *
 * A provider answers three questions: which addresses match what the shopper
 * typed, which postcode belongs to the one they picked, and — the reverse
 * direction — which street a postcode sits on. The first two power the
 * "I do not know my postcode" finder; the third is what lets the calculator
 * name the destination instead of only echoing the digits back.
 *
 * Providers never run in the browser: whatever credential they need stays on
 * the server and the storefront only ever talks to `hubgo/v1/address/*`.
 *
 * Nothing here is registered by default. {@see Address_Service} resolves the
 * active provider through the `Hubgo/Core/Address/Provider` filter, and the
 * Google Maps integration is what fills it in — so the feature is off, and free,
 * until a store opts into it.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core\Address
 * @author MeuMouse.com
 */
abstract class Address_Provider {

    /**
     * Provider takes a single free-text query.
     *
     * The only mode the bundled storefront knows how to render. A provider that
     * needs the query broken into fields declares its own mode and ships the
     * form with it — the finder is hidden for modes it cannot draw, which is
     * safer than rendering a form the provider will reject.
     *
     * @since 3.0.0
     * @var string
     */
    const MODE_FREETEXT = 'freetext';

    /**
     * Outbound request timeout, in seconds.
     *
     * @since 3.0.0
     * @var int
     */
    const TIMEOUT = 8;

    /**
     * Timeout for lookups that run inside another request, in seconds.
     *
     * The postcode lookup happens while a shopper waits on a shipping quote, so
     * it gets a fraction of the budget: the street name is decoration, and no
     * decoration is worth holding a quote open for eight seconds.
     *
     * @since 3.0.0
     * @var int
     */
    const INLINE_TIMEOUT = 2;


    /**
     * Provider identifier.
     *
     * @since 3.0.0
     * @return string
     */
    abstract public function get_id();


    /**
     * How the storefront should collect the query.
     *
     * @since 3.0.0
     * @return string One of the MODE_* constants.
     */
    abstract public function get_mode();


    /**
     * Whether the provider holds everything it needs to answer.
     *
     * @since 3.0.0
     * @return bool
     */
    abstract public function is_configured();


    /**
     * Search addresses matching the shopper's input.
     *
     * @since 3.0.0
     * @param array $args Sanitized query parts: q, session.
     * @return array<int,array<string,string>>|WP_Error Suggestions or an error.
     */
    abstract public function search( $args );


    /**
     * Resolve the postcode of a previously suggested address.
     *
     * @since 3.0.0
     * @param array $args Sanitized args: id, session.
     * @return string|WP_Error Eight-digit postcode, or an error.
     */
    abstract public function resolve( $args );


    /**
     * Resolve the address a postcode belongs to.
     *
     * @since 3.0.0
     * @param string $postcode Eight-digit postcode.
     * @param string $country Destination country code.
     * @return array<string,string>|WP_Error Address parts, or an error.
     */
    abstract public function lookup_postcode( $postcode, $country );


    /**
     * Human-readable label for the provider.
     *
     * @since 3.0.0
     * @return string
     */
    public function get_label() {
        return $this->get_id();
    }


    /**
     * Build a normalized suggestion row.
     *
     * `postcode` is filled by providers that already know it at search time —
     * the storefront then skips the resolve round-trip entirely.
     *
     * @since 3.0.0
     * @param string $id Opaque identifier the provider can resolve later.
     * @param string $primary Main line (street, number).
     * @param string $secondary Supporting line (neighbourhood, city, state).
     * @param string $postcode Postcode, when already known.
     * @return array<string,string>
     */
    protected function suggestion( $id, $primary, $secondary = '', $postcode = '' ) {
        return array(
            'id'        => (string) $id,
            'primary'   => sanitize_text_field( (string) $primary ),
            'secondary' => sanitize_text_field( (string) $secondary ),
            'postcode'  => self::normalize_postcode( $postcode ),
        );
    }


    /**
     * Build a normalized address row.
     *
     * Every key is always present and always a string: the payload reaches Vue,
     * where a missing key and an empty one behave differently enough to produce
     * "undefined" on screen. `summary` is the single line the compact card
     * prints, and `formatted` the fuller one the options modal shows.
     *
     * @since 3.0.0
     * @param array $parts Raw parts: street, neighborhood, city, state.
     * @return array<string,string>
     */
    protected function address( $parts ) {
        $address = array(
            'street'       => sanitize_text_field( (string) ( $parts['street'] ?? '' ) ),
            'neighborhood' => sanitize_text_field( (string) ( $parts['neighborhood'] ?? '' ) ),
            'city'         => sanitize_text_field( (string) ( $parts['city'] ?? '' ) ),
            'state'        => sanitize_text_field( (string) ( $parts['state'] ?? '' ) ),
        );

        $address['summary'] = self::build_summary( $address );
        $address['formatted'] = self::build_formatted( $address );

        return $address;
    }


    /**
     * The shape callers get when nothing could be resolved.
     *
     * @since 3.0.0
     * @return array<string,string>
     */
    public static function get_empty_address() {
        return array(
            'street'       => '',
            'neighborhood' => '',
            'city'         => '',
            'state'        => '',
            'summary'      => '',
            'formatted'    => '',
        );
    }


    /**
     * Whether an address row carries anything worth showing.
     *
     * @since 3.0.0
     * @param array $address Address row.
     * @return bool
     */
    public static function has_address( $address ) {
        return is_array( $address ) && '' !== trim( (string) ( $address['summary'] ?? '' ) );
    }


    /**
     * Single line naming the destination.
     *
     * The street when there is one, the city otherwise. Brazilian postcodes come
     * in two flavours: street-level codes, which geocode to a `route`, and the
     * city-wide codes small towns use, which do not. Falling back to the city
     * keeps the second kind saying something true instead of nothing.
     *
     * @since 3.0.0
     * @param array $address Address parts.
     * @return string
     */
    private static function build_summary( $address ) {
        if ( '' !== $address['street'] ) {
            return $address['street'];
        }

        if ( '' !== $address['city'] ) {
            return ( '' !== $address['state'] )
                ? $address['city'] . ' - ' . $address['state']
                : $address['city'];
        }

        return '';
    }


    /**
     * Fuller description of the destination.
     *
     * @since 3.0.0
     * @param array $address Address parts.
     * @return string
     */
    private static function build_formatted( $address ) {
        $locality = $address['city'];

        if ( '' !== $locality && '' !== $address['state'] ) {
            $locality .= ' - ' . $address['state'];
        }

        if ( '' === $locality ) {
            $locality = $address['state'];
        }

        $parts = array_filter( array(
            $address['street'],
            $address['neighborhood'],
            $locality,
        ), function( $part ) {
            return '' !== (string) $part;
        } );

        return implode( ', ', $parts );
    }


    /**
     * Reduce a postcode to its eight digits, or an empty string.
     *
     * @since 3.0.0
     * @param string $postcode Raw postcode.
     * @return string
     */
    public static function normalize_postcode( $postcode ) {
        $digits = preg_replace( '/\D/', '', (string) $postcode );

        return ( 8 === strlen( (string) $digits ) ) ? $digits : '';
    }


    /**
     * Perform a GET request and decode its JSON body.
     *
     * @since 3.0.0
     * @param string $url Absolute URL.
     * @param array $headers Extra request headers.
     * @param int $timeout Request timeout in seconds.
     * @return array|WP_Error Decoded body, or an error.
     */
    protected function get_json( $url, $headers = array(), $timeout = self::TIMEOUT ) {
        $response = wp_remote_get( $url, array(
            'timeout' => (int) $timeout,
            'headers' => array_merge( array( 'Accept' => 'application/json' ), $headers ),
        ) );

        return $this->decode_response( $response );
    }


    /**
     * Perform a POST request with a JSON body and decode the JSON response.
     *
     * @since 3.0.0
     * @param string $url Absolute URL.
     * @param array $body Payload to encode.
     * @param array $headers Extra request headers.
     * @param int $timeout Request timeout in seconds.
     * @return array|WP_Error Decoded body, or an error.
     */
    protected function post_json( $url, $body, $headers = array(), $timeout = self::TIMEOUT ) {
        $response = wp_remote_post( $url, array(
            'timeout' => (int) $timeout,
            'headers' => array_merge( array(
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ), $headers ),
            'body'    => wp_json_encode( $body ),
        ) );

        return $this->decode_response( $response );
    }


    /**
     * Turn an HTTP response into a decoded array or a WP_Error.
     *
     * Upstream error messages are deliberately not forwarded to the storefront:
     * they can echo the API key back, and they are written for developers.
     *
     * @since 3.0.0
     * @param array|WP_Error $response Response from the HTTP API.
     * @return array|WP_Error
     */
    private function decode_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'hubgo_address_transport', __( 'Could not search addresses right now. Please try again.', 'hubgo' ) );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code > 299 ) {
            return new WP_Error( 'hubgo_address_http', __( 'Could not search addresses right now. Please try again.', 'hubgo' ) );
        }

        return is_array( $body ) ? $body : array();
    }
}
